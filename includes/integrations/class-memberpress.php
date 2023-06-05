<?php
/**
 * Integrations: MemberPress
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Implements an integration for MemberPress.
 *
 * @since 1.2
 *
 * @see Affiliate_WP_Base
 */
class Affiliate_WP_MemberPress extends Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context = 'memberpress';

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.5
	*/
	public function init() {

		if( ! defined( 'MEPR_VERSION' ) ) {
			return;
		}

		add_action( 'mepr-txn-status-pending', array( $this, 'add_pending_referral' ), 10 );
		add_action( 'mepr-txn-status-complete', array( $this, 'mark_referral_complete' ), 10 );
		add_action( 'mepr-txn-status-confirmed', array( $this, 'mark_referral_complete' ), 10 );
		add_action( 'mepr-txn-status-refunded', array( $this, 'revoke_referral_on_refund' ), 10 );

		add_filter( 'affwp_referral_reference_column', array( $this, 'reference_link' ), 10, 2 );

		// Per membership referral rates
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );

		// Coupon support
		add_action( 'add_meta_boxes', array( $this, 'add_coupon_meta_box' ) );
		add_action( 'save_post', array( $this, 'store_discount_affiliate' ), 1, 2 );
	}

	/**
	 * Store a pending referral when a one-time product is purchased
	 *
	 * @access  public
	 * @since   1.5
	 *
	 * @param MeprTransaction $txn Transaction.
	 */
	public function add_pending_referral( $txn ) {

		// Check if an affiliate coupon was used.
		$affiliate_id = $this->get_coupon_affiliate_id( $txn );
		if ( false !== $affiliate_id ) {
			$this->affiliate_id = $affiliate_id;
		}

		// Check if referred or coupon.
		if ( ! $this->was_referred() && ! $affiliate_id ) {
			return; // Referral not created because affiliate not referred and not a coupon.
		}

		// Check for an existing referral.
		$existing = affwp_get_referral_by( 'reference', $txn->id, $this->context );
		if ( ! is_wp_error( $existing ) ) {  // Payment enters here again.
			return;
		}

		// Get user email.
		$user        = get_userdata( $txn->user_id );
		$this->email = ! empty( $user->user_email ) ? $user->user_email : '';

		// Create draft referral.
		$referral_id = $this->insert_draft_referral(
			$this->affiliate_id,
			array(
				'reference'   => $txn->id,
				'description' => get_the_title( $txn->product_id ),
			)
		);
		if ( ! $referral_id ) {
			$this->log( 'Draft referral creation failed.' );
			return;
		}

		// Customers cannot refer themselves.
		if ( ! empty( $user->user_email ) && $this->is_affiliate_email( $user->user_email ) ) {
			$this->log( 'Draft referral rejected because affiliate\'s own account was used.' );
			$this->mark_referral_failed( $referral_id );
			return;
		}

		// Check if referrals are disabled for this membership.
		if ( get_post_meta( $txn->product_id, '_affwp_' . $this->context . '_referrals_disabled', true ) ) {
			$this->log( 'Draft referral rejected because referrals are disabled on this membership.' );
			$this->mark_referral_failed( $referral_id );
			return;
		}

		// Set the base amount from the transaction at the top of the stack.
		$amount = $txn->amount;
		// If there's a subscription and the subscription has a trial, override $amount.
		if ( $txn->subscription() && $txn->subscription()->trial ) {
			$amount = $txn->subscription()->trial_amount;
		}

		// get referral total.
		$referral_total = $this->calculate_referral_amount( $amount, $txn->id, $txn->product_id );

		// Hydrates the previously created referral.
		$this->hydrate_referral(
			$referral_id,
			array(
				'status'             => 'pending',
				'amount'             => $referral_total,
				'is_coupon_referral' => false !== $this->get_coupon_affiliate_id( $txn ),
				'custom'             => array(
					'subscription_id' => $txn->subscription_id,
				),
			)
		);

		$this->log( sprintf( 'Memberpress referral #%d updated to pending successfully.', $referral_id ) );
	}

	/**
	 * Update a referral to Unpaid when a one-time purchase is completed
	 *
	 * @access  public
	 * @since   1.5
	*/
	public function mark_referral_complete( $txn ) {

		// Completes a referral for a one-time purchase
		$this->complete_referral( $txn->id );
	}

	/**
	 * Reject referrals when the transaction is refunded
	 *
	 * @access  public
	 * @since   1.5
	*/
	public function revoke_referral_on_refund( $txn ) {

		if( ! affiliate_wp()->settings->get( 'revoke_on_refund' ) ) {
			return;
		}

		$this->reject_referral( $txn->id );

	}

	/**
	 * Setup the reference link
	 *
	 * @access  public
	 * @since   1.5
	*/
	public function reference_link( $reference = 0, $referral ) {

		if( empty( $referral->context ) || 'memberpress' != $referral->context ) {

			return $reference;

		}

		$url = admin_url( 'admin.php?page=memberpress-trans&action=edit&id=' . $reference );

		return '<a href="' . esc_url( $url ) . '">' . $reference . '</a>';
	}

	/**
	 * Register the metabox for membership rates
	 *
	 * @access  public
	 * @since   1.7
	*/
	public function register_metabox() {

		add_meta_box( 'affwp_level_rate', __( 'Affiliate Rate', 'affiliate-wp' ),  array( $this, 'render_metabox' ), 'memberpressproduct', 'side', 'low' );

	}

	/**
	 * Render the affiliate rates metabox
	 *
	 * @access  public
	 * @since   1.7
	*/
	public function render_metabox() {

		global $post;

		$product_id = ! empty( $post ) ? $post->ID : 0;

		$rate       = get_post_meta( $product_id, '_affwp_' . $this->context . '_product_rate', true );
		$disabled   = get_post_meta( $product_id, '_affwp_' . $this->context . '_referrals_disabled', true );
?>
		<p>
			<label for="affwp_product_rate">
				<input type="text" name="_affwp_<?php echo $this->context; ?>_product_rate" id="affwp_product_rate" class="small-text" value="<?php echo esc_attr( $rate ); ?>" />
				<?php _e( 'Referral Rate', 'affiliate-wp' ); ?>
			</label>
		</p>

		<p>
			<label for="affwp_disable_referrals">
				<input type="checkbox" name="_affwp_<?php echo $this->context; ?>_referrals_disabled" id="affwp_disable_referrals" value="1"<?php checked( $disabled, true ); ?> />
				<?php _e( 'Disable referrals on this membership', 'affiliate-wp' ); ?>
			</label>
		</p>

		<p><?php _e( 'These settings will be used to calculate affiliate earnings per-sale. Leave blank to use the site default referral rate.', 'affiliate-wp' ); ?></p>
<?php
	}

	/**
	 * Saves per-product referral rate settings input fields
	 *
	 * @access  public
	 * @since   1.7
	*/
	public function save_meta( $post_id = 0 ) {

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Don't save revisions and autosaves
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return $post_id;
		}

		$post = get_post( $post_id );

		if( ! $post ) {
			return $post_id;
		}

		// Check post type is product
		if ( 'memberpressproduct' != $post->post_type ) {
			return $post_id;
		}

		// Check user permission
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		if( ! empty( $_POST['_affwp_' . $this->context . '_product_rate'] ) ) {

			$rate = sanitize_text_field( $_POST['_affwp_' . $this->context . '_product_rate'] );
			update_post_meta( $post_id, '_affwp_' . $this->context . '_product_rate', $rate );

		} else {

			delete_post_meta( $post_id, '_affwp_' . $this->context . '_product_rate' );

		}

		if( isset( $_POST['_affwp_' . $this->context . '_referrals_disabled'] ) ) {

			update_post_meta( $post_id, '_affwp_' . $this->context . '_referrals_disabled', 1 );

		} else {

			delete_post_meta( $post_id, '_affwp_' . $this->context . '_referrals_disabled' );

		}

	}


	/**
	 * Register coupon meta box
	 *
	 * @access public
	 * @since   1.7.5
	 */
	public function add_coupon_meta_box() {
		add_meta_box( 'memberpress-coupon-affiliate-data', __( 'Affiliate', 'affiliate-wp' ), array( $this, 'display_coupon_meta_box' ), MeprCoupon::$cpt, 'side', 'default' );
	}


	/**
	 * Display coupon meta box
	 *
	 * @access public
	 * @since   1.7.5
	 */
	public function display_coupon_meta_box() {
		global $post;

		add_filter( 'affwp_is_admin_page', '__return_true' );
		affwp_admin_scripts();

		$user_id      = 0;
		$user_name    = '';
		$affiliate_id = get_post_meta( $post->ID, 'affwp_discount_affiliate', true );
		if( $affiliate_id ) {
			$user_id      = affwp_get_affiliate_user_id( $affiliate_id );
			$user         = get_userdata( $user_id );
			$user_name    = $user ? $user->user_login : '';
		}
		?>
		<p class="form-field affwp-memberpress-coupon-field">
			<label for="user_name"><?php _e( 'If you would like to connect this discount to an affiliate, enter the name of the affiliate it belongs to.', 'affiliate-wp' ); ?></label>
			<span class="affwp-ajax-search-wrap">
				<span class="affwp-memberpress-coupon-input-wrap">
					<input type="text" name="user_name" id="user_name" value="<?php echo esc_attr( $user_name ); ?>" class="affwp-user-search" data-affwp-status="active" autocomplete="off" />
				</span>
			</span>
		</p>
		<?php
	}


	/**
	 * Save coupon meta
	 *
	 * @access public
	 * @since   1.7.5
	 */
	public function store_discount_affiliate( $post_id, $post ) {

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Don't save revisions and autosaves
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return $post_id;
		}

		if( ! is_admin() ) {
			return $post_id;
		}

		$post = get_post( $post_id );

		if( ! $post ) {
			return $post_id;
		}

		// Check post type is coupon
		if ( 'memberpresscoupon' != $post->post_type ) {
			return $post_id;
		}

		// Check user permission
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}


		if( empty( $_POST['user_name'] ) ) {
			delete_post_meta( $post_id, 'affwp_discount_affiliate' );
			return;
		}

		if( empty( $_POST['user_id'] ) && empty( $_POST['user_name'] ) ) {
			return;
		}

		$data = affiliate_wp()->utils->process_request_data( $_POST, 'user_name' );

		$affiliate_id = affwp_get_affiliate_id( $data['user_id'] );
		update_post_meta( $post_id, 'affwp_discount_affiliate', $affiliate_id );
	}

	/**
	 * Retrieves coupons of a given type.
	 *
	 * @since 2.6
	 * @since 2.8 Added integration type to details array.
	 *
	 * @param string               $type         Coupon type.
	 * @param int|\AffWP\Affiliate $affiliate    Optional. Affiliate ID or object to retrieve coupons for.
	 *                                           Default null (ignored).
	 * @param bool                 $details_only Optional. Whether to retrieve the coupon details only (for display).
	 *                                           Default true. If false, the full coupon objects will be retrieved.
	 * @return array|\AffWP\Affiliate\Coupon[]|\WP_Post[] An array of arrays of coupon details if `$details_only` is
	 *                                                    true or an array of coupon or post objects if false, depending
	 *                                                    on whether dynamic or manual coupons, otherwise an empty array.
	 */
	public function get_coupons_of_type( $type, $affiliate = null, $details_only = true ) {
		if ( ! $this->is_active() ) {
			return array();
		}

		$affiliate = affwp_get_affiliate( $affiliate );
		$coupons   = array();

		switch ( $type ) {
			case 'manual':
				$ids = $this->get_coupon_post_ids( 'memberpresscoupon', 'publish', $affiliate );

				if ( ! empty( $ids ) ) {
					foreach ( $ids as $id ) {
						if ( true === $details_only ) {
							$amount        = get_post_meta( $id, '_mepr_coupons_discount_amount', true );
							$discount_type = get_post_meta( $id, '_mepr_coupons_discount_type', true );

							if ( 'percent' === $discount_type ) {
								$amount = affwp_format_percentage( $amount );
							} else {
								$mepr_options  = MeprOptions::fetch();
								$currency_code = $mepr_options->currency_code;
								$amount        = $currency_code . affwp_format_amount( $amount );
							}

							$coupons[ $id ]['code']        = get_the_title( $id );
							$coupons[ $id ]['amount']      = esc_html( $amount );
							$coupons[ $id ]['integration'] = $this->context;
						} else {
							$coupons[ $id ] = get_post( $id );
						}
					}
				}
				break;

			default:
				$coupons = array();
				break;
		}

		return $coupons;
	}

	/**
	 * Retrieve the affiliate ID for the coupon used, if any
	 *
	 * @access  public
	 * @since   1.7.5
	 *
	 * @param MeprTransaction $txn Transaction.
	*/
	private function get_coupon_affiliate_id( $txn ) {
		if( ! $coupon = $txn->coupon() ) {
			return false;
		}

		$affiliate_id = get_post_meta( $coupon->ID, 'affwp_discount_affiliate', true );

		if ( $affiliate_id ) {
			if ( ! affiliate_wp()->tracking->is_valid_affiliate( $affiliate_id ) ) {
				return false;
			}

			return $affiliate_id;
		}

		return false;
	}

	/**
	 * Runs the check necessary to confirm this plugin is active.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	function plugin_is_active() {
		return class_exists( 'MeprAppCtrl' );
	}
}

	new Affiliate_WP_MemberPress;