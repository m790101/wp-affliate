<?php
/**
 * Tools: System Info File
 *
 * @package     AffiliateWP
 * @subpackage  Tools
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.8.7
 */

/**
 * Displays the system info report.
 *
 * @since 1.8.7
 *
 * @return string The compiled system info report.
 */
function affwp_tools_system_info_report() {

	global $wpdb;

	// Get theme info
	$theme_data = wp_get_theme();
	$theme      = $theme_data->Name . ' ' . $theme_data->Version;

	$return  = '### Begin System Info ###' . "\n\n";

	// Start with the basics...
	$return .= '-- Site Info' . "\n\n";
	$return .= 'Site URL:                 ' . site_url() . "\n";
	$return .= 'Home URL:                 ' . home_url() . "\n";
	$return .= 'Multisite:                ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";

	$locale = get_locale();

	// WordPress configuration
	$return .= "\n" . '-- WordPress Configuration' . "\n\n";
	$return .= 'Version:                  ' . get_bloginfo( 'version' ) . "\n";
	$return .= 'Language:                 ' . ( empty( $locale ) ? 'en_US' : $locale ) . "\n";
	$return .= 'Permalink Structure:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default' ) . "\n";
	$return .= 'Active Theme:             ' . $theme . "\n";
	$return .= 'Show On Front:            ' . get_option( 'show_on_front' ) . "\n";

	// Only show page specs if frontpage is set to 'page'
	if( get_option( 'show_on_front' ) === 'page' ) {
		$front_page_id = get_option( 'page_on_front' );
		$blog_page_id = get_option( 'page_for_posts' );

		$return .= 'Page On Front:            ' . ( $front_page_id != 0 ? get_the_title( $front_page_id ) . ' (#' . $front_page_id . ')' : 'Unset' ) . "\n";
		$return .= 'Page For Posts:           ' . ( $blog_page_id != 0 ? get_the_title( $blog_page_id ) . ' (#' . $blog_page_id . ')' : 'Unset' ) . "\n";
	}

	$wp_debug_log = @ini_get( 'error_log' );

	$return .= 'ABSPATH:                  ' . ABSPATH . "\n";
	$return .= 'Table Prefix:             ' . 'Length: ' . strlen( $wpdb->prefix ) . '   Status: ' . ( strlen( $wpdb->prefix ) > 16 ? 'ERROR: Too long' : 'Acceptable' ) . "\n";
	$return .= 'WP_DEBUG:                 ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
	$return .= 'WP_DEBUG Log Filesize:    ' . ( ! empty( $wp_debug_log ) ? size_format( filesize( $wp_debug_log ), 2 ) . "\n" : "N/A\n" );
	$return .= 'Memory Limit:             ' . WP_MEMORY_LIMIT . "\n";
	$return .= 'Registered Post Statuses: ' . implode( ', ', get_post_stati() ) . "\n";

	//
	// AffiliateWP
	//

	$settings = affiliate_wp()->settings;
	$upgrades = affwp_get_completed_upgrades();

	// General settings.
	$return .= "\n" . '-- AffiliateWP Configuration' . "\n\n";
	$return .= 'Version:                          ' . AFFILIATEWP_VERSION . "\n";
	$return .= 'License Key:                      ' . ( $settings->get( 'license_key' ) ? $settings->get( 'license_key' ) . "\n" : "Not set\n" );
	$return .= 'Global License Key:               ' . ( defined( 'AFFILIATEWP_LICENSE_KEY' ) ? empty( AFFILIATEWP_LICENSE_KEY ) ? 'Set empty' : AFFILIATEWP_LICENSE_KEY : 'Not set' ) . "\n";
	$return .= 'Currency:                         ' . ( $settings->get( 'currency' ) ? $settings->get( 'currency' ) . "\n" : "Default\n" );
	$return .= 'Currency Position:                ' . ( $settings->get( 'currency_position' ) ? $settings->get( 'currency_position' ) . "\n" : "Default\n" );
	$return .= 'Cookie Expiration:                ' . ( $settings->get( 'cookie_exp' ) ? $settings->get( 'cookie_exp' ) . " day(s)\n" : "Default\n" );
	$return .= 'Cookie Compat Mode Enabled:       ' . ( affiliate_wp()->tracking->cookie_compat_mode ? 'Yes' : 'No' ) . "\n";
	$return .= 'Network-wide DB Tables:           ' . ( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) ? AFFILIATE_WP_NETWORK_WIDE ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";

	// Environment.
	$return .= "\n" . '-- AffiliateWP Environment' . "\n\n";
	$return .= 'Debug Mode:                       ' . ( $settings->get( 'debug_mode', false ) ? "True" . "\n" : "False\n" );
	$return .= 'Debug Log Filesize:               ' . ( affiliate_wp()->utils->logs->get_log_size( true ) . "\n" );
	$return .= 'Completed Upgrade Routines:       ' . ( empty( $upgrades ) ? "None\n" : implode( ', ', $upgrades ) . "\n" );

	// Pages.
	$return .= "\n" . '-- AffiliateWP Page Configuration' . "\n\n";
	$return .= 'Affiliate Area:                   ' . ( $settings->get( 'affiliates_page' ) ? get_permalink( $settings->get( 'affiliates_page' ) ) . "\n" : "Unset\n" );
	$return .= 'Terms of Use:                     ' . ( $settings->get( 'terms_of_use' ) ? get_permalink( $settings->get( 'terms_of_use' ) ) . "\n" : "Unset\n" );

	// Referral Settings
	$return .= "\n" . '-- AffiliateWP Referral Configuration' . "\n\n";
	$return .= 'Referral Var:                     ' . ( $settings->get( 'referral_var' ) ? $settings->get( 'referral_var' ) . "\n" : "Default\n" );
	$return .= 'Referral Format:                  ' . ( $settings->get( 'referral_format' ) ? $settings->get( 'referral_format' ) . "\n" : "Default\n" );
	$return .= 'Show Pretty URLs:                 ' . ( $settings->get( 'referral_pretty_urls' ) ? "True" . "\n" : "False\n" );
	$return .= 'Referral Rate Type:               ' . ( $settings->get( 'referral_rate_type' ) ? $settings->get( 'referral_rate_type' ) . "\n" : "Default\n" );
	$return .= 'Referral Rate:                    ' . ( $settings->get( 'referral_rate' ) ? $settings->get( 'referral_rate' ) . "\n" : "Default\n" );
	$return .= 'Credit Last Referrer:             ' . ( $settings->get( 'referral_credit_last' ) ? "True\n" : "False\n" );

	// Registration Fields
	$return .= "\n" . '-- AffiliateWP Registration Settings' . "\n\n";
	$return .= 'Allow Affiliate Registrations:    ' . ( $settings->get( 'allow_affiliate_registration' ) ? "True\n" : "False\n" );
	$return .= 'Require Approval:                 ' . ( $settings->get( 'require_approval' )  ? "True\n" : "False\n" );
	$return .= 'Auto Register New Users:          ' . ( $settings->get( 'auto_register' ) ? "True\n" : "False\n" );
	$return .= 'Affiliate Area Forms              ' . ( $settings->get( 'affiliate_area_forms' ) ? $settings->get( 'affiliate_area_forms' ) . "\n" : "Default\n" );
	$return .= 'Required Registration Fields:     ' . ( $settings->get( 'required_registration_fields' ) ? implode( ', ', array_values( $settings->get( 'required_registration_fields', array() ) ) ) . "\n" : "None\n" );

	// Object counts.
	$return .= "\n" . '-- AffiliateWP Object Counts' . "\n\n";
	$return .= 'Affiliates:                       ' . affwp_format_amount( affiliate_wp()->affiliates->count(), false ) . "\n";
	$return .= 'Campaigns:                        ' . affwp_format_amount( affiliate_wp()->campaigns->count(), false ) . "\n";
	$return .= 'Coupons:                          ' . affwp_format_amount( affiliate_wp()->affiliates->coupons->count(), false ) . "\n";
	$return .= 'Creatives:                        ' . affwp_format_amount( affiliate_wp()->creatives->count(), false ) . "\n";
	$return .= 'Customers:                        ' . affwp_format_amount( affiliate_wp()->customers->count(), false ) . "\n";
	$return .= 'Payouts:                          ' . affwp_format_amount( affiliate_wp()->affiliates->payouts->count(), false ) . "\n";
	$return .= 'Referrals:                        ' . affwp_format_amount( affiliate_wp()->referrals->count(), false ) . "\n";
	$return .= 'Sales:                            ' . affwp_format_amount( affiliate_wp()->referrals->sales->count(), false ) . "\n";
	$return .= 'REST Consumers:                   ' . affwp_format_amount( affiliate_wp()->REST->consumers->count(), false ) . "\n";
	$return .= 'Visits:                           ' . affwp_format_amount( affiliate_wp()->visits->count(), false ) . "\n";

	// Integrations
	$return .= "\n" . '-- AffiliateWP Integrations' . "\n\n";

	$integrations = affiliate_wp()->integrations->query( array( 'fields' => 'ids' ) );

	foreach ( $integrations as $id ) {

		$integration = affiliate_wp()->integrations->get( $id );

		if ( ! is_wp_error( $integration ) ) {

			$needs_synced = $integration->needs_synced();
			if ( is_wp_error( $needs_synced ) ) {
				$sync_status = "Plugin Enabled, Sync Not Supported";
			} else {
				$sync_status = $integration->needs_synced() ? 'Plugin Enabled, Needs Synced' : 'Enabled, Synced';
			}

			$name = $integration->get_name();
		} else {
			$sync_status = "Sync Status Unknown";
			$name        = $id;
		}

		// Align text with other fields dynamically, based on the length of the integration name.
		$spaces = str_repeat( ' ', 33 - strlen( $name ) );
		$return .= $name . ":" . $spaces . $sync_status . "\n";
	}

	// Misc Settings
	$return .= "\n" . '-- AffiliateWP Misc Settings' . "\n\n";
	$return .= 'Enable reCaptcha:                  ' . ( $settings->get( 'recaptcha_enabled' ) ? "True\n" : "False\n" );
	$return .= 'reCaptcha Site Key:                ' . ( $settings->get( 'recaptcha_site_key' ) ? "Set\n" : "Unset\n" );
	$return .= 'reCaptcha Secret Key:              ' . ( $settings->get( 'recaptcha_secret_key' ) ? "Set\n" : "Unset\n" );
	$return .= 'Fallback Tracking Enabled:         ' . ( $settings->get( 'tracking_fallback' ) ? "True\n" : "False\n" );
	$return .= 'Ignore Zero Referrals:             ' . ( $settings->get( 'ignore_zero_referrals' ) ? "True\n" : "False\n" );
	$return .= 'Reject Unpaid Referrals on Refund: ' . ( $settings->get( 'revoke_on_refund' ) ? "True\n" : "False\n" );
	$return .= 'Default Referral URL:              ' . ( $settings->get( 'default_referral_url' ) ? $settings->get( 'default_referral_url' ) : "Default (empty)\n" );
	$return .= 'Dynamic Coupons Enabled:           ' . ( affwp_dynamic_coupons_is_setup() ? "True\n" : "False\n" );

	// AffiliateWP Templates
	$dir = trailingslashit( get_stylesheet_directory() . affiliate_wp()->templates->get_theme_template_dir_name() );

	if( is_dir( $dir ) && ( count( glob( "$dir/*" ) ) !== 0 ) ) {
		$return .= "\n" . '-- AffiliateWP Template Overrides' . "\n\n";

		foreach( glob( $dir . '/*' ) as $file ) {
			$return .= 'Filename:                 ' . basename( $file ) . "\n";
		}
	}

	// Get plugins that have an update
	$updates = get_plugin_updates();

	// Must-use plugins
	// NOTE: MU plugins can't show updates!
	$muplugins = get_mu_plugins();
	if( count( $muplugins ) > 0 ) {
		$return .= "\n" . '-- Must-Use Plugins' . "\n\n";

		foreach( $muplugins as $plugin => $plugin_data ) {
			$return .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
		}
	}

	// WordPress active plugins
	$return .= "\n" . '-- WordPress Active Plugins' . "\n\n";

	$plugins = get_plugins();
	$active_plugins = get_option( 'active_plugins', array() );

	foreach( $plugins as $plugin_path => $plugin ) {
		if( !in_array( $plugin_path, $active_plugins ) )
			continue;

		$update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
		$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
	}

	// WordPress inactive plugins
	$return .= "\n" . '-- WordPress Inactive Plugins' . "\n\n";

	foreach( $plugins as $plugin_path => $plugin ) {
		if( in_array( $plugin_path, $active_plugins ) )
			continue;

		$update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
		$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
	}

	if( is_multisite() ) {
		// WordPress Multisite active plugins
		$return .= "\n" . '-- Network Active Plugins' . "\n\n";

		$plugins = wp_get_active_network_plugins();
		$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

		foreach( $plugins as $plugin_path ) {
			$plugin_base = plugin_basename( $plugin_path );

			if( !array_key_exists( $plugin_base, $active_plugins ) )
				continue;

			$update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[$plugin_path]->update->new_version . ')' : '';
			$plugin  = get_plugin_data( $plugin_path );
			$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
		}
	}

	// Server configuration (really just versioning)
	$return .= "\n" . '-- Webserver Configuration' . "\n\n";
	$return .= 'PHP Version:              ' . PHP_VERSION . "\n";
	$return .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";
	$return .= 'Webserver Info:           ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
	$return .= 'SSL Configured:           ' . ( is_ssl() ? 'Yes' : 'No' ) . "\n";
	$return .= 'Pantheon:                 ' . ( affiliate_wp()->tracking->is_pantheon ? 'Yes' : 'No' ) . "\n";

	// PHP configuration
	$return .= "\n" . '-- PHP Configuration' . "\n\n";
	$return .= 'Memory Limit:             ' . ini_get( 'memory_limit' ) . "\n";
	$return .= 'Upload Max Size:          ' . ini_get( 'upload_max_filesize' ) . "\n";
	$return .= 'Post Max Size:            ' . ini_get( 'post_max_size' ) . "\n";
	$return .= 'Upload Max Filesize:      ' . ini_get( 'upload_max_filesize' ) . "\n";
	$return .= 'Time Limit:               ' . ini_get( 'max_execution_time' ) . "\n";
	$return .= 'Max Input Vars:           ' . ini_get( 'max_input_vars' ) . "\n";
	$return .= 'Display Errors:           ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";

	// PHP extensions and such
	$return .= "\n" . '-- PHP Extensions' . "\n\n";
	$return .= 'cURL:                     ' . ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) . "\n";
	$return .= 'fsockopen:                ' . ( function_exists( 'fsockopen' ) ? 'Supported' : 'Not Supported' ) . "\n";
	$return .= 'SOAP Client:              ' . ( class_exists( 'SoapClient' ) ? 'Installed' : 'Not Installed' ) . "\n";
	$return .= 'Suhosin:                  ' . ( extension_loaded( 'suhosin' ) ? 'Installed' : 'Not Installed' ) . "\n";

	$return .= "\n" . '### End System Info ###';

	return $return;
}
