<?php
/**
 * Admin Page - Settings
 *
 * Global plugin settings page.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Page class
 *
 * @since 2.0.0
 */
class Admin_Page_Settings extends Admin_Page_Base {

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['abc_save_settings']) && check_admin_referer('abc_save_settings')) {
            $this->save_settings();
        }

        ?>
        <div class="wrap abc-wrap">
            <?php
            $this->render_header(
                __('Settings', 'autoblogcraft'),
                __('Configure global plugin settings', 'autoblogcraft')
            );
            ?>

            <?php settings_errors('abc_settings'); ?>

            <div class="abc-page-content">
                <form method="post">
                    <?php wp_nonce_field('abc_save_settings'); ?>

                    <?php $this->render_general_settings(); ?>
                    <?php $this->render_processing_settings(); ?>
                    <?php $this->render_cleanup_settings(); ?>

                    <p class="submit">
                        <button type="submit" name="abc_save_settings" class="button button-primary button-large">
                            <?php _e('Save Settings', 'autoblogcraft'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render general settings
     *
     * @since 2.0.0
     */
    private function render_general_settings() {
        $this->render_card(__('General Settings', 'autoblogcraft'), function() {
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_cron"><?php _e('Enable Cron', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="enable_cron" id="enable_cron" value="1" 
                               <?php checked(get_option('abc_enable_cron', true)); ?>>
                        <p class="description">
                            <?php _e('Enable automated campaign discovery and processing.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="default_post_status"><?php _e('Default Post Status', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <select name="default_post_status" id="default_post_status" class="regular-text">
                            <option value="draft" <?php selected(get_option('abc_default_post_status', 'draft'), 'draft'); ?>><?php _e('Draft', 'autoblogcraft'); ?></option>
                            <option value="pending" <?php selected(get_option('abc_default_post_status', 'draft'), 'pending'); ?>><?php _e('Pending Review', 'autoblogcraft'); ?></option>
                            <option value="publish" <?php selected(get_option('abc_default_post_status', 'draft'), 'publish'); ?>><?php _e('Published', 'autoblogcraft'); ?></option>
                        </select>
                        <p class="description">
                            <?php _e('Default status for newly created posts.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="default_post_author"><?php _e('Default Post Author', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_dropdown_users([
                            'name' => 'default_post_author',
                            'id' => 'default_post_author',
                            'selected' => get_option('abc_default_post_author', get_current_user_id()),
                            'show_option_none' => __('Select Author', 'autoblogcraft'),
                        ]);
                        ?>
                        <p class="description">
                            <?php _e('Default author for newly created posts.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        });
    }

    /**
     * Render processing settings
     *
     * @since 2.0.0
     */
    private function render_processing_settings() {
        $this->render_card(__('Processing Settings', 'autoblogcraft'), function() {
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="processing_batch_size"><?php _e('Batch Size', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="processing_batch_size" id="processing_batch_size" 
                               value="<?php echo esc_attr(get_option('abc_processing_batch_size', 10)); ?>" 
                               min="1" max="100" class="small-text">
                        <p class="description">
                            <?php _e('Number of items to process in each batch.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="max_concurrent_campaigns"><?php _e('Max Concurrent Campaigns', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_concurrent_campaigns" id="max_concurrent_campaigns" 
                               value="<?php echo esc_attr(get_option('abc_max_concurrent_campaigns', 3)); ?>" 
                               min="1" max="20" class="small-text">
                        <p class="description">
                            <?php _e('Maximum campaigns to process simultaneously.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="max_concurrent_ai_calls"><?php _e('Max Concurrent AI Calls', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_concurrent_ai_calls" id="max_concurrent_ai_calls" 
                               value="<?php echo esc_attr(get_option('abc_max_concurrent_ai_calls', 10)); ?>" 
                               min="1" max="50" class="small-text">
                        <p class="description">
                            <?php _e('Maximum parallel AI API calls.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="max_retries"><?php _e('Max Retries', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_retries" id="max_retries" 
                               value="<?php echo esc_attr(get_option('abc_max_retries', 3)); ?>" 
                               min="0" max="10" class="small-text">
                        <p class="description">
                            <?php _e('Number of retry attempts for failed operations.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="enable_duplicate_check"><?php _e('Duplicate Check', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="enable_duplicate_check" id="enable_duplicate_check" value="1" 
                               <?php checked(get_option('abc_enable_duplicate_check', true)); ?>>
                        <p class="description">
                            <?php _e('Prevent duplicate content from being published.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        });
    }

    /**
     * Render cleanup settings
     *
     * @since 2.0.0
     */
    private function render_cleanup_settings() {
        $this->render_card(__('Cleanup Settings', 'autoblogcraft'), function() {
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="log_retention_days"><?php _e('Log Retention', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="log_retention_days" id="log_retention_days" 
                               value="<?php echo esc_attr(get_option('abc_log_retention_days', 30)); ?>" 
                               min="1" max="365" class="small-text">
                        <?php _e('days', 'autoblogcraft'); ?>
                        <p class="description">
                            <?php _e('Delete logs older than this many days.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="queue_retention_days"><?php _e('Queue Retention', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="queue_retention_days" id="queue_retention_days" 
                               value="<?php echo esc_attr(get_option('abc_queue_retention_days', 30)); ?>" 
                               min="1" max="365" class="small-text">
                        <?php _e('days', 'autoblogcraft'); ?>
                        <p class="description">
                            <?php _e('Delete completed queue items older than this many days.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="cache_retention_days"><?php _e('Cache Retention', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="cache_retention_days" id="cache_retention_days" 
                               value="<?php echo esc_attr(get_option('abc_cache_retention_days', 90)); ?>" 
                               min="1" max="365" class="small-text">
                        <?php _e('days', 'autoblogcraft'); ?>
                        <p class="description">
                            <?php _e('Delete cache entries older than this many days.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        });
    }

    /**
     * Save settings
     *
     * @since 2.0.0
     */
    private function save_settings() {
        // General settings
        update_option('abc_enable_cron', isset($_POST['enable_cron']) ? 1 : 0);
        update_option('abc_default_post_status', sanitize_text_field($_POST['default_post_status'] ?? 'draft'));
        update_option('abc_default_post_author', absint($_POST['default_post_author'] ?? 0));

        // Processing settings
        update_option('abc_processing_batch_size', absint($_POST['processing_batch_size'] ?? 10));
        update_option('abc_max_concurrent_campaigns', max(1, min(20, absint($_POST['max_concurrent_campaigns'] ?? 3))));
        update_option('abc_max_concurrent_ai_calls', max(1, min(50, absint($_POST['max_concurrent_ai_calls'] ?? 10))));
        update_option('abc_max_retries', absint($_POST['max_retries'] ?? 3));
        update_option('abc_enable_duplicate_check', isset($_POST['enable_duplicate_check']) ? 1 : 0);

        // Cleanup settings
        update_option('abc_log_retention_days', absint($_POST['log_retention_days'] ?? 30));
        update_option('abc_queue_retention_days', absint($_POST['queue_retention_days'] ?? 30));
        update_option('abc_cache_retention_days', absint($_POST['cache_retention_days'] ?? 90));

        add_settings_error('abc_settings', 'settings_updated', __('Settings saved successfully.', 'autoblogcraft'), 'success');
    }
}