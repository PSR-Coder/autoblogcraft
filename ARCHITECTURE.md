# AutoBlogCraft AI - v2.0 Architecture Specification

**Version**: 2.0.0 (WordPress Plugin Edition)  
**Last Updated**: January 7, 2026  
**Breaking Changes**: Yes - Complete redesign from v1.x  
**Target**: WordPress Plugin (Self-hosted) - SaaS deferred to future version

---

## 🎯 Core Design Principles

1. **Campaign Type Separation** - Website/YouTube/Amazon/News campaigns are distinct entities
2. **Per-Campaign Configuration** - No global AI settings; everything scoped to campaigns
3. **Two-Phase Pipeline** - Discovery (lightweight) → Processing (AI-heavy)
4. **Optional Modules** - SEO and Translation are campaign-level opt-ins
5. **Multi-Key Architecture** - Support multiple API keys per campaign with rotation
6. **Zero Backward Compatibility** - Clean slate design
7. **Enterprise-Grade Reliability** - Action Scheduler, rate limiting, legal compliance
8. **Security Hardened** - PBKDF2 encryption, input validation, XSS protection
9. **Market-Ready Features** - AI Humanizer, Featured Images, SEO plugin integration

---

## 📐 Campaign Type System

### Campaign Type Hierarchy

```php
abstract class Campaign_Base {
    protected $campaign_id;
    protected $campaign_type; // 'website' | 'youtube' | 'amazon' | 'news'
    
    abstract public function get_wizard_steps();
    abstract public function validate_source($source);
    abstract public function get_discovery_class();
    abstract public function get_processor_class();
}

class Website_Campaign extends Campaign_Base {
    // Handles RSS, Sitemap, Direct URLs
}

class YouTube_Campaign extends Campaign_Base {
    // Handles Channels, Playlists
}

class Amazon_Campaign extends Campaign_Base {
    // Handles Search, Category, Bestseller URLs
}

class News_Campaign extends Campaign_Base {
    // Handles SERP-based keyword discovery, real-time news
}
```

### Campaign Type Lock

- Selected during creation wizard (Step 1)
- Stored as `post_type` meta: `_campaign_type`
- **Immutable** after campaign creation
- Determines available wizard steps and settings

---

## 🗄️ Database Schema

### Table: `wp_abc_campaigns` (Custom Post Type Alternative)

**Decision**: Use WordPress Custom Post Type `abc_campaign` with extensive post meta.

**Post Meta Fields** (Common to all types):
```php
_campaign_type          // 'website' | 'youtube' | 'amazon' | 'news' (IMMUTABLE)
_campaign_status        // 'active' | 'paused' | 'archived'
_campaign_owner         // User ID (for multisite/agency use)
_discovery_interval     // Custom input: '5min', '1hr', '2hr', etc.
_last_discovery_run     // Timestamp
_last_processing_run    // Timestamp

// Module toggles
_seo_enabled            // boolean
_translation_enabled    // boolean

// Limits
_max_queue_size         // int
_max_posts_per_day      // int
_batch_size             // int (processing batch size)
_delay_seconds          // int (delay between posts)

// Featured Images - NEW
_featured_image_strategy // 'dalle3' | 'stable_diffusion' | 'unsplash' | 'source' | 'none'
_image_generation_prompt_template // Template for DALL-E 3
_unsplash_api_key_id    // FK to wp_abc_api_keys
_fallback_image_url     // Default if generation fails

// AI Humanizer - NEW
_humanizer_enabled      // boolean (toggle)
_humanizer_final_pass   // boolean (run humanizer after AI rewrite)

// Auto Internal Linking - NEW
_auto_internal_linking_enabled // boolean
_link_to_existing_posts // boolean (AI finds related posts and links)
_max_internal_links     // int (limit links per post)

// News-specific (only for news campaigns)
_news_keywords          // JSON array: ["AI", "Machine Learning"]
_news_exclude_keywords  // JSON array: ["COVID", "war"] - NEW
_news_freshness         // '1h' | '6h' | '24h' | '7d' - UPDATED
_news_geotargeting      // Country code: 'US', 'DE', 'JP' - NEW
_news_source_mode       // 'allow' | 'block'
_news_source_list       // JSON: ["cnn.com", "bbc.com", "techcrunch.com"]
_news_internal_links    // JSON: [{url, anchor_text, placement}] - UPDATED
_skip_if_no_news        // boolean
_content_summarize_only // boolean (snippet + AI summary instead of full scrape) - NEW
_serp_provider          // 'google_news' | 'serpapi' | 'newsapi' - NEW
_serp_fallback_providers // JSON array of fallback providers - NEW
_last_processed_urls    // JSON: recent URLs to prevent duplicates
```

### Table: `wp_abc_discovery_queue`

**Critical Improvements**:
- SHA256 content hash (collision-resistant)
- Priority-based processing (breaking news first)
- Error categorization for retry logic
- Auto-cleanup indexes for performance

```sql
CREATE TABLE wp_abc_discovery_queue (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned NOT NULL,
  campaign_type varchar(20) NOT NULL,
  
  -- Source identification
  source_type varchar(50) NOT NULL,      -- 'rss', 'sitemap', 'direct', 'channel', 'playlist', 'search', 'category'
  source_url text NOT NULL,
  item_id varchar(255),                   -- Unique ID: video_id, product_asin, url_hash
  
  -- Content preview
  title text,
  excerpt text,
  content longtext,                       -- Raw content before AI processing
  metadata longtext,                      -- JSON: { thumbnail, author, duration, price, rating, etc. }
  
  -- News-specific fields
  discovered_source_url text,             -- Original news article URL (for attribution)
  freshness_timestamp datetime,           -- When news was originally published
  attribution_data longtext,              -- JSON: {original_title, domain, author, publish_date}
  
  -- Queue management
  discovered_at datetime NOT NULL,
  created_at datetime NOT NULL,           -- For cleanup jobs (keep last 30 days)
  status varchar(20) DEFAULT 'pending',   -- 'pending' | 'processing' | 'completed' | 'failed' | 'skipped'
  priority tinyint DEFAULT 5,             -- 1-10 (10=highest, breaking news)
  
  -- Processing tracking
  processed_at datetime,
  post_id bigint(20) unsigned,            -- WordPress post ID after publishing
  retry_count int DEFAULT 0,
  last_error text,
  last_error_code varchar(50),            -- 'RATE_LIMITED', '404', 'INVALID_CONTENT', 'AI_ERROR'
  
  -- Deduplication (SHA256 - collision resistant)
  content_hash char(64),                  -- SHA256 (fixed length for performance)
  
  updated_at datetime,
  
  PRIMARY KEY (id),
  UNIQUE KEY unique_item (campaign_id, item_id),
  KEY campaign_type (campaign_type),
  KEY status (status),
  KEY priority (priority DESC),           -- Process high priority first
  KEY discovered_at (discovered_at),
  KEY created_at (created_at),            -- For cleanup job
  KEY content_hash (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Cleanup Strategy**:
- Nightly cron deletes rows WHERE `status='completed'` AND `created_at < NOW() - INTERVAL 30 DAY`
- Archive to `wp_abc_discovery_archive` for analytics (optional)

### Table: `wp_abc_api_keys`

**Security Improvements**:
- PBKDF2-based encryption (hardened key derivation)
- Quota tracking (prevent unexpected overages)
- Dynamic rate limit updates from provider APIs

```sql
CREATE TABLE wp_abc_api_keys (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  
  -- Key identification
  key_name varchar(100) NOT NULL,         -- User-friendly label: "OpenAI Primary", "Gemini Backup"
  provider varchar(50) NOT NULL,          -- 'openai', 'gemini', 'claude', 'deepseek', 'serpapi'
  provider_type varchar(30) DEFAULT 'ai', -- 'ai' | 'serp' | 'image' | 'humanizer'
  
  -- Encrypted storage (PBKDF2-hardened)
  api_key text NOT NULL,                  -- Encrypted using PBKDF2-derived key
  api_key_hash char(64) NOT NULL,         -- SHA256 for duplicate detection (fixed length)
  
  -- Additional config
  api_base_url varchar(255),              -- For custom endpoints
  organization_id varchar(100),           -- OpenAI org ID
  project_id varchar(100),
  
  -- Usage tracking
  usage_count int DEFAULT 0,
  total_tokens_used bigint DEFAULT 0,
  last_used_at datetime,
  
  -- Rate limiting (dynamic updates)
  rate_limit_per_minute int,              -- Provider-specific limits
  rate_limit_per_day int,
  rate_limit_updated_at datetime,         -- Last time limits were fetched from provider
  current_minute_count int DEFAULT 0,
  current_day_count int DEFAULT 0,
  rate_reset_at datetime,
  
  -- Quota management
  quota_limit bigint,                     -- Total quota (tokens/requests)
  quota_remaining bigint,                 -- Updated after each call
  quota_reset_at datetime,                -- When quota resets (monthly/daily)
  
  -- Status
  status varchar(20) DEFAULT 'active',    -- 'active' | 'disabled' | 'rate_limited' | 'invalid' | 'quota_exceeded'
  status_message text,                    -- Error messages
  failure_count int DEFAULT 0,            -- Auto-disable after 5 consecutive failures
  last_failure_at datetime,
  
  -- Metadata
  created_at datetime NOT NULL,
  created_by bigint(20) unsigned,         -- User ID
  updated_at datetime,
  
  PRIMARY KEY (id),
  UNIQUE KEY api_key_hash (api_key_hash),
  KEY provider (provider),
  KEY provider_type (provider_type),
  KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `wp_abc_campaign_ai_config`

**New Features**:
- AI Humanizer integration (anti-detection)
- Round-robin state persistence
- Internal linking configuration

```sql
CREATE TABLE wp_abc_campaign_ai_config (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned NOT NULL,
  
  -- AI Configuration
  provider varchar(50) NOT NULL,          -- 'openai', 'gemini', 'claude', 'deepseek'
  model varchar(100) NOT NULL,            -- 'gpt-4', 'gemini-pro', etc.
  
  -- Key assignment (multiple keys allowed)
  primary_key_id bigint(20) unsigned,     -- FK to wp_abc_api_keys
  fallback_key_ids text,                  -- JSON array of key IDs for rotation
  
  -- Load balancing
  key_rotation_strategy varchar(30) DEFAULT 'round_robin',  -- 'round_robin' | 'least_used' | 'failover_only'
  last_key_index int DEFAULT 0,           -- Persistent round-robin state
  
  -- Model parameters
  temperature decimal(3,2) DEFAULT 0.70,
  max_tokens int DEFAULT 2048,
  top_p decimal(3,2) DEFAULT 1.00,
  frequency_penalty decimal(3,2) DEFAULT 0.00,
  presence_penalty decimal(3,2) DEFAULT 0.00,
  
  -- Prompts
  system_prompt longtext,
  rewrite_prompt_template longtext,       -- Template with placeholders
  translation_prompt_template longtext,
  
  -- Custom instructions
  tone varchar(50),                       -- 'professional', 'casual', 'enthusiastic'
  audience varchar(50),                   -- 'general', 'technical', 'beginners'
  custom_instructions text,
  
  -- Author & Attribution (News campaigns)
  author_persona text,                    -- 'Tech journalist with 10 years at Forbes'
  attribution_style varchar(50),          -- 'inline' | 'footnote' | 'endnote' (removed 'none')
  
  -- Content rules
  min_word_count int DEFAULT 300,
  max_word_count int DEFAULT 2000,
  preserve_links boolean DEFAULT false,
  add_conclusion boolean DEFAULT true,
  
  -- AI Humanizer (anti-detection) - NEW
  humanizer_enabled boolean DEFAULT false,
  humanizer_level tinyint DEFAULT 5,      -- 1-10 (10=maximum humanization)
  humanizer_provider varchar(50),         -- 'undetectable_ai' | 'gpt4o_humanizer' | 'internal'
  humanizer_key_id bigint(20) unsigned,   -- FK to wp_abc_api_keys (if external service)
  
  -- Internal Linking - NEW
  internal_links longtext,                -- JSON: [{url, anchor_text, placement}]
  internal_links_mode varchar(30) DEFAULT 'regex',  -- 'regex' | 'ai_contextual'
  
  created_at datetime NOT NULL,
  updated_at datetime,
  
  PRIMARY KEY (id),
  UNIQUE KEY campaign_id (campaign_id),
  FOREIGN KEY (campaign_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE,
  FOREIGN KEY (primary_key_id) REFERENCES wp_abc_api_keys(ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `wp_abc_translation_cache`

**TTL Management**:
- Expire cache after 90 days of inactivity
- Nightly cleanup job purges expired entries

```sql
CREATE TABLE wp_abc_translation_cache (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  
  -- Translation key (deduplication)
  original_text_hash char(64) NOT NULL,     -- SHA256 of original text (fixed length)
  from_lang varchar(10) NOT NULL,
  to_lang varchar(10) NOT NULL,
  
  -- Content
  original_text longtext NOT NULL,
  translated_text longtext NOT NULL,
  
  -- Metadata
  provider varchar(50),                     -- Which AI provider did translation
  model varchar(100),
  tokens_used int,
  
  -- Cache management with TTL
  hit_count int DEFAULT 0,
  created_at datetime NOT NULL,
  last_used_at datetime,
  expires_at datetime,                      -- Auto-cleanup after expiration (90 days default)
  
  PRIMARY KEY (id),
  UNIQUE KEY cache_key (original_text_hash, from_lang, to_lang),
  KEY last_used_at (last_used_at),          -- For cache eviction
  KEY expires_at (expires_at)               -- For cleanup job
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Cleanup Job**: `DELETE FROM wp_abc_translation_cache WHERE expires_at < NOW()`

### Table: `wp_abc_logs`

```sql
CREATE TABLE wp_abc_logs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned,
  
  -- Log classification
  level varchar(20) NOT NULL,               -- 'debug' | 'info' | 'success' | 'warning' | 'error'
  category varchar(50) NOT NULL,            -- 'discovery' | 'processing' | 'ai' | 'cron' | 'system'
  
  -- Message
  message text NOT NULL,
  context text,                             -- JSON: additional data (TEXT instead of LONGTEXT)
  
  -- Tracing
  queue_item_id bigint(20) unsigned,        -- FK to discovery_queue
  post_id bigint(20) unsigned,
  
  -- Stack trace for errors
  stack_trace text,
  
  created_at datetime NOT NULL,
  
  PRIMARY KEY (id),
  KEY campaign_id (campaign_id),
  KEY level (level),
  KEY category (category),
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `wp_abc_seo_settings` (Per-Campaign)

```sql
CREATE TABLE wp_abc_seo_settings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned NOT NULL,
  
  -- SEO Configuration
  enabled boolean DEFAULT true,
  
  -- Default meta templates
  title_template varchar(255),              -- e.g., "{title} | {site_name}"
  description_template varchar(255),        -- e.g., "Learn about {title}..."
  
  -- Robots
  default_robots_index varchar(10) DEFAULT 'index',
  default_robots_follow varchar(10) DEFAULT 'follow',
  
  -- Schema.org
  schema_enabled boolean DEFAULT true,
  schema_type varchar(50) DEFAULT 'Article', -- 'Article', 'Product', 'VideoObject'
  
  -- Sitemap
  include_in_sitemap boolean DEFAULT true,
  sitemap_priority decimal(2,1) DEFAULT 0.5,
  sitemap_changefreq varchar(20) DEFAULT 'weekly',
  
  updated_at datetime,
  
  PRIMARY KEY (id),
  UNIQUE KEY campaign_id (campaign_id),
  FOREIGN KEY (campaign_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🏗️ File Structure (Refactored)

```
autoblogcraft-ai/
├─ autoblogcraft.php                      # Plugin bootstrap
├─ uninstall.php                          # Cleanup on uninstall
│
├─ includes/
│  │
│  ├─ core/
│  │  ├─ class-autoloader.php             # PSR-4 autoloader
│  │  ├─ class-activator.php              # Activation hooks
│  │  ├─ class-deactivator.php            # Deactivation hooks
│  │  ├─ class-plugin.php                 # Main plugin orchestrator
│  │  ├─ class-logger.php                 # Logging utility
│  │  ├─ class-rate-limiter.php           # Global API rate limiting (NEW)
│  │  └─ class-cleanup-manager.php        # Queue/cache cleanup jobs (NEW)
│  │
│  ├─ database/
│  │  ├─ class-installer.php              # Database table creation
│  │  ├─ class-migrator.php               # Version migrations
│  │  └─ class-schema-validator.php       # Validate schema integrity (NEW)
│  │
│  ├─ campaigns/
│  │  ├─ class-campaign-base.php          # Abstract base class
│  │  ├─ class-website-campaign.php       # Website campaign type
│  │  ├─ class-youtube-campaign.php       # YouTube campaign type
│  │  ├─ class-amazon-campaign.php        # Amazon campaign type
│  │  ├─ class-news-campaign.php          # News campaign type (SERP-based)
│  │  ├─ class-campaign-factory.php       # Factory pattern
│  │  └─ class-campaign-cloner.php        # Clone campaigns (NEW)
│  │
│  ├─ discovery/
│  │  ├─ class-discovery-manager.php      # Coordinates discovery
│  │  ├─ website/
│  │  │  ├─ class-rss-discoverer.php
│  │  │  ├─ class-sitemap-discoverer.php
│  │  │  └─ class-web-scraper.php
│  │  ├─ youtube/
│  │  │  ├─ class-channel-discoverer.php
│  │  │  └─ class-playlist-discoverer.php
│  │  ├─ amazon/
│  │  │  ├─ class-search-discoverer.php
│  │  │  ├─ class-category-discoverer.php
│  │  │  └─ class-bestseller-discoverer.php
│  │  └─ news/
│  │     ├─ class-serp-discoverer.php     # Google News API / SERP (legal)
│  │     ├─ class-newsapi-provider.php    # NewsAPI.org integration (NEW)
│  │     ├─ class-serpapi-provider.php    # SerpAPI integration (NEW)
│  │     ├─ class-provider-fallback.php   # Multi-provider failover (NEW)
│  │     ├─ class-freshness-filter.php    # 1h/6h/24h/7d filtering (UPDATED)
│  │     ├─ class-geotargeting-filter.php # Country-based filtering (NEW)
│  │     └─ class-source-validator.php    # Allow/block list logic
│  │
│  ├─ processing/
│  │  ├─ class-processing-manager.php     # Coordinates processing
│  │  ├─ class-queue-processor.php        # Processes queue items
│  │  ├─ class-content-cleaner.php        # HTML cleaning, extraction
│  │  ├─ class-ai-rewriter.php            # AI content generation
│  │  ├─ class-publisher.php              # WordPress post creation
│  │  ├─ class-featured-image-generator.php # DALL-E 3 / Unsplash (NEW)
│  │  ├─ class-humanizer.php              # AI humanization module (NEW)
│  │  ├─ class-internal-linker.php        # Auto internal linking (NEW)
│  │  └─ processors/
│  │     ├─ class-website-processor.php
│  │     ├─ class-youtube-processor.php
│  │     ├─ class-amazon-processor.php
│  │     └─ class-news-processor.php       # Attribution, persona, internal linking
│  │
│  ├─ ai/
│  │  ├─ class-ai-manager.php             # AI provider orchestration
│  │  ├─ class-key-manager.php            # API key CRUD, encryption
│  │  ├─ class-key-rotator.php            # Load balancing, failover
│  │  ├─ class-token-counter.php          # Token usage tracking
│  │  └─ providers/
│  │     ├─ class-base-provider.php       # Abstract provider
│  │     ├─ class-openai-provider.php
│  │     ├─ class-gemini-provider.php
│  │     ├─ class-claude-provider.php
│  │     └─ class-deepseek-provider.php
│  │
│  ├─ modules/
│  │  ├─ seo/
│  │  │  ├─ class-seo-module.php          # SEO module controller
│  │  │  ├─ class-meta-generator.php      # Title, description, keywords
│  │  │  ├─ class-sitemap-generator.php   # XML sitemap
│  │  │  ├─ class-schema-builder.php      # Schema.org markup
│  │  │  ├─ class-breadcrumbs.php         # Breadcrumb navigation
│  │  │  ├─ class-yoast-integration.php   # Yoast SEO filter hooks (NEW)
│  │  │  ├─ class-rankmath-integration.php # Rank Math filter hooks (NEW)
│  │  │  └─ class-aioseo-integration.php  # AIOSEO integration (NEW)
│  │  │
│  │  ├─ translation/
│  │  │  ├─ class-translation-module.php  # Translation controller
│  │  │  ├─ class-translator.php          # Core translation logic
│  │  │  ├─ class-translation-cache.php   # Cache management
│  │  │  ├─ class-hreflang-manager.php    # Hreflang tags
│  │  │  ├─ class-language-switcher.php   # Widget/shortcode
│  │  │  ├─ class-polylang-integration.php # Polylang compatibility (NEW)
│  │  │  ├─ class-wpml-integration.php    # WPML compatibility (NEW)
│  │  │  └─ class-language-detector.php   # Auto-detect source language (NEW)
│  │  │
│  │  └─ humanizer/                       # AI Humanizer module (NEW)
│  │     ├─ class-humanizer-module.php
│  │     ├─ class-undetectable-provider.php
│  │     └─ class-gpt4o-humanizer.php
│  │
│  ├─ cron/
│  │  ├─ class-cron-manager.php           # Action Scheduler setup (CRITICAL UPDATE)
│  │  ├─ class-discovery-job.php          # Discovery cron job
│  │  ├─ class-processing-job.php         # Processing cron job  
│  │  ├─ class-cleanup-job.php            # Queue cleanup job (NEW)
│  │  ├─ class-rate-limit-reset-job.php   # Reset rate limit counters (NEW)
│  │  └─ class-server-cron-detector.php   # Detect if server cron is active (NEW)
│  │
│  ├─ admin/
│  │  ├─ class-admin-menu.php             # Menu registration
│  │  ├─ class-admin-assets.php           # CSS/JS enqueue
│  │  ├─ class-admin-notices.php          # System warnings (NEW)
│  │  ├─ class-bulk-actions.php           # Bulk pause/resume/delete (NEW)
│  │  │
│  │  ├─ pages/
│  │  │  ├─ class-page-base.php           # Abstract page
│  │  │  ├─ class-dashboard-page.php      # Main dashboard
│  │  │  ├─ class-campaigns-page.php      # Campaign list (bulk actions)
│  │  │  ├─ class-campaign-detail-page.php # Single campaign (tabs)
│  │  │  ├─ class-api-keys-page.php       # API key management + health (UPDATED)
│  │  │  ├─ class-logs-page.php           # Global logs viewer
│  │  │  └─ class-settings-page.php       # Global settings (rate limits) (NEW)
│  │  │
│  │  └─ wizards/
│  │     ├─ class-wizard-base.php         # Abstract wizard
│  │     ├─ class-website-wizard.php      # Website campaign wizard
│  │     ├─ class-youtube-wizard.php      # YouTube campaign wizard
│  │     ├─ class-amazon-wizard.php       # Amazon campaign wizard
│  │     └─ class-news-wizard.php         # News campaign wizard
│  │
│  ├─ api/
│  │  ├─ class-rest-controller.php        # REST API endpoints
│  │  └─ endpoints/
│  │     ├─ class-campaigns-endpoint.php
│  │     ├─ class-queue-endpoint.php
│  │     └─ class-stats-endpoint.php
│  │
│  └─ helpers/
│     ├─ validation.php                   # Input validation (STRENGTHENED)
│     ├─ sanitization.php                 # Sanitization helpers (STRENGTHENED)
│     ├─ encryption.php                   # PBKDF2-based API key encryption (UPDATED)
│     ├─ hash-generator.php               # SHA256 content hashing (NEW)
│     ├─ utilities.php                    # General utilities
│     └─ constants.php                    # Plugin constants
│
├─ assets/
│  ├─ css/
│  │  ├─ admin.css                        # Global admin styles
│  │  ├─ wizard.css                       # Wizard-specific styles
│  │  └─ dashboard.css                    # Dashboard styles
│  │
│  └─ js/
│     ├─ admin.js                         # Global admin scripts
│     ├─ wizard.js                        # Wizard interactions
│     ├─ api-keys.js                      # API key management UI
│     └─ campaign-detail.js               # Campaign tabs
│
├─ templates/
│  ├─ admin/
│  │  ├─ dashboard.php
│  │  ├─ campaigns-list.php
│  │  └─ campaign-detail/
│  │     ├─ overview.php
│  │     ├─ sources.php
│  │     ├─ queue.php
│  │     ├─ posts.php
│  │     ├─ settings.php
│  │     └─ logs.php
│  │
│  ├─ wizards/
│  │  ├─ step-campaign-type.php           # Step 1: Select type
│  │  ├─ website/
│  │  │  ├─ step-sources.php
│  │  │  ├─ step-filters.php
│  │  │  └─ step-ai-config.php
│  │  ├─ youtube/
│  │  │  └─ ...
│  │  └─ amazon/
│  │     └─ ...
│  │
│  └─ partials/
│     ├─ header.php
│     ├─ footer.php
│     └─ notices.php
│
└─ languages/
   └─ autoblogcraft-ai.pot
```

---

## 🔄 Campaign Creation Workflow

### Wizard Flow

```
Step 1: Campaign Type Selection [LOCKED AFTER CREATION]
├─ Auto Blog from Website
├─ Auto Blog from YouTube
├─ Auto Affiliate Blog for Amazon
└─ AI News Intelligence (SERP-based)

↓

Step 2: Basic Information
├─ Campaign Name
├─ Target Language
├─ Country (for YouTube/Amazon)
└─ Description

↓

Step 3: Source Configuration [TYPE-SPECIFIC]

[Website Campaign]
├─ Add Sources (multiple)
│  ├─ RSS Feed URLs
│  ├─ Sitemap URLs
│  └─ Direct Website URLs
├─ Crawl Depth (for web URLs)
└─ Discovery Interval (custom input)

[YouTube Campaign]
├─ Add Sources
│  ├─ Channel URLs
│  └─ Playlist URLs
├─ Video Filters
│  ├─ Min Duration
│  ├─ Max Video Age
│  └─ Require Transcript
└─ Discovery Interval

[Amazon Campaign]
├─ Add Sources
│  ├─ Search URLs
│  ├─ Category URLs
│  └─ Bestseller Pages
├─ Product Filters
│  ├─ Price Range
│  ├─ Min Rating
│  └─ Availability
└─ Discovery Interval

[News Campaign]
├─ Add Keywords
│  ├─ Keywords: ["AI", "Machine Learning", "OpenAI"]
│  └─ Max Results Per Keyword: 5
├─ Freshness Filter
│  └─ 24 hours | 7 days | 30 days
├─ Source Control
│  ├─ Whitelist: cnn.com, bbc.com, techcrunch.com
│  └─ Blacklist: competitor.com, spam.com
├─ Skip if No News: [Checkbox]
└─ Discovery Interval (custom input)

↓

Step 4: Discovery Rules
├─ Max Queue Size
├─ Max Posts Per Day
├─ Include/Exclude Keywords
├─ Duplicate Detection Strategy
└─ Content Filters (min words, etc.)

↓

Step 5: AI Configuration
├─ Select Provider & Model
├─ Primary API Key (dropdown from vault)
├─ Fallback Keys (multi-select)
├─ Key Rotation Strategy
│  ├─ Round Robin
│  ├─ Least Used
│  └─ Failover Only
├─ Temperature / Max Tokens
├─ Prompt Templates
├─ Author Persona (News only): "Tech journalist, 10 years at Forbes"
├─ Attribution Style (News only): Inline | Footnote | Endnote | None
└─ Content Rules (word count, tone)

↓

Step 6: Internal Linking (News only - Optional)
├─ URL + Anchor Text Pairs:
│  ├─ https://mysite.com/ai-guide → "AI technology"
│  └─ https://mysite.com/services → "our consulting services"
└─ AI will contextually weave these into articles

↓

Step 7: Processing Settings
├─ Batch Size
├─ Delay Between Posts
├─ Post Status (Draft/Publish)
├─ Target Category
└─ Featured Image Strategy

↓

Step 7: Optional Modules
├─ Enable SEO Module? [Checkbox]
│  └─ SEO Settings (if enabled)
└─ Enable Translation? [Checkbox]
   └─ Translation Settings (if enabled)

↓

Step 8: Review & Create
├─ Summary of all settings
└─ [Create Campaign] button
```

---

## 📰 News Campaign Deep Dive

### SERP-Based Discovery Architecture

Unlike URL-based campaigns (Website/YouTube/Amazon), News Campaigns use **keyword-triggered SERP queries** to discover fresh content.

**Discovery Flow**:
```
Cron → Check Discovery Interval → Get News Keywords
  ↓
For Each Keyword:
  Query Google News API/SERP
    ↓
  Parse Results (Title, URL, Published Date, Source)
    ↓
  Apply Freshness Filter (_news_freshness: 1h, 6h, 24h, 7d)
    ↓
  Apply Source Validation (_news_sources: allow-list/block-list)
    ↓
  Check Duplicate (content_hash + item_id)
    ↓
  Queue Item with Attribution Data
```

**SERP_Discoverer Class Example**:
```php
<?php
namespace AutoBlogCraft\Discovery\News;

class SERP_Discoverer {
    
    private $campaign_id;
    private $keywords;
    private $freshness_window;
    private $source_control;
    
    public function discover() {
        $items = [];
        
        foreach ($this->keywords as $keyword) {
            // Query Google News API or SERP scraping service
            $results = $this->query_serp($keyword);
            
            foreach ($results as $article) {
                // Apply freshness filter
                if (!$this->is_fresh($article['published_at'])) {
                    continue;
                }
                
                // Apply source validation
                if (!$this->is_allowed_source($article['source_domain'])) {
                    continue;
                }
                
                // Extract snippet for preview
                $snippet = $this->extract_snippet($article['url']);
                
                $items[] = [
                    'source_type' => 'serp',
                    'source_url' => $article['url'], // Original news article URL
                    'item_id' => md5($article['url']), // Unique identifier
                    'title' => $article['title'],
                    'excerpt' => $snippet,
                    'raw_content' => '', // Will be scraped in processing phase
                    'metadata' => [
                        'keyword' => $keyword,
                        'published_at' => $article['published_at'],
                        'source_domain' => $article['source_domain'],
                        'source_title' => $article['source_name'],
                        'author' => $article['author'] ?? null,
                    ],
                    'discovered_source_url' => $article['url'], // For attribution
                    'freshness_timestamp' => strtotime($article['published_at']),
                    'attribution_data' => json_encode([
                        'title' => $article['title'],
                        'domain' => $article['source_domain'],
                        'author' => $article['author'] ?? 'Unknown',
                        'published' => $article['published_at'],
                    ]),
                ];
            }
        }
        
        return $items;
    }
    
    private function is_fresh($published_at) {
        $timestamp = strtotime($published_at);
        $cutoff = time() - $this->get_freshness_seconds();
        return $timestamp >= $cutoff;
    }
    
    private function is_allowed_source($domain) {
        $control = $this->source_control;
        
        if ($control['mode'] === 'allow') {
            return in_array($domain, $control['domains']);
        } elseif ($control['mode'] === 'block') {
            return !in_array($domain, $control['domains']);
        }
        
        return true; // No restrictions
    }
    
    private function query_serp($keyword) {
        // Integration with Google News API, SerpAPI, or custom scraper
        // Example: SerpAPI integration
        $api_key = get_option('abc_serpapi_key');
        $url = "https://serpapi.com/search.json?engine=google_news&q=" . urlencode($keyword);
        
        $response = wp_remote_get($url . "&api_key={$api_key}");
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        return $data['news_results'] ?? [];
    }
}
```

### Skip-If-No-News Logic

**Purpose**: Prevent generating posts when no fresh news is discovered for keywords during a discovery cycle.

**Implementation**:
```php
// In Discovery_Job::run()
$campaign_obj = Campaign_Factory::create($campaign);

if ($campaign_obj->get_type() === 'news') {
    $skip_if_no_news = get_post_meta($campaign->ID, '_skip_if_no_news', true);
    
    $discoverer = $campaign_obj->get_discovery_class();
    $items = $discoverer->discover();
    
    if (empty($items) && $skip_if_no_news) {
        Logger::log($campaign->ID, 'info', 'discovery', 
            'No fresh news found. Skipping this cycle per campaign settings.');
        
        // Update last run timestamp but don't queue anything
        update_post_meta($campaign->ID, '_last_discovery_run', time());
        continue; // Skip to next campaign
    }
    
    // Proceed with queuing if items found or skip_if_no_news=false
    foreach ($items as $item) {
        Discovery_Queue::add($item);
    }
}
```

### News Processor with Attribution

**Attribution Styles** (configured in AI Config):
1. **Inline**: "According to [TechCrunch](https://techcrunch.com/article), AI advancements..."
2. **Footnote**: Superscript numbers with links at bottom: "AI advancements<sup>[1]</sup>..."
3. **Endnote**: Full citation at end: "Source: TechCrunch - Title - Published 2025-01-06"
4. **None**: No attribution (use with caution for legal compliance)

**Processor Implementation**:
```php
<?php
namespace AutoBlogCraft\Processing\Processors;

class News_Processor {
    
    private $campaign_id;
    private $ai_manager;
    
    public function process($queue_item) {
        // 1. Scrape original article content
        $scraper = new Web_Scraper();
        $original_content = $scraper->extract_article($queue_item->source_url);
        
        // 2. Get AI configuration
        $ai_config = AI_Config::get($this->campaign_id);
        $provider = AI_Manager::get_provider($this->campaign_id);
        
        // 3. Build prompt with author persona
        $prompt = $this->build_news_prompt(
            $original_content,
            $ai_config->author_persona,
            $queue_item->metadata
        );
        
        // 4. AI rewrite with attribution context
        $rewritten = $provider->rewrite_content($original_content, [
            'prompt' => $prompt,
            'temperature' => $ai_config->temperature,
            'max_tokens' => $ai_config->max_tokens,
        ]);
        
        // 5. Apply attribution style
        $attributed_content = $this->apply_attribution(
            $rewritten,
            $queue_item->attribution_data,
            $ai_config->attribution_style
        );
        
        // 6. Inject internal links (if configured)
        $final_content = $this->inject_internal_links($attributed_content);
        
        // 7. Create WordPress post
        $post_id = Publisher::create_post([
            'title' => $queue_item->title,
            'content' => $final_content,
            'campaign_id' => $this->campaign_id,
            'source_url' => $queue_item->source_url,
            'metadata' => json_decode($queue_item->metadata, true),
        ]);
        
        return $post_id;
    }
    
    private function build_news_prompt($content, $persona, $metadata) {
        $keyword = $metadata['keyword'] ?? 'general news';
        $published = $metadata['published_at'] ?? 'recently';
        
        return <<<PROMPT
You are a {$persona}.

Original News Article Published {$published}:
{$content}

Your task:
1. Rewrite this news article in your unique voice while preserving factual accuracy.
2. Focus on the "{$keyword}" angle.
3. Add your expert analysis and insights.
4. Maintain journalistic integrity - cite sources, verify claims.
5. Write for an educated, curious audience.

Generate a comprehensive article (800-1200 words).
PROMPT;
    }
    
    private function apply_attribution($content, $attribution_json, $style) {
        $attr = json_decode($attribution_json, true);
        
        switch ($style) {
            case 'inline':
                return $this->apply_inline_attribution($content, $attr);
            case 'footnote':
                return $this->apply_footnote_attribution($content, $attr);
            case 'endnote':
                return $this->apply_endnote_attribution($content, $attr);
            default:
                return $content; // No attribution
        }
    }
    
    private function apply_inline_attribution($content, $attr) {
        // Add inline citation in first paragraph
        $source_link = sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            esc_url($attr['source_url']),
            esc_html($attr['domain'])
        );
        
        $citation = sprintf(
            '<p><em>This article is based on reporting by %s, published %s.</em></p>',
            $source_link,
            date('F j, Y', strtotime($attr['published']))
        );
        
        // Insert after first paragraph
        $paragraphs = explode('</p>', $content, 2);
        return $paragraphs[0] . '</p>' . $citation . ($paragraphs[1] ?? '');
    }
    
    private function inject_internal_links($content) {
        $links = get_post_meta($this->campaign_id, '_internal_links', true);
        
        if (empty($links)) {
            return $content;
        }
        
        foreach ($links as $link_data) {
            $anchor = $link_data['anchor_text'];
            $url = $link_data['url'];
            
            // Replace first occurrence of anchor text with link
            $content = preg_replace(
                '/\b' . preg_quote($anchor, '/') . '\b/',
                sprintf('<a href="%s">%s</a>', esc_url($url), $anchor),
                $content,
                1 // Only replace first match
            );
        }
        
        return $content;
    }
}
```

### Internal Link Injection

**Purpose**: Contextually weave predefined internal links into AI-generated news content.

**Campaign Meta Structure**:
```php
_internal_links = [
    [
        'url' => 'https://yoursite.com/ai-consulting',
        'anchor_text' => 'AI consulting services',
    ],
    [
        'url' => 'https://yoursite.com/ml-guide',
        'anchor_text' => 'machine learning fundamentals',
    ],
];
```

**AI Integration Strategy** (Future Enhancement):
Instead of regex replacement, send links to AI as context:
```php
$prompt .= "\n\nYour site offers these services (mention naturally if relevant):\n";
foreach ($internal_links as $link) {
    $prompt .= "- {$link['anchor_text']}: {$link['url']}\n";
}
```

AI will contextually integrate mentions, then Publisher replaces matched anchor text with `<a>` tags.

---

## ⚙️ Discovery vs Processing Architecture

### Discovery Pipeline (Lightweight, Frequent)

```php
class Discovery_Job {
    public function run() {
        // Get active campaigns due for discovery
        $campaigns = $this->get_due_campaigns();
        
        foreach ($campaigns as $campaign) {
            $campaign_obj = Campaign_Factory::create($campaign);
            $discoverer = $campaign_obj->get_discovery_class();
            
            // Discover items (NO AI usage)
            $items = $discoverer->discover();
            
            // Queue items
            foreach ($items as $item) {
                Discovery_Queue::add([
                    'campaign_id' => $campaign->ID,
                    'campaign_type' => $campaign_obj->get_type(),
                    'source_type' => $item['source_type'],
                    'source_url' => $item['source_url'],
                    'item_id' => $item['item_id'],
                    'title' => $item['title'],
                    'excerpt' => $item['excerpt'],
                    'content' => $item['raw_content'],
                    'metadata' => json_encode($item['metadata']),
                    'status' => 'pending',
                ]);
            }
            
            // Update last run
            update_post_meta($campaign->ID, '_last_discovery_run', time());
        }
    }
}
```

### Processing Pipeline (Heavy, AI-based)

```php
class Processing_Job {
    public function run() {
        // Get campaigns with pending queue items
        $campaigns = $this->get_campaigns_with_pending();
        
        foreach ($campaigns as $campaign) {
            $batch_size = get_post_meta($campaign->ID, '_batch_size', true) ?: 5;
            $delay = get_post_meta($campaign->ID, '_delay_seconds', true) ?: 60;
            
            // Get pending items
            $queue_items = Discovery_Queue::get_pending($campaign->ID, $batch_size);
            
            foreach ($queue_items as $item) {
                // Mark as processing
                Discovery_Queue::update_status($item->id, 'processing');
                
                try {
                    // Get campaign-specific processor
                    $campaign_obj = Campaign_Factory::create($campaign);
                    $processor = $campaign_obj->get_processor_class();
                    
                    // Process with AI
                    $post_id = $processor->process($item);
                    
                    // Update queue
                    Discovery_Queue::mark_completed($item->id, $post_id);
                    
                    // Log success
                    Logger::log($campaign->ID, 'success', 'processing', 
                        "Post created: {$post_id}");
                    
                } catch (Exception $e) {
                    // Retry logic
                    $retry_count = $item->retry_count + 1;
                    
                    if ($retry_count >= 3) {
                        Discovery_Queue::mark_failed($item->id, $e->getMessage());
                    } else {
                        Discovery_Queue::increment_retry($item->id);
                    }
                    
                    Logger::log($campaign->ID, 'error', 'processing', 
                        $e->getMessage(), ['queue_item_id' => $item->id]);
                }
                
                // Delay between posts
                sleep($delay);
            }
            
            update_post_meta($campaign->ID, '_last_processing_run', time());
        }
    }
}
```

---

## 🔐 API Key Management

### Encryption Strategy (PBKDF2-Hardened)

**CRITICAL SECURITY UPDATE**: Using PBKDF2 for key derivation instead of simple SHA256.

```php
class Key_Manager {
    
    /**
     * Encrypt API key using PBKDF2-derived encryption key
     * PBKDF2 prevents rainbow table attacks and provides key stretching
     */
    public function encrypt($plaintext) {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $ciphertext);
    }
    
    /**
     * Decrypt API key
     */
    public function decrypt($encrypted) {
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted);
        
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        
        return openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
    
    /**
     * Get encryption key using PBKDF2 (Password-Based Key Derivation Function 2)
     * Much more secure than simple hash()
     */
    private function get_encryption_key() {
        $salt = AUTH_KEY . SECURE_AUTH_KEY;
        
        // PBKDF2 with 100,000 iterations (recommended minimum)
        return hash_pbkdf2(
            'sha256',           // Hash algorithm
            $salt,              // Password/salt
            'AutoBlogCraft',    // Additional salt
            100000,             // Iterations (100k minimum for 2026)
            32,                 // Key length (256 bits)
            true                // Return raw binary
        );
    }
    
    /**
     * Generate SHA256 hash for duplicate detection
     * (separate from encryption)
     */
    public function hash_key($plaintext) {
        return hash('sha256', $plaintext);
    }
}
```

### Key Rotation Logic (Fixed Persistence)

**CRITICAL FIX**: Round-robin state now persists in database, not static variable.

```php
class Key_Rotator {
    
    /**
     * Get next API key based on rotation strategy
     */
    public function get_next_key($campaign_id, $provider) {
        global $wpdb;
        
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $strategy = $config->key_rotation_strategy;
        
        $key_ids = array_merge(
            [$config->primary_key_id],
            json_decode($config->fallback_key_ids, true) ?: []
        );
        
        // Filter out rate-limited or disabled keys
        $key_ids = $this->filter_available_keys($key_ids);
        
        if (empty($key_ids)) {
            throw new \Exception('No available API keys');
        }
        
        switch ($strategy) {
            case 'round_robin':
                return $this->round_robin($campaign_id, $key_ids);
            
            case 'least_used':
                return $this->least_used($key_ids);
            
            case 'failover_only':
                return $this->failover($key_ids);
        }
    }
    
    /**
     * Round-robin with PERSISTENT state (stored in DB)
     */
    private function round_robin($campaign_id, $key_ids) {
        global $wpdb;
        
        // Get last used index from database
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT last_key_index FROM {$wpdb->prefix}abc_campaign_ai_config WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $last_index = $config->last_key_index ?? 0;
        $next_index = ($last_index + 1) % count($key_ids);
        
        // Update index in database for next call
        $wpdb->update(
            "{$wpdb->prefix}abc_campaign_ai_config",
            ['last_key_index' => $next_index],
            ['campaign_id' => $campaign_id]
        );
        
        return $this->get_key_by_id($key_ids[$next_index]);
    }
    
    /**
     * Least used strategy with rate limit awareness
     */
    private function least_used($key_ids) {
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($key_ids), '%d'));
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abc_api_keys 
             WHERE id IN ($placeholders) 
             AND status = 'active'
             AND current_minute_count < rate_limit_per_minute
             AND current_day_count < rate_limit_per_day
             ORDER BY usage_count ASC 
             LIMIT 1",
            ...$key_ids
        ));
    }
    
    /**
     * Filter out rate-limited, disabled, or quota-exceeded keys
     */
    private function filter_available_keys($key_ids) {
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($key_ids), '%d'));
        
        $available = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}abc_api_keys 
             WHERE id IN ($placeholders)
             AND status = 'active'
             AND (quota_remaining IS NULL OR quota_remaining > 0)
             AND current_minute_count < rate_limit_per_minute
             AND current_day_count < rate_limit_per_day",
            ...$key_ids
        ));
        
        return $available;
    }
    
    /**
     * Increment usage counters after successful API call
     */
    public function increment_usage($key_id, $tokens_used = 0) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}abc_api_keys 
             SET usage_count = usage_count + 1,
                 total_tokens_used = total_tokens_used + %d,
                 current_minute_count = current_minute_count + 1,
                 current_day_count = current_day_count + 1,
                 quota_remaining = GREATEST(quota_remaining - %d, 0),
                 last_used_at = NOW()
             WHERE id = %d",
            $tokens_used,
            $tokens_used,
            $key_id
        ));
    }
}
```

---

## 🎨 Admin UI Architecture

### Campaign Detail Page (Tabs)

```php
class Campaign_Detail_Page extends Page_Base {
    
    public function render() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        $campaign = get_post($campaign_id);
        $campaign_obj = Campaign_Factory::create($campaign);
        
        $active_tab = $_GET['tab'] ?? 'overview';
        
        $tabs = [
            'overview' => 'Overview',
            'sources' => 'Sources',
            'queue' => 'Discovery Queue',
            'posts' => 'Published Posts',
            'ai' => 'AI Configuration',
            'settings' => 'Settings',
            'logs' => 'Logs',
        ];
        
        // SEO/Translation tabs only if enabled
        if ($campaign_obj->is_seo_enabled()) {
            $tabs['seo'] = 'SEO Settings';
        }
        if ($campaign_obj->is_translation_enabled()) {
            $tabs['translation'] = 'Translation';
        }
        
        include ABC_PLUGIN_DIR . 'templates/admin/campaign-detail.php';
    }
}
```

### API Key Management UI

```php
class API_Keys_Page extends Page_Base {
    
    public function render() {
        // List all keys grouped by provider
        $keys = API_Key::get_all_grouped();
        
        include ABC_PLUGIN_DIR . 'templates/admin/api-keys.php';
    }
    
    public function handle_add_key() {
        check_admin_referer('abc_add_api_key');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $key_name = sanitize_text_field($_POST['key_name']);
        $provider = sanitize_text_field($_POST['provider']);
        $api_key = sanitize_text_field($_POST['api_key']);
        
        // Validate key immediately
        $validator = new API_Key_Validator();
        $is_valid = $validator->validate($provider, $api_key);
        
        if (!$is_valid) {
            wp_die('Invalid API key');
        }
        
        // Encrypt and store
        $key_manager = new Key_Manager();
        $encrypted = $key_manager->encrypt($api_key);
        $hash = hash('sha256', $api_key);
        
        API_Key::create([
            'key_name' => $key_name,
            'provider' => $provider,
            'api_key' => $encrypted,
            'api_key_hash' => $hash,
            'status' => 'active',
            'created_by' => get_current_user_id(),
        ]);
        
        wp_redirect(add_query_arg('message', 'key_added', wp_get_referer()));
        exit;
    }
}
```

---

## 🧪 Module System

### Module Base Class

```php
abstract class Module_Base {
    protected $campaign_id;
    protected $enabled = false;
    
    abstract public function init();
    abstract public function get_settings_schema();
    abstract public function apply_to_post($post_id, $content);
    
    public function is_enabled() {
        return $this->enabled;
    }
}
```

### SEO Module

```php
class SEO_Module extends Module_Base {
    
    public function init() {
        if (!$this->enabled) return;
        
        add_filter('wp_head', [$this, 'output_meta_tags']);
        add_filter('document_title_parts', [$this, 'filter_title']);
    }
    
    public function apply_to_post($post_id, $content) {
        $config = SEO_Settings::get($this->campaign_id);
        
        // Generate title
        $title = $this->generate_title($content['title'], $config);
        update_post_meta($post_id, '_seo_title', $title);
        
        // Generate description
        $description = $this->generate_description($content['excerpt'], $config);
        update_post_meta($post_id, '_seo_description', $description);
        
        // Schema.org markup
        if ($config->schema_enabled) {
            $schema = $this->build_schema($post_id, $content);
            update_post_meta($post_id, '_seo_schema', json_encode($schema));
        }
        
        return $content;
    }
}
```

### Translation Module

```php
class Translation_Module extends Module_Base {
    
    public function apply_to_post($post_id, $content) {
        $config = Translation_Settings::get($this->campaign_id);
        
        if (!$config->enabled) {
            return $content;
        }
        
        $target_languages = $config->target_languages; // ['es', 'fr', 'de']
        
        foreach ($target_languages as $lang) {
            // Check cache first
            $cached = Translation_Cache::get(
                $content['title'],
                $content['source_lang'],
                $lang
            );
            
            if ($cached) {
                $translated_title = $cached->translated_text;
            } else {
                // Translate via AI
                $translator = new Translator($this->campaign_id);
                $translated_title = $translator->translate(
                    $content['title'],
                    $content['source_lang'],
                    $lang
                );
                
                // Cache result
                Translation_Cache::set(
                    $content['title'],
                    $translated_title,
                    $content['source_lang'],
                    $lang
                );
            }
            
            // Create translated post
            $translated_post_id = wp_insert_post([
                'post_title' => $translated_title,
                'post_content' => $translator->translate($content['content'], $content['source_lang'], $lang),
                'post_status' => 'publish',
                'post_type' => 'post',
            ]);
            
            // Link posts with hreflang
            Hreflang_Manager::link_posts($post_id, $translated_post_id, $lang);
        }
        
        return $content;
    }
}
```

---

## 🔧 WordPress Coding Standards

All code must follow:

### Security Checklist

```php
// ✅ Nonce verification
if (!wp_verify_nonce($_POST['_wpnonce'], 'action_name')) {
    wp_die('Invalid nonce');
}

// ✅ Capability checks
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// ✅ Input sanitization
$campaign_name = sanitize_text_field($_POST['campaign_name']);
$interval = absint($_POST['interval']);
$urls = array_map('esc_url_raw', $_POST['urls']);

// ✅ Output escaping
echo esc_html($campaign_name);
echo '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>';

// ✅ Database queries
$wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id);

// ✅ WP_Error usage
if (is_wp_error($result)) {
    Logger::log($campaign_id, 'error', 'ai', $result->get_error_message());
    return false;
}
```

### Singleton Pattern

```php
class Example_Manager {
    protected static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    protected function __construct() {
        // Protected constructor
    }
}
```

---

## 📊 Implementation Priority

### Phase 1: Foundation (Week 1-2)
- ✅ Database schema creation
- ✅ Autoloader + core classes
- ✅ Campaign base class + factory
- ✅ API key encryption + storage

### Phase 2: Discovery System (Week 2-3)
- ✅ Website discoverers (RSS, Sitemap, Web)
- ✅ YouTube discoverers
- ✅ Amazon discoverers
- ✅ Discovery queue management
- ✅ Discovery cron job

### Phase 3: Processing System (Week 3-4)
- ✅ AI provider refactor (keep base pattern)
- ✅ Key rotation logic
- ✅ Processing queue processor
- ✅ Campaign-specific processors
- ✅ Processing cron job

### Phase 4: Admin UI (Week 4-5)
- ✅ Campaign wizard (all types)
- ✅ Campaign detail page (tabs)
- ✅ API key management
- ✅ Dashboard + stats

### Phase 5: Modules (Week 5-6)
- ✅ SEO module (refactor current)
- ✅ Translation module (refactor current)
- ✅ Module enable/disable per campaign

### Phase 6: Testing & Polish (Week 6)
- ✅ End-to-end testing
- ✅ Documentation
- ✅ Performance optimization

---

---

## ⏰ ACTION SCHEDULER INTEGRATION (CRITICAL)

**WHY**: WP-Cron is unreliable on 80% of hosts. Action Scheduler is battle-tested by WooCommerce.

**Implementation**: Bundle Action Scheduler library with plugin, schedule recurring actions for discovery, processing, cleanup, and rate limit resets. See `includes/cron/class-cron-manager.php` for full implementation.

**Server Cron Detection**: Add admin notice if server cron is not configured. Check if DISABLE_WP_CRON is defined or if last Action Scheduler run was > 10 minutes ago.

---

## 🚦 GLOBAL RATE LIMITING (CRITICAL)

**WHY**: 50 campaigns × 5 batch size = 250 simultaneous AI calls → rate limits + $1,000+ bills.

**Implementation**: Use WordPress Object Cache (Redis/Memcached compatible):
- Track `abc_running_campaigns` (max 3 concurrent)
- Track `abc_concurrent_ai_calls` (max 10 concurrent)
- Processing job checks limits before starting campaigns
- Fallback: `sleep(5)` and retry if limit reached

**Global Settings**:
```php
_max_concurrent_campaigns // Default: 3
_max_concurrent_ai_calls  // Default: 10
```

---

## 🖼️ FEATURED IMAGE PIPELINE

**Strategies** (per campaign):
1. **DALL-E 3**: Generate from title using prompt template
2. **Stable Diffusion**: Self-hosted image generation
3. **Unsplash**: Fetch based on keywords from title
4. **Source**: Extract first image from original content
5. **Fallback**: Use default campaign image

**Implementation**: `Featured_Image_Generator` class downloads and attaches images, sets as post thumbnail.

---

## 🧠 AI HUMANIZER MODULE

**Providers**:
- **Undetectable.ai**: External API (subscription required)
- **GPT-4o Humanizer**: Use GPT-4o with special prompts
- **Internal**: Pattern-based humanization

**Levels**: 1-10 slider (10 = maximum humanization)
**Usage**: Optional final pass after AI rewrite

---

## ⚖️ LEGAL COMPLIANCE (NEWS CAMPAIGNS)

### Snippet-Only Mode
**Problem**: Full article scraping violates ToS/copyright  
**Solution**: 
- Extract 200-300 char snippet from SERP
- AI expands snippet into full article with analysis
- Always include attribution

### SERP Provider Fallback
**Chain**: Google News API → NewsAPI.org → SerpAPI → ZenSERP  
**Implementation**: Loop through providers until one succeeds

### Attribution Enforcement
**Removed**: "None" attribution option  
**Required**: Inline, Footnote, or Endnote attribution

---

## 🔗 AUTO INTERNAL LINKING

**v2.0 Approach** (Regex-based):
- Find related posts by tags/categories
- Link first occurrence of post title in content
- Word boundary matching to prevent partial matches
- Limit: 3 links per post (configurable)

**v2.1 Approach** (AI-driven):
- Send existing post titles to AI in prompt
- AI contextually integrates mentions
- Publisher converts mentions to links

---

## 🛡️ SECURITY IMPROVEMENTS

### Input Validation
- All wizard inputs: `filter_var($url, FILTER_VALIDATE_URL)`
- RSS URLs: `preg_match('/\.xml$|\/feed\//i', $url)`
- JSON meta fields: `json_decode()` validation on save

### XSS Protection
- ALL template outputs: `esc_html()`, `esc_attr()`, `esc_url()`
- Never use `echo $_POST` or `echo $variable` without escaping

### Encryption (PBKDF2)
```php
hash_pbkdf2('sha256', $salt, 'AutoBlogCraft', 100000, 32, true)
```
- 100,000 iterations (2026 minimum)
- Prevents rainbow table attacks

### Nonce Verification
- All form submissions: `wp_verify_nonce($_POST['_wpnonce'], 'action_name')`
- All AJAX calls: `check_ajax_referer('action_name')`

---

## 🔧 BULK OPERATIONS

**Campaign List Page**:
- Bulk Actions dropdown: Pause | Resume | Delete | Clone
- Select multiple campaigns with checkboxes
- Confirm dialog for destructive actions

**Queue Management**:
- Retry Failed Items button (resets status to 'pending')
- Bulk Delete Completed Items (frees up space)

---

## 📊 CLEANUP JOBS

### Discovery Queue Cleanup
**Schedule**: Nightly at 3 AM  
**Action**: Delete rows WHERE `status='completed'` AND `created_at < NOW() - INTERVAL 30 DAY`

### Translation Cache Cleanup
**Schedule**: Nightly at 3 AM  
**Action**: Delete rows WHERE `expires_at < NOW()`

### Rate Limit Reset
**Schedule**: Every minute  
**Action**: Reset `current_minute_count` if `rate_reset_at < NOW()`

---

## 🎯 SEO PLUGIN INTEGRATION

**Strategy**: Hook into existing plugins instead of competing

### Yoast SEO
```php
add_filter('wpseo_title', [$this, 'filter_title']);
add_filter('wpseo_metadesc', [$this, 'filter_description']);
```

### Rank Math
```php
add_filter('rank_math/title', [$this, 'filter_title']);
add_filter('rank_math/description', [$this, 'filter_description']);
```

### All-in-One SEO
```php
add_filter('aioseo_title', [$this, 'filter_title']);
add_filter('aioseo_description', [$this, 'filter_description']);
```

**Settings**: "Override External SEO Plugin" toggle per campaign

---

## 🌐 TRANSLATION PLUGIN INTEGRATION

### Polylang
- Detect if Polylang is active
- Create translated posts as language variants: `pll_save_post_translations()`
- Link posts with `pll_set_post_language()`

### WPML
- Use `wpml_add_translatable_content()` API
- Set language with `ICL_LANGUAGE_CODE` meta

**Auto-detect Source Language**: Use `franc-php` library to detect original language before translating

---

## 📋 IMPLEMENTATION PRIORITIES (UPDATED)

### Phase 1: Critical Fixes (Week 1) - BLOCKERS
1. ✅ Action Scheduler migration
2. ✅ Global rate limiting
3. ✅ Database schema updates (SHA256, priority, TTL, etc.)
4. ✅ PBKDF2 encryption
5. ✅ Input validation & XSS protection

### Phase 2: Legal Compliance (Week 2)
1. ✅ Snippet-only mode for news
2. ✅ SERP provider fallback chain
3. ✅ Attribution enforcement (remove "none")
4. ✅ Legal disclaimers in wizards

### Phase 3: Market Features (Week 3-4)
1. ✅ Featured image pipeline (DALL-E 3 + Unsplash)
2. ✅ AI Humanizer module
3. ✅ Auto internal linking
4. ✅ Bulk operations
5. ✅ Cleanup jobs

### Phase 4: Integrations (Week 5)
1. ✅ Yoast/Rank Math/AIOSEO integration
2. ✅ Polylang/WPML integration
3. ✅ Key health dashboard
4. ✅ Campaign cloning

### Phase 5: Testing & Polish (Week 6)
1. ✅ Security audit
2. ✅ Load testing (1,000 concurrent users)
3. ✅ Unit tests (PHPUnit)
4. ✅ Documentation
5. ✅ Beta testing (10+ users)

---

## 🚀 Next Steps

1. **Review this specification** - Architecture now includes all critical fixes
2. **Generate migration scripts** - Database table creation SQL with new schema
3. **Create implementation checklist** - File-by-file breakdown with priorities
4. **Begin Phase 1** - Critical fixes (Action Scheduler, rate limiting, security)

**SCOPE**: WordPress Plugin (Self-hosted)  
**TARGET**: CodeCanyon / WordPress.org  
**TIMELINE**: 6 weeks to v2.0 launch  
**FUTURE**: SaaS platform (v3.0) after plugin validation

**READY TO PROCEED WITH IMPLEMENTATION?**
