<?php
/**
 * Referrals Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

/**
 * Class Affiliate_WP_Referrals_DB
 *
 * @see Affiliate_WP_DB
 *
 * @property-read \AffWP\Referral\REST\v1\Endpoints $REST Referral REST endpoints.
 */
class Affiliate_WP_Referrals_DB extends Affiliate_WP_DB  {

	/**
	 * Cache group for queries.
	 *
	 * @internal DO NOT change. This is used externally both as a cache group and shortcut
	 *           for accessing db class instances via affiliate_wp()->{$cache_group}->*.
	 *
	 * @since 1.9
	 * @access public
	 * @var string
	 */
	public $cache_group = 'referrals';

	/**
	 * Database group value.
	 *
	 * @since 2.5
	 * @var string
	 */
	public $db_group = 'referrals';

	/**
	 * Object type to query for.
	 *
	 * @since 1.9
	 * @access public
	 * @var string
	 */
	public $query_object_type = 'AffWP\Referral';

	/**
	 * Referral types registry.
	 *
	 * @since 2.2
	 * @access public
	 * @var object
	 */
	public $types_registry;

	/**
	 * The sales instance variable.
	 *
	 * @since 2.5
	 * @var   \Affiliate_WP_Sales_DB
	 */
	public $sales;

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.0
	*/
	public function __construct() {
		global $wpdb, $wp_version;

		if( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE ) {
			// Allows a single referrals table for the whole network
			$this->table_name  = 'affiliate_wp_referrals';
		} else {
			$this->table_name  = $wpdb->prefix . 'affiliate_wp_referrals';
		}
		$this->primary_key = 'referral_id';
		$this->version     = '1.3';

		$this->sales = new \Affiliate_WP_Sales_DB;

		// REST endpoints.
		if ( version_compare( $wp_version, '4.4', '>=' ) ) {
			$this->REST = new \AffWP\Referral\REST\v1\Endpoints;
		}

		$this->types_registry = new \AffWP\Utils\Referral_Types\Registry;
		$this->types_registry->init();
	}

	/**
	 * Retrieves a referral object.
	 *
	 * @since 1.9
	 * @access public
	 *
	 * @see Affiliate_WP_DB::get_core_object()
	 *
	 * @param int|object|AffWP\Referral $referral Referral ID or object.
	 * @return AffWP\Referral|null Referral object, null otherwise.
	 */
	public function get_object( $referral ) {
		return $this->get_core_object( $referral, $this->query_object_type );
	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   1.0
	*/
	public function get_columns() {
		return array(
			'referral_id'    => '%d',
			'affiliate_id'   => '%d',
			'visit_id'       => '%d',
			'rest_id'        => '%s',
			'customer_id'    => '%d',
			'parent_id'      => '%d',
			'description'    => '%s',
			'status'         => '%s',
			'amount'         => '%s',
			'currency'       => '%s',
			'custom'         => '%s',
			'context'        => '%s',
			'campaign'       => '%s',
			'reference'      => '%s',
			'products'       => '%s',
			'payout_id'      => '%d',
			'type'           => '%s',
			'date'           => '%s',
		);
	}

	/**
	 * Get valid sum columns and formats
	 *
	 * @access  public
	 * @since   2.3
	 */
	public function get_sum_columns() {
		return array(
			'amount_sum' => '%d',
		);
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function get_column_defaults() {
		return array(
			'affiliate_id' => 0,
			'customer_id'  => 0,
			'parent_id'    => 0,
			'date'         => gmdate( 'Y-m-d H:i:s' ),
			'currency'     => affwp_get_currency(),
			'type'         => 'sale',
		);
	}

	/**
	 * Adds a referral.
	 *
	 * @since 1.0
	 * @since 2.5 Added an `order_total` argument.
	 *
	 * @param array $data {
	 *     Optional. Referral data. Default empty array.
	 *
	 *     @type string $status Referral status. Default 'pending'.
	 *     @type int    $amount Referral amount. Default 0.
	 * }
	 * @return int|false Referral ID if successfully added, false otherwise.
	*/
	public function add( $data = array() ) {

		$defaults = array(
			'status'        => 'pending',
			'amount'        => 0,
			'type'          => 'sale',
			'order_total'   => 0,
		);

		$args = wp_parse_args( $data, $defaults );

		$result = false;
		$errors = new \WP_Error();

		if ( ! isset( $args['affiliate_id'] ) ) {
			$errors->add( 'missing_affiliate_id', 'The affiliate ID is missing.' );

			$args['affiliate_id'] = 0;
		}

		if ( false === affwp_get_affiliate( $args['affiliate_id'] ) ) {

			$errors->add(
				'invalid_affiliate_id',
				sprintf( 'The #%d affiliate ID is invalid.',
					$args['affiliate_id']
				)
			);

		} else {

			$args['amount'] = affwp_sanitize_amount( $args['amount'] );

			if( ! empty( $args['products'] ) ) {
				$args['products'] = maybe_serialize( $args['products'] );
			}

			if( empty( $args['description'] ) ) {
				$args['description'] = ''; // Force description to empty string. NULL values won't work. See https://github.com/AffiliateWP/AffiliateWP/issues/2672
			}

			if ( ! empty( $args['custom'] ) ) {
				$args['custom']	 = maybe_serialize( $args['custom'] );
			}

			$rest_id_error = false;

			if ( ! empty( $args['rest_id'] ) ) {
				if ( ! affwp_validate_rest_id( $args['rest_id'] ) ) {
					$errors->add( 'invalid_rest_id', sprintf( 'REST ID \'%1$s\' is formatted incorrectly. Must contain a colon.',
						$args['rest_id']
					) );

					unset( $args['rest_id'] );
				} else {
					$args['rest_id'] = sanitize_text_field( $args['rest_id'] );
				}
			}

			if ( empty( $args['date'] ) ) {
				unset( $args['date'] );
			} else {
				$time = strtotime( $args['date'] );

				$args['date'] = gmdate( 'Y-m-d H:i:s', $time - affiliate_wp()->utils->wp_offset );
			}

			if( ! empty( $args['type'] ) && ! $this->types_registry->get_type( $args['type'] ) ) {
				$args['type'] = 'sale';
			}

			if ( ! empty( $args['context'] ) ) {
				// Force context to lowercase for system-wide compatibility.
				$args['context'] = strtolower( $args['context'] );
			}

			$args['customer_id'] = $this->setup_customer( $args );

			$add = $this->insert( $args, 'referral' );

			if ( $add ) {

				$referral    = affwp_get_referral( $add );
				$integration = affiliate_wp()->integrations->get( $referral->context );

				if ( ! is_wp_error( $integration ) ) {
					if ( $integration->is_active() ) {
						// If the order_total is empty, try to get it from the integration directly.
						if ( empty( $args['order_total'] ) ) {
							$args['order_total'] = $integration->get_order_total( $referral->reference );
						}

						affiliate_wp()->referrals->sales->add( array(
							'order_total'   => $args['order_total'],
							'amount'        => $referral->amount,
							'affiliate_id'  => $referral->affiliate_id,
							'referral_id'   => $referral->referral_id,
						) );
					}
				}

				/**
				 * Fires once a new referral has successfully been inserted into the database.
				 *
				 * @since 1.6
				 *
				 * @param int $add Referral ID.
				 */
				do_action( 'affwp_insert_referral', $add );

				$result = $add;
			}

		}

		$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );

		if ( true === $has_errors ) {
			if ( false !== $result ) {
				$message = sprintf( 'There was a problem while adding referral #%1$d.', $result );
			} else {
				$message = 'There was a problem while adding the referral.';
			}

			affiliate_wp()->utils->log( $message, $errors );
		}

		return $result;
	}

	/**
	 * Update a referral.
	 *
	 * @access  public
	 * @since   1.5
	 *
	 * @param int|AffWP\Referral $referral Referral ID or object.
	 * @return bool True if the referral was successfully updated, otherwise false.
	*/
	public function update_referral( $referral = 0, $data = array() ) {

		$args = array();

		if ( ! $referral = affwp_get_referral( $referral ) ) {
			return false;
		}

		if( ! empty( $data['products'] ) ) {
			$args['products'] = maybe_serialize( $data['products'] );
		}

		if ( ! empty( $data['date' ] ) && $data['date'] !== $referral->date ) {
			$timestamp    = strtotime( $data['date'] ) - affiliate_wp()->utils->wp_offset;
			$args['date'] = gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		if ( ! empty( $data['rest_id'] ) && is_string( $data['rest_id'] ) && $data['rest_id'] !== $referral->rest_id ) {
			if ( false !== strpos( $data['rest_id'], ':' ) ) {
				$args['rest_id'] = sanitize_text_field( $data['rest_id'] );
			}
		}

		$args['affiliate_id']  = ! empty( $data['affiliate_id' ] ) ? intval( $data['affiliate_id'] )             : $referral->affiliate_id;
		$args['visit_id']      = ! empty( $data['visit_id' ] )     ? intval( $data['visit_id'] )                 : $referral->visit_id;
		$args['customer_id']   = ! empty( $data['customer_id' ] )  ? intval( $data['customer_id'] )              : $referral->customer_id;
		$args['description']   = ! empty( $data['description' ] )  ? sanitize_text_field( $data['description'] ) : $referral->description;
		$args['amount']        = ! empty( $data['amount'] )        ? affwp_sanitize_amount( $data['amount'] )    : $referral->amount;
		$args['currency']      = ! empty( $data['currency'] )      ? sanitize_text_field( $data['currency'] )    : $referral->currency;
		$args['custom']        = ! empty( $data['custom'] )        ? sanitize_text_field( $data['custom'] )      : $referral->custom;
		$args['context']       = ! empty( $data['context'] )       ? sanitize_text_field( $data['context'] )     : $referral->context;
		$args['campaign']      = ! empty( $data['campaign'] )      ? sanitize_text_field( $data['campaign'] )    : $referral->campaign;
		$args['reference']     = ! empty( $data['reference'] )     ? sanitize_text_field( $data['reference'] )   : $referral->reference;
		$args['parent_id']     = ! empty( $data['parent_id'] )     ? intval( $data['parent_id'] )                : $referral->parent_id;

		// Validate any referral type changes.
		if ( ! empty( $data['type'] ) ) {
			$args['type'] = sanitize_key( $data['type'] );

			if ( ! $this->types_registry->get_type( $args['type'] ) ) {
				$args['type'] = 'sale';
			}
		} else {
			$args['type'] = $referral->type;
		}

		// Force context to lowercase for system-wide compatibility.
		$args['context'] = strtolower( $args['context'] );

		/*
		 * Deliberately defer updating the status – it will be updated instead
		 * in affwp_set_referral_status() if changed.
		 *
		 * Prior to 2.1, the status was updated in the first update() call, which
		 * resulted in affwp_set_referral_status() failing to trigger the earnings
		 * adjustments. Now the status is only updated once as needed. See #2257.
		 */
		$new_status = ! empty( $data['status'] ) ? sanitize_key( $data['status'] ) : $referral->status;
		$new_type   = $args['type'];

		$updated          = $this->update( $referral->ID, $args, '', 'referral' );
		$updated_referral = affwp_get_referral( $referral );

		/**
		 * Fires immediately after a referral update has been attempted.
		 *
		 * @since 2.1.9
		 *
		 * @param \AffWP\Referral $updated_referral Updated referral object.
		 * @param \AffWP\Referral $referral         Original referral object.
		 * @param bool            $updated          Whether the referral was successfully updated.
		 */
		do_action( 'affwp_updated_referral', $updated_referral, $referral, $updated );

		if( $updated ) {

			if( ! empty( $new_status ) && $referral->status !== $new_status ) {

				affwp_set_referral_status( $referral->ID, $new_status );

			} elseif( 'paid' === $new_status && 'paid' === $referral->status ) {

				// If the 'paid' status is unchanged, but the amount is, make earnings adjustments.
				if( $referral->amount > $args['amount'] ) {

					$change = $referral->amount - $args['amount'];
					affwp_decrease_affiliate_earnings( $referral->affiliate_id, $change );

				} elseif( $referral->amount < $args['amount'] ) {

					$change = $args['amount'] - $referral->amount;
					affwp_increase_affiliate_earnings( $referral->affiliate_id, $change );

				}

			} elseif( 'unpaid' === $new_status && 'unpaid' === $referral->status ) {

				// If the 'unpaid' status is unchanged, but the amount is, make earnings adjustments.
				if ( $referral->amount > $args['amount'] ) {

					affwp_decrease_affiliate_unpaid_earnings( $referral->affiliate_id, $referral->amount - $args['amount'] );

				} elseif ( $referral->amount < $args['amount'] ) {

					affwp_increase_affiliate_unpaid_earnings( $referral->affiliate_id, $args['amount'] - $referral->amount );

				}
			}

			// If the referral is now a sale, add the sales record.
			if ( 'sale' === $new_type && 'sale' !== $referral->type ) {

				affiliate_wp()->referrals->sales->add( array(
					'referral_id' => $updated_referral->ID
				) );

			// If the referral is still a sale, check for any updated sales data.
			} elseif ( 'sale' === $new_type && 'sale' === $referral->type ) {

				// If any columns that impact sales data were updated, update the corresponding sales record.
				$sales_data = array_intersect_key( $data, $this->sales->get_columns() );

				if ( ! empty( $sales_data ) ) {
					affiliate_wp()->referrals->sales->update_sale( $updated_referral, $sales_data );
				}

			// If the referral used to be a sale and now isn't, delete the corresponding sales record.
			} elseif ( 'sale' !== $new_type && 'sale' === $referral->type ) {

				affiliate_wp()->referrals->sales->delete( $updated_referral->ID );

			}

			return true;
		}

		return false;

	}

	/**
	 * Deletes a referral from the database.
	 *
	 * Please note: successfully deleting a record flushes the cache.
	 *
	 * @since 2.5
	 *
	 * @param int|string $row_id Referral ID.
	 * @param string     $type   Object type. Unused at this level.
	 * @return bool True if the record was deleted, otherwise false.
	 */
	public function delete( $row_id = 0, $type = '' ) {
		$referral_id = $row_id;

		$deleted = parent::delete( $referral_id, 'referral' );

		// If the referral was removed, trash collect any associated sales data as well.
		if ( $deleted ) {
			affiliate_wp()->referrals->sales->delete( $referral_id );
		}

		return $deleted;
	}

	/**
	 * Retrieves a referral by a specific column and value.
	 *
	 * @since 1.0
	 * @since 2.6.1 The optional `$context` parameter was deprecated and removed for PHP 8 compat.
	 *              Use get_by_with_context() instead. The `$row_id` parameter was renamed to `$value`.
	 *
	 * @param string $column Column name. See get_columns().
	 * @param mixed  $value  Column value
	 * @return object|false Resulting referral if found, otherwise false.
	*/
	public function get_by( $column, $value ) {
		global $wpdb;

		$args = func_get_args();

		if ( isset( $args[2] ) ) {
			return $this->get_by_with_context( $column, $value, $args[2] );
		}

		if ( empty( $column ) || empty( $value ) || ! array_key_exists( $column, $this->get_columns() ) ) {
			return false;
		}

		$query = $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $column = '%s' LIMIT 1;", $value );

		return $wpdb->get_row( $query );
	}

	/**
	 * Retrieves a referral by a specific column, value, and context.
	 *
	 * @since 2.6.1
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Column value.
	 * @param string $context Context under which to search.
	 * @return object|false Resulting referral if found, otherwise false.
	 */
	public function get_by_with_context( $column, $value, $context ) {
		global $wpdb;

		if ( empty( $column ) || empty( $value ) || empty( $context ) || ! array_key_exists( $column, $this->get_columns() ) ) {
			return false;
		}

		$query = $wpdb->prepare( "SELECT * FROM $this->table_name WHERE $column = '%s' AND context = '%s' LIMIT 1;", $value, $context );

		return $wpdb->get_row( $query );
	}

	/**
	 * Retrieves referrals from the database.
	 *
	 * @since 1.0
	 * @since 2.3   Added the `$sum_fields` argument.
	 * @since 2.6.2 Added the `$date_format` argument.
	 *
	 * @param array $args {
	 *     Optional. Arguments to retrieve referrals from the database.
	 *
	 *     @type int          $number         Number of referrals to retrieve. Accepts -1 for all. Default 20.
	 *     @type int          $offset         Number of referrals to offset in the query. Default 0.
	 *     @type int|array    $referral_id    Specific referral ID or array of IDs to query for. Default 0 (all).
	 *     @type int|array    $affiliate_id   Affiliate ID or array of IDs to query referrals for. Default 0 (all).
	 *     @type int|array    $customer_id    Customer ID or array of IDs to query referrals for. Default 0 (all).
	 *     @type int|array    $parent_id      Parent ID or array of IDs to query referrals for. Default 0 (all).
	 *     @type int|array    $payout_id      Payout ID or array of IDs to query referrals for. Default 0 (all).
	 *     @type float|array  $amount {
	 *         Specific amount to query for or min/max range. If float, can be used with `$amount_compare`.
	 *         If array, `BETWEEN` is used.
	 *
	 *         @type float $min Minimum amount to query for.
	 *         @type float $max Maximum amount to query for.
	 *     }
	 *     @type string       $amount_compare Comparison operator to use with `$amount`. Accepts '>', '<', '>=',
	 *                                        '<=', '=', or '!='. Default '='.
	 *     @type string|array $date {
	 *         Date string or start/end range to retrieve referrals for.
	 *
	 *         @type string $start Start date to retrieve referrals for.
	 *         @type string $end   End date to retrieve referrals for.
	 *     }
	 *     @type string       $reference      Specific reference to query referrals for (usually an order number).
	 *                                        Default empty.
	 *     @type string       $context        Specific context to query referrals for. Default empty.
	 *     @type string       $campaign       Specific campaign to query referrals for. Default empty.
	 *     @type string       $type           Specific referral type to query referrals for. Default empty.
	 *     @type string       $description    Description to search referrals for. Fuzzy matching is permitted when
	 *                                        `$search` is true.
	 *     @type string|array $status         Referral status or array of statuses to query referrals for.
	 *                                        Default empty (all).
	 *     @type string       $orderby        Column to order results by. Accepts any valid referrals table column.
	 *                                        Default 'referral_id'.
	 *     @type string       $order          How to order results. Accepts 'ASC' (ascending) or 'DESC' (descending).
	 *                                        Default 'DESC'.
	 *     @type bool         $search         Whether a search query is being performed. Default false.
	 *     @type string|array $fields         Specific fields to retrieve. Accepts 'ids', a single referral field, or an
	 *                                        array of fields. Default '*' (all).
	 *     @type array        $sum_fields     A database column, or an array of database columns to add to the query
	 *                                        as a sum. Default empty string.
	 *     @type string       $date_format    Specific format for date. Adds a formatted_date to response. Uses MySQL
	 *                                        date_format syntax. Default empty.
	 * }
	 * @param   bool  $count  Optional. Whether to return only the total number of results found. Default false.
	 * @return \AffWP\Referral[]|object[]|array|int An array of referral objects, an array of values from a single
	 *                                              field passed to `$fields`, or an array of objects with multiple
	 *                                              fields defined in `$fields`. Note: if `$sum_fields` and/or
	 *                                              `$date_format` are used, relevant sum-based and/or 'formatted_date'
	 *                                              fields, respectively, will be added to the objects in the result
	 *                                              set alongside any fields defined in `$fields`. If `$count` is true,
	 *                                              an integer will be returned.
	*/
	public function get_referrals( $args = array(), $count = false ) {

		global $wpdb;

		$defaults = array(
			'number'         => 20,
			'offset'         => 0,
			'referral_id'    => 0,
			'payout_id'      => 0,
			'affiliate_id'   => 0,
			'customer_id'    => 0,
			'parent_id'      => 0,
			'amount'         => 0,
			'amount_compare' => '=',
			'description'    => '',
			'reference'      => '',
			'context'        => '',
			'campaign'       => '',
			'type'           => '',
			'status'         => '',
			'orderby'        => 'referral_id',
			'groupby'        => '',
			'order'          => 'DESC',
			'search'         => false,
			'fields'         => '',
			'date_format'    => '',
			'sum_fields'     => '',
		);

		$args  = wp_parse_args( $args, $defaults );

		if( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		if ( ! is_array( $args['sum_fields'] ) ) {
			$args['sum_fields'] = (array) $args['sum_fields'];
		}

		$where = $join = '';

		// Specific referrals
		if( ! empty( $args['referral_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['referral_id'] ) ) {
				$referral_ids = implode( ',', array_map( 'intval', $args['referral_id'] ) );
			} else {
				$referral_ids = intval( $args['referral_id'] );
			}

			$where .= "`referral_id` IN( {$referral_ids} ) ";

		}

		// Referrals for specific affiliates
		if( ! empty( $args['affiliate_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['affiliate_id'] ) ) {
				$affiliate_ids = implode( ',', array_map( 'intval', $args['affiliate_id'] ) );
			} else {
				$affiliate_ids = intval( $args['affiliate_id'] );
			}

			$where .= "`affiliate_id` IN( {$affiliate_ids} ) ";

		}

		// Referrals for specific customers
		if( ! empty( $args['customer_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['customer_id'] ) ) {
				$customer_ids = implode( ',', array_map( 'intval', $args['customer_id'] ) );
			} else {
				$customer_ids = intval( $args['customer_id'] );
			}

			$where .= "`customer_id` IN( {$customer_ids} ) ";

		}

		// Referrals for specific payouts
		if( ! empty( $args['payout_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['payout_id'] ) ) {
				$payout_ids = implode( ',', array_map( 'intval', $args['payout_id'] ) );
			} else {
				$payout_ids = intval( $args['payout_id'] );
			}

			$where .= "`payout_id` IN( {$payout_ids} ) ";

		}

		// Referrals for specific parent_ids
		if( ! empty( $args['parent_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $args['parent_id'] ) ) {
				$parent_ids = implode( ',', array_map( 'intval', $args['parent_id'] ) );
			} else {
				$parent_ids = intval( $args['parent_id'] );
			}

			$where .= "`parent_id` IN( {$parent_ids} ) ";

		}

		// Amount.
		if ( ! empty( $args['amount'] ) ) {

			$amount = $args['amount'];

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $amount ) && ! empty( $amount['min'] ) && ! empty( $amount['max'] ) ) {

				$minimum = absint( $amount['min'] );
				$maximum = absint( $amount['max'] );

				$where .= "`amount` BETWEEN {$minimum} AND {$maximum} ";
			} else {

				$amount  = absint( $amount );
				$compare = '=';

				if ( ! empty( $args['amount_compare'] ) ) {
					$compare = $args['amount_compare'];

					if ( ! in_array( $compare, array( '>', '<', '>=', '<=', '=', '!=' ) ) ) {
						$compare = '=';
					}
				}

				$where .= "`amount` {$compare} {$amount} ";
			}
		}

		// Status.
		$where .= empty( $where ) ? "WHERE " : "AND ";
		if( empty( $args['status'] ) ) {

			$where .= "`status` != 'draft' AND `status` != 'failed' ";

		} else {

			if( is_array( $args['status'] ) ) {
				$where .= "`status` IN('" . implode( "','", array_map( 'esc_sql', $args['status'] ) ) . "') ";
			} else {
				$where .= "`status` = '" . esc_sql( $args['status'] ) . "' ";
			}

		}

		// Referrals for a date or date range
		if( ! empty( $args['date'] ) ) {
			$where = $this->prepare_date_query( $where, $args['date'] );
		}

		if( ! empty( $args['reference'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['reference'] ) ) {
				$where .= "`reference` IN(" . implode( ',', array_map( 'esc_sql', $args['reference'] ) ) . ") ";
			} else {
				$reference = esc_sql( $args['reference'] );

				if( ! empty( $args['search'] ) ) {
					$where .= "`reference` LIKE '%%" . $reference . "%%' ";
				} else {
					$where .= "`reference` = '" . $reference . "' ";
				}
			}

		}

		if( ! empty( $args['context'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $args['context'] ) ) {
				$args['context'] = array_map( 'strtolower', $args['context'] );

				$where .= "`context` IN('" . implode( "','", array_map( 'esc_sql', $args['context'] ) ) . "') ";
			} else {
				$context = esc_sql( strtolower( $args['context'] ) );

				if ( ! empty( $args['search'] ) ) {
					$where .= "`context` LIKE '%%" . $context . "%%' ";
				} else {
					$where .= "`context` = '" . $context . "' ";
				}
			}

		}

		if( ! empty( $args['campaign'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['campaign'] ) ) {
				$where .= "`campaign` IN(" . implode( ',', array_map( 'esc_sql', $args['campaign'] ) ) . ") ";
			} else {
				$campaign = esc_sql( $args['campaign'] );

				if ( ! empty( $args['search'] ) ) {
					$where .= "`campaign` LIKE '%%" . $campaign . "%%' ";
				} else {
					$where .= "`campaign` = '" . $campaign . "' ";
				}
			}

		}

		if( ! empty( $args['type'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['type'] ) ) {
				$where .= "`type` IN(" . implode( ',', array_map( 'esc_sql', $args['type'] ) ) . ") ";
			} else {
				$type = esc_sql( $args['type'] );

				if ( ! empty( $args['search'] ) ) {
					$where .= "`type` LIKE '%%" . $type . "%%' ";
				} else {
					$where .= "`type` = '" . $type . "' ";
				}
			}

		}


		// Description.
		if( ! empty( $args['description'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			$description = esc_sql( $args['description'] );

			if( ! empty( $args['search'] ) ) {
				$where .= "LOWER(`description`) LIKE LOWER('%%" . $description . "%%') ";
			} else {
				$where .= "`description` = '" . $description . "' ";
			}
		}

		// Select valid referrals only
		$where .= empty( $where ) ? "WHERE " : "AND ";
		$where .= "`$this->primary_key` > 0";

		// Get whitelist of orderby columns before specifying orderby
		if ( '' !== $args['sum_fields'] ) {
			$valid_orderby_columns = array_merge( $this->get_columns(), $this->filter_sum_columns( $args['sum_fields'] ) );
		} else {
			$valid_orderby_columns = $this->get_columns();
		}

		$orderby = array_key_exists( $args['orderby'], $valid_orderby_columns ) ? $args['orderby'] : $this->primary_key;

		// Non-column orderby exception;
		if ( 'amount' === $args['orderby'] ) {
			$orderby = 'amount+0';
		}

		$groupby = $this->prepare_group_by( $args['groupby'] );

		// There can be only two orders.
		if ( 'DESC' === strtoupper( $args['order'] ) ) {
			$order = 'DESC';
		} else {
			$order = 'ASC';
		}

		// Overload args values for the benefit of the cache.
		$args['orderby'] = $orderby;
		$args['order']   = $order;

		// Fields.
		$callback = '';

		if ( 'ids' === $args['fields'] ) {
			$fields   = "$this->primary_key";
			$callback = 'intval';
		} else {
			$fields = $this->parse_fields( $args['fields'], $args['date_format'] );

			if ( '*' === $fields ) {
				$callback = 'affwp_get_referral';
			}
		}

		// Append sum fields, if specified.
		$fields = $this->prepare_sum_fields( $fields, $args['sum_fields'] );

		$key = ( true === $count ) ? md5( 'affwp_referrals_count' . serialize( $args ) ) : md5( 'affwp_referrals_' . serialize( $args ) );

		$last_changed = wp_cache_get( 'last_changed', $this->cache_group );
		if ( ! $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, $this->cache_group );
		}

		$cache_key = "{$key}:{$last_changed}";

		$results = wp_cache_get( $cache_key, $this->cache_group );

		if ( false === $results ) {

			$clauses = compact( 'fields', 'join', 'where', 'groupby', 'orderby', 'order', 'count' );

			$results = $this->get_results( $clauses, $args, $callback );
		}

		wp_cache_add( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

		return $results;

	}

	/**
	 * Return the number of results found for a given query
	 *
	 * @param array $args
	 * @return int
	 */
	public function count( $args = array() ) {
		return $this->get_referrals( $args, true );
	}

	/**
	 * Retrieves sum of all earnings filtered by the status, affiliate id, or date.
	 *
	 * @since 2.3
	 * @since 2.5 Added optional `$context` and `$type` parameters.
	 *
	 * @param array|string $status       The earning status to get. Can be a single status, or an array of multiple
	 *                                   statuses.
	 * @param int          $affiliate_id Optional. The affiliate ID to sum from. Default 0. This will get the total
	 *                                   earnings for all affiliates if set to 0.
	 * @param array|string $date         Optional. Date range in which to calculate sum from. Default empty.
	 * @param array|string $context      Optional. The context type or array of types to use in the calculation.
	 *                                   Default empty.
	 * @param array|string $type         Optional. The referral type or array of types to use in the calculation.
	 *                                   Default empty.
	 * @return int|float The total earnings from the provided parameters. Returns 0 if affiliate does not exist.
	 */
	public function get_earnings_by_status( $status, $affiliate_id = 0, $date = '', $context = '', $type = '' ) {

		$affiliate_id = absint( $affiliate_id );

		$args = array(
			'status'       => $status,
			'affiliate_id' => $affiliate_id,
			'number'       => -1,
			'fields'       => 'amount',
			'context'      => $context,
			'groupby'      => $affiliate_id > 0 ? 'affiliate_id' : '',
			'sum_fields'   => array( 'amount' ),
		);

		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		if ( 0 !== $args['affiliate_id'] && false === affwp_get_affiliate( $args['affiliate_id'] ) ) {
			return 0;
		}

		if ( ! empty( $date ) ) {
			// Back-compat for string date rates.
			if ( is_string( $date ) ) {
				switch ( $date ) {

					case 'month' :

						$date = array(
							'start' => date( 'Y-m-01 00:00:00', current_time( 'timestamp' ) ),
							'end'   => date( 'Y-m-' . cal_days_in_month( CAL_GREGORIAN, date( 'n' ), date( 'Y' ) ) . ' 23:59:59', current_time( 'timestamp' ) ),
						);
						break;

					case 'last-month':
						$date = array(
							'start' => date( 'Y-m-01 00:00:00', ( current_time( 'timestamp' ) - MONTH_IN_SECONDS ) ),
							'end'   => date( 'Y-m-' . cal_days_in_month( CAL_GREGORIAN, date( 'n' ), date( 'Y' ) ) . ' 23:59:59', ( current_time( 'timestamp' ) - MONTH_IN_SECONDS ) ),
						);
						break;
					case 'alltime':
						$date = '';
						break;
				}
			}

			$args['date'] = $date;
		}

		$earnings = $this->get_referrals( $args );

		if ( isset( $earnings[0] ) && isset( $earnings[0]->amount_sum ) ) {
			$result = (float) $earnings[0]->amount_sum;
		} else {
			$result = 0.0;
		}

		return $result;
	}

	/**
	 * Retrieves the total paid earnings.
	 *
	 * @access  public
	 * @since   1.0
	 * @since   2.3 Refactored to use get_earnings_by_status()
	 *
	 * @param string | array $date         Optional. The date, or date range, to retrieve the earnings from. Accepts an array containing a start
	 *                                     and end date, a string containing a single date, or "alltime" for all dates. Default ''
	 * @param int            $affiliate_id Optional. The affiliate ID to get the earnings from. Default 0. This will get the total paid earnings for all affiliates if 0.
	 * @param bool           $format       Optional. Set to true to format the date as a currency string. Set to false to get the value as a float.
	 * @return array|float|int
	 */
	public function paid_earnings( $date = '', $affiliate_id = 0, $format = true ) {
		$earnings = $this->get_earnings_by_status( 'paid', $affiliate_id, $date );

		if ( $format ) {
			$earnings = affwp_currency_filter( affwp_format_amount( $earnings ) );
		}

		return $earnings;
	}

	/**
	 * Get the total unpaid earnings
	 *
	 * @access  public
	 * @since   1.0
	*/
	public function get_alltime_earnings() {
		return get_option( 'affwp_alltime_earnings', 0.00 );
	}

	/**
	 * Get the total unpaid earnings
	 *
	 * @access  public
	 * @since   1.0
	 * @since   2.3 Refactored to use get_earnings_by_status()
	 *
	 * @param string | array $date         Optional. The date, or date range, to retrieve the earnings from. Accepts an array containing a start
	 *                                     and end date, a string containing a single date, or "alltime" for all dates. Default ''
	 * @param int            $affiliate_id Optional. The affiliate ID to get the earnings from. Default 0.  This will get the total unpaid earnings for all affiliates if 0.
	 * @param bool           $format       Optional. Set to true to format the date as a currency string. Set to false to get the value as a float. Default false
	 * @return array|float|int
	 */
	public function unpaid_earnings( $date = '', $affiliate_id = 0, $format = true ) {
		$earnings = $this->get_earnings_by_status( 'unpaid', $affiliate_id, $date );

		if ( $format ) {
			$earnings = affwp_currency_filter( affwp_format_amount( $earnings ) );
		}

		return $earnings;
	}

	/**
	 * Counts the total number of referrals for the given status.
	 *
	 * @access public
	 * @since  1.8.6
	 *
	 * @param array|string $status       The status to get. Can be a single status, or an array of multiple
	 *                                   statuses.
	 * @param int          $affiliate_id Optional. Affiliate ID. Default 0. This will get total counts for all affiliates if 0.
	 * @param string       $date         Optional. Date range in which to search. Accepts 'month'. Default empty.
	 * @return int Number of referrals for the given status or 0 if the affiliate doesn't exist.
	 */
	public function count_by_status( $status, $affiliate_id = 0, $date = '' ) {

		$args = array(
			'status'       => $status,
			'affiliate_id' => absint( $affiliate_id ),
		);

		if ( 0 !== $args['affiliate_id'] && false === affwp_get_affiliate( $args['affiliate_id'] ) ) {
			return 0;
		}

		if ( ! empty( $date ) ) {

			// Whitelist for back-compat string values.
			if ( is_string( $date ) && ! in_array( $date, array( 'month', 'last-month', 'today' ) ) ) {
				$date = '';
			}

			if ( is_string( $date ) ) {
				switch( $date ) {
					case 'month':
						$date = array(
							'start' => date( 'Y-m-01 00:00:00', current_time( 'timestamp' ) ),
							'end'   => date( 'Y-m-' . cal_days_in_month( CAL_GREGORIAN, date( 'n' ), date( 'Y' ) ) . ' 23:59:59', current_time( 'timestamp' ) ),
						);
						break;

					case 'last-month':
						$date = array(
							'start' => date( 'Y-m-01 00:00:00', ( current_time( 'timestamp' ) - MONTH_IN_SECONDS ) ),
							'end'   => date( 'Y-m-' . cal_days_in_month( CAL_GREGORIAN, date( 'n' ), date( 'Y' ) ) . ' 23:59:59', ( current_time( 'timestamp' ) - MONTH_IN_SECONDS ) ),
						);
						break;
				}
			}
			$args['date'] = $date;
		}

		return $this->count( $args );
	}

	/**
	 * Count the total number of paid referrals
	 *
	 * @access  public
	 * @since   2.1.11
	 *
	 * @see count_by_status()
	 *
	 * @param string $date         Optional. Date range in which to search. Accepts 'month'. Default empty.
	 * @param int    $affiliate_id Optional. Affiliate ID. Default 0. This will get the count for all affiliates if 0.
	 * @return int Number of referrals for the given status or 0 if the affiliate doesn't exist.
	*/
	public function paid_count( $date = '', $affiliate_id = 0 ) {
		return $this->count_by_status( 'paid', $affiliate_id, $date );
	}

	/**
	 * Count the total number of unpaid referrals
	 *
	 * @access  public
	 * @since   1.0
	 * @since   1.8.6 Converted to a wrapper for count_by_status()
	 *
	 * @see count_by_status()
	 *
	 * @param string $date         Optional. Date range in which to search. Accepts 'month'. Default empty.
	 * @param int    $affiliate_id Optional. Affiliate ID. Default 0. This will get the total unpaid count for all affiliates if 0.
	 * @return int Number of referrals for the given status or 0 if the affiliate doesn't exist.
	*/
	public function unpaid_count( $date = '', $affiliate_id = 0 ) {
		return $this->count_by_status( 'unpaid', $affiliate_id, $date );
	}

	/**
	 * Set the status of multiple referrals at once
	 *
	 * @access  public
	 * @since   1.0
	*/
	public function bulk_update_status( $referral_ids = array(), $status = '' ) {

		global $wpdb;

		if( empty( $referral_ids ) ) {
			return false;
		}

		if( empty( $status ) ) {
			return false;
		}

		$referral_ids = implode( ',', array_map( 'intval', $referral_ids ) );

		// Not working yet
		$update = $wpdb->query( $wpdb->prepare( "UPDATE $this->table_name SET status = '%s' WHERE $this->primary_key IN(%s)", $status, $referral_ids ) );

		if( $update ) {
			return true;
		}
		return false;
	}

	/**
	 * Set up the customer_id key for the args array.
	 *
	 * A customer record will be created if it does not already exist.
	 *
	 * @since 2.2
	 *
	 * @param array $args {
	 *     Optional. Arguments for setting up the customer record.
	 *
	 *     @type int    $customer_id ID of an existing customer record to attribute the referral to.
	 *     @type string $email       Email address for the customer.
	 * }
	 * @return int The ID of the customer record for the referral.
	 */
	private function setup_customer( $args = array() ) {

		$existing      = false;
		$customer_id   = 0;

		if( ! isset( $args['customer'] ) ) {
			return $customer_id;
		}

		if( ! empty( $args['customer_id'] ) ) {

			// Ensure the provided customer ID exists
			$customer = affwp_get_customer( absint( $args['customer_id'] ) );

			if( $customer ) {
				$existing    = true;
				$customer_id = $customer->customer_id;
			}

		}

		if( ! $existing && is_array( $args['customer'] ) && ! empty( $args['customer']['email'] ) ) {

			$customer = affwp_get_customer_by( 'email', $args['customer']['email'] );

			if ( ! is_wp_error( $customer ) ) {
				$existing    = true;
				$customer_id = $customer->customer_id;
			}

		}

		if( $existing ) {

			// Update the customer record
			$args['customer_id'] = $customer_id;

			if ( ! $customer->user_id ) {

				$user = get_user_by( 'email', $customer->email );

				if ( $user ) {

					$args['user_id'] = $user->ID;

				}
			}

			affwp_update_customer( $args );

		} else {

			// Create a new customer record
			$customer_id = affiliate_wp()->customers->add( $args['customer'] );

		}

		return $customer_id;
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   1.0
	*/
	public function create_table() {

		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE " . $this->table_name . " (
		referral_id  bigint(20)  NOT NULL AUTO_INCREMENT,
		affiliate_id bigint(20)  NOT NULL,
		visit_id     bigint(20)  NOT NULL,
		rest_id      mediumtext  NOT NULL,
		customer_id  bigint(20)  NOT NULL,
		parent_id    bigint(20)  NOT NULL,
		description  longtext    NOT NULL,
		status       tinytext    NOT NULL,
		amount       mediumtext  NOT NULL,
		currency     char(3)     NOT NULL,
		custom       longtext    NOT NULL,
		context      tinytext    NOT NULL,
		campaign     varchar(50) NOT NULL,
		type         varchar(30) NOT NULL,
		reference    mediumtext  NOT NULL,
		products     mediumtext  NOT NULL,
		payout_id    bigint(20)  NOT NULL,
		date         datetime    NOT NULL,
		PRIMARY KEY  (referral_id),
		KEY affiliate_id (affiliate_id)
		) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}
}
