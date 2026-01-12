<?php
/**
 * Language Switcher
 *
 * Widget and shortcode for language switching functionality.
 *
 * @package AutoBlogCraft\Modules\Translation
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\Translation;

use AutoBlogCraft\Core\Logger;
use WP_Widget;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language Switcher class
 *
 * Provides language switching UI.
 *
 * @since 2.0.0
 */
class Language_Switcher extends WP_Widget {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        parent::__construct(
            'abc_language_switcher',
            __('ABC Language Switcher', 'autoblogcraft'),
            ['description' => __('Display language switcher for multilingual posts', 'autoblogcraft')]
        );

        $this->logger = Logger::instance();
    }

    /**
     * Register widget
     *
     * @since 2.0.0
     */
    public function register_widget() {
        register_widget('AutoBlogCraft\Modules\Translation\Language_Switcher');
    }

    /**
     * Render widget
     *
     * @since 2.0.0
     * @param array $args Widget arguments.
     * @param array $instance Widget instance.
     */
    public function widget($args, $instance) {
        if (!is_single()) {
            return;
        }

        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];
        }

        $this->render($instance);

        echo $args['after_widget'];
    }

    /**
     * Widget form
     *
     * @since 2.0.0
     * @param array $instance Widget instance.
     * @return void
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Languages', 'autoblogcraft');
        $style = !empty($instance['style']) ? $instance['style'] : 'dropdown';
        $show_flags = !empty($instance['show_flags']) ? $instance['show_flags'] : false;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'autoblogcraft'); ?>
            </label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('style')); ?>">
                <?php esc_html_e('Display Style:', 'autoblogcraft'); ?>
            </label>
            <select class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('style')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('style')); ?>">
                <option value="dropdown" <?php selected($style, 'dropdown'); ?>>
                    <?php esc_html_e('Dropdown', 'autoblogcraft'); ?>
                </option>
                <option value="list" <?php selected($style, 'list'); ?>>
                    <?php esc_html_e('List', 'autoblogcraft'); ?>
                </option>
                <option value="flags" <?php selected($style, 'flags'); ?>>
                    <?php esc_html_e('Flags Only', 'autoblogcraft'); ?>
                </option>
            </select>
        </p>
        <p>
            <input class="checkbox" 
                   type="checkbox" 
                   <?php checked($show_flags); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_flags')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_flags')); ?>" />
            <label for="<?php echo esc_attr($this->get_field_id('show_flags')); ?>">
                <?php esc_html_e('Show flags', 'autoblogcraft'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Update widget
     *
     * @since 2.0.0
     * @param array $new_instance New instance.
     * @param array $old_instance Old instance.
     * @return array Updated instance.
     */
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['style'] = !empty($new_instance['style']) ? sanitize_text_field($new_instance['style']) : 'dropdown';
        $instance['show_flags'] = !empty($new_instance['show_flags']);

        return $instance;
    }

    /**
     * Render language switcher
     *
     * @since 2.0.0
     * @param array $args Arguments.
     * @return string HTML output.
     */
    public function render($args = []) {
        if (!is_single()) {
            return '';
        }

        global $post;

        $current_lang = get_post_meta($post->ID, '_abc_language', true);
        $translations = get_post_meta($post->ID, '_abc_translations', true);

        if (empty($current_lang)) {
            return '';
        }

        $style = $args['style'] ?? 'dropdown';
        $show_flags = $args['show_flags'] ?? false;

        $languages = [
            $current_lang => [
                'url' => get_permalink($post),
                'name' => $this->get_language_name($current_lang),
                'current' => true,
            ],
        ];

        if (!empty($translations) && is_array($translations)) {
            foreach ($translations as $lang_code => $post_id) {
                $url = get_permalink($post_id);
                if ($url) {
                    $languages[$lang_code] = [
                        'url' => $url,
                        'name' => $this->get_language_name($lang_code),
                        'current' => false,
                    ];
                }
            }
        }

        if (count($languages) <= 1) {
            return '';
        }

        ob_start();

        echo '<div class="abc-language-switcher abc-language-switcher-' . esc_attr($style) . '">';

        switch ($style) {
            case 'dropdown':
                $this->render_dropdown($languages, $show_flags);
                break;

            case 'list':
                $this->render_list($languages, $show_flags);
                break;

            case 'flags':
                $this->render_flags($languages);
                break;
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Render dropdown style
     *
     * @since 2.0.0
     * @param array $languages Languages.
     * @param bool $show_flags Show flags.
     */
    private function render_dropdown($languages, $show_flags) {
        ?>
        <select class="abc-language-select" onchange="location = this.value;">
            <?php foreach ($languages as $code => $lang) : ?>
                <option value="<?php echo esc_url($lang['url']); ?>" 
                        <?php selected($lang['current']); ?>>
                    <?php if ($show_flags) : ?>
                        <?php echo $this->get_flag_emoji($code) . ' '; ?>
                    <?php endif; ?>
                    <?php echo esc_html($lang['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render list style
     *
     * @since 2.0.0
     * @param array $languages Languages.
     * @param bool $show_flags Show flags.
     */
    private function render_list($languages, $show_flags) {
        ?>
        <ul class="abc-language-list">
            <?php foreach ($languages as $code => $lang) : ?>
                <li class="<?php echo $lang['current'] ? 'current' : ''; ?>">
                    <a href="<?php echo esc_url($lang['url']); ?>">
                        <?php if ($show_flags) : ?>
                            <span class="flag"><?php echo $this->get_flag_emoji($code); ?></span>
                        <?php endif; ?>
                        <span class="language-name"><?php echo esc_html($lang['name']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Render flags style
     *
     * @since 2.0.0
     * @param array $languages Languages.
     */
    private function render_flags($languages) {
        ?>
        <div class="abc-language-flags">
            <?php foreach ($languages as $code => $lang) : ?>
                <a href="<?php echo esc_url($lang['url']); ?>" 
                   class="flag-link <?php echo $lang['current'] ? 'current' : ''; ?>"
                   title="<?php echo esc_attr($lang['name']); ?>">
                    <?php echo $this->get_flag_emoji($code); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Get language name
     *
     * @since 2.0.0
     * @param string $code Language code.
     * @return string Language name.
     */
    private function get_language_name($code) {
        $names = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ru' => 'Русский',
            'ja' => '日本語',
            'ko' => '한국어',
            'zh' => '中文',
            'ar' => 'العربية',
            'hi' => 'हिन्दी',
            'nl' => 'Nederlands',
            'sv' => 'Svenska',
            'no' => 'Norsk',
            'da' => 'Dansk',
            'fi' => 'Suomi',
            'pl' => 'Polski',
            'tr' => 'Türkçe',
            'el' => 'Ελληνικά',
        ];

        return $names[$code] ?? $code;
    }

    /**
     * Get flag emoji
     *
     * @since 2.0.0
     * @param string $code Language code.
     * @return string Flag emoji.
     */
    private function get_flag_emoji($code) {
        $flags = [
            'en' => '🇬🇧',
            'es' => '🇪🇸',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            'ru' => '🇷🇺',
            'ja' => '🇯🇵',
            'ko' => '🇰🇷',
            'zh' => '🇨🇳',
            'ar' => '🇸🇦',
            'hi' => '🇮🇳',
            'nl' => '🇳🇱',
            'sv' => '🇸🇪',
            'no' => '🇳🇴',
            'da' => '🇩🇰',
            'fi' => '🇫🇮',
            'pl' => '🇵🇱',
            'tr' => '🇹🇷',
            'el' => '🇬🇷',
        ];

        return $flags[$code] ?? '🌐';
    }
}
