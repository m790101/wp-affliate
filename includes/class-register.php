<?php
/**
 * User Registration Bootstrap
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

class Affiliate_WP_Register {

	private $errors;

	/**
	 * Get things started
	 *
	 * @since 1.0
	 */
	public function __construct() {

		add_action( 'affwp_affiliate_register', array( $this, 'process_registration' ) );
		add_action( 'user_register', array( $this, 'auto_register_user_as_affiliate' ) );
		add_action( 'user_new_form', array( $this, 'add_as_affiliate' ) );
		add_action( 'user_register', array( $this, 'process_add_as_affiliate' ) );
		add_action( 'added_existing_user', array( $this, 'process_add_as_affiliate' ) );
		add_action( 'admin_footer', array( $this, 'scripts' ) );

		add_filter( 'affwp_register_required_fields', array( $this, 'maybe_required_fields' ) );
	}

	/**
	 * Register Form
	 *
	 * @since 1.2
	 * @global $affwp_register_redirect
	 * @param string $redirect Redirect page URL
	 * @return string Register form
	*/
	public function register_form( $redirect = '' ) {
		global $affwp_register_redirect;

		if ( empty( $redirect ) ) {
			$redirect = affiliate_wp()->tracking->get_current_page_url();
		}

		$affwp_register_redirect = $redirect;

		ob_start();

		affiliate_wp()->templates->get_template_part( 'register' );

		/**
		 * Filters the output for the AffiliateWP registration form.
		 *
		 * @since 1.2
		 *
		 * @param string $output Registration form output.
		 */
		return apply_filters( 'affwp_register_form', ob_get_clean() );

	}

	/**
	 * Process registration form submission
	 *
	 * @since 1.0
	 */
	public function process_registration( $data ) {

		if ( ! isset( $_POST['affwp_register_nonce'] ) || ! wp_verify_nonce( $_POST['affwp_register_nonce'], 'affwp-register-nonce' ) ) {
			return;
		}

		/**
		 * Fires immediately prior to processing an affiliate registration form.
		 *
		 * @since 1.0
		 */
		do_action( 'affwp_pre_process_register_form' );
		$block_form = false;

		if ( isset( $_POST['affwp_post_id'] ) && isset( $_POST['affwp_block_hash'] ) ) {
			$block_form = affiliate_wp()->editor->get_submission_form( $_POST['affwp_post_id'], $_POST['affwp_block_hash'] );

			if ( is_wp_error( $block_form ) ) {
				$this->add_error( 'invalid_form', __( 'Something went wrong when submitting this form, please contact an administrator.', 'affiliate-wp' ) );
			} else {

				foreach ( $block_form->fields as $field ) {

					// Ignore legacy fields. The logic for these is handled downstream.
					if ( ! $field->is_legacy_field() && isset( $_POST[ $field->name ] ) ) {

						$is_valid = $field->validate( $_POST[ $field->name ] );

						if ( is_wp_error( $is_valid ) ) {
							foreach ( $is_valid->get_error_codes() as $error_code ) {
								$this->add_error( $error_code, $is_valid->get_error_message( $error_code ) );
							}
						}
					}
				}
			}
		}

		$user_login = isset( $_POST['affwp_user_login'] ) ? sanitize_text_field( $_POST['affwp_user_login'] ) : '';
		$user_email = isset( $_POST['affwp_user_email'] ) ? sanitize_text_field( $_POST['affwp_user_email'] ) : '';

		// Grab the user login and email if the current user is logged in.
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			if ( null !== $user ) {
				$user_login = $user->get( 'user_login' );
				$user_email = $user->get( 'user_email' );
			}
		}

		if ( ! is_wp_error( $block_form ) ) {

			if ( ! is_user_logged_in() ) {

				if ( false === $block_form ) {
					// Loop through required fields and show error message
					foreach ( $this->required_fields() as $field_name => $value ) {

						// Skip field if it doesn't exist.
						if ( ! isset( $_POST[ $field_name ] ) ) {
							$this->add_error( $value['error_id'], $value['error_message'] );
							continue;
						}

						$field = sanitize_text_field( $_POST[ $field_name ] );

						if ( empty( $field ) ) {
							$this->add_error( $value['error_id'], $value['error_message'] );
						}

						if ( 'affwp_user_url' === $field_name && false === filter_var( esc_url( $field ), FILTER_VALIDATE_URL ) ) {
							$this->add_error( 'invalid_url', __( 'Please enter a valid website URL', 'affiliate-wp' ) );
						}

					}
				}

				if ( username_exists( $user_login ) ) {
					$this->add_error( 'username_unavailable', __( 'Username already taken', 'affiliate-wp' ) );
				}

				if ( ! validate_username( $user_login ) || strstr( $user_login, ' ' ) ) {
					if ( is_multisite() ) {
						$this->add_error( 'username_invalid', __( 'Invalid username. Only lowercase letters (a-z) and numbers are allowed', 'affiliate-wp' ) );
					} else {
						$this->add_error( 'username_invalid', __( 'Invalid username', 'affiliate-wp' ) );
					}
				}

				if ( strlen( $user_login ) > 60 ) {
					$this->add_error( 'username_invalid_length', __( 'Invalid username. Must be between 1 and 60 characters.', 'affiliate-wp' ) );
				}

				if ( is_numeric( $user_login ) ) {
					$this->add_error( 'username_invalid_numeric', __( 'Invalid username. Usernames must include at least one letter', 'affiliate-wp' ) );
				}

				if ( email_exists( $user_email ) ) {
					$this->add_error( 'email_unavailable', __( 'Email address already taken', 'affiliate-wp' ) );
				}

				if ( empty( $user_email ) || ! is_email( $user_email ) ) {
					$this->add_error( 'email_invalid', __( 'Invalid account email', 'affiliate-wp' ) );
				}

				if ( ! empty( $data['affwp_payment_email'] ) && $data['affwp_payment_email'] != $user_email && ! is_email( $data['affwp_payment_email'] ) ) {
					$this->add_error( 'payment_email_invalid', __( 'Invalid payment email', 'affiliate-wp' ) );
				}

				$required_registration_fields = affiliate_wp()->settings->get( 'required_registration_fields' );

				// Password fields for block and non-block forms.
				if ( false === $block_form ) {
					if ( isset( $required_registration_fields['password'] ) ) {
						if ( isset( $data['affwp_user_pass'] ) && isset( $data['affwp_user_pass2'] ) ) {
							if ( $data['affwp_user_pass'] !== $data['affwp_user_pass2'] ) {
								$this->add_error( 'password_mismatch', __( 'Passwords do not match', 'affiliate-wp' ) );
							}
						} else {
							$this->add_error( 'password_missing', __( 'Both password fields are required.', 'affiliate-wp' ) );
						}
					}
				} else {
					if ( isset( $_POST['affwp_password_text'] ) && isset( $_POST['affwp_password_text_confirm'] )
					     && ( $_POST['affwp_password_text'] !== $_POST['affwp_password_text_confirm'] )
					) {
						$this->add_error( 'password_mismatch', __( 'Passwords do not match', 'affiliate-wp' ) );
					}
				}

			} else {

				if ( false === $block_form ) {
					// Loop through required fields and show error message
					foreach ( $this->required_fields() as $field_name => $value ) {
						// Skip the password fields for logged-in users.
						if ( 'affwp_user_pass' === $field_name || 'affwp_user_pass2' === $field_name ) {
							continue;
						}

						// Skip field if it doesn't exist.
						if ( ! isset( $data[ $field_name ] ) ) {
							$this->add_error( $value['error_id'], $value['error_message'] );
							continue;
						}

						if ( ! empty( $value['logged_out'] ) ) {
							continue;
						}

						$field = sanitize_text_field( $data[ $field_name ] );

						if ( empty( $field ) ) {
							$this->add_error( $value['error_id'], $value['error_message'] );
						}
					}
				}

			}

			/*
			 * Only check terms of use when the block form is not set.
			 *
			 * The block-based form can, and should, add its own terms of use checkbox, which is validated before this.
			 */
			if ( false === $block_form ) {
				$terms_of_use = affiliate_wp()->settings->get( 'terms_of_use' );

				if ( ! empty( $terms_of_use ) && empty( $data['affwp_tos'] ) ) {
					$this->add_error( 'empty_tos', __( 'Please agree to our terms of use', 'affiliate-wp' ) );
				}
			}

			if ( affwp_is_recaptcha_enabled() && ! $this->recaptcha_response_is_valid( $data ) ) {
				$this->add_error( 'recaptcha_required', __( 'Please verify that you are not a robot', 'affiliate-wp' ) );
			}

			if ( ! empty( $data['affwp_honeypot'] ) ) {
				$this->add_error( 'spam', __( 'Nice try honey bear, don&#8217;t touch our honey', 'affiliate-wp' ) );
			}

			if ( affwp_is_affiliate() ) {
				$this->add_error( 'already_registered', __( 'You are already registered as an affiliate', 'affiliate-wp' ) );
			}

			/**
			 * Fires after processing an affiliate registration form.
			 *
			 * @since 1.0
			 */
			do_action( 'affwp_process_register_form' );
		}

		// only log the user in if there are no errors
		if ( empty( $this->errors ) ) {
			$affiliate_id = $this->register_user( $user_email );

			if ( $block_form instanceof \AffWP\Core\Registration\Form_Container && false !== $affiliate_id ) {
				$custom_fields = array();

				foreach ( $block_form->fields as $field ) {
					// Ignore legacy fields.
					if ( ! $field->is_legacy_field() && isset( $_POST[ $field->name ] ) ) {
						$custom_fields[] = array(
							'meta_key' => $field->meta_field,
							'name'     => $field->label,
							'type'     => $field->field_type
						);
						affwp_update_affiliate_meta( $affiliate_id, $field->meta_field, $field->sanitize( $_POST[ $field->name ] ) );
					}
				}

				if ( ! empty( $custom_fields ) ) {
					affwp_update_affiliate_meta( $affiliate_id, '_submitted_custom_registration_fields', $custom_fields );
				}
			}

			$redirect = empty( $data['affwp_redirect'] ) ? affwp_get_affiliate_area_page_url() : $data['affwp_redirect'];

			/**
			 * Filters the redirect URL used after a successful AffiliateWP registration.
			 *
			 * @since 1.0
			 *
			 * @param string $redirect Redirect URL.
			 */
			$redirect = apply_filters( 'affwp_register_redirect', $redirect );

			if ( $redirect ) {
				wp_redirect( $redirect );
				exit;
			}

		}

	}

	/**
	 * Verify reCAPTCHA response is valid using a POST request to the Google API
	 *
	 * @access private
	 * @since  1.7
	 * @param  array   $data
	 * @return boolean
	 */
	private function recaptcha_response_is_valid( $data ) {
		if ( ! affwp_is_recaptcha_enabled() || empty( $data['g-recaptcha-response'] ) || empty( $data['g-recaptcha-remoteip'] ) ) {
			return false;
		}

		$verify = wp_safe_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => affiliate_wp()->settings->get( 'recaptcha_secret_key' ),
					'response' => $data['g-recaptcha-response'],
					'remoteip' => $data['g-recaptcha-remoteip']
				)
			)
		);

		$verify = json_decode( wp_remote_retrieve_body( $verify ) );

		return ( ! empty( $verify->success ) && true === $verify->success );
	}

	/**
	 * Register Form Required Fields
	 *
	 * @access      public
	 * @since       1.1.4
	 * @return      array
	 */
	public function required_fields() {
		$required_fields = array(
			'affwp_user_name' 	=> array(
				'error_id'      => 'empty_name',
				'error_message' => __( 'Please enter your name', 'affiliate-wp' ),
				'logged_out'    => true
			),
			'affwp_user_login' 	=> array(
				'error_id'      => 'empty_username',
				'error_message' => __( 'Invalid username. Must be between 1 and 60 characters.', 'affiliate-wp' ),
				'logged_out'    => true
			),
			'affwp_user_url' 	=> array(
				'error_id'      => 'invalid_url',
				'error_message' => __( 'Please enter a website URL', 'affiliate-wp' )
			)
		);

		/**
		 * Filters the list of required registration fields and their attributes.
		 *
		 * @since 1.1.4
		 *
		 * @param array $required_fields {
		 *     Required registration fields.
		 *
		 *     @type string $error_id     Error ID.
		 *     @type string error_message Translatable error message.
		 *     @type bool   $logged_out   Whether to output errors while logged out.
		 * }
		 */
		return apply_filters( 'affwp_register_required_fields', $required_fields );
	}



	/**
	 * Makes fields required/not required, based on the "Required Registration Fields"
	 * admin setting
	 *
	 * @access public
	 * @since  2.0
	 *
	 * @param array $required_fields The required fields
	 * @return array $required_fields The required fields
	 */
	public function maybe_required_fields( $required_fields ) {

		// Get the required fields from the settings
		$required_registration_fields = affiliate_wp()->settings->get( 'required_registration_fields' );

		/**
		 * Fields that are already required by default
		 */

		// Your Name
		if ( ! isset( $required_registration_fields['your_name'] ) ) {
			unset( $required_fields['affwp_user_name'] );
		}

		// Website URL
		if ( ! isset( $required_registration_fields['website_url'] ) ) {
			unset( $required_fields['affwp_user_url'] );
		}

		/**
		 * Fields that are not required by default
		 */

		// Payment Email
		if ( isset( $required_registration_fields['payment_email'] ) ) {
			$required_fields['affwp_payment_email']['error_id']      = 'empty_payment_email';
			$required_fields['affwp_payment_email']['error_message'] = __( 'Please enter your payment email', 'affiliate-wp' );
			$required_fields['affwp_payment_email']['logged_out']    = true;
		}

		// How will you promote us?
		if ( isset( $required_registration_fields['promotion_method'] ) ) {
			$required_fields['affwp_promotion_method']['error_id']      = 'empty_promotion_method';
			$required_fields['affwp_promotion_method']['error_message'] = __( 'Please tell us how you will promote us', 'affiliate-wp' );
			$required_fields['affwp_promotion_method']['logged_out']    = true;
		}

		// Password
		if ( isset( $required_registration_fields['password'] ) ) {
			$required_fields['affwp_user_pass']['error_id']      = 'empty_password';
			$required_fields['affwp_user_pass']['error_message'] = __( 'Please enter a password', 'affiliate-wp' );
			$required_fields['affwp_user_pass']['logged_out']    = true;
		}

		return $required_fields;

	}

	/**
	 * Register the affiliate / user
	 *
	 * @since 1.0
	 * @since 2.8.1 The `$user_email` parameter was added.
	 *
	 * @param string $user_email Optional. User email. Registration will be skipped if omitted. Default empty.
	 * @return int|false The newly-created affiliate ID if successful, otherwise false.
	 */
	private function register_user( $user_email = '' ) {

		if ( empty( $user_email ) ) {
			return false;
		}

		if ( ! empty( $_POST['affwp_user_name'] ) ) {
			$name       = explode( ' ', sanitize_text_field( $_POST['affwp_user_name'] ) );
			$user_first = array_shift( $name );
			$user_last = count( $name ) ? implode( ' ', $name ) : '';
		} else {
			$user_first = '';
			$user_last  = '';
		}

		$required_registration_fields = affiliate_wp()->settings->get( 'required_registration_fields' );

		// Start with a random password.
		$user_pass = wp_generate_password( 24 );

		// Password from the standard registration form.
		if ( isset( $required_registration_fields['password'] ) && isset( $_POST['affwp_user_pass'] ) ) {
			$user_pass = sanitize_text_field( $_POST['affwp_user_pass'] );
		}

		// Passwords from the block form.
		if ( isset( $_POST['affwp_password_text'] ) && isset( $_POST['affwp_password_text_confirm'] ) ) {
			$user_pass = sanitize_text_field( $_POST['affwp_password_text'] );
		}

		if ( ! is_user_logged_in() ) {

			$user_login = isset( $_POST['affwp_user_login'] ) ? sanitize_text_field( $_POST['affwp_user_login'] ) : $user_email;

			$args = array(
				'user_login'    => $user_login,
				'user_email'    => $user_email,
				'user_pass'     => $user_pass,
				'display_name'  => trim( $user_first . ' ' . $user_last ),
			);

			$new_user = true;

			$user_id = wp_insert_user( $args );

			// Enable referral notifications by default for new users.
			update_user_meta( $user_id, 'affwp_referral_notifications', true );

		} else {

			$new_user = false;

			$user_id = get_current_user_id();
			$user    = (array) get_userdata( $user_id );

			if ( isset( $user['data'] ) ) {
				$args = (array) $user['data'];
			} else {
				$args = array();
			}

		}

		// update first and last name
		wp_update_user( array(
			'ID'         => $user_id,
			'first_name' => $user_first,
			'last_name'  => $user_last
		) );

		// website URL
		$website_url = isset( $_POST['affwp_user_url'] ) ? sanitize_text_field( $_POST['affwp_user_url'] ) : '';

		$status = affiliate_wp()->settings->get( 'require_approval' ) ? 'pending' : 'active';

		affwp_add_affiliate( array(
			'user_id'        => $user_id,
			'payment_email'  => ! empty( $_POST['affwp_payment_email'] ) ? sanitize_text_field( $_POST['affwp_payment_email'] ) : $user_email,
			'status'         => $status,
			'website_url'    => $website_url,
			'dynamic_coupon' => ! affiliate_wp()->settings->get( 'require_approval' ) ? 1 : '',
		) );

		if ( ! is_user_logged_in() ) {
			$this->log_user_in( $user_id, $user_login );
		}

		// Retrieve affiliate ID. Resolves issues with caching on some hosts, such as GoDaddy
		$affiliate_id = affwp_get_affiliate_id( $user_id );

		if ( true === $new_user ) {
			// Enable referral notifications by default for new users.
			affwp_update_affiliate_meta( $affiliate_id, 'referral_notifications', true );
		}

		// promotion method
		$promotion_method = isset( $_POST['affwp_promotion_method'] ) ? sanitize_text_field( $_POST['affwp_promotion_method'] ) : '';

		if ( $promotion_method ) {
			affwp_update_affiliate_meta( $affiliate_id, 'promotion_method', $promotion_method );
		}

		/**
		 * Fires immediately after registering a user.
		 *
		 * @since 1.0
		 *
		 * @param int    $affiliate_id Affiliate ID.
		 * @param string $status       Affiliate status.
		 * @param array  $args         Data arguments used when registering the user.
		 */
		do_action( 'affwp_register_user', $affiliate_id, $status, $args );

		return (int) $affiliate_id;
	}

	/**
	 * Logs the user in.
	 *
	 * @since 1.0
	 *
	 * @param  $user_id    The user ID.
	 * @param  $user_login The `user_login` for the user.
	 * @param  $remember   Whether or not the browser should remember the user login.
	 */
	private function log_user_in( $user_id = 0, $user_login = '', $remember = false ) {

		$user = get_userdata( $user_id );
		if ( ! $user )
			return;

		wp_set_auth_cookie( $user_id, $remember );
		wp_set_current_user( $user_id, $user_login );

		/**
		 * The `wp_login` action is fired here to maintain compatibility and stability of
		 * any WordPress core features, plugins, or themes hooking onto it.
		 *
		 * @param  string   $user_login The `user_login` for the user.
		 * @param  stdClass $user       The user object.
		 */
		do_action( 'wp_login', $user_login, $user );

	}

	/**
	 * Register a user as an affiliate during user registration
	 *
	 * @since  1.1
	 * @return bool
	 *
	 * @param  $user_id The user ID.
	 */
	public function auto_register_user_as_affiliate( $user_id = 0 ) {

		if ( ! affiliate_wp()->settings->get( 'auto_register' ) ) {
			return;
		}

		if ( did_action( 'affwp_affiliate_register' ) ) {
			return;
		}

		$affiliate_id = affwp_add_affiliate( array(
			'user_id'        => $user_id,
			'dynamic_coupon' => ! affiliate_wp()->settings->get( 'require_approval' ) ? 1 : '',
		) );

		if ( ! $affiliate_id ) {
			return;
		}

		$status = affwp_get_affiliate_status( $affiliate_id );
		$user   = (array) get_userdata( $user_id );
		$args   = (array) $user['data'];

		/**
		 * Fires immediately after a new user has been auto-registered as an affiliate
		 *
		 * @since 1.7
		 *
		 * @param int    $affiliate_id Affiliate ID.
		 * @param string $status       The affiliate status.
		 * @param array  $args         Affiliate data.
		 */
		do_action( 'affwp_auto_register_user', $affiliate_id, $status, $args );

	}

	/**
	 * Register a submission error
	 *
	 * @since 1.0
	 */
	public function add_error( $error_id, $message = '' ) {
		$this->errors[ $error_id ] = $message;
	}

	/**
	 * Print errors
	 *
	 * @since 1.0
	 */
	public function print_errors() {

		if ( empty( $this->errors ) ) {
			return;
		}

		echo '<div class="affwp-errors">';

		foreach( $this->errors as $error_id => $error ) {

			echo '<p class="affwp-error">' . esc_html( $error ) . '</p>';

		}

		echo '</div>';

	}

	/**
	 * Get errors
	 *
	 * @since 1.1
	 * @return array
	 */
	public function get_errors() {

		if ( empty( $this->errors ) ) {
			return array();
		}

		return $this->errors;

	}

	/**
	 * Adds an "Add As Affiliate" checkbox to the WordPress "Add New User" screen
	 * On multisite this will only show when the "Skip Confirmation Email" checkbox is enabled
	 *
	 * @since 1.8
	 * @return void
	 */
	public function add_as_affiliate( $context ) {

		if ( affiliate_wp()->settings->get( 'auto_register' ) ) {
			return;
		}

		?>
		<table id="affwp-create-affiliate" class="form-table" style="margin-top:0;">
			<tr>
				<th scope="row"><label for="create-affiliate-<?php echo $context; ?>"><?php _e( 'Add as Affiliate',  'affiliate-wp' ); ?></label></th>
				<td>
					<label for="create-affiliate-<?php echo $context; ?>"><input type="checkbox" id="create-affiliate-<?php echo $context; ?>" name="affwp_create_affiliate" value="1" /> <?php _e( 'Add the user as an affiliate.', 'affiliate-wp' ); ?></label>
				</td>
			</tr>
			<?php if ( affwp_dynamic_coupons_is_setup() ) : ?>
				<tr class="form-row" id="affwp-affiliate-coupon-row">
					<th scope="row">
						<label for="dynamic-coupon-<?php echo $context; ?>"><?php _e( 'Dynamic Coupon', 'affiliate-wp' ); ?></label>
					</th>
					<td>
						<label for="dynamic-coupon-<?php echo $context; ?>">
							<input type="checkbox" name="dynamic_coupon" id="dynamic-coupon-<?php echo $context; ?>" value="1" />
							<?php _e( 'Create dynamic coupon for affiliate?', 'affiliate-wp' ); ?>
						</label>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( ! affiliate_wp()->emails->is_email_disabled() ) : ?>
			<tr>
				<th scope="row"><label for="disable-affiliate-email-<?php echo $context; ?>"><?php _e( 'Disable Affiliate Email',  'affiliate-wp' ); ?></label></th>
				<td>
					<label for="disable-affiliate-email-<?php echo $context; ?>"><input type="checkbox" id="disable-affiliate-email-<?php echo $context; ?>" name="disable_affiliate_email" value="1" /> <?php _e( 'Disable the application accepted email sent to the affiliate.', 'affiliate-wp' ); ?></label>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Adds a new affiliate when the "Add As Affiliate" checkbox is enabled
	 * Only works when "Skip Confirmation Email" is enabled
	 *
	 * @since 1.8
	 * @return void
	 */
	public function process_add_as_affiliate( $user_id = 0 ) {

		if ( affiliate_wp()->settings->get( 'auto_register' ) ) {
			return;
		}

		$add_affiliate     = isset( $_POST['affwp_create_affiliate'] ) ? $_POST['affwp_create_affiliate'] : '';
		$skip_confirmation = isset( $_POST['noconfirmation'] ) ? $_POST['noconfirmation'] : '';

		if ( is_multisite() && ! ( $add_affiliate && $skip_confirmation ) ) {
			return;
		} elseif ( ! $add_affiliate ) {
			return;
		}

		if ( $add_affiliate && isset( $_POST['disable_affiliate_email'] ) ) {
			add_filter( 'affwp_notify_on_approval', '__return_false' );
		}

		// add the affiliate
		affwp_add_affiliate( array(
			'user_id'        => $user_id,
			'dynamic_coupon' => isset( $_POST['dynamic_coupon'] ) ? $_POST['dynamic_coupon'] : '',
		) );

	}

	/**
	 * Scripts
	 *
	 * @since 1.8
	 * @return void
	 */
	function scripts() {

		if ( affiliate_wp()->settings->get( 'auto_register' ) ) {
			return;
		}

		global $pagenow;

		/**
		 * Javascript for the "Add New User" screen on (multisite only)
		 */
		if ( ( ! empty( $pagenow ) && ( 'user-new.php' === $pagenow ) && is_multisite() ) ) : ?>

		<script>
		jQuery(document).ready(function($) {

			var optionSkipConfirmation = $('input[name="noconfirmation"]');

			// show or hide the add affiliate table based on the "Skip Confirmation" checkbox option
			optionSkipConfirmation.click( function(e) {

				var tableNoConfirmation = this.closest('table');
				var tableAddAffiliate = $( tableNoConfirmation ).next('table');

				if ( this.checked ) {
					tableAddAffiliate.show();

				} else {
					tableAddAffiliate.hide();
				}

			});

			var tableNoConfirmation = $( optionSkipConfirmation ).closest('table');
			var tableAddAffiliate = $( tableNoConfirmation ).next('table');

			if ( optionSkipConfirmation.is(':checked') ) {
				tableAddAffiliate.show();
			} else {
				tableAddAffiliate.hide();
			}

		});

		</script>

		<?php endif;

	}

}
