<?php
/**
 * Integrations: WP Invoice
 *
 * @package     AffiliateWP
 * @subpackage  Integrations
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

/**
 * Implements an integration for WP Invoice.
 *
 * @since 1.2
 *
 * @see Affiliate_WP_Base
 */
class Affiliate_WP_Invoice extends Affiliate_WP_Base {

	/**
	 * The context for referrals. This refers to the integration that is being used.
	 *
	 * @access  public
	 * @since   1.2
	 */
	public $context = 'wp-invoice';

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.7.5
	*/
	public function init() {

		add_action( 'wpi_successful_payment', array( $this, 'track_successful_payment' ) );
		add_action( 'wpi_object_updated', array( $this, 'track_refund' ), 10, 2 );
	}

	/**
	 * Track Successful Payment
	 * @param $invoice
	 * @since 1.7.5
	 */
	public function track_successful_payment( $invoice ) {

		if( $this->was_referred() ) {

			$new_invoice = new WPI_Invoice();
			$new_invoice->load_invoice("id={$invoice->data['invoice_id']}");

			$this->insert_pending_referral(
				$new_invoice->data['total_payments'] ? $new_invoice->data['total_payments'] : $new_invoice->data['net'],
				$new_invoice->data['invoice_id'],
				$new_invoice->data['post_title']
			);

			if ( $new_invoice->data['post_status'] == 'paid' ) {
				$this->complete_referral( $new_invoice->data['invoice_id'] );
			}

		}
	}

	/**
	 * Handle refunds
	 * @param $old_invoice
	 * @param $new_post
	 * @since 1.7.5
	 */
	public function track_refund( $old_invoice, $new_post ) {

		if ( $new_post['post_status'] !== 'refund' ) {
			return;
		}

		if( ! affiliate_wp()->settings->get( 'revoke_on_refund' ) ) {
			return;
		}

		$this->reject_referral( $old_invoice['invoice_id'] );

	}

	/**
	 * Runs the check necessary to confirm this plugin is active.
	 *
	 * @since 2.5
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	function plugin_is_active() {
		return class_exists( 'WPI_Invoice' );
	}
}

	new Affiliate_WP_Invoice;