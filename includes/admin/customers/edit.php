<?php
/**
 * Admin: Edit Customer View
 *
 * @package    AffiliateWP
 * @subpackage Admin/Customers
 * @copyright  Copyright (c) 2020, Sandhills Development, LLC
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.5.7
 */

$customer_id = isset( $_REQUEST['customer_id'] ) ? intval( $_REQUEST['customer_id'] ) : 0;
$customer    = affwp_get_customer( $customer_id );
?>
<div class="wrap">

	<h2><?php _e( 'Edit Customer', 'affiliate-wp' ); ?></h2>

	<form method="post" id="affwp_edit_customer">

		<?php
		/**
		 * Fires at the top of the edit-customer admin screen, just inside of the form element.
		 *
		 * @since 2.5.7
		 *
		 * @param \AffWP\Customer $customer The customer object being edited.
		 */
		do_action( 'affwp_edit_customer_top', $customer );
		?>

		<table class="form-table">

			<?php
			/**
			 * Fires at the end of the edit-customer admin screen form area, below form fields.
			 *
			 * @since 2.5.7
			 *
			 * @param \AffWP\Customer $customer The customer object being edited.
			 */
			do_action( 'affwp_edit_customer_end', $customer );
			?>

		</table>

		<?php
		/**
		 * Fires at the bottom of the edit-customer admin screen, just before the submit button.
		 *
		 * @since 2.5.7
		 *
		 * @param \AffWP\Customer $customer The customer object being edited.
		 */
		do_action( 'affwp_edit_customer_bottom', $customer );
		?>

		<input type="hidden" name="affwp_action" value="update_customer" />

		<?php submit_button( __( 'Update Customer', 'affiliate-wp' ) ); ?>

	</form>

</div>
