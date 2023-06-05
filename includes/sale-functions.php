<?php
/**
 * Sale Functions
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

/**
 * Retrieves a sales record.
 *
 * @since 2.5
 *
 * @param int|\AffWP\Referral\Sale $referral Referral ID or object.
 * @return \AffWP\Referral\Sale|false Commission object if it exists, otherwise false.
 */
function affwp_get_sale( $referral = null ) {

	if ( is_object( $referral ) && isset( $referral->referral_id ) ) {
		$referral_id = $referral->referral_id;
	} elseif ( is_numeric( $referral ) ) {
		$referral_id = absint( $referral );
	} else {
		return false;
	}

	return affiliate_wp()->referrals->sales->get_object( $referral_id );
}

/**
 * Gets the cache key for sync totals.
 *
 * @since 2.5
 *
 * @param string $context Context (integration) to retrieve the sales referrals count cache key.
 * @return string The sync totals cache key if `$context` is not empty, otherwise an empty string.
 */
function affwp_get_sales_referrals_counts_cache_key( $context ) {
	$key = '';

	if ( ! empty( $context ) ) {
		$key = "{$context}_sales_referrals_counts";
	}

	return $key;
}

/**
 * Retrieves a sale by a given field and value.
 *
 * @since 2.7
 *
 * @param string $field Sale object field.
 * @param mixed  $value Field value.
 * @return \AffWP\Referral\Sale|\WP_Error Sale object if found, otherwise a WP_Error object.
 */
function affwp_get_sale_by( $field, $value ) {
	$result = affiliate_wp()->referrals->sales->get_by( $field, $value );

	if ( is_object( $result ) ) {
		$sale = affwp_get_sale( intval( $result->referral_id ) );
	} else {
		$sale = new \WP_Error(
			'invalid_sale_field',
			sprintf( 'No sale could be retrieved with a(n) \'%1$s\' field value of %2$s.', $field, $value )
		);
	}

	return $sale;
}
