<div id="affwp-affiliate-dashboard-payouts" class="affwp-tab-content">

	<h4><?php _e( 'Referral Payouts', 'affiliate-wp' ); ?></h4>

	<?php
	$affiliate_id            = affwp_get_affiliate_id();
	$payouts_service_enabled = (bool) affiliate_wp()->settings->get( 'enable_payouts_service', false );

	$per_page = 30;
	$page     = affwp_get_current_page_number();
	$count    = affiliate_wp()->affiliates->payouts->count( array( 'affiliate_id' => $affiliate_id ) );
	$pages    = absint( ceil( $count / $per_page ) );
	$payouts  = affiliate_wp()->affiliates->payouts->get_payouts(
		array(
			'number'       => $per_page,
			'status'       => array( 'processing', 'paid' ),
			'offset'       => $per_page * ( $page - 1 ),
			'affiliate_id' => $affiliate_id,
		)
	);
	?>

	<?php
	/**
	 * Fires right before displaying the affiliate payouts dashboard table.
	 *
	 * @since 1.9.4
	 *
	 * @param int $affiliate_id Affiliate ID.
	 */
	do_action( 'affwp_payouts_dashboard_before_table', $affiliate_id ); ?>

	<table id="affwp-affiliate-dashboard-payouts" class="affwp-table affwp-table-responsive" aria-describedby="affwp-table-summary">
		<thead>
			<tr>
				<th class="payout-date"><?php _e( 'Date', 'affiliate-wp' ); ?></th>
				<th class="payout-amount"><?php _e( 'Amount', 'affiliate-wp' ); ?></th>
				<th class="payout-method"><?php _e( 'Payout Method', 'affiliate-wp' ); ?></th>
				<?php if ( true === $payouts_service_enabled ): ?>
					<th class="payout-account"><?php _e( 'Payout Account', 'affiliate-wp' ); ?></th>
				<?php endif; ?>
				<th class="payout-status"><?php _e( 'Status', 'affiliate-wp' ); ?></th>
				<?php if ( true === $payouts_service_enabled ): ?>
					<th class="payout-account"><?php _e( 'Estimated Arrival Date', 'affiliate-wp' ); ?></th>
				<?php endif; ?>
				<?php
				/**
				 * Fires right after displaying the last affiliate payouts dashboard table header.
				 *
				 * @since 1.9.4
				 *
				 * @param int $affiliate_id Affiliate ID.
				 */
				do_action( 'affwp_payouts_dashboard_th' ); ?>
			</tr>
		</thead>

		<tbody>
			<?php if ( $payouts ) : ?>

				<?php foreach ( $payouts as $payout ) : ?>
					<tr>
						<td data-th="<?php _e( 'Date', 'affiliate-wp' ); ?>">
							<?php echo esc_html( $payout->date_i18n( 'datetime' ) ); ?>
						</td>
						<td data-th="<?php _e( 'Amount', 'affiliate-wp' ); ?>">
							<?php echo affwp_currency_filter( affwp_format_amount( $payout->amount ) ); ?>
						</td>
						<td data-th="<?php _e( 'Payout Method', 'affiliate-wp' ); ?>">
							<?php echo esc_html( $payout->payout_method ); ?>
						</td>
						<?php if ( true === $payouts_service_enabled ): ?>
							<td data-th="<?php _e( 'Payout Account', 'affiliate-wp' ); ?>">
								<?php echo esc_html( $payout->service_account ); ?>
							</td>
						<?php endif; ?>
						<td data-th="<?php _e( 'Status', 'affiliate-wp' ); ?>">
							<?php echo esc_html( affwp_get_payout_status_label( $payout ) ); ?>
						</td>
						<?php if ( true === $payouts_service_enabled ): ?>
							<td data-th="<?php _e( 'Estimated Arrival Date', 'affiliate-wp' ); ?>">
								<?php
									if ( 'paid' !== $payout->status && ! empty( $payout->service_account ) ) {
										$arrival_date = strtotime( $payout->date . '+ 14 days' );
										echo affwp_date_i18n( $arrival_date );
									}
								?>
							</td>
						<?php endif; ?>
						<?php
						/**
						 * Fires right after displaying the last affiliate payouts dashboard table data.
						 *
						 * @since 1.9.4
						 *
						 * @param object $payout Payout object.
						 */
						do_action( 'affwp_payouts_dashboard_td', $payout ); ?>
					</tr>
				<?php endforeach; ?>

			<?php else : ?>

				<tr>
					<td class="affwp-table-no-data" colspan="<?php echo true === $payouts_service_enabled ? 6 : 4; ?>"><?php _e( 'None of your referrals have been paid out yet.', 'affiliate-wp' ); ?></td>
				</tr>

			<?php endif; ?>
		</tbody>
	</table>

	<?php
	/**
	 * Fires right after displaying the affiliate payouts dashboard table.
	 *
	 * @since 1.9.4
	 *
	 * @param int $affiliate_id Affiliate ID.
	 */
	do_action( 'affwp_payouts_dashboard_after_table', $affiliate_id ); ?>

	<?php if ( $pages > 1 ) : ?>

		<p class="affwp-pagination">
			<?php
			echo paginate_links(
				array(
					'current'      => $page,
					'total'        => $pages,
					'add_fragment' => '#affwp-affiliate-dashboard-payouts',
					'add_args'     => array(
						'tab' => 'payouts',
					),
				)
			);
			?>
		</p>

	<?php endif; ?>

</div>
