<?php
/**
 * Template Helpers
 *
 * Reusable template rendering functions to reduce code duplication.
 *
 * @package AutoBlogCraft\Helpers
 * @since 2.0.0
 */

namespace AutoBlogCraft\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Helpers class
 *
 * Provides common rendering functions used across admin templates.
 *
 * @since 2.0.0
 */
class Template_Helpers {

    /**
     * Render empty state message
     *
     * @since 2.0.0
     * @param string $title Main message.
     * @param string $description Optional description.
     * @param string $icon Dashicons class (without 'dashicons-' prefix).
     */
    public static function render_empty_state($title, $description = '', $icon = 'info') {
        ?>
        <div class="abc-empty-state">
            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
            <h3><?php echo esc_html($title); ?></h3>
            <?php if (!empty($description)) : ?>
                <p><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render pagination
     *
     * @since 2.0.0
     * @param object|array $pagination Pagination data {total, per_page, current_page}.
     * @param string $base_url Optional base URL for pagination links.
     * @return string Pagination HTML.
     */
    public static function render_pagination($pagination, $base_url = '') {
        // Convert to object if array
        if (is_array($pagination)) {
            $pagination = (object) $pagination;
        }

        $total = $pagination->total ?? 0;
        $per_page = $pagination->per_page ?? 20;
        $current_page = $pagination->current_page ?? 1;

        // Don't show pagination if not needed
        if ($total <= $per_page) {
            return '';
        }

        $total_pages = ceil($total / $per_page);

        ob_start();
        ?>
        <div class="abc-pagination">
            <?php
            $args = [
                'base' => !empty($base_url) ? $base_url . '%_%' : add_query_arg('paged', '%#%'),
                'format' => !empty($base_url) ? '&paged=%#%' : '',
                'prev_text' => __('&laquo; Previous', 'autoblogcraft-ai'),
                'next_text' => __('Next &raquo;', 'autoblogcraft-ai'),
                'total' => $total_pages,
                'current' => $current_page,
                'type' => 'plain',
            ];

            echo paginate_links($args);
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render status badge
     *
     * @since 2.0.0
     * @param string $status Status value.
     * @param string $type Optional badge type (affects styling).
     * @return string Status badge HTML.
     */
    public static function render_status_badge($status, $type = 'default') {
        $status = strtolower($status);
        
        return sprintf(
            '<span class="abc-status abc-status-%s">%s</span>',
            esc_attr($status),
            esc_html(ucfirst($status))
        );
    }

    /**
     * Format relative time
     *
     * @since 2.0.0
     * @param string $date Date string.
     * @param bool $include_ago Whether to append "ago" text.
     * @return string Formatted relative time.
     */
    public static function format_relative_time($date, $include_ago = true) {
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        $diff = human_time_diff($timestamp, current_time('timestamp'));
        
        if ($include_ago) {
            return sprintf(
                esc_html__('%s ago', 'autoblogcraft-ai'),
                $diff
            );
        }
        
        return $diff;
    }

    /**
     * Render hidden form fields for campaign detail pages
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param string $tab Tab name.
     */
    public static function render_hidden_form_fields($campaign_id, $tab) {
        ?>
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'abc-campaigns'); ?>">
        <input type="hidden" name="action" value="detail">
        <input type="hidden" name="id" value="<?php echo esc_attr($campaign_id); ?>">
        <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
        <?php
    }

    /**
     * Render filter form wrapper
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param string $tab Tab name.
     * @param callable $content_callback Callback function to render filter fields.
     */
    public static function render_filter_form($campaign_id, $tab, $content_callback) {
        ?>
        <form method="get" class="abc-filters-form">
            <?php self::render_hidden_form_fields($campaign_id, $tab); ?>
            <?php call_user_func($content_callback); ?>
        </form>
        <?php
    }

    /**
     * Format full timestamp with relative time
     *
     * @since 2.0.0
     * @param string $date Date string.
     * @return string HTML with formatted date.
     */
    public static function format_timestamp_with_relative($date) {
        $formatted_date = date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($date)
        );
        
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr($formatted_date),
            esc_html(self::format_relative_time($date))
        );
    }

    /**
     * Render log level badge with icon
     *
     * @since 2.0.0
     * @param string $level Log level (info, success, warning, error, debug).
     * @return string Log level badge HTML.
     */
    public static function render_log_level_badge($level) {
        $level = strtolower($level);
        
        $icons = [
            'info' => 'info',
            'success' => 'yes-alt',
            'warning' => 'warning',
            'error' => 'dismiss',
            'debug' => 'admin-tools',
        ];
        
        $icon = $icons[$level] ?? 'info';
        
        return sprintf(
            '<span class="abc-log-icon dashicons dashicons-%s"></span> <span class="abc-log-level">%s</span>',
            esc_attr($icon),
            esc_html(strtoupper($level))
        );
    }

    /**
     * Render clear filter button
     *
     * @since 2.0.0
     * @param string $url Clear URL.
     * @param bool $has_active_filters Whether there are active filters.
     */
    public static function render_clear_filters_button($url, $has_active_filters = true) {
        if (!$has_active_filters) {
            return;
        }
        ?>
        <a href="<?php echo esc_url($url); ?>" class="button">
            <?php esc_html_e('Clear', 'autoblogcraft-ai'); ?>
        </a>
        <?php
    }

    /**
     * Render campaign type badge
     *
     * @since 2.0.0
     * @param string $type Campaign type.
     * @return string Campaign type badge HTML.
     */
    public static function render_campaign_type_badge($type) {
        return sprintf(
            '<span class="abc-campaign-type-badge">%s</span>',
            esc_html(ucfirst($type))
        );
    }

    /**
     * Render campaign filter bar with form
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @param string $tab Tab name.
     * @param array $filter_fields Array of filter field configurations.
     * @param array $filters Current filter values.
     * @param array $actions Optional action buttons to display.
     */
    public static function render_campaign_filter_bar($campaign_id, $tab, $filter_fields, $filters = [], $actions = []) {
        $has_active_filters = false;
        foreach ($filter_fields as $field) {
            $filter_key = $field['key'] ?? '';
            if (!empty($filters[$filter_key])) {
                $has_active_filters = true;
                break;
            }
        }

        $clear_url = admin_url('admin.php?page=abc-campaigns&action=detail&id=' . $campaign_id . '&tab=' . $tab);
        ?>
        <div class="abc-<?php echo esc_attr($tab); ?>-filters">
            <form method="get" class="abc-filters-form">
                <?php self::render_hidden_form_fields($campaign_id, $tab); ?>
                
                <?php foreach ($filter_fields as $field) : ?>
                    <?php self::render_filter_field($field, $filters); ?>
                <?php endforeach; ?>

                <button type="submit" class="button"><?php esc_html_e('Filter', 'autoblogcraft-ai'); ?></button>
                
                <?php self::render_clear_filters_button($clear_url, $has_active_filters); ?>
            </form>

            <?php if (!empty($actions)) : ?>
                <div class="abc-<?php echo esc_attr($tab); ?>-actions">
                    <?php foreach ($actions as $action) : ?>
                        <?php self::render_action_button($action); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render individual filter field
     *
     * @since 2.0.0
     * @param array $field Field configuration.
     * @param array $filters Current filter values.
     */
    private static function render_filter_field($field, $filters) {
        $type = $field['type'] ?? 'select';
        $key = $field['key'] ?? '';
        $label = $field['label'] ?? '';
        $name = $field['name'] ?? $key;
        $id = $field['id'] ?? 'filter-' . $key;
        $current_value = $filters[$key] ?? '';
        $class = $field['class'] ?? 'abc-filter-group';

        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php if ($type === 'select') : ?>
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
                <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>">
                    <option value=""><?php echo esc_html($field['placeholder'] ?? __('All', 'autoblogcraft-ai')); ?></option>
                    <?php foreach ($field['options'] as $value => $option_label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'search') : ?>
                <label for="<?php echo esc_attr($id); ?>" class="screen-reader-text"><?php echo esc_html($label); ?></label>
                <input type="search" 
                       name="<?php echo esc_attr($name); ?>" 
                       id="<?php echo esc_attr($id); ?>" 
                       value="<?php echo esc_attr($current_value); ?>" 
                       placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>">
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render action button
     *
     * @since 2.0.0
     * @param array $action Action button configuration.
     */
    private static function render_action_button($action) {
        $type = $action['type'] ?? 'button';
        $id = $action['id'] ?? '';
        $class = $action['class'] ?? 'button';
        $icon = $action['icon'] ?? '';
        $label = $action['label'] ?? '';
        $data_attrs = $action['data'] ?? [];

        $data_html = '';
        foreach ($data_attrs as $key => $value) {
            $data_html .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        ?>
        <button type="<?php echo esc_attr($type); ?>" 
                <?php if ($id) : ?>id="<?php echo esc_attr($id); ?>"<?php endif; ?>
                class="<?php echo esc_attr($class); ?>"
                <?php echo $data_html; ?>>
            <?php if ($icon) : ?>
                <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
            <?php endif; ?>
            <?php echo esc_html($label); ?>
        </button>
        <?php
    }
}
