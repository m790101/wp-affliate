<?php
/**
 * Visits Functions
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Retrieves a visit object.
 *
 * @since 1.9
 *
 * @param int|\AffWP\Visit $visit Visit ID or object.
 * @return \AffWP\Visit|false Visit object, otherwise false.
 */
function affwp_get_visit( $visit = null ) {

	if ( is_object( $visit ) && isset( $visit->visit_id ) ) {
		$visit_id = $visit->visit_id;
	} elseif( is_numeric( $visit ) ) {
		$visit_id = absint( $visit );
	} else {
		return false;
	}

	return affiliate_wp()->visits->get_object( $visit_id );
}

/**
 * Counts the number of visits logged for a given affiliate.
 *
 * @since 1.9
 *
 * @param int|\AffWP\Affiliate $affiliate Optional. Affiliate ID or object. Default is the current affiliate.
 * @param array|string         $date      Optional. Array of date data with 'start' and 'end' key/value pairs,
 *                                        or a timestamp. Default empty array.
 * @return int|false Number of visits, otherwise 0|false.
 */
function affwp_count_visits( $affiliate = 0, $date = array() ) {

	if ( ! $affiliate = affwp_get_affiliate( $affiliate ) ) {
		return 0;
	}

	$args = array(
		'affiliate_id' => $affiliate->ID,
	);

	if( ! empty( $date ) ) {
		$args['date'] = $date;
	}

	return affiliate_wp()->visits->count( $args );

}

/**
 * Deletes a visit record.
 *
 * @since 1.2
 *
 * @param int|\AffWP\Visit $visit Visit ID or object.
 * @return bool True if the visit was successfully deleted, otherwise false.
 */
function affwp_delete_visit( $visit ) {

	if ( ! $visit = affwp_get_visit( $visit ) ) {
		return false;
	}

	if ( affiliate_wp()->visits->delete( $visit->ID, 'visit' ) ) {
		// Decrease the visit count
		affwp_decrease_affiliate_visit_count( $visit->affiliate_id );

		/**
		 * Fires immediately after a visit has been deleted.
		 *
		 * @since 1.2
		 *
		 * @param int $visit_id Visit ID.
		 */
		do_action( 'affwp_delete_visit', $visit->ID );

		return true;

	}

	return false;
}

/**
 * Sanitizes visit a URL.
 *
 * @since 1.7.5
 *
 * @param string $url The URL to sanitize.
 * @return string $url The sanitized URL.
 */
function affwp_sanitize_visit_url( $url ) {
	$original_url = $url;
	$referral_var = affiliate_wp()->tracking->get_referral_var();

	// Remove the referral var
	$url = remove_query_arg( $referral_var, $url );

	// Fallback for pretty permalinks
	if( $original_url === $url ) {
		if( strpos( $url, $referral_var ) ) {
			$url = preg_replace( '/(\/' . $referral_var . ')[\/](\w\-*)+/', '', $url );
		}
	}

	return $url;
}

/**
 * Determines whether a visit ID is valid.
 *
 * @since 2.2.16
 *
 * @param int|mixed $visit_id Visit ID.
 * @return bool True if the visit ID is considered valid, otherwise false.
 */
function affwp_validate_visit_id( $visit_id ) {
	$valid = false;

	if ( is_int( $visit_id ) && $visit_id >= 0 ) {
		$valid = true;
	}

	return $valid;
}

/**
 * Retrieves a visit by a given field and value.
 *
 * @since 2.7
 *
 * @param string $field Visit object field.
 * @param mixed  $value Field value.
 * @return \AffWP\Visit|\WP_Error Visit object if found, otherwise a WP_Error object.
 */
function affwp_get_visit_by( $field, $value ) {
	$result = affiliate_wp()->visits->get_by( $field, $value );

	if ( is_object( $result ) ) {
		$visit = affwp_get_visit( intval( $result->visit_id ) );
	} else {
		$visit = new \WP_Error(
			'invalid_visit_field',
			sprintf( 'No visit could be retrieved with a(n) \'%1$s\' field value of %2$s.', $field, $value )
		);
	}

	return $visit;
}
