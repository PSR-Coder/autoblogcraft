<?php
/**
 * Featured Image Generator
 *
 * DALL-E 3 integration for AI image generation + Unsplash API for stock photos
 * with fallback logic and caching.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing;

use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Featured Image Generator class
 *
 * Handles featured image generation using DALL-E 3, Stable Diffusion,
 * Unsplash, or source extraction with intelligent fallback.
 *
 * @since 2.0.0
 */
class Featured_Image_Generator {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Supported strategies
     *
     * @var array
     */
    private $strategies = ['dalle3', 'stable_diffusion', 'unsplash', 'source', 'none'];

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Generate featured image
     *
     * Main entry point for featured image generation.
     *
     * @since 2.0.0
     * @param array $settings Image generation settings.
     * @param array $context Additional context (title, content, etc.).
     * @return int|WP_Error Attachment ID or error.
     */
    public function generate($settings = [], $context = []) {
        $strategy = !empty($settings['strategy']) ? $settings['strategy'] : 'unsplash';
        
        if (!in_array($strategy, $this->strategies, true)) {
            return new WP_Error('invalid_strategy', "Invalid strategy: {$strategy}");
        }

        // Strategy: none
        if ($strategy === 'none') {
            return null;
        }

        $this->logger->info("Generating featured image using strategy: {$strategy}", [
            'context' => $context,
        ]);

        // Try primary strategy
        $result = $this->generate_by_strategy($strategy, $settings, $context);

        if (!is_wp_error($result) && $result) {
            return $result;
        }

        // Log failure and try fallback
        if (is_wp_error($result)) {
            $this->logger->warning("Strategy {$strategy} failed: " . $result->get_error_message());
        }

        // Try fallback strategy
        if (!empty($settings['fallback_strategy']) && $settings['fallback_strategy'] !== $strategy) {
            $this->logger->info("Trying fallback strategy: " . $settings['fallback_strategy']);
            $result = $this->generate_by_strategy($settings['fallback_strategy'], $settings, $context);
            
            if (!is_wp_error($result) && $result) {
                return $result;
            }
        }

        // Use fallback URL if provided
        if (!empty($settings['fallback_url'])) {
            $this->logger->info("Using fallback URL: " . $settings['fallback_url']);
            return $this->download_image($settings['fallback_url'], $context);
        }

        return is_wp_error($result) ? $result : new WP_Error('generation_failed', 'Failed to generate featured image');
    }

    /**
     * Generate by strategy
     *
     * @since 2.0.0
     * @param string $strategy Generation strategy.
     * @param array $settings Settings.
     * @param array $context Context.
     * @return int|WP_Error|null Attachment ID, error, or null.
     */
    private function generate_by_strategy($strategy, $settings, $context) {
        switch ($strategy) {
            case 'dalle3':
                return $this->generate_dalle3($settings, $context);
            
            case 'stable_diffusion':
                return $this->generate_stable_diffusion($settings, $context);
            
            case 'unsplash':
                return $this->fetch_unsplash($settings, $context);
            
            case 'source':
                return $this->extract_from_source($settings, $context);
            
            default:
                return new WP_Error('unknown_strategy', "Unknown strategy: {$strategy}");
        }
    }

    /**
     * Generate image using DALL-E 3
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @param array $context Context.
     * @return int|WP_Error Attachment ID or error.
     */
    private function generate_dalle3($settings, $context) {
        // Get OpenAI API key
        $api_key = $this->get_openai_key($settings);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Build prompt
        $prompt = $this->build_dalle_prompt($settings, $context);
        
        $this->logger->info("DALL-E 3 prompt: {$prompt}");

        // Call DALL-E 3 API
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => !empty($settings['size']) ? $settings['size'] : '1024x1024',
                'quality' => !empty($settings['quality']) ? $settings['quality'] : 'standard',
                'style' => !empty($settings['style']) ? $settings['style'] : 'natural',
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $error_message = !empty($data['error']['message']) 
                ? $data['error']['message'] 
                : 'DALL-E 3 API request failed';
            
            return new WP_Error('dalle3_error', $error_message, ['status' => $code]);
        }

        if (empty($data['data'][0]['url'])) {
            return new WP_Error('dalle3_no_image', 'No image URL in response');
        }

        $image_url = $data['data'][0]['url'];

        // Download and attach image
        return $this->download_image($image_url, $context);
    }

    /**
     * Generate image using Stable Diffusion
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @param array $context Context.
     * @return int|WP_Error Attachment ID or error.
     */
    private function generate_stable_diffusion($settings, $context) {
        // Get Stable Diffusion API key (e.g., Stability AI)
        $api_key = $this->get_stability_key($settings);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Build prompt
        $prompt = $this->build_dalle_prompt($settings, $context); // Reuse prompt builder

        $this->logger->info("Stable Diffusion prompt: {$prompt}");

        // Call Stability AI API
        $response = wp_remote_post('https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'text_prompts' => [
                    ['text' => $prompt, 'weight' => 1],
                ],
                'cfg_scale' => 7,
                'height' => 1024,
                'width' => 1024,
                'samples' => 1,
                'steps' => 30,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $error_message = !empty($data['message']) 
                ? $data['message'] 
                : 'Stable Diffusion API request failed';
            
            return new WP_Error('stable_diffusion_error', $error_message, ['status' => $code]);
        }

        if (empty($data['artifacts'][0]['base64'])) {
            return new WP_Error('stable_diffusion_no_image', 'No image data in response');
        }

        // Decode base64 image
        $image_data = base64_decode($data['artifacts'][0]['base64']);
        
        // Save to temp file and upload
        return $this->upload_image_data($image_data, 'stable-diffusion', $context);
    }

    /**
     * Fetch image from Unsplash
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @param array $context Context.
     * @return int|WP_Error Attachment ID or error.
     */
    public function fetch_unsplash($settings, $context) {
        // Get Unsplash API key
        $api_key = $this->get_unsplash_key($settings);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Build search query
        $query = $this->build_unsplash_query($settings, $context);
        
        $this->logger->info("Unsplash search query: {$query}");

        // Search Unsplash
        $response = wp_remote_get(
            'https://api.unsplash.com/search/photos?' . http_build_query([
                'query' => $query,
                'per_page' => 1,
                'orientation' => !empty($settings['orientation']) ? $settings['orientation'] : 'landscape',
            ]),
            [
                'headers' => [
                    'Authorization' => 'Client-ID ' . $api_key,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            return new WP_Error('unsplash_error', 'Unsplash API request failed', ['status' => $code]);
        }

        if (empty($data['results'][0]['urls']['regular'])) {
            return new WP_Error('unsplash_no_results', 'No images found on Unsplash');
        }

        $image_url = $data['results'][0]['urls']['regular'];
        $photographer = $data['results'][0]['user']['name'];
        $photo_link = $data['results'][0]['links']['html'];

        // Download image
        $attachment_id = $this->download_image($image_url, $context);

        if (!is_wp_error($attachment_id)) {
            // Add attribution as image caption
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $query);
            update_post_meta($attachment_id, '_unsplash_photographer', $photographer);
            update_post_meta($attachment_id, '_unsplash_url', $photo_link);
        }

        return $attachment_id;
    }

    /**
     * Extract image from source content
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @param array $context Context.
     * @return int|WP_Error|null Attachment ID, error, or null if no image found.
     */
    private function extract_from_source($settings, $context) {
        if (empty($context['content'])) {
            return new WP_Error('no_content', 'No content provided for image extraction');
        }

        // Try to find og:image or first image in content
        $image_url = null;

        // Check metadata first
        if (!empty($context['metadata']['og_image'])) {
            $image_url = $context['metadata']['og_image'];
        } elseif (!empty($context['metadata']['thumbnail'])) {
            $image_url = $context['metadata']['thumbnail'];
        } else {
            // Parse content for first image
            preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $context['content'], $matches);
            if (!empty($matches[1])) {
                $image_url = $matches[1];
            }
        }

        if (!$image_url) {
            return null; // No image found
        }

        return $this->download_image($image_url, $context);
    }

    /**
     * Download image from URL
     *
     * @since 2.0.0
     * @param string $url Image URL.
     * @param array $context Context for naming.
     * @return int|WP_Error Attachment ID or error.
     */
    private function download_image($url, $context = []) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Generate filename
        $filename = $this->generate_filename($context);

        // Download image
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Prepare file array
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp,
        ];

        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, 0);

        // Clean up temp file
        if (file_exists($tmp)) {
            @unlink($tmp);
        }

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $this->logger->info("Image downloaded and uploaded: {$attachment_id}");

        return $attachment_id;
    }

    /**
     * Upload image data directly
     *
     * @since 2.0.0
     * @param string $image_data Binary image data.
     * @param string $prefix Filename prefix.
     * @param array $context Context.
     * @return int|WP_Error Attachment ID or error.
     */
    private function upload_image_data($image_data, $prefix, $context = []) {
        $upload_dir = wp_upload_dir();
        $filename = $this->generate_filename($context, $prefix);
        $filepath = $upload_dir['path'] . '/' . $filename;

        // Write file
        $written = file_put_contents($filepath, $image_data);
        if ($written === false) {
            return new WP_Error('write_failed', 'Failed to write image file');
        }

        // Create attachment
        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $filepath);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $filepath);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }

    /**
     * Build DALL-E prompt from context
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @param array $context Context.
     * @return string Prompt for image generation.
     */
    private function build_dalle_prompt($settings, $context) {
        // Use custom template if provided
        if (!empty($settings['prompt_template'])) {
            $template = $settings['prompt_template'];
            
            // Replace placeholders
            $replacements = [
                '{title}' => !empty($context['title']) ? $context['title'] : '',
                '{excerpt}' => !empty($context['excerpt']) ? $context['excerpt'] : '',
                '{keywords}' => !empty($context['keywords']) ? implode(', ', $context['keywords']) : '',
            ];
            
            return str_replace(array_keys($replacements), array_values($replacements), $template);
        }

        // Auto-generate prompt from title/content
        $prompt = 'A professional, high-quality image representing: ';
        
        if (!empty($context['title'])) {
            $prompt .= $context['title'];
        } elseif (!empty($context['excerpt'])) {
            $prompt .= wp_trim_words($context['excerpt'], 10);
        } else {
            $prompt .= 'article content';
        }

        // Add style hints
        $prompt .= '. Photorealistic, detailed, good lighting, suitable for blog featured image.';

        return $prompt;
    }

    /**
     * Build Unsplash search query
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @param array $context Context.
     * @return string Search query.
     */
    private function build_unsplash_query($settings, $context) {
        if (!empty($settings['unsplash_query'])) {
            return $settings['unsplash_query'];
        }

        // Extract keywords from title
        if (!empty($context['title'])) {
            // Remove common words
            $title = strtolower($context['title']);
            $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];
            $words = preg_split('/\s+/', $title);
            $keywords = array_diff($words, $stop_words);
            
            return implode(' ', array_slice($keywords, 0, 3));
        }

        return 'abstract background';
    }

    /**
     * Generate filename for image
     *
     * @since 2.0.0
     * @param array $context Context.
     * @param string $prefix Filename prefix.
     * @return string Filename.
     */
    private function generate_filename($context, $prefix = 'featured') {
        $slug = !empty($context['title']) 
            ? sanitize_title($context['title']) 
            : uniqid();
        
        return $prefix . '-' . substr($slug, 0, 50) . '-' . time() . '.jpg';
    }

    /**
     * Get OpenAI API key
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @return string|WP_Error API key or error.
     */
    private function get_openai_key($settings) {
        return $this->get_api_key('openai', $settings);
    }

    /**
     * Get Stability AI API key
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @return string|WP_Error API key or error.
     */
    private function get_stability_key($settings) {
        return $this->get_api_key('stability', $settings);
    }

    /**
     * Get Unsplash API key
     *
     * @since 2.0.0
     * @param array $settings Settings.
     * @return string|WP_Error API key or error.
     */
    private function get_unsplash_key($settings) {
        return $this->get_api_key('unsplash', $settings);
    }

    /**
     * Get API key from database
     *
     * @since 2.0.0
     * @param string $provider Provider name.
     * @param array $settings Settings.
     * @return string|WP_Error API key or error.
     */
    private function get_api_key($provider, $settings) {
        global $wpdb;

        // Check if key ID provided in settings
        if (!empty($settings["{$provider}_api_key_id"])) {
            $key_id = $settings["{$provider}_api_key_id"];
            $key = $wpdb->get_var($wpdb->prepare(
                "SELECT api_key FROM {$wpdb->prefix}abc_api_keys WHERE id = %d AND status = 'active'",
                $key_id
            ));
            
            if ($key) {
                return $key;
            }
        }

        // Get first active key for provider
        $key = $wpdb->get_var($wpdb->prepare(
            "SELECT api_key FROM {$wpdb->prefix}abc_api_keys 
            WHERE provider = %s AND status = 'active' 
            LIMIT 1",
            $provider
        ));

        if (!$key) {
            return new WP_Error(
                'no_api_key',
                "No active {$provider} API key found"
            );
        }

        return $key;
    }

    /**
     * Get supported strategies
     *
     * @since 2.0.0
     * @return array Supported strategies.
     */
    public function get_strategies() {
        return $this->strategies;
    }
}
