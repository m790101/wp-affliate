<?php
/**
 * Admin: Customers Action Callbacks
 *
 * @package    AffiliateWP
 * @subpackage Admin/Customers
 * @copyright  Copyright (c) 2020, Sandhills Development, LLC
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.5.7
 */

/**
 * Processes the add_customer request.
 *
 * @since 2.5.7
 *
 * @param array $data Data from the Add Customer form.
 * @return void|false
 */
function affwp_process_add_customer( $data ) {

	$errors = array();

	if ( empty( $data['email'] ) ) {
		return false;
	}

	if ( ! is_admin() ) {
		return false;
	}

	if ( ! current_user_can( 'manage_customers' ) ) {
		wp_die( __( 'You do not have permission to manage customers', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	/**
	 * Fires when a new customer is being processed.
	 *
	 * @since 2.5.7
	 *
	 * @param array $data   Data from the Add Customer form, passed by reference.
	 * @param array $errors Errors associated with the process, passed by reference.
	 */
	do_action_ref_array( 'affwp_process_add_customer', array( &$data, &$errors ) );

	if ( empty( $errors ) ) {

		$customer_id = affwp_add_customer( $data );

		if ( $customer_id ) {
			wp_safe_redirect( affwp_admin_url( 'customers', array( 'affwp_notice' => 'customer_added' ) ) );
			exit;
		} else {
			wp_safe_redirect( affwp_admin_url( 'customers', array( 'affwp_notice' => 'customer_added_failed' ) ) );
			exit;
		}

	} else {

		echo '<div class="error">';
		foreach ( $errors as $error ) {
			echo '<p>' . $error . '</p>';
		}
		echo '</div>';

		return false;
	}

}
add_action( 'affwp_add_customer', 'affwp_process_add_customer' );

/**
 * Process the update_customer request.
 *
 * @since 2.5.7
 *
 * @param array $data Data from the Edit Customer form.
 * @return void|false
 */
function affwp_process_update_customer( $data ) {

	if ( empty( $data['customer_id'] ) ) {
		return false;
	}

	if ( ! is_admin() ) {
		return false;
	}

	if ( ! current_user_can( 'manage_customers' ) ) {
		wp_die( __( 'You do not have permission to manage customers', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	/**
	 * Fires when a customer update is being processed.
	 *
	 * @since 2.5.7
	 *
	 * @param array $data Data from the Edit Customer form, passed by reference.
	 */
	do_action_ref_array( 'affwp_process_edit_customer', array( &$data ) );

	if ( affwp_update_customer( $data ) ) {
		wp_safe_redirect( affwp_admin_url( 'customers', array( 'action' => 'edit_customer', 'affwp_notice' => 'customer_updated' ) ) );
		exit;
	} else {
		wp_safe_redirect( affwp_admin_url( 'customers', array( 'affwp_notice' => 'customer_update_failed' ) ) );
		exit;
	}

}
add_action( 'affwp_update_customer', 'affwp_process_update_customer' );
