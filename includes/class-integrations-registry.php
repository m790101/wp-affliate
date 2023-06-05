<?php
/**
 * Integrations Registry
 *
 * @package     AffiliateWP
 * @subpackage  Core/Integrations
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

namespace AffWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Integrations_Registry
 *
 * @since 2.5
 * @package AffiliateWP
 */
class Integrations_Registry extends Utils\Registry {

	/**
	 * @inheritDoc
	 */
	public function init() {
		$this->register_core_integrations();
		$this->register_extended_integrations();
	}

	/**
	 * Registers integrations from other plugins.
	 *
	 * @since 2.5
	 */
	public function register_extended_integrations() {

		/**
		 * List of registered integrations from other plugins.
		 *
		 * @param array $integrations list of integration attributes, keyed by the integration ID.
		 *                            See add_integration for a list of possible attributes.
		 *
		 * @since 2.5
		 */
		$integrations = apply_filters( 'affwp_extended_integrations', array() );

		foreach ( $integrations as $integration_id => $attributes ) {
			$this->add_integration( $integration_id, $attributes );
		}

	}

	/**
	 * Adds a new integration to the integrations list.
	 *
	 * @since 2.5
	 *
	 * @param string $integration_id The integration identifier, usually an acronym or abbreviation for the integration.
	 * @param array $attributes {
	 *       List of attributes for this integration.
	 *
	 *       @type string $class   Required. The integration class name.
	 *       @type string $file    Required. The path to the file that contains this integration class.
	 *       @type bool   $enabled Optional. True forces this integration to always be enabled.
	 *                             False forces it to always be disabled. Defaults to user settings.
	 *       @type array $supports Optional. List of features this integration supports. Default empty array.
	 * }
	 */
	public function add_integration( $integration_id, $attributes ) {

		$errors = new \WP_Error();

		if ( ! isset( $attributes['enabled'] ) ) {
			$active_integrations   = affiliate_wp()->settings->get( 'integrations', array() );
			$attributes['enabled'] = isset( $active_integrations[ $integration_id ] );
		}

		if ( ! isset( $attributes['file'] ) ) {
			$attributes['file'] = AFFILIATEWP_PLUGIN_DIR . 'includes/integrations/class-' . $integration_id . '.php';
		}

		if ( ! isset( $attributes['supports'] ) ) {
			$attributes['supports'] = array();
		}

		if ( ! is_array( $attributes['supports'] ) ) {
			$attributes['supports'] = array();
		}

		// Validate supports.
		$invalid_supports = array_diff( $attributes['supports'], $this->supports_whitelist() );
		if ( ! empty( $invalid_supports ) ) {
			$message = __( sprintf( "The integration %s was not registered. Invalid support type.", $integration_id ) );
			_doing_it_wrong(
				__FUNCTION__,
				$message,
				'2.5'
			);

			$errors->add(
				'affwp_invalid_integration_support',
				$message,
				array( 'invalid_supports' => $invalid_supports )
			);
		}

		$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );

		if ( $has_errors ) {
			affiliate_wp()->utils->log(
				"The integration {$integration_id} was not registered. Errors were found during registration.",
				$errors
			);
		} else {
			$this->add_item( $integration_id, $attributes );
		}
	}

	/**
	 * Retrieves the list of features the current integration supports.
	 *
	 * @since 2.5
	 * @since 2.6 'manual_coupons' and 'dynamic_coupons' supports were added.
	 *
	 * @return array List of supported integration features.
	 */
	private function supports_whitelist() {
		$supports = array( 'sales_reporting', 'manual_coupons', 'dynamic_coupons' );

		/**
		 * Filters the supported integration features.
		 *
		 * @since 2.5
		 *
		 * @param array $supports List of supported integration features.
		 */
		$custom_supports = apply_filters( 'affwp_integration_supports', array() );

		return array_merge( $supports, $custom_supports );
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
		$defaults = array(
			'supports'         => array(),
			'does_not_support' => array(),
			'status'           => array( 'enabled' ),
			'id__in'           => array(),
			'id__not_in'       => array(),
			'fields'           => array(),
		);

		$results = $integrations = array();
		$args    = wp_parse_args( $args, $defaults );
		$errors  = new \WP_Error();

		if ( ! is_array( $args['supports'] ) ) {
			$args['supports'] = array( $args['supports'] );
		}

		if ( ! is_array( $args['does_not_support'] ) ) {
			$args['does_not_support'] = array( $args['does_not_support'] );
		}

		if ( ! is_array( $args['id__in'] ) ) {
			$args['id__in'] = array( $args['id__in'] );
		}

		if ( ! is_array( $args['fields'] ) ) {
			$args['fields'] = array( $args['fields'] );
		}

		if ( ! is_array( $args['id__not_in'] ) ) {
			$args['id__not_in'] = array( $args['id__not_in'] );
		}

		if ( ! is_array( $args['status'] ) ) {
			$args['status'] = array( $args['status'] );
		}

		// Ensure integrations are lowercase.
		$args['id__in']     = array_map( 'strtolower', $args['id__in'] );
		$args['id__not_in'] = array_map( 'strtolower', $args['id__not_in'] );

		// Validate supports.
		if ( ! empty( $args['supports'] ) ) {
			$invalid_supports = array_diff( $args['supports'], $this->supports_whitelist() );
			if ( ! empty( $invalid_supports ) ) {
				$errors->add(
					'integration_query_invalid_supports',
					'An integration query attempted to run with an invalid support feature.',
					array( 'invalid_supports' => $invalid_supports, 'args' => $args )
				);
			}
		}

		$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );

		if ( true === $has_errors ) {
			return $errors;
		}

		// Add integrations we do want
		if ( empty( $args['id__in'] ) ) {
			$integrations = array_keys( $this->get_items() );
		} else {
			$integrations = $args['id__in'];
		}

		// Filter out integrations we do not want
		if ( ! empty( $args['id__not_in'] ) ) {
			$integrations = array_diff( $integrations, $args['id__not_in'] );
		}

		$ids_only = array( 'ids' ) === $args['fields'];

		foreach ( $integrations as $integration_id ) {
			$integration = $this->get( $integration_id );

			// If the integration could not be found, skip it.
			if ( false === $integration ) {
				continue;
			}

			// If this integration is enabled, and we don't want enabled integrations, skip it.
			if ( ! in_array( 'enabled', $args['status'] ) && true === $integration['enabled'] ) {
				continue;
			}

			// If the integration is disabled, and we don't want disabled integrations, skip it.
			if ( ! in_array( 'disabled', $args['status'] ) && false === $integration['enabled'] ) {
				continue;
			}

			// If this integration does not support all of the specified features, skip it.
			if ( ! empty( $args['supports'] ) ) {
				$support_diff = array_diff( $args['supports'], $integration['supports'] );
				if ( ! empty( $support_diff ) ) {
					continue;
				}
			}

			// If this integration supports features that it should not support, skip it.
			if ( ! empty( $args['does_not_support'] ) ) {
				$does_not_support_diff = array_intersect( $integration['supports'],$args['does_not_support']  );
				if ( ! empty( $does_not_support_diff ) ) {
					continue;
				}
			}

			// If ids is set, append the ID and move on.
			if ( true === $ids_only ) {
				$results[] = $integration_id;
			} else {
				$results[ $integration_id ] = $integration;
			}

			// Only capture the specified fields
			if ( false === $ids_only && ! empty( $args['fields'] ) ) {

				$integration_fields = array_intersect_key( $integration, array_flip( $args['fields'] ) );

				// If we only have one value, set the value directly
				if ( 1 === count( $integration_fields ) ) {
					$field_values               = array_values( $integration_fields );
					$results[ $integration_id ] = $field_values[0];
				} else {
					$results[ $integration_id ] = $integration_fields;
				}
			}
		}

		return $results;
	}

	/**
	 * Registers core integrations
	 *
	 * @since 2.5
	 */
	public function register_core_integrations() {

		// Caldera Forms
		$this->add_integration( 'caldera-forms', array(
			'name'  => 'Caldera Forms',
			'class' => '\Affiliate_WP_Caldera_Forms',
		) );

		// Contact Form 7
		$this->add_integration( 'contactform7', array(
			'name'  => 'Contact Form 7',
			'class' => '\Affiliate_WP_Contact_Form_7',
		) );

		// Easy Digital Downloads
		$this->add_integration( 'edd', array(
			'name'     => 'Easy Digital Downloads',
			'class'    => '\Affiliate_WP_EDD',
			'supports' => array( 'sales_reporting', 'manual_coupons' ),
		) );

		// Formidable Pro
		$this->add_integration( 'formidablepro', array(
			'name'  => 'Formidable Pro',
			'class' => '\Affiliate_WP_Formidable_Pro',
		) );

		// Give
		$this->add_integration( 'give', array(
			'name'  => 'Give',
			'class' => '\Affiliate_WP_Give',
		) );

		// Gravity Forms
		$this->add_integration( 'gravityforms', array(
			'name'  => 'Gravity Forms',
			'class' => '\Affiliate_WP_Gravity_Forms',
		) );

		// ExchangeWP (iThemes Exchange)
		$this->add_integration( 'exchange', array(
			'name'     => 'ExchangeWP (iThemes Exchange)',
			'class'    => '\Affiliate_WP_Exchange',
			'supports' => array( 'manual_coupons' ),
		) );

		// Jigoshop
		$this->add_integration( 'jigoshop', array(
			'name'  => 'Jigoshop',
			'class' => '\Affiliate_WP_Jigoshop',
		) );

		// LifterLMS
		$this->add_integration( 'lifterlms', array(
			'name'  => 'LifterLMS',
			'class' => '\Affiliate_WP_LifterLMS',
		) );

		// MarketPress
		$this->add_integration( 'marketpress', array(
			'name'  => 'MarketPress',
			'class' => '\Affiliate_WP_MarketPress',
		) );

		// MemberMouse
		$this->add_integration( 'membermouse', array(
			'name'  => 'MemberMouse',
			'class' => '\Affiliate_WP_Membermouse',
		) );

		// MemberPress
		$this->add_integration( 'memberpress', array(
			'name'     => 'MemberPress',
			'class'    => '\Affiliate_WP_MemberPress',
			'supports' => array( 'manual_coupons' ),
		) );

		// Ninja Forms
		$this->add_integration( 'ninja-forms', array(
			'name'  => 'Ninja Forms',
			'class' => '\Affiliate_WP_Ninja_Forms',
		) );

		// OptimizeMember
		$this->add_integration( 'optimizemember', array(
			'name'  => 'OptimizeMember',
			'class' => '\Affiliate_WP_OptimizeMember',
		) );

		// PayPal Buttons
		$this->add_integration( 'paypal', array(
			'name'  => 'PayPal Buttons',
			'class' => '\Affiliate_WP_PayPal',
		) );

		// Paid Memberships Pro
		$this->add_integration( 'pmp', array(
			'name'  => 'Paid Memberships Pro',
			'class' => '\Affiliate_WP_PMP',
		) );

		// Paid Member Subscriptions
		$this->add_integration( 'pms', array(
			'name'  => 'Paid Member Subscriptions',
			'class' => '\Affiliate_WP_PMS',
		) );

		// Restrict Content Pro
		$this->add_integration( 'rcp', array(
			'name'     => 'Restrict Content Pro',
			'class'    => '\Affiliate_WP_RCP',
			'supports' => array( 'sales_reporting' ),
		) );

		// s2Member
		$this->add_integration( 's2member', array(
			'name'  => 's2Member',
			'class' => '\Affiliate_WP_S2Member',
		) );

		// Shopp
		$this->add_integration( 'shopp', array(
			'name'  => 'Shopp',
			'class' => '\Affiliate_WP_Shopp',
		) );

		// Sprout Invoices
		$this->add_integration( 'sproutinvoices', array(
			'name'  => 'Sprout Invoices',
			'class' => '\Affiliate_WP_Sprout_Invoices',
		) );

		// Stripe (through WP Simple Pay)
		$this->add_integration( 'stripe', array(
			'name'  => 'Stripe (through WP Simple Pay)',
			'class' => '\Affiliate_WP_Stripe',
		) );

		// WooCommerce
		$this->add_integration( 'woocommerce', array(
			'name'     => 'WooCommerce',
			'class'    => '\Affiliate_WP_WooCommerce',
			'supports' => array( 'sales_reporting', 'manual_coupons', 'dynamic_coupons' ),
		) );

		// WP EasyCart
		$this->add_integration( 'wpeasycart', array(
			'name'  => 'WP EasyCart',
			'class' => '\Affiliate_WP_EasyCart',
		) );

		// WP eCommerce
		$this->add_integration( 'wpec', array(
			'name'  => 'WP eCommerce',
			'class' => '\Affiliate_WP_WPEC',
		) );

		// WPForms
		$this->add_integration( 'wpforms', array(
			'name'  => 'WPForms',
			'class' => '\Affiliate_WP_WPForms',
		) );

		// WP-Invoice
		$this->add_integration( 'wp-invoice', array(
			'name'  => 'WP-Invoice',
			'class' => '\Affiliate_WP_Invoice',
		) );

		// Zippy Courses
		$this->add_integration( 'zippycourses', array(
			'name'  => 'Zippy Courses',
			'class' => '\Affiliate_WP_ZippyCourses',
		) );

	}
}