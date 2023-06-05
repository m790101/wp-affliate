<?php
/**
 * Integrations: Membermouse
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Implements an integration for Membermouse.
 *
 * @since 1.2
 *
 * @see Affiliate_WP_Base
 */
class Affiliate_WP_Membermouse extends Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context = 'membermouse';

	public function init() {

		add_action( 'mm_member_add',         array( $this, 'add_referral_on_free' ),      10    );
		add_action( 'mm_commission_initial', array( $this, 'add_referral' ),              10    );
		add_action( 'mm_refund_issued',      array( $this, 'revoke_referral_on_refund' ), 10    );

		add_filter( 'affwp_referral_reference_column', array( $this, 'reference_link' ),  10, 2 );
	}

	public function add_referral_on_free( $member_data ) {
		// Check if it was referred.
		if( ! $this->was_referred() ) {
			return;
		}

		// Just a fake order number so we can explode it and get the user ID later
		$reference = $member_data['member_id'] . '|0';

		// Create draft referral.
		$referral_id = $this->insert_draft_referral(
			$this->affiliate_id,
			array(
				'reference' => $reference,
			)
		);
		if ( ! $referral_id ) {
			$this->log( 'Draft referral creation failed.' );
			return;
		}

		// Confirm it's a free membership.
		$membership = new MM_MembershipLevel( $member_data['membership_level'] );
		if( ! $membership->isFree() ) {
			$this->log( 'Referral not created because membership is not free.' );
			$this->mark_referral_failed( $referral_id );
			return;
		}

		// Customers cannot refer themselves.
		if ( $this->is_affiliate_email( $member_data['email'] ) ) {
			$this->log( 'Referral not created because affiliate\'s own account was used.' );
			$this->mark_referral_failed( $referral_id );
			return;
		}

		// Hydrates the previously created referral.
		$this->hydrate_referral(
			$referral_id,
			array(
				'status'      => 'pending',
				'amount'      => 0,
				'description' => $member_data['membership_level_name'],
			)
		);
		$this->log( sprintf( 'Membermouse referral #%d updated to pending successfully.', $referral_id ) );
	}

	public function add_referral( $affiliate_data ) {

		// get affiliate.
		$this->affiliate_id = $affiliate_data['order_affiliate_id'];
		if ( ! absint( $this->affiliate_id ) && is_string( $this->affiliate_id ) ) {
			$this->affiliate_id = affiliate_wp()->tracking->get_affiliate_id_from_login( $affiliate_data['order_affiliate_id'] );
		}

		// get reference.
		$reference = $affiliate_data['member_id'] . '|' . $affiliate_data['order_number'];

		// Create draft referral.
		$referral_id = $this->insert_draft_referral(
			$this->affiliate_id,
			array(
				'reference' => $reference,
			)
		);
		if ( ! $referral_id ) {
			$this->log( 'Draft referral creation failed.' );
			return;
		}

		// Customers cannot refer themselves.
		$user_info = get_userdata( $affiliate_data['member_id'] );
		if ( $this->is_affiliate_email( $user_info->user_email ) ) {
			$this->log( 'Referral not created because affiliate\'s own account was used.' );
			$this->mark_referral_failed( $referral_id );
			return;
		}

		$products = json_decode( stripslashes( $affiliate_data['order_products'] ) );

		$description = '';

		if ( is_array( $products ) ) {

			$key   = 0;
			$count = count( $products );

			foreach ( $products as $product ) {

				$product = (array) $product;

				$description .= $product['name'];

				if( $key + 1 < $count ) {
					$description .= ', ';
				}

			}

		}

		$referral_total = $this->calculate_referral_amount( $affiliate_data['order_total'], $reference );

		// Hydrates the previously created referral.
		$this->hydrate_referral(
			$referral_id,
			array(
				'status'      => 'pending',
				'amount'      => $referral_total,
				'description' => $description,
			)
		);
		$this->log( sprintf( 'Membermouse referral #%d updated to pending successfully.', $referral_id ) );

		// Complete referral.
		$this->complete_referral( $reference );

	}

	public function revoke_referral_on_refund( $data ) {

		if( ! affiliate_wp()->settings->get( 'revoke_on_refund' ) ) {
			return;
		}

		$reference = $data['member_id'] . '|' . $data['order_number'] . '-' . $data['order_transaction_id'];

		$this->reject_referral( $reference );

	}

	public function reference_link( $reference = 0, $referral ) {

		if( empty( $referral->context ) || $this->context != $referral->context ) {

			return $reference;

		}

		$data = explode( '|', $reference );

		$url = admin_url( 'admin.php?page=manage_members&module=details_transaction_history&user_id=' . $data[0] );

		return '<a href="' . esc_url( $url ) . '">' . $reference . '</a>';
	}

	/**
	 * Runs the check necessary to confirm this plugin is active.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	function plugin_is_active() {
		return class_exists( 'MM_MembershipLevel' );
	}
}

	new Affiliate_WP_Membermouse;