<?php
/**
 * Integrations: Optimize Member
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Implements an integration for Optimize Member.
 *
 * @since 1.2
 *
 * @see Affiliate_WP_Base
 */
class Affiliate_WP_OptimizeMember extends Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context = 'optimizemember';

	/**
	 * Setup the integration
	 *
	 * @access  public
	 * @since   1.9
	*/
	public function init() {

		add_action( 'init', array( $this, 'generate_referral' ) );
		add_action( 'init', array( $this, 'revoke_referral_on_refund' ) );

		add_action( 'plugins_loaded', array( $this, 'notify_url' ) );
		add_action( 'ws_plugin__optimizemember_before_sc_paypal_button_after_shortcode_atts', array( $this, 'set_referral_variable' ) );
		add_action( 'ws_plugin__optimizemember_pro_before_sc_stripe_form_after_shortcode_atts', array( $this, 'set_referral_variable' ) );
		add_action( 'ws_plugin__optimizemember_pro_before_sc_authnet_form_after_shortcode_atts', array( $this, 'set_referral_variable' ) );
		add_action( 'ws_plugin__optimizemember_pro_before_sc_paypal_form_after_shortcode_atts', array( $this, 'set_referral_variable' ) );

		add_action( 'affwp_tracking_set_visit_id', array( $this, 'set_visit_id' ), 10, 2 );
		add_action( 'affwp_tracking_set_affiliate_id', array( $this, 'set_affiliate_id' ), 10, 2 );
		add_action( 'affwp_tracking_set_campaign', array( $this, 'set_campaign' ), 10, 2 );
	}

	/**
	 * Create a payment notification url & refund notification url for AffiliateWP
	 *
	 * @access  public
	 * @since   1.9
	*/
	public function notify_url(){

		$om_options = &$GLOBALS['WS_PLUGIN__']['optimizemember']['o'];

		$auth_key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$secret_auth = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
		$hash        = md5( $auth_key . home_url() . get_bloginfo( 'admin_email' ) . $secret_auth );

		$om_payment_notification_urls = &$om_options['payment_notification_urls'];
		$affwp_payment_notify_url     = home_url( '/?optimizemember_affiliatewp_notify=payment&amount=%%amount%%&txn_id=%%txn_id%%&item_name=%%item_name%%&&ip=%%user_ip%%&user_id=%%user_id%%&item_number=%%item_number%%&payer_email=%%payer_email%%&affiliate_id=%%cv1%%&secret=' . $hash );

		// Add AffiliateWP payment notification URL to the list dynamically.
		if( stripos( $om_payment_notification_urls, $affwp_payment_notify_url ) === FALSE ){

			$om_payment_notification_urls .= "\n" . $affwp_payment_notify_url;

		}

		$om_page_sale_notification_urls = &$om_options['sp_sale_notification_urls'];
		$affwp_page_sale_notify_url     = home_url( '/?optimizemember_affiliatewp_notify=payment&amount=%%amount%%&txn_id=%%txn_id%%&item_name=%%item_name%%&&ip=%%user_ip%%&user_id=%%user_id%%&item_number=%%item_number%%&payer_email=%%payer_email%%&affiliate_id=%%cv1%%&secret=' . $hash );

		// Add AffiliateWP payment notification URL to the list dynamically.
		if( stripos( $om_page_sale_notification_urls, $affwp_page_sale_notify_url ) === FALSE ){

			$om_page_sale_notification_urls .= "\n" . $affwp_page_sale_notify_url;

		}

		$om_ref_rev_notification_urls = &$om_options['ref_rev_notification_urls'];
		$affwp_refund_notify_url      = home_url( '/?optimizemember_affiliatewp_notify=refund&txn_id=%%parent_txn_id%%&secret=' . $hash );

		// Add AffiliateWP refund notification URL notification URL to the list dynamically.
		if( stripos( $om_ref_rev_notification_urls, $affwp_refund_notify_url ) === FALSE ){

			$om_ref_rev_notification_urls .= "\n". $affwp_refund_notify_url;

		}

		$om_page_ref_rev_notification_urls = &$om_options['sp_ref_rev_notification_urls'];
		$affwp_page_refund_notify_url      = home_url( '/?optimizemember_affiliatewp_notify=refund&txn_id=%%parent_txn_id%%&secret=' . $hash );

		// Add AffiliateWP refund notification URL notification URL to the list dynamically.
		if( stripos( $om_page_ref_rev_notification_urls, $affwp_page_refund_notify_url ) === FALSE ){

			$om_page_ref_rev_notification_urls .= "\n". $affwp_page_refund_notify_url;

		}

	}

	/**
	 * Mark a referral as complete when an payment is received
	 *
	 * @access  public
	 * @since   1.9
	*/
	public function generate_referral() {

		if( ! empty( $_REQUEST['optimizemember_affiliatewp_notify'] ) && 'payment' === $_REQUEST['optimizemember_affiliatewp_notify'] ) {

			$this->log( 'OptimizeMember payment notification.' );

			$auth_key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
			$secret_auth = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
			$hash        = md5( $auth_key . home_url() . get_bloginfo( 'admin_email' ) . $secret_auth );

			if( empty( $_REQUEST['secret'] ) || ! hash_equals( $hash, $_REQUEST['secret'] ) ) {

				$this->log( 'OptimizeMember hash invalid.' );

				return;
			}

			$this->log( 'OptimizeMember hash verified.' );

			if( ! empty( $_REQUEST['affiliate_id'] ) ){

				$this->log( 'OptimizeMember referral validation passed.' );

				$affiliate_id 	= (int) $_REQUEST['affiliate_id'];
				$user_id 		= ! empty( $_REQUEST['user_id'] ) ? (int) $_REQUEST['user_id'] : 0;
				$amount 		= ! empty( $_REQUEST['amount'] ) ? affwp_sanitize_amount( $_REQUEST['amount'] ) : 0;
				$txn_id 		= sanitize_text_field( $_REQUEST['txn_id'] );
				$item_name 		= sanitize_text_field( $_REQUEST['item_name'] );
				$user_ip 		= sanitize_text_field( $_REQUEST['ip'] );
				$item_number 	= sanitize_text_field( $_REQUEST['item_number'] );
				$payer_email 	= sanitize_text_field( $_REQUEST['payer_email'] );

				$args =  array(
					'user_id' 		=> $user_id,
					'amount'		=> $amount,
					'txn_id'		=> $txn_id,
					'desc'			=> $item_name,
					'affiliate_id'	=> $affiliate_id
				);

				if( $user_id ) {

					$user = get_userdata( $user_id );

					if( $user ) {
						$this->email = $user->user_email;
					}
				}

				$this->add_pending_referral( $args );

				$this->log( 'OptimizeMember pending referral created.' );

				$this->complete_referral( $txn_id );

				$this->log( 'OptimizeMember referral completed.' );
			
				exit;
			}

			$this->log( 'OptimizeMember referral validation failed. Missing affiliate ID.' );

			exit;
		}

	}

	/**
	 * Revoke a referral on refund or cancellation
	 *
	 * @access  public
	 * @since   1.9
	*/
	public function revoke_referral_on_refund() {

		if( ! empty( $_REQUEST['optimizemember_affiliatewp_notify'] ) && $_REQUEST['optimizemember_affiliatewp_notify'] === 'refund' ) {

			$this->log( 'OptimizeMember refund notification.' );

			$auth_key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
			$secret_auth = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
			$hash        = md5( $auth_key . home_url() . get_bloginfo( 'admin_email' ) . $secret_auth );

			if( empty( $_REQUEST['secret'] ) || ! hash_equals( $hash, $_REQUEST['secret'] ) ) {

				$this->log( 'OptimizeMember hash invalid.' );

				return;
			}

			$this->log( 'OptimizeMember hash verified.' );

			if( ! empty( $_REQUEST['txn_id'] ) ) {

				if( ! affiliate_wp()->settings->get( 'revoke_on_refund' ) ) {
					return;
				}

				$this->reject_referral( $_REQUEST['txn_id'] );

			}

			exit;

		}

	}

	/**
	 * Record a pending referral
	 *
	 * @access  public
	 * @since   1.9
	*/
	public function add_pending_referral( $args ){

		if ( affiliate_wp()->tracking->is_valid_affiliate( $args['affiliate_id'] ) ) {
			$this->affiliate_id = $args['affiliate_id'];

			$order_id    = $args['txn_id'];
			$description = $args['desc'];

			$user           = get_userdata( $args['user_id'] );
			$customer_email = $user->user_email;
			$this->email    = $customer_email;

			// Create draft referral.
			$referral_id = $this->insert_draft_referral(
				$this->affiliate_id,
				array(
					'reference'   => $order_id,
					'description' => $description,
				)
			);
			if ( ! $referral_id ) {
				$this->log( 'Draft referral creation failed.' );
				return;
			}

			// Customers cannot refer themselves.
			if ( $this->is_affiliate_email( $customer_email ) ) {
				$this->log( 'Referral not created because affiliate\'s own account was used.' );
				$this->mark_referral_failed( $referral_id );
				return;
			}

			$amount         = $args['amount'];
			$referral_total = $this->calculate_referral_amount( $amount, $order_id );

			// Hydrates the previously created referral.
			$this->hydrate_referral(
				$referral_id,
				array(
					'status' => 'pending',
					'amount' => $referral_total,
				)
			);
			$this->log( sprintf( 'OptimizeMember referral #%d updated to pending successfully.', $referral_id ) );
		}
	}

	/**
	 * Add the Affiliate ID if set to the request that is sent to the gateway
	 *
	 * @access  public
	 * @since   1.9
	*/
	public function set_referral_variable( $vars ){

		$this->log( 'OptimizeMember set_referral_variable() ran.' );

		if( affiliate_wp()->tracking->get_affiliate_id() ){

			$this->log( 'OptimizeMember affiliate ID was found.' );

			$affiliate_id = affiliate_wp()->tracking->get_affiliate_id();
			$vars["__refs"]["attr"]["custom"] .= "|" . $affiliate_id;

			$this->log( 'OptimizeMember custom variable set to ' . $vars["__refs"]["attr"]["custom"] );

		}
	}

	/**
	 * Set the affwp_ref cookie from the affiliate ID
	 *
	 * @access  public
	 * @since   2.1.17
	 *
	 * @param int                    $affiliate_id Affiliate ID.
	 * @param \Affiliate_WP_Tracking $tracking     Tracking class instance.
	 */
	public function set_affiliate_id( $affiliate_id, $tracking ) {
		$cookie_name = $tracking->get_cookie_name( 'referral' );
		$affwp_ref   = ! empty( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : false;

		if ( empty( $affwp_ref ) ) {
			$_COOKIE[ $cookie_name ] = $affiliate_id;
		}
	}

	/**
	 * Set the affwp_ref_visit_id cookie from the visit ID
	 *
	 * @access  public
	 * @since   2.1.17
	 *
	 * @param int                    $visit id Visit ID.
	 * @param \Affiliate_WP_Tracking $tracking Tracking class instance.
	 */
	public function set_visit_id( $visit_id, $tracking ) {
		$cookie_name        = $tracking->get_cookie_name( 'visit' );
		$affwp_ref_visit_id = ! empty( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : false;

		if ( empty( $affwp_ref_visit_id ) ) {
			$_COOKIE[ $cookie_name ] = $visit_id;
		}
	}

	/**
	 * Set the affwp_campaign cookie from the campaign
	 *
	 * @access  public
	 * @since   2.1.17
	 *
	 * @param string                 $campaign Campaign.
	 * @param \Affiliate_WP_Tracking $tracking Tracking class instance.
	 */
	public function set_campaign( $campaign, $tracking ) {
		$cookie_name    = $tracking->get_cookie_name( 'campaign' );
		$affwp_campaign = ! empty( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : false;

		if ( empty( $affwp_campaign ) ) {
			$_COOKIE[ $cookie_name ] = $campaign;
		}
	}

	/**
	 * Runs the check necessary to confirm this plugin is active.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	function plugin_is_active() {
		return function_exists( 'load_opm_plugin_screen' );
	}
}

	new Affiliate_WP_OptimizeMember;