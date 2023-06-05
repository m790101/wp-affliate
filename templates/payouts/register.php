<?php
/**
 * Payouts Service Registration Form
 *
 * This template is used to display the Payouts Service registration form.
 *
 * @package     AffiliateWP
 * @copyright   Copyright (c) 2019, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

$affiliate_id     = affwp_get_affiliate_id();
$user_email       = ! empty( $_REQUEST['email'] ) ? sanitize_text_field( $_REQUEST['email'] ) : affwp_get_affiliate_email( $affiliate_id );
$first_name       = ! empty( $_REQUEST['first_name'] ) ? sanitize_text_field( $_REQUEST['first_name'] ) : affwp_get_affiliate_first_name( $affiliate_id );
$last_name        = ! empty( $_REQUEST['last_name'] ) ? sanitize_text_field( $_REQUEST['last_name'] ) : affwp_get_affiliate_last_name( $affiliate_id );
$selected_country = ! empty( $_REQUEST['country'] ) ? sanitize_text_field( $_REQUEST['country'] ) : '';
$account_type     = ! empty( $_REQUEST['account_type'] ) ? sanitize_text_field( $_REQUEST['account_type'] ) : '';
$business_name    = ! empty( $_REQUEST['business_name'] ) ? sanitize_text_field( $_REQUEST['business_name'] ) : '';
$business_owner   = ! empty( $_REQUEST['business_owner'] ) ? sanitize_text_field( $_REQUEST['business_owner'] ) : '';
$day_of_birth     = ! empty( $_REQUEST['day_of_birth'] ) ? sanitize_text_field( $_REQUEST['day_of_birth'] ) : '';
$month_of_birth   = ! empty( $_REQUEST['month_of_birth'] ) ? sanitize_text_field( $_REQUEST['month_of_birth'] ) : '';
$year_of_birth    = ! empty( $_REQUEST['year_of_birth'] ) ? sanitize_text_field( $_REQUEST['year_of_birth'] ) : '';
$errors           = affiliate_wp()->affiliates->payouts->service_register->get_errors();
$error_codes      = affiliate_wp()->affiliates->payouts->service_register->get_error_codes();

$current_page_url = trailingslashit( get_permalink() );

if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
	$current_page_url = add_query_arg( wp_unslash( $_SERVER['QUERY_STRING'] ), '', $current_page_url );
}

$query_args = array(
	'affwp_action'     => 'payouts_service_connect_account',
	'current_page_url' => urlencode( $current_page_url ),
);
$connect_account_url = wp_nonce_url( add_query_arg( $query_args ), 'payouts_service_connect_account', 'payouts_service_connect_account_nonce' );

$months = array(
	'1'  => __( 'January', 'affiliate-wp' ),
	'2'  => __( 'February', 'affiliate-wp' ),
	'3'  => __( 'March', 'affiliate-wp' ),
	'4'  => __( 'April', 'affiliate-wp' ),
	'5'  => __( 'May', 'affiliate-wp' ),
	'6'  => __( 'June', 'affiliate-wp' ),
	'7'  => __( 'July', 'affiliate-wp' ),
	'8'  => __( 'August', 'affiliate-wp' ),
	'9'  => __( 'September', 'affiliate-wp' ),
	'10' => __( 'October', 'affiliate-wp' ),
	'11' => __( 'November', 'affiliate-wp' ),
	'12' => __( 'December', 'affiliate-wp' ),
);

affiliate_wp()->affiliates->payouts->service_register->print_errors();

if ( ! empty( $errors )
	 && ! ( in_array( 'service_account_not_created', $error_codes ) || in_array( 'http_request_failed', $error_codes ) )
) {

	if ( ! in_array( 'empty_country', $error_codes ) ) {
		$selected_country = sanitize_text_field( $_POST['country'] );
	}

	if ( ! in_array( 'empty_account_type', $error_codes ) ) {
		$account_type = sanitize_text_field( $_POST['account_type'] );
	}

	if ( ! in_array( 'empty_business_name', $error_codes ) ) {
		$business_name = sanitize_text_field( $_POST['business_name'] );
	}

	if ( ! in_array( 'empty_first_name', $error_codes ) ) {
		$first_name = sanitize_text_field( $_POST['first_name'] );
	}

	if ( ! in_array( 'empty_last_name', $error_codes ) ) {
		$last_name = sanitize_text_field( $_POST['last_name'] );
	}
	if ( ! in_array( 'empty_day_of_birth', $error_codes ) ) {
		$day_of_birth = sanitize_text_field( $_POST['day_of_birth'] );
	}

	if ( ! in_array( 'empty_month_of_birth', $error_codes ) ) {
		$month_of_birth = sanitize_text_field( $_POST['month_of_birth'] );
	}

	if ( ! in_array( 'empty_year_of_birth', $error_codes ) ) {
		$year_of_birth = sanitize_text_field( $_POST['year_of_birth'] );
	}

}
?>

<h4><?php esc_html_e( 'Payout Settings', 'affiliate-wp' ); ?></h4>

<?php

$payouts_service_description = affiliate_wp()->settings->get( 'payouts_service_description', '' );

if ( $payouts_service_description ) {
	echo '<p>' . wp_kses_post( nl2br( $payouts_service_description ) ) . '</p>';
}

?>

<?php /* translators: 1: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant, 2: Payouts service account connection URL */ ?>
<p><?php printf( __( 'Already have a %1$s account? Connect it <a href="%2$s">here</a>', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME, esc_url( $connect_account_url ) ); ?></p>

<form id="affwp-affiliate-dashboard-payouts-service-form" class="affwp-form" method="post">

	<div class="affwp-wrap affwp-payout-service-account-type-wrap">
		<label for="affwp-payout-service-account-type"><?php esc_html_e( 'Account Type', 'affiliate-wp' ); ?></label>
		<select name="account_type" id="affwp-payout-service-account-type" required>
			<option value="individual" <?php selected( $account_type, 'individual' ); ?>><?php esc_html_e( 'Personal Account', 'affiliate-wp' ); ?></option>
			<option value="company" <?php selected( $account_type, 'company' ); ?>><?php esc_html_e( 'Business Account', 'affiliate-wp' ); ?></option>
		</select>
	</div>

	<div class="affwp-wrap affwp-payout-service-country-wrap">
		<label for="affwp-payout-service-country"><?php esc_html_e( 'Your Country of Residence', 'affiliate-wp' ); ?></label>
		<select name="country" id="affwp-payout-service-country" required>
			<option value=""></option>
			<?php
			$countries = affwp_get_payouts_service_country_list();
			foreach ( $countries as $country_code => $country ) {
				echo '<option value="' . esc_attr( $country_code ) . '"' . selected( $country_code, $selected_country, false ) . '>' . $country . '</option>';
			}
			?>
		</select>
	</div>

	<div class="affwp-wrap affwp-payout-service-business-name-wrap" style="display:none;">
		<label for="affwp-payout-service-business-name"><?php esc_html_e( 'Your Business Name', 'affiliate-wp' ); ?></label>
		<input id="affwp-payout-service-business-name" type="text" name="business_name" value="<?php echo esc_attr( $business_name ); ?>">
	</div>

	<div class="affwp-wrap affwp-payout-service-business-owner-wrap" style="display:none;">
		<label class="affwp-payout-service-business-owner" for="affwp-payout-service-business-owner">
			<input id="affwp-payout-service-business-owner" type="checkbox" value="1" name="business_owner" <?php checked( $business_owner, 1 ); ?>>
			<?php esc_html_e( 'I am the owner of the business legal entity', 'affiliate-wp' ); ?>
		</label>
	</div>

	<div class="affwp-wrap affwp-payout-service-first-name-wrap">
		<label for="affwp-payout-service-first-name"><?php esc_html_e( 'Your First Name', 'affiliate-wp' ); ?></label>
		<input id="affwp-payout-service-first-name" type="text" name="first_name" value="<?php echo esc_attr( $first_name ); ?>" required>
	</div>

	<div class="affwp-wrap affwp-payout-service-last-name-wrap">
		<label for="affwp-payout-service-last-name"><?php esc_html_e( 'Your Last Name', 'affiliate-wp' ); ?></label>
		<input id="affwp-payout-service-last-name" type="text" name="last_name" value="<?php echo esc_attr( $last_name ); ?>" required>
	</div>

	<div class="affwp-wrap affwp-payout-service-email-wrap">
		<label for="affwp-payout-service-email"><?php esc_html_e( 'Your Email', 'affiliate-wp' ); ?></label>
		<input id="affwp-payout-service-email" type="email" name="email" value="<?php echo esc_attr( $user_email ); ?>" required>
	</div>

	<div class="affwp-wrap affwp-payout-service-dob-wrap">

		<label for="day-of-birth"><?php esc_html_e( 'Date of Birth', 'affiliate-wp' ); ?></label>

		<div>
			<select id="day-of-birth" name="day_of_birth" required>
				<option value=""><?php esc_html_e( 'Day', 'affiliate-wp' ); ?></option>
				<?php foreach ( range( 1, 31 ) as $day ) : ?>
					<option value="<?php echo $day; ?>" <?php selected( $day_of_birth, $day ); ?>><?php echo $day; ?></option>
				<?php endforeach; ?>
			</select>

			<select id="month-of-birth" name="month_of_birth" required>
				<option value=""><?php esc_html_e( 'Month', 'affiliate-wp' ); ?></option>
				<?php foreach ( $months as $key => $month ) : ?>
					<option value="<?php echo $key; ?>" <?php selected( $month_of_birth, $key ); ?>><?php echo $month; ?></option>
				<?php endforeach; ?>
			</select>

			<select id="year-of-birth" name="year_of_birth" required>
				<option value=""><?php esc_html_e( 'Year', 'affiliate-wp' ); ?></option>
				<?php foreach ( array_reverse( range( 1905, date( 'Y' ) ) ) as $year ): ?>
					<option value="<?php echo $year; ?>" <?php selected( $year_of_birth, $year ); ?>><?php echo $year; ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<?php $terms_of_use = affiliate_wp()->settings->get( 'terms_of_use', '' ); ?>
	<?php if ( ! empty( $terms_of_use ) ) : ?>
		<div class="affwp-wrap affwp-payout-tos-wrap">
			<label class="affwp-payout-service-tos" for="affwp-payout-service-tos">
				<input id="affwp-payout-service-tos" required="required" type="checkbox" name="tos">
				<a href="<?php echo esc_url( get_permalink( $terms_of_use ) ); ?>" target="_blank"><?php echo affiliate_wp()->settings->get( 'terms_of_use_label', __( 'Agree to our Terms of Use and Privacy Policy', 'affiliate-wp' ) ); ?></a>
			</label>
		</div>
	<?php endif; ?>

	<div class="affwp-register-payouts-service-wrap">
		<input type="hidden" name="affwp_action" value="payouts_service_register" />
		<input type="hidden" name="affiliate_id" value="<?php echo esc_attr( absint( $affiliate_id ) ); ?>" />
		<input type="hidden" name="current_page_url" value="<?php echo esc_url( $current_page_url ); ?>" />
		<input type="submit" class="button" value="<?php esc_attr_e( 'Register for Payouts Service', 'affiliate-wp' ); ?>" />
	</div>

</form>
