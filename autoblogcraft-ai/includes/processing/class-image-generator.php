<?php
/**
 * Image Generator
 *
 * Generates and manages featured images for posts.
 * Supports downloading from URLs and AI-generated images.
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
 * Image Generator class
 *
 * Responsibilities:
 * - Download images from URLs
 * - Generate AI images
 * - Process and optimize images
 * - Attach to WordPress media library
 *
 * @since 2.0.0
 */
class Image_Generator {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

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
     * Download image from URL
     *
     * @since 2.0.0
     * @param string $url Image URL.
     * @param string $title Image title/alt text.
     * @param int|null $post_id Optional post ID to attach to.
     * @return int|WP_Error Attachment ID or error.
     */
    public function download_image($url, $title = '', $post_id = null) {
        if (empty($url)) {
            return new WP_Error('empty_url', 'Image URL is empty');
        }

        $this->logger->debug("Downloading image: {$url}");

        // Check if already downloaded
        $existing = $this->find_existing_image($url);
        if ($existing) {
            $this->logger->debug("Image already exists: ID={$existing}");
            return $existing;
        }

        // Download image
        $temp_file = $this->download_to_temp($url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Upload to media library
        $attachment_id = $this->upload_to_media_library($temp_file, $url, $title, $post_id);

        // Clean up temp file
        @unlink($temp_file);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Store source URL
        update_post_meta($attachment_id, '_abc_source_url', $url);

        $this->logger->info("Image downloaded: ID={$attachment_id}");

        return $attachment_id;
    }

    /**
     * Generate image from text using AI
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param string $prompt Image generation prompt.
     * @param int|null $post_id Optional post ID to attach to.
     * @return int|WP_Error Attachment ID or error.
     */
    public function generate_from_text($campaign, $prompt, $post_id = null) {
        // Get AI image settings
        $provider = $campaign->get_meta('ai_image_provider', 'openai');
        $api_key = $campaign->get_meta('ai_image_api_key', '');

        // Fallback to global settings
        if (empty($api_key)) {
            $api_key = get_option('abc_' . $provider . '_api_key', '');
        }

        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'AI image API key not configured');
        }

        $this->logger->debug("Generating AI image: Provider={$provider}");

        switch ($provider) {
            case 'openai':
                return $this->generate_openai_image($prompt, $api_key, $post_id);
            
            case 'stability':
                return $this->generate_stability_image($prompt, $api_key, $post_id);
            
            default:
                return new WP_Error('unsupported_provider', "Unsupported image provider: {$provider}");
        }
    }

    /**
     * Generate image using OpenAI DALL-E
     *
     * @since 2.0.0
     * @param string $prompt Generation prompt.
     * @param string $api_key API key.
     * @param int|null $post_id Post ID.
     * @return int|WP_Error Attachment ID or error.
     */
    private function generate_openai_image($prompt, $api_key, $post_id) {
        $url = 'https://api.openai.com/v1/images/generations';

        $body = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard',
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['data'][0]['url'])) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            return new WP_Error('openai_error', $error_msg);
        }

        $image_url = $data['data'][0]['url'];

        // Download generated image
        return $this->download_image($image_url, $prompt, $post_id);
    }

    /**
     * Generate image using Stability AI
     *
     * @since 2.0.0
     * @param string $prompt Generation prompt.
     * @param string $api_key API key.
     * @param int|null $post_id Post ID.
     * @return int|WP_Error Attachment ID or error.
     */
    private function generate_stability_image($prompt, $api_key, $post_id) {
        $url = 'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image';

        $body = [
            'text_prompts' => [
                ['text' => $prompt, 'weight' => 1],
            ],
            'cfg_scale' => 7,
            'height' => 1024,
            'width' => 1024,
            'samples' => 1,
            'steps' => 30,
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['artifacts'][0]['base64'])) {
            $error_msg = isset($data['message']) ? $data['message'] : 'Unknown error';
            return new WP_Error('stability_error', $error_msg);
        }

        // Decode base64 image
        $image_data = base64_decode($data['artifacts'][0]['base64']);

        // Save to temp file
        $temp_file = wp_tempnam('stability-');
        file_put_contents($temp_file, $image_data);

        // Upload to media library
        $attachment_id = $this->upload_to_media_library($temp_file, 'ai-generated', $prompt, $post_id);

        // Clean up
        @unlink($temp_file);

        return $attachment_id;
    }

    /**
     * Download image to temporary file
     *
     * @since 2.0.0
     * @param string $url Image URL.
     * @return string|WP_Error Temp file path or error.
     */
    private function download_to_temp($url) {
        // Download image
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'stream' => false,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Check content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (!in_array($content_type, $this->allowed_types)) {
            return new WP_Error('invalid_type', "Invalid image type: {$content_type}");
        }

        // Check file size
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        if ($content_length > $this->max_size) {
            return new WP_Error('file_too_large', 'Image file is too large');
        }

        // Get body
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('empty_file', 'Downloaded file is empty');
        }

        // Save to temp file
        $temp_file = wp_tempnam(basename($url));
        file_put_contents($temp_file, $body);

        return $temp_file;
    }

    /**
     * Upload file to WordPress media library
     *
     * @since 2.0.0
     * @param string $file_path File path.
     * @param string $source_url Source URL.
     * @param string $title Image title.
     * @param int|null $post_id Post ID to attach to.
     * @return int|WP_Error Attachment ID or error.
     */
    private function upload_to_media_library($file_path, $source_url, $title, $post_id) {
        // Get filename from URL
        $filename = basename(parse_url($source_url, PHP_URL_PATH));
        
        // Ensure valid filename
        if (empty($filename) || !preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            $filename = 'image-' . md5($source_url) . '.jpg';
        }

        // Prepare file array
        $file = [
            'name' => $filename,
            'tmp_name' => $file_path,
        ];

        // Use WordPress media upload
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Upload
        $attachment_id = media_handle_sideload($file, $post_id, $title);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Update alt text
        if (!empty($title)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($title));
        }

        return $attachment_id;
    }

    /**
     * Find existing image by source URL
     *
     * @since 2.0.0
     * @param string $url Source URL.
     * @return int|false Attachment ID or false.
     */
    private function find_existing_image($url) {
        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_abc_source_url' 
            AND meta_value = %s 
            LIMIT 1",
            $url
        ));

        return $attachment_id ? (int) $attachment_id : false;
    }

    /**
     * Set maximum image size
     *
     * @since 2.0.0
     * @param int $size Size in bytes.
     */
    public function set_max_size($size) {
        $this->max_size = absint($size);
    }
}
