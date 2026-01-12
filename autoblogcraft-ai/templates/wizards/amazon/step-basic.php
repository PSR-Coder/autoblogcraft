<?php
/**
 * Amazon Campaign Wizard - Step 2: Basic Settings
 *
 * Configure basic settings for Amazon campaign.
 *
 * @package AutoBlogCraft_AI
 * @subpackage Templates\Wizards\Amazon
 * @since 2.0.0
 *
 * @var array $wizard_data Wizard form data
 */

defined('ABSPATH') || exit;

$wizard_data = $wizard_data ?? [];
?>

<div class="abc-wizard-step abc-wizard-step-amazon-basic">
	<div class="abc-wizard-step-header">
		<h2><?php esc_html_e('Amazon Campaign Settings', 'autoblogcraft-ai'); ?></h2>
		<p class="description">
			<?php esc_html_e('Configure your Amazon product campaign.', 'autoblogcraft-ai'); ?>
		</p>
	</div>

	<div class="abc-wizard-form">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="campaign_name">
						<?php esc_html_e('Campaign Name', 'autoblogcraft-ai'); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input type="text" name="campaign_name" id="campaign_name" 
					       value="<?php echo esc_attr($wizard_data['campaign_name'] ?? ''); ?>" 
					       class="regular-text" required>
					<p class="description"><?php esc_html_e('A descriptive name for this campaign.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="amazon_associate_id">
						<?php esc_html_e('Amazon Associate ID', 'autoblogcraft-ai'); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input type="text" name="amazon_associate_id" id="amazon_associate_id" 
					       value="<?php echo esc_attr($wizard_data['amazon_associate_id'] ?? ''); ?>" 
					       class="regular-text" required>
					<p class="description">
						<?php 
						printf(
							/* translators: %s: Link to Amazon Associates */
							esc_html__('Your Amazon Associate tracking ID. %s', 'autoblogcraft-ai'),
							'<a href="https://affiliate-program.amazon.com/" target="_blank" rel="noopener">' . esc_html__('Sign up here', 'autoblogcraft-ai') . '</a>'
						);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="amazon_marketplace"><?php esc_html_e('Amazon Marketplace', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<select name="amazon_marketplace" id="amazon_marketplace" class="regular-text">
						<option value="amazon.com" <?php selected($wizard_data['amazon_marketplace'] ?? 'amazon.com', 'amazon.com'); ?>><?php esc_html_e('Amazon.com (US)', 'autoblogcraft-ai'); ?></option>
						<option value="amazon.co.uk" <?php selected($wizard_data['amazon_marketplace'] ?? 'amazon.com', 'amazon.co.uk'); ?>><?php esc_html_e('Amazon.co.uk (UK)', 'autoblogcraft-ai'); ?></option>
						<option value="amazon.ca" <?php selected($wizard_data['amazon_marketplace'] ?? 'amazon.com', 'amazon.ca'); ?>><?php esc_html_e('Amazon.ca (Canada)', 'autoblogcraft-ai'); ?></option>
						<option value="amazon.de" <?php selected($wizard_data['amazon_marketplace'] ?? 'amazon.com', 'amazon.de'); ?>><?php esc_html_e('Amazon.de (Germany)', 'autoblogcraft-ai'); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="search_keywords"><?php esc_html_e('Search Keywords', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<div class="abc-keyword-list" id="amazon-keywords-list">
						<?php 
						$keywords = $wizard_data['search_keywords'] ?? [''];
						foreach ($keywords as $index => $keyword) :
						?>
							<div class="abc-keyword-item">
								<input type="text" name="search_keywords[]" 
								       value="<?php echo esc_attr($keyword); ?>" 
								       placeholder="e.g., wireless headphones" 
								       class="regular-text">
								<button type="button" class="button abc-remove-keyword">
									<span class="dashicons dashicons-no"></span>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button abc-add-keyword">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Keyword', 'autoblogcraft-ai'); ?>
					</button>
					<p class="description"><?php esc_html_e('Product keywords to search for.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="category_urls"><?php esc_html_e('Category URLs (Optional)', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<div class="abc-category-list" id="amazon-categories-list">
						<?php 
						$categories = $wizard_data['category_urls'] ?? [''];
						foreach ($categories as $index => $category) :
						?>
							<div class="abc-category-item">
								<input type="url" name="category_urls[]" 
								       value="<?php echo esc_url($category); ?>" 
								       placeholder="https://amazon.com/category-url" 
								       class="regular-text">
								<button type="button" class="button abc-remove-category">
									<span class="dashicons dashicons-no"></span>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button abc-add-category">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Category', 'autoblogcraft-ai'); ?>
					</button>
					<p class="description"><?php esc_html_e('Browse specific Amazon categories.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="min_price"><?php esc_html_e('Price Range', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<input type="number" name="min_price" id="min_price" 
					       value="<?php echo esc_attr($wizard_data['min_price'] ?? ''); ?>" 
					       min="0" step="0.01" placeholder="Min" class="small-text">
					<?php esc_html_e('to', 'autoblogcraft-ai'); ?>
					<input type="number" name="max_price" id="max_price" 
					       value="<?php echo esc_attr($wizard_data['max_price'] ?? ''); ?>" 
					       min="0" step="0.01" placeholder="Max" class="small-text">
					<p class="description"><?php esc_html_e('Filter products by price range (leave empty for all prices).', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="products_per_discovery"><?php esc_html_e('Products Per Discovery', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<input type="number" name="products_per_discovery" id="products_per_discovery" 
					       value="<?php echo esc_attr($wizard_data['products_per_discovery'] ?? 10); ?>" 
					       min="1" max="50" class="small-text">
					<p class="description"><?php esc_html_e('Maximum products to discover per run.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="auto_publish"><?php esc_html_e('Publishing', 'autoblogcraft-ai'); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="auto_publish" id="auto_publish" value="1" 
						       <?php checked(!empty($wizard_data['auto_publish'])); ?>>
						<?php esc_html_e('Automatically publish posts', 'autoblogcraft-ai'); ?>
					</label>
					<p class="description"><?php esc_html_e('If unchecked, posts will be saved as drafts.', 'autoblogcraft-ai'); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Add keyword field
		$('.abc-add-keyword').on('click', function() {
			var newItem = $('<div class="abc-keyword-item">' +
				'<input type="text" name="search_keywords[]" value="" placeholder="e.g., wireless headphones" class="regular-text">' +
				'<button type="button" class="button abc-remove-keyword"><span class="dashicons dashicons-no"></span></button>' +
				'</div>');
			$('#amazon-keywords-list').append(newItem);
		});

		// Remove keyword field
		$(document).on('click', '.abc-remove-keyword', function() {
			if ($('.abc-keyword-item').length > 1) {
				$(this).closest('.abc-keyword-item').remove();
			}
		});

		// Add category field
		$('.abc-add-category').on('click', function() {
			var newItem = $('<div class="abc-category-item">' +
				'<input type="url" name="category_urls[]" value="" placeholder="https://amazon.com/category-url" class="regular-text">' +
				'<button type="button" class="button abc-remove-category"><span class="dashicons dashicons-no"></span></button>' +
				'</div>');
			$('#amazon-categories-list').append(newItem);
		});

		// Remove category field
		$(document).on('click', '.abc-remove-category', function() {
			if ($('.abc-category-item').length > 1) {
				$(this).closest('.abc-category-item').remove();
			}
		});
	});
</script>
