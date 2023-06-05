<?php
/**
 * Admin: Notices API
 *
 * @package     AffiliateWP
 * @subpackage  Admin
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

use Sandhills\Utils\Persistent_Dismissible;

/**
 * AffiliateWP Admin Notices class
 *
 * @since 1.0
 */
class Affiliate_WP_Admin_Notices {

	/**
	 * Current AffiliateWP version.
	 *
	 * @since 2.0
	 * @var string
	 */
	private $version;

	/**
	 * Whether to display notices.
	 *
	 * Used primarily for unit testing expected output.
	 *
	 * @since 2.1
	 * @var bool Default true.
	 */
	private $display_notices = true;

	/**
	 * Notices registry.
	 *
	 * @since 2.4
	 * @var   \AffWP\Admin\Notices_Registry
	 */
	private static $registry;

	/**
	 * Sets up the notices API.
	 *
	 * Core notices are registered against the affwp_notices_registry_init hook, which
	 * grants local access to a canonical instance of the Notices_Registry class.
	 * The init() method of the Notices_Registry class is likewise hooked to admin_init,
	 * thereby effectively registering admin notices on admin_init.
	 *
	 * Since all core notices are registered on the one hook, a similar system can also
	 * be employed by any third-parties (including add-ons) wanting to hook into the core
	 * admin notices API for various purposes.
	 *
	 * Example:
	 *
	 *     add_action( 'affwp_notices_registry_init', function( $registry ) {
	 *         $registry->add_notice( 'example-notice', array(
	 *             'class'   => 'error',
	 *             'message' => 'There was an error with {component}.',
	 *         ) );
	 *     }, 11 );
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$registry = new \AffWP\Admin\Notices_Registry;

		add_action( 'affwp_notices_registry_init', array( $this,     'register_notices' ) );
		add_action( 'admin_init',                  array( $registry, 'init'             ) );
		add_action( 'admin_notices',               array( $this,     'show_notices'     ) );
		add_action( 'wp_ajax_affwp_dismiss_promo', array( $this,     'dismiss_promo'    ) );
		add_action( 'affwp_dismiss_notices',       array( $this,     'dismiss_notices'  ) );
	}

	/**
	 * Sets the registry for use by the class.
	 *
	 * @since 2.4
	 *
	 * @param \AffWP\Admin\Notices_Registry $registry Registry instance.
	 */
	private static function set_registry( $registry ) {
		self::$registry = $registry;
	}

	/**
	 * Registers the admin notices.
	 *
	 * @since 2.4
	 *
	 * @param \AffWP\Admin\Notices_Registry $registry Registry instance.
	 */
	public function register_notices( $registry ) {
		// Set up local access of the single registry instance.
		self::set_registry( $registry );

		$this->affiliate_notices();
		$this->consumer_notices();
		$this->creative_notices();
		$this->customer_notices();
		$this->payout_notices();
		$this->referral_notices();

		$this->integration_notices();
		$this->license_notices();
		$this->settings_notices();
		$this->environment_notices();
		$this->upgrade_notices();
		$this->development_notices();
	}

	/**
	 * Outputs general admin notices.
	 *
	 * @since 1.0
	 * @since 1.8.3 Notices are hidden for users lacking the 'manage_affiliates' capability
	 *
	 * @return string|void Output if `$display_notices` is false, otherwise void.
	 */
	public function show_notices() {
		$affwp_message = $notice_id = $output = '';

		// Handle displaying registered notices triggered via the 'affwp_notice' query arg in the URL.
		if ( ! empty( $_REQUEST['affwp_notice'] ) ) {
			$notice_id = urldecode( sanitize_text_field( $_REQUEST['affwp_notice'] ) );

			$output .= self::show_notice( $notice_id, false );
		}

		$promo_notice_id = isset( $_GET['test-promo'] ) ? 'promo' : 'affwp-license-upgrade';

		$promo_dismissed = Persistent_Dismissible::get( array( 'id' => $promo_notice_id ) );

		if ( ! $promo_dismissed && affwp_is_admin_page() ) {
			$this->show_promo_notices();
		}

		// Handle displaying the settings-updated notice.
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] && isset( $_GET['page'] ) && $_GET['page'] == 'affiliate-wp-settings' ) {
			$output .= self::show_notice( 'settings-updated', false );
		}

		// PHP minimum notice.
		if ( true === version_compare( phpversion(), '7.0', '<' )
			&& affwp_is_admin_page()
			&& false === get_transient( 'affwp_requirements_php_70_notice' )
		) {
			$output .= self::show_notice( 'requirements_php_70', false );
		}

		if ( affwp_is_admin_page() && false !== strpos( AFFILIATEWP_VERSION, '-' ) ) {
			$output .= self::show_notice( 'development_version', false );
		}

		$integrations = affiliate_wp()->integrations->get_enabled_integrations();

		if ( empty( $integrations ) && ! get_user_meta( get_current_user_id(), '_affwp_no_integrations_dismissed', true ) ) {
			$output .= self::show_notice( 'no_integrations', false );
		}

		if ( true === version_compare( AFFILIATEWP_VERSION, '2.0', '<' ) || false === affwp_has_upgrade_completed( 'upgrade_v20_recount_unpaid_earnings' ) ) {
			$output .= self::show_notice( 'upgrade_v20_recount_unpaid_earnings', false );
		}

		if ( false === affwp_has_upgrade_completed( 'upgrade_v22_create_customer_records' ) ) {
			$output .= self::show_notice( 'upgrade_v22_create_customer_records', false );
		}

		if ( false === affwp_has_upgrade_completed( 'upgrade_v245_create_customer_affiliate_relationship_records' ) ) {
			$output .= self::show_notice( 'upgrade_v245_create_customer_affiliate_relationship_records', false );
		}

		if ( false === affwp_has_upgrade_completed( 'upgrade_v26_create_dynamic_coupons' ) ) {
			$output .= self::show_notice( 'upgrade_v26_create_dynamic_coupons', false );
		}

		if ( false === affwp_has_upgrade_completed( 'upgrade_v261_utf8mb4_compat' ) ) {
			$output .= self::show_notice( 'upgrade_v261_utf8mb4_compat', false );
		}

		if ( false === affwp_has_upgrade_completed( 'upgrade_v27_calculate_campaigns' ) ) {
			$output .= self::show_notice( 'upgrade_v27_calculate_campaigns', false );
		}

		if ( false === affwp_has_upgrade_completed( 'upgrade_v274_calculate_campaigns' ) ) {
			$output .= self::show_notice( 'upgrade_v274_calculate_campaigns', false );
		}

		if ( affwp_get_current_migrated_user_meta_fields() !== affwp_get_pending_migrated_user_meta_fields() ) {
			$output .= self::show_notice( 'migrate_affiliate_user_meta', false );
		}

		if ( false === affwp_has_upgrade_completed( 'upgrade_v281_convert_failed_referrals' ) ) {
			$output .= self::show_notice( 'upgrade_v281_convert_failed_referrals', false );
		}

		// Payouts Service.
		if ( in_array( affwp_get_current_screen(), array( 'affiliate-wp-referrals', 'affiliate-wp-payouts' ), true ) ) {
			$vendor_id  = affiliate_wp()->settings->get( 'payouts_service_vendor_id', 0 );
			$access_key = affiliate_wp()->settings->get( 'payouts_service_access_key', '' );

			if ( ! ( $vendor_id && $access_key ) && false === get_transient( 'affwp_payouts_service_notice' ) ) {
				$output .= self::show_notice( 'payouts_service', false );
			}
		}

		// Integrations.
		$active_integrations = affiliate_wp()->integrations->query( array( 'fields' => 'ids' ) );

		foreach ( $active_integrations as $id ) {

			$integration = affiliate_wp()->integrations->get( $id );
			$screen      = affwp_get_current_screen();

			if ( 'affiliate-wp-reports' === $screen && ! is_wp_error( $integration ) && true === $integration->needs_synced() ) {
				$output .= self::show_notice( $integration->context . '_needs_synced', false );
			}
		}

		$legacy_plugin_active = class_exists( 'AffiliateWP_Discontinued_Integrations' );

		foreach ( affiliate_wp()->integrations->get_discontinued_integrations() as $integration => $label ) {
			if ( affwp_is_admin_page()
				&& array_key_exists( $integration, $integrations )
				&& false === $legacy_plugin_active
			) {
				$output .= self::show_notice( "{$integration}_discontinued_integration_enabled", false );
			}
		}

		// Don't display other types of notices for users who can't manage affiliates.
		if ( current_user_can( 'manage_affiliates' ) ) {
			// Compat for displaying notices defined via the 'affwp_message' query arg.
			if ( ! empty( $_REQUEST['affwp_message'] ) ) {
				$affwp_message = urldecode( sanitize_text_field( $_REQUEST['affwp_message'] ) );

				if ( ! empty( $_REQUEST['affwp_success'] ) && 'no' === $_REQUEST['affwp_success'] ) {
					$class = 'error';
				} else {
					$class = 'updated';
				}

				$output .= self::prepare_message_for_output( $affwp_message, $class );
			}
		}

		if ( true === $this->display_notices ) {
			echo $output;
		} else {
			return $output;
		}
	}

	/**
	 * Shows promo notices.
	 *
	 * @since 2.7
	 */
	private function show_promo_notices() {
		$source = affwp_get_current_screen();

		if ( ! $source ) {
			return;
		}

		$license_data = affiliate_wp()->settings->get( 'license_status', array() );
		$license_id   = isset( $license_data->price_id ) ? intval( $license_data->price_id ) : false;

		if ( isset( $_GET['license_id'] ) ) {
			$license_id = absint( $_GET['license_id'] );
		}

		if ( false === $license_id || $license_id > 1 || empty( $license_data ) ) {
			return;
		}

		if ( 0 === $license_id ) {
			$license_type = 'Personal';
		} elseif ( 1 === $license_id ) {
			$license_type = 'Plus';
		} else {
			$license_type = '';
		}

		if ( empty( $license_type ) ) {
			return;
		}

		$utm_args    = array(
			'utm_source'   => $source,
			'utm_medium'   => sprintf( 'upgrade-from-%s', strtolower( $license_type ) ),
			'utm_campaign' => 'admin',
		);

		$upgrade_url = add_query_arg( $utm_args, 'https://affiliatewp.com/professional' );

		?>
		<div
			id="affwp-notice-license-upgrade"
			class="affwp-admin-notice-top-of-page affwp-promo-notice"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'affwp-dismiss-notice-affwp-license-upgrade' ) ); ?>"
			data-id="affwp-license-upgrade"
			data-lifespan="<?php echo esc_attr( DAY_IN_SECONDS * 90 ); ?>"
		>
			<?php
			/* translators: %1$s License type. %2$s Opening anchor tag, do not translate. %3$s Closing anchor tag, do not translate. */
			$message = __(
				'You are using AffiliateWP with a %1$s license. %2$sUpgrade to Professional%3$s and grow your affiliate program.',
				'affiliate-wp'
			);

			echo wp_kses(
				sprintf(
					$message,
					$license_type,
					'<a href="' . esc_url( $upgrade_url ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				),
				array(
					'a' => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
				)
			);
			?>
			<button class="button-link affwp-notice-dismiss">
				&times;
			</button>
		</div>
		<?php
	}

	/**
	 * Registers affiliate notices.
	 *
	 * @since 2.4
	 */
	private function affiliate_notices() {
		$this->add_notice( 'affiliate_added', array(
			'message' => function() {
				require_once AFFILIATEWP_PLUGIN_DIR . 'includes/admin/tools/class-migrate-users.php';

				$total_affiliates = (int) Affiliate_WP_Migrate_Users::get_items_total( 'affwp_migrate_users_current_count' );

				/*
				 * If $total_affiliates is 0 and we know 'affiliate_added' has been fired,
				 * it was a manual addition, and therefore 1 affiliate was added.
				 */
				if ( 0 === $total_affiliates ) {
					$total_affiliates = 1;
				}

				$message = sprintf( _n(
					/* translators: Singular number of affiliates added */
					'%d affiliate was added successfully.',
					/* translators: Plural number of affiliates added */
					'%d affiliates were added successfully',
					$total_affiliates,
					'affiliate-wp'
				), number_format_i18n( $total_affiliates ) );

				Affiliate_WP_Migrate_Users::clear_items_total( 'affwp_migrate_users_current_count' );

				return $message;
			},
		) );

		$this->add_notice( 'affiliate_added_failed', array(
			'class'   => 'error',
			'message' => __( 'Affiliate wasn&#8217;t added, please try again.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliate_updated', array(
			'message' => function() {
				$message =  __( 'Affiliate updated successfully', 'affiliate-wp' );
				/* translators: URL to the affiliates screen */
				$message .= '<p>'. sprintf( __( '<a href="%s">Back to Affiliates</a>.', 'affiliate-wp' ), esc_url( affwp_admin_url( 'affiliates' ) ) ) .'</p>';

				return $message;
			},
		) );

		$this->add_notice( 'affiliate_update_failed', array(
			'class'   => 'error',
			'message' => __( 'Affiliate update failed, please try again', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliate_deleted', array(
			'message' => __( 'Affiliate account(s) deleted successfully', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliate_delete_failed', array(
			'class'   => 'error',
			'message' => __( 'Affiliate deletion failed, please try again', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliate_activated', array(
			'message' => __( 'Affiliate account activated', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliate_deactivated', array(
			'message' => __( 'Affiliate account deactivated', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliate_accepted', array(
			'message' => __( 'Affiliate request was accepted', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliate_rejected', array(
			'message' => __( 'Affiliate request was rejected', 'affiliate-wp' ),
		) );

		$this->add_notice( 'dynamic_coupon_created', array(
			'message' => __( 'Dynamic coupon successfully created.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'dynamic_coupon_create_failed', array(
			'class'   => 'error',
			'message' => __( 'Dynamic coupon creation failed, please try again.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'dynamic_coupon_deleted', array(
			'message' => __( 'Dynamic coupon successfully deleted.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'dynamic_coupon_delete_failed', array(
			'class'   => 'error',
			'message' => __( 'Dynamic coupon deletion failed, please try again.', 'affiliate-wp' ),
		) );
	}

	/**
	 * Registers API consumer admin notices.
	 *
	 * @since 2.4
	 */
	private function consumer_notices() {
		$this->add_notice( 'api_key_generated', array(
			'message' => __( 'The API keys were successfully generated.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'api_key_failed', array(
			'class'   => 'error',
			'message' => __( 'The API keys could not be generated.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'api_key_regenerated', array(
			'message' => __( 'The API keys were successfully regenerated.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'api_key_revoked', array(
			'message' => __( 'The API keys were successfully revoked.', 'affiliate-wp' ),
		) );
	}

	/**
	 * Registers creative admin notices.
	 *
	 * @since 2.4
	 */
	private function creative_notices() {
		$this->add_notice( 'creative_updated', array(
			'message' => function() {
				$message =  __( 'Creative updated successfully', 'affiliate-wp' );
				/* translators: URL to the Creatives screen */
				$message .= '<p>'. sprintf( __( '<a href="%s">Back to Creatives</a>', 'affiliate-wp' ), esc_url( affwp_admin_url( 'creatives' ) ) ) .'</p>';

				return $message;
			},
		) );

		$this->add_notice( 'creative_added', array(
			'message' => __( 'Creative added successfully', 'affiliate-wp' ),
		) );

		$this->add_notice( 'creative_deleted', arraY(
			'message' => __( 'Creative deleted successfully', 'affiliate-wp' ),
		) );

		$this->add_notice( 'creative_activated', array(
			'message' => __( 'Creative activated', 'affiliate-wp' ),
		) );

		$this->add_notice( 'creative_deactivated', array(
			'message' => __( 'Creative deactivated', 'affiliate-wp' ),
		) );
	}

	/**
	 * Registers customer admin notices.
	 *
	 * @since 2.5.7
	 */
	public function customer_notices() {
		$this->add_notice( 'customer_added', array(
			'message' => __( 'Customer added successfully', 'affiliate-wp' ),
		) );

		$this->add_notice( 'customer_added_failed', array(
			'class'   => 'error',
			'message' => __( 'Customer wasn&#8217;t added, please try again.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'customer_updated', array(
			'message' => __( 'Customer updated successfully', 'affiliate-wp' ),
		) );

		$this->add_notice( 'customer_update_failed', array(
			'class'   => 'error',
			'message' => __( 'Customer update failed, please try again', 'affiliate-wp' ),
		) );
	}

	/**
	 * Registers payout admin notices.
	 *
	 * @since 2.4
	 */
	public function payout_notices() {
		$this->add_notice( 'payout_created', array(
			'message' => __( 'A payout has been created.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'payout_deleted', array(
			'message' => __( 'Payout deleted successfully.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'payout_delete_failed', array(
			'message' => __( 'Payout deletion failed, please try again.', 'affiliate-wp' ),
		) );

		// Payouts service notices.
		$this->add_notice( 'payouts_service_site_connected', array(
			/* translators: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant */
			'message' => sprintf( __( 'Website connected to the %s.', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME ),
		) );

		$this->add_notice( 'payouts_service_site_disconnected', array(
			/* translators: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant */
			'message' => sprintf( __( 'Website disconnected from the %s.', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME ),
		) );

		$this->add_notice( 'payouts_service_site_reconnected', array(
			/* translators: Payouts Service name retrieved from the PAYOUTS_SERVICE_NAME constant */
			'message' => sprintf( __( 'Website reconnected to the %s.', 'affiliate-wp' ), PAYOUTS_SERVICE_NAME ),
		) );

		// Payouts Service.
		$message = '<p><strong>' . __( 'Effortlessly pay your affiliates', 'affiliate-wp' ) . '</strong></p>';

		$message .= sprintf(
			__( 'With the Payouts Service provided by AffiliateWP, you can easily pay affiliates in 31 countries using any debit or credit card. Learn more at <a href="%s" target="_blank">payouts.sandhillsplugins.com</a>.', 'affiliate-wp' ),
			PAYOUTS_SERVICE_URL
		);

		$added = $this->add_notice( 'payouts_service', array(
			'class'         => 'updated',
			'message'       => $message,
			'dismissible'   => true,
			'dismiss_label' => _x( 'Maybe later', 'payouts service', 'affiliate-wp' ),
		) );
	}

	/**
	 * Registers referral admin notices.
	 *
	 * @since 2.4
	 */
	private function referral_notices() {
		$this->add_notice( 'referral_added', array(
			'message' => __( 'Referral added successfully.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'referral_add_failed', array(
			'class'   => 'error',
			'message' => __( 'Referral wasn&#8217;t created, please try again.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'referral_add_invalid_affiliate', array(
			'class'   => 'error',
			'message' => __( 'Referral not created because the affiliate is invalid, please try again.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'referral_invalid_amount', array(
            'class'   => 'error',
            'message' => __( 'Referral amount cannot be negative, please try again.', 'affiliate-wp' ),
        ) );

		$this->add_notice( 'referral_updated', array(
			'message' => __( 'Referral updated successfully.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'referral_update_failed', array(
			'message' => __( 'Referral update failed, please try again.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'referral_deleted', array(
			'message' => __( 'Referral deleted successfully.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'referral_delete_failed', array(
			'class'   => 'error',
			'message' => __( 'Referral deletion failed, please try again.', 'affiliate-wp' ),
		) );
	}

	/**
	 * Displays upgrade notices.
	 *
	 * @since 2.0
	 */
	public function upgrade_notices() {

		$this->add_notice( 'upgrade_v20_recount_unpaid_earnings',
			array(
				'class'   => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database needs to be upgraded following the AffiliateWP v2.0 update', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'recount-affiliate-stats-upgrade_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="recount-affiliate-stats-upgrade" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database', 'affiliate-wp' ), 'secondary', 'v20-recount-unpaid-earnings', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			) );

		$this->add_notice( 'upgrade_v22_create_customer_records',
			array(
				'class'   => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database needs to be upgraded following the AffiliateWP v2.2 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'create-customers-upgrade_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="create-customers-upgrade" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database', 'affiliate-wp' ), 'secondary', 'v22-create-customers', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);

		$this->add_notice( 'upgrade_v245_create_customer_affiliate_relationship_records',
			array(
				'class'   => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database needs to be upgraded following the AffiliateWP v2.4.5 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'create-customer-affiliate-relationship-upgrade_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="create-customer-affiliate-relationship-upgrade" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database', 'affiliate-wp' ), 'secondary', 'v245-create-customer-affiliate-relationship', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);

		$this->add_notice( 'upgrade_v26_create_dynamic_coupons',
			array(
				'class'   => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database needs to be upgraded following the AffiliateWP v2.6 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'create-dynamic-coupons-upgrade_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="create-dynamic-coupons-upgrade" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database', 'affiliate-wp' ), 'secondary', 'v26-create-dynamic-coupons', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);

		$this->add_notice( 'upgrade_v261_utf8mb4_compat',
			array(
				'class' => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database tables need to be upgraded following the AffiliateWP v2.6.1 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'upgrade-db-utf8mb4_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="upgrade-db-utf8mb4" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database Tables', 'affiliate-wp' ), 'secondary', 'v261-upgrade-db-utf8mb4', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);

		$this->add_notice( 'upgrade_v27_calculate_campaigns',
			array(
				'class' => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database tables need to be upgraded following the AffiliateWP v2.7 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'recalculate-campaigns_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="recalculate-campaigns" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database Tables', 'affiliate-wp' ), 'secondary', 'recalculate-campaigns', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);

		$this->add_notice( 'upgrade_v274_calculate_campaigns',
			array(
				'class' => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database tables need to be upgraded following the AffiliateWP v2.7.4 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'recalculate-campaigns_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="recalculate-campaigns" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database Tables', 'affiliate-wp' ), 'secondary', 'recalculate-campaigns', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);

		$this->add_notice( 'migrate_affiliate_user_meta',
			array(
				'class' => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database tables need to be upgraded following the AffiliateWP v2.8 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'migrate-user-meta_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="migrate-user-meta" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database Tables', 'affiliate-wp' ), 'secondary', 'migrate-user-meta_step_nonce', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);

		$this->add_notice( 'upgrade_v281_convert_failed_referrals',
			array(
				'class' => 'notice notice-info is-dismissible',
				'message' => function() {
					$notice = __( 'Your database tables need to be upgraded following the AffiliateWP v2.8.1 update. Depending on the size of your database, this upgrade could take some time.', 'affiliate-wp' );
					$nonce  = wp_create_nonce( 'upgrade-convert-failed-referrals_step_nonce' );

					ob_start();
					// Enqueue admin JS for the batch processor.
					affwp_enqueue_admin_js();
					?>
					<p><?php echo $notice; ?></p>
					<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="upgrade-convert-failed-referrals" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<?php submit_button( __( 'Upgrade Database Tables', 'affiliate-wp' ), 'secondary', 'upgrade-convert-failed-referrals', false ); ?>
						</p>
					</form>
					<?php
					return ob_get_clean();
				},
			)
		);


	}

	/**
	 * Registers development-related notices.
	 *
	 * @since 2.8
	 */
	public function development_notices() {
		$this->add_notice( 'development_version', array(
			'class' => 'error',
			'message' => function() {
				$message  = sprintf( __( '<strong>Important:</strong> You have installed or updated to AffiliateWP <code>v%s</code>, a pre-release development version.', 'affiliate-wp' ),
					AFFILIATEWP_VERSION
				) . '<br />';
				$message .= sprintf( __( 'Development versions can sometimes be unstable and should <em>never</em> be tested on a live site. If you feel you may have installed this version of AffiliateWP in error, you can get a copy of the latest stable version from your <a href="%s" target="_blank">account page</a>.', 'affiliate-wp' ),
					'https://affiliatewp.com/account/?utm_campaign=admin&utm_source=development_version&utm_medium=error'
				);

				return $message;
			},
		) );
	}

	/**
	 * Display admin notices related to integrations.
	 *
	 * @since 2.1
	 * @since 2.4 Refactored to leverage the notices registry
	 *
	 * @return string|void Output if `$display_notices` is false, otherwise void.
	 */
	public function integration_notices() {
		$this->add_notice( 'no_integrations', array(
			'class'   => 'error',
			'message' => function() {
				/* translators: URL to the Integrations settings screen */
				$message = sprintf( __( 'There are currently no AffiliateWP <a href="%s">integrations</a> enabled. If you are using AffiliateWP without any integrations, you may disregard this message.', 'affiliate-wp' ), affwp_admin_url( 'settings', array( 'tab' => 'integrations' ) ) ) . '</p>';
				$message .= '<p><a href="' . wp_nonce_url( add_query_arg( array(
						'affwp_action' => 'dismiss_notices',
						'affwp_notice' => 'no_integrations',
					) ), 'affwp_dismiss_notice', 'affwp_dismiss_notice_nonce' ) . '">' . _x( 'Dismiss Notice', 'Integrations', 'affiliate-wp' ) . '</a>';

				return $message;
			},
		) );

		$integrations = affiliate_wp()->integrations->query( array( 'supports' => 'sales_reporting' ) );

		// Loop through active integrations and post any notices for that integration.
		foreach ( $integrations as $context => $integration ) {
			$this->add_notice( $context . '_needs_synced',
				array(
					'class'   => 'notice notice-warning',
					'message' => function() use ( $context, $integration ) {
						/* translators: Integration name */
						$notice = sprintf( __( "AffiliateWP needs to import %s sales data. This will ensure AffiliateWP sales reports are accurate." ), $integration['name'] );
						$nonce  = wp_create_nonce( 'sync-integration-sales-data_step_nonce' );

						ob_start();
						// Enqueue admin JS for the batch processor.
						affwp_enqueue_admin_js();
						?>
						<p><?php echo $notice; ?></p>
						<form method="post" class="affwp-batch-form" data-dismiss-when-complete="true" data-batch_id="sync-integration-sales-data" data-nonce="<?php echo esc_attr( $nonce ); ?>">
							<input type="hidden" name="context" value="<?php echo esc_attr( $context ); ?>">
							<p>
							<?php submit_button( __( 'Sync', 'affiliate-wp' ), 'secondary', 'sync-integration-sales-data-' . $context, false ); ?>
							</p>
						</form>
						<?php
						return ob_get_clean();
					},
				)
			);
		}

		foreach ( affiliate_wp()->integrations->get_discontinued_integrations() as $slug => $label ) {
			$this->add_notice( "{$slug}_discontinued_integration_enabled", array(
				'class'   => 'notice notice-info',
				'message' => function() use ( $label ) {
					$message  = '<p>';
					/* translators: 1: Integration name, 2: URL to the discontinued integrations guide */
					$message .= sprintf( __( 'AffiliateWP is ending official support for the <strong>%1$s</strong> integration beginning September 2021. See <a href="%2$s" target="_blank">this guide</a> for more information.', 'affiliate-wp' ),
						$label,
						esc_url( 'https://docs.affiliatewp.com/article/2407-discontinued-integrations' )
					);
					$message .= '</p>';

					return $message;
				},
			) );
		}
	}

	/**
	 * Display admin notices related to licenses.
	 *
	 * @since 2.1
	 * @since 2.4 Refactored to leverage the notices registry
	 *
	 * @return string|void Output if `$display_notices` is false, otherwise void.
	 */
	public function license_notices() {
		$license = affiliate_wp()->settings->check_license();

		if ( is_object( $license ) && ! is_wp_error( $license ) ) {
			$this->add_notice( 'license-expired', array(
				'class'   => 'error',
				'message' => function() use ( $license ) {
					$license_key = \Affiliate_WP_Settings::get_license_key();

					return sprintf(
						/* translators: 1: Date the license expired, 2: URL to renew the license key */
						__( 'Your license key expired on %1$s. Please <a href="%2$s" target="_blank">renew your license key</a>.', 'affiliate-wp' ),
						affwp_date_i18n( strtotime( $license->expires, current_time( 'timestamp' ) ) ),
						'https://affiliatewp.com/checkout/?edd_license_key=' . $license_key . '&utm_campaign=admin&utm_source=licenses&utm_medium=expired'
					);
				},
			) );
		}
		$this->add_notice( 'license-revoked', array(
			'class'   => 'error',
			'message' => sprintf(
				/* translators: URL to contact support */
				__( 'Your license key has been disabled. Please <a href="%s" target="_blank">contact support</a> for more information.', 'affiliate-wp' ),
				'https://affiliatewp.com/support?utm_campaign=admin&utm_source=licenses&utm_medium=revoked'
			),
		) );

		$this->add_notice( 'license-missing', array(
			'class'   => 'error',
			'message' => sprintf(
				/* translators: URL to the affiliatewp.com account page */
				__( 'Invalid license. Please <a href="%s" target="_blank">visit your account page</a> and verify it.', 'affiliate-wp' ),
				'https://affiliatewp.com/account/?utm_campaign=admin&utm_source=licenses&utm_medium=missing'
			),
		) );

		$this->add_notice( 'license-invalid', array(
			'class'   => 'error',
			'alias'   => 'license-site_inactive',
			'message' => sprintf(
				/* translators: URL to the affiliatewp.com account page */
				__( 'Your license key is not active for this URL. Please <a href="%s" target="_blank">visit your account page</a> to manage your license key URLs.', 'affiliate-wp' ),
				'https://affiliatewp.com/account/?utm_campaign=admin&utm_source=licenses&utm_medium=invalid'
			),
		) );

		$this->add_notice( 'license-item_name_mismatch', array(
			'class'   => 'error',
			'message' => __( 'This appears to be an invalid license key.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'license-no_activations_left', array(
			'class'   => 'error',
			'message' => sprintf(
				/* translators: URL to the affiliatewp.com account page */
				__( 'Your license key has reached its activation limit. <a href="%s">View possible upgrades</a> now.', 'affiliate-wp' ),
				'https://affiliatewp.com/account/?utm_campaign=admin&utm_source=licenses&utm_medium=missing'
			),
		) );

		$this->add_notice( 'expired_license', array(
			'class'   => array( 'error', 'info' ),
			'message' => function() {
				$notice_query_args = array(
					'affwp_action' => 'dismiss_notices',
					'affwp_notice' => 'expired_license',
				);

				$message =  __( 'Your license key for AffiliateWP has expired. Please renew your license to re-enable automatic updates.', 'affiliate-wp' ) . '</p>';
				$message .= '<p><a href="' . wp_nonce_url( add_query_arg( $notice_query_args ), 'affwp_dismiss_notice', 'affwp_dismiss_notice_nonce' ) . '">' . _x( 'Dismiss Notice', 'License', 'affiliate-wp' ) . '</a>';

				return $message;
			},
		) );

		$this->add_notice( 'invalid_license', array(
			'class'   => array( 'notice', 'notice-info' ),
			'message' => function() {
				$notice_query_args = array(
					'affwp_action' => 'dismiss_notices',
					'affwp_notice' => 'invalid_license',
				);

				/* translators: Settings screen URL */
				$message = sprintf( __( 'Please <a href="%s">enter and activate</a> your license key for AffiliateWP to enable automatic updates.', 'affiliate-wp' ), esc_url( affwp_admin_url( 'settings' ) ) ) . '</p>';
				$message .= '<p><a href="' . wp_nonce_url( add_query_arg( $notice_query_args ), 'affwp_dismiss_notice', 'affwp_dismiss_notice_nonce' ) . '">' . _x( 'Dismiss Notice', 'License', 'affiliate-wp' ) . '</a>';

				return $message;
			},
		) );

		if ( ! is_wp_error( $license ) && false === get_transient( 'affwp_license_notice' ) ) {

			// Base query args.
			$notice_query_args = array(
				'affwp_action' => 'dismiss_notices'
			);

			if ( is_object( $license ) ) {
				$status = $license->license;
			} else {
				$status = $license;
			}

			// Bail if there's no status.
			if ( empty( $status ) ) {
				return;
			}

			if ( 'expired' === $status ) {
				self::show_notice( 'expired_license', false === $this->display_notices );
			} elseif ( 'valid' !== $status ) {
				self::show_notice( 'invalid_license', false === $this->display_notices );
			}
		}
	}

	/**
	 * Registers settings admin notices.
	 *
	 * @since 2.4
	 */
	private function settings_notices() {
		$this->add_notice( 'settings-updated', array(
			'message' => __( 'Settings updated.', 'affiliate-wp' ),
		) );

		$this->add_notice( 'affiliates_migrated', array(
			'message' => function() {
				if ( ! class_exists( 'Affiliate_WP_Migrate_WP_Affiliate' ) ) {
					require_once AFFILIATEWP_PLUGIN_DIR . 'includes/admin/tools/class-migrate-wp-affiliate.php';
				}

				$total_affiliates = (int) Affiliate_WP_Migrate_WP_Affiliate::get_items_total( 'affwp_migrate_affiliates_total_count' );

				$message = sprintf( _n(
					/* translators: Singular number of affiliates added from WP Affiliate */
					'%d affiliate from WP Affiliate was added successfully.',
					/* translators: Plural number of affiliates added from WP Affiliate */
					'%d affiliates from WP Affiliate were added successfully',
					$total_affiliates,
					'affiliate-wp'
				), number_format_i18n( $total_affiliates ) );

				Affiliate_WP_Migrate_WP_Affiliate::clear_items_total( 'affwp_migrate_affiliates_total_count' );

				return $message;
			},
		) );

		$this->add_notice( 'affiliates_pro_migrated', array(
			'message' => function() {
				if ( ! class_exists( 'Affiliate_WP_Migrate_Affiliates_Pro' ) ) {
					require_once AFFILIATEWP_PLUGIN_DIR . 'includes/admin/tools/class-migrate-affiliates-pro.php';
				}

				$total_affiliates = (int) Affiliate_WP_Migrate_Affiliates_Pro::get_items_total( 'affwp_migrate_affiliates_pro_total_count' );

				$message = sprintf( _n(
					/* translators: Singular number of affiliates added from Affiliates Pro */
					'%d affiliate from Affiliates Pro was added successfully.',
					/* translators: Plural number of affiliates added from Affiliates Pro */
					'%d affiliates from Affiliates Pro were added successfully',
					$total_affiliates,
					'affiliate-wp'
				), number_format_i18n( $total_affiliates ) );

				Affiliate_WP_Migrate_Affiliates_Pro::clear_items_total( 'affwp_migrate_affiliates_pro_total_count' );

				return $message;
			},
		) );

		$this->add_notice( 'stats_recounted', array(
			'message' => __( 'Affiliate stats have been recounted!', 'affiliate-wp' ),
		) );

		$this->add_notice( 'settings-imported', array(
			'message' => __( 'Settings successfully imported', 'affiliate-wp' ),
		) );
	}

	/**
	 * Registers environment admin notices.
	 *
	 * @since 2.6
	 * @since 2.7.3 Updated for the PHP 7.0 soft bump
	 */
	public function environment_notices() {
		$this->add_notice( 'requirements_php_70', array(
			'class'       => 'notice-warning',
			'dismissible' => true,
			'message'     => function() {
				ob_start();
				?>
				<h3>
					<?php
					/* translators: Insecure PHP version */
					printf( __( 'AffiliateWP has detected that your site is running on an insecure version of PHP (%s).', 'affiliate-wp' ), phpversion() );
					?>
				</h3>

				<h4><?php _e( 'What is PHP and how does it affect my site?' ); ?></h4>
				<p><?php _e( 'PHP is the programming language used to build and maintain WordPress and plugins like AffiliateWP. Newer versions of PHP are both faster and more secure, so updating will have a positive effect on your site&#8217;s performance.', 'affiliate-wp' ); ?></p>

				<h4><?php _e( 'Why it matters for AffiliateWP', 'affiliate-wp' ); ?></h4>
				<p><?php _e( 'As we evolve over time, our ability to tap into more modern PHP features means we can continue to deliver a superior product to you.', 'affiliate-wp' ); ?></p>
				<p><?php _e( '<strong>Starting in September 2021, we&#8217;ll require PHP 7.0 or newer to use AffiliateWP.</strong>', 'affiliate-wp' ); ?></p>

				<p class="button-container">
					<?php
					printf(
						'<a class="button button-primary" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
						esc_url( wp_get_update_php_url() ),
						__( 'Learn more about updating PHP' ),
						/* translators: Accessibility text. */
						__( '(opens in a new tab)' )
					);
					?>
				</p>
				<?php

				wp_update_php_annotation();
				wp_direct_php_update_button();

				return ob_get_clean();
			}
		) );
	}

	/**
	 * Processes message data for output as admin notices.
	 *
	 * @since 2.1
	 * @since 2.4 Refactored to handle for multiple classes and for message to be a callable
	 *
	 * @param string|callable $message      Notice message.
	 * @param string|array    $class        Notice class or array of classes.
	 * @param string          $extra_output Optional. Extra output to append to the end of the message.
	 *                                      Default empty.
	 * @return string Notice markup or empty string if `$message` is empty.
	 */
	public static function prepare_message_for_output( $message, $class, $extra_output = '' ) {
		if ( ! empty( $message ) ) {
			if ( is_array( $class ) ) {
				$classes = implode( ' ', $class );
			} else {
				$classes = $class;
			}

			if ( is_callable( $message ) ) {
				$message = call_user_func( $message );
			}

			if ( ! empty( $extra_output ) ) {
				$message .= $extra_output;
			}

			$message = wpautop( $message, false );

			// wpautop() pads the end.
			$message = str_replace( "\n", '', $message );

			$output = sprintf( '<div class="%1$s">%2$s</div>',
				esc_attr( $classes ),
				$message
			);
		} else {
			$output = '';
		}

		return $output;
	}

	/**
	 * Prepares notice dismissal markup.
	 *
	 * @since 2.4
	 *
	 * @param string $notice_id Notice ID.
	 * @param string $label     Label.
	 * @return string HTML dismissal markup.
	 */
	public static function prepare_dismiss_output( $notice_id, $label ) {
		$notice_query_args = array(
			'affwp_action' => 'dismiss_notices',
			'affwp_notice' => $notice_id,
		);

		$url = wp_nonce_url( add_query_arg( $notice_query_args ), 'affwp_dismiss_notice', 'affwp_dismiss_notice_nonce' );

		return sprintf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), $label );
	}

	/**
	 * Dismisses promos via Ajax.
	 *
	 * @since 2.7
	 */
	public function dismiss_promo() {
		$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( $_POST['notice_id'] ) : false;
		$nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : false;

		if ( ! current_user_can( 'manage_options' ) ) {
			return wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $nonce, 'affwp-dismiss-notice-' . $notice_id ) ) {
			return wp_send_json_error();
		}

		$lifespan  = isset( $_POST['lifespan'] ) ? sanitize_text_field( $_POST['lifespan'] ) : '';

		Persistent_Dismissible::set( array(
			'id'   => $notice_id,
			'life' => $lifespan,
		) );

		wp_send_json_success();
	}

	/**
	 * Dismisses admin notices when Dismiss links are clicked.
	 *
	 * @since 1.7.5
	 */
	public function dismiss_notices() {
		if( ! isset( $_GET['affwp_dismiss_notice_nonce'] ) || ! wp_verify_nonce( $_GET['affwp_dismiss_notice_nonce'], 'affwp_dismiss_notice') ) {
			wp_die( __( 'Security check failed', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
		}

		if ( isset( $_GET['affwp_notice'] ) ) {

			$notice = sanitize_key( $_GET['affwp_notice'] );

			switch( $notice ) {
				case 'no_integrations':
					update_user_meta( get_current_user_id(), "_affwp_{$notice}_dismissed", 1 );
					break;
				case 'expired_license':
				case 'invalid_license':
					set_transient( 'affwp_license_notice', true, 2 * WEEK_IN_SECONDS );
					break;
				case 'payouts_service':
					set_transient( 'affwp_payouts_service_notice', true, 2 * WEEK_IN_SECONDS );
					break;
				case 'requirements_php_70':
					set_transient( 'affwp_requirements_php_70_notice', true, 2 * WEEK_IN_SECONDS );
					break;
				default:
					/**
					 * Fires once a notice has been flagged for dismissal.
					 *
					 * @since 1.8 as 'affwp_dismiss_notices'
					 * @since 2.0.4 Renamed to 'affwp_dismiss_notices_default' to avoid a dynamic hook conflict.
					 *
					 * @param string $notice Notice value via $_GET['affwp_notice'].
					 */
					do_action( 'affwp_dismiss_notices_default', $notice );
					break;
			}

			wp_redirect( remove_query_arg( array( 'affwp_action', 'affwp_notice' ) ) );
			exit;
		}
	}

	/**
	 * Helper method to add a notice to the registry.
	 *
	 * @since 2.4
	 *
	 * @param string $notice_id Notice ID.
	 * @param array  $notice_args Notice attributes.
	 * @return true|WP_Error True if successful, otherwise a WP_Error object.
	 */
	public function add_notice( $notice_id, $notice_args ) {
		return self::$registry->add_notice( $notice_id, $notice_args );
	}

	/**
	 * Renders a registered admin notice.
	 *
	 * @since 2.4
	 *
	 * @see Affiliate_WP_Admin_Notices::prepare_message_for_output()
	 *
	 * @param string|callable|array $notice Notice ID, callback to generate output on the fly, or an
	 *                                      array with on-the-fly notice 'message' and 'class' keys.
	 *                                      If passing a callable, the method assumes you've handled
	 *                                      preparation of the message on your own.
	 *                                      See {@see Affiliate_WP_Admin_Notices::prepare_message_for_output()}.
	 * @param bool                  $echo   Optional. Whether to echo the notice. Default true.
	 * @return void|string Void if `$echo` is true, otherwise a string.
	 */
	public static function show_notice( $notice, $echo = true ) {
		$output = '';

		if ( is_callable( $notice ) ) {
			$output = call_user_func( $notice );
		} else {
			// If a notice ID was passed, get its attributes.
			if ( is_string( $notice ) ) {
				$notice_id = $notice;
				$notice    = self::$registry->get( $notice );

				if ( false !== $notice ) {
					$notice['notice_id'] = $notice_id;
				}
			}

			$capability = empty( $notice['capability'] ) ? 'manage_affiliates' : $notice['capability'];

			if ( false !== $notice && current_user_can( $capability )
				&& ! empty( $notice['message'] ) && ! empty( $notice['class'] )
			) {
				if ( isset( $notice['dismissible'] ) && true === $notice['dismissible'] ) {
					$label = empty( $notice['dismiss_label'] ) ? _x( 'Dismiss', 'admin notice', 'affiliate-wp' ) : $notice['dismiss_label'];

					$extra_output = self::prepare_dismiss_output( $notice_id, $label );
				} else {
					$extra_output = '';
				}

				$output .= self::prepare_message_for_output( $notice['message'], $notice['class'], $extra_output );
			}
		}

		if ( true === $echo ) {
			echo $output;
		} else {
			return $output;
		}
	}

	/**
	 * Sets the display_notices property for unit testing purposes.
	 *
	 * If set to false, notice output will be returned rather than echoed.
	 *
	 * @since 2.1
	 *
	 * @param bool $display Whether to display notice output.
	 */
	public function set_display_notices( $display ) {
		$this->display_notices = (bool) $display;
	}

	/**
	 * Helper to retrieve a single notice from the registry.
	 *
	 * @since 2.4
	 *
	 * @param string $notice_id Notice ID.
	 * @return array|false Array of notice attributes if it exists, otherwise false.
	 */
	public function get_notice( $notice_id ) {
		return self::$registry->get( $notice_id );
	}

	/**
	 * Helper to retrieve all notices from the registry.
	 *
	 * @since 2.4
	 *
	 * @param bool $keys_only Optional. Whether to retrieve the notice IDs only. Default false.
	 * @return array Array of notices and their attributes. If `$keys_only` is true, an array of notice IDs.
	 */
	public function get_all_notices( $keys_only = false ) {
		$notices = self::$registry->get_items();

		if ( false !== $notices ) {
			$notices = array_keys( $notices );
		}

		return $notices;
	}

	/**
	 * Retrieves the current instance of the notices registry.
	 *
	 * @return \AffWP\Admin\Notices_Registry
	 */
	public function get_registry() {
		return self::$registry;
	}

}
new Affiliate_WP_Admin_Notices;
