<?php
/**
 * Dashboard - Quick Stats Widget Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var int $active_campaigns_count Number of active campaigns
 * @var int $queue_pending Pending items in queue
 * @var int $posts_count Total published posts
 * @var array $rate_stats Rate limiter statistics
 * @var int $queue_processing Processing items in queue
 * @var callable $render_stat_card_callback Callback for rendering stat cards
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($render_stat_card_callback) || !is_callable($render_stat_card_callback)) {
    return;
}

// Active Campaigns Card
call_user_func($render_stat_card_callback,
    __('Active Campaigns', 'autoblogcraft'),
    $active_campaigns_count,
    [
        'icon' => 'dashicons-megaphone',
        'color' => 'blue',
        'url' => admin_url('admin.php?page=autoblogcraft-campaigns'),
    ]
);

// Items in Queue Card
call_user_func($render_stat_card_callback,
    __('Items in Queue', 'autoblogcraft'),
    number_format($queue_pending),
    [
        'icon' => 'dashicons-list-view',
        'color' => 'purple',
        'url' => admin_url('admin.php?page=autoblogcraft-queue'),
    ]
);

// Posts Created Card
call_user_func($render_stat_card_callback,
    __('Posts Created', 'autoblogcraft'),
    number_format($posts_count),
    [
        'icon' => 'dashicons-edit',
        'color' => 'green',
    ]
);

// Campaign Rate Limit Card
$campaign_usage_percent = $rate_stats['max_campaigns'] > 0 ? 
    round(($rate_stats['running_campaigns'] / $rate_stats['max_campaigns']) * 100) : 0;
$campaign_color = $campaign_usage_percent >= 80 ? 'red' : ($campaign_usage_percent >= 50 ? 'orange' : 'green');

call_user_func($render_stat_card_callback,
    __('Campaign Slots', 'autoblogcraft'),
    sprintf('%d / %d', $rate_stats['running_campaigns'], $rate_stats['max_campaigns']),
    [
        'icon' => 'dashicons-performance',
        'color' => $campaign_color,
        'subtitle' => sprintf(__('%d%% utilized', 'autoblogcraft'), $campaign_usage_percent),
    ]
);

// AI Call Rate Limit Card
$ai_usage_percent = $rate_stats['max_ai_calls'] > 0 ? 
    round(($rate_stats['current_ai_calls'] / $rate_stats['max_ai_calls']) * 100) : 0;
$ai_color = $ai_usage_percent >= 80 ? 'red' : ($ai_usage_percent >= 50 ? 'orange' : 'green');

call_user_func($render_stat_card_callback,
    __('AI Call Slots', 'autoblogcraft'),
    sprintf('%d / %d', $rate_stats['current_ai_calls'], $rate_stats['max_ai_calls']),
    [
        'icon' => 'dashicons-chart-area',
        'color' => $ai_color,
        'subtitle' => sprintf(__('%d%% utilized', 'autoblogcraft'), $ai_usage_percent),
    ]
);

// Processing Card
call_user_func($render_stat_card_callback,
    __('Processing', 'autoblogcraft'),
    number_format($queue_processing),
    [
        'icon' => 'dashicons-update',
        'color' => 'purple',
    ]
);
