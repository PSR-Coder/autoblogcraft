<?php
/**
 * Plugin Constants
 *
 * Defines all constants used throughout AutoBlogCraft plugin.
 * Must be loaded before any other plugin files.
 *
 * @package AutoBlogCraft\Helpers
 * @since 2.0.0
 */

namespace AutoBlogCraft\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin version
 */
if (!defined('ABC_VERSION')) {
    define('ABC_VERSION', '2.0.0');
}

/**
 * Database version
 */
if (!defined('ABC_DB_VERSION')) {
    define('ABC_DB_VERSION', '2.0.0');
}

/**
 * Plugin file path (should be set by main plugin file)
 */
if (!defined('ABC_PLUGIN_FILE')) {
    define('ABC_PLUGIN_FILE', dirname(dirname(dirname(__FILE__))) . '/autoblogcraft-ai.php');
}

/**
 * Plugin directory path
 */
if (!defined('ABC_PLUGIN_DIR')) {
    define('ABC_PLUGIN_DIR', dirname(dirname(dirname(__FILE__))));
}

/**
 * Plugin URL
 */
if (!defined('ABC_PLUGIN_URL')) {
    define('ABC_PLUGIN_URL', plugin_dir_url(ABC_PLUGIN_FILE));
}

/**
 * Includes directory
 */
if (!defined('ABC_INCLUDES_DIR')) {
    define('ABC_INCLUDES_DIR', ABC_PLUGIN_DIR . '/includes');
}

/**
 * Assets directory
 */
if (!defined('ABC_ASSETS_DIR')) {
    define('ABC_ASSETS_DIR', ABC_PLUGIN_DIR . '/assets');
}

/**
 * Assets URL
 */
if (!defined('ABC_ASSETS_URL')) {
    define('ABC_ASSETS_URL', ABC_PLUGIN_URL . 'assets');
}

/**
 * Templates directory
 */
if (!defined('ABC_TEMPLATES_DIR')) {
    define('ABC_TEMPLATES_DIR', ABC_PLUGIN_DIR . '/templates');
}

/**
 * Logs directory
 */
if (!defined('ABC_LOGS_DIR')) {
    define('ABC_LOGS_DIR', WP_CONTENT_DIR . '/autoblogcraft-logs');
}

// ========================================
// Campaign Types
// ========================================

if (!defined('ABC_CAMPAIGN_TYPE_RSS')) {
    define('ABC_CAMPAIGN_TYPE_RSS', 'rss');
}

if (!defined('ABC_CAMPAIGN_TYPE_YOUTUBE')) {
    define('ABC_CAMPAIGN_TYPE_YOUTUBE', 'youtube');
}

if (!defined('ABC_CAMPAIGN_TYPE_AMAZON')) {
    define('ABC_CAMPAIGN_TYPE_AMAZON', 'amazon');
}

if (!defined('ABC_CAMPAIGN_TYPE_NEWS')) {
    define('ABC_CAMPAIGN_TYPE_NEWS', 'news');
}

if (!defined('ABC_CAMPAIGN_TYPE_WEBSITE')) {
    define('ABC_CAMPAIGN_TYPE_WEBSITE', 'website');
}

// ========================================
// Campaign Statuses
// ========================================

if (!defined('ABC_STATUS_ACTIVE')) {
    define('ABC_STATUS_ACTIVE', 'active');
}

if (!defined('ABC_STATUS_PAUSED')) {
    define('ABC_STATUS_PAUSED', 'paused');
}

if (!defined('ABC_STATUS_DRAFT')) {
    define('ABC_STATUS_DRAFT', 'draft');
}

// ========================================
// Queue Statuses
// ========================================

if (!defined('ABC_QUEUE_PENDING')) {
    define('ABC_QUEUE_PENDING', 'pending');
}

if (!defined('ABC_QUEUE_PROCESSING')) {
    define('ABC_QUEUE_PROCESSING', 'processing');
}

if (!defined('ABC_QUEUE_COMPLETED')) {
    define('ABC_QUEUE_COMPLETED', 'completed');
}

if (!defined('ABC_QUEUE_FAILED')) {
    define('ABC_QUEUE_FAILED', 'failed');
}

if (!defined('ABC_QUEUE_SKIPPED')) {
    define('ABC_QUEUE_SKIPPED', 'skipped');
}

// ========================================
// AI Providers
// ========================================

if (!defined('ABC_PROVIDER_OPENAI')) {
    define('ABC_PROVIDER_OPENAI', 'openai');
}

if (!defined('ABC_PROVIDER_ANTHROPIC')) {
    define('ABC_PROVIDER_ANTHROPIC', 'anthropic');
}

if (!defined('ABC_PROVIDER_GEMINI')) {
    define('ABC_PROVIDER_GEMINI', 'gemini');
}

if (!defined('ABC_PROVIDER_COHERE')) {
    define('ABC_PROVIDER_COHERE', 'cohere');
}

if (!defined('ABC_PROVIDER_CUSTOM')) {
    define('ABC_PROVIDER_CUSTOM', 'custom');
}

// ========================================
// Translation Providers
// ========================================

if (!defined('ABC_TRANS_PROVIDER_GOOGLE')) {
    define('ABC_TRANS_PROVIDER_GOOGLE', 'google');
}

if (!defined('ABC_TRANS_PROVIDER_DEEPL')) {
    define('ABC_TRANS_PROVIDER_DEEPL', 'deepl');
}

if (!defined('ABC_TRANS_PROVIDER_OPENAI')) {
    define('ABC_TRANS_PROVIDER_OPENAI', 'openai');
}

// ========================================
// Rate Limits
// ========================================

if (!defined('ABC_DEFAULT_RATE_LIMIT_PER_MINUTE')) {
    define('ABC_DEFAULT_RATE_LIMIT_PER_MINUTE', 60);
}

if (!defined('ABC_DEFAULT_RATE_LIMIT_PER_DAY')) {
    define('ABC_DEFAULT_RATE_LIMIT_PER_DAY', 10000);
}

if (!defined('ABC_MAX_QUEUE_SIZE')) {
    define('ABC_MAX_QUEUE_SIZE', 10000);
}

if (!defined('ABC_MAX_RETRY_COUNT')) {
    define('ABC_MAX_RETRY_COUNT', 3);
}

// ========================================
// Cache Durations
// ========================================

if (!defined('ABC_CACHE_DURATION_SHORT')) {
    define('ABC_CACHE_DURATION_SHORT', 3600); // 1 hour
}

if (!defined('ABC_CACHE_DURATION_MEDIUM')) {
    define('ABC_CACHE_DURATION_MEDIUM', 86400); // 1 day
}

if (!defined('ABC_CACHE_DURATION_LONG')) {
    define('ABC_CACHE_DURATION_LONG', 604800); // 1 week
}

if (!defined('ABC_TRANSLATION_CACHE_DURATION')) {
    define('ABC_TRANSLATION_CACHE_DURATION', 7776000); // 90 days
}

// ========================================
// Retention Periods
// ========================================

if (!defined('ABC_QUEUE_RETENTION_DAYS')) {
    define('ABC_QUEUE_RETENTION_DAYS', 30);
}

if (!defined('ABC_LOG_RETENTION_DEBUG')) {
    define('ABC_LOG_RETENTION_DEBUG', 7); // days
}

if (!defined('ABC_LOG_RETENTION_INFO')) {
    define('ABC_LOG_RETENTION_INFO', 30); // days
}

if (!defined('ABC_LOG_RETENTION_ERROR')) {
    define('ABC_LOG_RETENTION_ERROR', 60); // days
}

// ========================================
// Content Limits
// ========================================

if (!defined('ABC_MAX_CONTENT_LENGTH')) {
    define('ABC_MAX_CONTENT_LENGTH', 100000); // characters
}

if (!defined('ABC_MAX_TITLE_LENGTH')) {
    define('ABC_MAX_TITLE_LENGTH', 200);
}

if (!defined('ABC_MAX_EXCERPT_LENGTH')) {
    define('ABC_MAX_EXCERPT_LENGTH', 500);
}

// ========================================
// Processing
// ========================================

if (!defined('ABC_BATCH_SIZE')) {
    define('ABC_BATCH_SIZE', 10);
}

if (!defined('ABC_PROCESSING_TIMEOUT')) {
    define('ABC_PROCESSING_TIMEOUT', 300); // 5 minutes
}

if (!defined('ABC_DISCOVERY_BATCH_SIZE')) {
    define('ABC_DISCOVERY_BATCH_SIZE', 5);
}

// ========================================
// Humanizer
// ========================================

if (!defined('ABC_HUMANIZER_LEVEL_LOW')) {
    define('ABC_HUMANIZER_LEVEL_LOW', 'low');
}

if (!defined('ABC_HUMANIZER_LEVEL_MEDIUM')) {
    define('ABC_HUMANIZER_LEVEL_MEDIUM', 'medium');
}

if (!defined('ABC_HUMANIZER_LEVEL_HIGH')) {
    define('ABC_HUMANIZER_LEVEL_HIGH', 'high');
}

// ========================================
// Priority Levels
// ========================================

if (!defined('ABC_PRIORITY_LOW')) {
    define('ABC_PRIORITY_LOW', 1);
}

if (!defined('ABC_PRIORITY_NORMAL')) {
    define('ABC_PRIORITY_NORMAL', 5);
}

if (!defined('ABC_PRIORITY_HIGH')) {
    define('ABC_PRIORITY_HIGH', 10);
}

// ========================================
// Log Levels
// ========================================

if (!defined('ABC_LOG_LEVEL_DEBUG')) {
    define('ABC_LOG_LEVEL_DEBUG', 'debug');
}

if (!defined('ABC_LOG_LEVEL_INFO')) {
    define('ABC_LOG_LEVEL_INFO', 'info');
}

if (!defined('ABC_LOG_LEVEL_WARNING')) {
    define('ABC_LOG_LEVEL_WARNING', 'warning');
}

if (!defined('ABC_LOG_LEVEL_ERROR')) {
    define('ABC_LOG_LEVEL_ERROR', 'error');
}

// ========================================
// API Timeouts
// ========================================

if (!defined('ABC_API_TIMEOUT')) {
    define('ABC_API_TIMEOUT', 30); // seconds
}

if (!defined('ABC_API_CONNECT_TIMEOUT')) {
    define('ABC_API_CONNECT_TIMEOUT', 10); // seconds
}

// ========================================
// SEO
// ========================================

if (!defined('ABC_SEO_META_DESCRIPTION_LENGTH')) {
    define('ABC_SEO_META_DESCRIPTION_LENGTH', 160);
}

if (!defined('ABC_SEO_MAX_INTERNAL_LINKS')) {
    define('ABC_SEO_MAX_INTERNAL_LINKS', 5);
}

if (!defined('ABC_SEO_MIN_CONTENT_LENGTH')) {
    define('ABC_SEO_MIN_CONTENT_LENGTH', 300);
}

// ========================================
// Capabilities
// ========================================

if (!defined('ABC_CAP_MANAGE')) {
    define('ABC_CAP_MANAGE', 'manage_autoblogcraft');
}

if (!defined('ABC_CAP_EDIT_CAMPAIGNS')) {
    define('ABC_CAP_EDIT_CAMPAIGNS', 'edit_abc_campaigns');
}

if (!defined('ABC_CAP_DELETE_CAMPAIGNS')) {
    define('ABC_CAP_DELETE_CAMPAIGNS', 'delete_abc_campaigns');
}

// ========================================
// Cron Jobs
// ========================================

if (!defined('ABC_CRON_DISCOVERY')) {
    define('ABC_CRON_DISCOVERY', 'abc_discovery_cron');
}

if (!defined('ABC_CRON_PROCESSING')) {
    define('ABC_CRON_PROCESSING', 'abc_processing_cron');
}

if (!defined('ABC_CRON_CLEANUP')) {
    define('ABC_CRON_CLEANUP', 'abc_cleanup_cron');
}

if (!defined('ABC_CRON_RATE_LIMIT_RESET')) {
    define('ABC_CRON_RATE_LIMIT_RESET', 'abc_rate_limit_reset_cron');
}

// ========================================
// Debug
// ========================================

if (!defined('ABC_DEBUG')) {
    define('ABC_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
}
