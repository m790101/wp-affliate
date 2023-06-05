<?php
/**
 * Meta Database Model
 *
 * @package     AffiliateWP
 * @subpackage  Database/Meta
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

/**
 * Core middleware class used to implement object meta.
 *
 * @since 2.4
 *
 * @see Affiliate_WP_DB
 */
abstract class Affiliate_WP_Meta_DB extends Affiliate_WP_DB {

	/**
	 * Sets up the Meta DB class.
	 *
	 * @since 2.4
	*/
	public function __construct() {
		$this->primary_key = 'meta_id';

		$this->set_table_name();

		$meta_type = $this->meta_type();

		add_action( 'plugins_loaded',            array( $this, 'register_table' ), 11     );
		add_filter( "get_{$meta_type}_metadata", array( $this, 'sanitize_meta'  ), 100, 4 );
	}

	/**
	 * Sets the meta table name.
	 *
	 * @since 2.4
	 */
	public function set_table_name() {
		global $wpdb;

		$meta_type = $this->meta_type();

		if ( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE ) {
			// Allows a single meta table for the whole network for a given meta type, e.g. affiliate_wp_affiliatemeta.
			$this->table_name  = "affiliate_wp_{$meta_type}meta";
		} else {
			// Site specific table, e.g. wp_affiliate_wp_affiliatemeta.
			$this->table_name  = "{$wpdb->prefix}affiliate_wp_{$meta_type}meta";
		}
	}

	/**
	 * Retrieves the (immutable) meta type.
	 *
	 * @since 2.4
	 *
	 * @return string Meta type.
	 */
	abstract public function get_meta_type();

	/**
	 * Builds a sanitized version of the meta type for use by the API.
	 *
	 * @since 2.4
	 *
	 * @return string Sanitized meta type.
	 */
	private function meta_type() {
		return sanitize_key( $this->get_meta_type() );
	}

	/**
	 * Registers the table with $wpdb so the metadata api can find it.
	 *
	 * @since 2.4
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function register_table() {
		global $wpdb;

		$meta_table = "{$this->meta_type()}meta";

		$wpdb->$meta_table = $this->table_name;
	}

	/**
	 * Retrieves a meta field for an object.
	 *
	 * @since 2.4
	 *
	 * @param int    $object_id Optional. Object ID. Default 0.
	 * @param string $meta_key  Optional. The meta key to retrieve. Default empty.
	 * @param bool   $single    Optional. Whether to return a single value. Default false.
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
	 */
	function get_meta( $object_id = 0, $meta_key = '', $single = false ) {
		return get_metadata( $this->meta_type(), $object_id, $meta_key, $single );
	}

	/**
	 * Retrieves metadata from a key/value pair.
	 *
	 * @since 2.5.4
	 *
	 * @param string $meta_key   The meta key to look up.
	 * @param mixed  $meta_value The meta value to look up.
	 * @return object|false The data row if found, otherwise false.
	 */
	public function get_meta_by_value( $meta_key, $meta_value ) {
		$results = $this->get_results( array(
			'fields'  => '*',
			'where'   => 'WHERE meta_key="' . $meta_key . '" AND meta_value="' . $meta_value . '"',
			'count'   => false,
			'join'    => '',
			'orderby' => 'meta_key',
			'order'   => 'DESC',
		),
			array( 'number' => 1, 'offset' => 0 )
		);

		if ( is_array( $results ) && count( $results ) > 0 ) {
			$results = $results[0];
		}

		if ( ! is_object( $results ) ) {
			$results = false;
		}

		return $results;
	}

	/**
	 * Adds a meta data field to an object.
	 *
	 * @since 2.4
	 *
	 * @param int    $object_id  Optional. Object ID. Default 0.
	 * @param string $meta_key   Optional. Meta data key. Default empty.
	 * @param mixed  $meta_value Optional. Meta data value. Default empty
	 * @param bool   $unique     Optional. Whether the same key should not be added. Default false.
	 * @return bool False for failure. True for success.
	 */
	function add_meta( $object_id = 0, $meta_key = '', $meta_value = '', $unique = false ) {
		return add_metadata( $this->meta_type(), $object_id, $meta_key, $meta_value, $unique );
	}

	/**
	 * Updates an object meta field based on an object ID.
	 *
	 * Use the $prev_value parameter to differentiate between meta fields with the
	 * same key and object ID.
	 *
	 * If the meta field for the object does not exist, it will be added.
	 *
	 * @since 2.4
	 *
	 * @param int    $object_id  Optional. Object ID. Default 0.
	 * @param string $meta_key   Optional. Meta data key. Default empty.
	 * @param mixed  $meta_value Optional. Meta data value. Default empty.
	 * @param mixed  $prev_value Optional. Previous value to check before removing. Default empty.
	 * @return bool False on failure, true if success.
	 */
	function update_meta( $object_id = 0, $meta_key = '', $meta_value = '', $prev_value = '' ) {
		return update_metadata( $this->meta_type(), $object_id, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Removes metadata matching criteria from an object.
	 *
	 * You can match based on the key, or key and value. Removing based on key and
	 * value, will keep from removing duplicate metadata with the same key. It also
	 * allows removing all metadata matching key, if needed.
	 *
	 * @since 2.4
	 *
	 * @param int    $object_id  Optional. Object ID. Default 0.
	 * @param string $meta_key   Optional. Meta data key. Default empty.
	 * @param mixed  $meta_value Optional. Meta data value. Default empty.
	 * @return bool False for failure. True for success.
	 */
	function delete_meta( $object_id = 0, $meta_key = '', $meta_value = '' ) {
		return delete_metadata( $this->meta_type(), $object_id, $meta_key, $meta_value );
	}

	/**
	 * Sanitizes serialized object meta values when retrieved.
	 *
	 * @since 2.4
	 *
	 * @param null   $value     The value get_metadata() should return - a single metadata value,
	 *                          or an array of values.
	 * @param int    $object_id Object ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether to return only the first value of the specified $meta_key.
	 */
	public function sanitize_meta( $value, $object_id, $meta_key, $single ) {

		$meta_cache = wp_cache_get( $object_id, "{$this->meta_type()}_meta" );

		if ( ! $meta_cache ) {
			$meta_cache = update_meta_cache( $this->meta_type(), array( $object_id ) );
			$meta_cache = $meta_cache[ $object_id ];
		}

		// Bail and let get_metadata() handle it if there's no cache.
		if ( ! $meta_cache || ! isset( $meta_cache[ $meta_key ] ) ) {
			return $value;
		}

		$value = $meta_cache[ $meta_key ];

		foreach ( $value as $index => $_value ) {
			$value[ $index ] = affwp_maybe_unserialize( $_value );
		}

		return $value;
	}

	/**
	 * Creates the table.
	 *
	 * @since 2.4
	 *
	 * @see dbDelta()
	*/
	abstract public function create_table();

	/**
	 * Handles (maybe) converting the current meta table to utf8mb4 compatibility.
	 *
	 * @since 2.6.1
	 *
	 * @see maybe_convert_table_to_utf8mb4()
	 *
	 * @return bool True if the table was converted, otherwise false.
	 */
	public function maybe_convert_table_to_utf8mb4() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$db_version = get_option( $this->table_name . '_db_version', false );

		$result = false;

		if ( version_compare( $this->version, $db_version, '>' ) && 'utf8mb4' === $wpdb->charset ) {
			$wpdb->query( "ALTER TABLE {$this->table_name} DROP INDEX meta_key, ADD INDEX meta_key(meta_key(191))" );

			$result = maybe_convert_table_to_utf8mb4( $this->table_name );
		}

		return $result;
	}

}
