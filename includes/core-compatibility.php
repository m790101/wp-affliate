<?php
/**
 * WordPress Core Compatibility Functions
 *
 * @package     AffiliateWP
 * @subpackage  Functions/WordPress
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6
 */

global $wp_version;

if ( true === version_compare( $wp_version, '4.7', '<' ) ) {
	if ( ! function_exists( 'wp_doing_ajax' ) ) {
		/**
		 * Determines whether the current request is a WordPress Ajax request.
		 *
		 * Copied verbatim from WordPress' wp_doing_ajax() function introduced in 4.7.0.
		 *
		 * @since 4.7.0
		 *
		 * @return bool True if it's a WordPress Ajax request, false otherwise.
		 */
		function wp_doing_ajax() {
			/**
			 * Filters whether the current request is a WordPress Ajax request.
			 *
			 * @since 4.7.0
			 *
			 * @param bool $wp_doing_ajax Whether the current request is a WordPress Ajax request.
			 */
			return apply_filters( 'wp_doing_ajax', defined( 'DOING_AJAX' ) && DOING_AJAX );
		}
	}
}

if ( true === version_compare( $wp_version, '5.1', '<' ) ) {
	if ( ! function_exists( 'wp_get_update_php_url' ) ) {
		/**
		 * Gets the URL to learn more about updating the PHP version the site is running on.
		 *
		 * This URL can be overridden by specifying an environment variable `WP_UPDATE_PHP_URL` or by using the
		 * {@see 'wp_update_php_url'} filter. Providing an empty string is not allowed and will result in the
		 * default URL being used. Furthermore the page the URL links to should preferably be localized in the
		 * site language.
		 *
		 * Copied verbatim from WordPress' wp_get_update_php_url() function introduced in 5.1.0.
		 *
		 * @since 5.1.0
		 *
		 * @return string URL to learn more about updating PHP.
		 */
		function wp_get_update_php_url() {
			$default_url = wp_get_default_update_php_url();

			$update_url = $default_url;
			if ( false !== getenv( 'WP_UPDATE_PHP_URL' ) ) {
				$update_url = getenv( 'WP_UPDATE_PHP_URL' );
			}

			/**
			 * Filters the URL to learn more about updating the PHP version the site is running on.
			 *
			 * Providing an empty string is not allowed and will result in the default URL being used. Furthermore
			 * the page the URL links to should preferably be localized in the site language.
			 *
			 * Copied verbatim from WordPress' 'wp_update_php_url' filter introduced in 5.1.0.
			 *
			 * @since 5.1.0
			 *
			 * @param string $update_url URL to learn more about updating PHP.
			 */
			$update_url = apply_filters( 'wp_update_php_url', $update_url );

			if ( empty( $update_url ) ) {
				$update_url = $default_url;
			}

			return $update_url;
		}
	}

	if ( ! function_exists( 'wp_get_default_update_php_url' ) ) {
		/**
		 * Gets the default URL to learn more about updating the PHP version the site is running on.
		 *
		 * Do not use this function to retrieve this URL. Instead, use {@see wp_get_update_php_url()} when relying on the URL.
		 * This function does not allow modifying the returned URL, and is only used to compare the actually used URL with the
		 * default one.
		 *
		 * Copied verbatim from WordPress' wp_get_default_update_php_url() function introduced in 5.1.0.
		 *
		 * @since 5.1.0
		 * @access private
		 *
		 * @return string Default URL to learn more about updating PHP.
		 */
		function wp_get_default_update_php_url() {
			return _x( 'https://wordpress.org/support/update-php/', 'localized PHP upgrade information page' );
		}
	}

	if ( ! function_exists( 'wp_update_php_annotation' ) ) {
		/**
		 * Prints the default annotation for the web host altering the "Update PHP" page URL.
		 *
		 * This function is to be used after {@see wp_get_update_php_url()} to display a consistent
		 * annotation if the web host has altered the default "Update PHP" page URL.
		 *
		 * Copied verbatim from WordPress' wp_update_php_annotation() function introduced in 5.1.0.
		 *
		 * @since 5.1.0
		 * @since 5.2.0 Added the `$before` and `$after` parameters.
		 *
		 * @param string $before Markup to output before the annotation. Default `<p class="description">`.
		 * @param string $after  Markup to output after the annotation. Default `</p>`.
		 */
		function wp_update_php_annotation( $before = '<p class="description">', $after = '</p>' ) {
			$annotation = wp_get_update_php_annotation();

			if ( $annotation ) {
				echo $before . $annotation . $after;
			}
		}
	}
}

if ( true === version_compare( $wp_version, '5.1.1', '<' ) ) {
	if ( ! function_exists( 'wp_get_direct_php_update_url' ) ) {
		/**
		 * Gets the URL for directly updating the PHP version the site is running on.
		 *
		 * A URL will only be returned if the `WP_DIRECT_UPDATE_PHP_URL` environment variable is specified or
		 * by using the {@see 'wp_direct_php_update_url'} filter. This allows hosts to send users directly to
		 * the page where they can update PHP to a newer version.
		 *
		 * Copied verbatim from WordPress' wp_get_direct_php_update_url() function introduced in 5.1.1.
		 *
		 * @since 5.1.1
		 *
		 * @return string URL for directly updating PHP or empty string.
		 */
		function wp_get_direct_php_update_url() {
			$direct_update_url = '';

			if ( false !== getenv( 'WP_DIRECT_UPDATE_PHP_URL' ) ) {
				$direct_update_url = getenv( 'WP_DIRECT_UPDATE_PHP_URL' );
			}

			/**
			 * Filters the URL for directly updating the PHP version the site is running on from the host.
			 *
			 * Copied verbatim from WordPress' 'wp_direct_php_update_url' filter introduced in 5.1.1.
			 *
			 * @since 5.1.1
			 *
			 * @param string $direct_update_url URL for directly updating PHP.
			 */
			$direct_update_url = apply_filters( 'wp_direct_php_update_url', $direct_update_url );

			return $direct_update_url;
		}
	}

	if ( ! function_exists( 'wp_direct_php_update_button' ) ) {
		/**
		 * Display a button directly linking to a PHP update process.
		 *
		 * This provides hosts with a way for users to be sent directly to their PHP update process.
		 *
		 * The button is only displayed if a URL is returned by `wp_get_direct_php_update_url()`.
		 *
		 * Copied verbatim from WordPress' wp_direct_php_update_button() function introduced in 5.1.1.
		 *
		 * @since 5.1.1
		 */
		function wp_direct_php_update_button() {
			$direct_update_url = wp_get_direct_php_update_url();

			if ( empty( $direct_update_url ) ) {
				return;
			}

			echo '<p class="button-container">';
			printf(
				'<a class="button button-primary" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
				esc_url( $direct_update_url ),
				__( 'Update PHP' ),
				/* translators: Accessibility text. */
				__( '(opens in a new tab)' )
			);
			echo '</p>';
		}
	}
}

if ( true === version_compare( $wp_version, '5.2', '<' ) ) {
	if ( ! function_exists( 'wp_get_update_php_annotation' ) ) {
		/**
		 * Returns the default annotation for the web hosting altering the "Update PHP" page URL.
		 *
		 * This function is to be used after {@see wp_get_update_php_url()} to return a consistent
		 * annotation if the web host has altered the default "Update PHP" page URL.
		 *
		 * Copied verbatim from WordPress' wp_get_update_php_annotation() function introduced in 5.2.0.
		 *
		 * @since 5.2.0
		 *
		 * @return string Update PHP page annotation. An empty string if no custom URLs are provided.
		 */
		function wp_get_update_php_annotation() {
			$update_url  = wp_get_update_php_url();
			$default_url = wp_get_default_update_php_url();

			if ( $update_url === $default_url ) {
				return '';
			}

			$annotation = sprintf(
			/* translators: %s: Default Update PHP page URL. */
				__( 'This resource is provided by your web host, and is specific to your site. For more information, <a href="%s" target="_blank">see the official WordPress documentation</a>.' ),
				esc_url( $default_url )
			);

			return $annotation;
		}
	}
}
