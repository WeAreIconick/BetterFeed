<?php
/**
 * RSS Feed Optimizer
 * Handles RSS optimizations
 *
 * @package BetterFeed
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS Feed Optimizer class
 */
class BF_Feed_Optimizer {
    
    /**
     * Class instance
     * 
     * @var BF_Feed_Optimizer
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Feed_Optimizer
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
        // Feed content modifications
        add_filter('the_excerpt_rss', array($this, 'optimize_feed_excerpt'));
        add_filter('the_content_feed', array($this, 'optimize_feed_content'));
        add_filter('rss2_item', array($this, 'add_rss2_item_elements'));
        add_filter('atom_entry', array($this, 'add_atom_entry_elements'));
        
        // Feed headers
        add_action('rss2_head', array($this, 'add_feed_headers'));
        
        // HTTP headers and performance optimization
        add_action('template_redirect', array($this, 'handle_feed_headers'), 5);
        add_action('wp_head', array($this, 'add_enhanced_feed_discovery'));
    }

    /**
     * Add feed headers
     */
    public function add_feed_headers() {
        // WebSub headers if enabled
        if (get_option('bf_enable_websub', false)) {
            $this->add_websub_headers();
        }
    }
    
    /**
     * Optimize feed excerpt
     */
    public function optimize_feed_excerpt($excerpt) {
        if (class_exists('BF_Content_Enhancer')) {
            return BF_Content_Enhancer::instance()->enhance_feed_excerpt($excerpt);
        }
        return $excerpt;
    }
    
    /**
     * Handle HTTP headers for feed requests
     */
    public function handle_feed_headers() {
        if (!is_feed()) {
            return;
        }
        
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Don't send headers if already sent
        if (headers_sent()) {
            return;
        }
        
        // Handle conditional requests first
        if ($this->handle_conditional_request()) {
            return; // 304 response sent, exit
        }
        
        // Send standard headers
        $this->send_feed_headers();
        
        // Handle GZIP compression
        $this->handle_gzip_compression();
    }
    
    /**
     * Handle conditional requests (304 Not Modified)
     */
    private function handle_conditional_request() {
        // Check if conditional requests are enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_conditional_requests'])) {
            return false;
        }
        
        $last_modified = $this->get_feed_last_modified();
        $etag = $this->get_feed_etag();
        
        if (!$last_modified && !$etag) {
            return false;
        }
        
        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : '';
        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';
        
        $modified_match = false;
        $etag_match = false;
        
        // Check If-Modified-Since
        if ($if_modified_since && $last_modified) {
            $client_time = strtotime($if_modified_since);
            $server_time = strtotime($last_modified);
            $modified_match = ($client_time >= $server_time);
        }
        
        // Check If-None-Match (ETag)
        if ($if_none_match && $etag) {
            $etag_match = ($if_none_match === $etag || $if_none_match === '"' . $etag . '"');
        }
        
        // Send 304 if either condition matches
        if ($modified_match || $etag_match) {
            http_response_code(304);
            exit;
        }
        
        return false;
    }
    
    /**
     * Send comprehensive HTTP headers for feeds
     */
    private function send_feed_headers() {
        $feed_type = get_query_var('feed');
        $last_modified = $this->get_feed_last_modified();
        $etag = $this->get_feed_etag();
        
        // Content-Type header
        $content_types = array(
            'rss' => 'application/rss+xml; charset=' . get_option('blog_charset'),
            'rss2' => 'application/rss+xml; charset=' . get_option('blog_charset'),
            'atom' => 'application/atom+xml; charset=' . get_option('blog_charset'),
            'rdf' => 'application/rdf+xml; charset=' . get_option('blog_charset'),
            'json' => 'application/json; charset=' . get_option('blog_charset')
        );
        
        if (isset($content_types[$feed_type])) {
            header('Content-Type: ' . $content_types[$feed_type]);
        }
        
        // Last-Modified header
        if ($last_modified) {
            header('Last-Modified: ' . $last_modified);
        }
        
        // ETag header (if enabled)
        $performance_options = get_option('bf_performance_options', array());
        if ($etag && !empty($performance_options['enable_etag'])) {
            header('ETag: "' . $etag . '"');
        }
        
        // Cache-Control header
        $cache_duration = get_option('bf_cache_duration', 3600);
        header('Cache-Control: public, max-age=' . $cache_duration);
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        
        // Allow cross-origin requests for feeds
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: If-Modified-Since, If-None-Match');
    }
    
    /**
     * Handle GZIP compression
     */
    private function handle_gzip_compression() {
        // Check if compression is enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_gzip'])) {
            return;
        }
        
        // Check if compression is already handled by server
        if (extension_loaded('zlib') && !ob_get_level() && !headers_sent()) {
            $accept_encoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
            
            if (strpos($accept_encoding, 'gzip') !== false) {
                ob_start('ob_gzhandler');
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
            }
        }
    }
    
    /**
     * Get feed last modified timestamp
     */
    private function get_feed_last_modified() {
        global $wpdb;
        
        $last_post = $wpdb->get_var("
            SELECT post_modified_gmt 
            FROM {$wpdb->posts} 
            WHERE post_status = 'publish' 
            AND post_type IN ('post', 'page')
            ORDER BY post_modified_gmt DESC 
            LIMIT 1
        ");
        
        if ($last_post) {
            return gmdate('D, d M Y H:i:s', strtotime($last_post)) . ' GMT';
        }
        
        return false;
    }
    
    /**
     * Generate ETag for feed content
     */
    private function get_feed_etag() {
        // Create a simple ETag based on site URL, feed type, and last modified
        $feed_type = get_query_var('feed');
        $last_modified = $this->get_feed_last_modified();
        $site_url = get_site_url();
        
        $etag_string = $site_url . '|' . $feed_type . '|' . ($last_modified ? strtotime($last_modified) : '');
        
        return md5($etag_string);
    }
    
    /**
     * Add enhanced feed discovery links to HTML head
     */
    public function add_enhanced_feed_discovery() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Check if enhanced discovery is enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_enhanced_discovery'])) {
            return;
        }
        
        $site_title = get_bloginfo('name');
        
        // RSS 2.0 feed
        echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr($site_title . ' RSS Feed') . '" href="' . esc_url(get_feed_link('rss2')) . '" />' . "\n";
        
        // Atom feed
        echo '<link rel="alternate" type="application/atom+xml" title="' . esc_attr($site_title . ' Atom Feed') . '" href="' . esc_url(get_feed_link('atom')) . '" />' . "\n";
        
        // JSON Feed (if enabled)
        if (get_option('bf_enable_json_feed', true)) {
            echo '<link rel="alternate" type="application/json" title="' . esc_attr($site_title . ' JSON Feed') . '" href="' . esc_url(home_url('/feed/json/')) . '" />' . "\n";
        }
        
        // Category feeds
        $categories = get_categories(array('hide_empty' => true, 'number' => 10));
        foreach ($categories as $category) {
            echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr($site_title . ' - ' . $category->name . ' RSS') . '" href="' . esc_url(get_category_feed_link($category->term_id)) . '" />' . "\n";
        }
        
        // Tag feeds
        $tags = get_tags(array('hide_empty' => true, 'number' => 10));
        foreach ($tags as $tag) {
            echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr($site_title . ' - ' . $tag->name . ' RSS') . '" href="' . esc_url(get_tag_feed_link($tag->term_id)) . '" />' . "\n";
        }
        
        // Custom post type feeds
        $custom_post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        foreach ($custom_post_types as $post_type) {
            if ($post_type->publicly_queryable) {
                echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr($site_title . ' - ' . $post_type->label . ' RSS') . '" href="' . esc_url(get_post_type_archive_feed_link($post_type->name)) . '" />' . "\n";
            }
        }
    }

    /**
     * Optimize feed content
     */
    public function optimize_feed_content($content) {
        if (class_exists('BF_Content_Enhancer')) {
            return BF_Content_Enhancer::instance()->enhance_feed_content($content);
        }
        return $content;
    }
    
    /**
     * Add RSS2 item elements
     */
    public function add_rss2_item_elements() {
        global $post;
        
        // Add podcast elements if enabled
        if (get_option('bf_enable_podcast', false)) {
            echo '<itunes:summary>' . esc_html(get_the_excerpt()) . '</itunes:summary>' . "\n";
            echo '<itunes:explicit>' . (get_option('bf_podcast_explicit', false) ? '1' : '0') . '</itunes:explicit>' . "\n";
        }
    }
    
    /**
     * Add Atom entry elements
     */
    public function add_atom_entry_elements() {
        global $post;
        
        echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr(get_the_excerpt()) . '" />' . "\n";
    }

    /**
     * Add WebSub headers
     */
    private function add_websub_headers() {
        $hub_urls = array(
            'https://pubsubhubbub.appspot.com/',
            'https://pubsubhubbub.superfeedr.com/'
        );
        
        foreach ($hub_urls as $hub_url) {
            printf('<link rel="hub" href="%s" />' . "\n", esc_url($hub_url));
        }
    }
}