<?php
/**
 * Objects: Campaign
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.7
 */

namespace AffWP;

/**
 * Implements a campaign object.
 *
 * @since 2.7
 *
 * @see   AffWP\Base_Object
 * @see   affwp_get_campaign()
 *
 * @property-read int $ID Alias for `$campaign_id`
 */
final class Campaign extends Base_Object {

	/**
	 * Token to use for generating cache keys.
	 *
	 * @since  2.7
	 * @var    string
	 * @static
	 *
	 * @see    AffWP\Base_Object::get_cache_key()
	 */
	public static $cache_token = 'affwp_campaigns';

	/**
	 * Object type.
	 *
	 * Used as the cache group and for accessing object DB classes in the parent.
	 *
	 * @since 2.7
	 * @var string
	 */
	public static $object_type = 'campaign';

	/**
	 * Database group.
	 *
	 * @since 2.7
	 * @var string
	 */
	public static $db_group = 'campaigns';

	/**
	 * Campaign ID.
	 *
	 * @since  2.7
	 * @access public
	 * @var int
	 */
	public $campaign_id = 0;

	/**
	 * Affiliate ID.
	 *
	 * @since  2.7
	 * @access public
	 * @var int
	 */
	public $affiliate_id = 0;

	/**
	 * Rest ID.
	 *
	 * @since  2.7
	 * @access public
	 * @var string
	 */
	public $rest_id = '';

	/**
	 * Campaign.
	 *
	 * @since  2.7
	 * @var string
	 */
	public $campaign = '';

	/**
	 * Hash.
	 *
	 * @since  2.7
	 * @var string
	 */
	public $hash = '';

	/**
	 * Visits.
	 *
	 * @since  2.7
	 * @var int
	 */
	public $visits = 0;

	/**
	 * Unique Visits.
	 *
	 * @since  2.7
	 * @var int
	 */
	public $unique_visits = 0;

	/**
	 * Referrals.
	 *
	 * @since  2.7
	 * @var int
	 */
	public $referrals = 0;

	/**
	 * Conversion Rate.
	 *
	 * @since  2.7
	 * @var float
	 */
	public $conversion_rate = 0;

	/**
	 * Sanitizes a campaign object field.
	 *
	 * @since  2.7
	 * @access public
	 * @static
	 *
	 * @param string $field Object field.
	 * @param mixed  $value Field value.
	 *
	 * @return mixed Sanitized field value.
	 */
	public static function sanitize_field( $field, $value ) {
		if ( in_array( $field, array( 'referrals', 'campaign_id', 'visits', 'unique_visits', 'affiliate_id', 'ID' ) ) ) {
			$value = (int) $value;
		}

		if ( $field === 'conversion_rate' ) {
			$value = (float) $value;
		}

		if ( in_array( $field, array( 'rest_id', 'campaign', 'hash' ) ) ) {
			$value = sanitize_text_field( $value );
		}

		return $value;
	}
}
