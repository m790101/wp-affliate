<?php
/**
 * Registration: Form Container
 *
 * @package     AffiliateWP
 * @subpackage  Core/Registration
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.8
 */
namespace AffWP\Core\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registration form handler.
 *
 * @since 2.8
 */
class Form_Container {

	/**
	 * Form fields.
	 *
	 * @since 2.8
	 *
	 * @var Form_Field_Container[] Array of form fields
	 */
	protected $fields = array();

	/**
	 * Sets up the form container.
	 *
	 * @since 2.8
	 *
	 * @param array $args {
	 *     Array of arguments to construct this form.
	 *
	 *     @type array $fields Form fields.
	 * }
	 */
	public function __construct( $args ) {
		$defaults = array(
			'fields' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Strip invalid form fields.
		foreach ( $args['fields'] as $field ) {
			if ( $field instanceof Form_Field_Container ) {
				$this->fields[] = $field;
			} else {
				affiliate_wp()->utils->log( 'invalid_form_field', 'A registration field was not added because it was invalid' );
			}
		}
	}

	/**
	 * Retrieve the hash for this form.
	 *
	 * @since 2.8
	 *
	 * @return string the hash
	 */
	public function get_hash() {
		// Convert objects to arrays. This ensures they get normalized.
		$fields = array();
		foreach ( $this->fields as $field ) {
			$fields[] = (array) $field;
		}
		return affwp_get_hash( $fields );
	}

	/**
	 * Get magic method. Makes all private and protected fields accessible without making it possible to modify their
	 * values.
	 *
	 * @param string $key the key to retrieve.
	 *
	 * @return mixed the value
	 */
	public function __get( $key ) {
		return $this->$key;
	}

}