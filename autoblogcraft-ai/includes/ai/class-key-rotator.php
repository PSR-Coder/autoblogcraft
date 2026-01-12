<?php
/**
 * AI Key Rotator
 *
 * Handles API key rotation strategies for load balancing and failover.
 * Single Responsibility: Key selection logic only.
 *
 * @package AutoBlogCraft\AI
 * @since 2.0.0
 */

namespace AutoBlogCraft\AI;

use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Key Rotator class
 *
 * Implements different rotation strategies:
 * - Round Robin: Cycles through keys sequentially
 * - Random: Selects random key each time
 * - Least Used: Selects key with lowest usage
 * - Failover: Primary key with fallbacks
 */
class Key_Rotator {
    /**
     * Key Manager instance
     *
     * @var Key_Manager
     */
    private $key_manager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Rotation strategies
     *
     * @var array
     */
    const STRATEGIES = [
        'round_robin',
        'random',
        'least_used',
        'failover',
    ];

    /**
     * Constructor
     *
     * @param Key_Manager $key_manager Key Manager instance
     */
    public function __construct(Key_Manager $key_manager) {
        $this->key_manager = $key_manager;
        $this->logger = Logger::instance();
    }

    /**
     * Get next key using specified strategy
     *
     * @param string $provider Provider name
     * @param string $strategy Rotation strategy
     * @param array $state Current rotation state (for stateful strategies)
     * @return array|WP_Error {
     *     @type array $key Selected key data
     *     @type array $state Updated state for next rotation
     * }
     */
    public function get_next_key($provider, $strategy = 'round_robin', $state = []) {
        // Validate strategy
        if (!in_array($strategy, self::STRATEGIES, true)) {
            return new WP_Error(
                'invalid_strategy',
                sprintf(
                    __('Invalid rotation strategy. Must be one of: %s', 'autoblogcraft-ai'),
                    implode(', ', self::STRATEGIES)
                )
            );
        }

        // Get available keys for provider
        $keys = $this->key_manager->get_keys_by_provider($provider, true);

        if (empty($keys)) {
            return new WP_Error(
                'no_keys_available',
                sprintf(
                    __('No active API keys found for provider: %s', 'autoblogcraft-ai'),
                    $provider
                )
            );
        }

        // Filter out keys that exceeded quota
        $available_keys = $this->filter_available_keys($keys);

        if (empty($available_keys)) {
            return new WP_Error(
                'quota_exceeded',
                sprintf(
                    __('All API keys for %s have exceeded their quotas', 'autoblogcraft-ai'),
                    $provider
                )
            );
        }

        // Select key based on strategy
        $selected = $this->select_key($available_keys, $strategy, $state);

        if (is_wp_error($selected)) {
            return $selected;
        }

        // Get full key data with decrypted API key
        $key_data = $this->key_manager->get_key($selected['key_id'], true);

        if (is_wp_error($key_data)) {
            return $key_data;
        }

        return [
            'key' => $key_data,
            'state' => $selected['state'],
        ];
    }

    /**
     * Filter keys to only those with available quota
     *
     * @param array $keys Array of key data
     * @return array Filtered keys
     */
    private function filter_available_keys($keys) {
        $available = [];

        foreach ($keys as $key) {
            $quota_check = $this->key_manager->check_quota($key['id']);
            
            if ($quota_check === true) {
                $available[] = $key;
            } else {
                // Log quota exceeded
                $this->logger->warning(
                    null,
                    'ai',
                    sprintf(
                        'Key %d (%s) quota exceeded: %s',
                        $key['id'],
                        $key['label'] ?: 'unlabeled',
                        $quota_check->get_error_message()
                    )
                );
            }
        }

        return $available;
    }

    /**
     * Select key based on strategy
     *
     * @param array $keys Available keys
     * @param string $strategy Rotation strategy
     * @param array $state Current state
     * @return array|WP_Error {
     *     @type int $key_id Selected key ID
     *     @type array $state Updated state
     * }
     */
    private function select_key($keys, $strategy, $state) {
        switch ($strategy) {
            case 'round_robin':
                return $this->select_round_robin($keys, $state);

            case 'random':
                return $this->select_random($keys);

            case 'least_used':
                return $this->select_least_used($keys);

            case 'failover':
                return $this->select_failover($keys, $state);

            default:
                return new WP_Error('unknown_strategy', __('Unknown rotation strategy', 'autoblogcraft-ai'));
        }
    }

    /**
     * Round Robin strategy
     *
     * Cycles through keys sequentially.
     *
     * @param array $keys Available keys
     * @param array $state Current state
     * @return array
     */
    private function select_round_robin($keys, $state) {
        $total = count($keys);
        $current_index = isset($state['current_index']) ? intval($state['current_index']) : 0;

        // Wrap around if necessary
        if ($current_index >= $total) {
            $current_index = 0;
        }

        $selected_key = $keys[$current_index];
        $next_index = ($current_index + 1) % $total;

        return [
            'key_id' => $selected_key['id'],
            'state' => [
                'current_index' => $next_index,
                'last_used' => $selected_key['id'],
            ],
        ];
    }

    /**
     * Random strategy
     *
     * Selects a random key from available keys.
     *
     * @param array $keys Available keys
     * @return array
     */
    private function select_random($keys) {
        $random_index = array_rand($keys);
        $selected_key = $keys[$random_index];

        return [
            'key_id' => $selected_key['id'],
            'state' => [
                'last_used' => $selected_key['id'],
            ],
        ];
    }

    /**
     * Least Used strategy
     *
     * Selects the key with the lowest number of requests today.
     *
     * @param array $keys Available keys
     * @return array
     */
    private function select_least_used($keys) {
        // Sort by requests_today ascending
        usort($keys, function($a, $b) {
            return $a['requests_today'] - $b['requests_today'];
        });

        $selected_key = $keys[0];

        return [
            'key_id' => $selected_key['id'],
            'state' => [
                'last_used' => $selected_key['id'],
            ],
        ];
    }

    /**
     * Failover strategy
     *
     * Uses primary key, falls back to others if primary fails.
     * State tracks which key is currently active.
     *
     * @param array $keys Available keys
     * @param array $state Current state
     * @return array
     */
    private function select_failover($keys, $state) {
        $primary_key_id = isset($state['primary_key_id']) ? intval($state['primary_key_id']) : null;
        $failed_keys = isset($state['failed_keys']) ? $state['failed_keys'] : [];

        // If no primary set, use first available
        if (!$primary_key_id) {
            $primary_key_id = $keys[0]['id'];
        }

        // Try to find primary key
        foreach ($keys as $key) {
            if ($key['id'] === $primary_key_id && !in_array($key['id'], $failed_keys, true)) {
                return [
                    'key_id' => $key['id'],
                    'state' => [
                        'primary_key_id' => $primary_key_id,
                        'failed_keys' => $failed_keys,
                        'using_failover' => false,
                    ],
                ];
            }
        }

        // Primary failed or unavailable, use first available non-failed key
        foreach ($keys as $key) {
            if (!in_array($key['id'], $failed_keys, true)) {
                $this->logger->warning(
                    null,
                    'ai',
                    sprintf(
                        'Using failover key %d (primary %d unavailable)',
                        $key['id'],
                        $primary_key_id
                    )
                );

                return [
                    'key_id' => $key['id'],
                    'state' => [
                        'primary_key_id' => $primary_key_id,
                        'failed_keys' => $failed_keys,
                        'using_failover' => true,
                        'failover_key_id' => $key['id'],
                    ],
                ];
            }
        }

        // All keys failed
        return new WP_Error(
            'all_keys_failed',
            __('All API keys have failed or are unavailable', 'autoblogcraft-ai')
        );
    }

    /**
     * Mark key as failed for failover strategy
     *
     * @param string $provider Provider name
     * @param int $key_id Key ID that failed
     * @param array $state Current state
     * @return array Updated state
     */
    public function mark_key_failed($provider, $key_id, $state) {
        $failed_keys = isset($state['failed_keys']) ? $state['failed_keys'] : [];
        
        if (!in_array($key_id, $failed_keys, true)) {
            $failed_keys[] = $key_id;
        }

        $this->logger->error(
            null,
            'ai',
            sprintf('Marked %s key %d as failed', $provider, $key_id)
        );

        return array_merge($state, [
            'failed_keys' => $failed_keys,
        ]);
    }

    /**
     * Reset failed keys for a provider
     *
     * Useful after some time has passed and keys might be working again.
     *
     * @param array $state Current state
     * @return array Reset state
     */
    public function reset_failed_keys($state) {
        $primary_key_id = isset($state['primary_key_id']) ? $state['primary_key_id'] : null;

        return [
            'primary_key_id' => $primary_key_id,
            'failed_keys' => [],
        ];
    }

    /**
     * Get rotation strategy for campaign
     *
     * Reads from campaign's AI config.
     *
     * @param int $campaign_id Campaign ID
     * @return string Strategy name
     */
    public function get_campaign_strategy($campaign_id) {
        global $wpdb;

        $strategy = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT rotation_strategy FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
                $campaign_id
            )
        );

        return $strategy ?: 'round_robin';
    }

    /**
     * Get rotation state for campaign
     *
     * Reads from campaign's AI config.
     *
     * @param int $campaign_id Campaign ID
     * @return array State data
     */
    public function get_campaign_state($campaign_id) {
        global $wpdb;

        $state = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT rotation_state FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
                $campaign_id
            )
        );

        if ($state) {
            $decoded = json_decode($state, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Update rotation state for campaign
     *
     * @param int $campaign_id Campaign ID
     * @param array $state State data
     * @return bool
     */
    public function update_campaign_state($campaign_id, $state) {
        global $wpdb;

        $updated = $wpdb->update(
            $wpdb->prefix . 'abc_campaign_ai_config',
            ['rotation_state' => wp_json_encode($state)],
            ['campaign_id' => $campaign_id],
            ['%s'],
            ['%d']
        );

        return $updated !== false;
    }
}
