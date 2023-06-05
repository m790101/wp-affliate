<?php
/**
 * Coupons Database Abstraction Layer
 *
 * @package     AffiliateWP
 * @subpackage  Database
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.3
 */

/**
 * Coupons database class.
 *
 * @since 2.6
 *
 * @see Affiliate_WP_DB
 */
class Affiliate_WP_Coupons_DB extends Affiliate_WP_DB {

	/**
	 * Cache group for queries.
	 *
	 * @internal DO NOT change. This is used externally both as a cache group and shortcut
	 *           for accessing db class instances via affiliate_wp()->{$cache_group}->*.
	 *
	 * @since 2.6
	 * @var   string
	 */
	public $cache_group = 'coupons';

	/**
	 * Database group value.
	 *
	 * @since 2.6
	 * @var string
	 */
	public $db_group = 'affiliates:coupons';

	/**
	 * Object type to query for.
	 *
	 * @since 2.6
	 * @var   string
	 */
	public $query_object_type = 'AffWP\Affiliate\Coupon';

	/**
	 * Container for storing all tags
	 *
	 * @since 2.8
	 */
	private $tags;

	/**
	 * Coupon used to set up the coupon format.
	 *
	 * @since 2.8
	 */
	public $coupon;

	/**
	 * Get things started.
	 *
	 * @since 2.6
	*/
	public function __construct() {
		global $wpdb, $wp_version;

		if ( defined( 'AFFILIATE_WP_NETWORK_WIDE' ) && AFFILIATE_WP_NETWORK_WIDE ) {
			// Allows a single coupons table for the whole network.
			$this->table_name  = 'affiliate_wp_coupons';
		} else {
			$this->table_name  = $wpdb->prefix . 'affiliate_wp_coupons';
		}
		$this->primary_key = 'coupon_id';
		$this->version     = '1.3';
	}

	/**
	 * Retrieves a coupon record.
	 *
	 * @since 2.6
	 *
	 * @see Affiliate_WP_DB::get_core_object()
	 *
	 * @param int|AffWP\Affiliate\Coupon $coupon Coupon ID or object.
	 * @return AffWP\Affiliate\Coupon|false Coupon object, otherwise false.
	 */
	public function get_object( $coupon ) {
		return $this->get_core_object( $coupon, $this->query_object_type );
	}

	/**
	 * Defines the database columns and their default formats.
	 *
	 * @since 2.6
	*/
	public function get_columns() {
		return array(
			'coupon_id'    => '%d',
			'affiliate_id' => '%d',
			'coupon_code'  => '%s',
			'type'         => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @access public
	 * @since  2.6
	 */
	public function get_column_defaults() {
		return array(
			'affiliate_id' => 0,
		);
	}

	/**
	 * Retrieves coupons from the database.
	 *
	 * @since 2.6
	 * @since 2.8 Added the `$type` and `$no_type_only` arguments.
	 *
	 * @param array $args {
	 *     Optional. Arguments for querying coupons. Default empty array.
	 *
	 *     @type int          $number       Number of coupons to query for. Default 20.
	 *     @type int          $offset       Number of coupons to offset the query for. Default 0.
	 *     @type int|array    $coupon_id    Coupon ID or array of IDs to explicitly retrieve. Default 0 (all).
	 *     @type int|array    $affiliate_id Affiliate ID or array of IDs to explicitly retrieve. Default empty.
	 *     @type string|array $coupon_code  Coupon code or array of coupon codes to explicitly retrieve. Default empty.
	 *     @type string|array $type         Coupon type, array of types, or empty for all. Default empty.
	 *     @type bool         $no_type_only Whether to only query for coupons with no type. Default true.
	 *     @type string       $order        How to order returned results. Accepts 'ASC' or 'DESC'. Default 'DESC'.
	 *     @type string       $orderby      Coupons table column to order results by. Accepts any AffWP\Affiliate\Coupon
	 *                                      field. Default 'affiliate_id'.
	 *     @type string|array $fields       Specific fields to retrieve. Accepts 'ids', a single coupon field, or an
	 *                                      array of fields. Default '*' (all).
	 * }
	 * @param bool $count Whether to retrieve only the total number of results found. Default false.
	 * @return array|int Array of coupon objects or field(s) (if found), int if `$count` is true.
	 */
	public function get_coupons( $args = array(), $count = false ) {
		global $wpdb;

		$defaults = array(
			'number'       => 20,
			'offset'       => 0,
			'coupon_id'    => 0,
			'affiliate_id' => 0,
			'coupon_code'  => 0,
			'type'         => '',
			'no_type_only' => true,
			'orderby'      => $this->primary_key,
			'order'        => 'ASC',
			'fields'       => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		// Ignore no_type_only if querying for specific type or types.
		if ( ! empty( $args['type'] ) ) {
			$args['no_type_only'] = false;
		}

		$where = $join = '';

		// Specific coupons.
		if( ! empty( $args['coupon_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['coupon_id'] ) ) {
				$coupon_ids = implode( ',', array_map( 'intval', $args['coupon_id'] ) );
			} else {
				$coupon_ids = intval( $args['coupon_id'] );
			}

			$where .= "`coupon_id` IN( {$coupon_ids} ) ";

		}

		// Specific affiliates.
		if ( ! empty( $args['affiliate_id'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( is_array( $args['affiliate_id'] ) ) {
				$affiliate_ids = implode( ',', array_map( 'intval', $args['affiliate_id'] ) );
			} else {
				$affiliate_ids = intval( $args['affiliate_id'] );
			}

			$where .= "`affiliate_id` IN( {$affiliate_ids} ) ";
		}

		// Specific coupon code or codes.
		if ( ! empty( $args['coupon_code'] ) ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if( is_array( $args['coupon_code'] ) ) {
				$where .= "`coupon_code` IN('" . implode( "','", array_map( 'affwp_sanitize_coupon_code', $args['coupon_code'] ) ) . "') ";
			} else {
				$coupons = affwp_sanitize_coupon_code( $args['coupon_code'] );
				$where .= "`coupon_code` = '" . $coupons . "' ";
			}
		}

		// Coupon types.
		if ( ! empty( $args['type'] ) || true === $args['no_type_only'] ) {

			$where .= empty( $where ) ? "WHERE " : "AND ";

			if ( true === $args['no_type_only'] ) {
				$args['type'] = '';
			}

			if ( is_array( $args['type'] ) ) {
				$where .= "`type` IN('" . implode( "','", array_map( 'sanitize_key', $args['type'] ) ) . "') ";
			} else {
				$type = sanitize_key( $args['type'] );
				$where .= "`type` = '" . $type . "' ";
			}
		}

		// Select valid coupons only.
		$where .= empty( $where ) ? "WHERE " : "AND ";
		$where .= "`$this->primary_key` != ''";

		// There can be only two orders.
		if ( 'ASC' === strtoupper( $args['order'] ) ) {
			$order = 'ASC';
		} else {
			$order = 'DESC';
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
			$fields = $this->parse_fields( $args['fields'] );

			if ( '*' === $fields ) {
				$callback = 'affwp_get_coupon';
			}
		}

		$key = ( true === $count ) ? md5( 'affwp_coupons_count' . serialize( $args ) ) : md5( 'affwp_coupons_' . serialize( $args ) );

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
	 * Return the number of results found for a given query.
	 *
	 * @since 2.6
	 *
	 * @param array $args Query arguments.
	 * @return int Number of results matching the query.
	 */
	public function count( $args = array() ) {
		return $this->get_coupons( $args, true );
	}

	/**
	 * Adds a new coupon.
	 *
	 * @since 2.6
	 *
	 * @param array $data {
	 *     Optional. Data for adding a new affiliate coupon.
	 *
	 *     @type string $coupon_code  Coupon code to create. If left empty, a code will be auto-generated.
	 *     @type int    $affiliate_id Required. Affiliate ID to associate the coupon code with.
	 * }
	*/
	public function add( $data = array() ) {
		$defaults = array(
			'coupon_code'  => '',
			'affiliate_id' => 0,
			'type'         => '',
		);

		$args = wp_parse_args( $data, $defaults );
		$coupon_format = affiliate_wp()->settings->get( 'coupon_format' );

		// Bail if the coupon template is not set.
		$woocommerce_coupon_template = affiliate_wp()->settings->get( 'coupon_template_woocommerce', 0 );

		if ( empty( $woocommerce_coupon_template ) && ! defined( 'WP_TESTS_DOMAIN' ) ) {
			return false;
		}

		// Bail if the affiliate ID is invalid.
		if ( empty( $args['affiliate_id'] ) || false === affwp_get_affiliate( $args['affiliate_id'] ) ) {
			return false;
		}

		if ( empty( $args['coupon_code'] ) ) {
			$args['coupon_code'] = $this->generate_code( array( 'affiliate_id' => $args['affiliate_id'] ) );
		}

		// Use coupon format to set the coupon code.
		if ( false !== $coupon_format && ! empty( $coupon_format ) ) {
			// Make Affiliate ID, coupon code, and integration available for coupon parsing functions.
			$args['integration'] = 'coupon_template_woocommerce';

			$this->coupon = $args;

			$coupon_code = $this->parse_tags( $coupon_format );

			$coupon_code = affwp_sanitize_coupon_code( $coupon_code );

			// Only update if valid. Otherwise defaults to generated code.
			if ( true === affwp_validate_coupon_code( $coupon_code ) ) {
				$args['coupon_code'] = $coupon_code;
			}
		} else {
			$this->coupon = $args;
		}

		// Sanitize and store coupon code in caps.
		$args['coupon_code'] = affwp_sanitize_coupon_code( $args['coupon_code'] );

		$args['type'] = sanitize_key( $args['type'] );

		$added = $this->insert( $args, 'coupon' );

		if ( $added ) {
			/**
			 * Fires immediately after a coupon has been added to the database.
			 *
			 * @since 2.6
			 *
			 * @param array $added The coupon data being added.
			 */
			do_action( 'affwp_insert_coupon', $added );

			return $added;
		}

		return false;

	}

	/**
	 * Updates a coupon record in the database.
	 *
	 * @since 2.6
	 *
	 * @param int   $coupon_id ID for the coupon to update.
	 * @param array $data      Optional. Coupon data to update.
	 * @return bool True if the coupon was successfully updated, otherwise false.
	 */
	public function update_coupon( $coupon_id, $data = array() ) {
		if ( ! $coupon = affwp_get_coupon( $coupon_id ) ) {
			return false;
		}

		$args = array();

		if ( ! empty( $data['coupon_code'] ) ) {
			$args['coupon_code'] = affwp_sanitize_coupon_code( $data['coupon_code'] );
		}

		if ( ! empty( $data['type'] ) ) {
			$args['type'] = sanitize_key( $data['type'] );
		}

		$updated = parent::update( $coupon_id, $args, '', 'coupon' );

		/**
		 * Fires immediately after a coupon update has been attempted.
		 *
		 * @since 2.6
		 * @since 2.6.1 Hook tag fixed to remove inadvertent naming conflict
		 *
		 * @param \AffWP\Affiliate\Coupon $updated_coupon Updated coupon object.
		 * @param \AffWP\Affiliate\Coupon $coupon         Original coupon object.
		 * @param bool                    $updated        Whether the coupon was successfully updated.
		 */
		do_action( 'affwp_updated_coupon', affwp_get_coupon( $coupon_id ), $coupon, $updated );

		return $updated;
	}

	/**
	 * Retrieves a coupon row based on column and value.
	 *
	 * @since 2.6
	 * @since 2.6.1 Renamed the `$row_id` parameter to `$value`.
	 *
	 * @param  string $column Column name. See get_columns().
	 * @param  mixed  $value  Column value.
	 * @return object|null Database query result object or null on failure.
	 */
	public function get_by( $column, $value ) {
		if ( 'coupon_code' === $column ) {
			$value = strtoupper( $value );
		}

		return parent::get_by( $column, $value );
	}

	/**
	 * Generates a "random" coupon code.
	 *
	 * @since 2.6
	 *
	 * @param array $args {
	 *     Optional. Arguments for modifying the generated coupon code.
	 *
	 *     @type int $affiliate_id Affiliate ID to generate the code for. Default 0 (unused).
	 * }
	 * @return string "Random" coupon code.
	 */
	public function generate_code( $args = array() ) {
		$defaults = array(
			'affiliate_id' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$total_length = 10;

		// Generate the actual code.
		$code = wp_generate_password( $total_length, false );

		// Finished coupon code.
		$code = affwp_sanitize_coupon_code( $code );

		/**
		 * Filters the generated affiliate coupon code.
		 *
		 * @since 2.6
		 *
		 * @param string $code Generated coupon code.
		 * @param array  $args Arguments for modifying the generated coupon code.
		 */
		return apply_filters( 'affwp_coupons_generate_code', $code, $args );
	}

	/**
	 * Routine that creates the coupons table.
	 *
	 * @since 2.6
	 * @since 2.8 The coupon_code column was increased from 50 to 191 characters.
	 */
	public function create_table() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE {$this->table_name} (
			coupon_id    bigint(20)   NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20)   NOT NULL,
			coupon_code  varchar(191) NOT NULL,
			type         tinytext     NOT NULL,
			PRIMARY KEY (coupon_id),
			KEY coupon_code (coupon_code)
			) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

	/**
	 * Retrieves all registered coupon merge tags.
	 *
	 * @since 2.8
	 *	 
	 * @return array {
	 *     Coupon tags and their attributes
	 *
	 *     @type array Coupon tag slug {
	 *         @type string   $description Translatable description for what the coupon tag represents.
	 *         @type callable $function    Callback function for rendering the coupon tag.
	 *     }
	 * }
	 */
	public function get_tags() {

		// Setup default tags array.
		$coupon_tags = array(
			'coupon_code'   => array(
				'description' => __( 'The coupon code.', 'affiliate-wp' ),
				'function'    => 'affwp_coupon_tag_coupon_code',
			),
			'coupon_amount' => array(
				'description' => __( 'The coupon&#8217;s amount.', 'affiliate-wp' ),
				'function'    => 'affwp_coupon_tag_coupon_amount',
			),
			'user_name'     => array(
				'description' => __( 'The affiliate&#8217;s WordPress username.', 'affiliate-wp' ),
				'function'    => 'affwp_coupon_tag_user_name',
			),
			'first_name'    => array(
				'description' => __( 'The affiliate&#8217;s first name.', 'affiliate-wp' ),
				'function'    => 'affwp_coupon_tag_first_name',
			),
			'custom_text'   => array(
				'description' => __( 'Custom text', 'affiliate-wp', 'affiliate-wp' ),
				'function'    => 'affwp_coupon_tag_custom_text',
			),
		);

		return $coupon_tags;
	}

	/**
	 * Sets up all registered coupon tags.
	 *
	 * @since 2.8
	 *
	 * @return void
	 */
	private function setup_coupon_tags() {

		$tags = $this->get_tags();

		foreach ( $tags as $tag => $atts ) {
			if ( isset( $atts['function'] ) && is_callable( $atts['function'] ) ) {
				$this->tags[ $tag ] = $atts;
			}
		}

	}

	/**
	 * Searches the content for coupon tags and filter coupon tags through their hooks.
	 *
	 * @since 2.8
	 *
	 * @param string                          $content Content to search for coupon tags.
	 * @param null|AffWP\Affiliate\Coupon|int $coupon  Optional. Coupon ID or object. Default null (unused).
	 * @return string Filtered content.
	 */
	public function parse_tags( $content, $coupon = null ) {
		$this->setup_coupon_tags();

		if ( $coupon = affwp_get_coupon( $coupon ) ) {
			$this->coupon = $coupon->to_array();
		}

		$tags = $this->get_tags();

		// Make sure there's at least one tag.
		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return $content;
		}

		$new_content = preg_replace_callback( '/{([A-z0-9\-\_]+)}/s', array( $this, 'do_tag' ), $content );

		return $new_content;
	}

	/**
	 * Parses a specific tag.
	 *
	 * @since 2.8
	 *
	 * @param array $merge_tag Merge tag.
	 */
	private function do_tag( $merge_tag ) {
		// Get tag.
		$tag  = $merge_tag[1];
		$tags = $this->get_tags();

		// Return tag if not set.
		if ( ! $this->coupon_tag_exists( $tag ) ) {
			return $merge_tag[0];
		}

		return call_user_func( $tags[ $tag ]['function'], $this->coupon, $tag );
	}

	/**
	 * Checks if the given tag is registered.
	 *
	 * @since 2.8
	 *
	 * @param string $tag Coupon tag that will be searched.
	 * @return bool True if exists, false otherwise.
	 */
	public function coupon_tag_exists( $tag ) {
		return array_key_exists( $tag, $this->get_tags() );
	}
}
