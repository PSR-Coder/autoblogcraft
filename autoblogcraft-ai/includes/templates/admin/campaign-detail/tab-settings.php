<?php
/**
 * Campaign Detail - Settings Tab Template
 *
 * @package AutoBlogCraft\Templates
 * @since 2.0.0
 * 
 * Available variables:
 * @var object $campaign Campaign object
 * @var int $campaign_id Campaign ID
 * @var string $discovery_interval Current discovery interval
 * @var string $status Current campaign status
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<form method="post">
    <?php wp_nonce_field('abc_campaign_settings', 'abc_campaign_settings_nonce'); ?>
    <input type="hidden" name="abc_save_settings" value="1">

    <div class="abc-card">
        <div class="abc-card-header">
            <h3><?php esc_html_e('Campaign Settings', 'autoblogcraft'); ?></h3>
        </div>
        <div class="abc-card-body">
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
                        <p class="description">
                            <?php esc_html_e('How often should this campaign discover new content?', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="status"><?php esc_html_e('Campaign Status', 'autoblogcraft'); ?></label>
                    </th>
                    <td>
                        <select id="status" name="status">
                            <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'autoblogcraft'); ?></option>
                            <option value="paused" <?php selected($status, 'paused'); ?>><?php esc_html_e('Paused', 'autoblogcraft'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Paused campaigns will not discover or process new content.', 'autoblogcraft'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <div class="abc-card-footer">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Settings', 'autoblogcraft'); ?>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=abc-campaign-wizard&step=1&campaign_id=' . $campaign_id)); ?>" class="button">
                <?php esc_html_e('Advanced Settings', 'autoblogcraft'); ?>
            </a>
        </div>
    </div>
</form>
