<?php
/**
 * Feed Validation and Monitoring Class
 *
 * @package BetterFeed
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feed validator class
 */
class BF_Validator {
    
    /**
     * Class instance
     * 
     * @var BF_Validator
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return BF_Validator
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
        // Validate feeds on publish
        add_action('publish_post', array($this, 'validate_feeds_on_publish'));
        
        // Schedule regular feed validation
        if (!wp_next_scheduled('bf_validate_feeds')) {
            wp_schedule_event(time(), 'hourly', 'bf_validate_feeds');
        }
        
        add_action('bf_validate_feeds', array($this, 'scheduled_feed_validation'));
    }
    
    /**
     * Validate feed content
     * 
     * @param string $feed_url Feed URL to validate
     * @param string $feed_type Feed type (rss2, atom, json)
     * @return array Validation result
     */
    public function validate_feed($feed_url, $feed_type = 'rss2') {
        $start_time = microtime(true);
        
        // Fetch feed content
        $response = wp_remote_get($feed_url, array(
            'timeout' => 15,
            'user-agent' => 'SMFB Feed Validator/' . BF_VERSION
        ));
        
        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'errors' => array('Failed to fetch feed: ' . $response->get_error_message()),
                'warnings' => array(),
                'info' => array(),
                'performance' => array(
                    'load_time' => microtime(true) - $start_time,
                    'size' => 0
                )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $content = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        $load_time = microtime(true) - $start_time;
        $content_size = strlen($content);
        
        $result = array(
            'valid' => false,
            'errors' => array(),
            'warnings' => array(),
            'info' => array(),
            'performance' => array(
                'load_time' => $load_time,
                'size' => $content_size,
                'response_code' => $response_code
            )
        );
        
        // Check response code
        if ($response_code !== 200) {
            $result['errors'][] = "HTTP {$response_code} response code";
            return $result;
        }
        
        // Validate content type
        $content_type = $headers['content-type'] ?? '';
        $this->validate_content_type($content_type, $feed_type, $result);
        
        // Validate XML structure for RSS/Atom
        if (in_array($feed_type, array('rss2', 'atom'))) {
            $this->validate_xml_structure($content, $feed_type, $result);
        } elseif ($feed_type === 'json') {
            $this->validate_json_structure($content, $result);
        }
        
        // Check for required elements
        $this->validate_required_elements($content, $feed_type, $result);
        
        // Performance checks
        $this->validate_performance($result);
        
        // Security checks
        $this->validate_security($content, $headers, $result);
        
        // Accessibility checks
        $this->validate_accessibility($content, $feed_type, $result);
        
        $result['valid'] = empty($result['errors']);
        
        return $result;
    }
    
    /**
     * Validate content type
     */
    private function validate_content_type($content_type, $feed_type, &$result) {
        $expected_types = array(
            'rss2' => array('application/rss+xml', 'application/xml', 'text/xml'),
            'atom' => array('application/atom+xml', 'application/xml', 'text/xml'),
            'json' => array('application/json', 'application/feed+json')
        );
        
        if (isset($expected_types[$feed_type])) {
            $found_match = false;
            foreach ($expected_types[$feed_type] as $expected) {
                if (strpos(strtolower($content_type), $expected) !== false) {
                    $found_match = true;
                    break;
                }
            }
            
            if (!$found_match) {
                $result['warnings'][] = "Unexpected content-type: {$content_type}";
            }
        }
    }
    
    /**
     * Validate XML structure
     */
    private function validate_xml_structure($content, $feed_type, &$result) {
        // Load XML
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $xml = simplexml_load_string($content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $result['errors'][] = "XML Error: " . trim($error->message);
            }
            return;
        }
        
        // Validate RSS2
        if ($feed_type === 'rss2') {
            $this->validate_rss2_structure($xml, $result);
        }
        
        // Validate Atom
        if ($feed_type === 'atom') {
            $this->validate_atom_structure($xml, $result);
        }
    }
    
    /**
     * Validate RSS2 structure
     */
    private function validate_rss2_structure($xml, &$result) {
        // Check root element
        if ($xml->getName() !== 'rss') {
            $result['errors'][] = 'RSS feed must have <rss> as root element';
            return;
        }
        
        // Check version
        $version = (string) $xml['version'];
        if ($version !== '2.0') {
            $result['warnings'][] = "RSS version is {$version}, should be 2.0";
        }
        
        // Check channel
        if (!isset($xml->channel)) {
            $result['errors'][] = 'RSS feed must have <channel> element';
            return;
        }
        
        $channel = $xml->channel;
        
        // Required elements
        $required = array('title', 'link', 'description');
        foreach ($required as $element) {
            if (!isset($channel->$element)) {
                $result['errors'][] = "Missing required element: <{$element}>";
            }
        }
        
        // Check for items
        if (!isset($channel->item)) {
            $result['warnings'][] = 'No items found in feed';
        } else {
            $item_count = count($channel->item);
            $result['info'][] = "Feed contains {$item_count} items";
            
            // Validate items
            foreach ($channel->item as $item) {
                $this->validate_rss2_item($item, $result);
            }
        }
        
        // Check for namespaces
        $this->check_namespaces($xml, $result);
    }
    
    /**
     * Validate RSS2 item
     */
    private function validate_rss2_item($item, &$result) {
        // Either title or description is required
        if (!isset($item->title) && !isset($item->description)) {
            $result['errors'][] = 'RSS item must have either title or description';
        }
        
        // Check GUID
        if (isset($item->guid)) {
            $permalink = (string) $item->guid['isPermaLink'];
            if ($permalink === 'true' && !filter_var((string) $item->guid, FILTER_VALIDATE_URL)) {
                $result['warnings'][] = 'GUID marked as permalink but is not a valid URL';
            }
        }
        
        // Check enclosures
        if (isset($item->enclosure)) {
            foreach ($item->enclosure as $enclosure) {
                $url = (string) $enclosure['url'];
                $length = (string) $enclosure['length'];
                $type = (string) $enclosure['type'];
                
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $result['warnings'][] = 'Enclosure URL is not valid';
                }
                
                if ($length === '0') {
                    $result['warnings'][] = 'Enclosure length is 0 (common podcast issue)';
                }
                
                if (empty($type)) {
                    $result['warnings'][] = 'Enclosure missing MIME type';
                }
            }
        }
    }
    
    /**
     * Validate Atom structure
     */
    private function validate_atom_structure($xml, &$result) {
        // Register Atom namespace
        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        
        // Check root element
        if ($xml->getName() !== 'feed') {
            $result['errors'][] = 'Atom feed must have <feed> as root element';
            return;
        }
        
        // Required elements
        $required = array('id', 'title', 'updated');
        foreach ($required as $element) {
            if (!isset($xml->$element)) {
                $result['errors'][] = "Missing required Atom element: <{$element}>";
            }
        }
        
        // Check entries
        if (!isset($xml->entry)) {
            $result['warnings'][] = 'No entries found in Atom feed';
        } else {
            $entry_count = count($xml->entry);
            $result['info'][] = "Feed contains {$entry_count} entries";
        }
    }
    
    /**
     * Validate JSON structure
     */
    private function validate_json_structure($content, &$result) {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['errors'][] = 'Invalid JSON: ' . json_last_error_msg();
            return;
        }
        
        // JSON Feed validation
        if (!isset($data['version'])) {
            $result['errors'][] = 'JSON Feed missing version';
        }
        
        if (!isset($data['title'])) {
            $result['errors'][] = 'JSON Feed missing title';
        }
        
        if (isset($data['items'])) {
            $item_count = count($data['items']);
            $result['info'][] = "Feed contains {$item_count} items";
        } else {
            $result['warnings'][] = 'No items found in JSON Feed';
        }
    }
    
    /**
     * Validate required elements
     */
    private function validate_required_elements($content, $feed_type, &$result) {
        // Check for featured images
        if (get_option('bf_include_featured_images', true)) {
            if (strpos($content, '<enclosure') === false && 
                strpos($content, 'media:content') === false) {
                $result['warnings'][] = 'No media enclosures found despite featured images being enabled';
            }
        }
        
        // Check for WebSub links
        if (get_option('bf_enable_websub', false)) {
            if (strpos($content, 'rel="hub"') === false) {
                $result['warnings'][] = 'WebSub enabled but no hub links found';
            }
        }
    }
    
    /**
     * Check namespaces
     */
    private function check_namespaces($xml, &$result) {
        $namespaces = $xml->getNamespaces(true);
        
        $expected_namespaces = array(
            'content' => 'http://purl.org/rss/1.0/modules/content/',
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'atom' => 'http://www.w3.org/2005/Atom'
        );
        
        foreach ($expected_namespaces as $prefix => $uri) {
            if (!isset($namespaces[$prefix])) {
                $result['info'][] = "Consider adding {$prefix} namespace for enhanced functionality";
            }
        }
    }
    
    /**
     * Validate performance
     */
    private function validate_performance(&$result) {
        $load_time = $result['performance']['load_time'];
        $size = $result['performance']['size'];
        
        // Load time checks
        if ($load_time > 5.0) {
            $result['warnings'][] = sprintf('Slow feed load time: %.2f seconds', $load_time);
        } elseif ($load_time > 2.0) {
            $result['info'][] = sprintf('Feed load time: %.2f seconds (could be improved)', $load_time);
        }
        
        // Size checks
        $size_mb = $size / 1024 / 1024;
        if ($size_mb > 1) {
            $result['warnings'][] = sprintf('Large feed size: %.2f MB', $size_mb);
        }
        
        $result['performance']['size_mb'] = $size_mb;
    }
    
    /**
     * Validate security
     */
    private function validate_security($content, $headers, &$result) {
        // Check for security headers
        $security_headers = array(
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY'
        );
        
        foreach ($security_headers as $header => $expected_value) {
            if (!isset($headers[strtolower($header)])) {
                $result['info'][] = "Consider adding {$header} security header";
            }
        }
        
        // Check for HTTPS
        if (strpos($content, 'http://') !== false) {
            $result['warnings'][] = 'Feed contains non-HTTPS URLs';
        }
    }
    
    /**
     * Validate accessibility
     */
    private function validate_accessibility($content, $feed_type, &$result) {
        // Check for feed validation
        if (in_array($feed_type, array('rss2', 'atom'))) {
            if (strpos($content, '<?xml-stylesheet') === false) {
                $result['info'][] = 'Feed validation completed';
            }
        }
        
        // Check for proper encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $result['warnings'][] = 'Feed content may have encoding issues';
        }
    }
    
    /**
     * Validate feeds on publish
     */
    public function validate_feeds_on_publish($post_id) {
        if (!get_option('bf_validate_on_publish', false)) {
            return;
        }
        
        // Validate main feeds
        $feeds = array(
            'rss2' => get_feed_link('rss2'),
            'atom' => get_feed_link('atom')
        );
        
        foreach ($feeds as $type => $url) {
            $result = $this->validate_feed($url, $type);
            
            // Store validation result
            update_post_meta($post_id, "bf_validation_{$type}", $result);
            
            // Log errors
            if (!$result['valid']) {
                // Validation: {$type} feed has errors: " . implode(', ', $result['errors'])
            }
        }
    }
    
    /**
     * Scheduled feed validation
     */
    public function scheduled_feed_validation() {
        if (!get_option('bf_enable_monitoring', false)) {
            return;
        }
        
        $feeds = array(
            'rss2' => get_feed_link('rss2'),
            'atom' => get_feed_link('atom')
        );
        
        if (get_option('bf_enable_json_feed', true)) {
            $feeds['json'] = home_url('/feed/json/');
        }
        
        $results = array();
        
        foreach ($feeds as $type => $url) {
            $result = $this->validate_feed($url, $type);
            $results[$type] = $result;
            
            // Alert on errors
            if (!$result['valid'] && get_option('bf_alert_on_errors', false)) {
                $this->send_error_alert($type, $result);
            }
        }
        
        // Store validation history
        update_option('bf_last_validation', array(
            'timestamp' => current_time('mysql'),
            'results' => $results
        ));
    }
    
    /**
     * Send error alert
     */
    private function send_error_alert($feed_type, $result) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = "[{$site_name}] Feed Validation Error - {$feed_type}";
        
        $message = "Feed validation failed for {$feed_type} feed:\n\n";
        $message .= "Errors:\n" . implode("\n", $result['errors']) . "\n\n";
        
        if (!empty($result['warnings'])) {
            $message .= "Warnings:\n" . implode("\n", $result['warnings']) . "\n\n";
        }
        
        $message .= "Feed URL: " . get_feed_link($feed_type) . "\n";
        $message .= "Validation Time: " . current_time('mysql') . "\n";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get validation history
     * 
     * @param int $limit Number of validation results to return
     * @return array Validation history
     */
    public function get_validation_history($limit = 10) {
        $history = get_option('bf_validation_history', array());
        return array_slice($history, -$limit);
    }
    
    /**
     * Test specific feed URL
     * 
     * @param string $feed_url Feed URL
     * @param string $feed_type Feed type
     * @return array Test result
     */
    public function test_feed_url($feed_url, $feed_type = 'rss2') {
        return $this->validate_feed($feed_url, $feed_type);
    }
}