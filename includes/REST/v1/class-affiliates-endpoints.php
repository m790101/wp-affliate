<?php
/**
 * REST: Affiliates Endpoints
 *
 * @package     AffiliateWP
 * @subpackage  REST
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

namespace AffWP\Affiliate\REST\v1;

use AffWP\REST\v1\Controller;

/**
 * Implements REST routes and endpoints for Affiliates.
 *
 * @since 1.9
 *
 * @see AffWP\REST\Controller
 */
class Endpoints extends Controller {

	/**
	 * Object type.
	 *
	 * @since 1.9.5
	 * @access public
	 * @var string
	 */
	public $object_type = 'affwp_affiliate';

	/**
	 * Route base for affiliates.
	 *
	 * @since 1.9
	 * @access public
	 * @var string
	 */
	public $rest_base = 'affiliates';

	/**
	 * Registers Affiliate routes.
	 *
	 * @since 1.9
	 * @since 2.6.2 Updated the /affiliates/ID|username endpoint to allow affiliates to request their own data.
	 */
	public function register_routes() {
		// PHP 5.3 compat.
		$instance = $this;

		// /affiliates/
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_items' ),
				'args'     => $this->get_collection_params(),
				'permission_callback' => function( $request ) {
					return current_user_can( 'manage_affiliates' );
				},
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		// /affiliates/ID || /affiliates/username
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/((?P<id>\d+)|(?P<username>\w+))', array(
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
						'description'       => __( 'Whether to include the affiliate meta in the response.', 'affiliate-wp' ),
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						}
					),
				),
				'permission_callback' => function( $request ) use ( $instance ) {
					$permitted = $instance->check_affiliate_self_request( $request );

					if ( false === $permitted ) {
						$permitted = current_user_can( 'manage_affiliates' );
					}

					return $permitted;
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
	 * Base endpoint to retrieve all affiliates.
	 *
	 * @since 1.9
	 * @since 2.6.1 Added support for the 'response_callback' parameter.
	 * @since 2.7   Items are only processed for output if retrieving all fields. Added support for
	 *              retrieving multiple fields.
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Affiliates response object or \WP_Error object if not found.
	 */
	public function get_items( $request ) {

		$args = array();

		$args['number']       = isset( $request['number'] )       ? $request['number'] : 20;
		$args['offset']       = isset( $request['offset'] )       ? $request['offset'] : 0;
		$args['exclude']      = isset( $request['exclude'] )      ? $request['exclude'] : array();
		$args['affiliate_id'] = isset( $request['affiliate_id'] ) ? $request['affiliate_id'] : 0;
		$args['user_id']      = isset( $request['user_id'] )      ? $request['user_id'] : 0;
		$args['rest_id']      = isset( $request['rest_id'] )      ? $request['rest_id'] : '';
		$args['status']       = isset( $request['status'] )       ? $request['status'] : '';
		$args['order']        = isset( $request['order'] )        ? $request['order'] : 'ASC';
		$args['orderby']      = isset( $request['orderby'] )      ? $request['orderby'] : '';
		$args['search']       = isset( $request['search'] )       ? $request['search'] : '';

		if ( is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $request['filter'] );
		}

		$args['fields'] = $this->parse_fields_for_request( $request );
		$args['user']   = $user = ! empty( $request['user'] );
		$args['meta']   = $meta = ! empty( $request['meta'] );

		/**
		 * Filters the query arguments used to retrieve affiliates in a REST request.
		 *
		 * @since 1.9
		 *
		 * @param array            $args    Arguments.
		 * @param \WP_REST_Request $request Request.
		 */
		$args = apply_filters( 'affwp_rest_affiliates_query_args', $args, $request );

		$affiliates = affiliate_wp()->affiliates->get_affiliates( $args );

		if ( empty( $affiliates ) ) {
			$affiliates = new \WP_Error(
				'no_affiliates',
				'No affiliates were found.',
				array( 'status' => 404 )
			);
		} elseif ( '*' === $args['fields'] ) {
			array_map( function( $affiliate ) use ( $user, $meta, $request ) {
				$affiliate = $this->process_for_output( $affiliate, $request, $user, $meta );
				return $affiliate;
			}, $affiliates );
		}

		if ( isset( $request['response_callback'] ) && is_callable( $request['response_callback'] ) ) {
			$affiliates = call_user_func( $request['response_callback'], $affiliates, $request, 'affiliates' );
		}

		return $this->response( $affiliates );
	}

	/**
	 * Endpoint to retrieve an affiliate by ID.
	 *
	 * @since 1.9
	 * @access public
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Affiliate object response or \WP_Error object if not found.
	 */
	public function get_item( $request ) {

		if ( isset( $request['username'] ) ) {
			$affiliate_to_fetch = sanitize_text_field( $request['username'] );
			$error_msg = 'Invalid affiliate username';
		} else {
			$affiliate_to_fetch = intval( $request['id'] );
			$error_msg = 'Invalid affiliate ID';
		}

		/**
		 * Filters the requested affiliate ID or login.
		 *
		 * @since 2.3
		 *
		 * @param int|string       $affiliate_to_fetch Affiliate ID or login to (attempt to) retrieve.
		 * @param \WP_REST_Request $request            Request arguments.
		 */
		$affiliate_to_fetch = apply_filters( 'affwp_rest_get_affiliate', $affiliate_to_fetch, $request );

		if ( ! $affiliate = \affwp_get_affiliate( $affiliate_to_fetch ) ) {
			$affiliate = new \WP_Error(
				'invalid_affiliate',
				$error_msg,
				array( 'status' => 404 )
			);
		} else {
			$user = $request->get_param( 'user' );
			$meta = $request->get_param( 'meta' );

			// Populate extra fields and return.
			$affiliate = $this->process_for_output( $affiliate, $request, $user, $meta );
		}

		return $this->response( $affiliate );
	}

	/**
	 * Processes an Affiliate object for output.
	 *
	 * Populates non-public properties with derived values.
	 *
	 * @since 1.9
	 * @since 1.9.5 Added the `$meta` and `$request` parameters.
	 * @access protected
	 *
	 * @param \AffWP\Affiliate $affiliate Affiliate object.
	 * @param \WP_REST_Request $request   Full details about the request.
	 * @param bool             $user      Optional. Whether to lazy load the user object. Default false.
	 * @param bool             $meta      Optional. Whether to lazy load the affiliate meta. Default false.
	 * @return \AffWP\Affiliate Affiliate object.
	 */
	protected function process_for_output( $affiliate, $request, $user = false, $meta = false ) {

		if ( true == $user ) {
			$affiliate->user = $affiliate->get_user();
		}

		if ( true == $meta ) {
			$affiliate->meta = $affiliate->get_meta();
		}

		return parent::process_for_output( $affiliate, $request );
	}

	/**
	 * Retrieves the collection parameters for affiliates.
	 *
	 * @since 1.9
	 * @access public
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		/*
		 * Pass top-level get_affiliates() args as query vars:
		 * /affiliates/?status=pending&order=desc
		 */
		$params['affiliate_id'] = array(
			'description' => __( 'The affiliate ID or array of IDs to query for.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['user_id'] = array(
			'description' => __( 'The user ID or array of IDs to query payouts for.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['exclude'] = array(
			'description' => __( 'Affiliate ID or array of IDs to exclude from the query.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['search'] = array(
			'description'       => __( 'Terms to search for affiliates. Accepts an affiliate ID or a string.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param ) || is_string( $param );
			},
		);

		$params['status'] = array(
			'description'       => __( "The affiliate status. Accepts 'active', 'inactive', 'pending', or 'rejected'.", 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				$statuses = array_keys( affwp_get_affiliate_statuses() );

				return in_array( $param, $statuses );
			},
		);

		$params['orderby'] = array(
			'description'       => __( 'Affiliates table column to order by.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return array_key_exists( $param, affiliate_wp()->affiliates->get_columns() );
			}
		);

		$params['date'] = array(
			'description'       => __( 'The date array or string to query affiliate registrations within.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param ) || is_array( $param );
			}
		);

		/*
		 * Pass any valid get_creatives() args via filter:
		 * /affiliates/?filter[status]=pending&filter[order]=desc
		 */
		$params['filter'] = array(
			'description' => __( 'Use any get_affiliates() arguments to modify the response.', 'affiliate-wp' )
		);

		return $params;
	}

	/**
	 * Retrieves the schema for a single affiliate, conforming to JSON Schema.
	 *
	 * @access public
	 * @since  2.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => $this->get_object_type(),
			'type'       => 'object',
			// Base properties for every affiliate.
			'properties' => array(
				'affiliate_id' => array(
					'description' => __( 'The unique affiliate ID.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'user_id'         => array(
					'description' => __( 'ID for the user account associated with the affiliate.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'rest_id'         => array(
					'description' => __( 'REST ID (site:affiliate ID combination).', 'affiliate-wp' ),
					'type'        => 'string',
				),
				'rate'            => array(
					'description'       => __( 'The affiliate rate.', 'affiliate-wp' ),
					'type'              => 'object',
					'sanitize_callback' => array( $this, 'sanitize_rate' ),
					'properties'        => array(
						'raw'       => array(
							'description' => __( 'The affiliate rate, as it exists in the database', 'affiliate-wp' ),
							'type'        => 'string',
						),
						'inherited' => array(
							'description' => __( 'The affiliate rate, as inherited from global settings.', 'affiliate-wp' ),
							'type'        => 'string',
						),
					),
				),
				'rate_type'       => array(
					'description'       => __( 'The affiliate rate type', 'affiliate-wp' ),
					'type'              => 'object',
					'sanitize_callback' => array( $this, 'sanitize_rate_type' ),
					'properties'        => array(
						'raw'       => array(
							'description' => __( 'The affiliate rate type, as it exists in the database', 'affiliate-wp' ),
							'type'        => 'string',
						),
						'inherited' => array(
							'description' => __( 'The affiliate rate type, as inherited from global settings.', 'affiliate-wp' ),
							'type'        => 'string',
						),
					),
				),
				'flat_rate_basis' => array(
						'description'       => __( 'The affiliate flat rate basis.', 'affiliate-wp' ),
						'type'              => 'object',
						'sanitize_callback' => array( $this, 'sanitize_flat_rate_basis' ),
						'properties'        => array(
								'raw'       => array(
										'description' => __( 'The affiliate flat rate basis, as it exists in the database', 'affiliate-wp' ),
										'type'        => 'string',
								),
								'inherited' => array(
										'description' => __( 'The affiliate flat rate basis, as inherited from global settings.', 'affiliate-wp' ),
										'type'        => 'string',
								),
						),
				),
				'payment_email'   => array(
					'description'       => __( 'The affiliate payment email address.', 'affiliate-wp' ),
					'type'              => 'object',
					'sanitize_callback' => array( $this, 'sanitize_payment_email' ),
					'properties'        => array(
						'raw'       => array(
							'description' => __( 'The affiliate payment email address, as it exists in the database', 'affiliate-wp' ),
							'type'        => 'string',
						),
						'inherited' => array(
							'description' => __( 'The affiliate payment email address, as inherited from the user email address.', 'affiliate-wp' ),
							'type'        => 'string',
						)
					),
				),
				'status'          => array(
					'description' => __( 'The affiliate status.', 'affiliate-wp' ),
					'type'        => 'string',
				),
				'unpaid_earnings' => array(
					'description' => __( 'Unpaid affiliate earnings.', 'affiliate-wp' ),
					'type'        => 'float',
				),
				'earnings'        => array(
					'description' => __( 'Affiliate earnings.', 'affiliate-wp' ),
					'type'        => 'float',
				),
				'referrals'       => array(
					'description' => __( 'The number of paid referrals associated with the affiliate.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'visits'          => array(
					'description' => __( 'The number of visits associated with the affiliate.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'date_registered' => array(
					'description' => __( 'The date the affiliate was registered.', 'affiliate-wp' ),
					'type'        => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Sanitizes a string-based rate type into an object if necessary.
	 *
	 * @since 2.1.9
	 *
	 * @param string|array|\stdClass $rate_type Rate type string, array, or object.
	 * @return \stdClass Rate type object.
	 */
	public function sanitize_rate_type( $rate_type ) {
		$default = affiliate_wp()->settings->get( 'rate_type', 'percentage' );

		return $this->convert_param_to_object( $rate_type, array( 'flat', 'percentage' ), $default );
	}

	/**
	 * Sanitizes a rate into an object if necessary.
	 *
	 * @since 2.1.9
	 *
	 * @param int|array|\stdClass $rate Rate value, array, or object.
	 * @return \stdClass Rate object.
	 */
	public function sanitize_rate( $rate ) {
		$default = affiliate_wp()->settings->get( 'referral_rate', 20 );

		return $this->convert_param_to_object( $rate, array(), $default );
	}

	/**
	 * Sanitizes a flat rate basis into an object if necessary.
	 *
	 * @since 2.3
	 *
	 * @param int|array|\stdClass $flat_rate_basis Flat Rate Basis value, array, or object.
	 * @return \stdClass Flat Rate Basis object.
	 */
	public function sanitize_flat_rate_basis( $flat_rate_basis ) {
		$default = affwp_get_affiliate_flat_rate_basis();

		return $this->convert_param_to_object( $flat_rate_basis, array(), $default );
	}

	/**
	 * Sanitizes a string-based payment email into an object if necessary.
	 *
	 * @since 2.1.9
	 *
	 * @param string|array|\stdClass $payment_email Payment email string, array, or object.
	 * @return \stdClass Payment email object.
	 */
	public function sanitize_payment_email( $payment_email ) {
		return $this->convert_param_to_object( $payment_email );
	}

	/**
	 * Checks if ID for the current affiliate and the affiliate ID passed in the request match.
	 *
	 * @since 2.6.2
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool True if the user is permitted to perform this request, otherwise false.
	 */
	public function check_affiliate_self_request( $request ) {
		$id       = $request->get_param( 'id' );
		$username = $request->get_param( 'username' );

		if ( $this->request_has_param( $request, 'id' ) && ! empty( $id ) ) {

			$request['affiliate_id'] = (int) $id;

		} elseif ( $this->request_has_param( $request, 'username' ) && ! empty( $username ) ) {

			$affiliate = affwp_get_affiliate( $username );

			if ( $affiliate ) {
				$request['affiliate_id'] = (int) $affiliate->affiliate_id;
			}
		}

		return parent::check_affiliate_self_request( $request );
	}

}
