<?php
/**
 * Cache Cron Handler
 *
 * Ensures the cache cleanup cron job is scheduled for both new installs
 * and existing users updating the plugin. This file is loaded on every
 * request to ensure the cron is always scheduled when it should be.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure cache cleanup cron is scheduled.
 *
 * This function runs on plugins_loaded to check if the cache cleanup
 * cron job is scheduled. If not, it schedules it. This ensures that:
 * 1. Fresh installs get the cron scheduled
 * 2. Existing users updating to this version get the cron scheduled
 * 3. If the cron somehow gets unscheduled, it will be re-scheduled
 *
 * This approach is used by major plugins like WooCommerce and Yoast SEO.
 *
 * @since 9.11.0
 * @return void
 */
function monsterinsights_ensure_cache_cleanup_scheduled() {
	// Only run in admin or cron context to avoid unnecessary checks on frontend
	if ( ! is_admin() && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) ) {
		return;
	}

	// Check if cleanup is already scheduled
	if ( ! wp_next_scheduled( 'monsterinsights_cache_daily_cleanup' ) ) {
		monsterinsights_schedule_cache_cleanup();
	}
}

// Hook into plugins_loaded with priority 20 to ensure cache functions are loaded first
add_action( 'plugins_loaded', 'monsterinsights_ensure_cache_cleanup_scheduled', 20 );
