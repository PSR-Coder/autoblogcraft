<?php
/**
 * Settings Template - General Settings Section
 *
 * Template for rendering general plugin settings.
 *
 * @package AutoBlogCraft\Templates\Admin\Settings
 * @since 2.0.0
 *
 * @var bool   $enable_cron          Whether cron jobs are enabled
 * @var string $default_post_status  Default post status (draft, publish, pending)
 * @var int    $default_post_author  Default post author user ID
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="enable_cron"><?php _e('Enable Cron Jobs', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <label>
                <input type="checkbox" name="enable_cron" id="enable_cron" value="1" 
                       <?php checked($enable_cron); ?>>
                <?php _e('Enable automatic discovery and processing', 'autoblogcraft'); ?>
            </label>
            <p class="description">
                <?php _e('Disable this to pause all automated jobs.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="default_post_status"><?php _e('Default Post Status', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <select name="default_post_status" id="default_post_status">
                <option value="draft" <?php selected($default_post_status, 'draft'); ?>>
                    <?php _e('Draft', 'autoblogcraft'); ?>
                </option>
                <option value="publish" <?php selected($default_post_status, 'publish'); ?>>
                    <?php _e('Publish', 'autoblogcraft'); ?>
                </option>
                <option value="pending" <?php selected($default_post_status, 'pending'); ?>>
                    <?php _e('Pending Review', 'autoblogcraft'); ?>
                </option>
            </select>
            <p class="description">
                <?php _e('Default status for newly created posts.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="default_post_author"><?php _e('Default Post Author', 'autoblogcraft'); ?></label>
        </th>
        <td>
            <?php
            wp_dropdown_users([
                'name' => 'default_post_author',
                'id' => 'default_post_author',
                'selected' => $default_post_author,
                'show_option_none' => __('Select Author', 'autoblogcraft'),
            ]);
            ?>
            <p class="description">
                <?php _e('Default author for created posts.', 'autoblogcraft'); ?>
            </p>
        </td>
    </tr>
</table>
