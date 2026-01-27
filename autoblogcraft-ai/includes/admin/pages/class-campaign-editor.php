<?php
/**
 * Campaign Editor Page (Fully Optimized & Complete)
 *
 * @package AutoBlogCraft\Admin\Pages
 * @since 2.1.0
 */

namespace AutoBlogCraft\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Page_Campaign_Editor extends Admin_Page_Base {

    public function render() {
        $campaign_id = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
        $is_edit     = $campaign_id > 0;
        $active_tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'basic';

        // 1. Fetch Data
        $campaign      = $is_edit ? get_post($campaign_id) : null;
        $campaign_data = $is_edit ? $this->get_campaign_data($campaign_id, $campaign) : $this->get_default_data();

        // 2. Handle Wizard State
        if (!$is_edit) {
            $wizard_data = get_transient('abc_campaign_wizard_' . get_current_user_id());
            if ($wizard_data && is_array($wizard_data)) {
                $campaign_data = array_merge($campaign_data, $wizard_data);
            }
            $this->render_header(__('Create New Campaign', 'autoblogcraft'));
        } else {
            if (!$campaign || $campaign->post_type !== 'abc_campaign') {
                $this->render_notice(__('Invalid campaign ID.', 'autoblogcraft'), 'error');
                return;
            }
            $this->render_header(sprintf(__('Campaign: %s', 'autoblogcraft'), esc_html($campaign->post_title)));
        }

        // 3. Enqueue Assets
        $this->enqueue_assets($campaign_data);

        // 4. Render Messages
        if (isset($_GET['message'])) {
            $this->render_notice(
                ($_GET['message'] === 'created') ? __('Campaign created!', 'autoblogcraft') : __('Settings saved.', 'autoblogcraft'),
                'success'
            );
        }

        // 5. Render UI
        $this->render_navigation($campaign_id, $active_tab, $is_edit);
        
        echo '<div class="abc-editor-wrapper">';
        $this->render_active_panel($active_tab, $campaign_id, $campaign_data, $is_edit);
        echo '</div>';
    }

    private function enqueue_assets($data) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        // Pass complete data to JS for dynamic fields
        wp_localize_script('abc-admin', 'abcEditorData', [
            'aiConfig' => $data['ai_config'] ?? [],
            'sourceConfig' => $data['source_config'] ?? [],
            'type' => $data['type'] ?? 'website',
            'strings' => [
                'refreshing' => __('Refreshing...', 'autoblogcraft')
            ]
        ]);
    }

    private function render_active_panel($tab, $campaign_id, $data, $is_edit) {
        $form_action = esc_url(admin_url('admin-post.php'));
        $nonce_field = wp_nonce_field('abc_save_campaign', 'abc_nonce', true, false);
        
        // Helper to open form
        $open_form = function() use ($form_action, $nonce_field, $campaign_id, $tab, $data, $is_edit) {
            echo "<form method='post' action='{$form_action}' id='abc-{$tab}-form'>";
            echo $nonce_field;
            echo '<input type="hidden" name="action" value="abc_save_campaign">';
            echo '<input type="hidden" name="campaign_id" value="' . esc_attr($campaign_id) . '">';
            echo '<input type="hidden" name="current_tab" value="' . esc_attr($tab) . '">';
            if ($tab !== 'basic') {
                echo '<input type="hidden" name="post_title" value="' . esc_attr($data['title']) . '">';
                echo '<input type="hidden" name="campaign_type" value="' . esc_attr($data['type']) . '">';
            }
            if (!$is_edit) echo '<input type="hidden" name="wizard_step" value="' . esc_attr($tab) . '">';
        };

        switch ($tab) {
            case 'overview':
                $this->include_template('overview', ['campaign_id' => $campaign_id]);
                break;
            case 'basic':
                $open_form();
                $this->render_panel_basic($data, $is_edit);
                $this->render_footer_actions($is_edit, 'basic', 'sources');
                echo '</form>';
                break;
            case 'sources':
                $open_form();
                $this->render_panel_sources($data);
                $this->render_footer_actions($is_edit, 'sources', 'content');
                echo '</form>';
                break;
            case 'content':
                $open_form();
                $this->render_panel_content($data);
                $this->render_footer_actions($is_edit, 'content', 'ai');
                echo '</form>';
                break;
            case 'ai':
                $open_form();
                if (!$is_edit) echo '<input type="hidden" name="create_campaign" value="1">';
                $this->render_panel_ai($data);
                $this->render_footer_actions($is_edit, 'ai', 'finish');
                echo '</form>';
                break;
            default:
                if ($is_edit) $this->include_template($tab, ['campaign_id' => $campaign_id]);
        }
    }

    /**
     * PANEL: Basic Info & Schedule
     */
    private function render_panel_basic($data, $is_edit) {
        $authors = get_users(['role__in' => ['administrator', 'editor', 'author']]);
        $categories = get_categories(['hide_empty' => false]);
        
        // Interval Logic
        $interval = $data['discovery_interval'];
        $is_custom = strpos($interval, 'custom_') === 0;
        $custom_h = 0; $custom_m = 15;
        if ($is_custom && preg_match('/custom_(\d+)h_(\d+)m/', $interval, $m)) {
            $custom_h = $m[1]; $custom_m = $m[2];
        }
        ?>
        <div class="abc-editor-section">
            <h2><?php esc_html_e('Campaign Details', 'autoblogcraft'); ?></h2>
            <div class="abc-form-row">
                <label class="abc-form-label"><?php esc_html_e('Campaign Name', 'autoblogcraft'); ?> *</label>
                <input type="text" name="post_title" class="large-text" value="<?php echo esc_attr($data['title']); ?>" required>
            </div>

            <div class="abc-form-row">
                <label class="abc-form-label"><?php esc_html_e('Campaign Type', 'autoblogcraft'); ?></label>
                <?php if ($is_edit): ?>
                    <div class="abc-type-display">
                        <span class="abc-campaign-type-badge"><?php echo esc_html(ucfirst($data['type'])); ?></span>
                        <input type="hidden" name="campaign_type" value="<?php echo esc_attr($data['type']); ?>">
                    </div>
                <?php else: ?>
                    <div class="abc-type-selector">
                        <?php
                        $types = [
                            'website' => ['icon' => 'dashicons-admin-site', 'label' => 'Website Feed'],
                            'youtube' => ['icon' => 'dashicons-video-alt3', 'label' => 'YouTube'],
                            'amazon'  => ['icon' => 'dashicons-cart', 'label' => 'Amazon'],
                            'news'    => ['icon' => 'dashicons-megaphone', 'label' => 'News'],
                        ];
                        foreach ($types as $key => $type) {
                            $sel = ($data['type'] === $key) ? 'selected' : '';
                            echo "<div class='abc-type-option {$sel}' data-value='{$key}'>
                                    <span class='dashicons {$type['icon']}'></span>
                                    <div>{$type['label']}</div>
                                  </div>";
                        }
                        ?>
                        <input type="hidden" name="campaign_type" id="campaign_type" value="<?php echo esc_attr($data['type']); ?>">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="abc-editor-section">
            <h2><?php esc_html_e('Publishing & Schedule', 'autoblogcraft'); ?></h2>
            <div class="abc-two-col">
                <div class="abc-form-row">
                    <label class="abc-form-label"><?php esc_html_e('Category', 'autoblogcraft'); ?></label>
                    <select name="wp_category_id" class="regular-text">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat->term_id; ?>" <?php selected($data['wp_config']['category_id'], $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="abc-form-row">
                    <label class="abc-form-label"><?php esc_html_e('Author', 'autoblogcraft'); ?></label>
                    <select name="wp_author_id" class="regular-text">
                        <?php foreach ($authors as $auth): ?>
                            <option value="<?php echo $auth->ID; ?>" <?php selected($data['wp_config']['author_id'], $auth->ID); ?>>
                                <?php echo esc_html($auth->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Custom Interval Logic -->
            <div class="abc-form-row">
                <label class="abc-form-label"><?php esc_html_e('Discovery Interval', 'autoblogcraft'); ?></label>
                <div class="abc-interval-wrapper">
                    <label><input type="checkbox" id="use-custom-interval" <?php checked($is_custom); ?>> Use Custom Time</label>
                    
                    <select name="discovery_interval" id="discovery-interval-select" class="regular-text" <?php disabled($is_custom); ?>>
                        <option value="every_1_hour" <?php selected($interval, 'every_1_hour'); ?>>Every Hour</option>
                        <option value="every_6_hours" <?php selected($interval, 'every_6_hours'); ?>>Every 6 Hours</option>
                        <option value="daily" <?php selected($interval, 'daily'); ?>>Daily</option>
                    </select>

                    <div id="custom-interval-inputs" class="<?php echo $is_custom ? '' : 'abc-hidden'; ?>">
                        <input type="number" id="custom-hours" value="<?php echo $custom_h; ?>" min="0" placeholder="HH"> hrs
                        <input type="number" id="custom-minutes" value="<?php echo $custom_m; ?>" min="0" max="59" placeholder="MM"> mins
                        <input type="hidden" name="discovery_interval_custom" id="discovery-interval-custom" value="<?php echo esc_attr($is_custom ? $interval : ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="abc-form-row">
                <label class="abc-form-label"><?php esc_html_e('Max Posts Per Day', 'autoblogcraft'); ?></label>
                <input type="number" name="limits[max_posts_per_day]" class="small-text" value="<?php echo esc_attr($data['limits']['max_posts_per_day'] ?? 10); ?>">
            </div>
        </div>
        <?php
    }

    /**
     * PANEL: Sources (Restored News/Amazon)
     */
    private function render_panel_sources($data) {
        $type = $data['type'];
        ?>
        <div class="abc-editor-section">
            <h2><?php esc_html_e('Source Configuration', 'autoblogcraft'); ?></h2>
            
            <!-- Website -->
            <div class="abc-config-group <?php echo $type !== 'website' ? 'abc-hidden' : ''; ?>" id="config-website">
                <div class="abc-source-box">
                    <label><input type="checkbox" name="source_types[rss]" value="1" class="abc-toggle-input" data-target="rss-box"> RSS Feeds</label>
                    <div id="rss-box" class="abc-toggle-target">
                        <textarea name="rss_sources" class="abc-code-input" rows="3"><?php echo esc_textarea($data['source_config']['rss_urls'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="abc-source-box">
                    <label><input type="checkbox" name="source_types[blogs]" value="1" class="abc-toggle-input" data-target="url-box"> Direct URLs</label>
                    <div id="url-box" class="abc-toggle-target">
                        <textarea name="url_sources" class="abc-code-input" rows="3"><?php echo esc_textarea($data['source_config']['direct_urls'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- YouTube -->
            <div class="abc-config-group <?php echo $type !== 'youtube' ? 'abc-hidden' : ''; ?>" id="config-youtube">
                <div class="abc-form-row">
                    <label class="abc-form-label">Channel ID</label>
                    <input type="text" name="source_config[youtube][channel_id]" class="regular-text" value="<?php echo esc_attr($data['source_config']['channel_id'] ?? ''); ?>">
                </div>
            </div>

            <!-- News (Restored) -->
            <div class="abc-config-group <?php echo $type !== 'news' ? 'abc-hidden' : ''; ?>" id="config-news">
                <div class="abc-form-row">
                    <label class="abc-form-label">Keywords (Comma separated)</label>
                    <input type="text" name="source_config[news][keywords]" class="large-text" value="<?php echo esc_attr($data['source_config']['keywords'] ?? ''); ?>">
                </div>
                <div class="abc-form-row">
                    <label class="abc-form-label">Freshness</label>
                    <select name="source_config[news][freshness]">
                        <option value="1h" <?php selected($data['source_config']['freshness'] ?? '', '1h'); ?>>Last Hour</option>
                        <option value="24h" <?php selected($data['source_config']['freshness'] ?? '24h', '24h'); ?>>Last 24 Hours</option>
                        <option value="7d" <?php selected($data['source_config']['freshness'] ?? '', '7d'); ?>>Last 7 Days</option>
                    </select>
                </div>
            </div>

            <!-- Amazon (Restored) -->
            <div class="abc-config-group <?php echo $type !== 'amazon' ? 'abc-hidden' : ''; ?>" id="config-amazon">
                <div class="abc-form-row">
                    <label class="abc-form-label">Search Keywords</label>
                    <input type="text" name="source_config[amazon][keywords]" class="large-text" value="<?php echo esc_attr($data['source_config']['keywords'] ?? ''); ?>">
                </div>
                <div class="abc-form-row">
                    <label class="abc-form-label">Category (Optional)</label>
                    <input type="text" name="source_config[amazon][category]" class="regular-text" value="<?php echo esc_attr($data['source_config']['category'] ?? ''); ?>">
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * PANEL: AI (Restored Refresh Button)
     */
    private function render_panel_ai($data) {
        global $wpdb;
        $api_keys = $wpdb->get_results("SELECT id, key_name, provider FROM {$wpdb->prefix}abc_api_keys WHERE status = 'active'");
        ?>
        <div class="abc-editor-section">
            <h2><?php esc_html_e('AI Configuration', 'autoblogcraft'); ?></h2>
            <div class="abc-form-row">
                <div class="abc-radio-group">
                    <?php 
                    $modes = ['ai_rewrite' => 'AI Rewrite', 'ai_commentary' => 'AI Commentary', 'as_is' => 'As Is (No AI)'];
                    foreach($modes as $k => $l): ?>
                        <label class="abc-radio-option">
                            <input type="radio" name="ai_config[processing_mode]" value="<?php echo $k; ?>" <?php checked(($data['ai_config']['processing_mode'] ?? 'ai_rewrite'), $k); ?>>
                            <?php echo $l; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="abc-two-col">
                <div class="abc-form-row">
                    <label class="abc-form-label">AI Provider</label>
                    <div style="display:flex; gap:10px;">
                        <select name="ai_config[api_key_id]" id="ai-api-key-select" class="regular-text" required>
                            <option value=""><?php esc_html_e('Select API Key...', 'autoblogcraft'); ?></option>
                            <?php foreach ($api_keys as $key): ?>
                                <option value="<?php echo esc_attr($key->id); ?>" data-provider="<?php echo esc_attr($key->provider); ?>" <?php selected($data['ai_config']['api_key_id'] ?? '', $key->id); ?>>
                                    <?php echo esc_html($key->key_name . ' (' . ucfirst($key->provider) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="refresh-api-keys" class="button"><span class="dashicons dashicons-update"></span></button>
                    </div>
                </div>
                <div class="abc-form-row">
                    <label class="abc-form-label">AI Model</label>
                    <select name="ai_config[model]" id="ai-model-select" class="regular-text"></select>
                </div>
            </div>
            
            <div class="abc-form-row">
                <label class="abc-form-label">System Prompt</label>
                <textarea name="ai_config[system_prompt]" class="large-text" rows="3"><?php echo esc_textarea($data['ai_config']['system_prompt'] ?? ''); ?></textarea>
            </div>
        </div>
        <?php
    }

    private function render_panel_content($data) {
        // Reuse previous optimize logic here, it was fine
        ?>
        <div class="abc-editor-section">
            <h2><?php esc_html_e('Content Strategy', 'autoblogcraft'); ?></h2>
            <div class="abc-two-col">
                <div class="abc-form-row">
                    <label class="abc-form-label">Language</label>
                    <select name="ai_config[language]" class="regular-text">
                        <option value="english" <?php selected($data['ai_config']['language'] ?? '', 'english'); ?>>English</option>
                        <option value="spanish" <?php selected($data['ai_config']['language'] ?? '', 'spanish'); ?>>Spanish</option>
                    </select>
                </div>
            </div>
            <!-- Match Source Toggles -->
            <div class="abc-paired-row">
                <label><input type="checkbox" name="ai_config[match_source_length]" value="1" <?php checked($data['ai_config']['match_source_length'] ?? false); ?>> Match Length</label>
                <div class="abc-input-col">
                    <input type="number" name="ai_config[min_words]" class="tiny-text" value="<?php echo esc_attr($data['ai_config']['min_words'] ?? 300); ?>"> words
                </div>
            </div>
        </div>
        <?php
    }

    private function render_navigation($campaign_id, $active, $is_edit) {
        $tabs = [
            'overview' => ['label' => 'Overview', 'icon' => 'dashicons-chart-bar', 'hide_wizard' => true],
            'basic' => ['label' => 'Basic Info', 'icon' => 'dashicons-admin-generic'],
            'sources' => ['label' => 'Sources', 'icon' => 'dashicons-rss'],
            'content' => ['label' => 'Content', 'icon' => 'dashicons-edit'],
            'ai' => ['label' => 'AI Settings', 'icon' => 'dashicons-superhero'],
            'queue' => ['label' => 'Queue', 'icon' => 'dashicons-list-view', 'hide_wizard' => true],
            'posts' => ['label' => 'Posts', 'icon' => 'dashicons-admin-post', 'hide_wizard' => true],
            'logs' => ['label' => 'Logs', 'icon' => 'dashicons-media-text', 'hide_wizard' => true],
        ];

        echo '<div class="abc-nav-wrapper ' . ($is_edit ? 'abc-mode-edit' : 'abc-mode-wizard') . '">';
        foreach ($tabs as $k => $t) {
            if (!$is_edit && !empty($t['hide_wizard'])) continue;
            $class = ($active === $k) ? 'abc-nav-item active' : 'abc-nav-item';
            $url = $is_edit ? admin_url("admin.php?page=abc-campaign-editor&campaign_id={$campaign_id}&tab={$k}") : '#';
            echo "<a href='{$url}' class='{$class}'><span class='dashicons {$t['icon']}'></span> {$t['label']}</a>";
        }
        echo '</div>';
    }

    private function render_footer_actions($is_edit, $current, $next) {
        echo '<div class="abc-editor-footer">';
        if ($is_edit) {
            echo '<button type="submit" class="button button-primary">' . __('Save Changes', 'autoblogcraft') . '</button>';
        } else {
            if ($current !== 'basic') echo '<a href="javascript:history.back()" class="button button-secondary">' . __('Previous', 'autoblogcraft') . '</a> ';
            echo '<button type="submit" class="button button-primary">' . __('Next Step', 'autoblogcraft') . '</button>';
        }
        echo '</div>';
    }

    private function include_template($name, $args) {
        extract($args);
        $file = ABC_PLUGIN_DIR . "templates/admin/campaign-detail/{$name}.php";
        if (file_exists($file)) include $file;
    }

    private function get_campaign_data($id, $post) {
        return [
            'title' => $post->post_title,
            'type' => get_post_meta($id, '_campaign_type', true),
            'wp_config' => [
                'category_id' => get_post_meta($id, '_wp_category_id', true),
                'author_id' => get_post_meta($id, '_wp_author_id', true),
            ],
            'source_config' => get_post_meta($id, '_source_config', true) ?: [],
            'ai_config' => get_post_meta($id, '_ai_config', true) ?: [],
            'discovery_interval' => get_post_meta($id, '_discovery_interval', true),
            'limits' => get_post_meta($id, '_limits', true) ?: []
        ];
    }

    private function get_default_data() {
        return [
            'title' => '',
            'type' => 'website',
            'wp_config' => ['category_id' => '', 'author_id' => get_current_user_id()],
            'source_config' => [],
            'ai_config' => ['processing_mode' => 'ai_rewrite', 'language' => 'english'],
            'discovery_interval' => 'every_1_hour',
            'limits' => ['max_posts_per_day' => 10]
        ];
    }
}