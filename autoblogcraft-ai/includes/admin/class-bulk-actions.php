<?php
/**
 * Bulk Actions Handler
 *
 * Handles bulk operations on campaigns from the campaigns list page.
 * Provides mass pause, resume, delete, and clone functionality.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bulk Actions class
 *
 * Responsibilities:
 * - Register bulk actions
 * - Handle bulk pause campaigns
 * - Handle bulk resume campaigns
 * - Handle bulk delete campaigns
 * - Handle bulk clone campaigns
 * - Provide user feedback
 *
 * @since 2.0.0
 */
class Bulk_Actions {

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action('admin_init', [$this, 'handle_bulk_actions']);
        add_action('wp_ajax_abc_bulk_action', [$this, 'ajax_bulk_action']);
    }

    /**
     * Handle bulk actions from form submission
     *
     * @since 2.0.0
     * @return void
     */
    public function handle_bulk_actions() {
        // Check if we're on campaigns page
        if (!isset($_GET['page']) || $_GET['page'] !== 'autoblogcraft-campaigns') {
            return;
        }

        // Check for bulk action
        if (!isset($_POST['bulk_action']) || !isset($_POST['campaign_ids'])) {
            return;
        }

        // Verify nonce
        if (!check_admin_referer('abc_bulk_action', 'abc_bulk_nonce')) {
            wp_die(__('Security check failed', 'autoblogcraft'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblogcraft'));
        }

        $action = sanitize_key($_POST['bulk_action']);
        $campaign_ids = array_map('intval', $_POST['campaign_ids']);

        $result = $this->process_bulk_action($action, $campaign_ids);

        // Redirect with success message
        wp_safe_redirect(add_query_arg([
            'bulk_action' => $action,
            'processed' => $result['processed'],
            'errors' => $result['errors'],
        ], admin_url('admin.php?page=autoblogcraft-campaigns')));
        exit;
    }

    /**
     * Handle AJAX bulk actions
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_bulk_action() {
        check_ajax_referer('abc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'autoblogcraft')]);
        }

        $action = isset($_POST['action_type']) ? sanitize_key($_POST['action_type']) : '';
        $campaign_ids = isset($_POST['campaign_ids']) ? array_map('intval', $_POST['campaign_ids']) : [];

        if (empty($campaign_ids)) {
            wp_send_json_error(['message' => __('No campaigns selected', 'autoblogcraft')]);
        }

        $result = $this->process_bulk_action($action, $campaign_ids);

        if ($result['errors'] > 0) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Processed %d campaigns with %d errors', 'autoblogcraft'),
                    $result['processed'],
                    $result['errors']
                ),
                'processed' => $result['processed'],
                'errors' => $result['errors'],
            ]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Successfully processed %d campaigns', 'autoblogcraft'),
                $result['processed']
            ),
            'processed' => $result['processed'],
        ]);
    }

    /**
     * Process bulk action
     *
     * @since 2.0.0
     * @param string $action       Bulk action to perform.
     * @param array  $campaign_ids Campaign IDs.
     * @return array Result with processed and error counts.
     */
    private function process_bulk_action($action, $campaign_ids) {
        $result = [
            'processed' => 0,
            'errors' => 0,
        ];

        foreach ($campaign_ids as $campaign_id) {
            $success = false;

            switch ($action) {
                case 'pause':
                    $success = $this->pause_campaign($campaign_id);
                    break;

                case 'resume':
                    $success = $this->resume_campaign($campaign_id);
                    break;

                case 'delete':
                    $success = $this->delete_campaign($campaign_id);
                    break;

                case 'clone':
                    $success = $this->clone_campaign($campaign_id);
                    break;

                default:
                    $result['errors']++;
                    continue 2;
            }

            if ($success) {
                $result['processed']++;
            } else {
                $result['errors']++;
            }
        }

        // Log bulk action
        Logger::log(0, 'info', 'admin', "Bulk action: {$action}", [
            'campaign_count' => count($campaign_ids),
            'processed' => $result['processed'],
            'errors' => $result['errors'],
        ]);

        return $result;
    }

    /**
     * Pause a campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool Success status.
     */
    private function pause_campaign($campaign_id) {
        $campaign = get_post($campaign_id);

        if (!$campaign || $campaign->post_type !== 'abc_campaign') {
            return false;
        }

        $current_status = get_post_meta($campaign_id, '_campaign_status', true);

        if ($current_status === 'paused') {
            return true; // Already paused
        }

        update_post_meta($campaign_id, '_campaign_status', 'paused');
        update_post_meta($campaign_id, '_paused_at', time());

        Logger::log($campaign_id, 'info', 'campaign', 'Campaign paused via bulk action');

        return true;
    }

    /**
     * Resume a campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool Success status.
     */
    private function resume_campaign($campaign_id) {
        $campaign = get_post($campaign_id);

        if (!$campaign || $campaign->post_type !== 'abc_campaign') {
            return false;
        }

        $current_status = get_post_meta($campaign_id, '_campaign_status', true);

        if ($current_status === 'active') {
            return true; // Already active
        }

        update_post_meta($campaign_id, '_campaign_status', 'active');
        delete_post_meta($campaign_id, '_paused_at');

        Logger::log($campaign_id, 'info', 'campaign', 'Campaign resumed via bulk action');

        return true;
    }

    /**
     * Delete a campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool Success status.
     */
    private function delete_campaign($campaign_id) {
        global $wpdb;

        $campaign = get_post($campaign_id);

        if (!$campaign || $campaign->post_type !== 'abc_campaign') {
            return false;
        }

        // Delete related data
        $wpdb->delete(
            $wpdb->prefix . 'abc_discovery_queue',
            ['campaign_id' => $campaign_id],
            ['%d']
        );

        $wpdb->delete(
            $wpdb->prefix . 'abc_campaign_ai_config',
            ['campaign_id' => $campaign_id],
            ['%d']
        );

        $wpdb->delete(
            $wpdb->prefix . 'abc_seo_settings',
            ['campaign_id' => $campaign_id],
            ['%d']
        );

        $wpdb->delete(
            $wpdb->prefix . 'abc_logs',
            ['campaign_id' => $campaign_id],
            ['%d']
        );

        // Delete the campaign post
        $deleted = wp_delete_post($campaign_id, true);

        if ($deleted) {
            Logger::log(0, 'info', 'campaign', "Campaign deleted via bulk action: {$campaign_id}");
            return true;
        }

        return false;
    }

    /**
     * Clone a campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID to clone.
     * @return bool Success status.
     */
    private function clone_campaign($campaign_id) {
        $campaign = get_post($campaign_id);

        if (!$campaign || $campaign->post_type !== 'abc_campaign') {
            return false;
        }

        // Create new campaign post
        $new_campaign_id = wp_insert_post([
            'post_type' => 'abc_campaign',
            'post_title' => $campaign->post_title . ' (Copy)',
            'post_status' => 'publish',
            'post_content' => $campaign->post_content,
        ]);

        if (is_wp_error($new_campaign_id)) {
            return false;
        }

        // Copy all post meta
        $meta = get_post_meta($campaign_id);
        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($new_campaign_id, $key, maybe_unserialize($value));
            }
        }

        // Set cloned campaign to paused status
        update_post_meta($new_campaign_id, '_campaign_status', 'paused');

        // Copy AI configuration
        global $wpdb;
        
        $ai_config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);

        if ($ai_config) {
            unset($ai_config['id']);
            $ai_config['campaign_id'] = $new_campaign_id;
            $wpdb->insert($wpdb->prefix . 'abc_campaign_ai_config', $ai_config);
        }

        // Copy SEO settings
        $seo_settings = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_seo_settings WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);

        if ($seo_settings) {
            unset($seo_settings['id']);
            $seo_settings['campaign_id'] = $new_campaign_id;
            $wpdb->insert($wpdb->prefix . 'abc_seo_settings', $seo_settings);
        }

        Logger::log($new_campaign_id, 'info', 'campaign', "Campaign cloned from: {$campaign_id}");

        return true;
    }

    /**
     * Get available bulk actions
     *
     * @since 2.0.0
     * @return array
     */
    public static function get_actions() {
        return [
            'pause' => __('Pause', 'autoblogcraft'),
            'resume' => __('Resume', 'autoblogcraft'),
            'delete' => __('Delete', 'autoblogcraft'),
            'clone' => __('Clone', 'autoblogcraft'),
        ];
    }
}
