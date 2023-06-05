<?php
/**
 * Objects: Customer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.2
 */

namespace AffWP;

/**
 * Implements an customer object.
 *
 * @since 2.2
 *
 * @see AffWP\Base_Object
 * @see affwp_get_affiliate()
 *
 * @property-read int      $ID   Alias for `$customer_id`.
 * @property      stdClass $user User object.
 * @property      array    $meta Meta array.
 * @property-read string   $date Alias for `$date_registered`.
 */
final class Customer extends Base_Object {

	/**
	 * Customer ID.
	 *
	 * @since 2.2
	 * @access public
	 * @var int
	 */
	public $customer_id = 0;

	/**
	 * Affiliate IDs associated with the customer.
	 *
	 * @since 2.2
	 * @access protected
	 * @var array
	 */
	protected $affiliate_ids = array();

	/**
	 * Customer user ID.
	 *
	 * @since 2.2
	 * @access public
	 * @var int
	 */
	public $user_id = 0;

	/**
	 * Customer first name.
	 *
	 * @since 2.2
	 * @access public
	 * @var string
	 */
	public $first_name;

	/**
	 * Customer last name.
	 *
	 * @since 2.2
	 * @access public
	 * @var string
	 */
	public $last_name;

	/**
	 * Customer email.
	 *
	 * @since 2.2
	 * @access public
	 * @var string
	 */
	public $email;

	/**
	 * Customer IP.
	 *
	 * @since 2.2
	 * @access public
	 * @var string
	 */
	public $ip;

	/**
	 * Customer creation date.
	 *
	 * @since 2.2
	 * @access public
	 * @var string
	 */
	public $date_created;

	/**
	 * Token to use for generating cache keys.
	 *
	 * @since 2.2
	 * @access public
	 * @static
	 * @var string
	 *
	 * @see AffWP\Base_Object::get_cache_key()
	 */
	public static $cache_token = 'affwp_customers';

	/**
	 * Database group.
	 *
	 * Used in \AffWP\Base_Object for accessing the customers DB class methods.
	 *
	 * @since 2.2
	 * @access public
	 * @var string
	 */
	public static $db_group = 'customers';

	/**
	 * Object type.
	 *
	 * Used as the cache group and for accessing object DB classes in the parent.
	 *
	 * @since 2.2
	 * @access public
	 * @static
	 * @var string
	 */
	public static $object_type = 'customer';

	/**
	 * Retrieves the values of the given key.
	 *
	 * @since 2.2
	 * @access public
	 *
	 * @param string $key Key to retrieve the value for.
	 * @return mixed|\WP_User Value.
	 */
	public function __get( $key ) {

		if ( 'date' === $key ) {
			return $this->date_created;
		}

		if ( 'affiliate_ids' === $key ) {
			return $this->get_affiliate_ids();
		}

		if ( 'user' === $key ) {
			return $this->get_user();
		}

		if ( 'meta' === $key ) {
			return $this->get_meta();
		}

		return parent::__get( $key );
	}

	/**
	 * Retrieves the customer's name.
	 *
	 * If no name is present, the customer's email is returned.
	 *
	 * @since 2.2
	 * @access public
	 *
	 * @return string Customer name
	 */
	public function get_name() {

		$name = '';

		if( $this->first_name ) {
			$name = $this->first_name;
		}

		if( $this->last_name ) {
			$name .= ' ' . $this->last_name;
		}

		$name = ltrim( $name );

		if( empty( $name ) ) {
			$name = $this->email;
		}

		return $name;
	}

	/**
	 * Retrieves the affiliate IDs associated with this customer.
	 *
	 * @since 2.2
	 * @access public
	 *
	 * @return array Affiliate IDs.
	 */
	public function get_affiliate_ids() {
		return affwp_get_customer_meta( $this->customer_id, 'affiliate_id', false );
	}

	/**
	 * Retrieves the canonical affiliate ID associated with this customer.
	 *
	 * @since 2.2
	 * @access public
	 *
	 * @return int ID of the affiliate that originally referred the customer
	 */
	public function get_canonical_affiliate_id() {
		global $wpdb;
		$table_name   = affiliate_wp()->customer_meta->table_name;
		$affiliate_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$table_name} WHERE meta_key = 'affiliate_id' AND affwp_customer_id = %d ORDER BY meta_id ASC LIMIT 1;", $this->customer_id ) );

		/**
		 * Filters the canonical affiliate ID for the current customer.
		 *
		 * @since 2.2
		 *
		 * @param int             $affiliate_id Affiliate ID.
		 * @param \AffWP\Customer $customer     Current Customer object.
		 */
		return apply_filters( 'affwp_get_canonical_customer_affiliate_id', $affiliate_id, $this );
	}

	/**
	 * Builds the lazy-loaded user object with select fields removed for security reasons.
	 *
	 * @since 2.4.1
	 *
	 * @return \stdClass|false Built user object or false if it doesn't exist.
	 */
	public function get_user() {
		$user = get_user_by( 'id', $this->user_id );

		if ( $user ) {
			// Exclude user pass, activation key, and email from the response.
			unset( $user->data->user_pass, $user->data->user_activation_key );
			return $user->data;
		}

		return $user;
	}

	/**
	 * Retrieves the customer meta.
	 *
	 * @since 2.4.1
	 *
	 * @param string $meta_key Optional. The meta key to retrieve a value for. Default empty.
	 * @param bool   $single   Optional. Whether to return a single value. Default false.
	 * @return mixed Meta value or false if `$meta_key` specified, array of meta otherwise.
	 */
	public function get_meta( $meta_key = '', $single = false ) {
		return affiliate_wp()->customer_meta->get_meta( $this->ID, $meta_key, $single );
	}

	/**
	 * Sanitizes a customer object field.
	 *
	 * @since 2.3
	 * @static
	 *
	 * @param string $field Object field.
	 * @param mixed  $value Field value.
	 * @return mixed Sanitized field value.
	 */
	public static function sanitize_field( $field, $value ) {
		if ( in_array( $field, array( 'customer_id', 'user_id', 'ID' ) ) ) {
			$value = (int) $value;
		}

		if ( in_array( $field, array( 'first_name', 'last_name', 'email', 'ip', 'date_created' ) ) ) {
			$value = sanitize_text_field( $value );
		}

		return $value;
	}

}
