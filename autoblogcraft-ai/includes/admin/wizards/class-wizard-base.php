<?php
/**
 * Campaign Wizard Admin Page
 *
 * Multi-step wizard for creating campaigns.
 *
 * @package AutoBlogCraft\Admin
 * @since 2.0.0
 */

namespace AutoBlogCraft\Admin\Wizards;

use AutoBlogCraft\Campaigns\Campaign_Factory;
use AutoBlogCraft\Admin\Pages\Admin_Page_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Campaign Wizard Admin Page class
 *
 * @since 2.0.0
 */
class Admin_Page_Campaign_Wizard extends Admin_Page_Base {

    /**
     * Render page
     *
     * @since 2.0.0
     */
    public function render() {
        $step = isset($_GET['step']) ? absint($_GET['step']) : 1;
        $campaign_id = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;

        // DEBUG: Log wizard render
        error_log('ABC_WIZARD_DEBUG: render() called - Step: ' . $step . ', Campaign ID: ' . $campaign_id);

        // Form submission is now handled in Admin_Menu::handle_wizard_submission()
        // via admin_init hook (before any output)

        // Validate campaign_id for steps 2, 3, 4 BEFORE any output
        if ($step > 1 && !$campaign_id) {
            error_log('ABC_WIZARD_DEBUG: No campaign_id for step ' . $step . ', redirecting to step 1');
            wp_redirect(admin_url('admin.php?page=abc-campaign-wizard&step=1'));
            exit;
        }

        $this->render_header(__('Campaign Wizard', 'autoblogcraft'));
        
        error_log('ABC_WIZARD_DEBUG: About to render form with campaign_id=' . $campaign_id);
        
        // Build form action URL with step and campaign_id
        $form_action = add_query_arg(
            array(
                'page' => 'abc-campaign-wizard',
                'step' => $step,
                'campaign_id' => $campaign_id
            ),
            admin_url('admin.php')
        );
        
        ?>
        <div class="abc-wizard-container">
            <?php $this->render_progress_bar($step); ?>
            
            <form method="post" action="<?php echo esc_url($form_action); ?>" id="abc-wizard-form" class="abc-wizard-form">
                <?php wp_nonce_field('abc_wizard', 'abc_wizard_nonce'); ?>
                <input type="hidden" name="abc_wizard_submit" value="1">
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                <?php error_log('ABC_WIZARD_DEBUG: Hidden field campaign_id value=' . $campaign_id . ', form action=' . $form_action); ?>
                
                <script type="text/javascript">
                // Ensure campaign_id hidden field syncs with URL parameter on page load
                jQuery(document).ready(function($) {
                    var urlParams = new URLSearchParams(window.location.search);
                    var campaignId = urlParams.get('campaign_id');
                    if (campaignId) {
                        $('input[name="campaign_id"]').val(campaignId);
                        console.log('ABC_WIZARD: Set campaign_id hidden field to ' + campaignId);
                    }
                });
                </script>
                
                <?php
                switch ($step) {
                    case 1:
                        $this->render_step_1($campaign_id);
                        break;
                    case 2:
                        $this->render_step_2($campaign_id);
                        break;
                    case 3:
                        $this->render_step_3($campaign_id);
                        break;
                    case 4:
                        $this->render_step_4($campaign_id);
                        break;
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render progress bar
     *
     * @since 2.0.0
     * @param int $current_step Current step.
     */
    private function render_progress_bar($current_step) {
        $steps = [
            1 => __('Basic Info', 'autoblogcraft'),
            2 => __('Source Configuration', 'autoblogcraft'),
            3 => __('AI Settings', 'autoblogcraft'),
            4 => __('Review & Create', 'autoblogcraft'),
        ];

        ?>
        <ul class="abc-wizard-steps">
            <?php foreach ($steps as $step_num => $step_label): ?>
                <li class="abc-wizard-step <?php echo $step_num == $current_step ? 'active' : ''; ?> <?php echo $step_num < $current_step ? 'completed' : ''; ?>">
                    <span class="abc-wizard-step-indicator"><?php echo esc_html($step_num); ?></span>
                    <span class="abc-wizard-step-label"><?php echo esc_html($step_label); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Render step 1: Basic Info
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function render_step_1($campaign_id) {
        $campaign_name = '';
        $campaign_type = 'rss';
        $description = '';

        if ($campaign_id) {
            $campaign = get_post($campaign_id);
            $campaign_name = $campaign->post_title;
            $campaign_type = get_post_meta($campaign_id, '_campaign_type', true);
            $description = $campaign->post_content;
        }

        ?>
        <div class="abc-wizard-step-content">
            <h2><?php esc_html_e('Basic Information', 'autoblogcraft'); ?></h2>
            <p class="description"><?php esc_html_e('Let\'s start with the basics. Choose a name and type for your campaign.', 'autoblogcraft'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="campaign_name"><?php esc_html_e('Campaign Name', 'autoblogcraft'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="campaign_name" 
                               name="campaign_name" 
                               class="regular-text" 
                               value="<?php echo esc_attr($campaign_name); ?>" 
                               required>
                        <p class="description"><?php esc_html_e('A descriptive name for this campaign.', 'autoblogcraft'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="campaign_type"><?php esc_html_e('Campaign Type', 'autoblogcraft'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select id="campaign_type" name="campaign_type" required <?php echo $campaign_id ? 'disabled' : ''; ?>>
                            <option value="website" <?php selected($campaign_type, 'website'); ?>><?php esc_html_e('Website (RSS/Scraping)', 'autoblogcraft'); ?></option>
                            <option value="youtube" <?php selected($campaign_type, 'youtube'); ?>><?php esc_html_e('YouTube Channel', 'autoblogcraft'); ?></option>
                            <option value="amazon" <?php selected($campaign_type, 'amazon'); ?>><?php esc_html_e('Amazon Products', 'autoblogcraft'); ?></option>
                            <option value="news" <?php selected($campaign_type, 'news'); ?>><?php esc_html_e('News API', 'autoblogcraft'); ?></option>
                        </select>
                        <?php if ($campaign_id): ?>
                            <input type="hidden" name="campaign_type" value="<?php echo esc_attr($campaign_type); ?>">
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e('Choose where your content will come from.', 'autoblogcraft'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="description"><?php esc_html_e('Description', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <textarea id="description" 
                                  name="description" 
                                  class="large-text" 
                                  rows="3"><?php echo esc_textarea($description); ?></textarea>
                        <p class="description"><?php esc_html_e('Optional description for this campaign.', 'autoblogcraft'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="abc-wizard-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaigns')); ?>" class="button button-secondary">
                    <?php esc_html_e('Cancel', 'autoblogcraft'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Next Step', 'autoblogcraft'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render step 2: Source Configuration
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function render_step_2($campaign_id) {
        $campaign_type = get_post_meta($campaign_id, '_campaign_type', true);
        $source_config = get_post_meta($campaign_id, '_source_config', true) ?: [];

        error_log('ABC_WIZARD_DEBUG: render_step_2() - Campaign ID: ' . $campaign_id . ', Type: ' . $campaign_type);

        ?>
        <div class="abc-wizard-step-content">
            <h2><?php esc_html_e('Source Configuration', 'autoblogcraft'); ?></h2>
            <p class="description"><?php esc_html_e('Configure where to discover content from.', 'autoblogcraft'); ?></p>

            <?php
            error_log('ABC_WIZARD_DEBUG: Rendering config for type: ' . $campaign_type);
            switch ($campaign_type) {
                case 'website':
                    error_log('ABC_WIZARD_DEBUG: Rendering website config');
                    // Website campaigns support both RSS and web scraping
                    $this->render_website_config($source_config);
                    break;
                case 'youtube':
                    error_log('ABC_WIZARD_DEBUG: Rendering youtube config');
                    $this->render_youtube_config($source_config);
                    break;
                case 'amazon':
                    error_log('ABC_WIZARD_DEBUG: Rendering amazon config');
                    $this->render_amazon_config($source_config);
                    break;
                case 'news':
                    error_log('ABC_WIZARD_DEBUG: Rendering news config');
                    $this->render_news_config($source_config);
                    break;
                default:
                    error_log('ABC_WIZARD_DEBUG: Unknown campaign type: ' . $campaign_type);
                    echo '<div class="notice notice-error"><p>' . sprintf(esc_html__('Unknown campaign type: %s', 'autoblogcraft'), esc_html($campaign_type)) . '</p></div>';
                    break;
            }
            ?>

            <div class="abc-wizard-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaign-wizard&step=1&campaign_id=' . $campaign_id)); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php esc_html_e('Previous', 'autoblogcraft'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Next Step', 'autoblogcraft'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render website configuration (RSS feeds and web scraping)
     *
     * @since 2.0.0
     * @param array $config Configuration.
     */
    private function render_website_config($config) {
        $source_type = isset($config['source_type']) ? $config['source_type'] : 'rss';
        $feed_url = isset($config['feed_url']) ? $config['feed_url'] : '';
        $start_url = isset($config['start_url']) ? $config['start_url'] : '';
        $max_items = isset($config['max_items']) ? $config['max_items'] : 10;
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="source_type"><?php esc_html_e('Source Type', 'autoblogcraft'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select id="source_type" name="source_config[source_type]" class="abc-source-type-selector">
                        <option value="rss" <?php selected($source_type, 'rss'); ?>><?php esc_html_e('RSS Feed', 'autoblogcraft'); ?></option>
                        <option value="scraping" <?php selected($source_type, 'scraping'); ?>><?php esc_html_e('Web Scraping', 'autoblogcraft'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose whether to use RSS feeds or web scraping.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr class="abc-rss-field" <?php echo $source_type !== 'rss' ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="feed_url"><?php esc_html_e('Feed URL', 'autoblogcraft'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="url" 
                           id="feed_url" 
                           name="source_config[feed_url]" 
                           class="regular-text abc-validate-field" 
                           data-validation="rss"
                           value="<?php echo esc_url($feed_url); ?>">
                    <button type="button" class="button button-secondary abc-validate-btn" data-field="feed_url">
                        <?php esc_html_e('Validate Feed', 'autoblogcraft'); ?>
                    </button>
                    <span class="abc-validation-result"></span>
                    <p class="description"><?php esc_html_e('Enter the RSS or Atom feed URL.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_items"><?php esc_html_e('Max Items Per Discovery', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="max_items" 
                           name="source_config[max_items]" 
                           class="small-text" 
                           value="<?php echo esc_attr($max_items); ?>" 
                           min="1" 
                           max="100">
                    <p class="description"><?php esc_html_e('Maximum number of items to fetch per discovery run.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <!-- Web Scraping Fields (shown when source_type is 'scraping') -->
            <tr class="abc-scraping-field" style="display:none;">
                <th scope="row">
                    <label for="start_url"><?php esc_html_e('Start URL', 'autoblogcraft'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="url" 
                           id="start_url" 
                           name="source_config[start_url]" 
                           class="regular-text abc-validate-field" 
                           data-validation="url"
                           value="<?php echo esc_url($start_url); ?>">
                    <button type="button" class="button button-secondary abc-validate-btn" data-field="start_url">
                        <?php esc_html_e('Validate URL', 'autoblogcraft'); ?>
                    </button>
                    <span class="abc-validation-result"></span>
                    <p class="description"><?php esc_html_e('The starting URL for web scraping.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr class="abc-scraping-field" style="display:none;">
                <th scope="row">
                    <label for="url_pattern"><?php esc_html_e('URL Pattern', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="url_pattern" 
                           name="source_config[url_pattern]" 
                           class="regular-text" 
                           value="<?php echo esc_attr(isset($config['url_pattern']) ? $config['url_pattern'] : ''); ?>">
                    <p class="description"><?php esc_html_e('Regex pattern to match URLs. Leave empty to crawl all links.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr class="abc-scraping-field" style="display:none;">
                <th scope="row">
                    <label for="max_depth"><?php esc_html_e('Max Crawl Depth', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="max_depth" 
                           name="source_config[max_depth]" 
                           class="small-text" 
                           value="<?php echo esc_attr(isset($config['max_depth']) ? $config['max_depth'] : 2); ?>" 
                           min="1" 
                           max="5">
                    <p class="description"><?php esc_html_e('Maximum link depth to crawl from start URL.', 'autoblogcraft'); ?></p>
                </td>
            </tr>
        </table>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#source_type').on('change', function() {
                if ($(this).val() === 'rss') {
                    $('.abc-rss-field').show();
                    $('.abc-scraping-field').hide();
                } else {
                    $('.abc-rss-field').hide();
                    $('.abc-scraping-field').show();
                }
            }).trigger('change');
        });
        </script>
        <?php
    }

    /**
     * Render Amazon configuration
     *
     * @since 2.0.0
     * @param array $config Configuration.
     */
    private function render_amazon_config($config) {
        $search_keywords = isset($config['search_keywords']) ? $config['search_keywords'] : '';
        $category = isset($config['category']) ? $config['category'] : '';
        $max_products = isset($config['max_products']) ? $config['max_products'] : 10;
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="search_keywords"><?php esc_html_e('Search Keywords', 'autoblogcraft'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" 
                           id="search_keywords" 
                           name="source_config[search_keywords]" 
                           class="regular-text" 
                           value="<?php echo esc_attr($search_keywords); ?>" 
                           required>
                    <p class="description"><?php esc_html_e('Keywords to search for Amazon products.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="category"><?php esc_html_e('Category', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="category" 
                           name="source_config[category]" 
                           class="regular-text" 
                           value="<?php echo esc_attr($category); ?>">
                    <p class="description"><?php esc_html_e('Optional: Specific Amazon category to search in.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_products"><?php esc_html_e('Max Products Per Discovery', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="max_products" 
                           name="source_config[max_products]" 
                           class="small-text" 
                           value="<?php echo esc_attr($max_products); ?>" 
                           min="1" 
                           max="50">
                    <p class="description"><?php esc_html_e('Maximum number of products to fetch per discovery run.', 'autoblogcraft'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render YouTube configuration
     *
     * @since 2.0.0
     * @param array $config Configuration.
     */
    private function render_youtube_config($config) {
        $channel_id = isset($config['channel_id']) ? $config['channel_id'] : '';
        $max_videos = isset($config['max_videos']) ? $config['max_videos'] : 10;
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="channel_id"><?php esc_html_e('Channel ID', 'autoblogcraft'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" 
                           id="channel_id" 
                           name="source_config[channel_id]" 
                           class="regular-text" 
                           value="<?php echo esc_attr($channel_id); ?>" 
                           required>
                    <p class="description"><?php esc_html_e('YouTube channel ID (starts with UC).', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_videos"><?php esc_html_e('Max Videos Per Discovery', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="max_videos" 
                           name="source_config[max_videos]" 
                           class="small-text" 
                           value="<?php echo esc_attr($max_videos); ?>" 
                           min="1" 
                           max="50">
                    <p class="description"><?php esc_html_e('Maximum number of videos to fetch per discovery run.', 'autoblogcraft'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render News API configuration
     *
     * @since 2.0.0
     * @param array $config Configuration.
     */
    private function render_news_config($config) {
        $query = isset($config['query']) ? $config['query'] : '';
        $language = isset($config['language']) ? $config['language'] : 'en';
        $max_articles = isset($config['max_articles']) ? $config['max_articles'] : 10;
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="query"><?php esc_html_e('Search Query', 'autoblogcraft'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" 
                           id="query" 
                           name="source_config[query]" 
                           class="regular-text" 
                           value="<?php echo esc_attr($query); ?>" 
                           required>
                    <p class="description"><?php esc_html_e('Keywords or topics to search for.', 'autoblogcraft'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="language"><?php esc_html_e('Language', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <select id="language" name="source_config[language]">
                        <option value="en" <?php selected($language, 'en'); ?>>English</option>
                        <option value="ar" <?php selected($language, 'ar'); ?>>Arabic</option>
                        <option value="de" <?php selected($language, 'de'); ?>>German</option>
                        <option value="es" <?php selected($language, 'es'); ?>>Spanish</option>
                        <option value="fr" <?php selected($language, 'fr'); ?>>French</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_articles"><?php esc_html_e('Max Articles Per Discovery', 'autoblogcraft'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="max_articles" 
                           name="source_config[max_articles]" 
                           class="small-text" 
                           value="<?php echo esc_attr($max_articles); ?>" 
                           min="1" 
                           max="100">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render step 3: AI Settings
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function render_step_3($campaign_id) {
        $ai_config = get_post_meta($campaign_id, '_ai_config', true) ?: [];
        $rewrite_mode = isset($ai_config['rewrite_mode']) ? $ai_config['rewrite_mode'] : 'moderate';
        $tone = isset($ai_config['tone']) ? $ai_config['tone'] : 'professional';
        $min_length = isset($ai_config['min_length']) ? $ai_config['min_length'] : 500;
        $max_length = isset($ai_config['max_length']) ? $ai_config['max_length'] : 2000;
        
        ?>
        <div class="abc-wizard-step-content">
            <h2><?php esc_html_e('AI Settings', 'autoblogcraft'); ?></h2>
            <p class="description"><?php esc_html_e('Configure how AI will rewrite and enhance your content.', 'autoblogcraft'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rewrite_mode"><?php esc_html_e('Rewrite Mode', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <select id="rewrite_mode" name="ai_config[rewrite_mode]">
                            <option value="light" <?php selected($rewrite_mode, 'light'); ?>><?php esc_html_e('Light (Minor changes)', 'autoblogcraft'); ?></option>
                            <option value="moderate" <?php selected($rewrite_mode, 'moderate'); ?>><?php esc_html_e('Moderate (Balanced)', 'autoblogcraft'); ?></option>
                            <option value="heavy" <?php selected($rewrite_mode, 'heavy'); ?>><?php esc_html_e('Heavy (Complete rewrite)', 'autoblogcraft'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('How much should AI transform the original content?', 'autoblogcraft'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tone"><?php esc_html_e('Writing Tone', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <select id="tone" name="ai_config[tone]">
                            <option value="professional" <?php selected($tone, 'professional'); ?>><?php esc_html_e('Professional', 'autoblogcraft'); ?></option>
                            <option value="casual" <?php selected($tone, 'casual'); ?>><?php esc_html_e('Casual', 'autoblogcraft'); ?></option>
                            <option value="informative" <?php selected($tone, 'informative'); ?>><?php esc_html_e('Informative', 'autoblogcraft'); ?></option>
                            <option value="persuasive" <?php selected($tone, 'persuasive'); ?>><?php esc_html_e('Persuasive', 'autoblogcraft'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="min_length"><?php esc_html_e('Content Length', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="min_length" 
                               name="ai_config[min_length]" 
                               class="small-text" 
                               value="<?php echo esc_attr($min_length); ?>" 
                               min="100">
                        <span>to</span>
                        <input type="number" 
                               id="max_length" 
                               name="ai_config[max_length]" 
                               class="small-text" 
                               value="<?php echo esc_attr($max_length); ?>" 
                               min="100">
                        <span>words</span>
                        <p class="description"><?php esc_html_e('Target word count range for generated articles.', 'autoblogcraft'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label>
                            <input type="checkbox" name="ai_config[generate_images]" value="1" <?php checked(isset($ai_config['generate_images']) && $ai_config['generate_images']); ?>>
                            <?php esc_html_e('Generate Featured Images', 'autoblogcraft'); ?>
                        </label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e('Use AI to generate unique featured images for posts.', 'autoblogcraft'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label>
                            <input type="checkbox" name="ai_config[add_seo]" value="1" <?php checked(isset($ai_config['add_seo']) && $ai_config['add_seo']); ?>>
                            <?php esc_html_e('Add SEO Optimization', 'autoblogcraft'); ?>
                        </label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e('Generate meta descriptions and optimize for search engines.', 'autoblogcraft'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="abc-wizard-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaign-wizard&step=2&campaign_id=' . $campaign_id)); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php esc_html_e('Previous', 'autoblogcraft'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Next Step', 'autoblogcraft'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render step 4: Review & Create
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function render_step_4($campaign_id) {
        $campaign = get_post($campaign_id);
        $campaign_type = get_post_meta($campaign_id, '_campaign_type', true);
        $source_config = get_post_meta($campaign_id, '_source_config', true);
        $ai_config = get_post_meta($campaign_id, '_ai_config', true);
        $discovery_interval = get_post_meta($campaign_id, '_discovery_interval', true) ?: 'hourly';
        
        ?>
        <div class="abc-wizard-step-content">
            <h2><?php esc_html_e('Review & Create', 'autoblogcraft'); ?></h2>
            <p class="description"><?php esc_html_e('Review your campaign settings before creating.', 'autoblogcraft'); ?></p>

            <div class="abc-review-section">
                <h3><?php esc_html_e('Basic Information', 'autoblogcraft'); ?></h3>
                <table class="widefat">
                    <tr>
                        <th><?php esc_html_e('Name:', 'autoblogcraft'); ?></th>
                        <td><?php echo esc_html($campaign->post_title); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Type:', 'autoblogcraft'); ?></th>
                        <td><?php echo esc_html(ucfirst($campaign_type)); ?></td>
                    </tr>
                    <?php if ($campaign->post_content): ?>
                    <tr>
                        <th><?php esc_html_e('Description:', 'autoblogcraft'); ?></th>
                        <td><?php echo esc_html($campaign->post_content); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="abc-review-section">
                <h3><?php esc_html_e('Source Configuration', 'autoblogcraft'); ?></h3>
                <table class="widefat">
                    <?php foreach ($source_config as $key => $value): ?>
                    <tr>
                        <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</th>
                        <td><?php echo is_array($value) ? esc_html(implode(', ', $value)) : esc_html($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="abc-review-section">
                <h3><?php esc_html_e('AI Settings', 'autoblogcraft'); ?></h3>
                <table class="widefat">
                    <tr>
                        <th><?php esc_html_e('Rewrite Mode:', 'autoblogcraft'); ?></th>
                        <td><?php echo esc_html(ucfirst($ai_config['rewrite_mode'] ?? 'moderate')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Tone:', 'autoblogcraft'); ?></th>
                        <td><?php echo esc_html(ucfirst($ai_config['tone'] ?? 'professional')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Content Length:', 'autoblogcraft'); ?></th>
                        <td><?php echo esc_html(($ai_config['min_length'] ?? 500) . ' - ' . ($ai_config['max_length'] ?? 2000) . ' words'); ?></td>
                    </tr>
                </table>
            </div>

            <div class="abc-review-section">
                <h3><?php esc_html_e('Additional Settings', 'autoblogcraft'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="discovery_interval"><?php esc_html_e('Discovery Interval', 'autoblogcraft'); ?></label>
                        </th>
                        <td>
                            <select id="discovery_interval" name="discovery_interval">
                                <option value="15min" <?php selected($discovery_interval, '15min'); ?>><?php esc_html_e('Every 15 minutes', 'autoblogcraft'); ?></option>
                                <option value="30min" <?php selected($discovery_interval, '30min'); ?>><?php esc_html_e('Every 30 minutes', 'autoblogcraft'); ?></option>
                                <option value="hourly" <?php selected($discovery_interval, 'hourly'); ?>><?php esc_html_e('Every hour', 'autoblogcraft'); ?></option>
                                <option value="twicedaily" <?php selected($discovery_interval, 'twicedaily'); ?>><?php esc_html_e('Twice daily', 'autoblogcraft'); ?></option>
                                <option value="daily" <?php selected($discovery_interval, 'daily'); ?>><?php esc_html_e('Daily', 'autoblogcraft'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Campaign Status', 'autoblogcraft'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="radio" name="status" value="active" checked>
                                <?php esc_html_e('Active (Start immediately)', 'autoblogcraft'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="status" value="paused">
                                <?php esc_html_e('Paused (Create but don\'t start)', 'autoblogcraft'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="abc-wizard-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaign-wizard&step=3&campaign_id=' . $campaign_id)); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php esc_html_e('Previous', 'autoblogcraft'); ?>
                </a>
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Create Campaign', 'autoblogcraft'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Handle step submission
     *
     * @since 2.0.0
     * @param int $step Current step.
     * @param int $campaign_id Campaign ID.
     */
    private function handle_step_submission($step, $campaign_id) {
        error_log('ABC_WIZARD_DEBUG: handle_step_submission() - Step: ' . $step . ', Campaign ID: ' . $campaign_id);
        
        if (!isset($_POST['abc_wizard_nonce']) || !wp_verify_nonce($_POST['abc_wizard_nonce'], 'abc_wizard')) {
            error_log('ABC_WIZARD_DEBUG: Nonce verification failed');
            wp_die(__('Security check failed.', 'autoblogcraft'));
        }

        if (!current_user_can('manage_options')) {
            error_log('ABC_WIZARD_DEBUG: Permission denied');
            wp_die(__('Permission denied.', 'autoblogcraft'));
        }

        switch ($step) {
            case 1:
                error_log('ABC_WIZARD_DEBUG: Saving step 1 data');
                $campaign_id = $this->save_step_1($campaign_id);
                error_log('ABC_WIZARD_DEBUG: Step 1 saved, campaign_id: ' . $campaign_id . ', redirecting to step 2');
                wp_redirect(admin_url('admin.php?page=abc-campaign-wizard&step=2&campaign_id=' . $campaign_id));
                exit;

            case 2:
                error_log('ABC_WIZARD_DEBUG: Saving step 2 data');
                $this->save_step_2($campaign_id);
                error_log('ABC_WIZARD_DEBUG: Step 2 saved, redirecting to step 3');
                wp_redirect(admin_url('admin.php?page=abc-campaign-wizard&step=3&campaign_id=' . $campaign_id));
                exit;

            case 3:
                error_log('ABC_WIZARD_DEBUG: Saving step 3 data');
                $this->save_step_3($campaign_id);
                error_log('ABC_WIZARD_DEBUG: Step 3 saved, redirecting to step 4');
                wp_redirect(admin_url('admin.php?page=abc-campaign-wizard&step=4&campaign_id=' . $campaign_id));
                exit;

            case 4:
                error_log('ABC_WIZARD_DEBUG: Saving step 4 data and activating campaign');
                $this->save_step_4($campaign_id);
                error_log('ABC_WIZARD_DEBUG: Campaign activated, redirecting to campaigns page');
                wp_redirect(admin_url('admin.php?page=abc-campaigns&created=1'));
                exit;
        }
    }

    /**
     * Save step 1 data
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     * @return int Campaign ID.
     */
    private function save_step_1($campaign_id) {
        $campaign_name = isset($_POST['campaign_name']) ? sanitize_text_field($_POST['campaign_name']) : '';
        $campaign_type = isset($_POST['campaign_type']) ? sanitize_text_field($_POST['campaign_type']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

        error_log('ABC_WIZARD_DEBUG: save_step_1() - Name: ' . $campaign_name . ', Type: ' . $campaign_type);

        if (!$campaign_id) {
            error_log('ABC_WIZARD_DEBUG: Creating new campaign post');
            $campaign_id = wp_insert_post([
                'post_title' => $campaign_name,
                'post_content' => $description,
                'post_type' => 'abc_campaign',
                'post_status' => 'draft',
            ]);

            if (!$campaign_id) {
                error_log('ABC_WIZARD_DEBUG: Failed to create campaign post');
                wp_die(__('Failed to create campaign.', 'autoblogcraft'));
            }

            error_log('ABC_WIZARD_DEBUG: Campaign post created with ID: ' . $campaign_id);
            update_post_meta($campaign_id, '_campaign_type', $campaign_type);
            update_post_meta($campaign_id, '_campaign_status', 'draft');
            error_log('ABC_WIZARD_DEBUG: Campaign meta saved - Type: ' . $campaign_type);
        } else {
            error_log('ABC_WIZARD_DEBUG: Updating existing campaign: ' . $campaign_id);
            wp_update_post([
                'ID' => $campaign_id,
                'post_title' => $campaign_name,
                'post_content' => $description,
            ]);
        }

        return $campaign_id;
    }

    /**
     * Save step 2 data
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function save_step_2($campaign_id) {
        $source_config = isset($_POST['source_config']) ? $_POST['source_config'] : [];
        
        // Sanitize source config
        $sanitized_config = [];
        foreach ($source_config as $key => $value) {
            $key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized_config[$key] = array_map('sanitize_text_field', $value);
            } else {
                // URLs should use esc_url_raw
                if (in_array($key, ['feed_url', 'start_url'])) {
                    $sanitized_config[$key] = esc_url_raw($value);
                } else {
                    $sanitized_config[$key] = sanitize_text_field($value);
                }
            }
        }

        update_post_meta($campaign_id, '_source_config', $sanitized_config);
    }

    /**
     * Save step 3 data
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function save_step_3($campaign_id) {
        $ai_config = isset($_POST['ai_config']) ? $_POST['ai_config'] : [];
        
        // Sanitize AI config
        $sanitized_config = [];
        foreach ($ai_config as $key => $value) {
            $key = sanitize_key($key);
            if (is_numeric($value)) {
                $sanitized_config[$key] = absint($value);
            } else {
                $sanitized_config[$key] = sanitize_text_field($value);
            }
        }

        update_post_meta($campaign_id, '_ai_config', $sanitized_config);
    }

    /**
     * Save step 4 data and activate campaign
     *
     * @since 2.0.0
     * @param int $campaign_id Campaign ID.
     */
    private function save_step_4($campaign_id) {
        $discovery_interval = isset($_POST['discovery_interval']) ? sanitize_text_field($_POST['discovery_interval']) : 'hourly';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';

        update_post_meta($campaign_id, '_discovery_interval', $discovery_interval);
        update_post_meta($campaign_id, '_campaign_status', $status);

        // Publish the campaign
        wp_update_post([
            'ID' => $campaign_id,
            'post_status' => 'publish',
        ]);
    }
}
