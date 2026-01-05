<?php
/**
 * Cache Table Class
 *
 * Manages the cache database table for MonsterInsights.
 * Provides optimized storage for report data, API responses, and other cached content.
 *
 * @since 9.11.0
 * @package MonsterInsights
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache Table class.
 *
 * @since 9.11.0
 */
class MonsterInsights_Cache_Table extends MonsterInsights_DB_Base {

	/**
	 * Table name (without prefix).
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $table_name = 'monsterinsights_cache';

	/**
	 * Table version.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Primary key column.
	 *
	 * @since 9.11.0
	 * @var string
	 */
	protected $primary_key = 'cache_id';

	/**
	 * Get the table schema SQL.
	 *
	 * @since 9.11.0
	 * @return string CREATE TABLE SQL statement.
	 */
	public function get_schema() {
		global $wpdb;

		$table_name = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table_name} (
			cache_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			cache_key VARCHAR(255) NOT NULL,
			cache_value LONGTEXT NOT NULL,
			cache_group VARCHAR(64) DEFAULT 'default',
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (cache_id),
			UNIQUE KEY cache_key_group (cache_key, cache_group),
			KEY expires_at (expires_at),
			KEY created_at (created_at)
		) {$charset_collate};";
	}

	/**
	 * Get array of column names and their definitions.
	 *
	 * @since 9.11.0
	 * @return array Array of column definitions.
	 */
	public function get_columns() {
		return array(
			'cache_id'    => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
			'cache_key'   => 'VARCHAR(255) NOT NULL',
			'cache_value' => 'LONGTEXT NOT NULL',
			'cache_group' => 'VARCHAR(64) DEFAULT \'default\'',
			'expires_at'  => 'DATETIME NOT NULL',
			'created_at'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
		);
	}

	/**
	 * Get a cached value.
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'default').
	 * @return mixed|false Cached value or false if not found/expired.
	 */
	public function get_cache( $key, $group = 'default' ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cache_value, expires_at FROM {$table_name}
				WHERE cache_key = %s AND cache_group = %s AND expires_at > NOW()",
				$key,
				$group
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $result ) {
			return false;
		}

		return maybe_unserialize( $result->cache_value );
	}

	/**
	 * Set a cached value.
	 *
	 * @since 9.11.0
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param string $group      Cache group (default 'default').
	 * @param int    $expiration Expiration in seconds (default 3600).
	 * @return bool True on success, false on failure.
	 */
	public function set_cache( $key, $value, $group = 'default', $expiration = 3600 ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		// Calculate expiration datetime
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $expiration );

		// Serialize value
		$serialized = maybe_serialize( $value );

		// Use REPLACE to handle insert or update
		$result = $wpdb->replace(
			$table_name,
			array(
				'cache_key'   => $key,
				'cache_group' => $group,
				'cache_value' => $serialized,
				'expires_at'  => $expires_at,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $result !== false;
	}

	/**
	 * Delete a cached value.
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'default').
	 * @return bool True on success, false on failure.
	 */
	public function delete_cache( $key, $group = 'default' ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		$result = $wpdb->delete(
			$table_name,
			array(
				'cache_key'   => $key,
				'cache_group' => $group,
			),
			array( '%s', '%s' )
		);

		return $result !== false;
	}

	/**
	 * Flush all cached values in a group.
	 *
	 * @since 9.11.0
	 * @param string $group Cache group (default 'default').
	 * @return bool True on success, false on failure.
	 */
	public function flush_group( $group = 'default' ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		$result = $wpdb->delete(
			$table_name,
			array( 'cache_group' => $group ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * Flush all cached values.
	 *
	 * @since 9.11.0
	 * @return bool True on success, false on failure.
	 */
	public function flush_all() {
		return $this->truncate();
	}

	/**
	 * Clean up expired cache entries.
	 *
	 * @since 9.11.0
	 * @return int Number of entries deleted.
	 */
	public function cleanup_expired() {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$deleted = $wpdb->query(
			"DELETE FROM {$table_name} WHERE expires_at < NOW()"
		);

		// Optimize table if significant deletions
		if ( $deleted > 100 ) {
			$wpdb->query( "OPTIMIZE TABLE {$table_name}" );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $deleted;
	}

	/**
	 * Get cache statistics.
	 *
	 * @since 9.11.0
	 * @return array {
	 *     Cache statistics.
	 *
	 *     @type int $total_entries   Total cache entries.
	 *     @type int $valid_entries   Valid (not expired) entries.
	 *     @type int $expired_entries Expired entries.
	 *     @type int $total_size      Total size in bytes.
	 *     @type array $by_group      Entries grouped by cache_group.
	 * }
	 */
	public function get_stats() {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		// Overall stats
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_entries,
				SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as valid_entries,
				SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired_entries,
				SUM(LENGTH(cache_value)) as total_size
			FROM {$table_name}",
			ARRAY_A
		);

		// Stats by group
		$by_group = $wpdb->get_results(
			"SELECT
				cache_group,
				COUNT(*) as entries,
				SUM(LENGTH(cache_value)) as size
			FROM {$table_name}
			GROUP BY cache_group",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$stats['by_group'] = $by_group;

		return $stats;
	}

	/**
	 * Check if a cache key exists (not expired).
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'default').
	 * @return bool True if exists and not expired, false otherwise.
	 */
	public function exists( $key, $group = 'default' ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name}
				WHERE cache_key = %s AND cache_group = %s AND expires_at > NOW()",
				$key,
				$group
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $result > 0;
	}

	/**
	 * Get the remaining time to live for a cache entry.
	 *
	 * @since 9.11.0
	 * @param string $key   Cache key.
	 * @param string $group Cache group (default 'default').
	 * @return int|false Time to live in seconds, or false if not found/expired.
	 */
	public function get_ttl( $key, $group = 'default' ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM {$table_name}
				WHERE cache_key = %s AND cache_group = %s AND expires_at > NOW()",
				$key,
				$group
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $result === null ) {
			return false;
		}

		return max( 0, (int) $result );
	}
}
