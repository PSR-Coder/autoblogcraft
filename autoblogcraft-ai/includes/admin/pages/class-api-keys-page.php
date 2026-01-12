<?php
/**
 * Admin Page - API Keys
 *
 * API key management and monitoring page.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

use AutoBlogCraft\AI\Key_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Keys Page class
 *
 * @since 2.0.0
 */
class Admin_Page_API_Keys extends Admin_Page_Base {

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['abc_save_api_key']) && check_admin_referer('abc_save_api_key')) {
            $this->save_api_key();
        }

        if (isset($_POST['abc_delete_api_key']) && check_admin_referer('abc_delete_api_key')) {
            $this->delete_api_key();
        }

        ?>
        <div class="wrap abc-wrap">
            <?php
            $this->render_header(
                __('API Keys', 'autoblogcraft'),
                __('Manage AI provider API keys and monitor usage', 'autoblogcraft'),
                [
                    [
                        'label' => __('Add API Key', 'autoblogcraft'),
                        'url' => '#',
                        'class' => 'button-primary abc-show-add-key-form',
                        'icon' => 'dashicons-plus-alt',
                    ],
                ]
            );
            ?>

            <?php settings_errors('abc_api_keys'); ?>

            <div class="abc-page-content">
                <?php $this->render_add_key_form(); ?>
                <?php $this->render_api_keys_list(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render add key form
     *
     * @since 2.0.0
     */
    private function render_add_key_form() {
        ?>
        <div id="abc-add-key-form" class="abc-card" style="display: none;">
            <div class="abc-card-header">
                <h2 class="abc-card-title"><?php _e('Add API Key', 'autoblogcraft'); ?></h2>
            </div>
            <div class="abc-card-body">
                <form method="post">
                    <?php wp_nonce_field('abc_save_api_key'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="provider"><?php _e('Provider', 'autoblogcraft'); ?></label>
                            </th>
                            <td>
                                <select name="provider" id="provider" class="regular-text" required>
                                    <option value=""><?php _e('Select Provider', 'autoblogcraft'); ?></option>
                                    <option value="openai">OpenAI (GPT-4, DALL-E)</option>
                                    <option value="gemini">Google Gemini</option>
                                    <option value="claude">Anthropic Claude</option>
                                    <option value="deepseek">DeepSeek</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php _e('API Key', 'autoblogcraft'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="api_key" id="api_key" class="regular-text" required>
                                <p class="description"><?php _e('Your API key will be encrypted before storage.', 'autoblogcraft'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="label"><?php _e('Label', 'autoblogcraft'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="label" id="label" class="regular-text" placeholder="<?php esc_attr_e('My API Key', 'autoblogcraft'); ?>">
                                <p class="description"><?php _e('Optional label to identify this key.', 'autoblogcraft'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="quota_limit"><?php _e('Monthly Quota', 'autoblogcraft'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="quota_limit" id="quota_limit" class="regular-text" placeholder="0">
                                <p class="description"><?php _e('Maximum monthly spend in USD. 0 = unlimited.', 'autoblogcraft'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="abc_save_api_key" class="button button-primary">
                            <?php _e('Save API Key', 'autoblogcraft'); ?>
                        </button>
                        <button type="button" class="button abc-cancel-add-key">
                            <?php _e('Cancel', 'autoblogcraft'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render API keys list
     *
     * @since 2.0.0
     */
    private function render_api_keys_list() {
        // Manual loading to ensure Key_Manager is available
        if (!class_exists('AutoBlogCraft\\AI\\Key_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../ai/class-key-manager.php';
        }
        
        $key_manager = new Key_Manager();
        
        // Get all keys from all providers
        global $wpdb;
        $table_name = $wpdb->prefix . 'abc_api_keys';
        $keys = $wpdb->get_results(
            "SELECT id, provider, key_name, usage_count, status, created_at, last_used_at 
            FROM {$table_name}
            ORDER BY created_at DESC",
            ARRAY_A
        );
        
        if (empty($keys)) {
            $this->render_empty_state(
                __('No API keys configured', 'autoblogcraft'),
                __('Add your first API key to start using AI providers for content generation.', 'autoblogcraft'),
                [
                    'label' => __('Add API Key', 'autoblogcraft'),
                    'url' => '#',
                ]
            );
            return;
        }

        $columns = [
            'provider' => ['label' => __('Provider', 'autoblogcraft')],
            'label' => ['label' => __('Label', 'autoblogcraft')],
            'key' => ['label' => __('API Key', 'autoblogcraft')],
            'usage' => ['label' => __('Usage', 'autoblogcraft')],
            'status' => ['label' => __('Status', 'autoblogcraft')],
            'created' => ['label' => __('Created', 'autoblogcraft')],
            'actions' => ['label' => __('Actions', 'autoblogcraft'), 'class' => 'abc-text-right'],
        ];

        $rows = [];

        foreach ($keys as $key) {
            // Note: api_key is encrypted in database, we don't decrypt it for display
            $masked_key = '••••••••••••' . substr($key['id'], -4);
            
            $rows[] = [
                'provider' => sprintf(
                    '<strong>%s</strong>',
                    esc_html(ucfirst($key['provider']))
                ),
                'label' => esc_html($key['key_name'] ?: '—'),
                'key' => sprintf(
                    '<code class="abc-api-key">%s</code>',
                    esc_html($masked_key)
                ),
                'usage' => sprintf(
                    '<span class="abc-usage-text">%s %s</span>',
                    number_format($key['usage_count']),
                    __('requests', 'autoblogcraft')
                ),
                'status' => $this->format_status_badge($key['status']),
                'created' => $this->format_timestamp($key['created_at']),
                'actions' => $this->get_key_actions($key['id']),
            ];
        }

        $this->render_card(__('API Keys', 'autoblogcraft'), function() use ($columns, $rows) {
            $this->render_table($columns, $rows);
        });
    }

    /**
     * Get key action buttons
     *
     * @since 2.0.0
     * @param int $key_id Key ID.
     * @return string Actions HTML.
     */
    private function get_key_actions($key_id) {
        $actions = [];

        $actions[] = sprintf(
            '<form method="post" style="display: inline;">
                %s
                <input type="hidden" name="key_id" value="%d">
                <button type="submit" name="abc_delete_api_key" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</button>
            </form>',
            wp_nonce_field('abc_delete_api_key', '_wpnonce', true, false),
            $key_id,
            esc_js(__('Are you sure you want to delete this API key?', 'autoblogcraft')),
            __('Delete', 'autoblogcraft')
        );

        return implode(' ', $actions);
    }

    /**
     * Save API key
     *
     * @since 2.0.0
     */
    private function save_api_key() {
        // Manual loading to ensure Key_Manager is available
        if (!class_exists('AutoBlogCraft\\AI\\Key_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../ai/class-key-manager.php';
        }
        
        $key_manager = new Key_Manager();

        $result = $key_manager->add_key([
            'provider' => sanitize_text_field($_POST['provider']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'label' => sanitize_text_field($_POST['label'] ?? ''),
            'monthly_quota' => floatval($_POST['quota_limit'] ?? 0),
        ]);

        if (is_wp_error($result)) {
            add_settings_error('abc_api_keys', 'save_error', $result->get_error_message(), 'error');
        } else {
            add_settings_error('abc_api_keys', 'save_success', __('API key saved successfully.', 'autoblogcraft'), 'success');
        }
    }

    /**
     * Delete API key
     *
     * @since 2.0.0
     */
    private function delete_api_key() {
        // Manual loading to ensure Key_Manager is available
        if (!class_exists('AutoBlogCraft\\AI\\Key_Manager')) {
            require_once plugin_dir_path(__FILE__) . '../../ai/class-key-manager.php';
        }
        
        $key_manager = new Key_Manager();
        $key_id = absint($_POST['key_id']);

        $result = $key_manager->delete_key($key_id);

        if (is_wp_error($result)) {
            add_settings_error('abc_api_keys', 'delete_error', $result->get_error_message(), 'error');
        } else {
            add_settings_error('abc_api_keys', 'delete_success', __('API key deleted successfully.', 'autoblogcraft'), 'success');
        }
    }
}
