<?php
/**
 * Campaigns Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

/**
 * Class Affiliate_WP_Campaigns_DB
 *
 * @property-read \AffWP\Campaign\REST\v1\Endpoints $REST Campaigns REST endpoints.
 */
class Affiliate_WP_Campaigns_DB extends Affiliate_WP_DB {

	/**
	 * Cache group for queries.
	 *
	 * @internal DO NOT change. This is used externally both as a cache group and shortcut
	 *           for accessing db class instances via affiliate_wp()->{$cache_group}->*.
	 *
	 * @access public
	 * @since  1.9
	 * @var    string
	 */
	public $cache_group = 'campaigns';

	/**
	 * Database group value.
	 *
	 * @since 2.5
	 * @var string
	 */
	public $db_group = 'campaigns';

	/**
	 * Primary key (unique field) for the database table.
	 *
	 * @since 2.7
	 * @var   string
	 */
	public $primary_key = 'campaign_id';

	/**
	 * Object type to query for.
	 *
	 * @since 2.7
	 * @var   string
	 */
	public $query_object_type = 'AffWP\Campaign';

	/**
	 * Database version.
	 *
	 * @since 1.7
	 * @var   string
	 */
	public $version = '1.2';

	/**
	 * Retrieves the columns and their formats, as used by wpdb.
	 *
	 * @since 2.7
	 *
	 * @return array List of column formats keyed by the column name.
	 */
	public function get_columns() {
		return array(
			'campaign_id'     => '%d',
			'affiliate_id'    => '%d',
			'rest_id'         => '%s',
			'campaign'        => '%s',
			'visits'          => '%d',
			'unique_visits'   => '%d',
			'referrals'       => '%d',
			'conversion_rate' => '%f',
			'hash'            => '%s',
		);
	}

	/**
	 * Updates a campaign from a provided visit.
	 *
	 * @since 2.7
	 *
	 * @param int|\AffWP\Visit $visit The visit to update against.
	 *
	 * @return int|bool The campaign ID if successful, otherwise false.
	 */
	public function update_campaign( $visit ) {
		// Store this in-case it needs logged in an error later.
		$original_visit_value = $visit;

		// Maybe get the visit.
		if ( ! $visit instanceof \AffWP\Visit ) {
			$visit = affwp_get_visit( $visit );
		}

		// Validate visit.
		if ( false === $visit ) {
			affiliate_wp()->utils->log( 'Campaign was not updated because an invalid visit was provided.', $original_visit_value );
			return false;
		}

		// If the visit has no campaign, bail silently.
		if ( empty( $visit->campaign ) ) {
			return false;
		}

		return $this->update_affiliate_campaign( $visit->affiliate_id, $visit->campaign );
	}

	/**
	 * Updates campaign data against an affiliate ID and campaign slug.
	 *
	 * @since 2.7
	 *
	 * @param int    $affiliate_id      The affiliate ID for this campaign.
	 * @param string $campaign_slug     The campaign slug.
	 *
	 * @return int|bool The campaign ID if successful, otherwise false.
	 */
	public function update_affiliate_campaign( $affiliate_id, $campaign_slug ) {
		$campaign = affwp_get_affiliate_campaign( $affiliate_id, $campaign_slug );

		$args = array(
			'affiliate_id'  => $affiliate_id,
			'campaign'      => $campaign_slug,
			'referrals'     => $this->get_referral_count( $campaign_slug, $affiliate_id ),
			'unique_visits' => $this->get_unique_visit_count( $campaign_slug, $affiliate_id ),
			'visits'        => $this->get_visit_count( $campaign_slug, $affiliate_id ),
		);

		// If the campaign wasn't found, add a new record.
		if ( is_wp_error( $campaign ) ) {
			return $this->add( $args );

			// Otherwise, update the existing record.
		} else {
			$updated          = $this->update( $campaign->campaign_id, $args, '', 'campaign' );
			$updated_campaign = affwp_get_campaign( $campaign->campaign_id );

			/**
			 * Fires immediately after a campaign update has been attempted.
			 *
			 * @since 2.7
			 *
			 * @param \AffWP\campaign $updated_campaign Updated campaign object.
			 * @param \AffWP\campaign $campaign         Original campaign object.
			 * @param bool            $updated          Whether the campaign was successfully updated.
			 */
			do_action( 'affwp_updated_campaign', $updated_campaign, $campaign, $updated );

			// If updated successfully, return the campaign ID.
			if ( true === $updated ) {
				return $campaign->campaign_id;
			} else {
				return false;
			}
		}
	}

	/**
	 * Adds a campaign.
	 *
	 * @since 2.7
	 *
	 * @param array $data {
	 *     Optional. Array of arguments for adding a new campaign. Default empty array.
	 *
	 *     @type int    $affiliate_id  Affiliate ID. Default 0.
	 *     @type string $campaign      Campaign slug. Default empty string.
	 *     @type string $rest_id       Campaign Rest ID.
	 *     @type int    $visits        The number of campaign visits. Default 0.
	 *     @type int    $unique_visits The number of campaign unique visits. Default 0.
	 *     @type int    $referrals     The number of campaign referrals. Default 0.
	 * }
	 * @return int|false Campaign ID if successfully added, false otherwise.
	 */
	public function add( $data = array() ) {
		$defaults = $this->get_column_defaults();
		$errors   = new WP_Error();
		unset( $defaults[ $this->primary_key ] );

		$data = wp_parse_args( $data, $defaults );

		// Maybe calculate Conversion Rate
		if ( $data['visits'] > 0 && $data['referrals'] > 0 ) {
			$data['conversion_rate'] = $this->calculate_conversion_rate( $data['referrals'], $data['visits'] );
		}

		// Generate the hash from the campaign and affiliate ID.
		$data['hash'] = affwp_get_campaign_hash( $data['affiliate_id'], $data['campaign'] );

		// Add rest ID
		if ( ! empty( $data['rest_id'] ) ) {
			if ( ! affwp_validate_rest_id( $data['rest_id'] ) ) {
				$errors->add( 'invalid_rest_id', sprintf( 'REST ID \'%1$s\' is formatted incorrectly. Must contain a colon.',
					$data['rest_id']
				) );

				unset( $data['rest_id'] );
			} else {
				$data['rest_id'] = sanitize_text_field( $data['rest_id'] );
			}
		}

		$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );
		$added      = $this->insert( $data, 'campaign' );

		if ( false !== $added ) {
			/**
			 * Fires immediately after a campaign has been added to the database.
			 *
			 * @since 2.7
			 *
			 * @param array $added The campaign data that was added.
			 */
			do_action( 'affwp_insert_campaign', $added );

			$result = $added;
		}

		if ( true === $has_errors ) {
			if ( false !== $result ) {
				$message = sprintf( 'There was a problem while adding campaign #%1$d.', $result );
			} else {
				$message = 'There was a problem while adding the campaign.';
			}

			affiliate_wp()->utils->log( $message, $errors );
		}

		return $result;
	}

	/**
	 * Updates an existing record in the database.
	 *
	 * @access public
	 *
	 * @param int   $row_id  Row ID for the record being updated.
	 * @param array $data {
	 *     Optional. Array of arguments for updating a new campaign. Default empty array.
	 *
	 *     @type int    $affiliate_id  Affiliate ID.
	 *     @type string $campaign      Campaign slug.
	 *     @type string $rest_id       Campaign Rest ID.
	 *     @type int    $visits        The number of campaign visits.
	 *     @type int    $unique_visits The number of campaign unique visits.
	 *     @type int    $referrals     The number of campaign referrals.
	 * }
	 * @param string $where   Optional. Column to match against in the WHERE clause. If empty, $primary_key
	 *                        will be used. Default empty.
	 * @param string $type    Optional. Data type context, e.g. 'affiliate', 'creative', etc. Default empty.
	 * @return bool False if the record could not be updated, true otherwise.
	 */
	public function update( $row_id, $data = array(), $where = '', $type = '' ) {
		$campaign = affwp_get_campaign( $row_id );

		// Don't allow the conversion rate to be set directly.
		if ( isset( $data['conversion_rate'] ) ) {
			unset( $data['conversion_rate'] );
		}

		// Maybe calculate Conversion Rate
		if ( isset( $data['visits'] ) || isset( $data['referrals'] ) ) {
			$defaults = array();

			// If either visits or referrals are not set, get the defaults from the campaign record.
			if ( ! isset( $data['visits'] ) || ! isset( $data['referrals'] ) ) {
				$campaign = affwp_get_campaign( $row_id );
				$defaults = array(
					'referrals' => $campaign->referrals,
					'visits'    => $campaign->visits,
				);
			}

			// Override existing values with new. Used to calculate conversion rate.
			$rate_args = wp_parse_args( $data, $defaults );

			$data['conversion_rate'] = $this->calculate_conversion_rate( $rate_args['referrals'], $rate_args['visits'] );
		}

		// Hash should not be manually changed.
		if ( isset( $data['hash'] ) ) {
			unset( $data['hash'] );
		}

		// Maybe update hash
		if ( isset( $data['affiliate_id'] ) || isset( $data['campaign'] ) ) {
			$defaults = array();

			// If either affiliate ID or campaign is not set, use current values.
			if ( ! isset( $data['affiliate_id'] ) || ! isset( $data['campaign'] ) ) {
				$defaults = array(
					'affiliate_id' => $campaign->affiliate_id,
					'campaign'     => $campaign->campaign,
				);
			}

			// Override existing values with new. Used to rebuild the hash.
			$hash_args    = wp_parse_args( $data, $defaults );
			$data['hash'] = affwp_get_campaign_hash( $hash_args['affiliate_id'], $hash_args['campaign'] );
		}

		// Maybe sanitize rest ID
		if ( ! empty( $data['rest_id'] ) && $data['rest_id'] !== $campaign->rest_id ) {
			if ( false === affwp_validate_rest_id( $data['rest_id'] ) ) {
				unset( $data['rest_id'] );
			}
		}

		return parent::update( $row_id, $data, $where, $type );
	}

	/**
	 * Retrieves the number of unique visits based on the provided URL.
	 *
	 * @since 2.7
	 *
	 * @return int The total number of unique visits for this campaign, and affiliate.
	 */
	public function get_unique_visit_count( $campaign, $affiliate_id ) {
		global $wpdb;
		$table = affiliate_wp()->visits->table_name;
		$query = $wpdb->prepare( "SELECT COUNT(DISTINCT url) as total FROM {$table} WHERE `campaign`=%s AND `affiliate_id`=%d", $campaign, $affiliate_id );
		$count = $wpdb->get_results( $query );

		if ( empty( $count[0] ) ) {
			return 0;
		}

		return (int) $count[0]->total;
	}

	/**
	 * Queries the database to get the number of referrals for this campaign.
	 *
	 * @since 2.7
	 *
	 * @param string $campaign The campaign slug
	 *
	 * @return int The number of referrals the current campaign has.
	 */
	public function get_referral_count( $campaign, $affiliate_id ) {
		return affiliate_wp()->visits->count( array(
			'campaign'        => $campaign,
			'affiliate_id'    => $affiliate_id,
			'referral_status' => 'converted',
		) );
	}

	/**
	 * Queries the database to get the number of visits for this campaign.
	 *
	 * @since 2.7
	 *
	 * @param string $referral_id The referral ID to check against.
	 *
	 * @return int The number of visits the current campaign has.
	 */
	public function get_visit_count( $campaign, $affiliate_id ) {
		return affiliate_wp()->visits->count( array(
			'affiliate_id' => $affiliate_id,
			'campaign'     => $campaign,
		) );
	}

	/**
	 * Calculates the conversion rate from the provided vists and referrals.
	 *
	 * @since 2.7
	 *
	 * @param int $referrals The number of referrals
	 *
	 * @param int $visits    The number of visits
	 *
	 * @return float|int The calculated conversion rate.
	 */
	public function calculate_conversion_rate( $referrals, $visits ) {
		$percentage = affwp_calculate_percentage( $referrals, $visits );

		if ( is_infinite( $percentage ) ) {
			return 0.0;
		}

		return $percentage;
	}

	/**
	 * Retrieves the list of valid sum columns and formats.
	 *
	 * @since 2.7
	 *
	 * @return array List of valid sum columns.
	 */
	public function get_sum_columns() {
		return array(
			'visits'        => '%d',
			'unique_visits' => '%d',
			'referrals'     => '%d',
		);
	}

	/**
	 * Retrieves the list of columns and their default values.
	 *
	 * @since 2.7
	 *
	 * @return array List of default values for columns keyed by the column name.
	 */
	public function get_column_defaults() {
		return array(
			'campaign_id'     => 0,
			'affiliate_id'    => 0,
			'rest_id'         => '',
			'campaign'        => '',
			'visits'          => 0,
			'unique_visits'   => 0,
			'referrals'       => 0,
			'conversion_rate' => 0.0,
			'hash'            => '',
		);
	}

	/**
	 * Setup our table name, primary key, and version
	 *
	 * @since  1.7
	 */
	public function __construct() {
		global $wpdb, $wp_version;

		if ( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE ) {
			// Allows a single visits table for the whole network
			$this->table_name = 'affiliate_wp_campaigns';
		} else {
			$this->table_name = $wpdb->prefix . 'affiliate_wp_campaigns';
		}

		// REST endpoints.
		if ( version_compare( $wp_version, '4.4', '>=' ) ) {
			$this->REST = new \AffWP\Campaign\REST\v1\Endpoints;
		}
	}

	/**
	 * Retrieve campaigns and associated stats
	 *
	 * @param  int  $affiliate_id The ID of the affiliate to retrieve campaigns for
	 * @since  1.7
	 *
	 * @param array $args {
	 *     Optional. Arguments to retrieve campaigns.
	 *
	 *     @type int          $number           Number of campaigns to query for. Default 20.
	 *     @type int          $offset           Number of campaigns to offset the query for. Default 0.
	 *     @type int|array    $affiliate_id     Affiliate ID or array of IDs. Default 0.
	 *     @type int|array    $hash             Campaign hash, or array of hashes. Default empty string.
	 *     @type string|array $campaign         Campaign or array of campaigns. Default empty.
	 *     @type string       $campaign_compare Comparison operator to use when querying for visits by campaign.
	 *                                          Accepts '=', '!=' or 'NOT EMPTY'. If 'EMPTY' or 'NOT EMPTY', `$campaign`
	 *                                          will be ignored and campaigns will simply be queried based on whether
	 *                                          the `campaign` column is empty or not. Default '='.
	 *     @type float|array  $conversion_rate  {
	 *         Specific conversion rate to query for or min/max range. If float, can be used with `$rate_compare`.
	 *         If array, `BETWEEN` is used.
	 *
	 *         @type float $min Minimum conversion rate to query for.
	 *         @type float $max Maximum conversion rate to query for.
	 *     }
	 *     @type string       $rate_compare     Comparison operator to use with `$conversion_rate`. Accepts '>', '<',
	 *                                          '>=', '<=', '=', or '!='. Default '='.
	 *     @type string       $order            How to order returned campaign results. Accepts 'ASC' or 'DESC'.
	 *                                          Default 'DESC'.
	 *     @type string       $orderby          Campaigns table column to order results by. Default 'affiliate_id'.
	 *     @type string       $fields           Specific fields to retrieve. Accepts 'ids' or '*' (all). Default '*'.
	 * }
	 * @param bool  $count Optional. Whether to return only the total number of results found. Default false.
	 * @return array|int Array of campaign objects or field(s) (if found) or integer if `$count` is true.
	 */
	public function get_campaigns( $args = array(), $count = false ) {
		global $wpdb;

		// Back-compat for the old $affiliate_id parameter.
		if ( is_numeric( $args ) ) {
			$affiliate_id = $args;
			$args = array(
				'affiliate_id' => $affiliate_id
			);
			unset( $affiliate_id );
		}

		$defaults = array(
			'number'           => 20,
			'offset'           => 0,
			'affiliate_id'     => 0,
			'campaign'         => '',
			'campaign_compare' => '=',
			'conversion_rate'  => 0,
			'rate_compare'     => '',
			'orderby'          => 'affiliate_id',
			'order'            => 'DESC',
			'hash'             => '',
			'fields'           => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = $join = '';

		// Specific affiliate(s).
		if( ! empty( $args['affiliate_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['affiliate_id'] ) ) {
				$affiliate_ids = implode( ',', array_map( 'intval', $args['affiliate_id'] ) );
			} else {
				$affiliate_ids = intval( $args['affiliate_id'] );
			}

			$where .= "`affiliate_id` IN( {$affiliate_ids} ) ";

		}

		// Specific campaign(s).
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

		// Conversion rate.
		if ( ! empty( $args['conversion_rate'] ) ) {

			$rate = $args['conversion_rate'];

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $rate ) && ! empty( $rate['min'] ) && ! empty( $rate['max'] ) ) {

				$minimum = absint( $rate['min'] );
				$maximum = absint( $rate['max'] );

				$where .= "`conversion_rate` BETWEEN {$minimum} AND {$maximum} ";

			} else {

				$rate  = absint( $rate );
				$compare = '=';

				if ( ! empty( $args['rate_compare'] ) ) {
					$compare = $args['rate_compare'];

					if ( ! in_array( $compare, array( '>', '<', '>=', '<=', '=', '!=' ) ) ) {
						$compare = '=';
					}
				}

				$where .= " `conversion_rate` {$compare} {$rate}";
			}
		}

		// Hash
		if ( ! empty( $args['hash'] ) ) {
			$where .= empty( $where ) ? 'WHERE ' : 'AND';

			if ( is_array( $args['hash'] ) ) {
				$hashes = implode( '","', array_map( 'sanitize_text_field', $args['hash'] ) );
			} else {
				$hashes = sanitize_text_field( $args['hash'] );
			}

			$where .= "`hash` IN( \"{$hashes}\" ) ";
		}

		// Orderby.
		switch ( $args['orderby'] ) {
			case 'conversion_rate':
				$orderby = 'conversion_rate+0';
				break;

			case 'visits':
				$orderby = 'visits+0';
				break;

			case 'unique_visits':
				$orderby = 'unique_visits+0';
				break;

			case 'referrals':
				$orderby = 'referrals+0';
				break;

			default:
				$orderby = array_key_exists( $args['orderby'], $this->get_columns() ) ? $args['orderby'] : $this->primary_key;
				break;
		}

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
			$fields = $this->parse_fields( $args['fields'] );

			if ( '*' === $fields ) {
				$callback = 'affwp_get_campaign';
			}
		}

		if ( 'ids' === $args['fields'] ) {
			$fields   = "$this->primary_key";
			$callback = 'intval';
		} else {
			$fields = $this->parse_fields( $args['fields'] );
		}

		$key = ( true === $count ) ? md5( 'affwp_campaigns_count' . serialize( $args ) ) : md5( 'affwp_campaigns_' . serialize( $args ) );

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
	 * Retrieves a campaign record.
	 *
	 * @since 2.7
	 *
	 * @see   Affiliate_WP_DB::get_core_object()
	 *
	 * @param int|AffWP\Affiliate\Campaign $campaign Campaign ID or object.
	 *
	 * @return AffWP\Affiliate\Campaign|false Campaign object, otherwise false.
	 */
	public function get_object( $campaign ) {
		return $this->get_core_object( $campaign, $this->query_object_type );
	}

	/**
	 * Retrieves the number of results found for a given query.
	 *
	 * @access public
	 * @since  2.0.2
	 *
	 * @param array $args get_campaigns() arguments.
	 *
	 * @return int Number of campaigns for the given arguments.
	 */
	public function count( $args = array() ) {
		return $this->get_campaigns( $args, true );
	}

	/**
	 * Create the view.
	 *
	 * @since      1.7
	 * @deprecated 2.7 use create_table instead.
	 */
	public function create_view() {
		_deprecated_function( __FUNCTION__, '2.7', 'Affiliate_WP_Campaigns_DB::create_table' );
		return $this->create_table();
	}

	/**
	 * Create the table.
	 *
	 * @since  2.7
	 */
	public function create_table() {

		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE $this->table_name (
		campaign_id     bigint(20)  NOT NULL AUTO_INCREMENT,
		affiliate_id    bigint(20)  NOT NULL,
		campaign        varchar(50) NOT NULL,
		visits          bigint(20)  NOT NULL,
		unique_visits   bigint(20)  NOT NULL,
		referrals       bigint(20)  NOT NULL,
		conversion_rate float(2)    NOT NULL,
		hash            varchar(32) NOT NULL,
		rest_id         mediumtext  NOT NULL,
		PRIMARY KEY (campaign_id),
		KEY affiliate_id (affiliate_id),
    KEY hash (hash)
		) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}
}
