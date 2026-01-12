<?php
/**
 * Humanizer Module
 *
 * Humanizes AI-generated content to make it undetectable and more natural.
 *
 * @package AutoBlogCraft\Modules\Humanizer
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Humanizer;

use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Humanizer Module class
 *
 * Main controller for content humanization.
 *
 * @since 2.0.0
 */
class Humanizer_Module {

    /**
     * Instance of this class
     *
     * @var Humanizer_Module
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Undetectable provider
     *
     * @var Undetectable_Provider
     */
    private $undetectable;

    /**
     * GPT-4o humanizer
     *
     * @var GPT4o_Humanizer
     */
    private $gpt4o;

    /**
     * Get instance
     *
     * @since 2.0.0
     * @return Humanizer_Module
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    private function __construct() {
        $this->logger = Logger::instance();
        $this->undetectable = new Undetectable_Provider();
        $this->gpt4o = new GPT4o_Humanizer();
    }

    /**
     * Initialize humanizer module
     *
     * @since 2.0.0
     */
    public function init() {
        // Add humanizer meta box
        add_action('add_meta_boxes', [$this, 'add_humanizer_meta_box']);
        add_action('save_post', [$this, 'save_humanizer_meta']);

        // Add final pass filter
        add_filter('the_content', [$this, 'apply_final_pass'], 999);

        $this->logger->info('Humanizer module initialized');
    }

    /**
     * Check if humanizer is enabled
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return bool True if enabled.
     */
    public function is_enabled($campaign_id) {
        $enabled = get_post_meta($campaign_id, '_humanizer_enabled', true);
        return (bool) $enabled;
    }

    /**
     * Humanize content
     *
     * @since 2.0.0
     * @param string $content Content to humanize.
     * @param int $level Humanization level (1-10).
     * @param int $campaign_id Campaign ID (optional).
     * @return string|WP_Error Humanized content or error.
     */
    public function humanize($content, $level = 5, $campaign_id = 0) {
        if (empty($content)) {
            return new WP_Error('empty_content', 'Cannot humanize empty content');
        }

        // Validate level
        $level = max(1, min(10, intval($level)));

        $provider = $this->get_provider($campaign_id);

        $this->logger->debug('Humanizing content', [
            'provider' => $provider,
            'level' => $level,
            'length' => strlen($content),
        ]);

        // Humanize based on provider
        switch ($provider) {
            case 'undetectable_ai':
                return $this->undetectable->humanize($content, $level, $campaign_id);

            case 'gpt4o_humanizer':
                return $this->gpt4o->humanize($content, $level, $campaign_id);

            case 'internal':
                return $this->internal_humanize($content, $level);

            default:
                return new WP_Error('invalid_provider', "Humanizer provider '{$provider}' is not valid");
        }
    }

    /**
     * Internal humanization
     *
     * Uses pattern-based transformations without external APIs.
     *
     * @since 2.0.0
     * @param string $content Content to humanize.
     * @param int $level Humanization level.
     * @return string Humanized content.
     */
    private function internal_humanize($content, $level) {
        $content = $this->add_natural_variations($content, $level);
        $content = $this->add_filler_words($content, $level);
        $content = $this->vary_sentence_structure($content, $level);
        $content = $this->add_personal_touches($content, $level);
        
        return $content;
    }

    /**
     * Add natural variations
     *
     * @since 2.0.0
     * @param string $content Content.
     * @param int $level Level.
     * @return string Modified content.
     */
    private function add_natural_variations($content, $level) {
        $replacements = [
            'very good' => ['excellent', 'great', 'superb', 'outstanding'],
            'very bad' => ['terrible', 'awful', 'poor', 'disappointing'],
            'very big' => ['huge', 'enormous', 'massive', 'gigantic'],
            'very small' => ['tiny', 'minuscule', 'compact', 'petite'],
            'a lot of' => ['many', 'numerous', 'plenty of', 'countless'],
            'in addition' => ['furthermore', 'moreover', 'additionally', 'also'],
            'for example' => ['for instance', 'such as', 'like', 'including'],
            'in conclusion' => ['to sum up', 'ultimately', 'in summary', 'all in all'],
        ];

        // Apply based on level
        $apply_percentage = $level * 10; // 10% per level

        foreach ($replacements as $search => $options) {
            if (rand(1, 100) <= $apply_percentage) {
                $replacement = $options[array_rand($options)];
                $content = preg_replace('/\b' . preg_quote($search, '/') . '\b/i', $replacement, $content, 1);
            }
        }

        return $content;
    }

    /**
     * Add filler words
     *
     * @since 2.0.0
     * @param string $content Content.
     * @param int $level Level.
     * @return string Modified content.
     */
    private function add_filler_words($content, $level) {
        if ($level < 3) {
            return $content;
        }

        $fillers = [
            'actually', 'basically', 'essentially', 'generally',
            'honestly', 'literally', 'obviously', 'particularly',
            'probably', 'really', 'simply', 'typically',
        ];

        $sentences = preg_split('/([.!?]+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        for ($i = 0; $i < count($sentences); $i += 2) {
            if (empty(trim($sentences[$i]))) {
                continue;
            }

            // Add filler word based on level
            if (rand(1, 100) <= ($level * 5)) {
                $filler = $fillers[array_rand($fillers)];
                $sentences[$i] = preg_replace('/^(\s*)(\w+)/', '$1$2, ' . $filler . ',', $sentences[$i], 1);
            }
        }

        return implode('', $sentences);
    }

    /**
     * Vary sentence structure
     *
     * @since 2.0.0
     * @param string $content Content.
     * @param int $level Level.
     * @return string Modified content.
     */
    private function vary_sentence_structure($content, $level) {
        if ($level < 5) {
            return $content;
        }

        // Add question variations
        $content = preg_replace_callback('/(\w+)\s+is\s+important\./', function ($matches) {
            $variations = [
                "Why is {$matches[1]} important?",
                "What makes {$matches[1]} important?",
                "{$matches[1]} is crucial - here's why.",
            ];
            return $variations[array_rand($variations)];
        }, $content);

        return $content;
    }

    /**
     * Add personal touches
     *
     * @since 2.0.0
     * @param string $content Content.
     * @param int $level Level.
     * @return string Modified content.
     */
    private function add_personal_touches($content, $level) {
        if ($level < 7) {
            return $content;
        }

        $touches = [
            "In my experience,",
            "From what I've seen,",
            "Personally,",
            "I've found that",
            "It's worth noting that",
        ];

        // Add to random paragraphs
        $paragraphs = explode("\n\n", $content);
        
        foreach ($paragraphs as $key => $para) {
            if (rand(1, 100) <= 20 && !empty(trim($para))) {
                $touch = $touches[array_rand($touches)];
                $paragraphs[$key] = $touch . ' ' . lcfirst(trim($para));
            }
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * Get humanizer provider
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return string Provider name.
     */
    private function get_provider($campaign_id = 0) {
        if ($campaign_id > 0) {
            $provider = get_post_meta($campaign_id, '_humanizer_provider', true);
            if (!empty($provider)) {
                return $provider;
            }
        }

        return get_option('abc_humanizer_provider', 'internal');
    }

    /**
     * Add humanizer meta box
     *
     * @since 2.0.0
     */
    public function add_humanizer_meta_box() {
        add_meta_box(
            'abc_humanizer_meta',
            __('AI Humanizer', 'autoblogcraft'),
            [$this, 'render_humanizer_meta_box'],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render humanizer meta box
     *
     * @since 2.0.0
     * @param WP_Post $post Post object.
     */
    public function render_humanizer_meta_box($post) {
        $humanized = get_post_meta($post->ID, '_abc_humanized', true);
        $level = get_post_meta($post->ID, '_abc_humanizer_level', true);

        wp_nonce_field('abc_humanizer_meta', 'abc_humanizer_meta_nonce');
        ?>
        <p>
            <label>
                <input type="checkbox" name="abc_humanize_content" value="1">
                <?php esc_html_e('Humanize this content', 'autoblogcraft'); ?>
            </label>
        </p>

        <p>
            <label for="abc_humanizer_level"><?php esc_html_e('Humanization Level:', 'autoblogcraft'); ?></label>
            <select name="abc_humanizer_level" id="abc_humanizer_level" class="widefat">
                <?php for ($i = 1; $i <= 10; $i++) : ?>
                    <option value="<?php echo $i; ?>" <?php selected($level, $i); ?>>
                        <?php echo $i; ?> - <?php echo $this->get_level_description($i); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </p>

        <?php if ($humanized) : ?>
            <p>
                <strong><?php esc_html_e('Status:', 'autoblogcraft'); ?></strong>
                <span style="color: green;">✓ <?php esc_html_e('Humanized', 'autoblogcraft'); ?></span>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save humanizer meta
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     */
    public function save_humanizer_meta($post_id) {
        if (!isset($_POST['abc_humanizer_meta_nonce']) || 
            !wp_verify_nonce($_POST['abc_humanizer_meta_nonce'], 'abc_humanizer_meta')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['abc_humanize_content']) && $_POST['abc_humanize_content'] === '1') {
            $post = get_post($post_id);
            $level = isset($_POST['abc_humanizer_level']) ? intval($_POST['abc_humanizer_level']) : 5;

            $campaign_id = get_post_meta($post_id, '_abc_campaign_id', true);
            $humanized = $this->humanize($post->post_content, $level, $campaign_id);

            if (!is_wp_error($humanized)) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $humanized,
                ]);

                update_post_meta($post_id, '_abc_humanized', true);
                update_post_meta($post_id, '_abc_humanizer_level', $level);
            }
        }
    }

    /**
     * Apply final pass
     *
     * @since 2.0.0
     * @param string $content Content.
     * @return string Modified content.
     */
    public function apply_final_pass($content) {
        global $post;

        if (!$post || !get_post_meta($post->ID, '_humanizer_final_pass', true)) {
            return $content;
        }

        // Light touch-ups
        $content = $this->add_natural_variations($content, 3);
        
        return $content;
    }

    /**
     * Get level description
     *
     * @since 2.0.0
     * @param int $level Level.
     * @return string Description.
     */
    private function get_level_description($level) {
        $descriptions = [
            1 => 'Minimal',
            2 => 'Light',
            3 => 'Light-Medium',
            4 => 'Medium',
            5 => 'Medium (Recommended)',
            6 => 'Medium-High',
            7 => 'High',
            8 => 'Very High',
            9 => 'Maximum',
            10 => 'Ultra (Experimental)',
        ];

        return $descriptions[$level] ?? 'Medium';
    }

    /**
     * Get statistics
     *
     * @since 2.0.0
     * @return array Statistics.
     */
    public function get_stats() {
        return [
            'total_humanized' => $this->count_humanized_posts(),
            'providers' => [
                'undetectable_ai' => $this->undetectable->is_available(),
                'gpt4o_humanizer' => $this->gpt4o->is_available(),
                'internal' => true,
            ],
        ];
    }

    /**
     * Count humanized posts
     *
     * @since 2.0.0
     * @return int Count.
     */
    private function count_humanized_posts() {
        $query = new \WP_Query([
            'post_type' => 'post',
            'meta_key' => '_abc_humanized',
            'meta_value' => true,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        return $query->found_posts;
    }
}
