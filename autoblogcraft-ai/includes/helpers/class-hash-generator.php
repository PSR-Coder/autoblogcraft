<?php
/**
 * Hash Generator
 *
 * Provides consistent hash generation for content deduplication,
 * unique identifiers, and cache keys.
 *
 * @package AutoBlogCraft\Helpers
 * @since 2.0.0
 */

namespace AutoBlogCraft\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hash Generator class
 *
 * Responsibilities:
 * - Generate content hashes for deduplication
 * - Create unique identifiers
 * - Generate cache keys
 * - Create secure tokens
 *
 * @since 2.0.0
 */
class Hash_Generator {

    /**
     * Generate content hash
     *
     * Used for duplicate detection. Normalizes content before hashing.
     *
     * @since 2.0.0
     * @param string $content Content to hash.
     * @return string SHA256 hash.
     */
    public static function generate_content_hash($content) {
        // Normalize content
        $normalized = self::normalize_content($content);
        
        // Generate SHA256 hash
        return hash('sha256', $normalized);
    }

    /**
     * Normalize content for hashing
     *
     * @since 2.0.0
     * @param string $content Content to normalize.
     * @return string Normalized content.
     */
    private static function normalize_content($content) {
        // Convert to lowercase
        $content = strtolower($content);
        
        // Remove HTML tags
        $content = wp_strip_all_tags($content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Trim
        $content = trim($content);
        
        return $content;
    }

    /**
     * Generate unique ID
     *
     * Creates a unique identifier for queue items, cache entries, etc.
     *
     * @since 2.0.0
     * @param string $prefix Optional prefix.
     * @return string Unique ID.
     */
    public static function generate_unique_id($prefix = '') {
        $unique_id = uniqid($prefix, true);
        
        // Add random component for extra uniqueness
        $random = bin2hex(random_bytes(8));
        
        return $prefix . md5($unique_id . $random);
    }

    /**
     * Generate cache key
     *
     * @since 2.0.0
     * @param string $base_key Base key.
     * @param array  $params   Parameters to include in key.
     * @return string Cache key.
     */
    public static function generate_cache_key($base_key, $params = []) {
        $key_parts = [$base_key];
        
        // Sort params for consistent keys
        ksort($params);
        
        foreach ($params as $param_key => $param_value) {
            if (is_array($param_value)) {
                $param_value = serialize($param_value);
            }
            $key_parts[] = $param_key . ':' . $param_value;
        }
        
        $combined = implode('|', $key_parts);
        
        return 'abc_' . md5($combined);
    }

    /**
     * Generate secure token
     *
     * @since 2.0.0
     * @param int $length Token length (default 32).
     * @return string Secure token.
     */
    public static function generate_secure_token($length = 32) {
        $bytes = random_bytes($length);
        return bin2hex($bytes);
    }

    /**
     * Generate API key hash
     *
     * @since 2.0.0
     * @param string $api_key API key to hash.
     * @return string Hashed API key.
     */
    public static function hash_api_key($api_key) {
        return hash('sha256', $api_key);
    }

    /**
     * Generate URL hash
     *
     * Used for duplicate URL detection.
     *
     * @since 2.0.0
     * @param string $url URL to hash.
     * @return string MD5 hash of normalized URL.
     */
    public static function generate_url_hash($url) {
        // Parse URL
        $parsed = parse_url($url);
        
        if (!$parsed) {
            return md5($url);
        }
        
        // Normalize URL components
        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'http';
        $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        
        // Remove www prefix
        $host = preg_replace('/^www\./', '', $host);
        
        // Remove trailing slash from path
        $path = rtrim($path, '/');
        
        // Rebuild normalized URL
        $normalized = $scheme . '://' . $host . $path;
        
        // Add query if present (sorted)
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query_params);
            ksort($query_params);
            $normalized .= '?' . http_build_query($query_params);
        }
        
        return md5($normalized);
    }

    /**
     * Generate translation cache key
     *
     * @since 2.0.0
     * @param string $content       Content to translate.
     * @param string $source_lang   Source language.
     * @param string $target_lang   Target language.
     * @param string $provider      Translation provider.
     * @return string Cache key.
     */
    public static function generate_translation_key($content, $source_lang, $target_lang, $provider) {
        $content_hash = self::generate_content_hash($content);
        
        return sprintf(
            'abc_trans_%s_%s_%s_%s',
            $provider,
            $source_lang,
            $target_lang,
            substr($content_hash, 0, 16)
        );
    }

    /**
     * Generate fingerprint
     *
     * Creates a fingerprint from multiple data points.
     *
     * @since 2.0.0
     * @param array $data Data points.
     * @return string Fingerprint hash.
     */
    public static function generate_fingerprint($data) {
        // Sort data for consistency
        ksort($data);
        
        // Serialize and hash
        $serialized = serialize($data);
        return hash('sha256', $serialized);
    }

    /**
     * Generate short hash
     *
     * Creates a short hash suitable for URLs or identifiers.
     *
     * @since 2.0.0
     * @param string $input Input string.
     * @param int    $length Desired length (default 8).
     * @return string Short hash.
     */
    public static function generate_short_hash($input, $length = 8) {
        $hash = md5($input);
        return substr($hash, 0, $length);
    }

    /**
     * Verify content hash
     *
     * @since 2.0.0
     * @param string $content Content to verify.
     * @param string $hash    Expected hash.
     * @return bool True if hash matches.
     */
    public static function verify_content_hash($content, $hash) {
        $generated_hash = self::generate_content_hash($content);
        return hash_equals($generated_hash, $hash);
    }

    /**
     * Generate campaign signature
     *
     * Creates a unique signature for a campaign configuration.
     *
     * @since 2.0.0
     * @param int   $campaign_id Campaign ID.
     * @param array $config      Campaign configuration.
     * @return string Signature hash.
     */
    public static function generate_campaign_signature($campaign_id, $config) {
        $data = [
            'campaign_id' => $campaign_id,
            'config' => $config,
            'timestamp' => time(),
        ];
        
        return self::generate_fingerprint($data);
    }

    /**
     * Generate queue item hash
     *
     * Creates unique hash for queue items to prevent duplicates.
     *
     * @since 2.0.0
     * @param int    $campaign_id Campaign ID.
     * @param string $source_url  Source URL.
     * @param string $content     Content.
     * @return string Hash.
     */
    public static function generate_queue_hash($campaign_id, $source_url, $content) {
        $data = [
            'campaign_id' => $campaign_id,
            'source_url' => $source_url,
            'content_hash' => self::generate_content_hash($content),
        ];
        
        return self::generate_fingerprint($data);
    }
}
