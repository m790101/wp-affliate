<?php
/**
 * Payouts Service Registration Class
 *
 * @package     AffiliateWP
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */
namespace AffWP\Affiliate\Payout;

/**
 * Service_Register Class.
 *
 * This class defines all primary methods by which a Payouts Service account is created.
 *
 * @since  2.4
 */
class Service_Register {

	/**
	 * Holds the WP_Error object
	 *
	 * @since 2.4
	 * @var   \WP_Error $errors
	 */
	private $errors;

	/**
	 * Sets up integration logic for registering new payouts service accounts from within AffiliateWP.
	 *
	 * @access public
	 * @since  2.4
	 */
	public function __construct() {

		$this->errors = new \WP_Error();

		add_action( 'affwp_payouts_service_register',             array( $this, 'process_registration'     ) );
		add_action( 'affwp_payouts_service_add_payout_method',    array( $this, 'add_payout_method'        ) );
		add_action( 'affwp_payouts_service_connect_account',      array( $this, 'connect_existing_account' ) );
		add_action( 'affwp_payouts_service_change_payout_method', array( $this, 'change_payout_method'     ) );
		add_action( 'affwp_affiliate_dashboard_notices',          array( $this, 'affiliate_area_notice'    ) );

	}

	/**
	 * Processes registration of a new payouts service account for an affiliate.
	 *
	 * @since 2.4
	 *
	 * @param array $data {
	 *     Optional. Array of arguments for creating a new payout service account. Default empty array.
	 *
	 *     @type string $country          Country of residence.
	 *     @type string $account_type     Payouts Service account type.
	 *     @type string $first_name       Affiliate first name.
	 *     @type string $last_name        Affiliate last name.
	 *     @type string $email            Affiliate email.
	 *     @type string $current_page_url Current page URL.
	 *     @type int    $business_owner   Is Affiliate Business Owner.
	 *     @type int    $day_of_birth     Day of birth.
	 *     @type int    $month_of_birth   Month of birth.
	 *     @type int    $year_of_birth    Year of birth.
	 *     @type int    $tos              Terms of use acceptance.
	 *     @type int    $affiliate_id     Affiliate ID.
	 * }
	 * @return void
	 */
	public function process_registration( $data = array() ) {

		$defaults = array(
			'country'          => '',
			'account_type'     => '',
			'first_name'       => '',
			'last_name'        => '',
			'email'            => '',
			'current_page_url' => '',
			'business_owner'   => 0,
			'day_of_birth'     => 0,
			'month_of_birth'   => 0,
			'year_of_birth'    => 0,
			'tos'              => 0,
			'affiliate_id'     => 0,
		);

		$args = wp_parse_args( $data, $defaults );

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( empty( $args['affiliate_id'] ) ) {
			return;
		}

		if ( empty( $args['current_page_url'] ) ) {
			return;
		}

		if ( ! filter_var( $args['current_page_url'], FILTER_VALIDATE_URL ) ) {
			return;
		}

		$affiliate_id = absint( $args['affiliate_id'] );
		$user_id      = affwp_get_affiliate_user_id( $affiliate_id );

		if ( get_current_user_id() !== $user_id ) {
			return;
		}

		if ( empty( $args['country'] ) && 'company' === $args['account_type'] ) {
			$this->errors->add( 'empty_country', __( 'Please select the country where the business is legally established', 'affiliate-wp' ) );
		} elseif ( empty( $args['country'] ) ) {
			$this->errors->add( 'empty_country', __( 'Please select your country of residence', 'affiliate-wp' ) );
		}

		if ( empty( $args['account_type'] ) ) {
			$this->errors->add( 'empty_account_type', __( 'Please select your account type', 'affiliate-wp' ) );
		}

		if ( ( 'business' === $args['account_type'] ) && empty( $args['business_name'] ) ) {
			$this->errors->add( 'empty_business_name', __( 'Please enter your business name', 'affiliate-wp' ) );
		}

		if ( empty( $args['first_name'] ) ) {
			$this->errors->add( 'empty_first_name', __( 'Please enter your first name', 'affiliate-wp' ) );
		}

		if ( empty( $args['last_name'] ) ) {
			$this->errors->add( 'empty_last_name', __( 'Please enter your last name', 'affiliate-wp' ) );
		}

		if ( empty( $args['email'] ) || ! is_email( $args['email'] ) ) {
			$this->errors->add( 'email_invalid', __( 'Invalid account email', 'affiliate-wp' ) );
		}

		if ( empty( $args['day_of_birth'] ) ) {
			$this->errors->add( 'empty_day_of_birth', __( 'Please select your day of birth', 'affiliate-wp' ) );
		}

		if ( empty( $args['month_of_birth'] ) ) {
			$this->errors->add( 'empty_month_of_birth', __( 'Please select your month of birth', 'affiliate-wp' ) );
		}

		if ( empty( $args['year_of_birth'] ) ) {
			$this->errors->add( 'empty_year_of_birth', __( 'Please select your year of birth', 'affiliate-wp' ) );
		}

		$terms_of_use = affiliate_wp()->settings->get( 'terms_of_use', '' );
		if ( ! empty( $terms_of_use ) && empty( $args['tos'] ) ) {
			$this->errors->add( 'empty_tos', __( 'Please agree to our terms of use', 'affiliate-wp' ) );
		}

		$email = sanitize_text_field( $args['email'] );

		if ( empty( $this->get_errors() ) ) {

			$is_email_registered = $this->check_registration_status( $email );

			if ( $is_email_registered ) {

				$query_args          = array(
					'affwp_action'     => 'payouts_service_connect_account',
					'current_page_url' => rawurlencode( esc_url( $args['current_page_url'] ) ),
				);
				$connect_account_url = wp_nonce_url( add_query_arg( $query_args ), 'payouts_service_connect_account', 'payouts_service_connect_account_nonce' );

				/* translators: 1: Email, 2: Connect existing Payouts Service account URL, 3: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant */
				$this->errors->add( 'email_registered', sprintf( __( 'Your email address %1$s is already registered on the %3$s. Click <a href="%2$s">here</a> to continue registering to be paid for this site using the same email address.', 'affiliate-wp' ), $email, esc_url( $connect_account_url ), PAYOUTS_SERVICE_NAME ) );

			}
		}

		if ( empty( $this->get_errors() ) ) {

			// TODO Split this business logic off to its own method.
			$account_type   = sanitize_text_field( $args['account_type'] );
			$country        = sanitize_text_field( $args['country'] );
			$business_name  = sanitize_text_field( $args['business_name'] );
			$business_owner = ! empty( $args['business_owner'] ) ? true : false;
			$first_name     = sanitize_text_field( $args['first_name'] );
			$last_name      = sanitize_text_field( $args['last_name'] );
			$email          = sanitize_text_field( $args['email'] );
			$day_of_birth   = sanitize_text_field( $args['day_of_birth'] );
			$month_of_birth = sanitize_text_field( $args['month_of_birth'] );
			$year_of_birth  = sanitize_text_field( $args['year_of_birth'] );

			$headers = affwp_get_payouts_service_http_headers();

			$api_params = array(
				'site_url'       => home_url(),
				'account_type'   => $account_type,
				'affiliate_id'   => $affiliate_id,
				'country'        => $country,
				'business_name'  => $business_name,
				'business_owner' => $business_owner,
				'first_name'     => $first_name,
				'last_name'      => $last_name,
				'email'          => $email,
				'birthday_day'   => $day_of_birth,
				'birthday_month' => $month_of_birth,
				'birthday_year'  => $year_of_birth,
				'ip'             => affiliate_wp()->tracking->get_ip(),
				'affwp_version'  => AFFILIATEWP_VERSION,
			);

			$api_args = array(
				'body'      => $api_params,
				'headers'   => $headers,
				'timeout'   => 60,
				'sslverify' => false,
			);

			$request = wp_remote_post( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/account', $api_args );

			if ( is_wp_error( $request ) ) {

				$error_code = $request->get_error_code();
				$error_msg  = $request->get_error_message();

				$this->errors->add( $error_code, $error_msg );

			} else {

				$response      = json_decode( wp_remote_retrieve_body( $request ) );
				$response_code = wp_remote_retrieve_response_code( $request );

				if ( 200 === (int) $response_code ) {

					$payout_service_meta = array(
						'account_id' => $response->account_id,
						'status'     => $response->status,
						'link_id'    => $response->link_id,
					);

					affwp_update_affiliate_meta( $affiliate_id, 'payouts_service_account', $payout_service_meta );

					$current_page_url = esc_url( $args['current_page_url'] );

					$url = add_query_arg( 'redirect_url', urlencode( $current_page_url ), PAYOUTS_SERVICE_URL . '/account/' . $response->link_id );

					wp_redirect( $url );
					exit;

				} else {

					$this->errors->add( 'service_account_not_created', $response->message );

				}

			}

		}

	}

	/**
	 * Saves the affiliate payouts service payout method details to affiliate meta.
	 *
	 * @since 2.4
	 *
	 * @param array $data {
	 *     Array of arguments for saving the affiliate payouts service payout method details. Default empty array.
	 *
	 *     @type string $payout_method   Payout method (bank_account or card).
	 *     @type string $bank_name       Bank name.
	 *     @type string $account_name    Account holder name.
	 *     @type string $account_no      Masked Account no.
	 *     @type string $brand           Card brand.
	 *     @type string $last4           Card last 4 digits.
	 *     @type int    $exp_month       Card expiry month.
	 *     @type int    $exp_year        Card expiry year.
	 * }
	 * @return void
	 */
	public function add_payout_method( $data = array() ) {

		if ( ! isset( $data['affiliate_id'], $data['payout_method_id'] ) ) {
			return;
		}

		$affiliate_id     = intval( $data['affiliate_id'] );
		$payout_method_id = sanitize_text_field( $data['payout_method_id'] );

		$vendor_id = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );

		$headers = affwp_get_payouts_service_http_headers();

		$api_params = array(
			'vendor_id'        => $vendor_id,
			'affiliate_id'     => $affiliate_id,
			'payout_method_id' => $payout_method_id,
			'affwp_version'    => AFFILIATEWP_VERSION,
		);

		$args = array(
			'body'      => $api_params,
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => false,
		);

		$request = wp_remote_get( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/account/payout-method', $args );

		if ( is_wp_error( $request ) ) {

			$this->errors->add( $request->get_error_code(), $request->get_error_message() );

		} else {

			$response      = json_decode( wp_remote_retrieve_body( $request ) );
			$response_code = wp_remote_retrieve_response_code( $request );

			if ( ( 200 === (int) $response_code ) && ( $affiliate_id === (int) $response->affiliate_id ) ) {

				if ( 'bank_account' === $response->payout_method ) {

					$bank_name    = $response->bank_name;
					$account_name = $response->account_name;
					$account_no   = $response->account_no;

					$payout_method_meta = array(
						'payout_method' => 'bank_account',
						'account_no'    => $account_no,
						'account_name'  => $account_name,
						'bank_name'     => $bank_name,
					);

				} else {

					$brand     = $response->brand;
					$last4     = $response->last4;
					$exp_month = $response->exp_month;
					$exp_year  = $response->exp_year;

					$payout_method_meta = array(
						'payout_method' => 'card',
						/* translators: 1: Credit card brand, 2: Last four digits of the card number */
						'card'          => sprintf( __( '%1$s ending in %2$s', 'affiliate-wp' ), $brand, $last4 ),
						'expiry'        => $exp_month . '/' . $exp_year,
					);

				}

				affwp_update_affiliate_meta( $affiliate_id, 'payouts_service_payout_method', $payout_method_meta );

				$payout_service_meta = affwp_get_affiliate_meta( $affiliate_id, 'payouts_service_account', true );

				if ( ! $payout_service_meta ) {

					$payout_service_meta = array(
						'account_id' => $response->account_id,
						'status'     => 'payout_method_added',
					);

					affwp_update_affiliate_meta( $affiliate_id, 'payouts_service_account', $payout_service_meta );

				} else {

					$payout_service_meta['status'] = 'payout_method_added';

					// Delete Payouts Service link id since a payout method has been added.
					unset( $payout_service_meta['link_id'] );

					affwp_update_affiliate_meta( $affiliate_id, 'payouts_service_account', $payout_service_meta );

				}

				$query_args = array( 'affwp_action', 'payout_method_id', 'affiliate_id' );
				$url        = remove_query_arg( $query_args );

				wp_redirect( $url ); exit;

			} else {

				$this->errors->add( 'cant_add_payout_account', $response->message );

			}

		}

	}

	/**
	 * Generate a link to connect an existing payouts service account
	 *
	 * @since 2.4
	 *
	 * @param array $data {
	 *     Array of arguments for connecting an existing payouts service account. Default empty array.
	 *
	 *     @type string $current_page_url Current page URL
	 * }
	 *
	 * @return void
	 */
	public function connect_existing_account( $data = array() ) {

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_REQUEST['payouts_service_connect_account_nonce'] ) || ! wp_verify_nonce( $_REQUEST['payouts_service_connect_account_nonce'], 'payouts_service_connect_account' ) ) {
			return;
		}

		if ( empty( $data['current_page_url' ] ) ) {
			return;
		}

		if ( ! filter_var( $data['current_page_url' ], FILTER_VALIDATE_URL ) ) {
			return;
		}

		$vendor_id = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );

		$headers = affwp_get_payouts_service_http_headers();

		$current_page_url = esc_url( $data['current_page_url'] );

		$api_params = array(
			'affiliate_id'  => affwp_get_affiliate_id(),
			'site_url'      => home_url(),
			'redirect_url'  => $current_page_url,
			'vendor_id'     => $vendor_id,
			'affwp_version' => AFFILIATEWP_VERSION,
		);

		$args = array(
			'body'      => $api_params,
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => false,
		);

		$request = wp_remote_post( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/account/link', $args );

		if ( is_wp_error( $request ) ) {

			$this->errors->add( $request->get_error_code(), $request->get_error_message() );

		} else {

			$response      = json_decode( wp_remote_retrieve_body( $request ) );
			$response_code = wp_remote_retrieve_response_code( $request );

			if ( 200 === (int) $response_code ) {

				$url = PAYOUTS_SERVICE_URL . '/connect/' . $response->link_id;

				wp_redirect( $url ); exit;

			} else {

				$this->errors->add( 'service_account_not_created', $response->message );

			}

		}

	}

	/**
	 * Generate a link to change the payout method being used
	 *
	 * @since 2.4
	 *
	 * @param array $data {
	 *     Array of arguments for changing a new payout method. Default empty array.
	 *
	 *     @type string $current_page_url Current page URL
	 * }
	 *
	 * @return void
	 */
	public function change_payout_method( $data = array() ) {

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['payouts_service_change_payout_method_nonce'], 'payouts_service_change_payout_method' ) ) {
			return;
		}

		if ( empty( $data['current_page_url' ] ) ) {
			return;
		}

		if ( ! filter_var( $data['current_page_url' ], FILTER_VALIDATE_URL ) ) {
			return;
		}

		$affiliate_id                 = affwp_get_affiliate_id();
		$payouts_service_account_meta = affwp_get_affiliate_meta( $affiliate_id, 'payouts_service_account', true );

		$vendor_id = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );

		$headers = affwp_get_payouts_service_http_headers();

		$current_page_url = esc_url( $data['current_page_url'] );

		$api_params = array(
			'account_id'    => $payouts_service_account_meta['account_id'],
			'affiliate_id'  => $affiliate_id,
			'site_url'      => home_url(),
			'redirect_url'  => $current_page_url,
			'vendor_id'     => $vendor_id,
			'affwp_version' => AFFILIATEWP_VERSION,
		);

		$args = array(
			'body'      => $api_params,
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => false,
		);

		$request = wp_remote_get( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/account/change-payout-method', $args );

		if ( is_wp_error( $request ) ) {

			$error_code = $request->get_error_code();
			$error_msg  = $request->get_error_message();

			$this->errors->add( $error_code, $error_msg );

		} else {

			$response      = json_decode( wp_remote_retrieve_body( $request ) );
			$response_code = wp_remote_retrieve_response_code( $request );

			if ( 200 === (int) $response_code ) {

				$url = remove_query_arg( array( 'affwp_action', 'current_page_url', 'payouts_service_change_payout_method_nonce' ) );

				$url = add_query_arg( array(
					'affwp_notice' => 'change-payout-method',
					'email'        => urlencode( $response->email ),
				), $url );

				wp_redirect( $url ); exit;

			} else {

				$this->errors->add( 'change_payout_method_error', __( $response->message, 'affiliate-wp' ) );

			}

		}

	}

	/**
	 * Checks if the affiliate email is registered on the Payouts Service.
	 *
	 * @since 2.4
	 *
	 * @param string $email Affiliate email address.
	 * @return false|string Registration status if an account was found, otherwise false.
	 */
	private function check_registration_status( $email ) {

		$headers = affwp_get_payouts_service_http_headers();

		$api_params = array(
			'email'         => $email,
			'affwp_version' => AFFILIATEWP_VERSION,
		);

		$args = array(
			'body'      => $api_params,
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => false,
		);

		$request = wp_remote_get( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/account/validate-email', $args );

		if ( is_wp_error( $request ) ) {

			/* translators: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant */
			$invalid_email_error = sprintf( __( 'Unable to create a %s account at the moment. Try again later.', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME );
			$this->errors->add( 'invalid_email', $invalid_email_error );

		} else {

			$response      = json_decode( wp_remote_retrieve_body( $request ) );
			$response_code = wp_remote_retrieve_response_code( $request );

			if ( 200 === (int) $response_code ) {

				return $response->status;

			} else {

				/* translators: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant */
				$invalid_email_error = sprintf( __( 'Unable to create a %s account at the moment. Try again later.', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME );
				$this->errors->add( 'invalid_email', $invalid_email_error );

			}
		}

		return false;
	}

	/**
	 * Displays the payouts service notice on the affiliate area.
	 *
	 * @since 2.4
	 *
	 * @param int $affiliate_id Affiliate ID.
	 * @return void
	 */
	public function affiliate_area_notice( $affiliate_id ) {

		if ( ! $this->is_service_enabled() ) {
			return;
		}

		$payouts_service_account_meta = affwp_get_affiliate_meta( $affiliate_id, 'payouts_service_account', true );
		$payouts_service_notice       = affiliate_wp()->settings->get( 'payouts_service_notice', '' );

		if ( ! $payouts_service_account_meta && $payouts_service_notice ) : ?>
			<p class="affwp-notice payouts-service-notice"><?php echo wp_kses_post( nl2br( $payouts_service_notice ) ); ?></p>
		<?php endif;
	}

	/**
	 * Determines whether the payouts service is enabled and configured.
	 *
	 * @since 2.6.1
	 *
	 * @return bool True if the service is enabled and configured, otherwise false.
	 */
	public function is_service_enabled() {
		$enable_payouts_service = affiliate_wp()->settings->get( 'enable_payouts_service', false );
		$access_key             = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );
		$vendor_id              = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
		$connection_status      = affiliate_wp()->settings->get( 'payouts_service_connection_status', '' );

		if ( 'active' === $connection_status && ( $enable_payouts_service && $access_key && $vendor_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Prints errors.
	 *
	 * @since 2.4
	 */
	public function print_errors() {

		$errors = $this->get_errors();

		if ( empty( $errors ) ) {
			return;
		}

		echo '<div class="affwp-errors">';

		foreach ( $this->get_errors() as $error ) {

			echo '<p class="affwp-error">' . wp_kses_post( $error ) . '</p>';

		}

		echo '</div>';

	}

	/**
	 * Retrieves any errors.
	 *
	 * @since 2.4
	 *
	 * @return array Array of error messages.
	 */
	public function get_errors() {

		if ( empty( $this->errors ) ) {
			return array();
		}

		return $this->errors->get_error_messages();

	}

	/**
	 * Retrieves any error codes.
	 *
	 * @since 2.4
	 *
	 * @return array Error codes.
	 */
	public function get_error_codes() {
		if ( empty( $this->errors ) ) {
			return array();
		}

		return $this->errors->get_error_codes();
	}
}
