<?php
/**
 * Database Schema Manager
 *
 * Manages database schema versioning and table registration.
 * Tracks the overall schema version separately from plugin version.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Schema Manager class.
 *
 * @since 9.11.0
 */
class MonsterInsights_DB_Schema {

	/**
	 * Schema version option name.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	const SCHEMA_VERSION_OPTION = 'monsterinsights_schema_version';

	/**
	 * Current schema version.
	 *
	 * This is set dynamically from the plugin version to support both
	 * MonsterInsights (9.x) and ExactMetrics (8.x) versions.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	const CURRENT_SCHEMA_VERSION = '1.0.0'; // Base schema version - actual version determined by get_target_version()

	/**
	 * Registered tables.
	 *
	 * @since 9.11.0
	 * @var array
	 */
	private $tables = array();

	/**
	 * Register a table.
	 *
	 * @since 9.11.0
	 * @param MonsterInsights_DB_Base $table Table instance.
	 * @return bool True on success, false on failure.
	 */
	public function register_table( $table ) {
		if ( ! $table instanceof MonsterInsights_DB_Base ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'MonsterInsights Schema: Table must extend MonsterInsights_DB_Base' );
			return false;
		}

		$table_name = $table->get_bare_table_name();
		$this->tables[ $table_name ] = $table;

		return true;
	}

	/**
	 * Get all registered tables.
	 *
	 * @since 9.11.0
	 * @return array Array of table instances.
	 */
	public function get_tables() {
		return $this->tables;
	}

	/**
	 * Get a specific registered table.
	 *
	 * @since 9.11.0
	 * @param string $table_name Table name (without prefix).
	 * @return MonsterInsights_DB_Base|null Table instance or null if not found.
	 */
	public function get_table( $table_name ) {
		return isset( $this->tables[ $table_name ] ) ? $this->tables[ $table_name ] : null;
	}

	/**
	 * Get the current schema version from database.
	 *
	 * @since 9.11.0
	 * @return string Schema version or '0.0.0' if not set.
	 */
	public function get_current_version() {
		return get_option( self::SCHEMA_VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Get the target schema version (what the code expects).
	 *
	 * Uses the plugin version constant to support both MonsterInsights (9.x)
	 * and ExactMetrics (8.x) which share the same codebase.
	 *
	 * @since 9.11.0
	 * @return string Target schema version.
	 */
	public function get_target_version() {
		// Use the actual plugin version to support both MI and EM
		if ( defined( 'MONSTERINSIGHTS_VERSION' ) ) {
			return MONSTERINSIGHTS_VERSION;
		}
		// Fallback to constant if plugin version not defined
		return self::CURRENT_SCHEMA_VERSION;
	}

	/**
	 * Check if schema needs upgrade.
	 *
	 * @since 9.11.0
	 * @return bool True if upgrade needed, false otherwise.
	 */
	public function needs_upgrade() {
		$current = $this->get_current_version();
		$target = $this->get_target_version();

		return version_compare( $current, $target, '<' );
	}

	/**
	 * Update the schema version in database.
	 *
	 * @since 9.11.0
	 * @param string $version Version to set.
	 * @return bool True on success, false on failure.
	 */
	public function update_version( $version ) {
		return update_option( self::SCHEMA_VERSION_OPTION, $version, false );
	}

	/**
	 * Get schema version history.
	 *
	 * @since 9.11.0
	 * @return array Array of version upgrade records.
	 */
	public function get_version_history() {
		return get_option( 'monsterinsights_schema_version_history', array() );
	}

	/**
	 * Record a schema version upgrade in history.
	 *
	 * @since 9.11.0
	 * @param string $from_version Version upgraded from.
	 * @param string $to_version   Version upgraded to.
	 */
	public function record_upgrade( $from_version, $to_version ) {
		$history = $this->get_version_history();

		$history[] = array(
			'from'      => $from_version,
			'to'        => $to_version,
			'timestamp' => current_time( 'mysql' ),
		);

		// Keep only last 50 upgrade records
		if ( count( $history ) > 50 ) {
			$history = array_slice( $history, -50 );
		}

		update_option( 'monsterinsights_schema_version_history', $history, false );
	}

	/**
	 * Get tables that need upgrades.
	 *
	 * @since 9.11.0
	 * @return array Array of tables that need upgrades.
	 */
	public function get_tables_needing_upgrade() {
		$needs_upgrade = array();

		foreach ( $this->tables as $table_name => $table ) {
			$current_version = $table->get_table_version();
			$target_version = $table->version;

			// If table doesn't exist or version is outdated
			if ( ! $table->table_exists() || version_compare( $current_version, $target_version, '<' ) ) {
				$needs_upgrade[ $table_name ] = array(
					'current' => $current_version,
					'target'  => $target_version,
					'exists'  => $table->table_exists(),
				);
			}
		}

		return $needs_upgrade;
	}

	/**
	 * Create or update all registered tables.
	 *
	 * @since 9.11.0
	 * @return array {
	 *     Results of table creation/update.
	 *
	 *     @type bool  $success Whether all tables were created/updated successfully.
	 *     @type array $results Array of individual table results.
	 * }
	 */
	public function create_tables() {
		$results = array();
		$success = true;

		foreach ( $this->tables as $table_name => $table ) {
			try {
				$created = $table->create_table();

				$results[ $table_name ] = array(
					'success' => $created,
					'message' => $created ? 'Table created/updated successfully' : 'Failed to create table',
				);

				if ( ! $created ) {
					$success = false;
				}
			} catch ( Exception $e ) {
				$results[ $table_name ] = array(
					'success' => false,
					'message' => 'Exception: ' . $e->getMessage(),
				);
				$success = false;
			}
		}

		return array(
			'success' => $success,
			'results' => $results,
		);
	}

	/**
	 * Get schema information for debugging.
	 *
	 * @since 9.11.0
	 * @return array Schema information.
	 */
	public function get_schema_info() {
		$tables_info = array();

		foreach ( $this->tables as $table_name => $table ) {
			$tables_info[ $table_name ] = array(
				'full_name'       => $table->get_table_name(),
				'exists'          => $table->table_exists(),
				'current_version' => $table->get_table_version(),
				'target_version'  => $table->version,
				'primary_key'     => $table->primary_key,
			);
		}

		return array(
			'schema_version' => array(
				'current' => $this->get_current_version(),
				'target'  => $this->get_target_version(),
				'needs_upgrade' => $this->needs_upgrade(),
			),
			'tables' => $tables_info,
			'history' => $this->get_version_history(),
		);
	}

	/**
	 * Reset schema version (for testing).
	 *
	 * WARNING: This will mark schema as needing upgrade.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function reset_version() {
		return delete_option( self::SCHEMA_VERSION_OPTION );
	}

	/**
	 * Verify all tables exist and are up to date.
	 *
	 * @since 9.11.0
	 * @return bool True if all tables are current, false otherwise.
	 */
	public function verify_tables() {
		foreach ( $this->tables as $table ) {
			// Check if table exists
			if ( ! $table->table_exists() ) {
				return false;
			}

			// Check if table version is current
			$current_version = $table->get_table_version();
			$target_version = $table->version;

			if ( version_compare( $current_version, $target_version, '<' ) ) {
				return false;
			}
		}

		return true;
	}
}
