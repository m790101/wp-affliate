<?php
/**
 * Integrations Bootstrap
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
use AffWP\Integrations_Registry;

/**
 * Core class used to manage supported integrations.
 *
 * @since 1.0
 */
class Affiliate_WP_Integrations {

	/**
	 * Holds the opt_in integration property.
	 *
	 * @since 2.2
	 */
	public $opt_in;

	/**
	 * Integrations registry.
	 *
	 * @since 2.5
	 * @var   Integrations_Registry
	 */
	private static $registry;

	/**
	 * Error logging instance.
	 *
	 * @since 2.5
	 *
	 * @var Affiliate_WP_Logging
	 */
	private $logger;

	/**
	 * Get things started.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {

		// Because integrations runs fairly early, we have to manually load an instance the logger here.
		require_once AFFILIATEWP_PLUGIN_DIR . 'includes/class-logging.php';

		$this->logger = new Affiliate_WP_Logging;

		$this->load();

		$this->opt_in = new \AFFWP\Integrations\Opt_In;

		// Register upgrade routine for integrations.
		add_action( 'affwp_batch_process_init', function( $batch ) {
			if ( $batch instanceof AffWP\Utils\Batch_Process\Registry ) {
				$batch->register_process( 'sync-integration-sales-data', array(
					'class' => '\AffWP\Utils\Batch_Process\Sync_Integration_Sales_Data',
					'file'  => AFFILIATEWP_PLUGIN_DIR . 'includes/admin/tools/class-batch-sync-integration-sales-data.php',
				) );
			}
		} );
	}

	/**
	 * Retrieves the registry.
	 *
	 * @since 2.5
	 *
	 * @return Integrations_Registry The registry object.
	 */
	private function get_registry() {
		if ( ! self::$registry instanceof Integrations_Registry ) {
			require_once( AFFILIATEWP_PLUGIN_DIR . 'includes/class-integrations-registry.php' );

			self::$registry = new Integrations_Registry();
			self::$registry->init();
		}

		return self::$registry;
	}

	/**
	 * Retrieves an array of all supported integrations.
	 *
	 * @since 1.0
	 * @return array Integration name keyed by the context.
	 */
	public function get_integrations() {
		$integrations = $this->query( array(
			'status' => array( 'enabled', 'disabled' ),
			'fields' => 'name',
		) );

		/**
		 * Filters the list of supported integrations.
		 *
		 * @since 1.0
		 * @deprecated 2.5 use affwp_extended_integrations instead.
		 *
		 * @param array $integrations List of supported integrations.
		 */
		return apply_filters_deprecated(
			'affwp_integrations',
			array( $integrations ),
			'2.5',
			'affwp_extended_integrations'
		);
	}

	/**
	 * Retrieves an array of all enabled integrations.
	 *
	 * @since 2.2
	 *
	 * @return array The list of enabled integrations.
	 */
	public function get_enabled_integrations() {
		return $this->query( array( 'fields' => 'name' ) );
	}

	/**
	 * Retrieves the list of discontinued integrations.
	 *
	 * @since 2.7
	 *
	 * @return array List of discontinued integrations where the key is the integration slug and the value
	 *               is the label.
	 */
	public function get_discontinued_integrations() {
		return array(
			'exchange'    => __( 'Exchange', 'affiliate-wp' ),
			'jigoshop'    => __( 'Jigoshop', 'affiliate-wp' ),
			'marketpress' => __( 'MarketPress', 'affiliate-wp' ),
			'shopp'       => __( 'Shopp', 'affiliate-wp' ),
			'wpec'        => __( 'WP eCommerce', 'affiliate-wp' ),
		);
	}

	/**
	 * Retrieves a map of all integration keys and their associated class names.
	 *
	 * @since 2.2
	 *
	 * @return array The list of integration classes.
	 */
	public function get_integration_classes() {
		$integrations = $this->query( array(
			'status' => array( 'enabled', 'disabled' ),
			'fields' => 'class',
		) );

		/**
		 * Filters the list of integration classes.
		 *
		 * @since 2.2
		 * @deprecated 2.5 use affwp_extended_integrations instead.
		 *
		 * @param array $classes Key/value pairs where the key is the integration
		 *                       slug and the value is the class name.
		 */
		return apply_filters_deprecated( 'affwp_integrations_classes', array( $integrations ), '2.5', 'affwp_extended_integrations' );
	}

	/**
	 * Retrieves the class name for a specific integration
	 *
	 * @since 2.2
	 * @param string $integration The integration class to retrieve.
	 * @return bool|string
	 */
	public function get_integration_class( $integration = '' ) {
		$integrations = $this->get_integration_classes();

		if ( array_key_exists( $integration, $integrations ) ) {
			return $integrations[ $integration ];
		}

		return false;
	}

	/**
	 * Gets the order count from the specified integration, if the integration supports this.
	 *
	 * @since 2.5
	 * @param string|Affiliate_WP_Base $integration The integration slug or instance to retrieve the order count from.
	 * @param string|array             $date {
	 *     Optional. Date string or start/end range to retrieve orders for. Default empty.
	 *
	 *     @type string $start Start date to retrieve orders for.
	 *     @type string $end   End date to retrieve orders for.
	 * }
	 * @return \WP_Error|int Returns the order count if the integration exists and supports order counts,
	 *                       otherwise a WP_Error object.
	 */
	public function get_order_count( $integration, $date = '' ) {
		$integration = $this->get( $integration );

		if ( is_wp_error( $integration ) ) {
			return $integration;
		}

		// Get the order count, if possible.
		if ( $integration instanceof Affiliate_WP_Base && $integration->is_active() ) {
			$order_count = $integration->get_total_order_count( $date );
		} else {
			$name = $integration->get_name();
			$order_count = new \WP_Error( "Specified integration {$name} is not supported or is inactive." );
		}

		return is_wp_error( $order_count ) ? $order_count : (int) $order_count;
	}

	/**
	 * Gets the number of active integration orders that were received without a referral.
	 *
	 * @since 2.5
	 *
	 * @param string|array $date {
	 *     Optional. Date string or start/end range to retrieve orders for. Default empty.
	 *
	 *     @type string $start Start date to retrieve orders for.
	 *     @type string $end   End date to retrieve orders for.
	 * }
	 * @return \WP_Error|int Returns the order count if the integration exists, and supports order counts.
	 *                       Otherwise, returns a WP_Error.
	 */
	public function get_non_referral_order_count( $date = '' ) {
		$enabled_integrations = $this->query( array(
			'supports' => array( 'sales_reporting' ),
		) );
		$order_count          = 0;

		// If no active integrations support sales reporting, just return zero.
		if ( ! empty( $enabled_integrations ) ) {
			$integration_order_count = $this->get_total_order_count( $date );

			if ( ! is_wp_error( $integration_order_count ) ) {
				$sales_count = affiliate_wp()->referrals->get_referrals( array(
					'context' => array_keys( $enabled_integrations ),
					'date'    => $date,
				), true );

				$order_count = $integration_order_count - $sales_count;
			} else {
				// Return the error, if we have one.
				$order_count = $integration_order_count;
			}
		}

		return $order_count;
	}

	/**
	 * Gets the total order count for all active integrations
	 *
	 * @since 2.5
	 *
	 * @param string|array $date {
	 *     Optional. Date string or start/end range to retrieve orders for. Default empty
	 *
	 *     @type string $start Start date to retrieve orders for.
	 *     @type string $end   End date to retrieve orders for.
	 * }
	 * @return \WP_Error|int The total order count, or a WP_Error object containing an error
	 *                       for each failed attempt to get an order count
	 */
	public function get_total_order_count( $date = '' ) {
		$total        = 0;
		$errors       = new WP_Error();
		$integrations = $this->query( array(
			'supports' => array( 'sales_reporting' ),
		) );

		foreach ( $integrations as $integration_id => $attributes ) {
			$order_count = $this->get_order_count( $integration_id, $date );
			if ( ! is_wp_error( $order_count ) ) {
				$total += $order_count;
			} else {
				$name = $attributes['name'];
				$this->logger->log( "Could not get integration order count - active integration ${name} does not support order counting" );
				$errors->add( $order_count->get_error_code(), $order_count->get_error_message(), $order_count->get_error_data() );
			}
		}

		// If we have errors, bail and return those errors instead.
		if ( ! empty( $errors->errors ) ) {
			return $errors;
		}

		return $total;
	}

	/**
	 * Gets the total sales amount for all active integrations
	 *
	 * @since 2.5
	 *
	 * @param string|array $date  {
	 *     Optional. Date string or start/end range to retrieve orders for. Default empty
	 *
	 *     @type string $start Start date to retrieve orders for.
	 *     @type string $end   End date to retrieve orders for.
	 * }
	 * @return int|\WP_Error The total order count, or a WP_Error object containing an error
	 *                       for each failed attempt to get an order count
	 */
	public function get_total_sales( $date = '' ) {
		$total        = 0;
		$errors       = new WP_Error();
		$integrations = $this->query( array(
			'supports' => array( 'sales_reporting' ),
			'fields'   => 'ids'
		) );

		foreach ( $integrations as $id ) {
			$class = $this->get( $id );
			$integration_sales = $this->get_integration_sales( $class, $date );
			if ( ! is_wp_error( $integration_sales ) ) {
				$total += $integration_sales;
			} else {
				$this->logger->log( "Could not get integration sales totals - active integration {$id} does not support sales total calculations." );
				$errors->add( $integration_sales->get_error_code(), $integration_sales->get_error_message(), $integration_sales->get_error_data() );
			}
		}

		// If we have errors, bail and return those errors instead.
		if ( ! empty( $errors->errors ) ) {
			return $errors;
		}

		return $total;
	}

	/**
	 * Gets the total sales amounts from the specified integration.
	 *
	 * @since 2.5
	 *
	 * @param string|Affiliate_WP_Base $integration The integration class to use.
	 * @param string|array             $date  {
	 *     Optional. Date string or start/end range to retrieve orders for. Default empty.
	 *
	 *     @type string $start Start date to retrieve orders for.
	 *     @type string $end   End date to retrieve orders for.
	 * }
	 * @return int|\WP_Error The total sales, or a WP_Error object if the integration is inactive or not supported.
	 */
	public function get_integration_sales( $integration, $date = '' ) {
		$integration = $this->get( $integration );

		if ( is_wp_error( $integration ) ) {
			return $integration;
		}

		// Get the order count, if possible.
		if ( $integration instanceof Affiliate_WP_Base && $integration->is_active() ) {
			$sales_total = $integration->get_total_sales( $date );
		} else {
			$name = $integration->get_name();
			$sales_total = new \WP_Error( "Specified integration ${name} is not supported or is inactive." );
		}

		return is_wp_error( $sales_total ) ? $sales_total : (float) $sales_total;
	}

	/**
	 * Gets the percentage of orders that came from affiliates.
	 *
	 * @since 2.5
	 *
	 * @param string|array $date  {
	 *     Optional. Date string or start/end range to retrieve orders for. Default empty.
	 *
	 *     @type string $start Start date to retrieve orders for.
	 *     @type string $end   End date to retrieve orders for.
	 * }
	 * @return string|false|\WP_Error The sale percentage, or a WP_Error object containing an error
	 *                                for each failed attempt to get an order count. False if there's no data to show.
	 */
	public function get_affiliate_generated_order_percentage( $date = '' ) {
		$order_count = affiliate_wp()->integrations->get_total_order_count( $date );

		// If order count failed to calculate, return the error object.
		if ( is_wp_error( $order_count ) ) {
			return $order_count;
		}

		// If we don't have any orders, don't bother calculating.
		if ( $order_count <= 0 ) {
			$percentage = false;
		} else {
			$enabled_integrations = $this->get_enabled_integrations();
			$referral_sales       = affiliate_wp()->referrals->sales->count( array(
				'context' => array_keys( $enabled_integrations ),
				'status'  => array( 'paid', 'unpaid' ),
				'date'    => $date,
			) );

			$percentage = affwp_calculate_percentage( $referral_sales, $order_count );

			// If the percentage returned an invalid number, return false.
			if ( is_infinite( $percentage ) || $percentage < 0 ) {
				$percentage = false;
			}
		}

		return $percentage;
	}

	/**
	 * Calculates the percentage showing how much the affiliate program increased revenue.
	 *
	 * @since 2.5
	 *
	 * @param string|array $date  {
	 *     Optional. Date string or start/end range to retrieve sales for. Default empty.
	 *
	 *     @type string $start Start date to retrieve sales for.
	 *     @type string $end   End date to retrieve sales for.
	 * }
	 * @return float|\WP_Error|false The sale percentage, or a WP_Error object containing an error
	 *                               for each failed attempt to get sales total. False if there's
	 *                               no data to show.
	 */
	public function get_affiliate_generated_sale_percentage( $date = '' ) {
		$integration_sales = affiliate_wp()->integrations->get_total_sales( $date );

		// If sales failed to calculate, return the error object.
		if ( is_wp_error( $integration_sales ) ) {
			return $integration_sales;
		}

		$referral_sales = affiliate_wp()->referrals->sales->get_revenue_by_referral_status( array(
			'paid',
			'unpaid',
		), 0, $date );

		$percentage = affwp_calculate_percentage( $referral_sales, ( $integration_sales - $referral_sales ) );

		// If the percentage returned an invalid number, return false.
		if ( is_infinite( $percentage ) || $percentage < 0 ) {
			$percentage = false;
		}

		return $percentage;
	}

	/**
	 * Gets an instance of the specified integration class, if it exists.
	 *
	 * @since 2.5
	 *
	 * @param string|\Affiliate_WP_Base $integration The integration slug or instance to instantiate.
	 * @return Affiliate_WP_Base|WP_Error The integration class if it exists. A WP_Error object, otherwise.
	 */
	public function get( $integration ) {

		// If the integration is already set, bail early.
		if ( $integration instanceof Affiliate_WP_Base ) {
			return $integration;
		}

		// Retrieve the registry record.
		$integration = $this->get_registry()->get( $integration );

		// Autoload the file, if necessary.
		if ( is_array( $integration ) && ! class_exists( $integration['class'] ) && file_exists( $integration['file'] ) ) {
			require_once $integration['file'];
		}

		// Instantiate the class, or throw an error.
		if ( $integration && class_exists( $integration['class'] ) ) {
			$integration = new $integration['class'];
		} else {
			$integration = new \WP_Error(
				'integration_does_not_exist',
				'The specified integration could not be found',
				array( 'integration' => $integration )
			);
		}

		return $integration;
	}

	/**
	 * Load integration classes for each enabled integration.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function load() {

		// Load each enabled integrations
		require_once AFFILIATEWP_PLUGIN_DIR . 'includes/integrations/class-base.php';

		/**
		 * Filters the list of enabled integrations.
		 *
		 * @since 1.0
		 *
		 * @param array $enabled List of enabled integrations.
		 */
		$enabled = apply_filters( 'affwp_enabled_integrations', $this->get_enabled_integrations() );

		/**
		 * Fires immediately prior to AffiliateWP integrations being loaded.
		 *
		 * @since 1.0
		 */
		do_action( 'affwp_integrations_load' );

		$loaded = $errors = array();

		foreach ( $enabled as $integration_id => $integration ) {

			// Attempt to get the integration object
			$integration = $this->get( $integration_id );

			// If the object is what we expect, activate it.
			if ( ! is_wp_error( $integration ) && $integration instanceof Affiliate_WP_Base ) {
				$activated = $integration->activate();

				// TODO log integration activation errors with a sane approach.
				if ( ! is_wp_error( $activated ) ) {
					$loaded[] = $activated;
				}
			}

		}

		require_once AFFILIATEWP_PLUGIN_DIR . 'includes/integrations/class-opt-in.php';

		/**
		 * Fires immediately after all AffiliateWP integrations are loaded.
		 *
		 * @since 1.0
		 * @since 2.5.4 Added a `$loaded` parameter containing the list of loaded integrations.
		 *
		 * @param array $loaded Loaded integrations (if any).
		 */
		do_action( 'affwp_integrations_loaded', $loaded );

	}

	/**
	 * Checks to see if any enabled integrations support the specified feature.
	 *
	 * @since 2.5
	 *
	 * @param string $integration The integration to check
	 * @param string $feature     The feature to check.
	 * @return bool True if the feature is supported, false if not.
	 */
	public function supports( $integration, $feature ) {
		$integration = $this->query( array(
			'id__in'   => $integration,
			'supports' => $feature,
		) );

		if ( is_wp_error( $integration ) ) {
			return false;
		}

		return ! empty( $integration );
	}

	/**
	 * Queries integrations using specified arguments.
	 *
	 * @since 2.5
	 *
	 * @param array $args {
	 *      Optional. Arguments used to filter the returned integration objects.
	 *
	 *      @type array  $fields           List of fields to retrieve. Default all fields.
	 *      @type array  $supports         List of Features the integrations must support. default empty array.
	 *      @type array  $does_not_support List of Features the integrations must not support. default empty array.
	 *      @type array  $status           Filters integrations that are not enabled in AffiliateWP.
	 *                                     Accepts "enabled", "disabled", or an array containing both. Default
	 *                                     "enabled".
	 *      @type array  $id__in           List of integration IDs to query against. Leave empty for all integrations.
	 *                                     Default empty array.
	 *      @type array  $id__not_in       List of integration IDs to filter. Default empty array
	 * }
	 *
	 * @return array|\WP_Error Array of registered integration data, keyed by their id, or a WP_Error object.
	 */
	public function query( $args = array() ) {
		return $this->get_registry()->query( $args );
	}

	/**
	 * Retrieves registry attributes for the specified instance.
	 *
	 * @since 2.5
	 *
	 * @param string $id The integration ID.
	 * @return array|false Array of attributes for the item if registered, otherwise false.
	 */
	public function get_attributes( $id ) {
		return $this->get_registry()->get( $id );
	}
}
