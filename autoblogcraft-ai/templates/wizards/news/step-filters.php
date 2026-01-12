<?php
/**
 * News Campaign Wizard - Step 3: News Filters
 *
 * Template for configuring news discovery filters.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\News
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $filters News filters
 */

defined('ABSPATH') || exit;

$filters = $filters ?? [];
$freshness = $filters['freshness'] ?? '24h';
$country = $filters['country'] ?? 'us';
$language = $filters['language'] ?? 'en';
$interval = $filters['interval'] ?? 'hourly';
?>

<div class="abc-wizard-step abc-wizard-step-filters">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('News Filters & Discovery Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Filter news by freshness, location, language, and sources.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<h4><?php esc_html_e('Discovery Settings', 'autoblogcraft-ai'); ?></h4>

		<div class="abc-form-field">
			<label for="discovery_interval"><?php esc_html_e('Discovery Interval', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="discovery_interval" name="filters[interval]" required>
				<option value="15min" <?php selected($interval, '15min'); ?>><?php esc_html_e('Every 15 minutes', 'autoblogcraft-ai'); ?></option>
				<option value="30min" <?php selected($interval, '30min'); ?>><?php esc_html_e('Every 30 minutes', 'autoblogcraft-ai'); ?></option>
				<option value="hourly" <?php selected($interval, 'hourly'); ?>><?php esc_html_e('Every hour', 'autoblogcraft-ai'); ?></option>
				<option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>><?php esc_html_e('Twice daily', 'autoblogcraft-ai'); ?></option>
				<option value="daily" <?php selected($interval, 'daily'); ?>><?php esc_html_e('Daily', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How often to check for new articles', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="max_articles"><?php esc_html_e('Max Articles Per Discovery', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="max_articles" name="filters[max_articles]" 
				   value="10" min="1" max="100" class="small-text" />
			<p class="description"><?php esc_html_e('Maximum number of articles to fetch per discovery run', 'autoblogcraft-ai'); ?></p>
		</div>

		<hr />

		<h4><?php esc_html_e('Content Filters', 'autoblogcraft-ai'); ?></h4>

		<div class="abc-form-field">
			<label for="freshness"><?php esc_html_e('News Freshness', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="freshness" name="filters[freshness]" required>
				<option value="1h" <?php selected($freshness, '1h'); ?>><?php esc_html_e('Past Hour', 'autoblogcraft-ai'); ?></option>
				<option value="6h" <?php selected($freshness, '6h'); ?>><?php esc_html_e('Past 6 Hours', 'autoblogcraft-ai'); ?></option>
				<option value="24h" <?php selected($freshness, '24h'); ?>><?php esc_html_e('Past 24 Hours', 'autoblogcraft-ai'); ?></option>
				<option value="7d" <?php selected($freshness, '7d'); ?>><?php esc_html_e('Past Week', 'autoblogcraft-ai'); ?></option>
				<option value="30d" <?php selected($freshness, '30d'); ?>><?php esc_html_e('Past Month', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Only discover articles from this time period', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="language"><?php esc_html_e('Language', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="language" name="filters[language]" required>
				<option value="en" <?php selected($language, 'en'); ?>><?php esc_html_e('English', 'autoblogcraft-ai'); ?></option>
				<option value="ar" <?php selected($language, 'ar'); ?>><?php esc_html_e('Arabic', 'autoblogcraft-ai'); ?></option>
				<option value="de" <?php selected($language, 'de'); ?>><?php esc_html_e('German', 'autoblogcraft-ai'); ?></option>
				<option value="es" <?php selected($language, 'es'); ?>><?php esc_html_e('Spanish', 'autoblogcraft-ai'); ?></option>
				<option value="fr" <?php selected($language, 'fr'); ?>><?php esc_html_e('French', 'autoblogcraft-ai'); ?></option>
				<option value="it" <?php selected($language, 'it'); ?>><?php esc_html_e('Italian', 'autoblogcraft-ai'); ?></option>
				<option value="pt" <?php selected($language, 'pt'); ?>><?php esc_html_e('Portuguese', 'autoblogcraft-ai'); ?></option>
				<option value="ru" <?php selected($language, 'ru'); ?>><?php esc_html_e('Russian', 'autoblogcraft-ai'); ?></option>
				<option value="zh" <?php selected($language, 'zh'); ?>><?php esc_html_e('Chinese', 'autoblogcraft-ai'); ?></option>
				<option value="ja" <?php selected($language, 'ja'); ?>><?php esc_html_e('Japanese', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Language of news articles to discover', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="country"><?php esc_html_e('Country/Region', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="country" name="filters[country]" required>
				<option value="us" <?php selected($country, 'us'); ?>><?php esc_html_e('United States', 'autoblogcraft-ai'); ?></option>
				<option value="gb" <?php selected($country, 'gb'); ?>><?php esc_html_e('United Kingdom', 'autoblogcraft-ai'); ?></option>
				<option value="ca" <?php selected($country, 'ca'); ?>><?php esc_html_e('Canada', 'autoblogcraft-ai'); ?></option>
				<option value="au" <?php selected($country, 'au'); ?>><?php esc_html_e('Australia', 'autoblogcraft-ai'); ?></option>
				<option value="de" <?php selected($country, 'de'); ?>><?php esc_html_e('Germany', 'autoblogcraft-ai'); ?></option>
				<option value="fr" <?php selected($country, 'fr'); ?>><?php esc_html_e('France', 'autoblogcraft-ai'); ?></option>
				<option value="in" <?php selected($country, 'in'); ?>><?php esc_html_e('India', 'autoblogcraft-ai'); ?></option>
				<option value="jp" <?php selected($country, 'jp'); ?>><?php esc_html_e('Japan', 'autoblogcraft-ai'); ?></option>
				<option value="global" <?php selected($country, 'global'); ?>><?php esc_html_e('Global/Worldwide', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Geographic focus for news discovery', 'autoblogcraft-ai'); ?></p>
		</div>

		<hr />

		<h4><?php esc_html_e('Source Filtering', 'autoblogcraft-ai'); ?></h4>

		<div class="abc-form-field">
			<label for="source_whitelist"><?php esc_html_e('Source Whitelist (Optional)', 'autoblogcraft-ai'); ?></label>
			<textarea id="source_whitelist" name="filters[source_whitelist]" rows="4" class="large-text" 
					  placeholder="cnn.com&#10;bbc.com&#10;reuters.com&#10;apnews.com"></textarea>
			<p class="description"><?php esc_html_e('Only include news from these domains (one per line). Leave empty to allow all sources.', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="source_blacklist"><?php esc_html_e('Source Blacklist (Optional)', 'autoblogcraft-ai'); ?></label>
			<textarea id="source_blacklist" name="filters[source_blacklist]" rows="4" class="large-text" 
					  placeholder="example.com&#10;spam-site.com"></textarea>
			<p class="description"><?php esc_html_e('Exclude news from these domains (one per line)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="filters[verified_sources_only]" value="1" />
				<?php esc_html_e('Verified/trusted sources only', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Only discover from established news organizations', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="filters[skip_duplicates]" value="1" checked />
				<?php esc_html_e('Skip duplicate articles', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Automatically detect and skip duplicate or similar news stories', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
