<?php
/**
 * Database Installer
 *
 * @package AutoBlogCraft\Database
 * @since 2.0.0
 */

namespace AutoBlogCraft\Database;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Installer
 *
 * Handles database table creation and version migrations
 */
class Installer {

    /**
     * Current database version
     */
    const DB_VERSION = '2.0.0';

    /**
     * Database version option name
     */
    const DB_VERSION_OPTION = 'autoblogcraft_ai_db_version';

    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_custom_post_types();
        self::set_default_options();
        
        // Update database version
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Check database version and migrate if needed
     */
    public static function check_version() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '0');

        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::activate();
        }
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Table: wp_abc_discovery_queue
        $sql_queue = "CREATE TABLE {$wpdb->prefix}abc_discovery_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            campaign_type varchar(20) NOT NULL,
            source_type varchar(50) NOT NULL,
            source_url text NOT NULL,
            item_id varchar(255) DEFAULT NULL,
            title text,
            excerpt text,
            content longtext,
            metadata longtext,
            discovered_source_url text,
            freshness_timestamp datetime DEFAULT NULL,
            attribution_data longtext,
            discovered_at datetime NOT NULL,
            created_at datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            priority tinyint DEFAULT 5,
            processed_at datetime DEFAULT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            retry_count int DEFAULT 0,
            last_error text,
            last_error_code varchar(50) DEFAULT NULL,
            content_hash char(64) DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_item (campaign_id, item_id),
            KEY campaign_type (campaign_type),
            KEY status (status),
            KEY priority (priority),
            KEY discovered_at (discovered_at),
            KEY created_at (created_at),
            KEY content_hash (content_hash)
        ) $charset_collate;";

        // Table: wp_abc_api_keys
        $sql_keys = "CREATE TABLE {$wpdb->prefix}abc_api_keys (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            key_name varchar(100) NOT NULL,
            provider varchar(50) NOT NULL,
            provider_type varchar(30) DEFAULT 'ai',
            api_key text NOT NULL,
            api_key_hash char(64) NOT NULL,
            api_base_url varchar(255) DEFAULT NULL,
            organization_id varchar(100) DEFAULT NULL,
            project_id varchar(100) DEFAULT NULL,
            usage_count int DEFAULT 0,
            total_tokens_used bigint DEFAULT 0,
            last_used_at datetime DEFAULT NULL,
            rate_limit_per_minute int DEFAULT NULL,
            rate_limit_per_day int DEFAULT NULL,
            rate_limit_updated_at datetime DEFAULT NULL,
            current_minute_count int DEFAULT 0,
            current_day_count int DEFAULT 0,
            rate_reset_at datetime DEFAULT NULL,
            quota_limit bigint DEFAULT NULL,
            quota_remaining bigint DEFAULT NULL,
            quota_reset_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            status_message text,
            failure_count int DEFAULT 0,
            last_failure_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY api_key_hash (api_key_hash),
            KEY provider (provider),
            KEY provider_type (provider_type),
            KEY status (status)
        ) $charset_collate;";

        // Table: wp_abc_campaign_ai_config
        $sql_ai_config = "CREATE TABLE {$wpdb->prefix}abc_campaign_ai_config (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            provider varchar(50) NOT NULL,
            model varchar(100) NOT NULL,
            primary_key_id bigint(20) unsigned DEFAULT NULL,
            fallback_key_ids text,
            key_rotation_strategy varchar(30) DEFAULT 'round_robin',
            last_key_index int DEFAULT 0,
            temperature decimal(3,2) DEFAULT 0.70,
            max_tokens int DEFAULT 2048,
            top_p decimal(3,2) DEFAULT 1.00,
            frequency_penalty decimal(3,2) DEFAULT 0.00,
            presence_penalty decimal(3,2) DEFAULT 0.00,
            system_prompt longtext,
            rewrite_prompt_template longtext,
            translation_prompt_template longtext,
            tone varchar(50) DEFAULT NULL,
            audience varchar(50) DEFAULT NULL,
            custom_instructions text,
            author_persona text,
            attribution_style varchar(50) DEFAULT NULL,
            min_word_count int DEFAULT 300,
            max_word_count int DEFAULT 2000,
            preserve_links tinyint(1) DEFAULT 0,
            add_conclusion tinyint(1) DEFAULT 1,
            humanizer_enabled tinyint(1) DEFAULT 0,
            humanizer_level tinyint DEFAULT 5,
            humanizer_provider varchar(50) DEFAULT NULL,
            humanizer_key_id bigint(20) unsigned DEFAULT NULL,
            internal_links longtext,
            internal_links_mode varchar(30) DEFAULT 'regex',
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_id (campaign_id)
        ) $charset_collate;";

        // Table: wp_abc_translation_cache
        $sql_translation = "CREATE TABLE {$wpdb->prefix}abc_translation_cache (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            original_text_hash char(64) NOT NULL,
            from_lang varchar(10) NOT NULL,
            to_lang varchar(10) NOT NULL,
            original_text longtext NOT NULL,
            translated_text longtext NOT NULL,
            provider varchar(50) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            tokens_used int DEFAULT NULL,
            hit_count int DEFAULT 0,
            created_at datetime NOT NULL,
            last_used_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (original_text_hash, from_lang, to_lang),
            KEY last_used_at (last_used_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Table: wp_abc_logs
        $sql_logs = "CREATE TABLE {$wpdb->prefix}abc_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned DEFAULT NULL,
            level varchar(20) NOT NULL,
            category varchar(50) NOT NULL,
            message text NOT NULL,
            context text,
            queue_item_id bigint(20) unsigned DEFAULT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            stack_trace text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY level (level),
            KEY category (category),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Table: wp_abc_seo_settings
        $sql_seo = "CREATE TABLE {$wpdb->prefix}abc_seo_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            enabled tinyint(1) DEFAULT 1,
            title_template varchar(255) DEFAULT NULL,
            description_template varchar(255) DEFAULT NULL,
            default_robots_index varchar(10) DEFAULT 'index',
            default_robots_follow varchar(10) DEFAULT 'follow',
            schema_enabled tinyint(1) DEFAULT 1,
            schema_type varchar(50) DEFAULT 'Article',
            include_in_sitemap tinyint(1) DEFAULT 1,
            sitemap_priority decimal(2,1) DEFAULT 0.5,
            sitemap_changefreq varchar(20) DEFAULT 'weekly',
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_id (campaign_id)
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql_queue);
        dbDelta($sql_keys);
        dbDelta($sql_ai_config);
        dbDelta($sql_translation);
        dbDelta($sql_logs);
        dbDelta($sql_seo);
    }

    /**
     * Register custom post type for campaigns
     */
    private static function create_custom_post_types() {
        register_post_type('abc_campaign', [
            'labels' => [
                'name' => __('Campaigns', 'autoblogcraft-ai'),
                'singular_name' => __('Campaign', 'autoblogcraft-ai'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'supports' => ['title'],
            'rewrite' => false,
        ]);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        // Global rate limiting settings
        add_option('abc_max_concurrent_campaigns', 3);
        add_option('abc_max_concurrent_ai_calls', 10);
        
        // Cleanup settings
        add_option('abc_queue_retention_days', 30);
        add_option('abc_log_retention_days', 30);
        add_option('abc_cache_ttl_days', 90);
    }
}
