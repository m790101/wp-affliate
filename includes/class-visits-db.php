<?php
/**
 * Visits Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

/**
 * Class Affiliate_WP_Visits_DB
 *
 * @since 1.0
 *
 * @see Affiliate_WP_DB
 *
 * @property-read \AffWP\Affiliate\REST\v1\Endpoints $REST Visits REST endpoints.
 */
class Affiliate_WP_Visits_DB extends Affiliate_WP_DB {

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
	public $cache_group = 'visits';

	/**
	 * Database group value.
	 *
	 * @since 2.5
	 * @var string
	 */
	public $db_group = 'visits';

	/**
	 * Object type to query for.
	 *
	 * @since 1.9
	 * @access public
	 * @var string
	 */
	public $query_object_type = 'AffWP\Visit';

	public function __construct() {
		global $wpdb, $wp_version;

		if( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE ) {
			// Allows a single visits table for the whole network
			$this->table_name  = 'affiliate_wp_visits';
		} else {
			$this->table_name  = $wpdb->prefix . 'affiliate_wp_visits';
		}
		$this->primary_key = 'visit_id';
		$this->version     = '1.3';

		// REST endpoints.
		if ( version_compare( $wp_version, '4.4', '>=' ) ) {
			$this->REST = new \AffWP\Visit\REST\v1\Endpoints;
		}
	}

	/**
	 * Retrieves a visit object.
	 *
	 * @since 1.9
	 *
	 * @see Affiliate_WP_DB::get_core_object()
	 *
	 * @param int|object|AffWP\Visit $visit Visit ID or object.
	 * @return object|false Visit object, false otherwise.
	 */
	public function get_object( $visit ) {
		return $this->get_core_object( $visit, $this->query_object_type );
	}

	public function get_columns() {
		return array(
			'visit_id'     => '%d',
			'affiliate_id' => '%d',
			'referral_id'  => '%d',
			'rest_id'      => '%s',
			'url'          => '%s',
			'referrer'     => '%s',
			'campaign'     => '%s',
			'context'      => '%s',
			'ip'           => '%s',
			'date'         => '%s',
		);
	}

	public function get_column_defaults() {
		return array(
			'affiliate_id' => 0,
			'referral_id'  => 0,
			'date'         => gmdate( 'Y-m-d H:i:s' ),
			'referrer'     => ! empty( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
			'campaign'     => ! empty( $_REQUEST['campaign'] )    ? $_REQUEST['campaign']    : '',
			'context'      => ! empty( $_REQUEST['context'] )     ? $_REQUEST['context']     : ''
		);
	}

	/**
	 * Retrieve visits from the database
	 *
	 * @access  public
	 * @since   1.0
	 * @param   array $args {
	 *     Optional. Arguments to retrieve visits. Default empty array.
	 *
	 *     @type int          $number           Number of visits to retrieve. Accepts -1 for all. Default 20.
	 *     @type int          $offset           Number of visits to offset in the query. Default 0.
	 *     @type int|array    $visit_id         Specific visit ID or array of IDs to query for. Default 0 (all).
	 *     @type int|array    $affiliate_id     Specific affiliate ID or array of IDs to query visits for.
	 *                                          Default 0 (all).
	 *     @type int|array    $referral_id      Specific referral ID or array of IDs to query visits for.
	 *                                          Default 0 (all).
	 *     @type string       $referral_status  Specific conversion status to query for. Accepts 'converted'
	 *                                          or 'unconverted'. Default empty (all).
	 *     @type string|array $campaign         Specific campaign or array of campaigns to query visits for. Default
	 *                                          empty.
	 *     @type string       $campaign_compare Comparison operator to use when querying for visits by campaign.
	 *                                          Accepts '=', '!=' or 'NOT EMPTY'. If 'EMPTY' or 'NOT EMPTY', `$campaign`
	 *                                          will be ignored and visits will simply be queried based on whether
	 *                                          the campaign column is empty or not. Default '='.
	 *     @type string|array $context          Context or array of contexts under which the visit was generated.
	 *                                          Default empty.
	 *     @type string       $context_compare  Comparison operator to use when querying for visits by context. Accepts
	 *                                          '=', '!=', or 'NOT EMPTY'. If 'EMPTY' or 'NOT EMPTY', `$context`
	 *                                          will be ignored and visits will simply be queried based on whether the
	 *                                          context column is empty or not. Default '='.
	 *     @type string       $orderby          Column to order results by. Accepts any valid referrals table column.
	 *                                          Default 'referral_id'.
	 *     @type string       $order            How to order results. Accepts 'ASC' (ascending) or 'DESC' (descending).
	 *                                          Default 'DESC'.
	 *     @type string|array $fields           Specific fields to retrieve. Accepts 'ids', a single visit field, or an
	 *                                          array of fields. Default '*' (all).
	 *     @type string|array $date             {
	 *         Date string or start/end range to retrieve visits for.
	 *
	 *         @type string $start Start date to retrieve visits for.
	 *         @type string $end   End date to retrieve visits for.
	 *     }
	 *     @type string       $date_format    Specific format for date. Adds a formatted_date to response. Uses MYSQL date_format syntax.
	 * }
	 * @param   bool  $count  Return only the total number of results found (optional)
	 * @return array|int Array of visit objects or field(s) (if found), int if `$count` is true.
	*/
	public function get_visits( $args = array(), $count = false ) {
		global $wpdb;

		$defaults = array(
			'number'           => 20,
			'offset'           => 0,
			'visit_id'         => 0,
			'affiliate_id'     => 0,
			'referral_id'      => 0,
			'referral_status'  => '',
			'campaign'         => '',
			'campaign_compare' => '=',
			'context'          => '',
			'context_compare'  => '=',
			'order'            => 'DESC',
			'orderby'          => 'visit_id',
			'fields'           => '',
			'date_format'      => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = $join = '';

		// Specific visits.
		if( ! empty( $args['visit_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['visit_id'] ) ) {
				$visit_ids = implode( ',', array_map( function( $visit_id ) {
					return esc_sql( intval( $visit_id ) );
				}, $args['visit_id'] ) );
			} else {
				$visit_ids = esc_sql( intval( $args['visit_id'] ) );
			}

			$where .= "`visit_id` IN( {$visit_ids} ) ";
		}

		// visits for specific affiliates
		if( ! empty( $args['affiliate_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['affiliate_id'] ) ) {
				$affiliate_ids = implode( ',', array_map( 'intval', $args['affiliate_id'] ) );
			} else {
				$affiliate_ids = intval( $args['affiliate_id'] );
			}

			$where .= "`affiliate_id` IN( {$affiliate_ids} ) ";

		}

		// visits for specific referral
		if( ! empty( $args['referral_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['referral_id'] ) ) {
				$referral_ids = implode( ',', array_map( 'intval', $args['referral_id'] ) );
			} else {
				$referral_ids = intval( $args['referral_id'] );
			}

			$where .= "`referral_id` IN( {$referral_ids} ) ";

		}

		if ( empty( $args['campaign_compare'] ) ) {
			$campaign_compare = '=';
		} else {
			if ( 'NOT EMPTY' === $args['campaign_compare'] ) {
				$campaign_compare = '!=';

				// Cancel out campaign value for comparison purposes.
				$args['campaign'] = '';
			} elseif ( 'EMPTY' === $args['campaign_compare'] ) {
				$campaign_compare = '=';

				// Cancel out campaign value for comparison purposes.
				$args['campaign'] = '';
			} else {
				$campaign_compare = $args['campaign_compare'];
			}
		}

		// visits for specific campaign
		if( ! empty( $args['campaign'] )
			|| ( empty( $args['campaign'] ) && '=' !== $campaign_compare )
		) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['campaign'] ) ) {

				if ( '!=' === $campaign_compare ) {
					$where .= "`campaign` NOT IN(" . implode( ',', array_map( 'esc_sql', $args['campaign'] ) ) . ") ";
				} else {
					$where .= "`campaign` IN(" . implode( ',', array_map( 'esc_sql', $args['campaign'] ) ) . ") ";
				}

			} else {

				$campaign = esc_sql( $args['campaign'] );

				if ( empty( $args['campaign'] ) ) {
					$where .= "`campaign` {$campaign_compare} '' ";
				} else {
					$where .= "`campaign` {$campaign_compare} '{$campaign}' ";
				}
			}

		}

		// Visits context comparison.
		if ( empty( $args['context_compare'] ) ) {
			$context_compare = '=';
		} else {
			if ( 'NOT EMPTY' === $args['context_compare'] ) {
				$context_compare = '!=';

				// Cancel out context value for comparison purposes.
				$args['context'] = '';
			} elseif ( 'EMPTY' === $args['context_compare'] ) {
				$context_compare = '=';

				// Cancel out context value for comparison purposes.
				$args['context'] = '';
			} else {
				$context_compare = $args['context_compare'];
			}
		}

		// Visits context.
		if( ! empty( $args['context'] )
			|| ( empty( $args['context'] ) && '=' !== $context_compare )
			|| ( empty( $args['context'] ) && '=' === $context_compare && 'EMPTY' === $args['context_compare'] )
		) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['context'] ) ) {

				if ( '!=' === $context_compare ) {
					$where .= "`context` NOT IN('" . join("', '", array_map( 'esc_sql', $args['context'] ) ) . "') ";
				} else {
					$where .= "`context` IN('" . join("', '", array_map( 'esc_sql', $args['context'] ) ) . "') ";
				}

			} else {

				$context = esc_sql( $args['context'] );

				if ( empty( $args['context'] ) ) {
					$where .= "`context` {$context_compare} '' ";
				} else {
					$where .= "`context` {$context_compare} '{$context}' ";
				}
			}

		}

		// visits for specific referral status
		if ( ! empty( $args['referral_status'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( 'converted' === $args['referral_status'] ) {
				$where .= "`referral_id` > 0 ";
			} elseif ( 'unconverted' === $args['referral_status'] ) {
				$where .= "`referral_id` = 0 ";
			}

		}

		// Visits for a date or date range
		if( ! empty( $args['date'] ) ) {
			$where = $this->prepare_date_query( $where, $args['date'] );
		}

		// visits for specific referring url
		if( ! empty( $args['url'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			$search_value = esc_sql( $args['url'] );

			$where .= "`referrer` LIKE '" . $search_value . "'";

		}

		// Build the search query
		if( ! empty( $args['search'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( filter_var( $args['search'], FILTER_VALIDATE_IP ) ) {
				$where .= "`ip` LIKE '%%" . esc_sql( $args['search'] ) . "%%' ";
			} else {
				$search_value = esc_sql( $args['search'] );

				$where .= "( `referrer` LIKE '%%" . $search_value . "%%' OR `url` LIKE '%%" . $search_value . "%%' ) ";
			}
		}

		// Select valid visits only
		$where .= empty( $where ) ? "WHERE " : "AND ";
		$where .= "`$this->primary_key` > 0";

		if ( 'DESC' === strtoupper( $args['order'] ) ) {
			$order = 'DESC';
		} else {
			$order = 'ASC';
		}

		$orderby = array_key_exists( $args['orderby'], $this->get_columns() ) ? $args['orderby'] : $this->primary_key;

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
				$callback = 'affwp_get_visit';
			}
		}

		$key = ( true === $count ) ? md5( 'affwp_visits_count' . serialize( $args ) ) : md5( 'affwp_visits_' . serialize( $args ) );

		$last_changed = wp_cache_get( 'last_changed', $this->cache_group );
		if ( ! $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, $this->cache_group );
		}

		$cache_key = "{$key}:{$last_changed}";

		$results = wp_cache_get( $cache_key, $this->cache_group );

		if ( false === $results ) {

			$clauses = compact( 'fields', 'join', 'where', 'orderby', 'order', 'count' );

			$results = $this->get_results( $clauses, $args, $callback );
		}

		wp_cache_add( $cache_key, $results, $this->cache_group, HOUR_IN_SECONDS );

		return $results;

	}

	/**
	 * Returns the number of results found for a given query
	 *
	 * @param  array  $args
	 * @return int
	 */
	public function count( $args = array() ) {
		return $this->get_visits( $args, true );
	}

	/**
	 * Sanitizes the specified campaign, preparing it to be saved in the database.
	 *
	 * @since 2.6.1
	 *
	 * @param string $campaign The campaign to sanitize.
	 * @return string The sanitized campaign.
	 */
	public function sanitize_campaign( $campaign ) {

		// Make sure any encoded characters are converted before saving.
		$campaign = urldecode( $campaign );

		// Make sure campaign is not longer than 50 characters
		$campaign = substr( $campaign, 0, 50 );

		// Sanitize the value
		$campaign = sanitize_text_field( $campaign );

		return $campaign;
	}

	/**
	 * Adds a visit to the database.
	 *
	 * @since 1.0
	 *
	 * @param array $data {
	 *     Arguments for adding a new visit.
	 *
	 *     @type int    $affiliate_id Required. Affiliate the visit was recorded for.
	 *     @type int    $referral_id  Referral ID attached to the visit (if any).
	 *     @type string $referrer     Referrer. Typically a URL or empty if direct link.
	 *     @type string $url          Visit URL.
	 *     @type string $campaign     Campaign slug.
	 *     @type string $context      Context for the visit (typically the integration).
	 *     @type string $rest_id      REST ID (site:objectId) combination.
	 *     @type string $ip           IP address recorded for the visit. Will be ignored if disable_ip_logging
	 *                                setting is enabled.
	 *     @type string $date         Date the visit was recorded.
	 * }
	 * @return int|false ID of the added visit, otherwise false.
	 */
	public function add( $data = array() ) {

		if( ! empty( $data['url'] ) ) {
			$data['url'] = affwp_sanitize_visit_url( $data['url'] );
		}

		if( ! empty( $data['campaign'] ) ) {
			$data['campaign'] = $this->sanitize_campaign( $data['campaign'] );
		}

		if ( ! empty( $data['context'] ) ) {
			$data['context'] = sanitize_key( substr( $data['context'], 0, 50 ) );
		}

		$rest_id_error = false;

		if ( ! empty( $data['rest_id'] ) ) {
			if ( ! affwp_validate_rest_id( $data['rest_id'] ) ) {
				$rest_id_error = true;

				unset( $data['rest_id'] );
			} else {
				$data['rest_id'] = sanitize_text_field( $data['rest_id'] );
			}
		}

		if ( ! empty( $data['date'] ) ) {
			$time = strtotime( $data['date'] );

			$data['date'] = gmdate( 'Y-m-d H:i:s', $time - affiliate_wp()->utils->wp_offset );
		}

		if ( affiliate_wp()->settings->get( 'disable_ip_logging' ) ) {
			$data['ip'] = '';
		}

		$visit_id = $this->insert( $data, 'visit' );

		if ( $visit_id ) {

			affwp_increase_affiliate_visit_count( $data['affiliate_id'] );

			affiliate_wp()->campaigns->update_campaign( $visit_id );

			if ( false !== $rest_id_error ) {
				affiliate_wp()->utils->log( sprintf( 'REST ID %1$s for new visit #%2$d is invalid.',
					$rest_id_error,
					$visit_id
				) );
			}
		}

		return $visit_id;
	}

	/**
	 * Deletes a record from the database.
	 *
	 * Please note: successfully deleting a record flushes the cache.
	 *
	 * @access public
	 * @since  1.1
	 * @since  2.7 Also updates campaign counts on deletion.
	 *
	 * @param int|string $row_id Row ID.
	 *
	 * @return bool               False if the record could not be deleted, true otherwise.
	 */
	public function delete( $row_id = 0, $type = '' ) {
		$visit   = affwp_get_visit( $row_id );
		$deleted = parent::delete( $row_id, $type );

		// If the record was deleted, update the campaign object.
		if ( true === $deleted ) {
			affiliate_wp()->campaigns->update_campaign( $visit );
		}
		return $deleted;
	}

	/**
	 * Updates a visit.
	 *
	 * @since  1.9
	 * @access public
	 *
	 * @param int|AffWP\Visit $visit_id     Visit ID or object.
	 * @param array           $data         {
	 *     Arguments for updating a new visit.
	 *
	 *     @type int    $affiliate_id Affiliate to associate the visit with. Ignored if invalid.
	 *     @type int    $referral_id  Referral ID attached to the visit (if any). Ignored if invalid.
	 *     @type string $referrer     Referrer. Typically a URL or empty if direct link.
	 *     @type string $url          Visit URL.
	 *     @type string $campaign     Campaign slug.
	 *     @type string $context      Context for the visit (typically the integration).
	 *     @type string $rest_id      REST ID (site:objectId) combination.
	 *     @type string $ip           IP address recorded for the visit.
	 *     @type string $date         Date the visit was recorded.
	 * }
	 * @return int|false The visit ID if successfully updated, false otherwise.
	 */
	public function update_visit( $visit, $data = array() ) {

		if ( ! $visit = affwp_get_visit( $visit ) ) {
			return false;
		}

		$args = array();

		if ( ! empty( $data['referral_id'] ) ) {
			// If the passed affiliate ID is invalid, ignore the new value.
			if ( ! affwp_get_referral( $data['referral_id'] ) ) {
				$args['referral_id'] = $visit->referral_id;
			} else {
				$args['referral_id'] = $data['referral_id'];
			}
		}

		if ( isset( $data['referrer'] ) ) {
			$args['referrer'] = sanitize_text_field( $data['referrer'] );
		}

		if ( ! empty( $data['url'] ) ) {
			$args['url'] = affwp_sanitize_visit_url( $data['url'] );
		}

		if ( ! empty( $data['campaign'] ) ) {
			$args['campaign'] = $this->sanitize_campaign( $data['campaign'] );
		}

		if ( ! empty( $data['context'] ) ) {
			$args['context'] = sanitize_key( substr( $data['context'], 0, 50 ) );
		}

		if ( ! empty( $data['affiliate_id'] ) ) {
			// If the passed affiliate ID is invalid, ignore the new value.
			if ( ! affwp_get_affiliate( $data['affiliate_id'] ) ) {
				$args['affiliate_id'] = $visit->affiliate_id;
			}
		}

		if ( ! empty( $data['date' ] ) && $data['date'] !== $visit->date ) {
			$timestamp    = strtotime( $data['date'] ) - affiliate_wp()->utils->wp_offset;
			$args['date'] = gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		if ( ! empty( $data['rest_id'] ) && is_string( $data['rest_id'] ) && $data['rest_id'] !== $visit->rest_id ) {
			if ( false !== strpos( $data['rest_id'], ':' ) ) {
				$args['rest_id'] = sanitize_text_field( $data['rest_id'] );
			} else {
				$args['rest_id'] = $visit->rest_id;
			}
		}

		if ( $this->update( $visit->ID, $args, '', 'visit' ) ) {
			$updated_visit = affwp_get_visit( $visit->ID );

			// If the campaign updated, update it.
			if ( $visit->url !== $updated_visit->url || $visit->referral_id !== $updated_visit->referral_id ) {
				affiliate_wp()->campaigns->update_campaign( $updated_visit );
			}

			// Handle visit counts if the affiliate was changed.
			if ( $updated_visit->affiliate_id !== $visit->affiliate_id ) {

				affwp_decrease_affiliate_visit_count( $visit->affiliate_id );
				affwp_increase_affiliate_visit_count( $updated_visit->affiliate_id );
			}
			return $visit->ID;
		}
		return false;
	}

	/**
	 * Fetches the unique campaigns found in visit data.
	 *
	 * Used for batch processes to ensure campaign counts are correct. It is generally better to query the campaigns table
	 * directly instead of using this method.
	 *
	 * @since  2.7
	 * @access public
	 *
	 * @param array    $args         {
	 *     Optional. Arguments to retrieve visits. Default empty array.
	 *
	 *     @type int       $number       Number of visits to retrieve. Accepts -1 for all. Default 20.
	 *     @type int       $offset       Number of visits to offset in the query. Default 0.
	 *     @type int|array $affiliate_id Specific affiliate ID or array of IDs to query visits for.
	 *                                   Default 0 (all).
	 * }
	 *
	 * @return array|int
	 */
	public function get_unique_campaigns( $args = array(), $count = false ) {
		global $wpdb;

		$defaults = array(
			'offset'       => 0,
			'affiliate_id' => 0,
			'number'       => 20,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = 'WHERE campaign != "" ';

		if ( ! empty( $args['affiliate_id'] ) ) {

			if ( is_array( $args['affiliate_id'] ) ) {
				$affiliate_ids = implode( ',', array_map( 'intval', $args['affiliate_id'] ) );
			} else {
				$affiliate_ids = intval( $args['affiliate_id'] );
			}

			$where .= "AND affiliate_id IN( {$affiliate_ids} ) ";

		} else {
			$where .= "AND affiliate_id > 0";
		}

		$table_name = $this->table_name;

		if ( true === $count ) {
			$prepared = "SELECT COUNT(DISTINCT campaign) AS count FROM {$table_name} {$where}";
		} else {
			$sql      = "SELECT campaign, affiliate_id FROM {$table_name} {$where} GROUP BY campaign, affiliate_id LIMIT %d OFFSET %d";
			$prepared = $wpdb->prepare( $sql, $args['number'], $args['offset'] );
		}

		$result = $wpdb->get_results( $prepared );

		if ( false === $result ) {
			affiliate_wp()->utils->log( 'An error occurred when counting unique campaigns', $wpdb->last_error );
			return true === $count ? 0 : array();
		}

		// Convert result to int if this is a count.
		if ( true === $count ) {
			if ( isset( $result[0] ) && isset( $result[0]->count ) ) {
				$result = (int) $result[0]->count;
			} else {
				$result = 0;
			}
		}

		return $result;
	}

	/**
	 * Creates the visits database table.
	 *
	 * @access public
	 *
	 * @see dbDelta()
	 */
	public function create_table() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE {$this->table_name} (
			visit_id     bigint(20)  NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20)  NOT NULL,
			referral_id  bigint(20)  NOT NULL,
			rest_id      mediumtext  NOT NULL,
			url          mediumtext  NOT NULL,
			referrer     mediumtext  NOT NULL,
			campaign     varchar(50) NOT NULL,
			context      varchar(50) NOT NULL,
			ip           tinytext    NOT NULL,
			date         datetime    NOT NULL,
			PRIMARY KEY (visit_id),
			KEY affiliate_id (affiliate_id)
			) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}
}
