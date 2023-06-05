<?php
/**
 * CSV Exporter Interface
 *
 * @package     AffiliateWP
 * @subpackage  Core/Interfaces
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 */

namespace AffWP\Utils\Exporter;

use AffWP\Utils\Exporter;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Promise for structuring CSV exporters.
 *
 * @since 2.0
 */
interface CSV extends Exporter\Base {

	/**
	 * Sets the CSV columns.
	 *
	 * @access public
	 * @since  2.0
	 *
	 * @return array<string,string> CSV columns.
	 */
	public function csv_cols();

	/**
	 * Retrieves the CSV columns array.
	 *
	 * Alias for csv_cols(), usually used to implement a filter on the return.
	 *
	 * @access public
	 * @since  2.0
	 *
	 * @return array<string,string> CSV columns.
	 */
	public function get_csv_cols();

	/**
	 * Outputs the CSV columns.
	 *
	 * @access public
	 * @since  2.0
	 *
	 * @return void
	 */
	public function csv_cols_out();

	/**
	 * Outputs the CSV rows.
	 *
	 * @access public
	 * @since  2.0
	 *
	 * @return void
	 */
	public function csv_rows_out();

}
