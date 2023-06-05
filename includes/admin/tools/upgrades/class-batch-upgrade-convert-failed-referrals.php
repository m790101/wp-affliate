<?php
/**
 * Upgrades: Convert Failed Referrals
 *
 * @package     AffiliateWP
 * @subpackage  Tools/Upgrades
 * @copyright   Copyright (c) 2021, Awesome Motive Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.8.1
 */

namespace AffWP\Utils\Batch_Process;

use AffWP\Utils;
use AffWP\Utils\Batch_Process as Batch;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements an upgrade routine for converting failed referrals from using meta to use a failed referral status.
 *
 * @since 2.8.1
 *
 * @see AffWP\Utils\Batch_Process
 */
class Batch_Upgrade_Convert_Failed_Referrals extends Utils\Batch_Process implements Batch\With_PreFetch {

	/**
	 * Batch process ID.
	 *
	 * @since 2.8.1
	 * @var   string
	 */
	public $batch_id = 'upgrade-convert-failed-referrals';

	/**
	 * Capability needed to perform the current batch process.
	 *
	 * @since 2.8.1
	 * @var   string
	 */
	public $capability = 'manage_referrals';

	/**
	 * Number of referrals to process per step.
	 *
	 * @since 2.8.1
	 * @var   int
	 */
	public $per_step = 5;

	/**
	 * Initializes the batch process.
	 *
	 * @since 2.8.1
	 *
	 * @param mixed $data Optional. Data to be used for initializing the batch process (if any).
	 */
	public function init( $data = null ) {}

	/**
	 * Handles pre-fetching IDs for failed referrals.
	 *
	 * @since 2.8.1
	 */
	public function pre_fetch() {

		global $wpdb;

		$total_to_process = $this->get_total_count();

		if ( false === $total_to_process ) {

			$meta_table = affiliate_wp()->referral_meta->table_name;

			// Query for referrals marked failed with referral meta.
			$results = $wpdb->get_col( "SELECT referral_id FROM {$meta_table} WHERE meta_key = 'referral_has_failed'" );

			if ( ! empty( $results ) ) {
				$results = array_map( 'intval', $results );
			}

			$total_to_process = count( $results );

			// Store referral IDs for later.
			affiliate_wp()->utils->data->write( "{$this->batch_id}_failed_referral_ids", $results );

			$this->set_total_count( $total_to_process );
		}
	}

	/**
	 * Executes a single step in the batch process.
	 *
	 * @since 2.8.1
	 *
	 * @return int|string|\WP_Error Next step number, 'done', or a WP_Error object.
	 */
	public function process_step() {

		$current_count = $this->get_current_count();
		$offset        = $this->get_offset();

		$all_referrals = affiliate_wp()->utils->data->get( "{$this->batch_id}_failed_referral_ids", array() );

		if ( empty( $all_referrals ) || $current_count >= $this->get_total_count() ) {
			return 'done';
		}

		$referrals = array_slice( $all_referrals, $offset, $this->per_step, true );

		$referrals_updated = 0;

		foreach ( $referrals as $referral ) {

			// Apply the 'failed' referral status.
			affwp_set_referral_status( $referral, 'failed' );

			// Remove the legacy referral meta.
			affwp_delete_referral_meta( $referral, 'referral_has_failed' );

			$referrals_updated++;

		}

		$this->set_current_count( absint( $current_count ) + $referrals_updated );

		return ++$this->step;
	}

	/**
	 * Retrieves a message based on the given message code.
	 *
	 * @since 2.8.1
	 *
	 * @param string $code Message code.
	 * @return string Message.
	 */
	public function get_message( $code ) {
		switch ( $code ) {

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
	 * @since 2.8.1
	 *
	 * @param string $batch_id Batch process ID.
	 */
	public function finish( $batch_id ) {
		affwp_set_upgrade_complete( 'upgrade_v281_convert_failed_referrals' );

		// Clean up.
		parent::finish( $batch_id );
	}
}
