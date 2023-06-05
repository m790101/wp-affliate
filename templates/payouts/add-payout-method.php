<?php
/**
 * Payouts Service Add Payout Method
 *
 * This template is used to display the link to add a payout method after creating a Payouts Service account.
 *
 * @package     AffiliateWP
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

$payouts_service_account_meta = affwp_get_affiliate_meta( affwp_get_affiliate_id(), 'payouts_service_account', true );

$current_page_url = trailingslashit( get_permalink() );

if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
	$current_page_url = add_query_arg( wp_unslash( $_SERVER['QUERY_STRING'] ), '', $current_page_url );
}

$url = add_query_arg( array(
	'redirect_url'  => urlencode( $current_page_url ),
	'affwp_version' => AFFILIATEWP_VERSION,
), PAYOUTS_SERVICE_URL . '/account/' . $payouts_service_account_meta['link_id'] );

affiliate_wp()->affiliates->payouts->service_register->print_errors();
?>

<h4><?php _e( 'Add Payout Method', 'affiliate-wp' ); ?></h4>

<p>
	<?php
	/* translators: Payouts Service account URL */
	printf( __( 'Click <a href="%s">here</a> to add a payout method where you will receive your affiliate earnings.', 'affiliate-wp' ), esc_url( $url ) );
	?>
</p>
