<?php
/**
 * Database Migrator
 *
 * Handles version-based database migrations for schema updates.
 * Ensures smooth upgrades between plugin versions.
 *
 * @package AutoBlogCraft\Database
 * @since 2.0.0
 */

namespace AutoBlogCraft\Database;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrator class
 *
 * Responsibilities:
 * - Run delta migrations between versions
 * - Track migration history
 * - Rollback failed migrations
 * - Validate schema after migration
 *
 * @since 2.0.0
 */
class Migrator {

    /**
     * Current database version
     *
     * @var string
     */
    private $current_version;

    /**
     * Target database version
     *
     * @var string
     */
    private $target_version;

    /**
     * Migration history
     *
     * @var array
     */
    private $history = [];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->current_version = get_option('abc_db_version', '0.0.0');
        $this->target_version = ABC_DB_VERSION;
        $this->history = get_option('abc_migration_history', []);
    }

    /**
     * Run pending migrations
     *
     * Executes all migrations from current version to target version.
     *
     * @since 2.0.0
     * @return bool True on success, false on failure.
     */
    public function migrate() {
        // Check if migration is needed
        if (version_compare($this->current_version, $this->target_version, '>=')) {
            return true; // Already up to date
        }

        Logger::log(0, 'info', 'migration', 'Starting database migration', [
            'from' => $this->current_version,
            'to' => $this->target_version,
        ]);

        // Get migrations to run
        $migrations = $this->get_pending_migrations();

        if (empty($migrations)) {
            return $this->finalize_migration();
        }

        // Backup current state
        $this->backup_options();

        // Run each migration
        foreach ($migrations as $version => $callback) {
            $success = $this->run_migration($version, $callback);

            if (!$success) {
                Logger::log(0, 'error', 'migration', "Migration failed: {$version}");
                $this->rollback();
                return false;
            }

            // Update current version after successful migration
            $this->current_version = $version;
            update_option('abc_db_version', $version);
        }

        return $this->finalize_migration();
    }

    /**
     * Get pending migrations
     *
     * Returns array of migrations that need to be run.
     *
     * @since 2.0.0
     * @return array Migrations to run.
     */
    private function get_pending_migrations() {
        $migrations = [];

        // Migration from 1.x to 2.0.0
        if (version_compare($this->current_version, '2.0.0', '<')) {
            $migrations['2.0.0'] = [$this, 'migrate_to_2_0_0'];
        }

        // Future migrations can be added here
        // Example:
        // if (version_compare($this->current_version, '2.1.0', '<')) {
        //     $migrations['2.1.0'] = [$this, 'migrate_to_2_1_0'];
        // }

        return $migrations;
    }

    /**
     * Run a single migration
     *
     * @since 2.0.0
     * @param string   $version  Version number.
     * @param callable $callback Migration callback.
     * @return bool True on success, false on failure.
     */
    private function run_migration($version, $callback) {
        try {
            Logger::log(0, 'info', 'migration', "Running migration: {$version}");

            // Execute migration
            $result = call_user_func($callback);

            if ($result === false) {
                throw new \Exception("Migration {$version} returned false");
            }

            // Record in history
            $this->history[] = [
                'version' => $version,
                'timestamp' => time(),
                'success' => true,
            ];
            update_option('abc_migration_history', $this->history);

            Logger::log(0, 'success', 'migration', "Migration completed: {$version}");

            return true;

        } catch (\Exception $e) {
            Logger::log(0, 'error', 'migration', "Migration error: {$version}", [
                'error' => $e->getMessage(),
            ]);

            // Record failure in history
            $this->history[] = [
                'version' => $version,
                'timestamp' => time(),
                'success' => false,
                'error' => $e->getMessage(),
            ];
            update_option('abc_migration_history', $this->history);

            return false;
        }
    }

    /**
     * Migration: 2.0.0
     *
     * Migrates from v1.x to v2.0.0 schema.
     *
     * @since 2.0.0
     * @return bool
     */
    private function migrate_to_2_0_0() {
        global $wpdb;

        // Add new columns to existing tables
        $queue_table = $wpdb->prefix . 'abc_discovery_queue';
        
        // Check if columns exist before adding
        $columns = $wpdb->get_col("DESCRIBE {$queue_table}");

        if (!in_array('priority', $columns)) {
            $wpdb->query(
                "ALTER TABLE {$queue_table} 
                 ADD COLUMN priority tinyint DEFAULT 5 AFTER status"
            );
        }

        if (!in_array('freshness_timestamp', $columns)) {
            $wpdb->query(
                "ALTER TABLE {$queue_table} 
                 ADD COLUMN freshness_timestamp datetime AFTER attribution_data"
            );
        }

        if (!in_array('last_error_code', $columns)) {
            $wpdb->query(
                "ALTER TABLE {$queue_table} 
                 ADD COLUMN last_error_code varchar(50) AFTER last_error"
            );
        }

        // Add indexes for better performance
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$queue_table}", ARRAY_A);
        $index_names = array_column($indexes, 'Key_name');

        if (!in_array('priority', $index_names)) {
            $wpdb->query("ALTER TABLE {$queue_table} ADD INDEX priority (priority DESC)");
        }

        if (!in_array('created_at', $index_names)) {
            $wpdb->query("ALTER TABLE {$queue_table} ADD INDEX created_at (created_at)");
        }

        // Update API keys table for PBKDF2 encryption
        $keys_table = $wpdb->prefix . 'abc_api_keys';
        $key_columns = $wpdb->get_col("DESCRIBE {$keys_table}");

        if (!in_array('provider_type', $key_columns)) {
            $wpdb->query(
                "ALTER TABLE {$keys_table} 
                 ADD COLUMN provider_type varchar(30) DEFAULT 'ai' AFTER provider"
            );
        }

        if (!in_array('quota_limit', $key_columns)) {
            $wpdb->query(
                "ALTER TABLE {$keys_table} 
                 ADD COLUMN quota_limit bigint AFTER rate_reset_at,
                 ADD COLUMN quota_remaining bigint AFTER quota_limit,
                 ADD COLUMN quota_reset_at datetime AFTER quota_remaining"
            );
        }

        // Update campaign AI config table
        $config_table = $wpdb->prefix . 'abc_campaign_ai_config';
        $config_columns = $wpdb->get_col("DESCRIBE {$config_table}");

        if (!in_array('humanizer_enabled', $config_columns)) {
            $wpdb->query(
                "ALTER TABLE {$config_table} 
                 ADD COLUMN humanizer_enabled boolean DEFAULT false AFTER add_conclusion,
                 ADD COLUMN humanizer_level tinyint DEFAULT 5 AFTER humanizer_enabled,
                 ADD COLUMN humanizer_provider varchar(50) AFTER humanizer_level,
                 ADD COLUMN humanizer_key_id bigint unsigned AFTER humanizer_provider"
            );
        }

        if (!in_array('internal_links', $config_columns)) {
            $wpdb->query(
                "ALTER TABLE {$config_table} 
                 ADD COLUMN internal_links longtext AFTER humanizer_key_id,
                 ADD COLUMN internal_links_mode varchar(30) DEFAULT 'regex' AFTER internal_links"
            );
        }

        // Update translation cache table
        $cache_table = $wpdb->prefix . 'abc_translation_cache';
        $cache_columns = $wpdb->get_col("DESCRIBE {$cache_table}");

        if (!in_array('expires_at', $cache_columns)) {
            $wpdb->query(
                "ALTER TABLE {$cache_table} 
                 ADD COLUMN expires_at datetime AFTER last_used_at"
            );

            // Set expiration for existing entries (90 days from now)
            $wpdb->query(
                "UPDATE {$cache_table} 
                 SET expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY) 
                 WHERE expires_at IS NULL"
            );

            // Add index
            $wpdb->query("ALTER TABLE {$cache_table} ADD INDEX expires_at (expires_at)");
        }

        return true;
    }

    /**
     * Backup important options before migration
     *
     * @since 2.0.0
     * @return void
     */
    private function backup_options() {
        $backup = [
            'abc_db_version' => get_option('abc_db_version'),
            'abc_version' => get_option('abc_version'),
            'timestamp' => time(),
        ];

        update_option('abc_migration_backup', $backup);
    }

    /**
     * Rollback migration
     *
     * Attempts to restore previous state after failed migration.
     *
     * @since 2.0.0
     * @return void
     */
    private function rollback() {
        $backup = get_option('abc_migration_backup');

        if ($backup && isset($backup['abc_db_version'])) {
            update_option('abc_db_version', $backup['abc_db_version']);
            
            Logger::log(0, 'warning', 'migration', 'Migration rolled back', [
                'restored_version' => $backup['abc_db_version'],
            ]);
        }
    }

    /**
     * Finalize migration
     *
     * Runs validation and cleanup after successful migration.
     *
     * @since 2.0.0
     * @return bool
     */
    private function finalize_migration() {
        // Update version to target
        update_option('abc_db_version', $this->target_version);

        // Validate schema
        $validator = new Schema_Validator();
        $is_valid = $validator->validate_all();

        if (!$is_valid) {
            Logger::log(0, 'warning', 'migration', 'Schema validation failed after migration');
        }

        // Clear migration backup
        delete_option('abc_migration_backup');

        Logger::log(0, 'success', 'migration', 'Database migration completed', [
            'version' => $this->target_version,
            'schema_valid' => $is_valid,
        ]);

        return true;
    }

    /**
     * Get current database version
     *
     * @since 2.0.0
     * @return string
     */
    public function get_current_version() {
        return get_option('abc_db_version', '0.0.0');
    }

    /**
     * Get migration history
     *
     * @since 2.0.0
     * @return array
     */
    public function get_history() {
        return get_option('abc_migration_history', []);
    }

    /**
     * Check if migration is needed
     *
     * @since 2.0.0
     * @return bool
     */
    public function needs_migration() {
        return version_compare($this->current_version, $this->target_version, '<');
    }
}
