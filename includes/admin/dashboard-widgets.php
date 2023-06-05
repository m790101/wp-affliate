<?php
/**
 * Admin: Dashboard Widgets
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Dashboard
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.3
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the dashboard widgets
 *
 * @since 2.3
 *
 * @return void
 */
function affwp_register_dashboard_widgets() {
	/**
	 * Filters the capability that is required to view the dashboard stats.
	 *
	 * @since 2.3
	 *
	 * @param string The capability required to view the dashboard stats.
	 */
	$dashboard_stats_cap = apply_filters( 'affwp_dashboard_stats_cap', 'manage_affiliate_options' );

	if ( current_user_can( $dashboard_stats_cap ) ) {
		wp_add_dashboard_widget( 'affwp_dashboard_overview', __( 'AffiliateWP Referral Summary', 'affiliate-wp' ), 'affwp_dashboard_loading_icon' );
	}
}

add_action( 'wp_dashboard_setup', 'affwp_register_dashboard_widgets' );

/**
 * Loading Icon
 *
 * Loads the loading icon for the current widget.
 * Should be replaced by the actual widget content via an AJAX call.
 *
 * @since 2.3
 *
 * @return void
 */
function affwp_dashboard_loading_icon() {
	wp_enqueue_script( 'affwp-dashboard-ajax' );

	echo '<p><img alt="loading icon" src=" ' . esc_attr( set_url_scheme( AFFILIATEWP_PLUGIN_URL . 'assets/images/loading.gif', 'relative' ) ) . '"/></p>';
}

/**
 * AJAX callback to load referrals widget.
 *
 * @since 2.3
 *
 * @return void
 */
function affwp_dashboard_overview_load_template() {

	/**
	 * Filters the capability that is required to view the dashboard stats.
	 *
	 * @since 2.3
	 *
	 * @param string The capability required to view the dashboard stats.
	 */
	if ( ! current_user_can( apply_filters( 'affwp_dashboard_stats_cap', 'manage_affiliate_options' ) ) ) {
		die();
	}
	$summary_values = array(
		'current_month_earnings'  => affiliate_wp()->referrals->get_earnings_by_status( array( 'paid', 'unpaid' ), 0, 'month' ),
		'current_month_referrals' => affiliate_wp()->referrals->count_by_status( array( 'paid', 'unpaid' ), 0, 'month' ),
		'today_earnings'          => affiliate_wp()->referrals->get_earnings_by_status( array( 'paid','unpaid' ), 0, 'today' ),
		'today_referrals'         => affiliate_wp()->referrals->count_by_status( array( 'paid','unpaid' ), 0, 'today' ),
		'last_month_earnings'     => affiliate_wp()->referrals->get_earnings_by_status( array( 'paid','unpaid' ), 0, 'last-month' ),
		'last_month_referrals'    => affiliate_wp()->referrals->count_by_status( array( 'paid','unpaid' ), 0, 'last-month' ),
		'total_earnings'          => affiliate_wp()->referrals->get_earnings_by_status( array( 'paid','unpaid' )  ),
		'total_referrals'         => affiliate_wp()->referrals->count_by_status( array( 'paid','unpaid' )  ),
	);

	$recent_referrals = affiliate_wp()->referrals->get_referrals( array(
		'number'  => 5,
		'orderby' => 'date',
		'fields'  => array( 'referral_id', 'affiliate_id', 'amount' ),
	) );
	?>
	<div class="affwp-dashboard-widget">
		<div class="dataset dataset-referrals-current-month">
			<h4><?php _e( 'Current Month', 'affiliate-wp' ); ?></h4>

			<dl>
				<dt><?php _ex( 'Earnings', 'Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_currency_filter( affwp_format_amount( $summary_values['current_month_earnings'] ) ); ?></dd>
				<dt><?php _ex( 'Referrals', 'Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_format_amount( $summary_values['current_month_referrals'], false ); ?></dd>
		  </dl>
		</div>

		<div class="dataset dataset-referrals-today">
			<h4><?php _e('Today', 'affiliate-wp' ); ?></h4>

			<dl>
				<dt><?php _ex('Earnings','Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_currency_filter( affwp_format_amount( $summary_values['today_earnings'] ) ); ?></dd>
				<dt><?php _ex('Referrals','Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_format_amount( $summary_values['today_referrals'], false ); ?></dd>
			</dl>
		</div>

		<div class="dataset dataset-referrals-last-month">
			<h4><?php _e('Last Month', 'affiliate-wp' ); ?></h4>

			<dl>
				<dt><?php _ex('Earnings','Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_currency_filter( affwp_format_amount( $summary_values['last_month_earnings'] ) ); ?></dd>
				<dt><?php _ex('Referrals','Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_format_amount( $summary_values['last_month_referrals'], false ); ?></dd>
			</dl>
		</div>

		<div class="dataset dataset-referrals-totals">
			<h4><?php _e('Totals', 'affiliate-wp' ); ?></h4>

			<dl>
				<dt><?php _ex('Earnings','Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_currency_filter( affwp_format_amount( $summary_values['total_earnings'] ) ); ?></dd>
				<dt><?php _ex( 'Referrals', 'Dashboard widget', 'affiliate-wp' ); ?></dt>
				<dd><?php echo affwp_format_amount( $summary_values['total_referrals'], false ) ?></dd>
			</dl>
		</div>

		<?php
		/**
		 * Fires immediately after summarized earnings are displayed in the dashboard widget.
		 *
		 * @since 2.3
		 *
		 * @param array $summary_values An array of calculated summary values currently displayed in the widget.
		 */
		do_action( 'affwp_dashboard_overview_after_summary', $summary_values );
		?>
		<div class="dataset dataset-recent-referrals">
			<h4>
				<?php _e( 'Recent Referrals', 'affiliate-wp' ); ?> -
				<a href="<?php echo esc_url( affwp_admin_url( 'referrals' ) ); ?>" alt="<?php esc_attr_e( 'View all referrals', 'affiliate-wp' ); ?>">
					<?php _e( 'View All', 'affiliate-wp' ); ?>
				</a>
			</h4>
			<?php if ( count( $recent_referrals ) > 0 ): ?>
				<table>
					<thead>
						<tr>
						  <td><?php _e( 'Affiliate', 'affiliate-wp' ) ?></td>
						  <td><?php _e( 'Status', 'affiliate-wp' ) ?></td>
						  <td><?php _e( 'Amount', 'affiliate-wp' ) ?></td>
						</tr>
					</thead>

					<tbody>
						<?php foreach ( $recent_referrals as $recent_referral ): ?>
							<tr>
								<td><?php echo affiliate_wp()->affiliates->get_affiliate_name( $recent_referral->affiliate_id ) ?></td>
								<td><?php echo affwp_get_referral_status_label( $recent_referral ) ?></td>
								<td><?php echo affwp_currency_filter( $recent_referral->amount ) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<p class="affwp-textcenter"><?php _e( 'There are no referrals to show.', 'affiliate-wp' ) ?></p>
			<?php endif; ?>
		</div>

		<div class="clear"></div>
	</div>

	<?php
	/**
	 * Fires immediately after recent referrals are displayed in the dashboard widget.
	 *
	 * @since 2.3
	 *
	 * @param array $recent_referrals Recent referrals, as referral objects.
	 */
	do_action( 'affwp_dashboard_overview_after_recent_referrals', $recent_referrals );

	wp_die();
}

add_action( 'wp_ajax_affwp_dashboard_overview', 'affwp_dashboard_overview_load_template' );
