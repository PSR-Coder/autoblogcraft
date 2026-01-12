<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 *
 * @package AutoBlogCraft
 * @since 2.0.0
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Option to keep data on uninstall (user preference)
$keep_data = get_option('abc_keep_data_on_uninstall', false);

if (!$keep_data) {
    // Delete custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}abc_discovery_queue");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}abc_api_keys");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}abc_campaign_ai_config");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}abc_translation_cache");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}abc_logs");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}abc_seo_settings");

    // Delete all campaign posts
    $campaigns = get_posts([
        'post_type' => 'abc_campaign',
        'numberposts' => -1,
        'post_status' => 'any',
    ]);

    foreach ($campaigns as $campaign) {
        wp_delete_post($campaign->ID, true);
    }

    // Delete all plugin options
    delete_option('autoblogcraft_ai_db_version');
    delete_option('abc_max_concurrent_campaigns');
    delete_option('abc_max_concurrent_ai_calls');
    delete_option('abc_queue_retention_days');
    delete_option('abc_log_retention_days');
    delete_option('abc_cache_ttl_days');
    delete_option('abc_keep_data_on_uninstall');

    // Clear scheduled hooks (Action Scheduler)
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('abc_discovery_hook', [], 'autoblogcraft');
        as_unschedule_all_actions('abc_processing_hook', [], 'autoblogcraft');
        as_unschedule_all_actions('abc_cleanup_hook', [], 'autoblogcraft');
        as_unschedule_all_actions('abc_rate_limit_reset_hook', [], 'autoblogcraft');
    }
}

// Clear all caches
wp_cache_flush();
