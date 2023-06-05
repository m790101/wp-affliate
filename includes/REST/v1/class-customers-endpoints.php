<?php
/**
 * REST: Customers Endpoints
 *
 * @package     AffiliateWP
 * @subpackage  REST
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

namespace AffWP\Customer\REST\v1;

use AffWP\REST\v1\Controller;

/**
 * Implements REST routes and endpoints for Customers.
 *
 * @since 1.9
 *
 * @see \AffWP\REST\Controller
 */
class Endpoints extends Controller {

	/**
	 * Object type.
	 *
	 * @since 2.3
	 * @var   string
	 */
	public $object_type = 'affwp_customer';

	/**
	 * Route base for customers.
	 *
	 * @since 2.3
	 * @var   string
	 */
	public $rest_base = 'customers';

	/**
	 * Registers Customer routes.
	 *
	 * @since 2.3
	 */
	public function register_routes() {

		// /customers/
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'args'                => $this->get_collection_params(),
				'permission_callback' => function( $request ) {
					return current_user_can( 'manage_customers' );
				}
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		// /customers/ID || /customers/email
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/((?P<id>\d+)|(?P<email>.+))', array(
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_item' ),
				'args'     => array(
					'user' => array(
						'description'       => __( 'Whether to include a modified user object in the response.', 'affiliate-wp' ),
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						}
					),
					'meta' => array(
						'description'       => __( 'Whether to include the customer meta in the response.', 'affiliate-wp' ),
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						}
					),
				),
				'permission_callback' => function( $request ) {
					return current_user_can( 'manage_customers' );
				},
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		$this->register_field( 'id', array(
			'get_callback' => function( $object, $field_name, $request, $object_type ) {
				return $object->ID;
			}
		) );
	}

	/**
	 * Base endpoint to retrieve all customers.
	 *
	 * @since 2.3
	 * @since 2.6.1 Added support for the 'response_callback' parameter.
	 * @since 2.7   Items are only processed for output if retrieving all fields. Added support for
	 *              retrieving multiple fields.
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Customers response object or \WP_Error object if not found.
	 */
	public function get_items( $request ) {

		$args = array();

		$args['customer_id'] = isset( $request['customer_id'] ) ? $request['customer_id'] : 0;
		$args['user_id']     = isset( $request['user_id'] )     ? $request['user_id']     : 0;
		$args['email']       = isset( $request['email'] )       ? $request['email']       : '';
		$args['exclude']     = isset( $request['exclude'] )     ? $request['exclude']     : array();
		$args['date']        = isset( $request['date'] )        ? $request['date']        : '';
		$args['number']      = isset( $request['number'] )      ? $request['number']      : 20;
		$args['offset']      = isset( $request['offset'] )      ? $request['offset']      : 0;
		$args['order']       = isset( $request['order'] )       ? $request['order']       : 'ASC';
		$args['orderby']     = isset( $request['orderby'] )     ? $request['orderby']     : '';
		$args['search']      = isset( $request['search'] )      ? $request['search']      : false;

		if ( is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $request['filter'] );
		}

		$args['fields'] = $this->parse_fields_for_request( $request );

		/**
		 * Filters the query arguments used to retrieve customers in a REST request.
		 *
		 * @since 2.3
		 *
		 * @param array            $args    Arguments.
		 * @param \WP_REST_Request $request Request.
		 */
		$args = apply_filters( 'affwp_rest_customers_query_args', $args, $request );

		$customers = affiliate_wp()->customers->get_customers( $args );

		if ( empty( $customers ) ) {
			$customers = new \WP_Error(
				'no_customers',
				'No customers were found.',
				array( 'status' => 404 )
			);
		} elseif ( '*' === $args['fields'] ) {
			array_map( function( $customer ) use ( $request ) {
				$customer = $this->process_for_output( $customer, $request );
				return $customer;
			}, $customers );
		}

		if ( isset( $request['response_callback'] ) && is_callable( $request['response_callback'] ) ) {
			$customers = call_user_func( $request['response_callback'], $customers, $request, 'customers' );
		}

		return $this->response( $customers );
	}

	/**
	 * Endpoint to retrieve a customer by ID or email.
	 *
	 * @since 2.3
	 * @since 2.4.1 Added support for retrieving a customer by email
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Customer object response or \WP_Error object if not found.
	 */
	public function get_item( $request ) {
		if ( isset( $request['email'] ) ) {
			$customer_to_fetch = sanitize_text_field( $request['email'] );
			$error_msg = 'Invalid customer email';
		} else {
			$customer_to_fetch = intval( $request['id'] );
			$error_msg = 'Invalid customer ID';
		}

		/**
		 * Filters the requested customer ID or email.
		 *
		 * @since 2.4.1
		 *
		 * @param int|string       $customer_to_fetch Customer ID or email to (attempt to) retrieve.
		 * @param \WP_REST_Request $request           Request arguments.
		 */
		$customer_to_fetch = apply_filters( 'affwp_rest_get_customer', $customer_to_fetch, $request );

		if ( ! $customer = \affwp_get_customer( $customer_to_fetch ) ) {
			$customer = new \WP_Error(
				'invalid_customer',
				$error_msg,
				array( 'status' => 404 )
			);
		} else {
			$user = (bool) $request->get_param( 'user' );
			$meta = (bool) $request->get_param( 'meta' );

			// Populate extra fields and return.
			$customer = $this->process_for_output( $customer, $request, $user, $meta );
		}

		return $this->response( $customer );

	}

	/**
	 * Processes a Customer object for output.
	 *
	 * Populates non-public properties with derived values.
	 *
	 * @since 2.4.1
	 *
	 * @param \AffWP\Customer  $customer Customer object.
	 * @param \WP_REST_Request $request  Full details about the request.
	 * @param bool             $user     Optional. Whether to lazy load the user object. Default false.
	 * @param bool             $meta     Optional. Whether to lazy load the customer meta. Default false.
	 * @return \AffWP\Customer Customer object.
	 */
	protected function process_for_output( $customer, $request, $user = false, $meta = false ) {

		if ( true === $user ) {
			$customer->user = $customer->get_user();
		}

		if ( true === $meta ) {
			$customer->meta = $customer->get_meta();
		}

		return parent::process_for_output( $customer, $request );
	}

	/**
	 * Retrieves the collection parameters for customers.
	 *
	 * @since 2.3
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		// Customers don't have rest IDs.
		if ( isset( $params['rest_id'] ) ) {
			unset( $params['rest_id'] );
		}

		/*
		 * Pass top-level args as query vars:
		 * /customers/?status=paid&order=desc
		 */
		$params['customer_id'] = array(
			'description' => __( 'The customer ID or comma-separated list of IDs to query for.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['user_id'] = array(
			'description' => __( 'The user ID or comma-separated list of IDs to query customers for.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['exclude'] = array(
			'description' => __( 'Customer ID or comma-separated list of IDs to exclude.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['email'] = array(
			'description'       => __( 'The customer email or array of emails to query customers for.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param ) || is_array( $param );
			},
		);

		$params['orderby'] = array(
			'description'       => __( 'Customers table column to order by.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return array_key_exists( $param, affiliate_wp()->customers->get_columns() );
			}
		);

		$params['date'] = array(
			'description'       => __( 'The date array or string to query customers within.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param ) || is_array( $param );
			},
		);

		/*
		 * Pass any valid get_customers() args via filter:
		 * /customers/?filter[field]=value
		 */
		$params['filter'] = array(
			'description' => __( 'Use any get_customers() arguments to modify the response.', 'affiliate-wp' )
		);

		return $params;
	}

	/**
	 * Retrieves the schema for a single customer, conforming to JSON Schema.
	 *
	 * @since 2.3
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => $this->get_object_type(),
			'type'       => 'object',
			// Base properties for every customer.
			'properties' => array(
				'customer_id'     => array(
					'description' => __( 'The unique customer ID.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'user_id'  => array(
					'description' => __( 'The affiliate user ID associated with the customer.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'first_name' => array(
					'description' => __( 'Customer first name.', 'affiliate-wp' ),
					'type'        => 'string',
				),
				'last_name' => array(
					'description' => __( 'Customer last name.', 'affiliate-wp' ),
					'type'        => 'string',
				),
				'email' => array(
					'description' => __( 'Customer email.', 'affiliate-wp' ),
					'type'        => 'string',
				),
				'ip' => array(
					'description' => __( 'Customer IP address.', 'affiliate-wp' ),
					'type'        => 'string',
				),
				'date_created' => array(
					'description' => __( 'The date the customer was created.', 'affiliate-wp' ),
					'type'        => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

}
