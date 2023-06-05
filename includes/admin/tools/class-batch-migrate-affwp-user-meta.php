<?php
/**
 * Upgrades: Migrate Affiliate User Meta
 *
 * @package     AffiliateWP
 * @subpackage  Tools/Upgrades
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.8
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
 * @since 2.8
 *
 * @see   \AffWP\Utils\Batch_Process
 * @see   \AffWP\Utils\Batch_Process\With_PreFetch
 */
class Batch_Migrate_Affiliate_User_Meta extends Utils\Batch_Process implements Batch\With_PreFetch {

	/**
	 * Batch process ID.
	 *
	 * @since  2.8
	 * @var    string
	 */
	public $batch_id = 'migrate-affiliate-user-meta';

	/**
	 * Capability needed to perform the current batch process.
	 *
	 * @since  2.8
	 * @var    string
	 */
	public $capability = 'manage_referrals';

	/**
	 * Number of referrals to process per step.
	 *
	 * @since  2.8
	 * @var    int
	 */
	public $per_step = 20;

	private $affiliates = array();

	/**
	 * Initializes the batch process.
	 *
	 * This is the point where any relevant data should be initialized for use by the processor methods.
	 *
	 * @access public
	 * @since  2.8
	 */
	public function init( $data = null ) {
		if ( $this->step <= 1 ) {
			$this->set_current_count( 0 );
			$this->set_total_count( affiliate_wp()->affiliates->count( array( 'number' => -1 ) ) );
		}
	}

	/**
	 * Handles pre-fetching user IDs for accounts in migration.
	 *
	 * @since  2.8
	 */
	public function pre_fetch() {
		$this->affiliates = affiliate_wp()->affiliates->get_affiliates( array(
			'offset' => $this->get_current_count(),
			'fields' => array( 'user_id', 'affiliate_id' ),
			'number' => $this->per_step,
		) );

		// Remove hooks that prevent user meta from being accessed directly
		remove_filter( 'get_user_metadata', 'affwp_intercept_migrated_user_meta_fields', 2 );
		remove_filter( 'update_user_metadata', 'affwp_intercept_migrated_user_meta_field_updates', 2 );
		remove_action( 'delete_user_metadata', 'affwp_delete_affiliate_meta_when_migrated_user_meta_is_deleted', 2 );
	}

	/**
	 * Executes a single step in the batch process.
	 *
	 * @since  2.8
	 *
	 * @return int|string|\WP_Error Next step number, 'done', or a WP_Error object.
	 */
	public function process_step() {

		$current_count = (int) $this->get_current_count();

		foreach ( $this->affiliates as $affiliate ) {
			$migrated = $this->migrate_user_meta( $affiliate );
			affiliate_wp()->utils->log( 'Attempted to migrated affiliate user meta to affiliate meta', array(
				'affiliate'             => $affiliate,
				'migrated_field_status' => $migrated,
			) );
		}

		$this->set_current_count( $current_count + count( $this->affiliates ) );

		if ( $this->get_current_count() <= $this->get_total_count() ) {
			return 'done';
		}

		return ++$this->step;
	}

	/**
	 * Migrate a single affiliate's user meta to the affiliate table.
	 *
	 * @since 2.8
	 *
	 * @param \stdClass $affiliate Object containing the affiliate_id and user_id values.
	 *
	 * @return array List of meta fields that were checked, with "true" if the field was migrated, and false if-not.
	 */
	private function migrate_user_meta( \stdClass $affiliate ) {
		$migrated = array();
		foreach ( affwp_get_pending_migrated_user_meta_fields() as $meta_field ) {
			$migrated[ $meta_field ] = false;
			// If the meta is not already set, then set it.
			if ( "" === affwp_get_affiliate_meta( $affiliate->affiliate_id, $meta_field, true ) ) {
				$user_meta = get_user_meta( $affiliate->user_id, $meta_field, true );

				// If the user meta is set, set the affiliate meta.
				if ( "" !== $user_meta ) {
					// Strip un-necessary prefixes.
					$new_field = affwp_remove_prefix( $meta_field );
					$updated   = affwp_update_affiliate_meta( $affiliate->affiliate_id, $new_field, $user_meta );
					if ( false !== $updated ) {
						delete_user_meta( $affiliate->user_id, $meta_field );
						$migrated[ $meta_field ] = true;
					}
				}
			}
		}

		return $migrated;
	}

	/**
	 * Retrieves a message based on the given message code.
	 *
	 * @since  2.8
	 *
	 * @param string $code Message code.
	 *
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
	 * @since  2.8
	 *
	 * @param string $batch_id Batch process ID.
	 */
	public function finish( $batch_id ) {
		update_option( 'affwp_migrated_meta_fields', affwp_get_pending_migrated_user_meta_fields() );

		// Clean up.
		parent::finish( $batch_id );
	}

}
