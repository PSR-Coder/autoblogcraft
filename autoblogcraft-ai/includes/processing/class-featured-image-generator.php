<?php
/**
 * Featured Image Generator
 *
 * Integrated solution for AI image generation (DALL-E 3, Stable Diffusion),
 * Stock photos (Unsplash), and Source extraction.
 * Includes fallback logic, deduplication, and optimization.
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
     * Maximum image size in bytes (5MB)
     *
     * @var int
     */
    private $max_size = 5242880;

    /**
     * Allowed MIME types
     *
     * @var array
     */
    private $allowed_types = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

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
     * Main entry point with fallback logic.
     *
     * @since 2.0.0
     * @param array $settings Image generation settings.
     * @param array $context Additional context (title, content, post_id, etc.).
     * @return int|WP_Error Attachment ID or error.
     */
    public function generate($settings = [], $context = []) {
        $strategy = !empty($settings['strategy']) ? $settings['strategy'] : 'unsplash';

        if ($strategy === 'none') {
            return null;
        }

        if (!in_array($strategy, $this->strategies, true)) {
            return new WP_Error('invalid_strategy', "Invalid strategy: {$strategy}");
        }

        $this->logger->info("Generating featured image using strategy: {$strategy}", ['context' => $context]);

        // 1. Try Primary Strategy
        $result = $this->execute_strategy($strategy, $settings, $context);

        if (!is_wp_error($result) && $result) {
            return $result;
        }

        // Log failure
        if (is_wp_error($result)) {
            $this->logger->warning("Strategy {$strategy} failed: " . $result->get_error_message());
        }

        // 2. Try Fallback Strategy
        if (!empty($settings['fallback_strategy']) && $settings['fallback_strategy'] !== $strategy) {
            $fallback = $settings['fallback_strategy'];
            $this->logger->info("Trying fallback strategy: {$fallback}");
            
            $result = $this->execute_strategy($fallback, $settings, $context);
            if (!is_wp_error($result) && $result) {
                return $result;
            }
        }

        // 3. Try Fallback URL
        if (!empty($settings['fallback_url'])) {
            $this->logger->info("Using fallback URL: " . $settings['fallback_url']);
            return $this->process_image_from_url($settings['fallback_url'], $context);
        }

        return is_wp_error($result) ? $result : new WP_Error('generation_failed', 'Failed to generate featured image');
    }

    /**
     * Execute specific strategy logic
     *
     * @param string $strategy Strategy name.
     * @param array $settings Settings.
     * @param array $context Context.
     * @return int|WP_Error|null
     */
    private function execute_strategy($strategy, $settings, $context) {
        switch ($strategy) {
            case 'dalle3':
                return $this->strategy_dalle3($settings, $context);
            case 'stable_diffusion':
                return $this->strategy_stable_diffusion($settings, $context);
            case 'unsplash':
                return $this->strategy_unsplash($settings, $context);
            case 'source':
                return $this->strategy_source($settings, $context);
            default:
                return new WP_Error('unknown_strategy', "Unknown strategy: {$strategy}");
        }
    }

    /**
     * Strategy: DALL-E 3
     */
    private function strategy_dalle3($settings, $context) {
        $api_key = $this->get_api_key('openai', $settings);
        if (is_wp_error($api_key)) return $api_key;

        $prompt = $this->build_ai_prompt($settings, $context);
        $this->logger->info("DALL-E 3 prompt: {$prompt}");

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'   => 'dall-e-3',
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => !empty($settings['size']) ? $settings['size'] : '1024x1024',
                'quality' => !empty($settings['quality']) ? $settings['quality'] : 'standard',
                'style'   => !empty($settings['style']) ? $settings['style'] : 'natural',
            ]),
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['error'])) {
            return new WP_Error('dalle3_error', $data['error']['message'] ?? 'Unknown DALL-E error');
        }

        if (empty($data['data'][0]['url'])) {
            return new WP_Error('dalle3_no_image', 'No image URL in response');
        }

        // Add provider to context for meta storage
        $context['ai_provider'] = 'dall-e-3';
        $context['ai_prompt']   = $prompt;

        return $this->process_image_from_url($data['data'][0]['url'], $context);
    }

    /**
     * Strategy: Stable Diffusion
     */
    private function strategy_stable_diffusion($settings, $context) {
        $api_key = $this->get_api_key('stability', $settings);
        if (is_wp_error($api_key)) return $api_key;

        $prompt = $this->build_ai_prompt($settings, $context);
        $this->logger->info("Stable Diffusion prompt: {$prompt}");

        $response = wp_remote_post('https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode([
                'text_prompts' => [['text' => $prompt, 'weight' => 1]],
                'cfg_scale'    => 7,
                'height'       => 1024,
                'width'        => 1024,
                'samples'      => 1,
                'steps'        => 30,
            ]),
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['message'])) {
            return new WP_Error('stable_diffusion_error', $data['message']);
        }

        if (empty($data['artifacts'][0]['base64'])) {
            return new WP_Error('stable_diffusion_no_image', 'No image data in response');
        }

        $context['ai_provider'] = 'stable-diffusion';
        $context['ai_prompt']   = $prompt;

        // Decode and upload binary directly
        return $this->process_image_from_binary(
            base64_decode($data['artifacts'][0]['base64']), 
            'stable-diffusion', 
            $context
        );
    }

    /**
     * Strategy: Unsplash
     */
    private function strategy_unsplash($settings, $context) {
        $api_key = $this->get_api_key('unsplash', $settings);
        if (is_wp_error($api_key)) return $api_key;

        $query = $this->build_unsplash_query($settings, $context);
        $this->logger->info("Unsplash search: {$query}");

        $response = wp_remote_get('https://api.unsplash.com/search/photos?' . http_build_query([
            'query'       => $query,
            'per_page'    => 1,
            'orientation' => !empty($settings['orientation']) ? $settings['orientation'] : 'landscape',
        ]), [
            'headers' => ['Authorization' => 'Client-ID ' . $api_key],
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['results'][0]['urls']['regular'])) {
            return new WP_Error('unsplash_no_results', 'No images found on Unsplash');
        }

        $result = $data['results'][0];
        $image_url = $result['urls']['regular'];

        // Add attribution data to context
        $context['unsplash_photographer'] = $result['user']['name'];
        $context['unsplash_link']         = $result['links']['html'];
        $context['alt_text']              = $query; // Use query as alt text

        return $this->process_image_from_url($image_url, $context);
    }

    /**
     * Strategy: Extract from Source
     */
    private function strategy_source($settings, $context) {
        if (empty($context['content'])) {
            return new WP_Error('no_content', 'No content provided for extraction');
        }

        $image_url = null;

        // 1. Check Metadata (OG Image or Thumbnail)
        if (!empty($context['metadata']['og_image'])) {
            $image_url = $context['metadata']['og_image'];
        } elseif (!empty($context['metadata']['thumbnail'])) {
            $image_url = $context['metadata']['thumbnail'];
        } else {
            // 2. Parse Content regex
            preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $context['content'], $matches);
            if (!empty($matches[1])) {
                $image_url = $matches[1];
            }
        }

        if (!$image_url) {
            return new WP_Error('source_no_image', 'No image found in source content');
        }

        return $this->process_image_from_url($image_url, $context);
    }

    /**
     * Process and Upload Image from URL
     * * Handles deduplication, downloading, validation, and attachment.
     *
     * @param string $url Image URL.
     * @param array $context Context.
     * @return int|WP_Error Attachment ID.
     */
    private function process_image_from_url($url, $context) {
        if (empty($url)) return new WP_Error('empty_url', 'Image URL is empty');

        // 1. Deduplication: Check if we already have this image from this source
        $existing_id = $this->find_existing_image($url);
        if ($existing_id) {
            $this->logger->info("Using existing image: ID={$existing_id}");
            return $existing_id;
        }

        // 2. Prepare for Sideload
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // 3. Validation (Head Request)
        $head = wp_remote_head($url, ['timeout' => 10]);
        if (!is_wp_error($head)) {
            $type = wp_remote_retrieve_header($head, 'content-type');
            $length = wp_remote_retrieve_header($head, 'content-length');

            if ($length > $this->max_size) {
                return new WP_Error('file_too_large', 'Image file is too large');
            }
            // Note: Some servers don't send content-length, so we proceed if empty
        }

        // 4. Download to Temp
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // 5. Verify Actual File (Double check after download)
        $filesize = filesize($tmp);
        $filetype = wp_check_filetype($tmp, null);
        
        // Fix for download_url sometimes returning tmp without extension, mime check needed
        if (!$filetype['type']) {
             $mime = mime_content_type($tmp);
             if (!in_array($mime, $this->allowed_types)) {
                 @unlink($tmp);
                 return new WP_Error('invalid_mime', "Invalid mime type: {$mime}");
             }
        }

        if ($filesize > $this->max_size) {
            @unlink($tmp);
            return new WP_Error('file_too_large', 'Downloaded image exceeds size limit');
        }

        // 6. Generate Contextual Filename
        $filename = $this->generate_filename($context);
        
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];

        // 7. Sideload
        $post_id = !empty($context['post_id']) ? $context['post_id'] : 0;
        $desc    = !empty($context['alt_text']) ? $context['alt_text'] : '';
        
        $attachment_id = media_handle_sideload($file_array, $post_id, $desc);

        // Clean up
        if (file_exists($tmp)) @unlink($tmp);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // 8. Update Metadata
        $this->update_attachment_meta($attachment_id, $url, $context);

        return $attachment_id;
    }

    /**
     * Process and Upload Binary Image Data (Base64 decoded)
     *
     * @param string $data Binary data.
     * @param string $prefix Filename prefix.
     * @param array $context Context.
     * @return int|WP_Error
     */
    private function process_image_from_binary($data, $prefix, $context) {
        $upload_dir = wp_upload_dir();
        $filename   = $this->generate_filename($context, $prefix);
        $filepath   = $upload_dir['path'] . '/' . $filename;

        // Write file
        if (file_put_contents($filepath, $data) === false) {
            return new WP_Error('write_failed', 'Failed to write image file');
        }

        // Check filetype
        $filetype = wp_check_filetype($filename, null);
        
        // Create attachment
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $post_id = !empty($context['post_id']) ? $context['post_id'] : 0;
        $attachment_id = wp_insert_attachment($attachment, $filepath, $post_id);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate Metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $filepath);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        // Update Custom Meta
        $this->update_attachment_meta($attachment_id, 'generated-binary', $context);

        return $attachment_id;
    }

    /**
     * Update Attachment Metadata Helper
     */
    private function update_attachment_meta($attachment_id, $source_url, $context) {
        // Core Source URL for deduplication
        update_post_meta($attachment_id, '_abc_source_url', $source_url);

        // Unsplash Specific
        if (!empty($context['unsplash_photographer'])) {
            update_post_meta($attachment_id, '_unsplash_photographer', $context['unsplash_photographer']);
            update_post_meta($attachment_id, '_unsplash_url', $context['unsplash_link']);
        }

        // AI Specific
        if (!empty($context['ai_provider'])) {
            update_post_meta($attachment_id, '_abc_ai_provider', $context['ai_provider']);
        }
        if (!empty($context['ai_prompt'])) {
            update_post_meta($attachment_id, '_abc_ai_prompt', $context['ai_prompt']);
            // Also store as alt text if not set
            if (!get_post_meta($attachment_id, '_wp_attachment_image_alt', true)) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $context['ai_prompt']);
            }
        }
    }

    /**
     * Check if image already exists in library by Source URL
     */
    private function find_existing_image($url) {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_abc_source_url' AND meta_value = %s LIMIT 1",
            $url
        ));
        return $id ? (int) $id : false;
    }

    /**
     * Build AI Prompt
     */
    private function build_ai_prompt($settings, $context) {
        // Template replacement
        if (!empty($settings['prompt_template'])) {
            $replacements = [
                '{title}'    => $context['title'] ?? '',
                '{excerpt}'  => $context['excerpt'] ?? '',
                '{keywords}' => !empty($context['keywords']) ? implode(', ', $context['keywords']) : '',
            ];
            return str_replace(array_keys($replacements), array_values($replacements), $settings['prompt_template']);
        }

        // Auto-generation
        $prompt = 'A professional, high-quality image representing: ';
        $prompt .= !empty($context['title']) ? $context['title'] : ($context['excerpt'] ?? 'article content');
        $prompt .= '. Photorealistic, detailed, good lighting, suitable for blog featured image.';

        return $prompt;
    }

    /**
     * Build Unsplash Query
     */
    private function build_unsplash_query($settings, $context) {
        if (!empty($settings['unsplash_query'])) {
            return $settings['unsplash_query'];
        }

        if (!empty($context['title'])) {
            // Simple keyword extraction (remove stop words)
            $title = strtolower($context['title']);
            $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'with', 'by'];
            $words = array_diff(preg_split('/\s+/', $title), $stop_words);
            return implode(' ', array_slice($words, 0, 3));
        }

        return 'abstract background';
    }

    /**
     * Generate Filename
     */
    private function generate_filename($context, $prefix = 'featured') {
        $slug = !empty($context['title']) ? sanitize_title($context['title']) : uniqid();
        return $prefix . '-' . substr($slug, 0, 50) . '-' . time() . '.jpg';
    }

    /**
     * Get API Key
     */
    private function get_api_key($provider, $settings) {
        global $wpdb;

        // 1. Check specific ID in settings
        if (!empty($settings["{$provider}_api_key_id"])) {
            $key = $wpdb->get_var($wpdb->prepare(
                "SELECT api_key FROM {$wpdb->prefix}abc_api_keys WHERE id = %d AND status = 'active'",
                $settings["{$provider}_api_key_id"]
            ));
            if ($key) return $key;
        }

        // 2. Check general active key
        $key = $wpdb->get_var($wpdb->prepare(
            "SELECT api_key FROM {$wpdb->prefix}abc_api_keys WHERE provider = %s AND status = 'active' LIMIT 1",
            $provider
        ));

        // 3. Fallback to WP Options (Legacy support from File 2)
        if (!$key) {
            $key = get_option('abc_' . $provider . '_api_key');
        }

        if (!$key) {
            return new WP_Error('no_api_key', "No active {$provider} API key found");
        }

        return $key;
    }
}