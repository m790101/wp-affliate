<?php
/**
 * Payouts Service Payout Method
 *
 * This template is used to display the payout method for the affiliate on the Payouts Service.
 *
 * @package     AffiliateWP
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

$affiliate_id       = affwp_get_affiliate_id();
$payout_method_meta = affwp_get_affiliate_meta( $affiliate_id, 'payouts_service_payout_method', true );
$payout_method      = $payout_method_meta['payout_method'];

$current_page_url = trailingslashit( get_permalink() );

if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
	$current_page_url = add_query_arg( wp_unslash( $_SERVER['QUERY_STRING'] ), '', $current_page_url );
}

$current_page_url = remove_query_arg( array( 'affwp_notice', 'email' ), $current_page_url );

$query_args = array(
	'affwp_action'     => 'payouts_service_change_payout_method',
	'current_page_url' => urlencode( $current_page_url ),
);

$change_payout_method_url = remove_query_arg( array( 'affwp_notice', 'email' ) );
$change_payout_method_url = wp_nonce_url( add_query_arg( $query_args, $change_payout_method_url ), 'payouts_service_change_payout_method', 'payouts_service_change_payout_method_nonce' );

affiliate_wp()->affiliates->payouts->service_register->print_errors();
?>

<?php if ( ! empty( $_REQUEST['affwp_notice'] ) && 'change-payout-method' == $_REQUEST['affwp_notice'] ) : ?>

	<?php $email = ! empty( $_REQUEST['email'] ) ? sanitize_text_field( $_REQUEST['email'] ) : ''; ?>

	<p class="affwp-notice">
		<?php
		/* translators: Payouts Service account email */
		printf( __( 'An email has been sent to %s with a link to change the payout method', 'affiliate-wp' ), $email );
		?>
	</p>

<?php endif; ?>

<h4><?php esc_html_e( 'Payout Settings', 'affiliate-wp' ); ?></h4>

<?php if ( 'bank_account' === $payout_method ) : ?>

	<p><?php esc_html_e( 'Your earnings will be paid into the account below.', 'affiliate-wp' ); ?></p>
	<p><?php printf( __( '<strong>Bank Name: </strong> %s', 'affiliate-wp' ), $payout_method_meta['bank_name'] ); ?></p>
	<p><?php printf( __( '<strong>Account Holder Name: </strong> %s', 'affiliate-wp' ), $payout_method_meta['account_name'] ); ?></p>
	<p><?php printf( __( '<strong>Account Number: </strong> %s', 'affiliate-wp' ), $payout_method_meta['account_no'] ); ?></p>

<?php else : ?>

	<p><?php esc_html_e( 'Your earnings will be paid into the card below.', 'affiliate-wp' ); ?></p>
	<p><?php printf( __( '<strong>Card: </strong> %s', 'affiliate-wp' ), $payout_method_meta['card'] ); ?></p>
	<p><?php printf( __( '<strong>Expiry: </strong> %s', 'affiliate-wp' ), $payout_method_meta['expiry'] ); ?></p>

<?php endif; ?>

<?php
/* translators: Payouts Service change payout method URL */
printf( __( 'Want to change your payout method? Do that <a href="%s">here</a>.', 'affiliate-wp' ), esc_url( $change_payout_method_url ) );
?>
