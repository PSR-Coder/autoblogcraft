<?php
/**
 * Amazon Processor
 *
 * Complete Amazon product processing with ASIN extraction, product data enrichment,
 * affiliate link injection, and price tracking.
 *
 * @package AutoBlogCraft\Processing\Processors
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing\Processors;

use AutoBlogCraft\Processing\Base_Processor;
use AutoBlogCraft\Core\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon Processor class
 *
 * Handles processing of Amazon product queue items into WordPress posts
 * with affiliate link integration and product data enrichment.
 *
 * @since 2.0.0
 */
class Amazon_Processor extends Base_Processor {

    /**
     * Amazon Associate tag
     *
     * @var string
     */
    private $associate_tag;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Process Amazon product queue item
     *
     * @since 2.0.0
     * @param object $item Queue item from database.
     * @return int|WP_Error Post ID or error.
     */
    public function process($item) {
        $this->logger->info("Processing Amazon product: {$item->id}");

        // Parse metadata
        $metadata = json_decode($item->metadata, true);
        if (empty($metadata)) {
            return new WP_Error('invalid_metadata', 'Queue item has no valid metadata');
        }

        // Get campaign and configuration
        $campaign_id = $item->campaign_id;
        $campaign = \AutoBlogCraft\Campaigns\Campaign_Factory::create($campaign_id);
        
        if (is_wp_error($campaign)) {
            return $campaign;
        }

        // Get Amazon configuration
        $this->associate_tag = get_post_meta($campaign_id, '_amazon_associate_tag', true);
        
        if (empty($this->associate_tag)) {
            return new WP_Error('no_associate_tag', 'Amazon Associate Tag not configured');
        }

        // Extract ASIN
        $asin = $this->extract_asin($metadata, $item);
        if (is_wp_error($asin)) {
            return $asin;
        }

        $metadata['asin'] = $asin;

        // Enrich product data if needed
        if (!$this->has_complete_data($metadata)) {
            $enriched = $this->enrich_product_data($asin, $metadata);
            if (!is_wp_error($enriched)) {
                $metadata = array_merge($metadata, $enriched);
            }
        }

        // Build content
        $content = $this->build_product_content($metadata, $campaign_id);
        if (is_wp_error($content)) {
            return $content;
        }

        // Generate title
        $title = $this->generate_title($metadata, $campaign_id);

        // Prepare post data
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => get_post_meta($campaign_id, '_post_status', true) ?: 'draft',
            'post_author' => get_post_meta($campaign_id, '_post_author', true) ?: 1,
            'post_type' => 'post',
            'meta_input' => [
                '_abc_campaign_id' => $campaign_id,
                '_abc_queue_item_id' => $item->id,
                '_abc_source_url' => $item->source_url,
                '_abc_asin' => $asin,
                '_abc_amazon_price' => $metadata['price'] ?? '',
                '_abc_amazon_rating' => $metadata['rating'] ?? '',
                '_abc_amazon_reviews' => $metadata['review_count'] ?? '',
                '_abc_product_image_url' => $metadata['image'] ?? '',
            ],
        ];

        // Set category
        $category_id = get_post_meta($campaign_id, '_post_category', true);
        if ($category_id) {
            $post_data['post_category'] = [$category_id];
        }

        // Insert post
        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set featured image
        $this->set_featured_image($post_id, $metadata, $campaign_id);

        // Set tags
        $this->set_tags($post_id, $metadata, $campaign_id);

        // Track product
        $this->track_product($asin, $metadata);

        $this->logger->info("Amazon product processed successfully: Post {$post_id}, ASIN {$asin}");

        return $post_id;
    }

    /**
     * Extract ASIN from metadata
     *
     * @since 2.0.0
     * @param array $metadata Product metadata.
     * @param object $item Queue item.
     * @return string|WP_Error ASIN or error.
     */
    private function extract_asin($metadata, $item) {
        // Check if ASIN in metadata
        if (!empty($metadata['asin'])) {
            return $metadata['asin'];
        }

        // Try to extract from URL
        $url = $item->source_url;
        
        // Pattern 1: /dp/ASIN
        if (preg_match('/\/dp\/([A-Z0-9]{10})/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 2: /gp/product/ASIN
        if (preg_match('/\/gp\/product\/([A-Z0-9]{10})/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 3: Direct ASIN in metadata
        if (!empty($metadata['product_id']) && preg_match('/^[A-Z0-9]{10}$/', $metadata['product_id'])) {
            return $metadata['product_id'];
        }

        return new WP_Error('asin_not_found', 'Could not extract ASIN from product data');
    }

    /**
     * Check if product data is complete
     *
     * @since 2.0.0
     * @param array $metadata Product metadata.
     * @return bool True if complete, false otherwise.
     */
    private function has_complete_data($metadata) {
        $required_fields = ['title', 'price', 'image'];
        
        foreach ($required_fields as $field) {
            if (empty($metadata[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Enrich product data
     *
     * Fetches additional product information from Amazon if needed.
     *
     * @since 2.0.0
     * @param string $asin Product ASIN.
     * @param array $existing_data Existing metadata.
     * @return array|WP_Error Enriched data or error.
     */
    private function enrich_product_data($asin, $existing_data = []) {
        $this->logger->info("Enriching product data for ASIN: {$asin}");

        // Try Amazon PA-API first
        $api_data = $this->fetch_from_api($asin);
        
        if (!is_wp_error($api_data)) {
            return array_merge($existing_data, $api_data);
        }

        // Fallback to web scraping
        $scraped_data = $this->scrape_product_page($asin);
        
        if (!is_wp_error($scraped_data)) {
            return array_merge($existing_data, $scraped_data);
        }

        // Return existing data if enrichment fails
        return $existing_data;
    }

    /**
     * Fetch product data from Amazon PA-API
     *
     * @since 2.0.0
     * @param string $asin Product ASIN.
     * @return array|WP_Error Product data or error.
     */
    private function fetch_from_api($asin) {
        // Get PA-API credentials
        $credentials = $this->get_pa_api_credentials();
        if (is_wp_error($credentials)) {
            return $credentials;
        }

        // Build API request
        $endpoint = 'https://webservices.amazon.com/paapi5/getitems';
        
        $payload = [
            'ItemIds' => [$asin],
            'Resources' => [
                'Images.Primary.Large',
                'ItemInfo.Title',
                'ItemInfo.ByLineInfo',
                'ItemInfo.Features',
                'Offers.Listings.Price',
            ],
            'PartnerTag' => $this->associate_tag,
            'PartnerType' => 'Associates',
            'Marketplace' => 'www.amazon.com',
        ];

        $headers = $this->generate_api_headers($endpoint, $payload, $credentials);

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            return new WP_Error('api_error', 'PA-API request failed', ['status' => $code]);
        }

        if (empty($data['ItemsResult']['Items'][0])) {
            return new WP_Error('no_data', 'No product data in API response');
        }

        return $this->parse_api_response($data['ItemsResult']['Items'][0]);
    }

    /**
     * Parse PA-API response
     *
     * @since 2.0.0
     * @param array $item API response item.
     * @return array Parsed product data.
     */
    private function parse_api_response($item) {
        $data = [];

        // Title
        if (!empty($item['ItemInfo']['Title']['DisplayValue'])) {
            $data['title'] = $item['ItemInfo']['Title']['DisplayValue'];
        }

        // Image
        if (!empty($item['Images']['Primary']['Large']['URL'])) {
            $data['image'] = $item['Images']['Primary']['Large']['URL'];
        }

        // Price
        if (!empty($item['Offers']['Listings'][0]['Price']['DisplayAmount'])) {
            $data['price'] = $item['Offers']['Listings'][0]['Price']['DisplayAmount'];
        }

        // Features
        if (!empty($item['ItemInfo']['Features']['DisplayValues'])) {
            $data['features'] = $item['ItemInfo']['Features']['DisplayValues'];
        }

        // Brand
        if (!empty($item['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'])) {
            $data['brand'] = $item['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'];
        }

        return $data;
    }

    /**
     * Scrape product page
     *
     * @since 2.0.0
     * @param string $asin Product ASIN.
     * @return array|WP_Error Scraped data or error.
     */
    private function scrape_product_page($asin) {
        $url = "https://www.amazon.com/dp/{$asin}";

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $html = wp_remote_retrieve_body($response);
        
        $data = [];

        // Parse HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        // Title
        $title_node = $xpath->query('//span[@id="productTitle"]')->item(0);
        if ($title_node) {
            $data['title'] = trim($title_node->textContent);
        }

        // Price
        $price_node = $xpath->query('//span[@class="a-price-whole"]')->item(0);
        if ($price_node) {
            $data['price'] = '$' . trim($price_node->textContent);
        }

        // Image
        $image_node = $xpath->query('//img[@id="landingImage"]')->item(0);
        if ($image_node) {
            $data['image'] = $image_node->getAttribute('src');
        }

        // Rating
        $rating_node = $xpath->query('//span[@class="a-icon-alt"]')->item(0);
        if ($rating_node) {
            preg_match('/(\d+\.?\d*)/', $rating_node->textContent, $matches);
            if (!empty($matches[1])) {
                $data['rating'] = $matches[1];
            }
        }

        return $data;
    }

    /**
     * Build product content
     *
     * @since 2.0.0
     * @param array $metadata Product metadata.
     * @param int $campaign_id Campaign ID.
     * @return string|WP_Error Product content or error.
     */
    private function build_product_content($metadata, $campaign_id) {
        $template = get_post_meta($campaign_id, '_amazon_content_template', true);

        if (empty($template)) {
            // Default template
            $template = $this->get_default_template();
        }

        // Build affiliate link
        $affiliate_link = $this->build_affiliate_link($metadata['asin']);

        // Replace placeholders
        $replacements = [
            '{title}' => $metadata['title'] ?? '',
            '{price}' => $metadata['price'] ?? 'Check Price',
            '{rating}' => $metadata['rating'] ?? '',
            '{reviews}' => $metadata['review_count'] ?? '',
            '{description}' => $metadata['description'] ?? '',
            '{features}' => $this->format_features($metadata['features'] ?? []),
            '{brand}' => $metadata['brand'] ?? '',
            '{category}' => $metadata['category'] ?? '',
            '{affiliate_link}' => $affiliate_link,
            '{buy_button}' => $this->build_buy_button($affiliate_link, $metadata),
            '{asin}' => $metadata['asin'],
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Apply AI rewriting if enabled
        if (get_post_meta($campaign_id, '_ai_rewrite_enabled', true)) {
            $rewriter = new \AutoBlogCraft\Processing\AI_Rewriter();
            $content = $rewriter->rewrite($content, [
                'campaign_id' => $campaign_id,
                'strategy' => 'light',
            ]);
        }

        return $content;
    }

    /**
     * Get default content template
     *
     * @since 2.0.0
     * @return string Default template.
     */
    private function get_default_template() {
        return <<<HTML
<div class="amazon-product">
    <h2>{title}</h2>
    
    <div class="product-meta">
        <span class="price">{price}</span>
        <span class="rating">★ {rating}/5</span>
    </div>
    
    <div class="product-description">
        {description}
    </div>
    
    <div class="product-features">
        <h3>Key Features:</h3>
        {features}
    </div>
    
    <div class="buy-section">
        {buy_button}
    </div>
</div>
HTML;
    }

    /**
     * Format product features
     *
     * @since 2.0.0
     * @param array $features Features array.
     * @return string Formatted HTML.
     */
    private function format_features($features) {
        if (empty($features)) {
            return '';
        }

        $html = '<ul class="product-features">';
        foreach ($features as $feature) {
            $html .= '<li>' . esc_html($feature) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Build affiliate link
     *
     * @since 2.0.0
     * @param string $asin Product ASIN.
     * @return string Affiliate link.
     */
    private function build_affiliate_link($asin) {
        return "https://www.amazon.com/dp/{$asin}?tag={$this->associate_tag}";
    }

    /**
     * Build buy button
     *
     * @since 2.0.0
     * @param string $link Affiliate link.
     * @param array $metadata Product metadata.
     * @return string Buy button HTML.
     */
    private function build_buy_button($link, $metadata) {
        $price = !empty($metadata['price']) ? 'Buy Now - ' . $metadata['price'] : 'Check Price on Amazon';
        
        return sprintf(
            '<a href="%s" class="amazon-buy-button" target="_blank" rel="nofollow noopener">%s</a>',
            esc_url($link),
            esc_html($price)
        );
    }

    /**
     * Generate post title
     *
     * @since 2.0.0
     * @param array $metadata Product metadata.
     * @param int $campaign_id Campaign ID.
     * @return string Post title.
     */
    private function generate_title($metadata, $campaign_id) {
        $template = get_post_meta($campaign_id, '_amazon_title_template', true);

        if (empty($template)) {
            // Default: "[Product Title] - Review & Price Guide"
            return ($metadata['title'] ?? 'Product') . ' - Review & Price Guide';
        }

        $replacements = [
            '{title}' => $metadata['title'] ?? 'Product',
            '{brand}' => $metadata['brand'] ?? '',
            '{category}' => $metadata['category'] ?? '',
            '{price}' => $metadata['price'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Set featured image
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $metadata Product metadata.
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    private function set_featured_image($post_id, $metadata, $campaign_id) {
        if (empty($metadata['image'])) {
            return;
        }

        $image_generator = new \AutoBlogCraft\Processing\Featured_Image_Generator();
        
        $attachment_id = $image_generator->generate([
            'strategy' => 'source',
        ], [
            'metadata' => $metadata,
            'title' => $metadata['title'] ?? '',
        ]);

        if (!is_wp_error($attachment_id) && $attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    /**
     * Set post tags
     *
     * @since 2.0.0
     * @param int $post_id Post ID.
     * @param array $metadata Product metadata.
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    private function set_tags($post_id, $metadata, $campaign_id) {
        $tags = [];

        if (!empty($metadata['brand'])) {
            $tags[] = $metadata['brand'];
        }

        if (!empty($metadata['category'])) {
            $tags[] = $metadata['category'];
        }

        // Extract from title
        if (!empty($metadata['title'])) {
            $words = preg_split('/\s+/', $metadata['title']);
            $tags = array_merge($tags, array_slice($words, 0, 3));
        }

        if (!empty($tags)) {
            wp_set_post_tags($post_id, $tags, false);
        }
    }

    /**
     * Track product for price monitoring
     *
     * @since 2.0.0
     * @param string $asin Product ASIN.
     * @param array $metadata Product metadata.
     * @return void
     */
    private function track_product($asin, $metadata) {
        $tracking = get_option('abc_amazon_tracking', []);

        $tracking[$asin] = [
            'last_price' => $metadata['price'] ?? '',
            'last_checked' => current_time('mysql'),
            'title' => $metadata['title'] ?? '',
        ];

        update_option('abc_amazon_tracking', $tracking);
    }

    /**
     * Get PA-API credentials
     *
     * @since 2.0.0
     * @return array|WP_Error Credentials or error.
     */
    private function get_pa_api_credentials() {
        global $wpdb;

        $key = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}abc_api_keys 
            WHERE provider = 'amazon' AND status = 'active' 
            LIMIT 1"
        );

        if (!$key) {
            return new WP_Error('no_api_key', 'No Amazon PA-API key configured');
        }

        return [
            'access_key' => $key->api_key,
            'secret_key' => $key->api_secret,
        ];
    }

    /**
     * Generate PA-API headers
     *
     * @since 2.0.0
     * @param string $endpoint API endpoint.
     * @param array $payload Request payload.
     * @param array $credentials API credentials.
     * @return array Headers.
     */
    private function generate_api_headers($endpoint, $payload, $credentials) {
        // Simplified - full implementation requires AWS Signature V4
        return [
            'Content-Type' => 'application/json',
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems',
            'X-Amz-Date' => gmdate('Ymd\THis\Z'),
        ];
    }
}
