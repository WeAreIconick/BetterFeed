<?php
/**
 * Test Custom Feeds Functionality
 * 
 * This script tests the custom feeds functionality to ensure
 * feeds are properly created and accessible.
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-config.php');

echo "🔍 Testing Custom Feeds Functionality\n";
echo "=====================================\n\n";

// Test 1: Check if custom feeds option exists
echo "1. Checking custom feeds option...\n";
$custom_feeds = get_option('bf_custom_feeds', array());
echo "   Current feeds: " . count($custom_feeds) . "\n";

if (empty($custom_feeds)) {
    echo "   ❌ No custom feeds found\n";
} else {
    foreach ($custom_feeds as $index => $feed) {
        echo "   Feed " . esc_html($index) . ": " . esc_html($feed['title']) . " (slug: " . esc_html($feed['slug']) . ")\n";
    }
}

// Test 2: Check rewrite rules
echo "\n2. Checking rewrite rules...\n";
global $wp_rewrite;
$rules = $wp_rewrite->wp_rewrite_rules();

$custom_feed_rules = array_filter($rules, function($rule, $pattern) {
    return strpos($pattern, 'feed/') !== false && strpos($rule, 'bf_custom_feed') !== false;
}, ARRAY_FILTER_USE_BOTH);

echo "   Custom feed rewrite rules: " . count($custom_feed_rules) . "\n";

if (empty($custom_feed_rules)) {
    echo "   ❌ No custom feed rewrite rules found\n";
} else {
    foreach ($custom_feed_rules as $pattern => $rule) {
        echo "   Pattern: " . esc_html($pattern) . " -> " . esc_html($rule) . "\n";
    }
}

// Test 3: Check query vars
echo "\n3. Checking query vars...\n";
$query_vars = $wp_rewrite->public_query_vars;
$has_custom_feed_var = in_array('bf_custom_feed', $query_vars);
echo "   bf_custom_feed query var: " . ($has_custom_feed_var ? "✅ Yes" : "❌ No") . "\n";

// Test 4: Test custom feed access
echo "\n4. Testing custom feed access...\n";
if (!empty($custom_feeds)) {
    $test_feed = $custom_feeds[0];
    $feed_url = home_url('/feed/' . $test_feed['slug'] . '/');
    echo "   Testing feed URL: " . esc_html($feed_url) . "\n";
    
    // Make a request to the feed URL
    $response = wp_remote_get($feed_url);
    
    if (is_wp_error($response)) {
        echo "   ❌ Error accessing feed: " . esc_html($response->get_error_message()) . "\n";
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        echo "   Status Code: " . esc_html($status_code) . "\n";
        echo "   Content Type: " . esc_html($content_type) . "\n";
        
        if ($status_code === 200 && strpos($content_type, 'xml') !== false) {
            echo "   ✅ Feed is accessible and returns XML\n";
        } else {
            echo "   ❌ Feed is not working properly\n";
        }
    }
} else {
    echo "   ⚠️  No feeds to test\n";
}

// Test 5: Check if BF_Custom_Feeds class is loaded
echo "\n5. Checking BF_Custom_Feeds class...\n";
if (class_exists('BF_Custom_Feeds')) {
    echo "   ✅ BF_Custom_Feeds class is loaded\n";
    
    // Check if hooks are registered
    global $wp_filter;
    $has_init_hook = isset($wp_filter['init']);
    $has_template_redirect_hook = isset($wp_filter['template_redirect']);
    
    echo "   init hook registered: " . ($has_init_hook ? "✅ Yes" : "❌ No") . "\n";
    echo "   template_redirect hook registered: " . ($has_template_redirect_hook ? "✅ Yes" : "❌ No") . "\n";
} else {
    echo "   ❌ BF_Custom_Feeds class is not loaded\n";
}

// Test 6: Create a test feed
echo "\n6. Creating test feed...\n";
$test_feed_data = array(
    'title' => 'Test Feed',
    'slug' => 'test-feed-' . time(),
    'description' => 'Test feed created by test script',
    'limit' => 5,
    'post_types' => array('post'),
    'orderby' => 'date',
    'order' => 'DESC',
    'enabled' => true,
    'created_at' => current_time('mysql')
);

$custom_feeds[] = $test_feed_data;
update_option('bf_custom_feeds', $custom_feeds);

// Flush rewrite rules
flush_rewrite_rules();

echo "   ✅ Test feed created with slug: " . esc_html($test_feed_data['slug']) . "\n";

// Test the new feed
$test_url = home_url('/feed/' . $test_feed_data['slug'] . '/');
echo "   Testing new feed URL: " . esc_html($test_url) . "\n";

$response = wp_remote_get($test_url);
if (is_wp_error($response)) {
        echo "   ❌ Error accessing new feed: " . esc_html($response->get_error_message()) . "\n";
} else {
    $status_code = wp_remote_retrieve_response_code($response);
        echo "   Status Code: " . esc_html($status_code) . "\n";
    
    if ($status_code === 200) {
        echo "   ✅ New feed is accessible!\n";
    } else {
        echo "   ❌ New feed is not accessible\n";
    }
}

echo "\n📊 Test Summary\n";
echo "===============\n";
echo "Custom feeds functionality test completed.\n";
echo "If feeds are not accessible, check:\n";
echo "1. WordPress permalink structure\n";
echo "2. Rewrite rules flushing\n";
echo "3. Plugin activation status\n";
echo "4. Server configuration\n";
