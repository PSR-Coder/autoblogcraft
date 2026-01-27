<?php
namespace AutoBlogCraft\Campaigns;

class Campaign_Repository {
    /**
     * Save a campaign from form data (works for both AJAX and POST)
     */
    public function save($campaign_id, $data) {
        $campaign_id = absint($campaign_id);
        
        // 1. Basic Info
        if (isset($data['post_title'])) {
            $post_data = [
                'ID' => $campaign_id,
                'post_title' => sanitize_text_field($data['post_title']),
                'post_type' => 'abc_campaign',
                'post_status' => 'publish'
            ];
            
            if ($campaign_id === 0) {
                $campaign_id = wp_insert_post($post_data);
                if (isset($data['campaign_type'])) {
                    update_post_meta($campaign_id, '_campaign_type', sanitize_key($data['campaign_type']));
                    update_post_meta($campaign_id, '_campaign_status', 'active');
                }
            } else {
                wp_update_post($post_data);
            }
        }

        if (is_wp_error($campaign_id)) return $campaign_id;

        // 2. Save Configs (Reuse this logic for Wizard AND Edit Screen)
        if (!empty($data['ai_config'])) {
            // Sanitize and save AI config
            update_post_meta($campaign_id, '_ai_config', $this->sanitize_recursive($data['ai_config']));
        }
        
        if (!empty($data['source_config'])) {
            update_post_meta($campaign_id, '_source_config', $this->sanitize_recursive($data['source_config']));
        }

        return $campaign_id;
    }

    private function sanitize_recursive($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize_recursive'], $data);
        }
        return sanitize_text_field($data);
    }
}
