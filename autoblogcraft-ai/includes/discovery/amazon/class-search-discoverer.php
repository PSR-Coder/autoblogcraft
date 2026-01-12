<?php
/**
 * Amazon Search Discoverer
 *
 * Discovers Amazon products by keyword search using Amazon Product Advertising API
 * or fallback to web scraping methods.
 *
 * @package AutoBlogCraft\Discovery\Amazon
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\Amazon;

use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Discovery\Base_Discoverer;
use AutoBlogCraft\Helpers\Sanitization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon Search Discoverer class
 *
 * Responsibilities:
 * - Search Amazon products by keywords
 * - Parse search results
 * - Extract product details (ASIN, title, price, rating, image)
 * - Apply product filters (price range, rating, availability)
 * - Queue products for processing
 *
 * @since 2.0.0
 */
class Search_Discoverer extends Base_Discoverer {

    /**
     * Campaign ID
     *
     * @var int
     */
    private $campaign_id;

    /**
     * API credentials
     *
     * @var array
     */
    private $api_credentials;

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    public function __construct($campaign_id) {
        $this->campaign_id = $campaign_id;
        $this->load_api_credentials();
    }

    /**
     * Load Amazon API credentials
     *
     * @since 2.0.0
     * @return void
     */
    private function load_api_credentials() {
        global $wpdb;

        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_api_keys 
             WHERE provider = 'amazon' AND status = 'active' 
             ORDER BY last_used_at ASC LIMIT 1"
        ));

        if ($key) {
            $this->api_credentials = [
                'access_key' => $this->decrypt_key($key->api_key),
                'secret_key' => $this->decrypt_key($key->api_secret),
                'associate_tag' => $key->additional_data ? json_decode($key->additional_data, true)['associate_tag'] ?? '' : '',
            ];
        }
    }

    /**
     * Discover products by keyword
     *
     * @since 2.0.0
     * @param string $keyword Search keyword.
     * @param array  $filters Product filters.
     * @return array Discovered products.
     */
    public function discover_by_keyword($keyword, $filters = []) {
        Logger::log($this->campaign_id, 'info', 'discovery', "Amazon search: {$keyword}");

        // Try API first
        if ($this->api_credentials) {
            $products = $this->search_via_api($keyword, $filters);
            if (!empty($products)) {
                return $this->queue_products($products);
            }
        }

        // Fallback to web scraping
        Logger::log($this->campaign_id, 'warning', 'discovery', 'Amazon API unavailable, using fallback scraping');
        $products = $this->search_via_scraping($keyword, $filters);

        return $this->queue_products($products);
    }

    /**
     * Search via Amazon Product Advertising API
     *
     * @since 2.0.0
     * @param string $keyword Search keyword.
     * @param array  $filters Filters.
     * @return array Products.
     */
    private function search_via_api($keyword, $filters) {
        if (!$this->api_credentials) {
            return [];
        }

        $endpoint = 'https://webservices.amazon.com/paapi5/searchitems';
        
        $payload = [
            'Keywords' => $keyword,
            'Resources' => [
                'Images.Primary.Large',
                'ItemInfo.Title',
                'ItemInfo.Features',
                'Offers.Listings.Price',
                'CustomerReviews.StarRating',
            ],
            'ItemCount' => $filters['max_results'] ?? 10,
            'PartnerTag' => $this->api_credentials['associate_tag'],
            'PartnerType' => 'Associates',
            'Marketplace' => 'www.amazon.com',
        ];

        // Apply filters
        if (isset($filters['min_price'])) {
            $payload['MinPrice'] = intval($filters['min_price'] * 100);
        }

        if (isset($filters['max_price'])) {
            $payload['MaxPrice'] = intval($filters['max_price'] * 100);
        }

        $response = $this->make_api_request($endpoint, $payload);

        if (!$response || !isset($response['SearchResult']['Items'])) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'Amazon API search failed', ['keyword' => $keyword]);
            return [];
        }

        return $this->parse_api_response($response['SearchResult']['Items'], $filters);
    }

    /**
     * Make Amazon API request
     *
     * @since 2.0.0
     * @param string $endpoint API endpoint.
     * @param array  $payload  Request payload.
     * @return array|false Response data.
     */
    private function make_api_request($endpoint, $payload) {
        $headers = $this->generate_api_headers($endpoint, $payload);

        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'Amazon API request failed', [
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Generate Amazon API authentication headers
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint URL.
     * @param array  $payload  Request payload.
     * @return array Headers.
     */
    private function generate_api_headers($endpoint, $payload) {
        // Amazon PA-API 5.0 signature generation
        $timestamp = gmdate('Ymd\THis\Z');
        
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Amz-Date' => $timestamp,
            'Authorization' => $this->generate_signature($endpoint, $payload, $timestamp),
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems',
        ];
    }

    /**
     * Generate AWS Signature V4
     *
     * @since 2.0.0
     * @param string $endpoint  Endpoint.
     * @param array  $payload   Payload.
     * @param string $timestamp Timestamp.
     * @return string Signature.
     */
    private function generate_signature($endpoint, $payload, $timestamp) {
        // Simplified AWS Signature V4 - implement full version in production
        $access_key = $this->api_credentials['access_key'];
        $secret_key = $this->api_credentials['secret_key'];
        
        $canonical_request = json_encode($payload);
        $string_to_sign = $timestamp . $canonical_request;
        
        return hash_hmac('sha256', $string_to_sign, $secret_key);
    }

    /**
     * Parse API response
     *
     * @since 2.0.0
     * @param array $items   API items.
     * @param array $filters Filters.
     * @return array Parsed products.
     */
    private function parse_api_response($items, $filters) {
        $products = [];

        foreach ($items as $item) {
            $product = [
                'asin' => $item['ASIN'] ?? '',
                'title' => $item['ItemInfo']['Title']['DisplayValue'] ?? '',
                'url' => $item['DetailPageURL'] ?? '',
                'price' => $this->extract_price($item),
                'rating' => $this->extract_rating($item),
                'image_url' => $item['Images']['Primary']['Large']['URL'] ?? '',
                'features' => $item['ItemInfo']['Features']['DisplayValues'] ?? [],
            ];

            // Apply rating filter
            if (isset($filters['min_rating']) && $product['rating'] < $filters['min_rating']) {
                continue;
            }

            // Apply availability filter
            if (isset($filters['require_available']) && !$this->is_available($item)) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Extract price from API item
     *
     * @since 2.0.0
     * @param array $item API item.
     * @return float Price.
     */
    private function extract_price($item) {
        if (isset($item['Offers']['Listings'][0]['Price']['Amount'])) {
            return floatval($item['Offers']['Listings'][0]['Price']['Amount']);
        }
        return 0.0;
    }

    /**
     * Extract rating from API item
     *
     * @since 2.0.0
     * @param array $item API item.
     * @return float Rating.
     */
    private function extract_rating($item) {
        if (isset($item['CustomerReviews']['StarRating']['Value'])) {
            return floatval($item['CustomerReviews']['StarRating']['Value']);
        }
        return 0.0;
    }

    /**
     * Check if product is available
     *
     * @since 2.0.0
     * @param array $item API item.
     * @return bool
     */
    private function is_available($item) {
        return isset($item['Offers']['Listings'][0]['Availability']['Type']) &&
               $item['Offers']['Listings'][0]['Availability']['Type'] === 'Now';
    }

    /**
     * Search via web scraping (fallback)
     *
     * @since 2.0.0
     * @param string $keyword Search keyword.
     * @param array  $filters Filters.
     * @return array Products.
     */
    private function search_via_scraping($keyword, $filters) {
        $search_url = 'https://www.amazon.com/s?k=' . urlencode($keyword);

        $response = wp_remote_get($search_url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        if (is_wp_error($response)) {
            Logger::log($this->campaign_id, 'error', 'discovery', 'Amazon scraping failed', [
                'error' => $response->get_error_message(),
            ]);
            return [];
        }

        $html = wp_remote_retrieve_body($response);
        return $this->parse_search_page($html, $filters);
    }

    /**
     * Parse Amazon search page HTML
     *
     * @since 2.0.0
     * @param string $html    HTML content.
     * @param array  $filters Filters.
     * @return array Products.
     */
    private function parse_search_page($html, $filters) {
        $products = [];

        // Use DOMDocument to parse HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        
        // Find product containers
        $product_nodes = $xpath->query("//div[@data-component-type='s-search-result']");

        foreach ($product_nodes as $node) {
            $product = $this->extract_product_from_node($xpath, $node);

            if (empty($product['asin'])) {
                continue;
            }

            // Apply filters
            if (isset($filters['min_rating']) && $product['rating'] < $filters['min_rating']) {
                continue;
            }

            if (isset($filters['min_price']) && $product['price'] < $filters['min_price']) {
                continue;
            }

            if (isset($filters['max_price']) && $product['price'] > $filters['max_price']) {
                continue;
            }

            $products[] = $product;

            // Limit results
            if (count($products) >= ($filters['max_results'] ?? 10)) {
                break;
            }
        }

        return $products;
    }

    /**
     * Extract product data from DOM node
     *
     * @since 2.0.0
     * @param \DOMXPath $xpath XPath object.
     * @param \DOMNode  $node  Product node.
     * @return array Product data.
     */
    private function extract_product_from_node($xpath, $node) {
        $asin = $node->getAttribute('data-asin');
        
        $title_node = $xpath->query(".//h2//span", $node)->item(0);
        $title = $title_node ? $title_node->textContent : '';

        $price_node = $xpath->query(".//span[@class='a-price-whole']", $node)->item(0);
        $price = $price_node ? floatval(str_replace(',', '', $price_node->textContent)) : 0.0;

        $rating_node = $xpath->query(".//span[@class='a-icon-alt']", $node)->item(0);
        $rating = 0.0;
        if ($rating_node) {
            preg_match('/(\d+\.?\d*)/', $rating_node->textContent, $matches);
            $rating = isset($matches[1]) ? floatval($matches[1]) : 0.0;
        }

        $image_node = $xpath->query(".//img[@class='s-image']", $node)->item(0);
        $image_url = $image_node ? $image_node->getAttribute('src') : '';

        $url = 'https://www.amazon.com/dp/' . $asin;

        return [
            'asin' => $asin,
            'title' => trim($title),
            'url' => $url,
            'price' => $price,
            'rating' => $rating,
            'image_url' => $image_url,
            'features' => [],
        ];
    }

    /**
     * Queue products for processing
     *
     * @since 2.0.0
     * @param array $products Products to queue.
     * @return array Results.
     */
    private function queue_products($products) {
        global $wpdb;

        $queued = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $content_hash = md5($product['asin']);

            // Check for duplicates
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

        Logger::log($this->campaign_id, 'info', 'discovery', 'Amazon search completed', [
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
     * Decrypt API key
     *
     * @since 2.0.0
     * @param string $encrypted Encrypted key.
     * @return string Decrypted key.
     */
    private function decrypt_key($encrypted) {
        // Implement decryption logic (PBKDF2)
        // For now, return as-is (assuming stored unencrypted in dev)
        return $encrypted;
    }
}
