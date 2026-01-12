<?php
/**
 * Amazon Bestseller Discoverer
 *
 * Discovers bestselling products from Amazon bestseller lists.
 * Supports multiple categories and real-time bestseller rankings.
 *
 * @package AutoBlogCraft\Discovery\Amazon
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\Amazon;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Discovery\Base_Discoverer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon Bestseller Discoverer class
 *
 * Responsibilities:
 * - Discover products from bestseller lists
 * - Parse bestseller rankings
 * - Extract trending products
 * - Monitor rank changes over time
 * - Queue high-ranking products
 *
 * @since 2.0.0
 */
class Bestseller_Discoverer extends Base_Discoverer {

    /**
     * Campaign ID
     *
     * @var int
     */
    private $campaign_id;

    /**
     * Bestseller base URL
     *
     * @var string
     */
    private $bestseller_base_url = 'https://www.amazon.com/Best-Sellers';

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    public function __construct($campaign_id) {
        $this->campaign_id = $campaign_id;
    }

    /**
     * Discover bestsellers by category
     *
     * @since 2.0.0
     * @param string $category Category node ID or URL.
     * @param array  $options  Discovery options.
     * @return array Results.
     */
    public function discover_bestsellers($category = '', $options = []) {
        Logger::log($this->campaign_id, 'info', 'discovery', "Discovering Amazon bestsellers: {$category}");

        $defaults = [
            'max_rank' => 50,        // Only get top 50
            'min_rating' => 3.5,     // Minimum 3.5 stars
            'track_changes' => true, // Track rank changes
        ];

        $options = array_merge($defaults, $options);

        // Build bestseller URL
        $url = $this->build_bestseller_url($category);

        // Fetch bestseller page
        $products = $this->fetch_bestseller_page($url, $options);

        // Track rank changes if enabled
        if ($options['track_changes']) {
            $this->track_rank_changes($products);
        }

        return $this->queue_products($products);
    }

    /**
     * Build bestseller URL
     *
     * @since 2.0.0
     * @param string $category Category identifier.
     * @return string URL.
     */
    private function build_bestseller_url($category) {
        if (empty($category)) {
            return $this->bestseller_base_url;
        }

        // If full URL provided
        if (strpos($category, 'http') === 0) {
            return $category;
        }

        // If category node ID
        return $this->bestseller_base_url . '/' . $category;
    }

    /**
     * Fetch and parse bestseller page
     *
     * @since 2.0.0
     * @param string $url     Bestseller URL.
     * @param array  $options Options.
     * @return array Products.
     */
    private function fetch_bestseller_page($url, $options) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers' => [
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'Failed to fetch bestseller page', [
                'url' => $url,
                'error' => $response->get_error_message(),
            ]);
            return [];
        }

        $html = wp_remote_retrieve_body($response);
        return $this->parse_bestseller_page($html, $options);
    }

    /**
     * Parse bestseller page HTML
     *
     * @since 2.0.0
     * @param string $html    HTML content.
     * @param array  $options Options.
     * @return array Products.
     */
    private function parse_bestseller_page($html, $options) {
        $products = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Find bestseller items
        $product_nodes = $xpath->query("//div[@id='gridItemRoot'] | //div[contains(@class, 'zg-item-immersion')]");

        $rank = 1;
        foreach ($product_nodes as $node) {
            if ($rank > $options['max_rank']) {
                break;
            }

            $product = $this->extract_bestseller_data($xpath, $node, $rank);

            if (empty($product['asin'])) {
                continue;
            }

            // Apply rating filter
            if ($product['rating'] < $options['min_rating']) {
                $rank++;
                continue;
            }

            $products[] = $product;
            $rank++;
        }

        Logger::log($this->campaign_id, 'debug', 'discovery', "Found bestseller products", [
            'count' => count($products),
        ]);

        return $products;
    }

    /**
     * Extract bestseller product data
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath.
     * @param \DOMNode  $node  Product node.
     * @param int       $rank  Bestseller rank.
     * @return array Product data.
     */
    private function extract_bestseller_data($xpath, $node, $rank) {
        // Extract ASIN
        $asin = $this->extract_asin($xpath, $node);

        // Extract title
        $title_node = $xpath->query(".//div[@class='p13n-sc-truncate']", $node)->item(0);
        if (!$title_node) {
            $title_node = $xpath->query(".//a//span//div", $node)->item(0);
        }
        $title = $title_node ? trim($title_node->textContent) : '';

        // Extract price
        $price = $this->extract_price($xpath, $node);

        // Extract rating
        $rating_data = $this->extract_rating($xpath, $node);

        // Extract image
        $image_node = $xpath->query(".//img", $node)->item(0);
        $image_url = $image_node ? $image_node->getAttribute('src') : '';

        // Build product URL
        $product_url = !empty($asin) ? 'https://www.amazon.com/dp/' . $asin : '';

        return [
            'asin' => $asin,
            'title' => $title,
            'url' => $product_url,
            'price' => $price,
            'rating' => $rating_data['rating'],
            'review_count' => $rating_data['review_count'],
            'image_url' => $image_url,
            'bestseller_rank' => $rank,
            'discovered_at' => current_time('mysql'),
        ];
    }

    /**
     * Extract ASIN from bestseller node
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath.
     * @param \DOMNode  $node  Node.
     * @return string ASIN.
     */
    private function extract_asin($xpath, $node) {
        // Try data-asin attribute
        $asin = $node->getAttribute('data-asin');
        if (!empty($asin)) {
            return $asin;
        }

        // Try to extract from URL
        $link_node = $xpath->query(".//a[contains(@href, '/dp/')]", $node)->item(0);
        if ($link_node) {
            $href = $link_node->getAttribute('href');
            if (preg_match('/\/dp\/([A-Z0-9]{10})/', $href, $matches)) {
                return $matches[1];
            }
        }

        return '';
    }

    /**
     * Extract price
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath.
     * @param \DOMNode  $node  Node.
     * @return float Price.
     */
    private function extract_price($xpath, $node) {
        $price_node = $xpath->query(".//span[@class='p13n-sc-price'] | .//span[contains(@class, 'a-price-whole')]", $node)->item(0);
        
        if (!$price_node) {
            return 0.0;
        }

        $price_text = $price_node->textContent;
        $price_text = preg_replace('/[^0-9.]/', '', $price_text);
        
        return floatval($price_text);
    }

    /**
     * Extract rating and reviews
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath.
     * @param \DOMNode  $node  Node.
     * @return array Rating data.
     */
    private function extract_rating($xpath, $node) {
        $rating = 0.0;
        $review_count = 0;

        // Extract rating
        $rating_node = $xpath->query(".//span[@class='a-icon-alt'] | .//i[contains(@class, 'a-star')]", $node)->item(0);
        if ($rating_node) {
            $rating_text = $rating_node->textContent;
            if (preg_match('/(\d+\.?\d*)/', $rating_text, $matches)) {
                $rating = floatval($matches[1]);
            }
        }

        // Extract review count
        $review_node = $xpath->query(".//a[contains(@href, '#customerReviews')] | .//span[contains(@class, 'review-count')]", $node)->item(0);
        if ($review_node) {
            $review_text = $review_node->textContent;
            $review_text = preg_replace('/[^0-9]/', '', $review_text);
            $review_count = intval($review_text);
        }

        return [
            'rating' => $rating,
            'review_count' => $review_count,
        ];
    }

    /**
     * Track rank changes over time
     *
     * @since 2.0.0
     * @param array $products Products with current ranks.
     * @return void
     */
    private function track_rank_changes($products) {
        $rank_history = get_option('abc_bestseller_ranks_' . $this->campaign_id, []);

        foreach ($products as $product) {
            $asin = $product['asin'];
            $current_rank = $product['bestseller_rank'];

            if (isset($rank_history[$asin])) {
                $previous_rank = $rank_history[$asin]['rank'];
                $rank_change = $previous_rank - $current_rank;

                // Log significant rank changes
                if (abs($rank_change) >= 10) {
                    $direction = $rank_change > 0 ? 'up' : 'down';
                    Logger::log($this->campaign_id, 'info', 'discovery', "Bestseller rank changed: {$product['title']}", [
                        'asin' => $asin,
                        'previous_rank' => $previous_rank,
                        'current_rank' => $current_rank,
                        'change' => abs($rank_change),
                        'direction' => $direction,
                    ]);
                }
            }

            $rank_history[$asin] = [
                'rank' => $current_rank,
                'last_checked' => current_time('mysql'),
                'title' => $product['title'],
            ];
        }

        // Keep only last 100 products
        if (count($rank_history) > 100) {
            $rank_history = array_slice($rank_history, -100, 100, true);
        }

        update_option('abc_bestseller_ranks_' . $this->campaign_id, $rank_history);
    }

    /**
     * Queue products for processing
     *
     * @since 2.0.0
     * @param array $products Products.
     * @return array Results.
     */
    private function queue_products($products) {
        global $wpdb;

        $queued = 0;
        $skipped = 0;

        foreach ($products as $product) {
            if (empty($product['asin'])) {
                $skipped++;
                continue;
            }

            $content_hash = md5($product['asin']);

            // Check duplicates
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}abc_discovery_queue 
                 WHERE campaign_id = %d AND content_hash = %s",
                $this->campaign_id,
                $content_hash
            ));

            if ($existing) {
                // Update rank in raw_data
                $wpdb->update(
                    $wpdb->prefix . 'abc_discovery_queue',
                    ['raw_data' => json_encode($product)],
                    ['id' => $existing],
                    ['%s'],
                    ['%d']
                );
                $skipped++;
                continue;
            }

            // Higher priority for top-ranked products
            $priority = $product['bestseller_rank'] <= 10 ? 10 : 7;

            // Insert to queue
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'abc_discovery_queue',
                [
                    'campaign_id' => $this->campaign_id,
                    'source_url' => $product['url'],
                    'item_id' => $product['asin'],
                    'content_hash' => $content_hash,
                    'title' => $product['title'],
                    'raw_data' => json_encode($product),
                    'status' => 'pending',
                    'priority' => $priority,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );

            if ($inserted) {
                $queued++;
            }
        }

        Logger::log($this->campaign_id, 'info', 'discovery', 'Bestseller discovery completed', [
            'queued' => $queued,
            'skipped' => $skipped,
        ]);

        return [
            'queued' => $queued,
            'skipped' => $skipped,
            'total' => count($products),
        ];
    }

    /**
     * Get popular categories
     *
     * @since 2.0.0
     * @return array Category URLs.
     */
    public static function get_popular_categories() {
        return [
            'Books' => 'https://www.amazon.com/Best-Sellers-Books/zgbs/books',
            'Electronics' => 'https://www.amazon.com/Best-Sellers-Electronics/zgbs/electronics',
            'Home & Kitchen' => 'https://www.amazon.com/Best-Sellers-Home-Kitchen/zgbs/home-garden',
            'Clothing' => 'https://www.amazon.com/Best-Sellers-Clothing-Shoes-Jewelry/zgbs/fashion',
            'Sports & Outdoors' => 'https://www.amazon.com/Best-Sellers-Sports-Outdoors/zgbs/sporting-goods',
            'Toys & Games' => 'https://www.amazon.com/Best-Sellers-Toys-Games/zgbs/toys-and-games',
            'Health & Household' => 'https://www.amazon.com/Best-Sellers-Health-Personal-Care/zgbs/hpc',
            'Beauty' => 'https://www.amazon.com/Best-Sellers-Beauty/zgbs/beauty',
            'Tools & Home Improvement' => 'https://www.amazon.com/Best-Sellers-Home-Improvement/zgbs/hi',
            'Pet Supplies' => 'https://www.amazon.com/Best-Sellers-Pet-Supplies/zgbs/pet-supplies',
        ];
    }
}
