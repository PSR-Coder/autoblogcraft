<?php
/**
 * Rate Limit Reset Job
 *
 * Scheduled job for resetting API key rate limit counters.
 * Runs daily to reset daily quotas and continuously to reset per-minute limits.
 *
 * @package AutoBlogCraft\Cron
 * @since 2.0.0
 */

namespace AutoBlogCraft\Cron;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\AI\Key_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limit Reset Job class
 *
 * Responsibilities:
 * - Reset daily API key quotas at midnight
 * - Reset monthly quotas on first day of month
 * - Clear per-minute rate limit cache
 * - Prevent keys from getting stuck in rate-limited state
 * - Log reset operations for monitoring
 *
 * @since 2.0.0
 */
class Rate_Limit_Reset_Job {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Key Manager instance
     *
     * @var Key_Manager
     */
    private $key_manager;

    /**
     * Cache group for rate limits
     *
     * @var string
     */
    private $cache_group = 'autoblogcraft_rate_limits';

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
        $this->key_manager = new Key_Manager();
    }

    /**
     * Execute rate limit reset job
     *
     * This is the main entry point called by the cron scheduler.
     * Determines which resets to perform based on current time.
     *
     * @since 2.0.0
     * @return array Execution results.
     */
    public function execute() {
        $this->logger->info("Rate limit reset job started");

        $start_time = microtime(true);

        $stats = [
            'daily_resets' => 0,
            'monthly_resets' => 0,
            'minute_resets' => 0,
            'cache_cleared' => false,
        ];

        // Check if it's midnight (daily reset time)
        $current_hour = (int) current_time('H');
        $current_minute = (int) current_time('i');
        
        if ($current_hour === 0 && $current_minute < 5) {
            // Reset daily counters (runs between 00:00-00:05)
            $stats['daily_resets'] = $this->reset_daily_counters();

            // Check if it's the first day of month
            $current_day = (int) current_time('j');
            if ($current_day === 1) {
                $stats['monthly_resets'] = $this->reset_monthly_counters();
            }
        }

        // Always reset per-minute rate limits (runs every job execution)
        $stats['minute_resets'] = $this->reset_minute_counters();

        // Clear rate limit cache
        $stats['cache_cleared'] = $this->clear_rate_limit_cache();

        $execution_time = round(microtime(true) - $start_time, 2);

        $this->logger->info(
            sprintf(
                "Rate limit reset completed in %ss - Daily: %d, Monthly: %d, Minute: %d, Cache cleared: %s",
                $execution_time,
                $stats['daily_resets'],
                $stats['monthly_resets'],
                $stats['minute_resets'],
                $stats['cache_cleared'] ? 'Yes' : 'No'
            )
        );

        return $stats;
    }

    /**
     * Reset daily quota counters for all API keys
     *
     * Called once per day at midnight to reset the daily request counter.
     * Allows keys that hit daily limits to start fresh.
     *
     * @since 2.0.0
     * @return int Number of keys reset.
     */
    public function reset_daily_counters() {
        $this->logger->info("Resetting daily API key counters");

        try {
            $reset_count = $this->key_manager->reset_daily_counters();

            $this->logger->info(
                sprintf('Successfully reset daily counters for %d API keys', $reset_count)
            );

            // Log to admin notices if significant
            if ($reset_count > 0) {
                $this->log_admin_notice(
                    sprintf(
                        'Daily API key quotas reset for %d keys at midnight.',
                        $reset_count
                    ),
                    'info'
                );
            }

            return $reset_count;

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to reset daily counters: %s', $e->getMessage())
            );
            return 0;
        }
    }

    /**
     * Reset monthly quota counters for all API keys
     *
     * Called on the first day of each month to reset monthly quotas.
     *
     * @since 2.0.0
     * @return int Number of keys reset.
     */
    public function reset_monthly_counters() {
        $this->logger->info("Resetting monthly API key counters");

        try {
            $reset_count = $this->key_manager->reset_monthly_counters();

            $this->logger->info(
                sprintf('Successfully reset monthly counters for %d API keys', $reset_count)
            );

            // Log to admin notices
            if ($reset_count > 0) {
                $this->log_admin_notice(
                    sprintf(
                        'Monthly API key quotas reset for %d keys at start of month.',
                        $reset_count
                    ),
                    'info'
                );
            }

            return $reset_count;

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to reset monthly counters: %s', $e->getMessage())
            );
            return 0;
        }
    }

    /**
     * Reset per-minute rate limit counters
     *
     * Clears cached per-minute rate limit data to prevent keys from
     * staying stuck in rate-limited state beyond the minute window.
     *
     * @since 2.0.0
     * @return int Number of counters cleared.
     */
    public function reset_minute_counters() {
        global $wpdb;

        try {
            // Get all API keys with per-minute limits
            $keys = $wpdb->get_results(
                "SELECT id, provider 
                FROM {$wpdb->prefix}abc_api_keys 
                WHERE status = 'active'",
                ARRAY_A
            );

            if (empty($keys)) {
                return 0;
            }

            $cleared_count = 0;

            foreach ($keys as $key) {
                // Clear per-minute cache for this key
                $cache_key = "rate_limit_minute_{$key['id']}";
                
                if (wp_cache_delete($cache_key, $this->cache_group)) {
                    $cleared_count++;
                }
            }

            if ($cleared_count > 0) {
                $this->logger->debug(
                    sprintf('Cleared per-minute rate limits for %d API keys', $cleared_count)
                );
            }

            return $cleared_count;

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to reset minute counters: %s', $e->getMessage())
            );
            return 0;
        }
    }

    /**
     * Clear all rate limit cache
     *
     * Removes all cached rate limit data including per-minute counters,
     * temporary bans, and throttle states.
     *
     * @since 2.0.0
     * @return bool Success status.
     */
    public function clear_rate_limit_cache() {
        try {
            // Clear the entire rate limit cache group
            wp_cache_flush_group($this->cache_group);

            $this->logger->debug('Cleared rate limit cache group');

            return true;

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to clear rate limit cache: %s', $e->getMessage())
            );
            return false;
        }
    }

    /**
     * Get current reset schedule information
     *
     * Returns information about next scheduled resets.
     *
     * @since 2.0.0
     * @return array Schedule information.
     */
    public function get_reset_schedule() {
        $current_time = current_time('timestamp');
        
        // Calculate next midnight
        $next_midnight = strtotime('tomorrow midnight', $current_time);
        
        // Calculate next month
        $next_month = strtotime('first day of next month midnight', $current_time);

        return [
            'next_daily_reset' => [
                'timestamp' => $next_midnight,
                'formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_midnight),
                'relative' => human_time_diff($current_time, $next_midnight),
            ],
            'next_monthly_reset' => [
                'timestamp' => $next_month,
                'formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_month),
                'relative' => human_time_diff($current_time, $next_month),
            ],
            'minute_reset_frequency' => 'Every job execution (~5 minutes)',
        ];
    }

    /**
     * Get reset statistics
     *
     * Returns statistics about recent reset operations.
     *
     * @since 2.0.0
     * @return array Statistics data.
     */
    public function get_reset_stats() {
        global $wpdb;

        try {
            // Get total active keys
            $total_keys = $wpdb->get_var(
                "SELECT COUNT(*) 
                FROM {$wpdb->prefix}abc_api_keys 
                WHERE status = 'active'"
            );

            // Get keys currently at daily limit
            $daily_limited = $wpdb->get_var(
                "SELECT COUNT(*) 
                FROM {$wpdb->prefix}abc_api_keys 
                WHERE status = 'active' 
                AND daily_quota > 0 
                AND requests_today >= daily_quota"
            );

            // Get keys currently at monthly limit
            $monthly_limited = $wpdb->get_var(
                "SELECT COUNT(*) 
                FROM {$wpdb->prefix}abc_api_keys 
                WHERE status = 'active' 
                AND monthly_quota > 0 
                AND requests_month >= monthly_quota"
            );

            // Get last reset time from options
            $last_daily_reset = get_option('abc_last_daily_reset', 'Never');
            $last_monthly_reset = get_option('abc_last_monthly_reset', 'Never');

            return [
                'total_active_keys' => (int) $total_keys,
                'daily_limited_keys' => (int) $daily_limited,
                'monthly_limited_keys' => (int) $monthly_limited,
                'last_daily_reset' => $last_daily_reset,
                'last_monthly_reset' => $last_monthly_reset,
                'health_status' => $this->calculate_health_status($total_keys, $daily_limited, $monthly_limited),
            ];

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to get reset stats: %s', $e->getMessage())
            );
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate overall rate limit health status
     *
     * @since 2.0.0
     * @param int $total_keys Total active keys.
     * @param int $daily_limited Keys at daily limit.
     * @param int $monthly_limited Keys at monthly limit.
     * @return string Health status (good, warning, critical).
     */
    private function calculate_health_status($total_keys, $daily_limited, $monthly_limited) {
        if ($total_keys === 0) {
            return 'no_keys';
        }

        $limited_percentage = (($daily_limited + $monthly_limited) / $total_keys) * 100;

        if ($limited_percentage === 0) {
            return 'good';
        } elseif ($limited_percentage < 50) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Force reset all counters (emergency function)
     *
     * Immediately resets all daily, monthly, and minute counters.
     * Should only be used for emergency situations or manual intervention.
     *
     * @since 2.0.0
     * @return array Reset results.
     */
    public function force_reset_all() {
        $this->logger->warning("Force reset initiated for all rate limit counters");

        $results = [
            'daily_resets' => $this->reset_daily_counters(),
            'monthly_resets' => $this->reset_monthly_counters(),
            'minute_resets' => $this->reset_minute_counters(),
            'cache_cleared' => $this->clear_rate_limit_cache(),
        ];

        // Update last reset timestamps
        update_option('abc_last_daily_reset', current_time('mysql'));
        update_option('abc_last_monthly_reset', current_time('mysql'));

        $this->logger->warning(
            sprintf(
                "Force reset completed - Daily: %d, Monthly: %d, Minute: %d",
                $results['daily_resets'],
                $results['monthly_resets'],
                $results['minute_resets']
            )
        );

        return $results;
    }

    /**
     * Log admin notice
     *
     * Stores a notice to be displayed in WordPress admin.
     *
     * @since 2.0.0
     * @param string $message Notice message.
     * @param string $type Notice type (info, warning, error, success).
     */
    private function log_admin_notice($message, $type = 'info') {
        // Store notice in transient for display on next admin page load
        $notices = get_transient('abc_admin_notices') ?: [];
        
        $notices[] = [
            'message' => $message,
            'type' => $type,
            'timestamp' => current_time('timestamp'),
        ];

        set_transient('abc_admin_notices', $notices, HOUR_IN_SECONDS);
    }

    /**
     * Check if job should run
     *
     * Determines if the reset job should execute based on schedule.
     *
     * @since 2.0.0
     * @return bool Whether job should run.
     */
    public function should_run() {
        // Always return true - job logic handles timing internally
        return true;
    }

    /**
     * Get job status
     *
     * Returns current status and health of the reset job.
     *
     * @since 2.0.0
     * @return array Job status information.
     */
    public function get_status() {
        return [
            'enabled' => true,
            'schedule' => $this->get_reset_schedule(),
            'stats' => $this->get_reset_stats(),
            'last_execution' => get_option('abc_rate_limit_reset_last_run', 'Never'),
        ];
    }
}
