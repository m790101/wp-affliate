<?php
/**
 * Customer Meta Functions
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.2
 */

/**
 * Retrieves customer meta.
 *
 * @since 2.2
 *
 * @param int    $customer_id Customer ID.
 * @param string $meta_key    The meta key to retrieve.
 * @param bool   $single      Whether to return a single value.
 * @return mixed Will be an array if `$single` is false. Will be value of meta data field if `$single` is true.
 */
function affwp_get_customer_meta( $customer_id = 0, $meta_key = '', $single = false ) {
	return affiliate_wp()->customer_meta->get_meta( $customer_id, $meta_key, $single );
}

/**
 * Adds customer meta.
 *
 * @since 2.2
 *
 * @param int    $customer_id Customer ID.
 * @param string $meta_key    Metadata key.
 * @param mixed  $meta_value  Metadata value.
 * @param bool   $unique      Optional, default is false. Whether the same key should not be added.
 * @return bool False for failure. True for success.
 */
function affwp_add_customer_meta( $customer_id = 0, $meta_key = '', $meta_value = '', $unique = false ) {
	return affiliate_wp()->customer_meta->add_meta( $customer_id, $meta_key, $meta_value, $unique );
}

/**
 * Updates customer meta.
 *
 * @since 2.2
 *
 * @param int    $customer_id Customer ID.
 * @param string $meta_key    Metadata key.
 * @param mixed  $meta_value  Metadata value.
 * @param mixed  $prev_value  Optional. Previous value to check before removing.
 * @return bool False on failure, true if success.
 */
function affwp_update_customer_meta( $customer_id = 0, $meta_key = '', $meta_value = '', $prev_value = '' ) {
	return affiliate_wp()->customer_meta->update_meta( $customer_id, $meta_key, $meta_value, $prev_value );
}

/**
 * Deletes customer meta.
 *
 * @since 2.2
 *
 * @param int    $customer_id Customer ID.
 * @param string $meta_key    Metadata key.
 * @param mixed  $meta_value  Metadata value.
 * @return bool False for failure. True for success.
 */
function affwp_delete_customer_meta( $customer_id = 0, $meta_key = '', $meta_value = '' ) {
	return affiliate_wp()->customer_meta->delete_meta( $customer_id, $meta_key, $meta_value );
}
