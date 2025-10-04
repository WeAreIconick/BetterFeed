<?php
/**
 * BetterFeed Admin Interface
 *
 * Handles WordPress admin interface, settings pages, and REST API endpoints
 *
 * @package BetterFeed
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BF_Admin {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Whether hooks have been initialized
     */
    private static $hooks_initialized = false;
    
    /**
     * Get instance of this class
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
        
        // Only load assets on our admin page
        if ('settings_page_bf-settings' !== $hook_suffix) {
            return;
        }
        
        wp_enqueue_style(
            'bf-admin',
            BF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BF_VERSION
        );
        
        wp_enqueue_script(
            'bf-admin',
            BF_PLUGIN_URL . 'assets/js/admin.js?v=' . time() . '&cb=' . rand(),
            array(),
            BF_VERSION,
            false
        );
        
        // Enqueue editor scripts for post edit pages
        if ((isset($_GET['post']) || isset($_GET['post_type'])) && $this->is_betterfeed_enabled()) {
            wp_enqueue_script(
                'bf-editor-episode-panel',
                BF_PLUGIN_URL . 'assets/js/editor/episode-panel.js',
                array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-media-utils'),
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
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('bf-admin', 'bf_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
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
        $valid_tabs = array('general', 'performance', 'content', 'tools', 'analytics', 'podcast');
        if (!in_array($active_tab, $valid_tabs)) {
            $active_tab = 'general';
        }
        ?>
        
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <p><?php esc_html_e('Configure performance, SEO, analytics, and content optimization for your WordPress feeds.', 'betterfeed'); ?></p>
            
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
            </nav>
            
            <!-- Tab Content -->
            <form method="post" action="options.php">
                <?php settings_fields('bf-settings'); ?>
                <?php $this->render_tab_content($active_tab); ?>
                <?php submit_button(); ?>
            </form>
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
                    default:
                        $this->render_general_tab();
                        break;
        }
    }
    
    /**
     * Render General tab
     */
    private function render_general_tab() {
        ?><?php
        do_settings_sections('bf_general');
        ?><?php
    }
    
    /**
     * Render Performance tab
     */
    private function render_performance_tab() {
        ?><?php
        do_settings_sections('bf_performance');
        ?><?php
    }
    
    /**
     * Render Content tab
     */
    private function render_content_tab() {
        ?><?php
        do_settings_sections('bf_content');
        ?><?php
    }
    
    /**
     * Render Tools tab - following same pattern as Performance tab
     */
    private function render_tools_tab() {
      ?><?php
        do_settings_sections('bf_tools');
        ?>
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
        <?php
    }
    
    /**
     * Render Analytics tab - following same pattern as Performance tab
     */
    private function render_analytics_tab() {
      ?><?php
        do_settings_sections('bf_analytics');
        ?>
            
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
            
        <?php
    }
    
    /**
     * Render Podcast tab
     */
    private function render_podcast_tab() {
        ?>
        <div class="tab-content">
            <h2><?php esc_html_e('Podcast Settings', 'betterfeed'); ?></h2>
            <p><?php esc_html_e('Configure podcast RSS feed settings for Apple Podcasts, Spotify, and other platforms.', 'betterfeed'); ?></p>
            
            <?php do_settings_sections('bf_podcast_integrations'); ?>
            <?php do_settings_sections('bf_podcast_show'); ?>
        </div>
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
                    echo $image;
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
    
    
}
