<?php
/**
 * Core Action Callbacks
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

/**
 * Hooks AffiliateWP actions, when present in the $_REQUEST superglobal. Every affwp_action
 * present in $_REQUEST is called using WordPress's do_action function. These
 * functions are called on init.
 *
 * @since 1.0
 * @return void
*/
function affwp_do_actions() {
	if ( isset( $_REQUEST['affwp_action'] ) ) {
		$action = $_REQUEST['affwp_action'];

		/**
		 * Fires for every AffiliateWP action passed via `affwp_action`.
		 *
		 * The dynamic portion of the hook name, `$action`, refers to the action passed via
		 * the `affwp_action` parameter.
		 *
		 * @since 1.0
		 *
		 * @param array $_REQUEST Request data.
		 */
		do_action( "affwp_{$action}", $_REQUEST );
	}
}
add_action( 'init', 'affwp_do_actions', 9 );

// Process affiliate notification settings
add_action( 'affwp_update_profile_settings', 'affwp_update_profile_settings' );

/**
 * Removes single-use query args derived from executed actions in the admin.
 *
 * @since 1.8.6
 *
 * @param array $query_args Removable query arguments.
 * @return array Filtered list of removable query arguments.
 */
function affwp_remove_query_args( $query_args ) {
	// Prevent certain repeated AffWP actions on refresh.
	if ( isset( $_GET['_wpnonce'] )
		&& (
			isset( $_GET['affiliate_id'] )
			|| isset( $_GET['creative_id'] )
			|| isset( $_GET['referral_id'] )
			|| isset( $_GET['visit_id'] )
			|| isset( $_GET['payout_id'] )
	     )
	) {
		$query_args[] = '_wpnonce';
	}

	if ( ( isset( $_GET['filter_from'] ) || isset( $_GET['filter_to'] ) )
		&& ( isset( $_GET['range'] ) && 'other' !== $_GET['range'] )
	) {
		$query_args[] = 'filter_from';
		$query_args[] = 'filter_to';
	}

	$query_args[] = 'affwp_notice';

	if ( isset( $_GET['register_affiliate'] ) ) {
		$query_args[] = 'register_affiliate';
	}

	if ( isset( $_GET['generate_coupon'] ) ) {
		$query_args[] = 'generate_coupon';
	}

	if ( isset( $_GET['delete_coupon'] ) ) {
		$query_args[] = 'delete_coupon';
	}
	
	return $query_args;
}
add_filter( 'removable_query_args', 'affwp_remove_query_args' );


/**
 * Updates the website URL associated with a given affiliate's user account.
 *
 * @since 2.1
 *
 * @param int   $affiliate_id Affiliate ID.
 * @param array $args         Arguments passed to {@see Affiliate_WP_DB_Affiliates::add()}.
 * @return int|\WP_Error|false The updated user's ID if successful, WP_Error object on error, otherwise false.
 */
function affwp_process_add_affiliate_website( $affiliate_id, $args ) {
	$updated = false;

	if ( ! empty( $args['website_url'] ) ) {

		$website_url = esc_url( $args['website_url'] );

		$user_id = affwp_get_affiliate_user_id( $affiliate_id );

		$updated = wp_update_user( array(
			'ID'       => $user_id,
			'user_url' => $website_url
		) );

	}

	return $updated;

}
add_action( 'affwp_insert_affiliate', 'affwp_process_add_affiliate_website', 11, 2 );

/**
 * Denotes the Affiliate Area Page as such in the pages list table.
 *
 * @since 2.1.11
 *
 * @param array   $post_states An array of post display states.
 * @param WP_Post $post        The current post object.
 */
function affwp_display_post_states( $post_states, $post ) {

	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();

		// Bail if the current screen is unavailable.
		if ( null === $screen ) {
			return $post_states;
		}

		// Bail if not on the Pages screen.
		if ( 'edit-page' !== $screen->id ) {
			return $post_states;
		}

		if ( affwp_get_affiliate_area_page_id() === $post->ID ) {
			$post_states['affwp_page_for_affiliate_area'] = __( 'Affiliate Area Page', 'affiliate-wp' );
		}
	}

	return $post_states;

}
add_filter( 'display_post_states', 'affwp_display_post_states', 10, 2 );

/**
 * Sets the decimal places to 8 when Bitcoin is selected as the currency.
 *
 * @since 2.1.11
 *
 * @param int 	$decimal_places Decimal Places.
 * @return int 	Number of decimal places
 */
function affwp_btc_decimal_count( $decimal_places ) {

	if ( 'BTC' == affwp_get_currency() ) {
		return 8;
	}

	return $decimal_places;

}
add_filter( 'affwp_decimal_count', 'affwp_btc_decimal_count' );

/**
 * Remove template_redirect action in the Show Affiliate Coupons add-on.
 *
 * @since 2.6
 *
 * @return void
 */
function affwp_show_affiliate_coupons_remove_template_redirect() {

	if ( ! function_exists( 'affiliatewp_show_affiliate_coupons' ) ) {
		return;
	}

	$affiliatewp_show_affiliate_coupons = affiliatewp_show_affiliate_coupons();
	remove_action( 'template_redirect', array( $affiliatewp_show_affiliate_coupons, 'no_access_redirect' ) );

}
add_action( 'template_redirect', 'affwp_show_affiliate_coupons_remove_template_redirect', 5 );

/**
 * Handles a custom affwp_is_affiliate argument when passed to WP_User_Query
 * to filter affiliates in or out.
 *
 * `$query` is passed from the core hook by reference.
 *
 * @since 2.3.1
 * @since 2.7.1 Relocated for use outside admin contexts.
 *
 * @param \WP_User_Query $query WP_User_Query instance.
 */
function affwp_handle_wp_user_query( $query ) {
	// Bail if the argument isn't set.
	if ( ! isset( $query->query_vars['affwp_is_affiliate'] ) ) {
		return;
	}
	global $wpdb;

	$affiliates_table = affiliate_wp()->affiliates->table_name;

	if ( true === $query->query_vars['affwp_is_affiliate'] ) {
		$where = ' AND ID IN (SELECT user_id FROM ' . $affiliates_table . ')';
	} else {
		$where = ' AND ID NOT IN (SELECT user_id FROM ' . $affiliates_table . ')';
	}

	$query->query_where .= $where;
}
add_action( 'pre_user_query', 'affwp_handle_wp_user_query' );

/**
 * Forces a specific set of user meta fields to fetch data from the affiliate meta table, instead.
 *
 * @since 2.8
 *
 * @param mixed  $value     The value to return, either a single metadata value or an array
 *                          of values depending on the value of `$single`. Default null.
 * @param int    $object_id ID of the object metadata is for.
 * @param string $meta_key  Metadata key.
 * @param bool   $single    Whether to return only the first value of the specified `$meta_key`.
 *
 * @return mixed Single metadata value, or array of values. Null if the value does not exist.
 *               False if there's a problem with the parameters passed to the function.
 */
function affwp_intercept_migrated_user_meta_fields( $value, $object_id, $meta_key, $single ) {
	if ( in_array( $meta_key, affwp_get_current_migrated_user_meta_fields() ) ) {
		$affiliate_id = affwp_get_affiliate_id( $object_id );
		$value        = affwp_get_affiliate_meta( $affiliate_id, affwp_remove_prefix( $meta_key ), $single );
	}

	return $value;
}

add_filter( 'get_user_metadata', 'affwp_intercept_migrated_user_meta_fields', 2, 5 );

/**
 * Forces a specific set of user meta fields to update from affiliate meta.
 *
 * @since 2.8
 *
 * @param null|bool $value      Whether to allow updating metadata for the given type.
 * @param int       $object_id  ID of the object metadata is for.
 * @param string    $meta_key   Metadata key.
 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
 * @param mixed     $prev_value Optional. Previous value to check before updating.
 *                              If specified, only update existing metadata entries with
 *                              this value. Otherwise, update all entries.
 */
function affwp_intercept_migrated_user_meta_field_updates( $value, $object_id, $meta_key, $meta_value, $prev_value ) {

	if ( in_array( $meta_key, affwp_get_current_migrated_user_meta_fields() ) ) {
		$affiliate_id = affwp_get_affiliate_id( $object_id );
		$value        = affwp_update_affiliate_meta( $affiliate_id, affwp_remove_prefix( $meta_key ), $meta_value, $prev_value );
	}

	return $value;
}

add_filter( 'update_user_metadata', 'affwp_intercept_migrated_user_meta_field_updates', 2, 5 );

/**
 * Forces a specific set of user meta fields to be added to affiliate meta instead of user meta.
 *
 * @since 2.8
 *
 * @param null|bool $value      Whether to allow adding metadata for the given type.
 * @param int       $object_id  ID of the object metadata is for.
 * @param string    $meta_key   Metadata key.
 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
 * @param bool      $unique     Whether the specified meta key should be unique for the object.
 */
function affwp_intercept_migrated_user_meta_field_add( $value, $object_id, $meta_key, $meta_value, $unique ) {

	if ( in_array( $meta_key, affwp_get_current_migrated_user_meta_fields() ) ) {
		$affiliate_id = affwp_get_affiliate_id( $object_id );
		$value        = affwp_add_affiliate_meta( $affiliate_id, affwp_remove_prefix( $meta_key ), $meta_value, $unique );
	}

	return $value;
}

add_filter( 'add_user_metadata', 'affwp_intercept_migrated_user_meta_field_add', 2, 5 );

/**
 * Delete related affiliate meta when migrated user meta is deleted.
 *
 * @since 2.8
 *
 * @param string[] $meta_ids    An array of metadata entry IDs to delete.
 * @param int      $object_id   ID of the object metadata is for.
 * @param string   $meta_key    Metadata key.
 * @param mixed    $_meta_value Metadata value. Serialized if non-scalar.
 */
function affwp_delete_affiliate_meta_when_migrated_user_meta_is_deleted( $meta_ids, $object_id, $meta_key, $_meta_value ) {
	if ( in_array( $meta_key, affwp_get_current_migrated_user_meta_fields() ) ) {
		$affiliate_id = affwp_get_affiliate_id( $object_id );
		affwp_delete_affiliate_meta( $affiliate_id, affwp_remove_prefix( $meta_key ) );
	}
}

add_action( "delete_user_metadata", 'affwp_delete_affiliate_meta_when_migrated_user_meta_is_deleted', 2, 4 );
