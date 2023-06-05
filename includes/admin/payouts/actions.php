<?php
/**
 * Admin: Payouts Action Callbacks
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Payouts
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

/**
 * Processes the preview payout request.
 *
 * @since 2.4
 *
 * @param array $data {
 *     Array of arguments for generating a payout preview. Default empty array.
 *
 *     @type string $user_name     Affiliate username.
 *     @type string $from          Referrals start date.
 *     @type string $to            Referral end date.
 *     @type string $minimum       Minimum payout amount.
 *     @type string $payout_method Payout method.
 * }
 * @return void|false
 */
function affwp_process_preview_payout( $data ) {

	if ( ! is_admin() ) {
		return false;
	}

	if ( empty( $data['payout_method'] ) ) {
		return false;
	}

	if ( ! current_user_can( 'manage_payouts' ) ) {
		wp_die( __( 'You do not have permission to process payouts', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	if ( ! wp_verify_nonce( $data['affwp_preview_payout_nonce'], 'affwp_preview_payout_nonce' ) ) {
		wp_die( __( 'Security check failed', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	$payout_methods = affwp_get_payout_methods();
	if ( ! array_key_exists( $data['payout_method'], $payout_methods ) ) {
		wp_die( __( 'Invalid payout method', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	$query_args = array(
		'action'        => 'preview_payout',
		'user_name'     => sanitize_text_field( $data['user_name'] ),
		'from'          => sanitize_text_field( $data['from'] ),
		'to'            => sanitize_text_field( $data['to'] ),
		'minimum'       => sanitize_text_field( $data['minimum'] ),
		'payout_method' => sanitize_text_field( $data['payout_method'] ),
	);

	$url = affwp_admin_url( 'payouts', $query_args );

	wp_redirect( $url ); exit;

}
add_action( 'affwp_preview_payout', 'affwp_process_preview_payout' );

/**
 * Processes a new payout request.
 *
 * @since 2.4
 *
 * @param array $data {
 *     Array of arguments for creating a new payout. Default empty array.
 *
 *     @type string $user_name     Affiliate username.
 *     @type string $from          Referrals start date.
 *     @type string $to            Referral end date.
 *     @type string $minimum       Minimum payout amount.
 *     @type string $payout_method Payout method.
 * }
 *
 * @return false|void False if not admin page request, payout_method, affwp_new_payout_nonce data isn't
 *                    passed or nonce verification failed, void otherwise.
 */
function affwp_process_new_payout( $data ) {

	if ( ! is_admin() ) {
		return false;
	}

	if ( empty( $data['payout_method'] ) ) {
		return false;
	}

	if ( ! current_user_can( 'manage_payouts' ) ) {
		wp_die( __( 'You do not have permission to process payouts', 'affiliate-wp' ) );
	}

	$nonce = empty( $data['affwp_new_payout_nonce'] ) ? false : $data['affwp_new_payout_nonce'];

	if ( ! wp_verify_nonce( $data['affwp_new_payout_nonce'], 'affwp_new_payout_nonce' ) ) {
		return false;
	}

	$payout_methods = affwp_get_payout_methods();

	if ( ! array_key_exists( $data['payout_method'], $payout_methods ) ) {
		wp_die( __( 'Invalid payout method', 'affiliate-wp' ), array( 'response' => 403 ) );
	}

	if ( ! empty( $data['user_name'] ) && ( $affiliate = affwp_get_affiliate( $data['user_name'] ) ) ) {
		$affiliate_id = $affiliate->ID;
	} else {
		$affiliate_id = false;
	}

	$start         = ! empty( $data['from'] ) ? sanitize_text_field( $data['from'] ) : false;
	$end           = ! empty( $data['to'] ) ? sanitize_text_field( $data['to'] ) : false;
	$minimum       = ! empty( $data['minimum'] ) ? sanitize_text_field( affwp_sanitize_amount( $data['minimum'] ) ) : 0;
	$payout_method = ! empty( $data['payout_method'] ) ? strtolower( sanitize_text_field( $data['payout_method'] ) ) : 'manual';

	/**
	 * Fires after a new payout action is performed.
	 *
	 * The dynamic portion of the hook name, `$payout_method` refers to the payout method.
	 *
	 * @since 2.4
	 *
	 * @param string   $start         Referrals start date.
	 * @param string   $end           Referral end date.
	 * @param string   $minimum       Minimum payout amount.
	 * @param int|bool $affiliate_id  Affiliate ID.
	 * @param string   $payout_method Payout method.
	 */
	do_action( "affwp_process_payout_{$payout_method}", $start, $end, $minimum, $affiliate_id, $payout_method );

}
add_action( 'affwp_new_payout', 'affwp_process_new_payout' );

/**
 * Adds a note to the payout preview page for a manual payout.
 *
 * @since 2.4
 *
 * @return void
 */
function affwp_manual_payout_preview_payout_note() {
	?>
	<h2><?php esc_html_e( 'Note', 'affiliate-wp' ); ?></h2>
	<p><?php esc_html_e( 'A CSV file will be generated containing the payout details for each affiliate.', 'affiliate-wp' ); ?></p>
	<p>
		<?php
		/* translators: Import/Export Tools screen URL */
		printf( __( 'This will mark all unpaid referrals in this timeframe as paid. To export referrals with a status other than <em>unpaid</em>, go to the <a href="%s">Tools &rarr; Export</a> page.', 'affiliate-wp' ), esc_url( affwp_admin_url( 'tools', array( 'tab' => 'export_import' ) ) ) );
		?>
	</p>
	<?php
}
add_action( 'affwp_preview_payout_note_manual', 'affwp_manual_payout_preview_payout_note' );
