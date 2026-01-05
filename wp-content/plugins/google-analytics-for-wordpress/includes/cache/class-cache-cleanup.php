<?php
/**
 * Cache Cleanup Class
 *
 * Handles automatic cleanup of expired cache entries from the custom cache table.
 * Runs daily via WP-Cron to keep the cache table optimized.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache cleanup class.
 *
 * @since 9.11.0
 */
class MonsterInsights_Cache_Cleanup {

	/**
	 * WP-Cron hook name for daily cleanup.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	const CRON_HOOK = 'monsterinsights_cache_daily_cleanup';

	/**
	 * Initialize cleanup hooks.
	 *
	 * Registers the WP-Cron action handler.
	 *
	 * @since 9.11.0
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup_expired_entries' ) );
	}

	/**
	 * Schedule daily cache cleanup.
	 *
	 * Schedules a daily WP-Cron event to clean up expired cache entries.
	 * Safe to call multiple times - won't create duplicate events.
	 *
	 * @since 9.11.0
	 * @return bool True if event was scheduled, false if already scheduled.
	 */
	public static function schedule_cleanup() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return false; // Already scheduled
		}

		return wp_schedule_event( time(), 'daily', self::CRON_HOOK );
	}

	/**
	 * Unschedule cache cleanup.
	 *
	 * Removes the scheduled cleanup event. Called on plugin deactivation.
	 *
	 * @since 9.11.0
	 * @return bool True if event was unscheduled, false otherwise.
	 */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			return wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		return false;
	}

	/**
	 * Clean up expired cache entries.
	 *
	 * Deletes all cache entries where expiration timestamp has passed.
	 * This is called by WP-Cron daily to keep the cache table clean.
	 *
	 * @since 9.11.0
	 * @return int|false Number of entries deleted, or false on error.
	 */
	public static function cleanup_expired_entries() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'monsterinsights_cache';

		// Delete all expired entries in one query
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE expires_at < %s",
				current_time( 'mysql' )
			)
		);
		// phpcs:enable

		return $deleted;
	}

	/**
	 * Get cleanup statistics.
	 *
	 * Returns counts of expired vs valid cache entries.
	 * Useful for monitoring cache health.
	 *
	 * @since 9.11.0
	 * @return array {
	 *     Cache statistics.
	 *
	 *     @type int $total_entries   Total number of cache entries.
	 *     @type int $valid_entries   Number of non-expired entries.
	 *     @type int $expired_entries Number of expired entries.
	 * }
	 */
	public static function get_cleanup_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'monsterinsights_cache';
		$now        = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$valid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE expires_at >= %s",
				$now
			)
		);
		// phpcs:enable

		return array(
			'total_entries'   => (int) $total,
			'valid_entries'   => (int) $valid,
			'expired_entries' => (int) ( $total - $valid ),
		);
	}
}

// Initialize cleanup hooks
MonsterInsights_Cache_Cleanup::init();
