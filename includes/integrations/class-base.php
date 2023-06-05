<?php
/**
 * Integrations: Integration Base Model
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Core superclass used as the basis for all integrations.
 *
 * @since 1.2
 * @abstract
 */
abstract class Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context;

	/**
	 * The ID of the referring affiliate
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $affiliate_id;

	/**
	 * Debug mode
	 *
	 * @access  public
	 * @since   1.8
	 */
	public $debug;

	/**
	 * Logging class object
	 *
	 * @access  public
	 * @since   1.8
	 * @deprecated 2.0.2
	 */
	public $logs;

	/**
	 * Referral type
	 *
	 * @access  public
	 * @since   2.2
	 */
	public $referral_type = 'sale';

	/**
	 * Customer email address.
	 *
	 * @access  public
	 * @since   1.8
	 * @deprecated 2.2
	 */
	public $email;

	/**
	 * A list of supported AffiliateWP features
	 *
	 * @access protected
	 * @since  2.5
	 *
	 * @var array
	 */
	protected $supported_features = array();

	/**
	 * Active integrations.
	 *
	 * @access private
	 * @since  2.5
	 * @var    array List of integrations that have been activated.
	 * @static
	 */
	private static $active_integrations = array();

	/**
	 * Runs the check necessary to confirm this plugin is active.
	 *
	 * Integration sub-classes must extend this method.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	public function plugin_is_active() {
		_doing_it_wrong( __METHOD__, 'This method should run a boolean check against the existence of a vital integration class or function to determine if the integration is active.', '2.5' );

		// Prior to 2.5, if the integration class was running, it was because it was active.
		return true;
	}

	/**
	 * Constructor
	 *
	 * @access  public
	 * @since   1.0
	 *
	 */
	public function __construct() {
		$this->affiliate_id = affiliate_wp()->tracking->get_affiliate_id();

		// Set debug mode, if this is a test.
		if ( $this->is_test() ) {
			// Keep $debug initialization for back-compat.
			$this->debug = affiliate_wp()->settings->get( 'debug_mode', false );
		}
	}

	/**
	 * Retrieves the registry attributes for this integration.
	 *
	 * @since 2.5
	 *
	 * @return array|WP_Error Array of registry attributes, or a WP_Error if the item is not registered.
	 */
	public function get_registry_attributes() {
		$attributes = affiliate_wp()->integrations->get_attributes( $this->context );

		if ( false === $attributes ) {
			return new \WP_Error(
				'item_not_registered',
				'This integration is not registered to the integration registry.',
				array( 'context' => $this->context )
			);
		} else {
			return $attributes;
		}
	}

	/**
	 * Retrieves the value of a single registry attribute.
	 *
	 * @since 2.5
	 *
	 * @param string $attribute The registry attribute to retrieve.
	 * @return mixed|WP_Error The registry attribute value, or a WP error if something went wrong.
	 */
	public function get_registry_attribute( $attribute ) {
		$attributes = $this->get_registry_attributes();

		// Bail early if the attributes returned an error.
		if ( is_wp_error( $attributes ) ) {
			return $attributes;
		}

		$attribute = (string) $attribute;

		if ( isset( $attributes[ $attribute ] ) ) {
			$result = $attributes[ $attribute ];
		} else {
			$result = new \WP_Error(
				'attribute_does_not_exist',
				'The specified attribute does not exist in this context',
				array( 'attributes' => $attributes )
			);
		}

		return $result;
	}

	/**
	 * Attempts to retrieve the name from the registry. Falls back to the context if the name could not be found.
	 *
	 * @since 2.5
	 *
	 * @return string The name of the current integration, or the context if the name could not be found.
	 */
	public function get_name() {
		$name = $this->get_registry_attribute( 'name' );

		return is_wp_error( $name ) ? $this->context : $name;
	}

	/**
	 * Activates the integration, if it has not already been activated.
	 *
	 * @since 2.5
	 * @return true|\WP_Error True if the integration was activated. WP_Error otherwise.
	 */
	public function activate() {

		// Bail early if the plugin is not active
		if ( ! $this->plugin_is_active() ) {
			return new \WP_Error(
				'plugin_is_not_active',
				'The plugin for this integration is not active.',
				array( 'context', $this->context )
			);
		}

		// Bail early if the plugin has already been enabled.
		if ( $this->is_active() ) {
			return new \WP_Error(
				'integration_already_enabled',
				'This integration is already enabled',
				array( 'context' => $this->context )
			);
		}

		// If this integration has not been activated, set it up.
		$this->init();
		self::$active_integrations[] = $this->context;

		return true;
	}

	/**
	 * Determines if the current integration sales data is out of sync.
	 *
	 * @since 2.5
	 * @return bool|WP_Error True if a sync needs to run, false if not. WP_Error if the integration does not support sales reporting.
	 */
	public function needs_synced() {
		$totals = $this->get_sales_referrals_counts();

		// Bubble the WP Error up the chain.
		if ( is_wp_error( $totals ) ) {
			return $totals;
		}

		return $totals['sales'] !== $totals['referrals'];
	}

	/**
	 * Retrieves the total sales and referrals counts for this integration.
	 *
	 * @since 2.5
	 *
	 * @param bool $force      Optional. Set to true to force-refresh the cache. Default false.
	 * @return array|\WP_Error Array with sales and referral counts, or a WP_Error if integration does not support sales
	 *                         reporting.
	 */
	public function get_sales_referrals_counts( $force = false ) {

		// Bail early if this integration does not support sales reporting.
		if ( ! $this->supports_feature( 'sales_reporting' ) ) {
			return new \WP_Error(
				'get_sales_referrals_invalid_integration',
				'This integration does not support sales reporting.',
				$this->context
			);
		}

		// If the cache is forced to refresh, don't bother getting the cache.
		if ( true !== $force ) {
			$result = wp_cache_get( affwp_get_sales_referrals_counts_cache_key( $this->context ), 'affwp_integrations' );
		} else {
			$result = array();
		}

		// If the cache is not set, of if force is true, get the counts and set it.
		if ( true === $force || ! is_array( $result ) || ! isset( $result['sales'] ) || ! isset( $result['referrals'] ) ) {
			$sales_count    = affiliate_wp()->referrals->sales->count( array( 'context' => $this->context ) );
			$referral_count = affiliate_wp()->referrals->count( array( 'context' => $this->context, 'type' => 'sale' ) );
			$result         = array( 'sales' => $sales_count, 'referrals' => $referral_count );

			/**
			 * Filters how often the sales/referrals counts cache should expire.
			 *
			 * @since 2.5
			 *
			 * @param int    $expires Time, in seconds in which the sales referrals counts should expire. Defaults to 1 day, in seconds.
			 * @param string $context The current integration context.
			 */
			$expires = apply_filters( "affwp_sales_referrals_counts_cache_expire", DAY_IN_SECONDS, $this->context );

			// Cache the total counts.
			wp_cache_set(
				affwp_get_sales_referrals_counts_cache_key( $this->context ),
				$result,
				'affwp_integrations',
				$expires
			);
		}

		return $result;
	}

	/**
	 * Checks to see if the specified feature is supported by this integration.
	 *
	 * @since 2.5
	 *
	 * @param string $feature The feature to check
	 * @return bool True if the feature is supported, false otherwise.
	 */
	public function supports_feature( $feature ) {
		return in_array( $feature, $this->get_registry_attribute( 'supports' ) );
	}

	/**
	 * Checks to see if the current integration is active.
	 *
	 * @since 2.5
	 *
	 * @return bool true if the integration is active, false otherwise.
	 */
	public function is_active() {
		return in_array( $this->context, self::$active_integrations );
	}

	/**
	 * Gets the total order count for this integration.
	 *
	 * @since 2.5
	 *
	 * @param string|array $date {
	 *     Optional. Date string or start/end range to retrieve orders for. Default empty.
	 *
	 *     @type string $start Start date to retrieve orders for.
	 *     @type string $end   End date to retrieve orders for.
	 * }
	 * @return \WP_Error|int WP_Error object by default. Potential integer if sub-classes extend the method.
	 */
	public function get_total_order_count( $date = '' ) {
		$integration_name = $this->get_name();

		return new \WP_Error( "The integration $integration_name does not support order tracking." );
	}

	/**
	 * Gets the total sales for this integration.
	 *
	 * @since 2.5
	 *
	 * @param string|array $date  {
	 *                            Optional. Date string or start/end range to retrieve orders for. Default empty.
	 *
	 * @type string        $start Start date to retrieve orders for.
	 * @type string        $end   End date to retrieve orders for.
	 * }
	 * @return \WP_Error|int WP_Error object by default. Potential integer if sub-classes extend the method.
	 */
	public function get_total_sales( $date = '' ) {
		$integration_name = $this->get_name();

		return new \WP_Error( "The integration $integration_name does not support sales tracking." );
	}

	/**
	 * Retrieves coupons of a given type for the current integration.
	 *
	 * @since 2.6
	 *
	 * @param string               $type         Coupon type.
	 * @param int|\AffWP\Affiliate $affiliate    Optional. Affiliate ID or object to retrieve coupons for.
	 *                                           Default null (ignored).
	 * @param bool                 $details_only Optional. Whether to retrieve the coupon details only (for display).
	 *                                           Default true. If false, the full coupon objects will be retrieved.
	 * @return array|\AffWP\Affiliate\Coupon[]|WP_Post[] An array of arrays of coupon details if `$details_only` is
	 *                                                   true or an array of coupon or post objects if false, depending
	 *                                                   on whether dynamic or manual coupons, otherwise an empty array.
	 */
	public function get_coupons_of_type( $type, $affiliate = null, $details_only = true ) {
		return array();
	}

	/**
	 * Retrieves manual coupon post IDs for integrations storing coupons in post types.
	 *
	 * @since 2.6
	 *
	 * @param string               $post_type   Post type to retrieve entries for.
	 * @param string               $post_status Coupons post status.
	 * @param int|\AffWP\Affiliate $affiliate Optional. Affiliate ID or object. Default null (unused).
	 */
	public function get_coupon_post_ids( $post_type, $post_status, $affiliate = null ) {
		global $wpdb;

		$affiliate = affwp_get_affiliate( $affiliate );

		if ( $affiliate ) {
			$query_args = array(
				'fields'      => 'ids',
				'numberposts' => -1,
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'meta_query'  => array(
					array(
						'relation' => 'OR',
						array(
							'key'   => 'affwp_discount_affiliate',
							'value' => $affiliate->ID,
						),
						array(
							'key'   => 'affwp_coupon_affiliate',
							'value' => $affiliate->ID,
						),
					),
				),
			);
		} else {
			$query_args = array(
				'fields'      => 'ids',
				'numberposts' => -1,
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'meta_query'  => array(
					array(
						'relation' => 'OR',
						array(
							'key'     => 'affwp_discount_affiliate',
							'compare' => 'EXISTS',
						),
						array(
							'key'     => 'affwp_coupon_affiliate',
							'compare' => 'EXISTS',
						),
					),
				),
			);
		}

		$post_ids = get_posts( $query_args );

		return $post_ids;
	}

	/**
	 * Gets the coupon templates for this integration.
	 *
	 * @since 2.6

	 * @return \WP_Error|array WP_Error object by default. Potential array if sub-classes extend the method.
	 */
	public function get_coupon_templates() {
		$integration_name = $this->get_name();

		return new \WP_Error( 'not_support_dynamic_coupons', "The integration {$integration_name} does not support dynamic coupons." );
	}

	/**
	 * Builds an array of coupon template options for display in settings.
	 *
	 * @since 2.6
	 *
	 * @return array Options array.
	 */
	public function get_coupon_templates_options() {
		return array();
	}

	/**
	 * Retrieves the details for a coupon.
	 *
	 * @since 2.6
	 *
	 * @param AffWP\Affiliate\Coupon $coupon Coupon object.
	 * @return array The coupon details.
	 */
	public function get_coupon_details( $coupon ) {
		return array();
	}

	/**
	 * Prepares a date range to be accepted by the current integration.
	 * Most integrations accept a date range in a specific format. Often this format differs from AffiliateWP.
	 * This method provides a way to convert an AffiliateWP date range into the integration date range.
	 *
	 * @since 2.5
	 *
	 * @param array|string $date_range The AffiliateWP date range, or an empty value.
	 * @return array The date range, formatted for the current integration.
	 */
	public function prepare_date_range( $date_range ) {
		$start_date = false;
		$end_date   = false;

		// If the date range is empty, set the defaults
		if ( ! empty( $date_range ) ) {

			// If this is an array, set the start and end dates individually.
			if ( is_array( $date_range ) ) {

				if ( isset( $date_range['start'] ) ) {
					$start_date = $date_range['start'];
				}
				if ( isset( $date_range['end'] ) ) {
					$end_date = $date_range['end'];
				}

				// Otherwise, set both start and end dates to the date range value.
			} else {
				$start_date = $end_date = (string) $date_range;
			}

			$start_date = date( 'Y-m-d H:i:s', strtotime( $start_date ) );
			$end_date   = date( 'Y-m-d H:i:s', strtotime( $end_date ) );

		}

		// Integrations treat default date ranges differently.
		// These two checks forces the date range to default to all-time values if no date was set.
		if ( false === $start_date ) {
			$start_date = date( 'Y-m-d H:i:s', 0 );
		}
		if ( false === $end_date ) {
			$end_date = current_time( 'mysql' );
		}

		return array( 'start' => $start_date, 'end' => $end_date );
	}
	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */
	public function init() {}

	/**
	 * Determines if the current session was referred through an affiliate link
	 *
	 * @access  public
	 * @since   1.0
	 * @return  bool
	 */
	public function was_referred() {
		return affiliate_wp()->tracking->was_referred();
	}

	/**
	 * Inserts a pending referral. Used when orders are initially created
	 *
	 * @since 1.0
	 *
	 * @param string $amount      Optional. The final referral commission amount. Default empty string.
	 * @param mixed  $reference   Optional. The reference column for the referral per the current context. Default 0.
	 * @param string $description Optional. A plaintext description of the referral. Default empty string.
	 * @param array  $products    Optional. An array of product details. Default empty. Default empty array.
	 * @param array  $data        {
	 *     Optional. Any custom data that can be passed to and stored with the referral. Default empty.
	 *
	 *     @type int  $affiliate_id The affiliate ID to award this referral.
	 *     @type bool $is_coupon_referral Set to true if this referral came from a coupon instead of a visit.
	 * }
	 * @return bool|int Returns the referral ID on success, false on failure.
	 */
	public function insert_pending_referral( $amount = '', $reference = 0, $description = '', $products = array(), $data = array() ) {

		// get affiliate ID
		$this->affiliate_id = isset( $data['affiliate_id'] ) ? $data['affiliate_id'] : $this->get_affiliate_id( $reference, $this->context );

		/**
		 * Filters whether to allow referrals to be created for the current integration.
		 *
		 * @since 1.0
		 *
		 * @param bool $allow Whether to allow referrals to be created.
		 * @param array $args Many of the arguments for generating the referral.
		 */
		if ( ! (bool) apply_filters( 'affwp_integration_create_referral', true, array( 'affiliate_id' => $this->affiliate_id, 'amount' => $amount, 'reference' => $reference, 'description' => $description, 'products' => $products, 'data' => $data ) ) ) {

			$this->log( 'Referral not created because integration is disabled via filter' );

			return false; // Allow extensions to prevent referrals from being created
		}

		if ( ! affiliate_wp()->tracking->is_valid_affiliate( $this->affiliate_id ) ) {
			$this->log( sprintf( 'Pending referral not created. Affiliate ID %d is either referring themselves, or their status is not set to active.', $this->affiliate_id ) );

			return false; // Referral is invalid
		}

		$referral = affwp_get_referral_by( 'reference', $reference, $this->context );

		if ( ! is_wp_error( $referral ) ) {

			$this->log( sprintf( 'Referral for Reference %s already created', $reference ) );

			return false; // Referral already created for this reference
		}

		if ( empty( $amount ) && affiliate_wp()->settings->get( 'ignore_zero_referrals' ) ) {

			$this->log( 'Referral not created due to 0.00 amount.' );

			return false; // Ignore a zero amount referral
		}

		// If this referral was generated from a coupon, ignore the visit ID.
		$is_coupon_referral = isset( $data['is_coupon_referral'] ) && false !== $data['is_coupon_referral'];
		$visit_id           = false === $is_coupon_referral ? affiliate_wp()->tracking->get_visit_id() : false;

		// Allow overriding of ID through custom data.
		$visit_id = isset( $data['visit_id'] )
			? intval( $data['visit_id'] )
			: $visit_id;

		if ( false !== $visit_id && ! affwp_validate_visit_id( $visit_id ) ) {
			$this->log( sprintf( 'Referral not created due to invalid visit ID value, %d.', $visit_id ) );

			return false; // Ignore a referral with an invalid visit ID
		}

		$args = array(
			'amount'       => $amount,
			'reference'    => $reference,
			'description'  => ! empty( $description ) ? $description : '',
			'campaign'     => affiliate_wp()->tracking->get_campaign(),
			'affiliate_id' => $this->affiliate_id,
			'visit_id'     => $visit_id,
			'order_total'  => $this->get_order_total( $reference ),
			'products'     => ! empty( $products ) ? maybe_serialize( $products ) : '',
			'custom'       => ! empty( $data ) ? maybe_serialize( $data ) : '',
			'type'         => $this->referral_type,
			'context'      => $this->context,
			'customer'     => $this->get_customer( $reference )
		);

		if ( affiliate_wp()->settings->get( 'disable_ip_logging' ) ) {
			$args['customer']['ip'] = '';
		}

		$this->log( 'Arguments being sent to DB:', $args );

		/**
		 * Filters the arguments used to insert a pending referral.
		 *
		 * @since 1.0
		 *
		 * @param array  $args         Arguments sent to referrals->add() to insert a pending referral.
		 * @param float  $amount       Calculated referral amount.
		 * @param string $reference    Referral reference (usually the order or entry number).
		 * @param string $description  Referral description.
		 * @param int    $affiliate_id Affiliate ID.
		 * @param int    $visit_id     Visit ID.
		 * @param array  $data         Data originally sent to insert the referral.
		 * @param string $context      Context for creating the referral (typically the integration slug).
		 */
		$args = apply_filters( 'affwp_insert_pending_referral', $args, $amount, $reference, $description, $this->affiliate_id, $visit_id, $data, $this->context );

		if( ! empty( $args['customer'] ) && empty( $args['customer']['affiliate_id'] ) ) {
			$args['customer']['affiliate_id'] = $this->affiliate_id;
		}

		$referral_id = affiliate_wp()->referrals->add( $args );

		if ( $referral_id ) {
			$this->log( sprintf( 'Pending Referral #%d created successfully.', $referral_id ) );
		} else {
			$this->log( 'Pending referral could not be created due to an error.' );
		}

		return $referral_id;

	}

	/**
	 * Inserts a draft referral.
	 *
	 * @since 2.8
	 *
	 * @param string $affiliate_id       The affiliate ID to award this referral.
	 * @param array  $data        {
	 *     Optional. Any custom data that can be passed to and stored with the referral. Default empty.
	 *
	 *     @type string $reference          The reference column for the referral per the current context.
	 *     @type string $description        A plaintext description of the referral. Default
	 *     @type int    $visit_id           Visit ID.
	 *     @type bool   $is_coupon_referral Set to true if this referral came from a coupon instead of a visit.
	 * }
	 * @return bool|int Returns the referral ID on success, false on failure.
	 */
	public function insert_draft_referral( $affiliate_id, $data = array() ) {
		// get affiliate ID.
		$reference          = isset( $data['reference'] ) ? $data['reference'] : '';
		$this->affiliate_id = ! empty( $affiliate_id ) ? $affiliate_id : $this->get_affiliate_id( $reference, $this->context );

		// Check if reference already exists.
		$referral_by_reference = affwp_get_referral_by( 'reference', $reference, $this->context );

		// get visit ID.
		$visit_id           = isset( $data['visit_id'] ) ? $data['visit_id'] : false;
		$is_coupon_referral = isset( $data['is_coupon_referral'] ) ? $data['is_coupon_referral'] : false;
		if ( true === $is_coupon_referral ) {
			$visit_id = false;
		} elseif ( false === $visit_id ) {
			$visit_id = affiliate_wp()->tracking->get_visit_id();
		}

		// create draft referral.
		$args = array(
			'status'       => 'draft',
			'reference'    => $reference,
			'description'  => ! empty( $data['description'] ) ? $data['description'] : '',
			'campaign'     => affiliate_wp()->tracking->get_campaign(),
			'affiliate_id' => $this->affiliate_id,
			'visit_id'     => $visit_id,
			'type'         => $this->referral_type,
			'context'      => $this->context,
			'customer'     => $this->get_customer( $reference ),
		);

		// customer IP.
		if ( affiliate_wp()->settings->get( 'disable_ip_logging' ) ) {
			$args['customer']['ip'] = '';
		}

		// Log referrals->add() args.
		affiliate_wp()->utils->log( 'Arguments sent to the DB when creating the draft referral:', $args );

		$referral_id = affiliate_wp()->referrals->add( $args );

		if ( $referral_id ) {
			affiliate_wp()->utils->log( sprintf( 'Draft Referral #%d created successfully.', $referral_id ) );
		} else {
			affiliate_wp()->utils->log( 'Draft referral could not be created due to an error.' );
			return false;
		}

		// Check if valid affiliate.
		if ( ! affiliate_wp()->tracking->is_valid_affiliate( $this->affiliate_id ) ) {
			affiliate_wp()->utils->log( sprintf( 'Draft referral could not be converted to pending. Affiliate ID %d is either referring themselves, or their status is not set to active.', $this->affiliate_id ) );
			$this->mark_referral_failed( $referral_id );

			return false; // Referral is invalid.
		}

		// Check if reference already existed.
		if ( ! is_wp_error( $referral_by_reference ) ) {
			affiliate_wp()->utils->log( sprintf( 'Referral for Reference %s already created', $reference ) );
			$this->mark_referral_failed( $referral_id );

			return false; // Referral already created for this reference.
		}

		// Check if valid visit id.
		if ( false === $is_coupon_referral && false !== $visit_id && ! affwp_validate_visit_id( $visit_id ) ) {
			affiliate_wp()->utils->log( sprintf( 'Draft referral could not be converted to pending due to invalid visit ID value, %d.', $visit_id ) );
			$this->mark_referral_failed( $referral_id );

			return false; // Ignore a referral with an invalid visit ID.
		}

		return $referral_id;
	}

	/**
	 * Marks a referral failed.
	 *
	 * @since 2.8
	 * @since 2.8.1 Refactored to transition referrals to the 'failed' status instead of 'rejected'.
	 *
	 * @param int $referral_id The referral ID.
	 * @return bool Whether the referral was marked failed.
	 */
	public function mark_referral_failed( $referral_id ) {
		$referral = affwp_get_referral( $referral_id );
		$failed   = $this->fail_referral( $referral );

		if ( $failed ) {
			affiliate_wp()->utils->log( sprintf( 'Referral #%d marked as failed.', $referral_id ) );
		}

		return $failed;
	}

	/**
	 * Hydrates a draft referral.
	 *
	 * Completes a referral with the missing data.
	 *
	 * @since 2.8
	 *
	 * @param string $referral_id The affiliate ID to award this referral.
	 * @param array  $data        {
	 *     Optional. Any custom data that can be passed to and stored with the referral. Default empty.
	 * }
	 * @return bool|int Returns the referral ID on success, false on failure.
	 */
	public function hydrate_referral( $referral_id, $data ) {
		// get referral current data.
		$referral = affwp_get_referral( $referral_id );

		$amount      = isset( $data['amount'] ) ? $data['amount'] : $referral->amount;
		$reference   = isset( $data['reference'] ) ? $data['reference'] : $referral->reference;
		$description = isset( $data['description'] ) ? $data['description'] : $referral->description;
		$products    = isset( $data['products'] ) ? $data['products'] : $referral->products;
		$visit_id    = isset( $data['visit_id'] ) ? $data['visit_id'] : $referral->visit_id;

		$data['custom'] = isset( $data['custom'] ) ? maybe_serialize( $data['custom'] ) : $referral->custom;

		$referral_args = array(
			'affiliate_id' => $this->affiliate_id,
			'amount'       => $amount,
			'reference'    => $reference,
			'description'  => $description,
			'products'     => $products,
			'data'         => $data
		);

		/**
		 * Filters whether to allow referrals to be hydrated for the current integration.
		 *
		 * @since 1.0
		 * @since 2.8 Moved the affwp_integration_create_referral hook from insert_pending_referral
		 *            function to here so that it's still possible to bypass the referral "creation".
		 *
		 * @param bool  $allow         Whether to allow referrals to be created.
		 * @param array $referral_args Many of the arguments for generating the referral.
		 */
		if ( ! (bool) apply_filters( 'affwp_integration_create_referral', true, $referral_args ) ) {

			affiliate_wp()->utils->log( 'Referral not hydrated because integration is disabled via filter.' );

			$this->mark_referral_failed( $referral_id );

			return false; // Allow extensions to prevent referrals from being created.
		}

		/**
		 * Filters the arguments used to hydrate the referral.
		 *
		 * @since 1.0
		 * @since 2.8 Moved the affwp_insert_pending_referral hook from insert_pending_referral
		 *            function to here so that it's still possible to filter the referral arguments.
		 *
		 * @param array  $data         Arguments sent to update_referral() to update a draft referral to pending.
		 * @param float  $amount       Calculated referral amount.
		 * @param string $reference    Referral reference (usually the order or entry number).
		 * @param string $description  Referral description.
		 * @param int    $affiliate_id Affiliate ID.
		 * @param int    $visit_id     Visit ID.
		 * @param array  $data         Data originally sent to insert the referral.
		 * @param string $context      Context for creating the referral (typically the integration slug).
		 */
		$data = apply_filters( 'affwp_insert_pending_referral', $data, $amount, $reference, $description, $this->affiliate_id, $visit_id, $data, $this->context );

		// Log referrals->update_referral() args.
		affiliate_wp()->utils->log( 'Arguments sent to the DB during referral hydration:', $data );

		$success = affiliate_wp()->referrals->update_referral( $referral_id, $data );

		if ( $success ) {
			affiliate_wp()->utils->log( sprintf( 'Referral #%d hydrated successfully.', $referral_id ) );
		} else {
			affiliate_wp()->utils->log( 'Referral could not be hydrated due to an error.' );
		}

		return $success;
	}

	/**
	 * Completes a referral. Used when orders are marked as completed
	 *
	 * @access  public
	 * @since   1.0
	 * @param   $reference|$referral The reference column for the referral to complete per the current context or a complete referral object
	 * @return  bool
	 */
	public function complete_referral( $reference_or_referral = 0 ) {

		if ( empty( $reference_or_referral ) ) {

			$this->log( 'Empty $reference_or_referral parameter given during complete_referral()' );

			return false;
		}

		if( is_object( $reference_or_referral ) ) {

			$referral = affwp_get_referral( $reference_or_referral );

			if ( empty( $referral ) ) {

				$this->log( 'Referral could not be retrieved during complete_referral(). Value given: ' . print_r( $reference_or_referral, true ) );

				return false;
			}

		} else {

			$referral = affwp_get_referral_by( 'reference', $reference_or_referral, $this->context );

			if ( is_wp_error( $referral ) ) {
				// Bail: This is a non-referral sale.
				$this->log( 'Referral could not be retrieved by reference during complete_referral(). Reference value given: ' . print_r( $reference_or_referral, true ) );
				return false;
			}
		}

		$this->log( 'Referral retrieved successfully during complete_referral()' );

		// Check if referral has failed.
		$has_failed = affwp_get_referral_meta( $referral->referral_id, 'referral_has_failed', true );
		if ( $has_failed ) {
			affiliate_wp()->utils->log( 'Referral not marked as complete because it has failed before.' );
			// This referral has failed.
			return false;
		}

		if ( is_object( $referral ) && $referral->status != 'pending' && $referral->status != 'rejected' ) {
			// This referral has already been completed, or paid
			return false;
		}

		/**
		 * Filters whether to allows referrals to be auto-completed.
		 *
		 * @since 1.0
		 * @since 2.7.1 Added the `$referral` parameter.
		 *
		 * @param bool            $allow    Whether to allow referrals to be auto-completed.
		 * @param \AffWP\Referral $referral The current referral object.
		 */
		if ( ! apply_filters( 'affwp_auto_complete_referral', true, $referral ) ) {

			$this->log( 'Referral not marked as complete because of affwp_auto_complete_referral filter' );

			return false;
		}

		if ( affwp_set_referral_status( $referral->referral_id, 'unpaid' ) ) {

			/**
			 * Fires when completing a referral.
			 *
			 * @since 1.0
			 *
			 * @param int             $referral_id The referral ID.
			 * @param \AffWP\Referral $referral    The referral object.
			 * @param string          $reference   The referral reference.
			 */
			do_action( 'affwp_complete_referral', $referral->referral_id, $referral, $referral->reference );

			$this->log( sprintf( 'Referral #%d set to Unpaid successfully', $referral->referral_id ) );

			return true;
		}

		$this->log( sprintf( 'Referral #%d failed to be set to Unpaid', $referral->referral_id ) );

		return false;

	}

	/**
	 * Rejects a referral. Used when orders are refunded, deleted, or voided
	 *
	 * @since 1.0
	 * @since 2.3.2 Added an optional `$reject_pending` parameter
	 *
	 * @param string|\AffWP\Referral $reference_or_referral The reference column for the referral to complete
	 *                                                      per the current context or a complete referral object.
	 * @param bool                   $reject_pending        Optional. Whether to allow pending referrals to be rejected.
	 *                                                      Default false.
	 * @return bool Whether the referral was successfully rejected.
	 */
	public function reject_referral( $reference_or_referral = 0, $reject_pending = false ) {

		if ( empty( $reference_or_referral ) ) {

			$this->log( 'Empty $reference_or_referral parameter given during reject_referral()' );

			return false;
		}

		if( is_object( $reference_or_referral ) ) {

			$referral = affwp_get_referral( $reference_or_referral );

		} else {

			$referral = affwp_get_referral_by( 'reference', $reference_or_referral, $this->context );

		}

		if ( empty( $referral ) || is_wp_error( $referral ) ) {

			$this->log( 'Referral could not be retrieved during reject_referral(). Value given: ' . print_r( $reference_or_referral, true ) );

			return false;
		}

		$this->log( 'Referral retrieved successfully during reject_referral()' );

		if ( is_object( $referral ) && 'paid' === $referral->status ) {
			// This referral has already been paid so it cannot be rejected
			$this->log( sprintf( 'Referral #%d not Rejected because it is already paid', $referral->referral_id ) );
			return false;
		}

		if ( is_object( $referral ) && 'pending' === $referral->status && false === $reject_pending ) {
			// This referral is pending so it cannot be rejected
			$this->log( sprintf( 'Referral #%d not Rejected because it is pending', $referral->referral_id ) );
			return false;
		}

		if ( affwp_set_referral_status( $referral->referral_id, 'rejected' ) ) {

			$this->log( sprintf( 'Referral #%d set to Rejected successfully', $referral->referral_id ) );

			return true;

		}

		$this->log( sprintf( 'Referral #%d failed to be set to Rejected', $referral->referral_id ) );

		return false;

	}

	/**
	 * Fails a referral.
	 *
	 * Used when a draft referral has disqualifying factors preventing it from becoming pending.
	 *
	 * @since 2.8.1
	 *
	 * @param string|\AffWP\Referral $reference_or_referral Referral object or reference value.
	 * @return bool Whether the referral was successfully rejected.
	 */
	public function fail_referral( $reference_or_referral ) {

		if ( empty( $reference_or_referral ) ) {

			$this->log( 'Empty $reference_or_referral parameter given during fail_referral()' );

			return false;
		}

		if( is_object( $reference_or_referral ) ) {

			$referral = affwp_get_referral( $reference_or_referral );

		} else {

			$referral = affwp_get_referral_by( 'reference', $reference_or_referral, $this->context );

		}

		if ( empty( $referral ) || is_wp_error( $referral ) ) {

			$this->log( 'Referral could not be retrieved during fail_referral(). Value given: ' . print_r( $reference_or_referral, true ) );

			return false;
		}

		$this->log( 'Referral retrieved successfully during fail_referral()' );

		if ( is_object( $referral ) && 'draft' !== $referral->status ) {
			// This referral must be a draft to fail.
			$this->log( sprintf( 'Referral #%d not failed because it is not a draft.', $referral->referral_id ) );
			return false;
		}

		if ( affwp_set_referral_status( $referral->referral_id, 'failed' ) ) {

			$this->log( sprintf( 'Referral #%d set to Failed successfully', $referral->referral_id ) );

			return true;

		}

		$this->log( sprintf( 'Referral #%d could not be set to Failed', $referral->referral_id ) );

		return false;
	}

	/**
	 * Retrieves the ID of the referring affiliate.
	 *
	 * @since 1.0
	 *
	 * @param int $reference Optional. The referral reference. Default 0.
	 * @return int Affiliate ID for the referral.
	 */
	public function get_affiliate_id( $reference = 0 ) {
		/**
		 * Filters the ID of the referring affiliate.
		 *
		 * @since 1.0
		 *
		 * @param int    $affiliate_id Affiliate ID.
		 * @param string $reference    Referral reference (typically the order or entry number).
		 * @param string $context      Referral context (typically the integration slug).
		 */
		return absint( apply_filters( 'affwp_get_referring_affiliate_id', $this->affiliate_id, $reference, $this->context ) );
	}

	/**
	 * Retrieves the email address of the referring affiliate
	 *
	 * @access  public
	 * @since   1.0
	 * @return  string
	 */
	public function get_affiliate_email() {
		return affwp_get_affiliate_email( $this->get_affiliate_id() );
	}

	/**
	 * Determine if the passed email belongs to the affiliate
	 *
	 * Checks a given email address against the referring affiliate's
	 * user email and payment email addresses to prevent customers from
	 * referring themselves.
	 *
	 * @access  public
	 * @since   1.6
	 * @param   string $email
	 * @return  bool
	 */
	public function is_affiliate_email( $email, $affiliate_id = 0 ) {

		$is_affiliate_email = false;

		// allow an affiliate ID to be passed in
		if( empty( $affiliate_id ) ) {
			$affiliate_id = $this->get_affiliate_id();
		}

		// Get affiliate emails
		$user_email  = affwp_get_affiliate_email( $affiliate_id );

		$payment_email = affwp_get_affiliate_payment_email( $affiliate_id );

		// True if the email is valid and matches affiliate user email or payment email, otherwise false
		$is_affiliate_email = ( is_email( $email ) && ( $user_email === $email || $payment_email === $email ) );

		/**
		 * Filters whether the passed email belongs the affiliate.
		 *
		 * @since 1.6
		 *
		 * @param bool   $is_affiliate_email Whether the email is the affiliate email.
		 * @param string $email              Email address.
		 * @param int    $affiliate_id       Affiliate ID.
		 */
		return (bool) apply_filters( 'affwp_is_customer_email_affiliate_email', $is_affiliate_email, $email, $affiliate_id );

	}

	/**
	 * Retrieves the rate and type for a specific product
	 *
	 * @since 1.2
	 * @access public
	 *
	 * @param string $base_amount      Optional. Base amount to calculate the referral amount from.
	 *                                 Default empty.
	 * @param string|int $reference    Optional. Referral reference (usually the order ID). Default empty.
	 * @param int        $product_id   Optional. Product ID. Default 0.
	 * @param int        $affiliate_id Optional. Affiliate ID.
	 * @return string Referral amount.
	 */
	public function calculate_referral_amount( $base_amount = '', $reference = '', $product_id = 0, $affiliate_id = 0, $category_id = 0 ) {

		// the affiliate ID can be optionally passed in to override the referral amount
		$affiliate_id = ! empty( $affiliate_id ) ? $affiliate_id : $this->get_affiliate_id( $reference );

		$rate = '';

		if ( ! empty( $category_id ) ) {

			$get_rate = $this->get_category_rate( $category_id, $args = array( 'reference' => $reference, 'affiliate_id' => $affiliate_id ) );

			if ( is_numeric( $get_rate ) ) {
				$rate = $get_rate;
			}

		}

		if ( ! empty( $product_id ) ) {

			$get_rate = $this->get_product_rate( $product_id, $args = array( 'reference' => $reference, 'affiliate_id' => $affiliate_id ) );

			if ( is_numeric( $get_rate ) ) {
				$rate = $get_rate;
			}

		}

		$amount = affwp_calc_referral_amount( $base_amount, $affiliate_id, $reference, $rate, $product_id, $this->context );

		return $amount;

	}

	/**
	 * Retrieves the rate and type for a specific category.
	 *
	 * @access  public
	 * @since   2.2
	 * @return  float
	*/
	public function get_category_rate( $category_id = 0, $args = array() ) {

		$args = wp_parse_args( $args, array(
			'reference'   => '',
			'affiliate_id' => 0
		) );

		$affiliate_id = isset( $args['affiliate_id'] ) ? $args['affiliate_id'] : $this->get_affiliate_id( $args['reference'] );

		$rate = get_term_meta( $category_id, '_affwp_' . $this->context . '_category_rate', true );

		/**
		 * Filters the integration category rate.
		 *
		 * @since 2.2
		 *
		 * @param float  $rate         Category-level referral rate.
		 * @param int    $category_id  Category ID.
		 * @param array  $args         Arguments for retrieving the category rate.
		 * @param int    $affiliate_id Affiliate ID.
		 * @param string $context      Order context.
		 */
		$rate = apply_filters( 'affwp_get_category_rate', $rate, $category_id, $args, $affiliate_id, $this->context );

		$rate = affwp_sanitize_referral_rate( $rate );

		return $rate;
	}

	/**
	 * Retrieves the rate and type for a specific product
	 *
	 * @access  public
	 * @since   1.2
	 * @return  float
	*/
	public function get_product_rate( $product_id = 0, $args = array() ) {

		$args = wp_parse_args( $args, array(
			'reference'    => '',
			'affiliate_id' => 0
		) );

		$affiliate_id = isset( $args['affiliate_id'] ) ? $args['affiliate_id'] : $this->get_affiliate_id( $args['reference'] );

		$rate = get_post_meta( $product_id, '_affwp_' . $this->context . '_product_rate', true );

		/**
		 * Filters the integration product rate.
		 *
		 * @since 1.2
		 *
		 * @param float  $rate         Product-level referral rate.
		 * @param int    $product_id   Product ID.
		 * @param array  $args         Arguments for retrieving the product rate.
		 * @param int    $affiliate_id Affiliate ID.
		 * @param string $context      Order context.
		 */
		$rate = apply_filters( 'affwp_get_product_rate', $rate, $product_id, $args, $affiliate_id, $this->context );

		$rate = affwp_sanitize_referral_rate( $rate );

		return $rate;
	}

	/**
	 * Retrieves the product details array for the referral
	 *
	 * @access  public
	 * @since   1.6
	 * @return  array
	*/
	public function get_products( $order_id = 0 ) {
		return array();
	}

	/**
	 * Retrieves the order total from the order.
	 *
	 * @access public
	 * @since 2.5
	 *
	 * @return float The order total for the current integration.
	 */
	public function get_order_total( $order = 0 ) {
		return 0;
	}

	/**
	 * Retrieves the customer details for an order
	 *
	 * @since 2.2
	 *
	 * @param int $order_id The ID of the order to retrieve customer details for.
	 * @return array An array of the customer details
	 */
	public function get_customer( $order_id = 0 ) {

		$customer = array(
			'first_name'   => is_user_logged_in() ? wp_get_current_user()->last_name : '',
			'last_name'    => is_user_logged_in() ? wp_get_current_user()->first_name : '',
			'email'        => is_user_logged_in() ? wp_get_current_user()->user_email : $this->email,
			'user_id'      => get_current_user_id(),
			'ip'           => affiliate_wp()->tracking->get_ip(),
			'affiliate_id' => $this->affiliate_id
		);

		return $customer;
	}

	/**
	 * Parses a referral reference, potentially formatted per integration policy.
	 *
	 * Integrations can extend this method to manipulate the returned reference
	 * value, such as in the case of sequential numbering, etc.
	 *
	 * @since 2.3
	 *
	 * @param int $reference Reference.
	 * @return int Derived reference or 0.
	 */
	public function parse_reference( $reference ) {
		return $reference;
	}

	/**
	 * Writes a log message.
	 *
	 * @since 1.8
	 *
	 * @param string      $message Message to write to the debug log.
	 * @param array|mixed $data    Optional. Array of data or other output to send to the log.
	 *                             Default empty array.
	 */
	public function log( $message, $data = array() ) {

		// Add context to integration logs.
		if ( ! empty( $this->context ) ) {
			$message = "{$this->context}: {$message}";
		}

		affiliate_wp()->utils->log( $message, $data );

	}

	/**
	 * Determines whether a unit test is running.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the phpunit test suite is running, otherwise false.
	 */
	public function is_test() {
		return defined( 'WP_TESTS_DOMAIN' );
	}

}
