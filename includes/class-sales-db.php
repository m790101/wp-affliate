<?php
/**
 * Sales Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Core/Referrals/Sales
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

use AffWP\Utils\Processors\SQL_Fields_Processor;

/**
 * Implements a database abstraction for querying sales records.
 *
 * @see \Affiliate_WP_DB
 */
class Affiliate_WP_Sales_DB extends Affiliate_WP_DB {

	/**
	 * Cache group for queries.
	 *
	 * @internal DO NOT change. This is used externally both as a cache group and shortcut
	 *           for accessing db class instances via affiliate_wp()->{$cache_group}->*.
	 *
	 * @since 2.5
	 * @var   string
	 */
	public $cache_group = 'sales';

	/**
	 * Database group value.
	 *
	 * @since 2.5
	 * @var string
	 */
	public $db_group = 'referrals:sales';

	/**
	 * Object type to query for.
	 *
	 * @since 2.5
	 * @var   string
	 */
	public $query_object_type = 'AffWP\Referral\Sale';

	/**
	 * Sets up the class.
	 *
	 * @since 2.5
	 */
	public function __construct() {
		global $wpdb, $wp_version;

		if ( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE ) {
			// Allows a single sales table for the whole network
			$this->table_name = 'affiliate_wp_sales';
		} else {
			$this->table_name = $wpdb->prefix . 'affiliate_wp_sales';
		}
		$this->primary_key = 'referral_id';
		$this->version     = '1.1';

		// REST endpoints.
		if ( version_compare( $wp_version, '4.4', '>=' ) ) {
			$this->REST = new \AffWP\Referral\Sale\REST\v1\Endpoints;
		}
	}

	/**
	 * Retrieves a sale object.
	 *
	 * @since 2.5
	 *
	 * @see    Affiliate_WP_DB::get_core_object()
	 *
	 * @param int|object|AffWP\Referral\Sale $sale Sale ID or object.
	 * @return \AffWP\Referral\Sale|null Sale object, null otherwise.
	 */
	public function get_object( $sale ) {
		return $this->get_core_object( $sale, $this->query_object_type );
	}

	/**
	 * Retrieves the columns and their formats, as used by wpdb.
	 *
	 * @since 2.5
	 */
	public function get_columns() {
		return array(
			'referral_id'   => '%d',
			'affiliate_id'  => '%d',
			'order_total'   => '%s',
		);
	}

	/**
	 * Retrieves the list of valid sum columns and formats.
	 *
	 * @since 2.5
	 */
	public function get_sum_columns() {
		return array(
			'order_total_sum' => '%d',
		);
	}

	/**
	 * Retrieves the list of columns and their default values.
	 *
	 * @since 2.5
	 */
	public function get_column_defaults() {
		return array(
			'referral_id'   => 0,
			'affiliate_id'  => 0,
			'order_total'   => 0,
		);
	}

	/**
	 * Adds a sale.
	 *
	 * @since 2.5
	 *
	 * @param array $data Sale values to update, keyed by the column name.
	 * @return int|false Sale ID if successfully added, false otherwise.
	 */
	public function add( $data = array() ) {

		$args = wp_parse_args( $data, $this->get_column_defaults() );

		$result = false;
		$errors = new \WP_Error();

		if ( ! isset( $args['referral_id'] ) ) {
			$errors->add( 'missing_referral_id', 'The referral ID is missing.' );

			$args['referral_id'] = 0;
		}

		$referral = affwp_get_referral( $args['referral_id'] );

		if ( false !== $referral ) {
			// Force context to lowercase for system-wide compatibility.
			$context = strtolower( $referral->context );
		}

		if ( false === $referral ) {
			$errors->add(
					'invalid_referral_id',
					sprintf( 'The #%d referral ID is invalid.',
							$args['referral_id']
					)
			);
		} elseif ( false === affiliate_wp()->integrations->supports( $context, 'sales_reporting' ) ) {
			$errors->add(
				'not_supported',
				sprintf( 'Referral ID #%d used the %s integration, which does not support sales reporting.',
					$args['referral_id'],
					$context
				)
			);
		} elseif ( 'sale' !== $referral->type ) {
			$errors->add(
				'invalid_referral_type',
				sprintf( 'Referral ID #%d used the %s referral type, which does not support sales reporting.',
					$args['referral_id'],
					$referral->type
				)
			);
		}

		if ( affwp_get_sale( $args['referral_id'] ) ) {
			$errors->add(
				'sale_exists',
				sprintf( 'The sale for referral ID #%d already exists.',
					$args['referral_id']
				)
			);
		}

		// If an order total was not specified, attempt to retrieve it from the reference and integration.
		if ( ! isset( $data['order_total'] ) && false !== $referral ) {
			$integration = affiliate_wp()->integrations->get( $context );

			if ( is_wp_error( $integration ) ) {
				$errors->add(
					'integration_invalid',
					"The specified integration returned an error",
					array( 'error' => $integration )
				);
			} else {
				$args['order_total'] = $integration->get_order_total( $referral->reference );
			}
		}

		$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );

		if ( false === $has_errors ) {

			$args['order_total']  = affwp_sanitize_amount( $args['order_total'] );
			$args['affiliate_id'] = $referral->affiliate_id;
			$args['referral_id']  = $referral->referral_id;

			$add = $this->insert( $args, 'sale', $referral->referral_id );

			if ( $add ) {

				// Clear integration sales count cache. Prevents incorrect sync notifications from popping up.
				$referral->invalidate_sales_counts_cache();

				/**
				 * Fires once a new sale has successfully been inserted into the database.
				 *
				 * @since 2.5
				 *
				 * @param int $add Sale ID.
				 */
				do_action( 'affwp_insert_sale', $add );

				$result = $add;
			}
		} else {
			if ( 0 !== $args['referral_id'] ) {
				$message = sprintf( 'There was a problem while adding sale for referral #%1$d.', $args['referral_id'] );
			} else {
				$message = 'There was a problem while adding the sale.';
			}

			affiliate_wp()->utils->log( $message, $errors );
		}

		return $result;
	}

	/**
	 * Deletes a record from the database.
	 *
	 * Please note: successfully deleting a record flushes the cache.
	 *
	 * @access public
	 *
	 * @param int|string $row_id Row ID.
	 * @param string     $type   Unused.
	 * @return bool False if the record could not be deleted, true otherwise.
	 */
	public function delete( $row_id = 0, $type = '' ) {
		$deleted = parent::delete( $row_id, 'sale' );

		// Clear integration sales count cache. Prevents incorrect sync notifications from popping up.
		if ( true === $deleted ) {
			$referral = affwp_get_referral( $row_id );

			if ( false !== $referral ) {
				$referral->invalidate_sales_counts_cache();
			}
		}

		return $deleted;
	}

	/**
	 * Updates a sale.
	 *
	 * @since 2.5
	 *
	 * @param int|AffWP\Referral\Sale $referral Referral ID or object.
	 * @param array                   $data     The data to use to update the sale.
	 * @return bool True if the sale was successfully updated, otherwise false.
	 */
	public function update_sale( $referral = 0, $data = array() ) {

		$args          = array();
		$sale          = affwp_get_sale( $referral );
		$referral      = affwp_get_referral( $referral );
		$is_valid_sale = false !== $referral && 'sale' === $referral->type;

		// If the sale does not exist and it should, add it.
		if ( false === $sale && $is_valid_sale ) {

			affiliate_wp()->utils->log(
				sprintf( "Sale for record %s was added because the referral type is a sale.",
					$referral->referral_id
				),
				array( 'referral' => $referral, 'data' => $data )
			);

			$data['referral_id'] = $referral->referral_id;

			$updated = $this->add( $data );

			// If the sale does exist, and it should not, delete it.
		} elseif ( false !== $sale && ! $is_valid_sale ) {
			affiliate_wp()->utils->log(
				sprintf( "Sale for record %s was deleted because the referral type is something other than sale.",
					$referral->referral_id
				),
				array( 'referral' => $referral, 'data' => $data )
			);

			$updated = $this->delete( $referral->referral_id );

			// If the referral or sale do not exist, return false
		} elseif ( false === $sale || false === $referral ) {
			$updated = false;

			// Otherwise, update the existing sale.
		} else {

			// Maybe sanitize the order total.
			if ( isset( $data['order_total'] ) ) {
				$args['order_total'] = affwp_sanitize_amount( $data['order_total'] );
			}

			// Sync Affiliate ID with referral ID
			if ( $referral->affiliate_id !== $sale->affiliate_id ) {
				$args['affiliate_id'] = $referral->affiliate_id;
			}

			// Try to update the record.
			$updated = $this->update( $sale->ID, $args, '', 'sale' );
		}

		/**
		 * Fires immediately after a sale update has been attempted.
		 *
		 * @since 2.5
		 *
		 * @param \AffWP\Referral\Sale $updated_sale Updated sale object.
		 * @param \AffWP\Referral\Sale $sale         Original sale object.
		 * @param bool                 $updated      Whether the sale was successfully updated.
		 */
		do_action( 'affwp_updated_sale', affwp_get_sale( $sale ), $sale, $updated );

		return $updated;
	}

	/**
	 * Retrieves sales from the database.
	 *
	 * @since 2.5
	 *
	 * @param array       $args           {
	 *     Optional. Arguments to retrieve sales from the database.
	 *
	 *     @type int          $number         Number of sales to retrieve. Accepts -1 for all. Default 20.
	 *     @type int          $offset         Number of sales to offset in the query. Default 0.
	 *     @type int|array    $referral_id    Specific sale ID or array of IDs to query for. Default 0 (all).
	 *     @type int|array    $affiliate_id   Affiliate ID or array of IDs to query sales for. Default 0 (all).
	 *     @type string|array $date {
	 *         Date string or start/end range to retrieve sales for.
	 *
	 *         @type string $start Start date to retrieve sales for.
	 *         @type string $end   End date to retrieve sales for.
	 *     }
	 *     @type float|array  $order_total {
	 *         Specific order total to query for or min/max range. If float, can be used with `$order_total_compare`.
	 *         If array, `BETWEEN` is used.
	 *
	 *         @type float $min Minimum order total amount amount to query for.
	 *         @type float $max Maximum order total amount amount to query for.
	 *     }
	 *     @type string       $reference      Specific reference to query sales for (usually an order number).
	 *                                            Default empty.
	 *     @type string       $context        Specific context to query sales for. Default empty.
	 *     @type string       $campaign       Specific campaign to query sales for. Default empty.
	 *     @type string       $type           Specific sale type to query sales for. Default empty.
	 *     @type string       $description    Description to search sales for. Fuzzy matching is permitted when
	 *                                            `$search` is true.
	 *     @type string|array $status         Sale status or array of statuses to query sales for.
	 *                                            Default empty (all).
	 *     @type string       $orderby        Column to order results by. Accepts any valid sales table column.
	 *                                            Default 'referral_id'.
	 *     @type string       $order          How to order results. Accepts 'ASC' (ascending) or 'DESC' (descending).
	 *                                            Default 'DESC'.
	 *     @type bool         $search         Whether a search query is being performed. Default false.
	 *     @type string|array $fields         Specific fields to retrieve. Accepts 'ids', a single sale field, or an
	 *                                            array of fields. Default '*' (all).
	 *     @type array        $sum_fields     A database column, or an array of database columns to add to the query as a sum. Default empty string.
	 * }
	 * @param   bool  $count  Optional. Whether to return only the total number of results found. Default false.
	 * @return array|int Array of sale objects or field(s) (if found), int if `$count` is true.
	 */
	public function get_sales( $args = array(), $count = false ) {

		$defaults = array(
			'status'       => '',
			'date'         => '',
			'number'       => 20,
			'offset'       => 0,
			'context'      => '',
			'referral_id'  => 0,
			'affiliate_id' => 0,
			'order_total'  => 0,
			'orderby'      => 'referral_id',
			'groupby'      => '',
			'order'        => 'DESC',
			'fields'       => '',
			'sum_fields'   => '',
		);

		$args            = wp_parse_args( $args, $defaults );
		$query_processor = new SQL_Fields_Processor( $this->db_group, $args['fields'], array( 'referrals' ) );

		if ( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = $join = $groupby = '';

		// Specific referrals
		if ( ! empty( $args['referral_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $args['referral_id'] ) ) {
				$referral_ids = implode( ',', array_map( 'intval', $args['referral_id'] ) );
			} else {
				$referral_ids = intval( $args['referral_id'] );
			}

			$where .= $query_processor->prepend( 'referral_id' ) . " IN( {$referral_ids} ) ";

		}

		// Sales for specific affiliates
		if ( ! empty( $args['affiliate_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $args['affiliate_id'] ) ) {
				$affiliate_ids = implode( ',', array_map( 'intval', $args['affiliate_id'] ) );
			} else {
				$affiliate_ids = intval( $args['affiliate_id'] );
			}

			$where .= $query_processor->prepend( 'affiliate_id' ) . " IN( {$affiliate_ids} ) ";

		}

		// Order Total.
		if ( ! empty( $args['order_total'] ) ) {

			$amount = $args['order_total'];

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $amount ) && ! empty( $amount['min'] ) && ! empty( $amount['max'] ) ) {

				$minimum = absint( $amount['min'] );
				$maximum = absint( $amount['max'] );

				$where .= $query_processor->prepend( 'order_total' ) . " BETWEEN {$minimum} AND {$maximum} ";
			} else {

				$amount  = absint( $amount );
				$compare = '=';

				if ( ! empty( $args['order_total_compare'] ) ) {
					$compare = $args['order_total_compare'];

					if ( ! in_array( $compare, array( '>', '<', '>=', '<=', '=', '!=' ) ) ) {
						$compare = '=';
					}
				}

				$where .= $query_processor->prepend( 'order_total' ) . " {$compare} {$amount} ";
			}
		}

		// Sales for a date or date range
		if ( ! empty( $args['date'] ) ) {
			$query_processor->add_field( 'date', 'referrals' );

			$where = $this->prepare_date_query( $where,
				$args['date'],
				$query_processor->prepend( 'date', 'referrals' )
			);
		}

		// Status
		if ( ! empty( $args['status'] ) ) {
			$query_processor->add_field( 'status', 'referrals' );

			$where .= empty( $where ) ? "WHERE " : "AND ";
			$where .= $query_processor->prepend( 'status', 'referrals' );
			if ( is_array( $args['status'] ) ) {
				$where .= " IN('" . implode( "','", array_map( 'esc_sql', $args['status'] ) ) . "') ";
			} else {
				$where .= " = '" . esc_sql( $args['status'] ) . "' ";
			}
		}

		// Context
		if ( ! empty( $args['context'] ) ) {
			$query_processor->add_field( 'context', 'referrals' );
			// If the context is set to active, use active integrations that support sales data.
			if ( 'active' === $args['context'] ) {
				$supported_active_integrations = affiliate_wp()->integrations->query( array(
						'supports' => array( 'sales_reporting' ),
				) );

				$args['context'] = array_keys( $supported_active_integrations );
			}

			$where .= empty( $where ) ? "WHERE " : "AND ";
			$where .= $query_processor->prepend( 'context', 'referrals' );
			if ( is_array( $args['context'] ) ) {
				$where .= " IN('" . implode( "','", array_map( 'esc_sql', $args['context'] ) ) . "') ";
			} else {
				$where .= " = '" . esc_sql( $args['context'] ) . "' ";
			}
		}

		// Select valid sales only
		$where .= empty( $where ) ? "WHERE " : "AND ";
		$where .= $query_processor->prepend( $this->primary_key ) . " > 0";

		// Fields.
		$callback = '';

		if ( 'ids' === $args['fields'] ) {
			$fields   = $query_processor->prepend( $this->primary_key );
			$callback = 'intval';
		} else {
			$fields = $query_processor->parse_fields( array( 'sum' => $args['sum_fields'] ) );
			if ( '*' === $fields ) {
				$callback = 'affwp_get_sale';
			}
		}

		$key = true === $count ? md5( 'affwp_sales_count' . serialize( $args ) ) : md5( 'affwp_sales_' . serialize( $args ) );

		// orderby
		$orderby = $query_processor->prepare_field( $args['orderby'] );

		// Fallback to primary key if the field failed to prepare.
		if ( is_wp_error( $orderby ) ) {
			$orderby = $query_processor->prepend( $this->primary_key );
		}

		// There can be only two orders.
		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// Overload args values for the benefit of the cache.
		$args['orderby'] = $orderby;
		$args['order']   = $order;

		// groupby
		if ( ! empty( $args['groupby'] ) ) {
			$prepared_group_by = $query_processor->prepare_field( $args['groupby'] );

			// If the groupby field is valid, add the groupby clause.
			if ( ! is_wp_error( $prepared_group_by ) ) {
				$groupby = "GROUP BY {$prepared_group_by}";
			}
		}

		// Join the referrals table, if necessary.
		if ( $query_processor->table_has_fields( 'referrals' ) ) {
			$join .= 'RIGHT JOIN ' . affiliate_wp()->referrals->table_name . ' ON ' . $this->table_name . '.referral_id=' . affiliate_wp()->referrals->table_name . '.referral_id';
		}
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
	 * Retrieves the number of results found for a given query.
	 *
	 * @since 2.5
	 *
	 * @param array $args Arguments for retrieving the count query. See get_sales() for accepted arguments.
	 * @return int Number of sales based on the given arguments.
	 */
	public function count( $args = array() ) {
		return $this->get_sales( $args, true );
	}

	/**
	 * Retrieves sum of all revenue filtered by the status, affiliate id, or date.
	 *
	 * @since 2.5
	 *
	 * @param array|string $status       The referral status to get. Can be a single status, or an array of multiple
	 *                                   statuses.
	 * @param int          $affiliate_id Optional. The affiliate ID to sum from. Default 0. This will get the total
	 *                                   earnings for all affiliates if set to 0.
	 * @param array|string $date         Optional. Date range in which to calculate sum from. Default empty.
	 * @return int|float The total revenue from the provided parameters. Returns 0 if affiliate does not exist.
	 */
	public function get_revenue_by_referral_status( $status, $affiliate_id = 0, $date = '' ) {
		$total_revenue = 0;
		$affiliate_id  = intval( $affiliate_id );

		$args = array(
				'status'       => $status,
				'sum_fields'   => array( 'order_total' ),
				'affiliate_id' => $affiliate_id,
				'number'       => -1,
				'groupby'      => $affiliate_id > 0 ? 'affiliate_id' : '',
		);

		if ( ! empty( $date ) && 'alltime' !== $date ) {
			$args['date'] = $date;
		}

		$results = $this->get_sales( $args );

		if ( isset( $results[0] ) && isset( $results[0]->order_total_sum ) ) {
			$total_revenue = $results[0]->order_total_sum;
		}

		return (float) $total_revenue;
	}

	/**
	 * Calculates the total profits filtered by the status, affiliate id, or date.
	 *
	 * This only accounts for integrations that are active, and enabled.
	 *
	 * @since 2.5
	 *
	 * @param array|string $status       The referral status to get. Can be a single status, or an array of multiple
	 *                                   statuses.
	 * @param int          $affiliate_id Optional. The affiliate ID to sum from. Default 0. This will get the total
	 *                                   earnings for all affiliates if set to 0.
	 * @param array|string $date         Optional. Date range in which to calculate sum from. Default empty.
	 * @return int|float The total profits from the provided parameters. Returns 0 if affiliate does not exist.
	 */
	public function get_profits_by_referral_status( $status, $affiliate_id = 0, $date = '' ) {
		$supported_integrations = affiliate_wp()->integrations->query( array(
			'supported_integrations' => 'sales_reporting',
		) );

		$context = array_keys( $supported_integrations );

		$commissions = affiliate_wp()->referrals->get_earnings_by_status( $status, $affiliate_id, $date, $context, 'sale' );
		$revenue     = affiliate_wp()->referrals->sales->get_revenue_by_referral_status( $status, $affiliate_id, $date );

		return $revenue - $commissions;
	}

	/**
	 * Creates the sales table.
	 *
	 * @since 2.5
	 */
	public function create_table() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE " . $this->table_name . " (
			referral_id   bigint(20) NOT NULL,
			affiliate_id  bigint(20) NOT NULL,
			order_total   mediumtext NOT NULL,
			PRIMARY KEY  (referral_id),
			KEY affiliate_id (affiliate_id)
		) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}
}
