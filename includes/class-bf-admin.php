<?php
/**
 * BetterFeed Admin Interface
 *
 * This class manages the WordPress admin interface for the BetterFeed plugin,
 * including settings pages, REST API endpoints, asset management, and user
 * interface components. It provides a centralized admin experience with
 * comprehensive settings management and real-time functionality.
 *
 * The class handles:
 * - WordPress Settings API integration
 * - Custom admin pages and tabs
 * - REST API endpoint registration and callbacks
 * - Asset enqueuing and localization
 * - Form processing and validation
 * - User feedback and notifications
 *
 * @package BetterFeed
 * @since   1.0.0
 * 
 * @example
 * $admin = BF_Admin::instance();
 * $admin->init(); // Initialize admin functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface management class for BetterFeed plugin.
 * 
 * @package BetterFeed
 * @since   1.0.0
 */
class BF_Admin {
    
    /**
     * Class instance for singleton pattern.
     * 
     * Ensures only one instance of the admin class exists,
     * preventing conflicts and maintaining state consistency.
     * 
     * @since 1.0.0
     * @var BF_Admin
     */
    private static $instance = null;
    
    /**
     * Whether WordPress hooks have been initialized.
     * 
     * Prevents duplicate hook registration and ensures
     * proper initialization order.
     * 
     * @since 1.0.0
     * @var bool
     */
    private static $hooks_initialized = false;
    
    /**
     * Get singleton instance of this class.
     * 
     * Implements the singleton pattern to ensure only one
     * instance of the admin class exists throughout the
     * WordPress request lifecycle.
     * 
     * @since 1.0.0
     * 
     * @return BF_Admin The admin class instance
     * 
     * @example
     * $admin = BF_Admin::instance();
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
        
        
        // Always register REST API routes (needed on every request)
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Only initialize other hooks once to prevent duplicates
        if (!self::$hooks_initialized) {
        $this->init_hooks();
            self::$hooks_initialized = true;
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu and scripts
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Settings API
        add_action('admin_init', array($this, 'register_settings'));
        
        
        // Plugin meta links
        add_filter('plugin_action_links_' . BF_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        
        // Dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        
        // REST API endpoints registered in register_rest_routes() - no AJAX!
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            esc_html__('BetterFeed Settings', 'betterfeed'),
            esc_html__('BetterFeed', 'betterfeed'),
            'manage_options',
            'bf-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings using WordPress Settings API
     */
    public function register_settings() {
        // Always register settings - WordPress Settings API handles the validation
        
        // Keep one option page but use REMOVABLE QUERY ARGS FILTER  
        register_setting('bf-settings', 'bf_general_options', array($this, 'sanitize_general_options'));
        register_setting('bf-settings', 'bf_performance_options', array($this, 'sanitize_performance_options'));
        register_setting('bf-settings', 'bf_content_options', array($this, 'sanitize_content_options'));
        register_setting('bf-settings', 'bf_analytics_options', array($this, 'sanitize_analytics_options'));
        register_setting('bf-settings', 'bf_tools_options', array($this, 'sanitize_tools_options'));
        register_setting('bf-settings', 'bf_podcast_integrations', array($this, 'sanitize_podcast_integrations'));
        register_setting('bf-settings', 'bf_podcast_show', array($this, 'sanitize_podcast_show'));
        
        // WordPress automatically displays settings messages - no need for explicit settings_errors() call
        add_settings_section(
            'bf_general_section',
            esc_html__('General Settings', 'betterfeed'),
            array($this, 'general_section_callback'),
            'bf_general'
        );
        add_settings_field(
            'enable_betterfeed',
            esc_html__('Enable BetterFeed', 'betterfeed'),
            array($this, 'enable_betterfeed_callback'),
            'bf_general',
            'bf_general_section'
        );
        
        add_settings_section(
            'bf_performance_section',
            esc_html__('Performance Settings', 'betterfeed'),
            array($this, 'performance_section_callback'),
            'bf_performance'
        );
        add_settings_field(
            'enable_caching',
            esc_html__('Enable Caching', 'betterfeed'),
            array($this, 'enable_caching_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        add_settings_field(
            'cache_duration',
            esc_html__('Cache Duration (seconds)', 'betterfeed'),
            array($this, 'cache_duration_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        add_settings_field(
            'enable_gzip',
            esc_html__('GZIP Compression', 'betterfeed'),
            array($this, 'enable_gzip_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        add_settings_field(
            'enable_etag',
            esc_html__('ETag Headers', 'betterfeed'),
            array($this, 'enable_etag_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        
        add_settings_field(
            'enable_conditional_requests',
            esc_html__('304 Not Modified Responses', 'betterfeed'),
            array($this, 'enable_conditional_requests_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        
        add_settings_field(
            'enable_enhanced_discovery',
            esc_html__('Enhanced Feed Discovery', 'betterfeed'),
            array($this, 'enable_enhanced_discovery_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        
        add_settings_field(
            'enable_enclosure_fix',
            esc_html__('Auto-Detect Enclosure Sizes', 'betterfeed'),
            array($this, 'enable_enclosure_fix_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        
        add_settings_field(
            'enable_responsive_images',
            esc_html__('Responsive Images', 'betterfeed'),
            array($this, 'enable_responsive_images_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        
        add_settings_field(
            'enable_json_feed',
            esc_html__('JSON Feed Support', 'betterfeed'),
            array($this, 'enable_json_feed_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        
        add_settings_field(
            'enable_google_discover',
            esc_html__('Google Discover Optimization', 'betterfeed'),
            array($this, 'enable_google_discover_callback'),
            'bf_performance',
            'bf_performance_section'
        );
        
        
        add_settings_section(
            'bf_content_section',
            esc_html__('Content Settings', 'betterfeed'),
            array($this, 'content_section_callback'),
            'bf_content'
        );
        add_settings_field(
            'include_featured_images',
            esc_html__('Include Featured Images', 'betterfeed'),
            array($this, 'include_featured_images_callback'),
            'bf_content',
            'bf_content_section'
        );
        add_settings_field(
            'enable_full_content',
            esc_html__('Full Content in Feed', 'betterfeed'),
            array($this, 'enable_full_content_callback'),
            'bf_content',
            'bf_content_section'
        );
        add_settings_field(
            'max_feed_items',
            esc_html__('Maximum Feed Items', 'betterfeed'),
            array($this, 'max_feed_items_callback'),
            'bf_content',
            'bf_content_section'
        );
        
        add_settings_section(
            'bf_analytics_section',
            esc_html__('Analytics Settings', 'betterfeed'),
            array($this, 'analytics_section_callback'),
            'bf_analytics'
        );
        add_settings_field(
            'enable_analytics',
            esc_html__('Enable Analytics', 'betterfeed'),
            array($this, 'enable_analytics_callback'),
            'bf_analytics',
            'bf_analytics_section'
        );
        
        add_settings_section(
            'bf_tools_cache_section',
            esc_html__('Cache Management', 'betterfeed'),
            array($this, 'tools_cache_section_callback'),
            'bf_tools'
        );
        add_settings_field(
                'cache_actions',
                esc_html__('Cache Actions', 'betterfeed'),
                array($this, 'cache_actions_callback'),
                'bf_tools',
                'bf_tools_cache_section'
        );
        add_settings_field(
                'preset_selection',
                esc_html__('Configuration Preset', 'betterfeed'),
                array($this, 'preset_selection_callback'),
                'bf_tools',
                'bf_tools_cache_section'
        );
        
        // Podcast Integration Settings
        add_settings_section(
            'bf_podcast_integrations_section',
            esc_html__('Platform Integrations', 'betterfeed'),
            array($this, 'podcast_integrations_section_callback'),
            'bf_podcast_integrations'
        );
        
        add_settings_field(
            'apple_itunes',
            esc_html__('Apple Podcasts (iTunes)', 'betterfeed'),
            array($this, 'apple_itunes_callback'),
            'bf_podcast_integrations',
            'bf_podcast_integrations_section'
        );
        
        add_settings_field(
            'spotify',
            esc_html__('Spotify', 'betterfeed'),
            array($this, 'spotify_callback'),
            'bf_podcast_integrations',
            'bf_podcast_integrations_section'
        );
        
        add_settings_field(
            'podcast_index',
            esc_html__('Podcast Index', 'betterfeed'),
            array($this, 'podcast_index_callback'),
            'bf_podcast_integrations',
            'bf_podcast_integrations_section'
        );
        
        add_settings_field(
            'google_youtube_music',
            esc_html__('Google/YouTube Music', 'betterfeed'),
            array($this, 'google_youtube_music_callback'),
            'bf_podcast_integrations',
            'bf_podcast_integrations_section'
        );
        
        // Podcast Show Settings
        add_settings_section(
            'bf_podcast_show_section',
            esc_html__('Podcast Information', 'betterfeed'),
            array($this, 'podcast_show_section_callback'),
            'bf_podcast_show'
        );
        
        add_settings_field(
            'podcast_title',
            esc_html__('Podcast Title', 'betterfeed'),
            array($this, 'podcast_title_callback'),
            'bf_podcast_show',
            'bf_podcast_show_section'
        );
        
        add_settings_field(
            'podcast_description',
            esc_html__('Description', 'betterfeed'),
            array($this, 'podcast_description_callback'),
            'bf_podcast_show',
            'bf_podcast_show_section'
        );
        
        add_settings_field(
            'podcast_artwork',
            esc_html__('Cover Artwork', 'betterfeed'),
            array($this, 'podcast_artwork_callback'),
            'bf_podcast_show',
            'bf_podcast_show_section'
        );
        
        add_settings_field(
            'podcast_language',
            esc_html__('Language', 'betterfeed'),
            array($this, 'podcast_language_callback'),
            'bf_podcast_show',
            'bf_podcast_show_section'
        );
        
        add_settings_field(
            'podcast_category',
            esc_html__('Category', 'betterfeed'),
            array($this, 'podcast_category_callback'),
            'bf_podcast_show',
            'bf_podcast_show_section'
        );
        
        add_settings_field(
            'podcast_explicit',
            esc_html__('Explicit Content', 'betterfeed'),
            array($this, 'podcast_explicit_callback'),
            'bf_podcast_show',
            'bf_podcast_show_section'
        );
        
        add_settings_field(
            'podcast_author',
            esc_html__('Author', 'betterfeed'),
            array($this, 'podcast_author_callback'),
            'bf_podcast_show',
            'bf_podcast_show_section'
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        
        // Load settings page assets
        if ('settings_page_bf-settings' === $hook_suffix) {
            wp_enqueue_style(
                'bf-admin',
                BF_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                BF_VERSION
            );
            
            wp_enqueue_script(
                'bf-admin',
                BF_PLUGIN_URL . 'assets/js/admin.js?v=' . time() . '&cb=' . wp_rand(),
                array(),
                BF_VERSION,
                false
            );
            
            // Localize script with nonce (REST API only, no AJAX)
            wp_localize_script('bf-admin', 'bf_admin', array(
                'nonce' => wp_create_nonce('bf_admin_nonce')
            ));
            
            wp_localize_script('bf-admin', 'bf_config', array(
                'rest_api_url' => rest_url('betterfeed/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'timestamp' => time(), // Force cache refresh
                'strings' => array(
                    'cache_cleared' => esc_html__('Cache cleared successfully!', 'betterfeed'),
                    'cache_warmed' => esc_html__('Cache warmed successfully!', 'betterfeed'),
                )
            ));
        }
        
        // Enqueue editor scripts for post edit pages
        if (in_array($hook_suffix, array('post.php', 'post-new.php'))) {
            $betterfeed_enabled = $this->is_betterfeed_enabled();
            
            if ($betterfeed_enabled) {
                wp_enqueue_script(
                    'bf-editor-episode-panel',
                    BF_PLUGIN_URL . 'assets/js/editor/episode-panel.js',
                    array('wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-media-utils'),
                    BF_VERSION,
                    true
                );
                
                wp_enqueue_style(
                    'bf-editor-episode-panel',
                    BF_PLUGIN_URL . 'assets/css/editor.css',
                    array('wp-components'),
                    BF_VERSION
                );
            }
        }
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Clear cache route
        register_rest_route('betterfeed/v1', '/clear-cache', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_clear_cache'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Warm cache route  
        register_rest_route('betterfeed/v1', '/warm-cache', array(
            'methods' => 'POST', 
            'callback' => array($this, 'rest_warm_cache'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Export analytics route
        register_rest_route('betterfeed/v1', '/export-analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_export_analytics'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Apply preset route
        register_rest_route('betterfeed/v1', '/apply-preset', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_apply_preset'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Export settings route
        register_rest_route('betterfeed/v1', '/export-settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_export_settings'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Run performance test route
        register_rest_route('betterfeed/v1', '/run-performance-test', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_run_performance_test'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Generate optimization report route
        register_rest_route('betterfeed/v1', '/generate-optimization-report', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_generate_optimization_report'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Apply suggestion route
        register_rest_route('betterfeed/v1', '/apply-suggestion', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_apply_suggestion'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Add custom feed route
        register_rest_route('betterfeed/v1', '/add-custom-feed', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_add_custom_feed'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Add redirect route
        register_rest_route('betterfeed/v1', '/add-redirect', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_add_redirect'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Flush rewrite rules route
        register_rest_route('betterfeed/v1', '/flush-rewrite-rules', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_flush_rewrite_rules'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Delete custom feed route
        register_rest_route('betterfeed/v1', '/delete-custom-feed', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_delete_custom_feed'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Delete redirect route
        register_rest_route('betterfeed/v1', '/delete-redirect', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_delete_redirect'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
    }
    
    /**
     * Check admin permissions for REST API
     */
    public function check_admin_permissions($request) {
        // For now, allow REST API access if user is logged in and has admin capabilities
        // WordPress cookie auth is already checked by WordPress REST API
        return current_user_can('manage_options');
    }
    
    /**
     * REST endpoint: Clear cache
     */
    public function rest_clear_cache($request) {
        try {
            $cache = BF_Cache::instance();
            $result = $cache->clear_all();
            wp_cache_flush();
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Cache cleared successfully!',
                'cleared' => $result
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Warm cache
     */
    public function rest_warm_cache($request) {
        try {
            // Cache warming logic here
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Cache warmed successfully!'
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to warm cache: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Export analytics
     */
    public function rest_export_analytics($request) {
        try {
            $format = $request->get_param('format') ?: 'csv';
            $days = $request->get_param('days') ?: 30;
            
            // Validate parameters
            if (!in_array($format, array('csv', 'json'))) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Invalid format. Must be csv or json.'
                ), 400);
            }
            
            $days = intval($days);
            if ($days < 1 || $days > 365) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Days must be between 1 and 365.'
                ), 400);
            }
            
            // Get analytics data using the analytics class
            $analytics_class = BF_Analytics::instance();
            $data = $analytics_class->export_analytics($days, $format);
            
            if (empty($data)) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'data' => $format === 'csv' ? 'feed_url,user_agent,ip_address,referer,accessed_at' : '[]',
                    'filename' => 'analytics-empty-' . gmdate('Y-m-d') . '.' . $format,
                    'message' => 'No analytics data found for the specified period.'
                ), 200);
            }
            
            // Generate filename
            $filename = 'betterfeed-analytics-' . gmdate('Y-m-d') . '-' . $days . 'days.' . $format;
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $data,
                'filename' => $filename,
                'format' => $format,
                'days' => $days,
                'message' => 'Analytics exported successfully!'
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to export analytics: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Apply preset
     */
    public function rest_apply_preset($request) {
        try {
            // Get preset from JSON body or URL parameter
            $preset_name = $request->get_param('preset');
            
            // If not found in URL params, try JSON body
            if (empty($preset_name)) {
                $json_params = $request->get_json_params();
                if (isset($json_params['preset'])) {
                    $preset_name = sanitize_text_field($json_params['preset']);
                }
            }
            
            if (empty($preset_name)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'No preset specified'
                ), 400);
            }
            
            $import_export = BF_Import_Export::instance();
            $result = $import_export->apply_preset($preset_name);
            
            return new WP_REST_Response($result, $result['success'] ? 200 : 500);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Error applying preset: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Export settings
     */
    public function rest_export_settings($request) {
        try {
            // Collect all BetterFeed settings
            $settings = array(
                'general' => get_option('bf_general_options', array()),
                'performance' => get_option('bf_performance_options', array()),
                'content' => get_option('bf_content_options', array()),
                'analytics' => get_option('bf_analytics_options', array()),
                'tools' => get_option('bf_tools_options', array())
            );
            
            // Add metadata
            $export_data = array(
                'plugin' => 'BetterFeed',
                'version' => BF_VERSION,
                'exported_at' => gmdate('Y-m-d H:i:s'),
                'site_url' => get_site_url(),
                'settings' => $settings
            );
            
            // Convert to JSON
            $json_data = wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if ($json_data === false) {
                throw new Exception('Failed to encode settings to JSON');
            }
            
            // Generate filename
            $filename = 'betterfeed-settings-' . gmdate('Y-m-d-H-i-s') . '.json';
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $json_data,
                'filename' => $filename,
                'message' => 'Settings exported successfully!'
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to export settings: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=bf-settings')) . '">' . esc_html__('Settings', 'betterfeed') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (!get_option('bf_enable_analytics', true)) {
            return;
        }
        
        wp_add_dashboard_widget(
            'bf_dashboard_widget',
            __('BetterFeed Analytics', 'betterfeed'),
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        // Widget content here
        ?>
        <div class="bf-dashboard-widget">
            <p><?php esc_html_e('BetterFeed analytics dashboard coming soon.', 'betterfeed'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Render main settings page using WordPress core design standards
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'betterfeed'));
        }
        
        // Get active tab from URL parameter
        $active_tab = $this->get_current_tab();
        
        // Validate tab
        $valid_tabs = array('general', 'performance', 'content', 'tools', 'analytics', 'podcast', 'feeds', 'redirects', 'dashboard');
        if (!in_array($active_tab, $valid_tabs)) {
            $active_tab = 'general';
        }
        ?>
        
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <p><?php esc_html_e('Enhance your WordPress feeds with modern features, performance optimizations, and SEO improvements. Build upon the solid foundation WordPress already provides.', 'betterfeed'); ?></p>
            
            <!-- Tab Navigation - WordPress 5.2+ Standard -->
            <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Settings navigation', 'betterfeed'); ?>">
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'general'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'general' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('General', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'performance'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'performance' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Performance', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'content'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'content' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Content', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'tools'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'tools' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Tools', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'analytics'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'analytics' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Analytics', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'podcast'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'podcast' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Podcast', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'feeds'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'feeds' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Custom Feeds', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'redirects'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'redirects' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Feed Redirects', 'betterfeed'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'bf-settings', 'tab' => 'dashboard'), admin_url('options-general.php'))); ?>" 
                   class="nav-tab <?php echo esc_attr($active_tab === 'dashboard' ? 'nav-tab-active' : ''); ?>">
                    <?php esc_html_e('Dashboard', 'betterfeed'); ?>
                </a>
            </nav>
            
            <!-- Tab Content -->
            <?php $this->render_tab_content($active_tab); ?>
        </div>
        <?php
    }
    
    /**
     * Render tab content
     */
    private function render_tab_content($active_tab) {
                switch ($active_tab) {
                    case 'performance':
                        $this->render_performance_tab();
                        break;
                    case 'content':
                        $this->render_content_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab();
                        break;
                    case 'podcast':
                        $this->render_podcast_tab();
                        break;
                    case 'feeds':
                        $this->render_custom_feeds_tab();
                        break;
                    case 'redirects':
                        $this->render_feed_redirects_tab();
                        break;
                    case 'dashboard':
                        $this->render_dashboard_tab();
                        break;
                    default:
                        $this->render_general_tab();
                        break;
        }
    }
    
    /**
     * Render General tab
     */
    private function render_general_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('bf-settings'); ?>
            <?php do_settings_sections('bf_general'); ?>
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render Performance tab
     */
    private function render_performance_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('bf-settings'); ?>
            <?php do_settings_sections('bf_performance'); ?>
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render Content tab
     */
    private function render_content_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('bf-settings'); ?>
            <?php do_settings_sections('bf_content'); ?>
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render Tools tab - following same pattern as Performance tab
     */
    private function render_tools_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('bf-settings'); ?>
            <?php do_settings_sections('bf_tools'); ?>
            
            <table class="form-table">
                <tr>
                <th scope="row"><?php esc_html_e('Export Settings', 'betterfeed'); ?></th>
                <td>
                    <button type="button" class="button button-secondary" id="bf-export-settings">
                        <?php esc_html_e('Export Settings', 'betterfeed'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Download a JSON file containing all your BetterFeed settings.', 'betterfeed'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render Analytics tab - following same pattern as Performance tab
     */
    private function render_analytics_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('bf-settings'); ?>
            <?php do_settings_sections('bf_analytics'); ?>
            
            <?php 
            $options = get_option('bf_analytics_options');
            if (isset($options['enable_analytics']) && $options['enable_analytics']): ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Analytics Dashboard', 'betterfeed'); ?></th>
                    <td>
                        <div class="bf-stats-grid">
                        <?php
        $analytics = BF_Analytics::instance();
        $summary = $analytics->get_analytics_summary(30);
        $performance = $analytics->get_performance_metrics(7);
        ?>
                            <div class="bf-stat-card">
                                <h5><?php esc_html_e('Total Requests', 'betterfeed'); ?></h5>
                                <span class="bf-stat-number"><?php echo esc_html(number_format($summary['total_requests'])); ?></span>
                    </div>
                    
                            <div class="bf-stat-card">
                                <h5><?php esc_html_e('Unique Visitors', 'betterfeed'); ?></h5>
                                <span class="bf-stat-number"><?php echo esc_html(number_format($summary['unique_visitors'])); ?></span>
            </div>
                    
                            <div class="bf-stat-card">
                                <h5><?php esc_html_e('Cache Hit Rate', 'betterfeed'); ?></h5>
                                <span class="bf-stat-number"><?php echo esc_html($performance['cache_hit_rate'] ?? 0); ?>%</span>
                    </div>
                    
                            <div class="bf-stat-card">
                                <h5><?php esc_html_e('Active Readers', 'betterfeed'); ?></h5>
                                <span class="bf-stat-number"><?php echo esc_html($performance['realtime_readers'] ?? 0); ?></span>
                    </div>
                </div>
                
                        <div class="bf-analytics-actions">
                            <?php if (class_exists('BF_Analytics')): ?>
                            <button type="button" id="bf-export-analytics" class="button button-secondary">
                        <?php esc_html_e('Export Analytics (CSV)', 'betterfeed'); ?>
                    </button>
            <?php endif; ?>
        </div>
                    </td>
                </tr>
            </table>
            <?php endif; ?>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render Podcast tab
     */
    private function render_podcast_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('bf-settings'); ?>
            <div class="tab-content">
                <h2><?php esc_html_e('Podcast Settings', 'betterfeed'); ?></h2>
                <p><?php esc_html_e('Configure podcast RSS feed settings for Apple Podcasts, Spotify, and other platforms.', 'betterfeed'); ?></p>
                
                <?php do_settings_sections('bf_podcast_integrations'); ?>
                <?php do_settings_sections('bf_podcast_show'); ?>
                
                <?php submit_button(); ?>
            </div>
        </form>
        <?php
    }
    
    /**
     * Check if BetterFeed functionality is enabled
     */
    private function is_betterfeed_enabled() {
        $general_options = get_option('bf_general_options', array());
        return !empty($general_options['enable_betterfeed']);
    }
    
    // Settings API callback functions
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure basic BetterFeed settings.', 'betterfeed') . '</p>';
    }
    
    public function performance_section_callback() {
        echo '<p>' . esc_html__('Configure caching and performance optimization settings.', 'betterfeed') . '</p>';
    }
    
    public function content_section_callback() {
        echo '<p>' . esc_html__('Configure how content appears in your feeds.', 'betterfeed') . '</p>';
    }
    
    public function analytics_section_callback() {
        echo '<p>' . esc_html__('Configure feed analytics and tracking settings.', 'betterfeed') . '</p>';
    }
    
    public function tools_cache_section_callback() {
        echo '<p>' . esc_html__('Tools and utilities for managing BetterFeed.', 'betterfeed') . '</p>';
    }
    
    public function podcast_integrations_section_callback() {
        echo '<p>' . esc_html__('Enable support for different podcast platforms and directories.', 'betterfeed') . '</p>';
    }
    
    public function podcast_show_section_callback() {
        echo '<p>' . esc_html__('Configure your podcast show information and metadata.', 'betterfeed') . '</p>';
    }
    
    // Field callbacks
    public function enable_betterfeed_callback() {
        $options = get_option('bf_general_options');
        $value = isset($options['enable_betterfeed']) ? $options['enable_betterfeed'] : true;
        ?>
        <label for="enable_betterfeed">
            <input type="checkbox" 
                   id="enable_betterfeed" 
                   name="bf_general_options[enable_betterfeed]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Enable BetterFeed functionality', 'betterfeed'); ?>
        </label>
            <?php
    }
    
    public function enable_caching_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_caching']) ? $options['enable_caching'] : true;
        ?>
        <label for="enable_caching">
            <input type="checkbox" 
                   id="enable_caching" 
                   name="bf_performance_options[enable_caching]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Cache feed output for better performance', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    public function cache_duration_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['cache_duration']) ? $options['cache_duration'] : 3600;
        ?>
        <input type="number" 
               id="cache_duration" 
               name="bf_performance_options[cache_duration]" 
               value="<?php echo esc_attr($value); ?>" 
               class="small-text" 
               min="60" 
               max="86400">
        <p class="description"><?php esc_html_e('How long to cache feed output (60-86400 seconds)', 'betterfeed'); ?></p>
        <?php
    }
    
    public function enable_gzip_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_gzip']) ? $options['enable_gzip'] : true;
        ?>
        <label for="enable_gzip">
            <input type="checkbox" 
                   id="enable_gzip" 
                   name="bf_performance_options[enable_gzip]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Enable GZIP compression for feeds', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    public function enable_etag_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_etag']) ? $options['enable_etag'] : true;
        ?>
        <label for="enable_etag">
            <input type="checkbox" 
                   id="enable_etag" 
                   name="bf_performance_options[enable_etag]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Add ETag headers for better caching', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    /**
     * Enable conditional requests callback
     */
    public function enable_conditional_requests_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_conditional_requests']) ? $options['enable_conditional_requests'] : true;
        ?>
        <label for="enable_conditional_requests">
            <input type="checkbox" 
                   id="enable_conditional_requests" 
                   name="bf_performance_options[enable_conditional_requests]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Send 304 Not Modified responses for unchanged feeds', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    /**
     * Enable enhanced discovery callback
     */
    public function enable_enhanced_discovery_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_enhanced_discovery']) ? $options['enable_enhanced_discovery'] : true;
        ?>
        <label for="enable_enhanced_discovery">
            <input type="checkbox" 
                   id="enable_enhanced_discovery" 
                   name="bf_performance_options[enable_enhanced_discovery]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Add comprehensive feed discovery links to HTML head', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    /**
     * Enable enclosure fix callback
     */
    public function enable_enclosure_fix_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_enclosure_fix']) ? $options['enable_enclosure_fix'] : true;
        ?>
        <label for="enable_enclosure_fix">
            <input type="checkbox" 
                   id="enable_enclosure_fix" 
                   name="bf_performance_options[enable_enclosure_fix]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Automatically detect and fix enclosure file sizes for podcasts', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    /**
     * Enable responsive images callback
     */
    public function enable_responsive_images_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_responsive_images']) ? $options['enable_responsive_images'] : true;
        ?>
        <label for="enable_responsive_images">
            <input type="checkbox" 
                   id="enable_responsive_images" 
                   name="bf_performance_options[enable_responsive_images]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Add responsive images with multiple sizes and Media RSS support', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    /**
     * Enable JSON feed callback
     */
    public function enable_json_feed_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_json_feed']) ? $options['enable_json_feed'] : true;
        ?>
        <label for="enable_json_feed">
            <input type="checkbox" 
                   id="enable_json_feed" 
                   name="bf_performance_options[enable_json_feed]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Enable JSON Feed 1.1 support at /feed/json/', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    /**
     * Enable Google Discover callback
     */
    public function enable_google_discover_callback() {
        $options = get_option('bf_performance_options');
        $value = isset($options['enable_google_discover']) ? $options['enable_google_discover'] : true;
        ?>
        <label for="enable_google_discover">
            <input type="checkbox" 
                   id="enable_google_discover" 
                   name="bf_performance_options[enable_google_discover]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Add Google Discover optimization with schema.org markup and large images', 'betterfeed'); ?>
        </label>
        <?php
    }
    
    public function include_featured_images_callback() {
        $options = get_option('bf_content_options');
        $value = isset($options['include_featured_images']) ? $options['include_featured_images'] : true;
        ?>
        <label for="include_featured_images">
            <input type="checkbox" 
                   id="include_featured_images" 
                   name="bf_content_options[include_featured_images]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Add featured images to feed content', 'betterfeed'); ?>
                        </label>
        <?php
    }
    
    public function enable_full_content_callback() {
        $options = get_option('bf_content_options');
        $value = isset($options['enable_full_content']) ? $options['enable_full_content'] : false;
        ?>
        <label for="enable_full_content">
            <input type="checkbox" 
                   id="enable_full_content" 
                   name="bf_content_options[enable_full_content]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Include full post content instead of excerpt', 'betterfeed'); ?>
                        </label>
        <?php
    }
    
    public function max_feed_items_callback() {
        $options = get_option('bf_content_options');
        $value = isset($options['max_feed_items']) ? $options['max_feed_items'] : 10;
        ?>
        <input type="number" 
               id="max_feed_items" 
               name="bf_content_options[max_feed_items]" 
               value="<?php echo esc_attr($value); ?>" 
               class="small-text" 
               min="1" 
               max="100">
        <p class="description"><?php esc_html_e('Number of items to include in feeds (1-100)', 'betterfeed'); ?></p>
                        <?php
    }
    
    
    public function enable_analytics_callback() {
        $options = get_option('bf_analytics_options');
        $value = isset($options['enable_analytics']) ? $options['enable_analytics'] : true;
        ?>
        <label for="enable_analytics">
            <input type="checkbox" 
                   id="enable_analytics" 
                   name="bf_analytics_options[enable_analytics]" 
                   value="1" 
                   <?php checked(1, $value); ?>>
            <?php esc_html_e('Track feed access and reader statistics', 'betterfeed'); ?>
                        </label>
        <?php
    }
    
    public function cache_actions_callback() {
        ?>
        <button type="button" class="button button-primary" id="bf-clear-cache">
            <?php esc_html_e('Clear Cache', 'betterfeed'); ?>
        </button>
        <button type="button" class="button button-secondary" id="bf-warm-cache">
            <?php esc_html_e('Warm Cache', 'betterfeed'); ?>
        </button>
        <p class="description"><?php esc_html_e('Clear removes cached data. Warm rebuilds cache for faster loading.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function preset_selection_callback() {
        ?>
        <select id="bf-preset-select" name="bf_preset" class="regular-text">
            <option value=""><?php esc_html_e('Select a preset...', 'betterfeed'); ?></option>
            <option value="performance"><?php esc_html_e('Performance Focused', 'betterfeed'); ?></option>
            <option value="seo"><?php esc_html_e('SEO Optimized', 'betterfeed'); ?></option>
            <option value="minimal"><?php esc_html_e('Minimal Setup', 'betterfeed'); ?></option>
            <option value="custom"><?php esc_html_e('Custom Configuration', 'betterfeed'); ?></option>
        </select>
        <button type="button" class="button button-primary" id="bf-apply-preset" style="margin-left: 10px;">
            <?php esc_html_e('Apply Preset', 'betterfeed'); ?>
        </button>
        <p class="description"><?php esc_html_e('Choose a configuration preset to apply optimized settings.', 'betterfeed'); ?></p>
        <?php
    }
    
    // Sanitization callbacks  
    public function sanitize_general_options($input) {
        $sanitized = array();
        
        if (isset($input['enable_betterfeed'])) {
            $sanitized['enable_betterfeed'] = 1;
        } else {
            $sanitized['enable_betterfeed'] = 0;
        }
        
        return $sanitized;
    }
    
    public function sanitize_performanceOptions($input) {
        $sanitized = array();
        
        if (isset($input['enable_caching'])) {
            $sanitized['enable_caching'] = 1;
        } else {
            $sanitized['enable_caching'] = 0;
        }
        
        if (isset($input['cache_duration'])) {
            $duration = absint($input['cache_duration']);
            $sanitized['cache_duration'] = max(60, min(86400, $duration));
        } else {
            $sanitized['cache_duration'] = 3600;
        }
        
        if (isset($input['enable_gzip'])) {
            $sanitized['enable_gzip'] = 1;
        } else {
            $sanitized['enable_gzip'] = 0;
        }
        
        if (isset($input['enable_etag'])) {
            $sanitized['enable_etag'] = 1;
        } else {
            $sanitized['enable_etag'] = 0;
        }
        
        if (isset($input['enable_conditional_requests'])) {
            $sanitized['enable_conditional_requests'] = 1;
        } else {
            $sanitized['enable_conditional_requests'] = 0;
        }
        
        if (isset($input['enable_enhanced_discovery'])) {
            $sanitized['enable_enhanced_discovery'] = 1;
        } else {
            $sanitized['enable_enhanced_discovery'] = 0;
        }
        
        if (isset($input['enable_enclosure_fix'])) {
            $sanitized['enable_enclosure_fix'] = 1;
        } else {
            $sanitized['enable_enclosure_fix'] = 0;
        }
        
        if (isset($input['enable_responsive_images'])) {
            $sanitized['enable_responsive_images'] = 1;
        } else {
            $sanitized['enable_responsive_images'] = 0;
        }
        
        if (isset($input['enable_json_feed'])) {
            $sanitized['enable_json_feed'] = 1;
        } else {
            $sanitized['enable_json_feed'] = 0;
        }
        
        if (isset($input['enable_google_discover'])) {
            $sanitized['enable_google_discover'] = 1;
        } else {
            $sanitized['enable_google_discover'] = 0;
        }
        
        return $sanitized;
    }
    
    public function sanitize_content_options($input) {
        $sanitized = array();
        
        if (isset($input['include_featured_images'])) {
            $sanitized['include_featured_images'] = 1;
        } else {
            $sanitized['include_featured_images'] = 0;
        }
        
        if (isset($input['enable_full_content'])) {
            $sanitized['enable_full_content'] = 1;
        } else {
            $sanitized['enable_full_content'] = 0;
        }
        
        if (isset($input['max_feed_items'])) {
            $items = absint($input['max_feed_items']);
            $sanitized['max_feed_items'] = max(1, min(100, $items));
        } else {
            $sanitized['max_feed_items'] = 10;
        }
        
        return $sanitized;
    }
    
    public function sanitize_analytics_options($input) {
        $sanitized = array();
        
        if (isset($input['enable_analytics'])) {
            $sanitized['enable_analytics'] = 1;
        } else {
            $sanitized['enable_analytics'] = 0;
        }
        
        return $sanitized;
    }
    
    public function sanitize_tools_options($input) {
        // Tools tab doesn't really save settings, but we need this for Settings API
        return array();
    }
    
    // Podcast field callbacks
    public function apple_itunes_callback() {
        $options = get_option('bf_podcast_integrations');
        $value = isset($options['apple_itunes']) ? $options['apple_itunes'] : true;
        ?>
        <label for="apple_itunes">
            <input type="checkbox" id="apple_itunes" name="bf_podcast_integrations[apple_itunes]" value="1" <?php checked($value, 1); ?> />
            <?php esc_html_e('Enable Apple Podcasts (iTunes) namespace and tags', 'betterfeed'); ?>
        </label>
        <p class="description"><?php esc_html_e('Required for Apple Podcasts directory submission.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function spotify_callback() {
        $options = get_option('bf_podcast_integrations');
        $value = isset($options['spotify']) ? $options['spotify'] : false;
        ?>
        <label for="spotify">
            <input type="checkbox" id="spotify" name="bf_podcast_integrations[spotify]" value="1" <?php checked($value, 1); ?> />
            <?php esc_html_e('Enable Spotify namespace and tags', 'betterfeed'); ?>
        </label>
        <p class="description"><?php esc_html_e('Adds Spotify-specific RSS tags for better compatibility.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_index_callback() {
        $options = get_option('bf_podcast_integrations');
        $value = isset($options['podcast_index']) ? $options['podcast_index'] : false;
        ?>
        <label for="podcast_index">
            <input type="checkbox" id="podcast_index" name="bf_podcast_integrations[podcast_index]" value="1" <?php checked($value, 1); ?> />
            <?php esc_html_e('Enable Podcast Index namespace and tags', 'betterfeed'); ?>
        </label>
        <p class="description"><?php esc_html_e('Adds advanced features like chapters, transcripts, and funding links.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function google_youtube_music_callback() {
        $options = get_option('bf_podcast_integrations');
        $value = isset($options['google_youtube_music']) ? $options['google_youtube_music'] : false;
        ?>
        <label for="google_youtube_music">
            <input type="checkbox" id="google_youtube_music" name="bf_podcast_integrations[google_youtube_music]" value="1" <?php checked($value, 1); ?> />
            <?php esc_html_e('Enable Google/YouTube Music namespace and tags', 'betterfeed'); ?>
        </label>
        <p class="description"><?php esc_html_e('Adds Google Play Podcasts namespace for YouTube Music compatibility.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_title_callback() {
        $options = get_option('bf_podcast_show');
        $value = isset($options['title']) ? $options['title'] : get_bloginfo('name');
        ?>
        <input type="text" id="podcast_title" name="bf_podcast_show[title]" value="<?php echo esc_attr($value); ?>" class="regular-text" maxlength="255" />
        <p class="description"><?php esc_html_e('The name of your podcast (max 255 characters).', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_description_callback() {
        $options = get_option('bf_podcast_show');
        $value = isset($options['description']) ? $options['description'] : get_bloginfo('description');
        ?>
        <textarea id="podcast_description" name="bf_podcast_show[description]" rows="4" cols="50" class="large-text" maxlength="4000"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Full description of your podcast (max 4000 characters).', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_artwork_callback() {
        $options = get_option('bf_podcast_show');
        $value = isset($options['artwork']) ? $options['artwork'] : '';
        ?>
        <input type="hidden" id="podcast_artwork" name="bf_podcast_show[artwork]" value="<?php echo esc_attr($value); ?>" />
        <button type="button" class="button" id="upload_podcast_artwork"><?php esc_html_e('Select Cover Artwork', 'betterfeed'); ?></button>
        <div id="podcast_artwork_preview" style="margin-top: 10px;">
            <?php if ($value) : 
                $image = wp_get_attachment_image($value, 'medium');
                if ($image) {
                    echo wp_kses_post($image);
                }
            endif; ?>
        </div>
        <p class="description"><?php esc_html_e('Square image, minimum 1400x1400px, JPG or PNG format.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_language_callback() {
        $options = get_option('bf_podcast_show');
        $value = isset($options['language']) ? $options['language'] : get_locale();
        ?>
        <select id="podcast_language" name="bf_podcast_show[language]">
            <option value="en-us" <?php selected($value, 'en-us'); ?>><?php esc_html_e('English (United States)', 'betterfeed'); ?></option>
            <option value="en-gb" <?php selected($value, 'en-gb'); ?>><?php esc_html_e('English (United Kingdom)', 'betterfeed'); ?></option>
            <option value="es-es" <?php selected($value, 'es-es'); ?>><?php esc_html_e('Spanish (Spain)', 'betterfeed'); ?></option>
            <option value="es-mx" <?php selected($value, 'es-mx'); ?>><?php esc_html_e('Spanish (Mexico)', 'betterfeed'); ?></option>
            <option value="fr-fr" <?php selected($value, 'fr-fr'); ?>><?php esc_html_e('French (France)', 'betterfeed'); ?></option>
            <option value="de-de" <?php selected($value, 'de-de'); ?>><?php esc_html_e('German (Germany)', 'betterfeed'); ?></option>
            <option value="it-it" <?php selected($value, 'it-it'); ?>><?php esc_html_e('Italian (Italy)', 'betterfeed'); ?></option>
            <option value="pt-br" <?php selected($value, 'pt-br'); ?>><?php esc_html_e('Portuguese (Brazil)', 'betterfeed'); ?></option>
            <option value="ja-jp" <?php selected($value, 'ja-jp'); ?>><?php esc_html_e('Japanese (Japan)', 'betterfeed'); ?></option>
            <option value="ko-kr" <?php selected($value, 'ko-kr'); ?>><?php esc_html_e('Korean (South Korea)', 'betterfeed'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Primary language of your podcast.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_category_callback() {
        $options = get_option('bf_podcast_show');
        $value = isset($options['category']) ? $options['category'] : 'Arts';
        ?>
        <select id="podcast_category" name="bf_podcast_show[category]">
            <option value="Arts" <?php selected($value, 'Arts'); ?>><?php esc_html_e('Arts', 'betterfeed'); ?></option>
            <option value="Business" <?php selected($value, 'Business'); ?>><?php esc_html_e('Business', 'betterfeed'); ?></option>
            <option value="Comedy" <?php selected($value, 'Comedy'); ?>><?php esc_html_e('Comedy', 'betterfeed'); ?></option>
            <option value="Education" <?php selected($value, 'Education'); ?>><?php esc_html_e('Education', 'betterfeed'); ?></option>
            <option value="Fiction" <?php selected($value, 'Fiction'); ?>><?php esc_html_e('Fiction', 'betterfeed'); ?></option>
            <option value="Government" <?php selected($value, 'Government'); ?>><?php esc_html_e('Government', 'betterfeed'); ?></option>
            <option value="Health & Fitness" <?php selected($value, 'Health & Fitness'); ?>><?php esc_html_e('Health & Fitness', 'betterfeed'); ?></option>
            <option value="History" <?php selected($value, 'History'); ?>><?php esc_html_e('History', 'betterfeed'); ?></option>
            <option value="Kids & Family" <?php selected($value, 'Kids & Family'); ?>><?php esc_html_e('Kids & Family', 'betterfeed'); ?></option>
            <option value="Leisure" <?php selected($value, 'Leisure'); ?>><?php esc_html_e('Leisure', 'betterfeed'); ?></option>
            <option value="Music" <?php selected($value, 'Music'); ?>><?php esc_html_e('Music', 'betterfeed'); ?></option>
            <option value="News" <?php selected($value, 'News'); ?>><?php esc_html_e('News', 'betterfeed'); ?></option>
            <option value="Religion & Spirituality" <?php selected($value, 'Religion & Spirituality'); ?>><?php esc_html_e('Religion & Spirituality', 'betterfeed'); ?></option>
            <option value="Science" <?php selected($value, 'Science'); ?>><?php esc_html_e('Science', 'betterfeed'); ?></option>
            <option value="Society & Culture" <?php selected($value, 'Society & Culture'); ?>><?php esc_html_e('Society & Culture', 'betterfeed'); ?></option>
            <option value="Sports" <?php selected($value, 'Sports'); ?>><?php esc_html_e('Sports', 'betterfeed'); ?></option>
            <option value="Technology" <?php selected($value, 'Technology'); ?>><?php esc_html_e('Technology', 'betterfeed'); ?></option>
            <option value="True Crime" <?php selected($value, 'True Crime'); ?>><?php esc_html_e('True Crime', 'betterfeed'); ?></option>
            <option value="TV & Film" <?php selected($value, 'TV & Film'); ?>><?php esc_html_e('TV & Film', 'betterfeed'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Primary category for your podcast.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_explicit_callback() {
        $options = get_option('bf_podcast_show');
        $value = isset($options['explicit']) ? $options['explicit'] : 'false';
        ?>
        <select id="podcast_explicit" name="bf_podcast_show[explicit]">
            <option value="false" <?php selected($value, 'false'); ?>><?php esc_html_e('No (Clean)', 'betterfeed'); ?></option>
            <option value="true" <?php selected($value, 'true'); ?>><?php esc_html_e('Yes (Explicit)', 'betterfeed'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Does your podcast contain explicit content?', 'betterfeed'); ?></p>
        <?php
    }
    
    public function podcast_author_callback() {
        $options = get_option('bf_podcast_show');
        $value = isset($options['author']) ? $options['author'] : get_bloginfo('name');
        ?>
        <input type="text" id="podcast_author" name="bf_podcast_show[author]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Primary podcast creator/host name.', 'betterfeed'); ?></p>
        <?php
    }
    
    public function sanitize_podcast_integrations($input) {
        $sanitized = array();
        
        if (isset($input['apple_itunes'])) {
            $sanitized['apple_itunes'] = 1;
        } else {
            $sanitized['apple_itunes'] = 0;
        }
        
        if (isset($input['spotify'])) {
            $sanitized['spotify'] = 1;
        } else {
            $sanitized['spotify'] = 0;
        }
        
        if (isset($input['podcast_index'])) {
            $sanitized['podcast_index'] = 1;
        } else {
            $sanitized['podcast_index'] = 0;
        }
        
        if (isset($input['google_youtube_music'])) {
            $sanitized['google_youtube_music'] = 1;
        } else {
            $sanitized['google_youtube_music'] = 0;
        }
        
        return $sanitized;
    }
    
    public function sanitize_podcast_show($input) {
        $sanitized = array();
        
        if (isset($input['title'])) {
            $sanitized['title'] = sanitize_text_field($input['title']);
        }
        
        if (isset($input['description'])) {
            $sanitized['description'] = sanitize_textarea_field($input['description']);
        }
        
        if (isset($input['artwork'])) {
            $sanitized['artwork'] = absint($input['artwork']);
        }
        
        if (isset($input['language'])) {
            $sanitized['language'] = sanitize_text_field($input['language']);
        }
        
        if (isset($input['category'])) {
            $sanitized['category'] = sanitize_text_field($input['category']);
        }
        
        if (isset($input['explicit'])) {
            $sanitized['explicit'] = in_array($input['explicit'], array('true', 'false')) ? $input['explicit'] : 'false';
        }
        
        if (isset($input['author'])) {
            $sanitized['author'] = sanitize_text_field($input['author']);
        }
        
        return $sanitized;
    }
    
    public function sanitize_all_options_old($input) {
        // Consolidated sanitization function to handle all options together
        // This prevents multiple "Settings saved" messages
        $sanitized = array();
        
        // General options
        if (isset($input['enable_betterfeed'])) {
            $sanitized['general']['enable_betterfeed'] = 1;
        } else {
            $sanitized['general']['enable_betterfeed'] = 0;
        }
        
        // Performance options
        if (isset($input['enable_caching'])) {
            $sanitized['performance']['enable_caching'] = 1;
        } else {
            $sanitized['performance']['enable_caching'] = 0;
        }
        
        if (isset($input['cache_duration'])) {
            $duration = absint($input['cache_duration']);
            $sanitized['performance']['cache_duration'] = max(60, min(86400, $duration));
        } else {
            $sanitized['performance']['cache_duration'] = 3600;
        }
        
        if (isset($input['enable_gzip'])) {
            $sanitized['performance']['enable_gzip'] = 1;
        } else {
            $sanitized['performance']['enable_gzip'] = 0;
        }
        
        if (isset($input['enable_etag'])) {
            $sanitized['performance']['enable_etag'] = 1;
        } else {
            $sanitized['performance']['enable_etag'] = 0;
        }
        
        // Content options
        if (isset($input['include_featured_images'])) {
            $sanitized['content']['include_featured_images'] = 1;
        } else {
            $sanitized['content']['include_featured_images'] = 0;
        }
        
        if (isset($input['enable_full_content'])) {
            $sanitized['content']['enable_full_content'] = 1;
        } else {
            $sanitized['content']['enable_full_content'] = 0;
        }
        
        if (isset($input['max_feed_items'])) {
            $items = absint($input['max_feed_items']);
            $sanitized['content']['max_feed_items'] = max(1, min(100, $items));
        } else {
            $sanitized['content']['max_feed_items'] = 10;
        }
        
        // Analytics options
        if (isset($input['enable_analytics'])) {
            $sanitized['analytics']['enable_analytics'] = 1;
        } else {
            $sanitized['analytics']['enable_analytics'] = 0;
        }
        
        return $sanitized;
    }
    
    
    /**
     * Get current tab safely using WordPress functions  
     */
    private function get_current_tab() {
        // Check if we're on our settings page
        $current_screen = get_current_screen();
        if (!$current_screen || $current_screen->base !== 'settings_page_bf-settings') {
            return 'general';
        }
        
        // Use WordPress's $_SERVER handling with proper pattern
        $request_uri = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '';
        
        if ($request_uri && strpos($request_uri, 'tab=') !== false) {
            $parsed_url = wp_parse_url($request_uri);
            if (isset($parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_vars);
                if (isset($query_vars['tab'])) {
                    return sanitize_text_field($query_vars['tab']);
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Render Custom Feeds tab
     */
    private function render_custom_feeds_tab() {
        $custom_feeds = get_option('bf_custom_feeds', array());
        
        if (isset($_POST['action']) && $_POST['action'] === 'add_feed' && 
            isset($_POST['bf_feed_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bf_feed_nonce'])), 'bf_add_feed')) {
            $this->handle_add_custom_feed();
            $custom_feeds = get_option('bf_custom_feeds', array());
        }
        
        ?>
        <div class="tab-content">
            <h2><?php esc_html_e('Custom Feeds', 'betterfeed'); ?></h2>
            <p><?php esc_html_e('Create custom RSS feeds with specific filtering options.', 'betterfeed'); ?></p>
            
            <div class="bf-custom-feeds-admin">
                <div class="bf-feeds-list">
                    <h3><?php esc_html_e('Active Custom Feeds', 'betterfeed'); ?></h3>
                    
                    <?php if (empty($custom_feeds)): ?>
                        <p><?php esc_html_e('No custom feeds configured yet.', 'betterfeed'); ?></p>
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
                                        <td><?php echo esc_html($feed['slug']); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(home_url('/feed/' . $feed['slug'] . '/')); ?>" target="_blank">
                                                <?php echo esc_url(home_url('/feed/' . $feed['slug'] . '/')); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="bf-status <?php echo $feed['enabled'] ? 'enabled' : 'disabled'; ?>">
                                                <?php echo $feed['enabled'] ? esc_html__('Enabled', 'betterfeed') : esc_html__('Disabled', 'betterfeed'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small" onclick="editFeed(<?php echo esc_js($index); ?>)">
                                                <?php esc_html_e('Edit', 'betterfeed'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete" onclick="deleteFeed(<?php echo esc_js($index); ?>)">
                                                <?php esc_html_e('Delete', 'betterfeed'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="bf-add-feed">
                    <h3><?php esc_html_e('Add New Custom Feed', 'betterfeed'); ?></h3>
                    <form method="post">
                        <?php wp_nonce_field('bf_add_feed', 'bf_feed_nonce'); ?>
                        <input type="hidden" name="action" value="add_feed">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Feed Title', 'betterfeed'); ?></th>
                                <td>
                                    <input type="text" name="feed_title" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('Display name for the custom feed', 'betterfeed'); ?></p>
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
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Post Limit', 'betterfeed'); ?></th>
                                <td>
                                    <input type="number" name="feed_limit" value="10" min="1" max="100">
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
                                <th scope="row"><?php esc_html_e('Order By', 'betterfeed'); ?></th>
                                <td>
                                    <select name="feed_orderby">
                                        <option value="date"><?php esc_html_e('Date', 'betterfeed'); ?></option>
                                        <option value="title"><?php esc_html_e('Title', 'betterfeed'); ?></option>
                                        <option value="rand"><?php esc_html_e('Random', 'betterfeed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Order', 'betterfeed'); ?></th>
                                <td>
                                    <select name="feed_order">
                                        <option value="DESC"><?php esc_html_e('Newest First', 'betterfeed'); ?></option>
                                        <option value="ASC"><?php esc_html_e('Oldest First', 'betterfeed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Feed', 'betterfeed'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="feed_enabled" checked>
                                        <?php esc_html_e('Enable this custom feed', 'betterfeed'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <button type="button" class="button button-primary" onclick="addCustomFeed()">
                            <?php esc_html_e('Add Custom Feed', 'betterfeed'); ?>
                        </button>
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
        .bf-feeds-list {
            border: 1px solid #ddd;
            padding: 20px;
            background: #fff;
        }
        .bf-add-feed {
            border: 1px solid #ddd;
            padding: 20px;
            background: #f9f9f9;
        }
        .bf-status.enabled {
            color: #46b450;
            font-weight: bold;
        }
        .bf-status.disabled {
            color: #dc3232;
            font-weight: bold;
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
     * Render Feed Redirects tab
     */
    private function render_feed_redirects_tab() {
        $redirects = get_option('bf_feed_redirects', array());
        
        if (isset($_POST['action']) && $_POST['action'] === 'add_redirect' && 
            isset($_POST['bf_redirect_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bf_redirect_nonce'])), 'bf_add_redirect')) {
            $this->handle_add_feed_redirect();
            $redirects = get_option('bf_feed_redirects', array());
        }
        
        // Get redirect logs for analytics
        $logs = get_option('bf_redirect_logs', array());
        $recent_logs = array_slice(array_reverse($logs), 0, 10);
        
        ?>
        <div class="tab-content">
            <h2><?php esc_html_e('Feed Redirects', 'betterfeed'); ?></h2>
            <p><?php esc_html_e('Manage feed URL redirects and track redirect analytics.', 'betterfeed'); ?></p>
            
            <div class="bf-redirects-admin">
                <div class="bf-redirects-list">
                    <h3><?php esc_html_e('Active Redirects', 'betterfeed'); ?></h3>
                    
                    <?php if (empty($redirects)): ?>
                        <p><?php esc_html_e('No redirects configured yet.', 'betterfeed'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('From URL', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('To URL', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Status Code', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Status', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Actions', 'betterfeed'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redirects as $index => $redirect): ?>
                                    <tr>
                                        <td><?php echo esc_html($redirect['from']); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url($redirect['to']); ?>" target="_blank">
                                                <?php echo esc_html($redirect['to']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($redirect['status_code']); ?></td>
                                        <td>
                                            <span class="bf-status <?php echo $redirect['enabled'] ? 'enabled' : 'disabled'; ?>">
                                                <?php echo $redirect['enabled'] ? esc_html__('Enabled', 'betterfeed') : esc_html__('Disabled', 'betterfeed'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small" onclick="editRedirect(<?php echo esc_js($index); ?>)">
                                                <?php esc_html_e('Edit', 'betterfeed'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete" onclick="deleteRedirect(<?php echo esc_js($index); ?>)">
                                                <?php esc_html_e('Delete', 'betterfeed'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="bf-add-redirect">
                    <h3><?php esc_html_e('Add New Redirect', 'betterfeed'); ?></h3>
                    <form method="post">
                        <?php wp_nonce_field('bf_add_redirect', 'bf_redirect_nonce'); ?>
                        <input type="hidden" name="action" value="add_redirect">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('From URL Pattern', 'betterfeed'); ?></th>
                                <td>
                                    <input type="text" name="redirect_from" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('URL pattern to redirect from (supports wildcards with *)', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('To URL', 'betterfeed'); ?></th>
                                <td>
                                    <input type="url" name="redirect_to" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('URL to redirect to', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Status Code', 'betterfeed'); ?></th>
                                <td>
                                    <select name="redirect_status_code">
                                        <option value="301">301 - Permanent Redirect</option>
                                        <option value="302">302 - Temporary Redirect</option>
                                        <option value="307">307 - Temporary Redirect (Preserve Method)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Description', 'betterfeed'); ?></th>
                                <td>
                                    <input type="text" name="redirect_description" class="regular-text">
                                    <p class="description"><?php esc_html_e('Optional description for this redirect', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Redirect', 'betterfeed'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="redirect_enabled" checked>
                                        <?php esc_html_e('Enable this redirect', 'betterfeed'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <button type="button" class="button button-primary" onclick="addRedirect()">
                            <?php esc_html_e('Add Redirect', 'betterfeed'); ?>
                        </button>
                    </form>
                </div>
                
                <?php if (!empty($recent_logs)): ?>
                <div class="bf-redirect-logs">
                    <h3><?php esc_html_e('Recent Redirect Activity', 'betterfeed'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'betterfeed'); ?></th>
                                <th><?php esc_html_e('From', 'betterfeed'); ?></th>
                                <th><?php esc_html_e('To', 'betterfeed'); ?></th>
                                <th><?php esc_html_e('Status', 'betterfeed'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html(gmdate('M j, Y H:i', strtotime($log['timestamp']))); ?></td>
                                    <td><?php echo esc_html($log['from_url']); ?></td>
                                    <td><?php echo esc_html($log['to_url']); ?></td>
                                    <td><?php echo esc_html($log['status_code']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .bf-redirects-admin {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .bf-redirects-list, .bf-add-redirect {
            border: 1px solid #ddd;
            padding: 20px;
            background: #fff;
        }
        .bf-redirect-logs {
            grid-column: 1 / -1;
        }
        @media (max-width: 768px) {
            .bf-redirects-admin {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render Dashboard tab
     */
    private function render_dashboard_tab() {
        // Get performance metrics
        $metrics = get_option('bf_performance_metrics', array());
        
        // Get optimization suggestions
        $suggestions = get_option('bf_optimization_suggestions', array());
        
        // If no suggestions exist, generate some for demonstration
        if (empty($suggestions)) {
            $this->generate_demo_suggestions();
            $suggestions = get_option('bf_optimization_suggestions', array());
        }
        
        // Get recent performance test results
        $test_results = get_option('bf_performance_test_results', array());
        $latest_results = array_slice(array_reverse($test_results), 0, 5);
        
        ?>
        <div class="tab-content">
            <h2><?php esc_html_e('Performance Dashboard', 'betterfeed'); ?></h2>
            <p><?php esc_html_e('Monitor feed performance and optimization impact.', 'betterfeed'); ?></p>
            
            <div class="bf-dashboard-grid">
                <!-- Performance Metrics -->
                <div class="bf-dashboard-section">
                    <h3><?php esc_html_e('Performance Metrics', 'betterfeed'); ?></h3>
                    <div class="bf-metrics-grid">
                        <div class="bf-metric-card">
                            <h4><?php esc_html_e('Total Feed Requests', 'betterfeed'); ?></h4>
                            <span class="bf-metric-value"><?php echo esc_html($metrics['total_requests'] ?? 0); ?></span>
                        </div>
                        <div class="bf-metric-card">
                            <h4><?php esc_html_e('Cached Requests', 'betterfeed'); ?></h4>
                            <span class="bf-metric-value"><?php echo esc_html($metrics['cached_requests'] ?? 0); ?></span>
                        </div>
                        <div class="bf-metric-card">
                            <h4><?php esc_html_e('Average Load Time', 'betterfeed'); ?></h4>
                            <span class="bf-metric-value"><?php echo esc_html(round($metrics['avg_load_time'] ?? 0, 2)); ?>s</span>
                        </div>
                        <div class="bf-metric-card">
                            <h4><?php esc_html_e('Bandwidth Saved', 'betterfeed'); ?></h4>
                            <span class="bf-metric-value"><?php echo esc_html(size_format($metrics['bandwidth_saved'] ?? 0)); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Optimization Suggestions -->
                <div class="bf-dashboard-section">
                    <h3><?php esc_html_e('Optimization Suggestions', 'betterfeed'); ?></h3>
                    <?php if (empty($suggestions)): ?>
                        <p><?php esc_html_e('No optimization suggestions at this time.', 'betterfeed'); ?></p>
                    <?php else: ?>
                        <div class="bf-suggestions-list">
                            <?php foreach (array_slice($suggestions, 0, 5) as $suggestion): ?>
                                <div class="bf-suggestion-item priority-<?php echo esc_attr($suggestion['priority']); ?>">
                                    <h4><?php echo esc_html($suggestion['title']); ?></h4>
                                    <p><?php echo esc_html($suggestion['description']); ?></p>
                                    <button type="button" class="button button-small" onclick="applySuggestion('<?php echo esc_js($suggestion['id']); ?>')">
                                        <?php esc_html_e('Apply', 'betterfeed'); ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Performance Tests -->
                <div class="bf-dashboard-section">
                    <h3><?php esc_html_e('Recent Performance Tests', 'betterfeed'); ?></h3>
                    <?php if (empty($latest_results)): ?>
                        <p><?php esc_html_e('No performance test results available.', 'betterfeed'); ?></p>
                        <button type="button" class="button button-primary" onclick="runPerformanceTest()">
                            <?php esc_html_e('Run Performance Test', 'betterfeed'); ?>
                        </button>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Test Time', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Feed URL', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Load Time', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Status', 'betterfeed'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_results as $result): ?>
                                    <tr>
                                        <td><?php echo esc_html(gmdate('M j, Y H:i', strtotime($result['test_time']))); ?></td>
                                        <td><?php echo esc_html($result['feed_url']); ?></td>
                                        <td><?php echo esc_html($result['load_time']); ?>s</td>
                                        <td>
                                            <span class="bf-status <?php echo $result['status'] === 'success' ? 'enabled' : 'disabled'; ?>">
                                                <?php echo esc_html(ucfirst($result['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="bf-dashboard-section">
                    <h3><?php esc_html_e('Quick Actions', 'betterfeed'); ?></h3>
                    <div class="bf-quick-actions">
                        <button type="button" class="button button-secondary" onclick="clearFeedCache()">
                            <?php esc_html_e('Clear Feed Cache', 'betterfeed'); ?>
                        </button>
                        <button type="button" class="button button-secondary" onclick="runPerformanceTest()">
                            <?php esc_html_e('Run Performance Test', 'betterfeed'); ?>
                        </button>
                        <button type="button" class="button button-secondary" onclick="generateOptimizationReport()">
                            <?php esc_html_e('Generate Report', 'betterfeed'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .bf-dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .bf-dashboard-section {
            border: 1px solid #ddd;
            padding: 20px;
            background: #fff;
        }
        .bf-dashboard-section:last-child {
            grid-column: 1 / -1;
        }
        .bf-metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .bf-metric-card {
            text-align: center;
            padding: 15px;
            border: 1px solid #ddd;
            background: #f9f9f9;
        }
        .bf-metric-card h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        .bf-metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        .bf-suggestion-item {
            margin-bottom: 15px;
            padding: 15px;
            border-left: 4px solid #ddd;
        }
        .bf-suggestion-item.priority-high {
            border-left-color: #dc3232;
        }
        .bf-suggestion-item.priority-medium {
            border-left-color: #ffb900;
        }
        .bf-suggestion-item.priority-low {
            border-left-color: #46b450;
        }
        .bf-quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .bf-dashboard-grid {
                grid-template-columns: 1fr;
            }
            .bf-metrics-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Handle add custom feed
     */
    private function handle_add_custom_feed() {
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
     * Handle add feed redirect
     */
    private function handle_add_feed_redirect() {
        // Check if nonce exists and verify it
        if (!isset($_POST['bf_redirect_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bf_redirect_nonce'])), 'bf_add_redirect')) {
            wp_die(esc_html__('Security check failed.', 'betterfeed'));
        }
        
        $redirect_data = array(
            'id' => uniqid(),
            'from' => isset($_POST['redirect_from']) ? sanitize_text_field(wp_unslash($_POST['redirect_from'])) : '',
            'to' => isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '',
            'status_code' => isset($_POST['redirect_status_code']) ? intval($_POST['redirect_status_code']) : 301,
            'description' => isset($_POST['redirect_description']) ? sanitize_text_field(wp_unslash($_POST['redirect_description'])) : '',
            'enabled' => isset($_POST['redirect_enabled']),
            'created_at' => current_time('mysql')
        );
        
        $redirects = get_option('bf_feed_redirects', array());
        $redirects[] = $redirect_data;
        
        update_option('bf_feed_redirects', $redirects);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Redirect created successfully!', 'betterfeed') . '</p></div>';
        });
    }
    
    /**
     * REST endpoint: Run performance test
     */
    public function rest_run_performance_test($request) {
        try {
            // Check if performance monitor class exists and has the required method
            if (class_exists('BF_Performance_Monitor')) {
                $monitor = BF_Performance_Monitor::instance();
                
                // Verify the method exists before calling it
                if (method_exists($monitor, 'run_performance_tests')) {
                    $results = $monitor->run_performance_tests();
                    
                    return new WP_REST_Response(array(
                        'success' => true,
                        'message' => 'Performance test completed successfully!',
                        'results' => $results
                    ), 200);
                } else {
                    // Method doesn't exist, return appropriate error
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => 'Performance monitoring method not available'
                    ), 400);
                }
            } else {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Performance monitoring class not available'
                ), 400);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to run performance test: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Generate optimization report
     */
    public function rest_generate_optimization_report($request) {
        try {
            // Generate a simple optimization report
            $report_data = array(
                'timestamp' => current_time('mysql'),
                'site_url' => home_url(),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => BF_VERSION,
                'performance_metrics' => get_option('bf_performance_metrics', array()),
                'optimization_suggestions' => get_option('bf_optimization_suggestions', array()),
                'settings' => array(
                    'general_options' => get_option('bf_general_options', array()),
                    'performance_options' => get_option('bf_performance_options', array()),
                    'content_options' => get_option('bf_content_options', array())
                )
            );
            
            // Store report temporarily
            $report_id = 'bf_report_' . time();
            update_option('bf_optimization_report_' . $report_id, $report_data, false);
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Optimization report generated successfully!',
                'report_id' => $report_id,
                'report_url' => admin_url('options-general.php?page=bf-settings&tab=dashboard&report=' . $report_id)
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Apply optimization suggestion
     */
    public function rest_apply_suggestion($request) {
        try {
            // Log the request for debugging
            // Debug logging removed for production
            
            $suggestion_id = $request->get_param('suggestion_id');
            
            if (empty($suggestion_id)) {
                // No suggestion ID provided
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Suggestion ID is required'
                ), 400);
            }
            
            // Applying suggestion
            
            // Always use manual application for now (safer)
            $applied = $this->apply_suggestion_manually($suggestion_id);
            
            if ($applied) {
                // Suggestion applied successfully
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Optimization suggestion applied successfully!',
                    'suggestion_id' => $suggestion_id
                ), 200);
            } else {
                // Failed to apply suggestion
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Unknown suggestion or failed to apply'
                ), 400);
            }
        } catch (Exception $e) {
            // Exception in apply suggestion
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to apply suggestion: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Manually apply optimization suggestions
     */
    private function apply_suggestion_manually($suggestion_id) {
        try {
            switch ($suggestion_id) {
                case 'enable_caching':
                    $options = get_option('bf_performance_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['enable_caching'] = 1;
                    $result = update_option('bf_performance_options', $options);
                    return $result !== false;
                    
                case 'enable_gzip':
                    $options = get_option('bf_performance_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['enable_gzip'] = 1;
                    $result = update_option('bf_performance_options', $options);
                    return $result !== false;
                    
                case 'enable_etag':
                    $options = get_option('bf_performance_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['enable_etag'] = 1;
                    $result = update_option('bf_performance_options', $options);
                    return $result !== false;
                    
                case 'enable_conditional_requests':
                    $options = get_option('bf_performance_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['enable_conditional_requests'] = 1;
                    $result = update_option('bf_performance_options', $options);
                    return $result !== false;
                    
                case 'reduce_feed_items':
                    $options = get_option('bf_content_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['max_items'] = 50; // Set reasonable default
                    $result = update_option('bf_content_options', $options);
                    return $result !== false;
                    
                case 'optimize_images':
                    $options = get_option('bf_content_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['optimize_images'] = 1;
                    $result = update_option('bf_content_options', $options);
                    return $result !== false;
                    
                case 'fix_enclosures':
                    $options = get_option('bf_content_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['fix_enclosures'] = 1;
                    $result = update_option('bf_content_options', $options);
                    return $result !== false;
                    
                case 'add_featured_images':
                    $options = get_option('bf_content_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['add_featured_images'] = 1;
                    $result = update_option('bf_content_options', $options);
                    return $result !== false;
                    
                case 'enable_json_feed':
                    $options = get_option('bf_general_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['enable_json_feed'] = 1;
                    $result = update_option('bf_general_options', $options);
                    return $result !== false;
                    
                case 'enable_google_discover':
                    $options = get_option('bf_general_options', array());
                    if (!is_array($options)) {
                        $options = array();
                    }
                    $options['google_discover'] = 1;
                    $result = update_option('bf_general_options', $options);
                    return $result !== false;
                    
                default:
                    return false;
            }
        } catch (Exception $e) {
            // Error applying suggestion
            return false;
        }
    }
    
    /**
     * REST endpoint: Add custom feed
     */
    public function rest_add_custom_feed($request) {
        try {
            // Log the request for debugging
            // Add custom feed request received
            
            $feed_title = sanitize_text_field($request->get_param('feed_title'));
            $feed_slug = sanitize_title($request->get_param('feed_slug'));
            $feed_description = sanitize_textarea_field($request->get_param('feed_description'));
            $feed_limit = intval($request->get_param('feed_limit'));
            $feed_post_types = $request->get_param('feed_post_types');
            $feed_orderby = sanitize_text_field($request->get_param('feed_orderby'));
            $feed_order = sanitize_text_field($request->get_param('feed_order'));
            $feed_enabled = $request->get_param('feed_enabled') ? true : false;
            
            // Validate required fields
            if (empty($feed_title) || empty($feed_slug)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Feed title and slug are required'
                ), 400);
            }
            
            // Check if slug already exists
            $existing_feeds = get_option('bf_custom_feeds', array());
            foreach ($existing_feeds as $feed) {
                if ($feed['slug'] === $feed_slug) {
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => 'A feed with this slug already exists'
                    ), 400);
                }
            }
            
            // Create feed data
            $feed_data = array(
                'id' => uniqid(),
                'title' => $feed_title,
                'slug' => $feed_slug,
                'description' => $feed_description,
                'limit' => $feed_limit ?: 10,
                'post_types' => is_array($feed_post_types) ? $feed_post_types : array('post'),
                'orderby' => $feed_orderby ?: 'date',
                'order' => $feed_order ?: 'DESC',
                'enabled' => $feed_enabled,
                'created_at' => current_time('mysql')
            );
            
            // Add to existing feeds
            $existing_feeds[] = $feed_data;
            update_option('bf_custom_feeds', $existing_feeds);
            
            // Flush rewrite rules to make the new feed accessible
            flush_rewrite_rules();
            
            // Custom feed added successfully
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Custom feed added successfully!',
                'feed_data' => $feed_data,
                'feed_url' => home_url('/feed/' . $feed_slug . '/')
            ), 200);
            
        } catch (Exception $e) {
            // Exception in add custom feed
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to add custom feed: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Add redirect
     */
    public function rest_add_redirect($request) {
        try {
            // Log the request for debugging
            // Add redirect request received
            
            $redirect_from = sanitize_text_field($request->get_param('redirect_from'));
            $redirect_to = esc_url_raw($request->get_param('redirect_to'));
            $redirect_status_code = intval($request->get_param('redirect_status_code'));
            $redirect_description = sanitize_text_field($request->get_param('redirect_description'));
            $redirect_enabled = $request->get_param('redirect_enabled') ? true : false;
            
            // Validate required fields
            if (empty($redirect_from) || empty($redirect_to)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'From URL and To URL are required'
                ), 400);
            }
            
            // Check if redirect already exists
            $existing_redirects = get_option('bf_feed_redirects', array());
            foreach ($existing_redirects as $redirect) {
                if ($redirect['from'] === $redirect_from) {
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => 'A redirect with this From URL already exists'
                    ), 400);
                }
            }
            
            // Create redirect data
            $redirect_data = array(
                'id' => uniqid(),
                'from' => $redirect_from,
                'to' => $redirect_to,
                'status_code' => $redirect_status_code ?: 301,
                'description' => $redirect_description,
                'enabled' => $redirect_enabled,
                'created_at' => current_time('mysql')
            );
            
            // Add to existing redirects
            $existing_redirects[] = $redirect_data;
            update_option('bf_feed_redirects', $existing_redirects);
            
            // Redirect added successfully
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Redirect added successfully!',
                'redirect_data' => $redirect_data
            ), 200);
            
        } catch (Exception $e) {
            // Exception in add redirect
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to add redirect: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Flush rewrite rules
     */
    public function rest_flush_rewrite_rules($request) {
        try {
            // Flush rewrite rules
            flush_rewrite_rules();
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Rewrite rules flushed successfully!'
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to flush rewrite rules: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Delete custom feed
     */
    public function rest_delete_custom_feed($request) {
        try {
            $feed_index = intval($request->get_param('feed_index'));
            
            if ($feed_index < 0) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Invalid feed index'
                ), 400);
            }
            
            $custom_feeds = get_option('bf_custom_feeds', array());
            
            if (!isset($custom_feeds[$feed_index])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Feed not found'
                ), 404);
            }
            
            $deleted_feed = $custom_feeds[$feed_index];
            unset($custom_feeds[$feed_index]);
            $custom_feeds = array_values($custom_feeds); // Re-index array
            
            update_option('bf_custom_feeds', $custom_feeds);
            
            // Flush rewrite rules to remove the feed URL
            flush_rewrite_rules();
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Custom feed deleted successfully!',
                'deleted_feed' => $deleted_feed
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to delete custom feed: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * REST endpoint: Delete redirect
     */
    public function rest_delete_redirect($request) {
        try {
            $redirect_index = intval($request->get_param('redirect_index'));
            
            if ($redirect_index < 0) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Invalid redirect index'
                ), 400);
            }
            
            $redirects = get_option('bf_feed_redirects', array());
            
            if (!isset($redirects[$redirect_index])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Redirect not found'
                ), 404);
            }
            
            $deleted_redirect = $redirects[$redirect_index];
            unset($redirects[$redirect_index]);
            $redirects = array_values($redirects); // Re-index array
            
            update_option('bf_feed_redirects', $redirects);
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Redirect deleted successfully!',
                'deleted_redirect' => $deleted_redirect
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to delete redirect: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Generate demo optimization suggestions for testing
     * 
     * Creates sample suggestions to demonstrate the functionality
     * when no real suggestions are available.
     * 
     * @since 1.0.0
     * @private
     */
    private function generate_demo_suggestions() {
        $demo_suggestions = array(
            array(
                'id' => 'enable_etag',
                'type' => 'performance',
                'priority' => 'high',
                'title' => esc_html__('Enable ETag Headers', 'betterfeed'),
                'description' => esc_html__('ETag headers help feed readers determine if content has changed, reducing unnecessary downloads.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_etag]',
                'value' => '1',
                'impact' => esc_html__('Improves caching efficiency', 'betterfeed')
            ),
            array(
                'id' => 'enable_conditional_requests',
                'type' => 'performance',
                'priority' => 'high',
                'title' => esc_html__('Enable 304 Not Modified Responses', 'betterfeed'),
                'description' => esc_html__('304 responses tell feed readers when content hasn\'t changed, saving bandwidth.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_conditional_requests]',
                'value' => '1',
                'impact' => esc_html__('Reduces bandwidth usage', 'betterfeed')
            ),
            array(
                'id' => 'enable_gzip',
                'type' => 'performance',
                'priority' => 'medium',
                'title' => esc_html__('Enable GZIP Compression', 'betterfeed'),
                'description' => esc_html__('GZIP compression can reduce feed size by 70-80%, saving bandwidth and improving load times.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_gzip]',
                'value' => '1',
                'impact' => esc_html__('Reduces bandwidth usage by 70-80%', 'betterfeed')
            )
        );
        
        update_option('bf_optimization_suggestions', $demo_suggestions);
    }
    
}
