<?php
/**
 * Admin: View Affiliate View
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Affiliates
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

$affiliate_id = isset( $_GET['affiliate_id'] ) ? absint( $_GET['affiliate_id'] ) : 0;

?>
<div class="wrap">
	<h2>
		<?php
		/* translators: 1: Affiliate ID, 2: Affiliate name */
		printf( __( 'Affiliate: #%1$d %2$s', 'affiliate-wp' ), $affiliate_id, affiliate_wp()->affiliates->get_affiliate_name( $affiliate_id ) );
		?>
	</h2>

	<?php
	/**
	 * Fires at the top of the view-affiliate report admin screen.
	 *
	 * @since 1.9
	 */
	do_action( 'affwp_view_affiliate_report_top' );
	?>

	<?php
	// Recent Payouts.
	$payouts_table = new AffWP_Payouts_Table( array(
		'query_args' => array(
			'affiliate_id' => $affiliate_id
		),
		'display_args' => array(
			'hide_bulk_options'    => true,
			'columns_to_hide'      => array( 'status' ),
			'hide_column_controls' => true,
		),
	) );
	$payouts_table->prepare_items();
	?>
	<h2><?php _e( 'Recent Payouts', 'affiliate-wp' ); ?></h2>

	<?php $payouts_table->views(); ?>
	<?php $payouts_table->display(); ?>

	<?php
	/**
	 * Fires at the bottom of view-affiliate-report screens.
	 *
	 * @since 1.9
	 */
	do_action( 'affwp_view_affiliate_report_bottom' );
	?>

</div>
