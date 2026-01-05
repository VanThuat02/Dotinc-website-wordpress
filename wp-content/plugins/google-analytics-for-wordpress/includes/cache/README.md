# MonsterInsights Cache System

**Version:** 9.11.0
**Status:** Production Ready

## Overview

The MonsterInsights cache system provides a unified, high-performance caching layer with automatic fallback from object cache (Redis/Memcached) to a custom database table.

## Architecture

```
┌─────────────────────────────────┐
│   Cache Request                 │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  MonsterInsights_Cache_Wrapper  │
└────────────┬────────────────────┘
             │
             ├──► Object Cache Available?
             │         │
             │         ├─ YES ──► Redis/Memcached
             │         │              └─► Fast (1-5ms)
             │         │
             │         └─ NO ───┐
             │                  │
             └──────────────────┴──► Custom Cache Table
                                        └─► Still Fast (5-10ms)
```

## Quick Start

### Basic Usage

```php
// Get from cache (defaults to 'reports' group)
$data = monsterinsights_cache_get( 'overview_30d' );

if ( $data === false ) {
    // Cache miss - fetch fresh data
    $data = fetch_report_data();

    // Store in cache for 1 hour
    monsterinsights_cache_set( 'overview_30d', $data, 'reports', HOUR_IN_SECONDS );
}

// Use the data
return $data;
```

### With Custom Group

```php
// For non-report caching, specify the group
$settings = monsterinsights_cache_get( 'plugin_settings', 'settings' );

if ( $settings === false ) {
    $settings = get_option( 'monsterinsights_settings' );
    monsterinsights_cache_set( 'plugin_settings', $settings, 'settings', DAY_IN_SECONDS );
}
```

## API Reference

### Core Functions

#### `monsterinsights_cache_get( $key, $group = 'reports' )`

Retrieve cached data.

**Parameters:**
- `$key` (string) - Cache key
- `$group` (string) - Cache group (default: 'reports')

**Returns:** `mixed|false` - Cached value or false if not found

**Example:**
```php
$data = monsterinsights_cache_get( 'my_report_key' );
```

---

#### `monsterinsights_cache_set( $key, $value, $group = 'reports', $expiration = 3600 )`

Store data in cache.

**Parameters:**
- `$key` (string) - Cache key
- `$value` (mixed) - Data to cache
- `$group` (string) - Cache group (default: 'reports')
- `$expiration` (int) - Expiration in seconds (default: 3600)

**Returns:** `bool` - True on success, false on failure

**Example:**
```php
monsterinsights_cache_set( 'my_report_key', $report_data, 'reports', DAY_IN_SECONDS );
```

---

#### `monsterinsights_cache_delete( $key, $group = 'reports' )`

Delete cached data.

**Parameters:**
- `$key` (string) - Cache key
- `$group` (string) - Cache group (default: 'reports')

**Returns:** `bool` - True on success, false on failure

**Example:**
```php
monsterinsights_cache_delete( 'my_report_key' );
```

---

#### `monsterinsights_cache_flush_group( $group = 'reports' )`

Flush all cache in a group.

**Parameters:**
- `$group` (string) - Cache group (default: 'reports')

**Returns:** `bool` - True on success, false on failure

**Example:**
```php
// Clear all report caches
monsterinsights_cache_flush_group( 'reports' );
```

---

#### `monsterinsights_cache_flush_all()`

Flush all cache.

**Returns:** `bool` - True on success, false on failure

**Example:**
```php
monsterinsights_cache_flush_all();
```

---

### Utility Functions

#### `monsterinsights_cache_exists( $key, $group = 'reports' )`

Check if a cache key exists.

**Returns:** `bool`

---

#### `monsterinsights_cache_get_stats()`

Get cache statistics.

**Returns:** Array with:
- `total_entries` - Total cache entries
- `valid_entries` - Non-expired entries
- `expired_entries` - Expired entries
- `total_size` - Total size in bytes
- `by_group` - Breakdown by group
- `object_cache_available` - Whether Redis/Memcached is available

**Example:**
```php
$stats = monsterinsights_cache_get_stats();
echo "Cache hit rate: " . ( $stats['valid_entries'] / $stats['total_entries'] * 100 ) . "%";
echo "Object cache: " . ( $stats['object_cache_available'] ? 'Yes' : 'No' );
```

---

#### `monsterinsights_cache_cleanup()`

Clean up expired cache entries.

**Returns:** `int` - Number of entries deleted

**Example:**
```php
$deleted = monsterinsights_cache_cleanup();
echo "Cleaned up {$deleted} expired entries";
```

---

#### `monsterinsights_cache_get_ttl( $key, $group = 'reports' )`

Get remaining time to live for a cache entry.

**Returns:** `int|false` - Seconds remaining or false if not found

---

#### `monsterinsights_has_object_cache()`

Check if object cache (Redis/Memcached) is available.

**Returns:** `bool`

**Example:**
```php
if ( monsterinsights_has_object_cache() ) {
    echo "Using Redis/Memcached for super-fast caching!";
} else {
    echo "Using custom cache table";
}
```

## Cache Groups

The default group is `'reports'` since that's the most common use case. You can use any custom group name:

**Recommended Groups:**
- `reports` - Report data (default)
- `api` - API responses
- `settings` - Plugin settings
- `popular_posts` - Popular posts data
- `tracking` - Tracking data
- `temp` - Temporary data (short TTL)

**Example:**
```php
// Reports (default group - no need to specify)
monsterinsights_cache_get( 'overview_30d' );

// API responses
monsterinsights_cache_get( 'ga_profile_data', 'api' );

// Popular posts
monsterinsights_cache_get( 'widget_posts', 'popular_posts' );
```

## Migrating from Old Cache System

### Old Pattern (wp_options/transients)

```php
// Old way
$transient_key = 'monsterinsights_report_' . $this->name . '_' . $start . '_' . $end;
$data = get_transient( $transient_key );

if ( $data === false ) {
    $data = fetch_from_api();
    set_transient( $transient_key, $data, DAY_IN_SECONDS );
}
```

### New Pattern (cache wrapper)

```php
// New way
$cache_key = 'report_' . $this->name . '_' . $start . '_' . $end;
$data = monsterinsights_cache_get( $cache_key ); // defaults to 'reports' group

if ( $data === false ) {
    $data = fetch_from_api();
    monsterinsights_cache_set( $cache_key, $data, 'reports', DAY_IN_SECONDS );
}
```

### Benefits of Migration

1. **Automatic object cache usage** - Redis/Memcached when available
2. **Cleaner API** - Simpler function names
3. **Better performance** - Custom table vs wp_options bloat
4. **No autoload issues** - Cache data never autoloads
5. **Unified system** - One cache system for everything

## Advanced Usage

### Conditional Caching

```php
// Only cache in production
if ( defined( 'WP_ENV' ) && WP_ENV === 'production' ) {
    $data = monsterinsights_cache_get( 'expensive_data' );
    if ( $data === false ) {
        $data = expensive_operation();
        monsterinsights_cache_set( 'expensive_data', $data, 'reports', HOUR_IN_SECONDS );
    }
} else {
    // Always fresh in dev
    $data = expensive_operation();
}
```

### Cache Warming

```php
// Pre-populate cache for common requests
function monsterinsights_warm_report_cache() {
    $common_reports = array( 'overview', 'publishers', 'ecommerce' );
    $date_ranges = array(
        array( 'start' => date( 'Y-m-d', strtotime( '-7 days' ) ), 'end' => date( 'Y-m-d' ) ),
        array( 'start' => date( 'Y-m-d', strtotime( '-30 days' ) ), 'end' => date( 'Y-m-d' ) ),
    );

    foreach ( $common_reports as $report ) {
        foreach ( $date_ranges as $range ) {
            $cache_key = "report_{$report}_{$range['start']}_{$range['end']}";

            if ( ! monsterinsights_cache_exists( $cache_key ) ) {
                $data = fetch_report( $report, $range['start'], $range['end'] );
                monsterinsights_cache_set( $cache_key, $data );
            }
        }
    }
}

// Run via cron
add_action( 'monsterinsights_cache_warm_cron', 'monsterinsights_warm_report_cache' );
```

### Debug Cache Performance

```php
// Check cache performance
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $stats = monsterinsights_cache_get_stats();
    $hit_rate = $stats['total_entries'] > 0
        ? round( $stats['valid_entries'] / $stats['total_entries'] * 100, 2 )
        : 0;

    echo '<div class="notice notice-info">';
    echo '<p><strong>Cache Stats:</strong></p>';
    echo '<ul>';
    echo '<li>Hit Rate: ' . $hit_rate . '%</li>';
    echo '<li>Valid Entries: ' . number_format( $stats['valid_entries'] ) . '</li>';
    echo '<li>Total Size: ' . size_format( $stats['total_size'] ) . '</li>';
    echo '<li>Object Cache: ' . ( $stats['object_cache_available'] ? 'Active' : 'Not Available' ) . '</li>';
    echo '</ul>';
    echo '</div>';
});
```

## Cron Jobs

The cache table requires periodic cleanup of expired entries.

### Setup Cleanup Cron

```php
// In your plugin activation
if ( ! wp_next_scheduled( 'monsterinsights_cache_cleanup' ) ) {
    wp_schedule_event( time(), 'daily', 'monsterinsights_cache_cleanup' );
}

// Hook the cleanup function
add_action( 'monsterinsights_cache_cleanup', 'monsterinsights_cache_cleanup' );

// On deactivation
wp_clear_scheduled_hook( 'monsterinsights_cache_cleanup' );
```

## Performance Comparison

### Before (wp_options + transients)

- Cache lookup: **50-100ms** (database query in bloated wp_options)
- Autoload penalty: **+50ms per page load** (all pages affected)
- Hit rate: **~25%** (per-user caching, short TTLs)

### After (Object Cache + Custom Table)

**With Redis/Memcached:**
- Cache lookup: **1-5ms** (memory-based)
- Autoload penalty: **0ms** (not in wp_options)
- Hit rate: **>80%** (shared cache, longer TTLs)

**Without Object Cache:**
- Cache lookup: **5-10ms** (dedicated table with indexes)
- Autoload penalty: **0ms** (not in wp_options)
- Hit rate: **>80%** (shared cache, longer TTLs)

## Troubleshooting

### Cache Not Working

Check if table exists:
```php
$cache_table = monsterinsights_get_cache_table();
if ( ! $cache_table->table_exists() ) {
    echo "Cache table doesn't exist!";
    // Run migration
    require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/database/loader.php';
    monsterinsights_run_database_migrations();
}
```

### Object Cache Not Being Used

```php
if ( ! monsterinsights_has_object_cache() ) {
    echo "Object cache not available. Install Redis or Memcached for better performance.";
}
```

### Clear All Cache

```php
// Clear everything
monsterinsights_cache_flush_all();

// Or just reports
monsterinsights_cache_flush_group( 'reports' );
```

## Best Practices

1. **Use descriptive cache keys** - Include what's cached in the key name
2. **Set appropriate expiration** - Longer for static data, shorter for dynamic
3. **Group logically** - Use groups to organize related cache
4. **Clean up on settings changes** - Flush cache when settings change
5. **Monitor hit rates** - Check cache stats regularly
6. **Don't cache user-specific data in shared cache** - Unless intentional
7. **Use cache warming for common requests** - Pre-populate during off-peak hours

## Future Enhancements

- [ ] Cache warming scheduler
- [ ] Per-group statistics
- [ ] Admin UI for cache management
- [ ] Cache invalidation on data changes
- [ ] Redis-specific optimizations (pipelining)
- [ ] Cache preloading on plugin activation

---

**Last Updated:** 2025-01-11
**Maintainer:** MonsterInsights Development Team
