<?php
/**
 * Coupon functions
 *
 * @package     AffiliateWP
 * @subpackage  Core/Coupons
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6
 */

/**
 * Retrieves the coupon object.
 *
 * @since 2.6
 *
 * @param AffWP\Affiliate\Coupon|int|string $coupon Coupon object, coupon ID, or coupon code.
 * @return AffWP\Affiliate\Coupon|false Coupon object if found, otherwise false.
 */
function affwp_get_coupon( $coupon ) {

	if ( is_object( $coupon ) && isset( $coupon->coupon_id ) ) {
		$coupon_id = $coupon->coupon_id;
	} elseif ( is_int( $coupon ) ) {
		$coupon_id = $coupon;
	} elseif ( is_string( $coupon ) ) {
		$coupon_id = affiliate_wp()->affiliates->coupons->get_column_by( 'coupon_id', 'coupon_code', $coupon );

		if ( ! $coupon_id ) {
			return false;
		}
	} else {
		return false;
	}

	return affiliate_wp()->affiliates->coupons->get_object( $coupon_id );
}

/**
 * Retrieves an affiliate's coupon.
 *
 * @since 2.6
 *
 * @param int|AffWP\Affiliate $affiliate Affiliate ID or object.
 * @param int|string          $coupon    Coupon ID or code.
 * @return AffWP\Affiliate\Coupon|\WP_Error Coupon object associated with the given affiliate and coupon or WP_Error object.
 */
function affwp_get_affiliate_coupon( $affiliate, $coupon ) {

	if ( ! $affiliate = affwp_get_affiliate( $affiliate ) ) {
		return new \WP_Error( 'invalid_coupon_affiliate', __( 'Invalid affiliate', 'affiliate-wp' ) );
	}

	$args = array(
		'affiliate_id' => $affiliate->ID,
		'number'       => 1,
	);

	if ( is_string( $coupon ) ) {
		$args['coupon_code'] = $coupon;
	} elseif ( is_numeric( $coupon ) ) {
		$args['coupon_id'] = intval( $coupon );
	}

	$affiliate_coupon = affiliate_wp()->affiliates->coupons->get_coupons( $args );

	if ( ! empty( $affiliate_coupon ) ) {
		return $affiliate_coupon[0];
	}

	return new \WP_Error( 'no_coupons', __( 'No coupons were found.', 'affiliate-wp' ) );
}

/**
 * Retrieves an affiliate's coupon code.
 *
 * @since 2.6
 *
 * @param int|AffWP\Affiliate $affiliate Affiliate ID or object.
 * @param int                 $coupon_id Coupon ID.
 * @return string Affiliate's coupon code or empty string.
 */
function affwp_get_affiliate_coupon_code( $affiliate, $coupon_id ) {

	$coupon_code = '';

	if ( ! is_numeric( $coupon_id ) ) {
		return $coupon_code;
	}

	$coupon = affwp_get_affiliate_coupon( $affiliate, intval( $coupon_id ) );

	if ( ! is_wp_error( $coupon ) ) {
		$coupon_code = $coupon->coupon_code;
	}

	return $coupon_code;
}

/**
 * Sanitizes a global affiliate coupon code.
 *
 * @since 2.6
 * @since 2.8 Modifed for scenarios using the coupon format setting.
 *
 * @param string $code Raw coupon code.
 * @return string Sanitized coupon code.
 */
function affwp_sanitize_coupon_code( $code ) {
	// Remove special characters.
	$code = sanitize_key( $code );

	// Remove underscores.
	$code = str_replace( '_', '', $code );

	$use_hyphens = boolval( affiliate_wp()->settings->get( 'coupon_hyphen_delimiter' ) );

	if ( true === $use_hyphens ){
		// Replace multiple hyphens with a single. For example if the affiliate has no first name.
		$code = preg_replace( '(-{2,})', '-', $code );

		// Remove hyphen from beginning and end.
		$code = trim( $code, '-' );
	} else {
		// Remove all hyphens.
		$code = str_replace( '-', '', $code );
	}

	// Return capitalized code.
	return strtoupper( $code );
}

/**
 * Sanitizes a coupon's custom text.
 *
 * @since 2.8
 *
 * @param string $custom_text Custom text.
 * @return string Sanitized custom text.
 */
function affwp_sanitize_coupon_custom_text( $custom_text ) {
	/**
	 * Filters the max character limit for dynamic coupon custom text.
	 *
	 * @since 2.8
	 *
	 * @param int $max_length Max char limit default is 50.
	 */
	$max_length =  apply_filters( 'affwp_coupons_custom_text_limit', 50 );

	if ( ! is_int ( $max_length ) || $max_length > 191 ) {
		return new \WP_Error(
			'invalid_coupon_max_length',
			'Max length must be an integer and less than 191 characters.'
		);
	}

	$custom_text = sanitize_key( $custom_text );

	// Remove underscores and hyphens.
	$special_char = array( '_', '-');

	$custom_text = str_replace( $special_char, '', $custom_text );

	// If greater than max length, shorten the string.
	if ( strlen( $custom_text ) > $max_length ) {
		$custom_text = substr( $custom_text, 0, $max_length );
	}

	// Return capitalized custom text.
	return strtoupper( $custom_text );
}
  
/**
 * Validates an affiliate coupon code.
 *
 * @since 2.8
 *
 * @param string $code Coupon code.
 *
 * @return bool Return true if unique and not over char limit. Otherwise false.
 */
function affwp_validate_coupon_code( $code ) {
	if ( empty( $code ) ) {
		return false;
	}
	// Check if unique. If not, return false.
	$coupon_exists = affwp_get_coupon( $code );

	if ( ! empty( $coupon_exists ) ) {
		return false;
	}

	// If the code is over the char limit 191, return false.
	$max_char_limit_for_coupon = 191;

	if ( strlen( $code ) > $max_char_limit_for_coupon  ) {
		return false;
	}

	return true;
}

/**
 * Retrieves an options list of integrations that supports dynamic coupons and their respective labels.
 *
 * @since 2.6
 *
 * @return array Options array where the key is the integration ID and value is the integration name.
 */
function affwp_get_dynamic_coupons_integrations() {

	$dynamic_coupon_enabled_integrations = affiliate_wp()->integrations->query( array(
		'supports' => 'dynamic_coupons',
		'status'   => 'enabled',
		'fields'   => array( 'ids', 'name' ),
	) );

	if ( ! is_wp_error( $dynamic_coupon_enabled_integrations ) ) {
		$dynamic_coupon_integrations = $dynamic_coupon_enabled_integrations;
	} else {
		$dynamic_coupon_integrations = array();
	}

	return $dynamic_coupon_integrations;
}

/**
 * Retrieves an options list of integrations that supports manual coupons and their respective labels.
 *
 * @since 2.6
 *
 * @return array Options array where the key is the integration ID and value is the integration name.
 */
function affwp_get_manual_coupons_integrations() {

	$manual_coupon_enabled_integrations = affiliate_wp()->integrations->query( array(
		'supports' => 'manual_coupons',
		'status'   => 'enabled',
		'fields'   => array( 'ids', 'name' ),
	) );

	if ( ! is_wp_error( $manual_coupon_enabled_integrations ) ) {
		$manual_coupon_integrations = $manual_coupon_enabled_integrations;
	} else {
		$manual_coupon_integrations = array();
	}

	return $manual_coupon_integrations;
}

/**
 * Checks if dynamic coupons is setup.
 *
 * @since 2.6
 *
 * @return bool True if dynamic coupons is setup, false otherwise.
 */
function affwp_dynamic_coupons_is_setup() {

	$dynamic_coupons_setup = false;

	$enabled_coupons_integrations = affwp_get_dynamic_coupons_integrations();

	if ( $enabled_coupons_integrations ) {

		$dynamic_coupon_template = affiliate_wp()->settings->get( 'coupon_template_woocommerce' );

		if ( $dynamic_coupon_template ) {
			$dynamic_coupons_setup = true;
		}

	}

	return $dynamic_coupons_setup;
}

/**
 * Retrieve all coupons assigned to the affiliate.
 *
 * @since 2.6
 *
 * @param int|\AffWP\Affiliate $affiliate    Affiliate ID or object.
 * @param bool                 $details_only Optional. Whether to retrieve the coupon details only (for display).
 *                                           Default true. If false, the full coupon objects will be retrieved.
 * @return array[]|\AffWP\Affiliate\Coupon[]|array Array of arrays of coupon details, an array of coupon objects,
 *                                                 dependent upon whether `$details_only` is true or false,
 *                                                 respectively, otherwise an empty array.
 */
function affwp_get_affiliate_coupons( $affiliate, $details_only = true ) {

	$coupons = array();

	if ( ! $affiliate = affwp_get_affiliate( $affiliate ) ) {
		return $coupons;
	}

	$manual_coupons  = affwp_get_manual_affiliate_coupons( $affiliate, $details_only );
	$dynamic_coupons = affwp_get_dynamic_affiliate_coupons( $affiliate, $details_only );

	if ( ! empty( $manual_coupons ) ) {
		foreach ( $manual_coupons as $coupon_id => $coupon ) {
			$coupons['manual'][ $coupon_id ] = $coupon;
		}
	}

	if ( ! empty( $dynamic_coupons ) ) {
		foreach ( $dynamic_coupons as $coupon_id => $coupon ) {
			$coupons['dynamic'][ $coupon_id ] = $coupon;
		}
	}

	/**
	 * Filters the list of coupons associated with an affiliate.
	 *
	 * @since 2.6
	 *
	 * @param array $coupons      The affiliate's coupons.
	 * @param int   $affiliate_id Affiliate ID.
	 * @param bool  $details_only Whether only details (for display use) were retrieved or not.
	 */
	return apply_filters( 'affwp_get_affiliate_coupons', $coupons, $affiliate->ID, $details_only );
}

/**
 * Retrieves all manual coupons associated with an affiliate.
 *
 * @since 2.6
 *
 * @param int|\AffWP\Affiliate $affiliate    Affiliate ID or object.
 * @param bool                 $details_only Optional. Whether to retrieve the coupon details only (for display).
 *                                           Default true. If false, the full coupon objects will be retrieved.
 * @return array[]|\AffWP\Affiliate\Coupon[]|array Array of arrays of coupon details, an array of coupon objects,
 *                                                 dependent upon whether `$details_only` is true or false,
 *                                                 respectively, otherwise an empty array.
 */
function affwp_get_manual_affiliate_coupons( $affiliate, $details_only = true ) {
	$coupons = array();

	if ( ! $affiliate = affwp_get_affiliate( $affiliate ) ) {
		return $coupons;
	}

	$manual_coupons_integrations = affwp_get_manual_coupons_integrations();

	if ( ! empty( $manual_coupons_integrations ) ) {
		$coupons = array();

		$integrations = array_keys( $manual_coupons_integrations );

		if ( ! empty( $integrations ) ) {
			foreach ( $integrations as $integration ) {
				$integration = affiliate_wp()->integrations->get( $integration );

				if ( ! is_wp_error( $integration ) && $integration->is_active() ) {
					$integration_coupons = $integration->get_coupons_of_type( 'manual', $affiliate, $details_only );

					if ( ! empty( $integration_coupons ) ) {
						foreach ( $integration_coupons as $coupon_id => $coupon ) {
							$coupons[ $coupon_id ] = $coupon;
						}
					}
				}
			}
		}
	}

	/**
	 * Filters the list of manual coupons associated with an affiliate.
	 *
	 * @since 2.6
	 *
	 * @param array $coupons      The affiliate's coupons.
	 * @param int   $affiliate_id Affiliate ID.
	 * @param bool  $details_only Whether only details (for display use) were retrieved or not.
	 */
	return apply_filters( 'affwp_get_manual_affiliate_coupons', $coupons, $affiliate->ID, $details_only );
}

/**
 * Retrieves all dynamic coupons associated with an affiliate.
 *
 * @since 2.6
 *
 * @param int|\AffWP\Affiliate $affiliate    Affiliate ID or object.
 * @param bool                 $details_only Optional. Whether to retrieve the coupon details only (for display).
 *                                           Default true. If false, the full coupon objects will be retrieved.
 * @return array[]|\AffWP\Affiliate\Coupon[]|array Array of arrays of coupon details, an array of coupon objects,
 *                                                 dependent upon whether `$details_only` is true or false,
 *                                                 respectively, otherwise an empty array.
 */
function affwp_get_dynamic_affiliate_coupons( $affiliate, $details_only = true ) {
	$coupons = array();

	if ( ! $affiliate = affwp_get_affiliate( $affiliate ) ) {
		return $coupons;
	}

	$dynamic_coupons_integrations = affwp_get_dynamic_coupons_integrations();

	if ( ! empty( $dynamic_coupons_integrations ) ) {
		$coupons = array();

		$integrations = array_keys( $dynamic_coupons_integrations );

		if ( ! empty( $integrations ) ) {
			foreach ( $integrations as $integration ) {
				$integration = affiliate_wp()->integrations->get( $integration );

				if ( ! is_wp_error( $integration ) && $integration->is_active() ) {
					$integration_coupons = $integration->get_coupons_of_type( 'dynamic', $affiliate, $details_only );

					if ( ! empty( $integration_coupons ) ) {
						foreach ( $integration_coupons as $coupon_id => $coupon ) {
							$coupons[ $coupon_id ] = $coupon;
						}
					}
				}

			}

			// Remove duplicates (i.e. globally dynamic coupons).
			if ( ! empty( $coupons ) ) {
				$coupons = array_unique( $coupons, SORT_REGULAR );
			}
		}
	}

	/**
	 * Filters the list of dynamic coupons associated with an affiliate.
	 *
	 * @since 2.6
	 *
	 * @param array $coupons      The affiliate's coupons.
	 * @param int   $affiliate_id Affiliate ID.
	 * @param bool  $details_only Whether only details (for display use) were retrieved or not.
	 */
	return apply_filters( 'affwp_get_dynamic_affiliate_coupons', $coupons, $affiliate->ID, $details_only );
}

/**
 * Retrieves a list of potential coupon types.
 *
 * @since 2.6
 *
 * @return array Array of coupon types.
 */
function affwp_get_coupon_types() {
	return array( 'manual', 'dynamic' );
}

/**
 * Retrieves a list of coupon type labels.
 *
 * @since 2.6
 *
 * @return array Coupon type labels, keyed by coupon type.
 */
function affwp_get_coupon_type_labels() {
	return array(
		'manual' => __( 'Manual', 'affiliate-wp' ),
		'dynamic' => __( 'Dynamic', 'affiliate-wp' ),
	);
}

/**
 * Retrieves a coupon by a given field and value.
 *
 * @since 2.7
 *
 * @param string $field Coupon object field.
 * @param mixed  $value Field value.
 * @return \AffWP\Affiliate\Coupon|\WP_Error Coupon object if found, otherwise a WP_Error object.
 */
function affwp_get_coupon_by( $field, $value ) {
	$result = affiliate_wp()->affiliates->coupons->get_by( $field, $value );

	if ( is_object( $result ) ) {
		$coupon = affwp_get_coupon( intval( $result->coupon_id ) );
	} else {
		$coupon = new \WP_Error(
			'invalid_coupon_field',
			sprintf( 'No coupon could be retrieved with a(n) \'%1$s\' field value of %2$s.', $field, $value )
		);
	}

	return $coupon;
}

/**
 * Replaces the given coupon code merge tag.
 *
 * @since 2.8
 *
 * @param array $coupon Optional. Coupon creation arguments usually including: affiliate_id, coupon_code, and integration.
 * @return string Coupon code or preview coupon code.
 */
function affwp_coupon_tag_coupon_code( $coupon = array() ) {
	if ( ! empty( $coupon ) && isset( $coupon['coupon_code'] ) ) {
		return $coupon['coupon_code'];
	} else {
		return 'FGFUVCQOK1';
	}
}

/**
 *  Replaces the given coupon amount merge tag.
 *
 * @since 2.8
 *
 * @param array $coupon Optional coupon creation arguments usually include: affiliate_id, coupon_code, and integration.
 * @return string Coupon amount or empty string or '10' for the preview if the coupon template is not set.
 */
function affwp_coupon_tag_coupon_amount( $coupon = array() ) {
	if ( ! empty( $coupon ) && isset( $coupon['integration'] ) ) {
		// Use coupon's integration type. TODO: Abstract this out for any dynamic coupons integration.
		if ( 'coupon_template_woocommerce' === $coupon['integration'] ) {
			// Get coupon template ID.
			$woocomerce_template_id = affiliate_wp()->settings->get( $coupon['integration'] );

			// Use the ID to get the coupon amount.
			$coupon_amount = get_post_meta( $woocomerce_template_id, 'coupon_amount', true );

			return affwp_sanitize_coupon_code( $coupon_amount );
		}
		// Invalid integration type returns empty string.
		return '';
	}

	// For coupon preview: currently uses amount from Woocommerce Coupon Template if set.
	if ( false !== affiliate_wp()->settings->get( 'coupon_template_woocommerce' ) ) {
		$woocomerce_template_id = affiliate_wp()->settings->get( 'coupon_template_woocommerce' );

		$coupon_amount = get_post_meta( $woocomerce_template_id, 'coupon_amount', true );

		return affwp_sanitize_coupon_code( $coupon_amount );
	}
	// Default to 10 if no integration is set.
	return '10';
}

/**
 * Replaces the given user name merge tag.
 *
 * @since 2.8
 *
 * @param array $coupon Optional. Coupon creation arguments usually include: affiliate_id, coupon_code, and integration.
 * @return string The affiliate or current logged in user's user name.
 */
function affwp_coupon_tag_user_name( $coupon = array() ) {
	if ( ! empty( $coupon ) && isset( $coupon['affiliate_id'] ) ) {
		if ( ! empty( $coupon['affiliate_id'] ) ) {
			$user_name = affwp_get_affiliate_username( $coupon['affiliate_id'] );
		} else {
			return '';
		}
	} else {
		// Get current logged in user's username.
		$user_info = get_userdata( get_current_user_id() );
		$user_name = ! empty ( $user_info->user_login ) ? esc_html( $user_info->user_login ) : '';
	}

	// Limit username length to 10 char.
	if ( strlen( $user_name ) > 10 ) {
		$user_name = substr( $user_name, 0, 10 );
	}

	return affwp_sanitize_coupon_code( $user_name );
}

/**
 * Replaces the given first name merge tag.
 *
 * @since 2.8
 *
 * @param array $coupon Optional. Coupon creation arguments usually include: affiliate_id, coupon_code, and integration.
 * @return string The affiliate or current logged in user's first name or 'Bob' for the preview.
 */
function affwp_coupon_tag_first_name( $coupon = array() ) {
	if ( ! empty( $coupon ) && isset( $coupon['affiliate_id'] ) ) {
		if ( $coupon['affiliate_id'] ) {
			$first_name = affwp_get_affiliate_first_name( $coupon['affiliate_id'] );
		} else {
			return '';
		}
	} else {
		// Get current logged in user's first name or default to Bob.
		$user_info  = get_userdata( get_current_user_id() );
		$first_name = ! empty( $user_info->first_name ) ? esc_html( $user_info->first_name ) : 'Bob';
	}

	// Limit first name length to 10 char.
	if ( strlen( $first_name ) > 10 ) {
		$first_name = substr( $first_name, 0, 10 );
	}

	return affwp_sanitize_coupon_code( $first_name );
}

/**
 * Replaces the given custom text merge tag.
 *
 * @since 2.8
 *
 * @param array $coupon Optional. Coupon creation arguments usually include: affiliate_id, coupon_code, and integration.
 * @return string Text from the coupon custom text setting or empty string defaults to 'text' for the preview.
 */
function affwp_coupon_tag_custom_text( $coupon = array() ) {
	$custom_text = affiliate_wp()->settings->get( 'coupon_custom_text' );

	// For preview, default to 'Text'.
	if ( empty( $coupon ) && ! is_array( $coupon ) && empty( $custom_text ) ) {
		$custom_text = 'text';
	}

	return affwp_sanitize_coupon_custom_text( $custom_text );
}
