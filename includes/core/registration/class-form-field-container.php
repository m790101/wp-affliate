<?php
/**
 * Registration: Form Field Container
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
 * Class Form_Field_Container
 *
 * @since   2.8
 *
 * @package AFFWP
 */
class Form_Field_Container {

	/**
	 * Sanitize Callback.
	 *
	 * @since 2.8
	 *
	 * @var callable|false
	 */
	protected $sanitize_callback;

	/**
	 * Validate Callback.
	 *
	 * @since 2.8
	 *
	 * @var callable|false
	 */
	protected $validate_callback;

	/**
	 * Field Type.
	 *
	 * @since 2.8
	 *
	 * @var string
	 */
	protected $field_type = '';

	/**
	 * Name
	 *
	 * @since 2.8
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Field label
	 *
	 * @since 2.8
	 *
	 * @var string
	 */
	protected $label = '';

	/**
	 * Meta Field
	 *
	 * @since 2.8
	 *
	 * @var string
	 */
	protected $meta_field = '';

	/**
	 * Legacy Field Type
	 *
	 * @since 2.8
	 *
	 * @var string|null
	 */
	protected $legacy_type = null;

	/**
	 * Required. Set to true if this field is required.
	 *
	 * @since 2.8
	 *
	 * @var bool
	 */
	protected $required = false;

	/**
	 * Form_Field_Container constructor.
	 *
	 * @since 2.8
	 *
	 * @param array $args {
	 *     Form field container arguments.
	 *
	 *     @type string $field_type  The field block type to register. Usually the block type
	 *     @type string $label       The field label. Default empty string.
	 *     @type string $legacy_type The legacy field type, used for backcompat with the legacy form. Default empty.
	 *     @type string $meta_field  The meta field key to save as in affiliate meta. Default: label with underscores.
	 *     @type bool   $required    True if the field is required, otherwise false. Default false.
	 * }
	 */
	public function __construct( $args = array() ) {
		$defaults = array(
			'field_type'  => '',
			'label'       => '',
			'legacy_type' => '',
			'meta_field'  => '',
			'required'    => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$field_type = preg_replace( '/affiliatewp\/(field-)?/', '', $args['field_type'] );

		// If meta field is not provided, try to use the label directly.
		if ( empty( $args['meta_field'] ) ) {
			$this->meta_field = str_replace( '-', '_', sanitize_title( $args['label'] ) );
		}

		$this->name       = 'affwp_' . $this->meta_field . '_' . $field_type;
		$this->field_type = str_replace( '-', '_', $field_type );

		$this->label = $args['label'];

		$this->legacy_type = $args['legacy_type'];

		$this->required          = (bool) $args['required'];
		$callbacks               = $this->get_callbacks();
		$this->sanitize_callback = isset( $callbacks['sanitize_callback'] ) ? $callbacks['sanitize_callback'] : false;
		$this->validate_callback = isset( $callbacks['validate_callback'] ) ? $callbacks['validate_callback'] : false;

		// If the sanitize callback is specified, and invalid, do not save the field.
		if ( false !== $this->sanitize_callback && ! is_callable( $this->sanitize_callback ) ) {
			$this->validate_callback = '__return_false';
			affiliate_wp()->utils->log( 'invalid_sanitize_callback', 'The provided affiliate registration form field sanitize callback is invalid', array(
				'sanitize_callback' => $this->sanitize_callback,
				'expects_type'      => 'callable',
			) );
		}
	}

	/**
	 * Retrieves the sanitize and validation callbacks for the current field.
	 *
	 * @since 2.8
	 *
	 * @return array Array containing the sanitize_callback and validate_callback strings.
	 */
	private function get_callbacks() {
		$callback_types = array(
			'email'    => array(
				'validate_callback' => 'is_email',
				'sanitize_callback' => 'sanitize_email',
			),
			'text'     => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'textarea' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'website'  => array(
				'sanitize_callback' => 'esc_url',
			),
			'password' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'phone'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'checkbox' => array( 'sanitize_callback' => function ( $value ) {
				return (bool) $value;
			} ),
		);

		return isset( $callback_types[ $this->field_type ] ) ? $callback_types[ $this->field_type ] : array();
	}

	/**
	 * Checks to see if the provided field is a legacy field.
	 *
	 * @since 2.8
	 *
	 * @return bool
	 */
	public function is_legacy_field() {

		/**
		 * Legacy Affiliate Registration Fields.
		 *
		 * Contains a list of legacy field names that do not use the block-based editor.
		 *
		 * @since 2.8
		 *
		 * @param array $fields list of legacy field names.
		 *
		 */
		$legacy_fields = apply_filters( 'affwp_legacy_affiliate_registration_fields', array(
			'name',
			'username',
			'account',
			'payment',
			'websiteUrl',
			'promotionMethod',
		) );

		return in_array( $this->legacy_type, $legacy_fields );
	}


	/**
	 * Validates the field, given a value.
	 *
	 * @since 2.8
	 *
	 * @param mixed $value The field value.
	 *
	 * @return true|\WP_Error True if valid, otherwise WP Error explaining why the field is invalid.
	 */
	public function validate( $value ) {
		$valid = true;

		if ( false !== $this->validate_callback ) {
			if ( ! is_callable( $this->validate_callback ) ) {
				$valid = new \WP_Error( 'could_not_validate', 'Could not validate this field. Please contact an administrator.' );
			} else {
				$valid = call_user_func( $this->validate_callback, $value );
			}
		}

		// If this field is required, and was not provided, add an error.
		if ( empty( $value ) && true === $this->required ) {
			$code    = 'field_' . $this->meta_field . '_required';
			/* translators: Field label. */
			$message = __( sprintf( 'The field "%s" is required', $this->label ) );
			if ( is_wp_error( $valid ) ) {
				$valid->add_error( $code, $message );
			} else {
				$valid = new \WP_Error( $code, $message );
			}
		}

		return $valid;
	}

	/**
	 * Sanitizes a field's value.
	 *
	 * @since 2.8
	 *
	 * @param mixed $value The field value.
	 *
	 * @return mixed The field value.
	 */
	public function sanitize( $value ) {
		if ( is_callable( $this->sanitize_callback ) ) {
			$value = call_user_func( $this->sanitize_callback, $value );
		}

		return $value;
	}

	public function __get( $key ) {
		return $this->$key;
	}

}