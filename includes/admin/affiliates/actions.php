<?php
/**
 * Admin: Affiliates Action Callbacks
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Affiliates
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Process the add affiliate request
 *
 * @since 1.2
 * @return void|false
 */
function affwp_process_add_affiliate( $data ) {

	$errors = array();

	if ( empty( $data['user_id'] ) && empty( $data['user_name'] ) ) {
		return false;
	}

	if ( ! is_admin() ) {
		return false;
	}

	// Trim username, email, payment email fields.
	foreach ( array( 'user_name', 'user_email', 'payment_email' ) as $key ) {
		if ( isset( $data[ $key ] ) ) {
			$data[ $key ] = trim( $data[ $key ] );
		}
	}

	if ( ! current_user_can( 'manage_affiliates' ) ) {
		wp_die( __( 'You do not have permission to manage affiliates', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	if ( ! username_exists( $data['user_name'] ) && is_numeric( $data['user_name'] ) ) {
		$errors[ 'invalid_username_numeric' ] = __( 'Invalid user login name. User login name must include at least one letter', 'affiliate-wp' );
	}

	if ( ! username_exists( $data['user_name'] ) && mb_strlen( $data['user_name'] ) < 4 || mb_strlen( $data['user_name'] ) > 60 ) {
		$errors[ 'invalid_username'] = __( 'Invalid user login name. Must be between 4 and 60 characters.', 'affiliate-wp' );
	}

	if ( ! username_exists( $data['user_name'] ) && ! is_email( $data['user_email' ] ) ) {
		$errors[ 'invalid_email'] = __( 'Invalid user email', 'affiliate-wp' );
	}

	if ( ! empty( $data['payment_email'] ) && ! is_email( $data['payment_email' ] ) ) {
		$errors[ 'invalid_payment_email'] = __( 'Invalid payment email', 'affiliate-wp' );
	}

	if ( empty( $errors ) ) {

		// If an an email address was submitted instead of an existing user, set the data so that affwp will create the user.
		if ( ! username_exists( $data['user_name'] ) && is_email( $data['user_name'] ) ) {
			$data['user_email'] = $data['user_name'];
			unset( $data['user_name'] );
		}

		$data['dynamic_coupon'] = isset( $data['dynamic_coupon'] ) ? $data['dynamic_coupon'] : '';

		$affiliate_id = affwp_add_affiliate( $data );

		if ( $affiliate_id ) {
			wp_safe_redirect( affwp_admin_url( 'affiliates', array( 'affwp_notice' => 'affiliate_added' ) ) );
			exit;
		} else {
			wp_safe_redirect( affwp_admin_url( 'affiliates', array( 'affwp_notice' => 'affiliate_added_failed' ) ) );
			exit;
		}

	} else {

		if( isset( $errors ) ) {

			echo '<div class="error">';
			foreach( $errors as $error ) {
				echo '<p>' . $error . '</p>';
			}
			echo '</div>';

		}

		return false;
	}

}
add_action( 'affwp_add_affiliate', 'affwp_process_add_affiliate' );

/**
 * Add affiliate meta
 *
 * @since 2.0
 * @return void
 */
function affwp_process_add_affiliate_meta( $affiliate_id, $args ) {

	// add notes against affiliate
	$notes = ! empty( $args['notes'] ) ? wp_kses_post( $args['notes'] ) : '';

	if ( $notes ) {
		affwp_update_affiliate_meta( $affiliate_id, 'notes', $notes );
	}

}
add_action( 'affwp_insert_affiliate', 'affwp_process_add_affiliate_meta', 10, 2 );

/**
 * Process affiliate deletion requests
 *
 * @since 1.2
 * @param $data array
 * @return void
 */
function affwp_process_affiliate_deletion( $data ) {

	if ( ! is_admin() ) {
		return;
	}

	if ( ! current_user_can( 'manage_affiliates' ) ) {
		wp_die( __( 'You do not have permission to delete affiliate accounts', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	if ( ! wp_verify_nonce( $data['affwp_delete_affiliates_nonce'], 'affwp_delete_affiliates_nonce' ) ) {
		wp_die( __( 'Security check failed', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	if ( empty( $data['affwp_affiliate_ids'] ) || ! is_array( $data['affwp_affiliate_ids'] ) ) {
		wp_die( __( 'No affiliate IDs specified for deletion', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 400 ) );
	}

	$to_delete        = array_map( 'absint', $data['affwp_affiliate_ids'] );
	$delete_users_too = isset( $data['affwp_delete_users_too'] ) && current_user_can( 'remove_users' );

	// Snag affiliate user IDs before deleting the affiliate records they correspond to and filter out invalid users.
	// It is possible to have an affiliate with a deleted user. This filters out the users that do not exist.
	$users_to_delete = array();
	if ( true === $delete_users_too ) {
		foreach ( $to_delete as $affiliate_id_to_delete ) {
			$user_id = affwp_get_affiliate_user_id( $affiliate_id_to_delete );
			if ( false !== get_userdata( $user_id ) ) {
				$users_to_delete[] = $user_id;
			}
		}
	}

	// Loop through, and delete affiliates
	foreach ( $to_delete as $affiliate_id ) {
		affwp_delete_affiliate( $affiliate_id, true );
	}

	// Redirect to the core user delete interface to finalize user deletion.
	if ( $delete_users_too && ! empty( $users_to_delete ) ) {
		$url = wp_nonce_url( self_admin_url( 'users.php' ), 'bulk-users' );

		$action = is_multisite() ? 'remove' : 'delete';

		$url = add_query_arg( 'action', $action, $url );

		if ( count( $to_delete ) === 1 ) {
			$url = add_query_arg( 'user', $users_to_delete[0], $url );
		} else {
			$url = add_query_arg( 'users', $users_to_delete, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	wp_safe_redirect( affwp_admin_url( 'affiliates', array( 'affwp_notice' => 'affiliate_deleted' ) ) );
	exit;

}
add_action( 'affwp_delete_affiliates', 'affwp_process_affiliate_deletion' );

/**
 * Process the update affiliate request
 *
 * @since 1.2
 * @return void
 */
function affwp_process_update_affiliate( $data ) {

	if ( empty( $data['affiliate_id'] ) ) {
		return false;
	}

	if ( ! is_admin() ) {
		return false;
	}

	if ( ! current_user_can( 'manage_affiliates' ) ) {
		wp_die( __( 'You do not have permission to manage affiliates', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	if ( affwp_update_affiliate( $data ) ) {
		wp_safe_redirect( affwp_admin_url( 'affiliates', array( 'action' => 'edit_affiliate', 'affwp_notice' => 'affiliate_updated', 'affiliate_id' => $data['affiliate_id'] ) ) );
		exit;
	} else {
		wp_safe_redirect( affwp_admin_url( 'affiliates', array( 'affwp_notice' => 'affiliate_update_failed' ) ) );
		exit;
	}

}
add_action( 'affwp_update_affiliate', 'affwp_process_update_affiliate' );

/**
 * Process the affiliate moderation request
 *
 * @since 1.7
 * @return void
 */
function affwp_process_affiliate_moderation( $data ) {

	if ( empty( $data['affiliate_id'] ) ) {
		return false;
	}

	if ( ! is_admin() ) {
		return false;
	}

	if ( ! current_user_can( 'manage_affiliates' ) ) {
		wp_die( __( 'You do not have permission to manage affiliates', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	if ( ! wp_verify_nonce( $data['affwp_moderate_affiliates_nonce'], 'affwp_moderate_affiliates_nonce' ) ) {
		wp_die( __( 'Security check failed', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}


	$status = isset( $data['affwp_accept'] ) ? 'active' : 'rejected';
	$notice = isset( $data['affwp_accept'] ) ? 'affiliate_accepted' : 'affiliate_rejected';

	if( 'rejected' == $status ) {

		$reason = ! empty( $data['affwp_rejection_reason'] ) ? wp_kses_post( $data['affwp_rejection_reason'] ) : false;

		if( $reason ) {

			affwp_add_affiliate_meta( $data['affiliate_id'], '_rejection_reason', $reason, true );

		}

	}

	if ( 'active' === $status && isset( $data['dynamic_coupon'] ) ) {

		$coupon = affwp_get_dynamic_affiliate_coupons( $data['affiliate_id'], false );

		if ( empty( $coupon ) ) {

			$coupon_added = affiliate_wp()->affiliates->coupons->add( array( 'affiliate_id' => $data['affiliate_id'] ) );

			if ( false === $coupon_added ) {
				affiliate_wp()->utils->log( sprintf( 'Coupon could not be added for affiliate #%1$d.', $data['affiliate_id'] ), $data );
			}
		}

	}

	if ( affwp_set_affiliate_status( $data['affiliate_id'], $status ) ) {
		wp_safe_redirect( affwp_admin_url( 'affiliates', array( 'affwp_notice' => $notice, 'affiliate_id' => $data['affiliate_id'] ) ) );
		exit;
	} else {
		wp_safe_redirect( affwp_admin_url( 'affiliates', array( 'affwp_notice' => 'affiliate_update_failed' ) ) );
		exit;
	}

}
add_action( 'affwp_moderate_affiliate', 'affwp_process_affiliate_moderation' );
