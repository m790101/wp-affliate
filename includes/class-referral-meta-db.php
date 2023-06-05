<?php
/**
 * Referral Meta Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

/**
 * Core class used to implement referral meta.
 *
 * @since 2.4
 *
 * @see Affiliate_WP_Meta_DB
 */
class Affiliate_WP_Referral_Meta_DB extends Affiliate_WP_Meta_DB {

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
	public $db_group = 'referral_meta';

	/**
	 * Retrieves the table columns and data types.
	 *
	 * @since 2.4
	 *
	 * @return array List of referral meta table columns and their respective types.
	*/
	public function get_columns() {
		return array(
			'meta_id'     => '%d',
			'referral_id' => '%d',
			'meta_key'    => '%s',
			'meta_value'  => '%s',
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
		return 'referral';
	}

	/**
	 * Creates the table.
	 *
	 * @since 2.4
	 *
	 * @see dbDelta()
	*/
	public function create_table() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE {$this->table_name} (
			meta_id bigint(20) NOT NULL AUTO_INCREMENT,
			referral_id bigint(20) NOT NULL DEFAULT '0',
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY referral_id (referral_id),
			KEY meta_key (meta_key(191))
			) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
