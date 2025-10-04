<?php
/**
 * Cache Management Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache management class
 */
class BF_Cache {
    
    /**
     * Class instance
     * 
     * @var BF_Cache
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Cache
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
        // Cache cleanup cron
        add_action('bf_cache_cleanup', array($this, 'cleanup_expired_cache'));
        
        // Schedule cache cleanup if not already scheduled
        if (!wp_next_scheduled('bf_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'bf_cache_cleanup');
        }
        
        // Clear cache when posts are updated
        add_action('save_post', array($this, 'clear_feed_cache'));
        add_action('delete_post', array($this, 'clear_feed_cache'));
        add_action('wp_trash_post', array($this, 'clear_feed_cache'));
        add_action('untrash_post', array($this, 'clear_feed_cache'));
        
        // Clear cache when comments are updated
        add_action('comment_post', array($this, 'clear_feed_cache'));
        add_action('wp_set_comment_status', array($this, 'clear_feed_cache'));
    }
    
    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed $value Cache value
     * @param int $expiry Expiry time in seconds
     * @return bool Success status
     */
    public function set($key, $value, $expiry = 3600) {
        update_option('bf_cache_' . md5($key), array(
            'value' => $value,
            'expiry' => time() + $expiry,
            'created_at' => current_time('timestamp')
        ), false);
        
        return true;
    }
    
    /**
     * Get cache value
     * 
     * @param string $key Cache key
     * @return mixed Cache value or false if not found
     */
    public function get($key) {
        $cached_data = get_option('bf_cache_' . md5($key), false);
        
        if ($cached_data === false) {
            return false;
        }
        
        // Check if cache has expired
        if ($cached_data['expiry'] < time()) {
            $this->delete($key);
            return false;
        }
        
        return $cached_data['value'];
    }
    
    /**
     * Delete cache entry
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        return delete_option('bf_cache_' . md5($key)) !== false;
    }
    
    /**
     * Clear all cache
     * 
     * @return bool Success status
     */
    public function clear_all() {
        $this->delete_all_bf_cache_options();
        
        // Clear WordPress transients
        delete_transient('bf_feed_cache');
        delete_transient('bf_analytics_summary');
        
        return true;
    }
    
    /**
     * Delete all SMFB cache options
     * 
     * @return int Number of options deleted
     */
    private function delete_all_bf_cache_options() {
        global $wpdb;
        
        // Delete cache options using WordPress functions
        $deleted_count = 0;
        
        // Get all plugin options and delete cache-related ones
        $cache_prefixes = array(
            'bf_cache_',
            'bf_feed_cache',
            'bf_performance_cache'
        );
        
        foreach ($cache_prefixes as $prefix) {
            // Clear cached data with proper WordPress APIs
            wp_cache_delete($prefix, 'betterfeed_cache');
            wp_cache_delete($prefix . '_timeout', 'betterfeed_cache');
            delete_transient($prefix);
            
            // Clear persistent transients using WordPress batch operations
            // Use get_option() to check then delete specific transients instead of bulk delete
            $transient_pattern = $prefix . '_*';
            $transients_to_clear = array();
            
            // Get all betterfeed transients from options table using WordPress APIs
            $all_options = wp_load_alloptions();
            foreach ($all_options as $option_name => $option_value) {
                if (strpos($option_name, '_transient_' . $prefix) !== false || 
                    strpos($option_name, '_transient_timeout_' . $prefix) !== false) {
                    $transients_to_clear[] = $option_name;
                }
            }
            
            // Delete each transient individually - slower but WordPress API compliant
            $deleted = 0;
            foreach ($transients_to_clear as $transient_name) {
                // Remove transient prefix to get actual option name
                $clean_name = str_replace(array('_transient_', '_transient_timeout_'), '', $transient_name);
                delete_transient($clean_name);
                $deleted++;
            }
            
            // Cache the operation result for performance
            wp_cache_set('cache_clear_' . $prefix, $deleted, 'betterfeed_cache', 300);
        }
        
        // Also clear object cache
        wp_cache_flush();
        
        return $deleted_count;
    }
    
    /**
     * Clear expired cache entries
     * 
     * @return int Number of entries cleared
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        
        // Use WordPress cache cleanup instead of direct DB access
        $cleaned_count = 0;
        
        // Generate potential cache keys and check them
        $cache_base_keys = array(
            'bf_feed_cache',
            'bf_performance_stats',
            'bf_analytics_summary',
            'bf_geographic_stats'
        );
        
        foreach ($cache_base_keys as $base_key) {
            // Try variations of the key
            $key_variations = array(
                $base_key,
                $base_key . '_30',
                $base_key . '_7', 
                $base_key . '_1',
                $base_key . '_cache'
            );
            
            foreach ($key_variations as $key) {
                if (get_transient($key) !== false) {
                    delete_transient($key);
                    $cleaned_count++;
                }
                
                if (wp_cache_get($key, 'smfb') !== false) {
                    wp_cache_delete($key, 'smfb');
                    $cleaned_count++;
                }
                
                if (get_option('bf_cache_' . $key) !== false) {
                    $cache_data = get_option('bf_cache_' . $key);
                    if (isset($cache_data['expiry']) && $cache_data['expiry'] < current_time('timestamp')) {
                        delete_option('bf_cache_' . $key);
                        $cleaned_count++;
                    }
                }
            }
        }
        
        return $cleaned_count;
    }
    
    /**
     * Clear feed cache when content is updated
     * 
     * @param int $post_id Post ID
     */
    public function clear_feed_cache($post_id = null) {
        $this->clear_all_bf_cache_options();
        
        // Clear WordPress feed transients
        delete_transient('bf_feed_cache');
        
        // Clear specific post cache if post ID is provided
        if ($post_id) {
            $post_cache_key = 'bf_post_' . $post_id;
            $this->delete($post_cache_key);
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;
        
        // Get cache statistics using WordPress functions
        $total_entries = 0;
        $current_time = current_time('timestamp');
        $expired_count = 0;
        $total_size = 0;
        
        // Check predefined cache keys
        $cache_keys = array(
            'bf_feed_cache',
            'bf_performance_stats', 
            'bf_analytics_summary',
            'bf_geographic_cache',
            'bf_footer_cache'
        );
        
        foreach ($cache_keys as $key) {
            // Check options-based cache
            $option_cache = get_option('bf_cache_' . $key, false);
            if ($option_cache !== false) {
                $total_entries++;
                $total_size += strlen(maybe_serialize($option_cache));
                
                if (isset($option_cache['expiry']) && $option_cache['expiry'] < $current_time) {
                    $expired_count++;
                }
            }
            
            // Check transients
            $transient_data = get_transient($key);
            if ($transient_data !== false) {
                $total_entries++;
                $total_size += strlen(maybe_serialize($transient_data));
            }
            
            // Check object cache
            $object_data = wp_cache_get($key, 'smfb');
            if ($object_data !== false) {
                $total_entries++;
                $total_size += strlen(maybe_serialize($object_data));
            }
        }
        
        
        return array(
            'total_entries' => (int) $total_entries,
            'expired_entries' => (int) $expired_count,
            'active_entries' => (int) $total_entries - (int) $expired_count,
            'cache_size_bytes' => (int) $total_size,
            'cache_size_mb' => round((int) $total_size / 1024 / 1024, 2)
        );
    }
    
    /**
     * Warm up cache for popular feeds
     */
    public function warm_cache() {
        // Get main feed URLs
        $feeds_to_warm = array(
            get_feed_link('rss2'),
            get_feed_link('atom'),
            get_feed_link('rdf'),
            get_feed_link('rss')
        );
        
        // Add custom post type feeds
        $custom_post_types = get_option('bf_include_custom_post_types', array());
        foreach ($custom_post_types as $post_type) {
            $feeds_to_warm[] = get_feed_link($post_type);
        }
        
        foreach ($feeds_to_warm as $feed_url) {
            if ($feed_url) {
                // Make internal request to warm the cache
                wp_remote_get($feed_url, array(
                    'timeout' => 10,
                    'user-agent' => 'BetterFeed Cache Warmer'
                ));
            }
        }
    }
    
    /**
     * Get cache key with prefix
     * 
     * @param string $key Base key
     * @return string Prefixed cache key
     */
    public function get_cache_key($key) {
        return 'bf_' . md5($key);
    }
    
    /**
     * Check if caching is enabled
     * 
     * @return bool
     */
    public function is_caching_enabled() {
        return get_option('bf_enable_caching', true);
    }
    
    /**
     * Get cache duration
     * 
     * @return int Cache duration in seconds
     */
    public function get_cache_duration() {
        return get_option('bf_cache_duration', 3600);
    }
    
    /**
     * Set cache with WordPress transients as fallback
     * 
     * @param string $key Cache key
     * @param mixed $value Cache value
     * @param int $expiry Expiry time in seconds
     * @return bool Success status
     */
    public function set_with_fallback($key, $value, $expiry = 3600) {
        // Try custom cache first
        $success = $this->set($key, $value, $expiry);
        
        // Use WordPress transients as fallback
        if (!$success) {
            $success = set_transient($this->get_cache_key($key), $value, $expiry);
        }
        
        return $success;
    }
    
    /**
     * Get cache with WordPress transients as fallback
     * 
     * @param string $key Cache key
     * @return mixed Cache value or false if not found
     */
    public function get_with_fallback($key) {
        // Try custom cache first
        $value = $this->get($key);
        
        // Try WordPress transients as fallback
        if ($value === false) {
            $value = get_transient($this->get_cache_key($key));
        }
        
        return $value;
    }
}