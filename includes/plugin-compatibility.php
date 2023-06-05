<?php
/**
 * Plugin Compatibility Actions
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

/**
 *  Prevents OptimizeMember from intefering with our ajax user search
 *
 *  @since 1.6.2
 *  @return void
 */
function affwp_optimize_member_user_query( $search_term = '' ) {

	remove_action( 'pre_user_query', 'c_ws_plugin__optimizemember_users_list::users_list_query', 10 );

}
add_action( 'affwp_pre_search_users', 'affwp_optimize_member_user_query' );

/**
 *  Prevents OptimizeMember from redirecting affiliates to the
 *  "Members Home Page/Login Welcome Page" when they log in
 *
 *  @since 1.7.16
 *  @return boolean
 */
function affwp_optimize_member_prevent_affiliate_redirect( $return, $vars ) {

	if ( doing_action( 'affwp_user_login' ) || doing_action( 'affwp_affiliate_register' ) ) {
		$return = false;
	}

	return $return;

}
add_filter( 'ws_plugin__optimizemember_login_redirect', 'affwp_optimize_member_prevent_affiliate_redirect', 10, 2 );

/**
 *  Fixes affiliate redirects when "Allow WishList Member To Handle Login Redirect"
 *  and "Allow WishList Member To Handle Logout Redirect" are enabled in WishList Member
 *
 *  @since 1.7.13
 *  @return boolean
 */
function affwp_wishlist_member_redirects( $return ) {

    $user    = wp_get_current_user();
    $user_id = $user->ID;

    if ( affwp_is_affiliate( $user_id ) ) {
        $return = true;
    }

    return $return;

}
add_filter( 'wishlistmember_login_redirect_override', 'affwp_wishlist_member_redirects' );
add_filter( 'wishlistmember_logout_redirect_override', 'affwp_wishlist_member_redirects' );

/**
 * Disables the mandrill_nl2br filter while sending AffiliateWP emails
 *
 * @since 1.7.17
 * @return void
 */
function affwp_disable_mandrill_nl2br() {
	add_filter( 'mandrill_nl2br', '__return_false' );
}
add_action( 'affwp_email_send_before', 'affwp_disable_mandrill_nl2br');

/**
 * Remove sptRemoveVariationsFromLoop() from pre_get_posts when query var is present.
 *
 * See https://github.com/AffiliateWP/AffiliateWP/issues/1586
 *
 * @since 1.9
 * @return void
 */
function affwp_simple_page_test_compat() {

	if( ! defined( 'SPT_PLUGIN_DIR' ) ) {
		return;
	}

	$tracking = affiliate_wp()->tracking;

	if( empty( $tracking ) ) {
		return;
	}

	if( $tracking->was_referred() ) {

		remove_action( 'pre_get_posts', 'sptRemoveVariationsFromLoop', 10 );

	}

}
add_action( 'pre_get_posts', 'affwp_simple_page_test_compat', -9999 );

/**
 * Removes content filtering originating from Encyclopedia Pro in the affiliate area Creatives tab.
 *
 * @since 2.0.2
 *
 * @param int|false $affiliate_id ID for the current affiliate.
 * @param string    $active_tab   Slug for the currently-active tab.
 */
function affwp_encyclopedia_pro_creatives_affiliate_area_compat( $affiliate_id, $active_tab = '' ) {
	if ( 'creatives' === $active_tab ) {
		add_filter( 'encyclopedia_link_terms_in_post', '__return_false' );
	}
}
add_action( 'affwp_affiliate_dashboard_top', 'affwp_encyclopedia_pro_creatives_affiliate_area_compat', 10, 2 );

/**
 * Removes the RCP Prevent Account Sharing check when logging in or registering thhrough Affiliate Area
 *
 * @since 2.1
 *
 */
function affwp_remove_rcp_can_be_logged_in_check() {
	remove_action( 'init', 'rcp_can_user_be_logged_in', 10 );
}
add_action( 'affwp_pre_process_login_form', 'affwp_remove_rcp_can_be_logged_in_check' );
add_action( 'affwp_pre_process_register_form', 'affwp_remove_rcp_can_be_logged_in_check' );

/**
 * Circumvents an incompatibility between WPS Hide Login and Signup Referrals during activation.
 *
 * During plugin activation, WPS Hide Login broadly searches for *wp-signup*
 * in the URL and calls wp_die() if found. Unfortunately, this global strpos
 * search does not take into account other things that might use 'wp-signup'
 * in its filename, such as the plugin file name affiliatewp-signup-referrals.
 *
 * This filter circumvents that check.
 *
 * @since 2.2.16
 *
 * @param bool $enable Whether to "enable" circumventing the WPS Hide Login check. Default false.
 * @return bool Whether to circumvent the check.
 */
function affwp_enable_signup_referrals_activation_with_wps_hide_login( $enable ) {
	if ( isset( $_SERVER['REQUEST_URI'] )
		&& false !== strpos( $_SERVER['REQUEST_URI'], 'affiliatewp-signup-referrals' )
	) {
		$enable = true;
	}

	return $enable;
}
add_filter( 'wps_hide_login_signup_enable', 'affwp_enable_signup_referrals_activation_with_wps_hide_login' );

/**
 * Prevent Edwiser Bridge – WordPress Moodle LMS Integration plugin from overwriting the email "From Name" value.
 *
 * @since 2.6.1
 *
 * @param string $from_name The email from name.
 * @return string The email from name.
 */
function affwp_edwiser_bridge_prevent_from_name_from_being_overwritten( $from_name ) {

	if ( did_action( 'affwp_email_send_before' ) ) {
		$from_name = affiliate_wp()->emails->get_from_name();
	}

	return $from_name;
}
add_filter( 'pre_option_eb_mail_from_name', 'affwp_edwiser_bridge_prevent_from_name_from_being_overwritten' );

/**
 * Integrates with the User Switching plugin to add 'Switch to' row actions to the Affiliates list table.
 *
 * @since 2.6.4
 * @since 2.6.5 Adjusted to bail early if the affiliate user no longer exists.
 *
 * @param array            $row_actions Row actions array.
 * @param \AffWP\Affiliate $affiliate   Current affiliate.
 * @return array (Maybe) modified row actions.
 */
function affwp_user_switching_switch_to_affiliate( $row_actions, $affiliate ) {
	if ( method_exists( 'user_switching', 'maybe_switch_url' ) ) {
		$base_query_args = array(
			'page' => 'affiliate-wp-affiliates',
		);

		$user = get_user_by( 'id', $affiliate->user_id );

		// Invalid user, bail.
		if ( ! $user ) {
			return $row_actions;
		}

		$url  = user_switching::maybe_switch_url( $user );

		if ( $url ) {
			if ( isset( $row_actions['delete'] ) ) {
				$delete_row_action = $row_actions['delete'];
				unset( $row_actions['delete'] );
			} else {
				$delete_row_action = false;
			}

			$url  = add_query_arg( $base_query_args, $url );
			$name = affwp_get_affiliate_name( $affiliate );

			$row_actions['switch_to'] = sprintf( '<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Switch To', 'affiliate-wp' )
			);

			if ( false !== $delete_row_action ) {
				$row_actions['delete'] = $delete_row_action;
			}
		}
	}

	return $row_actions;
}
add_filter( 'affwp_affiliate_row_actions', 'affwp_user_switching_switch_to_affiliate', 100, 2 );

/**
 * Deactivates the AffiliateWP Blocks add-on in AffiliateWP 2.8+.
 *
 * @since 2.8
 */
function affwp_deactivate_affiliatewp_blocks() {
	if ( class_exists( 'AffiliateWP_Blocks' ) ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_paths = array_keys( get_plugins() );

		$found = preg_grep( "/\/affiliatewp-blocks.php$/", $plugin_paths );

		if ( ! empty( $found ) ) {
			$path = reset( $found );

			// Deactivate AffiliateWP Blocks.
			deactivate_plugins( $path );
		}
	}
}
add_action( 'admin_init', 'affwp_deactivate_affiliatewp_blocks' );
