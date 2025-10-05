<?php
/**
 * Plugin Name: BetterFeed
 * Plugin URI: https://github.com/WeAreIconick/betterfeed
 * Description: Enhances WordPress RSS feeds with modern features, performance optimizations, and SEO improvements. Builds upon WordPress's solid foundation to deliver an even better feed experience for your readers.
 * Version: 1.0.0
 * Author: WeAreIconick
 * Author URI: https://github.com/WeAreIconick
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: betterfeed
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * 
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BF_VERSION', '1.0.3');
define('BF_PLUGIN_FILE', __FILE__);
define('BF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BF_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class BetterFeed {
    
    /**
     * Plugin instance
     * 
     * @var BetterFeed
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     * 
     * @return BetterFeed
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
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Load plugin files
        $this->includes();
        
        // Plugin loaded successfully
        
        // BetterFeed core functionality
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once BF_PLUGIN_DIR . 'includes/class-bf-activator.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-deactivator.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-uninstaller.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-feed-optimizer.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-admin.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-cache.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-analytics.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-websub.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-validator.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-content-enhancer.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-import-export.php';
        require_once BF_PLUGIN_DIR . 'includes/class-bf-scheduler.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-episode-meta.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-podcast-rss.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-json-feed.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-custom-feeds.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-redirects.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-dashboard.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-performance-monitor.php';
require_once BF_PLUGIN_DIR . 'includes/class-bf-optimizer-suggestions.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin activation and deactivation hooks
        register_activation_hook(BF_PLUGIN_FILE, array('BF_Activator', 'activate'));
        register_deactivation_hook(BF_PLUGIN_FILE, array('BF_Deactivator', 'deactivate'));
        register_uninstall_hook(BF_PLUGIN_FILE, array('BF_Uninstaller', 'uninstall'));
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
        
        // Admin hooks - instantiate once
        BF_Admin::instance();
        
        // Frontend feed optimizations
        BF_Feed_Optimizer::instance();
        
        // Cache management
        BF_Cache::instance();
        
        // Analytics
        BF_Analytics::instance();
        
        // WebSub/PubSubHubbub
        BF_WebSub::instance();
        
        // Feed validation
        BF_Validator::instance();
        
        // Content enhancement
        BF_Content_Enhancer::instance();
        
        // Import/Export
        BF_Import_Export::instance();
        
        // Feed scheduling and optimization
        BF_Scheduler::instance();
        
        // Episode meta fields
        BF_Episode_Meta::instance();
        
        // Podcast RSS feed emission
        BF_Podcast_RSS::instance();
        
        // JSON Feed support
        BF_JSON_Feed::instance();
        
        // Custom Feed Endpoints
        BF_Custom_Feeds::instance();
        
        // Feed Redirect Management
        BF_Redirects::instance();
        
        // Performance Dashboard
        BF_Dashboard::instance();
        
        // Performance Monitoring
        BF_Performance_Monitor::instance();
        
        // Optimization Suggestions
        BF_Optimizer_Suggestions::instance();
    }
    
    /**
     * Load plugin text domain for internationalization
     * 
     * WordPress.org automatically loads translations for hosted plugins,
     * so this method is removed for WordPress.org compliance.
     */
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        do_action('bf_init');
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version() {
        return BF_VERSION;
    }
    
    /**
     * Check if current request is a REST API request
     */
    private function is_rest_api_request() {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
    
}

/**
 * Initialize the plugin
 */
function betterfeed() {
    return BetterFeed::instance();
}

// Prevent multiple initializations
if (!defined('BETTERFEED_PLUGIN_LOADED')) {
    define('BETTERFEED_PLUGIN_LOADED', true);
    
    // Start the plugin
    betterfeed();
}