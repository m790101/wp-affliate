<?php
/**
 * Utilities: Logging API
 *
 * @package     AffiliateWP
 * @subpackage  Utilities
 * @copyright   Copyright (c) 2017, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.9
 */

class Affiliate_WP_Logging {

	public $is_writable = true;
	private $filename   = '';
	private $file       ='';

	/**
	 * Get things started
	 *
	 * @since 1.7.15
	 */
	public function __construct() {

		$this->init();

	}

	/**
	 * Get things started
	 *
	 * @since 1.7.15
	 * @return void
	 */
	public function init() {

		$upload_dir     = wp_upload_dir( null, false );
		$hash           = affwp_get_hash( $upload_dir, AUTH_SALT );
		$this->filename = sprintf( 'affwp-debug-log__%s.log', $hash );

		$base_dir   = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : ABSPATH;
		$this->file = trailingslashit( $base_dir ) . $this->filename;

		if ( ! is_writeable( $base_dir ) ) {
			$this->is_writable = false;
		}

	}

	/**
	 * Retrieve the log data
	 *
	 * @since 1.7.15
	 * @return string
	 */
	public function get_log() {
		return $this->get_file();
	}

	/**
	 * Log message to file
	 *
	 * @since 1.7.15
	 * @since 2.3 An optional `$data` parameter was added.
	 *
	 * @param string      $message Message to write to the debug log.
	 * @param array|mixed $data    Optional. Array of data or other output to send to the log.
	 *                             Default empty array.
	 * @return void
	 */
	public function log( $message, $data = array() ) {
		$message = date( 'Y-n-d H:i:s' ) . ' - ' . $message . "\r\n";

		if ( ! empty( $data ) ) {
			if ( is_array( $data ) ) {
				$data = var_export( $data, true );
			} elseif ( is_wp_error( $data ) ) {
				$data = $this->collate_errors( $data );
			} else {
				ob_start();

				var_dump( $data );

				$data = ob_get_clean();
			}

			$message .= $data;
		}


		$this->write_to_log( $message );
	}

	/**
	 * Retrieve the file data is written to
	 *
	 * @since 1.7.15
	 * @return string
	 */
	protected function get_file() {

		$file = '';

		if ( @file_exists( $this->file ) ) {

			if ( ! is_writeable( $this->file ) ) {
				$this->is_writable = false;
			}

			$file = @file_get_contents( $this->file );

		} else {

			@file_put_contents( $this->file, '' );
			@chmod( $this->file, 0664 );

		}

		return $file;
	}

	/**
	 * Write the log message
	 *
	 * @since 1.7.15
	 *
	 * @param string $message Message to write to the debug log.
	 * @return void
	 */
	protected function write_to_log( $message ) {
		$file = $this->get_file();
		$file .= $message;

		@file_put_contents( $this->file, $file );
	}

	/**
	 * Write the log message
	 *
	 * @since 1.7.15
	 * @return void
	 */
	public function clear_log() {
		@unlink( $this->file );
	}

	/**
	 * Collates errors stored in a WP_Error object for output to the debug log.
	 *
	 * @since 2.3
	 *
	 * @param \WP_Error $wp_error WP_Error object.
	 * @return string Error log output. Empty if not a WP_Error object or if there are no errors to collate.
	 */
	public function collate_errors( $wp_error ) {
		$output = '';

		if ( ! is_wp_error( $wp_error ) ) {
			return $output;
		}

		$has_errors = method_exists( $wp_error, 'has_errors' ) ? $wp_error->has_errors() : ! empty( $wp_error->errors );

		if ( false === $has_errors ) {
			return $output;
		}

		foreach ( $wp_error->errors as $code => $messages ) {
			$message = implode( ' ', $messages );

			if ( isset( $wp_error->error_data[ $code ] ) ) {
				$data = $wp_error->error_data[ $code ];
			} else {
				$data = '';
			}

			$output .= sprintf( '- AffWP Error (%1$s): %2$s', $code, $message ) . "\r\n";

			if ( ! empty( $data ) ) {
				$output .= var_export( $data, true ) . "\r\n";
			}
		}

		return $output;
	}

	/**
	 * Retrieves the filesize of the log file.
	 *
	 * @since 2.5.4
	 *
	 * @param bool $formatted Whether to retrieve the formatted filesize. Default false.
	 * @return int|string Filesize in bytes or 0 if it doesn't exist. If `$formatted` is true,
	 *                    a formatted string.
	 */
	public function get_log_size( $formatted = false ) {
		$filesize = 0;

		if ( @file_exists( $this->file ) ) {
			$filesize = filesize( $this->file );
		}

		if ( true === $formatted ) {
			$filesize = size_format( $filesize, 2 );
		}

		return $filesize;
	}

}
