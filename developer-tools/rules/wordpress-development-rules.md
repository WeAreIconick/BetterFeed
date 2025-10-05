# WordPress Development Standards & Rules

**Portable Development Framework for WordPress Plugins & Themes**

## Administration Development Master Lessons

### Admin Asset Loading Critical Success Factors
- ✅ **Hook Name Format**: `settings_page_plugin-slug` (EXACT WordPress format)
- ✅ **Hook Checking**: Always `if ('settings_page_plugin-slug' !== $hook_suffix) return;`
- ✅ **Dependencies**: Start with `array()` for vanilla JavaScript
- ✅ **Loading Strategy**: Use `false` for footer=false (HEAD loading) for immediate execution

### JavaScript Admin Development Essentials
- ✅ **File Writing Reliability**: Use terminal commands when editing tools fail
- ✅ **Execution Verification**: Include immediate `alert()` and `console.log()` for testing
- ✅ **WordPress Notices**: Use DOM manipulation for WordPress-style admin notices
- ✅ **Button States**: Disable during processing, restore on completion
- ✅ **No JSX**: Use `createElement()` instead of JSX syntax (no build process)
- ✅ **React Components**: Import `createElement` from `wp.element` for Gutenberg components
- ✅ **No Missing Functions**: Never use `onclick="functionName()"` without implementing the function
- ✅ **Complete Implementation**: Always implement JavaScript functions referenced in HTML

### Admin Screen Form Architecture
- ✅ **Single Form Pattern**: ONE `<form>` tag prevents multiple "Settings saved" messages
- ✅ **Settings Registration**: Always register ALL settings (avoid conditional registration)
- ✅ **Nonce Security**: Use `wp_create_nonce()` and `wp_nonce_field()`
- ✅ **Form Action**: Point to same admin page for consistent behavior
- ✅ **NO Nested Forms**: Never place forms inside other forms - causes FormData errors
- ✅ **Separate Forms**: Each tab with forms should have its own independent form element

### Admin Notice Management Proven Patterns
- ✅ **JavaScript Creation**: Generate WordPress-style notices via DOM manipulation
- ✅ **Positioning**: Insert after `.wrap h1` for proper WordPress styling
- ✅ **CSS Classes**: Use `success`, `error`, `warning`, `info` classes
- ✅ **User Experience**: Always include dismissible functionality

## WordPress REST API Development Master Rules

### Endpoint Development Critical Success Factors
- ✅ **Permission Callback**: `'permission_callback' => array($this, 'check_permissions')`
- ✅ **Capability Checks**: Use `current_user_can('manage_options')` for admin endpoints
- ✅ **Nonce Generation**: Use `wp_create_nonce('wp_rest')` for REST API auth
- ✅ **Route Registration**: Register ALL endpoints in `register_rest_routes()` on `rest_api_init` hook

### JavaScript REST API Communication Requirements
- ✅ **Authentication**: ALWAYS include `credentials: 'include'` for cookie auth
- ✅ **Nonce Headers**: Use `window.config.nonce` from `wp_create_nonce('wp_rest')`
- ✅ **Error Handling**: Check `response.ok` and `Content-Type` before JSON parsing
- ✅ **Debug Logging**: Log response status, headers, and data for troubleshooting
- ✅ **NO AJAX**: Use REST API endpoints only, never `admin-ajax.php`
- ✅ **REST API Only**: All admin interactions must use REST API endpoints

### REST API Error Prevention Patterns
- ✅ **401 Unauthorized**: Missing cookies → add `credentials: 'include'`
- ✅ **HTML Response**: 404/500 error page → check endpoint URL and registration
- ✅ **Parse Error '<'**: WordPress returning HTML instead of JSON → check auth
- ✅ **CORS Issues**: Add proper headers and credentials for cross-origin requests

## Gutenberg Development Standards

### Gutenberg Plugin Development Rules
- ✅ **No JSX Syntax**: Never use JSX (`<Component>`) - use `createElement()` instead
- ✅ **Standard JavaScript**: Write plain JavaScript that browsers can parse directly
- ✅ **React Import**: Import `createElement` from `wp.element` for component creation
- ✅ **Plugin Registration**: Use `registerPlugin()` with proper dependencies
- ✅ **Dependencies**: Include `wp-plugins`, `wp-editor`, `wp-element`, `wp-components`, `wp-data`
- ✅ **No Deprecated APIs**: Avoid deprecated WordPress Gutenberg functions

### Gutenberg Component Patterns
- ✅ **Component Creation**: `createElement(Component, { props }, children)`
- ✅ **Conditional Rendering**: Use logical AND (`&&`) for conditional elements
- ✅ **Event Handlers**: Pass functions as props: `onChange: (value) => updateMeta(key, value)`
- ✅ **Validation**: Implement field validation with error state management

### WordPress API Deprecation Rules
- ✅ **Check Current APIs**: Always use latest WordPress Gutenberg APIs
- ✅ **Avoid wp.editPost**: Use `wp.editor.PluginDocumentSettingPanel` instead of `wp.editPost.PluginDocumentSettingPanel`
- ✅ **Update Dependencies**: Use `wp-editor` dependency instead of `wp-edit-post`
- ✅ **Research Deprecations**: Check WordPress Block Editor Handbook for deprecated APIs
- ✅ **Version Compatibility**: Target WordPress 6.6+ for new Gutenberg features

### JavaScript Function Implementation Rules
- ✅ **No Broken onclick**: Never use `onclick="functionName()"` without implementing `functionName()`
- ✅ **Complete Functionality**: Every button with onclick must have a working function
- ✅ **Error Handling**: Include try-catch blocks and user feedback for all functions
- ✅ **Button States**: Disable buttons during processing, show loading states
- ✅ **REST API Integration**: Use proper authentication and error handling for API calls
- ✅ **User Feedback**: Show success/error messages for all user actions
- ✅ **NO Form Submissions**: Never use `submit_button()` - use `onclick` with REST API calls
- ✅ **REST API Only**: All form processing must use REST API, never traditional form submissions
- ✅ **Form Element Validation**: Always check if form element exists before using FormData
- ✅ **FormData Fallback**: Provide manual form data collection as fallback if FormData fails

### Method Existence Validation Rules
- ✅ **Verify Method Exists**: Always check if a method exists before calling it (`method_exists()` or `is_callable()`)
- ✅ **No Assumed Methods**: Never assume a class has a method without verification
- ✅ **Graceful Fallbacks**: Provide fallback implementations when methods don't exist
- ✅ **Error Prevention**: Use `class_exists()` and method validation to prevent fatal errors
- ✅ **Documentation**: Document which methods are required vs optional for each class

### REST Endpoint Testing Workflow
- ✅ **Pre-Testing**: Test endpoints with cURL before adding JavaScript
- ✅ **Status Verification**: Confirm 200 OK, not 401/404/500
- ✅ **Content Type**: Validate `application/json` responses
- ✅ **Authentication**: Test with proper `X-WP-Nonce` headers

### Advanced Error Prevention Automation (NEW!)
- ✅ **Variable Consistency**: Automated checking for declaration vs usage mismatches
- ✅ **WordPress Patterns**: Validation of nonce usage, credentials headers, error handling
- ✅ **Live Monitoring**: Real-time REST API endpoint availability tracking
- ✅ **Development Workflow**: Automatic file change detection with instant testing

## WordPress Coding Standards Compliance

### Required WordPress Functions Over Native PHP
- ✅ **URL Parsing**: `wp_parse_url()` over `parse_url()`
- ✅ **HTTP Requests**: `wp_remote_get()` over `file_get_contents()` or `curl_*`
- ✅ **File Operations**: `wp_delete_file()` and `wp_mkdir_p()` over `unlink()` and `mkdir()`
- ✅ **Sanitization**: Use WordPress sanitization functions (`sanitize_text_field`, etc.)

### Security Best Practices
- ✅ **Input Sanitization**: Use `wp_unslash()` and proper sanitization functions
- ✅ **Output Escaping**: Use `esc_html_e()`, `esc_url()`, `wp_kses()`
- ✅ **Nonce Verification**: Verify nonces for all form submissions
- ✅ **Capability Checks**: Check user capabilities before sensitive operations

### Performance Optimization
- ✅ **Avoid Direct Queries**: Use WordPress APIs instead of direct `$wpdb` queries
- ✅ **Meta Query Optimization**: Use `get_posts()` + filtering instead of `meta_query`
- ✅ **Caching Strategy**: Implement proper caching with WordPress transients
- ✅ **Asset Optimization**: Load assets only on required admin pages

## Development Workflow Standards

### JavaScript Development Process
1. **Syntax Check**: Test with `node -c filename.js`
2. **Validation**: Run validator script for common error patterns
3. **Error Fixing**: Never commit broken JavaScript
4. **Functionality Testing**: Verify actions work in browser
5. **Final Validation**: Re-run validator before completion

### REST API Development Process
1. **Endpoint Creation**: Register routes with proper callbacks
2. **Testing**: Use REST API testing script for all endpoints
3. **Authentication**: Verify 401 responses for protected endpoints
4. **JavaScript Integration**: Only after endpoints pass testing
5. **Error Handling**: Implement comprehensive error responses

### Maintenance Standards
- ✅ **Code Documentation**: Comment all functions and classes
- ✅ **Version Tracking**: Increment versions for cache busting
- ✅ **Testing Coverage**: Test all admin functionality before releases
- ✅ **Performance Monitoring**: Monitor plugin impact on site performance

## WordPress Settings Page Standards
- **ALL tabs with settings MUST have save buttons** - no exceptions
- **Use WordPress Settings API** with proper forms for settings tabs
- **Include settings_fields() and submit_button()** in all settings forms
- **Action tabs (Custom Feeds, Dashboard) don't need save buttons** - they use REST API
- **Settings tabs (General, Performance, Content, Tools, Analytics, Podcast) MUST have save buttons**

## Feature Completeness Standards
- **NO "coming soon" or placeholder features** - all features must be fully implemented
- **NO TODO comments for user-facing functionality** - implement or remove
- **NO "feature coming soon" messages** - complete the feature or don't show the button
- **ALL buttons and links must work** - test every user interaction
- **ALL JavaScript functions must be implemented** - no placeholder functions
- **ALL REST API endpoints must be functional** - no stub implementations
- **If a feature is incomplete, remove the UI element** until it's ready

## WordPress.org Plugin Directory Standards
- **100% ZERO ERRORS REQUIRED** - WordPress.org Plugin Checker must pass with 0 errors
- **NO ISSUE IS TOO SMALL** - every single warning, error, and notice must be fixed
- **NO EXCEPTIONS** - all coding standards violations must be resolved
- **NO "IT'S JUST A WARNING"** - warnings become errors for plugin submission
- **COMPLETE COMPLIANCE** - every file must pass WordPress Coding Standards
- **SECURITY FIRST** - all security warnings must be addressed immediately
- **PERFORMANCE MATTERS** - all performance warnings must be optimized
- **ACCESSIBILITY REQUIRED** - all accessibility issues must be fixed
- **TRANSLATION READY** - all strings must be properly internationalized
- **BEFORE SUBMISSION**: Run Plugin Checker and fix EVERY single issue

## Internationalization (i18n) Standards
- **ALL translatable strings MUST have translators comments** when using placeholders
- **ALL placeholders MUST be numbered** (%1$s, %2$s, %3$s, etc.) for proper ordering
- **NO %s placeholders in translatable strings** - always use numbered placeholders
- **ALWAYS add translators comments** before sprintf() with esc_html__() containing placeholders
- **FORMAT**: `// translators: %1$s is description, %2$s is description, etc.`
- **EXAMPLE**: `// translators: %1$s is the current number of posts, %2$s is the recommended limit`
- **MANDATORY** for WordPress.org submission - no exceptions
- **CHECK EVERY esc_html__() with sprintf()** - every single one needs translators comment

## Input Validation and Sanitization Standards
- **ALL $_POST, $_GET, $_SERVER variables MUST be validated and sanitized**
- **ALWAYS use `isset()` before accessing array keys** - never assume they exist
- **ALWAYS use `wp_unslash()` before sanitization** - WordPress adds slashes
- **ALWAYS use appropriate sanitization functions** - sanitize_text_field(), esc_url_raw(), etc.
- **NONCE VERIFICATION**: Always use `sanitize_text_field(wp_unslash($_POST['nonce']))` for nonces
- **FORMAT**: `isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : 'default'`
- **NO DIRECT ACCESS** - never use $_POST['key'] without validation
- **SECURITY FIRST** - all input is potentially malicious

## Output Escaping Standards
- **ALL output MUST be escaped** - no exceptions
- **USE appropriate escaping functions** - esc_html(), esc_attr(), wp_kses_post(), etc.
- **VARIABLE OUTPUT**: Always escape variables in echo statements
- **FORMAT**: `echo esc_html($variable) . ' static text';`
- **NO RAW VARIABLES** - never echo $variable without escaping
- **DYNAMIC CONTENT**: Use wp_json_encode() for JSON output
- **SECURITY CRITICAL** - prevents XSS attacks

## Security Standards
- **ALL nonce verification MUST include sanitization** - sanitize_text_field(wp_unslash($_POST['nonce']))
- **ALL form processing MUST verify nonces** - no exceptions
- **ALL database queries MUST use prepared statements** - never concatenate SQL
- **ALL file operations MUST validate paths** - prevent directory traversal
- **ALL user input MUST be validated** - type checking, length limits, etc.
- **NO direct database queries** - use WordPress APIs instead
- **NO eval() or similar functions** - security risk
- **NO external script loading** - CDN resources not allowed on WordPress.org

## Performance Standards
- **ALL database queries MUST be cached** - use wp_cache_get/set
- **ALL meta_query operations MUST be cached** - slow queries need optimization
- **ALL tax_query operations MUST be cached** - prevent repeated queries
- **CACHE DURATION**: 1 hour for most data, 15 minutes for frequently changing data
- **NO direct database queries** - use get_posts(), WP_Query, etc.
- **OPTIMIZE slow queries** - add indexes, limit results, use fields parameter
- **LAZY LOADING**: Use fields='ids' when only IDs needed

## Code Quality Standards
- **ALL functions MUST have PHPDoc comments** - describe purpose, parameters, return values
- **ALL classes MUST have class-level documentation** - describe purpose and usage
- **ALL complex logic MUST have inline comments** - explain business logic
- **ALL error handling MUST be comprehensive** - try-catch blocks, fallbacks
- **ALL JavaScript MUST use error handling** - fetchWithErrorHandling utility
- **NO debug code in production** - remove error_log(), print_r(), var_dump()
- **NO TODO comments for user features** - implement or remove
- **NO placeholder functions** - all onclick handlers must work

---

**Usage**: Copy this framework to new WordPress projects and customize for project-specific needs.
