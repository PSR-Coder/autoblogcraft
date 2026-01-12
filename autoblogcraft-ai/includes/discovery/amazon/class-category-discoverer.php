<?php
/**
 * Amazon Category Discoverer
 *
 * Discovers Amazon products by browsing category pages.
 * Extracts products from category listings and subcategories.
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
 * Amazon Category Discoverer class
 *
 * Responsibilities:
 * - Browse Amazon category pages
 * - Extract products from categories
 * - Navigate subcategories
 * - Apply filters (price, rating, reviews)
 * - Queue discovered products
 *
 * @since 2.0.0
 */
class Category_Discoverer extends Base_Discoverer {

    /**
     * Campaign ID
     *
     * @var int
     */
    private $campaign_id;

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
     * Discover products from category URL
     *
     * @since 2.0.0
     * @param string $category_url Category URL.
     * @param array  $options      Discovery options.
     * @return array Results.
     */
    public function discover_by_category($category_url, $options = []) {
        Logger::log($this->campaign_id, 'info', 'discovery', "Discovering Amazon category: {$category_url}");

        $defaults = [
            'max_pages' => 3,
            'max_products' => 30,
            'min_rating' => 0,
            'min_reviews' => 0,
            'include_subcategories' => false,
        ];

        $options = array_merge($defaults, $options);

        $all_products = [];
        $page = 1;

        while ($page <= $options['max_pages'] && count($all_products) < $options['max_products']) {
            $page_url = $this->build_page_url($category_url, $page);
            $products = $this->fetch_category_page($page_url, $options);

            if (empty($products)) {
                break; // No more products
            }

            $all_products = array_merge($all_products, $products);
            $page++;

            // Rate limiting
            sleep(2);
        }

        // Discover subcategories if enabled
        if ($options['include_subcategories']) {
            $subcategories = $this->discover_subcategories($category_url);
            foreach ($subcategories as $subcat_url) {
                $subcat_products = $this->discover_by_category($subcat_url, [
                    'max_pages' => 1,
                    'max_products' => 10,
                    'include_subcategories' => false,
                ]);
                $all_products = array_merge($all_products, $subcat_products);
            }
        }

        return $this->queue_products(array_slice($all_products, 0, $options['max_products']));
    }

    /**
     * Build pagination URL
     *
     * @since 2.0.0
     * @param string $base_url Base category URL.
     * @param int    $page     Page number.
     * @return string Paginated URL.
     */
    private function build_page_url($base_url, $page) {
        if ($page === 1) {
            return $base_url;
        }

        $separator = strpos($base_url, '?') !== false ? '&' : '?';
        return $base_url . $separator . 'page=' . $page;
    }

    /**
     * Fetch and parse category page
     *
     * @since 2.0.0
     * @param string $url     Category URL.
     * @param array  $options Options.
     * @return array Products.
     */
    private function fetch_category_page($url, $options) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers' => [
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'Failed to fetch category page', [
                'url' => $url,
                'error' => $response->get_error_message(),
            ]);
            return [];
        }

        $html = wp_remote_retrieve_body($response);
        return $this->parse_category_page($html, $options);
    }

    /**
     * Parse category page HTML
     *
     * @since 2.0.0
     * @param string $html    HTML content.
     * @param array  $options Options.
     * @return array Products.
     */
    private function parse_category_page($html, $options) {
        $products = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Find product grids/lists
        $product_nodes = $xpath->query("//div[@data-component-type='s-search-result'] | //div[contains(@class, 'product-item')]");

        foreach ($product_nodes as $node) {
            $product = $this->extract_product_data($xpath, $node);

            if (empty($product['asin'])) {
                continue;
            }

            // Apply filters
            if ($product['rating'] < $options['min_rating']) {
                continue;
            }

            if ($product['review_count'] < $options['min_reviews']) {
                continue;
            }

            $products[] = $product;
        }

        Logger::log($this->campaign_id, 'debug', 'discovery', "Found {$count} products on category page", [
            'count' => count($products),
        ]);

        return $products;
    }

    /**
     * Extract product data from DOM node
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath.
     * @param \DOMNode  $node  Product node.
     * @return array Product data.
     */
    private function extract_product_data($xpath, $node) {
        // Extract ASIN
        $asin = $node->getAttribute('data-asin');
        if (empty($asin)) {
            $asin = $this->extract_asin_from_url($node);
        }

        // Extract title
        $title_node = $xpath->query(".//h2//span | .//h2//a", $node)->item(0);
        $title = $title_node ? trim($title_node->textContent) : '';

        // Extract price
        $price = $this->extract_price($xpath, $node);

        // Extract rating
        $rating_data = $this->extract_rating($xpath, $node);

        // Extract image
        $image_node = $xpath->query(".//img", $node)->item(0);
        $image_url = $image_node ? $image_node->getAttribute('src') : '';

        // Extract link
        $link_node = $xpath->query(".//h2//a | .//a[contains(@class, 'product-link')]", $node)->item(0);
        $product_url = $link_node ? $link_node->getAttribute('href') : '';
        
        if (!empty($product_url) && strpos($product_url, 'http') !== 0) {
            $product_url = 'https://www.amazon.com' . $product_url;
        }

        // Extract category/department
        $category = $this->extract_category($xpath, $node);

        return [
            'asin' => $asin,
            'title' => $title,
            'url' => $product_url ?: 'https://www.amazon.com/dp/' . $asin,
            'price' => $price,
            'rating' => $rating_data['rating'],
            'review_count' => $rating_data['review_count'],
            'image_url' => $image_url,
            'category' => $category,
        ];
    }

    /**
     * Extract ASIN from product URL
     *
     * @since 2.0.0
     * @param \DOMNode $node Product node.
     * @return string ASIN.
     */
    private function extract_asin_from_url($node) {
        $xpath = new \DOMXPath($node->ownerDocument);
        $link_node = $xpath->query(".//a", $node)->item(0);
        
        if (!$link_node) {
            return '';
        }

        $url = $link_node->getAttribute('href');
        
        // Extract ASIN from URL patterns
        if (preg_match('/\/dp\/([A-Z0-9]{10})/', $url, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/\/gp\/product\/([A-Z0-9]{10})/', $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Extract price from node
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath.
     * @param \DOMNode  $node  Node.
     * @return float Price.
     */
    private function extract_price($xpath, $node) {
        $price_node = $xpath->query(".//span[@class='a-price-whole'] | .//span[contains(@class, 'price')]", $node)->item(0);
        
        if (!$price_node) {
            return 0.0;
        }

        $price_text = $price_node->textContent;
        $price_text = preg_replace('/[^0-9.]/', '', $price_text);
        
        return floatval($price_text);
    }

    /**
     * Extract rating and review count
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
        $review_node = $xpath->query(".//span[@class='a-size-base'] | .//span[contains(@class, 'review-count')]", $node)->item(0);
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
     * Extract category/department
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath.
     * @param \DOMNode  $node  Node.
     * @return string Category.
     */
    private function extract_category($xpath, $node) {
        $category_node = $xpath->query(".//span[contains(@class, 'category')] | .//a[contains(@class, 'department')]", $node)->item(0);
        return $category_node ? trim($category_node->textContent) : '';
    }

    /**
     * Discover subcategories
     *
     * @since 2.0.0
     * @param string $category_url Parent category URL.
     * @return array Subcategory URLs.
     */
    private function discover_subcategories($category_url) {
        $response = wp_remote_get($category_url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $html = wp_remote_retrieve_body($response);

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Find subcategory links
        $subcat_nodes = $xpath->query("//div[@id='s-refinements']//a[contains(@class, 'category')] | //div[contains(@class, 'left-nav')]//a");

        $subcategories = [];
        foreach ($subcat_nodes as $link) {
            $url = $link->getAttribute('href');
            if (!empty($url) && strpos($url, 'http') !== 0) {
                $url = 'https://www.amazon.com' . $url;
            }
            if (!empty($url) && !in_array($url, $subcategories)) {
                $subcategories[] = $url;
            }
        }

        return array_slice($subcategories, 0, 5); // Limit to 5 subcategories
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
                $skipped++;
                continue;
            }

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
                    'priority' => 5,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );

            if ($inserted) {
                $queued++;
            }
        }

        Logger::log($this->campaign_id, 'info', 'discovery', 'Category discovery completed', [
            'queued' => $queued,
            'skipped' => $skipped,
        ]);

        return [
            'queued' => $queued,
            'skipped' => $skipped,
            'total' => count($products),
        ];
    }
}
