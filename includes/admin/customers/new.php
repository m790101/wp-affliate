<?php
/**
 * Admin: Add Customer View
 *
 * @package    AffiliateWP
 * @subpackage Admin/Customers
 * @copyright  Copyright (c) 2020, Sandhills Development, LLC
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.5.7
 */
?>
<div class="wrap">

	<h2><?php _e( 'New Customer', 'affiliate-wp' ); ?></h2>

	<form method="post" id="affwp_add_customer">

		<?php
		/**
		 * Fires at the top of the new-customer admin screen, just inside of the form element.
		 *
		 * @since 2.5.7
		 */
		do_action( 'affwp_new_customer_top' );
		?>

		<table class="form-table">

			<?php
			/**
			 * Fires at the end of the new-customer admin screen form area, below form fields.
			 *
			 * @since 2.5.7
			 */
			do_action( 'affwp_new_customer_end' );
			?>

		</table>

		<?php
		/**
		 * Fires at the bottom of the new-customer admin screen, prior to the submit button.
		 *
		 * @since 2.5.7
		 */
		do_action( 'affwp_new_customer_bottom' );
		?>

		<input type="hidden" name="affwp_action" value="add_customer" />

		<?php submit_button( __( 'Add Customer', 'affiliate-wp' ), 'primary', 'submit', true ); ?>

	</form>

</div>
