<?php
/**
 * Editor Component
 *
 * Sets up integration code for the block editor.
 *
 * @package     AffiliateWP
 * @subpackage  Core
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.8
 */

use AffWP\Core\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core class used to set up the Editor component.
 *
 * @since 2.8
 */
final class Affiliate_WP_Editor {

	private static $current_form = 0;

	/**
	 * Set to true if blocks_init has ran.
	 *
	 * @since 2.8
	 *
	 * @var bool True if init ran, otherwise false.
	 */
	private $init_ran;

	/**
	 * Editor constructor.
	 *
	 * @since 2.8
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Sets up the default hooks and actions.
	 *
	 * @since 2.8
	 */
	private function hooks() {
		global $wp_version;

		// Set up Blocks
		add_action( 'init', array( $this, 'blocks_init' ) );

		// Set up block categories
		if ( version_compare( $wp_version, '5.8', '>=' ) ) {
			add_filter( 'block_categories_all', array( $this, 'add_block_category' ), 10, 2 );
		} else {
			add_filter( 'block_categories', array( $this, 'add_block_category' ), 10, 2 );
		}

		// Add form data to meta
		add_action( 'save_post', array( $this, 'save_submission_form_hashes' ) );
	}

	/**
	 * Registers all block assets so that they can be enqueued through the block editor
	 * in the corresponding context.
	 *
	 * @since 2.8
	 */
	public function blocks_init() {

		// Bail early if init has already ran
		if ( true === $this->init_ran ) {
			return;
		}

		$this->init_ran = true;

		$script_asset_path = AFFILIATEWP_PLUGIN_DIR . "assets/js/editor/build/index.asset.php";
		$script_asset      = include( $script_asset_path );

		wp_register_script(
			'affiliatewp-blocks-editor',
			AFFILIATEWP_PLUGIN_URL . 'assets/js/editor/build/index.js',
			$script_asset['dependencies'],
			$script_asset['version']
		);

		wp_set_script_translations(
			'affiliatewp-blocks-editor',
			'affiliate-wp',
			AFFILIATEWP_PLUGIN_DIR . 'languages'
		);

		wp_localize_script( 'affiliatewp-blocks-editor', 'affwp_blocks', array(
			'terms_of_use'                 => affiliate_wp()->settings->get( 'terms_of_use' ) ? true : false,
			'terms_of_use_link'            => affiliate_wp()->settings->get( 'terms_of_use' ) ? get_permalink( affiliate_wp()->settings->get( 'terms_of_use' ) ) : '',
			'terms_of_use_label'           => affiliate_wp()->settings->get( 'terms_of_use_label', __( 'Agree to our Terms of Use and Privacy Policy', 'affiliate-wp' ) ),
			'required_registration_fields' => affiliate_wp()->settings->get( 'required_registration_fields' ),
			'affiliate_area_forms'         => affiliate_wp()->settings->get( 'affiliate_area_forms' ),
			'allow_affiliate_registration' => affiliate_wp()->settings->get( 'allow_affiliate_registration' ),
			'affiliate_id'                 => affwp_get_affiliate_id( get_current_user_id() ),
			'affiliate_username'           => affwp_get_affiliate_username( affwp_get_affiliate_id( get_current_user_id() ) ),
			'referral_variable'            => affiliate_wp()->tracking->get_referral_var(),
			'referral_format'              => affwp_get_referral_format(),
			'pretty_referral_urls'         => affwp_is_pretty_referral_urls(),
		) );

		wp_register_style(
			'affiliatewp-blocks-editor',
			AFFILIATEWP_PLUGIN_URL . 'assets/css/editor.css',
			array(),
			AFFILIATEWP_VERSION
		);

		// Affiliate Content block.
		register_block_type(
			'affiliatewp/affiliate-content',
			array(
				'editor_script'   => 'affiliatewp-blocks-editor',
				'editor_style'    => 'affiliatewp-blocks-editor',
				'render_callback' => array( $this, 'affiliate_content_block_render_callback' ),
			)
		);

		// Non-affiliate Content block.
		register_block_type(
			'affiliatewp/non-affiliate-content',
			array(
				'editor_script'   => 'affiliatewp-blocks-editor',
				'editor_style'    => 'affiliatewp-blocks-editor',
				'render_callback' => array( $this, 'non_affiliate_content_block_render_callback' ),
			)
		);

		// Opt-in block.
		register_block_type(
			'affiliatewp/opt-in',
			array(
				'editor_script'   => 'affiliatewp-blocks-editor',
				'editor_style'    => 'affiliatewp-blocks-editor',
				'render_callback' => array( $this, 'opt_in_block_render_callback' ),
				'attributes'      => array(
					'redirect' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		// Affiliate Referral URL block.
		register_block_type(
			'affiliatewp/affiliate-referral-url',
			array(
				'editor_script'   => 'affiliatewp-blocks-editor',
				'editor_style'    => 'affiliatewp-blocks-editor',
				'render_callback' => array( $this, 'affiliate_referral_url_block_render_callback' ),
				'attributes'      => array(
					'url'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'format' => array(
						'type'    => 'string',
						'default' => 'default',
					),
					'pretty' => array(
						'type'    => 'string',
						'default' => 'default',
					),
				),
			)
		);

		// Affiliate Creatives block.
		register_block_type(
			'affiliatewp/affiliate-creatives',
			array(
				'editor_script'   => 'affiliatewp-blocks-editor',
				'editor_style'    => 'affiliatewp-blocks-editor',
				'render_callback' => array( $this, 'affiliate_creatives_block_render_callback' ),
				'attributes'      => array(
					'preview' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'number'  => array(
						'type'    => 'number',
						'default' => 20,
					),
				),
			)
		);

		// Affiliate Creative block.
		register_block_type(
			'affiliatewp/affiliate-creative',
			array(
				'editor_script'   => 'affiliatewp-blocks-editor',
				'editor_style'    => 'affiliatewp-blocks-editor',
				'render_callback' => array( $this, 'affiliate_creative_block_render_callback' ),
				'attributes'      => array(
					'id' => array(
						'type' => 'integer',
					),
				),
			)
		);

		// Finally, register the dynamic blocks.
		$this->register_dynamic_blocks();
	}

	/**
	 * Fetches default login fields.
	 *
	 * @since 2.8
	 *
	 * @return array list of default login labels.
	 */
	public function login_defaults() {
		return array(
			'legend'     => __( 'Log into your account', 'affiliate-wp' ),
			'label'      => array(
				'username'     => __( 'Username', 'affiliate-wp' ),
				'password'     => __( 'Password', 'affiliate-wp' ),
				'userRemember' => __( 'Remember Me', 'affiliate-wp' ),
			),
			'buttonText' => __( 'Log In', 'affiliate-wp' ),
		);
	}

	/**
	 * Fetches default registration fields.
	 *
	 * @since 2.8
	 *
	 * @return array list of default registration labels.
	 */
	public function registration_defaults() {
		return array(
			'legend' => __( 'Register a new affiliate account', 'affiliate-wp' ),
		);
	}

	/**
	 * Registers blocks that should be added
	 */
	private function register_dynamic_blocks() {

		$login_defaults        = $this->login_defaults();
		$registration_defaults = $this->registration_defaults();


		register_block_type( 'affiliatewp/affiliate-area', array(
			'render_callback' => array( $this, 'render_affiliate_area' ),
		) );

		register_block_type( 'affiliatewp/login', array(
			'attributes'      => array(
				'redirect'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'legend'       => array(
					'type'    => 'string',
					'default' => $login_defaults['legend'],
				),
				'placeholders' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'label'        => array(
					'type'    => 'object',
					'default' => array(
						'username'     => $login_defaults['label']['username'],
						'password'     => $login_defaults['label']['password'],
						'userRemember' => $login_defaults['label']['userRemember'],
					),
				),
				'placeholder'  => array(
					'type'    => 'object',
					'default' => array(
						'username' => '',
						'password' => '',
					),
				),
				'buttonText'   => array(
					'type'    => 'string',
					'default' => $login_defaults['buttonText'],
				),
			),
			'render_callback' => array( $this, 'render_login_form' ),
		) );

		register_block_type( 'affiliatewp/registration', array(
			'attributes'       => array(
				'placeholders' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'legend'       => array(
					'type'    => 'string',
					'default' => $registration_defaults['legend'],
				),
				'redirect'     => array(
					'type' => 'string',
				),
			),
			'provides_context' => array(
				'affiliatewp/placeholders' => 'placeholders',
				'affiliatewp/redirect'     => 'redirect',
			),
			'render_callback'  => array( $this, 'render_registration_form' ),
			'editor_style'     => 'affiliatewp-blocks-editor',
		) );

		$blocks = array(
			'checkbox'        => array(
				'label' => __( 'Option', 'affiliate-wp' ),
			),
			'email'           => array(
				'label' => __( 'Email Address', 'affiliate-wp' ),
			),
			'payment-email'   => array(
				'label'           => __( 'Payment Email', 'affiliate-wp' ),
				'render_callback' => array( $this, 'render_field_email' ),
			),
			'account-email'   => array(
				'label'           => __( 'Account Email', 'affiliate-wp' ),
				'render_callback' => array( $this, 'render_field_email' ),
			),
			'password'        => array(
				'label' => __( 'Password', 'affiliate-wp' ),
			),
			'phone'           => array(
				'label' => __( 'Phone Number', 'affiliate-wp' ),
			),
			'register-button' => array(
				'label' => '',
			),
			'text'            => array(
				'label' => __( 'Text', 'affiliate-wp' ),
			),
			'textarea'        => array(
				'label' => __( 'Message', 'affiliate-wp' ),
			),
			'username'        => array(
				'label' => __( 'Username', 'affiliate-wp' ),
			),
			'name'            => array(
				'label' => __( 'Name', 'affiliate-wp' ),
			),
			'website'         => array(
				'label' => __( 'Website', 'affiliate-wp' ),
			),
		);

		foreach ( $blocks as $block => $args ) {

			if ( isset( $args['render_callback'] ) ) {
				$render_callback = $args['render_callback'];
			} else {
				$render_callback = array( $this, sprintf( 'render_field_%s', str_replace( '-', '_', $block ) ) );
			}

			register_block_type(
				"affiliatewp/field-$block",
				array(
					'attributes'      => array(
						'label' => array( 'type' => 'string', 'default' => isset( $args['label'] ) ? $args['label'] : '' ),
					),
					'parent'          => array( 'affiliatewp/registration' ),
					'uses_context'    => array(
						'affiliatewp/placeholders',
						'affiliatewp/redirect',
					),
					'render_callback' => $render_callback,
				)
			);
		}
	}

	/**
	 * Renders the Affiliate Content block.
	 *
	 * @since 2.8
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused).
	 */
	public function affiliate_content_block_render_callback( $attributes, $content ) {

		if ( ! ( affwp_is_affiliate() && affwp_is_active_affiliate() ) ) {
			return;
		}

		return $content;
	}

	/**
	 * Renders the Non-affiliate Content block.
	 *
	 * @since 2.8
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused).
	 */
	public function non_affiliate_content_block_render_callback( $attributes, $content ) {

		if ( affwp_is_affiliate() && affwp_is_active_affiliate() ) {
			return;
		}

		return $content;
	}

	/**
	 * Renders the Opt-in block
	 *
	 * @since 2.8
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused).
	 */
	public function opt_in_block_render_callback( $attributes, $content ) {
		return ( new Affiliate_WP_Shortcodes() )->opt_in_form( $attributes );
	}

	/**
	 * Renders the Affiliate Referral URL block.
	 *
	 * @since 2.8
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused).
	 */
	public function affiliate_referral_url_block_render_callback( $attributes ) {

		if ( 'default' === $attributes['pretty'] && true === affwp_is_pretty_referral_urls() ) {
			$attributes['pretty'] = 'yes';
		}

		if ( 'default' === $attributes['format'] && 'username' === affwp_get_referral_format() ) {
			$attributes['format'] = 'username';
		}

		$referral_url = ( new Affiliate_WP_Shortcodes() )->referral_url( $attributes );

		return '<p class="affiliate-referral-url">' . $referral_url . '</p>';
	}

	/**
	 * Renders the Affiliate Creatives block.
	 *
	 * @since 2.8
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused).
	 */
	public function affiliate_creatives_block_render_callback( $attributes ) {
		$attributes['preview'] = true === $attributes['preview'] && isset( $attributes['preview'] ) ? 'yes' : 'no';

		return ( new Affiliate_WP_Shortcodes() )->affiliate_creatives( $attributes );
	}

	/**
	 * Renders the Affiliate Creative block.
	 *
	 * @since 2.8
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused).
	 */
	public function affiliate_creative_block_render_callback( $attributes ) {
		return ( new Affiliate_WP_Shortcodes() )->affiliate_creative( $attributes );
	}

	/**
	 * Adds the "AffiliateWP" category to the block editor.
	 *
	 * @since 2.8
	 *
	 * @param array    $categories Array of block categories.
	 * @param \WP_Post $post       Post being loaded.
	 *
	 * @return array Modified categories list.
	 */
	public function add_block_category( $categories, $post ) {
		$categories = array_merge(
			$categories,
			array(
				array(
					'slug'  => 'affiliatewp',
					'title' => __( 'AffiliateWP', 'affiliate-wp' ),
				),
			)
		);

		return $categories;
	}

	public function user() {
		$current_user = wp_get_current_user();
		$user         = array();

		if ( is_user_logged_in() ) {
			$user['user_name']  = $current_user->user_firstname . ' ' . $current_user->user_lastname;
			$user['user_login'] = $current_user->user_login;
			$user['user_email'] = $current_user->user_email;
			$user['url']        = $current_user->user_url;
		}

		return $user;
	}

	/**
	 * Render the Affiliate Area.
	 *
	 * @param array $atts    Block attributes.
	 * @param mixed $content Block content.
	 * @param array $block   WP_Block Object
	 *
	 * @return mixed
	 */
	public function render_affiliate_area( $atts, $content, $block ) {

		ob_start();

		if ( is_user_logged_in() && affwp_is_affiliate() ) {
			affiliate_wp()->templates->get_template_part( 'dashboard' );
		} else {

			if ( ! affiliate_wp()->settings->get( 'allow_affiliate_registration' ) ) {
				affiliate_wp()->templates->get_template_part( 'no', 'access' );
			}

			// Render the inner blocks (registration and login).
			echo do_blocks( $content );
		}

		return ob_get_clean();
	}

	/**
	 * Render the login form
	 *
	 * @param array $atts    Block attributes.
	 * @param mixed $content Block content.
	 * @param array $block   WP_Block Object
	 *
	 * @return mixed Form markup or success message when form submits successfully.
	 */
	public function render_login_form( $atts, $content, $block ) {

		if ( affwp_is_affiliate() ) {
			return;
		}

		$login_defaults = $this->login_defaults();
		$redirect       = isset( $atts['redirect'] ) ? $atts['redirect'] : '';
		$placeholders   = isset( $atts['placeholders'] ) && true === $atts['placeholders'] ? true : false;

		$label_username      = isset( $atts['label']['username'] ) ? $atts['label']['username'] : $login_defaults['label']['username'];
		$label_password      = isset( $atts['label']['password'] ) ? $atts['label']['password'] : $login_defaults['label']['password'];
		$label_user_remember = isset( $atts['label']['userRemember'] ) ? $atts['label']['userRemember'] : $login_defaults['label']['userRemember'];

		$placeholder_username = '';
		$placeholder_password = '';

		if ( $placeholders ) {
			$placeholder_username_text = isset( $atts['placeholder']['username'] ) ? $atts['placeholder']['username'] : $login_defaults['placeholder']['username'];

			$placeholder_username = ' placeholder="' . $placeholder_username_text . '"';

			$placeholder_password_text = isset( $atts['placeholder']['password'] ) ? $atts['placeholder']['password'] : $login_defaults['placeholder']['password'];

			$placeholder_password = ' placeholder="' . $placeholder_password_text . '"';
		}

		$button_text = isset( $atts['buttonText'] ) ? $atts['buttonText'] : $login_defaults['buttonText'];
		$legend      = isset( $atts['legend'] ) ? $atts['legend'] : $login_defaults['legend'];

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
			'affwp-form',
		);
		$classes = $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';

		ob_start();
		affiliate_wp()->login->print_errors();
		?>

		<form id="affwp-login-form" <?php echo $classes; ?> action="" method="post">
			<?php
			/**
			 * Fires at the top of the affiliate login form template
			 *
			 * @since 1.0
			 */
			do_action( 'affwp_affiliate_login_form_top' );
			?>

			<fieldset>
				<legend><?php echo esc_attr( $legend ); ?></legend>

				<?php
				/**
				 * Fires immediately prior to the affiliate login form template fields.
				 *
				 * @since 1.0
				 */
				do_action( 'affwp_login_fields_before' );
				?>

				<p>
					<label for="affwp-login-user-login"><?php echo $label_username; ?></label>
					<input id="affwp-login-user-login" class="required" type="text" name="affwp_user_login"
					       title="<?php echo esc_attr( $label_username ); ?>"<?php echo $placeholder_username; ?> />
				</p>

				<p>
					<label for="affwp-login-user-pass"><?php echo $label_password; ?></label>
					<input id="affwp-login-user-pass" class="password required" type="password"
					       name="affwp_user_pass"<?php echo $placeholder_password; ?> />
				</p>

				<p>
					<label class="affwp-user-remember" for="affwp-user-remember">
						<input id="affwp-user-remember" type="checkbox" name="affwp_user_remember"
						       value="1"/> <?php echo esc_attr( $label_user_remember ); ?>
					</label>
				</p>

				<p>
					<?php if ( $redirect ) : ?>
						<input type="hidden" name="affwp_redirect" value="<?php echo esc_url( $redirect ); ?>"/>
					<?php endif; ?>
					<input type="hidden" name="affwp_login_nonce" value="<?php echo wp_create_nonce( 'affwp-login-nonce' ); ?>"/>
					<input type="hidden" name="affwp_action" value="user_login"/>
					<input type="submit" class="button" value="<?php echo $button_text; ?>"/>
				</p>

				<p class="affwp-lost-password">
					<a
						href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php _e( 'Lost your password?', 'affiliate-wp' ); ?></a>
				</p>

				<?php
				/**
				 * Fires immediately after the affiliate login form template fields.
				 *
				 * @since 1.0
				 */
				do_action( 'affwp_login_fields_after' );
				?>
			</fieldset>

			<?php
			/**
			 * Fires at the bottom of the affiliate login form template (inside the form element).
			 *
			 * @since 1.0
			 */
			do_action( 'affwp_affiliate_login_form_bottom' );
			?>
		</form>

		<?php

		return ob_get_clean();
	}

	/**
	 * Render the form
	 *
	 * @param array $atts    Block attributes.
	 * @param mixed $content Block content.
	 * @param array $block   WP_Block Object
	 *
	 * @return mixed Form markup or success message when form submits successfully.
	 */
	public function render_registration_form( $atts, $content, $block ) {

		if ( ! affiliate_wp()->settings->get( 'allow_affiliate_registration' ) || affwp_is_affiliate() ) {
			return;
		}

		$registration_defaults = $this->registration_defaults();

		$legend = isset( $atts['legend'] ) ? $atts['legend'] : $registration_defaults['legend'];

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
			'affwp-form',
		);
		$classes = $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';

		ob_start();

		affiliate_wp()->register->print_errors();
		?>

		<form id="affwp-register-form" <?php echo $classes; ?> action="" method="post">

			<?php
			/**
			 * Fires at the top of the affiliate registration templates' form (inside the form element).
			 *
			 * @since 1.0
			 */
			do_action( 'affwp_affiliate_register_form_top' );
			?>

			<fieldset>
				<legend><?php echo esc_attr( $legend ); ?></legend>

				<?php
				/**
				 * Fires just before the affiliate registration templates' form fields.
				 *
				 * @since 1.0
				 */
				do_action( 'affwp_register_fields_before' );
				?>

				<?php echo do_blocks( $content ); ?>

				<?php
				/**
				 * Fires inside of the affiliate registration form template (inside the form element, after the submit button).
				 *
				 * @since 1.0
				 */
				do_action( 'affwp_register_fields_after' );
				?>
			</fieldset>

			<?php
			/**
			 * Fires at the bottom of the affiliate registration form template (inside the form element).
			 *
			 * @since 1.0
			 */
			do_action( 'affwp_affiliate_register_form_bottom' );
			?>

		</form>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render the classes.
	 *
	 * @param array $classes Array of classes
	 *
	 * @return string Markup for the class attribute
	 */

	public function render_classes( $classes = array() ) {

		$classes = array_filter( $classes );

		if ( empty( $classes ) ) {
			return;
		}

		return ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
	}

	public function render_field_username( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';
		$label             = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : '';
		$user              = $this->user();
		$label_slug        = 'affwp-user-login';
		$name              = 'affwp_user_login';
		$value             = isset( $user['user_login'] ) ? $user['user_login'] : '';
		$disabled          = is_user_logged_in() ? ' disabled="disabled"' : '';
		$value             = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : $value;
		$required_attr     = isset( $atts['required'] ) && $atts['required'] ? 'required' : '';
		$placeholder       = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';
		$label_classes     = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-name',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>

		<p<?php echo $this->render_classes( $classes ); ?>>
			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>

			<input type="text" id="<?php echo esc_attr( $label_slug ); ?>"
			       name="<?php echo esc_attr( $name ); ?>"<?php echo $this->render_classes( $field_classes ); ?> <?php echo esc_attr( $required_attr ); ?><?php echo $placeholder; ?>
			       title="<?php echo esc_attr( $label ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"<?php echo $disabled; ?>/>
		</p>

		<?php
		return ob_get_clean();
	}

	public function render_field_name( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';
		$label             = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : '';
		$user              = $this->user();
		$label_slug        = 'affwp-user-name';
		$name              = 'affwp_user_name';
		$value             = isset( $user['user_name'] ) ? $user['user_name'] : '';
		$disabled          = is_user_logged_in() ? ' disabled="disabled"' : '';
		$value             = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : $value;
		$required_attr     = isset( $atts['required'] ) && $atts['required'] ? 'required' : '';
		$placeholder       = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';

		$label_classes = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-name',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>

		<p<?php echo $this->render_classes( $classes ); ?>>
			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>

			<input type="text" id="<?php echo esc_attr( $label_slug ); ?>"
			       name="<?php echo esc_attr( $name ); ?>"<?php echo $this->render_classes( $field_classes ); ?> <?php echo esc_attr( $required_attr ); ?><?php echo $placeholder; ?>
			       title="<?php echo esc_attr( $label ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"<?php echo $disabled; ?>/>
		</p>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render the text field
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Markup for the text field.
	 */
	public function render_field_text( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';

		$label = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : '';

		$type = isset( $atts['type'] ) ? $atts['type'] : '';

		$user     = $this->user();
		$disabled = '';

		switch ( $type ) {

			case 'username':
				$label_slug = 'affwp-user-login';
				$name       = 'affwp_user_login';
				$value      = isset( $user['user_login'] ) ? $user['user_login'] : '';
				$disabled   = is_user_logged_in() ? ' disabled="disabled"' : '';
				break;

			case 'name':
				$label_slug = 'affwp-user-name';
				$name       = 'affwp_user_name';
				$value      = isset( $user['user_name'] ) ? $user['user_name'] : '';
				break;

			default:
				$label_slug = 'affwp-' . sanitize_title( $label );
				$name       = esc_attr( str_replace( '-', '_', $label_slug ) ) . '_text';
				$value      = '';
				break;
		}

		$value         = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : $value;
		$required_attr = isset( $atts['required'] ) && $atts['required'] ? 'required' : '';
		$placeholder   = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';

		$label_classes = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-name',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>

		<p<?php echo $this->render_classes( $classes ); ?>>
			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>

			<input type="text" id="<?php echo esc_attr( $label_slug ); ?>"
			       name="<?php echo esc_attr( $name ); ?>"<?php echo $this->render_classes( $field_classes ); ?> <?php echo esc_attr( $required_attr ); ?><?php echo $placeholder; ?>
			       title="<?php echo esc_attr( $label ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"<?php echo $disabled; ?>/>
		</p>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render the phone field
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Markup for the phone field.
	 */
	public function render_field_phone( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';

		$label = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : __( 'Phone Number', 'affiliate-wp' );

		$label_slug = 'affwp-' . sanitize_title( $label );
		$name       = esc_attr( str_replace( '-', '_', $label_slug ) ) . '_phone';
		$value      = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : '';

		$required_attr = isset( $atts['required'] ) && $atts['required'] ? 'required' : '';
		$placeholder   = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';

		$label_classes = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-phone',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>

		<p<?php echo $this->render_classes( $classes ); ?>>
			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>

			<input type="tel" id="<?php echo esc_attr( $label_slug ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"
			       name="<?php echo esc_attr( $name ); ?>"<?php echo $this->render_classes( $field_classes ); ?> <?php echo esc_attr( $required_attr ); ?><?php echo $placeholder; ?>
			       title="<?php echo esc_attr( $label ); ?>"/>
		</p>


		<?php
		return ob_get_clean();
	}

	/**
	 * Render the textarea field
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Markup for the textarea field.
	 */
	public function render_field_textarea( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';

		$label = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : __( 'Message', 'affiliate-wp' );

		$type = isset( $atts['type'] ) ? $atts['type'] : '';

		switch ( $type ) {
			case 'promotionMethod':
				$label_slug = 'affwp-promotion-method';
				$name       = 'affwp_promotion_method';
				break;

			default:
				$label_slug = 'affwp-' . sanitize_title( $label );
				$name       = esc_attr( str_replace( '-', '_', $label_slug ) ) . '_textarea';
				break;
		}

		$required_attr = isset( $atts['required'] ) ? 'required' : '';
		$placeholder   = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';
		$value         = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : '';
		$label_classes = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-textarea',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>

		<p<?php echo $this->render_classes( $classes ); ?>>
			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>
			<textarea name="<?php echo esc_attr( $name ); ?>"
			          id="<?php echo esc_attr( $label_slug ); ?>"<?php echo $this->render_classes( $field_classes ); ?> rows="5" <?php echo esc_attr( $required_attr ); ?><?php echo $placeholder; ?> title="<?php echo $label; ?>"><?php echo esc_attr( $value ) ?></textarea>
		</p>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render the checkbox field
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Markup for the checkbox field.
	 */
	public function render_field_checkbox( $atts, $content, $block ) {

		$label = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : '';

		$label_slug = 'affwp-' . sanitize_title( $label );
		$name       = esc_attr( str_replace( '-', '_', $label_slug ) ) . '_checkbox';
		$value      = '1';
		$current    = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : false;
		$checked    = checked( $value, $current, false );

		$required_attr = isset( $atts['required'] ) && $atts['required'] ? 'required' : '';

		$label_classes = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-checkbox',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>

		<p<?php echo $this->render_classes( $classes ); ?>>
			<input type="checkbox" id="<?php echo esc_attr( $label_slug ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"
			       <?php echo $checked ?>
			       name="<?php echo esc_attr( $name ); ?>"<?php echo $this->render_classes( $field_classes ); ?> <?php echo esc_attr( $required_attr ); ?> />

			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>
		</p>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render the password fields
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Markup for the password fields.
	 */
	public function render_field_password( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';

		$label         = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? $atts['label'] : __( 'Password', 'affiliate-wp' );
		$label_confirm = isset( $atts['labelConfirm'] ) && ! empty( $atts['labelConfirm'] ) ? $atts['labelConfirm'] : __( 'Confirm Password', 'affiliate-wp' );

		$label_slug = 'affwp-' . sanitize_title( $label );
		$name       = esc_attr( str_replace( '-', '_', $label_slug ) ) . '_text';
		$value      = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : '';

		$placeholder         = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';
		$placeholder_confirm = isset( $atts['placeholderConfirm'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholderConfirm'] . '"' : '';

		$label_classes = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-password',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();

		if ( ! is_user_logged_in() ) : ?>
			<p<?php echo $this->render_classes( $classes ); ?>>
				<?php echo $this->render_field_label( $atts, $label, 'affwp-user-pass', $label_classes, $block ); ?>

				<input type="password" id="affwp-user-pass"
				       value="<?php echo esc_attr( $value ) ?>"
				       name="<?php echo esc_attr( $name ) ?>" <?php echo $this->render_classes( $field_classes ); ?>
				       required<?php echo $placeholder; ?> title="<?php echo $label; ?>"/>

			</p>

			<p<?php echo $this->render_classes( $classes ); ?>>
				<?php echo $this->render_field_label( $atts, $label_confirm, 'affwp-user-pass2', $label_classes, $block ); ?>

				<input type="password" id="affwp-user-pass2"
				       name="<?php echo esc_attr( $name ) ?>_confirm"<?php echo $this->render_classes( $field_classes ); ?>
				       required<?php echo $placeholder_confirm; ?> title="<?php echo $label_confirm; ?>"/>

			</p>

		<?php endif;

		return ob_get_clean();
	}

	/**
	 * Render the website field
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Markup for the website field.
	 */
	public function render_field_website( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';

		$label = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : __( 'Website URL', 'affiliate-wp' );

		$value = '';
		$user  = $this->user();

		$type = isset( $atts['type'] ) ? $atts['type'] : '';

		switch ( $type ) {

			case 'websiteUrl':
				$label_slug = 'affwp-user-url';
				$name       = esc_attr( str_replace( '-', '_', $label_slug ) );
				$value      = isset( $user['url'] ) ? $user['url'] : '';
				break;

			default:
				$label_slug = 'affwp-' . sanitize_title( $label );
				$name       = esc_attr( str_replace( '-', '_', $label_slug ) ) . '_website';
				break;
		}

		$value         = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : $value;
		$required_attr = isset( $atts['required'] ) && $atts['required'] ? 'required' : '';
		$placeholder   = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';

		$label_classes = '';

		$field_classes = array(
			'affwp-field',
			'affwp-field-website',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>

		<p<?php echo $this->render_classes( $classes ); ?>>
			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>

			<input type="url" id="<?php echo esc_attr( $label_slug ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"
			       name="<?php echo esc_attr( $name ); ?>"<?php echo $this->render_classes( $field_classes ); ?> <?php echo esc_attr( $required_attr ); ?><?php echo $placeholder; ?>
			       title="<?php echo esc_attr( $label ); ?>"/>
		</p>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render the email field
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Markup for the email field.
	 */
	public function render_field_email( $atts, $content, $block ) {

		$block_context     = isset( $block->context ) ? $block->context : '';
		$show_placeholders = isset( $block_context['affiliatewp/placeholders'] ) ? $block_context['affiliatewp/placeholders'] : '';

		$label_classes = '';
		$label         = isset( $atts['label'] ) && ! empty( $atts['label'] ) ? __( $atts['label'], 'affiliate-wp' ) : __( 'Email Address', 'affiliate-wp' );

		$value    = '';
		$user     = $this->user();
		$disabled = '';
		$type     = isset( $atts['type'] ) ? $atts['type'] : '';

		switch ( $type ) {

			case 'payment':
				$label_slug = 'affwp-payment-email';
				$name       = 'affwp_payment_email';
				break;

			case 'account':
				$label_slug = 'affwp-user-email';
				$name       = 'affwp_user_email';
				$value      = isset( $user['user_email'] ) ? $user['user_email'] : '';
				$disabled   = is_user_logged_in() ? ' disabled="disabled"' : '';
				break;

			default:
				$label_slug = 'affwp-' . sanitize_title( $label );
				$name       = esc_attr( str_replace( '-', '_', $label_slug ) ) . '_email';
				break;
		}


		$required_attr = isset( $atts['required'] ) && $atts['required'] ? 'required' : '';
		$placeholder   = isset( $atts['placeholder'] ) && $show_placeholders ? ' placeholder="' . $atts['placeholder'] . '"' : '';
		$value         = isset( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : $value;

		$field_classes = array(
			'affwp-field',
			'affwp-field-email',
		);

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
		);

		ob_start();
		?>
		<p<?php echo $this->render_classes( $classes ); ?>>
			<?php echo $this->render_field_label( $atts, $label, $label_slug, $label_classes, $block ); ?>

			<input type="email" id="<?php echo esc_attr( $label_slug ); ?>"
			       name="<?php echo esc_attr( $name ); ?>"<?php echo $this->render_classes( $field_classes ); ?> <?php echo esc_attr( $required_attr ); ?><?php echo $placeholder; ?>
			       title="<?php echo esc_attr( $label ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"<?php echo $disabled; ?>/>
		</p>

		<?php
		return ob_get_clean();
	}

	/**
	 * Generate the form field label.
	 *
	 * @param array $atts        Block attributes.
	 * @param mixed $field_label Block content.
	 *
	 * @return mixed Form field label markup.
	 */
	public function render_field_label( $atts, $field_label, $label_for, $label_classes, $block ) {

		$label = isset( $field_label ) ? $field_label : '';

		/**
		 * Filter the required text in the field label.
		 *
		 * @param string $field_label Form field label text.
		 */
		$required_text = (string) apply_filters( 'affwp_registration_form_label_required_text', __( '(required)', 'affiliate-wp' ), $field_label );

		// Checkboxes don't need required text
		if ( 'affiliatewp/field-checkbox' === $block->name ) {
			$required_text = '';
		}

		$required_attr = ( isset( $atts['required'] ) && $atts['required'] ) ? 'required' : '';

		$required_label = ! empty( $required_attr ) || ( 'affiliatewp/field-password' === $block->name ) ? sprintf( ' <span class="required">%s</span>', esc_html( $required_text ) ) : '';

		/*
		 * Format an array of allowed HTML tags and attributes for the $required_label value.
		 *
		 * @link https://codex.wordpress.org/Function_Reference/wp_kses
		 */
		$allowed_html = array(
			'span' => array( 'class' => array() ),
		);

		if ( ! isset( $atts['hidden'] ) ) {
			printf(
				'<label for="%1$s"%2$s>%3$s%4$s</label>',
				esc_attr( $label_for ),
				$label_classes ? ' class="' . esc_attr( $label_classes ) . '"' : '',
				wp_kses_post( $label ),
				wp_kses( $required_label, $allowed_html )
			);
		}
	}

	/**
	 * Render the reCAPTCHA field at the bottom of the affiliate registration form.
	 */
	public function recaptcha() {
		?>
		<?php if ( affwp_is_recaptcha_enabled() ) :
			affwp_enqueue_script( 'affwp-recaptcha' ); ?>

			<div class="g-recaptcha"
			     data-sitekey="<?php echo esc_attr( affiliate_wp()->settings->get( 'recaptcha_site_key' ) ); ?>"></div>

			<p>
				<input type="hidden" name="g-recaptcha-remoteip"
				       value="<?php echo esc_attr( affiliate_wp()->tracking->get_ip() ); ?>"/>
			</p>
		<?php endif;

	}

	/**
	 * Renders the form submit button.
	 *
	 * @param array $atts Block attributes.
	 *
	 * @return mixed Form submit button markup. Empty string if no form could be found.
	 */
	public function render_field_register_button( $atts, $content, $block ) {

		$block_context = isset( $block->context ) ? $block->context : '';
		$redirect      = isset( $block_context['affiliatewp/redirect'] ) ? $block_context['affiliatewp/redirect'] : '';
		$btn_text      = isset( $atts['text'] ) ? $atts['text'] : __( 'Register', 'affiliate-wp' );
		$form          = $this->get_current_form();

		if ( is_wp_error( $form ) ) {
			return '';
		}

		$classes = array(
			isset( $atts['className'] ) ? $atts['className'] : '',
			'button',
		);

		$classes = $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';

		ob_start();
		?>

		<?php $this->recaptcha(); ?>

		<?php
		/**
		 * Fires inside of the affiliate registration form template (inside the form element, prior to the submit button).
		 *
		 * @since 1.0
		 */
		do_action( 'affwp_register_fields_before_submit' );
		?>

		<input type="hidden" name="affwp_honeypot" value=""/>
		<input type="hidden" name="affwp_redirect" value="<?php echo esc_url( $redirect ); ?>"/>
		<input type="hidden" name="affwp_register_nonce" value="<?php echo wp_create_nonce( 'affwp-register-nonce' ); ?>"/>
		<input type="hidden" name="affwp_action" value="affiliate_register"/>
		<?php if ( ! is_wp_error( $form ) ): ?>
			<input type="hidden" name="affwp_post_id" value="<?php echo get_the_ID(); ?>"/>
			<input type="hidden" name="affwp_block_hash" value="<?php echo $form->get_hash(); ?>">
		<?php endif; ?>

		<input <?php echo $classes; ?> type="submit" value="<?php esc_attr_e( $btn_text ); ?>"/>

		<?php
		/**
		 * Fires inside of the affiliate registration form template (inside the form element, after the submit button).
		 *
		 * @since 1.0
		 */
		do_action( 'affwp_register_fields_after' );
		?>

		<?php
		self::$current_form++;
		return ob_get_clean();
	}

	/**
	 * Retrieve the form for the current post.
	 *
	 * @since 2.8
	 *
	 * @return Registration\Form_Container|WP_Error The current registration form container, or a WP_Error object if not
	 *                                              found.
	 */
	protected function get_current_form() {
		$submission_forms = $this->get_submission_forms( get_the_ID() );

		if ( isset( $submission_forms[ self::$current_form ] ) ) {
			$submission_form = $submission_forms[ self::$current_form ];
		} else {
			$submission_form = new WP_Error( 'submission_form_not_found', 'The provided submission form could not be found', array(
				'current_form'     => self::$current_form,
				'post_id'          => get_the_ID(),
				'submission_forms' => $submission_forms,
			) );
		}

		return $submission_form;
	}

	/**
	 * Retrieves the block names for submission form types.
	 *
	 * @since 2.8
	 *
	 * @return string[] List of block names considered submission form types.
	 */
	protected function get_submission_form_types() {
		return array( 'login', 'registration', 'affiliate-area' );
	}

	/**
	 * Retrieve the submission form fields, given an affiliateWP registration block.
	 *
	 * @since 2.8
	 *
	 * @param \WP_Block|array $form_block WP_Block object or parsed block array.
	 * @return array List of registration form fields for the specified block.
	 */
	public function get_submission_form_fields( $form_block ) {

		if ( $form_block instanceof \WP_Block ) {
			$form_block = $form_block->parsed_block;
		}

		$result = array();

		$form_types = $this->get_submission_form_types();

		foreach ( $form_types as $type ) {

			if ( "affiliatewp/{$type}" === $form_block['blockName'] ) {
				foreach ( $form_block['innerBlocks'] as $field ) {

					$inner_block   = WP_Block_Type_Registry::get_instance()->get_registered( $field['blockName'] );
					$default_label = '';

					if ( isset( $inner_block->attributes['label'] ) && isset( $inner_block->attributes['label']['default'] ) ) {
						$default_label = $inner_block->attributes['label']['default'];
					}

					$default_attrs = array(
						'label'    => $default_label,
						'type'     => '',
						'required' => false,
					);

					$attrs = wp_parse_args( $field['attrs'], $default_attrs );

					// Ignore submit button.
					if ( $field['blockName'] !== 'affiliatewp/field-register-button' ) {
						$result[] = new Registration\Form_Field_Container( array(
							'field_type'  => $field['blockName'],
							'label'       => $attrs['label'],
							'legacy_type' => $attrs['type'],
							'required'    => $attrs['required'],
						) );
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Retrieve the submission form.
	 *
	 * @since 2.8
	 *
	 * @param \WP_Block|array $block An instance of WP_Block, or the parsed block.
	 * @return Registration\Form_Container|\WP_Error The form object, or WP_Error if the block is invalid.
	 */
	public function get_block_submission_form( $block ) {

		if ( $block instanceof WP_Block ) {
			$block = $block->parsed_block;
		}

		$form_types = $this->get_submission_form_types();

		foreach ( $form_types as $type ) {
			if ( is_array( $block ) && $block['blockName'] === "affiliatewp/{$type}" ) {
				return new Registration\Form_Container( array( 'fields' => $this->get_submission_form_fields( $block ) ) );
			}
		}

		return new \WP_Error( 'invalid_block', 'An invalid block was provided. The block must be an affiliatewp/registration or affiliatewp/affiliate-area block.', array(
			'block' => $block,
		) );
	}

	/**
	 * Retrieve all submission forms for the provided post ID.
	 *
	 * @since 2.8
	 *
	 * @param int $post_id The post ID,
	 * @return array List of submission form objects for the provided post.
	 */
	public function get_submission_forms( $post_id ) {
		$result = array();
		$post   = get_post( $post_id );

		$form_types = $this->get_submission_form_types();

		if ( has_block( "affiliatewp/affiliate-area", $post ) ) {
			// Force blocks to possibly be registered early.
			affiliate_wp()->editor->blocks_init();

			$blocks = parse_blocks( $post->post_content );

			foreach ( $blocks as $block ) {
				if ( isset( $block['blockName'] ) && 'affiliatewp/affiliate-area' !== $block['blockName'] ) {
					continue;
				}

				if ( isset( $block['innerBlocks'] ) ) {
					foreach ( $block['innerBlocks'] as $inner_block ) {
						$result[] = new Registration\Form_Container( array(
							'fields' => $this->get_submission_form_fields( $inner_block ),
						) );
					}
				}
			}
		}

		foreach ( $form_types as $type ) {
			if ( 'affiliate-area' === $type ) {
				continue;
			}

			// If this post has the registration block, save the fields for this form to meta.
			if ( has_block( "affiliatewp/{$type}", $post ) ) {
				// Force blocks to possibly be registered early.
				affiliate_wp()->editor->blocks_init();

				$blocks = parse_blocks( $post->post_content );

				foreach ( $blocks as $block ) {
					if ( "affiliatewp/{$type}" === $block['blockName'] ) {
						$result[] = new Registration\Form_Container( array(
							'fields' => $this->get_submission_form_fields( $block ),
						) );
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Saves the submission form hashes to the database.
	 *
	 * @since 2.8
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_submission_form_hashes( $post_id ) {
		$forms = $this->get_submission_forms( $post_id );
		$meta  = array();

		foreach ( $forms as $form ) {
			if ( $form instanceof Registration\Form_Container ) {
				$meta[] = $form->get_hash();
			}
		}

		update_post_meta( $post_id, 'affwp_affiliate_submission_forms', $meta );
	}

	/**
	 * Retrieves a single affiliate submission form given a post ID and form hash.
	 *
	 * @since 2.8
	 *
	 * @param int    $post_id The post ID containing the form.
	 * @param string $hash    Submission form hash.
	 * @return Registration\Form_Container|\WP_Error
	 */
	public function get_submission_form( $post_id, $hash ) {
		$saved_form_hashes = get_post_meta( $post_id, 'affwp_affiliate_submission_forms', true );

		$forms = $this->get_submission_forms( $post_id );

		foreach ( $forms as $form ) {
			/**
			 * @var $form Registration\Form_Container
			 */
			if ( in_array( $form->get_hash(), $saved_form_hashes ) ) {
				return $form;
			}
		}

		return new \WP_Error( 'submission_form_not_found', 'A form for the provided hash could not be found', array(
			'post_id' => $post_id,
			'hash'    => $hash,
		) );
	}

}
