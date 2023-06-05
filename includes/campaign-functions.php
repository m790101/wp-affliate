<?php
/**
 * Campaign Functions
 *
 * @package     AffiliateWP
 * @subpackage  Core/Functions
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.7
 */

/**
 * Retrieves a campaign record.
 *
 * @since 2.7
 *
 * @param int|Affiliate_WP_Campaigns_DB $campaign Campaign ID or object.
 *
 * @return \AffWP\Campaign|false Campaign object if it exists, otherwise false.
 */
function affwp_get_campaign( $campaign = null ) {

	if ( is_object( $campaign ) && isset( $campaign->campaign_id ) ) {
		$campaign_id = $campaign->campaign_id;
	} elseif ( is_numeric( $campaign ) ) {
		$campaign_id = absint( $campaign );
	} else {
		return false;
	}

	return affiliate_wp()->campaigns->get_object( $campaign_id );
}

/**
 * Gets the campaign from the affiliate ID and campaign slug.
 *
 * @since 2.7
 *
 * @param int    $affiliate_id The Affiliate ID.
 * @param string $campaign     The campaign slug.
 *
 * @return \AffWP\Campaign|WP_Error The campaign, if it exists. Otherwise WP_Error.
 */
function affwp_get_affiliate_campaign( $affiliate_id, $campaign ) {
	$errors = new WP_Error();
	if ( empty( $affiliate_id ) ) {
		$errors->add( 'invalid_affiliate_id', 'The affiliate ID provided is invalid.' );
	}

	if ( empty( $campaign ) ) {
		$errors->add( 'invalid_campaign_slug', 'The provided campaign slug is invalid.' );
	}

	$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );

	if ( false === $has_errors ) {
		$query = affiliate_wp()->campaigns->get_campaigns( array(
			'hash'   => affwp_get_campaign_hash( $affiliate_id, $campaign ),
			'number' => 1,
		) );

		if ( count( $query ) > 0 ) {
			$result = $query[0];
		} else {
			$errors->add( 'campaign_not_found', 'This campaign could not be found.', array(
				'affiliate_id' => $affiliate_id,
				'campaign'     => $campaign,
			) );
		}
	}

	return isset( $result ) ? $result : $errors;
}

/**
 * Retrieves a campaign hash given the affiliate ID and campaign slug.
 *
 * @since 2.7
 *
 * @param int    $affiliate_id The Affiliate ID.
 * @param string $campaign     The campaign slug.
 *
 * @return string The campaign hash.
 */
function affwp_get_campaign_hash( $affiliate_id, $campaign ) {
	return affwp_get_hash( array( 'affiliate_id' => (int) $affiliate_id, 'campaign' => (string) $campaign ) );
}
