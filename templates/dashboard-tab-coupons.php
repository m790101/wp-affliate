<?php
$affiliate_id = affwp_get_affiliate_id();
$all_coupons  = affwp_get_affiliate_coupons( $affiliate_id );
?>

<div id="affwp-affiliate-dashboard-coupons" class="affwp-tab-content">

	<h4><?php _e( 'Coupons', 'affiliate-wp' ); ?></h4>

	<?php
	/**
	 * Fires at the top of the Coupons dashboard tab.
	 *
	 * @since 2.6
	 *
	 * @param int $affiliate_id Affiliate ID of the currently logged-in affiliate.
	 */
	do_action( 'affwp_affiliate_dashboard_coupons_top', $affiliate_id );
	?>

	<?php
	/**
	 * Fires right before displaying the affiliate coupons dashboard table.
	 *
	 * @since 2.6
	 *
	 * @param int $affiliate_id Affiliate ID.
	 */
	do_action( 'affwp_coupons_dashboard_before_table', $affiliate_id ); ?>

	<?php if ( ! empty( $all_coupons ) ) : ?>
		<table class="affwp-table">
			<thead>
				<tr>
					<th><?php _e( 'Coupon Code', 'affiliate-wp' ); ?></th>
					<th><?php _e( 'Amount', 'affiliate-wp' ); ?></th>
					<?php
					/**
					 * Fires right after displaying the last affiliate coupons dashboard table header.
					 *
					 * @since 2.6
					 *
					 * @param int $affiliate_id Affiliate ID.
					 */
					do_action( 'affwp_coupons_dashboard_th' ); ?>
				</tr>
			</thead>

			<tbody>

			<?php if ( $all_coupons ) :
				$coupon_details = array();
				foreach ( $all_coupons as $type => $coupons ) :
					foreach ( $coupons as $id => $coupon ) :
						$coupon_details = array(
							'id'          => $id,
							'type'        => $type,
							'code'        => $coupon['code'],
							'amount'      => $coupon['amount'],
							'integration' => $coupon['integration']
						);
						?>
						<tr>
							<td data-th="<?php esc_attr_e( 'Coupon Code', 'affiliate-wp' ); ?>">
								<?php
									/**
									 * Filters the coupon code table cell data.
									 *
									 * @since 2.8
									 *
									 * @param string $coupon_code Coupon code.
									 * @param array $coupon_details {
									 *     Coupon details.
									 *
									 *     @type int     $id          Coupon ID.
									 *     @type sting   $type        Coupon type (manual or dynamic).
									 *     @type string  $code        Coupon code.
									 *     @type array   $amount      Coupon amount.
									 *     @type string  $integration Integration.
									 * }
									 * @param int $affiliate_id Affiliate ID.
									 */
									$coupon_code = apply_filters( 'affwp_coupons_dashboard_code_td', $coupon['code'], $coupon_details, $affiliate_id );
									// If unchanged, escape it.
									if ( $coupon['code'] === $coupon_code ) {
										$coupon_code = esc_html( $coupon_code );
									}

									echo $coupon_code;
								?>
							</td>
							<td data-th="<?php esc_attr_e( 'Amount', 'affiliate-wp' ); ?>"><?php echo $coupon['amount']; ?></td>
							<?php
							/**
							 * Fires right after displaying the last affiliate coupons dashboard table data.
							 *
							 * @since 2.6
							 * @since 2.8 Added $coupon_details parameter.
							 *
							 * @param array $coupons Coupons array.
							 * @param array $coupon_details {
							 *     Coupon details.
							 *
							 *     @type int     $id          Coupon ID.
							 *     @type sting   $type        Coupon type (manual or dynamic).
							 *     @type string  $code        Coupon code.
							 *     @type array   $amount      Coupon amount.
							 *     @type string  $integration Integration.
							 * }
							 */
							do_action( 'affwp_coupons_dashboard_td', $coupon, $coupon_details ); ?>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>

			</tbody>
		</table>
	<?php else : ?>
		<p><?php _e( 'There are currently no coupon codes to display.', 'affiliate-wp' ); ?></p>
	<?php endif; ?>

	<?php
	/**
	 * Fires right after displaying the affiliate coupons dashboard table.
	 *
	 * @since 2.6
	 *
	 * @param int $affiliate_id Affiliate ID.
	 */
	do_action( 'affwp_coupons_dashboard_after_table', $affiliate_id ); ?>

</div>
