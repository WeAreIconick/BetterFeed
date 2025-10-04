<?php
/**
 * Optimization Suggestions
 *
 * Automated recommendations for feed optimization
 *
 * @package BetterFeed
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimizer Suggestions class
 */
class BF_Optimizer_Suggestions {
    
    /**
     * Class instance
     * 
     * @var BF_Optimizer_Suggestions
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Optimizer_Suggestions
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
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_bf_run_optimization_scan', array($this, 'ajax_run_optimization_scan'));
        add_action('wp_ajax_bf_apply_suggestion', array($this, 'ajax_apply_suggestion'));
        
        // Run suggestions on admin init
        add_action('admin_init', array($this, 'run_optimization_check'));
    }
    
    /**
     * Run optimization check
     */
    public function run_optimization_check() {
        // Only run on BetterFeed admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'bf-') !== 0) {
            return;
        }
        
        $suggestions = $this->generate_suggestions();
        if (!empty($suggestions)) {
            $this->store_suggestions($suggestions);
        }
    }
    
    /**
     * Generate optimization suggestions
     */
    public function generate_suggestions() {
        $suggestions = array();
        
        // Check performance settings
        $performance_options = get_option('bf_performance_options', array());
        
        // Check if caching is enabled
        if (empty($performance_options['enable_caching'])) {
            $suggestions[] = array(
                'id' => 'enable_caching',
                'type' => 'performance',
                'priority' => 'high',
                'title' => esc_html__('Enable Feed Caching', 'betterfeed'),
                'description' => esc_html__('Caching can significantly improve feed performance and reduce server load.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_caching]',
                'value' => '1',
                'impact' => esc_html__('Reduces server load and improves response times', 'betterfeed')
            );
        }
        
        // Check if GZIP compression is enabled
        if (empty($performance_options['enable_gzip'])) {
            $suggestions[] = array(
                'id' => 'enable_gzip',
                'type' => 'performance',
                'priority' => 'high',
                'title' => esc_html__('Enable GZIP Compression', 'betterfeed'),
                'description' => esc_html__('GZIP compression can reduce feed size by 70-80%, saving bandwidth and improving load times.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_gzip]',
                'value' => '1',
                'impact' => esc_html__('Reduces bandwidth usage by 70-80%', 'betterfeed')
            );
        }
        
        // Check if ETag support is enabled
        if (empty($performance_options['enable_etag'])) {
            $suggestions[] = array(
                'id' => 'enable_etag',
                'type' => 'performance',
                'priority' => 'medium',
                'title' => esc_html__('Enable ETag Headers', 'betterfeed'),
                'description' => esc_html__('ETag headers help feed readers determine if content has changed, reducing unnecessary downloads.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_etag]',
                'value' => '1',
                'impact' => esc_html__('Improves caching efficiency', 'betterfeed')
            );
        }
        
        // Check if conditional requests are enabled
        if (empty($performance_options['enable_conditional_requests'])) {
            $suggestions[] = array(
                'id' => 'enable_conditional_requests',
                'type' => 'performance',
                'priority' => 'medium',
                'title' => esc_html__('Enable 304 Not Modified Responses', 'betterfeed'),
                'description' => esc_html__('304 responses tell feed readers when content hasn\'t changed, saving bandwidth.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_conditional_requests]',
                'value' => '1',
                'impact' => esc_html__('Reduces bandwidth for unchanged content', 'betterfeed')
            );
        }
        
        // Check feed item count
        $posts_per_rss = get_option('posts_per_rss', 10);
        if ($posts_per_rss > 20) {
            $suggestions[] = array(
                'id' => 'reduce_feed_items',
                'type' => 'performance',
                'priority' => 'medium',
                'title' => esc_html__('Reduce Feed Item Count', 'betterfeed'),
                'description' => sprintf(esc_html__('Your feed currently shows %d items. Consider reducing to 10-15 items for better performance.', 'betterfeed'), $posts_per_rss),
                'action' => 'update_option',
                'setting' => 'posts_per_rss',
                'value' => '10',
                'impact' => esc_html__('Reduces feed size and load time', 'betterfeed')
            );
        }
        
        // Check for large images
        $large_images = $this->check_large_images();
        if (!empty($large_images)) {
            $suggestions[] = array(
                'id' => 'optimize_images',
                'type' => 'content',
                'priority' => 'medium',
                'title' => esc_html__('Optimize Large Images', 'betterfeed'),
                'description' => sprintf(esc_html__('Found %d posts with large featured images that may slow down feed loading.', 'betterfeed'), count($large_images)),
                'action' => 'show_details',
                'details' => $large_images,
                'impact' => esc_html__('Improves feed load time', 'betterfeed')
            );
        }
        
        // Check for broken enclosures
        $broken_enclosures = $this->check_broken_enclosures();
        if (!empty($broken_enclosures)) {
            $suggestions[] = array(
                'id' => 'fix_enclosures',
                'type' => 'content',
                'priority' => 'high',
                'title' => esc_html__('Fix Broken Enclosures', 'betterfeed'),
                'description' => sprintf(esc_html__('Found %d posts with broken or missing enclosures.', 'betterfeed'), count($broken_enclosures)),
                'action' => 'show_details',
                'details' => $broken_enclosures,
                'impact' => esc_html__('Ensures podcast compatibility', 'betterfeed')
            );
        }
        
        // Check for missing featured images
        $missing_images = $this->check_missing_featured_images();
        if (!empty($missing_images)) {
            $suggestions[] = array(
                'id' => 'add_featured_images',
                'type' => 'content',
                'priority' => 'low',
                'title' => esc_html__('Add Featured Images', 'betterfeed'),
                'description' => sprintf(esc_html__('Found %d posts without featured images. Featured images improve feed appearance.', 'betterfeed'), count($missing_images)),
                'action' => 'show_details',
                'details' => $missing_images,
                'impact' => esc_html__('Improves visual appeal', 'betterfeed')
            );
        }
        
        // Check for JSON Feed support
        if (empty($performance_options['enable_json_feed'])) {
            $suggestions[] = array(
                'id' => 'enable_json_feed',
                'type' => 'modern',
                'priority' => 'low',
                'title' => esc_html__('Enable JSON Feed Support', 'betterfeed'),
                'description' => esc_html__('JSON Feed is a modern feed format that\'s easier for developers to work with.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_json_feed]',
                'value' => '1',
                'impact' => esc_html__('Modern feed format support', 'betterfeed')
            );
        }
        
        // Check for Google Discover optimization
        if (empty($performance_options['enable_google_discover'])) {
            $suggestions[] = array(
                'id' => 'enable_google_discover',
                'type' => 'seo',
                'priority' => 'medium',
                'title' => esc_html__('Enable Google Discover Optimization', 'betterfeed'),
                'description' => esc_html__('Optimize your feeds for Google Discover with proper schema markup and large images.', 'betterfeed'),
                'action' => 'enable_setting',
                'setting' => 'bf_performance_options[enable_google_discover]',
                'value' => '1',
                'impact' => esc_html__('Improves Google Discover visibility', 'betterfeed')
            );
        }
        
        return $suggestions;
    }
    
    /**
     * Check for large images
     */
    private function check_large_images() {
        $posts = get_posts(array(
            'numberposts' => 50,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $large_images = array();
        
        foreach ($posts as $post) {
            $featured_image_id = get_post_thumbnail_id($post->ID);
            if ($featured_image_id) {
                $image_data = wp_get_attachment_image_src($featured_image_id, 'full');
                if ($image_data && $image_data[1] > 2000) { // Width > 2000px
                    $large_images[] = array(
                        'id' => $post->ID,
                        'title' => get_the_title($post->ID),
                        'url' => get_edit_post_link($post->ID),
                        'image_size' => $image_data[1] . 'x' . $image_data[2],
                        'file_size' => $this->format_bytes(filesize(get_attached_file($featured_image_id)))
                    );
                }
            }
        }
        
        return $large_images;
    }
    
    /**
     * Check for broken enclosures
     */
    private function check_broken_enclosures() {
        $posts = get_posts(array(
            'numberposts' => 100,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'episode_audio_url',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $broken_enclosures = array();
        
        foreach ($posts as $post) {
            $audio_url = get_post_meta($post->ID, 'episode_audio_url', true);
            if ($audio_url) {
                // Check if it's a valid URL
                if (filter_var($audio_url, FILTER_VALIDATE_URL)) {
                    $response = wp_remote_head($audio_url, array('timeout' => 10));
                    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                        $broken_enclosures[] = array(
                            'id' => $post->ID,
                            'title' => get_the_title($post->ID),
                            'url' => get_edit_post_link($post->ID),
                            'audio_url' => $audio_url,
                            'status' => is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response)
                        );
                    }
                } else {
                    $broken_enclosures[] = array(
                        'id' => $post->ID,
                        'title' => get_the_title($post->ID),
                        'url' => get_edit_post_link($post->ID),
                        'audio_url' => $audio_url,
                        'status' => esc_html__('Invalid URL format', 'betterfeed')
                    );
                }
            }
        }
        
        return $broken_enclosures;
    }
    
    /**
     * Check for missing featured images
     */
    private function check_missing_featured_images() {
        $posts = get_posts(array(
            'numberposts' => 50,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        $missing_images = array();
        
        foreach ($posts as $post) {
            $missing_images[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'url' => get_edit_post_link($post->ID),
                'date' => get_the_date('Y-m-d', $post->ID)
            );
        }
        
        return $missing_images;
    }
    
    /**
     * Store suggestions
     */
    private function store_suggestions($suggestions) {
        update_option('bf_optimization_suggestions', $suggestions);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            esc_html__('Optimization Suggestions', 'betterfeed'),
            esc_html__('Optimization Suggestions', 'betterfeed'),
            'manage_options',
            'bf-optimization-suggestions',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $suggestions = get_option('bf_optimization_suggestions', array());
        
        // Group suggestions by priority
        $high_priority = array_filter($suggestions, function($s) { return $s['priority'] === 'high'; });
        $medium_priority = array_filter($suggestions, function($s) { return $s['priority'] === 'medium'; });
        $low_priority = array_filter($suggestions, function($s) { return $s['priority'] === 'low'; });
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BetterFeed Optimization Suggestions', 'betterfeed'); ?></h1>
            
            <div class="bf-suggestions">
                <!-- Actions -->
                <div class="bf-suggestions-actions">
                    <button type="button" class="button button-primary" id="bf-run-scan">
                        <?php esc_html_e('Run New Scan', 'betterfeed'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="bf-apply-all">
                        <?php esc_html_e('Apply All High Priority', 'betterfeed'); ?>
                    </button>
                </div>
                
                <!-- Summary -->
                <div class="bf-suggestions-summary">
                    <div class="bf-summary-card high">
                        <div class="bf-summary-icon">🔴</div>
                        <div class="bf-summary-content">
                            <h3><?php echo esc_html(count($high_priority)); ?></h3>
                            <p><?php esc_html_e('High Priority', 'betterfeed'); ?></p>
                        </div>
                    </div>
                    
                    <div class="bf-summary-card medium">
                        <div class="bf-summary-icon">🟡</div>
                        <div class="bf-summary-content">
                            <h3><?php echo esc_html(count($medium_priority)); ?></h3>
                            <p><?php esc_html_e('Medium Priority', 'betterfeed'); ?></p>
                        </div>
                    </div>
                    
                    <div class="bf-summary-card low">
                        <div class="bf-summary-icon">🟢</div>
                        <div class="bf-summary-content">
                            <h3><?php echo esc_html(count($low_priority)); ?></h3>
                            <p><?php esc_html_e('Low Priority', 'betterfeed'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- High Priority Suggestions -->
                <?php if (!empty($high_priority)): ?>
                <div class="bf-suggestions-section">
                    <h2><?php esc_html_e('High Priority Recommendations', 'betterfeed'); ?></h2>
                    <?php foreach ($high_priority as $suggestion): ?>
                        <?php $this->render_suggestion($suggestion); ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Medium Priority Suggestions -->
                <?php if (!empty($medium_priority)): ?>
                <div class="bf-suggestions-section">
                    <h2><?php esc_html_e('Medium Priority Recommendations', 'betterfeed'); ?></h2>
                    <?php foreach ($medium_priority as $suggestion): ?>
                        <?php $this->render_suggestion($suggestion); ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Low Priority Suggestions -->
                <?php if (!empty($low_priority)): ?>
                <div class="bf-suggestions-section">
                    <h2><?php esc_html_e('Low Priority Recommendations', 'betterfeed'); ?></h2>
                    <?php foreach ($low_priority as $suggestion): ?>
                        <?php $this->render_suggestion($suggestion); ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (empty($suggestions)): ?>
                <div class="bf-no-suggestions">
                    <h2><?php esc_html_e('🎉 Great Job!', 'betterfeed'); ?></h2>
                    <p><?php esc_html_e('No optimization suggestions at this time. Your feeds are well optimized!', 'betterfeed'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#bf-run-scan').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php esc_js(__('Scanning...', 'betterfeed')); ?>');
                
                $.post(ajaxurl, {
                    action: 'bf_run_optimization_scan',
                    nonce: '<?php echo esc_js(wp_create_nonce('bf_run_optimization_scan')); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php esc_js(__('Scan failed. Please try again.', 'betterfeed')); ?>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php esc_js(__('Run New Scan', 'betterfeed')); ?>');
                });
            });
            
            $('#bf-apply-all').on('click', function() {
                if (confirm('<?php esc_js(__('Apply all high priority suggestions? This will modify your settings.', 'betterfeed')); ?>')) {
                    $('.bf-suggestion-item[data-priority="high"] .bf-apply-btn').each(function() {
                        if (!$(this).prop('disabled')) {
                            $(this).click();
                        }
                    });
                }
            });
            
            $('.bf-apply-btn').on('click', function() {
                var button = $(this);
                var suggestionId = button.data('suggestion-id');
                
                button.prop('disabled', true).text('<?php esc_js(__('Applying...', 'betterfeed')); ?>');
                
                $.post(ajaxurl, {
                    action: 'bf_apply_suggestion',
                    suggestion_id: suggestionId,
                    nonce: '<?php echo esc_js(wp_create_nonce('bf_apply_suggestion')); ?>'
                }, function(response) {
                    if (response.success) {
                        button.text('<?php esc_js(__('Applied', 'betterfeed')); ?>').addClass('applied');
                        setTimeout(function() {
                            button.closest('.bf-suggestion-item').fadeOut();
                        }, 1000);
                    } else {
                        alert('<?php esc_js(__('Failed to apply suggestion.', 'betterfeed')); ?>');
                        button.prop('disabled', false).text('<?php esc_js(__('Apply', 'betterfeed')); ?>');
                    }
                });
            });
        });
        </script>
        
        <style>
        .bf-suggestions {
            margin-top: 20px;
        }
        
        .bf-suggestions-actions {
            margin-bottom: 20px;
        }
        
        .bf-suggestions-actions .button {
            margin-right: 10px;
        }
        
        .bf-suggestions-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .bf-summary-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bf-summary-card.high {
            border-left: 4px solid #d63638;
        }
        
        .bf-summary-card.medium {
            border-left: 4px solid #ff6900;
        }
        
        .bf-summary-card.low {
            border-left: 4px solid #00a32a;
        }
        
        .bf-summary-icon {
            font-size: 2em;
            margin-right: 15px;
        }
        
        .bf-summary-content h3 {
            margin: 0;
            font-size: 2em;
            color: #0073aa;
        }
        
        .bf-summary-content p {
            margin: 5px 0 0 0;
            color: #666;
        }
        
        .bf-suggestions-section {
            margin-bottom: 30px;
        }
        
        .bf-suggestions-section h2 {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        
        .bf-suggestion-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bf-suggestion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .bf-suggestion-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #0073aa;
            margin: 0;
        }
        
        .bf-suggestion-type {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .bf-suggestion-description {
            color: #666;
            margin-bottom: 10px;
        }
        
        .bf-suggestion-impact {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-style: italic;
        }
        
        .bf-suggestion-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .bf-apply-btn {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .bf-apply-btn:hover {
            background: #005a87;
        }
        
        .bf-apply-btn:disabled,
        .bf-apply-btn.applied {
            background: #666;
            cursor: not-allowed;
        }
        
        .bf-suggestion-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .bf-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .bf-detail-item:last-child {
            border-bottom: none;
        }
        
        .bf-detail-label {
            font-weight: 500;
        }
        
        .bf-detail-value {
            color: #0073aa;
        }
        
        .bf-no-suggestions {
            text-align: center;
            padding: 40px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .bf-no-suggestions h2 {
            color: #00a32a;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .bf-suggestions-summary {
                grid-template-columns: 1fr;
            }
            
            .bf-suggestion-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .bf-suggestion-type {
                margin-top: 10px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render suggestion
     */
    private function render_suggestion($suggestion) {
        ?>
        <div class="bf-suggestion-item" data-priority="<?php echo esc_attr($suggestion['priority']); ?>">
            <div class="bf-suggestion-header">
                <h3 class="bf-suggestion-title"><?php echo esc_html($suggestion['title']); ?></h3>
                <span class="bf-suggestion-type bf-type-<?php echo esc_attr($suggestion['type']); ?>">
                    <?php echo esc_html($suggestion['type']); ?>
                </span>
            </div>
            
            <div class="bf-suggestion-description">
                <?php echo esc_html($suggestion['description']); ?>
            </div>
            
            <?php if (!empty($suggestion['impact'])): ?>
            <div class="bf-suggestion-impact">
                <strong><?php esc_html_e('Impact:', 'betterfeed'); ?></strong> <?php echo esc_html($suggestion['impact']); ?>
            </div>
            <?php endif; ?>
            
            <div class="bf-suggestion-actions">
                <?php if ($suggestion['action'] === 'enable_setting' || $suggestion['action'] === 'update_option'): ?>
                    <button type="button" class="bf-apply-btn" data-suggestion-id="<?php echo esc_attr($suggestion['id']); ?>">
                        <?php esc_html_e('Apply', 'betterfeed'); ?>
                    </button>
                <?php endif; ?>
                
                <?php if ($suggestion['action'] === 'show_details' && !empty($suggestion['details'])): ?>
                    <button type="button" class="button button-secondary bf-show-details" data-target="details-<?php echo esc_attr($suggestion['id']); ?>">
                        <?php esc_html_e('View Details', 'betterfeed'); ?>
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if ($suggestion['action'] === 'show_details' && !empty($suggestion['details'])): ?>
            <div class="bf-suggestion-details" id="details-<?php echo esc_attr($suggestion['id']); ?>" style="display: none;">
                <h4><?php esc_html_e('Affected Items:', 'betterfeed'); ?></h4>
                <?php foreach (array_slice($suggestion['details'], 0, 10) as $detail): ?>
                    <div class="bf-detail-item">
                        <span class="bf-detail-label">
                            <a href="<?php echo esc_url($detail['url']); ?>" target="_blank"><?php echo esc_html($detail['title']); ?></a>
                        </span>
                        <span class="bf-detail-value">
                            <?php if (isset($detail['status'])): ?>
                                <?php echo esc_html($detail['status']); ?>
                            <?php elseif (isset($detail['image_size'])): ?>
                                <?php echo esc_html($detail['image_size']); ?>
                            <?php elseif (isset($detail['date'])): ?>
                                <?php echo esc_html($detail['date']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($suggestion['details']) > 10): ?>
                    <p><em><?php printf(esc_html__('... and %d more items', 'betterfeed'), count($suggestion['details']) - 10); ?></em></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
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
     * AJAX run optimization scan
     */
    public function ajax_run_optimization_scan() {
        check_ajax_referer('bf_run_optimization_scan', 'nonce');
        
        $suggestions = $this->generate_suggestions();
        $this->store_suggestions($suggestions);
        
        wp_send_json_success(array(
            'message' => esc_html__('Optimization scan completed.', 'betterfeed'),
            'suggestions_count' => count($suggestions)
        ));
    }
    
    /**
     * AJAX apply suggestion
     */
    public function ajax_apply_suggestion() {
        check_ajax_referer('bf_apply_suggestion', 'nonce');
        
        $suggestion_id = sanitize_text_field($_POST['suggestion_id']);
        $suggestions = get_option('bf_optimization_suggestions', array());
        
        foreach ($suggestions as $index => $suggestion) {
            if ($suggestion['id'] === $suggestion_id) {
                $success = false;
                
                if ($suggestion['action'] === 'enable_setting') {
                    $setting_parts = explode('[', str_replace(']', '', $suggestion['setting']));
                    $option_name = $setting_parts[0];
                    $setting_key = $setting_parts[1] ?? null;
                    
                    $option_value = get_option($option_name, array());
                    if ($setting_key) {
                        $option_value[$setting_key] = $suggestion['value'];
                    } else {
                        $option_value = $suggestion['value'];
                    }
                    
                    $success = update_option($option_name, $option_value);
                } elseif ($suggestion['action'] === 'update_option') {
                    $success = update_option($suggestion['setting'], $suggestion['value']);
                }
                
                if ($success) {
                    // Remove applied suggestion
                    unset($suggestions[$index]);
                    update_option('bf_optimization_suggestions', array_values($suggestions));
                    
                    wp_send_json_success(array('message' => esc_html__('Suggestion applied successfully.', 'betterfeed')));
                } else {
                    wp_send_json_error(array('message' => esc_html__('Failed to apply suggestion.', 'betterfeed')));
                }
                
                break;
            }
        }
        
        wp_send_json_error(array('message' => esc_html__('Suggestion not found.', 'betterfeed')));
    }
}
