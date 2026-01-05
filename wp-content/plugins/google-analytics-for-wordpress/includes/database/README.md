# MonsterInsights Database Framework

**Version:** 9.11.0
**Status:** Production Ready

## Overview

The MonsterInsights Database Framework provides a scalable, maintainable system for managing database tables and migrations. It replaces ad-hoc database management with a structured, testable approach.

## Architecture

```
includes/database/
├── class-db-base.php              # Abstract base for all tables
├── class-migration.php            # Abstract base for migrations
├── class-migration-runner.php     # Orchestrates migrations
├── class-db-schema.php            # Schema version management
├── loader.php                     # Loads all DB classes
├── tables/                        # Table implementations
│   └── class-cache-table.php
└── migrations/                    # Migration classes
    └── class-migration-9110-cache-table.php
```

## Core Components

### 1. MonsterInsights_DB_Base

Abstract base class for all database tables. Provides:
- Table schema management
- CRUD operations (Create, Read, Update, Delete)
- Version tracking per table
- Utility methods (table_exists, column_exists, etc.)

**Example Usage:**

```php
class My_Table extends MonsterInsights_DB_Base {
    protected $table_name = 'monsterinsights_my_table';
    protected $version = '1.0.0';
    protected $primary_key = 'id';

    public function get_schema() {
        global $wpdb;
        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY name (name)
        ) {$charset_collate};";
    }

    public function get_columns() {
        return array(
            'id'         => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
            'name'       => 'VARCHAR(255) NOT NULL',
            'value'      => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        );
    }
}
```

### 2. MonsterInsights_Migration

Abstract base class for database migrations. Provides:
- up() method for schema changes
- Helper methods for common operations
- Automatic logging

**Example Usage:**

```php
class MonsterInsights_Migration_9120_Add_Column extends MonsterInsights_Migration {
    protected $version = '9.12.0';
    protected $description = 'Add status column to cache table';

    public function up() {
        if ( ! $this->table_exists( 'monsterinsights_cache' ) ) {
            throw new Exception( 'Cache table does not exist' );
        }

        if ( $this->column_exists( 'monsterinsights_cache', 'status' ) ) {
            $this->log_info( 'Column already exists' );
            return;
        }

        $this->add_column(
            'monsterinsights_cache',
            'status',
            'VARCHAR(20) DEFAULT "active"'
        );

        $this->log_success( 'Added status column' );
    }
}
```

### 3. MonsterInsights_Migration_Runner

Orchestrates migration execution. Provides:
- Migration registration
- Execution of pending migrations
- Migration history tracking
- Lock mechanism to prevent concurrent runs

**Example Usage:**

```php
$runner = new MonsterInsights_Migration_Runner();
$runner->register_migration( 'MonsterInsights_Migration_9110_Cache_Table' );
$results = $runner->run_pending_migrations();

if ( $results['success'] ) {
    echo "All migrations completed successfully";
} else {
    echo "Migrations failed: " . print_r( $results, true );
}
```

### 4. MonsterInsights_DB_Schema

Manages schema versioning. Provides:
- Schema version tracking
- Table registration
- Upgrade detection

**Example Usage:**

```php
$schema = monsterinsights_get_db_schema();

if ( $schema->needs_upgrade() ) {
    $current = $schema->get_current_version();
    $target = $schema->get_target_version();
    echo "Upgrade needed: {$current} → {$target}";
}

// Get schema info for debugging
$info = $schema->get_schema_info();
print_r( $info );
```

## Creating a New Table

### Step 1: Create Table Class

Create a new file in `includes/database/tables/`:

```php
<?php
// includes/database/tables/class-my-table.php

class MonsterInsights_My_Table extends MonsterInsights_DB_Base {
    protected $table_name = 'monsterinsights_my_table';
    protected $version = '1.0.0';
    protected $primary_key = 'id';

    public function get_schema() {
        global $wpdb;
        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            data TEXT NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";
    }

    public function get_columns() {
        return array(
            'id'   => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
            'data' => 'TEXT NOT NULL',
        );
    }

    // Add custom methods for your table
    public function get_data_by_id( $id ) {
        return $this->get( $id );
    }
}
```

### Step 2: Create Migration Class

Create a new file in `includes/database/migrations/`:

```php
<?php
// includes/database/migrations/class-migration-9120-my-table.php

class MonsterInsights_Migration_9120_My_Table extends MonsterInsights_Migration {
    protected $version = '9.12.0';
    protected $description = 'Create my custom table';

    public function up() {
        require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/database/tables/class-my-table.php';

        $table = new MonsterInsights_My_Table();

        if ( $table->table_exists() ) {
            $this->log_info( 'Table already exists' );
            return;
        }

        $created = $table->create_table();

        if ( ! $created ) {
            throw new Exception( 'Failed to create table' );
        }

        $this->log_success( 'Table created successfully' );
    }
}
```

### Step 3: Register in Loader

Update `includes/database/loader.php`:

```php
// Add to table loading section
require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/database/tables/class-my-table.php';

// Add to migration loading section
require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/database/migrations/class-migration-9120-my-table.php';

// Register in monsterinsights_run_database_migrations()
$migrations = array(
    'MonsterInsights_Migration_9110_Cache_Table',
    'MonsterInsights_Migration_9120_My_Table', // Add this
);
```

### Step 4: Update Schema Version

Update `class-db-schema.php`:

```php
const CURRENT_SCHEMA_VERSION = '9.12.0'; // Increment this
```

## Working with the Cache Table

The cache table is the first implementation and provides a complete example.

### Storing Data

```php
$cache_table = monsterinsights_get_cache_table();

// Store a value for 1 hour
$cache_table->set_cache(
    'my_cache_key',
    array( 'data' => 'value' ),
    'my_group',
    HOUR_IN_SECONDS
);
```

### Retrieving Data

```php
$cache_table = monsterinsights_get_cache_table();

$data = $cache_table->get_cache( 'my_cache_key', 'my_group' );

if ( $data === false ) {
    // Cache miss - fetch fresh data
    $data = fetch_fresh_data();
    $cache_table->set_cache( 'my_cache_key', $data, 'my_group', HOUR_IN_SECONDS );
}
```

### Clearing Cache

```php
$cache_table = monsterinsights_get_cache_table();

// Delete specific key
$cache_table->delete_cache( 'my_cache_key', 'my_group' );

// Flush entire group
$cache_table->flush_group( 'my_group' );

// Flush all cache
$cache_table->flush_all();
```

### Cache Statistics

```php
$cache_table = monsterinsights_get_cache_table();
$stats = $cache_table->get_stats();

echo "Total entries: " . $stats['total_entries'];
echo "Valid entries: " . $stats['valid_entries'];
echo "Expired entries: " . $stats['expired_entries'];
echo "Total size: " . size_format( $stats['total_size'] );
```

## Migration Helpers

The Migration base class provides these helper methods:

### Table Operations

```php
// Check if table exists
if ( $this->table_exists( 'my_table' ) ) {
    // ...
}
```

### Column Operations

```php
// Check if column exists
if ( $this->column_exists( 'my_table', 'my_column' ) ) {
    // ...
}

// Add column
$this->add_column( 'my_table', 'status', 'VARCHAR(20) DEFAULT "active"' );

// Modify column
$this->modify_column( 'my_table', 'status', 'VARCHAR(50) DEFAULT "pending"' );
```

### Index Operations

```php
// Check if index exists
if ( $this->index_exists( 'my_table', 'status_idx' ) ) {
    // ...
}

// Add index
$this->add_index( 'my_table', 'status_idx', array( 'status' ) );

// Add unique index
$this->add_index( 'my_table', 'email_idx', array( 'email' ), 'UNIQUE' );

// Drop index
$this->drop_index( 'my_table', 'old_idx' );
```

### Logging

```php
// Log success
$this->log_success( 'Operation completed successfully' );

// Log info
$this->log_info( 'Skipping step, already completed' );

// Log error
$this->log_error( 'Failed to perform operation' );
```

## Testing Migrations

### Manual Testing

1. **Fresh Install Test**
   - Deactivate plugin
   - Delete all `monsterinsights_*` options
   - Drop all `wp_monsterinsights_*` tables
   - Reactivate plugin
   - Verify tables created

2. **Upgrade Test**
   - Start with older version installed
   - Update to new version
   - Verify migrations run automatically

3. **Re-run Test**
   - Run migrations again
   - Verify they're skipped (idempotent)

### Programmatic Testing

```php
// Get migration statistics
$runner = new MonsterInsights_Migration_Runner();
$runner->register_migrations( $all_migrations );

$stats = $runner->get_statistics();
echo "Pending: " . $stats['pending_migrations'];
echo "Completed: " . $stats['completed_migrations'];

// Check migration history
$history = $runner->get_migration_history();
foreach ( $history as $entry ) {
    echo "{$entry['version']}: {$entry['status']} in {$entry['duration']}s\n";
}

// Check schema status
$schema = monsterinsights_get_db_schema();
if ( $schema->needs_upgrade() ) {
    echo "Schema needs upgrade!\n";
}
```

## Troubleshooting

### Migrations Not Running

Check if migrations are locked:

```php
$runner = new MonsterInsights_Migration_Runner();
if ( $runner->is_locked() ) {
    echo "Migrations are locked\n";
    // Force release if needed
    $runner->force_release_lock();
}
```

### View Migration Log

```php
$log = get_option( 'monsterinsights_migration_log', array() );
foreach ( $log as $entry ) {
    echo "{$entry['timestamp']} [{$entry['level']}] {$entry['message']}\n";
}
```

### View Migration History

```php
$history = get_option( 'monsterinsights_migration_history', array() );
print_r( $history );
```

### Reset Migrations (Testing Only)

```php
// WARNING: This will cause migrations to run again
$runner = new MonsterInsights_Migration_Runner();
$runner->clear_history();

$schema = new MonsterInsights_DB_Schema();
$schema->reset_version();
```

## Best Practices

1. **Always use dbDelta()** for table creation - it's idempotent
2. **Check existence before operations** - use helper methods
3. **Log all operations** - use logging methods
4. **Handle errors gracefully** - throw exceptions for failures
5. **Test on fresh install** - migrations should work from scratch
6. **Test on upgrade** - migrations should work on existing installs
7. **Make migrations idempotent** - safe to run multiple times
8. **Version everything** - increment schema version for each migration
9. **Document changes** - clear migration descriptions
10. **Keep migrations small** - one logical change per migration

## Security Considerations

- Never trust user input in migrations
- Use `$wpdb->prepare()` for dynamic SQL
- Validate data before insertion
- Use proper capability checks if triggered manually

## Performance Tips

- Index frequently queried columns
- Use appropriate column types (don't use TEXT for short strings)
- Consider data volume when designing schema
- Batch large operations in migrations
- Use `OPTIMIZE TABLE` after large deletions

## Future Enhancements

Potential improvements for future versions:

1. **Rollback Support** - Add down() methods for reverting migrations
2. **Migration Scheduling** - Run migrations in background
3. **Dry Run Mode** - Test migrations without executing
4. **Migration Export** - Export schema for replication
5. **Performance Profiling** - Track migration execution times
6. **Parallel Execution** - Run independent migrations concurrently

## Support

For questions or issues with the database framework:

1. Check this documentation first
2. Review existing migrations as examples
3. Check migration logs and history
4. Contact the MonsterInsights development team

## Changelog

### 9.11.0 (2025-11-11)

- Initial database framework implementation
- Created DB_Base abstract class
- Created Migration system
- Created Migration_Runner
- Created DB_Schema manager
- Implemented Cache Table as first example
- Integrated with existing install.php
- **Cache Migration Approach:** Implemented cleanup-instead-of-migration for old cache data
  - Old cache uses incompatible key format (no date ranges)
  - Migration replaced with WP-Cron-based batch cleanup
  - Processes 100 entries per batch
  - Tracks progress in wp_options
  - Self-terminating when complete
  - Zero performance risk, no timeout issues

## Migration Notes: Cache Table (9.11.0)

The cache table migration (`MonsterInsights_Migration_9110_Cache_Table`) uses a **cleanup approach** instead of traditional data migration:

**Why Cleanup Instead of Migration?**
- Old cache keys: `monsterinsights_report_data_overview` (no dates)
- New cache keys: `report_overview_2025-01-01_2025-01-31` (with dates)
- Key format incompatible - accurate migration impossible without date metadata
- Clean slate ensures correct key format from day one

**How It Works:**
1. Migration creates cache table schema
2. Counts old cache entries in wp_options
3. Schedules cleanup via WP-Cron hook `monsterinsights_cleanup_old_cache_batch`
4. Cleanup runs in background, 100 entries per batch every 5 seconds
5. Tracks progress: `monsterinsights_cache_cleanup_total`, `monsterinsights_cache_cleanup_processed`
6. Auto-completes and cleans up progress tracking

**Trade-offs:**
- First report loads after update will fetch fresh data (one-time cache miss)
- Acceptable trade-off for data accuracy and correct key format

**Patterns Cleaned:**
- `monsterinsights_report_data_%`
- `_transient_monsterinsights_report_%`
- `_transient_timeout_monsterinsights_report_%`

---

**Last Updated:** 2025-11-11
**Maintainer:** MonsterInsights Development Team
