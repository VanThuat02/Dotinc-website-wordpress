<?php
/**
 * Cache Wrapper Class
 *
 * Provides unified caching interface with automatic fallback from object cache
 * (Redis/Memcached) to custom cache table.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache Wrapper class.
 *
 * @since 9.11.0
 */
class MonsterInsights_Cache_Wrapper {

	/**
	 * Cache table instance.
	 *
	 * @since 9.11.0
	 * @var MonsterInsights_Cache_Table
	 */
	private $cache_table;

	/**
	 * Constructor.
	 *
	 * @since 9.11.0
	 */
	public function __construct() {
		// Load cache table class if not already loaded
		if ( ! class_exists( 'MonsterInsights_Cache_Table' ) ) {
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/database/tables/class-cache-table.php';
		}

		$this->cache_table = new MonsterInsights_Cache_Table();
	}

	/**
	 * Check if persistent object cache is available.
	 *
	 * @since 9.11.0
	 * @return bool True if object cache available, false otherwise.
	 */
	public function has_object_cache() {
		global $_wp_using_ext_object_cache;
		return (bool) $_wp_using_ext_object_cache;
	}

	/**
	 * Get cached data.
	 *
	 * Tries object cache first (Redis/Memcached), then falls back to cache table.
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'reports').
	 * @return mixed|false Cached data or false if not found.
	 */
	public function get( $key, $group = 'reports' ) {
		// Try object cache first
		if ( $this->has_object_cache() ) {
			$cached = wp_cache_get( $key, $group );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Fallback to cache table
		return $this->cache_table->get_cache( $key, $group );
	}

	/**
	 * Set cached data.
	 *
	 * Stores in both object cache (if available) and cache table for redundancy.
	 *
	 * @since 9.11.0
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param string $group      Cache group (default 'reports').
	 * @param int    $expiration Expiration in seconds (default 3600).
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $group = 'reports', $expiration = 3600 ) {
		$success = true;

		// Store in object cache if available
		if ( $this->has_object_cache() ) {
			wp_cache_set( $key, $value, $group, $expiration );
		}

		// Always store in cache table as well
		// This provides persistence and fallback
		$table_success = $this->cache_table->set_cache( $key, $value, $group, $expiration );

		return $table_success;
	}

	/**
	 * Delete cached data.
	 *
	 * Deletes from both object cache and cache table.
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'reports').
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key, $group = 'reports' ) {
		// Delete from object cache if available
		if ( $this->has_object_cache() ) {
			wp_cache_delete( $key, $group );
		}

		// Delete from cache table
		return $this->cache_table->delete_cache( $key, $group );
	}

	/**
	 * Flush all cached data in a group.
	 *
	 * @since 9.11.0
	 * @param string $group Cache group (default 'reports').
	 * @return bool True on success, false on failure.
	 */
	public function flush_group( $group = 'reports' ) {
		// Flush from object cache if available and supported
		if ( $this->has_object_cache() && function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $group );
		}

		// Flush from cache table
		return $this->cache_table->flush_group( $group );
	}

	/**
	 * Flush all cached data.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function flush_all() {
		// Flush object cache if available
		if ( $this->has_object_cache() ) {
			wp_cache_flush();
		}

		// Flush cache table
		return $this->cache_table->flush_all();
	}

	/**
	 * Check if a cache key exists.
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'reports').
	 * @return bool True if exists, false otherwise.
	 */
	public function exists( $key, $group = 'reports' ) {
		// Check object cache first
		if ( $this->has_object_cache() ) {
			$cached = wp_cache_get( $key, $group );
			if ( false !== $cached ) {
				return true;
			}
		}

		// Check cache table
		return $this->cache_table->exists( $key, $group );
	}

	/**
	 * Get cache statistics.
	 *
	 * @since 9.11.0
	 * @return array Cache statistics.
	 */
	public function get_stats() {
		$stats = $this->cache_table->get_stats();

		$stats['object_cache_available'] = $this->has_object_cache();

		return $stats;
	}

	/**
	 * Clean up expired cache entries from table.
	 *
	 * Object cache handles its own expiration.
	 *
	 * @since 9.11.0
	 * @return int Number of entries deleted.
	 */
	public function cleanup_expired() {
		return $this->cache_table->cleanup_expired();
	}

	/**
	 * Get the remaining time to live for a cache entry.
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'reports').
	 * @return int|false Time to live in seconds, or false if not found/expired.
	 */
	public function get_ttl( $key, $group = 'reports' ) {
		// Object cache doesn't expose TTL, so check table
		return $this->cache_table->get_ttl( $key, $group );
	}
}
