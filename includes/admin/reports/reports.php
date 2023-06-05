<?php
/**
 * Admin: Reports Bootstrap
 *
 * This class bootstraps rendering of the Reports screen.
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Affiliates
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once AFFILIATEWP_PLUGIN_DIR . 'includes/admin/reports/class-reports-admin.php';

/**
 * Sets up the Reports admin.
 *
 * @since 1.0
 *
 * @see AffWP_Reports_Admin;
 */
function affwp_reports_admin() {
	new AffWP\Admin\Reports;
}
