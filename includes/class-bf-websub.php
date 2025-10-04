<?php
/**
 * WebSub/PubSubHubbub Integration Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WebSub class for handling PubSubHubbub notifications
 */
class BF_WebSub {
    
    /**
     * Class instance
     * 
     * @var BF_WebSub
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_WebSub
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
        // Ping hubs when posts are published
        add_action('publish_post', array($this, 'ping_hubs'));
        add_action('save_post', array($this, 'maybe_ping_hubs'));
        
        // Add WebSub link headers to feeds
        add_action('rss_tag_pre', array($this, 'add_websub_headers'));
        add_action('atom_head', array($this, 'add_websub_headers'));
        
        // Add WebSub links to feed content
        add_action('rss2_head', array($this, 'add_websub_link_element'));
        add_action('atom_head', array($this, 'add_websub_link_element'));
    }
    
    /**
     * Ping configured WebSub hubs
     */
    public function ping_hubs($post_id = null) {
        if (!get_option('bf_enable_websub', false)) {
            return;
        }
        
        $hubs = $this->get_configured_hubs();
        if (empty($hubs)) {
            return;
        }
        
        $feed_urls = $this->get_feed_urls();
        
        foreach ($hubs as $hub_url) {
            foreach ($feed_urls as $feed_url) {
                $this->ping_hub($hub_url, $feed_url);
            }
        }
    }
    
    /**
     * Maybe ping hubs on post save
     * 
     * @param int $post_id Post ID
     */
    public function maybe_ping_hubs($post_id) {
        $post = get_post($post_id);
        
        // Only ping on published posts
        if ($post && $post->post_status === 'publish') {
            $this->ping_hubs($post_id);
        }
    }
    
    /**
     * Ping a specific hub
     * 
     * @param string $hub_url Hub URL
     * @param string $feed_url Feed URL
     */
    private function ping_hub($hub_url, $feed_url) {
        $body = array(
            'hub.mode' => 'publish',
            'hub.url' => $feed_url
        );
        
        wp_remote_post($hub_url, array(
            'body' => $body,
            'timeout' => 10,
            'user-agent' => 'WordPress SMFB WebSub/' . BF_VERSION,
            'blocking' => false // Don't wait for response
        ));
        
        // Log the ping
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // WebSub: Successfully pinged {$hub_url} for {$feed_url}
        }
    }
    
    /**
     * Add WebSub headers to feeds
     */
    public function add_websub_headers() {
        if (!get_option('bf_enable_websub', false)) {
            return;
        }
        
        $hubs = $this->get_configured_hubs();
        if (empty($hubs)) {
            return;
        }
        
        // Add Link headers for WebSub - ONLY if headers aren't sent yet
        if (!headers_sent()) {
            foreach ($hubs as $hub_url) {
                header('Link: <' . esc_url($hub_url) . '>; rel="hub"', false);
            }
            
            // Add self link
            $current_feed_url = $this->get_current_feed_url();
            if ($current_feed_url) {
                header('Link: <' . esc_url($current_feed_url) . '>; rel="self"', false);
            }
        }
    }
    
    /**
     * Add WebSub link elements to feed content
     */
    public function add_websub_link_element() {
        if (!get_option('bf_enable_websub', false)) {
            return;
        }
        
        $hubs = $this->get_configured_hubs();
        if (empty($hubs)) {
            return;
        }
        
        // Add hub links
        foreach ($hubs as $hub_url) {
            echo '<link rel="hub" href="' . esc_url($hub_url) . '" />' . "\n";
        }
        
        // Add self link
        $current_feed_url = $this->get_current_feed_url();
        if ($current_feed_url) {
            echo '<link rel="self" href="' . esc_url($current_feed_url) . '" />' . "\n";
        }
    }
    
    /**
     * Get configured WebSub hubs
     * 
     * @return array Hub URLs
     */
    private function get_configured_hubs() {
        $default_hubs = array(
            'https://pubsubhubbub.appspot.com/',
            'https://pubsubhubbub.superfeedr.com/'
        );
        
        $custom_hubs = get_option('bf_websub_hubs', '');
        $custom_hubs = array_filter(array_map('trim', explode("\n", $custom_hubs)));
        
        $use_default_hubs = get_option('bf_websub_use_default_hubs', true);
        
        $hubs = array();
        
        if ($use_default_hubs) {
            $hubs = array_merge($hubs, $default_hubs);
        }
        
        if (!empty($custom_hubs)) {
            $hubs = array_merge($hubs, $custom_hubs);
        }
        
        return array_unique(array_filter($hubs, 'esc_url_raw'));
    }
    
    /**
     * Get all feed URLs for the site
     * 
     * @return array Feed URLs
     */
    private function get_feed_urls() {
        $feeds = array(
            get_feed_link('rss2'),
            get_feed_link('atom')
        );
        
        // Add JSON feed if enabled
        if (get_option('bf_enable_json_feed', true)) {
            $feeds[] = home_url('/feed/json/');
        }
        
        // Add custom post type feeds
        $custom_post_types = get_option('bf_include_custom_post_types', array());
        foreach ($custom_post_types as $post_type) {
            $feeds[] = get_feed_link($post_type);
        }
        
        return array_filter($feeds);
    }
    
    /**
     * Get current feed URL
     * 
     * @return string|false Current feed URL or false
     */
    private function get_current_feed_url() {
        if (!is_feed()) {
            return false;
        }
        
        $feed_type = get_query_var('feed');
        
        if ($feed_type === 'json') {
            return home_url('/feed/json/');
        }
        
        return get_feed_link($feed_type);
    }
    
    /**
     * Test WebSub hub connectivity
     * 
     * @param string $hub_url Hub URL to test
     * @return array Test result
     */
    public function test_hub($hub_url) {
        $response = wp_remote_get($hub_url, array(
            'timeout' => 10,
            'user-agent' => 'WordPress SMFB WebSub/' . BF_VERSION
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        return array(
            'success' => $response_code < 400,
            'status_code' => $response_code,
            'message' => $response_code < 400 ? 'Hub is accessible' : 'Hub returned error: ' . $response_code
        );
    }
    
    /**
     * Get WebSub statistics
     * 
     * @return array WebSub stats
     */
    public function get_websub_stats() {
        $stats = get_option('bf_websub_stats', array(
            'total_pings' => 0,
            'successful_pings' => 0,
            'failed_pings' => 0,
            'last_ping_time' => null,
            'last_ping_status' => null
        ));
        
        return $stats;
    }
    
    /**
     * Update WebSub statistics
     * 
     * @param bool $success Whether the ping was successful
     */
    public function update_stats($success = true) {
        $stats = $this->get_websub_stats();
        
        $stats['total_pings']++;
        
        if ($success) {
            $stats['successful_pings']++;
            $stats['last_ping_status'] = 'success';
        } else {
            $stats['failed_pings']++;
            $stats['last_ping_status'] = 'failed';
        }
        
        $stats['last_ping_time'] = current_time('mysql');
        
        update_option('bf_websub_stats', $stats);
    }
}