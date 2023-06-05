<?php
/**
 * Objects: Coupon
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6
 */

namespace AffWP\Affiliate;

/**
 * Implements a coupon object.
 *
 * @since 2.6
 *
 * @see \AffWP\Base_Object
 * @see affwp_get_coupon()
 *
 * @property-read string $code Alias for `$coupon_code`
 */
final class Coupon extends \AffWP\Base_Object {

	/**
	 * Coupon ID
	 *
	 * @since 2.6
	 * @var   int
	 */
	public $coupon_id = 0;

	/**
	 * Associated affiliate ID
	 *
	 * @since 2.6
	 * @var   int
	 */
	public $affiliate_id = 0;

	/**
	 * Coupon code.
	 *
	 * @since 2.6
	 * @var   string
	 */
	public $coupon_code;

	/**
	 * Coupon type.
	 *
	 * @since 2.8
	 * @var   string
	 */
	public $type;

	/**
	 * Coupon Template
	 *
	 * @since 2.6
	 * @var   string
	 */
	private $template;

	/**
	 * Coupon Integration
	 *
	 * @since 2.6
	 * @var   string
	 */
	private $integration;

	/**
	 * Token to use for generating cache keys.
	 *
	 * @since 2.6
	 * @var   string
	 * @static
	 *
	 * @see AffWP\Base_Object::get_cache_key()
	 */
	public static $cache_token = 'affwp_coupons';

	/**
	 * Database group.
	 *
	 * Used in AffWP\Base_Object for accessing the coupons DB class methods.
	 *
	 * @since 2.6
	 * @var   string
	 */
	public static $db_group = 'affiliates:coupons';

	/**
	 * Object type.
	 *
	 * Used as the cache group and for accessing object DB classes in the parent.
	 *
	 * @since 2.6
	 * @var   string
	 * @static
	 */
	public static $object_type = 'coupon';

	/**
	 * Retrieves the values of the given key.
	 *
	 * @since 2.6
	 *
	 * @param string $key Key to retrieve the value for.
	 * @return mixed|\WP_User Value.
	 */
	public function __get( $key ) {
		if ( 'code' === $key ) {
			return $this->coupon_code;
		}

		return parent::__get( $key );
	}

	/**
	 * Retrieves the value of the coupon integration.
	 *
	 * @since 2.6
	 *
	 * @return string Integration slug.
	 */
	public function get_integration() {
		if ( defined( 'WP_TESTS_DOMAIN' ) ) {
			$integration = $this->integration;
		} else {
			$integration = 'woocommerce';
		}

		return $integration;
	}

	/**
	 * Sanitizes a coupon object field.
	 *
	 * @since 2.6
	 * @static
	 *
	 * @param string $field        Object field.
	 * @param mixed  $value        Field value.
	 * @return mixed Sanitized field value.
	 */
	public static function sanitize_field( $field, $value ) {
		if ( in_array( $field, array( 'coupon_id', 'affiliate_id' ) ) ) {
			$value = intval( $value );
		}

		if ( 'coupon_code' === $field ) {
			$value = affwp_sanitize_coupon_code( $value );
		}

		if ( 'type' === $field ) {
			$value = sanitize_key( $value );
		}

		return $value;
	}

}
