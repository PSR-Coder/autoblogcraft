<?php
/**
 * Translation Cache
 *
 * Manages caching of translations to reduce API calls and improve performance.
 *
 * @package AutoBlogCraft\Modules\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

use AutoBlogCraft\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translation Cache class
 *
 * Handles caching and retrieval of translations.
 *
 * @since 2.0.0
 */
class Translation_Cache {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Cache option name
     *
     * @var string
     */
    private $option_name = 'abc_translation_cache';

    /**
     * Cache expiry (30 days)
     *
     * @var int
     */
    private $cache_expiry = 2592000;

    /**
     * Cache hit count
     *
     * @var int
     */
    private $hit_count = 0;

    /**
     * Cache miss count
     *
     * @var int
     */
    private $miss_count = 0;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Get cached translation
     *
     * @since 2.0.0
     * @param string $text Original text.
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     * @return string|null Cached translation or null if not found.
     */
    public function get($text, $source_lang, $target_lang) {
        $key = $this->generate_cache_key($text, $source_lang, $target_lang);
        $cache = $this->get_cache();

        if (isset($cache[$key])) {
            $entry = $cache[$key];

            // Check if expired
            if (isset($entry['expires']) && $entry['expires'] < time()) {
                $this->miss_count++;
                return null;
            }

            $this->hit_count++;
            return $entry['translation'] ?? null;
        }

        $this->miss_count++;
        return null;
    }

    /**
     * Set cached translation
     *
     * @since 2.0.0
     * @param string $text Original text.
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     * @param string $translation Translated text.
     * @return bool Success.
     */
    public function set($text, $source_lang, $target_lang, $translation) {
        $key = $this->generate_cache_key($text, $source_lang, $target_lang);
        $cache = $this->get_cache();

        $cache[$key] = [
            'translation' => $translation,
            'created' => time(),
            'expires' => time() + $this->cache_expiry,
        ];

        return $this->save_cache($cache);
    }

    /**
     * Generate cache key
     *
     * @since 2.0.0
     * @param string $text Text.
     * @param string $source_lang Source language.
     * @param string $target_lang Target language.
     * @return string Cache key.
     */
    private function generate_cache_key($text, $source_lang, $target_lang) {
        // Use hash for consistent key length
        return md5($source_lang . ':' . $target_lang . ':' . $text);
    }

    /**
     * Get cache
     *
     * @since 2.0.0
     * @return array Cache data.
     */
    private function get_cache() {
        $cache = get_option($this->option_name, []);
        return is_array($cache) ? $cache : [];
    }

    /**
     * Save cache
     *
     * @since 2.0.0
     * @param array $cache Cache data.
     * @return bool Success.
     */
    private function save_cache($cache) {
        return update_option($this->option_name, $cache, false);
    }

    /**
     * Cleanup expired cache entries
     *
     * @since 2.0.0
     * @return int Number of entries removed.
     */
    public function cleanup_expired() {
        $cache = $this->get_cache();
        $current_time = time();
        $removed = 0;

        foreach ($cache as $key => $entry) {
            if (isset($entry['expires']) && $entry['expires'] < $current_time) {
                unset($cache[$key]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->save_cache($cache);
            $this->logger->info("Cleaned up {$removed} expired translation cache entries");
        }

        return $removed;
    }

    /**
     * Clear all cache
     *
     * @since 2.0.0
     * @return bool Success.
     */
    public function clear_all() {
        $this->logger->info('Clearing all translation cache');
        return delete_option($this->option_name);
    }

    /**
     * Get cache size
     *
     * @since 2.0.0
     * @return int Number of cached entries.
     */
    public function get_size() {
        $cache = $this->get_cache();
        return count($cache);
    }

    /**
     * Get cache hit count
     *
     * @since 2.0.0
     * @return int Hit count.
     */
    public function get_hit_count() {
        return $this->hit_count;
    }

    /**
     * Get cache miss count
     *
     * @since 2.0.0
     * @return int Miss count.
     */
    public function get_miss_count() {
        return $this->miss_count;
    }

    /**
     * Get cache statistics
     *
     * @since 2.0.0
     * @return array Statistics.
     */
    public function get_stats() {
        $cache = $this->get_cache();
        $total_size = 0;
        $expired_count = 0;
        $current_time = time();

        foreach ($cache as $entry) {
            $total_size += strlen(serialize($entry));
            
            if (isset($entry['expires']) && $entry['expires'] < $current_time) {
                $expired_count++;
            }
        }

        return [
            'total_entries' => count($cache),
            'expired_entries' => $expired_count,
            'size_bytes' => $total_size,
            'size_kb' => round($total_size / 1024, 2),
            'hit_count' => $this->hit_count,
            'miss_count' => $this->miss_count,
            'hit_rate' => $this->hit_count + $this->miss_count > 0 
                ? round($this->hit_count / ($this->hit_count + $this->miss_count) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Prune cache to size limit
     *
     * Removes oldest entries if cache exceeds size limit.
     *
     * @since 2.0.0
     * @param int $max_entries Maximum number of entries.
     * @return int Number of entries removed.
     */
    public function prune($max_entries = 10000) {
        $cache = $this->get_cache();
        
        if (count($cache) <= $max_entries) {
            return 0;
        }

        // Sort by creation time
        uasort($cache, function ($a, $b) {
            return ($a['created'] ?? 0) - ($b['created'] ?? 0);
        });

        // Remove oldest entries
        $to_remove = count($cache) - $max_entries;
        $cache = array_slice($cache, $to_remove, null, true);

        $this->save_cache($cache);
        $this->logger->info("Pruned {$to_remove} old translation cache entries");

        return $to_remove;
    }

    /**
     * Warm cache with common translations
     *
     * @since 2.0.0
     * @param array $translations Array of [text, source_lang, target_lang, translation].
     * @return int Number of entries added.
     */
    public function warm($translations) {
        $added = 0;

        foreach ($translations as $item) {
            if (!isset($item['text'], $item['source_lang'], $item['target_lang'], $item['translation'])) {
                continue;
            }

            $this->set(
                $item['text'],
                $item['source_lang'],
                $item['target_lang'],
                $item['translation']
            );

            $added++;
        }

        $this->logger->info("Warmed translation cache with {$added} entries");
        return $added;
    }

    /**
     * Export cache
     *
     * @since 2.0.0
     * @return array Cache data.
     */
    public function export() {
        return $this->get_cache();
    }

    /**
     * Import cache
     *
     * @since 2.0.0
     * @param array $data Cache data.
     * @param bool $merge Merge with existing cache.
     * @return bool Success.
     */
    public function import($data, $merge = true) {
        if (!is_array($data)) {
            return false;
        }

        if ($merge) {
            $existing = $this->get_cache();
            $data = array_merge($existing, $data);
        }

        $this->logger->info('Imported ' . count($data) . ' translation cache entries');
        return $this->save_cache($data);
    }
}
