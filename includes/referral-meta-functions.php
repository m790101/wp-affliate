<?php
/**
 * Referral Meta Functions
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

/**
 * Retrieves meta from a referral.
 *
 * @since 2.4
 *
 * @param int    $referral_id Referral ID.
 * @param string $meta_key    Meta key.
 * @param bool   $single      Whether to return a single value for the given meta key.
 * @return array|mixed An array of values if `$single` is false, otherwise a meta data value.
 */
function affwp_get_referral_meta( $referral_id, $meta_key = '', $single = false ) {
	return affiliate_wp()->referral_meta->get_meta( $referral_id, $meta_key, $single );
}

/**
 * Adds meta to a referral.
 *
 * @since 2.4
 *
 * @param int    $referral_id Referral ID.
 * @param string $meta_key    Meta key.
 * @param mixed  $meta_value  Metadata value.
 * @param bool   $unique      Optional. Whether the same key should not be added. Default false.
 * @return bool True on success, otherwise false.
 */
function affwp_add_referral_meta( $referral_id, $meta_key = '', $meta_value, $unique = false ) {
	return affiliate_wp()->referral_meta->add_meta( $referral_id, $meta_key, $meta_value, $unique );
}

/**
 * Updates referral meta.
 *
 * @since 2.4
 *
 * @param int    $referral_id Referral ID.
 * @param string $meta_key    Meta key.
 * @param mixed  $meta_value  Metadata value.
 * @param mixed  $prev_value  Optional. Previous value to check before removing. Default empty.
 * @return bool True on success, otherwise false.
 */
function affwp_update_referral_meta( $referral_id, $meta_key = '', $meta_value, $prev_value = '' ) {
	return affiliate_wp()->referral_meta->update_meta( $referral_id, $meta_key, $meta_value, $prev_value );
}

/**
 * Deletes referral meta.
 *
 * @since 2.4
 *
 * @param int    $referral_id Referral ID.
 * @param string $meta_key    Meta key.
 * @param mixed  $meta_value  Metadata value.
 * @return bool True if the deletion was succesful, otherwise false.
 */
function affwp_delete_referral_meta( $referral_id, $meta_key = '', $meta_value = '' ) {
	return affiliate_wp()->referral_meta->delete_meta( $referral_id, $meta_key, $meta_value );
}
