<?php
/**
 * Admin: Referrals Overview
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Referrals
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/referrals/screen-options.php';
include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/referrals/contextual-help.php';
require_once AFFILIATEWP_PLUGIN_DIR . 'includes/admin/referrals/class-list-table.php';

/**
 * Loads the Referrals admin screen.
 *
 * @since 1.0
 */
function affwp_referrals_admin() {

	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : null;

	$referral_id = isset( $_REQUEST['referral_id'] ) ? absint( $_REQUEST['referral_id'] ) : 0;
	$referral    = affwp_get_referral( $referral_id );

	if ( 'edit_referral' === $action && $referral ) {

		include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/referrals/edit.php';

	} elseif ( 'add_referral' === $action ) {

		include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/referrals/new.php';

	} else {

		$referrals_table = new AffWP_Referrals_Table();
		$referrals_table->prepare_items();
		?>
		<div class="wrap">
			<h1>
				<?php _e( 'Referrals', 'affiliate-wp' ); ?>
				<a href="<?php echo esc_url( add_query_arg( 'action', 'add_referral' ) ); ?>" class="button action button-small"><?php _e( 'Add New', 'affiliate-wp' ); ?></a>
				<?php if ( current_user_can( 'view_affiliate_reports' ) ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'affiliate-wp-reports', 'tab' => 'referrals' ) ) ); ?>" class="button action button-small"><?php _ex( 'Reports', 'referrals', 'affiliate-wp' ); ?></a>
				<?php endif; ?>
				<?php if ( current_user_can( 'manage_payouts' ) ) : ?>
					<a href="<?php echo esc_url( affwp_admin_url( 'payouts', array( 'action' => 'new_payout' ) ) ); ?>" class="button action button-primary button-small"><?php _e( 'Pay Affiliates', 'affiliate-wp' ); ?></a>
				<?php endif; // manage_payouts ?>
			</h1>

			<?php
			/**
			 * Fires at the top of the referrals list-table admin screen.
			 *
			 * @since 1.0
			 */
			do_action( 'affwp_referrals_page_top' );
			?>

			<?php if ( current_user_can( 'manage_payouts' ) ) : ?>
				<div id="affwp-referrals-export-wrap">

					<?php
					/**
					 * Fires in the action buttons area of the referrals list-table admin screen.
					 *
					 * @since 1.0
					 */
					do_action( 'affwp_referrals_page_buttons' );
					?>

				</div>
			<?php endif; // manage_payouts ?>

			<form id="affwp-referrals-filter-form" method="get" action="<?php echo esc_url( affwp_admin_url( 'referrals' ) ); ?>">

				<?php $referrals_table->search_box( __( 'Search', 'affiliate-wp' ), 'affwp-referrals' ); ?>

				<input type="hidden" name="page" value="affiliate-wp-referrals" />

				<?php $referrals_table->views() ?>
				<?php $referrals_table->display() ?>
			</form>
			<?php
			/**
			 * Fires at the bottom of the referrals list table admin screen.
			 *
			 * @since 1.0
			 */
			do_action( 'affwp_referrals_page_bottom' );
			?>
		</div>
	<?php
	}

}
