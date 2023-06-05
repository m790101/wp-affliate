<?php
/**
 * Admin: Review Affiliate View
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Affiliates
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

$affiliate               = affwp_get_affiliate( absint( $_GET['affiliate_id'] ) );
$affiliate_id            = $affiliate->affiliate_id;
$name                    = affiliate_wp()->affiliates->get_affiliate_name( $affiliate_id );
$user_info               = get_userdata( $affiliate->user_id );
$user_url                = $user_info->user_url;
$promotion_method        = get_user_meta( $affiliate->user_id, 'affwp_promotion_method', true );
$payment_email           = $affiliate->payment_email;
$dynamic_coupons_enabled = affiliate_wp()->settings->get( 'dynamic_coupons' );
$dynamic_coupons         = affwp_get_dynamic_affiliate_coupons( $affiliate_id, false );
$custom_fields           = affwp_get_custom_registration_fields( $affiliate_id, true );
?>
<div class="wrap">

	<h2><?php _e( 'Review Affiliate', 'affiliate-wp' ); ?> <?php affwp_admin_link( 'affiliates', __( 'Go Back', 'affiliate-wp' ), array(), array( 'class' => 'button-secondary' ) ); ?></h2>

	<form method="post" id="affwp_review_affiliate">

		<?php
		/**
		 * Fires at the top of the review-affiliate admin screen, just inside of the form element.
		 *
		 * @since 1.2
		 *
		 * @param \AffWP\Affiliate $affiliate Affiliate object.
		 */
		do_action( 'affwp_review_affiliate_top', $affiliate );
		?>

		<table class="form-table">

			<tr class="form-row form-required">

				<th scope="row">
					<?php _e( 'Name', 'affiliate-wp' ); ?>
				</th>

				<td>
					<?php echo esc_html( $name ); ?>
				</td>

			</tr>

			<tr class="form-row form-required">

				<th scope="row">
					<?php _e( 'Username', 'affiliate-wp' ); ?>
				</th>

				<td>
					<?php echo esc_html( $user_info->user_login ); ?>
				</td>

			</tr>

			<tr class="form-row form-required">

				<th scope="row">
					<?php _e( 'Email Address', 'affiliate-wp' ); ?>
				</th>

				<td>
					<?php echo esc_html( $user_info->user_email ); ?>
				</td>

			</tr>

			<?php if ( $payment_email ) : ?>
			<tr class="form-row form-required">

				<th scope="row">
					<?php _e( 'Payment Email', 'affiliate-wp' ); ?>
				</th>

				<td>
					<?php echo esc_html( $payment_email ); ?>
				</td>

			</tr>
			<?php endif; ?>

			<?php if ( $user_url ) : ?>
			<tr class="form-row form-required">

				<th scope="row">
					<?php _e( 'Website URL', 'affiliate-wp' ); ?>
				</th>

				<td>
					<a href="<?php echo esc_url( $user_url ); ?>" title="<?php _e( 'Affiliate&#8217;s Website URL', 'affiliate-wp' ); ?>" target="blank"><?php echo esc_url( $user_url ); ?></a>
				</td>

			</tr>
			<?php endif; ?>

			<?php if ( $promotion_method ) : ?>
				<tr class="form-row form-required">

					<th scope="row">
						<?php _e( 'Promotion Method', 'affiliate-wp' ); ?>
					</th>

					<td>
						<?php echo esc_html( $promotion_method ); ?>
					</td>

				</tr>
			<?php endif; ?>

			<?php foreach ( $custom_fields as $custom_field ):
				if( 'checkbox' === $custom_field['type'] ) {
						$value = (bool) $custom_field['meta_value'];
						$custom_field['meta_value'] = true === $value ? _x( 'Yes', 'registration checkbox enabled', 'affiliate-wp' ) : _x( 'No', 'registration checkbox disabled', 'affiliate-wp' );
				}
			?>
				<tr class="form-row">

					<th scope="row">
						<?php echo wp_strip_all_tags( $custom_field['name'] ) ?>
					</th>

					<td>
						<?php echo esc_html( $custom_field['meta_value'] ) ?>
					</td>

				</tr>
			<?php endforeach; ?>


			<tr class="form-row" id="affwp-rejection-reason">

				<th scope="row">
					<?php _e( 'Rejection Reason', 'affiliate-wp' ); ?>
				</th>

				<td>
					<textarea class="large-text" name="affwp_rejection_reason" rows="10"></textarea>
					<p class="description"><?php _e( 'Leave blank if approving this affiliate.', 'affiliate-wp' ); ?></p>
				</td>

			</tr>

			<?php if ( affwp_dynamic_coupons_is_setup() && empty( $dynamic_coupons ) ) : ?>

				<tr class="form-row">

					<th scope="row">
						<label for="dynamic_coupon"><?php _e( 'Dynamic Coupon', 'affiliate-wp' ); ?></label>
					</th>

					<td>
						<label class="description">
							<input type="checkbox" name="dynamic_coupon" id="dynamic_coupon" value="1" <?php checked( $dynamic_coupons_enabled, true ); ?> />
							<?php _e( 'Create dynamic coupon for affiliate?', 'affiliate-wp' ); ?>
						</label>
					</td>

				</tr>

			<?php endif; ?>

			<?php
			/**
			 * Fires at the end of the review-affiliate admin screen, prior to the closing table element tag.
			 *
			 * @since 1.2
			 *
			 * @param \AffWP\Affiliate $affiliate Affiliate object.
			 */
			do_action( 'affwp_review_affiliate_end', $affiliate );
			?>

		</table>

		<?php
		/**
		 * Fires at the bottom of the review-affiliate admin screen, just prior to the submit button.
		 *
		 * @since 1.2
		 *
		 * @param \AffWP\Affiliate $affiliate Affiliate object.
		 */
		do_action( 'affwp_review_affiliate_bottom', $affiliate );
		?>

		<?php wp_nonce_field( 'affwp_moderate_affiliates_nonce', 'affwp_moderate_affiliates_nonce' ); ?>
		<input type="hidden" name="affiliate_id" value="<?php echo esc_attr( absint( $affiliate_id ) ); ?>"/>
		<input type="hidden" name="affwp_action" value="moderate_affiliate"/>
		<input type="submit" name="affwp_accept" value="<?php esc_attr_e( __( 'Accept Affiliate', 'affiliate-wp' ) ); ?>" class="button button-primary"/>
		<input type="submit" name="affwp_reject" value="<?php esc_attr_e( __( 'Reject Affiliate', 'affiliate-wp' ) ); ?>" class="button button-secondary"/>

	</form>

</div>
