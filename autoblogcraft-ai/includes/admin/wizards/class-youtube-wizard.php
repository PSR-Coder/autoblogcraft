<?php
/**
 * YouTube Campaign Wizard
 *
 * Specialized wizard for creating YouTube video campaigns.
 * Handles channel and playlist configuration.
 *
 * @package AutoBlogCraft\Admin\Wizards
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Wizards;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * YouTube Wizard class
 *
 * @since 2.0.0
 */
class YouTube_Wizard extends Wizard_Base {

    /**
     * Get campaign type
     *
     * @since 2.0.0
     * @return string
     */
    protected function get_campaign_type() {
        return 'youtube';
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
                'id' => 'channels',
                'title' => __('YouTube Sources', 'autoblogcraft'),
                'description' => __('Channels and playlists', 'autoblogcraft'),
            ],
            [
                'id' => 'discovery',
                'title' => __('Discovery Settings', 'autoblogcraft'),
                'description' => __('Video discovery options', 'autoblogcraft'),
            ],
            [
                'id' => 'processing',
                'title' => __('Processing Settings', 'autoblogcraft'),
                'description' => __('AI and publishing options', 'autoblogcraft'),
            ],
        ];
    }

    /**
     * Render channels step
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return void
     */
    public function render_step_channels($campaign_id = 0) {
        $channels = $this->get_campaign_meta('_youtube_channels', []);
        $playlists = $this->get_campaign_meta('_youtube_playlists', []);
        
        $this->load_template('step-channels', [
            'campaign_id' => $campaign_id,
            'channels' => $channels,
            'playlists' => $playlists,
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
