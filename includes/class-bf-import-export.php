<?php
/**
 * Import/Export Settings Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import/Export settings class
 */
class BF_Import_Export {
    
    /**
     * Class instance
     * 
     * @var BF_Import_Export
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Import_Export
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
        // AJAX handlers removed - functionality moved to REST API
    }
    
    /**
     * Get all SMFB settings
     * 
     * @return array All plugin settings
     */
    public function get_all_settings() {
        $settings = array();
        
        // Get all SMFB options
        $option_names = array(
            // Performance Settings
            'bf_enable_caching',
            'bf_cache_duration',
            'bf_enable_gzip_compression',
            'bf_enable_etag_headers',
            
            // SEO Settings
            'bf_enable_seo_optimization',
            'bf_custom_feed_title',
            'bf_custom_feed_description',
            'bf_enable_feed_discovery',
            
            // Content Settings
            'bf_include_featured_images',
            'bf_enable_full_content',
            'bf_max_feed_items',
            'bf_include_custom_post_types',
            'bf_exclude_categories',
            
            // Analytics Settings
            'bf_enable_analytics',
            
            // Feed Formats
            'bf_enable_atom_feed',
            'bf_enable_json_feed',
            
            // Security Settings
            'bf_enable_security_headers',
            
            // WebSub Settings
            'bf_enable_websub',
            'bf_websub_use_default_hubs',
            'bf_websub_hubs',
            
            // Validation Settings
            'bf_validate_on_publish',
            'bf_enable_monitoring',
            'bf_alert_on_errors',
            
            // Content Enhancement Settings
            'bf_convert_relative_urls',
            'bf_fix_encoding',
            'bf_include_read_time',
            'bf_include_word_count',
            'bf_auto_excerpt',
            'bf_reading_speed',
            'bf_custom_footer',
            'bf_image_sizes',
            
            // Podcast Settings
            'bf_enable_podcast_elements',
            'bf_podcast_category',
            'bf_podcast_subcategory',
            'bf_podcast_explicit',
            'bf_podcast_author',
            'bf_podcast_email',
        );
        
        foreach ($option_names as $option_name) {
            $value = get_option($option_name);
            if ($value !== false) {
                $settings[$option_name] = $value;
            }
        }
        
        return $settings;
    }
    
    /**
     * Export settings to JSON
     * 
     * @return string JSON encoded settings
     */
    public function export_settings() {
        $settings = $this->get_all_settings();
        
        $export_data = array(
            'plugin' => 'betterfeed',
            'version' => BF_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url' => home_url(),
            'settings' => $settings
        );
        
        // Use PHP's native json_encode for WordPress 6.0+ compatibility
        return function_exists('wp_json_encode') ? wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Import settings from JSON
     * 
     * @param string $json_data JSON encoded settings
     * @return array Import result
     */
    public function import_settings($json_data) {
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid JSON format', 'betterfeed')
            );
        }
        
        // Validate import data
        if (!isset($data['plugin']) || $data['plugin'] !== 'betterfeed') {
            return array(
                'success' => false,
                'message' => __('Invalid plugin data', 'betterfeed')
            );
        }
        
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return array(
                'success' => false,
                'message' => __('No settings found in import data', 'betterfeed')
            );
        }
        
        $imported_count = 0;
        $skipped_count = 0;
        
        foreach ($data['settings'] as $option_name => $option_value) {
            // Validate option name
            if (strpos($option_name, 'bf_') !== 0) {
                $skipped_count++;
                continue;
            }
            
            // Sanitize and update option
            $sanitized_value = $this->sanitize_option_value($option_name, $option_value);
            
            if (update_option($option_name, $sanitized_value)) {
                $imported_count++;
            } else {
                $skipped_count++;
            }
        }
        
        // Clear cache after import
        if (class_exists('BF_Cache')) {
            BF_Cache::instance()->clear_all();
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                // Translators: %1$d is the number of imported settings, %2$d is the number of skipped settings.
                __('Settings imported successfully. %1$d settings imported, %2$d skipped.', 'betterfeed'),
                $imported_count,
                $skipped_count
            ),
            'imported' => $imported_count,
            'skipped' => $skipped_count
        );
    }
    
    /**
     * Sanitize option value based on option name
     * 
     * @param string $option_name Option name
     * @param mixed $value Option value
     * @return mixed Sanitized value
     */
    private function sanitize_option_value($option_name, $value) {
        // Boolean options
        $boolean_options = array(
            'bf_enable_caching',
            'bf_enable_gzip_compression',
            'bf_enable_etag_headers',
            'bf_enable_seo_optimization',
            'bf_enable_feed_discovery',
            'bf_include_featured_images',
            'bf_enable_full_content',
            'bf_enable_analytics',
            'bf_enable_atom_feed',
            'bf_enable_json_feed',
            'bf_enable_security_headers',
            'bf_enable_websub',
            'bf_websub_use_default_hubs',
            'bf_validate_on_publish',
            'bf_enable_monitoring',
            'bf_alert_on_errors',
            'bf_convert_relative_urls',
            'bf_fix_encoding',
            'bf_include_read_time',
            'bf_include_word_count',
            'bf_auto_excerpt',
            'bf_enable_podcast_elements'
        );
        
        if (in_array($option_name, $boolean_options)) {
            return (bool) $value;
        }
        
        // Numeric options
        $numeric_options = array(
            'bf_cache_duration',
            'bf_max_feed_items',
            'bf_reading_speed'
        );
        
        if (in_array($option_name, $numeric_options)) {
            return (int) $value;
        }
        
        // Array options
        $array_options = array(
            'bf_include_custom_post_types',
            'bf_exclude_categories',
            'bf_image_sizes'
        );
        
        if (in_array($option_name, $array_options)) {
            return is_array($value) ? $value : array();
        }
        
        // Text options (default)
        return sanitize_text_field($value);
    }
    
    /**
     * Generate preset configurations
     * 
     * @param string $preset_name Preset name
     * @return array Preset configuration
     */
    public function get_preset_configuration($preset_name) {
        $presets = array(
            'performance' => array(
                'name' => __('Performance Focused', 'betterfeed'),
                'description' => __('Optimized for maximum performance and speed', 'betterfeed'),
                'settings' => array(
                    'bf_enable_caching' => true,
                    'bf_cache_duration' => 3600,
                    'bf_enable_gzip_compression' => true,
                    'bf_enable_etag_headers' => true,
                    'bf_enable_security_headers' => true,
                    'bf_enable_analytics' => false, // Disable for max performance
                    'bf_include_featured_images' => true,
                    'bf_enable_full_content' => false, // Use excerpts for performance
                    'bf_max_feed_items' => 10,
                    'bf_convert_relative_urls' => true,
                    'bf_fix_encoding' => true,
                )
            ),
            
            'seo' => array(
                'name' => __('SEO Optimized', 'betterfeed'),
                'description' => __('Best settings for search engine optimization', 'betterfeed'),
                'settings' => array(
                    'bf_enable_seo_optimization' => true,
                    'bf_enable_feed_discovery' => true,
                    'bf_enable_websub' => true,
                    'bf_websub_use_default_hubs' => true,
                    'bf_include_featured_images' => true,
                    'bf_enable_full_content' => true,
                    'bf_convert_relative_urls' => true,
                    'bf_fix_encoding' => true,
                    'bf_enable_xslt' => true,
                    'bf_max_feed_items' => 20
                )
            ),
            
            'podcast' => array(
                'name' => __('Podcast Ready', 'betterfeed'),
                'description' => __('Optimized for podcast feeds with proper enclosures', 'betterfeed'),
                'settings' => array(
                    'bf_enable_podcast_elements' => true,
                    'bf_podcast_explicit' => 'no',
                    'bf_include_featured_images' => true,
                    'bf_enable_full_content' => true,
                    'bf_max_feed_items' => 50,
                    'bf_convert_relative_urls' => true,
                    'bf_fix_encoding' => true,
                    'bf_enable_security_headers' => true
                )
            ),
            
            'minimal' => array(
                'name' => __('Minimal Setup', 'betterfeed'),
                'description' => __('Basic optimization with minimal features enabled', 'betterfeed'),
                'settings' => array(
                    'bf_enable_caching' => true,
                    'bf_cache_duration' => 1800,
                    'bf_include_featured_images' => true,
                    'bf_enable_full_content' => false,
                    'bf_max_feed_items' => 10,
                    'bf_convert_relative_urls' => true,
                    'bf_fix_encoding' => true
                )
            )
        );
        
        return isset($presets[$preset_name]) ? $presets[$preset_name] : null;
    }
    
    /**
     * Apply preset configuration
     * 
     * @param string $preset_name Preset name
     * @return array Result
     */
    public function apply_preset($preset_name) {
        $preset = $this->get_preset_configuration($preset_name);
        
        if (!$preset) {
            return array(
                'success' => false,
                'message' => __('Preset not found', 'betterfeed')
            );
        }
        
        $applied_count = 0;
        
        foreach ($preset['settings'] as $option_name => $option_value) {
            $sanitized_value = $this->sanitize_option_value($option_name, $option_value);
            
            if (update_option($option_name, $sanitized_value)) {
                $applied_count++;
            }
        }
        
        // Clear cache after applying preset
        if (class_exists('BF_Cache')) {
            BF_Cache::instance()->clear_all();
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                // Translators: %1$s is the preset name, %2$d is the number of settings updated.
                __('Preset "%1$s" applied successfully. %2$d settings updated.', 'betterfeed'),
                $preset['name'],
                $applied_count
            ),
            'applied' => $applied_count
        );
    }
}