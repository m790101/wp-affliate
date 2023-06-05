<?php
/**
 * Affiliate Meta Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2015, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.6
 */

/**
 * Core class used to implement affiliate meta.
 *
 * @since 1.6
 *
 * @see Affiliate_WP_Meta_DB
 */
class Affiliate_WP_Affiliate_Meta_DB extends Affiliate_WP_Meta_DB {

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
	public $db_group = 'affiliate_meta';

	/**
	 * Retrieves the table columns and data types.
	 *
	 * @access public
	 * @since  1.7.18
	 *
	 * @return array List of affiliate meta table columns and their respective types.
	*/
	public function get_columns() {
		return array(
			'meta_id'      => '%d',
			'affiliate_id' => '%d',
			'meta_key'     => '%s',
			'meta_value'   => '%s',
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
		return 'affiliate';
	}

	/**
	 * Creates the table.
	 *
	 * @access public
	 * @since  1.6
	 *
	 * @see dbDelta()
	*/
	public function create_table() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE {$this->table_name} (
			meta_id bigint(20) NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) NOT NULL DEFAULT '0',
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY affiliate_id (affiliate_id),
			KEY meta_key (meta_key(191))
			) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
