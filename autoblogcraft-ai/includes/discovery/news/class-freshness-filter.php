<?php
/**
 * Freshness Filter
 *
 * Filters news articles by publication date/age.
 * Supports configurable time windows (1h, 6h, 24h, 7d).
 *
 * @package AutoBlogCraft\Discovery\News
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\News;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Freshness Filter class
 *
 * Responsibilities:
 * - Filter articles by age
 * - Support multiple time windows
 * - Calculate article freshness scores
 * - Prioritize recent content
 *
 * @since 2.0.0
 */
class Freshness_Filter {

    /**
     * Freshness window
     *
     * @var string
     */
    private $window;

    /**
     * Maximum age in seconds
     *
     * @var int
     */
    private $max_age_seconds;

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param string $window Freshness window ('1h', '6h', '24h', '7d', etc.).
     */
    public function __construct($window = '24h') {
        $this->window = $window;
        $this->max_age_seconds = $this->parse_window($window);
    }

    /**
     * Parse freshness window to seconds
     *
     * @since 2.0.0
     * @param string $window Window string.
     * @return int Seconds.
     */
    private function parse_window($window) {
        // Extract number and unit
        preg_match('/^(\d+)([hdw])$/', strtolower($window), $matches);

        if (count($matches) !== 3) {
            return 86400; // Default to 24 hours
        }

        $value = intval($matches[1]);
        $unit = $matches[2];

        $multipliers = [
            'h' => 3600,      // hours
            'd' => 86400,     // days
            'w' => 604800,    // weeks
        ];

        return $value * ($multipliers[$unit] ?? 86400);
    }

    /**
     * Filter articles by freshness
     *
     * @since 2.0.0
     * @param array $articles Articles to filter.
     * @return array Filtered articles.
     */
    public function filter($articles) {
        $now = time();
        $cutoff_time = $now - $this->max_age_seconds;

        $filtered = array_filter($articles, function($article) use ($cutoff_time) {
            $published_at = $article['published_at'] ?? time();
            return $published_at >= $cutoff_time;
        });

        return array_values($filtered);
    }

    /**
     * Calculate freshness score
     *
     * @since 2.0.0
     * @param int $published_timestamp Publication timestamp.
     * @return float Score between 0 and 100.
     */
    public function calculate_score($published_timestamp) {
        $age_seconds = time() - $published_timestamp;

        if ($age_seconds < 0) {
            $age_seconds = 0; // Future date, consider as now
        }

        // Score decreases linearly with age
        $score = 100 * (1 - ($age_seconds / $this->max_age_seconds));

        return max(0, min(100, $score));
    }

    /**
     * Get freshness label
     *
     * @since 2.0.0
     * @param int $published_timestamp Publication timestamp.
     * @return string Human-readable label.
     */
    public function get_freshness_label($published_timestamp) {
        $age_seconds = time() - $published_timestamp;

        if ($age_seconds < 3600) {
            $minutes = round($age_seconds / 60);
            return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'autoblogcraft'), $minutes);
        }

        if ($age_seconds < 86400) {
            $hours = round($age_seconds / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'autoblogcraft'), $hours);
        }

        $days = round($age_seconds / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'autoblogcraft'), $days);
    }

    /**
     * Sort articles by freshness
     *
     * @since 2.0.0
     * @param array $articles Articles to sort.
     * @param bool  $ascending Sort order.
     * @return array Sorted articles.
     */
    public function sort($articles, $ascending = false) {
        usort($articles, function($a, $b) use ($ascending) {
            $time_a = $a['published_at'] ?? 0;
            $time_b = $b['published_at'] ?? 0;

            if ($ascending) {
                return $time_a - $time_b;
            }

            return $time_b - $time_a;
        });

        return $articles;
    }

    /**
     * Group articles by freshness category
     *
     * @since 2.0.0
     * @param array $articles Articles.
     * @return array Grouped articles.
     */
    public function group_by_category($articles) {
        $grouped = [
            'breaking' => [],    // < 1 hour
            'recent' => [],      // 1-6 hours
            'today' => [],       // 6-24 hours
            'yesterday' => [],   // 24-48 hours
            'older' => [],       // > 48 hours
        ];

        $now = time();

        foreach ($articles as $article) {
            $age_seconds = $now - ($article['published_at'] ?? $now);

            if ($age_seconds < 3600) {
                $grouped['breaking'][] = $article;
            } elseif ($age_seconds < 21600) {
                $grouped['recent'][] = $article;
            } elseif ($age_seconds < 86400) {
                $grouped['today'][] = $article;
            } elseif ($age_seconds < 172800) {
                $grouped['yesterday'][] = $article;
            } else {
                $grouped['older'][] = $article;
            }
        }

        return $grouped;
    }

    /**
     * Get recommended time window based on keyword
     *
     * @since 2.0.0
     * @param string $keyword Keyword.
     * @return string Recommended window.
     */
    public static function get_recommended_window($keyword) {
        $breaking_keywords = ['breaking', 'urgent', 'alert', 'live', 'now'];
        $recent_keywords = ['today', 'latest', 'new', 'update'];

        $keyword_lower = strtolower($keyword);

        foreach ($breaking_keywords as $bk) {
            if (strpos($keyword_lower, $bk) !== false) {
                return '1h';
            }
        }

        foreach ($recent_keywords as $rk) {
            if (strpos($keyword_lower, $rk) !== false) {
                return '6h';
            }
        }

        return '24h'; // Default
    }

    /**
     * Check if article is breaking news
     *
     * @since 2.0.0
     * @param int $published_timestamp Publication timestamp.
     * @return bool
     */
    public function is_breaking($published_timestamp) {
        return (time() - $published_timestamp) < 3600; // Less than 1 hour
    }
}
