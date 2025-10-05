# WordPress Plugin Coding Standards & Documentation Guide

## Overview
This guide establishes the coding standards and documentation practices for the BetterFeed WordPress plugin, ensuring it serves as a model for other developers to follow.

## 1. PHPDoc Documentation Standards

### Function Documentation Template
```php
/**
 * Brief description of what the function does.
 * 
 * More detailed description if needed. Explain the purpose,
 * behavior, and any important implementation details.
 * 
 * @since 1.0.0
 * 
 * @param string $param_name Description of the parameter.
 * @param int    $param_name Description of the parameter.
 * @param array  $param_name {
 *     Optional. Array of options.
 * 
 *     @type string $key Description of array key.
 *     @type bool   $key Description of array key.
 * }
 * @param mixed  $param_name Optional. Description of parameter.
 * 
 * @return mixed|WP_Error Description of return value.
 * 
 * @example
 * $result = example_function('value', 123, array('key' => 'value'));
 */
```

### Class Documentation Template
```php
/**
 * Class description explaining the purpose and functionality.
 * 
 * This class handles [specific functionality]. It provides methods for
 * [list main capabilities] and integrates with WordPress through
 * [hooks, APIs, etc.].
 * 
 * @package BetterFeed
 * @since   1.0.0
 * 
 * @example
 * $instance = BF_Class_Name::instance();
 * $instance->method_name();
 */
class BF_Class_Name {
    
    /**
     * Class instance for singleton pattern.
     * 
     * @since 1.0.0
     * @var BF_Class_Name
     */
    private static $instance = null;
}
```

### Method Documentation Template
```php
/**
 * Brief description of what the method does.
 * 
 * Detailed description explaining the method's purpose,
 * when it's called, and any side effects.
 * 
 * @since 1.0.0
 * 
 * @param string $param_name Description of parameter.
 * @param array  $options {
 *     Optional. Configuration options.
 * 
 *     @type bool   $enabled Whether the feature is enabled.
 *     @type string $method  The method to use.
 * }
 * 
 * @return bool|WP_Error True on success, WP_Error on failure.
 * 
 * @throws Exception When something goes wrong.
 * 
 * @example
 * $result = $this->method_name('value', array('enabled' => true));
 */
```

## 2. Inline Comment Standards

### Good Inline Comments
```php
// Check if user has permission to access this feature
if (!current_user_can('manage_options')) {
    return false;
}

// Get cached results first to avoid expensive database query
$cached_result = wp_cache_get($cache_key, 'betterfeed');

// Process each item in the array
foreach ($items as $item) {
    // Skip items that don't meet our criteria
    if (empty($item['required_field'])) {
        continue;
    }
    
    // Transform the data for our specific needs
    $processed_item = $this->process_item($item);
}
```

### Bad Inline Comments
```php
// Increment counter
$counter++;

// Set variable
$var = 'value';

// Loop through array
foreach ($array as $item) {
    // Do something
    do_something($item);
}
```

## 3. JavaScript Documentation Standards

### Function Documentation
```javascript
/**
 * Brief description of what the function does.
 * 
 * More detailed description if needed. Explain the purpose,
 * parameters, and return value.
 * 
 * @since 1.0.0
 * 
 * @param {number} feedIndex - The index of the feed to delete
 * @param {Object} options   - Optional configuration object
 * @param {boolean} options.confirm - Whether to show confirmation dialog
 * 
 * @return {Promise} Promise that resolves when operation completes
 * 
 * @example
 * deleteFeed(0, { confirm: true })
 *   .then(() => console.log('Feed deleted'))
 *   .catch(error => console.error('Error:', error));
 */
function deleteFeed(feedIndex, options = {}) {
    // Implementation here
}
```

### Error Handling Standards
```javascript
/**
 * Fetch data from REST API with comprehensive error handling.
 * 
 * @param {string} endpoint - The API endpoint to call
 * @param {Object} options  - Fetch options
 * 
 * @return {Promise<Object>} Promise resolving to API response data
 */
function fetchWithErrorHandling(endpoint, options = {}) {
    return fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': bf_config.nonce
        },
        ...options
    })
    .then(response => {
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Parse JSON response
        return response.json();
    })
    .then(data => {
        // Validate response structure
        if (!data || typeof data !== 'object') {
            throw new Error('Invalid response format');
        }
        
        return data;
    })
    .catch(error => {
        // Log error for debugging
        console.error('BetterFeed API Error:', error);
        
        // Show user-friendly error message
        showAdminNotice(`Operation failed: ${error.message}`, 'error');
        
        // Re-throw for caller to handle if needed
        throw error;
    });
}
```

## 4. File Header Standards

### PHP File Header
```php
<?php
/**
 * File description explaining what this file contains.
 * 
 * This file handles [specific functionality]. It includes
 * [list main components] and provides [list main features].
 * 
 * @package BetterFeed
 * @since   1.0.0
 * 
 * @link    https://github.com/your-repo/betterfeed
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
```

### JavaScript File Header
```javascript
/**
 * File description explaining what this file contains.
 * 
 * This file handles [specific functionality] for the BetterFeed plugin.
 * It provides [list main features] and integrates with [WordPress APIs].
 * 
 * @package BetterFeed
 * @since   1.0.0
 * 
 * @link    https://github.com/your-repo/betterfeed
 */

(function($) {
    'use strict';
    
    // Implementation here
    
})(jQuery);
```

## 5. Code Organization Standards

### Class Structure
```php
/**
 * Example class showing proper organization.
 * 
 * @package BetterFeed
 * @since   1.0.0
 */
class BF_Example_Class {
    
    // 1. Constants
    const VERSION = '1.0.0';
    
    // 2. Properties
    private static $instance = null;
    private $options = array();
    
    // 3. Constructor and singleton methods
    private function __construct() {
        $this->init_hooks();
    }
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // 4. Initialization methods
    private function init_hooks() {
        // Hook initialization
    }
    
    // 5. Public methods (alphabetical)
    public function public_method() {
        // Implementation
    }
    
    // 6. Private/protected methods (alphabetical)
    private function private_method() {
        // Implementation
    }
    
    // 7. Static methods
    public static function static_method() {
        // Implementation
    }
}
```

## 6. Error Handling Standards

### PHP Error Handling
```php
/**
 * Example of proper error handling in PHP.
 * 
 * @param array $data The data to process
 * 
 * @return bool|WP_Error True on success, WP_Error on failure
 */
public function process_data($data) {
    try {
        // Validate input
        if (!is_array($data)) {
            return new WP_Error(
                'invalid_data',
                __('Data must be an array.', 'betterfeed'),
                array('data' => $data)
            );
        }
        
        // Process data
        $result = $this->do_processing($data);
        
        // Validate result
        if (!$result) {
            return new WP_Error(
                'processing_failed',
                __('Failed to process data.', 'betterfeed'),
                array('data' => $data)
            );
        }
        
        return true;
        
    } catch (Exception $e) {
        // Log error
        error_log('BetterFeed Error: ' . $e->getMessage());
        
        // Return user-friendly error
        return new WP_Error(
            'exception',
            __('An unexpected error occurred.', 'betterfeed'),
            array('exception' => $e->getMessage())
        );
    }
}
```

## 7. Security Documentation Standards

### Input Validation Documentation
```php
/**
 * Sanitize and validate user input.
 * 
 * This method demonstrates proper input sanitization and validation
 * following WordPress security best practices.
 * 
 * @since 1.0.0
 * 
 * @param mixed $input The user input to sanitize
 * @param string $type The expected type of input (text, email, url, etc.)
 * 
 * @return string|false Sanitized input or false if invalid
 * 
 * @security This method prevents XSS attacks by properly escaping output
 *           and validates input to prevent injection attacks.
 */
public function sanitize_input($input, $type = 'text') {
    // Implementation with proper sanitization
}
```

## 8. Performance Documentation Standards

### Caching Documentation
```php
/**
 * Get data with caching support.
 * 
 * This method implements WordPress transient caching to improve
 * performance by avoiding expensive operations.
 * 
 * @since 1.0.0
 * 
 * @param string $key The cache key
 * @param int    $expiration Cache expiration in seconds (default: 1 hour)
 * 
 * @return mixed Cached data or false if not found
 * 
 * @performance This method reduces database queries by caching
 *              expensive operations for 1 hour by default.
 */
public function get_cached_data($key, $expiration = 3600) {
    // Implementation with caching
}
```

## 9. Testing Documentation Standards

### Test Method Documentation
```php
/**
 * Test custom feed creation functionality.
 * 
 * This test verifies that custom feeds can be created successfully
 * and that all required data is properly stored and validated.
 * 
 * @since 1.0.0
 * 
 * @test This test covers:
 *       - Feed creation with valid data
 *       - Data validation and sanitization
 *       - Database storage verification
 *       - Error handling for invalid data
 * 
 * @return void
 */
public function test_create_custom_feed() {
    // Test implementation
}
```

## 10. User-Facing Documentation Standards

### Admin Notice Documentation
```php
/**
 * Display admin notice with proper styling and context.
 * 
 * This method creates user-friendly admin notices that follow
 * WordPress UI patterns and provide clear feedback to users.
 * 
 * @since 1.0.0
 * 
 * @param string $message The message to display
 * @param string $type    The notice type (success, error, warning, info)
 * @param bool   $dismissible Whether the notice can be dismissed
 * 
 * @return void
 * 
 * @user-experience Provides clear feedback to users about
 *                  operation results and system status.
 */
public function show_admin_notice($message, $type = 'info', $dismissible = true) {
    // Implementation
}
```

## Implementation Checklist

- [ ] All functions have PHPDoc comments with @param and @return
- [ ] All classes have comprehensive class-level documentation
- [ ] All public methods are documented with examples where helpful
- [ ] Inline comments explain complex logic, not obvious code
- [ ] Error handling is documented with @throws where applicable
- [ ] Security considerations are documented where relevant
- [ ] Performance implications are documented for expensive operations
- [ ] JavaScript functions have JSDoc comments
- [ ] All fetch calls have comprehensive error handling
- [ ] File headers explain the purpose of each file

## Tools for Maintaining Standards

1. **PHP_CodeSniffer** - Enforces coding standards
2. **PHPStan** - Static analysis for error detection
3. **JSHint/ESLint** - JavaScript code quality
4. **WordPress Coding Standards** - Official WordPress standards
5. **PHPDoc** - Documentation generation

This guide ensures that the BetterFeed plugin serves as an excellent example of WordPress development best practices.
