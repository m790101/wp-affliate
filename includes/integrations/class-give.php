<?php
/**
 * Integrations: Give
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Implements an integration for Give.
 *
 * @since 1.2
 *
 * @see Affiliate_WP_Base
 */
class Affiliate_WP_Give extends Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context = 'give';

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function init() {

		$this->context = 'give';

		add_action( 'give_insert_payment', array( $this, 'add_pending_referral' ), 99999, 2 );

		add_action( 'give_complete_form_donation', array( $this, 'mark_referral_complete' ), 10, 3 );
		add_action( 'give_complete_form_donation', array( $this, 'insert_payment_note' ), 10, 3 );

		add_action( 'give_update_payment_status', array( $this, 'revoke_referral_on_refund' ), 10, 3 );
		add_action( 'give_payment_delete', array( $this, 'revoke_referral_on_delete' ), 10 );

		add_filter( 'affwp_referral_reference_column', array( $this, 'reference_link' ), 10, 2 );

		// Per donation form referral rates
		add_filter( 'give_metabox_form_data_settings', array( $this, 'donation_settings' ), 99 );
	}

	/**
	 * Records a pending referral when a pending payment is created
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function add_pending_referral( $payment_id = 0, $payment_data = array() ) {

		// Check if referred.
		if ( ! $this->was_referred() ) {
			return false;
		}

		// Get Affiliate ID.
		$affiliate_id = $this->get_affiliate_id( $payment_id );

		// Get customer email.
		$customer_email = give_get_payment_user_email( $payment_id );
		$this->email    = $customer_email;

		// Get referral description.
		$desc = $this->get_referral_description( $payment_id );

		// Create draft referral.
		$referral_id = $this->insert_draft_referral(
			$this->affiliate_id,
			array(
				'reference'   => $payment_id,
				'description' => $desc,
			)
		);
		if ( ! $referral_id ) {
			$this->log( 'Draft referral creation failed.' );
			return;
		}

		// Block referral if donation form does not allow it.
		if ( ! get_post_meta( $payment_data['give_form_id'], '_affwp_give_allow_referrals', true ) ) {
			$this->log( 'Draft referral rejected because donation form does not allow it.' );
			$this->mark_referral_failed( $referral_id );
			return false;
		}

		// Customers cannot refer themselves.
		if ( $this->is_affiliate_email( $customer_email, $affiliate_id ) ) {
			$this->log( 'Referral not created because affiliate\'s own account was used.' );
			$this->mark_referral_failed( $referral_id );
			return false;
		}

		// Check if it has description.
		if ( empty( $desc ) ) {
			$this->log( 'Referral not created due to empty description.' );
			$this->mark_referral_failed( $referral_id );
			return;
		}

		// Get referral total.
		$referral_total = $this->get_referral_total( $payment_id, $affiliate_id );

		// Hydrates the previously created referral.
		$this->hydrate_referral(
			$referral_id,
			array(
				'status'             => 'pending',
				'amount'             => $referral_total,
			)
		);
		$this->log( sprintf( 'Give referral #%d updated to pending successfully.', $referral_id ) );
	}

	/**
	 * Get the referral total
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function get_referral_total( $payment_id = 0, $affiliate_id = 0 ) {

		$form_id = get_post_meta( $payment_id, '_give_payment_form_id', true );

		$payment_amount = give_get_payment_amount( $payment_id );
		$referral_total = $this->calculate_referral_amount( $payment_amount, $payment_id, $form_id, $affiliate_id );

		return $referral_total;

	}

	/**
	 * Get the referral description
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function get_referral_description( $payment_id = 0 ) {

		$payment_meta = give_get_payment_meta( $payment_id );

		$form_id    = isset( $payment_meta['form_id'] ) ? $payment_meta['form_id'] : 0;
		$price_id   = isset( $payment_meta['price_id'] ) ? $payment_meta['price_id'] : null;

		$referral_description = isset( $payment_meta['form_title'] ) ? $payment_meta['form_title'] : '';

		$separator  = '';

		// If multi-level, append to the form title.
		if ( give_has_variable_prices( $form_id ) ) {

			// Only add separator if there is a form title.
			if ( ! empty( $referral_description ) ) {
				$referral_description .= ' ' . $separator . ' ';
			}

			if ( $price_id == 'custom' ) {

				$custom_amount_text = get_post_meta( $form_id, '_give_custom_amount_text', true );
				$referral_description .= ! empty( $custom_amount_text ) ? $custom_amount_text : __( 'Custom Amount', 'affiliate-wp' );

			} else {

				$referral_description .= give_get_price_option_name( $form_id, $price_id );

			}

		}

		return $referral_description;

	}

	/**
	 * Sets a referral to unpaid when payment is completed
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function mark_referral_complete( $form_id, $payment_id = 0, $payment_meta ) {
		$this->complete_referral( $payment_id );
	}

	/**
	 * Insert payment note
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function insert_payment_note( $form_id, $payment_id = 0, $payment_meta ) {

		$referral = affwp_get_referral_by( 'reference', $payment_id, $this->context );

		if ( is_wp_error( $referral ) ) {
			affiliate_wp()->utils->log( 'insert_payment_note: The referral could not be found.', $referral );

			return;
		}

		$amount       = affwp_currency_filter( affwp_format_amount( $referral->amount ) );
		$affiliate_id = $referral->affiliate_id;
		$name         = affiliate_wp()->affiliates->get_affiliate_name( $affiliate_id );

		/* translators: 1: Referral ID, 2: Formatted referral amount, 3: Affiliate name */
		give_insert_payment_note( $payment_id, sprintf( __( 'Referral #%1$d for %2$s recorded for %3$s', 'affiliate-wp' ), $referral->referral_id, $amount, $name ) );

	}

	/**
	 * Revokes a referral when donation is refunded
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function revoke_referral_on_refund( $payment_id = 0, $new_status, $old_status ) {

		if ( 'publish' != $old_status && 'revoked' != $old_status ) {
			return;
		}

		if ( 'refunded' != $new_status ) {
			return;
		}

		if ( ! affiliate_wp()->settings->get( 'revoke_on_refund' ) ) {
			return;
		}

		$this->reject_referral( $payment_id );

	}

	/**
	 * Revokes a referral when a donation is deleted
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function revoke_referral_on_delete( $payment_id = 0 ) {

		if ( ! affiliate_wp()->settings->get( 'revoke_on_refund' ) ) {
			return;
		}

		$this->reject_referral( $payment_id );

	}

	/**
	 * Sets up the reference link in the Referrals table
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function reference_link( $reference = 0, $referral ) {

		if ( empty( $referral->context ) || 'give' != $referral->context ) {
			return $reference;
		}

		$url = admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-order-details&id=' . $reference );

		return '<a href="' . esc_url( $url ) . '">' . $reference . '</a>';

	}

	/**
	 * Adds Give settings, using the Give Settings API.
	 *
	 * @param  array  $settings Give form settings.
	 * @return array $settings  Modified settings.
	 * @since  2.0
	 */
	public function donation_settings( $settings ) {
		$settings_fields = array(
			array(
				'name' => esc_html__( 'Allow Referrals', 'affiliate-wp' ),
				'desc' => esc_html__( 'Enable affiliate referral creation for this donation form', 'affiliate-wp' ),
				'id'   => '_affwp_give_allow_referrals',
				'type' => 'checkbox'
			),
			array(
				'name'            => esc_html__( 'Affiliate Rate', 'affiliate-wp' ),
				'description'     => esc_html__( 'This setting will be used to calculate affiliate earnings per-donation. Leave blank to use default affiliate rates.', 'affiliate-wp' ),
				'id'              => '_affwp_give_product_rate',
				'type'            => 'text_small'
			)
		);

		$settings[ 'affiliatewp' ] = array(
			'id'     => "affiliatewp",
			'title'  => __( 'AffiliateWP', 'affiliate-wp' ),
			'fields' => $settings_fields
		);

		return $settings;
	}

	/**
	 * Retrieves the customer details for a donation.
	 *
	 * @since 2.2
	 *
	 * @param int $payment_id The ID of the payment to retrieve customer details for.
	 * @return array An array of the customer details
	*/
	public function get_customer( $payment_id = 0 ) {

		$customer = array();

		if ( class_exists( 'Give_Donor' ) ) {

			$donor        = new Give_Donor( give_get_payment_donor_id( $payment_id ) );
			$names        = explode( ' ', $donor->name );
			$first_name   = $names[0];
			$last_name    = '';
			if( ! empty( $names[1] ) ) {
				unset( $names[0] );
				$last_name = implode( ' ', $names );
			}

			$customer['user_id']    = $donor->user_id;
			$customer['email']      = $donor->email;
			$customer['first_name'] = $first_name;
			$customer['last_name']  = $last_name;
			$customer['ip']         = give_get_payment_user_ip( $payment_id );

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
		return class_exists( 'Give' );
	}
}

	new Affiliate_WP_Give;