<?php
/**
 * Admin: Preview Payout View
 *
 * @package    AffiliateWP
 * @subpackage Admin/Payouts
 * @copyright  Copyright (c) 2019, Sandhills Development, LLC
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.4
 */

$start               = ! empty( $_REQUEST['from'] ) ? sanitize_text_field( $_REQUEST['from'] ) : false;
$end                 = ! empty( $_REQUEST['to'] ) ? sanitize_text_field( $_REQUEST['to'] ) : false;
$user_name           = ! empty( $_REQUEST['user_name'] ) ? sanitize_text_field( $_REQUEST['user_name'] ) : false;
$payout_method       = ! empty( $_REQUEST['payout_method'] ) ? strtolower( sanitize_text_field( $_REQUEST['payout_method'] ) ) : 'manual';
$payout_method       = array_key_exists( $payout_method, affwp_get_payout_methods() ) ? $payout_method : 'manual';
$payout_method_label = affwp_get_payout_method_label( $payout_method );

if ( $user_name && ( $affiliate = affwp_get_affiliate( $user_name ) ) ) {
	$affiliate_id = $affiliate->ID;
} else {
	$affiliate_id = false;
}

// The minimum payout amount.
$minimum = ! empty( $_REQUEST['minimum'] ) ? sanitize_text_field( affwp_sanitize_amount( $_REQUEST['minimum'] ) ) : 0;

/**
 * Filters the arguments used to retrieve referrals for the payout preview.
 *
 * @since 2.4.3
 *
 * @param array $args Array of get_referrals() arguments.
 */
$args = apply_filters( 'affwp_preview_payout_get_referrals_args', array(
	'status'       => 'unpaid',
	'date'         => array(
		'start' => $start,
		'end'   => $end,
	),
	'number'       => -1,
	'affiliate_id' => $affiliate_id,
) );

// Final  affiliate / referral data to be paid out.
$data = array();

// The affiliates that can't be paid out.
$invalid_affiliates = array();

// Retrieve the referrals from the database.
$referrals = affiliate_wp()->referrals->get_referrals( $args );

$referrals_total = 0;

if ( $referrals ) {

	foreach ( $referrals as $referral ) {

		if ( array_key_exists( $referral->affiliate_id, $invalid_affiliates ) ) {
			continue;
		}

		if ( array_key_exists( $referral->affiliate_id, $data ) ) {

			// Add the amount to an affiliate that already has a referral in the export.
			$amount = $data[ $referral->affiliate_id ]['amount'] + $referral->amount;

			$data[ $referral->affiliate_id ]['amount'] = $amount;

		} else {

			switch ( $payout_method ) {

				case 'manual':
					$data[ $referral->affiliate_id ] = array(
						'amount' => $referral->amount,
					);

					break;

				case 'payouts-service':
					$payout_service_account = affwp_get_payouts_service_account( $referral->affiliate_id );

					if ( false !== $payout_service_account['valid'] ) {

						$data[ $referral->affiliate_id ] = array(
							'amount'     => $referral->amount,
							'account_id' => $payout_service_account['account_id'],
						);

					} else {

						$invalid_affiliates[ $referral->affiliate_id ] = $payout_service_account['status'];

					}

					break;

				default:
					$data[ $referral->affiliate_id ] = array(
						'amount' => $referral->amount,
					);

					break;
			}
		}
	}

	if ( 'payouts-service' === $payout_method ) {
		$payouts_service_data = affwp_validate_payouts_service_payout_data( $data );

		$data                = $payouts_service_data['valid_payout_data'];
		$invalid_payout_data = $payouts_service_data['invalid_payout_data'];

		$invalid_affiliates = array_replace_recursive( $invalid_affiliates, $invalid_payout_data );
	}

	/**
	 * Filters the list of invalid affiliates whose payout can't be processed.
	 *
	 * The dynamic portion of the hook name, `$payout_method` refers to the payout method.
	 *
	 * @since 2.4
	 *
	 * @param array  $invalid_affiliates Invalid affiliates.
	 * @param array  $data               Payout data.
	 * @param string $payout_method      Payout method.
	 */
	$invalid_affiliates = apply_filters( "affwp_preview_payout_invalid_affiliates_{$payout_method}", $invalid_affiliates, $data, $payout_method );

	/**
	 * Filters the payout preview data.
	 *
	 * The dynamic portion of the hook name, `$payout_method` refers to the payout method.
	 *
	 * @since 2.4
	 *
	 * @param array  $data          Payout data.
	 * @param string $payout_method Payout method.
	 */
	$data = apply_filters( "affwp_preview_payout_data_{$payout_method}", $data, $payout_method );

	$payouts = array();

	$i = 0;
	foreach ( $data as $affiliate_id => $payout ) {

		// Ensure the minimum amount was reached and the affiliate is valid.
		if ( ( $minimum > 0 && $payout['amount'] < $minimum ) || in_array( $affiliate_id, $invalid_affiliates ) ) {

			// Ensure the minimum amount was reached.
			unset( $data[ $affiliate_id ] );

			$invalid_affiliates[ $affiliate_id ] = 'minimum_payout';

			// Skip to the next affiliate.
			continue;

		}

		$payouts[ $affiliate_id ] = array( 'amount' => $payout['amount'] );

		$i++;
	}

	$referrals_total = array_sum( wp_list_pluck( $payouts, 'amount' ) );
}

?>

<div class="wrap">

	<h2><?php esc_html_e( 'Preview Payout Request', 'affiliate-wp' ); ?></h2>

	<table id="affwp_payout" class="form-table">

		<tr class="form-row">

			<th scope="row">
				<?php esc_html_e( 'Minimum Amount', 'affiliate-wp' ); ?>
			</th>

			<td>
				<?php echo affwp_currency_filter( affwp_format_amount( $minimum ) ); ?>
			</td>

		</tr>

		<tr class="form-row">

			<th scope="row">
				<?php esc_html_e( 'Referrals Start Date', 'affiliate-wp' ); ?>
			</th>

			<td>
				<?php
				if ( false === $start ) {
					echo affwp_date_i18n( strtotime( '04/07/2014' ) );
				} else {
					echo affwp_date_i18n( strtotime( $start ) );
				}
				?>
			</td>

		</tr>

		<tr class="form-row">

			<th scope="row">
				<?php esc_html_e( 'Referrals End Date', 'affiliate-wp' ); ?>
			</th>

			<td>
				<?php
				if ( false === $end ) {
					echo affwp_date_i18n( strtotime( gmdate( 'm/d/Y' ) ) );
				} else {
					echo affwp_date_i18n( strtotime( $end ) );
				}
				?>
			</td>

		</tr>

		<tr class="form-row">

			<th scope="row">
				<?php esc_html_e( 'Referrals Total', 'affiliate-wp' ); ?>
			</th>

			<td>
				<?php echo affwp_currency_filter( affwp_format_amount( $referrals_total ) ); ?>
			</td>

		</tr>

		<?php
		/**
		 * Fires immediately after the referrals total table row.
		 *
		 * The dynamic portion of the hook name, `$payout_method` refers to the payout method.
		 *
		 * @since 2.4
		 *
		 * @param float $referrals_total Referrals total.
		 * @param array $data            Payout data.
		 */
		do_action( "affwp_preview_payout_after_referrals_total_{$payout_method}", $referrals_total, $data );
		?>

		<tr class="form-row">

			<th scope="row">
				<?php esc_html_e( 'Payout Method', 'affiliate-wp' ); ?>
			</th>

			<td>
				<?php echo esc_attr( $payout_method_label ); ?>
			</td>

		</tr>

		<tr class="form-row">

			<th scope="row">
				<?php esc_html_e( 'Payout Currency', 'affiliate-wp' ); ?>
			</th>

			<td>
				<?php echo esc_attr( affwp_get_currency() ); ?>
			</td>

		</tr>

	</table>

	<?php if ( ! empty( $payouts ) ) : ?>

		<?php
		/**
		 * Fires immediately after the referrals table and before the Affiliates to be Paid table.
		 *
		 * The dynamic portion of the hook name, `$payout_method` refers to the payout method.
		 *
		 * @since 2.4
		 */
		do_action( "affwp_preview_payout_note_{$payout_method}" ); ?>

		<h2>
			<?php
			echo sprintf(
				_n(
					/* translators: Payout method label single affiliate */
					'Affiliate to be paid via %s',
					/* translators: Payout method label plural affiliates */
					'Affiliates to be paid via %s',
					count( $payouts ),
					'affiliate-wp'
	             ), $payout_method_label
			);
			?>
		</h2>

		<table class="wp-list-table widefat fixed striped payouts">
			<thead>
			<tr>
				<th class="manage-column column-affiliate column-primary" id="affiliate" scope="col">
					<?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?>
				</th>
				<th class="manage-column column-amount" id="amount" scope="col">
					<?php esc_html_e( 'Amount', 'affiliate-wp' ); ?>
				</th>
			</tr>
			</thead>
			<tbody data-wp-lists="list:valid-payouts" id="valid-payouts">
			<?php foreach ( $payouts as $affiliate_id => $payout ) : ?>
				<tr>
					<td class="affiliate column-affiliate has-row-actions column-primary" data-colname="Affiliate">
						<?php
						$url = affwp_admin_url( 'affiliates', array(
							'action'       => 'view_affiliate',
							'affiliate_id' => $affiliate_id,
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
						<button class="toggle-row" type="button">
							<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'affiliate-wp' ); ?></span>
						</button>
					</td>
					<td class="amount column-amount" data-colname="Amount">
						<?php echo affwp_currency_filter( affwp_format_amount( $payout['amount'] ) ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
			<tr>
				<th class="manage-column column-affiliate column-primary" scope="col">
					<?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?>
				</th>
				<th class="manage-column column-amount" scope="col">
					<?php esc_html_e( 'Amount', 'affiliate-wp' ); ?>
				</th>
			</tr>
			</tfoot>
		</table>

		<?php if ( ! empty( $invalid_affiliates ) ) : ?>

			<h2>
				<?php
				echo sprintf(
					_n(
						/* translators: Payment method label for single affiliate */
						'Affiliate who cannot be paid via %s',
						/* translators: Payout method label for multiple affiliates */
						'Affiliates who cannot be paid via %s',
						count( $invalid_affiliates ),
						'affiliate-wp'
					), $payout_method_label
				);
				?>
			</h2>

			<table class="wp-list-table widefat fixed striped payouts">
				<thead>
					<tr>
						<th class="manage-column column-invalid-affiliate column-primary" id="affiliate" scope="col">
							<?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?>
						</th>
						<th class="manage-column column-reason" id="reason" scope="col">
							<?php esc_html_e( 'Reason', 'affiliate-wp' ); ?>
						</th>
					</tr>
				</thead>
				<tbody data-wp-lists="list:invalid-payouts" id="invalid-payouts">
				<?php foreach ( $invalid_affiliates as $affiliate_id => $status ) : ?>
					<tr>
						<td class="affiliate column-affiliate has-row-actions column-primary" data-colname="Affiliate">
							<?php
							$url = affwp_admin_url( 'affiliates', array(
								'action'       => 'view_affiliate',
								'affiliate_id' => $affiliate_id,
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
							<button class="toggle-row" type="button">
								<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'affiliate-wp' ); ?></span>
							</button>
						</td>
						<td class="reason column-reason" data-colname="Reason">
							<?php echo esc_attr( affwp_get_preview_payout_request_failed_reason_label( $status ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th class="manage-column column-invalid-affiliate column-primary" scope="col">
							<?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?>
						</th>
						<th class="manage-column column-reason" id="reason" scope="col">
							<?php esc_html_e( 'Reason', 'affiliate-wp' ); ?>
						</th>
					</tr>
				</tfoot>
			</table>

		<?php endif; ?>

		<?php if ( 'manual' === $payout_method ) : ?>

			<form class="affwp-batch-form" data-batch_id="generate-payouts" data-nonce="<?php echo esc_attr( wp_create_nonce( 'generate-payouts_step_nonce' ) ); ?>" data-ays="<?php esc_attr_e( 'Are you sure you want to generate the payout file? All included referrals will be marked as Paid.', 'affiliate-wp' ); ?>">
				<p>
					<input type="hidden" name="user_name" value="<?php echo esc_attr( $user_name ); ?>"/>
					<input type="hidden" name="from" value="<?php echo esc_attr( $start ); ?>"/>
					<input type="hidden" name="to" value="<?php echo esc_attr( $end ); ?>"/>
					<input type="hidden" name="minimum" value="<?php echo esc_attr( $minimum ); ?>"/>
					<?php submit_button( __( 'Generate CSV File', 'affiliate-wp' ), 'primary', 'generate-payouts-submit', false ); ?>
					<a id="cancel-new-payout" href="<?php echo esc_url( affwp_admin_url( 'referrals' ) ); ?>" class="button-secondary"><?php esc_html_e( 'Cancel', 'affiliate-wp' ); ?></a>
				</p>
			</form>

		<?php else : ?>

			<script>
				jQuery(document).ready(function($) {
					$('#affwp-new-payout-form').submit(function() {
						if ( ! confirm( "<?php
							/* translators: Payout method label */
							printf( __( 'Are you sure you want to payout referrals for the specified time frame via %s?', 'affiliate-wp' ), $payout_method_label ); ?>" ) ) {
							return false;
						}
						$("#new-payout-submit").attr("disabled", true);
						$("#cancel-new-payout").hide();
					});
				});
			</script>

			<form method="post" id="affwp-new-payout-form">
				<p>
					<input type="hidden" name="user_name" value="<?php echo esc_attr( $user_name ); ?>"/>
					<input type="hidden" name="from" value="<?php echo esc_attr( $start ); ?>"/>
					<input type="hidden" name="to" value="<?php echo esc_attr( $end ); ?>"/>
					<input type="hidden" name="minimum" value="<?php echo esc_attr( $minimum ); ?>"/>
					<input type="hidden" name="payout_method" value="<?php echo esc_attr( $payout_method ); ?>"/>
					<input type="hidden" name="affwp_action" value="new_payout"/>
					<?php wp_nonce_field( 'affwp_new_payout_nonce', 'affwp_new_payout_nonce' ); ?>
					<?php submit_button( __( 'Submit Payout', 'affiliate-wp' ), 'primary', 'new-payout-submit', false ); ?>
					<a id="cancel-new-payout" href="<?php echo esc_url( affwp_admin_url( 'referrals' ) ); ?>" class="button-secondary"><?php esc_html_e( 'Cancel', 'affiliate-wp' ); ?></a>
				</p>
			</form>

		<?php endif; ?>

	<?php else : ?>

		<?php if ( ! empty( $invalid_affiliates ) ) : ?>

			<h2>
				<?php
				echo sprintf(
					_n(
						'Affiliate who cannot be paid via %s',
						'Affiliates who cannot be paid via %s',
						count( $invalid_affiliates ),
						'affiliate-wp'
					), $payout_method_label
				);
				?>
			</h2>

			<table class="wp-list-table widefat fixed striped payouts">
			<thead>
				<tr>
					<th class="manage-column column-invalid-affiliate column-primary" id="affiliate" scope="col">
						<?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?>
					</th>
					<th class="manage-column column-reason" id="reason" scope="col">
						<?php esc_html_e( 'Reason', 'affiliate-wp' ); ?>
					</th>
				</tr>
			</thead>
			<tbody data-wp-lists="list:invalid-payouts" id="invalid-payouts">
			<?php foreach ( $invalid_affiliates as $affiliate_id => $status ) : ?>
				<tr>
					<td class="affiliate column-affiliate has-row-actions column-primary" data-colname="Affiliate">
						<?php
						$url = affwp_admin_url( 'affiliates', array(
							'action'       => 'view_affiliate',
							'affiliate_id' => $affiliate_id,
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
						<button class="toggle-row" type="button">
							<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'affiliate-wp' ); ?></span>
						</button>
					</td>
					<td class="reason column-reason" data-colname="Reason">
						<?php echo esc_attr( affwp_get_preview_payout_request_failed_reason_label( $status ) ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th class="manage-column column-invalid-affiliate column-primary" scope="col">
						<?php esc_html_e( 'Affiliate', 'affiliate-wp' ); ?>
					</th>
					<th class="manage-column column-reason" scope="col">
						<?php esc_html_e( 'Reason', 'affiliate-wp' ); ?>
					</th>
				</tr>
			</tfoot>
		</table>

		<?php else : ?>

			<p>
				<?php
				/* translators: Referrals screen URL */
				printf( __( 'No referrals are available to be paid out. View all <a href="%s">referrals</a>.', 'affiliate-wp' ), esc_url( affwp_admin_url( 'referrals' ) ) );
				?>
			</p>

		<?php endif; ?>

	<?php endif; ?>

</div>
