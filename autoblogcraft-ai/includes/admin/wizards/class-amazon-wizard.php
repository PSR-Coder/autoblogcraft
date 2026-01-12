<?php
/**
 * Amazon Campaign Wizard
 *
 * Specialized wizard for creating Amazon product campaigns.
 * Handles product search, categories, and bestseller configuration.
 *
 * @package AutoBlogCraft\Admin\Wizards
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Wizards;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon Wizard class
 *
 * @since 2.0.0
 */
class Amazon_Wizard extends Wizard_Base {

    /**
     * Get campaign type
     *
     * @since 2.0.0
     * @return string
     */
    protected function get_campaign_type() {
        return 'amazon';
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
                'id' => 'products',
                'title' => __('Product Sources', 'autoblogcraft'),
                'description' => __('Keywords and categories', 'autoblogcraft'),
            ],
            [
                'id' => 'discovery',
                'title' => __('Discovery Settings', 'autoblogcraft'),
                'description' => __('Product discovery options', 'autoblogcraft'),
            ],
            [
                'id' => 'processing',
                'title' => __('Processing Settings', 'autoblogcraft'),
                'description' => __('AI and publishing options', 'autoblogcraft'),
            ],
        ];
    }

    /**
     * Render products step
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function render_step_products($campaign_id = 0) {
        $keywords = $this->get_campaign_meta('_amazon_keywords', []);
        $categories = $this->get_campaign_meta('_amazon_categories', []);
        
        $this->load_template('step-products', [
            'campaign_id' => $campaign_id,
            'keywords' => $keywords,
            'categories' => $categories,
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
