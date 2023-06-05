<?php
/**
 * Customer Functions
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.2
 */

/**
 * Retrieves the customer object.
 *
 * @since 2.2
 *
 * @param int|\AffWP\Customer $creative Customer ID or object.
 * @return \AffWP\Customer|false Customer object, otherwise false.
 */
function affwp_get_customer( $customer = null ) {

	if ( is_object( $customer ) && isset( $customer->customer_id ) ) {
		$customer_id = $customer->customer_id;
	} elseif( is_numeric( $customer ) ) {
		$customer_id = absint( $customer );
	} elseif( is_string( $customer ) && is_email( $customer ) ) {
		$customer_id = affiliate_wp()->customers->get_column_by( 'customer_id', 'email', $customer );

		if ( ! $customer_id ) {
			return false;
		}
	} else {
		return false;
	}

	return affiliate_wp()->customers->get_object( $customer_id );
}

/**
 * Adds a new customer record to the database.
 *
 * @since 2.2
 *
 * @param array $data {
 *     Optional. Arguments for setting up the customer record. Default empty array.
 *
 *     @type string       $first_name     First name for the customer.
 *     @type string       $last_name      Last  anme for the customer.
 *     @type string       $email          Email address for the customer.
 *     @type int          $affiliate_id   ID of the affiliate that generated this customer.
 *     @type int          $user_id        ID of the user to associate with the customer.
 *     @type string       $date_created   The date this customer was created in Y-m-d H:i:s format.
 * }
 * @return int|false ID of the newly-created customer, otherwise false.
 */
function affwp_add_customer( $data = array() ) {

	if ( $customer_id = affiliate_wp()->customers->add( $data ) ) {
		return $customer_id;
	}

	return false;

}

/**
 * Updates a customer record.
 *
 * @since 2.2
 *
 * @param array $data Customer data to update. Default empty array. Passing a `customer_id`
 *                    value is required.
 * @return bool True if the customer was updated, otherwise false.
 */
function affwp_update_customer( $data = array() ) {

	if ( empty( $data['customer_id'] )
		|| ( ! $customer = affwp_get_customer( $data['customer_id'] ) )
	) {
		return false;
	}

	if ( affiliate_wp()->customers->update( $customer->ID, $data, '', 'customer' ) ) {
		return true;
	}

	return false;

}

/**
 * Deletes a customer record.
 *
 * @since 2.2
 *
 * @param \AffWP\Customer|int Customer ID or object.
 * @return bool True if the customer was successfully deleted, otherwise false.
 */
function affwp_delete_customer( $customer ) {

	if ( ! $customer = affwp_get_customer( $customer ) ) {
		return false;
	}

	return affiliate_wp()->customers->delete( $customer->ID, 'customer' );
}

/**
 * Retrieves a customer by a given field and value.
 *
 * @since 2.7
 *
 * @param string $field Customer object field.
 * @param mixed  $value Field value.
 * @return \AffWP\Customer|\WP_Error Customer object if found, otherwise a WP_Error object.
 */
function affwp_get_customer_by( $field, $value ) {
	$result = affiliate_wp()->customers->get_by( $field, $value );

	if ( is_object( $result ) ) {
		$customer = affwp_get_customer( intval( $result->customer_id ) );
	} else {
		$customer = new \WP_Error(
			'invalid_customer_field',
			sprintf( 'No customer could be retrieved with a(n) \'%1$s\' field value of %2$s.', $field, $value )
		);
	}

	return $customer;
}
