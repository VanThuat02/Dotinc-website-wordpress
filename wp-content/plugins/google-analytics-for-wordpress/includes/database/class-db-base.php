<?php
/**
 * Database Base Class
 *
 * Abstract base class for all MonsterInsights database tables.
 * Provides common functionality for table operations, CRUD methods,
 * and schema management.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for database tables.
 *
 * @since 9.11.0
 */
abstract class MonsterInsights_DB_Base {

	/**
	 * The table name (without prefix).
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $table_name = '';

	/**
	 * The table version.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * The primary key column.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Get the table name (with prefix).
	 *
	 * @since 9.11.0
	 * @return string Full table name including prefix.
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->table_name;
	}

	/**
	 * Get the table name without prefix.
	 *
	 * @since 9.11.0
	 * @return string Table name without prefix.
	 */
	public function get_bare_table_name() {
		return $this->table_name;
	}

	/**
	 * Get the table schema SQL.
	 *
	 * Must be implemented by child classes.
	 *
	 * @since 9.11.0
	 * @return string CREATE TABLE SQL statement.
	 */
	abstract public function get_schema();

	/**
	 * Get array of column names and their definitions.
	 *
	 * Must be implemented by child classes.
	 *
	 * @since 9.11.0
	 * @return array Array of column definitions.
	 */
	abstract public function get_columns();

	/**
	 * Check if the table exists in the database.
	 *
	 * @since 9.11.0
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists() {
		global $wpdb;
		$table_name = $this->get_table_name();

		// Check if table exists
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $result === $table_name;
	}

	/**
	 * Check if a column exists in the table.
	 *
	 * @since 9.11.0
	 * @param string $column Column name to check.
	 * @return bool True if column exists, false otherwise.
	 */
	public function column_exists( $column ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		$result = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM `%s` LIKE %s',
				$table_name,
				$column
			)
		);

		return ! empty( $result );
	}

	/**
	 * Create the table using dbDelta.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function create_table() {
		global $wpdb;

		// Load WordPress upgrade functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Get schema SQL
		$sql = $this->get_schema();

		// Create/update table
		$result = dbDelta( $sql );

		// Verify table was created
		$created = $this->table_exists();

		// Update table version if successful
		if ( $created ) {
			$this->update_table_version( $this->version );
		}

		return $created;
	}

	/**
	 * Get the stored table version from options.
	 *
	 * @since 9.11.0
	 * @return string|false Table version or false if not set.
	 */
	public function get_table_version() {
		$option_name = 'monsterinsights_' . $this->table_name . '_version';
		return get_option( $option_name, false );
	}

	/**
	 * Update the table version in options.
	 *
	 * @since 9.11.0
	 * @param string $version Version to store.
	 * @return bool True on success, false on failure.
	 */
	public function update_table_version( $version ) {
		$option_name = 'monsterinsights_' . $this->table_name . '_version';
		return update_option( $option_name, $version, false );
	}

	/**
	 * Insert a row into the table.
	 *
	 * @since 9.11.0
	 * @param array $data Associative array of column => value pairs.
	 * @return int|false The number of rows affected, or false on error.
	 */
	public function insert( $data ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		$result = $wpdb->insert( $table_name, $data );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a row in the table.
	 *
	 * @since 9.11.0
	 * @param int   $id   Primary key value.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update( $id, $data ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		return $wpdb->update(
			$table_name,
			$data,
			array( $this->primary_key => $id )
		);
	}

	/**
	 * Delete a row from the table.
	 *
	 * @since 9.11.0
	 * @param int $id Primary key value.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete( $id ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		return $wpdb->delete(
			$table_name,
			array( $this->primary_key => $id )
		);
	}

	/**
	 * Get a single row by primary key.
	 *
	 * @since 9.11.0
	 * @param int $id Primary key value.
	 * @return object|null Row object or null if not found.
	 */
	public function get( $id ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and primary key are safely constructed.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE {$this->primary_key} = %d",
				$id
			)
		);
		// phpcs:enable
	}

	/**
	 * Get rows with optional filtering.
	 *
	 * @since 9.11.0
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type array  $where   WHERE clause conditions as column => value pairs.
	 *     @type string $orderby Column to order by.
	 *     @type string $order   ASC or DESC.
	 *     @type int    $limit   Number of rows to return.
	 *     @type int    $offset  Number of rows to skip.
	 * }
	 * @return array Array of row objects.
	 */
	public function get_rows( $args = array() ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		// Parse arguments
		$defaults = array(
			'where'   => array(),
			'orderby' => $this->primary_key,
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause
		$where_clauses = array();
		$where_values = array();

		if ( ! empty( $args['where'] ) ) {
			foreach ( $args['where'] as $column => $value ) {
				$where_clauses[] = "`{$column}` = %s";
				$where_values[] = $value;
			}
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Build ORDER BY clause
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$orderby_sql = "ORDER BY `{$args['orderby']}` {$order}";

		// Build LIMIT clause
		$limit_sql = '';
		if ( $args['limit'] > 0 ) {
			$limit_sql = $wpdb->prepare( 'LIMIT %d', $args['limit'] );

			if ( $args['offset'] > 0 ) {
				$limit_sql .= $wpdb->prepare( ' OFFSET %d', $args['offset'] );
			}
		}

		// Build final query
		$sql = "SELECT * FROM {$table_name} {$where_sql} {$orderby_sql} {$limit_sql}";

		// Prepare and execute
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is dynamically built with safe table name.
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is dynamically built with safe table name.
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count rows with optional filtering.
	 *
	 * @since 9.11.0
	 * @param array $where WHERE clause conditions as column => value pairs.
	 * @return int Number of rows.
	 */
	public function count( $where = array() ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		// Build WHERE clause
		$where_clauses = array();
		$where_values = array();

		if ( ! empty( $where ) ) {
			foreach ( $where as $column => $value ) {
				$where_clauses[] = "`{$column}` = %s";
				$where_values[] = $value;
			}
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Build and execute query
		$sql = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is dynamically built with safe table name.
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is dynamically built with safe table name.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Truncate the table (delete all rows).
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function truncate() {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		return $wpdb->query( "TRUNCATE TABLE {$table_name}" ) !== false;
	}

	/**
	 * Drop the table from the database.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function drop_table() {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		// Delete version option
		if ( $result !== false ) {
			delete_option( 'monsterinsights_' . $this->table_name . '_version' );
		}

		return $result !== false;
	}
}
