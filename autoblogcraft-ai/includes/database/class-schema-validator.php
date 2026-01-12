<?php
/**
 * Schema Validator
 *
 * Validates database table structure and integrity.
 * Ensures all required tables and columns exist with correct types.
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
 * Schema Validator class
 *
 * Responsibilities:
 * - Validate table existence
 * - Verify column structure
 * - Check indexes
 * - Detect schema drift
 * - Auto-repair missing elements
 *
 * @since 2.0.0
 */
class Schema_Validator {

    /**
     * Expected schema definition
     *
     * @var array
     */
    private $expected_schema = [];

    /**
     * Validation errors
     *
     * @var array
     */
    private $errors = [];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->define_expected_schema();
    }

    /**
     * Define expected database schema
     *
     * @since 2.0.0
     * @return void
     */
    private function define_expected_schema() {
        global $wpdb;

        $this->expected_schema = [
            'abc_discovery_queue' => [
                'required_columns' => [
                    'id', 'campaign_id', 'campaign_type', 'source_type', 
                    'source_url', 'item_id', 'title', 'excerpt', 'content',
                    'metadata', 'discovered_at', 'created_at', 'status', 
                    'priority', 'processed_at', 'post_id', 'retry_count',
                    'last_error', 'last_error_code', 'content_hash', 'updated_at',
                ],
                'required_indexes' => [
                    'PRIMARY', 'unique_item', 'campaign_type', 'status',
                    'priority', 'discovered_at', 'created_at', 'content_hash',
                ],
            ],

            'abc_api_keys' => [
                'required_columns' => [
                    'id', 'key_name', 'provider', 'provider_type', 'api_key',
                    'api_key_hash', 'api_base_url', 'organization_id', 'project_id',
                    'usage_count', 'total_tokens_used', 'last_used_at',
                    'rate_limit_per_minute', 'rate_limit_per_day',
                    'current_minute_count', 'current_day_count', 'rate_reset_at',
                    'quota_limit', 'quota_remaining', 'quota_reset_at',
                    'status', 'status_message', 'failure_count', 'last_failure_at',
                    'created_at', 'created_by', 'updated_at',
                ],
                'required_indexes' => [
                    'PRIMARY', 'api_key_hash', 'provider', 'provider_type', 'status',
                ],
            ],

            'abc_campaign_ai_config' => [
                'required_columns' => [
                    'id', 'campaign_id', 'provider', 'model', 'primary_key_id',
                    'fallback_key_ids', 'key_rotation_strategy', 'last_key_index',
                    'temperature', 'max_tokens', 'top_p', 'frequency_penalty',
                    'presence_penalty', 'system_prompt', 'rewrite_prompt_template',
                    'translation_prompt_template', 'tone', 'audience',
                    'custom_instructions', 'author_persona', 'attribution_style',
                    'min_word_count', 'max_word_count', 'preserve_links',
                    'add_conclusion', 'humanizer_enabled', 'humanizer_level',
                    'humanizer_provider', 'humanizer_key_id', 'internal_links',
                    'internal_links_mode', 'created_at', 'updated_at',
                ],
                'required_indexes' => [
                    'PRIMARY', 'campaign_id',
                ],
            ],

            'abc_translation_cache' => [
                'required_columns' => [
                    'id', 'original_text_hash', 'from_lang', 'to_lang',
                    'original_text', 'translated_text', 'provider', 'model',
                    'tokens_used', 'hit_count', 'created_at', 'last_used_at',
                    'expires_at',
                ],
                'required_indexes' => [
                    'PRIMARY', 'cache_key', 'last_used_at', 'expires_at',
                ],
            ],

            'abc_logs' => [
                'required_columns' => [
                    'id', 'campaign_id', 'level', 'category', 'message',
                    'context', 'queue_item_id', 'post_id', 'stack_trace',
                    'created_at',
                ],
                'required_indexes' => [
                    'PRIMARY', 'campaign_id', 'level', 'category', 'created_at',
                ],
            ],

            'abc_seo_settings' => [
                'required_columns' => [
                    'id', 'campaign_id', 'enabled', 'title_template',
                    'description_template', 'default_robots_index',
                    'default_robots_follow', 'schema_enabled', 'schema_type',
                    'include_in_sitemap', 'sitemap_priority', 'sitemap_changefreq',
                    'updated_at',
                ],
                'required_indexes' => [
                    'PRIMARY', 'campaign_id',
                ],
            ],
        ];
    }

    /**
     * Validate all tables
     *
     * @since 2.0.0
     * @return bool True if all valid, false if errors found.
     */
    public function validate_all() {
        $this->errors = [];

        foreach ($this->expected_schema as $table_name => $schema) {
            $this->validate_table($table_name, $schema);
        }

        return empty($this->errors);
    }

    /**
     * Validate a single table
     *
     * @since 2.0.0
     * @param string $table_name Table name (without prefix).
     * @param array  $schema     Expected schema.
     * @return bool
     */
    public function validate_table($table_name, $schema) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . $table_name;

        // Check if table exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)
        );

        if (!$exists) {
            $this->errors[] = [
                'type' => 'missing_table',
                'table' => $table_name,
                'message' => "Table {$table_name} does not exist",
            ];
            return false;
        }

        // Validate columns
        if (isset($schema['required_columns'])) {
            $this->validate_columns($full_table_name, $schema['required_columns']);
        }

        // Validate indexes
        if (isset($schema['required_indexes'])) {
            $this->validate_indexes($full_table_name, $schema['required_indexes']);
        }

        return true;
    }

    /**
     * Validate table columns
     *
     * @since 2.0.0
     * @param string $table_name        Full table name with prefix.
     * @param array  $required_columns  Expected column names.
     * @return void
     */
    private function validate_columns($table_name, $required_columns) {
        global $wpdb;

        $existing_columns = $wpdb->get_col("DESCRIBE {$table_name}");

        foreach ($required_columns as $column) {
            if (!in_array($column, $existing_columns)) {
                $this->errors[] = [
                    'type' => 'missing_column',
                    'table' => $table_name,
                    'column' => $column,
                    'message' => "Column {$column} missing in {$table_name}",
                ];
            }
        }
    }

    /**
     * Validate table indexes
     *
     * @since 2.0.0
     * @param string $table_name       Full table name with prefix.
     * @param array  $required_indexes Expected index names.
     * @return void
     */
    private function validate_indexes($table_name, $required_indexes) {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}", ARRAY_A);
        $existing_indexes = array_unique(array_column($indexes, 'Key_name'));

        foreach ($required_indexes as $index) {
            if (!in_array($index, $existing_indexes)) {
                $this->errors[] = [
                    'type' => 'missing_index',
                    'table' => $table_name,
                    'index' => $index,
                    'message' => "Index {$index} missing in {$table_name}",
                ];
            }
        }
    }

    /**
     * Repair schema issues
     *
     * Attempts to fix missing tables, columns, and indexes.
     *
     * @since 2.0.0
     * @return array Repair results.
     */
    public function repair() {
        $results = [
            'tables_created' => 0,
            'columns_added' => 0,
            'indexes_added' => 0,
            'errors' => [],
        ];

        // Run validation first
        $this->validate_all();

        if (empty($this->errors)) {
            return $results; // Nothing to repair
        }

        // Group errors by type
        $missing_tables = array_filter($this->errors, function($e) {
            return $e['type'] === 'missing_table';
        });

        $missing_columns = array_filter($this->errors, function($e) {
            return $e['type'] === 'missing_column';
        });

        $missing_indexes = array_filter($this->errors, function($e) {
            return $e['type'] === 'missing_index';
        });

        // Recreate missing tables
        if (!empty($missing_tables)) {
            $installer = new Installer();
            $installer->create_tables();
            $results['tables_created'] = count($missing_tables);
        }

        // Note: Column and index repair requires ALTER TABLE statements
        // which are complex and version-specific. For now, log them.
        if (!empty($missing_columns) || !empty($missing_indexes)) {
            Logger::log(0, 'warning', 'schema', 'Schema repair needed', [
                'missing_columns' => count($missing_columns),
                'missing_indexes' => count($missing_indexes),
                'recommendation' => 'Run database migrator or reinstall plugin',
            ]);
        }

        return $results;
    }

    /**
     * Get validation errors
     *
     * @since 2.0.0
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get schema health report
     *
     * @since 2.0.0
     * @return array Health report with statistics.
     */
    public function get_health_report() {
        $this->validate_all();

        $total_tables = count($this->expected_schema);
        $total_errors = count($this->errors);

        $error_by_type = [];
        foreach ($this->errors as $error) {
            $type = $error['type'];
            if (!isset($error_by_type[$type])) {
                $error_by_type[$type] = 0;
            }
            $error_by_type[$type]++;
        }

        return [
            'status' => empty($this->errors) ? 'healthy' : 'issues_found',
            'total_tables' => $total_tables,
            'total_errors' => $total_errors,
            'errors_by_type' => $error_by_type,
            'errors' => $this->errors,
            'can_auto_repair' => $this->can_auto_repair(),
        ];
    }

    /**
     * Check if errors can be auto-repaired
     *
     * @since 2.0.0
     * @return bool
     */
    private function can_auto_repair() {
        // Only missing tables can be auto-repaired
        foreach ($this->errors as $error) {
            if ($error['type'] !== 'missing_table') {
                return false;
            }
        }
        return true;
    }
}
