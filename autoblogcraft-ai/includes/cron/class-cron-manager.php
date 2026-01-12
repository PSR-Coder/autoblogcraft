<?php
/**
 * Cron Manager
 *
 * Manages scheduled jobs using Action Scheduler.
 * Handles job registration, scheduling, and execution.
 *
 * @package AutoBlogCraft\Cron
 * @since 2.0.0
 */

namespace AutoBlogCraft\Cron;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Cron\Cron_Detector;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron Manager class
 *
 * Responsibilities:
 * - Register scheduled jobs
 * - Schedule recurring actions
 * - Monitor job execution
 * - Handle job failures
 * - Provide job status information
 *
 * @since 2.0.0
 */
class Cron_Manager {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Action group
     *
     * @var string
     */
    private $group = 'autoblogcraft';

    /**
     * Registered jobs
     *
     * @var array
     */
    private $jobs = [];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Initialize cron manager
     *
     * @since 2.0.0
     */
    public function init() {
        // Check if Action Scheduler functions are available
        $has_action_scheduler = function_exists('as_schedule_recurring_action');
        
        if ($has_action_scheduler) {
            $this->logger->info("Cron Manager: Using Action Scheduler");
        } else {
            $this->logger->warning("Cron Manager: Action Scheduler not available, using WP-Cron fallback");
        }

        // Register jobs
        $this->register_jobs();

        // Schedule jobs on init
        add_action('init', [$this, 'schedule_jobs'], 20);

        // Hook job actions
        $this->hook_jobs();

        $this->logger->debug("Cron Manager initialized with " . count($this->jobs) . " jobs");
    }

    /**
     * Register all jobs
     *
     * @since 2.0.0
     */
    private function register_jobs() {
        // Discovery job - runs every 5 minutes
        $this->jobs['discovery'] = [
            'hook' => 'abc_discovery_job',
            'interval' => 300, // 5 minutes
            'class' => Discovery_Job::class,
            'enabled' => true,
        ];

        // Processing job - runs every 2 minutes
        $this->jobs['processing'] = [
            'hook' => 'abc_processing_job',
            'interval' => 120, // 2 minutes
            'class' => Processing_Job::class,
            'enabled' => true,
        ];

        // Cleanup job - runs daily
        $this->jobs['cleanup'] = [
            'hook' => 'abc_cleanup_job',
            'interval' => 86400, // 24 hours
            'class' => Cleanup_Job::class,
            'enabled' => true,
        ];

        // Rate limit reset job - runs every 5 minutes (checks timing internally)
        $this->jobs['rate_limit_reset'] = [
            'hook' => 'abc_rate_limit_reset_job',
            'interval' => 300, // 5 minutes
            'class' => Rate_Limit_Reset_Job::class,
            'enabled' => true,
        ];

        // Allow filtering
        $this->jobs = apply_filters('abc_cron_jobs', $this->jobs);

        $this->logger->debug("Registered " . count($this->jobs) . " cron jobs");
    }

    /**
     * Schedule all jobs
     *
     * @since 2.0.0
     */
    public function schedule_jobs() {
        foreach ($this->jobs as $job_id => $job) {
            if (!$job['enabled']) {
                continue;
            }

            $this->schedule_job($job_id, $job);
        }
    }

    /**
     * Schedule a single job
     *
     * @since 2.0.0
     * @param string $job_id Job identifier.
     * @param array $job Job configuration.
     */
    private function schedule_job($job_id, $job) {
        $hook = $job['hook'];
        $interval = $job['interval'];

        // Check if already scheduled
        if (as_next_scheduled_action($hook, [], $this->group)) {
            return;
        }

        // Schedule recurring action
        as_schedule_recurring_action(
            time(),
            $interval,
            $hook,
            [],
            $this->group
        );

        $this->logger->info("Scheduled job: {$job_id} (every {$interval}s)");
    }

    /**
     * Hook job actions
     *
     * @since 2.0.0
     */
    private function hook_jobs() {
        foreach ($this->jobs as $job_id => $job) {
            if (!$job['enabled']) {
                continue;
            }

            $hook = $job['hook'];
            $class = $job['class'];

            // Add action hook
            add_action($hook, function() use ($job_id, $class) {
                $this->execute_job($job_id, $class);
            });
        }
    }

    /**
     * Execute a job
     *
     * @since 2.0.0
     * @param string $job_id Job identifier.
     * @param string $class Job class name.
     */
    private function execute_job($job_id, $class) {
        // Record cron execution for detector
        $detector = Cron_Detector::get_instance();
        $detector->record_cron_run();

        $this->logger->info("Executing job: {$job_id}");

        $start_time = microtime(true);

        try {
            // Instantiate job class
            if (!class_exists($class)) {
                throw new \Exception("Job class not found: {$class}");
            }

            $job = new $class();

            // Execute job
            if (!method_exists($job, 'execute')) {
                throw new \Exception("Job class missing execute() method: {$class}");
            }

            $result = $job->execute();

            $duration = round(microtime(true) - $start_time, 2);

            $this->logger->info("Job completed: {$job_id} ({$duration}s)", [
                'job_id' => $job_id,
                'duration' => $duration,
                'result' => $result,
            ]);

            // Fire completion action
            do_action('abc_job_completed', $job_id, $result, $duration);

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $start_time, 2);

            $this->logger->error("Job failed: {$job_id} - " . $e->getMessage(), [
                'job_id' => $job_id,
                'duration' => $duration,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fire failure action
            do_action('abc_job_failed', $job_id, $e, $duration);
        }
    }

    /**
     * Unschedule all jobs
     *
     * @since 2.0.0
     */
    public function unschedule_all() {
        foreach ($this->jobs as $job_id => $job) {
            $this->unschedule_job($job['hook']);
        }

        $this->logger->info("Unscheduled all jobs");
    }

    /**
     * Unschedule a single job
     *
     * @since 2.0.0
     * @param string $hook Action hook.
     */
    public function unschedule_job($hook) {
        as_unschedule_all_actions($hook, [], $this->group);
        $this->logger->info("Unscheduled job: {$hook}");
    }

    /**
     * Get job status
     *
     * @since 2.0.0
     * @param string $hook Action hook.
     * @return array Status information.
     */
    public function get_job_status($hook) {
        $next_run = as_next_scheduled_action($hook, [], $this->group);
        
        $status = [
            'scheduled' => (bool) $next_run,
            'next_run' => $next_run ? $next_run : null,
            'next_run_human' => $next_run ? human_time_diff($next_run) : null,
        ];

        // Get pending count
        $pending = as_get_scheduled_actions([
            'hook' => $hook,
            'group' => $this->group,
            'status' => 'pending',
        ]);

        $status['pending_count'] = count($pending);

        // Get running count
        $running = as_get_scheduled_actions([
            'hook' => $hook,
            'group' => $this->group,
            'status' => 'in-progress',
        ]);

        $status['running_count'] = count($running);

        // Get failed count
        $failed = as_get_scheduled_actions([
            'hook' => $hook,
            'group' => $this->group,
            'status' => 'failed',
        ]);

        $status['failed_count'] = count($failed);

        return $status;
    }

    /**
     * Get all jobs status
     *
     * @since 2.0.0
     * @return array All jobs status.
     */
    public function get_all_status() {
        $status = [];

        foreach ($this->jobs as $job_id => $job) {
            $status[$job_id] = $this->get_job_status($job['hook']);
            $status[$job_id]['enabled'] = $job['enabled'];
            $status[$job_id]['interval'] = $job['interval'];
        }

        return $status;
    }

    /**
     * Trigger job immediately
     *
     * @since 2.0.0
     * @param string $job_id Job identifier.
     * @return int|false Action ID or false.
     */
    public function trigger_now($job_id) {
        if (!isset($this->jobs[$job_id])) {
            $this->logger->error("Unknown job: {$job_id}");
            return false;
        }

        $hook = $this->jobs[$job_id]['hook'];

        // Schedule single action immediately
        $action_id = as_schedule_single_action(
            time(),
            $hook,
            [],
            $this->group
        );

        $this->logger->info("Triggered job immediately: {$job_id}");

        return $action_id;
    }

    /**
     * Clear failed jobs
     *
     * @since 2.0.0
     * @param string|null $hook Optional specific hook.
     * @return int Number of cleared jobs.
     */
    public function clear_failed($hook = null) {
        $args = [
            'group' => $this->group,
            'status' => 'failed',
        ];

        if ($hook) {
            $args['hook'] = $hook;
        }

        $failed = as_get_scheduled_actions($args);
        $count = count($failed);

        foreach ($failed as $action) {
            as_unschedule_action($action->get_hook(), $action->get_args(), $this->group);
        }

        $this->logger->info("Cleared {$count} failed jobs");

        return $count;
    }

    /**
     * Enable job
     *
     * @since 2.0.0
     * @param string $job_id Job identifier.
     */
    public function enable_job($job_id) {
        if (!isset($this->jobs[$job_id])) {
            return;
        }

        $this->jobs[$job_id]['enabled'] = true;
        $this->schedule_job($job_id, $this->jobs[$job_id]);

        $this->logger->info("Enabled job: {$job_id}");
    }

    /**
     * Disable job
     *
     * @since 2.0.0
     * @param string $job_id Job identifier.
     */
    public function disable_job($job_id) {
        if (!isset($this->jobs[$job_id])) {
            return;
        }

        $this->jobs[$job_id]['enabled'] = false;
        $this->unschedule_job($this->jobs[$job_id]['hook']);

        $this->logger->info("Disabled job: {$job_id}");
    }

    /**
     * Get registered jobs
     *
     * @since 2.0.0
     * @return array Registered jobs.
     */
    public function get_jobs() {
        return $this->jobs;
    }
}
