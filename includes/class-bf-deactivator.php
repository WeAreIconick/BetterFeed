<?php
/**
 * Plugin deactivation handler
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin deactivation class
 */
class BF_Deactivator {
    
    /**
     * Deactivate the plugin
     * 
     * @since 1.0.0
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
        
        // Clear cache
        self::clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Remove activation flag
        delete_option('bf_activated');
        
        // Log deactivation
        // Plugin deactivated successfully
    }
    
    /**
     * Clear scheduled cron jobs
     * 
     * @since 1.0.0
     */
    private static function clear_cron_jobs() {
        // Clear cache cleanup cron
        $timestamp = wp_next_scheduled('bf_cache_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'bf_cache_cleanup');
        }
        
        // Clear analytics cleanup cron
        $timestamp = wp_next_scheduled('bf_analytics_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'bf_analytics_cleanup');
        }
        
        // Clear performance monitor cron jobs
        $timestamp = wp_next_scheduled('bf_performance_monitor_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'bf_performance_monitor_cron');
        }
        
        $timestamp = wp_next_scheduled('bf_performance_cleanup_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'bf_performance_cleanup_cron');
        }
    }
    
    /**
     * Clear plugin cache
     * 
     * @since 1.0.0
     */
    private static function clear_cache() {
        // Clear cache options using WordPress API
        $cache_options = array(
            'bf_cache_feed_cache',
            'bf_cache_performance_stats',
            'bf_cache_analytics_summary',
            'bf_cache_geographic_stats',
            'bf_cache_footer_cache'
        );
        
        foreach ($cache_options as $option_name) {
            delete_option($option_name);
        }
        
        // Clear WordPress transients
        $transient_keys = array(
            'bf_feed_cache',
            'bf_analytics_summary',
            'bf_performance_stats',
            'bf_geographic_stats',
            'bf_footer_cache'
        );
        
        foreach ($transient_keys as $transient_name) {
            delete_transient($transient_name);
        }
        
        // Clear object cache
        wp_cache_flush();
    }
}