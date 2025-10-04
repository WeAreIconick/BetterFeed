<?php
/**
 * Plugin Uninstaller Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin uninstaller
 */
class BF_Uninstaller {
    
    /**
     * Run uninstall tasks
     * 
     * @since 1.0.0
     */
    public static function uninstall() {
        // Remove all plugin options
        self::remove_options();
        
        // Clear all plugin transients
        self::clear_transients();
        
        // Remove uploaded files
        self::remove_uploaded_files();
        
        // Clear all scheduled cron jobs
        self::clear_scheduled_crons();
        
        // Clean up plugin cache
        self::clear_plugin_cache();
        
        // Log uninstall action
        // Plugin uninstalled successfully
    }
    
    /**
     * Remove plugin tables (if custom tables were created)
     * Note: This method is commented out since we're using WordPress options instead
     * 
     * @since 1.0.0
     */
    private static function remove_tables() {
        // Tables are not used in this implementation
        // Instead we use WordPress options system
        return true;
    }
    
    /**
     * Remove all plugin options
     * 
     * @since 1.0.0
     */
    private static function remove_options() {
        // Get all BetterFeed options
        $options_to_remove = array(
            'bf_enable_analytics',
            'bf_enable_caching',
            'bf_cache_duration',
            'bf_enable_delayed_feed',
            'bf_feed_delay_hours',
            'bf_feed_delay_days',
            'bf_included_post_types',
            'bf_include_custom_post_types',
            'bf_enable_websub',
            'bf_websub_hubs',
            'bf_google_discover_enabled',
            'bf_content_optimization_enabled',
            'bf_feed_enhancement_enabled',
            'bf_cache_'  // Remove all cache options
        );
        
        // Delete specific named options
        foreach ($options_to_remove as $option) {
            if ($option === 'bf_cache_') {
                // Handle cache options with wildcards
                continue; // Will be handled in clear_transients
            } else {
                delete_option($option);
            }
        }
        
        // Delete all BetterFeed options using predefined keys
        $bf_options = array(
            'bf_cache_feed_cache',
            'bf_cache_performance_stats',
            'bf_cache_analytics_summary',
            'bf_cache_geographic_stats',
            'bf_cache_footer_cache',
            'bf_feed_format',
            'bf_items_cache_duration',
            'bf_optimize_performance',
            'bf_add_footer_message',
            'bf_include_html',
            'bf_feed_url',
            'bf_analytics_enable',
            'bf_daily_stats_last_check',
            'bf_web_sub_enabled',
            'bf_web_sub_hub_url'
        );
        
        foreach ($bf_options as $option_name) {
            delete_option($option_name);
        }
    }
    
    /**
     * Clear all plugin transients
     * 
     * @since 1.0.0
     */
    private static function clear_transients() {
        // Delete plugin transients using WordPress functions
        $transient_keys = array(
            'bf_feed_cache',
            'bf_analytics_summary',
            'bf_performance_stats',
            'bf_geographic_stats',
            'bf_footer_cache',
            'bf_daily_stats_last_check',
            'bf_web_sub_cache'
        );
        
        foreach ($transient_keys as $transient_key) {
            delete_transient($transient_key);
        }
        
        // Clear object cache for our plugin
        wp_cache_flush();
    }
    
    /**
     * Remove uploaded files
     * 
     * @since 1.0.0
     */
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $bf_dir = $upload_dir['basedir'] . '/bf/';
        
        if (is_dir($bf_dir)) {
            // Recursively delete all files in BetterFeed directory
            self::recursive_delete_directory($bf_dir);
        }
        
        // Remove any BetterFeed-related files in uploads
        $bf_files = glob($upload_dir['basedir'] . '/bf_*');
        foreach ($bf_files as $file) {
            if (is_file($file)) {
                wp_delete_file($file);
            }
        }
    }
    
    /**
     * Clear all scheduled cron jobs
     * 
     * @since 1.0.0
     */
    private static function clear_scheduled_crons() {
        $cron_events = array(
            'bf_cache_cleanup',
            'bf_analytics_cleanup',
            'bf_feed_cleanup',
            'bf_scheduled_feed_check'
        );
        
        foreach ($cron_events as $event) {
            wp_clear_scheduled_hook($event);
        }
    }
    
    /**
     * Clear plugin cache
     * 
     * @since 1.0.0
     */
    private static function clear_plugin_cache() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear any specific plugin caches
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('bf_analytics');
            wp_cache_delete_group('bf_cache');
        }
    }
    
    /**
     * Recursively delete directory and all contents
     * 
     * @param string $directory Directory to delete
     * @return bool Success status
     */
    private static function recursive_delete_directory($directory) {
        if (!is_dir($directory)) {
            return false;
        }
        
        $files = array_diff(scandir($directory), array('.', '..'));
        
        foreach ($files as $file) {
            $file_path = $directory . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($file_path)) {
                self::recursive_delete_directory($file_path);
            } else {
                wp_delete_file($file_path);
            }
        }
        
        // Use WP_Filesystem for safe directory insertion
        if (! function_exists('WP_Filesystem')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $creds = request_filesystem_credentials('', '', false, false);
        if (WP_Filesystem($creds)) {
            global $wp_filesystem;
            return $wp_filesystem->rmdir($directory, true); // recursive removal
        }
        
        // If WP_Filesystem fails, remove files individually and leave empty directory
        return true;
    }
    
    /**
     * Remove plugin meta data from posts
     * 
     * @since 1.0.0
     */
    private static function remove_post_meta() {
        global $wpdb;
        
        // Remove all BetterFeed-related post meta
        $meta_keys = array(
            'bf_feed_publish_time',
            'bf_google_discover_score',
            'bf_content_score',
            'bf_feed_priority'
        );
        
        foreach ($meta_keys as $meta_key) {
            delete_metadata('post', 0, $meta_key, '', true);
        }
        
        // Additional cleanup using WordPress meta functions
        $additional_meta_keys = array(
            'bf_feed_cache',
            'bf_cache_key',
            'bf_last_update',
            'bf_validation_pass',
            'bf_error_log'
        );
        
        foreach ($additional_meta_keys as $meta_key) {
            delete_metadata('post', 0, $meta_key, '', true);
        }
    }
    
    /**
     * Clean up any remaining BetterFeed data
     * 
     * @since 1.0.0
     */
    private static function cleanup_remaining_data() {
        // Use WordPress meta deletion functions
        // Get posts with BetterFeed meta data and delete individually
        $meta_keys = array(
            'bf_feed_publish_time',
            'bf_original_publish_time',
            'bf_intro_message',
            'bf_outro_message',
            'bf_exclude_from_feed',
            'bf_override_header',
            'bf_override_footer'
        );
        
        foreach ($meta_keys as $meta_key) {
            // Delete post meta using WordPress functions
            delete_post_meta_by_key($meta_key);
            
            // Delete comment meta if needed  
            delete_comment_meta_by_key($meta_key);
            
            // Delete user meta if needed
            delete_user_meta_by_key($meta_key);
            
            // Delete term meta if needed
            delete_term_meta_by_key($meta_key);
        }
    }
}