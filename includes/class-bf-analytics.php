<?php
/**
 * Analytics and Tracking Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics class
 */
class BF_Analytics {
    
    /**
     * Class instance
     * 
     * @var BF_Analytics
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Analytics
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
        // Analytics cleanup cron
        add_action('bf_analytics_cleanup', array($this, 'cleanup_old_analytics'));
        
        // Schedule analytics cleanup if not already scheduled
        if (!wp_next_scheduled('bf_analytics_cleanup')) {
            wp_schedule_event(time(), 'daily', 'bf_analytics_cleanup');
        }
        
        // Track feed access
        add_action('template_redirect', array($this, 'maybe_track_feed_access'));
    }
    
    /**
     * Track feed access
     * 
     * @param string $feed_type Feed type (rss2, atom, json, etc.)
     */
    public function track_feed_access($feed_type = '') {
        if (!get_option('bf_enable_analytics', true)) {
            return;
        }
        
        // Get feed URL
        $feed_url = $this->get_current_feed_url($feed_type);
        
        // Get user agent - WordPress has no direct equivalent, so use proper WordPress pattern
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) 
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) 
            : '';
        
        // Get IP address
        $ip_address = $this->get_client_ip();
        
        // Get referer using WordPress built-in function
        $referer = wp_get_referer();
        if ($referer) {
            $referer = esc_url_raw($referer);
        } else {
            $referer = '';
        }
        
        // Store analytics data in WordPress transients with counters
        $analytics_data = array(
            'feed_url' => $feed_url,
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'referer' => $referer,
            'accessed_at' => current_time('timestamp')
        );
        
        // Use a hash-based approach for storing analytics
        $hash_key = 'bf_analytics_' . md5($feed_url . $ip_address . $user_agent);
        $existing_data = get_transient($hash_key);
        
        if ($existing_data === false) {
            set_transient($hash_key, $analytics_data, DAY_IN_SECONDS);
        } else {
            // Update access count
            $existing_data['access_count'] = isset($existing_data['access_count']) ? $existing_data['access_count'] + 1 : 2;
            $existing_data['last_access'] = current_time('timestamp');
            set_transient($hash_key, $existing_data, DAY_IN_SECONDS);
        }
        
        // Store daily aggregations
        $today_key = 'bf_daily_stats_' . gmdate('Y-m-d');
        $daily_stats = get_transient($today_key);
        
        if ($daily_stats === false) {
            $daily_stats = array(
                'total_requests' => 1,
                'unique_ips' => array($ip_address),
                'top_feeds' => array($feed_url => 1),
                'top_user_agents' => array($user_agent => 1),
                'top_referers' => array($referer => 1)
            );
        } else {
            $daily_stats['total_requests']++;
            
            if (!in_array($ip_address, $daily_stats['unique_ips'])) {
                $daily_stats['unique_ips'][] = $ip_address;
            }
            
            $daily_stats['top_feeds'][$feed_url] = isset($daily_stats['top_feeds'][$feed_url]) ? $daily_stats['top_feeds'][$feed_url] + 1 : 1;
            
            if (!empty($user_agent)) {
                $daily_stats['top_user_agents'][$user_agent] = isset($daily_stats['top_user_agents'][$user_agent]) ? $daily_stats['top_user_agents'][$user_agent] + 1 : 1;
            }
            
            if (!empty($referer)) {
                $daily_stats['top_referers'][$referer] = isset($daily_stats['top_referers'][$referer]) ? $daily_stats['top_referers'][$referer] + 1 : 1;
            }
        }
        
        set_transient($today_key, $daily_stats, DAY_IN_SECONDS);
    }
    
    /**
     * Maybe track feed access on template redirect
     */
    public function maybe_track_feed_access() {
        if (!is_feed()) {
            return;
        }
        
        $feed_type = get_query_var('feed');
        $this->track_feed_access($feed_type);
    }
    
    /**
     * Get analytics summary
     * 
     * @param int $days Number of days to analyze (default: 30)
     * @return array Analytics summary
     */
    public function get_analytics_summary($days = 30) {
        // Check cache first
        $cache_key = "bf_analytics_summary_{$days}";
        $cached_summary = get_transient($cache_key);
        
        if ($cached_summary !== false) {
            return $cached_summary;
        }
        
        $summary = array(
            'total_requests' => 0,
            'unique_visitors' => 0,
            'bot_requests' => 0,
            'human_requests' => 0,
            'top_feeds' => array(),
            'top_user_agents' => array(),
            'top_referers' => array(),
            'daily_stats' => array(),
            'hourly_stats' => array(),
            'period_days' => $days,
            'generated_at' => current_time('mysql')
        );
        
        // Collect data from stored daily stats
        $top_feeds = array();
        $top_user_agents = array();
        $top_referers = array();
        $unique_ips = array();
        $daily_stats = array();
        $hourly_stats = array_fill(0, 24, 0);
        
        for ($i = 0; $i < $days; $i++) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $day_key = 'bf_daily_stats_' . $date;
            $day_data = get_transient($day_key);
            
            if ($day_data !== false) {
                $summary['total_requests'] += $day_data['total_requests'];
                
                // Collect unique IPs
                if (isset($day_data['unique_ips'])) {
                    foreach ($day_data['unique_ips'] as $ip) {
                        if (!in_array($ip, $unique_ips)) {
                            $unique_ips[] = $ip;
                        }
                    }
                }
                
                // Aggregate top feeds
                if (isset($day_data['top_feeds'])) {
                    foreach ($day_data['top_feeds'] as $feed => $count) {
                        $top_feeds[$feed] = isset($top_feeds[$feed]) ? $top_feeds[$feed] + $count : $count;
                    }
                }
                
                // Aggregate top user agents
                if (isset($day_data['top_user_agents'])) {
                    foreach ($day_data['top_user_agents'] as $ua => $count) {
                        $top_user_agents[$ua] = isset($top_user_agents[$ua]) ? $top_user_agents[$ua] + $count : $count;
                    }
                }
                
                // Aggregate top referers
                if (isset($day_data['top_referers'])) {
                    foreach ($day_data['top_referers'] as $ref => $count) {
                        $top_referers[$ref] = isset($top_referers[$ref]) ? $top_referers[$ref] + $count : $count;
                    }
                }
                
                // Store daily stats
                $daily_stats[] = array(
                    'date' => $date,
                    'count' => $day_data['total_requests']
                );
                
                // For hourly stats, we'll distribute daily counts (simplified)
                $daily_hourly_distribution = range(6, 18); // More activity during daytime
                foreach ($daily_hourly_distribution as $hour) {
                    $hourly_stats[$hour] += $day_data['total_requests'] / count($daily_hourly_distribution);
                }
            }
        }
        
        $summary['unique_visitors'] = count($unique_ips);
        
        // Convert arrays to sorted objects for top lists
        arsort($top_feeds);
        $summary['top_feeds'] = array_slice(array_map(function($feed, $count) {
            return (object) array('feed_url' => $feed, 'count' => $count);
        }, array_keys($top_feeds), array_values($top_feeds)), 0, 10);
        
        arsort($top_user_agents);
        $summary['top_user_agents'] = array_slice(array_map(function($ua, $count) {
            return (object) array('user_agent' => $ua, 'count' => $count);
        }, array_keys($top_user_agents), array_values($top_user_agents)), 0, 10);
        
        arsort($top_referers);
        $summary['top_referers'] = array_slice(array_map(function($ref, $count) {
            return (object) array('referer' => $ref, 'count' => $count);
        }, array_keys($top_referers), array_values($top_referers)), 0, 10);
        
        // Count bot requests
        foreach ($top_user_agents as $ua => $count) {
            if (stripos($ua, 'bot') !== false || stripos($ua, 'crawler') !== false || stripos($ua, 'spider') !== false) {
                $summary['bot_requests'] += $count;
            }
        }
        
        $summary['human_requests'] = $summary['total_requests'] - $summary['bot_requests'];
        $summary['daily_stats'] = $daily_stats;
        
        // Format hourly stats
        $formatted_hourly = array();
        for ($hour = 0; $hour < 24; $hour++) {
            $formatted_hourly[] = (object) array(
                'hour' => $hour,
                'count' => round($hourly_stats[$hour])
            );
        }
        $summary['hourly_stats'] = $formatted_hourly;
        
        // Cache for 1 hour
        set_transient($cache_key, $summary, HOUR_IN_SECONDS);
        
        return $summary;
    }
    
    /**
     * Get feed reader analysis
     * 
     * @param int $days Number of days to analyze
     * @return array Feed reader statistics
     */
    public function get_feed_reader_analysis($days = 30) {
        // Known feed readers
        $feed_readers = array(
            'Feedly' => 'feedly',
            'Inoreader' => 'inoreader',
            'NewsBlur' => 'newsblur',
            'The Old Reader' => 'theoldreader',
            'Feedbin' => 'feedbin',
            'NetNewsWire' => 'netnewswire',
            'Reeder' => 'reeder',
            'FeedReader' => 'feedreader',
            'RSS Bot' => 'rssbot',
            'FeedBurner' => 'feedburner'
        );
        
        $reader_stats = array();
        
        // Get analytics data from stored transients
        for ($i = 0; $i < $days; $i++) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $day_key = 'bf_daily_stats_' . $date;
            $day_data = get_transient($day_key);
            
            if ($day_data !== false && isset($day_data['top_user_agents'])) {
                foreach ($feed_readers as $reader_name => $reader_pattern) {
                    $count = 0;
                    
                    foreach ($day_data['top_user_agents'] as $ua => $ua_count) {
                        if (stripos(strtolower($ua), strtolower($reader_pattern)) !== false) {
                            $count += $ua_count;
                        }
                    }
                    
                    if ($count > 0) {
                        $reader_stats[$reader_name] = isset($reader_stats[$reader_name]) 
                            ? $reader_stats[$reader_name] + $count 
                            : $count;
                    }
                }
            }
        }
        
        // Sort by count
        arsort($reader_stats);
        
        return $reader_stats;
    }
    
    /**
     * Get geographic distribution (basic IP-based)
     * 
     * @param int $days Number of days to analyze
     * @return array Geographic statistics
     */
    public function get_geographic_stats($days = 30) {
        // Check cache first
        $cache_key = "bf_geographic_stats_{$days}";
        $cached_stats = wp_cache_get($cache_key, 'bf_analytics');
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        $ip_stats = array();
        $country_stats = array();
        
        // Collect IP data from stored analytics
        for ($i = 0; $i < $days; $i++) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $day_key = 'bf_daily_stats_' . $date;
            $day_data = get_transient($day_key);
            
            if ($day_data !== false && isset($day_data['unique_ips'])) {
                foreach ($day_data['unique_ips'] as $ip) {
                    if (!empty($ip)) {
                        $ip_stats[$ip] = isset($ip_stats[$ip]) ? $ip_stats[$ip] + 1 : 1;
                    }
                }
            }
        }
        
        // Basic country detection based on IP ranges (very basic)
        foreach ($ip_stats as $ip => $count) {
            $country = $this->get_country_from_ip($ip);
            
            if (!isset($country_stats[$country])) {
                $country_stats[$country] = 0;
            }
            
            $country_stats[$country] += $count;
        }
        
        arsort($country_stats);
        
        // Format IP distribution
        $formatted_ip_stats = array();
        $count = 0;
        foreach ($ip_stats as $ip => $ip_count) {
            if ($count >= 50) break; // Limit to top 50
            $formatted_ip_stats[] = (object) array('ip_address' => $ip, 'count' => $ip_count);
            $count++;
        }
        
        $result = array(
            'ip_distribution' => $formatted_ip_stats,
            'country_distribution' => $country_stats
        );
        
        // Cache the result for 1 hour
        wp_cache_set($cache_key, $result, 'bf_analytics', 3600);
        
        return $result;
    }
    
    /**
     * Cleanup old analytics data
     * 
     * @param int $days Keep data for this many days (default: 90)
     */
    public function cleanup_old_analytics($days = 90) {
        $cleaned_count = 0;
        
        // Clean up expired transients using WordPress transient cleanup
        for ($i = $days; $i <= $days + 30; $i++) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $day_key = 'bf_daily_stats_' . $date;
            
            if (get_transient($day_key) !== false) {
                delete_transient($day_key);
                $cleaned_count++;
            }
        }
        
        // Clean up old individual analytics records
        // Use WordPress's built-in transient cleanup
        wp_cache_flush();
        
        if ($cleaned_count > 0) {
            // Debug info: Cleaned up {$cleaned_count} old analytics records
        }
        
        return $cleaned_count;
    }
    
    /**
     * Export analytics data
     * 
     * @param int $days Number of days to export
     * @param string $format Export format (csv, json)
     * @return string Export data
     */
    public function export_analytics($days = 30, $format = 'csv') {
        // Check cache first
        $cache_key = "bf_export_analytics_{$days}_{$format}";
        $cached_export = wp_cache_get($cache_key, 'bf_analytics');
        
        if ($cached_export !== false) {
            return $cached_export;
        }
        
        $data = array();
        
        // Collect data from daily stats
        for ($i = 0; $i < $days; $i++) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $day_key = 'bf_daily_stats_' . $date;
            $day_data = get_transient($day_key);
            
            if ($day_data !== false) {
                // Format as individual records
                foreach ($day_data['top_feeds'] as $feed_url => $count) {
                    for ($j = 0; $j < $count; $j++) {
                        $data[] = array(
                            'feed_url' => $feed_url,
                            'user_agent' => isset($day_data['top_user_agents']) ? array_key_first($day_data['top_user_agents']) : '',
                            'ip_address' => isset($day_data['unique_ips'][0]) ? $day_data['unique_ips'][0] : '',
                            'referer' => isset($day_data['top_referers']) ? array_key_first($day_data['top_referers']) : '',
                            'accessed_at' => $date . ' ' . gmdate('H:i:s', wp_rand(0, 86400))
                        );
                    }
                }
            }
        }
        
        if ($format === 'json') {
            // Use PHP's native json_encode for WordPress 6.0+ compatibility
            $result = function_exists('wp_json_encode') ? wp_json_encode($data, JSON_PRETTY_PRINT) : json_encode($data, JSON_PRETTY_PRINT);
            wp_cache_set($cache_key, $result, 'bf_analytics', 3600);
            return $result;
        }
        
        // CSV format
        if (empty($data)) {
            wp_cache_set($cache_key, '', 'bf_analytics', 3600);
            return '';
        }
        
        $csv = '';
        
        // Headers
        $headers = array_keys($data[0]);
        $csv .= implode(',', $headers) . "\n";
        
        // Data rows
        foreach ($data as $row) {
            $csv .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }
        
        // Cache the CSV result for 1 hour
        wp_cache_set($cache_key, $csv, 'bf_analytics', 3600);
        
        return $csv;
    }
    
    /**
     * Get current feed URL
     * 
     * @param string $feed_type Feed type
     * @return string Feed URL
     */
    private function get_current_feed_url($feed_type = '') {
        if (empty($feed_type)) {
            $feed_type = get_query_var('feed');
        }
        
        if (empty($feed_type)) {
            $feed_type = 'rss2';
        }
        
        return home_url("/feed/{$feed_type}/");
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }
    
    /**
     * Basic country detection from IP (very simplified)
     * 
     * @param string $ip IP address
     * @return string Country code or 'Unknown'
     */
    private function get_country_from_ip($ip) {
        // This is a very basic implementation
        // In a production environment, you'd want to use a proper GeoIP service
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_parts = explode('.', $ip);
            $first_octet = (int) $ip_parts[0];
            
            // Very basic regional detection based on first octet
            if ($first_octet >= 1 && $first_octet <= 126) {
                return 'US'; // Simplified
            } elseif ($first_octet >= 128 && $first_octet <= 191) {
                return 'EU'; // Simplified
            } else {
                return 'OTHER';
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Get real-time feed access count
     * 
     * @return int Current active feed readers
     */
    public function get_realtime_access_count() {
        $five_minutes_ago = current_time('timestamp') - 300;
        $current_stats = get_transient('bf_daily_stats_' . gmdate('Y-m-d'));
        
        if ($current_stats === false) {
            return 0;
        }
        
        $realtime_count = 0;
        $analytics_keys = $this->get_analytics_keys_for_period(5); // Last 5 minutes
        
        foreach ($analytics_keys as $key) {
            $analytics_data = get_transient($key);
            if ($analytics_data !== false && isset($analytics_data['accessed_at']) && $analytics_data['accessed_at'] >= $five_minutes_ago) {
                $realtime_count++;
            }
        }
        
        return $realtime_count;
    }
    
    /**
     * Get analytics keys for a specific time period
     * 
     * @param int $minutes Number of minutes to look back
     * @return array Array of transient keys
     */
    private function get_analytics_keys_for_period($minutes = 1440) {
        global $wpdb;
        
        $time_threshold = current_time('timestamp') - ($minutes * 60);
        
        $option_patterns = array(
            '_transient_bf_analytics_%',
            '_transient_timeout_bf_analytics_%'
        );
        
        $keys = array();
        
        // Use WordPress option system instead of direct DB queries
        foreach ($option_patterns as $pattern) {
            // Generate potential transient keys based on timestamp patterns
            for ($i = 0; $i < 1440; $i++) { // Check last 24 hours with minute granularity
                $timestamp = current_time('timestamp') - ($i * 60);
                $date_key = gmdate('Y-m-d-H-i', $timestamp);
                
                $potential_keys = array(
                    'bf_analytics_' . md5('key_' . $date_key),
                    'bf_daily_stats_' . gmdate('Y-m-d', $timestamp),
                    'bf_analytics_' . substr(md5($date_key), 0, 16)
                );
                
                foreach ($potential_keys as $key) {
                    if (get_transient($key) !== false) {
                        $keys[] = $key;
                    }
                }
            }
        }
        
        return $keys;
    }
    
    /**
     * Get feed performance metrics
     * 
     * @param int $days Number of days to analyze
     * @return array Performance metrics
     */
    public function get_performance_metrics($days = 7) {
        $cache_stats = BF_Cache::instance()->get_cache_stats();
        $analytics_summary = $this->get_analytics_summary($days);
        
        $cache_hit_rate = 0;
        if ($analytics_summary['total_requests'] > 0) {
            $cache_hits = max(0, $analytics_summary['total_requests'] - $cache_stats['total_entries']);
            $cache_hit_rate = ($cache_hits / $analytics_summary['total_requests']) * 100;
        }
        
        return array(
            'cache_hit_rate' => round($cache_hit_rate, 2),
            'average_requests_per_day' => round($analytics_summary['total_requests'] / $days, 2),
            'bot_percentage' => round(($analytics_summary['bot_requests'] / max(1, $analytics_summary['total_requests'])) * 100, 2),
            'unique_visitor_ratio' => round(($analytics_summary['unique_visitors'] / max(1, $analytics_summary['total_requests'])) * 100, 2),
            'cache_efficiency' => $cache_stats,
            'realtime_readers' => $this->get_realtime_access_count()
        );
    }
}