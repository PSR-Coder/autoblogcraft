<?php
/**
 * Amazon Campaign Wizard - Step 3: Discovery Settings
 *
 * Template for configuring Amazon product discovery settings.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Amazon
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $discovery_settings Discovery settings
 */

defined('ABSPATH') || exit;

$discovery_settings = $discovery_settings ?? [];
$interval = $discovery_settings['interval'] ?? 'daily';
$max_products = $discovery_settings['max_products'] ?? 10;
$min_price = $discovery_settings['min_price'] ?? '';
$max_price = $discovery_settings['max_price'] ?? '';
$min_rating = $discovery_settings['min_rating'] ?? 3.5;
?>

<div class="abc-wizard-step abc-wizard-step-discovery">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Product Discovery Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how to discover and filter Amazon products.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<div class="abc-form-field">
			<label for="discovery_interval"><?php esc_html_e('Discovery Interval', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="discovery_interval" name="discovery_settings[interval]" required>
				<option value="hourly" <?php selected($interval, 'hourly'); ?>><?php esc_html_e('Every hour', 'autoblogcraft-ai'); ?></option>
				<option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>><?php esc_html_e('Twice daily', 'autoblogcraft-ai'); ?></option>
				<option value="daily" <?php selected($interval, 'daily'); ?>><?php esc_html_e('Daily', 'autoblogcraft-ai'); ?></option>
				<option value="weekly" <?php selected($interval, 'weekly'); ?>><?php esc_html_e('Weekly', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How often to check for new products', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="max_products"><?php esc_html_e('Max Products Per Discovery', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="max_products" name="discovery_settings[max_products]" 
				   value="<?php echo esc_attr($max_products); ?>" min="1" max="100" class="small-text" />
			<p class="description"><?php esc_html_e('Maximum number of products to fetch per discovery run', 'autoblogcraft-ai'); ?></p>
		</div>

		<h4><?php esc_html_e('Product Filters', 'autoblogcraft-ai'); ?></h4>

		<div class="abc-form-field">
			<label><?php esc_html_e('Price Range', 'autoblogcraft-ai'); ?></label>
			<input type="number" name="discovery_settings[min_price]" 
				   value="<?php echo esc_attr($min_price); ?>" min="0" step="0.01" class="small-text" placeholder="Min" />
			<span><?php esc_html_e('to', 'autoblogcraft-ai'); ?></span>
			<input type="number" name="discovery_settings[max_price]" 
				   value="<?php echo esc_attr($max_price); ?>" min="0" step="0.01" class="small-text" placeholder="Max" />
			<p class="description"><?php esc_html_e('Only discover products within this price range (leave empty for no limit)', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="min_rating"><?php esc_html_e('Minimum Star Rating', 'autoblogcraft-ai'); ?></label>
			<select id="min_rating" name="discovery_settings[min_rating]">
				<option value="0" <?php selected($min_rating, 0); ?>><?php esc_html_e('Any rating', 'autoblogcraft-ai'); ?></option>
				<option value="3" <?php selected($min_rating, 3); ?>><?php esc_html_e('3+ stars', 'autoblogcraft-ai'); ?></option>
				<option value="3.5" <?php selected($min_rating, 3.5); ?>><?php esc_html_e('3.5+ stars', 'autoblogcraft-ai'); ?></option>
				<option value="4" <?php selected($min_rating, 4); ?>><?php esc_html_e('4+ stars', 'autoblogcraft-ai'); ?></option>
				<option value="4.5" <?php selected($min_rating, 4.5); ?>><?php esc_html_e('4.5+ stars', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Only discover products with at least this rating', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="min_reviews"><?php esc_html_e('Minimum Reviews', 'autoblogcraft-ai'); ?></label>
			<input type="number" id="min_reviews" name="discovery_settings[min_reviews]" 
				   value="10" min="0" class="small-text" />
			<p class="description"><?php esc_html_e('Only discover products with at least this many reviews', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[prime_only]" value="1" />
				<?php esc_html_e('Amazon Prime eligible only', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Only discover products eligible for Amazon Prime', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[in_stock_only]" value="1" checked />
				<?php esc_html_e('In stock only', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Skip out-of-stock products', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label>
				<input type="checkbox" name="discovery_settings[skip_duplicates]" value="1" checked />
				<?php esc_html_e('Skip duplicate products', 'autoblogcraft-ai'); ?>
			</label>
			<p class="description"><?php esc_html_e('Automatically detect and skip products already published', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-form-field">
			<label for="brand_filter"><?php esc_html_e('Brand Filter (Optional)', 'autoblogcraft-ai'); ?></label>
			<textarea id="brand_filter" name="discovery_settings[brand_filter]" rows="3" class="large-text" 
					  placeholder="Apple&#10;Samsung&#10;Sony"></textarea>
			<p class="description"><?php esc_html_e('Only include products from these brands (one per line, leave empty for all brands)', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
