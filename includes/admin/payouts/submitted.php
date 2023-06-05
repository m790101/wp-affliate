<?php
/**
 * Admin: Payout Submitted View
 *
 * @package    AffiliateWP
 * @subpackage Admin/Payouts
 * @copyright  Copyright (c) 2019, Sandhills Development, LLC
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.4
 */

$payouts   = array();
$payout_id = isset( $_REQUEST['payouts_service_payout_id'] ) ? intval( $_REQUEST['payouts_service_payout_id'] ) : 0;
?>

<div class="wrap">

	<h2><?php esc_html_e( 'Payout submitted successfully!', 'affiliate-wp' ); ?></h2>

	<?php
	/**
	 * Fires at the top of the 'Submitted Payout' page, just inside the opening div.
	 *
	 * @since 2.4
	 *
	 * @param int $payout_id Payouts service payout ID or 0 if undefined.
	 */
	do_action( 'affwp_submitted_payout_top', $payout_id );
	?>

	<p>
		<?php esc_html_e( 'Your payout request is now being processed and your affiliates will be notified once the funds have been deposited.', 'affiliate-wp' ); ?>
	</p>

	<p>
		<?php esc_html_e( 'For affiliates located in the United States, it takes 3-7 days for the funds transfer to be completed. For affiliates not located in the United States, it can take 1-2 weeks to complete the transfer of funds.', 'affiliate-wp' ); ?>
	</p>

	<?php
	if ( $payout_id ) {

		$payout_args = array(
			'service_id' => $payout_id,
			'status'     => '',
			'number'     => -1,
		);

		$payouts = affiliate_wp()->affiliates->payouts->get_payouts( $payout_args );
	}
	?>

	<?php if ( ! empty( $payouts ) ) : ?>

		<h2><?php esc_html_e( 'Affiliates Paid', 'affiliate-wp' ); ?></h2>

		<table class="wp-list-table widefat fixed striped payouts">
			<thead>
				<tr>
					<th class="manage-column column-payout_id column-primary" id="payout_id" scope="col"><?php _e( 'Payout ID', 'affiliate-wp' ); ?></th>
					<th class="manage-column column-affiliate" id="affiliate" scope="col"><?php _e( 'Affiliate', 'affiliate-wp' ); ?></th>
					<th class="manage-column column-amount" id="amount" scope="col"><?php _e( 'Amount', 'affiliate-wp' ); ?></th>
					<th class="manage-column column-service_account" scope="col"><?php _e( 'Payout Account', 'affiliate-wp' ); ?></th>
				</tr>
			</thead>
			<tbody data-wp-lists="list:payouts" id="the-list">
				<?php foreach ( $payouts as $payout ) : ?>
					<tr>
					<td class="payout_id column-amount has-row-actions column-primary" data-colname="Payout ID">
						<?php echo esc_html( $payout->payout_id ); ?>
						<button class="toggle-row" type="button">
							<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'affiliate-wp' ); ?></span>
						</button>
					</td>
					<td class="affiliate column-affiliate" data-colname="Affiliate">
						<?php
						$affiliate_id = $payout->affiliate_id;

						$url = affwp_admin_url( 'affiliates', array(
							'action'       => 'view_affiliate',
							'affiliate_id' => $affiliate_id
						) );
						$name      = affiliate_wp()->affiliates->get_affiliate_name( $affiliate_id );
						$affiliate = affwp_get_affiliate( $affiliate_id );

						if ( $affiliate && $name ) {
							$value = sprintf( '<a href="%1$s" target="_blank">%2$s</a> (ID: %3$s)',
								esc_url( $url ),
								esc_html( $name ),
								esc_html( $affiliate->ID )
							);
						} else {
							$value = __( '(user deleted)', 'affiliate-wp' );
						}
						echo $value;
						?>
					</td>
					<td class="amount column-amount" data-colname="Amount">
						<?php echo affwp_currency_filter( affwp_format_amount( $payout->amount ) ); ?>
					</td>
					<td class="amount column-service_account" data-colname="Payout Account">
						<?php echo esc_html( $payout->service_account ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th class="manage-column column-payout_id column-primary" id="payout_id" scope="col"><?php esc_html_e( 'Payout ID', 'affiliate-wp' ); ?></th>
					<th class="manage-column column-affiliate" id="affiliate" scope="col"><?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?></th>
					<th class="manage-column column-amount" id="amount" scope="col"><?php esc_html_e( 'Amount', 'affiliate-wp' ); ?></th>
					<th class="manage-column column-service_account" scope="col"><?php esc_html_e( 'Payout Account', 'affiliate-wp' ); ?></th>
				</tr>
			</tfoot>
		</table>

	<?php endif; ?>

	<p>
		<?php esc_html_e( 'An email will be sent to you and your affiliates when the payouts have been completed. You have also been sent an email with a confirmation of the payout details and a link to the paid invoice for your records.', 'affiliate-wp' ); ?>
	</p>

	<?php
	/**
	 * Fires at the end of the 'Submitted Payout' page, just inside the closing div.
	 *
	 * @since 2.4
	 *
	 * @param int $payout_id Payouts service payout ID or 0 if undefined.
	 */
	do_action( 'affwp_submitted_payout_bottom' );
	?>

</div>