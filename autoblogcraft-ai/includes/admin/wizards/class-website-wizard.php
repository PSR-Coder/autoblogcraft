<?php
/**
 * Website Campaign Wizard
 *
 * Specialized wizard for creating website/blog campaigns.
 * Handles RSS feeds, sitemap discovery, and web scraping configuration.
 *
 * @package AutoBlogCraft\Admin\Wizards
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Wizards;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Website Wizard class
 *
 * @since 2.0.0
 */
class Website_Wizard extends Wizard_Base {

    /**
     * Get campaign type
     *
     * @since 2.0.0
     * @return string
     */
    protected function get_campaign_type() {
        return 'website';
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
                'id' => 'sources',
                'title' => __('Website Sources', 'autoblogcraft'),
                'description' => __('RSS feeds, sitemaps, or URLs', 'autoblogcraft'),
            ],
            [
                'id' => 'discovery',
                'title' => __('Discovery Settings', 'autoblogcraft'),
                'description' => __('How to discover content', 'autoblogcraft'),
            ],
            [
                'id' => 'processing',
                'title' => __('Processing Settings', 'autoblogcraft'),
                'description' => __('AI and publishing options', 'autoblogcraft'),
            ],
        ];
    }

    /**
     * Render sources step
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function render_step_sources($campaign_id = 0) {
        $sources = $this->get_campaign_meta('_sources', []);
        
        $this->load_template('step-sources', [
            'campaign_id' => $campaign_id,
            'sources' => $sources,
        ]);
    }

    /**
     * Render discovery settings step
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function render_step_discovery($campaign_id = 0) {
        $discovery_settings = $this->get_campaign_meta('_discovery_settings', []);
        
        $this->load_template('step-discovery', [
            'campaign_id' => $campaign_id,
            'discovery_settings' => $discovery_settings,
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
