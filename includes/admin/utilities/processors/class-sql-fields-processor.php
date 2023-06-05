<?php
/**
 * Utilities: SQL Fields Processor
 *
 * @package     AffiliateWP
 * @subpackage  Utilities
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

namespace AffWP\Utils\Processors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class to make processing SQL fields (for operations such as complex joins) simpler.
 *
 * @since 2.5
 */
final class SQL_Fields_Processor {

	/**
	 * Primary table.
	 *
	 * @since 2.5
	 *
	 * @var \Affiliate_WP_DB
	 */
	public $table = '';

	/**
	 * Joined tables.
	 *
	 * @since 2.5
	 *
	 * @var array
	 */
	protected $joined_tables = array();

	/**
	 * List of whitelisted joined fields.
	 *
	 * @since 2.5
	 *
	 * @var array
	 */
	protected $joined_fields = array();

	/**
	 * List of prepared fields.
	 *
	 * @since 2.5
	 *
	 * @var array
	 */
	protected $prepared_fields = array();

	/**
	 * List of whitelisted primary table fields.
	 *
	 * @since 2.5
	 * @var array
	 */
	protected $table_fields = array();

	/**
	 * Whitelist of supported MYSQL functions.
	 *
	 * @since 2.5
	 *
	 * @var array
	 */
	private static $supported_sql_functions = array( 'sum' );

	/**
	 * SQL_Fields_Processor constructor.
	 *
	 * @since 2.5
	 *
	 * @param string $table         Primary table identifier. Subclasses use semicolons.
	 * @param array  $fields        Optional. List of fields to process. Default empty array.
	 * @param array  $joined_tables Optional. List of table identifiers that can be merged using a JOIN statement in this
	 *                              query. Default empty array.
	 */
	public function __construct( $table, $fields = array(), $joined_tables = array() ) {

		if ( ! is_array( $joined_tables ) ) {
			$joined_tables = array( $joined_tables );
		}

		// Construct the array of joined tables.
		foreach ( $joined_tables as $joined_table ) {
			$this->joined_tables[ $joined_table ] = $this->get_table( $joined_table );
		}

		// Get the table object
		$this->table = $this->get_table( $table );

		// Prepare the fields
		$this->add_fields( $fields );
	}

	/**
	 * Returns true if the specified table is using any fields in this query.
	 *
	 * @since 2.5
	 *
	 * @param bool $table Optional. The table to check. If left empty, this will check the primary table.
	 * @return bool True if the table has fields in this query. False otherwise.
	 */
	public function table_has_fields( $table = '' ) {
		$fields = $this->get_table_fields( $table );

		return ! empty( $fields );
	}

	/**
	 * Returns true if the field exists in the table.
	 *
	 * @since 2.5
	 *
	 * @param string                  $field The name of the field to look for.
	 * @param string|\Affiliate_WP_DB $table The table name or table object.
	 * @return bool True if the table has the specified field. Otherwise, false.
	 */
	public function table_has_field( $field, $table = '' ) {
		$fields = $this->get_table_fields( $table );

		return in_array( $field, $fields );
	}

	/**
	 * Retrieves the database table object from the provided table name.
	 *
	 * Constructs items based on the object structure of AffiliateWP. Uses semicolons to separate sub-table values.
	 * For example, if you want to get the sales table object, you would use referrals:sales.
	 *
	 * @param string|\Affiliate_WP_DB $table Optional. The table to retrieve. Defaults to the primary table.
	 * @return \Affiliate_WP_DB|\WP_Error The table object if found. \WP_Error if something went wrong.
	 */
	public function get_table( $table = '' ) {

		if ( empty( $table ) ) {
			$table = $this->table;
		}

		// Bail early if the table is an error.
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		// If the table is already an instance of Affiliate_WP_DB, confirm the instance is valid.
		if ( affwp_is_db( $table ) ) {
			return $table;
		}

		// If we already constructed the joined table, return that table.
		if ( in_array( $table, $this->joined_tables ) && affwp_is_db( $this->joined_tables[ $table ] ) ) {
			return $this->joined_tables[ $table ];
		}

		// convert table:subtable:subtable syntax to array.
		$table_structure = explode( ':', $table );
		$result          = affiliate_wp();

		foreach ( $table_structure as $table ) {
			if ( isset( $result->$table ) && affwp_is_db( $result->$table ) ) {
				$result = $result->$table;
			} else {
				$result = false;
				break;
			}
		}

		if ( ! affwp_is_db( $result ) ) {
			$result = new \WP_Error(
				'get_table_failed',
				'Failed to retrieve table object.',
				array( 'table_structure' => $table_structure, 'failed_at' => $table )
			);
		}

		return $result;
	}

	/**
	 * Retrieves the database object that uses the specified field.
	 *
	 * @since 2.5
	 *
	 * @param string $field The field name to retrieve the table from.
	 * @return \Affiliate_WP_DB|\WP_Error Array containing the object and the table name. Otherwise \WP_Error.
	 */
	public function get_table_for_field( $field ) {

		// If the primary table has this field, return the primary table.
		if ( $this->table_has_field( $field ) ) {
			return $this->table;
		}

		// Otherwise, loop through joined tables and find the table.
		foreach ( $this->joined_tables as $table => $table_object ) {
			if ( $this->table_has_field( $field, $table_object ) ) {
				return $table_object;
			}
		}

		// If all-else fails, return a WP Error.
		return new \WP_Error(
			'get_table_for_field_not_found',
			'The specified field could not be found in any table.',
			array( 'field' => $field )
		);
	}

	/**
	 * Organizes a list of fields into the table in which they belong.
	 *
	 * @since 2.5
	 *
	 * @param array $fields List of fields to organize.
	 * @return array list of fields that were added.
	 */
	public function add_fields( $fields ) {

		$added_fields = array();

		// If fields is not an array, convert it to one.
		if ( ! is_array( $fields ) ) {
			$fields = array( $fields );
		}

		// Loop through each field, and find which table it belongs.
		foreach ( $fields as $field ) {

			// If this column exists in the current database schema, append it.
			$added = $this->add_field( $field );

			// If this field was added, append it to the list of added fields.
			if(true === $added){
				$added_fields[] = $field;

			// Otherwise, search the joined tables for the field.
			}else{
				foreach ( $this->joined_tables as $table_name => $table_object ) {
					$added = $this->add_field( $field, $table_name );

					// Break out of the loop if the joined table successfully added the field.
					if ( true === $added ) {
						$added_fields[] = $field;
						break;
					}
				}
			}
		}

		return $added_fields;
	}

	/**
	 * Adds a single field to this query, if it is in the table's whitelist.
	 *
	 * @since 2.5
	 *
	 * @param string $field The field to add to the query.
	 * @param string $table Optional. The table to add the field to. Defaults to the primary table.
	 * @return true|\WP_Error True if the field was added successfully. \WP_Error if something went wrong.
	 */
	public function add_field( $field, $table = '' ) {
		// Get the table.
		$table  = $this->get_table( $table );
		$result = false;

		// Bail early if the table object could not be retrieved.
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		// Bail early if this table was not joined in the constructor.
		if ( ! $this->is_joined_table( $table ) && ! $this->is_primary_table( $table ) ) {
			return new \WP_Error(
				'table_is_not_joined',
				'The provided table exists, but was not joined in the constructor.',
				array( 'table_group' => $table->db_group, 'joined_tables' => $this->joined_tables )
			);
		}

		$columns = $table->get_all_columns();

		// If the table supports this column.
		if ( isset( $columns[ $field ] ) ) {

			// If a table was not specified, this is the primary table.
			if ( $this->is_primary_table( $table ) ) {
				$this->table_fields[] = $field;

			// Otherwise, this is a joined table.
			} else {
				$this->joined_fields[ $table->db_group ][] = $field;
			}

			// Success, return true.
			$result = true;
		}

		// If successful, add this field to the list of prepared fields.
		if ( true === $result ) {
			$prepared_field = $this->prepare_field( $field, $table );

			if ( ! is_wp_error( $prepared_field ) ) {
				$this->prepared_fields[ $field ] = $prepared_field;
			}
		} else {
			$result = new \WP_Error(
				'field_does_not_exist',
				'The specified field could not be found.',
				array( 'table' => $table->db_group, 'fields' => $this->get_table_fields( $table ) )
			);
		}

		return $result;
	}

	/**
	 * Prepares a column for use in an SQL query.
	 *
	 * @since 2.5
	 *
	 * @param string $field The field to use to prepare this column.
	 * @param string $table Optional. The table to use when preparing this field.
	 *                      If no table is specified, prepare_field will attempt to find the table from the field value.
	 * @return string|\WP_Error an SQL-ready reference to this column if the column was found. Otherwise \WP_Error.
	 */
	public function prepare_field( $field, $table = '' ) {

		// If a table wasn't specified, attempt to find the table from the specified columns.
		if ( empty( $table ) ) {
			$table_object = $this->get_table_for_field( $field );

			// Otherwise, just get the table.
		} else {
			$table_object = $this->get_table( $table );
		}

		// If something went wrong, bubble the error.
		if ( is_wp_error( $table_object ) ) {
			return $table_object;
		}

		if ( ! $this->is_joined_table( $table ) && ! $this->is_primary_table( $table ) ) {
			return new \WP_Error(
				'prepare_field_table_invalid',
				'Field could not be prepared because the specified table was not added to the processor.',
				array( 'field' => $field, 'table' => $table_object->db_group )
			);
		}

		$columns = $table_object->get_all_columns();

		// Bail If this table does not have the specified field.
		if ( ! isset( $columns[ $field ] ) ) {
			return new \WP_Error(
				'prepare_field_table_field_invalid',
				'Field could not be prepared because the specified field is not in the table',
				array( 'field' => $field, 'table' => $table_object->db_group )
			);
		}

		return $this->prepend( $field, $table_object );
	}

	/**
	 * Parses a string of one or more valid object fields into a SQL-friendly format.
	 *
	 * @since 2.5
	 *
	 * @param array $function_fields List of additional function fields to retrieve, keyed by the SQL function.
	 * @return string SQL-ready fields list. If empty, default is '*'.
	 */
	public function parse_fields( $function_fields = array() ) {

		$fields_sql = "";

		// Start by getting the primary fields
		$fields = $this->prepared_fields;

		// Merge SQL function fields.
		foreach ( $function_fields as $function => $sql_function_fields ) {

			// If we have function fields, merge them.
			if ( ! empty( $sql_function_fields ) ) {
				$fields = array_merge( $fields, $this->prepend_function_fields( $sql_function_fields, $function ) );
			}
		}

		if ( ! empty( $fields ) ) {
			// Format the fields for SQL.
			$fields_sql = implode( ', ', $fields );
		}

		// Set to wildcard if no fields were found.
		if ( empty ( $fields_sql ) ) {
			$fields_sql = '*';
		}

		return $fields_sql;
	}

	/**
	 * Formats fields to use the provided SQL function.
	 *
	 * @since 2.5
	 *
	 * @param array  $fields       List of function fields to use.
	 * @param string $sql_function Supported SQL function name, such as sum.
	 * @return array List of SQL-ready function call field statements.
	 */
	public function prepend_function_fields( $fields, $sql_function ) {
		$prepended_fields = array();
		$sql_function     = strtolower( $sql_function );

		if ( in_array( $sql_function, self::$supported_sql_functions ) ) {
			// Add this SQL function's fields.
			$this->add_fields( $fields );

			foreach ( $fields as $field ) {
				$field_name = $field . '_' . $sql_function;

				// Add the function field, if it is valid.
				$table_class = $this->get_table_for_field( $field );

				// Skip this if something went wrong.
				if ( is_wp_error( $table_class ) ) {
					continue;
				}

				// Add this function field to the query.
				$added = $this->add_field( $field_name, $table_class->db_group );

				if ( ! is_wp_error( $added ) ) {
					$field              = $this->prepend( $field, $table_class );
					$prepended_fields[] = strtoupper( $sql_function ) . '(' . $field . ') as ' . $field_name;
				}
			}
		}

		return $prepended_fields;
	}

	/**
	 * Retrieve the fields for the specified table.
	 *
	 * @since 2.5
	 *
	 * @param string|\Affiliate_WP_DB $table The table name or table object.
	 * @return array List of table fields for the specified table.
	 */
	public function get_table_fields( $table = '' ) {
		// If this is the primary table, get the table fields.
		if ( $this->is_primary_table( $table ) ) {
			return $this->table_fields;
		}

		// If this is a joined table, get the fields.
		if ( $this->is_joined_table( $table ) ) {
			$table = $this->get_table( $table );

			return isset( $this->joined_fields[ $table->db_group ] ) ? $this->joined_fields[ $table->db_group ] : array();
		}

		// If all else fails, return an empty array
		return array();
	}

	/**
	 * Checks if the specified table is the primary table.
	 *
	 * @since 2.5
	 *
	 * @param string|\Affiliate_WP_DB $table The table name or table object.
	 * @return bool True if this is the primary table. False, otherwise.
	 */
	public function is_primary_table( $table ) {
		$table = $this->get_table( $table );

		// Return true if the table is valid, and the db group matches the primary table db group.
		return ! is_wp_error( $table ) && $table->db_group === $this->table->db_group;
	}

	/**
	 * Checks if the specified table is a joined table.
	 *
	 * @since 2.5
	 *
	 * @param string|\Affiliate_WP_DB $table The table name or table object.
	 * @return bool True if this is a joined table. False, otherwise.
	 */
	public function is_joined_table( $table ) {
		$table = $this->get_table( $table );

		// Return true if the table is valid and the db group is in the list of joined tables.
		return ! is_wp_error( $table ) && isset( $this->joined_tables[ $table->db_group ] );
	}

	/**
	 * Prepends a table name to the specified column.
	 *
	 * Prevents conflicts in situations where joined tables share fields with the same names.
	 *
	 * @since 2.5
	 *
	 * @param string                  $column The database column to prepend.
	 * @param string|\Affiliate_WP_DB $table  Optional. The table in which the column belongs. Default current table.
	 * @return string|\WP_Error an unambiguous column name, with the table name appended to the column.
	 */
	public function prepend( $column, $table = '' ) {

		// If this column has already been prepended, use it as-is.
		if ( false !== strpos( $column, '.' ) ) {
			return $column;
		}

		$table_object = $this->get_table( $table );

		// Bail early if the table is an error.
		if ( is_wp_error( $table_object ) ) {
			return $table_object;
		}

		$table_name = $table_object->table_name;
		$column     = "${table_name}.${column}";

		return $column;
	}
}