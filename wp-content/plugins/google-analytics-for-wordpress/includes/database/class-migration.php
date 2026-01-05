<?php
/**
 * Database Migration Base Class
 *
 * Abstract base class for all MonsterInsights database migrations.
 * Provides common functionality for schema changes and data migrations.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for database migrations.
 *
 * @since 9.11.0
 */
abstract class MonsterInsights_Migration {

	/**
	 * The migration version.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $version = '';

	/**
	 * Human-readable description of what this migration does.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $description = '';

	/**
	 * Run the migration.
	 *
	 * Must be implemented by child classes.
	 *
	 * @since 9.11.0
	 * @throws Exception If migration fails.
	 */
	abstract public function up();

	/**
	 * Get the migration version.
	 *
	 * @since 9.11.0
	 * @return string Migration version.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get the migration description.
	 *
	 * @since 9.11.0
	 * @return string Migration description.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Check if a table exists in the database.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (with or without prefix).
	 * @return bool True if table exists, false otherwise.
	 */
	protected function table_exists( $table_name ) {
		global $wpdb;

		// Add prefix if not already present
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $result === $table_name;
	}

	/**
	 * Check if a column exists in a table.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (with or without prefix).
	 * @param string $column     Column name.
	 * @return bool True if column exists, false otherwise.
	 */
	protected function column_exists( $table_name, $column ) {
		global $wpdb;

		// Add prefix if not already present
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				$column
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return ! empty( $result );
	}

	/**
	 * Check if an index exists on a table.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (with or without prefix).
	 * @param string $index_name Index name.
	 * @return bool True if index exists, false otherwise.
	 */
	protected function index_exists( $table_name, $index_name ) {
		global $wpdb;

		// Add prefix if not already present
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
				$index_name
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return ! empty( $result );
	}

	/**
	 * Add a column to an existing table.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (with or without prefix).
	 * @param string $column     Column name.
	 * @param string $definition Column definition (e.g., 'VARCHAR(255) NOT NULL').
	 * @return bool True on success, false on failure.
	 */
	protected function add_column( $table_name, $column, $definition ) {
		global $wpdb;

		// Add prefix if not already present
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		// Check if column already exists
		if ( $this->column_exists( $table_name, $column ) ) {
			$this->log_info( "Column {$column} already exists in {$table_name}" );
			return true;
		}

		// Add the column
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, column, and definition are safely constructed.
		$result = $wpdb->query(
			"ALTER TABLE `{$table_name}` ADD COLUMN `{$column}` {$definition}"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $result === false ) {
			$this->log_error( "Failed to add column {$column} to {$table_name}: " . $wpdb->last_error );
			return false;
		}

		$this->log_success( "Added column {$column} to {$table_name}" );
		return true;
	}

	/**
	 * Modify an existing column.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (with or without prefix).
	 * @param string $column     Column name.
	 * @param string $definition New column definition.
	 * @return bool True on success, false on failure.
	 */
	protected function modify_column( $table_name, $column, $definition ) {
		global $wpdb;

		// Add prefix if not already present
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		// Check if column exists
		if ( ! $this->column_exists( $table_name, $column ) ) {
			$this->log_error( "Column {$column} does not exist in {$table_name}" );
			return false;
		}

		// Modify the column
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, column, and definition are safely constructed.
		$result = $wpdb->query(
			"ALTER TABLE `{$table_name}` MODIFY COLUMN `{$column}` {$definition}"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $result === false ) {
			$this->log_error( "Failed to modify column {$column} in {$table_name}: " . $wpdb->last_error );
			return false;
		}

		$this->log_success( "Modified column {$column} in {$table_name}" );
		return true;
	}

	/**
	 * Add an index to a table.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (with or without prefix).
	 * @param string $index_name Index name.
	 * @param array  $columns    Column names to index.
	 * @param string $type       Index type (INDEX, UNIQUE, FULLTEXT). Default 'INDEX'.
	 * @return bool True on success, false on failure.
	 */
	protected function add_index( $table_name, $index_name, $columns, $type = 'INDEX' ) {
		global $wpdb;

		// Add prefix if not already present
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		// Check if index already exists
		if ( $this->index_exists( $table_name, $index_name ) ) {
			$this->log_info( "Index {$index_name} already exists on {$table_name}" );
			return true;
		}

		// Build column list
		$column_list = '`' . implode( '`, `', $columns ) . '`';

		// Add the index
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, index name, type, and columns are safely constructed.
		$result = $wpdb->query(
			"ALTER TABLE `{$table_name}` ADD {$type} `{$index_name}` ({$column_list})"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $result === false ) {
			$this->log_error( "Failed to add index {$index_name} to {$table_name}: " . $wpdb->last_error );
			return false;
		}

		$this->log_success( "Added index {$index_name} to {$table_name}" );
		return true;
	}

	/**
	 * Drop an index from a table.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (with or without prefix).
	 * @param string $index_name Index name.
	 * @return bool True on success, false on failure.
	 */
	protected function drop_index( $table_name, $index_name ) {
		global $wpdb;

		// Add prefix if not already present
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		// Check if index exists
		if ( ! $this->index_exists( $table_name, $index_name ) ) {
			$this->log_info( "Index {$index_name} does not exist on {$table_name}" );
			return true;
		}

		// Drop the index
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and index name are safely constructed.
		$result = $wpdb->query(
			"ALTER TABLE `{$table_name}` DROP INDEX `{$index_name}`"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $result === false ) {
			$this->log_error( "Failed to drop index {$index_name} from {$table_name}: " . $wpdb->last_error );
			return false;
		}

		$this->log_success( "Dropped index {$index_name} from {$table_name}" );
		return true;
	}

	/**
	 * Log a success message.
	 *
	 * @since 9.11.0
	 * @param string $message Message to log.
	 */
	protected function log_success( $message ) {
		$this->log_message( 'success', $message );
	}

	/**
	 * Log an info message.
	 *
	 * @since 9.11.0
	 * @param string $message Message to log.
	 */
	protected function log_info( $message ) {
		$this->log_message( 'info', $message );
	}

	/**
	 * Log an error message.
	 *
	 * @since 9.11.0
	 * @param string $message Message to log.
	 */
	protected function log_error( $message ) {
		$this->log_message( 'error', $message );
	}

	/**
	 * Log a message to the migration log.
	 *
	 * @since 9.11.0
	 * @param string $level   Log level (success, info, error).
	 * @param string $message Message to log.
	 */
	private function log_message( $level, $message ) {
		// Get current migration log
		$log = get_option( 'monsterinsights_migration_log', array() );

		// Add new log entry
		$log[] = array(
			'migration' => $this->version,
			'level'     => $level,
			'message'   => $message,
			'timestamp' => current_time( 'mysql' ),
		);

		// Keep only last 1000 log entries
		if ( count( $log ) > 1000 ) {
			$log = array_slice( $log, -1000 );
		}

		// Save log
		update_option( 'monsterinsights_migration_log', $log, false );

		// Also log to debug.log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "MonsterInsights Migration [{$this->version}] [{$level}]: {$message}" );
		}
	}
}
