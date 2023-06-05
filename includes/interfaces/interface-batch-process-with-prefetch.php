<?php
/**
 * Batch Process With Pre-fetch Interface
 *
 * @package     AffiliateWP
 * @subpackage  Core/Interfaces
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 */

namespace AffWP\Utils\Batch_Process;

/**
 * Second-level interface for registering a batch process that leverages
 * pre-fetch and data storage.
 *
 * @since 2.0
 *
 * @see \AffWP\Utils\Data_Storage
 */
interface With_PreFetch extends Base {
	/**
	 * Initializes the batch process.
	 *
	 * This is the point where any relevant data should be initialized for use by the processor methods.
	 *
	 * @access public
	 * @since  2.0
	 */
	public function init( $data = null );

	/**
	 * Pre-fetches data to speed up processing.
	 *
	 * @access public
	 * @since  2.0
	 */
	public function pre_fetch();

}
