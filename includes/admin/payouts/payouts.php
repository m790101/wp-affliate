<?php
/**
 * Admin: Payouts Overview
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Payouts
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/payouts/screen-options.php';
require_once AFFILIATEWP_PLUGIN_DIR . 'includes/admin/payouts/class-list-table.php';

/**
 * Loads the Payouts admin screen.
 *
 * @since 1.9
 */
function affwp_payouts_admin() {

	$action = null;

	if ( isset( $_GET['action2'] ) && '-1' !== $_GET['action2'] ) {
		$action = $_GET['action2'];
	} elseif ( isset( $_GET['action'] ) && '-1' !== $_GET['action'] ) {
		$action = $_GET['action'];
	}

	if ( 'view_payout' === $action ) {
		include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/payouts/view.php';
	} elseif ( 'new_payout' === $action ) {
		include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/payouts/new.php';
	} elseif ( 'preview_payout' === $action ) {
		include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/payouts/preview.php';
	} elseif ( 'payout_submitted' === $action ) {
		include AFFILIATEWP_PLUGIN_DIR . 'includes/admin/payouts/submitted.php';
	} else {

		$payouts_table = new AffWP_Payouts_Table();
		$payouts_table->prepare_items();
?>
		<div class="wrap">
			<h1>
				<?php _e( 'Payouts', 'affiliate-wp' ); ?>
				<?php
				echo affwp_admin_link( 'reports', _x( 'Reports', 'payouts', 'affiliate-wp' ), array( 'tab' => 'payouts' ), array( 'class' => 'button action button-small' ) );
				?>
				<?php
				echo affwp_admin_link( 'referrals', __( 'Manage Referrals', 'affiliate-wp' ), array(), array( 'class' => 'button action button-small' ) );
				?>
				<?php if ( current_user_can( 'manage_payouts' ) ) : ?>
					<a href="<?php echo esc_url( affwp_admin_url( 'payouts', array( 'action' => 'new_payout' ) ) ); ?>" class="button action button-primary button-small"><?php _e( 'Pay Affiliates', 'affiliate-wp' ); ?></a>
				<?php endif; // manage_payouts ?>
			</h1>
			<?php
			/**
			 * Fires at the top of the Payouts page (outside the form element).
			 *
			 * @since 1.9
			 */
			do_action( 'affwp_payouts_page_top' );
			?>
			<form id="affwp-payouts-filter" method="get" action="<?php echo esc_url( affwp_admin_url( 'payouts' ) ); ?>">
				<?php $payouts_table->search_box( __( 'Search', 'affiliate-wp' ), 'affwp-payouts' ); ?>

				<input type="hidden" name="page" value="affiliate-wp-payouts" />

				<?php $payouts_table->views(); ?>
				<?php $payouts_table->display(); ?>
			</form>
			<?php
			/**
			 * Fires at the bottom of the Payouts page (outside the form element).
			 *
			 * @since 1.9
			 */
			do_action( 'affwp_payouts_page_bottom' );
			?>
		</div>
<?php

	}

}
