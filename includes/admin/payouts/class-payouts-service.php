<?php
/**
 * Admin: Payouts Service Class
 *
 * @package    AffiliateWP
 * @subpackage Payouts
 * @copyright  Copyright (c) 2019, Sandhills Development, LLC
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.4
 */

/**
 * Affiliate_WP_Payouts_Service Class
 *
 * @since 2.4
 */
class Affiliate_WP_Payouts_Service {

	/**
	 * Constructor.
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function __construct() {

		add_filter( 'affwp_payout_methods',                             array( $this, 'add_payout_method' ) );
		add_filter( 'affwp_is_payout_method_enabled',                   array( $this, 'is_payout_service_enabled' ), 10, 2 );
		add_action( 'affwp_notices_registry_init',                      array( $this, 'register_admin_notices' ) );

		add_action( 'affwp_process_payouts_service_connect_completion', array( $this, 'complete_connection' ) );
		add_action( 'affwp_payouts_service_reconnect',                  array( $this, 'reconnect_site' ) );
		add_action( 'affwp_payouts_service_disconnect',                 array( $this, 'disconnect_site' ) );

		add_action( 'affwp_preview_payout_note_payouts-service',        array( $this, 'preview_payout_message' ) );
		add_action( 'affwp_process_payout_payouts-service',             array( $this, 'process_payout' ), 10, 5 );

		add_action( 'affwp_preview_payout_after_referrals_total_payouts-service', array( $this, 'display_fee' ), 10, 2 );
	}

	/**
	 * Adds 'Payouts Service' as a payout method to AffiliateWP.
	 *
	 * @since 2.4
	 *
	 * @param array $payout_methods Payout methods.
	 * @return array Filtered payout methods.
	 */
	public function add_payout_method( $payout_methods ) {

		$vendor_id         = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
		$access_key        = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );
		$connection_status = affiliate_wp()->settings->get( 'payouts_service_connection_status', '' );

		if ( 'active' !== $connection_status || ! ( $vendor_id && $access_key ) ) {
			/* translators: 1: Payouts Service settings link */
			$payout_methods['payouts-service'] = sprintf( __( 'Payouts Service - <a href="%s">Register and/or connect</a> your account to enable this payout method', 'affiliate-wp' ), affwp_admin_url( 'settings', array( 'tab' => 'payouts_service' ) ) );
		} else {
			$payout_methods['payouts-service'] = __( 'Payouts Service', 'affiliate-wp' );
		}

		return $payout_methods;
	}

	/**
	 * Checks if the 'Payouts Service' payout method is enabled.
	 *
	 * @since 2.4
	 *
	 * @param bool   $enabled       True if the payout method is enabled. False otherwise.
	 * @param string $payout_method Payout method.
	 * @return bool True if the payout method is enabled. False otherwise.
	 */
	public function is_payout_service_enabled( $enabled, $payout_method ) {

		if ( 'payouts-service' === $payout_method ) {
			$vendor_id         = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
			$access_key        = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );
			$connection_status = affiliate_wp()->settings->get( 'payouts_service_connection_status', '' );

			if ( 'active' !== $connection_status || ! ( $vendor_id && $access_key ) ) {
				$enabled = false;
			}
		}

		return $enabled;
	}

	/**
	 * Adds a note to the preview page for a payout being made via the service.
	 *
	 * @since 2.4
	 *
	 * @return void.
	 */
	public function preview_payout_message() {
		?>
		<h2><?php esc_html_e( 'Note', 'affiliate-wp' ); ?></h2>
		<p><?php echo esc_html( _x( 'It takes approximately two weeks for each payout to be deposited into each affiliates bank account when the Payouts Service invoice has been paid.', 'Note shown on the preview payout page for a Payouts Service payout', 'affiliate-wp' ) ); ?></p>
		<p><?php echo esc_html( _x( 'For affiliates located in the United States, it takes approximately a week.', 'Note shown on the preview payout page for a Payouts Service payout', 'affiliate-wp' ) ); ?></p>
		<?php
	}

	/**
	 * Displays the service fee on the preview payout page.
	 *
	 * @since 2.4
	 *
	 * @param float $referrals_total Referrals total.
	 * @param array $data            Payout data.
	 * @return void.
	 */
	public function display_fee( $referrals_total, $data ) {

		if ( empty( $data ) ) {
			return;
		}

		$body_args = array(
			'payout_data'   => $data,
			'currency'      => affwp_get_currency(),
			'affwp_version' => AFFILIATEWP_VERSION,
		);

		$headers = affwp_get_payouts_service_http_headers();

		$args = array(
			'body'      => $body_args,
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => false,
		);

		$request = wp_remote_get( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/fee', $args );

		$response_code = wp_remote_retrieve_response_code( $request );
		$response      = json_decode( wp_remote_retrieve_body( $request ) );

		if ( ! is_wp_error( $request ) && 200 === (int) $response_code ) {

			$payout_service_fee = $response->payout_service_fee;
			$payout_total       = $referrals_total + $payout_service_fee;

		} else {

			$payout_total       = $referrals_total;
			$payout_service_fee = __( 'Can&#8217;t retrieve Payouts Service fee at the moment', 'affiliate-wp' );

		}

		?>

		<tr class="form-row">

			<th scope="row">
				<?php esc_html_e( 'Payouts Service Fee', 'affiliate-wp' ); ?>
			</th>

			<td>
				<?php if ( is_numeric( $payout_service_fee ) ) : ?>
					<?php echo affwp_currency_filter( affwp_format_amount( $payout_service_fee ) ); ?>
				<?php else : ?>
					<?php echo esc_attr( $payout_service_fee ); ?>
				<?php endif; ?>
			</td>

		</tr>

		<tr class="form-row">

			<th scope="row">
				<?php echo esc_html( _x( 'Total', 'Total amount for a Payouts Service payout', 'affiliate-wp' ) ); ?>
			</th>

			<td>
				<?php echo affwp_currency_filter( affwp_format_amount( $payout_total ) ); ?>
			</td>

		</tr>

		<?php

	}

	/**
	 * Processes payouts in bulk for a specified time frame via the service.
	 *
	 * @since 2.4
	 *
	 * @param string $start         Referrals start date.
	 * @param string $end           Referrals end date data.
	 * @param int    $minimum       Minimum payout.
	 * @param int    $affiliate_id  Affiliate ID.
	 * @param string $payout_method Payout method.
	 *
	 * @return void
	 */
	public function process_payout( $start, $end, $minimum, $affiliate_id, $payout_method ) {

		$vendor_id         = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
		$access_key        = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );
		$connection_status = affiliate_wp()->settings->get( 'payouts_service_connection_status', '' );

		if ( 'active' !== $connection_status || ! ( $vendor_id && $access_key ) ) {

			$message = __( 'Your website is not connected to the Payouts Service', 'affiliate-wp' );

			$redirect = affwp_admin_url( 'referrals', array(
				'affwp_notice'     => 'payouts_service_error',
				'affwp_ps_message' => urlencode( $message ),
			) );

			wp_redirect( $redirect );
			exit;

		}

		$headers = affwp_get_payouts_service_http_headers();

		$args = array(
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => false,
		);

		$payouts_service_url = add_query_arg( array(
			'affwp_version' => AFFILIATEWP_VERSION,
		), PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/vendor' );

		$request = wp_remote_get( $payouts_service_url, $args );

		$error_redirect_args = array(
			'affwp_notice' => 'payouts_service_error',
		);

		if ( is_wp_error( $request ) ) {

			$error_redirect_args['affwp_ps_message'] = urlencode( $request->get_error_message() );

			$redirect = affwp_admin_url( 'referrals', $error_redirect_args );

			wp_redirect( $redirect );
			exit;

		} else {

			$response      = json_decode( wp_remote_retrieve_body( $request ) );
			$response_code = wp_remote_retrieve_response_code( $request );

			if ( 200 === (int) $response_code ) {

				$args = array(
					'status'       => 'unpaid',
					'date'         => array(
						'start' => $start,
						'end'   => $end,
					),
					'number'       => -1,
					'affiliate_id' => $affiliate_id,
				);

				// Final  affiliate / referral data to be paid out.
				$data = array();

				// The affiliates that have earnings to be paid.
				$affiliates = array();

				// The affiliates that can't be paid out.
				$invalid_affiliates = array();

				// Retrieve the referrals from the database.
				$referrals = affiliate_wp()->referrals->get_referrals( $args );

				if ( $referrals ) {

					foreach ( $referrals as $referral ) {

						if ( in_array( $referral->affiliate_id, $invalid_affiliates ) ) {
							continue;
						}

						if ( in_array( $referral->affiliate_id, $affiliates ) ) {

							// Add the amount to an affiliate that already has a referral in the export.
							$amount = $data[ $referral->affiliate_id ]['amount'] + $referral->amount;

							$data[ $referral->affiliate_id ]['amount']      = $amount;
							$data[ $referral->affiliate_id ]['referrals'][] = $referral->referral_id;

						} else {

							$payout_service_account = affwp_get_payouts_service_account( $referral->affiliate_id );

							if ( false !== $payout_service_account['valid'] ) {

								$data[ $referral->affiliate_id ] = array(
									'account_id' => $payout_service_account['account_id'],
									'amount'     => $referral->amount,
									'referrals'  => array( $referral->referral_id ),
								);

								$affiliates[] = $referral->affiliate_id;

							} else {

								$invalid_affiliates[] = $referral->affiliate_id;

							}
						}
					}

					$payouts = array();

					$i = 0;

					foreach ( $data as $affiliate_id => $payout ) {

						if ( $minimum > 0 && $payout['amount'] < $minimum ) {
							// Ensure the minimum amount was reached.
							unset( $data[ $affiliate_id ] );

							// Skip to the next affiliate.
							continue;
						}

						$payouts[ $affiliate_id ] = array(
							'account_id'   => $payout['account_id'],
							'affiliate_id' => $affiliate_id,
							'amount'       => $payout['amount'],
							'referrals'    => $payout['referrals'],
						);

						$i++;
					}

					$response = $this->send_payout_request( $payouts );

					if ( is_wp_error( $response ) ) {

						$error_redirect_args['affwp_ps_message'] = $response->get_error_message();

						$redirect = affwp_admin_url( 'referrals', $error_redirect_args );

						// A header is used here instead of wp_redirect() due to the esc_url() bug that removes [] from URLs.
						header( 'Location:' . $redirect );
						exit;

					} else {

						$payout_invoice_url = esc_url( $response->payment_link );
						$payouts_data       = affwp_object_to_array( $response->payout_data );

						// We now know which referrals should be marked as paid.
						foreach ( $payouts_data as $affiliate_id => $payout ) {

							$payout_method = affwp_get_affiliate_meta( $affiliate_id, 'payouts_service_payout_method', true );

							if ( 'bank_account' === $payout_method['payout_method'] ) {
								$payout_account = $payout_method['bank_name'] . ' (' . $payout_method['account_no'] . ')';
							} else {
								$payout_account = $payout_method['card'];
							}

							$payout_id = affwp_add_payout( array(
								'status'               => 'processing',
								'affiliate_id'         => $affiliate_id,
								'referrals'            => $payout['referrals'],
								'amount'               => $payout['amount'],
								'payout_method'        => 'payouts-service',
								'service_account'      => $payout_account,
								'service_id'           => $response->payout_id,
								'service_invoice_link' => $response->payment_link,
							) );

						}

						wp_redirect( $payout_invoice_url );
						exit;

					}

				}

			} else {

				$message = $response->message;

				if ( empty( $message ) ) {
					$message = __( 'Unable to process payout request at the moment. Please try again later.', 'affiliate-wp' );
				}

				$error_redirect_args['affwp_ps_message'] = urldecode( $message );

				$redirect = affwp_admin_url( 'referrals', $error_redirect_args );

				wp_redirect( $redirect );
				exit;

			}
		}
	}

	/**
	 * Sends a payout request to the service.
	 *
	 * @since 2.4
	 *
	 * @param array $data Optional. Payout Data. Default empty array.
	 * @return bool|WP_Error
	 */
	public function send_payout_request( $payouts = array() ) {

		$body_args = array(
			'payout_data'   => $payouts,
			'currency'      => affwp_get_currency(),
			'affwp_version' => AFFILIATEWP_VERSION,
		);

		$headers = affwp_get_payouts_service_http_headers();

		$args = array(
			'body'      => $body_args,
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => false,
		);

		affiliate_wp()->utils->log( 'send_payout_request()', $body_args );

		$request = wp_remote_post( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/payout', $args );

		$response      = json_decode( wp_remote_retrieve_body( $request ) );
		$response_code = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $response_code ) {
			$error_response = new \WP_Error( $response_code, $response->message );

			affiliate_wp()->utils->log( 'send_payout_request() request failed', $error_response );

			return $error_response;
		}

		return $response;
	}

	/**
	 * Completes a connection request with the payouts service.
	 *
	 * @since 2.4
	 *
	 * @param array $data Optional. Payout Data. Default empty array.
	 * @return void
	 */
	public function complete_connection( $data = array() ) {

		$errors = new \WP_Error();

		if ( ! isset( $data['token'] ) ) {
			$errors->add( 'missing_token', 'The token was missing when attempting to complete the payouts service connection.' );
		}

		if ( ! current_user_can( 'manage_affiliate_options' ) ) {
			$errors->add( 'permission_denied', 'The current user does not have permission to complete the payouts service connection.' );
		}

		if ( headers_sent() ) {
			$errors->add( 'headers_already_sent', 'Headers were already sent by the time the payouts service connection completion was attempted.' );
		}

		$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );

		if ( true === $has_errors ) {
			affiliate_wp()->utils->log( 'Payouts Service: complete_connection() failed', $errors );

			return;
		}

		$headers = affwp_get_payouts_service_http_headers( false );

		$body = array(
			'token'    => sanitize_text_field( $data['token'] ),
			'site_url' => home_url(),
		);

		$args = array(
			'body'    => $body,
			'headers' => $headers,
			'timeout' => 60,
		);

		$api_url       = PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/vendor/validate-access-key';
		$response      = wp_remote_post( $api_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || 200 !== $response_code ) {
			// Add a debug log entry.
			affiliate_wp()->utils->log( 'payouts_service_connection_error', $response );

			// Dump a user-friendly error message to the UI.
			$message  = '<p>';
			/* translators: 1: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant, 2: Payouts service settings URL */
			$message .= sprintf( __( 'There was an error connecting to the %1$s. Please <a href="%2$s">try again</a>. If you continue to have this problem, please contact support.', 'affiliate-wp' ),
				PAYOUTS_SERVICE_NAME,
				esc_url( affwp_admin_url( 'settings', array( 'tab' => 'payouts_service' ) ) )
			);
			$message .= '</p>';

			wp_die( $message );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$settings = array(
			'payouts_service_access_key'        => $data['access_key'],
			'payouts_service_vendor_id'         => $data['vendor_id'],
			'payouts_service_connection_status' => 'active',
		);

		affiliate_wp()->settings->set( $settings, true );

		wp_safe_redirect( affwp_admin_url( 'settings', array(
			'tab'          => 'payouts_service',
			'affwp_notice' => 'payouts_service_site_connected'
		) ) );
		exit;
	}

	/**
	 * Reconnect a site to the Payouts Service
	 *
	 * @access public
	 * @since 2.4
	 *
	 * @param array $data Payout Data.
	 *
	 * @return void
	 */
	public function reconnect_site( $data = array() ) {

		if ( ! current_user_can( 'manage_affiliate_options' ) ) {
			wp_die( __( 'You do not have permission to disconnect the site from the Payouts Service payments', 'affiliate-wp' ) );
		}

		if ( ! isset( $_GET['payouts_service_reconnect_nonce'] ) || ! wp_verify_nonce( $_GET['payouts_service_reconnect_nonce'], 'payouts_service_reconnect' ) ) {
			return;
		}

		$headers = affwp_get_payouts_service_http_headers();

		$body = array(
			'site_url' => home_url(),
		);

		$args = array(
			'body'    => $body,
			'headers' => $headers,
			'timeout' => 60,
		);

		$api_url       = PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/vendor/reconnect';
		$response      = wp_remote_post( $api_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || 200 !== $response_code ) {
			// Add a debug log entry.
			affiliate_wp()->utils->log( 'payouts_service_reconnection_failure', $response );

			// Dump a user-friendly error message to the UI.
			/* translators: 1: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant, 2: Payouts service settings URL */
			$message = '<p>' . sprintf( __( 'Unable to reconnect to the %1$s. Please <a href="%2$s">try again</a>. If you continue to have this problem, please contact support.', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME, esc_url( affwp_admin_url( 'settings', array( 'tab' => 'payouts_service' ) ) ) ) . '</p>';
			wp_die( $message );
		}

		$settings = array(
			'payouts_service_connection_status' => 'active'
		);

		affiliate_wp()->settings->set( $settings, true );

		wp_safe_redirect( affwp_admin_url( 'settings', array( 'tab' => 'payouts_service', 'affwp_notice' => 'payouts_service_site_reconnected' ) ) );
		exit;
	}

	/**
	 * Disconnect a site from the Payouts Service
	 *
	 * @access public
	 * @since 2.4
	 *
	 * @param array $data Payout Data.
	 *
	 * @return void
	 */
	public function disconnect_site( $data = array() ) {

		if ( ! current_user_can( 'manage_affiliate_options' ) ) {
			wp_die( __( 'You do not have permission to disconnect the site from the Payouts Service payments', 'affiliate-wp' ) );
		}

		if ( ! isset( $_GET['payouts_service_disconnect_nonce'] ) || ! wp_verify_nonce( $_GET['payouts_service_disconnect_nonce'], 'payouts_service_disconnect' ) ) {
			return;
		}

		$headers = affwp_get_payouts_service_http_headers();

		$body = array(
			'site_url' => home_url(),
		);

		$args = array(
			'body'    => $body,
			'headers' => $headers,
			'timeout' => 60,
		);

		$api_url       = PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/vendor/disconnect';
		$response      = wp_remote_post( $api_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || 200 !== $response_code ) {
			// Add a debug log entry.
			affiliate_wp()->utils->log( 'payouts_service_disconnection_failure', $response );

			/* translators: 1: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant, 2: Payouts service settings URL */
			$message = '<p>' . sprintf( __( 'Unable to disconnect from the %1$s. Please <a href="%2$s">try again</a>. If you continue to have this problem, please contact support.', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME, esc_url( affwp_admin_url( 'settings', array( 'tab' => 'payouts_service' ) ) ) ) . '</p>';
			wp_die( $message );
		}

		$settings = array(
			'payouts_service_connection_status' => 'inactive'
		);

		affiliate_wp()->settings->set( $settings, true );

		wp_safe_redirect( affwp_admin_url( 'settings', array(
			'tab'          => 'payouts_service',
			'affwp_notice' => 'payouts_service_site_disconnected'
		) ) );
		exit;
	}

	/**
	 * Admin notices for success and error messages
	 *
	 * @since 2.4
	 *
	 * @param \AffWP\Admin\Notices_Registry $registry Registry instance.
	 * @return void
	 */
	public function register_admin_notices( $registry ) {

		if ( affwp_is_admin_page() && isset( $_REQUEST['affwp_ps_message'] ) ) {

			$message = ! empty( $_REQUEST['affwp_ps_message'] ) ? urldecode( $_REQUEST['affwp_ps_message'] ) : '';

			$registry->add_notice( 'payouts_service_error', array(
				'class'   => 'error',
				'message' => '<strong>' . __( 'Error:', 'affiliate-wp' ) . '</strong> ' . esc_html( $message ),
			) );
		}
	}

}
new Affiliate_WP_Payouts_Service;
