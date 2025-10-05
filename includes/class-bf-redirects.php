<?php
/**
 * Feed Redirect Management
 *
 * Manages feed URL redirects with admin interface
 *
 * @package BetterFeed
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feed Redirects class
 */
class BF_Redirects {
    
    /**
     * Class instance
     * 
     * @var BF_Redirects
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Redirects
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
        
        // Handle redirects
        add_action('template_redirect', array($this, 'handle_redirects'), 1);
        
        // Admin hooks - now integrated into main BetterFeed settings
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_bf_save_redirect', array($this, 'save_redirect'));
        add_action('admin_post_bf_delete_redirect', array($this, 'delete_redirect'));
        add_action('admin_post_bf_test_redirect', array($this, 'test_redirect'));
        
        // Log redirects for analytics
        add_action('wp_ajax_bf_log_redirect', array($this, 'log_redirect_ajax'));
        add_action('wp_ajax_nopriv_bf_log_redirect', array($this, 'log_redirect_ajax'));
    }
    
    /**
     * Handle feed redirects
     */
    public function handle_redirects() {
        if (!is_feed()) {
            return;
        }
        
        $redirects = get_option('bf_feed_redirects', array());
        if (empty($redirects)) {
            return;
        }
        
        $current_url = $this->get_current_feed_url();
        if (!$current_url) {
            return;
        }
        
        foreach ($redirects as $redirect) {
            if (empty($redirect['enabled'])) {
                continue;
            }
            
            if ($this->url_matches($current_url, $redirect['from'])) {
                $this->perform_redirect($redirect);
                break;
            }
        }
    }
    
    /**
     * Get current feed URL
     */
    private function get_current_feed_url() {
        global $wp;
        
        $feed_type = get_query_var('feed');
        if (!$feed_type) {
            return null;
        }
        
        $url_parts = array();
        
        // Base feed URL
        if ($feed_type === 'rss2' || $feed_type === 'rss') {
            $url_parts[] = '/feed/';
        } elseif ($feed_type === 'atom') {
            $url_parts[] = '/feed/atom/';
        } elseif ($feed_type === 'rdf') {
            $url_parts[] = '/feed/rdf/';
        } else {
            $url_parts[] = '/feed/' . $feed_type . '/';
        }
        
        // Add query parameters
        $query_vars = $wp->query_vars;
        if (!empty($query_vars['category_name'])) {
            $url_parts[] = 'category/' . $query_vars['category_name'] . '/';
        }
        if (!empty($query_vars['tag'])) {
            $url_parts[] = 'tag/' . $query_vars['tag'] . '/';
        }
        if (!empty($query_vars['post_type']) && $query_vars['post_type'] !== 'post') {
            $url_parts[] = $query_vars['post_type'] . '/';
        }
        
        return home_url(implode('', $url_parts));
    }
    
    /**
     * Check if URL matches redirect pattern
     */
    private function url_matches($url, $pattern) {
        // Exact match
        if ($url === $pattern) {
            return true;
        }
        
        // Wildcard match
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
            return preg_match('/^' . $pattern . '$/', $url);
        }
        
        // Regex match (if pattern starts and ends with /)
        if (preg_match('/^\/.*\/$/', $pattern)) {
            return preg_match($pattern, $url);
        }
        
        return false;
    }
    
    /**
     * Perform redirect
     */
    private function perform_redirect($redirect) {
        $status_code = intval($redirect['status_code']);
        $to_url = $redirect['to'];
        
        // Log redirect for analytics
        $this->log_redirect($redirect, $this->get_current_feed_url());
        
        // Set redirect headers
        wp_redirect($to_url, $status_code);
        exit;
    }
    
    /**
     * Log redirect for analytics
     */
    private function log_redirect($redirect, $from_url) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'from_url' => $from_url,
            'to_url' => $redirect['to'],
            'status_code' => $redirect['status_code'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'ip_address' => $this->get_client_ip(),
            'redirect_id' => $redirect['id']
        );
        
        $logs = get_option('bf_redirect_logs', array());
        $logs[] = $log_entry;
        
        // Keep only last 1000 entries
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        update_option('bf_redirect_logs', $logs);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', sanitize_text_field(wp_unslash($_SERVER[$key]))) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            esc_html__('Feed Redirects', 'betterfeed'),
            esc_html__('Feed Redirects', 'betterfeed'),
            'manage_options',
            'bf-feed-redirects',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $redirects = get_option('bf_feed_redirects', array());
        
        if (isset($_POST['action']) && $_POST['action'] === 'add_redirect' && 
            isset($_POST['bf_redirect_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bf_redirect_nonce'])), 'bf_add_redirect')) {
            $this->handle_add_redirect();
            $redirects = get_option('bf_feed_redirects', array());
        }
        
        // Get redirect logs for analytics
        $logs = get_option('bf_redirect_logs', array());
        $recent_logs = array_slice(array_reverse($logs), 0, 20);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Feed Redirects', 'betterfeed'); ?></h1>
            
            <div class="bf-redirects-admin">
                <div class="bf-redirects-list">
                    <h2><?php esc_html_e('Active Redirects', 'betterfeed'); ?></h2>
                    
                    <?php if (empty($redirects)): ?>
                        <p><?php esc_html_e('No redirects configured yet.', 'betterfeed'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('From URL', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('To URL', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Status Code', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Status', 'betterfeed'); ?></th>
                                    <th><?php esc_html_e('Actions', 'betterfeed'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redirects as $index => $redirect): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($redirect['from']); ?></code></td>
                                        <td><a href="<?php echo esc_url($redirect['to']); ?>" target="_blank"><?php echo esc_html($redirect['to']); ?></a></td>
                                        <td><?php echo esc_html($redirect['status_code']); ?></td>
                                        <td><?php echo !empty($redirect['enabled']) ? '<span style="color: green;">✓ Active</span>' : '<span style="color: red;">✗ Disabled</span>'; ?></td>
                                        <td>
                                            <a href="?page=bf-feed-redirects&edit=<?php echo esc_attr($index); ?>" class="button button-small"><?php esc_html_e('Edit', 'betterfeed'); ?></a>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=bf_test_redirect&redirect_index=' . $index), 'bf_test_redirect')); ?>" class="button button-small"><?php esc_html_e('Test', 'betterfeed'); ?></a>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=bf_delete_redirect&redirect_index=' . $index), 'bf_delete_redirect')); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure?', 'betterfeed'); ?>')"><?php esc_html_e('Delete', 'betterfeed'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="bf-add-redirect">
                    <h2><?php esc_html_e('Add New Redirect', 'betterfeed'); ?></h2>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('bf_add_redirect', 'bf_redirect_nonce'); ?>
                        <input type="hidden" name="action" value="add_redirect">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('From URL Pattern', 'betterfeed'); ?></th>
                                <td>
                                    <input type="text" name="redirect_from" class="regular-text" placeholder="/feed/old-feed/" required>
                                    <p class="description">
                                        <?php esc_html_e('Source URL pattern. Use * for wildcards or /pattern/ for regex.', 'betterfeed'); ?><br>
                                        <?php esc_html_e('Examples:', 'betterfeed'); ?><br>
                                        • <code>/feed/old-feed/</code> - exact match<br>
                                        • <code>/feed/category/*/</code> - wildcard match<br>
                                        • <code>/\/feed\/tag\/.*\//</code> - regex match
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('To URL', 'betterfeed'); ?></th>
                                <td>
                                    <input type="url" name="redirect_to" class="regular-text" placeholder="https://example.com/feed/" required>
                                    <p class="description"><?php esc_html_e('Destination URL for the redirect', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Status Code', 'betterfeed'); ?></th>
                                <td>
                                    <select name="redirect_status_code">
                                        <option value="301">301 - Permanent Redirect</option>
                                        <option value="302">302 - Temporary Redirect</option>
                                        <option value="307">307 - Temporary Redirect (Preserve Method)</option>
                                        <option value="308">308 - Permanent Redirect (Preserve Method)</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('301: Permanent redirect (SEO friendly for FeedBurner migration)', 'betterfeed'); ?><br>
                                        <?php esc_html_e('302: Temporary redirect', 'betterfeed'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Description', 'betterfeed'); ?></th>
                                <td>
                                    <input type="text" name="redirect_description" class="regular-text" placeholder="Migrate from old feed to FeedBurner">
                                    <p class="description"><?php esc_html_e('Optional description for this redirect', 'betterfeed'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Redirect', 'betterfeed'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="redirect_enabled" value="1" checked>
                                        <?php esc_html_e('Enable this redirect', 'betterfeed'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(esc_html__('Create Redirect', 'betterfeed')); ?>
                    </form>
                </div>
                
                <?php if (!empty($recent_logs)): ?>
                <div class="bf-redirect-logs">
                    <h2><?php esc_html_e('Recent Redirect Activity', 'betterfeed'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'betterfeed'); ?></th>
                                <th><?php esc_html_e('From', 'betterfeed'); ?></th>
                                <th><?php esc_html_e('To', 'betterfeed'); ?></th>
                                <th><?php esc_html_e('Status', 'betterfeed'); ?></th>
                                <th><?php esc_html_e('IP', 'betterfeed'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html(gmdate('M j, Y H:i:s', strtotime($log['timestamp']))); ?></td>
                                    <td><code><?php echo esc_html($log['from_url']); ?></code></td>
                                    <td><?php echo esc_html($log['to_url']); ?></td>
                                    <td><?php echo esc_html($log['status_code']); ?></td>
                                    <td><?php echo esc_html($log['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .bf-redirects-admin {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .bf-redirects-list,
        .bf-redirect-logs {
            grid-column: 1 / -1;
        }
        .bf-add-redirect {
            border: 1px solid #ddd;
            padding: 20px;
            background: #f9f9f9;
        }
        .bf-redirect-logs {
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .bf-redirects-admin {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Handle add redirect
     */
    private function handle_add_redirect() {
        // Check if nonce exists and verify it
        if (!isset($_POST['bf_redirect_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bf_redirect_nonce'])), 'bf_add_redirect')) {
            wp_die(esc_html__('Security check failed.', 'betterfeed'));
        }
        
        $redirect_data = array(
            'id' => uniqid(),
            'from' => isset($_POST['redirect_from']) ? sanitize_text_field(wp_unslash($_POST['redirect_from'])) : '',
            'to' => isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '',
            'status_code' => isset($_POST['redirect_status_code']) ? intval($_POST['redirect_status_code']) : 301,
            'description' => isset($_POST['redirect_description']) ? sanitize_text_field(wp_unslash($_POST['redirect_description'])) : '',
            'enabled' => isset($_POST['redirect_enabled']),
            'created_at' => current_time('mysql')
        );
        
        $redirects = get_option('bf_feed_redirects', array());
        $redirects[] = $redirect_data;
        
        update_option('bf_feed_redirects', $redirects);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Redirect created successfully!', 'betterfeed') . '</p></div>';
        });
    }
    
    /**
     * Save redirect
     */
    public function save_redirect() {
        // Implementation for editing existing redirects
        wp_redirect(admin_url('options-general.php?page=bf-feed-redirects'));
        exit;
    }
    
    /**
     * Delete redirect
     */
    public function delete_redirect() {
        // Check if nonce exists and verify it
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'bf_delete_redirect')) {
            wp_die(esc_html__('Security check failed.', 'betterfeed'));
        }
        
        $redirect_index = isset($_GET['redirect_index']) ? intval($_GET['redirect_index']) : -1;
        $redirects = get_option('bf_feed_redirects', array());
        
        if (isset($redirects[$redirect_index])) {
            unset($redirects[$redirect_index]);
            $redirects = array_values($redirects); // Re-index array
            update_option('bf_feed_redirects', $redirects);
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Redirect deleted successfully!', 'betterfeed') . '</p></div>';
            });
        }
        
        wp_redirect(admin_url('options-general.php?page=bf-feed-redirects'));
        exit;
    }
    
    /**
     * Test redirect
     */
    public function test_redirect() {
        // Check if nonce exists and verify it
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'bf_test_redirect')) {
            wp_die(esc_html__('Security check failed.', 'betterfeed'));
        }
        
        $redirect_index = isset($_GET['redirect_index']) ? intval($_GET['redirect_index']) : -1;
        $redirects = get_option('bf_feed_redirects', array());
        
        if (isset($redirects[$redirect_index])) {
            $redirect = $redirects[$redirect_index];
            
            // Test URL matching
            $test_url = home_url('/feed/');
            $matches = $this->url_matches($test_url, $redirect['from']);
            
            $message = $matches 
                ? sprintf(
                    // translators: %1$s is the redirect pattern, %2$s is the test URL
                    esc_html__('Redirect pattern "%1$s" matches test URL "%2$s"', 'betterfeed'), 
                    $redirect['from'], 
                    $test_url
                )
                : sprintf(
                    // translators: %1$s is the redirect pattern, %2$s is the test URL
                    esc_html__('Redirect pattern "%1$s" does not match test URL "%2$s"', 'betterfeed'), 
                    $redirect['from'], 
                    $test_url
                );
            
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
        }
        
        wp_redirect(admin_url('options-general.php?page=bf-feed-redirects'));
        exit;
    }
    
    /**
     * Log redirect AJAX handler
     */
    public function log_redirect_ajax() {
        // Handle AJAX logging if needed
        wp_die();
    }
}
