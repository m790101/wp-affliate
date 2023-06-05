<?php
/**
 * Upgrades: utf8mb4 Database Compat Batch Processor
 *
 * @package     AffiliateWP
 * @subpackage  Tools/Upgrades
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6.1
 */

namespace AffWP\Utils\Batch_Process;

use AffWP\Utils;
use AffWP\Utils\Batch_Process as Batch;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements a batch process for upgrading core database tables for compatibility with utf8mb4.
 *
 * @since 2.6.1
 *
 * @see \AffWP\Utils\Batch_Process
 */
class Upgrade_Database_ut8mb4_Compat extends Utils\Batch_Process implements Batch\With_PreFetch {

	/**
	 * Batch process ID.
	 *
	 * @since 2.6.1
	 * @var   string
	 */
	public $batch_id = 'upgrade-db-utf8mb4';

	/**
	 * Capability needed to perform the current batch process.
	 *
	 * @since 2.6.1
	 * @var    string
	 */
	public $capability = 'manage_affiliate_options';

	/**
	 * Number of referrals to process per step.
	 *
	 * @since 2.6.1
	 * @var   int
	 */
	public $per_step = 1;

	/**
	 * Initializes the batch process.
	 *
	 * @since 2.6.1
	 *
	 * @param mixed $data Optional. Any form data passed to the batch process at initialization.
	 *                    Default null.
	 */
	public function init( $data = null ) {}

	/**
	 * Handles pre-fetching any needed data.
	 *
	 * @since 2.6.1
	 */
	public function pre_fetch() {

		$total_to_process = $this->get_total_count();

		if ( false === $total_to_process ) {
			$table_count = count( $this->get_tables() );

			$this->set_total_count( $table_count );
		}
	}

	/**
	 * Retrieves the list of table instances to process.
	 *
	 * @since 2.6.1
	 *
	 * @return \Affiliate_WP_DB[] Table class instances.
	 */
	protected function get_tables() {
		$table_classes = array(
			affiliate_wp()->affiliates,
			affiliate_wp()->affiliates->coupons,
			affiliate_wp()->affiliates->payouts,
			affiliate_wp()->REST->consumers,
			affiliate_wp()->creatives,
			affiliate_wp()->customers,
			affiliate_wp()->referrals,
			affiliate_wp()->referrals->sales,
			affiliate_wp()->visits,
		);

		$meta_table_classes = array(
			affiliate_wp()->affiliate_meta,
			affiliate_wp()->customer_meta,
			affiliate_wp()->referral_meta,
		);

		return array_merge( $table_classes, $meta_table_classes );
	}

	/**
	 * Executes a single step in the batch process.
	 *
	 * @since 2.6.1
	 *
	 * @return int|string|\WP_Error Next step number, 'done', or a WP_Error object.
	 */
	public function process_step() {

		if ( 1 === $this->step ) {
			global $wpdb;

			$min_mysql_check = $wpdb->check_database_version();

			if ( is_wp_error( $min_mysql_check ) ) {
				affiliate_wp()->utils->log( 'Upgrade (2.6.1): Database upgrades could not be completed due to WordPress minimum MySQL requirements not being met.' );

				return $min_mysql_check;
			}
		}

		$current_count = absint( $this->get_current_count() );
		$offset        = $this->get_offset();
		$tables        = $this->get_tables();
		$table_class   = array_slice( $tables, $offset, $this->per_step );

		$table_class = reset( $table_class );

		if ( ! $table_class ) {
			return 'done';
		}

		if ( method_exists( $table_class, 'maybe_convert_table_to_utf8mb4' ) ) {
			$result = $table_class->maybe_convert_table_to_utf8mb4();

			if ( true === $result ) {
				affiliate_wp()->utils->log( sprintf( 'Upgrade (2.6.1): The character set for the \'%s\' table was successfully converted to utf8mb4.', $table_class->table_name ) );
			} else {
				affiliate_wp()->utils->log( sprintf( 'Upgrade (2.6.1): The character set for the \'%s\' table could not be converted to utf8mb4.', $table_class->table_name ) );
			}
		}

		$current_count++;

		$this->set_current_count( $current_count );

		return ++$this->step;
	}

	/**
	 * Retrieves a message based on the given message code.
	 *
	 * @since 2.6.1
	 *
	 * @param string $code Message code.
	 * @return string Message.
	 */
	public function get_message( $code ) {
		switch( $code ) {

			case 'done':
				/* translators: Dismiss Notice link markup */
				$message = sprintf( __( 'Your database tables have been successfully upgraded. %s', 'affiliate-wp' ),
					sprintf( '<a href="">%s</a>', __( 'Dismiss Notice', 'affiliate-wp' ) )
				);
				break;

			default:
				$message = '';
				break;
		}

		return $message;
	}

	/**
	 * Defines logic to execute after the batch processing is complete.
	 *
	 * @since 2.6.1
	 *
	 * @param string $batch_id Batch process ID.
	 */
	public function finish( $batch_id ) {
		affwp_set_upgrade_complete( 'upgrade_v261_utf8mb4_compat' );

		// Clean up.
		parent::finish( $batch_id );
	}
}
