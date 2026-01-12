<?php
/**
 * YouTube Processor
 *
 * Processes YouTube videos.
 * Fetches video metadata and transcript.
 *
 * @package AutoBlogCraft\Processing
 * @since 2.0.0
 */

namespace AutoBlogCraft\Processing\Processors;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * YouTube Processor class
 *
 * Processes YouTube videos into articles.
 *
 * @since 2.0.0
 */
class YouTube_Processor extends Base_Processor {

    /**
     * YouTube API base URL
     *
     * @var string
     */
    private $api_base = 'https://www.googleapis.com/youtube/v3';

    /**
     * Fetch content from YouTube video
     *
     * @since 2.0.0
     * @param array $queue_item Queue item data.
     * @return array|WP_Error Content data or error.
     */
    protected function fetch_content($queue_item) {
        // Extract video ID from URL
        $video_id = $this->extract_video_id($queue_item['source_url']);
        
        if (empty($video_id)) {
            return new WP_Error('invalid_video_url', 'Could not extract video ID from URL');
        }

        // Get video details
        $video_data = $this->fetch_video_details($video_id);
        
        if (is_wp_error($video_data)) {
            return $video_data;
        }

        // Get transcript (if available)
        $transcript = $this->fetch_transcript($video_id);

        // Build content
        $content = $this->build_video_content($video_data, $transcript);

        return [
            'title' => $video_data['title'],
            'content' => $content,
            'html' => $content,
            'author' => $video_data['channel_title'],
            'date' => $video_data['published_at'],
            'image' => $video_data['thumbnail'],
            'description' => $video_data['description'],
            'video_id' => $video_id,
            'video_data' => $video_data,
        ];
    }

    /**
     * Extract video ID from URL
     *
     * @since 2.0.0
     * @param string $url YouTube URL.
     * @return string|null Video ID or null.
     */
    private function extract_video_id($url) {
        // Standard format: youtube.com/watch?v=VIDEO_ID
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Short format: youtu.be/VIDEO_ID
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Embed format: youtube.com/embed/VIDEO_ID
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Fetch video details from YouTube API
     *
     * @since 2.0.0
     * @param string $video_id Video ID.
     * @return array|WP_Error Video data or error.
     */
    private function fetch_video_details($video_id) {
        // Get API key from settings
        $api_key = get_option('abc_youtube_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'YouTube API key not configured');
        }

        $url = add_query_arg([
            'part' => 'snippet,contentDetails,statistics',
            'id' => $video_id,
            'key' => $api_key,
        ], $this->api_base . '/videos');

        $response = wp_remote_get($url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['items'][0])) {
            return new WP_Error('video_not_found', 'Video not found');
        }

        $video = $data['items'][0];
        $snippet = $video['snippet'];

        return [
            'video_id' => $video_id,
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'published_at' => $snippet['publishedAt'],
            'channel_id' => $snippet['channelId'],
            'channel_title' => $snippet['channelTitle'],
            'thumbnail' => isset($snippet['thumbnails']['high']['url']) ? 
                $snippet['thumbnails']['high']['url'] : '',
            'duration' => isset($video['contentDetails']['duration']) ? 
                $video['contentDetails']['duration'] : '',
            'view_count' => isset($video['statistics']['viewCount']) ? 
                $video['statistics']['viewCount'] : 0,
            'like_count' => isset($video['statistics']['likeCount']) ? 
                $video['statistics']['likeCount'] : 0,
            'tags' => isset($snippet['tags']) ? $snippet['tags'] : [],
        ];
    }

    /**
     * Fetch video transcript
     *
     * Note: YouTube doesn't provide official transcript API.
     * This is a placeholder for third-party transcript services.
     *
     * @since 2.0.0
     * @param string $video_id Video ID.
     * @return string Transcript or empty string.
     */
    private function fetch_transcript($video_id) {
        /**
         * Filter to provide video transcript
         *
         * Allows integration with third-party transcript services.
         *
         * @since 2.0.0
         * @param string $transcript Transcript text.
         * @param string $video_id Video ID.
         */
        $transcript = apply_filters('abc_youtube_transcript', '', $video_id);

        return $transcript;
    }

    /**
     * Build article content from video data
     *
     * @since 2.0.0
     * @param array $video_data Video metadata.
     * @param string $transcript Video transcript.
     * @return string HTML content.
     */
    private function build_video_content($video_data, $transcript) {
        $content = '';

        // Embed video
        $content .= sprintf(
            '<div class="video-embed"><iframe width="800" height="450" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe></div>',
            esc_attr($video_data['video_id'])
        );

        $content .= "\n\n";

        // Video description
        if (!empty($video_data['description'])) {
            $content .= '<div class="video-description">';
            $content .= wpautop(esc_html($video_data['description']));
            $content .= '</div>';
            $content .= "\n\n";
        }

        // Transcript
        if (!empty($transcript)) {
            $content .= '<h2>Video Transcript</h2>';
            $content .= '<div class="video-transcript">';
            $content .= wpautop(esc_html($transcript));
            $content .= '</div>';
        }

        // Video stats
        $content .= '<div class="video-stats">';
        $content .= '<p><strong>Channel:</strong> ' . esc_html($video_data['channel_title']) . '</p>';
        $content .= '<p><strong>Published:</strong> ' . date('F j, Y', strtotime($video_data['published_at'])) . '</p>';
        
        if ($video_data['view_count'] > 0) {
            $content .= '<p><strong>Views:</strong> ' . number_format($video_data['view_count']) . '</p>';
        }
        
        $content .= '</div>';

        return $content;
    }

    /**
     * Extract metadata
     *
     * @since 2.0.0
     * @param array $content_data Content data.
     * @param array $queue_item Queue item.
     * @return array Metadata.
     */
    protected function extract_metadata($content_data, $queue_item) {
        $metadata = parent::extract_metadata($content_data, $queue_item);

        // Add YouTube-specific metadata
        if (isset($content_data['video_data'])) {
            $metadata['video_data'] = $content_data['video_data'];
            $metadata['video_id'] = $content_data['video_id'];
        }

        return $metadata;
    }

    /**
     * Get processor type
     *
     * @since 2.0.0
     * @return string Processor type.
     */
    protected function get_processor_type() {
        return 'youtube';
    }
}
