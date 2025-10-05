<?php
/**
 * JSON Feed Implementation
 *
 * Implements JSON Feed 1.1 specification for BetterFeed
 *
 * @package BetterFeed
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * JSON Feed class
 */
class BF_JSON_Feed {
    
    /**
     * Class instance
     * 
     * @var BF_JSON_Feed
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_JSON_Feed
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
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Add rewrite rule for JSON feed
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_json_feed_request'));
        
        // Flush rewrite rules when needed
        add_action('wp_loaded', array($this, 'maybe_flush_rewrite_rules'));
    }
    
    /**
     * Add rewrite rules for JSON feed
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^feed/json/?$',
            'index.php?bf_json_feed=1',
            'top'
        );
    }
    
    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'bf_json_feed';
        return $vars;
    }
    
    /**
     * Handle JSON feed requests
     */
    public function handle_json_feed_request() {
        if (!get_query_var('bf_json_feed')) {
            return;
        }
        
        // Check if JSON feed is enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_json_feed'])) {
            return;
        }
        
        // Set proper headers
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        
        // Generate JSON feed
        $json_feed = $this->generate_json_feed();
        
        // Output JSON
        echo wp_json_encode($json_feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Generate JSON Feed
     */
    private function generate_json_feed() {
        $feed = array(
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'home_page_url' => home_url('/'),
            'feed_url' => home_url('/feed/json/'),
            'language' => get_locale(),
            'authors' => $this->get_feed_authors(),
            'icon' => $this->get_site_icon(),
            'favicon' => $this->get_favicon(),
            'expired' => false,
            'items' => $this->get_feed_items()
        );
        
        return $feed;
    }
    
    /**
     * Get feed authors
     */
    private function get_feed_authors() {
        $authors = array();
        
        // Get site admin as primary author
        $admin_user = get_user_by('id', 1);
        if ($admin_user) {
            $authors[] = array(
                'name' => $admin_user->display_name,
                'url' => get_author_posts_url($admin_user->ID),
                'avatar' => get_avatar_url($admin_user->ID, array('size' => 64))
            );
        }
        
        return $authors;
    }
    
    /**
     * Get site icon
     */
    private function get_site_icon() {
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $icon_url = wp_get_attachment_image_url($site_icon_id, 'full');
            if ($icon_url) {
                return $icon_url;
            }
        }
        
        // Fallback to favicon
        return $this->get_favicon();
    }
    
    /**
     * Get favicon
     */
    private function get_favicon() {
        return home_url('/favicon.ico');
    }
    
    /**
     * Get feed items
     */
    private function get_feed_items() {
        $items = array();
        
        // Get posts for feed
        $posts_per_page = get_option('posts_per_rss', 10);
        $posts = get_posts(array(
            'post_status' => 'publish',
            'post_type' => 'post',
            'numberposts' => $posts_per_page,
            'suppress_filters' => false
        ));
        
        foreach ($posts as $post) {
            $items[] = $this->format_post_as_json_item($post);
        }
        
        return $items;
    }
    
    /**
     * Format post as JSON feed item
     */
    private function format_post_as_json_item($post) {
        $item = array(
            'id' => $post->ID,
            'url' => get_permalink($post->ID),
            'title' => get_the_title($post->ID),
            'content_html' => $this->get_post_content_html($post),
            'content_text' => wp_strip_all_tags($this->get_post_content_html($post)),
            'summary' => $this->get_post_summary($post),
            'date_published' => get_post_time('c', true, $post),
            'date_modified' => get_post_modified_time('c', true, $post),
            'authors' => $this->get_post_authors($post),
            'tags' => $this->get_post_tags($post),
            'language' => get_locale()
        );
        
        // Add featured image if available
        $featured_image = $this->get_featured_image($post);
        if ($featured_image) {
            $item['image'] = $featured_image['url'];
            $item['banner_image'] = $featured_image['url'];
        }
        
        // Add attachments (enclosures)
        $attachments = $this->get_post_attachments($post);
        if (!empty($attachments)) {
            $item['attachments'] = $attachments;
        }
        
        // Add external URL if custom field exists
        $external_url = get_post_meta($post->ID, '_external_url', true);
        if ($external_url) {
            $item['external_url'] = $external_url;
        }
        
        return $item;
    }
    
    /**
     * Get post content HTML
     */
    private function get_post_content_html($post) {
        $content = get_the_content(null, false, $post);
        $content = apply_filters('the_content', $content);
        $content = str_replace(']]>', ']]&gt;', $content);
        
        return $content;
    }
    
    /**
     * Get post summary
     */
    private function get_post_summary($post) {
        $excerpt = get_the_excerpt($post);
        if ($excerpt) {
            return $excerpt;
        }
        
        // Fallback to truncated content
        $content = wp_strip_all_tags($this->get_post_content_html($post));
        return wp_trim_words($content, 55, '...');
    }
    
    /**
     * Get post authors
     */
    private function get_post_authors($post) {
        $authors = array();
        $author_id = $post->post_author;
        
        $author = get_userdata($author_id);
        if ($author) {
            $authors[] = array(
                'name' => $author->display_name,
                'url' => get_author_posts_url($author_id),
                'avatar' => get_avatar_url($author_id, array('size' => 64))
            );
        }
        
        return $authors;
    }
    
    /**
     * Get post tags
     */
    private function get_post_tags($post) {
        $tags = array();
        $post_tags = get_the_tags($post->ID);
        
        if ($post_tags) {
            foreach ($post_tags as $tag) {
                $tags[] = $tag->name;
            }
        }
        
        return $tags;
    }
    
    /**
     * Get featured image
     */
    private function get_featured_image($post) {
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if (!$featured_image_id) {
            return null;
        }
        
        $image_url = wp_get_attachment_image_url($featured_image_id, 'large');
        if (!$image_url) {
            return null;
        }
        
        return array(
            'url' => $image_url,
            'mime_type' => get_post_mime_type($featured_image_id),
            'size_in_bytes' => wp_get_attachment_filesize($featured_image_id)
        );
    }
    
    /**
     * Get post attachments (enclosures)
     */
    private function get_post_attachments($post) {
        $attachments = array();
        
        // Check for audio/video attachments
        $audio_url = get_post_meta($post->ID, 'episode_audio_url', true);
        if ($audio_url) {
            if (is_numeric($audio_url)) {
                // Attachment ID
                $attachment_url = wp_get_attachment_url($audio_url);
                $mime_type = get_post_mime_type($audio_url);
                $file_size = wp_get_attachment_filesize($audio_url);
            } else {
                // Direct URL
                $attachment_url = $audio_url;
                $mime_type = $this->get_url_mime_type($audio_url);
                $file_size = null; // Would need HTTP HEAD request
            }
            
            if ($attachment_url) {
                $attachments[] = array(
                    'url' => $attachment_url,
                    'mime_type' => $mime_type ?: 'audio/mpeg',
                    'title' => get_the_title($post->ID),
                    'size_in_bytes' => $file_size,
                    'duration_in_seconds' => null // Could be added as meta field
                );
            }
        }
        
        return $attachments;
    }
    
    /**
     * Get MIME type from URL
     */
    private function get_url_mime_type($url) {
        $extension = pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $extension = strtolower($extension);
        
        $mime_types = array(
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'webm' => 'video/webm',
            'avi' => 'video/avi',
            'mov' => 'video/quicktime'
        );
        
        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }
    
    /**
     * Maybe flush rewrite rules
     */
    public function maybe_flush_rewrite_rules() {
        $rewrite_rules_version = get_option('bf_json_feed_rewrite_rules_version');
        $current_version = '1.0';
        
        if ($rewrite_rules_version !== $current_version) {
            flush_rewrite_rules();
            update_option('bf_json_feed_rewrite_rules_version', $current_version);
        }
    }
}
