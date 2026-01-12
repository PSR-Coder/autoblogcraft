# AutoBlogCraft AI v2.0 - Developer Documentation

**Version**: 2.0.0  
**Last Updated**: January 7, 2026  
**Audience**: Plugin developers, theme developers, advanced users

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Code Organization](#code-organization)
3. [Design Patterns](#design-patterns)
4. [Extending Campaigns](#extending-campaigns)
5. [Custom AI Providers](#custom-ai-providers)
6. [Custom Discoverers](#custom-discoverers)
7. [Custom Processors](#custom-processors)
8. [Hooks Reference](#hooks-reference)
9. [Database Schema](#database-schema)
10. [Best Practices](#best-practices)

---

## Architecture Overview

### Core Principles

1. **Zero Code Duplication**: All shared logic in base classes
2. **Template Method Pattern**: Abstract methods for customization
3. **Factory Pattern**: Dynamic object creation
4. **Dependency Injection**: No hard-coded dependencies
5. **Single Responsibility**: Each class has one purpose
6. **Clean Code**: Self-documenting, well-commented

### System Flow

```
Campaign Creation (Admin UI)
    ↓
Discovery Manager (Cron)
    ↓
Queue Manager (Database)
    ↓
Processing Manager (AI)
    ↓
Post Publisher (WordPress)
    ↓
SEO Manager (Meta)
    ↓
Translation Manager (Multilingual)
```

### Component Diagram

```
includes/
├── core/           (Autoloader, Plugin, Logger, Rate Limiter)
├── campaigns/      (Campaign_Base, Factory, Concrete Types)
├── discovery/      (Discovery_Manager, Queue_Manager, Discoverers)
├── processing/     (Processing_Manager, Processors, Publisher)
├── ai/             (AI_Manager, Providers, Key_Manager, Token_Counter)
├── cron/           (Cron_Manager, Discovery_Job, Processing_Job)
├── admin/          (Admin_Menu, Admin_Pages, Wizards)
├── seo/            (SEO_Manager, Integrations, Breadcrumbs, Sitemap)
├── translation/    (Translation_Manager, Integrations, Hreflang)
├── database/       (Installer, schema definitions)
└── helpers/        (Encryption, Validation, Sanitization)
```

---

## Code Organization

### Namespace Structure

```php
namespace AutoBlogCraft\Campaigns;    // Campaign classes
namespace AutoBlogCraft\Discovery;    // Discovery system
namespace AutoBlogCraft\Processing;   // Processing system
namespace AutoBlogCraft\AI;           // AI providers
namespace AutoBlogCraft\AI\Providers; // Concrete providers
namespace AutoBlogCraft\Cron;         // Cron jobs
namespace AutoBlogCraft\Admin;        // Admin pages
namespace AutoBlogCraft\SEO;          // SEO features
namespace AutoBlogCraft\Translation;  // Translation features
```

### File Naming Convention

```
class-campaign-base.php        → Campaign_Base class
class-openai-provider.php      → OpenAI_Provider class
class-discovery-manager.php    → Discovery_Manager class
```

**Rule**: `class-{name}.php` where `{name}` is kebab-case of class name

### Class Naming Convention

```
CampaignBase      ✗ Wrong
Campaign_Base     ✓ Correct
campaign_base     ✗ Wrong
```

**Rule**: `Class_Name` with underscores (WordPress standard)

---

## Design Patterns

### 1. Template Method Pattern

**Used in**: Campaigns, Discoverers, Processors

**Example: Campaign Base Class**

```php
abstract class Campaign_Base {
    // Template method (final)
    final public function execute() {
        $this->validate();
        $this->discover();
        $this->process();
        $this->publish();
    }
    
    // Abstract methods (must implement)
    abstract protected function validate();
    abstract protected function discover();
    abstract protected function process();
    
    // Concrete methods (can override)
    protected function publish() {
        // Default implementation
    }
}
```

**Usage**:
```php
class Website_Campaign extends Campaign_Base {
    protected function validate() {
        // Website-specific validation
    }
    
    protected function discover() {
        // RSS/Sitemap discovery
    }
    
    protected function process() {
        // Web scraping
    }
}
```

### 2. Factory Pattern

**Used in**: Campaign creation, Provider initialization

**Example: Campaign Factory**

```php
class Campaign_Factory {
    private static $types = [
        'website' => Website_Campaign::class,
        'youtube' => YouTube_Campaign::class,
        'amazon'  => Amazon_Campaign::class,
    ];
    
    public static function create($campaign_post) {
        $type = get_post_meta($campaign_post->ID, '_campaign_type', true);
        
        if (!isset(self::$types[$type])) {
            throw new \Exception("Unknown campaign type: {$type}");
        }
        
        $class = self::$types[$type];
        return new $class($campaign_post);
    }
    
    public static function register_type($type, $class) {
        self::$types[$type] = $class;
    }
}
```

### 3. Strategy Pattern

**Used in**: Key rotation

**Example: Key Rotator**

```php
class Key_Rotator {
    private $strategies = [
        'round_robin' => 'rotate_round_robin',
        'least_used'  => 'rotate_least_used',
        'random'      => 'rotate_random',
        'priority'    => 'rotate_priority',
    ];
    
    public function get_next_key($provider, $strategy) {
        if (!isset($this->strategies[$strategy])) {
            $strategy = 'round_robin';
        }
        
        $method = $this->strategies[$strategy];
        return $this->$method($provider);
    }
}
```

### 4. Singleton Pattern

**Used in**: Logger, Managers

**Example: Logger**

```php
class Logger {
    private static $instance = null;
    
    private function __construct() {
        // Private constructor
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function log($campaign_id, $level, $category, $message) {
        $instance = self::get_instance();
        $instance->write_log($campaign_id, $level, $category, $message);
    }
}
```

---

## Extending Campaigns

### Create Custom Campaign Type

**Step 1: Create Campaign Class**

```php
<?php
namespace AutoBlogCraft\Campaigns;

class Podcast_Campaign extends Campaign_Base {
    
    protected $campaign_type = 'podcast';
    
    public function get_wizard_steps() {
        return [
            'basic_info',
            'podcast_sources',
            'transcription_settings',
            'ai_config',
            'review',
        ];
    }
    
    public function validate_source($source) {
        // Validate podcast RSS feed
        if (!filter_var($source, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', 'Invalid podcast feed URL');
        }
        
        // Check if it's a valid podcast feed
        $response = wp_remote_get($source);
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (strpos($body, '<enclosure') === false) {
            return new \WP_Error('not_podcast', 'Not a valid podcast feed');
        }
        
        return true;
    }
    
    public function get_discovery_class() {
        return 'AutoBlogCraft\\Discovery\\Podcast_Discoverer';
    }
    
    public function get_processor_class() {
        return 'AutoBlogCraft\\Processing\\Podcast_Processor';
    }
}
```

**Step 2: Register Campaign Type**

```php
// In your plugin or theme's functions.php
add_action('plugins_loaded', function() {
    Campaign_Factory::register_type('podcast', Podcast_Campaign::class);
});
```

**Step 3: Create Discoverer**

```php
<?php
namespace AutoBlogCraft\Discovery;

class Podcast_Discoverer extends Base_Discoverer {
    
    protected $source_type = 'podcast';
    
    protected function fetch_items($source) {
        // Fetch podcast episodes
        $feed = fetch_feed($source);
        
        if (is_wp_error($feed)) {
            return $feed;
        }
        
        $items = [];
        foreach ($feed->get_items() as $item) {
            $items[] = [
                'title' => $item->get_title(),
                'content' => $item->get_description(),
                'source_url' => $item->get_link(),
                'metadata' => [
                    'audio_url' => $this->get_enclosure_url($item),
                    'duration' => $this->get_duration($item),
                    'publish_date' => $item->get_date('Y-m-d H:i:s'),
                ],
            ];
        }
        
        return $items;
    }
    
    private function get_enclosure_url($item) {
        $enclosure = $item->get_enclosure();
        return $enclosure ? $enclosure->get_link() : '';
    }
}
```

**Step 4: Create Processor**

```php
<?php
namespace AutoBlogCraft\Processing;

class Podcast_Processor extends Base_Processor {
    
    public function process($queue_item) {
        // 1. Download audio file
        $audio_url = $queue_item->metadata['audio_url'];
        $audio_file = $this->download_audio($audio_url);
        
        // 2. Transcribe via Whisper API
        $transcription = $this->transcribe_audio($audio_file);
        
        // 3. Generate article from transcription
        $article = $this->ai_provider->generate_content([
            'prompt' => "Convert this podcast transcription to an article:\n\n{$transcription}",
            'tone' => $this->ai_config->tone,
            'length' => $this->ai_config->target_length,
        ]);
        
        // 4. Publish post
        return Publisher::create_post([
            'title' => $queue_item->title,
            'content' => $article['content'],
            'excerpt' => $article['excerpt'],
            'campaign_id' => $queue_item->campaign_id,
            'metadata' => [
                'audio_url' => $audio_url,
                'duration' => $queue_item->metadata['duration'],
            ],
        ]);
    }
}
```

---

## Custom AI Providers

### Create Custom Provider

**Step 1: Extend Base Provider**

```php
<?php
namespace AutoBlogCraft\AI\Providers;

class Custom_Provider extends Base_Provider {
    
    protected $provider_name = 'custom';
    protected $base_url = 'https://api.custom-ai.com/v1';
    
    public function generate_content($params) {
        $endpoint = $this->base_url . '/chat/completions';
        
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $params['prompt']],
            ],
            'max_tokens' => $params['max_tokens'] ?? 2000,
            'temperature' => $params['temperature'] ?? 0.7,
        ];
        
        $response = $this->make_request('POST', $endpoint, $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'content' => $response['choices'][0]['message']['content'],
            'tokens_used' => $response['usage']['total_tokens'],
            'model' => $response['model'],
        ];
    }
    
    public function count_tokens($text) {
        // Implement token counting logic
        return ceil(str_word_count($text) * 1.3);
    }
    
    public function get_models() {
        return [
            'custom-small' => 'Custom Small Model',
            'custom-large' => 'Custom Large Model',
        ];
    }
    
    public function get_pricing() {
        return [
            'custom-small' => ['input' => 0.001, 'output' => 0.002],
            'custom-large' => ['input' => 0.01, 'output' => 0.02],
        ];
    }
}
```

**Step 2: Register Provider**

```php
add_filter('abc_ai_providers', function($providers) {
    $providers['custom'] = Custom_Provider::class;
    return $providers;
});
```

**Step 3: Add to Admin UI**

```php
add_filter('abc_ai_provider_options', function($options) {
    $options['custom'] = 'Custom AI Provider';
    return $options;
});
```

---

## Custom Discoverers

### Example: GitHub Releases Discoverer

```php
<?php
namespace AutoBlogCraft\Discovery;

class GitHub_Discoverer extends Base_Discoverer {
    
    protected $source_type = 'github';
    
    protected function fetch_items($source) {
        // Parse repo from URL
        // e.g., https://github.com/owner/repo
        preg_match('/github\.com\/([^\/]+)\/([^\/]+)/', $source, $matches);
        
        if (empty($matches)) {
            return new \WP_Error('invalid_github_url', 'Invalid GitHub URL');
        }
        
        $owner = $matches[1];
        $repo = $matches[2];
        
        // Fetch releases via GitHub API
        $url = "https://api.github.com/repos/{$owner}/{$repo}/releases";
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $releases = json_decode(wp_remote_retrieve_body($response), true);
        
        $items = [];
        foreach ($releases as $release) {
            $items[] = [
                'title' => $release['name'],
                'content' => $release['body'], // Release notes
                'source_url' => $release['html_url'],
                'metadata' => [
                    'tag' => $release['tag_name'],
                    'published_at' => $release['published_at'],
                    'download_url' => $release['zipball_url'],
                ],
            ];
        }
        
        return $items;
    }
    
    protected function calculate_priority($item) {
        // Higher priority for recent releases
        $age_days = (time() - strtotime($item['metadata']['published_at'])) / 86400;
        
        if ($age_days < 1) return 10;
        if ($age_days < 7) return 7;
        if ($age_days < 30) return 5;
        return 3;
    }
}
```

---

## Custom Processors

### Example: Video to Blog Post Processor

```php
<?php
namespace AutoBlogCraft\Processing;

class Video_Processor extends Base_Processor {
    
    public function process($queue_item) {
        // 1. Download video thumbnail
        $thumbnail = $this->download_thumbnail($queue_item->metadata['thumbnail_url']);
        
        // 2. Extract video metadata
        $duration = $queue_item->metadata['duration'];
        $view_count = $queue_item->metadata['view_count'];
        
        // 3. Generate blog post from video description
        $prompt = $this->build_prompt($queue_item);
        
        $article = $this->ai_provider->generate_content([
            'prompt' => $prompt,
            'max_tokens' => 1500,
        ]);
        
        // 4. Create post
        $post_id = Publisher::create_post([
            'title' => $queue_item->title,
            'content' => $this->format_content($article['content'], $queue_item),
            'campaign_id' => $queue_item->campaign_id,
        ]);
        
        // 5. Set featured image
        if ($thumbnail) {
            $this->set_featured_image($post_id, $thumbnail);
        }
        
        // 6. Add video embed
        $this->add_video_embed($post_id, $queue_item->metadata['video_url']);
        
        return $post_id;
    }
    
    private function build_prompt($queue_item) {
        return <<<PROMPT
Convert this video information into an engaging blog post:

Title: {$queue_item->title}
Description: {$queue_item->content}
Duration: {$queue_item->metadata['duration']}
Views: {$queue_item->metadata['view_count']}

Create a blog post that:
1. Summarizes the video content
2. Includes key takeaways
3. Adds relevant context
4. Ends with a call-to-action to watch the video
PROMPT;
    }
}
```

---

## Hooks Reference

### Actions

#### Campaign Hooks

```php
// After campaign created
do_action('abc_campaign_created', $campaign_id, $campaign_type);

// After campaign updated
do_action('abc_campaign_updated', $campaign_id, $old_meta, $new_meta);

// Before campaign deleted
do_action('abc_campaign_before_delete', $campaign_id);

// After campaign deleted
do_action('abc_campaign_deleted', $campaign_id);

// Campaign status changed
do_action('abc_campaign_status_changed', $campaign_id, $old_status, $new_status);
```

#### Discovery Hooks

```php
// Before discovery run
do_action('abc_before_discovery', $campaign_id);

// After discovery run
do_action('abc_after_discovery', $campaign_id, $discovered_count);

// Item added to queue
do_action('abc_queue_item_added', $queue_id, $item_data);

// Item removed from queue
do_action('abc_queue_item_removed', $queue_id);
```

#### Processing Hooks

```php
// Before processing
do_action('abc_before_process', $queue_item);

// After processing success
do_action('abc_after_process', $queue_item, $post_id);

// Processing failed
do_action('abc_process_failed', $queue_item, $error);

// Post published
do_action('abc_post_published', $post_id, $campaign_id, $queue_item);
```

#### AI Hooks

```php
// Before AI request
do_action('abc_before_ai_request', $provider, $params);

// After AI request
do_action('abc_after_ai_request', $provider, $response, $tokens_used);

// AI request failed
do_action('abc_ai_request_failed', $provider, $error);

// Key rotated
do_action('abc_key_rotated', $old_key_id, $new_key_id, $strategy);
```

### Filters

#### Content Filters

```php
// Modify AI prompt
apply_filters('abc_ai_prompt', $prompt, $queue_item, $campaign);

// Modify generated content
apply_filters('abc_generated_content', $content, $queue_item);

// Modify post before publishing
apply_filters('abc_post_data', $post_data, $queue_item, $campaign);

// Modify post title
apply_filters('abc_post_title', $title, $queue_item);

// Modify post excerpt
apply_filters('abc_post_excerpt', $excerpt, $queue_item);
```

#### Discovery Filters

```php
// Modify discovered items
apply_filters('abc_discovered_items', $items, $source, $campaign);

// Modify priority calculation
apply_filters('abc_item_priority', $priority, $item, $campaign);

// Modify queue item data
apply_filters('abc_queue_item_data', $data, $campaign);
```

#### Provider Filters

```php
// Add custom provider
apply_filters('abc_ai_providers', $providers);

// Modify provider models
apply_filters('abc_provider_models', $models, $provider);

// Modify provider pricing
apply_filters('abc_provider_pricing', $pricing, $provider);
```

### Usage Examples

**Example 1: Auto-assign category based on keywords**

```php
add_filter('abc_post_data', function($post_data, $queue_item) {
    $content = strtolower($queue_item->content);
    
    if (strpos($content, 'technology') !== false) {
        $post_data['post_category'] = [5]; // Tech category
    } elseif (strpos($content, 'health') !== false) {
        $post_data['post_category'] = [8]; // Health category
    }
    
    return $post_data;
}, 10, 2);
```

**Example 2: Send notification after publishing**

```php
add_action('abc_post_published', function($post_id, $campaign_id) {
    $post = get_post($post_id);
    $campaign = get_post($campaign_id);
    
    wp_mail(
        'admin@example.com',
        'New Post Published',
        "Campaign: {$campaign->post_title}\nPost: {$post->post_title}\nURL: " . get_permalink($post_id)
    );
}, 10, 2);
```

**Example 3: Custom content modification**

```php
add_filter('abc_generated_content', function($content, $queue_item) {
    // Add disclaimer at the end
    $disclaimer = "\n\n---\n\n*This content was automatically generated and curated by AI.*";
    return $content . $disclaimer;
}, 10, 2);
```

---

## Database Schema

### Tables

#### wp_abc_discovery_queue

```sql
CREATE TABLE wp_abc_discovery_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    campaign_type VARCHAR(50) NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_url TEXT NOT NULL,
    title TEXT,
    content LONGTEXT,
    content_hash VARCHAR(64),
    priority INT DEFAULT 5,
    status VARCHAR(20) DEFAULT 'pending',
    retry_count INT DEFAULT 0,
    post_id BIGINT UNSIGNED NULL,
    metadata TEXT,
    discovered_at DATETIME,
    processed_at DATETIME NULL,
    INDEX idx_campaign_status (campaign_id, status),
    INDEX idx_content_hash (content_hash),
    INDEX idx_priority (priority DESC)
);
```

#### wp_abc_api_keys

```sql
CREATE TABLE wp_abc_api_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    encrypted_key TEXT NOT NULL,
    is_active TINYINT DEFAULT 1,
    total_requests BIGINT DEFAULT 0,
    total_tokens BIGINT DEFAULT 0,
    daily_quota BIGINT DEFAULT 0,
    priority INT DEFAULT 5,
    last_used_at DATETIME NULL,
    created_by BIGINT UNSIGNED,
    created_at DATETIME,
    INDEX idx_provider_active (provider, is_active)
);
```

#### wp_abc_logs

```sql
CREATE TABLE wp_abc_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NULL,
    level VARCHAR(20) NOT NULL,
    category VARCHAR(50) NOT NULL,
    message TEXT,
    context TEXT,
    created_at DATETIME,
    INDEX idx_campaign_level (campaign_id, level),
    INDEX idx_created (created_at DESC)
);
```

---

## Best Practices

### 1. Error Handling

**Always use WP_Error**:
```php
if (!$result) {
    return new \WP_Error('error_code', 'Error message');
}
```

**Check for errors**:
```php
$result = $this->some_method();
if (is_wp_error($result)) {
    Logger::log($campaign_id, 'error', 'category', $result->get_error_message());
    return $result;
}
```

### 2. Sanitization & Escaping

**Input**: Always sanitize
```php
$title = sanitize_text_field($_POST['title']);
$url = esc_url_raw($_POST['url']);
$content = wp_kses_post($_POST['content']);
```

**Output**: Always escape
```php
echo esc_html($title);
echo esc_url($url);
echo esc_attr($attribute);
```

### 3. Database Queries

**Always use prepare()**:
```php
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}abc_discovery_queue WHERE campaign_id = %d",
    $campaign_id
));
```

### 4. Capability Checks

**Always check permissions**:
```php
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

### 5. Nonce Validation

**Always verify nonces**:
```php
if (!check_ajax_referer('abc_nonce', 'nonce', false)) {
    wp_send_json_error('Invalid nonce');
}
```

---

**Need help?** Join our [developer community](https://developers.autoblogcraft.ai) or [report issues](https://github.com/your-repo/autoblogcraft-ai/issues).
