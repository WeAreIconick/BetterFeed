<?php
/**
 * Feed Scheduling Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feed scheduler class for delayed publishing and Google Discover optimization
 */
class BF_Scheduler {
    
    /**
     * Class instance
     * 
     * @var BF_Scheduler
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Scheduler
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
        // Feed query modifications for scheduling
        add_action('pre_get_posts', array($this, 'modify_feed_query'));
        
        // Google Discover optimization
        add_filter('the_content_feed', array($this, 'optimize_for_google_discover'), 10, 2);
        add_filter('the_excerpt_rss', array($this, 'optimize_excerpt_for_discover'));
        
        // Add structured data for Google Discover
        add_action('rss2_item', array($this, 'add_google_discover_elements'));
        add_action('atom_entry', array($this, 'add_google_discover_elements'));
        
        // Feed delay scheduling
        if (!wp_next_scheduled('bf_process_delayed_feeds')) {
            wp_schedule_event(time(), 'hourly', 'bf_process_delayed_feeds');
        }
        
        add_action('bf_process_delayed_feeds', array($this, 'process_delayed_feed_items'));
        
        // Handle post publication delay
        add_action('transition_post_status', array($this, 'handle_post_publication_delay'), 10, 3);
    }
    
    /**
     * Modify feed query to respect scheduling settings
     * 
     * @param WP_Query $query Query object
     */
    public function modify_feed_query($query) {
        if (!$query->is_feed() || !$query->is_main_query()) {
            return;
        }
        
        $feed_delay_hours = get_option('bf_feed_delay_hours', 0);
        
        if ($feed_delay_hours > 0) {
            // Exclude posts published within the delay period
            $delay_time = current_time('mysql');
            $delay_timestamp = strtotime("-{$feed_delay_hours} hours", strtotime($delay_time));
            $delay_mysql = gmdate('Y-m-d H:i:s', $delay_timestamp);
            
            $query->set('date_query', array(
                array(
                    'before' => $delay_mysql,
                    'inclusive' => false,
                )
            ));
        }
        
        // Google Discover optimization: Prefer newer, high-quality content
        if (get_option('bf_optimize_google_discover', false)) {
            $this->optimize_feed_query_for_discover($query);
        }
    }
    
    /**
     * Optimize feed query specifically for Google Discover
     * 
     * @param WP_Query $query Query object
     */
    private function optimize_feed_query_for_discover($query) {
        // Limit to recent posts (Google Discover prefers fresh content)
        $discover_days = get_option('bf_discover_freshness_days', 7);
        
        $date_query = $query->get('date_query', array());
        $date_query[] = array(
            'after' => "{$discover_days} days ago",
        );
        
        $query->set('date_query', $date_query);
        
        // Prioritize posts with featured images (required for Google Discover)
        $meta_query = $query->get('meta_query', array());
        $meta_query[] = array(
            'key' => '_thumbnail_id',
            'compare' => 'EXISTS'
        );
        
        $query->set('meta_query', $meta_query);
        
        // Exclude short posts (Google Discover prefers substantial content)
        $min_words = get_option('bf_discover_min_words', 300);
        if ($min_words > 0) {
            add_filter('posts_where', array($this, 'filter_posts_by_word_count'));
        }
    }
    
    /**
     * Filter posts by minimum word count
     * 
     * @param string $where WHERE clause
     * @return string Modified WHERE clause
     */
    public function filter_posts_by_word_count($where) {
        global $wpdb;
        
        $min_words = get_option('bf_discover_min_words', 300);
        
        // Rough estimate: average 5 characters per word
        $min_chars = $min_words * 5;
        
        $where .= $wpdb->prepare(" AND CHAR_LENGTH(post_content) >= %d", $min_chars);
        
        // Remove filter to prevent affecting other queries
        remove_filter('posts_where', array($this, 'filter_posts_by_word_count'));
        
        return $where;
    }
    
    /**
     * Optimize content for Google Discover
     * 
     * @param string $content Post content
     * @param string $feed_type Feed type
     * @return string Optimized content
     */
    public function optimize_for_google_discover($content, $feed_type = 'rss2') {
        global $post;
        
        if (!get_option('bf_optimize_google_discover', false)) {
            return $content;
        }
        
        // Ensure featured image is prominent
        if (has_post_thumbnail($post->ID)) {
            $image_html = get_the_post_thumbnail($post->ID, 'large', array(
                'style' => 'width: 100%; height: auto; max-width: 1200px; margin-bottom: 20px;',
                'loading' => 'eager'
            ));
            
            // Prepend image if not already present
            if (strpos($content, $image_html) === false) {
                $content = $image_html . $content;
            }
        }
        
        // Add structured markup hints for Google Discover
        $content = $this->add_discover_markup($content, $post);
        
        // Ensure minimum content length
        $min_words = get_option('bf_discover_min_words', 300);
        if (str_word_count(wp_strip_all_tags($content)) < $min_words) {
            // Add a note about reading more on the website
            $read_more_text = sprintf(
                // Translators: %1$s is the site name, %2$s is the post permalink.
                __('Read the full article on %1$s: %2$s', 'betterfeed'),
                get_bloginfo('name'),
                get_permalink($post->ID)
            );
            
            $content .= '<p><em>' . $read_more_text . '</em></p>';
        }
        
        return $content;
    }
    
    /**
     * Optimize excerpt for Google Discover
     * 
     * @param string $excerpt Post excerpt
     * @return string Optimized excerpt
     */
    public function optimize_excerpt_for_discover($excerpt) {
        global $post;
        
        if (!get_option('bf_optimize_google_discover', false)) {
            return $excerpt;
        }
        
        // Ensure excerpt is substantial (Google Discover prefers detailed descriptions)
        $min_excerpt_words = get_option('bf_discover_min_excerpt_words', 50);
        $current_word_count = str_word_count(wp_strip_all_tags($excerpt));
        
        if ($current_word_count < $min_excerpt_words) {
            // Generate a longer excerpt from content
            $content = get_the_content();
            $content = wp_strip_all_tags($content);
            $words = explode(' ', $content);
            
            if (count($words) > $min_excerpt_words) {
                $excerpt = implode(' ', array_slice($words, 0, $min_excerpt_words)) . '...';
            }
        }
        
        return $excerpt;
    }
    
    /**
     * Add Google Discover specific elements
     */
    public function add_google_discover_elements() {
        global $post;
        
        if (!get_option('bf_optimize_google_discover', false)) {
            return;
        }
        
        // Add article type
        echo '<bf:articleType>news</bf:articleType>' . "\n";
        
        // Add reading time for user engagement signals
        $read_time = get_post_meta($post->ID, 'bf_read_time', true);
        if ($read_time) {
            echo '<bf:readingTime>' . esc_html($read_time) . '</bf:readingTime>' . "\n";
        }
        
        // Add freshness indicator
        $published_time = get_post_time('c', true);
        $now = current_time('c', true);
        $hours_since_publish = (strtotime($now) - strtotime($published_time)) / 3600;
        
        if ($hours_since_publish < 24) {
            echo '<bf:freshness>recent</bf:freshness>' . "\n";
        }
        
        // Add engagement metrics if available
        $comment_count = get_comments_number($post->ID);
        if ($comment_count > 0) {
            echo '<bf:engagement>' . esc_html($comment_count) . '</bf:engagement>' . "\n";
        }
        
        // Add topic classification
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $primary_category = $categories[0]->name;
            echo '<bf:topic>' . esc_html($primary_category) . '</bf:topic>' . "\n";
        }
    }
    
    /**
     * Add structured markup for Google Discover
     * 
     * @param string $content Post content
     * @param WP_Post $post Post object
     * @return string Content with markup
     */
    private function add_discover_markup($content, $post) {
        // Add article schema hints
        $schema_attributes = array(
            'data-article-title' => $post->post_title,
            'data-article-author' => get_the_author_meta('display_name', $post->post_author),
            'data-article-published' => get_post_time('c', true, $post),
            'data-article-modified' => get_post_modified_time('c', true, $post),
            'data-article-url' => get_permalink($post->ID),
        );
        
        // Add organization info
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        $schema_attributes['data-publisher-name'] = $site_name;
        $schema_attributes['data-publisher-url'] = $site_url;
        
        // Wrap content in article container with schema attributes
        $attributes_string = '';
        foreach ($schema_attributes as $attr => $value) {
            $attributes_string .= $attr . '="' . esc_attr($value) . '" ';
        }
        
        return '<div class="bf-discover-article" ' . trim($attributes_string) . '>' . $content . '</div>';
    }
    
    /**
     * Handle post publication delay
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function handle_post_publication_delay($new_status, $old_status, $post) {
        // Only process posts being published
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        // Only process configured post types
        $allowed_types = get_option('bf_delay_post_types', array('post'));
        if (!in_array($post->post_type, $allowed_types)) {
            return;
        }
        
        $feed_delay_hours = get_option('bf_feed_delay_hours', 0);
        
        if ($feed_delay_hours > 0) {
            // Store the original publication time
            update_post_meta($post->ID, 'bf_original_publish_time', current_time('mysql'));
            
            // Calculate when the post should appear in feeds
            $feed_publish_time = strtotime("+{$feed_delay_hours} hours");
            update_post_meta($post->ID, 'bf_feed_publish_time', gmdate('Y-m-d H:i:s', $feed_publish_time));
            
            // Clear feed cache to reflect changes
            if (class_exists('BF_Cache')) {
                BF_Cache::instance()->clear_feed_cache($post->ID);
            }
        }
    }
    
    /**
     * Process delayed feed items
     */
    public function process_delayed_feed_items() {
        $current_time = current_time('mysql');
        
        // Use WP_Query to find posts with delayed feed publish time (optimized)
        $cache_key = 'bf_delayed_posts_' . md5($current_time);
        $delayed_posts = wp_cache_get($cache_key, 'bf_scheduler');
        
        if ($delayed_posts === false) {
            // Enhance with WordPress object caching for better performance
            $cache_group = 'bf_scheduler_posts';
            $delayed_posts = wp_cache_get($cache_key, $cache_group);
            
            if ($delayed_posts === false) {
                // Avoid meta_query performance issues by using post query + filtering
                // Get recent posts first, then filter by delayed publish time
                $all_posts = get_posts(array(
                    'numberposts'  => 50, // Get more posts to filter from
                    'orderby'      => 'post_date',
                    'order'        => 'DESC',
                    'fields'       => 'ids',
                    'post_status'  => 'publish',
                    'post_type'    => array('post', 'page'),
                    'no_found_rows' => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false
                ));
                
                // Filter posts with delayed publish time
                $delayed_posts = array();
                foreach ($all_posts as $post_id) {
                    $publish_time = get_post_meta($post_id, 'bf_feed_publish_time', true);
                    if ($publish_time && $publish_time <= $current_time) {
                        $delayed_posts[] = $post_id;
                        if (count($delayed_posts) >= 10) {
                            break; // Limit to 10 delayed posts
                        }
                    }
                }
                
                // Sort by publish time (ascending - oldest first)
                usort($delayed_posts, function($a, $b) use ($current_time) {
                    $time_a = get_post_meta($a, 'bf_feed_publish_time', true);
                    $time_b = get_post_meta($b, 'bf_feed_publish_time', true);
                    return ($time_a <=> $time_b);
                });
                
                // Cache for 5 minutes with enhanced caching
                wp_cache_set($cache_key, $delayed_posts, $cache_group, 300);
                wp_cache_set('bf_delayed_count', count($delayed_posts), 'bf_scheduler', 300);
            }
        }
        
        if (!empty($delayed_posts)) {
            foreach ($delayed_posts as $post_id) {
                // Remove the delay meta
                delete_post_meta($post_id, 'bf_feed_publish_time');
                
                // Trigger feed update actions
                do_action('bf_feed_item_released', $post_id);
            }
            
            // Clear feed cache
            if (class_exists('BF_Cache')) {
                BF_Cache::instance()->clear_all();
            }
        }
        
        wp_reset_postdata();
            
        // Ping WebSub hubs if enabled
        if (class_exists('BF_WebSub') && get_option('bf_enable_websub', false)) {
            BF_WebSub::instance()->ping_hubs();
        }
    }
    
    /**
     * Get scheduled feed items count
     * 
     * @return int Number of posts waiting to appear in feeds
     */
    public function get_scheduled_items_count() {
        $current_time = current_time('mysql');
        
        // Use WP_Query with meta_query to count delayed posts (optimized)
        $cache_key = 'bf_scheduled_count_' . md5($current_time);
        $count = wp_cache_get($cache_key, 'bf_scheduler');
        
        if ($count === false) {
            // Avoid meta_query performance issues by counting manually
            // Get recent posts and count those with future publish times
            $all_posts = get_posts(array(
                'numberposts'  => -1, // Get all posts for counting
                'orderby'      => 'post_date',
                'order'        => 'DESC',
                'fields'       => 'ids',
                'post_status'  => 'publish',
                'post_type'    => array('post', 'page'),
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false
            ));
            
            // Count posts with future publish times
            $count = 0;
            foreach ($all_posts as $post_id) {
                $publish_time = get_post_meta($post_id, 'bf_feed_publish_time', true);
                if ($publish_time && $publish_time > $current_time) {
                    $count++;
                }
            }
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $count, 'bf_scheduler', 300);
        }
        
        return (int) $count;
    }
    
    /**
     * Get Google Discover optimization score
     * 
     * @param int $post_id Post ID
     * @return array Optimization score and recommendations
     */
    public function get_discover_optimization_score($post_id) {
        $score = 0;
        $max_score = 100;
        $recommendations = array();
        
        $post = get_post($post_id);
        
        if (!$post) {
            return array('score' => 0, 'recommendations' => array('Post not found'));
        }
        
        // Featured image check (30 points)
        if (has_post_thumbnail($post_id)) {
            $score += 30;
        } else {
            $recommendations[] = __('Add a high-quality featured image (required for Google Discover)', 'betterfeed');
        }
        
        // Content length check (25 points)
        $word_count = str_word_count(wp_strip_all_tags($post->post_content));
        $min_words = get_option('bf_discover_min_words', 300);
        
        if ($word_count >= $min_words) {
            $score += 25;
        } else {
            $recommendations[] = sprintf(
                // Translators: %1$d is the minimum word count, %2$d is the current word count.
                __('Increase content length to at least %1$d words (current: %2$d)', 'betterfeed'), 
                $min_words, 
                $word_count
            );
        }
        
        // Excerpt quality (15 points)
        $excerpt = get_the_excerpt($post);
        $excerpt_words = str_word_count(wp_strip_all_tags($excerpt));
        $min_excerpt_words = get_option('bf_discover_min_excerpt_words', 50);
        
        if ($excerpt_words >= $min_excerpt_words) {
            $score += 15;
        } else {
            // Translators: %d is the minimum number of words for a good excerpt
            $recommendations[] = sprintf(__('Write a more detailed excerpt (at least %d words)', 'betterfeed'), $min_excerpt_words);
        }
        
        // Categories/tags (10 points)
        $categories = get_the_category($post_id);
        $tags = get_the_tags($post_id);
        
        if (!empty($categories) || !empty($tags)) {
            $score += 10;
        } else {
            $recommendations[] = __('Add relevant categories or tags for better topic classification', 'betterfeed');
        }
        
        // Freshness (20 points)
        $publish_time = get_post_time('U', true, $post_id);
        $hours_since_publish = (current_time('U', true) - $publish_time) / 3600;
        
        if ($hours_since_publish <= 24) {
            $score += 20;
        } elseif ($hours_since_publish <= 168) { // 1 week
            $score += 10;
            $recommendations[] = __('Content is getting older - consider updating for freshness', 'betterfeed');
        } else {
            $recommendations[] = __('Content is older than a week - Google Discover heavily favors fresh content', 'betterfeed');
        }
        
        return array(
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => round(($score / $max_score) * 100),
            'recommendations' => $recommendations
        );
    }
}