<?php
/**
 * Performance Dashboard
 *
 * Visual dashboard showing optimization impact and metrics
 *
 * @package BetterFeed
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Dashboard class
 */
class BF_Dashboard {
    
    /**
     * Class instance
     * 
     * @var BF_Dashboard
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Dashboard
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
        
        // Admin hooks - now integrated into main BetterFeed settings
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        // AJAX hooks removed - using REST API instead
        // add_action('wp_ajax_bf_get_dashboard_data', array($this, 'ajax_get_dashboard_data'));
        // add_action('wp_ajax_bf_reset_metrics', array($this, 'ajax_reset_metrics'));
        
        // Track feed requests for analytics
        add_action('template_redirect', array($this, 'track_feed_request'), 999);
        
        // Initialize metrics if needed
        add_action('init', array($this, 'init_metrics'));
    }
    
    /**
     * Initialize metrics
     */
    public function init_metrics() {
        $metrics = get_option('bf_performance_metrics', array());
        
        if (empty($metrics)) {
            $default_metrics = array(
                'total_requests' => 0,
                'cached_requests' => 0,
                'gzip_savings' => 0,
                'bandwidth_saved' => 0,
                'avg_load_time' => 0,
                'avg_feed_size' => 0,
                'compressed_size' => 0,
                'uncompressed_size' => 0,
                'last_updated' => current_time('mysql'),
                'daily_stats' => array(),
                'feed_types' => array(),
                'user_agents' => array(),
                'status_codes' => array()
            );
            
            update_option('bf_performance_metrics', $default_metrics);
        }
    }
    
    /**
     * Track feed requests for analytics
     */
    public function track_feed_request() {
        if (!is_feed()) {
            return;
        }
        
        $start_time = microtime(true);
        $feed_type = get_query_var('feed') ?: 'rss2';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'Unknown';
        
        // Track request
        $this->track_request_metric($feed_type, $user_agent, $start_time);
    }
    
    /**
     * Track request metric
     */
    private function track_request_metric($feed_type, $user_agent, $start_time) {
        $metrics = get_option('bf_performance_metrics', array());
        
        // Increment total requests
        $metrics['total_requests']++;
        
        // Track feed types
        if (!isset($metrics['feed_types'][$feed_type])) {
            $metrics['feed_types'][$feed_type] = 0;
        }
        $metrics['feed_types'][$feed_type]++;
        
        // Track user agents (top 10)
        $metrics['user_agents'][$user_agent] = ($metrics['user_agents'][$user_agent] ?? 0) + 1;
        arsort($metrics['user_agents']);
        $metrics['user_agents'] = array_slice($metrics['user_agents'], 0, 10, true);
        
        // Track daily stats
        $today = gmdate('Y-m-d');
        if (!isset($metrics['daily_stats'][$today])) {
            $metrics['daily_stats'][$today] = array(
                'requests' => 0,
                'avg_load_time' => 0,
                'bandwidth_saved' => 0
            );
        }
        $metrics['daily_stats'][$today]['requests']++;
        
        $metrics['last_updated'] = current_time('mysql');
        
        update_option('bf_performance_metrics', $metrics);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            esc_html__('Performance Dashboard', 'betterfeed'),
            esc_html__('Performance Dashboard', 'betterfeed'),
            'manage_options',
            'bf-performance-dashboard',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $metrics = get_option('bf_performance_metrics', array());
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BetterFeed Performance Dashboard', 'betterfeed'); ?></h1>
            
            <div class="bf-dashboard">
                <!-- Performance Overview -->
                <div class="bf-overview-cards">
                    <div class="bf-card">
                        <div class="bf-card-icon">📊</div>
                        <div class="bf-card-content">
                            <h3><?php echo esc_html($metrics['total_requests'] ?? 0); ?></h3>
                            <p><?php esc_html_e('Total Feed Requests', 'betterfeed'); ?></p>
                        </div>
                    </div>
                    
                    <div class="bf-card">
                        <div class="bf-card-icon">⚡</div>
                        <div class="bf-card-content">
                            <h3><?php echo esc_html(round($metrics['avg_load_time'] ?? 0, 2)); ?>s</h3>
                            <p><?php esc_html_e('Average Load Time', 'betterfeed'); ?></p>
                        </div>
                    </div>
                    
                    <div class="bf-card">
                        <div class="bf-card-icon">💾</div>
                        <div class="bf-card-content">
                            <h3><?php echo esc_html($this->format_bytes($metrics['bandwidth_saved'] ?? 0)); ?></h3>
                            <p><?php esc_html_e('Bandwidth Saved', 'betterfeed'); ?></p>
                        </div>
                    </div>
                    
                    <div class="bf-card">
                        <div class="bf-card-icon">🎯</div>
                        <div class="bf-card-content">
                            <h3><?php echo esc_html($this->calculate_optimization_score($metrics)); ?>%</h3>
                            <p><?php esc_html_e('Optimization Score', 'betterfeed'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="bf-charts-section">
                    <div class="bf-chart-container">
                        <h2><?php esc_html_e('Feed Request Trends (Last 30 Days)', 'betterfeed'); ?></h2>
                        <canvas id="bf-requests-chart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="bf-chart-container">
                        <h2><?php esc_html_e('Feed Types Distribution', 'betterfeed'); ?></h2>
                        <canvas id="bf-feed-types-chart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Performance Details -->
                <div class="bf-performance-details">
                    <div class="bf-detail-section">
                        <h2><?php esc_html_e('Compression Performance', 'betterfeed'); ?></h2>
                        <div class="bf-compression-stats">
                            <div class="bf-stat-item">
                                <span class="bf-stat-label"><?php esc_html_e('Uncompressed Size:', 'betterfeed'); ?></span>
                                <span class="bf-stat-value"><?php echo esc_html($this->format_bytes($metrics['uncompressed_size'] ?? 0)); ?></span>
                            </div>
                            <div class="bf-stat-item">
                                <span class="bf-stat-label"><?php esc_html_e('Compressed Size:', 'betterfeed'); ?></span>
                                <span class="bf-stat-value"><?php echo esc_html($this->format_bytes($metrics['compressed_size'] ?? 0)); ?></span>
                            </div>
                            <div class="bf-stat-item">
                                <span class="bf-stat-label"><?php esc_html_e('Compression Ratio:', 'betterfeed'); ?></span>
                                <span class="bf-stat-value"><?php echo esc_html($this->calculate_compression_ratio($metrics)); ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bf-detail-section">
                        <h2><?php esc_html_e('Top Feed Readers', 'betterfeed'); ?></h2>
                        <div class="bf-user-agents">
                            <?php
                            $user_agents = $metrics['user_agents'] ?? array();
                            foreach (array_slice($user_agents, 0, 5, true) as $agent => $count):
                            ?>
                                <div class="bf-user-agent-item">
                                    <span class="bf-user-agent-name"><?php echo esc_html($this->format_user_agent($agent)); ?></span>
                                    <span class="bf-user-agent-count"><?php echo esc_html($count); ?> <?php esc_html_e('requests', 'betterfeed'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bf-detail-section">
                        <h2><?php esc_html_e('Performance Actions', 'betterfeed'); ?></h2>
                        <div class="bf-dashboard-actions">
                            <button type="button" class="button button-secondary" id="bf-refresh-metrics">
                                <?php esc_html_e('Refresh Metrics', 'betterfeed'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="bf-reset-metrics">
                                <?php esc_html_e('Reset All Data', 'betterfeed'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="bf-export-report">
                                <?php esc_html_e('Export Report', 'betterfeed'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        // Chart.js removed - external scripts not allowed on WordPress.org
        // Charts will display as basic HTML tables instead
        ?>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize charts
            initRequestsChart();
            initFeedTypesChart();
            
            // Dashboard actions
            $('#bf-refresh-metrics').on('click', function() {
                location.reload();
            });
            
            $('#bf-reset-metrics').on('click', function() {
                // AJAX functionality removed - using REST API instead
                alert('<?php esc_js(__('Reset metrics functionality moved to REST API.', 'betterfeed')); ?>');
            });
            
            $('#bf-export-report').on('click', function() {
                window.open('<?php echo esc_url(admin_url('admin-post.php?action=bf_export_dashboard_report')); ?>', '_blank');
            });
        });
        
        function initRequestsChart() {
            const ctx = document.getElementById('bf-requests-chart').getContext('2d');
            
            // Get daily data for last 30 days
            const dailyStats = <?php echo wp_json_encode($this->get_daily_stats_for_chart()); ?>;
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dailyStats.labels,
                    datasets: [{
                        label: '<?php esc_js(__('Feed Requests', 'betterfeed')); ?>',
                        data: dailyStats.data,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function initFeedTypesChart() {
            const ctx = document.getElementById('bf-feed-types-chart').getContext('2d');
            
            const feedTypes = <?php echo wp_json_encode($metrics['feed_types'] ?? array()); ?>;
            const labels = Object.keys(feedTypes);
            const data = Object.values(feedTypes);
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#0073aa',
                            '#00a32a',
                            '#d63638',
                            '#ff6900',
                            '#8b5cf6'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        </script>
        
        <style>
        .bf-dashboard {
            margin-top: 20px;
        }
        
        .bf-overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .bf-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bf-card-icon {
            font-size: 2em;
            margin-right: 15px;
        }
        
        .bf-card-content h3 {
            margin: 0;
            font-size: 2em;
            color: #0073aa;
        }
        
        .bf-card-content p {
            margin: 5px 0 0 0;
            color: #666;
        }
        
        .bf-charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .bf-chart-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bf-chart-container h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .bf-performance-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .bf-detail-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bf-detail-section h2 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .bf-compression-stats,
        .bf-user-agents {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .bf-stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .bf-stat-label {
            font-weight: 500;
        }
        
        .bf-stat-value {
            color: #0073aa;
            font-weight: bold;
        }
        
        .bf-user-agent-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .bf-user-agent-name {
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .bf-user-agent-count {
            color: #666;
            font-size: 0.9em;
        }
        
        .bf-dashboard-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .bf-charts-section,
            .bf-performance-details {
                grid-template-columns: 1fr;
            }
            
            .bf-overview-cards {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        </style>
        <?php
    }
    
    /**
     * Get daily stats for chart
     */
    private function get_daily_stats_for_chart() {
        $metrics = get_option('bf_performance_metrics', array());
        $daily_stats = $metrics['daily_stats'] ?? array();
        
        $labels = array();
        $data = array();
        
        // Get last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $labels[] = gmdate('M j', strtotime($date));
            $data[] = $daily_stats[$date]['requests'] ?? 0;
        }
        
        return array(
            'labels' => $labels,
            'data' => $data
        );
    }
    
    /**
     * Calculate optimization score
     */
    private function calculate_optimization_score($metrics) {
        $score = 0;
        
        // Compression enabled (25 points)
        $performance_options = get_option('bf_performance_options', array());
        if (!empty($performance_options['enable_gzip'])) {
            $score += 25;
        }
        
        // Headers enabled (25 points)
        if (!empty($performance_options['enable_etag'])) {
            $score += 25;
        }
        
        // Conditional requests enabled (25 points)
        if (!empty($performance_options['enable_conditional_requests'])) {
            $score += 25;
        }
        
        // Good compression ratio (25 points)
        $compression_ratio = $this->calculate_compression_ratio($metrics);
        if ($compression_ratio > 70) {
            $score += 25;
        } elseif ($compression_ratio > 50) {
            $score += 15;
        } elseif ($compression_ratio > 30) {
            $score += 10;
        }
        
        return $score;
    }
    
    /**
     * Calculate compression ratio
     */
    private function calculate_compression_ratio($metrics) {
        $uncompressed = $metrics['uncompressed_size'] ?? 0;
        $compressed = $metrics['compressed_size'] ?? 0;
        
        if ($uncompressed > 0) {
            return round((($uncompressed - $compressed) / $uncompressed) * 100, 1);
        }
        
        return 0;
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
     * Format user agent
     */
    private function format_user_agent($user_agent) {
        // Extract readable name from user agent
        if (strpos($user_agent, 'FeedBurner') !== false) {
            return 'FeedBurner';
        } elseif (strpos($user_agent, 'AppleCoreMedia') !== false) {
            return 'Apple Podcasts';
        } elseif (strpos($user_agent, 'Spotify') !== false) {
            return 'Spotify';
        } elseif (strpos($user_agent, 'Googlebot') !== false) {
            return 'Google Bot';
        } elseif (strpos($user_agent, 'Mozilla') !== false) {
            return 'Web Browser';
        } else {
            return substr($user_agent, 0, 30) . (strlen($user_agent) > 30 ? '...' : '');
        }
    }
    
    /**
     * AJAX get dashboard data
     */
    public function ajax_get_dashboard_data() {
        check_ajax_referer('bf_dashboard_nonce', 'nonce');
        
        $metrics = get_option('bf_performance_metrics', array());
        
        wp_send_json_success($metrics);
    }
    
    /**
     * AJAX reset metrics
     */
    public function ajax_reset_metrics() {
        check_ajax_referer('bf_reset_metrics', 'nonce');
        
        $this->init_metrics();
        
        wp_send_json_success(array('message' => esc_html__('Metrics reset successfully.', 'betterfeed')));
    }
}
