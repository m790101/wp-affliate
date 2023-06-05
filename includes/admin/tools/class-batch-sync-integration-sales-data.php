<?php
/**
 * Tools: Sync Integration Sales Batch Processor
 *
 * @package     AffiliateWP
 * @subpackage  Tools
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

namespace AffWP\Utils\Batch_Process;

use AffWP\Utils;

/**
 * Implements a batch process to import integration sales data.
 *
 * @since   2.5
 *
 * @see     \AffWP\Utils\Batch_Process\Base
 * @see     \AffWP\Utils\Batch_Process
 */
class Sync_Integration_Sales_Data extends Utils\Batch_Process implements With_PreFetch {

	/**
	 * Batch process ID.
	 *
	 * @since  2.5
	 * @var    string
	 */
	public $batch_id = 'sync-integration-sales-data';

	/**
	 * Integration Class
	 *
	 * @since  2.5
	 * @var \Affiliate_WP_Base|\WP_Error Integration class, or a WP Error object.
	 */
	private $integration;

	/**
	 * Referral context
	 *
	 * @since  2.5
	 * @var string The referral context. Also known as the integration name.
	 */
	private $context;

	/**
	 * List of existing sales record referral IDs for the current context.
	 *
	 * @since  2.5
	 * @var array list of existing referral IDs.
	 */
	private $existing_referrals = array();

	/**
	 * Initializes the batch process.
	 *
	 * This is the point where any relevant data should be initialized for use by the processor methods.
	 *
	 * @since  2.5
	 *
	 * @param null|array $data The data to run against this batch process.
	 */
	public function init( $data = null ) {
		if ( is_array( $data ) ) {
			$this->context     = isset( $data['context'] ) ? (string) $data['context'] : '';
			$this->integration = affiliate_wp()->integrations->get( $this->context );
		}
	}

	/**
	 * Pre-fetches data to speed up processing.
	 *
	 * @since  2.5
	 */
	public function pre_fetch() {
		if ( false === $this->get_total_count() && ! is_wp_error( $this->integration ) ) {
			$version_upgraded_from = get_option( 'affwp_version_upgraded_from', '2.5' );

			if ( version_compare( $version_upgraded_from, '2.5', '<' ) ) {
				$this->reset_sales_db();
			}

			$total_counts = $this->integration->get_sales_referrals_counts();

			if ( ! is_wp_error( $total_counts ) ) {
				$total_count = $total_counts['referrals'] - $total_counts['sales'];

				$this->set_total_count( $total_count );
			} else {
				$this->set_total_count( 0 );
			}
		}
	}

	/**
	 * Processes a single step (batch).
	 *
	 * @since  2.5
	 */
	public function process_step() {

		// Bail if the integration returned an error, or if the total count is less than 1.
		if ( is_wp_error( $this->integration ) || $this->get_total_count() < 1 ) {
			return 'done';
		}

		$referrals = affiliate_wp()->referrals->get_referrals( array(
			'fields'  => array( 'affiliate_id', 'referral_id', 'reference' ),
			'context' => $this->context,
			'number'  => $this->per_step,
			'offset'  => $this->get_offset(),
		) );

		// If the results are empty, there's no more referrals to process.
		if ( empty( $referrals ) ) {
			return 'done';
		}

		$current_count = (int) $this->get_current_count();

		foreach ( $referrals as $referral ) {
			if ( ! affwp_get_sale( $referral ) ) {
				$order_total = $this->integration->get_order_total( $referral->reference );

				$sale = affiliate_wp()->referrals->sales->add( array(
					'referral_id'  => $referral->referral_id,
					'affiliate_id' => $referral->affiliate_id,
					'order_total'  => $order_total,
				) );

				if ( $sale ) {
					$current_count++;
				}
			}
		}

		$this->set_current_count( $current_count );

		// If the current count and total counts match, we have sync'd all of the referral records and can stop.
		if ( $this->get_current_count() === $this->get_total_count() ) {
			return 'done';
		}

		return ++$this->step;
	}

	/**
	 * Resets the Sales table by deleting all records.
	 *
	 * @since 2.5
	 */
	private function reset_sales_db() {
		global $wpdb;

		$table_name = affiliate_wp()->referrals->sales->table_name;

		$wpdb->query( "DELETE FROM $table_name;" );

		affiliate_wp()->utils->log( 'Sync: The Sales database table has been cleared for resync.' );
	}

	/**
	 * Defines logic to execute once batch processing is complete.
	 *
	 * @since  2.5
	 *
	 * @param string $batch_id Batch process ID.
	 */
	public function finish( $batch_id ) {
		parent::finish( $batch_id );

		// Invalidate the cache and force sync check to recount records. See self::$integration->needs_synced()
		wp_cache_delete( affwp_get_sales_referrals_counts_cache_key( $this->context ), 'affwp_integrations' );
	}
}
