<?php
/**
 * CLI: Visit Object Fetcher
 *
 * @package     AffiliateWP
 * @subpackage  CLI/Utils
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

namespace AffWP\Visit\CLI;

use \WP_CLI\Fetchers\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Implements a single object fetcher for visits.
 *
 * @since 1.9
 *
 * @see \WP_CLI\Fetchers\Base
 */
class Fetcher extends Base {

	/**
	 * Not found message.
	 *
	 * @since 1.9
	 * @access protected
	 * @var string
	 */
	protected $msg = "Could not find the visit with ID %s.";

	/**
	 * Retrieves a visit by ID.
	 *
	 * @since 1.9
	 * @access public
	 *
	 * @param int $arg Visit ID.
	 * @return \AffWP\Visit|false Visit object, false otherwise.
	 */
	public function get( $arg ) {
		return affwp_get_visit( $arg );
	}
}
