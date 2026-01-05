<?php
/**
 * Migration: Remove Per-User Cache Data (9.11.0)
 *
 * Cleans up old per-user cache metadata since cache system now uses
 * site-wide cache with metrics hash in the key (Phase 1.5).
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove Per-User Cache Migration class.
 *
 * @since 9.11.0
 */
class MonsterInsights_Migration_9110_Remove_Per_User_Cache extends MonsterInsights_Migration {

	/**
	 * Migration version.
	 *
	 * Uses a version-agnostic identifier to support both MonsterInsights (9.x)
	 * and ExactMetrics (8.x) which share the same codebase and database tables.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $version = '1.0.1-remove-per-user-cache';

	/**
	 * Migration description.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $description = 'Remove per-user cache metadata (switched to site-wide cache)';

	/**
	 * Run migration up.
	 *
	 * @since 9.11.0
	 * @return bool
	 */
	public function up() {
		global $wpdb;

		$this->log_info( 'Starting per-user cache cleanup' );

		// Clean up old per-user cache tracking user meta
		// These are no longer needed since metrics are now part of the cache key
		$deleted_meta = $wpdb->query(
			"DELETE FROM {$wpdb->usermeta}
			WHERE meta_key LIKE 'monsterinsights_previous_included_metrics_%'"
		);

		if ( $deleted_meta !== false ) {
			$this->log_success( sprintf( 'Removed %d per-user cache tracking entries from user meta', $deleted_meta ) );
		} else {
			$this->log_warning( 'Could not remove per-user cache tracking entries: ' . $wpdb->last_error );
		}

		$this->log_success( 'Per-user cache cleanup completed' );

		return true;
	}

	/**
	 * Run migration down (rollback).
	 *
	 * @since 9.11.0
	 * @return bool
	 */
	public function down() {
		// Nothing to rollback - user meta deletion is intentional
		// The monsterinsights_included_metrics user meta is preserved (still in use)
		$this->log_info( 'No rollback needed for per-user cache cleanup' );
		return true;
	}
}
