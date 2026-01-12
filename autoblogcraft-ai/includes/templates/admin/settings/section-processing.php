<?php
/**
 * Settings Template - Processing Settings Section
 *
 * Template for rendering processing and rate limit settings.
 *
 * @package AutoBlogCraft\Templates\Admin\Settings
 * @since 2.0.0
 *
 * @var int  $processing_batch_size     Number of items to process per job
 * @var int  $max_concurrent_campaigns  Maximum concurrent campaigns (1-20)
 * @var int  $max_concurrent_ai_calls   Maximum concurrent AI calls (1-50)
 * @var int  $max_retries               Maximum retry attempts for failed items
 * @var bool $enable_duplicate_check    Whether duplicate detection is enabled
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="processing_batch_size"><?php _e('Processing Batch Size', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <input type="number" name="processing_batch_size" id="processing_batch_size" 
                   value="<?php echo esc_attr($processing_batch_size); ?>" 
                   min="1" max="100" class="small-text">
            <p class="description">
                <?php _e('Number of items to process per job run.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="max_concurrent_campaigns"><?php _e('Max Concurrent Campaigns', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <input type="number" name="max_concurrent_campaigns" id="max_concurrent_campaigns" 
                   value="<?php echo esc_attr($max_concurrent_campaigns); ?>" 
                   min="1" max="20" class="small-text">
            <p class="description">
                <?php _e('Maximum number of campaigns processing simultaneously. Prevents server overload.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="max_concurrent_ai_calls"><?php _e('Max Concurrent AI Calls', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <input type="number" name="max_concurrent_ai_calls" id="max_concurrent_ai_calls" 
                   value="<?php echo esc_attr($max_concurrent_ai_calls); ?>" 
                   min="1" max="50" class="small-text">
            <p class="description">
                <?php _e('Maximum number of AI API calls across all campaigns. Prevents runaway costs.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="max_retries"><?php _e('Maximum Retries', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <input type="number" name="max_retries" id="max_retries" 
                   value="<?php echo esc_attr($max_retries); ?>" 
                   min="1" max="10" class="small-text">
            <p class="description">
                <?php _e('Maximum retry attempts for failed items.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="enable_duplicate_check"><?php _e('Duplicate Detection', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <label>
                <input type="checkbox" name="enable_duplicate_check" id="enable_duplicate_check" value="1" 
                       <?php checked($enable_duplicate_check); ?>>
                <?php _e('Enable duplicate content detection', 'autoblogcraft'); ?>
            </label>
            <p class="description">
                <?php _e('Prevents creating duplicate posts from same source.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>
</table>
