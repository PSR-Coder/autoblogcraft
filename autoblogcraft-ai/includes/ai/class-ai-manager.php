<?php
/**
 * AI Manager
 *
 * Orchestrates AI providers, key rotation, and usage tracking.
 * Single Responsibility: Coordinate AI operations across campaigns.
 *
 * @package AutoBlogCraft\AI
 * @since 2.0.0
 */

namespace AutoBlogCraft\AI;

use AutoBlogCraft\AI\Providers\OpenAI_Provider;
use AutoBlogCraft\AI\Providers\Gemini_Provider;
use AutoBlogCraft\AI\Providers\Claude_Provider;
use AutoBlogCraft\AI\Providers\DeepSeek_Provider;
use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Manager class
 *
 * Clean orchestration:
 * - No duplicate logic
 * - Single point of provider access
 * - Handles key rotation transparently
 * - Tracks usage automatically
 */
class AI_Manager {
    /**
     * Singleton instance
     *
     * @var AI_Manager
     */
    private static $instance = null;

    /**
     * Key Manager
     *
     * @var Key_Manager
     */
    private $key_manager;

    /**
     * Key Rotator
     *
     * @var Key_Rotator
     */
    private $key_rotator;

    /**
     * Logger
     *
     * @var Logger
     */
    private $logger;

    /**
     * Provider instances cache
     *
     * @var array
     */
    private $providers = [];

    /**
     * Get singleton instance
     *
     * @return AI_Manager
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Ensure Key_Manager is loaded
        if (!class_exists('AutoBlogCraft\AI\Key_Manager')) {
            require_once ABC_PLUGIN_DIR . 'includes/ai/class-key-manager.php';
        }
        
        // Ensure Key_Rotator is loaded
        if (!class_exists('AutoBlogCraft\AI\Key_Rotator')) {
            require_once ABC_PLUGIN_DIR . 'includes/ai/class-key-rotator.php';
        }
        
        $this->key_manager = new Key_Manager();
        $this->key_rotator = new Key_Rotator($this->key_manager);
        $this->logger = Logger::instance();
    }

    /**
     * Get provider instance
     *
     * @param string $provider_name Provider name (openai, gemini, claude, deepseek)
     * @return object|WP_Error Provider instance or error
     */
    public function get_provider($provider_name) {
        // Return cached instance if available
        if (isset($this->providers[$provider_name])) {
            return $this->providers[$provider_name];
        }

        // Create new instance
        $provider = $this->create_provider($provider_name);

        if (is_wp_error($provider)) {
            return $provider;
        }

        // Cache it
        $this->providers[$provider_name] = $provider;

        return $provider;
    }

    /**
     * Create provider instance
     *
     * @param string $provider_name Provider name
     * @return object|WP_Error Provider instance or error
     */
    private function create_provider($provider_name) {
        $provider_classes = [
            'openai' => OpenAI_Provider::class,
            'gemini' => Gemini_Provider::class,
            'claude' => Claude_Provider::class,
            'deepseek' => DeepSeek_Provider::class,
        ];

        if (!isset($provider_classes[$provider_name])) {
            return new WP_Error(
                'unknown_provider',
                sprintf(
                    __('Unknown AI provider: %s', 'autoblogcraft-ai'),
                    $provider_name
                )
            );
        }

        $class_name = $provider_classes[$provider_name];

        if (!class_exists($class_name)) {
            return new WP_Error(
                'provider_class_missing',
                sprintf(
                    __('Provider class not found: %s', 'autoblogcraft-ai'),
                    $class_name
                )
            );
        }

        return new $class_name();
    }

    /**
     * Execute AI operation with automatic key rotation
     *
     * This is the main entry point for all AI operations.
     * Handles key rotation, error recovery, and usage tracking.
     *
     * @param int $campaign_id Campaign ID
     * @param string $operation Operation name (rewrite_content, translate, humanize)
     * @param array $args Operation arguments
     * @return array|WP_Error Operation result or error
     */
    public function execute($campaign_id, $operation, $args = []) {
        // Get campaign AI config
        $config = $this->get_campaign_config($campaign_id);

        if (is_wp_error($config)) {
            return $config;
        }

        $provider_name = $config['provider'];
        $strategy = $config['rotation_strategy'];
        $state = $config['rotation_state'];

        // Get next API key using rotation strategy
        $key_result = $this->key_rotator->get_next_key($provider_name, $strategy, $state);

        if (is_wp_error($key_result)) {
            $this->logger->error(
                $campaign_id,
                'ai',
                sprintf('Failed to get API key: %s', $key_result->get_error_message())
            );
            return $key_result;
        }

        $key_data = $key_result['key'];
        $new_state = $key_result['state'];

        // Get provider instance
        $provider = $this->get_provider($provider_name);

        if (is_wp_error($provider)) {
            return $provider;
        }

        // Verify provider supports operation
        if (!method_exists($provider, $operation)) {
            return new WP_Error(
                'unsupported_operation',
                sprintf(
                    __('Provider %s does not support operation: %s', 'autoblogcraft-ai'),
                    $provider_name,
                    $operation
                )
            );
        }

        // Inject API key into arguments
        $args['api_key'] = $key_data['api_key'];

        // If model not specified, use campaign default or provider default
        if (empty($args['model'])) {
            $args['model'] = !empty($config['model']) ? $config['model'] : $provider->get_default_model();
        }

        // Execute operation
        $result = $provider->$operation($args[0] ?? '', $args);

        // Handle result
        if (is_wp_error($result)) {
            // Mark key as failed for failover strategy
            if ($strategy === 'failover') {
                $new_state = $this->key_rotator->mark_key_failed($provider_name, $key_data['id'], $new_state);
                $this->key_rotator->update_campaign_state($campaign_id, $new_state);
            }

            $this->logger->error(
                $campaign_id,
                'ai',
                sprintf(
                    '%s operation failed: %s',
                    $operation,
                    $result->get_error_message()
                )
            );

            return $result;
        }

        // Track usage
        $tokens_used = isset($result['tokens_used']) ? $result['tokens_used'] : 0;
        $this->key_manager->track_usage($key_data['id'], $tokens_used);

        // Update rotation state
        $this->key_rotator->update_campaign_state($campaign_id, $new_state);

        // Log success
        $this->logger->info(
            $campaign_id,
            'ai',
            sprintf(
                '%s completed (%d tokens, key: %d)',
                $operation,
                $tokens_used,
                $key_data['id']
            )
        );

        return $result;
    }

    /**
     * Rewrite content (convenience method)
     *
     * @param int $campaign_id Campaign ID
     * @param string $content Content to rewrite
     * @param array $options Options
     * @return array|WP_Error
     */
    public function rewrite_content($campaign_id, $content, $options = []) {
        return $this->execute($campaign_id, 'rewrite_content', array_merge([$content], $options));
    }

    /**
     * Translate content (convenience method)
     *
     * @param int $campaign_id Campaign ID
     * @param string $content Content to translate
     * @param array $options Options (from_language, to_language required)
     * @return array|WP_Error
     */
    public function translate($campaign_id, $content, $options = []) {
        return $this->execute($campaign_id, 'translate', array_merge([$content], $options));
    }

    /**
     * Humanize content (convenience method)
     *
     * @param int $campaign_id Campaign ID
     * @param string $content Content to humanize
     * @param array $options Options
     * @return array|WP_Error
     */
    public function humanize($campaign_id, $content, $options = []) {
        return $this->execute($campaign_id, 'humanize', array_merge([$content], $options));
    }

    /**
     * Get campaign AI configuration
     *
     * @param int $campaign_id Campaign ID
     * @return array|WP_Error Configuration or error
     */
    private function get_campaign_config($campaign_id) {
        global $wpdb;

        $config = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
                $campaign_id
            ),
            ARRAY_A
        );

        if (!$config) {
            return new WP_Error(
                'no_ai_config',
                __('Campaign has no AI configuration', 'autoblogcraft-ai')
            );
        }

        // Decode rotation state
        $rotation_state = !empty($config['rotation_state']) ? json_decode($config['rotation_state'], true) : [];

        return [
            'provider' => $config['provider'],
            'model' => $config['model'],
            'rotation_strategy' => $config['rotation_strategy'] ?: 'round_robin',
            'rotation_state' => is_array($rotation_state) ? $rotation_state : [],
            'primary_key_id' => $config['primary_key_id'],
            'fallback_key_ids' => !empty($config['fallback_key_ids']) ? json_decode($config['fallback_key_ids'], true) : [],
        ];
    }

    /**
     * Create or update campaign AI configuration
     *
     * @param int $campaign_id Campaign ID
     * @param array $config Configuration data
     * @return bool|WP_Error
     */
    public function save_campaign_config($campaign_id, $config) {
        global $wpdb;

        // Validate provider
        $valid_providers = ['openai', 'gemini', 'claude', 'deepseek'];
        if (empty($config['provider']) || !in_array($config['provider'], $valid_providers, true)) {
            return new WP_Error('invalid_provider', __('Invalid AI provider', 'autoblogcraft-ai'));
        }

        // Validate primary key exists
        if (empty($config['primary_key_id'])) {
            return new WP_Error('missing_key', __('Primary API key is required', 'autoblogcraft-ai'));
        }

        // Check if config exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT campaign_id FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
                $campaign_id
            )
        );

        $data = [
            'provider' => $config['provider'],
            'model' => isset($config['model']) ? $config['model'] : '',
            'primary_key_id' => $config['primary_key_id'],
            'fallback_key_ids' => isset($config['fallback_key_ids']) ? wp_json_encode($config['fallback_key_ids']) : '[]',
            'rotation_strategy' => isset($config['rotation_strategy']) ? $config['rotation_strategy'] : 'round_robin',
        ];

        if ($exists) {
            // Update
            $updated = $wpdb->update(
                $wpdb->prefix . 'abc_campaign_ai_config',
                $data,
                ['campaign_id' => $campaign_id],
                ['%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );

            return $updated !== false;
        } else {
            // Insert
            $data['campaign_id'] = $campaign_id;
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'abc_campaign_ai_config',
                $data,
                ['%d', '%s', '%s', '%d', '%s', '%s']
            );

            return $inserted !== false;
        }
    }

    /**
     * Get available providers
     *
     * @return array Provider names and labels
     */
    public function get_available_providers() {
        return [
            'openai' => __('OpenAI (ChatGPT)', 'autoblogcraft-ai'),
            'gemini' => __('Google Gemini', 'autoblogcraft-ai'),
            'claude' => __('Claude (Anthropic)', 'autoblogcraft-ai'),
            'deepseek' => __('DeepSeek', 'autoblogcraft-ai'),
        ];
    }
}
