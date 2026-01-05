<?php
/**
 * Migration: Create Cache Table (9.11.0)
 *
 * Creates the custom cache table for improved caching performance.
 * This migration moves cache storage from wp_options to a dedicated table.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create Cache Table Migration class.
 *
 * @since 9.11.0
 */
class MonsterInsights_Migration_9110_Cache_Table extends MonsterInsights_Migration {

	/**
	 * Initialize hooks for cache cleanup.
	 *
	 * This registers the WP-Cron action that processes cache cleanup batches.
	 * Called automatically when the class is loaded.
	 *
	 * @since 9.11.0
	 */
	public static function init_hooks() {
		add_action( 'monsterinsights_cleanup_old_cache_batch', array( __CLASS__, 'process_cache_cleanup_batch' ) );
	}

	/**
	 * Migration version.
	 *
	 * Uses a version-agnostic identifier to support both MonsterInsights (9.x)
	 * and ExactMetrics (8.x) which share the same codebase and database tables.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $version = '1.0.0-cache-table';

	/**
	 * Migration description.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $description = 'Create custom cache table for improved performance';

	/**
	 * Run the migration.
	 *
	 * Creates the monsterinsights_cache table and migrates existing cache data
	 * from wp_options to the new table.
	 *
	 * @since 9.11.0
	 * @throws Exception If table creation fails.
	 */
	public function up() {
		// Instantiate cache table (already loaded by loader.php)
		$cache_table = new MonsterInsights_Cache_Table();

		// Check if table already exists
		if ( $cache_table->table_exists() ) {
			$this->log_info( 'Cache table already exists, skipping creation' );
			return;
		}

		$this->log_info( 'Creating cache table' );

		// Create the table
		$created = $cache_table->create_table();

		if ( ! $created ) {
			throw new Exception( 'Failed to create cache table' );
		}

		// Verify table was created
		if ( ! $cache_table->table_exists() ) {
			throw new Exception( 'Cache table does not exist after creation attempt' );
		}

		$this->log_success( 'Cache table created successfully' );

		// Schedule cleanup of old cache data from wp_options
		$this->schedule_old_cache_cleanup();

		$this->log_success( 'Cache table migration completed' );
	}

	/**
	 * Schedule cleanup of old cache data from wp_options.
	 *
	 * Instead of migrating cache (which has key format mismatches), we simply
	 * schedule background cleanup of old cache entries. This allows the new
	 * cache system to start fresh with correct key formats.
	 *
	 * Cleanup is done in batches to avoid performance issues on sites with
	 * large amounts of cached data.
	 *
	 * @since 9.11.0
	 */
	private function schedule_old_cache_cleanup() {
		global $wpdb;

		$this->log_info( 'Checking for old cache data to clean up' );

		// Count existing cache entries
		$count = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->options}
			WHERE option_name LIKE 'monsterinsights_report_data_%'
			OR option_name LIKE '_transient_monsterinsights_report_%'
			OR option_name LIKE '_transient_timeout_monsterinsights_report_%'"
		);

		// Check for database errors
		if ( $count === null ) {
			$this->log_error( 'Database error while counting old cache entries: ' . $wpdb->last_error );
			// Schedule cleanup anyway - worst case it finds nothing
			$count = 0;
		}

		$count = (int) $count;

		if ( $count === 0 ) {
			$this->log_info( 'No old cache data found to clean up' );
			return;
		}

		$this->log_info( sprintf( 'Found %d old cache entries to clean up', $count ) );

		// Store cleanup progress
		update_option( 'monsterinsights_cache_cleanup_total', $count, false );
		update_option( 'monsterinsights_cache_cleanup_processed', 0, false );
		update_option( 'monsterinsights_cache_cleanup_status', 'scheduled', false );

		// Schedule first batch via WP-Cron
		if ( ! wp_next_scheduled( 'monsterinsights_cleanup_old_cache_batch' ) ) {
			wp_schedule_single_event( time() + 10, 'monsterinsights_cleanup_old_cache_batch' );
			$this->log_success( 'Scheduled cleanup of old cache entries in background' );
		}
	}

	/**
	 * Process a batch of old cache cleanup.
	 *
	 * This is called by WP-Cron and processes a batch of old cache entries.
	 * It schedules itself again if there are more entries to process.
	 *
	 * @since 9.11.0
	 * @return void
	 */
	public static function process_cache_cleanup_batch() {
		global $wpdb;

		$batch_size = 100; // Process 100 entries per batch

		// Get current status
		$status = get_option( 'monsterinsights_cache_cleanup_status', 'completed' );

		if ( $status === 'completed' ) {
			return; // Already done
		}

		// Get batch of old cache entries
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name
				FROM {$wpdb->options}
				WHERE option_name LIKE 'monsterinsights_report_data_%%'
				OR option_name LIKE '_transient_monsterinsights_report_%%'
				OR option_name LIKE '_transient_timeout_monsterinsights_report_%%'
				LIMIT %d",
				$batch_size
			)
		);

		if ( empty( $results ) ) {
			// Cleanup complete
			update_option( 'monsterinsights_cache_cleanup_status', 'completed', false );
			delete_option( 'monsterinsights_cache_cleanup_total' );
			delete_option( 'monsterinsights_cache_cleanup_processed' );
			return;
		}

		// Delete this batch
		$deleted = 0;
		foreach ( $results as $row ) {
			if ( delete_option( $row->option_name ) ) {
				$deleted++;
			}
		}

		// Update progress
		$processed = (int) get_option( 'monsterinsights_cache_cleanup_processed', 0 );
		update_option( 'monsterinsights_cache_cleanup_processed', $processed + $deleted, false );

		// Schedule next batch if there are more entries
		if ( count( $results ) === $batch_size ) {
			// Likely more entries, schedule next batch
			wp_schedule_single_event( time() + 5, 'monsterinsights_cleanup_old_cache_batch' );
		} else {
			// This was the last batch
			update_option( 'monsterinsights_cache_cleanup_status', 'completed', false );
			delete_option( 'monsterinsights_cache_cleanup_total' );
			delete_option( 'monsterinsights_cache_cleanup_processed' );
		}
	}
}

// Initialize hooks
MonsterInsights_Migration_9110_Cache_Table::init_hooks();
