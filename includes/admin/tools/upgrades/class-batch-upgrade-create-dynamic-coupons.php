<?php
/**
 * Upgrades: Dynamic Coupon Creation Batch Processor
 *
 * @package     AffiliateWP
 * @subpackage  Tools/Upgrades
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6
 */

namespace AffWP\Utils\Batch_Process;

use AffWP\Utils;
use AffWP\Utils\Batch_Process as Batch;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements an upgrade routine for creating dynamic coupons for all affiliates.
 *
 * @since 2.6
 *
 * @see \AffWP\Utils\Batch_Process
 */
class Upgrade_Create_Dynamic_Coupons extends Utils\Batch_Process implements Batch\With_PreFetch {

	/**
	 * Batch process ID.
	 *
	 * @since 2.6
	 * @var   string
	 */
	public $batch_id = 'create-dynamic-coupons-upgrade';

	/**
	 * Capability needed to perform the current batch process.
	 *
	 * @since 2.6
	 * @var   string
	 */
	public $capability = 'manage_affiliates';

	/**
	 * Number of affiliates to process per step.
	 *
	 * @since 2.6
	 * @var   int
	 */
	public $per_step = 40;

	/**
	 * Initializes the batch process.
	 *
	 * @since 2.6
	 */
	public function init( $data = null ) {
		if ( $this->step <= 1 ) {
			affiliate_wp()->affiliates->coupons->create_table();
			affiliate_wp()->utils->log( 'Upgrade: The coupons table has been created.' );
		}
	}

	/**
	 * Handles pre-fetching user IDs for accounts in migration.
	 *
	 * @since 2.6
	 */
	public function pre_fetch() {
		$total_to_process = $this->get_total_count();

		if ( false === $total_to_process ) {
			$total_to_process = affiliate_wp()->affiliates->count( array(
				'number' => -1,
			) );

			$this->set_total_count( $total_to_process );
		}
	}

	/**
	 * Executes a single step in the batch process.
	 *
	 * @since 2.6
	 *
	 * @return int|string|\WP_Error Next step number, 'done', or a WP_Error object.
	 */
	public function process_step() {

		$current_count = $this->get_current_count();

		$args = array(
			'number'  => $this->per_step,
			'offset'  => $this->get_offset(),
			'orderby' => 'affiliate_id',
			'order'   => 'ASC',
			'status'  => 'active',
			'fields'  => array( 'affiliate_id' ),
		);

		$affiliates = affiliate_wp()->affiliates->get_affiliates( $args );

		if ( empty( $affiliates ) ) {
			return 'done';
		}

		$inserted = 0;

		foreach ( $affiliates as $affiliate_id ) {

			$coupons = affwp_get_dynamic_affiliate_coupons( $affiliate_id, false );

			if ( $coupons ) {
				continue;
			}

			$added = affiliate_wp()->affiliates->coupons->add( array(
				'affiliate_id' => $affiliate_id,
			) );

			if ( false !== $added ) {
				$inserted++;
			}
		}

		$this->set_current_count( absint( $current_count ) + $inserted );

		return ++$this->step;
	}

	/**
	 * Retrieves a message based on the given message code.
	 *
	 * @since 2.6
	 *
	 * @param string $code Message code.
	 * @return string Message.
	 */
	public function get_message( $code ) {
		switch( $code ) {

			case 'done':
				/* translators: Dismiss Notice link markup */
				$message = sprintf( __( 'Your database has been successfully upgraded. %s', 'affiliate-wp' ),
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
	 * @since  2.6
	 *
	 * @param string $batch_id Batch process ID.
	 */
	public function finish( $batch_id ) {
		affwp_set_upgrade_complete( 'upgrade_v26_create_dynamic_coupons' );

		// Clean up.
		parent::finish( $batch_id );
	}
}
