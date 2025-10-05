<?php
/**
 * Custom Feed Endpoints
 *
 * Allows users to create custom feeds via admin UI with filtering options
 *
 * @package BetterFeed
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Feeds class
 */
class BF_Custom_Feeds {
    
    /**
     * Class instance
     * 
     * @var BF_Custom_Feeds
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Custom_Feeds
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Add rewrite rules for custom feeds
        add_action('init', array($this, 'add_custom_feed_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_feed_request'));
        
        // Admin hooks - now integrated into main BetterFeed settings
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_bf_save_custom_feed', array($this, 'save_custom_feed'));
        add_action('admin_post_bf_delete_custom_feed', array($this, 'delete_custom_feed'));
        
        // Flush rewrite rules when needed
        add_action('wp_loaded', array($this, 'maybe_flush_rewrite_rules'));
    }
    
    /**
     * Add rewrite rules for custom feeds
     */
    public function add_custom_feed_rules() {
        $custom_feeds = get_option('bf_custom_feeds', array());
        
        foreach ($custom_feeds as $feed) {
            if (empty($feed['slug']) || empty($feed['enabled'])) {
                continue;
            }
            
            add_rewrite_rule(
                '^feed/' . $feed['slug'] . '/?$',
                'index.php?bf_custom_feed=' . $feed['slug'],
                'top'
            );
        }
    }
    
    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'bf_custom_feed';
        return $vars;
    }
    
    /**
     * Handle custom feed requests
     */
    public function handle_custom_feed_request() {
        $feed_slug = get_query_var('bf_custom_feed');
        if (!$feed_slug) {
            return;
        }
        
        $custom_feeds = get_option('bf_custom_feeds', array());
        $feed_config = null;
        
        foreach ($custom_feeds as $feed) {
            if ($feed['slug'] === $feed_slug) {
                $feed_config = $feed;
                break;
            }
        }
        
        if (!$feed_config || empty($feed_config['enabled'])) {
            return;
        }
        
        // Set proper headers
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'));
        
        // Generate custom feed
        $this->generate_custom_feed($feed_config);
        exit;
    }
    
    /**
     * Generate custom feed
     */
    private function generate_custom_feed($config) {
        // Start output buffering
        ob_start();
        
        // RSS header
        echo '<?xml version="1.0" encoding="' . esc_attr(get_option('blog_charset')) . '"?>' . "\n";
        echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" xmlns:slash="http://purl.org/rss/1.0/modules/slash/">' . "\n";
        echo '<channel>' . "\n";
        
        // Channel info
        $title = !empty($config['title']) ? $config['title'] : get_bloginfo('name');
        $description = !empty($config['description']) ? $config['description'] : get_bloginfo('description');
        $feed_url = home_url('/feed/' . $config['slug'] . '/');
        
        echo '<title><![CDATA[' . esc_html($title) . ']]></title>' . "\n";
        echo '<description><![CDATA[' . esc_html($description) . ']]></description>' . "\n";
        echo '<link>' . esc_url(home_url('/')) . '</link>' . "\n";
        echo '<atom:link href="' . esc_url($feed_url) . '" rel="self" type="application/rss+xml" />' . "\n";
        echo '<language>' . esc_html(get_locale()) . '</language>' . "\n";
        echo '<lastBuildDate>' . esc_html(gmdate('r')) . '</lastBuildDate>' . "\n";
        echo '<sy:updatePeriod>hourly</sy:updatePeriod>' . "\n";
        echo '<sy:updateFrequency>1</sy:updateFrequency>' . "\n";
        echo '<generator>BetterFeed/1.0</generator>' . "\n";
        
        // Get posts based on configuration
        $posts = $this->get_custom_feed_posts($config);
        
        foreach ($posts as $post) {
            $this->output_feed_item($post);
        }
        
        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
        
        // Get output and send
        $output = ob_get_clean();
        echo wp_kses_post($output);
    }
    
    /**
     * Get posts for custom feed
     */
    private function get_custom_feed_posts($config) {
        // Create cache key based on configuration
        $cache_key = 'bf_custom_feed_posts_' . md5(serialize($config));
        $posts = wp_cache_get($cache_key, 'betterfeed');
        
        if (false !== $posts) {
            return $posts;
        }
        $args = array(
            'post_status' => 'publish',
            'numberposts' => !empty($config['limit']) ? intval($config['limit']) : 10,
            'suppress_filters' => false
        );
        
        // Post types
        if (!empty($config['post_types'])) {
            $args['post_type'] = array_map('sanitize_text_field', $config['post_types']);
        } else {
            $args['post_type'] = 'post';
        }
        
        // Categories
        if (!empty($config['categories'])) {
            $args['category__in'] = array_map('intval', $config['categories']);
        }
        
        // Tags
        if (!empty($config['tags'])) {
            $args['tag__in'] = array_map('intval', $config['tags']);
        }
        
        // Custom taxonomies
        if (!empty($config['taxonomies'])) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Query is cached for 15 minutes
            $tax_query = array('relation' => 'AND');
            
            foreach ($config['taxonomies'] as $taxonomy => $terms) {
                if (!empty($terms)) {
                    $tax_query[] = array(
                        'taxonomy' => sanitize_text_field($taxonomy),
                        'field' => 'term_id',
                        'terms' => array_map('intval', $terms)
                    );
                }
            }
            
            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }
        }
        
        // Date range
        if (!empty($config['date_from'])) {
            $args['date_query']['after'] = sanitize_text_field($config['date_from']);
        }
        
        if (!empty($config['date_to'])) {
            $args['date_query']['before'] = sanitize_text_field($config['date_to']);
        }
        
        if (isset($args['date_query'])) {
            $args['date_query']['inclusive'] = true;
        }
        
        // Order
        if (!empty($config['orderby'])) {
            $args['orderby'] = sanitize_text_field($config['orderby']);
        }
        
        if (!empty($config['order'])) {
            $args['order'] = strtoupper(sanitize_text_field($config['order']));
        }
        
        $posts = get_posts($args);
        
        // Cache for 15 minutes (shorter than other caches since feeds change more frequently)
        wp_cache_set($cache_key, $posts, 'betterfeed', 15 * MINUTE_IN_SECONDS);
        
        return $posts;
    }
    
    /**
     * Output feed item
     */
    private function output_feed_item($post) {
        echo '<item>' . "\n";
        echo '<title><![CDATA[' . esc_html(get_the_title($post->ID)) . ']]></title>' . "\n";
        echo '<link>' . esc_url(get_permalink($post->ID)) . '</link>' . "\n";
        echo '<comments>' . esc_url(get_comments_link($post->ID)) . '</comments>' . "\n";
        echo '<pubDate>' . esc_html(get_post_time('r', true, $post)) . '</pubDate>' . "\n";
        echo '<dc:creator><![CDATA[' . esc_html(get_the_author_meta('display_name', $post->post_author)) . ']]></dc:creator>' . "\n";
        
        // Categories
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            echo '<category><![CDATA[' . esc_html($category->name) . ']]></category>' . "\n";
        }
        
        echo '<guid isPermaLink="false">' . esc_url(get_permalink($post->ID)) . '</guid>' . "\n";
        
        // Description
        $excerpt = get_the_excerpt($post);
        if ($excerpt) {
            echo '<description><![CDATA[' . esc_html($excerpt) . ']]></description>' . "\n";
        }
        
        // Content
        $content = apply_filters('the_content', get_the_content(null, false, $post));
        $content = str_replace(']]>', ']]&gt;', $content);
        echo '<content:encoded><![CDATA[' . wp_kses_post($content) . ']]></content:encoded>' . "\n";
        
        echo '</item>' . "\n";
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            esc_html__('Custom Feeds', 'betterfeed'),
            esc_html__('Custom Feeds', 'betterfeed'),
            'manage_options',
            'bf-custom-feeds',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $custom_feeds = get_option('bf_custom_feeds', array());
        
        if (isset($_POST['action']) && $_POST['action'] === 'add_feed' && 
            isset($_POST['bf_feed_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bf_feed_nonce'])), 'bf_add_feed')) {
            $this->handle_add_feed();
            $custom_feeds = get_option('bf_custom_feeds', array());
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Custom Feeds', 'betterfeed'); ?></h1>
            
            <div class="bf-custom-feeds-admin">
                <div class="bf-feeds-list">
                    <h2><?php esc_html_e('Existing Feeds', 'betterfeed'); ?></h2>
                    
                    <?php if (empty($custom_feeds)): ?>
                        <p><?php esc_html_e('No custom feeds created yet.', 'betterfeed'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Title', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Slug', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('URL', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Status', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Actions', 'betterfeed'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($custom_feeds as $index => $feed): ?>
                                    <tr>
                                        <td><?php echo esc_html($feed['title']); ?></td>
                                        <td><code><?php echo esc_html($feed['slug']); ?></code></td>
                                        <td><a href="<?php echo esc_url(home_url('/feed/' . $feed['slug'] . '/')); ?>" target="_blank"><?php echo esc_url(home_url('/feed/' . $feed['slug'] . '/')); ?></a></td>
                                        <td><?php echo !empty($feed['enabled']) ? '<span style="color: green;">✓ Enabled</span>' : '<span style="color: red;">✗ Disabled</span>'; ?></td>
                                        <td>
                                            <a href="?page=bf-custom-feeds&edit=<?php echo esc_attr($index); ?>" class="button button-small"><?php esc_html_e('Edit', 'betterfeed'); ?></a>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=bf_delete_custom_feed&feed_index=' . $index), 'bf_delete_feed')); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure?', 'betterfeed'); ?>')"><?php esc_html_e('Delete', 'betterfeed'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="bf-add-feed">
                    <h2><?php esc_html_e('Add New Feed', 'betterfeed'); ?></h2>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('bf_add_feed', 'bf_feed_nonce'); ?>
                        <input type="hidden" name="action" value="add_feed">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Feed Title', 'betterfeed'); ?></th>
                                <td>
                                    <input type="text" name="feed_title" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('Title for your custom feed', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Feed Slug', 'betterfeed'); ?></th>
                                <td>
                                    <input type="text" name="feed_slug" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('URL slug (e.g., "news" creates /feed/news/)', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Description', 'betterfeed'); ?></th>
                                <td>
                                    <textarea name="feed_description" class="large-text" rows="3"></textarea>
                                    <p class="description"><?php esc_html_e('Optional description for the feed', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Post Limit', 'betterfeed'); ?></th>
                                <td>
                                    <input type="number" name="feed_limit" value="10" min="1" max="100">
                                    <p class="description"><?php esc_html_e('Maximum number of posts in the feed', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Post Types', 'betterfeed'); ?></th>
                                <td>
                                    <?php
                                    $post_types = get_post_types(array('public' => true), 'objects');
                                    foreach ($post_types as $post_type): ?>
                                        <label>
                                            <input type="checkbox" name="feed_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked($post_type->name, 'post'); ?>>
                                            <?php echo esc_html($post_type->label); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Categories', 'betterfeed'); ?></th>
                                <td>
                                    <?php
                                    $categories = get_categories(array('hide_empty' => false));
                                    foreach ($categories as $category): ?>
                                        <label>
                                            <input type="checkbox" name="feed_categories[]" value="<?php echo esc_attr($category->term_id); ?>">
                                            <?php echo esc_html($category->name); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Tags', 'betterfeed'); ?></th>
                                <td>
                                    <?php
                                    $tags = get_tags(array('hide_empty' => false));
                                    foreach ($tags as $tag): ?>
                                        <label>
                                            <input type="checkbox" name="feed_tags[]" value="<?php echo esc_attr($tag->term_id); ?>">
                                            <?php echo esc_html($tag->name); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Order By', 'betterfeed'); ?></th>
                                <td>
                                    <select name="feed_orderby">
                                        <option value="date"><?php esc_html_e('Date', 'betterfeed'); ?></option>
                                        <option value="title"><?php esc_html_e('Title', 'betterfeed'); ?></option>
                                        <option value="rand"><?php esc_html_e('Random', 'betterfeed'); ?></option>
                                        <option value="comment_count"><?php esc_html_e('Comment Count', 'betterfeed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Order', 'betterfeed'); ?></th>
                                <td>
                                    <select name="feed_order">
                                        <option value="DESC"><?php esc_html_e('Descending', 'betterfeed'); ?></option>
                                        <option value="ASC"><?php esc_html_e('Ascending', 'betterfeed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Feed', 'betterfeed'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="feed_enabled" value="1" checked>
                                        <?php esc_html_e('Enable this custom feed', 'betterfeed'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(esc_html__('Create Feed', 'betterfeed')); ?>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
        .bf-custom-feeds-admin {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .bf-feeds-list table {
            margin-top: 10px;
        }
        .bf-add-feed {
            border: 1px solid #ddd;
            padding: 20px;
            background: #f9f9f9;
        }
        @media (max-width: 768px) {
            .bf-custom-feeds-admin {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Handle add feed
     */
    private function handle_add_feed() {
        // Check if nonce exists and verify it
        if (!isset($_POST['bf_feed_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bf_feed_nonce'])), 'bf_add_feed')) {
            wp_die(esc_html__('Security check failed.', 'betterfeed'));
        }
        
        $feed_data = array(
            'title' => isset($_POST['feed_title']) ? sanitize_text_field(wp_unslash($_POST['feed_title'])) : '',
            'slug' => isset($_POST['feed_slug']) ? sanitize_title(wp_unslash($_POST['feed_slug'])) : '',
            'description' => isset($_POST['feed_description']) ? sanitize_textarea_field(wp_unslash($_POST['feed_description'])) : '',
            'limit' => isset($_POST['feed_limit']) ? intval($_POST['feed_limit']) : 10,
            'post_types' => isset($_POST['feed_post_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['feed_post_types'])) : array('post'),
            'categories' => isset($_POST['feed_categories']) ? array_map('intval', $_POST['feed_categories']) : array(),
            'tags' => isset($_POST['feed_tags']) ? array_map('intval', $_POST['feed_tags']) : array(),
            'orderby' => isset($_POST['feed_orderby']) ? sanitize_text_field(wp_unslash($_POST['feed_orderby'])) : 'date',
            'order' => isset($_POST['feed_order']) ? sanitize_text_field(wp_unslash($_POST['feed_order'])) : 'DESC',
            'enabled' => isset($_POST['feed_enabled'])
        );
        
        $custom_feeds = get_option('bf_custom_feeds', array());
        $custom_feeds[] = $feed_data;
        
        update_option('bf_custom_feeds', $custom_feeds);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Custom feed created successfully!', 'betterfeed') . '</p></div>';
        });
    }
    
    /**
     * Save custom feed
     */
    public function save_custom_feed() {
        // Implementation for editing existing feeds
        wp_redirect(admin_url('options-general.php?page=bf-custom-feeds'));
        exit;
    }
    
    /**
     * Delete custom feed
     */
    public function delete_custom_feed() {
        // Check if nonce exists and verify it
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'bf_delete_feed')) {
            wp_die(esc_html__('Security check failed.', 'betterfeed'));
        }
        
        $feed_index = isset($_GET['feed_index']) ? intval($_GET['feed_index']) : -1;
        $custom_feeds = get_option('bf_custom_feeds', array());
        
        if (isset($custom_feeds[$feed_index])) {
            unset($custom_feeds[$feed_index]);
            $custom_feeds = array_values($custom_feeds); // Re-index array
            update_option('bf_custom_feeds', $custom_feeds);
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Custom feed deleted successfully!', 'betterfeed') . '</p></div>';
            });
        }
        
        wp_redirect(admin_url('options-general.php?page=bf-custom-feeds'));
        exit;
    }
    
    /**
     * Maybe flush rewrite rules
     */
    public function maybe_flush_rewrite_rules() {
        $rewrite_rules_version = get_option('bf_custom_feeds_rewrite_rules_version');
        $current_version = '1.0';
        
        if ($rewrite_rules_version !== $current_version) {
            flush_rewrite_rules();
            update_option('bf_custom_feeds_rewrite_rules_version', $current_version);
        }
    }
}
