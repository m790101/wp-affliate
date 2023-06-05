<?php
/**
 * Admin: Notices Registry
 *
 * @package     AffiliateWP
 * @subpackage  Admin
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

namespace AffWP\Admin;

use AffWP\Utils;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements an admin notice registry class.
 *
 * @since 2.4
 *
 * @see \AffWP\Utils\Registry
 */
class Notices_Registry extends Utils\Registry {

	/**
	 * Initializes the notices registry.
	 *
	 * @since 2.4
	 */
	public function init() {
		/**
		 * Fires during instantiation of the notices registry.
		 *
		 * @since 2.4
		 *
		 * @param \AffWP\Admin\Notices_Registry $this Registry instance.
		 */
		do_action( 'affwp_notices_registry_init', $this );
	}

	/**
	 * Registers a new admin notice.
	 *
	 * @since 2.4
	 *
	 * @param string $notice_id   Unique notice ID.
	 * @param array  $notice_args {
	 *     Arguments for registering a new notice.
	 *
	 *     @type string|array    $class         HTML class array of classes to use.
	 *     @type string|callable $message       Message as a string or callback that returns a message string.
	 *     @type string          $capability    Capability needed to see the notice.
	 *     @type string          $alias         Notice ID alias (registers a second alias with the same attributes).
	 *     @type bool            $dismissible   Whether the notice should be dismissible or not. Default false.
	 *     @type string          $dismiss_label Label to use if the notice is dismissible. Default 'Dismiss'.
	 * }
	 * @return \WP_Error|true True on successful registration, otherwise a WP_Error object.
	 */
	public function add_notice( $notice_id, $notice_args ) {
		// Bail if the notice is already registered.
		if ( $this->offsetExists( $notice_id ) ) {
			return new \WP_Error(
				'notice_exists',
				/* translators: Notice ID */
				sprintf( __( 'The %s notice already exists and could not be added.', 'affiliate-wp' ), $notice_id )
			);
		}

		$errors = new \WP_Error;

		$defaults = array(
			'class'         => 'updated',
			'message'       => '',
			'alias'         => '',
			'capability'    => 'manage_affiliates',
			'dismissible'   => false,
			'dismiss_label' => _x( 'Dismiss', 'admin notice', 'affiliate-wp' ),
		);

		$notice_args = wp_parse_args( $notice_args, $defaults );

		if ( ! is_array( $notice_args['class'] ) ) {
			$classes = explode( ' ', $notice_args['class'] );
		} else {
			$classes = $notice_args['class'];
		}

		if ( ! in_array( 'notice', $classes ) ) {
			$classes[] = 'notice';
		}

		$notice_args['class'] = array_map( 'sanitize_html_class', $classes );

		if ( empty( $notice_args['message'] ) ) {
			$errors->add( 'missing_notice_message', __( 'No message has been supplied for the notice.', 'affiliate-wp' ) );
		}

		$alias = '';

		if ( ! empty( $notice_args['alias'] ) ) {
			$alias = $notice_args['alias'];

			unset( $notice_args['alias'] );
		}

		if ( empty( $notice_args['dismissible'] ) && false !== $notice_args['dismissible'] ) {
			$notice_args['dismissible'] = false;
		} else {
			$notice_args['dismissible'] = (bool) $notice_args['dismissible'];
		}

		if ( true === $notice_args['dismissible'] && empty( $notice_args['dismiss_label'] ) ) {
			$errors->add( 'missing_dismiss_label', __( 'Dismissible notices must specify a dismiss_label attribute.', 'affiliate-wp' ) );
		}

		$has_errors = method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );

		if ( true === $has_errors ) {
			return $errors;
		}

		$notice_added = $this->add_item( $notice_id, $notice_args );

		if ( ! empty( $alias ) ) {
			$this->add_notice( $alias, $notice_args );
		}

		return $notice_added;
	}

	/**
	 * Removes an admin notice from the registry by ID.
	 *
	 * @since 2.4
	 *
	 * @param string $notice_id Notice ID.
	 */
	public function remove_notice( $notice_id ) {
		$this->remove_item( $notice_id );
	}

}
