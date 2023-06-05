<?php
/**
 * REST: Authentication
 *
 * @package     AffiliateWP
 * @subpackage  REST
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

namespace AffWP\REST;

/**
 * Implements API key authentication for AffiliateWP REST endpoints.
 *
 * @since 1.9
 */
final class Authentication {

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since  1.9
	 */
	public function __construct() {
		add_filter( 'determine_current_user', array( $this, 'authenticate' ) );
	}

	/**
	 * Authenticates a user using Basic Auth.
	 *
	 * User is the public key, password is the token.
	 *
	 * @access public
	 * @since  1.9
	 *
	 * @param int $user_id ID for the current user.
	 * @return int API consumer user ID if authenticated.
	 */
	public function authenticate( $user_id ) {

		if ( ! empty( $user_id ) || empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			return $user_id;
		}

		$public_key = $_SERVER['PHP_AUTH_USER'];
		$token      = $_SERVER['PHP_AUTH_PW'];

		// Prevent recursion.
		remove_filter( 'determine_current_user', array( $this, 'authenticate' ), 20 );

		$consumer = affwp_get_rest_consumer_by( 'public_key', $public_key );

		if ( ! is_wp_error( $consumer ) ) {
			if ( hash_equals( affwp_auth_hash( $public_key, $consumer->secret_key, false ), $token ) ) {
				/**
				 * Fires immediately after a REST consumer has been successfully authenticated.
				 *
				 * @since 2.2.2
				 *
				 * @param \AffWP\REST\Consumer $consumer REST Consumer object.
				 */
				do_action( 'affwp_rest_authenticate_consumer', $consumer );

				return $consumer->user_id;
			}
		}

		return $user_id;
	}
}
