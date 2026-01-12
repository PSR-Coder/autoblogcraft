<?php
/**
 * YouTube Discoverer
 *
 * Discovers videos from YouTube channels and playlists.
 * Uses YouTube Data API v3.
 *
 * @package AutoBlogCraft\Discovery
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\YouTube;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * YouTube Discoverer class
 *
 * Integrates with YouTube Data API v3 to discover videos.
 *
 * @since 2.0.0
 */
class YouTube_Discoverer extends Base_Discoverer {

    /**
     * YouTube API base URL
     *
     * @var string
     */
    private $api_base = 'https://www.googleapis.com/youtube/v3';

    /**
     * Perform YouTube discovery
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $source Source configuration.
     * @return array|WP_Error Array with 'items' or error.
     */
    protected function do_discover($campaign, $source) {
        // Get API key
        $api_key = $this->get_api_key($campaign);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Determine source type (channel or playlist)
        $source_type = isset($source['type']) ? $source['type'] : 'channel';

        if ($source_type === 'channel') {
            return $this->discover_from_channel($source, $api_key);
        } elseif ($source_type === 'playlist') {
            return $this->discover_from_playlist($source, $api_key);
        }

        return new WP_Error('invalid_youtube_type', 'Invalid YouTube source type');
    }

    /**
     * Discover videos from channel
     *
     * @since 2.0.0
     * @param array $source Source configuration.
     * @param string $api_key YouTube API key.
     * @return array|WP_Error Discovery result or error.
     */
    private function discover_from_channel($source, $api_key) {
        $channel_id = isset($source['channel_id']) ? $source['channel_id'] : '';
        $channel_url = isset($source['url']) ? $source['url'] : '';

        // Extract channel ID from URL if not provided
        if (empty($channel_id) && !empty($channel_url)) {
            $channel_id = $this->extract_channel_id($channel_url);
        }

        if (empty($channel_id)) {
            return new WP_Error('missing_channel_id', 'Channel ID is required');
        }

        // Get uploads playlist ID
        $uploads_playlist = $this->get_uploads_playlist($channel_id, $api_key);
        if (is_wp_error($uploads_playlist)) {
            return $uploads_playlist;
        }

        // Get videos from uploads playlist
        return $this->get_playlist_videos($uploads_playlist, $api_key);
    }

    /**
     * Discover videos from playlist
     *
     * @since 2.0.0
     * @param array $source Source configuration.
     * @param string $api_key YouTube API key.
     * @return array|WP_Error Discovery result or error.
     */
    private function discover_from_playlist($source, $api_key) {
        $playlist_id = isset($source['playlist_id']) ? $source['playlist_id'] : '';
        $playlist_url = isset($source['url']) ? $source['url'] : '';

        // Extract playlist ID from URL if not provided
        if (empty($playlist_id) && !empty($playlist_url)) {
            $playlist_id = $this->extract_playlist_id($playlist_url);
        }

        if (empty($playlist_id)) {
            return new WP_Error('missing_playlist_id', 'Playlist ID is required');
        }

        return $this->get_playlist_videos($playlist_id, $api_key);
    }

    /**
     * Get uploads playlist ID for channel
     *
     * @since 2.0.0
     * @param string $channel_id Channel ID.
     * @param string $api_key API key.
     * @return string|WP_Error Uploads playlist ID or error.
     */
    private function get_uploads_playlist($channel_id, $api_key) {
        $url = add_query_arg([
            'part' => 'contentDetails',
            'id' => $channel_id,
            'key' => $api_key,
        ], $this->api_base . '/channels');

        $response = $this->fetch_url($url);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
            return new WP_Error('channel_not_found', 'Channel not found or has no uploads');
        }

        return $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
    }

    /**
     * Get videos from playlist
     *
     * @since 2.0.0
     * @param string $playlist_id Playlist ID.
     * @param string $api_key API key.
     * @param int $max_results Maximum results (default 50, max 50).
     * @return array|WP_Error Array with items or error.
     */
    private function get_playlist_videos($playlist_id, $api_key, $max_results = 50) {
        $url = add_query_arg([
            'part' => 'snippet,contentDetails',
            'playlistId' => $playlist_id,
            'maxResults' => min($max_results, 50),
            'key' => $api_key,
        ], $this->api_base . '/playlistItems');

        $response = $this->fetch_url($url);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['items'])) {
            return new WP_Error('api_error', 'Invalid API response');
        }

        $items = [];

        foreach ($data['items'] as $video) {
            $item = $this->parse_video_item($video);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [
            'items' => $items,
            'playlist_id' => $playlist_id,
        ];
    }

    /**
     * Parse video item from API response
     *
     * @since 2.0.0
     * @param array $video Video data from API.
     * @return array|null Item data or null if invalid.
     */
    private function parse_video_item($video) {
        if (!isset($video['snippet'])) {
            return null;
        }

        $snippet = $video['snippet'];

        // Video ID
        $video_id = isset($snippet['resourceId']['videoId']) ? $snippet['resourceId']['videoId'] : '';
        if (empty($video_id)) {
            return null;
        }

        // Title
        $title = isset($snippet['title']) ? $snippet['title'] : '';
        if (empty($title) || $title === 'Private video' || $title === 'Deleted video') {
            return null;
        }

        // Description
        $description = isset($snippet['description']) ? $snippet['description'] : '';
        $excerpt = $this->truncate($description, 500);

        // Published date
        $date = isset($snippet['publishedAt']) ? $snippet['publishedAt'] : '';

        // Channel info
        $channel_title = isset($snippet['channelTitle']) ? $snippet['channelTitle'] : '';

        // Thumbnail
        $thumbnail = '';
        if (isset($snippet['thumbnails']['high']['url'])) {
            $thumbnail = $snippet['thumbnails']['high']['url'];
        } elseif (isset($snippet['thumbnails']['medium']['url'])) {
            $thumbnail = $snippet['thumbnails']['medium']['url'];
        } elseif (isset($snippet['thumbnails']['default']['url'])) {
            $thumbnail = $snippet['thumbnails']['default']['url'];
        }

        // Video URL
        $url = 'https://www.youtube.com/watch?v=' . $video_id;

        return [
            'url' => $url,
            'title' => sanitize_text_field($title),
            'excerpt' => wp_kses_post($excerpt),
            'date' => $date,
            'author' => sanitize_text_field($channel_title),
            'image' => esc_url_raw($thumbnail),
            'categories' => [],
            'raw_data' => [
                'video_id' => $video_id,
                'channel_id' => isset($snippet['channelId']) ? $snippet['channelId'] : '',
                'channel_title' => $channel_title,
                'duration' => isset($video['contentDetails']['duration']) ? $video['contentDetails']['duration'] : '',
            ],
        ];
    }

    /**
     * Extract channel ID from URL
     *
     * Supports:
     * - youtube.com/channel/CHANNEL_ID
     * - youtube.com/@username
     * - youtube.com/c/customname
     * - youtube.com/user/username
     *
     * @since 2.0.0
     * @param string $url YouTube URL.
     * @return string|null Channel ID or null if not found.
     */
    private function extract_channel_id($url) {
        // Direct channel ID
        if (preg_match('/youtube\.com\/channel\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Handle @username, /c/, /user/ formats
        // These require API lookup, which we'll return the identifier for now
        // and let the API handle the resolution
        if (preg_match('/youtube\.com\/@([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return '@' . $matches[1];
        }

        if (preg_match('/youtube\.com\/c\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'c/' . $matches[1];
        }

        if (preg_match('/youtube\.com\/user\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'user/' . $matches[1];
        }

        return null;
    }

    /**
     * Extract playlist ID from URL
     *
     * @since 2.0.0
     * @param string $url YouTube URL.
     * @return string|null Playlist ID or null if not found.
     */
    private function extract_playlist_id($url) {
        if (preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get YouTube API key
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return string|WP_Error API key or error.
     */
    private function get_api_key($campaign) {
        // Check campaign-specific key
        $api_key = $campaign->get_meta('youtube_api_key', '');

        // Fallback to global setting
        if (empty($api_key)) {
            $api_key = get_option('abc_youtube_api_key', '');
        }

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'YouTube API key is not configured'
            );
        }

        return $api_key;
    }

    /**
     * Get source type identifier
     *
     * @since 2.0.0
     * @return string Source type.
     */
    protected function get_source_type() {
        return 'youtube';
    }

    /**
     * Validate campaign configuration
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    protected function validate_campaign($campaign) {
        $parent_valid = parent::validate_campaign($campaign);
        if (is_wp_error($parent_valid)) {
            return $parent_valid;
        }

        // Check API key
        $api_key = $this->get_api_key($campaign);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        $sources = $this->get_sources($campaign);
        if (empty($sources)) {
            return new WP_Error(
                'no_youtube_sources',
                'No YouTube sources configured for campaign'
            );
        }

        return true;
    }
}
