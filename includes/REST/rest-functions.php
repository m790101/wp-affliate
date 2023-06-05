<?php
/**
 * REST: Functions
 *
 * @package     AffiliateWP
 * @subpackage  REST
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

/**
 * Retrieves a REST consumer object.
 *
 * @since 1.9
 *
 * @param int|\AffWP\REST\Consumer $consumer Consumer ID or object.
 * @return \AffWP\REST\Consumer|false Consumer object, otherwise false.
 */
function affwp_get_rest_consumer( $consumer = null ) {

	if ( is_object( $consumer ) && isset( $consumer->consumer_id ) ) {
		$consumer_id = $consumer->consumer_id;
	} elseif ( is_numeric( $consumer ) ) {
		$consumer_id = absint( $consumer );
	} elseif ( is_string( $consumer ) ) {
		if ( $user = get_user_by( 'login', $consumer ) ) {
			$consumer_id = affiliate_wp()->REST->consumers->get_column_by( 'consumer_id', 'user_id', $user->ID );

			if ( ! $consumer_id ) {
				return false;
			}
		} else {
			return false;
		}
	} else {
		return false;
	}

	return affiliate_wp()->REST->consumers->get_object( $consumer_id );
}

/**
 * Generates a random hash.
 *
 * Note: This is primary used in the REST component and should not be used by itself.
 * It's used to re-hash already-hashed tokens used for REST authentication.
 *
 * @since 1.9
 *
 * @return string Random hash. If openssl_random_pseudo_bytes() is available, bin2hex() is used,
 *                otherwise sha1().
 */
function affwp_rand_hash() {
	if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
		return bin2hex( openssl_random_pseudo_bytes( 20 ) );
	} else {
		return sha1( wp_rand() );
	}
}

/**
 * Generates a random hash for use with generating REST authentication tokens.
 *
 * @since 1.9
 *
 * @param string $data         Input data.
 * @param string $key          Key.
 * @param bool   $add_auth_key Optional. Whether to append the AUTH_KEY to `$data`.
 *                             Default true.
 * @return false|string Hashed string or false.
 */
function affwp_auth_hash( $data, $key, $add_auth_key = true ) {
	if ( true === $add_auth_key ) {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';

		$data = $data . $auth_key;
	}
	return hash_hmac( 'md5', $data, $key );
}

/**
 * Registers a new REST field on an existing AffiliateWP object type.
 *
 * Intended to be forward-compatible with register_rest_field().
 *
 * @since 1.9.5
 *
 * @param string $object_type Object the field is being registered to. Accepts 'affiliate', 'creative',
 *                            'payout', 'referral', 'visit', or the above prefixed with 'affwp_'. Fields
 *                            are actually registered with the prefixed object types, e.g. 'affwp_affiliate'.
 * @param string $field_name  The attribute name.
 * @param array  $args {
 *     Optional. An array of arguments used to handle the registered field.
 *
 *     @type string|array|null $get_callback    Optional. The callback function used to retrieve the field
 *                                              value. Default is 'null', the field will not be returned in
 *                                              the response.
 *     @type string|array|null $schema          Optional. The callback function used to create the schema for
 *                                              this field. Default is 'null', no schema entry will be returned.
 * }
 * @return false|void False if the REST API is not available (<4.4), otherwise void.
 */
function affwp_register_rest_field( $object_type, $field_name, $args = array() ) {
	if ( version_compare( $GLOBALS['wp_version'], '4.4', '<' ) ) {
		return false;
	}

	switch ( $object_type ) {
		case 'affiliate' :
		case 'affwp_affiliate' :
			affiliate_wp()->affiliates->REST->register_field( $field_name, $args );
			break;
		case 'creative' :
		case 'affwp_creative' :
			affiliate_wp()->creatives->REST->register_field( $field_name, $args );
			break;
		case 'payout' :
		case 'affwp_payout' :
			affiliate_wp()->affiliates->payouts->REST->register_field( $field_name, $args );
			break;
		case 'referral' :
		case 'affwp_referral' :
			affiliate_wp()->referrals->REST->register_field( $field_name, $args );
			break;
		case 'visit' :
		case 'affwp_visit' :
			affiliate_wp()->visits->REST->register_field( $field_name, $args );
			break;
		default : break;
	}
}

/**
 * Validates a rest_id value.
 *
 * @since 2.2.2
 *
 * @param string $rest_id Potential REST ID to validate.
 * @return bool True of the rest_id value is syntactically valid, otherwise false.
 */
function affwp_validate_rest_id( $rest_id ) {
	$valid = false;

	if ( false !== strpos( $rest_id, ':' ) ) {
		$valid = true;
	}

	return $valid;
}


/**
 * Dispatches a request to a given REST endpoint.
 *
 * @since 2.6
 *
 * @param string $method The method to use for this request.
 * @param string $route  The route to call
 * @param array  $params Optional. Parameters to send along with the request. Default empty array.
 * @return mixed The API request's response, otherwise a WP_Error object.
 */
function affwp_rest_request( $method, $route, $params = array() ) {

	$request = new \WP_REST_Request( $method, $route );
	$request->set_query_params( $params );

	$validate_or_error = $request->has_valid_params();

	if ( is_wp_error( $validate_or_error ) ) {
		return $validate_or_error;
	}

	$response = rest_do_request( $request );
	$server   = rest_get_server();
	$data     = $server->response_to_data( $response, false );

	return $data;
}

/**
 * Dispatches a GET request to a given REST endpoint.
 *
 * @since 2.6
 *
 * @see affwp_rest_request()
 *
 * @param string $route  The route to call
 * @param array  $params Optional. Parameters to send along with the request. Default empty array.
 * @return mixed The API request's response, otherwise a WP_Error object.
 */
function affwp_rest_get( $route, $params = array() ) {
	return affwp_rest_request( 'GET', $route, $params );
}

/**
 * Dispatches a PUT request to a given REST endpoint.
 *
 * @since 2.6
 *
 * @see affwp_rest_request()
 *
 * @param string $route  The route to call
 * @param array  $params Optional. Parameters to send along with the request. Default empty array.
 * @return mixed The API request's response, otherwise a WP_Error object.
 */
function affwp_rest_put( $route, $params = array() ) {
	return affwp_rest_request( 'PUT', $route, $params );
}

/**
 * Dispatches a POST request to a given REST endpoint.
 *
 * @since 2.6
 *
 * @see affwp_rest_request()
 *
 * @param string $route  The route to call
 * @param array  $params Optional. Parameters to send along with the request. Default empty array.
 * @return mixed The API request's response, otherwise a WP_Error object.
 */
function affwp_rest_post( $route, $params = array() ) {
	return affwp_rest_request( 'POST', $route, $params );
}

/**
 * Retrieves a REST consumer by a given field and value.
 *
 * @since 2.7
 *
 * @param string $field REST consumer object field.
 * @param mixed  $value Field value.
 * @return \AffWP\REST\Consumer|\WP_Error Consumer object if found, otherwise a WP_Error object.
 */
function affwp_get_rest_consumer_by( $field, $value ) {
	$result = affiliate_wp()->REST->consumers->get_by( $field, $value );

	if ( is_object( $result ) ) {
		$consumer = affwp_get_rest_consumer( intval( $result->consumer_id ) );
	} else {
		$consumer = new \WP_Error(
			'invalid_rest_consumer_field',
			sprintf( 'No REST consumer could be retrieved with a(n) \'%1$s\' field value of %2$s.', $field, $value )
		);
	}

	return $consumer;
}
