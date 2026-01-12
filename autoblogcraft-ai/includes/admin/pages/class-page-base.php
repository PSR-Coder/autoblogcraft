<?php
/**
 * Admin Page Base
 *
 * Base class for all admin pages with common UI components.
 *
 * @package AutoBlogCraft\Admin\Pages
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Page Base class
 *
 * Provides common functionality for admin pages:
 * - Page header and footer
 * - Cards and sections
 * - Buttons and forms
 * - Tables and lists
 * - Notices and alerts
 *
 * @since 2.0.0
 */
abstract class Admin_Page_Base {

    /**
     * Page title
     *
     * @var string
     */
    protected $title = '';

    /**
     * Page description
     *
     * @var string
     */
    protected $description = '';

    /**
     * Render page
     *
     * @since 2.0.0
     */
    abstract public function render();

    /**
     * Render page header
     *
     * @since 2.0.0
     * @param string $title Page title.
     * @param string $description Page description.
     * @param array $actions Header actions.
     */
    protected function render_header($title, $description = '', $actions = []) {
        ?>
        <div class="abc-page-header">
            <div class="abc-page-header-content">
                <h1 class="abc-page-title"><?php echo esc_html($title); ?></h1>
                <?php if ($description): ?>
                    <p class="abc-page-description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($actions)): ?>
                <div class="abc-page-header-actions">
                    <?php foreach ($actions as $action): ?>
                        <a href="<?php echo esc_url($action['url']); ?>" 
                           class="button <?php echo esc_attr($action['class'] ?? 'button-secondary'); ?>">
                            <?php if (!empty($action['icon'])): ?>
                                <span class="dashicons <?php echo esc_attr($action['icon']); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html($action['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render card
     *
     * @since 2.0.0
     * @param string $title Card title.
     * @param callable $content Content callback.
     * @param array $args Card arguments.
     */
    protected function render_card($title, $content, $args = []) {
        $class = $args['class'] ?? '';
        $footer = $args['footer'] ?? '';
        ?>
        <div class="abc-card <?php echo esc_attr($class); ?>">
            <?php if ($title): ?>
                <div class="abc-card-header">
                    <h2 class="abc-card-title"><?php echo esc_html($title); ?></h2>
                </div>
            <?php endif; ?>
            <div class="abc-card-body">
                <?php call_user_func($content); ?>
            </div>
            <?php if ($footer): ?>
                <div class="abc-card-footer">
                    <?php echo $footer; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render stat card
     *
     * @since 2.0.0
     * @param string $label Stat label.
     * @param string $value Stat value.
     * @param array $args Additional arguments.
     */
    protected function render_stat_card($label, $value, $args = []) {
        $icon = $args['icon'] ?? 'dashicons-chart-line';
        $color = $args['color'] ?? 'blue';
        $trend = $args['trend'] ?? '';
        $url = $args['url'] ?? '';
        ?>
        <div class="abc-stat-card abc-stat-card-<?php echo esc_attr($color); ?>">
            <div class="abc-stat-icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            </div>
            <div class="abc-stat-content">
                <div class="abc-stat-label"><?php echo esc_html($label); ?></div>
                <div class="abc-stat-value"><?php echo esc_html($value); ?></div>
                <?php if ($trend): ?>
                    <div class="abc-stat-trend"><?php echo esc_html($trend); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($url): ?>
                <a href="<?php echo esc_url($url); ?>" class="abc-stat-link"></a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render notice
     *
     * @since 2.0.0
     * @param string $message Notice message.
     * @param string $type Notice type (success, error, warning, info).
     */
    protected function render_notice($message, $type = 'info') {
        ?>
        <div class="abc-notice abc-notice-<?php echo esc_attr($type); ?>">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }

    /**
     * Render empty state
     *
     * @since 2.0.0
     * @param string $title Empty state title.
     * @param string $description Empty state description.
     * @param array $action Call to action button.
     */
    protected function render_empty_state($title, $description, $action = []) {
        ?>
        <div class="abc-empty-state">
            <div class="abc-empty-state-icon">
                <span class="dashicons dashicons-format-aside"></span>
            </div>
            <h3 class="abc-empty-state-title"><?php echo esc_html($title); ?></h3>
            <p class="abc-empty-state-description"><?php echo esc_html($description); ?></p>
            <?php if (!empty($action)): ?>
                <a href="<?php echo esc_url($action['url']); ?>" 
                   class="button button-primary button-large">
                    <?php echo esc_html($action['label']); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render tabs
     *
     * @since 2.0.0
     * @param array $tabs Tab configuration.
     * @param string $active Active tab ID.
     */
    protected function render_tabs($tabs, $active) {
        ?>
        <div class="abc-tabs">
            <nav class="abc-tabs-nav">
                <?php foreach ($tabs as $tab_id => $tab): ?>
                    <a href="<?php echo esc_url($tab['url']); ?>" 
                       class="abc-tab-link <?php echo $active === $tab_id ? 'active' : ''; ?>">
                        <?php if (!empty($tab['icon'])): ?>
                            <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                        <?php endif; ?>
                        <?php echo esc_html($tab['label']); ?>
                        <?php if (!empty($tab['badge'])): ?>
                            <span class="abc-badge"><?php echo esc_html($tab['badge']); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }

    /**
     * Render table
     *
     * @since 2.0.0
     * @param array $columns Table columns.
     * @param array $rows Table rows.
     * @param array $args Additional arguments.
     */
    protected function render_table($columns, $rows, $args = []) {
        $class = $args['class'] ?? '';
        ?>
        <div class="abc-table-wrapper">
            <table class="abc-table <?php echo esc_attr($class); ?>">
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th class="<?php echo esc_attr($column['class'] ?? ''); ?>">
                                <?php echo esc_html($column['label']); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?php echo count($columns); ?>" class="abc-table-empty">
                                <?php echo esc_html($args['empty_message'] ?? __('No items found.', 'autoblogcraft')); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $col_id => $column): ?>
                                    <td class="<?php echo esc_attr($column['class'] ?? ''); ?>">
                                        <?php
                                        if (isset($row[$col_id])) {
                                            echo $row[$col_id];
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Format timestamp
     *
     * @since 2.0.0
     * @param string $timestamp Timestamp.
     * @return string Formatted timestamp.
     */
    protected function format_timestamp($timestamp) {
        if (empty($timestamp)) {
            return '—';
        }

        $time = strtotime($timestamp);
        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr(date_i18n('Y-m-d H:i:s', $time)),
            esc_html(human_time_diff($time) . ' ago')
        );
    }

    /**
     * Format status badge
     *
     * @since 2.0.0
     * @param string $status Status.
     * @param array $colors Status color map.
     * @return string Badge HTML.
     */
    protected function format_status_badge($status, $colors = []) {
        $default_colors = [
            'active' => 'green',
            'paused' => 'yellow',
            'inactive' => 'gray',
            'pending' => 'blue',
            'processing' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'error' => 'red',
        ];

        $colors = array_merge($default_colors, $colors);
        $color = $colors[$status] ?? 'gray';

        return sprintf(
            '<span class="abc-badge abc-badge-%s">%s</span>',
            esc_attr($color),
            esc_html(ucfirst($status))
        );
    }

    /**
     * Get pagination HTML
     *
     * @since 2.0.0
     * @param int $total Total items.
     * @param int $per_page Items per page.
     * @param int $current_page Current page.
     * @param string $base_url Base URL.
     * @return string Pagination HTML.
     */
    protected function get_pagination($total, $per_page, $current_page, $base_url) {
        $total_pages = ceil($total / $per_page);

        if ($total_pages <= 1) {
            return '';
        }

        $output = '<div class="abc-pagination">';

        // Previous
        if ($current_page > 1) {
            $output .= sprintf(
                '<a href="%s" class="abc-pagination-link">« Previous</a>',
                esc_url(add_query_arg('paged', $current_page - 1, $base_url))
            );
        }

        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            $class = $i === $current_page ? 'abc-pagination-link active' : 'abc-pagination-link';
            $output .= sprintf(
                '<a href="%s" class="%s">%d</a>',
                esc_url(add_query_arg('paged', $i, $base_url)),
                $class,
                $i
            );
        }

        // Next
        if ($current_page < $total_pages) {
            $output .= sprintf(
                '<a href="%s" class="abc-pagination-link">Next »</a>',
                esc_url(add_query_arg('paged', $current_page + 1, $base_url))
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Load template file
     *
     * Loads a template file from the templates directory and extracts variables.
     *
     * @since 2.0.0
     * @param string $template_name Template name (without .php extension).
     * @param array $vars Variables to extract into template scope.
     */
    protected function load_template($template_name, $vars = []) {
        $template_path = dirname(dirname(dirname(__FILE__))) . '/templates/' . $template_name . '.php';

        if (!file_exists($template_path)) {
            echo '<!-- Template not found: ' . esc_html($template_path) . ' -->';
            return;
        }

        // Extract variables into local scope
        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        // Include template
        include $template_path;
    }
}
