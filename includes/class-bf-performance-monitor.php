<?php
/**
 * Performance Monitoring
 *
 * Automated performance tracking via cron jobs
 *
 * @package BetterFeed
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Monitor class
 */
class BF_Performance_Monitor {
    
    /**
     * Class instance
     * 
     * @var BF_Performance_Monitor
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Performance_Monitor
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
        
        // Schedule cron jobs
        add_action('wp', array($this, 'schedule_cron_jobs'));
        add_action('bf_performance_monitor_cron', array($this, 'run_performance_tests'));
        add_action('bf_performance_cleanup_cron', array($this, 'cleanup_old_data'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_bf_run_manual_test', array($this, 'ajax_run_manual_test'));
        
        // Handle cron job activation/deactivation
        register_activation_hook(BF_PLUGIN_FILE, array($this, 'activate_cron'));
        register_deactivation_hook(BF_PLUGIN_FILE, array($this, 'deactivate_cron'));
    }
    
    /**
     * Schedule cron jobs
     */
    public function schedule_cron_jobs() {
        // Performance monitoring every hour
        if (!wp_next_scheduled('bf_performance_monitor_cron')) {
            wp_schedule_event(time(), 'hourly', 'bf_performance_monitor_cron');
        }
        
        // Cleanup old data daily
        if (!wp_next_scheduled('bf_performance_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'bf_performance_cleanup_cron');
        }
    }
    
    /**
     * Run performance tests
     */
    public function run_performance_tests() {
        $feed_urls = $this->get_feed_urls_to_test();
        $test_results = array();
        
        foreach ($feed_urls as $feed_url) {
            $result = $this->test_feed_performance($feed_url);
            if ($result) {
                $test_results[] = $result;
            }
        }
        
        // Store results
        $this->store_performance_results($test_results);
        
        // Check for performance degradation
        $this->check_performance_alerts($test_results);
    }
    
    /**
     * Get feed URLs to test
     */
    private function get_feed_urls_to_test() {
        $urls = array();
        
        // Main feeds
        $urls[] = home_url('/feed/');
        $urls[] = home_url('/feed/rss2/');
        $urls[] = home_url('/feed/atom/');
        
        // JSON Feed if enabled
        $performance_options = get_option('bf_performance_options', array());
        if (!empty($performance_options['enable_json_feed'])) {
            $urls[] = home_url('/feed/json/');
        }
        
        // Custom feeds
        $custom_feeds = get_option('bf_custom_feeds', array());
        foreach ($custom_feeds as $feed) {
            if (!empty($feed['enabled'])) {
                $urls[] = home_url('/feed/' . $feed['slug'] . '/');
            }
        }
        
        return $urls;
    }
    
    /**
     * Test feed performance
     */
    private function test_feed_performance($url) {
        $start_time = microtime(true);
        
        // Test with different scenarios
        $scenarios = array(
            'normal' => array(),
            'gzip' => array(
                'headers' => array('Accept-Encoding' => 'gzip, deflate')
            ),
            'conditional' => array(
                'headers' => array(
                    'If-Modified-Since' => gmdate('D, d M Y H:i:s', strtotime('-1 day')) . ' GMT',
                    'If-None-Match' => '"test-etag"'
                )
            )
        );
        
        $results = array(
            'url' => $url,
            'timestamp' => current_time('mysql'),
            'scenarios' => array()
        );
        
        foreach ($scenarios as $scenario_name => $args) {
            $scenario_result = $this->test_scenario($url, $args);
            if ($scenario_result) {
                $results['scenarios'][$scenario_name] = $scenario_result;
            }
        }
        
        return $results;
    }
    
    /**
     * Test specific scenario
     */
    private function test_scenario($url, $args) {
        $start_time = microtime(true);
        
        $response = wp_remote_get($url, array_merge(array(
            'timeout' => 30,
            'redirection' => 5,
            'user-agent' => 'BetterFeed-Performance-Monitor/1.0'
        ), $args));
        
        $end_time = microtime(true);
        $response_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        
        if (is_wp_error($response)) {
            return array(
                'error' => $response->get_error_message(),
                'response_time' => $response_time
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        $body = wp_remote_retrieve_body($response);
        
        return array(
            'status_code' => $status_code,
            'response_time' => round($response_time, 2),
            'content_length' => strlen($body),
            'content_encoding' => $headers->offsetGet('content-encoding'),
            'cache_control' => $headers->offsetGet('cache-control'),
            'etag' => $headers->offsetGet('etag'),
            'last_modified' => $headers->offsetGet('last-modified'),
            'content_type' => $headers->offsetGet('content-type')
        );
    }
    
    /**
     * Store performance results
     */
    private function store_performance_results($results) {
        $stored_results = get_option('bf_performance_history', array());
        
        foreach ($results as $result) {
            $url = $result['url'];
            $timestamp = $result['timestamp'];
            
            if (!isset($stored_results[$url])) {
                $stored_results[$url] = array();
            }
            
            $stored_results[$url][] = array(
                'timestamp' => $timestamp,
                'scenarios' => $result['scenarios']
            );
            
            // Keep only last 100 entries per URL
            if (count($stored_results[$url]) > 100) {
                $stored_results[$url] = array_slice($stored_results[$url], -100);
            }
        }
        
        update_option('bf_performance_history', $stored_results);
        
        // Update metrics
        $this->update_performance_metrics($results);
    }
    
    /**
     * Update performance metrics
     */
    private function update_performance_metrics($results) {
        $metrics = get_option('bf_performance_metrics', array());
        
        $total_response_time = 0;
        $total_size = 0;
        $compressed_size = 0;
        $uncompressed_size = 0;
        $test_count = 0;
        
        foreach ($results as $result) {
            foreach ($result['scenarios'] as $scenario_name => $scenario_result) {
                if (!isset($scenario_result['error'])) {
                    $total_response_time += $scenario_result['response_time'];
                    $total_size += $scenario_result['content_length'];
                    $test_count++;
                    
                    // Track compression
                    if ($scenario_name === 'gzip' && !empty($scenario_result['content_encoding'])) {
                        $compressed_size += $scenario_result['content_length'];
                    } elseif ($scenario_name === 'normal') {
                        $uncompressed_size += $scenario_result['content_length'];
                    }
                }
            }
        }
        
        if ($test_count > 0) {
            $metrics['avg_load_time'] = round($total_response_time / $test_count / 1000, 2); // Convert to seconds
            $metrics['avg_feed_size'] = round($total_size / $test_count);
            
            if ($compressed_size > 0) {
                $metrics['compressed_size'] = $compressed_size;
                $metrics['bandwidth_saved'] = max(0, $uncompressed_size - $compressed_size);
            }
            
            $metrics['uncompressed_size'] = $uncompressed_size;
        }
        
        $metrics['last_updated'] = current_time('mysql');
        
        update_option('bf_performance_metrics', $metrics);
    }
    
    /**
     * Check for performance alerts
     */
    private function check_performance_alerts($results) {
        $alerts = array();
        
        foreach ($results as $result) {
            foreach ($result['scenarios'] as $scenario_name => $scenario_result) {
                if (isset($scenario_result['error'])) {
                    $alerts[] = array(
                        'type' => 'error',
                        'url' => $result['url'],
                        'message' => sprintf(
                            esc_html__('Feed error: %s', 'betterfeed'),
                            $scenario_result['error']
                        ),
                        'timestamp' => $result['timestamp']
                    );
                } elseif ($scenario_result['response_time'] > 5000) { // 5 seconds
                    $alerts[] = array(
                        'type' => 'warning',
                        'url' => $result['url'],
                        'message' => sprintf(
                            esc_html__('Slow response time: %s ms', 'betterfeed'),
                            $scenario_result['response_time']
                        ),
                        'timestamp' => $result['timestamp']
                    );
                }
            }
        }
        
        if (!empty($alerts)) {
            $this->store_alerts($alerts);
            $this->send_performance_alerts($alerts);
        }
    }
    
    /**
     * Store alerts
     */
    private function store_alerts($alerts) {
        $stored_alerts = get_option('bf_performance_alerts', array());
        
        foreach ($alerts as $alert) {
            $stored_alerts[] = $alert;
        }
        
        // Keep only last 50 alerts
        if (count($stored_alerts) > 50) {
            $stored_alerts = array_slice($stored_alerts, -50);
        }
        
        update_option('bf_performance_alerts', $stored_alerts);
    }
    
    /**
     * Send performance alerts
     */
    private function send_performance_alerts($alerts) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(esc_html__('[%s] BetterFeed Performance Alert', 'betterfeed'), $site_name);
        
        $message = esc_html__('BetterFeed Performance Alert', 'betterfeed') . "\n\n";
        $message .= sprintf(esc_html__('Site: %s', 'betterfeed'), $site_name) . "\n";
        $message .= sprintf(esc_html__('Time: %s', 'betterfeed'), current_time('Y-m-d H:i:s')) . "\n\n";
        
        foreach ($alerts as $alert) {
            $message .= sprintf(
                esc_html__('[%s] %s: %s', 'betterfeed'),
                strtoupper($alert['type']),
                $alert['url'],
                $alert['message']
            ) . "\n";
        }
        
        $message .= "\n" . esc_html__('Please check your BetterFeed settings and server performance.', 'betterfeed') . "\n";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup_old_data() {
        $performance_history = get_option('bf_performance_history', array());
        
        // Remove data older than 30 days
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        foreach ($performance_history as $url => &$results) {
            $results = array_filter($results, function($result) use ($cutoff_date) {
                return $result['timestamp'] > $cutoff_date;
            });
        }
        
        update_option('bf_performance_history', $performance_history);
        
        // Cleanup old metrics (keep daily averages)
        $this->cleanup_old_metrics();
    }
    
    /**
     * Cleanup old metrics
     */
    private function cleanup_old_metrics() {
        $metrics = get_option('bf_performance_metrics', array());
        $daily_stats = $metrics['daily_stats'] ?? array();
        
        // Keep only last 90 days of daily stats
        $cutoff_date = date('Y-m-d', strtotime('-90 days'));
        
        $metrics['daily_stats'] = array_filter($daily_stats, function($date) use ($cutoff_date) {
            return $date > $cutoff_date;
        }, ARRAY_FILTER_USE_KEY);
        
        update_option('bf_performance_metrics', $metrics);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            esc_html__('Performance Monitor', 'betterfeed'),
            esc_html__('Performance Monitor', 'betterfeed'),
            'manage_options',
            'bf-performance-monitor',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $performance_history = get_option('bf_performance_history', array());
        $alerts = get_option('bf_performance_alerts', array());
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BetterFeed Performance Monitor', 'betterfeed'); ?></h1>
            
            <div class="bf-monitor">
                <!-- Monitor Status -->
                <div class="bf-monitor-status">
                    <h2><?php esc_html_e('Monitor Status', 'betterfeed'); ?></h2>
                    <div class="bf-status-cards">
                        <div class="bf-status-card">
                            <div class="bf-status-icon">⏰</div>
                            <div class="bf-status-content">
                                <h3><?php esc_html_e('Next Test', 'betterfeed'); ?></h3>
                                <p><?php echo esc_html(wp_next_scheduled('bf_performance_monitor_cron') ? date('Y-m-d H:i:s', wp_next_scheduled('bf_performance_monitor_cron')) : esc_html__('Not scheduled', 'betterfeed')); ?></p>
                            </div>
                        </div>
                        
                        <div class="bf-status-card">
                            <div class="bf-status-icon">📊</div>
                            <div class="bf-status-content">
                                <h3><?php esc_html_e('Total Tests', 'betterfeed'); ?></h3>
                                <p><?php echo esc_html($this->count_total_tests($performance_history)); ?></p>
                            </div>
                        </div>
                        
                        <div class="bf-status-card">
                            <div class="bf-status-icon">⚠️</div>
                            <div class="bf-status-content">
                                <h3><?php esc_html_e('Active Alerts', 'betterfeed'); ?></h3>
                                <p><?php echo esc_html(count($alerts)); ?></p>
                            </div>
                        </div>
                        
                        <div class="bf-status-card">
                            <div class="bf-status-icon">🔧</div>
                            <div class="bf-status-content">
                                <h3><?php esc_html_e('Monitor Actions', 'betterfeed'); ?></h3>
                                <button type="button" class="button button-primary" id="bf-run-manual-test">
                                    <?php esc_html_e('Run Test Now', 'betterfeed'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Alerts -->
                <?php if (!empty($alerts)): ?>
                <div class="bf-recent-alerts">
                    <h2><?php esc_html_e('Recent Alerts', 'betterfeed'); ?></h2>
                    <div class="bf-alerts-list">
                        <?php foreach (array_slice(array_reverse($alerts), 0, 10) as $alert): ?>
                            <div class="bf-alert-item bf-alert-<?php echo esc_attr($alert['type']); ?>">
                                <div class="bf-alert-header">
                                    <span class="bf-alert-type"><?php echo esc_html(strtoupper($alert['type'])); ?></span>
                                    <span class="bf-alert-time"><?php echo esc_html(date('M j, H:i:s', strtotime($alert['timestamp']))); ?></span>
                                </div>
                                <div class="bf-alert-content">
                                    <div class="bf-alert-url"><?php echo esc_html($alert['url']); ?></div>
                                    <div class="bf-alert-message"><?php echo esc_html($alert['message']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Performance History -->
                <div class="bf-performance-history">
                    <h2><?php esc_html_e('Performance History', 'betterfeed'); ?></h2>
                    
                    <?php if (empty($performance_history)): ?>
                        <p><?php esc_html_e('No performance data available yet. Tests will start automatically.', 'betterfeed'); ?></p>
                    <?php else: ?>
                        <?php foreach ($performance_history as $url => $results): ?>
                            <div class="bf-feed-performance">
                                <h3><?php echo esc_html($url); ?></h3>
                                <div class="bf-performance-chart">
                                    <canvas id="bf-chart-<?php echo esc_attr(md5($url)); ?>" width="400" height="150"></canvas>
                                </div>
                                <div class="bf-performance-stats">
                                    <?php
                                    $latest_result = end($results);
                                    if ($latest_result && isset($latest_result['scenarios']['normal'])) {
                                        $normal = $latest_result['scenarios']['normal'];
                                        ?>
                                        <div class="bf-stat">
                                            <span class="bf-stat-label"><?php esc_html_e('Response Time:', 'betterfeed'); ?></span>
                                            <span class="bf-stat-value"><?php echo esc_html($normal['response_time'] ?? 'N/A'); ?>ms</span>
                                        </div>
                                        <div class="bf-stat">
                                            <span class="bf-stat-label"><?php esc_html_e('Size:', 'betterfeed'); ?></span>
                                            <span class="bf-stat-value"><?php echo esc_html($this->format_bytes($normal['content_length'] ?? 0)); ?></span>
                                        </div>
                                        <div class="bf-stat">
                                            <span class="bf-stat-label"><?php esc_html_e('Status:', 'betterfeed'); ?></span>
                                            <span class="bf-stat-value"><?php echo esc_html($normal['status_code'] ?? 'N/A'); ?></span>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#bf-run-manual-test').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php esc_js(__('Running...', 'betterfeed')); ?>');
                
                $.post(ajaxurl, {
                    action: 'bf_run_manual_test',
                    nonce: '<?php echo esc_js(wp_create_nonce('bf_run_manual_test')); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php esc_js(__('Test completed successfully!', 'betterfeed')); ?>');
                        location.reload();
                    } else {
                        alert('<?php esc_js(__('Test failed. Please try again.', 'betterfeed')); ?>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php esc_js(__('Run Test Now', 'betterfeed')); ?>');
                });
            });
        });
        </script>
        
        <style>
        .bf-monitor {
            margin-top: 20px;
        }
        
        .bf-status-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .bf-status-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bf-status-icon {
            font-size: 2em;
            margin-right: 15px;
        }
        
        .bf-status-content h3 {
            margin: 0 0 5px 0;
            color: #0073aa;
        }
        
        .bf-status-content p {
            margin: 0;
            color: #666;
        }
        
        .bf-alerts-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .bf-alert-item {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
        }
        
        .bf-alert-error {
            border-left: 4px solid #d63638;
            background: #fef2f2;
        }
        
        .bf-alert-warning {
            border-left: 4px solid #ff6900;
            background: #fff8f0;
        }
        
        .bf-alert-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .bf-alert-type {
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .bf-alert-time {
            color: #666;
            font-size: 0.9em;
        }
        
        .bf-alert-url {
            font-family: monospace;
            font-size: 0.9em;
            color: #0073aa;
            margin-bottom: 4px;
        }
        
        .bf-alert-message {
            color: #333;
        }
        
        .bf-feed-performance {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .bf-feed-performance h3 {
            margin-top: 0;
            font-family: monospace;
            font-size: 1.1em;
        }
        
        .bf-performance-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .bf-stat {
            display: flex;
            flex-direction: column;
        }
        
        .bf-stat-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .bf-stat-value {
            font-weight: bold;
            color: #0073aa;
        }
        
        @media (max-width: 768px) {
            .bf-status-cards {
                grid-template-columns: 1fr;
            }
            
            .bf-performance-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Count total tests
     */
    private function count_total_tests($performance_history) {
        $total = 0;
        foreach ($performance_history as $results) {
            $total += count($results);
        }
        return $total;
    }
    
    /**
     * Format bytes
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * AJAX run manual test
     */
    public function ajax_run_manual_test() {
        check_ajax_referer('bf_run_manual_test', 'nonce');
        
        // Run performance tests
        $this->run_performance_tests();
        
        wp_send_json_success(array('message' => esc_html__('Performance test completed.', 'betterfeed')));
    }
    
    /**
     * Activate cron on plugin activation
     */
    public function activate_cron() {
        $this->schedule_cron_jobs();
    }
    
    /**
     * Deactivate cron on plugin deactivation
     */
    public function deactivate_cron() {
        wp_clear_scheduled_hook('bf_performance_monitor_cron');
        wp_clear_scheduled_hook('bf_performance_cleanup_cron');
    }
}
