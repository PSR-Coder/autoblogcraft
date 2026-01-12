<?php
/**
 * News Campaign Wizard
 *
 * Specialized wizard for creating news article campaigns.
 * Handles keyword search, SERP discovery, and news filtering.
 *
 * @package AutoBlogCraft\Admin\Wizards
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Wizards;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Wizard class
 *
 * @since 2.0.0
 */
class News_Wizard extends Wizard_Base {

    /**
     * Get campaign type
     *
     * @since 2.0.0
     * @return string
     */
    protected function get_campaign_type() {
        return 'news';
    }

    /**
     * Get wizard steps
     *
     * @since 2.0.0
     * @return array
     */
    public function get_steps() {
        return [
            [
                'id' => 'basic',
                'title' => __('Basic Information', 'autoblogcraft'),
                'description' => __('Campaign name and description', 'autoblogcraft'),
            ],
            [
                'id' => 'keywords',
                'title' => __('News Keywords', 'autoblogcraft'),
                'description' => __('Topics and search terms', 'autoblogcraft'),
            ],
            [
                'id' => 'filters',
                'title' => __('News Filters', 'autoblogcraft'),
                'description' => __('Freshness and source filters', 'autoblogcraft'),
            ],
            [
                'id' => 'processing',
                'title' => __('Processing Settings', 'autoblogcraft'),
                'description' => __('AI and publishing options', 'autoblogcraft'),
            ],
        ];
    }

    /**
     * Render keywords step
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function render_step_keywords($campaign_id = 0) {
        $keywords = $this->get_campaign_meta('_news_keywords', []);
        
        $this->load_template('step-keywords', [
            'campaign_id' => $campaign_id,
            'keywords' => $keywords,
        ]);
    }

    /**
     * Render filters step
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function render_step_filters($campaign_id = 0) {
        $filters = $this->get_campaign_meta('_news_filters', []);
        
        $this->load_template('step-filters', [
            'campaign_id' => $campaign_id,
            'filters' => $filters,
        ]);
    }

    /**
     * Render processing settings step
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function render_step_processing($campaign_id = 0) {
        $processing_settings = $this->get_campaign_meta('_processing_settings', []);
        
        $this->load_template('step-processing', [
            'campaign_id' => $campaign_id,
            'processing_settings' => $processing_settings,
        ]);
    }
}
