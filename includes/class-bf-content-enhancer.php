<?php
/**
 * Content Enhancement Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content enhancer class
 */
class BF_Content_Enhancer {
    
    /**
     * Class instance
     * 
     * @var BF_Content_Enhancer
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Content_Enhancer
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
        // Content enhancement filters
        add_filter('the_content_feed', array($this, 'enhance_feed_content'), 10, 2);
        add_filter('the_excerpt_rss', array($this, 'enhance_feed_excerpt'));
        add_filter('comment_text_rss', array($this, 'enhance_comment_content'));
        
        // Add read time to post meta
        add_action('save_post', array($this, 'calculate_read_time'), 20);
        
        // Add custom feed elements
        add_action('rss2_item', array($this, 'add_custom_rss_elements'));
        add_action('atom_entry', array($this, 'add_custom_atom_elements'));
        
        // Enclosure length auto-detection
        add_action('rss2_item', array($this, 'fix_enclosure_lengths'));
        add_action('atom_entry', array($this, 'fix_atom_enclosure_lengths'));
        
        // Responsive images
        add_action('rss2_item', array($this, 'add_responsive_images'));
        add_action('atom_entry', array($this, 'add_responsive_images_atom'));
        
        // Google Discover optimization
        add_action('rss2_item', array($this, 'add_google_discover_tags'));
        add_action('atom_entry', array($this, 'add_google_discover_tags_atom'));
    }
    
    /**
     * Enhance feed content
     * 
     * @param string $content Post content
     * @param string $feed_type Feed type
     * @return string Enhanced content
     */
    public function enhance_feed_content($content, $feed_type = 'rss2') {
        global $post;
        
        if (!$post) {
            return $content;
        }
        
        // Convert relative URLs to absolute
        if (get_option('bf_convert_relative_urls', true)) {
            $content = $this->convert_relative_urls($content);
        }
        
        // Fix character encoding
        if (get_option('bf_fix_encoding', true)) {
            $content = $this->fix_character_encoding($content);
        }
        
        // Add featured image
        if (get_option('bf_include_featured_images', true) && has_post_thumbnail($post->ID)) {
            $featured_image = $this->get_responsive_featured_image($post->ID);
            $content = $featured_image . $content;
        }
        
        // Add read time information
        if (get_option('bf_include_read_time', false)) {
            $read_time = $this->get_read_time($post->ID);
            if ($read_time) {
                $read_time_html = '<p class="smfb-read-time"><em>Estimated reading time: ' . $read_time . ' minutes</em></p>';
                $content = $read_time_html . $content;
            }
        }
        
        // Add custom footer
        $custom_footer = get_option('bf_custom_footer', '');
        if (!empty($custom_footer)) {
            $custom_footer = $this->process_footer_variables($custom_footer, $post);
            $content .= '<div class="smfb-custom-footer">' . wpautop($custom_footer) . '</div>';
        }
        
        // Clean up content
        $content = $this->clean_feed_content($content);
        
        return $content;
    }
    
    /**
     * Enhance feed excerpt
     * 
     * @param string $excerpt Post excerpt
     * @return string Enhanced excerpt
     */
    public function enhance_feed_excerpt($excerpt) {
        global $post;
        
        if (!$post) {
            return $excerpt;
        }
        
        // If no excerpt, generate one from content
        if (empty($excerpt) && get_option('bf_auto_excerpt', true)) {
            $excerpt = $this->generate_smart_excerpt($post->post_content);
        }
        
        // Convert relative URLs
        if (get_option('bf_convert_relative_urls', true)) {
            $excerpt = $this->convert_relative_urls($excerpt);
        }
        
        // Fix encoding
        if (get_option('bf_fix_encoding', true)) {
            $excerpt = $this->fix_character_encoding($excerpt);
        }
        
        // Add featured image to excerpt
        if (get_option('bf_include_featured_images', true) && has_post_thumbnail($post->ID)) {
            $image_html = $this->get_featured_image_html($post->ID, 'medium');
            $excerpt = $image_html . $excerpt;
        }
        
        return $excerpt;
    }
    
    /**
     * Enhance comment content
     * 
     * @param string $content Comment content
     * @return string Enhanced content
     */
    public function enhance_comment_content($content) {
        // Convert relative URLs
        if (get_option('bf_convert_relative_urls', true)) {
            $content = $this->convert_relative_urls($content);
        }
        
        // Fix encoding
        if (get_option('bf_fix_encoding', true)) {
            $content = $this->fix_character_encoding($content);
        }
        
        return $content;
    }
    
    /**
     * Convert relative URLs to absolute URLs
     * 
     * @param string $content Content with potential relative URLs
     * @return string Content with absolute URLs
     */
    private function convert_relative_urls($content) {
        $home_url = trailingslashit(home_url());
        
        // Convert relative URLs in href attributes
        $content = preg_replace_callback(
            '/href=["\']([^"\']+)["\']/i',
            function($matches) use ($home_url) {
                $url = $matches[1];
                
                // Skip if already absolute or anchor link
                if (preg_match('/^(https?:|mailto:|tel:|#)/', $url)) {
                    return $matches[0];
                }
                
                // Convert relative to absolute
                if (strpos($url, '/') === 0) {
                    $url = rtrim(home_url(), '/') . $url;
                } else {
                    $url = $home_url . ltrim($url, './');
                }
                
                return 'href="' . $url . '"';
            },
            $content
        );
        
        // Convert relative URLs in src attributes
        $content = preg_replace_callback(
            '/src=["\']([^"\']+)["\']/i',
            function($matches) use ($home_url) {
                $url = $matches[1];
                
                // Skip if already absolute
                if (preg_match('/^https?:/', $url)) {
                    return $matches[0];
                }
                
                // Convert relative to absolute
                if (strpos($url, '/') === 0) {
                    $url = rtrim(home_url(), '/') . $url;
                } else {
                    $url = $home_url . ltrim($url, './');
                }
                
                return 'src="' . $url . '"';
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Fix character encoding issues
     * 
     * @param string $content Content to fix
     * @return string Fixed content
     */
    private function fix_character_encoding($content) {
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }
        
        // Fix common encoding issues
        $replacements = array(
            // Smart quotes
            '�' => '"',
            '�' => '"',
            '�' => "'",
            '�' => "'",
            // Em/en dashes
            '�' => '—',
            '�' => '–',
            // Other common issues
            '�' => '…',
            '�' => '•',
        );
        
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        
        // Remove control characters except tabs, newlines, and carriage returns
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        return $content;
    }
    
    /**
     * Get responsive featured image HTML
     * 
     * @param int $post_id Post ID
     * @return string Featured image HTML
     */
    private function get_responsive_featured_image($post_id) {
        if (!has_post_thumbnail($post_id)) {
            return '';
        }
        
        $image_id = get_post_thumbnail_id($post_id);
        $image_sizes = get_option('bf_image_sizes', array('medium', 'large', 'full'));
        
        $srcset = array();
        foreach ($image_sizes as $size) {
            $image_data = wp_get_attachment_image_src($image_id, $size);
            if ($image_data) {
                $srcset[] = $image_data[0] . ' ' . $image_data[1] . 'w';
            }
        }
        
        $image_url = wp_get_attachment_image_url($image_id, 'medium');
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $image_caption = wp_get_attachment_caption($image_id);
        
        $html = '<figure class="smfb-featured-image" style="margin: 0 0 1em 0;">';
        $html .= '<img src="' . esc_url($image_url) . '"';
        
        if (!empty($srcset)) {
            $html .= ' srcset="' . esc_attr(implode(', ', $srcset)) . '"';
            $html .= ' sizes="(max-width: 600px) 100vw, 600px"';
        }
        
        $html .= ' alt="' . esc_attr($image_alt) . '"';
        $html .= ' style="max-width: 100%; height: auto; display: block;"';
        $html .= ' />';
        
        if (!empty($image_caption)) {
            $html .= '<figcaption style="font-size: 0.9em; color: #666; margin-top: 0.5em;">';
            $html .= esc_html($image_caption);
            $html .= '</figcaption>';
        }
        
        $html .= '</figure>';
        
        return $html;
    }
    
    /**
     * Get featured image HTML for specific size
     * 
     * @param int $post_id Post ID
     * @param string $size Image size
     * @return string Image HTML
     */
    private function get_featured_image_html($post_id, $size = 'medium') {
        if (!has_post_thumbnail($post_id)) {
            return '';
        }
        
        return get_the_post_thumbnail($post_id, $size, array(
            'style' => 'max-width: 100%; height: auto; display: block; margin-bottom: 1em;'
        ));
    }
    
    /**
     * Calculate and store read time for a post
     * 
     * @param int $post_id Post ID
     */
    public function calculate_read_time($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'post') {
            return;
        }
        
        $content = $post->post_content;
        $word_count = $this->count_words($content);
        
        // Average reading speed: 200 words per minute
        $reading_speed = get_option('bf_reading_speed', 200);
        $read_time = ceil($word_count / $reading_speed);
        
        update_post_meta($post_id, 'bf_read_time', $read_time);
        update_post_meta($post_id, 'bf_word_count', $word_count);
    }
    
    /**
     * Get read time for a post
     * 
     * @param int $post_id Post ID
     * @return int|null Read time in minutes
     */
    public function get_read_time($post_id) {
        $read_time = get_post_meta($post_id, 'bf_read_time', true);
        
        if (empty($read_time)) {
            // Calculate on the fly if not stored
            $this->calculate_read_time($post_id);
            $read_time = get_post_meta($post_id, 'bf_read_time', true);
        }
        
        return $read_time ? (int) $read_time : null;
    }
    
    /**
     * Count words in content
     * 
     * @param string $content Content to count
     * @return int Word count
     */
    private function count_words($content) {
        // Strip HTML tags
        $content = wp_strip_all_tags($content);
        
        // Remove shortcodes
        $content = strip_shortcodes($content);
        
        // Count words
        return str_word_count($content);
    }
    
    /**
     * Generate smart excerpt from content
     * 
     * @param string $content Post content
     * @param int $length Excerpt length in words
     * @return string Generated excerpt
     */
    private function generate_smart_excerpt($content, $length = 55) {
        $content = wp_strip_all_tags($content);
        $content = strip_shortcodes($content);
        
        $words = explode(' ', $content);
        
        if (count($words) <= $length) {
            return implode(' ', $words);
        }
        
        $excerpt = implode(' ', array_slice($words, 0, $length));
        
        // Try to end at a sentence
        $last_period = strrpos($excerpt, '.');
        $last_exclamation = strrpos($excerpt, '!');
        $last_question = strrpos($excerpt, '?');
        
        $last_sentence_end = max($last_period, $last_exclamation, $last_question);
        
        if ($last_sentence_end && $last_sentence_end > strlen($excerpt) * 0.7) {
            $excerpt = substr($excerpt, 0, $last_sentence_end + 1);
        } else {
            $excerpt .= '…';
        }
        
        return $excerpt;
    }
    
    /**
     * Process footer variables
     * 
     * @param string $footer Footer content
     * @param WP_Post $post Current post
     * @return string Processed footer
     */
    private function process_footer_variables($footer, $post) {
        $variables = array(
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{post_title}' => $post->post_title,
            '{post_url}' => get_permalink($post->ID),
            '{post_date}' => get_the_date('F j, Y', $post->ID),
            '{author_name}' => get_the_author_meta('display_name', $post->post_author),
            '{year}' => gmdate('Y'),
        );
        
        return str_replace(array_keys($variables), array_values($variables), $footer);
    }
    
    /**
     * Clean feed content
     * 
     * @param string $content Content to clean
     * @return string Cleaned content
     */
    private function clean_feed_content($content) {
        // Remove empty paragraphs
        $content = preg_replace('/<p[^>]*>(\s|&nbsp;)*<\/p>/', '', $content);
        
        // Remove script tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        
        // Remove style tags
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
        
        // Remove form elements
        $content = preg_replace('/<(form|input|textarea|select|button)[^>]*>.*?<\/\1>/is', '', $content);
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Add custom RSS elements
     */
    public function add_custom_rss_elements() {
        global $post;
        
        // Add read time
        if (get_option('bf_include_read_time', false)) {
            $read_time = $this->get_read_time($post->ID);
            if ($read_time) {
                echo '<smfb:readTime>' . esc_html($read_time) . '</smfb:readTime>' . "\n";
            }
        }
        
        // Add word count
        if (get_option('bf_include_word_count', false)) {
            $word_count = get_post_meta($post->ID, 'bf_word_count', true);
            if ($word_count) {
                echo '<smfb:wordCount>' . esc_html($word_count) . '</smfb:wordCount>' . "\n";
            }
        }
        
        // Add post thumbnail as Media RSS
        if (get_option('bf_include_featured_images', true) && has_post_thumbnail($post->ID)) {
            $this->add_media_rss_elements();
        }
        
        // Add Dublin Core metadata
        $this->add_dublin_core_elements();
    }
    
    /**
     * Add custom Atom elements
     */
    public function add_custom_atom_elements() {
        global $post;
        
        // Similar to RSS but using Atom format
        if (get_option('bf_include_read_time', false)) {
            $read_time = $this->get_read_time($post->ID);
            if ($read_time) {
                echo '<smfb:readTime xmlns:smfb="https://github.com/WeAreIconick/-betterfeed">' . esc_html($read_time) . '</smfb:readTime>' . "\n";
            }
        }
    }
    
    /**
     * Add Media RSS elements
     */
    private function add_media_rss_elements() {
        global $post;
        
        $image_id = get_post_thumbnail_id($post->ID);
        $image_url = wp_get_attachment_image_url($image_id, 'large');
        $image_data = wp_get_attachment_metadata($image_id);
        
        if (!$image_url) {
            return;
        }
        
        $image_width = $image_data['width'] ?? '';
        $image_height = $image_data['height'] ?? '';
        $image_size = filesize(get_attached_file($image_id)) ?: 0;
        $image_type = get_post_mime_type($image_id);
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $image_caption = wp_get_attachment_caption($image_id);
        
        echo '<media:content url="' . esc_url($image_url) . '"';
        if ($image_width) echo ' width="' . esc_attr($image_width) . '"';
        if ($image_height) echo ' height="' . esc_attr($image_height) . '"';
        if ($image_type) echo ' type="' . esc_attr($image_type) . '"';
        echo ' />' . "\n";
        
        if ($image_alt) {
            echo '<media:title>' . esc_html($image_alt) . '</media:title>' . "\n";
        }
        
        if ($image_caption) {
            echo '<media:description>' . esc_html($image_caption) . '</media:description>' . "\n";
        }
    }
    
    /**
     * Add Dublin Core elements
     */
    private function add_dublin_core_elements() {
        global $post;
        
        // DC Creator (already handled by default)
        // DC Date
        echo '<dc:date>' . get_the_date('c', $post->ID) . '</dc:date>' . "\n";
        
        // DC Subject (categories/tags)
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            echo '<dc:subject>' . esc_html($category->name) . '</dc:subject>' . "\n";
        }
        
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                echo '<dc:subject>' . esc_html($tag->name) . '</dc:subject>' . "\n";
            }
        }
        
        // DC Language
        $language = get_locale();
        if ($language) {
            echo '<dc:language>' . esc_html($language) . '</dc:language>' . "\n";
        }
    }
    
    /**
     * Fix enclosure lengths in RSS2 feeds
     */
    public function fix_enclosure_lengths() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Check if enclosure fix is enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_enclosure_fix'])) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Get enclosures from post content
        $enclosures = $this->get_post_enclosures($post_id);
        
        foreach ($enclosures as $enclosure) {
            $file_size = $this->get_file_size($enclosure['url']);
            if ($file_size > 0) {
                echo '<enclosure url="' . esc_url($enclosure['url']) . '" length="' . esc_attr($file_size) . '" type="' . esc_attr($enclosure['type']) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Fix enclosure lengths in Atom feeds
     */
    public function fix_atom_enclosure_lengths() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Check if enclosure fix is enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_enclosure_fix'])) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Get enclosures from post content
        $enclosures = $this->get_post_enclosures($post_id);
        
        foreach ($enclosures as $enclosure) {
            $file_size = $this->get_file_size($enclosure['url']);
            if ($file_size > 0) {
                echo '<link rel="enclosure" type="' . esc_attr($enclosure['type']) . '" length="' . esc_attr($file_size) . '" href="' . esc_url($enclosure['url']) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Get enclosures from post content
     */
    private function get_post_enclosures($post_id) {
        $enclosures = array();
        
        // Check for audio/video URLs in content
        $content = get_post_field('post_content', $post_id);
        $post_meta = get_post_meta($post_id, 'episode_audio_url', true);
        
        // Audio URLs from post meta (podcast episodes)
        if (!empty($post_meta)) {
            if (is_numeric($post_meta)) {
                // Attachment ID
                $attachment_url = wp_get_attachment_url($post_meta);
                if ($attachment_url) {
                    $mime_type = get_post_mime_type($post_meta);
                    $enclosures[] = array(
                        'url' => $attachment_url,
                        'type' => $mime_type ?: 'audio/mpeg'
                    );
                }
            } else {
                // Direct URL
                $mime_type = $this->get_url_mime_type($post_meta);
                $enclosures[] = array(
                    'url' => $post_meta,
                    'type' => $mime_type ?: 'audio/mpeg'
                );
            }
        }
        
        // Extract audio/video URLs from content
        $url_pattern = '/https?:\/\/[^\s<>"]+\.(mp3|mp4|m4a|wav|ogg|webm)(\?[^\s<>"]*)?/i';
        preg_match_all($url_pattern, $content, $matches);
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $url) {
                $mime_type = $this->get_url_mime_type($url);
                $enclosures[] = array(
                    'url' => $url,
                    'type' => $mime_type ?: 'audio/mpeg'
                );
            }
        }
        
        return $enclosures;
    }
    
    /**
     * Get file size via HTTP HEAD request
     */
    private function get_file_size($url) {
        // Check cache first
        $cache_key = 'bf_file_size_' . md5($url);
        $cached_size = get_transient($cache_key);
        
        if ($cached_size !== false) {
            return $cached_size;
        }
        
        // Make HEAD request to get file size
        $response = wp_remote_head($url, array(
            'timeout' => 10,
            'redirection' => 5,
            'user-agent' => 'BetterFeed/1.0 (+https://wordpress.org/plugins/betterfeed/)'
        ));
        
        if (is_wp_error($response)) {
            return 0;
        }
        
        $headers = wp_remote_retrieve_headers($response);
        $content_length = $headers->offsetGet('content-length');
        
        if ($content_length) {
            $file_size = (int) $content_length;
            // Cache for 24 hours
            set_transient($cache_key, $file_size, DAY_IN_SECONDS);
            return $file_size;
        }
        
        return 0;
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
     * Add responsive images to RSS2 feeds
     */
    public function add_responsive_images() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Check if responsive images are enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_responsive_images'])) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        $featured_image_id = get_post_thumbnail_id($post_id);
        if (!$featured_image_id) {
            return;
        }
        
        // Get image sizes
        $image_sizes = $this->get_image_sizes($featured_image_id);
        
        if (!empty($image_sizes)) {
            // Add Media RSS namespace
            echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
            
            foreach ($image_sizes as $size) {
                echo '<media:content url="' . esc_url($size['url']) . '" type="' . esc_attr($size['mime_type']) . '" medium="image"';
                if ($size['width']) {
                    echo ' width="' . esc_attr($size['width']) . '"';
                }
                if ($size['height']) {
                    echo ' height="' . esc_attr($size['height']) . '"';
                }
                echo ' />' . "\n";
            }
        }
    }
    
    /**
     * Add responsive images to Atom feeds
     */
    public function add_responsive_images_atom() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Check if responsive images are enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_responsive_images'])) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        $featured_image_id = get_post_thumbnail_id($post_id);
        if (!$featured_image_id) {
            return;
        }
        
        // Get image sizes
        $image_sizes = $this->get_image_sizes($featured_image_id);
        
        if (!empty($image_sizes)) {
            foreach ($image_sizes as $size) {
                echo '<link rel="enclosure" type="' . esc_attr($size['mime_type']) . '" href="' . esc_url($size['url']) . '"';
                if ($size['width']) {
                    echo ' width="' . esc_attr($size['width']) . '"';
                }
                if ($size['height']) {
                    echo ' height="' . esc_attr($size['height']) . '"';
                }
                echo ' />' . "\n";
            }
        }
    }
    
    /**
     * Get responsive image sizes for featured image
     */
    private function get_image_sizes($attachment_id) {
        $sizes = array();
        
        // Get available image sizes
        $image_sizes = array('thumbnail', 'medium', 'large', 'full');
        
        foreach ($image_sizes as $size) {
            $image_data = wp_get_attachment_image_src($attachment_id, $size);
            if ($image_data) {
                $sizes[] = array(
                    'url' => $image_data[0],
                    'width' => $image_data[1],
                    'height' => $image_data[2],
                    'mime_type' => get_post_mime_type($attachment_id)
                );
            }
        }
        
        // Add srcset for modern browsers
        $srcset = wp_get_attachment_image_srcset($attachment_id, 'full');
        if ($srcset) {
            // Parse srcset to get individual URLs
            $srcset_parts = explode(', ', $srcset);
            foreach ($srcset_parts as $part) {
                $part_data = explode(' ', trim($part));
                if (count($part_data) >= 2) {
                    $url = $part_data[0];
                    $descriptor = $part_data[1];
                    
                    // Get dimensions for this URL
                    $image_info = wp_get_attachment_metadata($attachment_id);
                    if ($image_info && isset($image_info['sizes'])) {
                        foreach ($image_info['sizes'] as $size_name => $size_data) {
                            $size_url = wp_get_attachment_image_src($attachment_id, $size_name)[0];
                            if ($size_url === $url) {
                                $sizes[] = array(
                                    'url' => $url,
                                    'width' => $size_data['width'],
                                    'height' => $size_data['height'],
                                    'mime_type' => get_post_mime_type($attachment_id)
                                );
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // Remove duplicates
        $unique_sizes = array();
        foreach ($sizes as $size) {
            $key = $size['url'] . '_' . $size['width'] . '_' . $size['height'];
            if (!isset($unique_sizes[$key])) {
                $unique_sizes[$key] = $size;
            }
        }
        
        return array_values($unique_sizes);
    }
    
    /**
     * Add Google Discover optimization tags to RSS2 feeds
     */
    public function add_google_discover_tags() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Check if Google Discover optimization is enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_google_discover'])) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Add schema.org Article markup
        $this->add_article_schema($post_id);
        
        // Ensure large featured image for Google Discover
        $this->add_large_featured_image($post_id);
        
        // Add author and publication information
        $this->add_author_publication_info($post_id);
    }
    
    /**
     * Add Google Discover optimization tags to Atom feeds
     */
    public function add_google_discover_tags_atom() {
        // Check if BetterFeed is enabled
        $general_options = get_option('bf_general_options', array());
        if (empty($general_options['enable_betterfeed'])) {
            return;
        }
        
        // Check if Google Discover optimization is enabled
        $performance_options = get_option('bf_performance_options', array());
        if (empty($performance_options['enable_google_discover'])) {
            return;
        }
        
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        // Add schema.org Article markup (JSON-LD)
        echo '<script type="application/ld+json">' . wp_json_encode($this->get_article_schema_json($post_id), JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        
        // Ensure large featured image for Google Discover
        $this->add_large_featured_image_atom($post_id);
    }
    
    /**
     * Add schema.org Article markup for RSS2
     */
    private function add_article_schema($post_id) {
        $schema = $this->get_article_schema_data($post_id);
        
        if (!$schema) {
            return;
        }
        
        // Add JSON-LD script tag
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
    }
    
    /**
     * Get article schema JSON for Atom feeds
     */
    private function get_article_schema_json($post_id) {
        $schema = $this->get_article_schema_data($post_id);
        
        if (!$schema) {
            return '';
        }
        
        return wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Get article schema.org data
     */
    private function get_article_schema_data($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }
        
        $author = get_userdata($post->post_author);
        $featured_image_id = get_post_thumbnail_id($post_id);
        $categories = get_the_category($post_id);
        $tags = get_the_tags($post_id);
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post_id),
            'description' => wp_strip_all_tags(get_the_excerpt($post_id)),
            'url' => get_permalink($post_id),
            'datePublished' => get_post_time('c', true, $post_id),
            'dateModified' => get_post_modified_time('c', true, $post_id),
            'author' => array(
                '@type' => 'Person',
                'name' => $author ? $author->display_name : 'Unknown',
                'url' => $author ? get_author_posts_url($author->ID) : ''
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => $this->get_site_logo_url()
                )
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post_id)
            )
        );
        
        // Add featured image if available and meets Google Discover requirements
        if ($featured_image_id) {
            $image_data = $this->get_large_image_data($featured_image_id);
            if ($image_data && $image_data['width'] >= 1200) {
                $schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $image_data['url'],
                    'width' => $image_data['width'],
                    'height' => $image_data['height']
                );
            }
        }
        
        // Add categories
        if (!empty($categories)) {
            $schema['articleSection'] = array();
            foreach ($categories as $category) {
                $schema['articleSection'][] = $category->name;
            }
        }
        
        // Add tags as keywords
        if (!empty($tags)) {
            $keywords = array();
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
            $schema['keywords'] = implode(', ', $keywords);
        }
        
        // Add word count for content quality signals
        $word_count = str_word_count(wp_strip_all_tags(get_the_content(null, false, $post_id)));
        if ($word_count > 0) {
            $schema['wordCount'] = $word_count;
        }
        
        return $schema;
    }
    
    /**
     * Ensure large featured image for Google Discover (RSS2)
     */
    private function add_large_featured_image($post_id) {
        $featured_image_id = get_post_thumbnail_id($post_id);
        if (!$featured_image_id) {
            return;
        }
        
        $image_data = $this->get_large_image_data($featured_image_id);
        if (!$image_data) {
            return;
        }
        
        // Google Discover requires images to be at least 1200px wide
        if ($image_data['width'] >= 1200) {
            echo '<media:content url="' . esc_url($image_data['url']) . '" type="' . esc_attr($image_data['mime_type']) . '" medium="image" width="' . esc_attr($image_data['width']) . '" height="' . esc_attr($image_data['height']) . '" />' . "\n";
        }
    }
    
    /**
     * Ensure large featured image for Google Discover (Atom)
     */
    private function add_large_featured_image_atom($post_id) {
        $featured_image_id = get_post_thumbnail_id($post_id);
        if (!$featured_image_id) {
            return;
        }
        
        $image_data = $this->get_large_image_data($featured_image_id);
        if (!$image_data) {
            return;
        }
        
        // Google Discover requires images to be at least 1200px wide
        if ($image_data['width'] >= 1200) {
            echo '<link rel="enclosure" type="' . esc_attr($image_data['mime_type']) . '" href="' . esc_url($image_data['url']) . '" width="' . esc_attr($image_data['width']) . '" height="' . esc_attr($image_data['height']) . '" />' . "\n";
        }
    }
    
    /**
     * Get large image data suitable for Google Discover
     */
    private function get_large_image_data($attachment_id) {
        // Try to get the largest available image
        $image_sizes = array('full', 'large', 'medium_large', 'medium');
        
        foreach ($image_sizes as $size) {
            $image_data = wp_get_attachment_image_src($attachment_id, $size);
            if ($image_data && $image_data[1] >= 1200) {
                return array(
                    'url' => $image_data[0],
                    'width' => $image_data[1],
                    'height' => $image_data[2],
                    'mime_type' => get_post_mime_type($attachment_id)
                );
            }
        }
        
        // If no large image available, return the largest we have
        $image_data = wp_get_attachment_image_src($attachment_id, 'full');
        if ($image_data) {
            return array(
                'url' => $image_data[0],
                'width' => $image_data[1],
                'height' => $image_data[2],
                'mime_type' => get_post_mime_type($attachment_id)
            );
        }
        
        return null;
    }
    
    /**
     * Add author and publication information for Google Discover
     */
    private function add_author_publication_info($post_id) {
        $author_id = get_post_field('post_author', $post_id);
        $author = get_userdata($author_id);
        
        if ($author) {
            echo '<dc:creator><![CDATA[' . esc_html($author->display_name) . ']]></dc:creator>' . "\n";
        }
        
        // Add publication date in ISO format
        echo '<dc:date>' . esc_html(get_post_time('c', true, $post_id)) . '</dc:date>' . "\n";
        
        // Add modification date
        echo '<dc:modified>' . esc_html(get_post_modified_time('c', true, $post_id)) . '</dc:modified>' . "\n";
    }
    
    /**
     * Get site logo URL
     */
    private function get_site_logo_url() {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                return $logo_url;
            }
        }
        
        // Fallback to site icon
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $icon_url = wp_get_attachment_image_url($site_icon_id, 'full');
            if ($icon_url) {
                return $icon_url;
            }
        }
        
        // Final fallback
        return home_url('/wp-content/uploads/logo.png');
    }
}