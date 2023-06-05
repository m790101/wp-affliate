<?php
/**
 * Tools: Campaign Recalculation Batch Processor
 *
 * @package     AffiliateWP
 * @subpackage  Tools
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.7
 */

namespace AffWP\Utils\Batch_Process;

use AffWP\Utils;
use AffWP\Utils\Batch_Process as Batch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements an batch process to recalculate campaigns.
 *
 * @since 2.7
 *
 * @see Utils\Batch_Process
 */
class Batch_Recalculate_Campaigns extends Utils\Batch_Process implements Batch\With_PreFetch {

	/**
	 * Batch process ID.
	 *
	 * @access public
	 * @since  2.7
	 * @var    string
	 */
	public $batch_id = 'recalculate-campaigns';

	/**
	 * Number of items to process per step.
	 *
	 * @access public
	 * @since  2.7
	 * @var    int
	 */
	public $per_step = 50;

	/**
	 * Campaigns for this step.
	 *
	 * @accees private
	 * @since  2.7
	 * @var array
	 */
	private $campaigns = array();

	/**
	 * Affiliate ID.
	 *
	 * @accees private
	 * @since  2.7
	 * @var int The affiliate ID
	 */
	private $affiliate_id = 0;

	/**
	 * Initializes the batch process.
	 *
	 * This is the point where any relevant data should be initialized for use by the processor methods.
	 *
	 * @access public
	 * @since  2.7
	 */
	public function init( $data = null ) {

		if ( ! is_array( $data ) ) {
			$data = array( $data );
		}

		if ( ! isset( $data['user_name'] ) ) {
			$data['user_name'] = '';
		}

		$affiliate          = affwp_get_affiliate( $data['user_name'] );
		$this->affiliate_id = false !== $affiliate ? $affiliate->affiliate_id : 0;
	}

	/**
	 * Pre-fetches data to speed up processing.
	 *
	 * @access public
	 * @since  2.7
	 */
	public function pre_fetch() {
		global $wpdb;

		$args = array(
			'affiliate_id' => $this->affiliate_id,
			'number'       => $this->per_step,
			'offset'       => $this->get_offset(),
		);

		$this->campaigns = affiliate_wp()->visits->get_unique_campaigns( $args );

		// Set the total count, if it has not been set yet.
		if ( false === $this->get_total_count() ) {
			$this->set_total_count( affiliate_wp()->visits->get_unique_campaigns( $args, true ) );
		}
	}

	/**
	 * Executes a single step in the batch process.
	 *
	 * @access public
	 * @since  2.7
	 *
	 * @return int|string|\WP_Error Next step number, 'done', or a WP_Error object.
	 */
	public function process_step() {
		$count = $this->get_current_count();

		if ( ! is_int( $count ) ) {
			$count = 0;
		}

		foreach ( $this->campaigns as $campaign ) {
			affiliate_wp()->campaigns->update_affiliate_campaign( $campaign->affiliate_id, $campaign->campaign );

			$count++;
		}

		$this->set_current_count( $count );

		if ( $this->get_current_count() >= $this->get_total_count() ) {
			return 'done';
		}

		return ++$this->step;
	}

	/**
	 * Retrieves a message based on the given message code.
	 *
	 * @access public
	 * @since  2.0
	 *
	 * @param string $code Message code.
	 *
	 * @return string Message.
	 */
	public function get_message( $code ) {
		switch ( $code ) {

			case 'done':
				$final_count = $this->get_current_count();

				$message = sprintf(
					_n(
						/* translators: Singular campaign */
						'%s campaign was successfully recounted.',
						/* translators: Plural campaigns */
						'%s campaigns were successfully recounted.',
						$final_count,
						'affiliate-wp'
					), number_format_i18n( $final_count )
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

		// Set the upgrade complete if all affiliate campaigns were calculated.
		if ( 0 === $this->affiliate_id ) {
			affwp_set_upgrade_complete( 'upgrade_v27_calculate_campaigns' );
			affwp_set_upgrade_complete( 'upgrade_v274_calculate_campaigns' );
		}

		// Clean up.
		parent::finish( $batch_id );
	}

}
