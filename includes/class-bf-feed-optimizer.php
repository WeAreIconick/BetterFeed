<?php
/**
 * RSS Feed Optimizer
 * Handles RSS optimizations
 *
 * @package BetterFeed
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS Feed Optimizer class
 */
class BF_Feed_Optimizer {
    
    /**
     * Class instance
     * 
     * @var BF_Feed_Optimizer
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Feed_Optimizer
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
        // Feed content modifications
        add_filter('the_excerpt_rss', array($this, 'optimize_feed_excerpt'));
        add_filter('the_content_feed', array($this, 'optimize_feed_content'));
        add_filter('rss2_item', array($this, 'add_rss2_item_elements'));
        add_filter('atom_entry', array($this, 'add_atom_entry_elements'));
        
        // Feed headers
        add_action('rss2_head', array($this, 'add_feed_headers'));
    }

    /**
     * Add feed headers
     */
    public function add_feed_headers() {
        // WebSub headers if enabled
        if (get_option('bf_enable_websub', false)) {
            $this->add_websub_headers();
        }
    }
    
    /**
     * Optimize feed excerpt
     */
    public function optimize_feed_excerpt($excerpt) {
        if (class_exists('BF_Content_Enhancer')) {
            return BF_Content_Enhancer::instance()->enhance_feed_excerpt($excerpt);
        }
        return $excerpt;
    }
    
    /**
     * Optimize feed content
     */
    public function optimize_feed_content($content) {
        if (class_exists('BF_Content_Enhancer')) {
            return BF_Content_Enhancer::instance()->enhance_feed_content($content);
        }
        return $content;
    }
    
    /**
     * Add RSS2 item elements
     */
    public function add_rss2_item_elements() {
        global $post;
        
        // Add podcast elements if enabled
        if (get_option('bf_enable_podcast', false)) {
            echo '<itunes:summary>' . esc_html(get_the_excerpt()) . '</itunes:summary>' . "\n";
            echo '<itunes:explicit>' . (get_option('bf_podcast_explicit', false) ? '1' : '0') . '</itunes:explicit>' . "\n";
        }
    }
    
    /**
     * Add Atom entry elements
     */
    public function add_atom_entry_elements() {
        global $post;
        
        echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr(get_the_excerpt()) . '" />' . "\n";
    }

    /**
     * Add WebSub headers
     */
    private function add_websub_headers() {
        $hub_urls = array(
            'https://pubsubhubbub.appspot.com/',
            'https://pubsubhubbub.superfeedr.com/'
        );
        
        foreach ($hub_urls as $hub_url) {
            printf('<link rel="hub" href="%s" />' . "\n", esc_url($hub_url));
        }
    }
}