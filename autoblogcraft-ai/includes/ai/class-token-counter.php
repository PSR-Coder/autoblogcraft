<?php
/**
 * Token Counter Utility
 *
 * Estimates and tracks token usage for different AI providers.
 * Single Responsibility: Token counting and reporting.
 *
 * @package AutoBlogCraft\AI
 * @since 2.0.0
 */

namespace AutoBlogCraft\AI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Token Counter class
 *
 * Provides token estimation for cost calculation and quota management.
 */
class Token_Counter {
    /**
     * Characters per token (rough estimates)
     *
     * @var array
     */
    private static $chars_per_token = [
        'openai' => 4, // ~4 chars per token for English
        'gemini' => 4,
        'claude' => 4,
        'deepseek' => 4,
    ];

    /**
     * Estimate tokens in text
     *
     * @param string $text Text to count
     * @param string $provider Provider name
     * @return int Estimated tokens
     */
    public static function estimate($text, $provider = 'openai') {
        if (empty($text)) {
            return 0;
        }

        $chars = strlen($text);
        $chars_per_token = isset(self::$chars_per_token[$provider]) 
            ? self::$chars_per_token[$provider] 
            : 4;

        return intval($chars / $chars_per_token);
    }

    /**
     * Estimate cost based on tokens
     *
     * Prices per 1M tokens (as of Jan 2026 - update regularly)
     *
     * @param int $tokens Token count
     * @param string $provider Provider name
     * @param string $model Model name
     * @param string $type input|output
     * @return float Estimated cost in USD
     */
    public static function estimate_cost($tokens, $provider, $model = '', $type = 'input') {
        $pricing = self::get_pricing($provider, $model);

        if (!$pricing) {
            return 0.0;
        }

        $price_per_million = $type === 'output' 
            ? $pricing['output'] 
            : $pricing['input'];

        return ($tokens / 1000000) * $price_per_million;
    }

    /**
     * Get pricing for provider/model
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @return array|null {input, output} prices per 1M tokens
     */
    private static function get_pricing($provider, $model) {
        $pricing_table = [
            'openai' => [
                'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
                'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
                'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
                'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
            ],
            'gemini' => [
                'gemini-2.0-flash-exp' => ['input' => 0.00, 'output' => 0.00], // Free tier
                'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
                'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
            ],
            'claude' => [
                'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
                'claude-3-5-haiku-20241022' => ['input' => 1.00, 'output' => 5.00],
                'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
            ],
            'deepseek' => [
                'deepseek-chat' => ['input' => 0.14, 'output' => 0.28],
                'deepseek-coder' => ['input' => 0.14, 'output' => 0.28],
            ],
        ];

        // Try exact model match first
        if (isset($pricing_table[$provider][$model])) {
            return $pricing_table[$provider][$model];
        }

        // Fallback to first model of provider
        if (isset($pricing_table[$provider])) {
            return reset($pricing_table[$provider]);
        }

        return null;
    }

    /**
     * Get usage report for campaign
     *
     * @param int $campaign_id Campaign ID
     * @param string $period today|week|month|all
     * @return array|WP_Error Report data
     */
    public static function get_campaign_report($campaign_id, $period = 'today') {
        global $wpdb;

        $date_filter = self::get_date_filter($period);

        // Get AI config to know which provider/model
        $config = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT provider, model FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
                $campaign_id
            ),
            ARRAY_A
        );

        if (!$config) {
            return new \WP_Error('no_config', __('Campaign has no AI configuration', 'autoblogcraft-ai'));
        }

        // Get token usage from logs (this is simplified - in production, query actual usage table)
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as requests,
                SUM(CAST(JSON_EXTRACT(context, '$.tokens') AS UNSIGNED)) as total_tokens
            FROM {$wpdb->prefix}abc_logs
            WHERE campaign_id = %d
            AND category = 'ai'
            {$date_filter}",
            $campaign_id
        );

        $data = $wpdb->get_row($query, ARRAY_A);

        $total_tokens = isset($data['total_tokens']) ? intval($data['total_tokens']) : 0;
        $requests = isset($data['requests']) ? intval($data['requests']) : 0;

        // Estimate cost
        $estimated_cost = self::estimate_cost($total_tokens, $config['provider'], $config['model']);

        return [
            'campaign_id' => $campaign_id,
            'period' => $period,
            'provider' => $config['provider'],
            'model' => $config['model'],
            'requests' => $requests,
            'total_tokens' => $total_tokens,
            'estimated_cost' => $estimated_cost,
            'avg_tokens_per_request' => $requests > 0 ? intval($total_tokens / $requests) : 0,
        ];
    }

    /**
     * Get date filter SQL for period
     *
     * @param string $period Period name
     * @return string SQL WHERE clause fragment
     */
    private static function get_date_filter($period) {
        switch ($period) {
            case 'today':
                return "AND DATE(created_at) = CURDATE()";
            case 'week':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            default:
                return '';
        }
    }

    /**
     * Get usage summary for all campaigns
     *
     * @param string $period Period
     * @return array Summary data
     */
    public static function get_global_summary($period = 'month') {
        global $wpdb;

        $date_filter = self::get_date_filter($period);

        $query = "SELECT 
            COUNT(DISTINCT campaign_id) as active_campaigns,
            COUNT(*) as total_requests,
            SUM(CAST(JSON_EXTRACT(context, '$.tokens') AS UNSIGNED)) as total_tokens
        FROM {$wpdb->prefix}abc_logs
        WHERE category = 'ai'
        {$date_filter}";

        $data = $wpdb->get_row($query, ARRAY_A);

        return [
            'period' => $period,
            'active_campaigns' => isset($data['active_campaigns']) ? intval($data['active_campaigns']) : 0,
            'total_requests' => isset($data['total_requests']) ? intval($data['total_requests']) : 0,
            'total_tokens' => isset($data['total_tokens']) ? intval($data['total_tokens']) : 0,
        ];
    }

    /**
     * Format token count for display
     *
     * @param int $tokens Token count
     * @return string Formatted string
     */
    public static function format_tokens($tokens) {
        if ($tokens < 1000) {
            return number_format($tokens) . ' tokens';
        } elseif ($tokens < 1000000) {
            return number_format($tokens / 1000, 1) . 'K tokens';
        } else {
            return number_format($tokens / 1000000, 2) . 'M tokens';
        }
    }

    /**
     * Format cost for display
     *
     * @param float $cost Cost in USD
     * @return string Formatted string
     */
    public static function format_cost($cost) {
        if ($cost < 0.01) {
            return '$' . number_format($cost, 4);
        } elseif ($cost < 1.00) {
            return '$' . number_format($cost, 3);
        } else {
            return '$' . number_format($cost, 2);
        }
    }
}
