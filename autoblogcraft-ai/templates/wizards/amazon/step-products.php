<?php
/**
 * Amazon Campaign Wizard - Step 2: Product Sources
 *
 * Template for configuring Amazon product discovery sources.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Amazon
 * @since 2.0.0
 *
 * @var int $campaign_id Campaign ID
 * @var array $keywords Search keywords
 * @var array $categories Amazon categories
 */

defined('ABSPATH') || exit;

$keywords = $keywords ?? [];
$categories = $categories ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-products">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Product Sources', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure how to discover Amazon products.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-step-content">
		<div class="abc-form-field">
			<label for="discovery_method"><?php esc_html_e('Discovery Method', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="discovery_method" name="discovery_method" required>
				<option value="search"><?php esc_html_e('Keyword Search', 'autoblogcraft-ai'); ?></option>
				<option value="category"><?php esc_html_e('Browse Categories', 'autoblogcraft-ai'); ?></option>
				<option value="bestsellers"><?php esc_html_e('Bestsellers', 'autoblogcraft-ai'); ?></option>
				<option value="new_releases"><?php esc_html_e('New Releases', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('How to discover products on Amazon', 'autoblogcraft-ai'); ?></p>
		</div>

		<div class="abc-field-group" data-show-when="discovery_method" data-show-value="search,bestsellers,new_releases">
			<div class="abc-field-group-header">
				<h4 class="abc-field-group-title"><?php esc_html_e('Search Keywords', 'autoblogcraft-ai'); ?></h4>
				<p class="description"><?php esc_html_e('Product keywords or search terms', 'autoblogcraft-ai'); ?></p>
			</div>
			
			<div class="abc-field-list">
				<?php
				if (!empty($keywords) && is_array($keywords)) {
					foreach ($keywords as $keyword) {
						?>
						<div class="abc-field-item">
							<input type="text" name="keywords[]" value="<?php echo esc_attr($keyword); ?>" 
								   placeholder="e.g., wireless headphones" class="regular-text" />
							<button type="button" class="button abc-remove-field-btn" title="<?php esc_attr_e('Remove', 'autoblogcraft-ai'); ?>">
								<span class="dashicons dashicons-no"></span> <?php esc_html_e('Remove', 'autoblogcraft-ai'); ?>
							</button>
						</div>
						<?php
					}
				} else {
					?>
					<div class="abc-field-item">
						<input type="text" name="keywords[]" placeholder="e.g., wireless headphones" class="regular-text" />
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
					data-placeholder="e.g., wireless headphones">
				<span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Keyword', 'autoblogcraft-ai'); ?>
			</button>
		</div>

		<div class="abc-field-group" data-show-when="discovery_method" data-show-value="category,bestsellers">
			<div class="abc-field-group-header">
				<h4 class="abc-field-group-title"><?php esc_html_e('Amazon Categories', 'autoblogcraft-ai'); ?></h4>
				<p class="description"><?php esc_html_e('Browse specific Amazon category pages', 'autoblogcraft-ai'); ?></p>
			</div>
			
			<div class="abc-field-list">
				<?php
				if (!empty($categories) && is_array($categories)) {
					foreach ($categories as $category) {
						?>
						<div class="abc-field-item">
							<input type="url" name="categories[]" value="<?php echo esc_url($category); ?>" 
								   placeholder="https://www.amazon.com/Best-Sellers-Electronics/..." class="regular-text" />
							<button type="button" class="button abc-remove-field-btn" title="<?php esc_attr_e('Remove', 'autoblogcraft-ai'); ?>">
								<span class="dashicons dashicons-no"></span> <?php esc_html_e('Remove', 'autoblogcraft-ai'); ?>
							</button>
						</div>
						<?php
					}
				} else {
					?>
					<div class="abc-field-item">
						<input type="url" name="categories[]" placeholder="https://www.amazon.com/Best-Sellers-Electronics/..." class="regular-text" />
						<button type="button" class="button abc-remove-field-btn" title="<?php esc_attr_e('Remove', 'autoblogcraft-ai'); ?>">
							<span class="dashicons dashicons-no"></span> <?php esc_html_e('Remove', 'autoblogcraft-ai'); ?>
						</button>
					</div>
					<?php
				}
				?>
			</div>

			<button type="button" class="button abc-add-field-btn" 
					data-field-name="categories" 
					data-field-type="url" 
					data-placeholder="https://www.amazon.com/Best-Sellers-Electronics/...">
				<span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add Category URL', 'autoblogcraft-ai'); ?>
			</button>
		</div>

		<div class="abc-form-field">
			<label for="affiliate_tag"><?php esc_html_e('Amazon Affiliate Tag', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<input type="text" id="affiliate_tag" name="affiliate_tag" value="" required class="regular-text" placeholder="yourtag-20" />
			<p class="description">
				<?php
				printf(
					/* translators: %s: Amazon Associates URL */
					esc_html__('Your Amazon Associates tracking ID. %s', 'autoblogcraft-ai'),
					'<a href="https://affiliate-program.amazon.com/" target="_blank">' . esc_html__('Sign up here', 'autoblogcraft-ai') . '</a>'
				);
				?>
			</p>
		</div>

		<div class="abc-form-field">
			<label for="amazon_region"><?php esc_html_e('Amazon Region', 'autoblogcraft-ai'); ?> <span class="required">*</span></label>
			<select id="amazon_region" name="amazon_region" required>
				<option value="com"><?php esc_html_e('United States (.com)', 'autoblogcraft-ai'); ?></option>
				<option value="co.uk"><?php esc_html_e('United Kingdom (.co.uk)', 'autoblogcraft-ai'); ?></option>
				<option value="de"><?php esc_html_e('Germany (.de)', 'autoblogcraft-ai'); ?></option>
				<option value="fr"><?php esc_html_e('France (.fr)', 'autoblogcraft-ai'); ?></option>
				<option value="jp"><?php esc_html_e('Japan (.jp)', 'autoblogcraft-ai'); ?></option>
				<option value="ca"><?php esc_html_e('Canada (.ca)', 'autoblogcraft-ai'); ?></option>
				<option value="it"><?php esc_html_e('Italy (.it)', 'autoblogcraft-ai'); ?></option>
				<option value="es"><?php esc_html_e('Spain (.es)', 'autoblogcraft-ai'); ?></option>
				<option value="in"><?php esc_html_e('India (.in)', 'autoblogcraft-ai'); ?></option>
				<option value="com.br"><?php esc_html_e('Brazil (.com.br)', 'autoblogcraft-ai'); ?></option>
			</select>
			<p class="description"><?php esc_html_e('Amazon marketplace to discover products from', 'autoblogcraft-ai'); ?></p>
		</div>
	</div>
</div>
