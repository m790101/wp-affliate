<?php
/**
 * CLI: Affiliate Meta Sub-Commands
 *
 * @package     AffiliateWP
 * @subpackage  CLI
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4.3
 */

namespace AffWP\Affiliate\Meta\CLI;

use WP_CLI\CommandWithMeta;

/**
 * Class to implement adding, updating, deleting, and listing affiliate meta fields via wp-cli.
 *
 * @since 2.4.3
 *
 * ## EXAMPLES
 *
 *     # Set affiliate meta.
 *     $ wp affwp affiliate meta add 123 customer_id 5
 *     Success: Added custom field.
 *
 *     # Get affiliate meta.
 *     $ wp affwp affiliate meta get 123 customer_id
 *     5
 *
 *     # Update affiliate meta.
 *     $ wp affwp affiliate meta update 123 customer_id 6
 *     Success: Updated custom field 'customer_id'.
 *
 *     # Delete affiliate meta.
 *     $ wp affwp affiliate meta delete 123 customer_id
 *     Success: Deleted custom field.
 */
class Sub_Commands extends CommandWithMeta {

	/**
	 * Meta type.
	 *
	 * @since 2.4.3
	 * @var   string
	 */
	protected $meta_type = 'affiliate';

	/**
	 * Check that the affiliate ID exists.
	 *
	 * @param int Object ID.
	 * @return int
	 */
	protected function check_object_id( $object_id ) {
		$fetcher   = new \AffWP\Affiliate\CLI\Fetcher;
		$affiliate = $fetcher->get( $object_id );

		return $affiliate->ID;
	}

	/**
	 * Wrapper method for add_metadata that can be overridden in sub classes.
	 *
	 * @since 2.4.3
	 *
	 * @param int    $object_id  ID of the object the metadata is for.
	 * @param string $meta_key   Metadata key to use.
	 * @param mixed  $meta_value Metadata value. Must be serializable if
	 *                           non-scalar.
	 * @param bool   $unique     Optional, default is false. Whether the
	 *                           specified metadata key should be unique for the
	 *                           object. If true, and the object already has a
	 *                           value for the specified metadata key, no change
	 *                           will be made.
	 *
	 * @return int|false The meta ID on success, false on failure.
	 */
	protected function add_metadata( $object_id, $meta_key, $meta_value, $unique = false ) {
		return affwp_add_affiliate_meta( $object_id, $meta_key, $meta_value, $unique );
	}

	/**
	 * Wrapper method for update_metadata that can be overridden in sub classes.
	 *
	 * @since 2.4.3
	 *
	 * @param int    $object_id  ID of the object the metadata is for.
	 * @param string $meta_key   Metadata key to use.
	 * @param mixed  $meta_value Metadata value. Must be serializable if
	 *                           non-scalar.
	 * @param mixed  $prev_value Optional. If specified, only update existing
	 *                           metadata entries with the specified value.
	 *                           Otherwise, update all entries.
	 *
	 * @return int|bool Meta ID if the key didn't exist, true on successful
	 *                  update, false on failure.
	 */
	protected function update_metadata( $object_id, $meta_key, $meta_value, $prev_value = '' ) {
		return affwp_update_affiliate_meta( $object_id, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Wrapper method for get_metadata that can be overridden in sub classes.
	 *
	 * @since 2.4.3
	 *
	 * @param int    $object_id ID of the object the metadata is for.
	 * @param string $meta_key  Optional. Metadata key. If not specified,
	 *                          retrieve all metadata for the specified object.
	 * @param bool   $single    Optional, default is false. If true, return only
	 *                          the first value of the specified meta_key. This
	 *                          parameter has no effect if meta_key is not
	 *                          specified.
	 *
	 * @return mixed Single metadata value, or array of values.
	 */
	protected function get_metadata( $object_id, $meta_key = '', $single = false ) {
		return affwp_get_affiliate_meta( $object_id, $meta_key, $single );
	}

	/**
	 * Wrapper method for delete_metadata that can be overridden in sub classes.
	 *
	 * @since 2.4.3
	 *
	 * @param int    $object_id  ID of the object metadata is for
	 * @param string $meta_key   Metadata key
	 * @param mixed $meta_value  Optional. Metadata value. Must be serializable
	 *                           if non-scalar. If specified, only delete
	 *                           metadata entries with this value. Otherwise,
	 *                           delete all entries with the specified meta_key.
	 *                           Pass `null, `false`, or an empty string to skip
	 *                           this check. For backward compatibility, it is
	 *                           not possible to pass an empty string to delete
	 *                           those entries with an empty string for a value.
	 *
	 * @return bool True on successful delete, false on failure.
	 */
	protected function delete_metadata( $object_id, $meta_key, $meta_value = '' ) {
		return affwp_delete_affiliate_meta( $object_id, $meta_key, $meta_value );
	}
}

try {

	\WP_CLI::add_command( 'affwp affiliate meta', 'AffWP\Affiliate\Meta\CLI\Sub_Commands' );

} catch( \Exception $exception ) {

	affiliate_wp()->utils->log( $exception->getCode() . ' - ' . $exception->getMessage() );

}
