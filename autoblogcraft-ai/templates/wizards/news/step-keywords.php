<?php
/**
 * News Campaign Wizard - Step 2: News Keywords
 *
 * Template for configuring news search keywords and providers.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\News
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $keywords News keywords
 */

defined('ABSPATH') || exit;

$keywords = $keywords ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-keywords">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('News Keywords & Provider', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure what news topics to discover and which service to use.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<div class="abc-field-group">
			<div class="abc-field-group-header">
				<h4 class="abc-field-group-title"><?php esc_html_e('News Keywords', 'autoblogcraft-ai'); ?></h4>
				<p class="description"><?php esc_html_e('Topics, search terms, or keywords to discover news about', 'autoblogcraft-ai'); ?></p>
			</div>
			
			<div class="abc-field-list">
				<?php
				if (!empty($keywords) && is_array($keywords)) {
					foreach ($keywords as $keyword) {
						?>
						<div class="abc-field-item">
							<input type="text" name="keywords[]" value="<?php echo esc_attr($keyword); ?>" 
								   placeholder="e.g., technology trends, AI news" class="regular-text" />
							<button type="button" class="button abc-remove-field-btn" title="<?php esc_attr_e('Remove', 'autoblogcraft-ai'); ?>">
								<span class="dashicons dashicons-no"></span> <?php esc_html_e('Remove', 'autoblogcraft-ai'); ?>
							</button>
						</div>
						<?php
					}
				} else {
					?>
					<div class="abc-field-item">
						<input type="text" name="keywords[]" placeholder="e.g., technology trends, AI news" class="regular-text" />
						<button type="button" class="button abc-remove-field-btn" title="<?php esc_attr_e('Remove', 'autoblogcraft-ai'); ?>">
							<span class="dashicons dashicons-no"></span> <?php esc_html_e('Remove', 'autoblogcraft-ai'); ?>
						</button>
					</div>
					<?php
				}
				?>
			</div>

			<button type="button" class="button abc-add-field-btn" 
					data-field-name="keywords" 
					data-field-type="text" 
					data-placeholder="e.g., technology trends, AI news">
				<span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Keyword', 'autoblogcraft-ai'); ?>
			</button>
		</div>

		<div class="abc-form-field">
			<label for="news_provider"><?php esc_html_e('News Provider', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="news_provider" name="news_provider" required>
				<option value="google_news"><?php esc_html_e('Google News (Free)', 'autoblogcraft-ai'); ?></option>
				<option value="newsapi"><?php esc_html_e('NewsAPI.org', 'autoblogcraft-ai'); ?></option>
				<option value="serpapi"><?php esc_html_e('SerpAPI', 'autoblogcraft-ai'); ?></option>
				<option value="bing_news"><?php esc_html_e('Bing News API', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('News discovery service to use', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field abc-dependent-field" data-depends="news_provider" data-depends-not="google_news">
			<label for="news_api_key"><?php esc_html_e('API Key', 'autoblogcraft-ai'); ?></label>
			<input type="text" id="news_api_key" name="news_api_key" value="" class="regular-text" />
			<p class="description">
				<?php esc_html_e('Required for NewsAPI, SerpAPI, and Bing News', 'autoblogcraft-ai'); ?>
				<br>
				<a href="https://newsapi.org/" target="_blank"><?php esc_html_e('Get NewsAPI key', 'autoblogcraft-ai'); ?></a> | 
				<a href="https://serpapi.com/" target="_blank"><?php esc_html_e('Get SerpAPI key', 'autoblogcraft-ai'); ?></a> | 
				<a href="https://www.microsoft.com/en-us/bing/apis/bing-news-search-api" target="_blank"><?php esc_html_e('Get Bing key', 'autoblogcraft-ai'); ?></a>
			</p>
		</div>
	</div>
</div>
