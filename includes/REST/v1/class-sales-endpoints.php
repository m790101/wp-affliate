<?php
/**
 * REST: Sales Endpoints
 *
 * @package     AffiliateWP
 * @subpackage  REST
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

namespace AffWP\Referral\Sale\REST\v1;

use \AffWP\REST\v1\Controller;

/**
 * Implements REST routes and endpoints for sales.
 *
 * @since 2.5
 *
 * @see \AffWP\REST\v1\Controller
 */
class Endpoints extends Controller {

	/**
	 * Object type.
	 *
	 * @since 2.5
	 * @access public
	 * @var string
	 */
	public $object_type = 'affwp_sale';

	/**
	 * Route base for sales.
	 *
	 * @since 2.5
	 * @var   string
	 */
	public $rest_base = 'sales';

	/**
	 * Registers sales routes.
	 *
	 * @since 2.5
	 * @since 2.6.1 Updated the /sales endpoint to allow affiliates to request their own data.
	 */
	public function register_routes() {
		// PHP 5.3 compat.
		$instance = $this;

		// /sales/
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'args'                => $this->get_collection_params(),
				'permission_callback' => function( \WP_REST_Request $request ) use ( $instance ) {
					$permitted = $instance->check_affiliate_self_request( $request );

					if ( false === $permitted ) {
						$permitted = current_user_can( 'manage_referrals' );
					}

					return $permitted;
				},
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		// /sales/ID
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => function( $request ) {
					return current_user_can( 'manage_referrals' );
				},
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		$this->register_field( 'id', array(
			'get_callback' => function( $object, $field_name, $request, $object_type ) {
				return $object->ID;
			},
		) );
	}

	/**
	 * Base endpoint to retrieve all sales.
	 *
	 * @since 2.5
	 * @since 2.7 Items are only processed for output if retrieving all fields. Added support
	 *            for retrieving one or more specific fields.
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error sales query response, otherwise \WP_Error.
	 */
	public function get_items( $request ) {

		$args = array();

		$args['number']       = isset( $request['number'] )       ? $request['number']       : 20;
		$args['offset']       = isset( $request['offset'] )       ? $request['offset']       : 0;
		$args['referral_id']  = isset( $request['referral_id'] )  ? $request['referral_id']  : 0;
		$args['affiliate_id'] = isset( $request['affiliate_id'] ) ? $request['affiliate_id'] : 0;
		$args['rest_id']      = isset( $request['rest_id'] )      ? $request['rest_id']      : '';
		$args['status']       = isset( $request['status'] )       ? $request['status']       : '';
		$args['orderby']      = isset( $request['orderby'] )      ? $request['orderby']      : '';
		$args['order']        = isset( $request['order'] )        ? $request['order']        : 'ASC';
		$args['date']         = isset( $request['date'] )         ? $request['date']         : '';
		$args['parent_id']    = isset( $request['parent_id'] )    ? $request['parent_id']    : '';

		if ( is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $request['filter'] );
		}

		$args['fields'] = $this->parse_fields_for_request( $request );

		/**
		 * Filters the query arguments used to retrieve sales in a REST request.
		 *
		 * @since 2.5
		 * @since 2.6.1 Added support for the 'response_callback' parameter.
		 *
		 * @param array            $args    Arguments.
		 * @param \WP_REST_Request $request Request.
		 */
		$args = apply_filters( 'affwp_rest_sales_query_args', $args, $request );

		$sales = affiliate_wp()->referrals->sales->get_sales( $args );

		if ( empty( $sales ) ) {
			$sales = new \WP_Error(
				'no_sales',
				'No sales were found.',
				array( 'status' => 404 )
			);
		} elseif ( '*' === $args['fields'] ) {
			array_map( function( $referral ) use ( $request ) {
				$referral = $this->process_for_output( $referral, $request );

				return $referral;
			}, $sales );
		}

		if ( isset( $request['response_callback'] ) && is_callable( $request['response_callback'] ) ) {
			$sales = call_user_func( $request['response_callback'], $sales, $request, 'sales' );
		}

		return $this->response( $sales );
	}

	/**
	 * Endpoint to retrieve a sale by referral ID.
	 *
	 * @since 2.5
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Response object or \WP_Error object if not found.
	 */
	public function get_item( $request ) {
		if ( ! $sale = \affwp_get_sale( $request['id'] ) ) {
			$sale = new \WP_Error(
				'invalid_referral_id',
				'Invalid referral ID',
				array( 'status' => 404 )
			);
		} else {
			// Populate extra fields.
			$sale = $this->process_for_output( $sale, $request );
		}

		return $this->response( $sale );
	}

	/**
	 * Retrieves the collection parameters for sales.
	 *
	 * @since 2.5
	 *
	 * @return array Sales collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		/*
		 * Pass top-level get_sales() args as query vars:
		 * /sales/?status=pending&order=desc
		 */
		$params['referral_id'] = array(
			'description' => __( 'The referral ID or array of IDs to query for.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['affiliate_id'] = array(
			'description' => __( 'The affiliate ID or array of IDs to query for.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['reference'] = array(
			'description'       => __( 'Reference information (product ID) for the sale.', 'affiliate-wp' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param );
			},
		);

		// 'ref_context' so as not to conflict with the global 'content' parameter.
		$params['ref_context'] = array(
			'description'       => __( 'The context under which the sale was created (integration).', 'affiliate-wp' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param );
			},
		);

		$params['campaign'] = array(
			'description'       => __( 'The associated campaign.', 'affiliate-wp' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param );
			},
		);

		$params['status'] = array(
			'description'       => __( 'The sale status or array of statuses.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				$statuses = array_keys( affwp_get_referral_statuses() );

				return in_array( $param, $statuses );
			},
		);

		$params['orderby'] = array(
			'description'       => __( 'sales table column to order by.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return array_key_exists( $param, affiliate_wp()->sales->get_columns() );
			},
		);

		$params['search'] = array(
			'description'       => __( 'A referral ID or the search string to query for sales with.', 'affiliate-wp' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param ) || is_string( $param );
			},
		);

		$params['date'] = array(
			'description'       => __( 'The date array or string to query sales within.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param ) || is_array( $param );
			},
		);

		$params['parent_id'] = array(
			'description'       => __( 'The parent referral ID or array of IDs to query for.', 'affiliate-wp' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param );
			},
		);

		/*
		 * Pass any valid get_sales() args via filter:
		 * /sales/?filter[status]=pending&filter[order]=desc
		 */
		$params['filter'] = array(
			'description' => __( 'Use any get_sales() arguments to modify the response.', 'affiliate-wp' ),
		);

		return $params;
	}

	/**
	 * Retrieves the schema for a single sale, conforming to JSON Schema.
	 *
	 * @since 2.5
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => $this->get_object_type(),
			'type'       => 'object',
			// Base properties for every sale.
			'properties' => array(
				'referral_id'  => array(
					'description' => __( 'The unique referral ID.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'affiliate_id' => array(
					'description' => __( 'ID for the affiliate account associated with the sale.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'order_total'  => array(
					'description' => __( 'sale order total.', 'affiliate-wp' ),
					'type'        => 'float',
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

}
