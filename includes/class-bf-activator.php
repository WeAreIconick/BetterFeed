<?php
/**
 * Plugin activation handler
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation class
 */
class BF_Activator {
    
    /**
     * Activate the plugin
     * 
     * @since 1.0.0
     */
    public static function activate() {
        // Create database tables if needed
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('bf_activated', true);
        
        // Log activation
        // Plugin activated successfully
    }
    
    /**
     * Create database tables for analytics and caching
     * 
     * @since 1.0.0
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics table
        $analytics_table = $wpdb->prefix . 'bf_analytics';
        $analytics_sql = "CREATE TABLE $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feed_url varchar(255) NOT NULL,
            user_agent text,
            ip_address varchar(45),
            referer text,
            accessed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY feed_url (feed_url),
            KEY accessed_at (accessed_at)
        ) $charset_collate;";
        
        // Cache table
        $cache_table = $wpdb->prefix . 'bf_cache';
        $cache_sql = "CREATE TABLE $cache_table (
            cache_key varchar(255) NOT NULL,
            cache_value longtext,
            expiry datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cache_key),
            KEY expiry (expiry)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($analytics_sql);
        dbDelta($cache_sql);
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private static function set_default_options() {
        $default_options = array(
            'enable_caching' => true,
            'cache_duration' => 3600, // 1 hour
            'enable_analytics' => true,
            'enable_seo_optimization' => true,
            'enable_performance_optimization' => true,
            'feed_image_optimization' => true,
            'custom_feed_title' => '',
            'custom_feed_description' => '',
            'include_featured_images' => true,
            'include_custom_post_types' => array(),
            'exclude_categories' => array(),
            'max_feed_items' => 10,
            'enable_full_content' => false,
            'enable_feed_validation' => true,
            'enable_atom_feed' => true,
            'enable_json_feed' => true,
            'enable_feed_discovery' => true,
            'custom_namespace' => '',
            'enable_podcast_elements' => false,
            'podcast_category' => '',
            'podcast_subcategory' => '',
            'podcast_explicit' => 'no',
            'podcast_author' => '',
            'podcast_email' => '',
            'enable_security_headers' => true,
            'enable_etag_headers' => true,
            'enable_gzip_compression' => true
        );
        
        foreach ($default_options as $option_name => $option_value) {
            add_option('bf_' . $option_name, $option_value);
        }
        
        // Set version option
        add_option('bf_version', BF_VERSION);
    }
}