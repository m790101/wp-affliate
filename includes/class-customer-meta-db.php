<?php
/**
 * Customer Meta Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.2
 */

 /**
 * Core class used to implement customer meta.
 *
 * @since 2.2
 *
 * @see Affiliate_WP_Meta_DB
 */
class Affiliate_WP_Customer_Meta_DB extends Affiliate_WP_Meta_DB {

	/**
	 * Represents the meta table database version.
	 *
	 * @since 2.4
	 * @var   string
	 */
	public $version = '1.1';

	/**
	 * Database group value.
	 *
	 * @since 2.5
	 * @var string
	 */
	public $db_group = 'customer_meta';

	/**
	 * Sets the customer meta table name.
	 *
	 * @since 2.4
	 */
	public function set_table_name() {
		global $wpdb;

		if ( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE ) {
			// Allows a single meta table for the whole network for a given meta type.
			$this->table_name  = 'affiliate_wp_customermeta';
		} else {
			$this->table_name  = $wpdb->prefix . 'affiliate_wp_customermeta';
		}
	}

	/**
	 * Retrieves the table columns and data types.
	 *
	 * @access public
	 * @since  2.2
	 *
	 * @return array List of customer meta table columns and their respective types.
	*/
	public function get_columns() {
		return array(
			'meta_id'           => '%d',
			'affwp_customer_id' => '%d',
			'meta_key'          => '%s',
			'meta_value'        => '%s',
		);
	}

	/**
	 * Retrieves the meta type.
	 *
	 * @since 2.4
	 *
	 * @return string Meta type.
	 */
	public function get_meta_type() {
		return 'affwp_customer';
	}

	/**
	 * Creates the table.
	 *
	 * @access public
	 * @since  2.2
	 *
	 * @see dbDelta()
	*/
	public function create_table() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE {$this->table_name} (
			meta_id bigint(20) NOT NULL AUTO_INCREMENT,
			affwp_customer_id bigint(20) NOT NULL DEFAULT '0',
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY affwp_customer_id (affwp_customer_id),
			KEY meta_key (meta_key(191))
			) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
