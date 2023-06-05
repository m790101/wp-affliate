<?php
/**
 * REST: Campaigns Endpoints
 *
 * @package     AffiliateWP
 * @subpackage  REST
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6.2
 */

namespace AffWP\Campaign\REST\v1;

use \AffWP\REST\v1\Controller;

/**
 * Implements REST routes and endpoints for Campaigns.
 *
 * @since 2.6.2
 *
 * @see \AffWP\REST\Controller
 */
class Endpoints extends Controller {

	/**
	 * Object type.
	 *
	 * @since 2.6.2
	 * @var   string
	 */
	public $object_type = 'affwp_campaign';

	/**
	 * Route base for campaigns.
	 *
	 * @since 2.6.2
	 * @var   string
	 */
	public $rest_base = 'campaigns';

	/**
	 * Registers Campaign routes.
	 *
	 * @since 2.6.2
	 */
	public function register_routes() {
		// PHP 5.3 compat.
		$instance = $this;

		// /campaigns
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'args'                => $this->get_collection_params(),
				'permission_callback' => function( \WP_REST_Request $request ) use ( $instance ) {
					$permitted = $instance->check_affiliate_self_request( $request );

					if ( false === $permitted ) {
						$permitted = current_user_can( 'manage_affiliate_options' );
					}

					return $permitted;
				}
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

	}

	/**
	 * Base endpoint to retrieve all campaigns.
	 *
	 * @since 2.6.2
	 * @since 2.7   Items are only processed for output if retrieving all fields. Added support for
	 *              retrieving multiple fields.
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Array of campaigns, otherwise WP_Error.
	 */
	public function get_items( $request ) {

		$args = array();

		$args['number']           = isset( $request['number'] )           ? $request['number'] : 20;
		$args['offset']           = isset( $request['offset'] )           ? $request['offset'] : 0;
		$args['affiliate_id']     = isset( $request['affiliate_id'] )     ? $request['affiliate_id'] : 0;
		$args['campaign']         = isset( $request['campaign'] )         ? $request['campaign'] : 0;
		$args['campaign_compare'] = isset( $request['campaign_compare'] ) ? $request['campaign_compare'] : '';
		$args['conversion_rate']  = isset( $request['conversion_rate'] )  ? $request['conversion_rate'] : '';
		$args['hash']             = isset( $request['hash'] )             ? $request['hash'] : '';
		$args['rest_id']          = isset( $request['rest_id'] )          ? $request['rest_id'] : '';
		$args['rate_compare']     = isset( $request['rate_compare'] )     ? $request['rate_compare'] : '';
		$args['order']            = isset( $request['order'] )            ? $request['order'] : 'ASC';
		$args['orderby']          = isset( $request['orderby'] )          ? $request['orderby'] : '';

		if ( is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $request['filter'] );
		}

		$args['fields'] = $this->parse_fields_for_request( $request );

		/**
		 * Filters the query arguments used to retrieve campaigns in a REST request.
		 *
		 * @since 2.6.2
		 *
		 * @param array            $args    Arguments.
		 * @param \WP_REST_Request $request Request.
		 */
		$args = apply_filters( 'affwp_rest_campaigns_query_args', $args, $request );

		$campaigns = affiliate_wp()->campaigns->get_campaigns( $args );

		if ( empty( $campaigns ) ) {
			$campaigns = new \WP_Error(
				'no_campaigns',
				'No campaigns were found.',
				array( 'status' => 404 )
			);
		} elseif ( '*' === $args['fields'] ) {
			array_map( function( $campaign ) use ( $request ) {
				$campaign = $this->process_for_output( $campaign, $request );
				return $campaign;
			}, $campaigns );
		}

		if ( isset( $request['response_callback'] ) && is_callable( $request['response_callback'] ) ) {
			$campaigns = call_user_func( $request['response_callback'], $campaigns, $request, 'campaigns' );
		}

		return $this->response( $campaigns );
	}

	/**
	 * Retrieves the collection parameters for campaigns.
	 *
	 * @since 2.6.2
	 *
	 * @return array Collection parameters for campaigns.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		/*
		 * Pass top-level get_campaigns() args as query vars:
		 * /campaigns/?campaign=foo&order=desc
		 */
		$params['affiliate_id'] = array(
			'description' => __( 'The affiliate ID the campaign belongs to.', 'affiliate-wp' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default' => array(),
		);

		$params['visits'] = array(
			'description'       => __( 'The total number of (non-unique) visits recorded for the campaign.', 'affiliate-wp' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param );
			},
		);

		$params['unique_visits'] = array(
			'description'       => __( 'The number of visits (unique by referrer) recorded for the campaign.', 'affiliate-wp' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param );
			},
		);

		$params['referrals'] = array(
			'description'       => __( 'The number of referrals recorded against the campaign.', 'affiliate-wp' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param );
			},
		);

		$params['hash'] = array(
			'description'       => __( 'The unique hash for this campaign.', 'affiliate-wp' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param );
			},
		);

		$params['conversion_rate'] = array(
			'description'       => __( 'The conversion rate for the campaign (ratio of visits divided by referrals).', 'affiliate-wp' ),
			'sanitize_callback' => 'floatval',
			'validate_callback' => function( $param, $request, $key ) {
				return is_float( $param );
			},
		);

		$params['orderby'] = array(
			'description'       => __( 'Campaigns view column to order by.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return in_array( $param, array( 'conversion_rate', 'visits', 'unique_visits', 'referrals' ) );
			},
		);

		/*
		 * Pass any valid get_campaigns() args via filter:
		 * /campaigns/?filter[affiliate_id]=123&filter[order]=desc
		 */
		$params['filter'] = array(
			'description' => __( 'Use any get_campaigns() arguments to modify the response.', 'affiliate-wp' )
		);

		return $params;
	}

	/**
	 * Retrieves the schema for a single campaign, conforming to JSON Schema.
	 *
	 * @since 2.6.2
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => $this->get_object_type(),
			'type'       => 'object',
			// Base properties for every campaign.
			'properties' => array(
				'affiliate_id'    => array(
					'description' => __( 'The affiliate ID the campaign belongs to.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'visits'          => array(
					'description' => __( 'The total number of (non-unique) visits recorded for the campaign.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'unique_visits'   => array(
					'description' => __( 'The number of visits (unique by referrer) recorded for the campaign.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'referrals'       => array(
					'description' => __( 'The number of referrals recorded against the campaign.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'hash'       => array(
					'description' => __( 'The unique hash for this campaign.', 'affiliate-wp' ),
					'type'        => 'integer',
				),
				'conversion_rate' => array(
					'description' => __( 'The conversion rate for the campaign (ratio of visits divided by referrals).', 'affiliate-wp' ),
					'type'        => 'float',
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

}
