<?php
/**
 * Referral Functions
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

/**
 * Retrieves a referral object.
 *
 * @param int|\AffWP\Referral $referral Referral ID or object.
 * @return \AffWP\Referral|false Referral object, otherwise false.
 */
function affwp_get_referral( $referral = null ) {

	if ( is_object( $referral ) && isset( $referral->referral_id ) ) {
		$referral_id = $referral->referral_id;
	} elseif ( is_numeric( $referral ) ) {
		$referral_id = absint( $referral );
	} else {
		return false;
	}

	$referral = affiliate_wp()->referrals->get_object( $referral_id );

	if ( ! empty( $referral->products ) ) {
		// products is a multidimensional array. Double unserialize is not a typo.
		$referral->products = maybe_unserialize( maybe_unserialize( $referral->products ) );
	}

	return $referral;
}

/**
 * Retrieves a referral's status.
 *
 * @since 1.6
 *
 * @param int|\AffWP\Referral $referral Referral ID or object.
 * @return string|false Referral status, otherwise false.
 */
function affwp_get_referral_status( $referral ) {

	if ( ! $referral = affwp_get_referral( $referral ) ) {
		return false;
	}

	return $referral->status;
}

/**
 * Retrieves the status label for a referral.
 *
 * @since 1.6
 * @since 2.3   The `$referral` parameter was renamed to `$referral_or_status` and now also accepts
 *              a referral status.
 * @since 2.8   Compatibility for a 'Draft' status label was added.
 * @since 2.8.1 Compatibility for a 'Failed' status label was added.
 *
 * @param int|\AffWP\Referral|string $referral_or_status Referral ID, object, or referral status.
 * @return string|false The localized version of the referral status, otherwise false. If the status
 *                      isn't registered and the referral is valid, the default 'pending' status will
 *                      be returned.
 */
function affwp_get_referral_status_label( $referral_or_status ) {

	if ( is_string( $referral_or_status ) ) {
		$referral = null;
		$status   = $referral_or_status;
	} else {
		$referral = affwp_get_referral( $referral_or_status );

		if ( isset( $referral->status ) ) {
			$status = $referral->status;
		} else {
			return false;
		}
	}

	$statuses = affwp_get_referral_statuses( true );
	$label    = array_key_exists( $status, $statuses ) ? $statuses[ $status ] : $statuses['pending'];

	/**
	 * Filters the referral status label.
	 *
	 * @since 1.6
	 * @since 2.3 Added the `$status` parameter.
	 *
	 * @param string               $label     A localized version of the referral status label.
	 * @param \AffWP\Referral|null $referral Referral object if an id or object was passed, otherwise null.
	 * @param string               $status    Referral status.
	 */
	return apply_filters( 'affwp_referral_status_label', $label, $referral, $status );

}

/**
 * Retrieves the list of referral statuses and corresponding labels.
 *
 * @since 2.3
 * @since 2.8.1 The optional `$include_internal` parameter was added.
 *
 * @param array $include_internal Optional. Whether to include internal-only statuses. Default false.
 * @return array Key/value pairs of statuses where key is the status and the value is the label.
 */
function affwp_get_referral_statuses( $include_internal = false ) {
	$statuses = array(
		'paid'     => __( 'Paid', 'affiliate-wp' ),
		'unpaid'   => __( 'Unpaid', 'affiliate-wp' ),
		'rejected' => __( 'Rejected', 'affiliate-wp' ),
		'pending'  => __( 'Pending', 'affiliate-wp' ),
	);

	if ( true === $include_internal ) {
		$statuses = array_merge( $statuses, array(
			'draft'  => __( 'Draft', 'affiliate-wp' ),
			'failed' => __( 'Failed', 'affiliate-wp' ),
		) );
	}

	return $statuses;
}

/**
 * Sets a referral's status.
 *
 * @since 1.0
 *
 * @param int|\AffWP\Referral $referral   Referral ID or object.
 * @param string              $new_status Optional. New referral status to set. Default empty.
 * @return bool True if the referral status was successfully changed from the old status to the
 *              new one, otherwise false.
 */
function affwp_set_referral_status( $referral, $new_status = '' ) {

	if ( ! $referral = affwp_get_referral( $referral ) ) {
		return false;
	}

	$new_status = strtolower( $new_status );
	$old_status = $referral->status;

	if( $old_status == $new_status ) {
		return false;
	}

	if( empty( $new_status ) ) {
		return false;
	}

	if( affiliate_wp()->referrals->update( $referral->ID, array( 'status' => $new_status ), '', 'referral' ) ) {

		// Old status cleanup.
		if ( 'paid' === $old_status ) {

			// Reverse the effect of a paid referral.
			affwp_decrease_affiliate_earnings( $referral->affiliate_id, $referral->amount );
			affwp_decrease_affiliate_referral_count( $referral->affiliate_id );

		} elseif ( 'unpaid' === $old_status ) {

			affwp_decrease_affiliate_unpaid_earnings( $referral->affiliate_id, $referral->amount );

		}

		// New status.
		if( 'paid' === $new_status ) {

			affwp_increase_affiliate_earnings( $referral->affiliate_id, $referral->amount );
			affwp_increase_affiliate_referral_count( $referral->affiliate_id );

		} elseif ( 'unpaid' === $new_status ) {

			affwp_increase_affiliate_unpaid_earnings( $referral->affiliate_id, $referral->amount );

			if ( in_array( $old_status, array( 'pending', 'rejected', 'failed' ) ) ) {
				// Update the visit ID that spawned this referral
				affiliate_wp()->visits->update_visit( $referral->visit_id, array( 'referral_id' => $referral->ID ) );

				/**
				 * Fires when a referral is marked as accepted.
				 *
				 * @since 1.0
				 *
				 * @param int             $affiliate_id Referral affiliate ID.
				 * @param \AffWP\Referral $referral     The referral object.
				 */
				do_action( 'affwp_referral_accepted', $referral->affiliate_id, $referral );
			}
		}

		/**
		 * Fires immediately after a referral's status has been successfully updated.
		 *
		 * Will not fire if the new status matches the old one, or if `$new_status` is empty.
		 *
		 * @since 1.0
		 *
		 * @param int    $referral_id Referral ID.
		 * @param string $new_status  New referral status.
		 * @param string $old_status  Old referral status.
		 */
		do_action( 'affwp_set_referral_status', $referral->ID, $new_status, $old_status );

		return true;
	}

	return false;

}

/**
 * Adds a new referral to the database.
 *
 * Referral status cannot be updated here, use affwp_set_referral_status().
 *
 * @since 1.0
 *
 * @param array $data {
 *     Optional. Arguments for adding a new referral. Default empty array.
 *
 *     @type int          $user_id      User ID. Used to retrieve the affiliate ID if `affiliate_id` not given.
 *     @type int          $affiliate_id Affiliate ID.
 *     @type string       $user_name    User login. Used to retrieve the affiliate ID if `affiliate_id` not given.
 *     @type float        $amount       Referral amount. Default empty.
 *     @type string       $description  Description. Default empty.
 *     @type string       $products     Referral products. Accepts an array or string, and will be
 *                                      serialized when stored. Default empty.
 *     @type string       $currency     Referral Currency. Default empty.
 *     @type string       $campaign     Referral Campaign, if set. Default Empty
 *     @type string       $reference    Referral reference (usually product information). Default empty.
 *     @type string       $context      Referral context (usually the integration it was generated from).
 *                                      Default empty.
 *     @type string|array $custom       Any custom data that can be passed to and stored with the referral. Accepts
 *                                      an array or string, and will be serialized when stored. Default empty.
 *     @type string       $status       Status to update the referral too. Default 'pending'.
 * }
 * @return int|bool 0|false if no referral was added, referral ID if it was successfully added.
 */
function affwp_add_referral( $data = array() ) {

	if ( empty( $data['user_id'] ) && empty( $data['affiliate_id'] ) && empty( $data['user_name'] ) ) {
		return 0;
	}

	$data = affiliate_wp()->utils->process_request_data( $data, 'user_name' );

	if ( empty( $data['affiliate_id'] ) ) {

		$user_id      = absint( $data['user_id'] );
		$affiliate_id = affiliate_wp()->affiliates->get_column_by( 'affiliate_id', 'user_id', $user_id );

		if ( ! empty( $affiliate_id ) ) {

			$data['affiliate_id'] = $affiliate_id;

		} else {

			return 0;

		}

	}

	if ( ! empty( $data['custom'] ) ) {
		if ( is_array( $data['custom'] ) ) {
			$data['custom'] = array_map( 'sanitize_text_field', $data['custom'] );
		} else {
			$data['custom'] = sanitize_text_field( $data['custom'] );
		}
	}

	$args = array(
		'affiliate_id' => absint( $data['affiliate_id'] ),
		'amount'       => ! empty( $data['amount'] )      ? sanitize_text_field( $data['amount'] )        : '',
		'description'  => ! empty( $data['description'] ) ? sanitize_text_field( $data['description'] )   : '',
		'order_total'  => ! empty( $data['order_total'] ) ? affwp_sanitize_amount( $data['order_total'] ) : '',
		'reference'    => ! empty( $data['reference'] )   ? sanitize_text_field( $data['reference'] )     : '',
		'parent_id'    => ! empty( $data['parent_id'] )   ? absint( $data['parent_id'] )                  : '',
		'currency'     => ! empty( $data['currency'] )    ? sanitize_text_field( $data['currency'] )      : '',
		'campaign'     => ! empty( $data['campaign'] )    ? sanitize_text_field( $data['campaign'] )      : '',
		'context'      => ! empty( $data['context'] )     ? sanitize_text_field( $data['context'] )       : '',
		'custom'       => ! empty( $data['custom'] )      ? $data['custom']                               : '',
		'date'         => ! empty( $data['date'] )        ? $data['date']                                 : '',
		'type'         => ! empty( $data['type'] )        ? $data['type']                                 : '',
		'products'     => ! empty( $data['products'] )    ? $data['products']                             : '',
		'status'       => 'pending',
	);

	if ( ! empty( $data['visit_id'] ) && is_wp_error( affwp_get_referral_by( 'visit_id', $data['visit_id'] ) ) ) {
		$args['visit_id'] = absint( $data['visit_id'] );
	}

	$referral_id = affiliate_wp()->referrals->add( $args );

	if ( $referral_id ) {

		$status = ! empty( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'pending';

		affwp_set_referral_status( $referral_id, $status );

		return $referral_id;
	}

	return 0;

}

/**
 * Deletes a referral.
 *
 * If the referral has a status of 'paid', the affiliate's earnings and referral count will decrease.
 *
 * @since
 *
 * @param int|\AffWP\Referral $referral Referral ID or object.
 * @return bool True if the referral was successfully deleted, otherwise false.
 */
function affwp_delete_referral( $referral ) {

	if ( ! $referral = affwp_get_referral( $referral ) ) {
		return false;
	}

	if ( $referral ) {
		if ( 'paid' === $referral->status ) {
			// This referral has already been paid, so decrease the affiliate's earnings
			affwp_decrease_affiliate_earnings( $referral->affiliate_id, $referral->amount );

			// Decrease the referral count
			affwp_decrease_affiliate_referral_count( $referral->affiliate_id );

		} elseif ( 'unpaid' === $referral->status ) {

			// Decrease the unpaid earnings.
			affwp_decrease_affiliate_unpaid_earnings( $referral->affiliate_id, $referral->amount );

		}
	}

	if( affiliate_wp()->referrals->delete( $referral->ID, 'referral' ) ) {

		/**
		 * Fires immediately after a referral has been deleted.
		 *
		 * @since 1.0
		 *
		 * @param int $referral_id Referral ID.
		 */
		do_action( 'affwp_delete_referral', $referral->ID );

		return true;

	}

	return false;
}

/**
 * Calculates the referral amount.
 *
 * @since 1.0
 *
 * @param  string $amount       Optional. Base amount to calculate the referral amount from. Usually the Order Total
 * @param  int    $affiliate_id Optional. The Affiliate to calculate from. Default empty.
 * @param  int    $reference    Optional. Referral reference (usually the order ID). Default empty.
 * @param  string $rate         Optional. The Product or category rate.
 * @param  int    $product_id   Optional. The product ID to to reference
 * @param  string $context      Optional. The context for referrals. This refers to the integration that is being used.
 * @return string Calculated referral amount.
 */
function affwp_calc_referral_amount( $amount = '', $affiliate_id = 0, $reference = 0, $rate = '', $product_id = 0, $context = '' ) {
	$rate = affwp_get_affiliate_rate( $affiliate_id, false, $rate, $reference );

	if ( affwp_is_per_order_rate( $affiliate_id ) ) {

		/**
		 * Filters the referral amount for per-order referrals.
		 *
		 * @since 2.3
		 *
		 * @param string     $base_amount  Base amount to calculate the referral amount from.
		 * @param string|int $reference    Referral reference (usually the order ID).
		 * @param int        $product_id   Product ID.
		 * @param int        $affiliate_id Affiliate ID.
		 * @param string     $context      The context for referrals. This refers to the integration that is being used.
		 */
		$referral_amount = apply_filters( 'affwp_calc_per_order_referral_amount', $rate, $affiliate_id, $reference, $product_id, $context );
	} else {
		$type     = affwp_get_affiliate_rate_type( $affiliate_id );
		$decimals = affwp_get_decimal_count();

		$referral_amount = ( 'percentage' === $type ) ? round( $amount * $rate, $decimals ) : $rate;

		/**
		 * Filters the referral calculation.
		 *
		 * @since 2.3
		 *
		 * @param string     $referral_amount Base amount to calculate the referral amount from.
		 * @param int        $affiliate_id    Affiliate ID.
		 * @param string     $amount          Base amount to calculate the referral amount from. Usually the Order Total
		 * @param string|int $reference       Referral reference (usually the order ID).
		 * @param int        $product_id      Product ID. Default 0.
		 * @param string     $context         The context for referrals. This refers to the integration that is being used.
		 */
		$referral_amount = apply_filters( 'affwp_calc_referral_amount', $referral_amount, $affiliate_id, $amount, $reference, $product_id, $context );
	}

	if ( $referral_amount < 0 ) {
		$referral_amount = 0;
	}

	return (string) $referral_amount;

}

/**
 * Retrieves the number of referrals for the given affiliate.
 *
 * @since 1.0
 *
 * @param int|\AffWP\Affiliate $affiliate Optional. Affiliate ID or object. Default is the current affiliate.
 * @param string|array         $status    Optional. Referral status or array of statuses. Default empty array.
 * @param array|string         $date      Optional. Array of date data with 'start' and 'end' key/value pairs,
 *                                        or a timestamp. Default empty array.
 * @return int Zero if the affiliate is invalid, or the number of referrals for the given arguments.
 */
function affwp_count_referrals( $affiliate_id = 0, $status = array(), $date = array() ) {

	if ( ! $affiliate = affwp_get_affiliate( $affiliate_id ) ) {
		return 0;
	}

	$args = array(
		'affiliate_id' => $affiliate->ID,
		'status'       => $status
	);

	if( ! empty( $date ) ) {
		$args['date'] = $date;
	}

	return affiliate_wp()->referrals->count( $args );
}

/**
 * Retrieves an array of banned URLs.
 *
 * @since 2.0
 *
 * @return array The array of banned URLs.
 */
function affwp_get_banned_urls() {
	$urls = affiliate_wp()->settings->get( 'referral_url_blacklist', array() );

	if ( ! empty( $urls ) ) {
		$urls = array_map( 'trim', explode( "\n", $urls ) );
		$urls = array_unique( $urls );
		$urls = array_map( 'sanitize_text_field', $urls );
	}

	/**
	 * Filters the list of banned URLs.
	 *
	 * @since 2.0
	 *
	 * @param array $url Banned URLs.
	 */
	return apply_filters( 'affwp_get_banned_urls', $urls );
}

/**
 * Determines if a URL is banned.
 *
 * @since 2.0
 *
 * @param string $url The URL to check against the black list.
 * @return bool True if banned, otherwise false.
 */
function affwp_is_url_banned( $url ) {
	$banned_urls = affwp_get_banned_urls();

	if( ! is_array( $banned_urls ) || empty( $banned_urls ) ) {
		$banned = false;
	}

	foreach( $banned_urls as $banned_url ) {

		$banned = ( stristr( trim( $url ), $banned_url ) ? true : false );

		if ( true === $banned ) {
			break;
		}
	}

	/**
	 * Filters whether the given URL is considered 'banned'.
	 *
	 * @since 2.0
	 *
	 * @param bool   $banned Whether the given URL is banned.
	 * @param string $url    The URL check for ban status.
	 */
	return apply_filters( 'affwp_is_url_banned', $banned, $url );
}

/**
 * Sanitize the given referral rate.
 *
 * @since 2.2.11
 *
 * @param string $rate The referral rate to sanitize.
 * @return string The sanitized referral rate.
 */
function affwp_sanitize_referral_rate( $rate ) {
	return preg_replace( '/[^0-9\.]/', '', $rate );
}

/**
 * Retrieves a list of referral types and labels.
 *
 * New referral types can be registered via the {@see 'affwp_referral_type_init'} filter.
 *
 * @since 2.6.4
 *
 * @param bool $types_only Optional. Whether to retrieve the types only. If true, only the referral type
 *                         identifiers will be returned. Default false.
 * @return array List of referral types and associated attributes (unless `$types_only` is true).
 */
function affwp_get_referral_types( $types_only = false ) {
	$types = affiliate_wp()->referrals->types_registry->get_types();

	if ( true === $types_only ) {
		$types = array_keys( $types );
	}

	return $types;
}

/**
 * Retrieves the referral type label.
 *
 * @since 2.6.4
 *
 * @param int|\AffWP\Referral|string $referral_or_type Referral ID, object, or referral type.
 * @return string|false The localized version of the referral type, otherwise false. If the type
 *                      isn't registered and the referral is valid, the default 'sale' type's
 *                      label will be returned.
 */
function affwp_get_referral_type_label( $referral_or_type ) {

	if ( is_string( $referral_or_type ) ) {
		$referral = null;
		$type     = $referral_or_type;
	} else {
		$referral = affwp_get_referral( $referral_or_type );

		if ( isset( $referral->type ) ) {
			$type = $referral->type;
		} else {
			return false;
		}
	}

	$types = affwp_get_referral_types();
	$label = array_key_exists( $type, $types ) ? $types[ $type ]['label'] : $types['sale']['label'];

	/**
	 * Filters the referral type label.
	 *
	 * @since 2.6.4
	 *
	 * @param string               $label    A localized version of the referral type label.
	 * @param \AffWP\Referral|null $referral Referral object if an id or object was passed, otherwise null.
	 * @param string               $type     Referral type.
	 */
	return apply_filters( 'affwp_referral_type_label', $label, $referral, $type );
}

/**
 * Retrieves a referral by a given field and value.
 *
 * @since 2.7
 *
 * @param string $field   Referral object field.
 * @param mixed  $value   Field value.
 * @param string $context Optional. Context to retrieve a referral by. Default empty.
 * @return \AffWP\Referral|\WP_Error Referral object if found, otherwise a WP_Error object.
 */
function affwp_get_referral_by( $field, $value, $context = '' ) {
	if ( ! empty( $context ) ) {
		$result = affiliate_wp()->referrals->get_by_with_context( $field, $value, $context );
	} else {
		$result = affiliate_wp()->referrals->get_by( $field, $value );
	}

	if ( is_object( $result ) ) {
		$referral = affwp_get_referral( intval( $result->referral_id ) );
	} else {
		$referral = new \WP_Error(
			'invalid_referral_field',
			sprintf( 'No referral could be retrieved with a(n) \'%1$s\' field value of %2$s.', $field, $value )
		);
	}

	return $referral;
}
