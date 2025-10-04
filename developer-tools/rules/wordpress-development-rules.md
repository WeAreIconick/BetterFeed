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

### Admin Screen Form Architecture
- ✅ **Single Form Pattern**: ONE `<form>` tag prevents multiple "Settings saved" messages
- ✅ **Settings Registration**: Always register ALL settings (avoid conditional registration)
- ✅ **Nonce Security**: Use `wp_create_nonce()` and `wp_nonce_field()`
- ✅ **Form Action**: Point to same admin page for consistent behavior

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

### REST API Error Prevention Patterns
- ✅ **401 Unauthorized**: Missing cookies → add `credentials: 'include'`
- ✅ **HTML Response**: 404/500 error page → check endpoint URL and registration
- ✅ **Parse Error '<'**: WordPress returning HTML instead of JSON → check auth
- ✅ **CORS Issues**: Add proper headers and credentials for cross-origin requests

### REST Endpoint Testing Workflow
- ✅ **Pre-Testing**: Test endpoints with cURL before adding JavaScript
- ✅ **Status Verification**: Confirm 200 OK, not 401/404/500
- ✅ **Content Type**: Validate `application/json` responses
- ✅ **Authentication**: Test with proper `X-WP-Nonce` headers

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

---

**Usage**: Copy this framework to new WordPress projects and customize for project-specific needs.
