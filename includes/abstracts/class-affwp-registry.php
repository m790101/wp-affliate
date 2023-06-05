<?php
/**
 * Registry Model
 *
 * @package     AffiliateWP
 * @subpackage  Utilities
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.5
 */
namespace AffWP\Utils;

/**
 * Defines the construct for building an item registry.
 *
 * @since 2.0.5
 * @since 2.3.3 Now extends ArrayObject
 * @abstract
 */
abstract class Registry extends \ArrayObject {

	/**
	 * Array of registry items.
	 *
	 * @since 2.0.5
	 * @var   array
	 */
	private $items = array();

	/**
	 * Initialize the registry.
	 *
	 * Each sub-class will need to do various initialization operations in this method.
	 *
	 * @since 2.0.5
	 */
	abstract public function init();

	/**
	 * Adds an item to the registry.
	 *
	 * @since 2.0.5
	 * @since 2.6.4 $attributes argument can now be a non-array item.
	 *
	 * @param int    $item_id   Item ID.
	 * @param mixed  $attributes {
	 *     Item attributes.
	 *
	 *     @type string $class Item handler class.
	 *     @type string $file  Item handler class file.
	 * }
	 * @return true Always true.
	 */
	public function add_item( $item_id, $attributes ) {
		if ( is_array( $attributes ) ) {
			foreach ( $attributes as $attribute => $value ) {
				$this->items[ $item_id ][ $attribute ] = $value;
			}
		} else {
			$this->items[ $item_id ] = $attributes;
		}

		return true;
	}

	/**
	 * Removes an item from the registry by ID.
	 *
	 * @since 2.0.5
	 *
	 * @param string $item_id Item ID.
	 */
	public function remove_item( $item_id ) {
		unset( $this->items[ $item_id ] );
	}

	/**
	 * Retrieves an item and its associated attributes.
	 *
	 * @since 2.0.5
	 *
	 * @param string $item_id Item ID.
	 * @return array|false Array of attributes for the item if registered, otherwise false.
	 */
	public function get( $item_id ) {
		if ( isset( $this->items[ $item_id ] ) ) {
			return $this->items[ $item_id ];
		}

		return false;
	}

	/**
	 * Retrieves registered items.
	 *
	 * @since 2.0.5
	 *
	 * @return array The list of registered items.
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * Queries the registry fields against a set of specific parameters.
	 *
	 * Makes querying of any registry's items fast and straightforward with dynamically-built
	 * query arguments. For instance, if a registry entry contains an 'affiliate_id' key and
	 * the intent is to query all records with a given key and value, passing a given affiliate
	 * ID via 'affiliate_id__in' will filter items to only those with one of the given affiliate
	 * IDs. One or more dynamically-built arguments can be passed in this way to filter results.
	 *
	 * Example:
	 *
	 *     $registry->query( array(
	 *         'affiliate_id__in' => array( 1, 2, 3 ),
	 *         'status__not_in'   => array( 'pending', 'rejected' ),
	 *     ) );
	 *
	 * @since 2.6.3
	 *
	 * @param array $args {
	 *     List of arguments for querying registered items. Some arguments, like `$field`, `$field__in`,
	 *     and `$field__not_in` can be used multiple times to narrow down the results. Fields can be
	 *     appended with __in or __not_in to automatically filter by or exclude a list of values.
	 *
	 *     @type array  $key__in       Explicit keys for items to query from, where `$key` represents an
	 *                                 item key. If no items are found matching the given key(s), they key__in
	 *                                 argument will be ignored and the query set will comprise of all items.
	 *     @type string $field         Field/value pair to explicitly query items for where. Can be used multiple
	 *                                 times.
	 *     @type array  $field__in     Dynamic filter where the `$field` portion of the argument name represents
	 *                                 the field name and the value(s) represent the values to query matching
	 *                                 items for. For example, 'affiliate_id__in' or 'referrals__in'. Can be used
	 *                                 multiple times.
	 *     @type array  $field__not_in Dynamic filter where the `$field` portion of the argument name represents
	 *                                 the field name and the value(s) represent the values used to exclude matching
	 *                                 items. For example, 'affiliate_id__not_in' or 'referral__not_in'. Can be used
	 *                                 multiple times.
	 * }
	 * @return array<string, array> Keyed items filtered by the specified parameters.
	 */
	public function query( $args = array() ) {
		$results = array();

		$all_items = $this->get_items();

		// Filter out IDs before starting.
		if ( isset( $args['key__in'] ) ) {
			$items = array_intersect_key( $all_items, array_flip( $args['key__in'] ) );

			unset( $args['key__in'] );
		} else {
			$items = $all_items;
		}

		// Loop through filtered items, and get final set of items.
		foreach ( $items as $item_key => $item_value ) {
			$valid = true;

			foreach ( $args as $key => $arg ) {
				// Process the argument key
				$processed = explode( '__', $key );

				// Set the field type to the first item in the array.
				$field = $processed[0];

				// If there was some specificity after a __, use it.
				$type = count( $processed ) > 1 ? $processed[1] : 'in';

				// Bail early if this field is not in this item.
				if ( ! isset( $item_value[ $field ] ) ) {
					continue;
				}

				$object_field = $item_value[ $field ];

				// Convert argument to an array. This allows us to always use array functions for checking.
				if ( ! is_array( $arg ) ) {
					$arg = array( $arg );
				}

				// Convert field to array. This allows us to always use array functions to check.
				if ( ! is_array( $object_field ) ) {
					$object_field = array( $object_field );
				}

				// Run the intersection.
				$fields = array_intersect( $arg, $object_field );

				// Check based on type.
				switch ( $type ) {
					case 'not_in':
						$valid = empty( $fields );
						break;
					case 'and':
						$valid = count( $fields ) === count( $arg );
						break;
					default:
						$valid = ! empty( $fields );
						break;
				}

				if ( false === $valid ) {
					break;
				}
			}

			if ( true === $valid ) {
				$results[ $item_key ] = $item_value;
			}
		}

		return $results;
	}

	/**
	 * Only intended for use by tests.
	 *
	 * @since 2.0.5
	 */
	public function _reset_items() {
		if ( ! defined( 'WP_TESTS_DOMAIN' ) ) {
			_doing_it_wrong( 'This method is only intended for use in phpunit tests', '2.0.5' );
		} else {
			$this->items = array();
		}
	}

	/**
	 * Determines whether an item exists.
	 *
	 * @since 2.3.3
	 *
	 * @param string $offset Item ID.
	 * @return bool True if the item exists, false on failure.
	 */
	public function offsetExists( $offset ) {
		if ( false !== $this->get( $offset ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieves an item by its ID.
	 *
	 * Defined only for compatibility with ArrayAccess, use get() directly.
	 *
	 * @since 2.3.3
	 *
	 * @param string $offset Item ID.
	 * @return mixed The registered item, if it exists.
	 */
	public function offsetGet( $offset ) {
		return $this->get( $offset );
	}

	/**
	 * Adds/overwrites an item in the registry.
	 *
	 * Defined only for compatibility with ArrayAccess, use add_item() directly.
	 *
	 * @since 2.3.3
	 *
	 * @param string $offset Item ID.
	 * @param mixed  $value  Item attributes.
	 */
	public function offsetSet( $offset, $value ) {
		$this->add_item( $offset, $value );
	}

	/**
	 * Removes an item from the registry.
	 *
	 * Defined only for compatibility with ArrayAccess, use remove_item() directly.
	 *
	 * @since 2.3.3
	 *
	 * @param string $offset Item ID.
	 */
	public function offsetUnset( $offset ) {
		$this->remove_item( $offset );
	}
}
