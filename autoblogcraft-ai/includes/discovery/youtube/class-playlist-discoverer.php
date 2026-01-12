<?php
/**
 * YouTube Playlist Discoverer
 *
 * Discovers videos from YouTube playlists using YouTube Data API v3.
 *
 * @package AutoBlogCraft\Discovery\YouTube
 * @since 2.0.0
 */

namespace AutoBlogCraft\Discovery\YouTube;

use AutoBlogCraft\Discovery\Base_Discoverer;
use AutoBlogCraft\Core\Logger;
use AutoBlogCraft\Helpers\Sanitization;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * YouTube Playlist Discoverer class
 *
 * Integrates with YouTube Data API v3 to discover videos from playlists.
 *
 * @since 2.0.0
 */
class Playlist_Discoverer extends Base_Discoverer {

    /**
     * YouTube API base URL
     *
     * @var string
     */
    private $api_base = 'https://www.googleapis.com/youtube/v3';

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Sanitization helper
     *
     * @var Sanitization
     */
    private $sanitization;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        parent::__construct();
        $this->logger = Logger::instance();
        $this->sanitization = new Sanitization();
    }

    /**
     * Perform playlist discovery
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $source Source configuration.
     * @return array|WP_Error Array with 'items' or error.
     */
    protected function do_discover($campaign, $source) {
        // Get API key
        $api_key = $this->get_youtube_api_key($campaign);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Extract playlist ID from source
        $playlist_id = $this->extract_playlist_id($source);
        if (is_wp_error($playlist_id)) {
            return $playlist_id;
        }

        $this->logger->info("Discovering videos from playlist: {$playlist_id}", [
            'campaign_id' => $campaign->get_id(),
            'source' => $source,
        ]);

        // Get playlist items
        $videos = $this->get_playlist_items($playlist_id, $api_key, $source);
        if (is_wp_error($videos)) {
            return $videos;
        }

        // Queue discovered videos
        $queued = $this->queue_videos($campaign, $videos);

        $this->logger->info("Queued {$queued} videos from playlist {$playlist_id}", [
            'campaign_id' => $campaign->get_id(),
            'total_found' => count($videos),
            'queued' => $queued,
        ]);

        return [
            'items' => $videos,
            'queued' => $queued,
        ];
    }

    /**
     * Extract playlist ID from source
     *
     * @since 2.0.0
     * @param array $source Source configuration.
     * @return string|WP_Error Playlist ID or error.
     */
    private function extract_playlist_id($source) {
        // Check if playlist_id provided directly
        if (!empty($source['playlist_id'])) {
            return sanitize_text_field($source['playlist_id']);
        }

        // Check if URL provided
        if (empty($source['url'])) {
            return new WP_Error(
                'missing_playlist_id',
                'Playlist ID or URL is required'
            );
        }

        $url = $source['url'];

        // Pattern 1: youtube.com/playlist?list=PLAYLIST_ID
        if (preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Direct playlist ID (PL...)
        if (preg_match('/^[a-zA-Z0-9_-]{34}$/', $url)) {
            return $url;
        }

        return new WP_Error(
            'invalid_playlist_url',
            'Could not extract playlist ID from URL'
        );
    }

    /**
     * Get playlist items from YouTube API
     *
     * @since 2.0.0
     * @param string $playlist_id Playlist ID.
     * @param string $api_key YouTube API key.
     * @param array $source Source configuration.
     * @return array|WP_Error Array of videos or error.
     */
    private function get_playlist_items($playlist_id, $api_key, $source) {
        $videos = [];
        $page_token = '';
        $max_results = !empty($source['max_results']) ? (int) $source['max_results'] : 50;
        $total_fetched = 0;

        // Pagination loop
        do {
            $params = [
                'part' => 'snippet,contentDetails',
                'playlistId' => $playlist_id,
                'maxResults' => min(50, $max_results - $total_fetched), // API max is 50
                'key' => $api_key,
            ];

            if (!empty($page_token)) {
                $params['pageToken'] = $page_token;
            }

            $url = $this->api_base . '/playlistItems?' . http_build_query($params);

            $response = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code !== 200) {
                $error_message = !empty($data['error']['message'])
                    ? $data['error']['message']
                    : 'YouTube API request failed';

                return new WP_Error('youtube_api_error', $error_message, [
                    'status' => $code,
                    'response' => $data,
                ]);
            }

            if (empty($data['items'])) {
                break;
            }

            // Process items
            foreach ($data['items'] as $item) {
                $video = $this->parse_playlist_item($item);
                if ($video) {
                    $videos[] = $video;
                    $total_fetched++;

                    // Check if we've reached the limit
                    if ($total_fetched >= $max_results) {
                        break 2; // Break out of both loops
                    }
                }
            }

            $page_token = !empty($data['nextPageToken']) ? $data['nextPageToken'] : '';

        } while (!empty($page_token) && $total_fetched < $max_results);

        return $videos;
    }

    /**
     * Parse playlist item from API response
     *
     * @since 2.0.0
     * @param array $item Playlist item data.
     * @return array|null Parsed video data or null.
     */
    private function parse_playlist_item($item) {
        // Skip private/deleted videos
        if (empty($item['snippet']['title']) || $item['snippet']['title'] === 'Private video') {
            return null;
        }

        if (empty($item['contentDetails']['videoId'])) {
            return null;
        }

        $video_id = $item['contentDetails']['videoId'];
        $snippet = $item['snippet'];

        // Build video data
        $video = [
            'video_id' => $video_id,
            'title' => $snippet['title'],
            'description' => !empty($snippet['description']) ? $snippet['description'] : '',
            'url' => "https://www.youtube.com/watch?v={$video_id}",
            'thumbnail' => $this->get_best_thumbnail($snippet),
            'published_at' => !empty($snippet['publishedAt']) ? $snippet['publishedAt'] : '',
            'channel_id' => !empty($snippet['channelId']) ? $snippet['channelId'] : '',
            'channel_title' => !empty($snippet['channelTitle']) ? $snippet['channelTitle'] : '',
            'playlist_id' => !empty($snippet['playlistId']) ? $snippet['playlistId'] : '',
            'position' => !empty($item['snippet']['position']) ? (int) $item['snippet']['position'] : 0,
        ];

        return $video;
    }

    /**
     * Get best quality thumbnail from snippet
     *
     * @since 2.0.0
     * @param array $snippet Video snippet data.
     * @return string Thumbnail URL.
     */
    private function get_best_thumbnail($snippet) {
        if (empty($snippet['thumbnails'])) {
            return '';
        }

        $thumbnails = $snippet['thumbnails'];

        // Priority: maxres > standard > high > medium > default
        $priorities = ['maxres', 'standard', 'high', 'medium', 'default'];

        foreach ($priorities as $quality) {
            if (!empty($thumbnails[$quality]['url'])) {
                return $thumbnails[$quality]['url'];
            }
        }

        return '';
    }

    /**
     * Queue discovered videos
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @param array $videos Array of videos to queue.
     * @return int Number of videos queued.
     */
    private function queue_videos($campaign, $videos) {
        global $wpdb;

        $table = $wpdb->prefix . 'abc_discovery_queue';
        $queued = 0;

        foreach ($videos as $video) {
            // Generate unique content hash
            $content_hash = hash('sha256', $video['video_id'] . '|' . $video['url']);

            // Check if already queued
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE campaign_id = %d AND content_hash = %s",
                $campaign->get_id(),
                $content_hash
            ));

            if ($exists) {
                continue; // Skip duplicates
            }

            // Build metadata
            $meta = [
                'video_id' => $video['video_id'],
                'title' => $video['title'],
                'description' => $video['description'],
                'thumbnail' => $video['thumbnail'],
                'published_at' => $video['published_at'],
                'channel_id' => $video['channel_id'],
                'channel_title' => $video['channel_title'],
                'playlist_id' => $video['playlist_id'],
                'position' => $video['position'],
            ];

            // Insert into queue
            $inserted = $wpdb->insert(
                $table,
                [
                    'campaign_id' => $campaign->get_id(),
                    'source_url' => $video['url'],
                    'content_hash' => $content_hash,
                    'item_type' => 'youtube_playlist_video',
                    'metadata' => wp_json_encode($meta),
                    'priority' => $this->calculate_priority($video),
                    'status' => 'pending',
                    'discovered_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if ($inserted) {
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * Calculate priority for video
     *
     * @since 2.0.0
     * @param array $video Video data.
     * @return int Priority (1-10).
     */
    private function calculate_priority($video) {
        $priority = 5; // Default

        // Higher priority for recent videos
        if (!empty($video['published_at'])) {
            $published = strtotime($video['published_at']);
            $age_days = (time() - $published) / DAY_IN_SECONDS;

            if ($age_days < 1) {
                $priority = 10; // Published today
            } elseif ($age_days < 7) {
                $priority = 8; // Published this week
            } elseif ($age_days < 30) {
                $priority = 6; // Published this month
            }
        }

        // First videos in playlist get higher priority
        if (!empty($video['position']) && $video['position'] < 5) {
            $priority = max($priority, 7);
        }

        return $priority;
    }

    /**
     * Get YouTube API key
     *
     * @since 2.0.0
     * @param object $campaign Campaign instance.
     * @return string|WP_Error API key or error.
     */
    private function get_youtube_api_key($campaign) {
        global $wpdb;

        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_api_keys 
            WHERE provider = 'youtube' 
            AND status = 'active' 
            AND (user_id = %d OR user_id = 0)
            ORDER BY user_id DESC, id ASC
            LIMIT 1",
            get_current_user_id()
        ));

        if (!$key) {
            return new WP_Error(
                'no_youtube_key',
                'No active YouTube API key found. Please add one in Settings > API Keys.'
            );
        }

        return $key->api_key;
    }

    /**
     * Get playlist details
     *
     * Fetches metadata about the playlist itself (title, description, item count).
     *
     * @since 2.0.0
     * @param string $playlist_id Playlist ID.
     * @param string $api_key YouTube API key.
     * @return array|WP_Error Playlist details or error.
     */
    public function get_playlist_details($playlist_id, $api_key) {
        $params = [
            'part' => 'snippet,contentDetails',
            'id' => $playlist_id,
            'key' => $api_key,
        ];

        $url = $this->api_base . '/playlists?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $error_message = !empty($data['error']['message'])
                ? $data['error']['message']
                : 'Failed to fetch playlist details';

            return new WP_Error('youtube_api_error', $error_message, [
                'status' => $code,
                'response' => $data,
            ]);
        }

        if (empty($data['items'][0])) {
            return new WP_Error('playlist_not_found', 'Playlist not found');
        }

        $playlist = $data['items'][0];
        $snippet = $playlist['snippet'];
        $content_details = $playlist['contentDetails'];

        return [
            'id' => $playlist['id'],
            'title' => $snippet['title'],
            'description' => !empty($snippet['description']) ? $snippet['description'] : '',
            'channel_id' => $snippet['channelId'],
            'channel_title' => $snippet['channelTitle'],
            'published_at' => $snippet['publishedAt'],
            'thumbnail' => $this->get_best_thumbnail($snippet),
            'item_count' => (int) $content_details['itemCount'],
        ];
    }

    /**
     * Validate playlist URL or ID
     *
     * Checks if a playlist is accessible and returns basic info.
     *
     * @since 2.0.0
     * @param string $playlist_url_or_id Playlist URL or ID.
     * @return array|WP_Error Validation result or error.
     */
    public function validate_playlist($playlist_url_or_id) {
        // Extract playlist ID
        $source = ['url' => $playlist_url_or_id];
        $playlist_id = $this->extract_playlist_id($source);

        if (is_wp_error($playlist_id)) {
            return $playlist_id;
        }

        // Get API key (use first available)
        global $wpdb;
        $key = $wpdb->get_var(
            "SELECT api_key FROM {$wpdb->prefix}abc_api_keys 
            WHERE provider = 'youtube' AND status = 'active' 
            LIMIT 1"
        );

        if (!$key) {
            return new WP_Error(
                'no_youtube_key',
                'No active YouTube API key found'
            );
        }

        // Get playlist details
        $details = $this->get_playlist_details($playlist_id, $key);

        if (is_wp_error($details)) {
            return $details;
        }

        return [
            'valid' => true,
            'playlist_id' => $playlist_id,
            'title' => $details['title'],
            'channel_title' => $details['channel_title'],
            'item_count' => $details['item_count'],
        ];
    }
}
