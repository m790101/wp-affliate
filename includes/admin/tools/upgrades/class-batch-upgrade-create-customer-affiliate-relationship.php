<?php
/**
 * Upgrades: Customer/Affiliate Relationship Creation Batch Processor
 *
 * @package     AffiliateWP
 * @subpackage  Tools/Upgrades
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4.5
 */

namespace AffWP\Utils\Batch_Process;

use AffWP\Utils;
use AffWP\Utils\Batch_Process as Batch;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements an upgrade routine for creating customer records.
 *
 * @since 2.4.5
 *
 * @see \AffWP\Utils\Batch_Process
 * @see \AffWP\Utils\Batch_Process\With_PreFetch
 */
class Upgrade_Create_Customer_Affiliate_Relationship extends Utils\Batch_Process implements Batch\With_PreFetch {

	/**
	 * Batch process ID.
	 *
	 * @since  2.4.5
	 * @var    string
	 */
	public $batch_id = 'create-customer-affiliate-relationship-upgrade';

	/**
	 * Capability needed to perform the current batch process.
	 *
	 * @since  2.4.5
	 * @var    string
	 */
	public $capability = 'manage_referrals';

	/**
	 * Number of referrals to process per step.
	 *
	 * @since  2.4.5
	 * @var    int
	 */
	public $per_step = 1;

	/**
	 * Initializes the batch process.
	 *
	 * This is the point where any relevant data should be initialized for use by the processor methods.
	 *
	 * @access public
	 * @since  2.4.5
	 */
	public function init( $data = null ) {}

	/**
	 * Handles pre-fetching user IDs for accounts in migration.
	 *
	 * @since  2.4.5
	 */
	public function pre_fetch() {

		$total_to_process = $this->get_total_count();

		if ( false === $total_to_process ) {

			$total_to_process = affiliate_wp()->referrals->count( array(
				'number' => -1,
			) );

			$this->set_total_count( absint( $total_to_process ) );
		}
	}

	/**
	 * Executes a single step in the batch process.
	 *
	 * @since  2.4.5
	 *
	 * @return int|string|\WP_Error Next step number, 'done', or a WP_Error object.
	 */
	public function process_step() {

		$current_count = $this->get_current_count();

		$args = array(
			'number'  => $this->per_step,
			'offset'  => $this->get_offset(),
			'orderby' => 'referral_id',
			'order'   => 'ASC',
		);

		$referrals = affiliate_wp()->referrals->get_referrals( $args );

		if ( empty( $referrals ) ) {
			return 'done';
		}

		$inserted = array();

		global $wpdb;

		$table_name = affiliate_wp()->customer_meta->table_name;

		foreach ( $referrals as $referral ) {

			$customer_id  = $referral->customer_id;
			$affiliate_id = absint( $referral->affiliate_id );

			$customer_meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$table_name} WHERE affwp_customer_id = %d AND meta_key = 'affiliate_id' AND meta_value = %d LIMIT 1;", $customer_id, $affiliate_id ) );

			if ( ! $customer_meta_id ) {
				affwp_add_customer_meta( $customer_id, 'affiliate_id', $affiliate_id, false );
			}

			$inserted[] = $customer_id;
		}

		$this->set_current_count( absint( $current_count ) + count( $inserted ) );

		return ++$this->step;
	}

	/**
	 * Retrieves a message based on the given message code.
	 *
	 * @since  2.4.5
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
	 * @since  2.4.5
	 *
	 * @param string $batch_id Batch process ID.
	 */
	public function finish( $batch_id ) {
		affwp_set_upgrade_complete( 'upgrade_v245_create_customer_affiliate_relationship_records' );

		// Clean up.
		parent::finish( $batch_id );
	}
}
