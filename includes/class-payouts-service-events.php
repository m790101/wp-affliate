<?php
/**
 * Payouts Service Events Class
 *
 * @package     AffiliateWP
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */
namespace AffWP\Affiliate\Payout;

/**
 * The Service_Events class.
 *
 * This class defines all primary methods by which AffiliateWP handles all events from the Payouts Service.
 *
 * @since  2.4
 */
class Service_Events {

	/**
	 * Constructor.
	 *
	 * @access  public
	 * @since   2.4
	 */
	public function __construct() {
		add_action( 'affwp_payouts_service_update_payout_status',          array( $this, 'update_payout_status'          ) );
		add_action( 'affwp_payouts_service_update_kyc_status',             array( $this, 'update_kyc_status'             ) );
		add_action( 'affwp_payouts_service_update_payout_service_account', array( $this, 'update_payout_service_account' ) );
	}

	/**
	 * Updates a payout status when it paid out to the affiliate.
	 *
	 * @since 2.4
	 *
	 * @param array $data {
	 *     Optional. Array of arguments for updating the payout status for a payout. Default empty array.
	 *
	 *     @type int    $payout_id     AffiliateWP payout ID.
	 *     @type int    $affiliate_id  Affiliate ID.
	 *     @type string $status        Payout status.
	 *     @type string $token         Payout token.
	 *     @type string $description   Description for the payout.
	 * }
	 *
	 * @return void
	 */
	public function update_payout_status( $data = array() ) {

		$defaults = array(
			'payout_id'    => 0,
			'affiliate_id' => 0,
			'status'       => '',
			'token'        => '',
			'description'  => '',
		);

		$args = wp_parse_args( $data, $defaults );

		if ( empty( $args['payout_id'] ) || empty( $args['affiliate_id'] ) || empty( $args['status'] ) || empty( $args['token'] ) ) {
			wp_send_json_error();
		}

		$vendor_id  = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
		$access_key = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );

		$auth_check = base64_encode( $access_key . ':' . $vendor_id );

		if ( $auth_check !== $args['token'] ) {
			wp_send_json_error();
		}

		$payout = affiliate_wp()->affiliates->payouts->get_payouts( array(
			'service_id'   => intval( $args['payout_id'] ),
			'affiliate_id' => intval( $args['affiliate_id'] ),
			'status'       => array( 'processing', 'failed', 'paid' ),
			'number'       => 1,
		) );

		if ( ! empty( $payout[0] ) ) {

			$update_args = array(
				'status' => sanitize_text_field( $args['status'] ),
			);

			if ( ! empty( $args['description'] ) ) {
				$update_args['description'] = sanitize_text_field( $args['description'] );
			}

			affiliate_wp()->affiliates->payouts->update( $payout[0]->payout_id, $update_args );

			wp_send_json_success();

		}

		wp_send_json_error();

	}

	/**
	 * Updates the KYC status for an affiliate.
	 *
	 * @since 2.4
	 *
	 * @param array $data {
	 *     Optional. Array of arguments for updating the kyc status for an affiliate.
	 *     Default empty array.
	 *
	 *     @type int    $affiliate_id Affiliate ID.
	 *     @type string $kyc_status   KYC Status.
	 *     @type string $kyc_link     KYC Link.
	 *     @type string $token        Payout token.
	 * }
	 * @return void
	 */
	public function update_kyc_status( $data = array() ) {

		$defaults = array(
			'affiliate_id' => 0,
			'kyc_status'   => '',
			'kyc_link'     => '',
			'token'        => '',
		);

		$args = wp_parse_args( $data, $defaults );

		if ( empty( $args['affiliate_id'] ) || empty( $args['kyc_status'] ) || empty( $args['token'] ) ) {
			wp_send_json_error();
		}

		$vendor_id  = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
		$access_key = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );

		$auth_check = base64_encode( $access_key . ':' . $vendor_id );

		if ( $auth_check !== $args['token'] ) {
			wp_send_json_error();
		}

		$affiliate = affwp_get_affiliate( intval( $args['affiliate_id'] ) );

		if ( $affiliate ) {

			$kyc_status = sanitize_text_field( $args['kyc_status'] );

			$account_meta = affwp_get_affiliate_meta( $affiliate->ID, 'payouts_service_account', true );

			if ( $account_meta ) {

				if ( 'required' === $kyc_status ) {

					$kyc_link = esc_url_raw( $args['kyc_link'] );

					$account_meta['kyc_link']   = $kyc_link;
					$account_meta['kyc_status'] = $kyc_status;
					affwp_update_affiliate_meta( $affiliate->ID, 'payouts_service_account', $account_meta );

				} else {

					// Delete kyc link and kyc status since the identity verification has been carried out.
					unset( $account_meta['kyc_link'] );
					unset( $account_meta['kyc_status'] );
					affwp_update_affiliate_meta( $affiliate->ID, 'payouts_service_account', $account_meta );

				}
			}

			wp_send_json_success();
		}

		wp_send_json_error();

	}

	/**
	 * Updates the service account for a payout.
	 *
	 * @since 2.6.8
	 *
	 * @param array $data {
	 *     Optional. Array of arguments for updating the service account for a payout.
	 *     Default empty array.
	 *
	 *     @type int    $affiliate_id    Affiliate ID.
	 *     @type int    $payout_id       Payouts Service Payout ID.
	 *     @type string $service_account Service account.
	 *     @type string $payout_status   Payout status.
	 *     @type string $token           Payouts Service token.
	 * }
	 * @return void
	 */
	public function update_payout_service_account( $data = array() ) {

		$defaults = array(
			'affiliate_id'    => 0,
			'payout_id'       => 0,
			'service_account' => '',
			'status'          => '',
			'token'           => '',
		);

		$args = wp_parse_args( $data, $defaults );

		if ( empty( $args['affiliate_id'] ) || empty( $args['payout_id'] ) || empty( $args['service_account'] ) || empty( $args['token'] ) ) {
			wp_send_json_error( null, 400 );
		}

		$vendor_id  = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
		$access_key = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );

		$auth_check = base64_encode( $access_key . ':' . $vendor_id );

		if ( $auth_check !== $args['token'] ) {
			wp_send_json_error( null, 401 );
		}

		$payout = affiliate_wp()->affiliates->payouts->get_payouts( array(
			'service_id'   => intval( $args['payout_id'] ),
			'affiliate_id' => intval( $args['affiliate_id'] ),
			'status'       => array( 'processing', 'failed', 'paid' ),
			'number'       => 1,
		) );

		if ( ! empty( $payout[0] ) ) {

			$update_args = array(
				'service_account' => sanitize_text_field( $args['service_account'] ),
			);

			if ( 'paid' !== $payout[0]->status && ! empty( $args['status'] ) && array_key_exists( $args['status'], affwp_get_payout_statuses() ) ) {
				$update_args['status'] = sanitize_text_field( $args['status'] );
			}

			affiliate_wp()->affiliates->payouts->update( $payout[0]->payout_id, $update_args );
		}

		wp_send_json_success( null, 200 );
	}
}
