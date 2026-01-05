<?php
/**
 * Database Migration Runner
 *
 * Orchestrates execution of database migrations, tracks migration history,
 * and handles migration errors.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration Runner class.
 *
 * @since 9.11.0
 */
class MonsterInsights_Migration_Runner {

	/**
	 * Registered migrations.
	 *
	 * @since 9.11.0
	 * @var array
	 */
	private $migrations = array();

	/**
	 * Migration history option name.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	const HISTORY_OPTION = 'monsterinsights_migration_history';

	/**
	 * Migration lock option name.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	const LOCK_OPTION = 'monsterinsights_migration_lock';

	/**
	 * Lock timeout in seconds.
	 *
	 * @since 9.11.0
	 * @var int
	 */
	const LOCK_TIMEOUT = 300; // 5 minutes

	/**
	 * Register a migration.
	 *
	 * @since 9.11.0
	 * @param string $migration_class Migration class name.
	 * @return bool True on success, false if class doesn't exist.
	 */
	public function register_migration( $migration_class ) {
		if ( ! class_exists( $migration_class ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "MonsterInsights Migration Runner: Class {$migration_class} does not exist" );
			return false;
		}

		// Instantiate migration
		$migration = new $migration_class();

		// Verify it extends MonsterInsights_Migration
		if ( ! $migration instanceof MonsterInsights_Migration ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "MonsterInsights Migration Runner: {$migration_class} must extend MonsterInsights_Migration" );
			return false;
		}

		// Add to migrations array
		$version = $migration->get_version();

		// Check for duplicate version
		if ( isset( $this->migrations[ $version ] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "MonsterInsights Migration Runner: Duplicate version {$version} - {$migration_class} conflicts with existing migration" );
			return false;
		}

		$this->migrations[ $version ] = $migration;

		return true;
	}

	/**
	 * Register multiple migrations.
	 *
	 * @since 9.11.0
	 * @param array $migration_classes Array of migration class names.
	 * @return int Number of migrations successfully registered.
	 */
	public function register_migrations( $migration_classes ) {
		$count = 0;
		foreach ( $migration_classes as $migration_class ) {
			if ( $this->register_migration( $migration_class ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Get all registered migrations.
	 *
	 * @since 9.11.0
	 * @return array Array of migration objects.
	 */
	public function get_migrations() {
		return $this->migrations;
	}

	/**
	 * Get pending migrations that haven't been run yet.
	 *
	 * @since 9.11.0
	 * @return array Array of migration objects.
	 */
	public function get_pending_migrations() {
		$history = $this->get_migration_history();
		$completed = wp_list_pluck( $history, 'version' );

		$pending = array();
		foreach ( $this->migrations as $version => $migration ) {
			if ( ! in_array( $version, $completed, true ) ) {
				$pending[ $version ] = $migration;
			}
		}

		// Sort by version
		uksort( $pending, 'version_compare' );

		return $pending;
	}

	/**
	 * Check if there are pending migrations.
	 *
	 * @since 9.11.0
	 * @return bool True if migrations are pending, false otherwise.
	 */
	public function has_pending_migrations() {
		return ! empty( $this->get_pending_migrations() );
	}

	/**
	 * Run all pending migrations.
	 *
	 * @since 9.11.0
	 * @return array {
	 *     Migration results.
	 *
	 *     @type bool  $success Whether all migrations completed successfully.
	 *     @type array $results Array of individual migration results.
	 * }
	 */
	public function run_pending_migrations() {
		// Check for lock
		if ( ! $this->acquire_lock() ) {
			return array(
				'success' => false,
				'error'   => 'Migration is already running',
				'results' => array(),
			);
		}

		$pending = $this->get_pending_migrations();
		$results = array();
		$success = true;

		foreach ( $pending as $version => $migration ) {
			$result = $this->run_migration( $migration );
			$results[ $version ] = $result;

			if ( ! $result['success'] ) {
				$success = false;
				// Stop on first failure
				break;
			}
		}

		// Release lock
		$this->release_lock();

		return array(
			'success' => $success,
			'results' => $results,
		);
	}

	/**
	 * Run a single migration.
	 *
	 * @since 9.11.0
	 * @param MonsterInsights_Migration $migration Migration instance.
	 * @return array {
	 *     Migration result.
	 *
	 *     @type bool   $success Whether migration completed successfully.
	 *     @type string $error   Error message if failed.
	 *     @type float  $duration Time taken in seconds.
	 * }
	 */
	public function run_migration( $migration ) {
		$version = $migration->get_version();
		$description = $migration->get_description();
		$start_time = microtime( true );

		// Log start
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "MonsterInsights: Running migration {$version}: {$description}" );

		try {
			// Run the migration
			$migration->up();

			// Calculate duration
			$duration = microtime( true ) - $start_time;

			// Record in history
			$this->record_migration( $version, 'completed', $duration );

			// Log success
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'MonsterInsights: Migration %s completed successfully in %.2f seconds',
				$version,
				$duration
			) );

			return array(
				'success'  => true,
				'duration' => $duration,
			);

		} catch ( Exception $e ) {
			// Calculate duration
			$duration = microtime( true ) - $start_time;

			// Record failure in history
			$this->record_migration( $version, 'failed', $duration, $e->getMessage() );

			// Log error
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'MonsterInsights: Migration %s failed after %.2f seconds: %s',
				$version,
				$duration,
				$e->getMessage()
			) );

			return array(
				'success'  => false,
				'error'    => $e->getMessage(),
				'duration' => $duration,
			);
		}
	}

	/**
	 * Get migration history.
	 *
	 * @since 9.11.0
	 * @return array Array of migration history entries.
	 */
	public function get_migration_history() {
		return get_option( self::HISTORY_OPTION, array() );
	}

	/**
	 * Record a migration in the history.
	 *
	 * @since 9.11.0
	 * @param string $version  Migration version.
	 * @param string $status   Status (completed, failed).
	 * @param float  $duration Time taken in seconds.
	 * @param string $error    Error message if failed.
	 */
	private function record_migration( $version, $status, $duration, $error = '' ) {
		$history = $this->get_migration_history();

		$history[] = array(
			'version'   => $version,
			'status'    => $status,
			'duration'  => $duration,
			'error'     => $error,
			'timestamp' => current_time( 'mysql' ),
		);

		update_option( self::HISTORY_OPTION, $history, false );
	}

	/**
	 * Clear migration history.
	 *
	 * Useful for testing or troubleshooting.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function clear_history() {
		return delete_option( self::HISTORY_OPTION );
	}

	/**
	 * Acquire migration lock to prevent concurrent runs.
	 *
	 * @since 9.11.0
	 * @return bool True if lock acquired, false if already locked.
	 */
	private function acquire_lock() {
		$lock = get_option( self::LOCK_OPTION );

		// Check if lock exists and is still valid
		if ( $lock && ( time() - $lock ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		// Set new lock
		update_option( self::LOCK_OPTION, time(), false );
		return true;
	}

	/**
	 * Release migration lock.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	private function release_lock() {
		return delete_option( self::LOCK_OPTION );
	}

	/**
	 * Force release migration lock.
	 *
	 * Useful if migration process dies and lock isn't released.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function force_release_lock() {
		return $this->release_lock();
	}

	/**
	 * Check if migrations are currently locked.
	 *
	 * @since 9.11.0
	 * @return bool True if locked, false otherwise.
	 */
	public function is_locked() {
		$lock = get_option( self::LOCK_OPTION );

		if ( ! $lock ) {
			return false;
		}

		// Check if lock has expired
		if ( ( time() - $lock ) >= self::LOCK_TIMEOUT ) {
			// Lock expired, release it
			$this->release_lock();
			return false;
		}

		return true;
	}

	/**
	 * Get migration statistics.
	 *
	 * @since 9.11.0
	 * @return array {
	 *     Migration statistics.
	 *
	 *     @type int $total_migrations   Total registered migrations.
	 *     @type int $pending_migrations Number of pending migrations.
	 *     @type int $completed_migrations Number of completed migrations.
	 *     @type int $failed_migrations  Number of failed migrations.
	 * }
	 */
	public function get_statistics() {
		$history = $this->get_migration_history();
		$pending = $this->get_pending_migrations();

		$completed = 0;
		$failed = 0;

		foreach ( $history as $entry ) {
			if ( $entry['status'] === 'completed' ) {
				$completed++;
			} elseif ( $entry['status'] === 'failed' ) {
				$failed++;
			}
		}

		return array(
			'total_migrations'     => count( $this->migrations ),
			'pending_migrations'   => count( $pending ),
			'completed_migrations' => $completed,
			'failed_migrations'    => $failed,
		);
	}
}
