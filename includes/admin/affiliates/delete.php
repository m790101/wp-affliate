<?php
/**
 * Admin: Delete Affiliate View
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Affiliates
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if( ! empty( $_GET['affiliate_id'] ) && is_array( $_GET['affiliate_id'] ) ) {

	$to_delete = array_map( 'absint', $_GET['affiliate_id'] );

} else {

	$to_delete = ! empty( $_GET['affiliate_id'] ) ? array( absint( $_GET['affiliate_id'] ) ) : array();

}

// Number of affiliate accounts marked for deletion.
$to_delete_count = count( $to_delete );

// Get user IDs of affiliates marked for deletion.
$affiliates_to_delete = affiliate_wp()->affiliates->get_affiliates( array(
	'number'       => $to_delete_count,
	'affiliate_id' => $to_delete
) );

$current_user      = get_current_user_id();
$user_affiliate_id = absint( affiliate_wp()->affiliates->get_column_by( 'affiliate_id', 'user_id', $current_user ) );

$total_invalid_count     = 0;
$invalid_affiliate_count = 0;
$affiliate_names         = array();

foreach ( $to_delete as $affiliate_id ) {
	$affiliate_exists = false !== affwp_get_affiliate( $affiliate_id );

	$deleting_self = $user_affiliate_id === $affiliate_id;
	$name          = $affiliate_exists ? affiliate_wp()->affiliates->get_affiliate_name( $affiliate_id ) : '';

	if ( $deleting_self ) {
		$name = __( sprintf( '%s (The current user will not be deleted)', $name ), 'affiliate-wp' );
		$total_invalid_count++;
	} elseif ( ! $affiliate_exists ) {
		$name = __( '(Invalid Affiliate ID)', 'affiliate-wp' );
		$total_invalid_count++;
		$invalid_affiliate_count++;
	} elseif ( ! $name ) {
		$name = __( '(User Deleted)', 'affiliate-wp' );
		$total_invalid_count++;
	}

	$affiliate_names[ $affiliate_id ] = $name;
}

$have_affiliates_to_delete = $to_delete_count > $invalid_affiliate_count;
$have_users_to_delete      = $to_delete_count > $total_invalid_count;

?>
<div class="wrap">

	<h2><?php echo _n(
				'Delete Affiliate',
				'Delete Affiliates',
				$to_delete_count,
				'affiliate-wp'
		); ?>
	</h2>

	<form method="post" id="affwp_delete_affiliate">

		<?php
		/**
		 * Fires at the top of the delete affiliate admin screen.
		 *
		 * @since 1.0
		 *
		 * @param int $to_delete Affiliate ID to delete.
		 */
		do_action( 'affwp_delete_affiliate_top', $to_delete );

		if ( $have_affiliates_to_delete ):?>

			<p><?php echo _n(
						'You have specified the following affiliate for deletion:',
						'You have specified the following affiliates for deletion:',
						$to_delete_count,
						'affiliate-wp'
				); ?></p>

		<?php endif; ?>

		<ul>

			<?php foreach ( $affiliate_names as $affiliate_id => $name ) : ?>

			<li>
				<?php
				/* translators: 1: Affiliate ID, 2: Affiliate name */
				printf( _x( 'ID #%1$d: %1$s', 'Affiliate ID, affiliate name', 'affiliate-wp' ), $affiliate_id, $name );
				?>
				<input type="hidden" name="affwp_affiliate_ids[]" value="<?php echo esc_attr( $affiliate_id ); ?>"/>
			</li>

		<?php endforeach; ?>

		</ul>

		<?php if ( $have_affiliates_to_delete ): ?>
			<p>
				<?php echo _n(
						'Deleting this affiliate will also delete their referral and visit data.',
						'Deleting these affiliates will also delete their referral and visit data.',
						$to_delete_count,
						'affiliate-wp'
				); ?>
			</p>
		<?php endif; ?>

		<?php if ( current_user_can( 'remove_users' ) && $have_users_to_delete ) : ?>
			<p>
				<label for="affwp_delete_users_too">
					<input type="checkbox" name="affwp_delete_users_too" id="affwp_delete_users_too" value="1" />
					<?php
					echo _n(
							'Proceed to the user management page to remove the user account associated with this affiliate?',
							'Proceed to the user management page to remove the user accounts associated with these affiliates?',
							$to_delete_count,
							'affiliate-wp'
					); ?>
				</label>
			</p>
		<?php endif; ?>

		<?php
		/**
		 * Fires at the bottom of the delete affiliate admin screen.
		 *
		 * @since 1.0
		 *
		 * @param int $to_delete Affiliate ID to delete.
		 */
		do_action( 'affwp_delete_affiliate_bottom', $to_delete );
		?>
		<?php if ( $have_affiliates_to_delete ): ?>
			<input type="hidden" name="affwp_action" value="delete_affiliates"/>
			<?php echo wp_nonce_field( 'affwp_delete_affiliates_nonce', 'affwp_delete_affiliates_nonce' ); ?>

			<?php submit_button( __( 'Confirm Deletion', 'affiliate-wp' ) ); ?>
		<?php else: ?>
			<p><?php
				echo _n(
						'The specified affiliate does not exist.',
						'None of the specified affiliates exist.',
						$to_delete_count,
						'affiliate-wp'
				); ?></p>
			<a href="<?php echo affwp_admin_url( 'affiliates' ) ?>"><?php _e( 'Back to Affiliates', 'affiliate-wp' ) ?></a>
		<?php endif; ?>
	</form>

</div>
