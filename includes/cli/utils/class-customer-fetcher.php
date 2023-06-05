<?php
/**
 * CLI: Customer Object Fetcher
 *
 * @package     AffiliateWP
 * @subpackage  CLI/Utils
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.2
 */

namespace AffWP\Customer\CLI;

use \WP_CLI\Fetchers\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Implements a single object fetcher for customers.
 *
 * @since 2.2
 *
 * @see \WP_CLI\Fetchers\Base
 */
class Fetcher extends Base {

	/**
	 * Not found message.
	 *
	 * @since 2.2
	 * @access protected
	 * @var string
	 */
	protected $msg = "Could not find a customer with ID %s.";

	/**
	 * Retrieves a customer by ID.
	 *
	 * @since 2.2
	 * @access public
	 *
	 * @param int $arg Creative ID.
	 * @return \AffWP\Creative|false Creative object, false otherwise.
	 */
	public function get( $arg ) {
		return affwp_get_customer( $arg );
	}
}
