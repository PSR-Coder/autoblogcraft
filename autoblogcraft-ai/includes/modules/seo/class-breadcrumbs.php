<?php
/**
 * Breadcrumbs Handler
 *
 * Generates SEO-friendly breadcrumb navigation.
 *
 * @package AutoBlogCraft\SEO
 * @since 2.0.0
 */

namespace AutoBlogCraft\Modules\SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Breadcrumbs class
 *
 * Responsibilities:
 * - Generate breadcrumb trail
 * - Output JSON-LD structured data
 * - Support custom post types and taxonomies
 *
 * @since 2.0.0
 */
class Breadcrumbs {

    /**
     * Breadcrumb items
     *
     * @var array
     */
    private $items = [];

    /**
     * Generate breadcrumbs for current page
     *
     * @since 2.0.0
     * @return array Breadcrumb items.
     */
    public function generate() {
        $this->items = [];

        // Always start with home
        $this->add_item(__('Home', 'autoblogcraft'), home_url('/'));

        if (is_singular()) {
            $this->generate_singular();
        } elseif (is_archive()) {
            $this->generate_archive();
        } elseif (is_search()) {
            $this->add_item(__('Search Results', 'autoblogcraft'), '');
        } elseif (is_404()) {
            $this->add_item(__('404 Not Found', 'autoblogcraft'), '');
        }

        return $this->items;
    }

    /**
     * Generate breadcrumbs for singular pages
     *
     * @since 2.0.0
     */
    private function generate_singular() {
        global $post;

        if (!$post) {
            return;
        }

        // Add post type archive link if not 'post'
        if ($post->post_type !== 'post') {
            $post_type_obj = get_post_type_object($post->post_type);
            if ($post_type_obj && $post_type_obj->has_archive) {
                $this->add_item(
                    $post_type_obj->labels->name,
                    get_post_type_archive_link($post->post_type)
                );
            }
        } else {
            // For regular posts, add blog page if set
            $page_for_posts = get_option('page_for_posts');
            if ($page_for_posts) {
                $this->add_item(
                    get_the_title($page_for_posts),
                    get_permalink($page_for_posts)
                );
            }
        }

        // Add categories for posts
        if ($post->post_type === 'post') {
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                $category = $categories[0];
                $this->add_category_hierarchy($category);
            }
        }

        // Add parent pages for hierarchical post types
        if (is_post_type_hierarchical($post->post_type) && $post->post_parent) {
            $this->add_parent_pages($post->post_parent);
        }

        // Add current page (no link)
        $this->add_item(get_the_title(), '');
    }

    /**
     * Generate breadcrumbs for archive pages
     *
     * @since 2.0.0
     */
    private function generate_archive() {
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            
            if ($term && $term->parent) {
                $this->add_term_hierarchy($term->parent, $term->taxonomy);
            }

            $this->add_item(single_term_title('', false), '');

        } elseif (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $post_type_obj = get_post_type_object($post_type);
            
            if ($post_type_obj) {
                $this->add_item($post_type_obj->labels->name, '');
            }

        } elseif (is_author()) {
            $this->add_item(
                sprintf(__('Author: %s', 'autoblogcraft'), get_the_author()),
                ''
            );

        } elseif (is_date()) {
            if (is_year()) {
                $this->add_item(get_the_date('Y'), '');
            } elseif (is_month()) {
                $this->add_item(get_the_date('Y'), get_year_link(get_the_date('Y')));
                $this->add_item(get_the_date('F'), '');
            } elseif (is_day()) {
                $this->add_item(get_the_date('Y'), get_year_link(get_the_date('Y')));
                $this->add_item(get_the_date('F'), get_month_link(get_the_date('Y'), get_the_date('m')));
                $this->add_item(get_the_date('d'), '');
            }
        }
    }

    /**
     * Add category hierarchy
     *
     * @since 2.0.0
     * @param object $category Category object.
     */
    private function add_category_hierarchy($category) {
        if ($category->parent) {
            $parent = get_category($category->parent);
            if ($parent) {
                $this->add_category_hierarchy($parent);
            }
        }

        $this->add_item($category->name, get_category_link($category->term_id));
    }

    /**
     * Add term hierarchy
     *
     * @since 2.0.0
     * @param int $term_id Term ID.
     * @param string $taxonomy Taxonomy name.
     */
    private function add_term_hierarchy($term_id, $taxonomy) {
        $term = get_term($term_id, $taxonomy);
        
        if (!$term || is_wp_error($term)) {
            return;
        }

        if ($term->parent) {
            $this->add_term_hierarchy($term->parent, $taxonomy);
        }

        $this->add_item($term->name, get_term_link($term));
    }

    /**
     * Add parent pages
     *
     * @since 2.0.0
     * @param int $post_id Parent post ID.
     */
    private function add_parent_pages($post_id) {
        $parent = get_post($post_id);
        
        if (!$parent) {
            return;
        }

        if ($parent->post_parent) {
            $this->add_parent_pages($parent->post_parent);
        }

        $this->add_item(get_the_title($parent), get_permalink($parent));
    }

    /**
     * Add breadcrumb item
     *
     * @since 2.0.0
     * @param string $title Item title.
     * @param string $url Item URL (empty for current page).
     */
    private function add_item($title, $url) {
        $this->items[] = [
            'title' => $title,
            'url' => $url,
        ];
    }

    /**
     * Get breadcrumb items
     *
     * @since 2.0.0
     * @return array Breadcrumb items.
     */
    public function get_items() {
        if (empty($this->items)) {
            $this->generate();
        }

        return $this->items;
    }

    /**
     * Render breadcrumbs HTML
     *
     * @since 2.0.0
     * @param array $args Display arguments.
     * @return string Breadcrumbs HTML.
     */
    public function render($args = []) {
        $defaults = [
            'separator' => ' &raquo; ',
            'container' => 'nav',
            'container_class' => 'abc-breadcrumbs',
            'list_class' => 'breadcrumb-list',
            'item_class' => 'breadcrumb-item',
            'link_class' => 'breadcrumb-link',
            'active_class' => 'active',
            'show_on_front' => false,
        ];

        $args = wp_parse_args($args, $defaults);

        // Don't show on front page unless explicitly enabled
        if (is_front_page() && !$args['show_on_front']) {
            return '';
        }

        $items = $this->get_items();

        if (empty($items)) {
            return '';
        }

        $output = sprintf('<%s class="%s">', esc_attr($args['container']), esc_attr($args['container_class']));
        $output .= sprintf('<ol class="%s">', esc_attr($args['list_class']));

        $item_count = count($items);
        
        foreach ($items as $index => $item) {
            $is_last = ($index === $item_count - 1);
            $item_classes = [$args['item_class']];

            if ($is_last) {
                $item_classes[] = $args['active_class'];
            }

            $output .= sprintf('<li class="%s">', esc_attr(implode(' ', $item_classes)));

            if (!empty($item['url']) && !$is_last) {
                $output .= sprintf(
                    '<a href="%s" class="%s">%s</a>',
                    esc_url($item['url']),
                    esc_attr($args['link_class']),
                    esc_html($item['title'])
                );
            } else {
                $output .= sprintf('<span>%s</span>', esc_html($item['title']));
            }

            if (!$is_last) {
                $output .= sprintf('<span class="separator">%s</span>', $args['separator']);
            }

            $output .= '</li>';
        }

        $output .= '</ol>';
        $output .= sprintf('</%s>', esc_attr($args['container']));

        return $output;
    }

    /**
     * Generate JSON-LD structured data
     *
     * @since 2.0.0
     * @return array Structured data array.
     */
    public function get_structured_data() {
        $items = $this->get_items();

        if (empty($items)) {
            return [];
        }

        $list_items = [];
        
        foreach ($items as $index => $item) {
            $list_item = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['title'],
            ];

            if (!empty($item['url'])) {
                $list_item['item'] = $item['url'];
            }

            $list_items[] = $list_item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list_items,
        ];
    }

    /**
     * Output JSON-LD structured data
     *
     * @since 2.0.0
     */
    public function output_structured_data() {
        $data = $this->get_structured_data();

        if (empty($data)) {
            return;
        }

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        echo '</script>' . "\n";
    }
}
