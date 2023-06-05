<?php
/**
 * Admin: New Payout View
 *
 * @package    AffiliateWP
 * @subpackage Admin/Payouts
 * @copyright  Copyright (c) 2019, Sandhills Development, LLC
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.4
 */
?>

<div class="wrap">

	<h2><?php esc_html_e( 'Pay Affiliates', 'affiliate-wp' ); ?></h2>

	<?php
	/**
	 * Fires at the top of the 'New Payout' page, just inside the opening div.
	 *
	 * @since 2.4
	 */
	do_action( 'affwp_new_payout_top' );
	?>

	<p><?php esc_html_e( 'Pay your affiliates via the chosen payout method for all their unpaid referrals in the specified timeframe.', 'affiliate-wp' ); ?></p>

	<form method="post" id="affwp_new_payout">

		<table id="affwp_payout" class="form-table">

			<tr class="form-row">

				<th scope="row">
					<?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?>
				</th>

				<td>
					<span class="affwp-ajax-search-wrap">
						<input type="text" name="user_name" id="user_name" class="affwp-user-search" data-affwp-status="any" autocomplete="off" placeholder="<?php esc_html_e( 'Affiliate name', 'affiliate-wp' ); ?>" />
					</span>
					<p class="search-description description"><?php esc_html_e( 'To pay a specific affiliate, enter the affiliate&#8217;s login name, first name, or last name. Leave blank to pay all affiliates.', 'affiliate-wp' ); ?></p>
				</td>

			</tr>

			<tr class="form-row">

				<th scope="row">
					<?php esc_html_e( 'Start Date', 'affiliate-wp' ); ?>
				</th>

				<td>
					<input type="text" class="affwp-datepicker" autocomplete="off" name="from" placeholder="<?php esc_html_e( 'From - mm/dd/yyyy', 'affiliate-wp' ); ?>"/>
					<p class="description"><?php esc_html_e( 'Referrals start date', 'affiliate-wp' ); ?></p>
				</td>

			</tr>

			<tr class="form-row">

				<th scope="row">
					<?php esc_html_e( 'End Date', 'affiliate-wp' ); ?>
				</th>

				<td>
					<input type="text" class="affwp-datepicker" autocomplete="off" name="to" placeholder="<?php esc_html_e( 'To - mm/dd/yyyy', 'affiliate-wp' ); ?>"/>
					<p class="description"><?php esc_html_e( 'Referrals end date', 'affiliate-wp' ); ?></p>
				</td>

			</tr>

			<tr class="form-row">

				<th scope="row">
					<?php esc_html_e( 'Minimum Earnings', 'affiliate-wp' ); ?>
				</th>

				<td>
					<input type="text" class="affwp-text" name="minimum" placeholder="<?php esc_attr_e( 'Minimum amount', 'affiliate-wp' ); ?>"/>
					<p class="description"><?php esc_html_e( 'The minimum earnings for each affiliate for a payout to be processed.', 'affiliate-wp' ); ?></p>
				</td>

			</tr>

			<tr class="form-row">

				<th scope="row">
					<?php esc_html_e( 'Payout Method', 'affiliate-wp' ); ?>
				</th>

				<td>
					<?php $payout_methods = affwp_get_payout_methods(); ?>
					<?php foreach ( $payout_methods as $payout_method => $label ) : ?>
						<?php $disabled = affwp_is_payout_method_enabled( $payout_method ) ? '' : 'disabled'; ?>
						<label for="<?php echo esc_attr( $payout_method ); ?>">
							<input type="radio" name="payout_method" id="<?php echo esc_attr( $payout_method ); ?>" value="<?php echo esc_attr( $payout_method ); ?>" <?php echo esc_attr( $disabled ); ?> required>
							<?php echo affwp_get_payout_method_label( $payout_method ); ?>
						</label><br>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Choose the payout method for this payout.', 'affiliate-wp' ); ?></p>
				</td>

			</tr>

		</table>

		<?php echo wp_nonce_field( 'affwp_preview_payout_nonce', 'affwp_preview_payout_nonce' ); ?>
		<input type="hidden" name="affwp_action" value="preview_payout" />

		<?php submit_button( __( 'Preview Payout', 'affiliate-wp' ), 'primary', 'submit' ); ?>

	</form>

	<?php
	/**
	 * Fires at the end of the 'New Payout' page, just inside the closing table tag.
	 *
	 * @since 2.4
	 */
	do_action( 'affwp_new_payout_end' );
	?>

</div>
