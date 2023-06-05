<?php
/**
 * Payout Functions
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

/**
 * Retrieves a payout object.
 *
 * @since 1.9
 *
 * @param int|\AffWP\Affiliate\Payout $payout Payout ID or object.
 * @return \AffWP\Affiliate\Payout|false Payout object if found, otherwise false.
 */
function affwp_get_payout( $payout = 0 ) {

	/**
	 * Filters the payout ID or object before it is retrieved.
	 *
	 * Passing a non-null value in the hook callback will effectively preempt retrieving
	 * the payout from the database, returning the passed value instead.
	 *
	 * @since 2.2.2
	 *
	 * @param null                        $payout_before Value to short circuit retrieval of the payout.
	 * @param int|\AffWP\Affiliate\Payout $payout        Payout ID or object passed to affwp_get_payout().
	 */
	$payout_before = apply_filters( 'affwp_get_payout_before', null, $payout );

	if ( null !== $payout_before ) {
		return $payout_before;
	}

	if ( is_object( $payout ) && isset( $payout->payout_id ) ) {
		$payout_id = $payout->payout_id;
	} elseif ( is_numeric( $payout ) ) {
		$payout_id = absint( $payout );
	} else {
		return false;
	}

	return affiliate_wp()->affiliates->payouts->get_object( $payout_id );
}

/**
 * Adds a payout record.
 *
 * @since 1.9
 *
 * @param array $args {
 *     Optional. Arguments for adding a new payout record. Default empty array.
 *
 *     @type int          $affiliate_id  Affiliate ID.
 *     @type int|array    $referrals     Referral ID or array of IDs.
 *     @type string       $amount        Payout amount.
 *     @type string       $payout_method Payout method.
 *     @type string       $status        Payout status. Default 'paid'.
 *     @type string|array $date          Payout date.
 * }
 * @return int|false The ID for the newly-added payout, otherwise false.
 */
function affwp_add_payout( $args = array() ) {

	if ( empty( $args['referrals'] ) || empty( $args['affiliate_id'] ) ) {
		return false;
	}

	if ( $payout = affiliate_wp()->affiliates->payouts->add( $args ) ) {
		return $payout;
	}

	return false;
}

/**
 * Deletes a payout.
 *
 * @since 1.9
 *
 * @param int|\AffWP\Affiliate\Payout $payout Payout ID or object.
 * @return bool True if the payout was successfully deleted, otherwise false.
 */
function affwp_delete_payout( $payout ) {
	if ( ! $payout = affwp_get_payout( $payout ) ) {
		return false;
	}

	// Handle updating paid referrals to unpaid.
	if ( $payout && in_array( $payout->status, array( 'paid', 'processing' ) ) ) {
		$referrals = affiliate_wp()->affiliates->payouts->get_referral_ids( $payout );

		foreach ( $referrals as $referral_id ) {
			if ( 'paid' == affwp_get_referral_status( $referral_id ) ) {
				affwp_set_referral_status( $referral_id, 'unpaid' );
			}
		}
	}

	if ( affiliate_wp()->affiliates->payouts->delete( $payout->ID, 'payout' ) ) {
		/**
		 * Fires immediately after a payout has been deleted.
		 *
		 * @since 1.9
		 *
		 * @param int $payout_id Payout ID.
		 */
		do_action( 'affwp_delete_payout', $payout->ID );

		return true;
	}

	return false;
}

/**
 * Retrieves the referrals associated with a payout.
 *
 * @since 1.9
 *
 * @param int|\AffWP\Affiliate\Payout $payout Payout ID or object.
 * @return array|false List of referral objects associated with the payout, otherwise false.
 */
function affwp_get_payout_referrals( $payout = 0 ) {
	if ( ! $payout = affwp_get_payout( $payout ) ) {
		return false;
	}

	$referrals = affiliate_wp()->affiliates->payouts->get_referral_ids( $payout );

	return array_map( 'affwp_get_referral', $referrals );
}

/**
 * Retrieves the status label for a payout.
 *
 * @since 1.6
 * @since 2.6.1 The `$payout` parameter was renamed to `$payout_or_status` and now also accepts
 *              a payout status.
 *
 * @param int|\AffWP\Affiliate\Payout|string $payout_or_status Payout ID, object, or status.
 * @return string|false The localized version of the payout status label, otherwise false.
 */
function affwp_get_payout_status_label( $payout_or_status ) {

	if ( is_string( $payout_or_status ) ) {
		$payout = null;
		$status = $payout_or_status;
	} else {
		$payout = affwp_get_payout( $payout_or_status );

		if ( isset( $payout->status ) ) {
			$status = $payout->status;
		} else {
			return false;
		}
	}

	$statuses = affwp_get_payout_statuses();
	$label    = array_key_exists( $status, $statuses ) ? $statuses[ $status ] : $statuses['paid'];

	/**
	 * Filters the payout status label.
	 *
	 * @since 1.9
	 * @since 2.6.1 Added the `$status` parameter
	 *
	 * @param string                  $label  A localized version of the payout status label.
	 * @param \AffWP\Affiliate\Payout $payout Payout object.
	 * @param string                  $status Payout status.
	 */
	return apply_filters( 'affwp_payout_status_label', $label, $payout, $status );
}

/**
 * Retrieves the list of payout statuses and corresponding labels.
 *
 * @since 2.6.1
 *
 * @return array Key/value pairs of statuses where key is the status and the value is the label.
 */
function affwp_get_payout_statuses() {
	return array(
		'processing' => _x( 'Processing', 'payout', 'affiliate-wp' ),
		'paid'       => _x( 'Paid', 'payout', 'affiliate-wp' ),
		'failed'     => __( 'Failed', 'affiliate-wp' ),
	);
}

/**
 * Retrieves the list of payout methods and corresponding labels.
 *
 * @since 2.4
 *
 * @return array Key/value pairs of payout methods where key is the payout method and the value is the label.
 */
function affwp_get_payout_methods() {

	$payout_methods = array(
		'manual' => __( 'Manual Payout', 'affiliate-wp' ),
	);

	/**
	 * Filters the payout methods.
	 *
	 * @since 2.4
	 *
	 * @param array $payout_methods Payout methods.
	 */
	return apply_filters( 'affwp_payout_methods', $payout_methods );
}

/**
 * Retrieves the label for a payout method.
 *
 * @since 2.4
 *
 * @param string $payout_method Optional. Payout method. Default empty.
 * @return string $label The localized version of the payout method label. If the payout method
 *                       isn't registered, the default 'Manual Payout' label will be returned.
 */
function affwp_get_payout_method_label( $payout_method = '' ) {

	$payout_methods = affwp_get_payout_methods();
	$label          = array_key_exists( $payout_method, $payout_methods ) ? $payout_methods[ $payout_method ] : $payout_methods['manual'];

	/**
	 * Filters the payout method label.
	 *
	 * @since 2.4
	 *
	 * @param string $label         A localized version of the payout method label.
	 * @param string $payout_method Payout method.
	 */
	return apply_filters( 'affwp_payout_method_label', $label, $payout_method );
}

/**
 * Checks if a given payout method is enabled.
 *
 * @since 2.4
 *
 * @param string $payout_method Payout method.
 * @return bool $enabled True if the payout method is enabled. False otherwise.
 */
function affwp_is_payout_method_enabled( $payout_method ) {

	$payout_methods = affwp_get_payout_methods();
	$enabled        = array_key_exists( $payout_method, $payout_methods ) ? true : false;

	/**
	 * Filters the payout method enabled boolean.
	 *
	 * @since 2.4
	 *
	 * @param bool   $enabled       True if the payout method is enabled. False otherwise.
	 * @param string $payout_method Payout method.
	 */
	return (bool) apply_filters( 'affwp_is_payout_method_enabled', $enabled, $payout_method );
}

/**
 * Retrieves a list of all enabled payout methods.
 *
 * @since 2.4
 *
 * @return array Enabled payout methods.
 */
function affwp_get_enabled_payout_methods() {

	$enabled_methods = array();

	foreach ( affwp_get_payout_methods() as $payout_method => $label ) {
		if ( affwp_is_payout_method_enabled( $payout_method ) ) {
			$enabled_methods[] = $payout_method;
		}
	}

	return $enabled_methods;
}

/**
 * Retrieves the list of preview payout request failed reasons and corresponding labels.
 *
 * @since 2.4
 *
 * @return array Key/value pairs of reasons where key is the reason and the value is the label.
 */
function affwp_get_preview_payout_request_failed_reasons() {
	$reasons = array(
		'invalid_account'               => __( 'Invalid affiliate account', 'affiliate-wp' ),
		'invalid_ps_account'            => __( 'Invalid Payouts Service account', 'affiliate-wp' ),
		'minimum_payout'                => __( 'Doesn&#8217;t meet the minimum payout amount', 'affiliate-wp' ),
		'no_ps_account'                 => __( 'Hasn&#8217;t created a Payouts Service account', 'affiliate-wp' ),
		'no_ps_payout_method'           => __( 'Hasn&#8217;t submitted payout method on Payouts Service', 'affiliate-wp' ),
		'no_referrals'                  => __( 'No referrals within the specified date range', 'affiliate-wp' ),
		'ps_account_disabled'           => __( 'Account temporarily disabled on the Payouts Service', 'affiliate-wp' ),
		'unable_to_retrieve_ps_account' => __( 'Unable to retrieve Payouts Service account', 'affiliate-wp' ),
		'unable_to_validate_payout'     => __( 'Unable to validate payout on the Payouts Service', 'affiliate-wp' ),
		'user_account_deleted'          => __( 'Affiliate user account deleted', 'affiliate-wp' ),
	);

	/**
	 * Filters the preview payout request failed reasons.
	 *
	 * @since 2.4
	 *
	 * @param array $reasons Array of key/value pairs of reasons.
	 */
	return apply_filters( 'affwp_get_preview_payout_request_failed_reasons', $reasons );
}

/**
 * Retrieves the label for a preview payout request failed reason.
 *
 * @since 2.4
 *
 * @param string $reason Preview payout request failed reason.
 * @return string The localized version of the reason label.
 */
function affwp_get_preview_payout_request_failed_reason_label( $reason ) {

	$reasons = affwp_get_preview_payout_request_failed_reasons();
	$label   = array_key_exists( $reason, $reasons ) ? $reasons[ $reason ] : sanitize_text_field( $reason );

	return $label;
}

/**
 * Validate payout data on the Payouts Service.
 *
 * @since 2.4.2
 *
 * @param array $data Payout data.
 * @return array Validated payout data.
 */
function affwp_validate_payouts_service_payout_data( $data ) {

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

	$request = wp_remote_post( PAYOUTS_SERVICE_URL . '/wp-json/payouts/v1/validate-payout', $args );

	$valid_payout_data   = array();
	$invalid_payout_data = array();

	foreach ( $data as $affiliate_id => $affiliate_data ) {
		$invalid_payout_data[ $affiliate_id ] = 'unable_to_validate_payout';
	}

	if ( ! is_wp_error( $request ) ) {

		$response      = json_decode( wp_remote_retrieve_body( $request ) );
		$response_code = wp_remote_retrieve_response_code( $request );

		if ( 200 === (int) $response_code && $response->status ) {

			$valid_payout_data   = affwp_object_to_array( $response->valid_payout_data );
			$invalid_payout_data = affwp_object_to_array( $response->invalid_payout_data );

		}
	}

	$validated_payout_data = array(
		'valid_payout_data'   => $valid_payout_data,
		'invalid_payout_data' => $invalid_payout_data,
	);

	return $validated_payout_data;
}

/**
 * Determines whether the payouts service is enabled and configured.
 *
 * @since 2.6.1
 *
 * @return bool True if the service is enabled and configured, otherwise false.
 */
function affwp_is_payouts_service_enabled() {
	return affiliate_wp()->affiliates->payouts->service_register->is_service_enabled();
}

/**
 * Retrieve the headers to be sent for HTTP request to the Payouts Service.
 *
 * @since 2.6.8
 *
 * @param bool $add_authorization_header Optional. Whether to return the Authorization header.
 *                                       Default true.
 * @return array HTTP headers.
 */
function affwp_get_payouts_service_http_headers( $add_authorization_header = true ) {

	$headers = array(
		'Payouts-Service-Platform'         => 'affiliatewp',
		'Payouts-Service-Platform-Url'     => site_url(),
		'Payouts-Service-Platform-Version' => AFFILIATEWP_VERSION,
	);

	if ( true === $add_authorization_header ) {
		$vendor_id  = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
		$access_key = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );

		$headers['Authorization'] = 'Basic ' . base64_encode( $vendor_id . ':' . $access_key );
	}

	return $headers;
}

/**
 * Retrieves a payout by a given field and value.
 *
 * @since 2.7
 *
 * @param string $field Payout object field.
 * @param mixed  $value Field value.
 * @return \AffWP\Affiliate\Payout|\WP_Error Payout object if found, otherwise a WP_Error object.
 */
function affwp_get_payout_by( $field, $value ) {
	$result = affiliate_wp()->affiliates->payouts->get_by( $field, $value );

	if ( is_object( $result ) ) {
		$payout = affwp_get_payout( intval( $result->payout_id ) );
	} else {
		$payout = new \WP_Error(
			'invalid_payout_field',
			sprintf( 'No payout could be retrieved with a(n) \'%1$s\' field value of %2$s.', $field, $value )
		);
	}

	return $payout;
}
