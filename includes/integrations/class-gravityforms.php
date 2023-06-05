<?php
/**
 * Integrations: Gravity Forms
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Implements an integration for Gravity Forms.
 *
 * @since 1.2
 *
 * @see Affiliate_WP_Base
 */
class Affiliate_WP_Gravity_Forms extends Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context = 'gravityforms';

	/**
	 * Register hooks for this integration
	 *
	 * @access public
	 */
	public function init() {

		// Gravity Forms hooks
		add_filter( 'gform_entry_created', array( $this, 'add_pending_referral' ), 10, 2 );
		add_action( 'gform_post_payment_completed', array( $this, 'mark_referral_complete' ), 10, 2 );
		add_action( 'gform_post_payment_refunded', array( $this, 'revoke_referral_on_refund' ), 10, 2 );

		// Internal hooks
		add_filter( 'affwp_referral_reference_column', array( $this, 'reference_link' ), 10, 2 );

		// Form settings
		add_filter( 'gform_form_settings', array( $this, 'add_settings' ), 10, 2 );
		add_filter( 'gform_pre_form_settings_save', array( $this, 'save_settings' ) );

		// Coupon settings
		add_filter( 'gform_gravityformscoupons_feed_settings_fields', array( $this, 'coupon_settings' ), 10, 2 );
		add_filter( 'admin_footer', array( $this, 'coupon_scripts' ) );
	}

	/**
	 * Add pending referral
	 *
	 * @access public
	 * @uses GFFormsModel::get_lead()
	 * @uses GFCommon::get_product_fields()
	 * @uses GFCommon::to_number()
	 *
	 * @param array $entry GF entry.
	 * @param array $form  GF form.
	 */
	public function add_pending_referral( $entry, $form ) {
		// Block referral if form does not allow them.
		if ( ! rgar( $form, 'affwp_allow_referrals' ) ) {
			return;
		}

		$reference    = $entry['id'];
		$affiliate_id = $this->get_affiliate_id( $reference );

		// Check if an affiliate coupon was included.
		$is_coupon_referral = $this->check_coupons( $form, $entry );

		// Block referral if not referred or affiliate ID is empty.
		if ( ! $this->was_referred() && empty( $affiliate_id ) ) {
			return; // Referral not created because affiliate not referred and not a coupon.
		}

		// create draft referral.
		$desc        = isset( $form['title'] ) ? $form['title'] : '';
		$referral_id = $this->insert_draft_referral(
			$affiliate_id,
			array(
				'reference'          => $reference,
				'description'        => $desc,
				'is_coupon_referral' => $is_coupon_referral,
			)
		);
		if ( ! $referral_id ) {
			$this->log( 'Draft referral creation failed.' );
			return;
		}

		// Get the referral type we are creating.
		$type = rgar( $form, 'affwp_referral_type' );
		$type = empty( $type ) ? 'sale' : $type;

		$this->referral_type = $type;

		// Get all emails from submitted form.
		$emails = $this->get_emails( $entry, $form );

		// Block referral if any of the affiliate's emails have been submitted.
		if ( $emails ) {
			foreach ( $emails as $customer_email ) {
				if ( $this->is_affiliate_email( $customer_email, $this->affiliate_id ) ) {
					$this->log( 'Draft referral rejected because affiliate\'s own account was used.' );
					$this->mark_referral_failed( $referral_id );

					return false;
				}
			}
		}

		// Do some craziness to determine the price (this should be easy but is not).

		$desc     = isset( $form['title'] ) ? $form['title'] : '';
		$entry    = GFFormsModel::get_lead( $entry['id'] );
		$products = GFCommon::get_product_fields( $form, $entry );
		$total    = 0;

		foreach ( $products['products'] as $key => $product ) {

			$price = GFCommon::to_number( $product['price'] );

			if ( is_array( rgar( $product,'options' ) ) ) {
				$count = count( $product['options'] );
				$index = 1;

				foreach ( $product['options'] as $option ) {
					$price += GFCommon::to_number( $option['price'] );
				}
			}

			$subtotal = floatval( $product['quantity'] ) * $price;

			$total += $subtotal;

		}

		// replace description if there are products.
		if ( ! empty( $products['products'] ) ) {
			$product_names = wp_list_pluck( $products['products'], 'name' );
			$desc          = implode( ', ', $product_names );
		}

		$total += floatval( $products['shipping']['price'] );

		$referral_total = $this->calculate_referral_amount( $total, $entry['id'] );

		$this->hydrate_referral(
			$referral_id,
			array(
				'status'      => 'pending',
				'amount'      => $referral_total,
				'description' => $desc,
			)
		);

		$this->log( sprintf( 'Referral #%d updated successfully.', $referral_id ) );

		if ( empty( $total ) ) {
			$this->mark_referral_complete( $entry, array() );
		}
	}

	/**
	 * Mark referral as complete
	 *
	 * @access public
	 * @uses GFFormsModel::add_note()
	 *
	 * @param array $entry
	 * @param array $action
	 */
	public function mark_referral_complete( $entry, $action ) {

		$this->complete_referral( $entry['id'] );

		$referral = affwp_get_referral_by( 'reference', $entry['id'], $this->context );

		if ( ! is_wp_error( $referral ) ) {
			$amount = affwp_currency_filter( affwp_format_amount( $referral->amount ) );
			$name   = affiliate_wp()->affiliates->get_affiliate_name( $referral->affiliate_id );
			/* translators: 1: Referral ID, 2: Formatted referral amount, 3: Affiliate name, 4: Referral affiliate ID  */
			$note   = sprintf( __( 'Referral #%1$d for %2$s recorded for %3$s (ID: %4$d).', 'affiliate-wp' ),
				$referral->referral_id,
				$amount,
				$name,
				$referral->affiliate_id
			);

			GFFormsModel::add_note( $entry["id"], 0, 'AffiliateWP', $note );
		} else {
			affiliate_wp()->utils->log( 'mark_referral_complete: The referral could not be found.', $referral );
		}

	}

	/**
	 * Revoke referral on refund
	 *
	 * @access public
	 * @uses GFFormsModel::add_note()
	 *
	 * @param array $entry
	 * @param array $action
	 */
	public function revoke_referral_on_refund( $entry, $action ) {

		$this->reject_referral( $entry['id'] );

		$referral = affwp_get_referral_by( 'reference', $entry['id'], $this->context );

		if ( ! is_wp_error( $referral ) ) {
			$amount = affwp_currency_filter( affwp_format_amount( $referral->amount ) );
			$name   = affiliate_wp()->affiliates->get_affiliate_name( $referral->affiliate_id );
			$note   = sprintf( __( 'Referral #%d for %s for %s rejected', 'affiliate-wp' ), $referral->referral_id, $amount, $name );

			GFFormsModel::add_note( $entry["id"], 0, 'AffiliateWP', $note );
		} else {
			affiliate_wp()->utils->log( 'revoke_referral_on_refund: The referral could not be found.', $referral );
		}

	}

	/**
	 * Sets up the reference link in the Referrals table
	 *
	 * @access public
	 * @uses GFFormsModel::get_lead()
	 *
	 * @param  int    $reference
	 * @param  object $referral
	 * @return string
	 */
	public function reference_link( $reference = 0, $referral ) {

		if ( empty( $referral->context ) || 'gravityforms' != $referral->context ) {
			return $reference;
		}

		$entry = GFFormsModel::get_lead( $reference );

		$url = admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $entry['form_id'] . '&lid=' . $reference );

		return '<a href="' . esc_url( $url ) . '">' . $reference . '</a>';
	}

	/**
	 * Checks for submitted coupons and sets affiliate ID to the associated affiliate, if any.
	 *
	 * @since      1.9
	 * @deprecated 2.2.16 Use check_coupons method instead.
	 *
	 * @param array $form  The Gravity Forms form to check against.
	 * @param array $entry The Gravity Forms entry to check against.
	 * @return bool|void
	 */
	public function maybe_check_coupons( $form, $entry ) {
		_deprecated_function( __METHOD__, '2.2.16', 'Affiliate_WP_Gravity_Forms::check_coupons' );

		$check_coupons = $this->check_coupons( $form, $entry );

		if ( $check_coupons === false ) {
			return;
		}

		return $check_coupons;
	}

	/**
	 * Checks for submitted coupons and sets affiliate ID to the associated affiliate, if any.
	 *
	 * @since 2.2.16
	 *
	 * @uses GFCoupons::get_submitted_coupon_codes()
	 * @uses GFCoupons::get_coupon_field()
	 * @uses GFCoupons::get_config()
	 *
	 * @param array $form  The Gravity Forms form to check against.
	 * @param array $entry The Gravity Forms entry to check against.
	 * @return bool
	 */
	public function check_coupons( $form, $entry ) {

		if( ! class_exists( 'GFCoupons' ) ) {
			return false;
		}

		$gf_coupons   = new \GFCoupons;
		$coupons      = $gf_coupons->get_submitted_coupon_codes( $form, $entry );
		$coupon_field = $gf_coupons->get_coupon_field( $form );
		$has_coupon   = false;

		if( empty( $coupons ) ) {
			return false;
		}

		if ( ! is_object( $coupon_field ) ) {
			return false;
		}

		foreach( $coupons as $coupon ) {

			// Forms can have multiple coupons. If there are multiple affiliate coupons, the last one in the list will be used.

			$config = $gf_coupons->get_config( $form, $coupon );

			if( empty( $config['meta']['affwp_affiliate'] ) ) {
				continue;
			}

			$has_coupon = true;
			$username  = $config['meta']['affwp_affiliate'];
			$affiliate = affwp_get_affiliate( $username );

			if( $affiliate && affiliate_wp()->tracking->is_valid_affiliate( $affiliate->ID ) ) {

				$this->affiliate_id = $affiliate->ID;

			}

		}

		return $has_coupon;
	}

	/**
	 * Get all emails from form
	 *
	 * @since 2.0
	 * @access public
	 * @return array $emails all emails submitted via email fields
	 */
	public function get_emails( $entry, $form ) {

		$email_fields = GFCommon::get_email_fields( $form );

		$emails = array();

		if ( $email_fields ) {
			foreach ( $email_fields as $email_field ) {
				if ( ! empty( $entry[ $email_field->id ] ) ) {
					$emails[] = $entry[ $email_field->id ];
				}
			}
		}

		return $emails;

	}

	/**
	 * Get all names from form.
	 *
	 * @since 2.4.2
	 * @access public
	 *
	 * @param array $entry The Gravity Forms entry.
	 * @param array $form  The Gravity Forms form.
	 * @return array $names all names submitted via names fields.
	 */
	public function get_names( $entry, $form ) {

		$names = array();

		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'name' || $field->inputType == 'name' ) {
				$names[] = array(
					'first_name' => rgar( $entry, $field->id . '.3' ),
					'last_name'  => rgar( $entry, $field->id . '.6' ),
				);
			}
		}

		return $names;
	}

	/**
	 * Register the form-specific settings
	 *
	 * @since  1.7
	 * @return void
	 */
	public function add_settings( $settings, $form ) {

		$checked  = rgar( $form, 'affwp_allow_referrals' );
		$selected = rgar( $form, 'affwp_referral_type' );

		$field  = '<input type="checkbox" id="affwp_allow_referrals" name="affwp_allow_referrals" value="1" ' . checked( 1, $checked, false ) . ' />';
		$field .= ' <label for="affwp_allow_referrals">' . __( 'Enable affiliate referral creation for this form', 'affiliate-wp' ) . '</label>';

		$field_type = '<select name="affwp_referral_type" id="affwp_referral_type">';
			foreach( affwp_get_referral_types() as $type_id => $type ) {
				$field_type .= '<option value="' . esc_attr( $type_id ) . '"' . selected( $type_id, $selected, false ) .'>' . esc_html( $type['label'] ) . '</option>';
			}
		$field_type .= '</select>';
		$field_type .= ' <label for="affwp_referral_type">' . __( 'Referral Type', 'affiliate-wp' ) . '</label>';

		$settings['Form Options']['affwp_allow_referrals'] = '
			<tr>
				<th>' . __( 'Allow referrals', 'affiliate-wp' ) . '</th>
				<td>' .
					'<p>' . $field . '</p>' . 
					'<p>' . $field_type . '</p>' . 
				'</td>
			</tr>';

		return $settings;

	}

	/**
	 * Save form settings
	 *
	 * @since 1.7
	 */
	public function save_settings( $form ) {

		$form['affwp_allow_referrals'] = rgpost( 'affwp_allow_referrals' );
		$form['affwp_referral_type'] = rgpost( 'affwp_referral_type' );

		return $form;

	}


	/**
	 * Add settings to Coupon edit screens
	 *
	 * @since 1.9
	 */
	public function coupon_settings( $settings, $addon ) {

		$settings[2]['fields'][] = array(
			'name'  => 'affwp_affiliate',
			'label' => __( 'Affiliate Coupon', 'affiliate-wp' ),
			'type'  => 'text',
			'class' => 'affwp_gf_coupon',
			'tooltip' => __( 'To connect this coupon to an affiliate, enter the username of the affiliate. Anytime this coupon is redeemed, the connected affiliate will receive a referral commission.', 'affiliate-wp' )
		);

		return $settings;

	}

	/**
	 * Add inline scripts to Coupon edit screen
	 *
	 * @since 1.9
	 */
	public function coupon_scripts() {

		if( empty( $_GET['page'] ) || 'gravityformscoupons' !== $_GET['page'] ) {
			return;
		}

		if( empty( $_GET['fid'] ) ) {
			return;
		}
?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Ajax user search.
			$( '.affwp_gf_coupon' ).each( function() {
				var	$this    = $( this ),
					$action  = 'affwp_search_users',
					$search  = $this.val();

				$this.autocomplete( {
					source: ajaxurl + '?action=' + $action + '&term=' + $search,
					delay: 500,
					minLength: 2,
					position: { offset: '0, -1' },
					select: function( event, data ) {
						$this.val( data.item.user_id );
					},
					open: function() {
						$this.addClass( 'open' );
					},
					close: function() {
						$this.removeClass( 'open' );
					}
				} );

				// Unset the input if the input is cleared.
				$this.on( 'keyup', function() {
					if ( ! this.value ) {
						$this.val( '' );
					}
				} );
			} );
		});
		</script>
<?php
	}

	/**
	 * Retrieves the customer details for a form submission
	 *
	 * @since 2.2
	 *
	 * @param int $entry_id The ID of the entry to retrieve customer details for.
	 * @return array An array of the customer details
	*/
	public function get_customer( $entry_id = 0 ) {

		$customer = array();

		if ( class_exists( 'GFCommon' ) ) {

			$entry  = GFFormsModel::get_lead( $entry_id );
			$form   = GFAPI::get_form( $entry['form_id'] );
			$emails = $this->get_emails( $entry, $form );
			$names  = $this->get_names( $entry, $form );

			$customer = array(
				'first_name' => isset( current( $names )['first_name'] ) ? current( $names )['first_name'] : '',
				'last_name'  => isset( current( $names )['last_name'] ) ? current( $names )['last_name'] : '',
				'email'      => current( $emails ),
				'ip'         => affiliate_wp()->tracking->get_ip(),
			);

		}

		return $customer;
	}

	/**
	 * Runs the check necessary to confirm this plugin is active.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	function plugin_is_active() {
		return class_exists( 'GFCommon' );
	}
}

	new Affiliate_WP_Gravity_Forms;