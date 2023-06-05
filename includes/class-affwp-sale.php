<?php
/**
 * Objects: Sale
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

namespace AffWP\Referral;

/**
 * Implements a sale object.
 *
 * @since 2.5
 *
 * @see \AffWP\Base_Object
 * @see affwp_get_sale()
 *
 * @property-read int $ID Alias for `$referral_id`
 */
final class Sale extends \AffWP\Base_Object {

	/**
	 * Referral ID.
	 *
	 * @since 2.5
	 * @var   int
	 */
	public $referral_id = 0;

	/**
	 * Affiliate ID.
	 *
	 * @since 2.5
	 * @var   int
	 */
	public $affiliate_id = 0;

	/**
	 * Order Total.
	 *
	 * @since 2.5
	 * @var   string
	 */
	public $order_total = '';

	/**
	 * Token to use for generating cache keys.
	 *
	 * @since 2.5
	 * @var   string
	 * @static
	 *
	 * @see \AffWP\Base_Object::get_cache_key()
	 */
	public static $cache_token = 'affwp_sales';

	/**
	 * Database group.
	 *
	 * Used in \AffWP\Base_Object for accessing the sales DB class methods.
	 *
	 * @since 2.5
	 * @var   string
	 * @static
	 */
	public static $db_group = 'referrals:sales';

	/**
	 * Object type.
	 *
	 * Used as the cache group and for accessing object DB classes in the parent.
	 *
	 * @since 2.5
	 * @var   string
	 * @static
	 */
	public static $object_type = 'sale';

	/**
	 * Sanitizes a sale object field.
	 *
	 * @since 2.5
	 * @static
	 *
	 * @param string $field Object field.
	 * @param mixed  $value Field value.
	 * @return mixed Sanitized field value.
	 */
	public static function sanitize_field( $field, $value ) {
		if ( in_array( $field, array( 'referral_id', 'affiliate_id' ) ) ) {
			$value = (int) $value;
		}

		if ( in_array( $field, array( 'rest_id' ) ) ) {
			$value = sanitize_text_field( $value );
		}

		return $value;
	}
}
