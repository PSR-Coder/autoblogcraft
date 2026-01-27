<?php
/**
 * News Processor
 *
 * Processes discovered news items into full WordPress posts using AI.
 * Handles attribution, persona injection, and content rewriting.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.1.0
 */

namespace AutoBlogCraft\Processing;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Core\API_Key_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Processor Class
 */
class News_Processor
{

    private $logger;
    private $api_manager;

    public function __construct()
    {
        $this->logger = Logger::instance();
        $this->api_manager = new API_Key_Manager();
    }

    /**
     * Process a queue item
     * 
     * @param object $queue_item The queue item from DB.
     * @param object $campaign The campaign object.
     * @return int|WP_Error Post ID on success.
     */
    public function process_item($queue_item, $campaign)
    {
        $this->logger->info("Processing News Item: {$queue_item->id}");

        // 1. Prepare Context (Source Data)
        $source_data = json_decode($queue_item->source_data, true) ?: [];
        $context = [
            'title' => $queue_item->title,
            'excerpt' => $queue_item->excerpt,
            'url' => $queue_item->source_url,
            'source_name' => $source_data['source_name'] ?? 'Unknown Source',
            'publish_date' => $source_data['date'] ?? date('Y-m-d')
        ];

        // 2. Get AI Config
        $ai_config = $campaign->get_meta('_ai_config', true);
        $key_id = $ai_config['api_key_id'] ?? 0;
        $api_key = $this->api_manager->get_provider_key($key_id); // Basic retrieval

        if (!$api_key) {
            return new WP_Error('missing_api_key', 'No Valid API Key found for processing.');
        }

        // 3. Construct Prompt with Persona
        $system_prompt = $this->build_system_prompt($ai_config);
        $user_prompt = $this->build_user_prompt($context, $ai_config);

        // 4. Call AI (Mocking the LLM call for now, assume LLM_Gateway exists or inline)
        // In a real scenario, this would call LLM_Gateway::generate($api_key, $model, $prompts);
        $generated_content = $this->mock_ai_generate($system_prompt, $user_prompt);

        if (is_wp_error($generated_content))
            return $generated_content;

        // 5. Create Post
        $post_data = [
            'post_title' => $this->generate_title($queue_item->title), // Optional AI title gen
            'post_content' => $generated_content,
            'post_status' => $campaign->get_meta('_wp_post_status', 'publish'),
            'post_author' => $campaign->get_meta('_wp_author_id', get_current_user_id()),
            'post_category' => array_filter([$campaign->get_meta('_wp_category_id', 0)])
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // 6. Handle Attribution
        $this->add_attribution($post_id, $context, $ai_config['attribution_style'] ?? 'inline');

        return $post_id;
    }

    /**
     * Build System Prompt including Persona
     */
    private function build_system_prompt($config)
    {
        $base = "You are an expert news editor.";

        if (!empty($config['author_persona'])) {
            $base .= " Adopt the following persona:\n" . $config['author_persona'];
        }

        $base .= "\n\nYour goal is to rewrite the news story to be unique, engaging, and professional while maintaining factual accuracy.";

        return $base;
    }

    /**
     * Build User Prompt with News Context
     */
    private function build_user_prompt($context, $config)
    {
        $prompt = "Source Article:\n";
        $prompt .= "Title: {$context['title']}\n";
        $prompt .= "Source: {$context['source_name']}\n";
        $prompt .= "Content/Excerpt: {$context['excerpt']}\n";

        if (!empty($config['prompt_template'])) {
            $prompt .= "\nInstructions:\n" . $config['prompt_template'];
        }

        return $prompt;
    }

    /**
     * Add Attribution to Post
     */
    private function add_attribution($post_id, $context, $style)
    {
        if ($style === 'none')
            return;

        $link = sprintf('<a href="%s" target="_blank" rel="nofollow noopener">%s</a>', esc_url($context['url']), esc_html($context['source_name']));
        $html = "";

        switch ($style) {
            case 'footnote':
                $html = "<hr><em>Source: $link</em>";
                break;
            case 'endnote':
                $html = "<p>Originally published on $link.</p>";
                break;
            case 'inline':
            default:
                // Prepend or assume AI handled it, but let's append safely
                $html = "<p><small>Via: $link</small></p>";
                break;
        }

        if ($html) {
            $content = get_post_field('post_content', $post_id);
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $content . "\n\n" . $html
            ]);
        }
    }

    // Mock AI Generator for Structure
    private function mock_ai_generate($system, $user)
    {
        // Placeholder
        return "<!-- AI Generated Content -->\n<p>Here is the rewritten news story based on the input...</p>";
    }

    private function generate_title($original)
    {
        return "News: " . $original;
    }
}
