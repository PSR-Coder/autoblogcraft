<?php
/**
 * Geotargeting Filter
 *
 * Filters news articles by geographic relevance.
 * Supports country and region-based filtering.
 *
 * @package AutoBlogCraft\Discovery\News
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\News;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Geotargeting Filter class
 *
 * Responsibilities:
 * - Filter articles by country
 * - Detect article geo-relevance
 * - Support multiple countries
 * - Validate source locations
 *
 * @since 2.0.0
 */
class Geotargeting_Filter {

    /**
     * Target country
     *
     * @var string
     */
    private $country;

    /**
     * Target countries (for multi-country campaigns)
     *
     * @var array
     */
    private $countries;

    /**
     * Constructor
     *
     * @since 2.0.0
     * @param string|array $country Country code(s).
     */
    public function __construct($country = 'US') {
        if (is_array($country)) {
            $this->countries = array_map('strtoupper', $country);
            $this->country = $this->countries[0] ?? 'US';
        } else {
            $this->country = strtoupper($country);
            $this->countries = [$this->country];
        }
    }

    /**
     * Filter articles by geotargeting
     *
     * @since 2.0.0
     * @param array $articles Articles to filter.
     * @return array Filtered articles.
     */
    public function filter($articles) {
        // If no specific geotargeting, return all
        if (empty($this->countries) || in_array('ALL', $this->countries)) {
            return $articles;
        }

        $filtered = array_filter($articles, function($article) {
            return $this->is_geo_relevant($article);
        });

        return array_values($filtered);
    }

    /**
     * Check if article is geo-relevant
     *
     * @since 2.0.0
     * @param array $article Article data.
     * @return bool
     */
    private function is_geo_relevant($article) {
        // Check source domain country
        $source_country = $this->detect_source_country($article['source_url'] ?? $article['url'] ?? '');
        
        if ($source_country && in_array($source_country, $this->countries)) {
            return true;
        }

        // Check content for geo mentions
        $content = strtolower($article['title'] . ' ' . ($article['description'] ?? ''));
        
        foreach ($this->countries as $country) {
            $country_name = $this->get_country_name($country);
            if (!empty($country_name) && strpos($content, strtolower($country_name)) !== false) {
                return true;
            }
        }

        // Default to true if can't determine (don't filter out)
        return true;
    }

    /**
     * Detect source country from domain
     *
     * @since 2.0.0
     * @param string $url Source URL.
     * @return string|null Country code.
     */
    private function detect_source_country($url) {
        $domain = parse_url($url, PHP_URL_HOST);
        
        if (!$domain) {
            return null;
        }

        // Check TLD
        $tld = substr($domain, strrpos($domain, '.') + 1);
        $tld_to_country = $this->get_tld_country_map();

        if (isset($tld_to_country[strtoupper($tld)])) {
            return $tld_to_country[strtoupper($tld)];
        }

        // Check known domains
        $domain_map = $this->get_known_domains_map();
        foreach ($domain_map as $pattern => $country) {
            if (strpos($domain, $pattern) !== false) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Get TLD to country mapping
     *
     * @since 2.0.0
     * @return array
     */
    private function get_tld_country_map() {
        return [
            'US' => 'US',
            'UK' => 'GB',
            'CO.UK' => 'GB',
            'CA' => 'CA',
            'AU' => 'AU',
            'DE' => 'DE',
            'FR' => 'FR',
            'IT' => 'IT',
            'ES' => 'ES',
            'JP' => 'JP',
            'CN' => 'CN',
            'IN' => 'IN',
            'BR' => 'BR',
            'MX' => 'MX',
            'NL' => 'NL',
        ];
    }

    /**
     * Get known domains to country mapping
     *
     * @since 2.0.0
     * @return array
     */
    private function get_known_domains_map() {
        return [
            'nytimes.com' => 'US',
            'wsj.com' => 'US',
            'washingtonpost.com' => 'US',
            'cnn.com' => 'US',
            'foxnews.com' => 'US',
            'nbcnews.com' => 'US',
            'abcnews.com' => 'US',
            'cbsnews.com' => 'US',
            'usatoday.com' => 'US',
            'latimes.com' => 'US',
            
            'bbc.com' => 'GB',
            'bbc.co.uk' => 'GB',
            'theguardian.com' => 'GB',
            'telegraph.co.uk' => 'GB',
            'independent.co.uk' => 'GB',
            'dailymail.co.uk' => 'GB',
            
            'cbc.ca' => 'CA',
            'globalnews.ca' => 'CA',
            
            'abc.net.au' => 'AU',
            'news.com.au' => 'AU',
            
            'spiegel.de' => 'DE',
            'bild.de' => 'DE',
            
            'lemonde.fr' => 'FR',
            'lefigaro.fr' => 'FR',
            
            'elpais.com' => 'ES',
            'elmundo.es' => 'ES',
        ];
    }

    /**
     * Get country name from code
     *
     * @since 2.0.0
     * @param string $code Country code.
     * @return string Country name.
     */
    private function get_country_name($code) {
        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'NL' => 'Netherlands',
        ];

        return $countries[strtoupper($code)] ?? '';
    }

    /**
     * Get country code from name
     *
     * @since 2.0.0
     * @param string $name Country name.
     * @return string|null Country code.
     */
    public static function get_country_code($name) {
        $name = strtolower(trim($name));
        
        $map = [
            'united states' => 'US',
            'usa' => 'US',
            'america' => 'US',
            'united kingdom' => 'GB',
            'uk' => 'GB',
            'britain' => 'GB',
            'canada' => 'CA',
            'australia' => 'AU',
            'germany' => 'DE',
            'france' => 'FR',
            'italy' => 'IT',
            'spain' => 'ES',
            'japan' => 'JP',
            'china' => 'CN',
            'india' => 'IN',
            'brazil' => 'BR',
            'mexico' => 'MX',
            'netherlands' => 'NL',
        ];

        return $map[$name] ?? null;
    }

    /**
     * Get supported countries
     *
     * @since 2.0.0
     * @return array
     */
    public static function get_supported_countries() {
        return [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'NL' => 'Netherlands',
        ];
    }
}
