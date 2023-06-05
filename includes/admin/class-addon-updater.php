<?php

//set_site_transient( 'update_plugins', null );

class AffWP_AddOn_Updater {

	private $api_url    = '';
	private $api_data   = array();
	private $addon_id   = '';
	private $name       = '';
	private $slug       = '';
	private $version    = '';
	private $cache_key  = '';
	private $failed_request_cache_key;

	/**
	 * Class constructor.
	 *
	 * @uses plugin_basename()
	 * @uses hook()
	 *
	 * @param string $_api_url The URL pointing to the custom API endpoint.
	 * @param string $_plugin_file Path to the plugin file.
	 * @param array $_api_data Optional data to send with API calls.
	 * @return void
	 */
	function __construct( $_addon_id, $_plugin_file, $_version ) {
		$this->api_url    = 'https://affiliatewp.com';
		$this->addon_id   = $_addon_id;
		$this->name       = plugin_basename( $_plugin_file );
		$this->slug       = basename( $_plugin_file, '.php');
		$this->version    = $_version;
		$this->cache_key  = 'edd_sl_' . md5( serialize( $this->slug . $this->addon_id ) );

		$this->failed_request_cache_key = 'edd_sl_failed_http_' . md5( $this->api_url );

		// Set up hooks.
		$this->hook();
	}

	/**
	 * Set up Wordpress filters to hook into WP's update process.
	 *
	 * @uses add_filter()
	 *
	 * @return void
	 */
	private function hook() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
		add_action( 'after_plugin_row_' . $this->name, array( $this, 'show_update_notification' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'show_changelog' ) );

		// Due to some older extensions not loading after AffiliateWP, fetch the data initially on plugins_loaded.
		add_action( 'plugins_loaded', array( $this, 'fetch_initial_plugin_data' ), 11 );
	}

	/**
	 * Fetches the initial plugin data.
	 *
	 * @since 2.6.4.1
	 *
	 * This method exists to do the initial splicing of the plugin data, either from cache or the API,
	 * into the global data so it can be added to the main update_plugins transient of WordPress Core.
	 *
	 * @return void
	 */
	public function fetch_initial_plugin_data() {
		global $affwp_plugin_data;

		$affwp_plugin_data[ $this->slug ] = $this->get_repo_api_data();

		/**
		 * Fires after the $affwp_plugin_data is setup.
		 *
		 * @since 2.6.4
		 *
		 * @param array $affwp_plugin_data Array of EDD SL plugin data.
		 */
		do_action( 'post_affwp_sl_plugin_updater_setup', $affwp_plugin_data );
	}

	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update api just when Wordpress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native Wordpress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @uses api_request()
	 *
	 * @param array $_transient_data Update array build by Wordpress.
	 * @return array Modified update array with custom plugin data.
	 */
	function check_update( $_transient_data ) {

		global $pagenow;

		if( 'plugins.php' == $pagenow && is_multisite() ) {
			return $_transient_data;
		}

		if( ! is_object( $_transient_data ) ) {
			$_transient_data = new stdClass;
		}

		if ( empty( $_transient_data->response ) || empty( $_transient_data->response[ $this->name ] ) ) {

			$api_response = $this->get_repo_api_data();

			if( false !== $api_response && is_object( $api_response ) && isset( $api_response->new_version ) ) {
				if( version_compare( $this->version, $api_response->new_version, '<' ) ) {
					$_transient_data->response[ $this->name ] = $api_response;
				}
			}

			$_transient_data->last_checked = time();
			$_transient_data->checked[ $this->name ] = $this->version;

		}

		return $_transient_data;
	}

	/**
	 * Gets repo API data from store.
	 *
	 * Save to cache.
	 *
	 * @since 2.6.4
	 *
	 * @return \stdClass|false
	 */
	public function get_repo_api_data() {
		$version_info = $this->get_cached_version_info();

		if ( false === $version_info ) {
			$version_info = $this->api_request(
				'plugin_latest_version',
				array(
					'slug' => $this->slug,
				)
			);

			if ( ! $version_info ) {
				return false;
			}

			// This is required for your plugin to support auto-updates in WordPress 5.5.
			$version_info->plugin = $this->name;
			$version_info->id     = $this->name;

			$this->set_version_info_cache( $version_info );
		}

		return $version_info;
	}

	/**
	 * Gets the plugin's cached version information from the database.
	 *
	 * @since 2.6.4
	 *
	 * @param string $cache_key Optional. Cache key. Defaults to the value of the `$cache_key` property.
	 * @return \stdClass|false Version info if set, otherwise false.
	 */
	public function get_cached_version_info( $cache_key = '' ) {

		if( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$cache = get_option( $cache_key );

		if( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false; // Cache is expired
		}

		// We need to turn the icons into an array, thanks to WP Core forcing these into an object at some point.
		$cache['value'] = json_decode( $cache['value'] );

		if ( ! empty( $cache['value']->icons ) ) {
			$cache['value']->icons = (array) $cache['value']->icons;
		}

		return $cache['value'];

	}

	/**
	 * Adds the plugin version information to the database.
	 *
	 * @since 2.6.4
	 *
	 * @param \stdClass $value     Version info to pass into the cache.
	 * @param string    $cache_key Optional. Cache key. Defaults to the value of the `$cache_key` property.
	 */
	public function set_version_info_cache( $value, $cache_key = '' ) {

		if( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$data = array(
			'timeout' => strtotime( '+3 hours', time() ),
			'value'   => json_encode( $value )
		);

		update_option( $cache_key, $data, 'no' );

		// Delete the duplicate option
		delete_option( 'edd_api_request_' . md5( serialize( $this->slug . $this->addon_id ) ) );
	}

	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param mixed $_data
	 * @param string $_action
	 * @param object $_args
	 * @return object $_data
	 */
	function plugins_api_filter( $_data, $_action = '', $_args = null ) {
		if ( ( $_action != 'plugin_information' ) || !isset( $_args->slug ) || ( $_args->slug != $this->slug ) ) {
			return $_data;
		}

		$to_send = array( 'slug' => $this->slug );

		$api_response = $this->api_request( 'plugin_information', $to_send );
		if ( false !== $api_response ) {
			$_data = $api_response;
		}
		return $_data;
	}

	/**
     * show update nofication row -- needed for multisite subsites, because WP won't tell you otherwise!
     *
     * @param string  $file
     * @param array   $plugin
     */
	public function show_update_notification( $file, $plugin ) {

		if( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if( ! is_multisite() || is_network_admin() ) {
			return;
		}

		if ( $this->name != $file ) {
			return;
		}

		// Remove our filter on the site transient
		remove_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ), 10 );

		$update_cache = get_site_transient( 'update_plugins' );

		if ( ! is_object( $update_cache ) || empty( $update_cache->response ) || empty( $update_cache->response[ $this->name ] ) ) {

			$version_info = $this->get_repo_api_data();

			if( false === $version_info ) {

				$version_info = $this->api_request( 'plugin_latest_version', array( 'slug' => $this->slug ) );

				$this->set_version_info_cache( $version_info );
			}


			if( ! is_object( $version_info ) ) {
				return;
			}

			if( version_compare( $this->version, $version_info->new_version, '<' ) ) {

				$update_cache->response[ $this->name ] = $version_info;

			}

			$update_cache->last_checked = time();
			$update_cache->checked[ $this->name ] = $this->version;

			set_site_transient( 'update_plugins', $update_cache );

		} else {

			$version_info = $update_cache->response[ $this->name ];

		}

		if ( ! empty( $update_cache->response[ $this->name ] ) && version_compare( $this->version, $update_cache->response[ $this->name ]->new_version, '<' ) ) {

			// build a plugin list row, with update notification
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
			echo '<tr class="plugin-update-tr" id="' . $this->slug . '-update" data-slug="' . $this->slug . '" data-plugin="' . $this->slug . '/' . $this->name . '">';
			echo '<td colspan="3" class="plugin-update colspanchange">';
			echo '<div class="update-message notice inline notice-warning notice-alt"><p>';


			$changelog_link = self_admin_url( 'index.php?affwp_action=view_plugin_changelog&plugin=' . $this->name . '&slug=' . $this->slug . '&addon_id=' . $this->addon_id . '&TB_iframe=true&width=772&height=911' );

			if ( empty( $version_info->download_link ) ) {
				printf(
					/* translators: 1: Plugin name, 2: Changelog URL, 3: New plugin version */
					__( 'There is a new version of %1$s available. <a target="_blank" class="thickbox" href="%2$s">View version %3$s details</a>.', 'affiliate-wp' ),
					esc_html( $version_info->name ),
					esc_url( $changelog_link ),
					esc_html( $version_info->new_version )
				);
			} else {
				printf(
					/* translators: 1: Plugin name, 2: Changelog URL, 3: New plugin version, 4: Update URL */
					__( 'There is a new version of %1$s available. <a target="_blank" class="thickbox" href="%2$s">View version %3$s details</a> or <a href="%4$s">update now</a>.', 'affiliate-wp' ),
					esc_html( $version_info->name ),
					esc_url( $changelog_link ),
					esc_html( $version_info->new_version ),
					esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $this->name, 'upgrade-plugin_' . $this->name ) )
				);
			}

			echo '</p></div></td></tr>';
		}

		// Restore our filter
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
	}

	/**
	 * Calls the API and, if successful, returns the object delivered by the API.
	 *
	 * @uses get_bloginfo()
	 * @uses wp_remote_post()
	 * @uses is_wp_error()
	 *
	 * @param string $_action The requested action.
	 * @param array $_data Parameters for the API action.
	 * @return false|object|void
	 */
	private function api_request( $_action, $_data ) {

		global $wp_version;

		$data = $_data;

		$data['license'] = affiliate_wp()->settings->get( 'license_key' );

		if( empty( $data['license'] ) ) {
			return;
		}

		if( empty( $data['addon_id'] ) ) {
			$data['addon_id'] = $this->addon_id;
		}

		if( empty( $data['addon_id'] ) ) {
			return;
		}

		if ( $this->request_recently_failed() ) {
			return false;
		}

		$api_params = array(
			'affwp_action'  => 'get_version',
			'license'       => $data['license'],
			'id'            => $data['addon_id'],
			'slug'          => $data['slug'],
			'url'           => home_url(),
			'php_version'   => phpversion(),
			'affwp_version' => get_option( 'affwp_version' ),
			'wp_version'    => $wp_version,
		);

		$request = wp_remote_post( $this->api_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		if ( ! is_wp_error( $request ) && ( 200 === wp_remote_retrieve_response_code( $request ) ) ) {
			$request = json_decode( wp_remote_retrieve_body( $request ) );
			if( $request && isset( $request->sections ) ) {
				$request->sections = maybe_unserialize( $request->sections );
			}

			return $request;

		} else {
			$this->log_failed_request();

			return false;

		}

	}

	/**
	 * Determines if a request has recently failed.
	 *
	 * @return bool
	 */
	private function request_recently_failed() {
		$failed_request_details = get_option( $this->failed_request_cache_key );

		// Request has never failed.
		if ( empty( $failed_request_details ) || ! is_numeric( $failed_request_details ) ) {
			return false;
		}

		/*
		 * Request previously failed, but the timeout has expired.
		 * This means we're allowed to try again.
		 */
		if ( time() > $failed_request_details ) {
			delete_option( $this->failed_request_cache_key );

			return false;
		}

		return true;
	}

	/**
	 * Logs a failed HTTP request for this API URL.
	 */
	private function log_failed_request() {
		update_option( $this->failed_request_cache_key, strtotime( '+1 hour' ) );
	}

	public function show_changelog() {

		if( empty( $_REQUEST['affwp_action'] ) || 'view_plugin_changelog' != $_REQUEST['affwp_action'] ) {
		    return;
		}

		if( empty( $_REQUEST['plugin'] ) ) {
		    return;
		}

		if( empty( $_REQUEST['slug'] ) ) {
		    return;
		}

		if( ! current_user_can( 'update_plugins' ) ) {
			wp_die( __( 'You do not have permission to install plugin updates', 'affiliate-wp' ), __( 'Error', 'affiliate-wp' ), array( 'response' => 403 ) );
		}

		$response = $this->api_request( 'plugin_latest_version', array( 'slug' => $_REQUEST['slug'], 'addon_id' => $_REQUEST['addon_id'] ) );

		if( $response && isset( $response->sections['changelog'] ) ) {
			echo '<div style="background:#fff;padding:10px;height:100%;">' . $response->sections['changelog'] . '</div>';
		}

		exit;

	}

}
