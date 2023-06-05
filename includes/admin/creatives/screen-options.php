<?php
/**
 * Admin: Creatives Screen Options
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Creatives
 * @copyright   Copyright (c) 2015, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.7
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once AFFILIATEWP_PLUGIN_DIR . 'includes/admin/creatives/class-list-table.php';

/**
 * Add per page screen option to the Creatives list table
 *
 * @since 1.7
 */
function affwp_creatives_screen_options() {

	$screen = affwp_get_current_screen();

	if ( $screen !== 'affiliate-wp-creatives' ) {
		return;
	}

	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Number of creatives per page:', 'affiliate-wp' ),
			'option'  => 'affwp_edit_creatives_per_page',
			'default' => 30,
		)
	);

	// Instantiate the list table to make the columns array available to screen options.
	new AffWP_Creatives_Table;

	/**
	 * Fires within the screen options shown on the creatives screen.
	 *
	 * @since 1.7
	 *
	 * @param string $screen The current screen.
	 */
	do_action( 'affwp_creatives_screen_options', $screen );

}

/**
 * Per page screen option value for the Creatives list table
 *
 * @since  1.7
 * @param  bool|int $status
 * @param  string   $option
 * @param  mixed    $value
 * @return mixed
 */
function affwp_creatives_set_screen_option( $status, $option, $value ) {

	if ( 'affwp_edit_creatives_per_page' === $option ) {
		update_user_meta( get_current_user_id(), $option, $value );

		return $value;
	}

	return $status;

}
add_filter( 'set-screen-option', 'affwp_creatives_set_screen_option', 10, 3 );
