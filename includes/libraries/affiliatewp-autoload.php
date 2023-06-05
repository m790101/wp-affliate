<?php
/**
 * AffiliateWP Autoloader
 *
 * ////
 *
 * Used in place of affiliatewp-autoloader.php while the plugin code base
 * is being transitioned to full autoloader compatibility.
 *
 * ////
 *
 * Supports autoloading classes, interfaces, and traits. Class file names must use the "class-" prefix,
 * interfaces the "interface-" prefix, and traits the "trait-" prefix.
 *
 * For traits and interfaces, the fully-qualified name need not include the words "Trait" nor "Interface"
 * if the namespace includes the words "Traits" or "Interfaces", respectively.
 *
 * For example:
 *  - interface-foo.php: Base\Interfaces\Foo, Interface_Foo, or Foo_Interface
 *  - trait-bar.php: Base\Traits\Bar, Trait_Bar, or Bar_Trait
 *
 * @package     AffiliateWP
 * @subpackage  Tools
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @author      Drew Jaynes
 * @version     2.7
 */

$autoload = array(
	// Classes.
	'AffWP\Core\Registration\Form_Container',
	'AffWP\Core\Registration\Form_Field_Container',
);

spl_autoload_register( function( $filename ) use ( $autoload ) {

	// Bail if not in the hard-coded list.
	if ( ! in_array( $filename, $autoload ) ) {
		return;
	}

	// First, separate the components of the incoming file.
	$file_path = explode( '\\', $filename );

	/*
	 * - The first index will always be the namespace since it's part of the plugin.
	 * - All but the last index will be the path to the file.
	 * - The final index will be the filename.
	 */

	$file_name = '';

	if ( isset( $file_path[ count( $file_path ) - 1 ] ) ) {

		// Get the last index of the array. This is the file we're loading.
		$file_name = strtolower(
			$file_path[ count( $file_path ) - 1 ]
		);

		$file_name       = str_ireplace( '_', '-', $file_name );
		$file_name_parts = explode( '-', $file_name );

		// Use array_search() to handle both Interface|Trait_Foo or Foo_Interface|Trait.
		$interface_index = array_search( 'interface', $file_name_parts );
		$trait_index     = array_search( 'trait', $file_name_parts );

		if ( false !== $interface_index || in_array( 'Interfaces', $file_path ) ) {
			// Only drop 'interface' if not part of the namespace.
			if ( false !== $interface_index ) {
				unset( $file_name_parts[ $interface_index ] );
			}

			// Rebuild the file name.
			$file_name = implode( '-', $file_name_parts );

			$file_name = "interface-{$file_name}.php";
		} elseif ( false !== $trait_index || in_array( 'Traits', $file_path ) ) {
			// Only drop 'trait' if not part of the namespace.
			if ( false !== $trait_index ) {
				unset( $file_name_parts[ $trait_index ] );
			}

			// Rebuild the file name.
			$file_name = implode( '-', $file_name_parts );

			$file_name = "trait-{$file_name}.php";
		} else {
			$file_name = "class-$file_name.php";
		}
	}

	/*
	 * Find the fully qualified path to the class file by iterating through the $file_path array.
	 * We ignore the first index since it's always the top-level package. The last index is always
	 * the file so we append that at the end.
	 *
	 * Note: This autoloader is intended to live TWO directories deep from the root: includes/{lib}/affwp
	 */
	$fully_qualified_path = trailingslashit(
		dirname(
			dirname( __FILE__ )
		)
	);

	for ( $i = 1; $i < count( $file_path ) - 1; $i++ ) {

		$dir = strtolower( $file_path[ $i ] );
		$fully_qualified_path .= trailingslashit( $dir );
	}

	// Fix includes/REST capitalization.
	if ( false !== strpos( $fully_qualified_path, 'includes/rest/' ) ) {
		$fully_qualified_path = str_replace( 'includes/rest/', 'includes/REST/', $fully_qualified_path );
	}

	$fully_qualified_path .= $file_name;

	// Now include the file.
	if ( stream_resolve_include_path( $fully_qualified_path ) ) {
		include_once $fully_qualified_path;
	}

} );