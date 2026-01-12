<?php
/**
 * Admin Page - Logs
 *
 * System logs viewer and manager.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logs Page class
 *
 * @since 2.0.0
 */
class Admin_Page_Logs extends Admin_Page_Base {

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render() {
        // Get filter parameters
        $level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : 'all';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 50;

        ?>
        <div class="wrap abc-wrap">
            <?php
            $this->render_header(
                __('Logs', 'autoblogcraft'),
                __('System activity logs and error tracking', 'autoblogcraft')
            );
            ?>

            <div class="abc-page-content">
                <?php $this->render_filters($level_filter, $search); ?>
                <?php $this->render_logs_table($level_filter, $search, $paged, $per_page); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render filters
     *
     * @since 2.0.0
     * @param string $level_filter Level filter.
     * @param string $search Search query.
     */
    private function render_filters($level_filter, $search) {
        ?>
        <div class="abc-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="autoblogcraft-logs">
                
                <select name="level" id="abc-level-filter" class="abc-filter-select">
                    <option value="all" <?php selected($level_filter, 'all'); ?>><?php _e('All Levels', 'autoblogcraft'); ?></option>
                    <option value="debug" <?php selected($level_filter, 'debug'); ?>><?php _e('Debug', 'autoblogcraft'); ?></option>
                    <option value="info" <?php selected($level_filter, 'info'); ?>><?php _e('Info', 'autoblogcraft'); ?></option>
                    <option value="warning" <?php selected($level_filter, 'warning'); ?>><?php _e('Warning', 'autoblogcraft'); ?></option>
                    <option value="error" <?php selected($level_filter, 'error'); ?>><?php _e('Error', 'autoblogcraft'); ?></option>
                </select>

                <input type="text" name="search" id="abc-log-search" class="abc-filter-input" 
                       placeholder="<?php esc_attr_e('Search logs...', 'autoblogcraft'); ?>" 
                       value="<?php echo esc_attr($search); ?>">

                <button type="submit" class="button"><?php _e('Filter', 'autoblogcraft'); ?></button>
                
                <a href="<?php echo admin_url('admin.php?page=autoblogcraft-logs'); ?>" class="button">
                    <?php _e('Clear', 'autoblogcraft'); ?>
                </a>
            </form>
        </div>
        <?php
    }

    /**
     * Render logs table
     *
     * @since 2.0.0
     * @param string $level_filter Level filter.
     * @param string $search Search query.
     * @param int $paged Current page.
     * @param int $per_page Items per page.
     */
    private function render_logs_table($level_filter, $search, $paged, $per_page) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'abc_logs';
        $where = ['1=1'];
        
        if ($level_filter !== 'all') {
            $where[] = $wpdb->prepare('level = %s', $level_filter);
        }
        
        if (!empty($search)) {
            $where[] = $wpdb->prepare('message LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}");
        
        // Get logs
        $offset = ($paged - 1) * $per_page;
        $logs = $wpdb->get_results(
            "SELECT * FROM {$table} 
            WHERE {$where_sql} 
            ORDER BY created_at DESC 
            LIMIT {$offset}, {$per_page}"
        );

        if (empty($logs)) {
            $this->render_empty_state(
                __('No logs found', 'autoblogcraft'),
                __('System logs will appear here as the plugin operates.', 'autoblogcraft')
            );
            return;
        }

        $columns = [
            'level' => ['label' => __('Level', 'autoblogcraft'), 'class' => 'abc-log-level'],
            'message' => ['label' => __('Message', 'autoblogcraft')],
            'created' => ['label' => __('Time', 'autoblogcraft')],
        ];

        $rows = [];
        
        foreach ($logs as $log) {
            $level_class = 'abc-log-level-' . strtolower($log->level);
            
            $message_html = sprintf('<strong>%s</strong>', esc_html($log->message));
            
            // Add context if available
            if (!empty($log->context)) {
                $context = json_decode($log->context, true);
                if (!empty($context)) {
                    $message_html .= '<div class="abc-log-context">';
                    $message_html .= '<pre>' . esc_html(json_encode($context, JSON_PRETTY_PRINT)) . '</pre>';
                    $message_html .= '</div>';
                }
            }
            
            $rows[] = [
                'level' => sprintf(
                    '<span class="abc-badge %s">%s</span>',
                    $level_class,
                    esc_html(strtoupper($log->level))
                ),
                'message' => $message_html,
                'created' => sprintf(
                    '<span title="%s">%s</span>',
                    esc_attr(date_i18n('Y-m-d H:i:s', strtotime($log->created_at))),
                    esc_html(human_time_diff(strtotime($log->created_at)) . ' ago')
                ),
            ];
        }

        $this->render_card('', function() use ($columns, $rows) {
            $this->render_table($columns, $rows);
        });

        // Pagination
        $base_url = admin_url('admin.php?page=autoblogcraft-logs');
        if ($level_filter !== 'all') {
            $base_url = add_query_arg('level', $level_filter, $base_url);
        }
        if (!empty($search)) {
            $base_url = add_query_arg('search', $search, $base_url);
        }
        
        echo $this->get_pagination($total, $per_page, $paged, $base_url);
    }
}
