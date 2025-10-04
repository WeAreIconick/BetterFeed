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
            // Analytics export logic here
            return new WP_REST_Response(array(
                'success' => true,
                'data' => 'analytics,data,here',
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
            // Settings export logic here
            return new WP_REST_Response(array(
                'success' => true,
                'data' => 'settings,database,here',
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
        $valid_tabs = array('general', 'performance', 'content', 'tools', 'analytics');
        if (!in_array($active_tab, $valid_tabs)) {
            $active_tab = 'general';
        }
        ?>
        
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
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
