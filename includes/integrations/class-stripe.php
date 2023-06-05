<?php
/**
 * Integrations: WP Simple Pay
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Implements an integration for WP Simple Pay (both lite and pro).
 *
 * @since 1.2
 *
 * @see Affiliate_WP_Base
 */
class Affiliate_WP_Stripe extends Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context = 'stripe';

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   2.0
	 */
	public function init() {

		add_filter( 'affwp_referral_reference_column', array( $this, 'reference_link' ), 10, 2 );

		// Use webhooks to track conversions more accurately across multiple types
		// of Payment Methods in WP Simple Pay Pro >= 3.6.0.
		if ( 
			class_exists( '\SimplePay\Pro\SimplePayPro' ) &&
			version_compare( SIMPLE_PAY_VERSION, '3.6.0', '>=' )
		) {
			add_filter( 'simpay_get_subscription_args_from_payment_form_request', array( $this, 'maybe_track_referral_360' ) );
			add_filter( 'simpay_get_paymentintent_args_from_payment_form_request', array( $this, 'maybe_track_referral_360' ) );

			add_action( 'simpay_webhook_subscription_created', array( $this, 'process_referral_360' ), 10, 2 );
			add_action( 'simpay_webhook_payment_intent_succeeded', array( $this, 'process_referral_360' ), 10, 2 );

		// Track conversions when the "Payment Success Page" is reached in
		// WP Simple Pay Lite or WP Simple Pay Pro < 3.6.0 (no webhooks).
		//
		// "Payment Success Page" must include [simpay_payment_receipt] shortcode
		// for legacy actions to run and referrals tracked.
		} else {
			add_action( 'simpay_subscription_created', array( $this, 'insert_referral' ) );
			add_action( 'simpay_charge_created', array( $this, 'insert_referral' ) );
		}
	}

	/**
	 * Adds affiliate metadata to Stripe object creation.
	 *
	 * @since 2.3.4
	 *
	 * @param array $object_args Subscription or PaymentIntent arguments.
	 *                           Both utilize Stripe metadata.
	 * @return array (Maybe) modified array of object metadata.
	 */
	public function maybe_track_referral_360( $object_args ) {
		if ( ! $this->was_referred() ) {
			return $object_args;
		}

		$object_args['metadata']['affwp_visit_id']     = affiliate_wp()->tracking->get_visit_id();
		$object_args['metadata']['affwp_affiliate_id'] = $this->affiliate_id;

		return $object_args;
	}

	/**
	 * Determines if an object contains affiliate metadata and creates a referral if needed.
	 *
	 * @since 2.3.4
	 *
	 * @param \Stripe\Event                              $event Stripe Event.
	 * @param \Stripe\Subscription|\Stripe\PaymentIntent $object Stripe Subscription or PaymentIntent
	 */
	public function process_referral_360( $event, $object ) {
		$affiliate_id = isset( $object->metadata->affwp_affiliate_id ) ? $object->metadata->affwp_affiliate_id : 0;

		if ( 0 === $affiliate_id ) {
			$this->log( 'Stripe webhook not processed because affiliate ID was not set.' );
			return;
		}

		// Assign email.
		$this->email = $object->customer->email;

		// Create draft referral.
		$referral_id = $this->insert_draft_referral(
			$affiliate_id,
			array(
				'reference' => $object->id,
			)
		);
		if ( ! $referral_id ) {
			$this->log( 'Draft referral creation failed.' );
			return;
		}

		$visit_id = isset( $object->metadata->affwp_visit_id )
			? intval( $object->metadata->affwp_visit_id )
			: false;

		switch ( $object->object ) {
			case 'subscription': 
				$this->log( 'Processing referral for Stripe subscription.' );

				$invoice = $event->data->object;

				$stripe_amount = $invoice->amount_paid;
				$currency      = $invoice->currency;
				$mode          = $invoice->livemode;
				$description   = $object->plan->nickname;

				break;

			case 'payment_intent':
				$this->log( 'Processing referral for Stripe charge.' );

				$stripe_amount = $object->amount_received;
				$currency      = $object->currency;
				$mode          = $object->livemode;
				$description   = $object->description;

				break;
		}

		// Fill any empty descriptions with the form's item description or title.
		if ( empty( $description ) ) {
			$form_id     = $object->metadata->simpay_form_id;
			$description = simpay_get_filtered( 'item_description', simpay_get_saved_meta( $form_id, '_item_description' ), $form_id );

			if ( empty( $description ) ) {
				$description = get_the_title( $form_id );
			}
		}

		// Adjust amount based on currency decimals.
		if ( $this->is_zero_decimal( $currency ) ) {
			$amount = $stripe_amount;
		} else {
			$amount = round( $stripe_amount / 100, 2 );
		}

		$amount = $this->calculate_referral_amount( $amount, $object->id, 0, $affiliate_id );

		if ( $this->is_affiliate_email( $this->email, $affiliate_id ) ) {
			$this->log( 'Referral not created because affiliate\'s own account was used.' );
			$this->mark_referral_failed( $referral_id );
			return;
		}

		// Hydrates the previously created referral.
		$this->hydrate_referral(
			$referral_id,
			array(
				'status'      => 'pending',
				'amount'      => $amount,
				'description' => $description,
				'visit_id'    => $visit_id,
				'custom'      => array(
					'affiliate_id' => $affiliate_id,
					'visit_id'     => $visit_id,
					'livemode'     => $mode,
				),
			)
		);

		$this->log( 'Pending referral created successfully during Stripe webhook processing.' );

		$completed = $this->complete_referral( $object->id );

		if ( true === $completed ) {
			$this->log( 'Referral completed successfully during Stripe webhook processing.' );
		} else{
			$this->log( 'Referral failed to be set to completed with complete_referral() during Stripe webhook processing.', $object );
		}
	}

	/**
	 * Create a referral during stripe form submission if customer was referred
	 *
	 * Legacy < 3.6.0 support.
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function insert_referral( $object ) {

		// Check if it was referred.
		if( ! $this->was_referred() ) {
			return false; // Referral not created because affiliate was not referred.
		}

		// Create draft referral.
		$referral_id = $this->insert_draft_referral(
			$this->affiliate_id,
			array(
				'reference' => $object->id,
			)
		);
		if ( ! $referral_id ) {
			$this->log( 'Draft referral creation failed.' );
			return;
		}

		global $simpay_form;

		switch( $object->object ) {

			case 'subscription' :

				$this->log( 'Processing referral for Stripe subscription.' );

				$stripe_amount = ! empty( $object->plan->trial_period_days ) ? 0 : $object->plan->amount;
				$currency      = $object->plan->currency;
				$description   = $object->plan->nickname;
				$mode          = $object->plan->livemode;

				break;

			case 'charge' :
			default :

				if( did_action( 'simpay_subscription_created' ) ) {

					$this->log( 'insert_referral() short circuited because simpay_subscription_created already fired.' );

					return; // This was a subscription purchase and we've already processed the referral creation
				}


				$this->log( 'Processing referral for Stripe charge.' );

				$stripe_amount = $object->amount;
				$currency      = $object->currency;
				$description   = ! empty( $object->description ) ? $object->description : '';
				$mode          = $object->livemode;

				break;

		}

		if ( empty( $description ) && isset( $simpay_form->post->post_title ) ) {

			$description = $simpay_form->post->post_title;

		}

		if( $this->is_zero_decimal( $currency ) ) {
			$amount = $stripe_amount;
		} else {
			$amount = round( $stripe_amount / 100, 2 );
		}

		if( is_object( $object->customer ) && ! empty( $object->customer->email ) ) {
			$this->email = $object->customer->email;
		} else {
			if ( isset( $_POST['stripeEmail'] ) ) {

				// WP Simple Pay < 3.0
				$this->email = sanitize_text_field( $_POST['stripeEmail'] );
			} elseif ( isset( $_POST['simpay_stripe_email'] ) ) {

				// WP Simple Pay >= 3.0
				$this->email = sanitize_text_field( $_POST['simpay_stripe_email'] );
			}
		}

		if( $this->is_affiliate_email( $this->email, $this->affiliate_id ) ) {

			$this->log( 'Referral not created because affiliate\'s own account was used.' );
			$this->mark_referral_failed( $referral_id );
			return;

		}

		$referral_total = $this->calculate_referral_amount( $amount, $object->id );
		
		// Hydrates the previously created referral.
		$this->hydrate_referral(
			$referral_id,
			array(
				'status'      => 'pending',
				'amount'      => $referral_total,
				'description' => $description,
				'custom'      => array(
					'livemode'     => $mode,
				),
			)
		);

		$this->log( 'Pending referral created successfully during insert_referral()' );

		if( $this->complete_referral( $object->id ) ) {

			$this->log( 'Referral completed successfully during insert_referral()' );

			return;

		}

		$this->log( 'Referral failed to be set to completed with complete_referral()' );
	}

	/**
	 * Determine if this is a zero decimal currency
	 *
	 * @access public
	 * @since  2.0
	 * @param  $currency String The currency code
	 * @return bool
	 */
	public function is_zero_decimal( $currency ) {

		$is_zero = array(
			'BIF',
			'CLP',
			'DJF',
			'GNF',
			'JPY',
			'KMF',
			'KRW',
			'MGA',
			'PYG',
			'RWF',
			'VND',
			'VUV',
			'XAF',
			'XOF',
			'XPF',
		);

		return in_array( strtoupper( $currency ), $is_zero );
	}

	/**
	 * Sets up the reference link in the Referrals table
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function reference_link( $reference = 0, $referral ) {

		if ( empty( $referral->context ) || 'stripe' != $referral->context ) {

			return $reference;

		}

		$test = '';

		if( ! empty( $referral->custom ) ) {
			$custom = maybe_unserialize( $referral->custom );
			$test   = empty( $custom['livemode'] ) ? 'test/' : '';
		}

		$endpoint = false !== strpos( $reference, 'sub_' ) ? 'subscriptions' : 'payments';

		$url = 'https://dashboard.stripe.com/' . $test . $endpoint  . '/' . $reference ;

		return '<a href="' . esc_url( $url ) . '">' . $reference . '</a>';
	}

	/**
	 * Runs the check necessary to confirm this plugin is active.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	function plugin_is_active() {
		return class_exists( 'Stripe_Checkout' ) || class_exists( 'Stripe_Checkout_Pro' ) || class_exists( '\SimplePay\Core\SimplePay' );
	}
}

	new Affiliate_WP_Stripe;