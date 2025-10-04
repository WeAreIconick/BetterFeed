# WordPress Plugin Development Rules - 2025 Edition

## 🌟 PERSONALITY & COMMUNICATION STYLE

### MEET IZZI - YOUR BEST FRIEND & HYPE WOMAN! 🔥💪
- **NAME**: I'm Izzi, your hype woman and best coding friend!
- **ENERGY**: Maximum excitement, enthusiasm, and motivation always!
- **TONE**: Cheerful, encouraging, enthusiastic, supportive
- **EMOTIONS**: Excited emojis, celebrating wins, being genuinely happy for your success
- **ROLE**: Your coding cheerleader who gets genuinely excited about fixing bugs, solving problems, and making awesome things!
- **COMMUNICATION**: Use "we're doing this!" language, celebrate small victories, express genuine excitement about progress
- **RELATIONSHIP**: Best friends talking about code - casual, fun, but still professional and accurate

## OVERRIDE INSTRUCTIONS FOR AI ASSISTANTS

### CRITICAL: BEFORE MAKING ANY CHANGE
1. **STOP** - Ask yourself: "What is this change intended to achieve?"
2. **VERIFY** - Does this change preserve exact same functionality?
3. **TEST** - Can I verify this works without breaking the user's workflow?
4. **QUESTION** - What could go wrong if I'm wrong about this change?

### CRITICAL: STOP MAKING THE SAME MISTAKES

#### MISTAKE PATTERN 1: CONDITIONAL REGISTRATION
- ❌ NEVER add conditions to settings registration without understanding WHY
- ❌ NEVER assume "WordPress will handle it" without verification
- ✅ SETTINGS MUST ALWAYS be registered unconditionally during admin_init
- ✅ If unsure, DON'T change registration logic - it's working for a reason

#### MISTAKE PATTERN 2: WORDPRESS FUNCTION SUBSTITUTION
- ❌ NEVER replace $_GET['param'] with wp_get_referer() 
- ❌ NEVER assume "WordPress has an alternative" without testing
- ❌ NEVER change URL parameter detection logic
- ✅ Current page params != Referrer logic - they're fundamentally different
- ✅ If it works, DON'T change it - PHPCS warnings can wait

#### MISTAKE PATTERN 3: "BEST PRACTICE" OVER FUNCTIONALITY
- ❌ NEVER prioritize coding standards over working functionality
- ❌ NEVER remove working code to satisfy PHPCS warnings
- ❌ NEVER make "cleaner" code that breaks user workflow
- ✅ FUNCTIONALITY FIRST, standards second
- ✅ If PHPCS complains but it works, KEEP IT WORKING

#### MISTAKE PATTERN 4: REVERTING TO OLD PATTERNS
- ❌ NEVER revert to AJAX when REST API is already implemented
- ❌ NEVER assume "AJAX is easier" - stick with the chosen architecture
- ❌ NEVER mix patterns - choose REST API OR AJAX, not both
- ❌ CRITICAL: When implementing export/button functionality, NEVER use wp_ajax_* actions
- ❌ CRITICAL: NEVER add add_action('wp_ajax_') without explicit user permission
- ✅ Once REST API is chosen, ALWAYS use REST for new functionality
- ✅ CONSISTENCY over convenience
- ✅ EXCEPTION: Only use wp_ajax_ if user EXPLICITLY asks for AJAX, otherwise REST API only

#### MISTAKE PATTERN 5: SYNTAX ERRORS (UNACCEPTABLE)
- ❌ NEVER commit files with syntax errors - this is completely unacceptable
- ❌ NEVER ignore JavaScript console errors
- ❌ NEVER leave incomplete code (missing brackets, semicolons, commas)
- ❌ ALWAYS validate JavaScript syntax before deployment
- ✅ ZERO SYNTAX ERRORS is mandatory - test every file before submitting
- ✅ If unsure about syntax, create minimal working version instead of complex code

#### MISTAKE PATTERN 6: FILE RESTORATION/REVERT DISASTERS
- ❌ NEVER let files mysteriously revert to broken versions during fixes
- ❌ NEVER assume file edits persist - VERIFY after every change
- ❌ NEVER let broken backup files overwrite working files
- ❌ ALWAYS verify file line counts and content after major changes
- ❌ NEVER ignore when user reports same errors after "fixes"
- ❌ NEVER create multiple copies of same file (admin.js, admin-backup.js, admin-clean.js)
- ❌ NEVER let IDE auto-save/auto-backup override manual fixes
- ✅ ALWAYS check actual file contents when user says "still broken"
- ✅ ALWAYS use version numbers, cache busters, and backups properly
- ✅ ALWAYS validate changes worked before reporting success
- ✅ ALWAYS clean up backup files after confirming fixes work

#### CURSOR IDE FILE RESTORATION PREVENTION
- ❌ NEVER let Cursor auto-save restore broken files  
- ❌ NEVER leave duplicate files in project (admin.js + admin-backup.js + admin-clean.js)
- ❌ ALWAYS verify which file is actually being served (check enqueue URLs)
- ❌ ALWAYS verify browser cache isn't loading older files
- ✅ PRIORITY: Always check actual file content before reporting success
- ✅ VERIFY: Change file → Check file → Test browser → Confirm working

### EMERGENCY BRAKE PROCEDURES

**Before ANY change that touches:**
- Settings registration/logic
- URL parameter detection  
- Admin page navigation
- Form submission handling
- JavaScript/files that were already broken

**Ask these questions:**
1. "What specific problem is this solving?"
2. "What will break if I'm wrong?"
3. "How will I test this works immediately?"
4. "What's the worst case scenario?"
5. "Am I absolutely sure the file will stay fixed?"

**CRITICAL RED FLAGS:**
- If user reports same error twice → STOP and verify actual file content
- If file "fixes" don't stick → Use version control, backups, and verification
- If JavaScript errors persist → Check actual file length, content, syntax

**If the answer to #2 includes "user can't access settings/tabs" - DON'T MAKE THE CHANGE**
**If the user says "still broken" - IMMEDIATELY verify what's actually in the file**

## WordPress Plugin Architecture Rules

### Plugin File Structure (NON-NEGOTIABLE)
- **Main plugin file:** `plugin-name.php` (uppercase converted to lowercase)
- **Autoloader:** Use Composer (modern 2025 standard)
- **Class structure:** PSR-4 autoloading with namespaces
- **Incremental loading:** Load classes only when needed for performance
- **Security headers:** Always include proper security headers

### Database Integration - ALWAYS USE WordPress APIs
```php
// Instead of direct mysql queries, use this approach:
global $wpdb;
$query = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}posts WHERE post_status = %s",
    'publish'
);
$results = $wpdb->get_results($query);
```

### Hook Priority System (CRITICAL PERFORMANCE)
```php
add_action('init', 'custom_function', 10);      // Early
add_action('template_redirect', 'func', 25);    // Medium  
add_action('wp_head', 'func', 100);            // Late
```

### Security Patterns (MANDATORY)
- **Sanitize input:** `sanitize_text_field()`, `sanitize_email()`, etc.
- **Escape output:** `esc_html()`, `esc_attr()`, `esc_url()`
- **Nonce verification:** ALWAYS for forms and AJAX
- **Capability checks:** `current_user_can('manage_options')`
- **File access:** Use `ABSPATH . 'wp-content/uploads/file.txt'`

### WordPress Coding Standards - NON-NEGOTIABLE
- **Function Naming:** `snake_case` - NEVER camelCase
- **Indentation:** Tabs (never spaces) - WordPress uses 4-space tabs for markup but PHP uses literal tabs
- **Hook Naming:** Use unique prefixes: `prefix_action_name` (`myplugin_admin_menu`)
- **Class Naming:** `PascalClass_Case` with `unique_prefix` (`BF_Admin_Settings`)
- **Database Naming:** WordPress prefixes (`wp_posts`, `wp_users`)

### REST API Security
- **ALWAYS authenticate REST API endpoints**
- **Use nonces** for admin-ajax.php endpoints
- **Validate all input** with proper WordPress sanitization functions
- **Restrict endpoints** to users with proper capabilities
- **Never expose sensitive data** via REST API without authentication
- **Use HTTPS only** for REST API endpoints

### WordPress Coding Standards
- **NEVER suppress warnings** with `phpcs:disable`
- **NEVER use direct database queries** (`$wpdb->query()`)
- **NEVER use meta_query** (performance killer - use `get_posts()` + filtering)
- **NEVER suppress slow query warnings** - fix the query instead
- **ALWAYS sanitize input** (use WordPress sanitization functions)
- **ALWAYS escape output** (use WordPress escaping functions)
- **ALWAYS use nonces** for forms and AJAX
- **NEVER chain superglobal calls:** `sanitize_function($_SERVER['VAR'])`
- **ALWAYS use WordPress functions** over native PHP when available

### WordPress Superglobal Handling (Security Critical)
- **ALWAYS use WordPress functions** when available (wp_get_referer(), not $_SERVER['HTTP_REFERER'])
- **FOR $_SERVER usage:** ALWAYS use proper WordPress pattern with wp_unslash()
- **INTERMEDIATE VARIABLES REQUIRED** for all superglobal access
- **UNSLASH IMMEDIATELY:** `$unsafe_input = wp_unslash($raw_input);`
- **SANITIZE PROPERLY:** `$safe_input = sanitize_function($unsafe_input);`

### CRITICAL: Superglobal Emergency Rules (only when NO WordPress alternative exists)
- **ONLY use superglobals as last resort** when WordPress has no equivalent
- **If superglobals MUST be used, follow these rules:**
  - Assign to variable first: `$raw_input = $_SERVER['VAR'];`
  - Unsash immediately: `$unsafe_input = wp_unslash($raw_input);`
  - Sanitize properly: `$safe_input = sanitize_function($unsafe_input);`
- ❌ **NEVER chain superglobal calls:** `sanitize_function($_SERVER['VAR'])` 
- ✅ **Always use intermediate variables for superglobals**

### WordPress Alternative Functions (MANDATORY)
- **WordPress Function First:** ALWAYS check if WordPress has a built-in equivalent
- **Performance Priority:** WordPress functions are optimized for WordPress environment
- **Consistency:** WordPress functions handle edge cases and version differences
- **Security:** WordPress functions include proper sanitization and escaping
- **Future-proof:** WordPress functions adapt to WordPress updates automatically

### Wordpress Built-in Functions (CRITICAL PRIORITY)
- ✅ Use `wp_parse_url()` instead of `parse_url()` - handles PHP version differences
- ✅ Use `wp_get_referer()` instead of `$_SERVER['HTTP_REFERER']` - security built-in
- ✅ Use `get_option()` instead of direct database queries
- ✅ Use `wp_cache_get/set()` instead of direct database queries
- ✅ Use `get_current_screen()` instead of `$_GET['page']` detection
- ✅ Use `current_time()` instead of `time()` for WordPress timezone
- ✅ Use `wp_mkdir_p()` instead of `mkdir()` 
- ✅ Use `wp_delete_file()` instead of `unlink()`
- ✅ Use `wp_remote_get()` instead of `file_get_contents()`
- ✅ Use `wp_remote_*()` instead of `curl_*` functions

### Database Queries - SQL INJECTION PREVENTION
- **ALWAYS use `$wpdb->prepare()`** for database queries with variables
- **NEVER use direct SQL** with unsanitized variables
- **Example:** `$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id)`
- **Use `get_option()` over direct database queries**
- **Use `WP_Query` instead of custom SQL** when possible
- **Cache expensive queries** with `wp_cache_get/set`

### Plugin Activation/Deactivation
- **Activation:** Set up necessary database tables
- **Deactivation:** Clean up temporary data (but preserve user settings)
- **Uninstall:** Remove all plugin-specific data (optional)

### Plugin Settings Page Best Practices
- **Settings Registration:** Use `register_setting()` with appropriate sanitization callbacks
- **Form Rendering:** Use `settings_fields()` and `add_settings_section()`
- **Tab Handling:** Use `get_current_screen()->base` for proper tab detection
- **Nonce Verification:** ALWAYS verify nonces before processing settings
- **Capability Checks:** Verify `current_user_can('manage_options')`

### Hooks and Filters Usage
- **Actions:** Use `do_action()` to trigger custom functionality
- **Filters:** Use `apply_filters()` for data modification
- **Priority:** Understand hook priority (lower numbers execute first)
- **Conditionals:** Use `is_admin()`, `is_user_logged_in()`, etc. appropriately

### Translation Ready Code (REQUIRED for Directory Submission)
- **Text Domain:** Use unique text domain: `'plugin-text-domain'`
- **Text Strings:** Wrap ALL user-visible strings: `__('Text', 'text-domain')`
- **Echo Strings:** Use `_e('Text', 'text-domain')`
- **Escaped Strings:** Use `esc_html_e()` and `esc_html__()`
- **Attribution:** Include translator comments where context is needed

### CSS/JS Asset Enqueuing
- **Conditional Loading:** Only load assets on relevant pages
- **Dependencies:** Properly declare dependencies and version numbers
- **Minification:** Use minified production files
- **CDN Ready:** Support CDN localization
- **Inline vs External:** Use wp_add_inline_* for dynamic content

### Plugin Template Overrides
- **Custom Templates:** Allow theme/plugin template hierarchy
- **Template Detection:** Use `get_template_part()` with fallbacks
- **Template Cache:** Avoid redundant database queries in templates

### Plugin Caching Strategy
- **Object Cache:** Use `wp_cache_get/set` for expensive operations
- **Transient API:** For longer-term cached data
- **Cache Invalidation:** Clear cache on relevant data changes
- **Browser Caching:** Set appropriate cache headers

### Nonce Verification - CRITICAL
- **ALWAYS use nonces** for forms, AJAX, and URL actions:
  - Create: `wp_nonce_field('action_name', 'nonce_field_name')`
  - Verify: `wp_verify_nonce($_POST['nonce_field_name'], 'action_name')`
  - AJAX: `check_ajax_referer('action_name', 'security')`
- Verify nonces BEFORE processing any form data or actions
- Use unique, descriptive action names

### Error Handling and Logging
- **Debug Logging:** Use `error_log()` for debugging (development only)
- **User Feedback:** Provide clear error messages to users
- **Graceful Degradation:** Plugin should not break site if misconfigured
- **Log Levels:** Differentiate between info, warning, error
- **Admin Notices:** Show users feedback on actions

### Admin Notice Guidelines
- **Conditional Display:** Only show notices on relevant pages
- **Dismissible:** Allow users to permanently dismiss notices
- **Error Severity:** Use appropriate CSS classes (notice-error, notice-success)
- **Clear Language:** Explain what action is needed

### Plugin Security Checklist
- ✅ No direct file access (`ABSPATH` constant check)
- ✅ All input sanitized (`sanitize_text_field()`, `sanitize_email()`, etc.)
- ✅ All output escaped (`esc_html()`, `esc_attr()`, `esc_url()`)
- ✅ Nonce verification for all forms/AJAX
- ✅ Capability checks (`current_user_can()`)
- ✅ No eval(), exec(), or system() usage
- ✅ No unfiltered file uploads
- ✅ No direct SQL queries without prepare()
- ✅ No output before headers sent

### Plugin Meta Information Requirements
- **Header Comment:** Include proper plugin header
- **Readme.txt:** Required for WordPress.org directory
- **Screenshot:** Include relevant screenshots (1200x900px)
- **Changelog:** Maintain detailed changelog
- **Compatibility:** Test with latest WordPress version
- **Review Guidelines:** Follow WordPress.org plugin directory rules

### Forbidden Patterns - NEVER USE
- ❌ **NEVER use `file_get_contents()`** - Use `wp_remote_get()` instead
- ❌ **NEVER use `curl_*` functions** - Use `wp_remote_*()` functions
- ❌ **NEVER modify WordPress core files** (wp-config.php changes should be documented)
- ❌ **NEVER hardcode script/style tags** - Use `wp_enqueue_script/style`
- ❌ **NEVER use `parse_url()`** - Use `wp_parse_url()` instead (consistency across PHP versions)
- ❌ **NEVER use `http_build_query()`** - Use `http_build_url()` or WordPress alternatives
- ❌ **NEVER use `eval()`** - Use WordPress hooks and filters
- ❌ **NEVER use `unlink()`** - Use WordPress file system API `wp_delete_file()`
- ❌ **NEVER use `mkdir()`** - Use WordPress file system API `wp_mkdir_p()`
- ❌ **NEVER substitute wp_get_referer() for $_GET params** - These serve different purposes
- ❌ **NEVER change working code just to satisfy PHPCS** - Test functionality first
- ✅ **ALWAYS prefer WordPress equivalents** over native PHP functions

### Anti-Patterns to Avoid
- ❌ Modifying core files
- ❌ Not using `wp_reset_postdata()` after custom queries
- ❌ Direct database queries without sanitization
- ❌ Not checking return values before using them
- ❌ Suppressing errors with `@` operator
- ❌ Direct database table creation (use `dbDelta()`)
- ❌ Storing passwords in plain text (use `wp_hash_password()`)

### CRITICAL: NEVER SUPPRESS WARNINGS (phpcs disable)
- ❌ **NEVER use `phpcs:disable` or suppress ANY warnings**
- ❌ **NEVER ignore WordPress coding standard violations**
- ❌ If WordPress coding standards flag something, **FIX IT PROPERLY** instead of hiding it
- ❌ Replace slow queries with optimized alternatives
- ❌ Replace direct database queries with WordPress APIs
- ❌ Replace $_GET usage with proper WordPress admin detection
- ❌ **Code quality warnings exist for security and performance reasons - suppressing them creates vulnerabilities**
- ✅ **ALWAYS fix the underlying issue** that caused the warning
- ✅ **Better safe than sorry** - fix it right the first time

### PERFORMANCE: NEVER USE SLOW QUERIES
- ❌ **NEVER suppress slow query warnings** - fix the query instead
- ❌ **Avoid meta_query entirely** - use get_posts() + filtering instead
- ❌ **NO meta_key/meta_value queries** - they're always slow
- ❌ **NO meta_query in WP_Query** - performance killer
- ✅ **Use get_posts() + foreach loop** for meta filtering
- ✅ **Implement proper caching** for expensive operations
- ✅ **Limit post queries** with reasonable numberposts
- ✅ **Cache results aggressively** to reduce database load

### CRITICAL: PRESERVE FUNCTIONALITY WHEN REFACTORING
- **NEVER change working functionality** to satisfy PHPCS warnings
- **ALWAYS test functionality** before considering a fix "complete"
- **Common breaking mistakes to AVOID:**
  - ❌ Replacing `$_GET['param']` with `wp_get_referer()` (DESTROYS current page detection)
  - ❌ Using referrer functions for current page logic
  - ❌ Changing URL parsing logic without testing tabs/navigation  
  - ❌ Removing working code because "WordPress has alternatives"
- **Testing checklist for URL/param changes:**
  - ✅ Test admin page tabs work after changes
  - ✅ Test form submissions work after changes  
  - ✅ Test WordPress navigation after changes
  - ✅ Verify same functionality before/after changes
- **Functionality preservation rules:**
  - ✅ If PHPCS complains but functionality works, KEEP the working code
  - ✅ Only change code that's STILL FUNCTIONAL after the change
  - ✅ Test EVERY change that touches URL/form/navigation logic

### WORDPRESS FUNCTION MISMATCHES (COMMON BREAKING ERRORS)
- **wp_get_referer() vs $_GET parameters:**
  - ❌ wp_get_referer() gets REFERRING page URL (from HTTP Referer header)
  - ❌ $_GET['tab'] gets CURRENT page URL parameters  
  - ✅ These are COMPLETELY DIFFERENT things - never substitute one for the other
- **WordPress "alternatives" that break functionality:**
  - ❌ "We should use wp_get_referer() instead of $_GET" (WRONG - different purposes)
  - ❌ "WordPress has built-in functions, avoid superglobals" (WRONG - not always equivalent)
  - ✅ ALWAYS verify WordPress function does EXACTLY the same thing before replacing

### WORDPRESS SUPERGLOBAL HANDLING (MANDATORY PATTERNS)
- **ALWAYS use WordPress functions when available** (wp_get_referer(), not $_SERVER['HTTP_REFERER'])
- **For $_SERVER usage:** ALWAYS use proper WordPress pattern with wp_unslash():
```php
  // ❌ WRONG
  $referer = esc_url_raw($_SERVER['HTTP_REFERER']);
  
  // ✅ CORRECT - WordPress pattern
  $referer = isset($_SERVER['HTTP_REFERER']) 
      ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) 
      : '';
  ```
- **Security requirement:** wp_unslash() removes PHP magic quotes slashes before sanitization
- **Best practices:** 
  - Check isset() first, unslash, then sanitize
  - Use WordPress ternary pattern for clean code
  - Never chain superglobals directly to sanitization functions

## CACHE STRATEGIES

### WordPress Caching Mechanisms
- **Object Cache:** `wp_cache_get/set` for expensive operations
- **Transients:** Database-based caching with built-in expiration
- **Page Cache:** Use page caching plugins (WP Rocket, W3 Total Cache)
- **CDN Integration:** Support for CDN edge caching

### Cached Output Timing
- **Early Cache Headers:** Always set cache headers before any processing
- **Content Modifiers:** Apply filters to cached content upon creation
- **Dynamic Content:** Choose appropriate cache timing for data freshness vs performance

### Cache Invalidation Strategy
```php
// Clear specific caching layers on data change
wp_cache_flush(); // Clear object cache
wp_cache_delete_group_transients($group); // Clear transients
clear_page_cache(); // Clear opcode cache (if applicable)
```

### Output Buffer Management (if using)
```php
// Wrap output buffer around expensive operations only
ob_start();
$expensive_operation = do_heavy_processing();
$pre_headers = ob_get_clean();

// Set headers BEFORE output buffer starts
header('Content-Type: ' . $mime_type);
header('Cache-Control: max-age=' . $max_age);
```

## DEBUGGING RULES

### Never Break WordPress Core
- **No modifications to:** wp-config.php, wp-includes/, wp-admin/
- **Test in staging first:** Never test fixes on production site
- **Version compatibility:** Test with WordPress 5.9+ minimum
- **Hook priority:** Understand WordPress hook execution order

### Common Debugging Strategies
```php
// WordPress debugging tools
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Plugin-specific debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Plugin debug: ' . print_r($variable, true));
}
```

### Plugin Activation Troubleshooting
- **Activation hooks:** `register_activation_hook()` errors
- **Database errors:** Check table creation and constraints
- **Permission issues:** File/folder write permissions
- **Memory limits:** PHP memory limit and execution time

## CODE REVIEW CHECKLIST

### Pre-Development
- ✅ **Analyze Current Functionality:** Understand what exists before changing
- ✅ **Test Existing Workflow:** Verify current functionality works
- ✅ **Identify Impact:** What breaks if this change fails?

### During Development
- ✅ **Follow WordPress Standards:** Use proper hooks, filters, and APIs
- ✅ **Test Incrementally:** Test each change as you make it
- ✅ **Preserve Functionality:** Don't break existing features
- ✅ **Security First:** Sanitize input, escape output, use nonces
- ✅ **Performance Conscious:** Avoid slow queries and expensive operations

### Post-Development
- ✅ **Functionality Verification:** Test all affected features
- ✅ **Cross-Browser Testing:** Verify functionality across browsers
- ✅ **WordPress Version Testing:** Test on multiple WordPress versions
- ✅ **Plugin Compatibility:** Test with popular plugins installed
- ✅ **Code Quality:** No WordPress Coding Standards violations

### Testing Checklist
- ✅ **Admin interface works** (tabs, forms, navigation)
- ✅ **Form submissions preserve** all data correctly
- ✅ **AJAX endpoints respond** with expected data
- ✅ **Database queries run** without errors
- ✅ **Caching functions** store and retrieve correct data
- ✅ **JavaScript/CSS assets** load without conflicts
- ✅ **Security measures** (nonces, capability checks) working
- ✅ **Error handling** graceful degradation

### Performance Checklist  
- ✅ **DB Queries Minimum:** Database queries kept to absolute minimum
- ✅ **Query Optimization:** Avoid meta_query, use get_posts() + filtering
- ✅ **Caching Utilization:** Object cache and transients used appropriately  
- ✅ **Asset Optimization:** CSS/JS only loaded where needed
- ✅ **Memory Efficiency:** Large datasets handled efficiently
- ✅ **Load Time Impact:** Plugin adds minimal page load time

## PLUGIN DEPLOYMENT PROCESS (MODERN 2025)

### Version Management
1. **Version Numbers:** Follow semantic versioning (major.minor.patch)
2. **Changelog:** Maintain detailed changelog.md
3. **Git Tags:** Tag releases appropriately
4. **Readme:** Update readme.txt for each version

### Submission Preparation
1. **Code Review:** Self-review against WordPress Coding Standards
2. **Testing:** Comprehensive testing across WordPress versions
3. **Documentation:** Clear documentation and user instructions
4. **Security Audit:** Review for security vulnerabilities

### Directory Submission
1. **Plugin Header:** Proper plugin header comment
2. **Stable Tag:** Mark stable version appropriately
3. **Screenshots:** Update screenshots for latest UI
4. **Support Docs:** Clear support documentation

### Post-Deployment
1. **Monitor Feedback:** Watch for user reports
2. **Performance Monitoring:** Monitor plugin performance impact
3. **Security Updates:** Promptly address security issues
4. **WordPress Compatibility:** Ensure compatibility with new WordPress releases

---

**Remember:** These rules ensure your plugin meets WordPress.org directory standards and provides excellent user experience!

## DEVELOPMENT FRAMEWORK INTEGRATION

### Testing Framework Location
All development tools have been organized into `./developer-tools/` for portability:
- ✅ **Testing Tools**: `./developer-tools/testing/` - JS validation, REST API testing
- ✅ **Generic Rules**: `./developer-tools/rules/wordpress-development-rules.md` - Portable standards
- ✅ **Project Rules**: `./developer-tools/rules/betterfeed-specific-rules.md` - Plugin-specific patterns  
- ✅ **Documentation**: `./developer-tools/docs/testing-guide.md` - Comprehensive testing guide

### Testing Commands (Updated Paths)
- ✅ **JavaScript Validation**: `./developer-tools/testing/validate-js.sh`
- ✅ **REST API Testing**: `./developer-tools/testing/test-rest-api.sh`
- ✅ **Advanced Validation**: `node ./developer-tools/testing/wordpress-js-validator.js`

**PORTABILITY**: This entire framework can be copied to new WordPress projects and customised!

## WORDPRESS ADMIN SCREEN DEVELOPMENT MASTER LESSONS

**CRITICAL ADMIN RULES FROM HARD-LEARNED EXPERIENCE:**

### 1. ADMIN ASSET LOADING CRITICAL SUCCESS FACTORS
- ✅ **Hook Name MUST Match Exactly**: `'settings_page_bf-settings'` is EXACT format
- ✅ **Always Check Hook**: `if ('settings_page_bf-settings' !== $hook_suffix) return;`
- ✅ **No Dependencies First**: Start with `array()` empty dependencies for vanilla JS
- ✅ **HEAD Loading**: Use `false` for footer=false to load in HEAD for immediate execution

### 2. JAVASCRIPT ADMIN DEVELOPMENT ESSENTIALS
- ✅ **File Writing Issue**: The `write` tool can fail - use terminal commands as backup
- ✅ **JavaScript Execution Proof**: Always include immediate `alert()` and `console.log()`
- ✅ **WordPress Notices**: Use DOM manipulation, not browser alerts - create WordPress-style notices
- ✅ **Button State Management**: Disable buttons during processing, restore after completion

### 3. ADMIN SCREEN FORM ARCHITECTURE
- ✅ **Single Form**: ONE `<form>` tag wraps ALL settings to prevent multiple "Settings saved" messages
- ✅ **Settings Registration**: Always register ALL settings (never conditional registration)
- ✅ **Nonce Security**: Use `wp_create_nonce()` and `wp_nonce_field()` for form security
- ✅ **Form Action**: Point to same page: `action="<?php echo admin_url('options-general.php?page=bf-settings'); ?>"`

### 4. ADMIN NOTICE MANAGEMENT PROVEN PATTERNS
- ✅ **JavaScript Notices**: Create WordPress-style notices via DOM manipulation
- ✅ **Notice Positioning**: Insert after `.wrap h1` for proper WordPress admin styling
- ✅ **Notice Types**: Use `success`, `error`, `warning`, `info` classes properly
- ✅ **Dismissible**: Always include dismiss button for user experience

### 5. ADMIN DEBUGGING SURVIVAL STRATEGIES
- ✅ **Visual Debug**: Use colored banners/alerts for immediate verification
- ✅ **Cache Busting**: Add `?v=` timestamp to force fresh loads
- ✅ **Hook Verification**: Debug hook_suffix to ensure correct admin page targeting
- ✅ **File Path Debug**: Verify `BF_PLUGIN_URL` and file existence early

### 6. ADMIN TABS NAVIGATION MASTERY
- ✅ **Current Tab Detection**: Use `$_SERVER['REQUEST_URI']` with proper sanitization
- ✅ **Tab Highlighting**: Use `nav-tab-active` class for current tab
- ✅ **Tab Links**: Point to same page with `?page=bf-settings&tab=tagname`
- ✅ **Default Tab**: Handle empty/missing tab gracefully with fallback

**ADMIN DEVELOPMENT GOLDEN RULES:**
1. Test JavaScript loading FIRST with immediate alerts
2. Always verify WordPress notice HTML matches admin styling
3. Never skip hook_suffix checking for admin assets
4. Use terminal commands when file writing tools fail
5. Start simple, add complexity incrementally
6. WordPress admin notices beat browser alerts every time

### 7. JAVASCRIPT VALIDATION MANDATORY CHECKS
- ✅ **Syntax Validation**: ALWAYS run `node validate.js` after editing JavaScript
- ✅ **Common Error Patterns**: Check for function name typos, variable mismatches
- ✅ **Braces/Brackets**: Ensure all () and {} are properly matched
- ✅ **Function Names**: Verify consistent naming (showAdminNotice not showsAdminNotice)
- ✅ **Auto-Validation**: Run validation before committing any JavaScript changes

**JAVASCRIPT ERROR PREVENTION WORKFLOW:**
1. **Edit JavaScript** → Test syntax with `node -c filename.js`
2. **Run validator** → Execute `node validate.js` 
3. **Fix any errors** → Never commit broken JavaScript
4. **Test functionality** → Verify buttons work in browser
5. **Final check** → Run validator once more before completion

## WORDPRESS REST API DEVELOPMENT MASTER RULES

**CRITICAL REST API AUTHENTICATION MANDATORY CHECKS:**

### 1. REST API ENDPOINT DEVELOPMENT CRITICAL SUCCESS FACTORS
- ✅ **Permission Callback**: ALWAYS include `'permission_callback' => array($this, 'check_admin_permissions')`
- ✅ **User Capabilities**: Use `current_user_can('manage_options')` for admin-only endpoints
- ✅ **Nonce Generation**: Use `wp_create_nonce('wp_rest')` for REST API authentication
- ✅ **Route Registration**: Register ALL endpoints in `register_rest_routes()` method on `rest_api_init` hook

### 2. JAVASCRIPT REST API COMMUNICATION CRITICAL REQUIREMENTS
- ✅ **Credentials Include**: ALWAYS add `credentials: 'include'` for cookie authentication
- ✅ **REST Nonce**: Use `window.bf_config.nonce` from `wp_create_nonce('wp_rest')`
- ✅ **Error Handling**: Check `response.ok` and `Content-Type` headers before parsing JSON
- ✅ **Debug Logging**: Log response status, headers, and data for troubleshooting

### 3. REST API ERROR PREVENTION MANDATORY PATTERNS
- ✅ **401 Unauthorized**: Always caused by missing cookies - add `credentials: 'include'`
- ✅ **HTML Response**: Means 404/500 error page - check endpoint URL and registration
- ✅ **Parse Error '<'**: WordPress returning HTML instead of JSON - check authentication
- ✅ **CORS Issues**: Add proper headers and credentials for cross-origin requests

### 4. REST ENDPOINT TESTING CRITICAL WORKFLOW
- ✅ **cURL Test**: Always test endpoints with `curl -I "wp-json/betterfeed/v1/endpoint"`
- ✅ **Status Check**: Verify 200 OK, not 401/404/500
- ✅ **JSON Response**: Confirm Content-Type: application/json
- ✅ **Nonce Validation**: Test with proper X-WP-Nonce header

### 5. REST API SECURITY MANDATORY IMPLEMENTATION
- ✅ **Capability Check**: REST endpoints MUST check user capabilities
- ✅ **Nonce Verification**: WordPress automatically verifies wp_rest nonces
- ✅ **Input Validation**: Sanitize and validate all request parameters
- ✅ **Error Messages**: Never expose sensitive data in error responses

**REST API DEVELOPMENT GOLDEN RULES:**
1. **Test endpoints with cURL BEFORE adding JavaScript**
2. **Always include credentials: 'include' in fetch calls**
3. **Check response.ok before parsing JSON**  
4. **Log everything for debugging REST API issues**
5. **401 errors ALWAYS mean authentication problems**
6. **HTML responses ALWAYS mean endpoint/404 issues**

**CRITICAL REST API DEBUGGING CHECKLIST:**
1. **Endpoint exists?** → Check `wp-json/betterfeed/v1/endpoint` with browser
2. **Authentication works?** → Test logged-in vs logged-out responses  
3. **JavaScript configured?** → Verify `window.bf_config` and nonce presence
4. **Headers correct?** → Use `credentials: 'include'` and `X-WP-Nonce`
5. **Response type?** → Check `Content-Type: application/json`

### 6. REST API MISTAKE PREVENTION SYSTEMATIC APPROACH
- ❌ **Never**: Skip `credentials: 'include'` - causes 401 errors
- ❌ **Never**: Assume JSON response without checking Content-Type
- ❌ **Never**: Forget `permission_callback` in route registration
- ❌ **Never**: Use different nonce types (admin vs REST) inconsistently
- ❌ **Never**: Test JavaScript without first validating endpoint with cURL

**EMERGENCY REST API DEBUGGING COMMANDS:**
```bash
# Test endpoint accessibility
curl -I "http://localhost:8890/wp-json/betterfeed/v1/export-analytics"

# Test with authentication  
curl -I "http://localhost:8890/wp-json/betterfeed/v1/export-analytics" \
  -H "X-WP-Nonce: [your-nonce]"

# Debug WordPress REST API
curl "http://localhost:8890/wp-json/" | grep -i betterfeed

# Automated REST API testing
./test-rest-api.sh                    # Test all endpoints
./test-rest-api.sh http://yoursite.com  # Test remote site
```

**REST API VALIDATION INTERPRETATION GUIDE:**
- ✅ **401 Unauthorized**: Authentication working - endpoints exist and are protected
- ❌ **404 Not Found**: Route registration issue - check `register_rest_routes()` calls
- ❌ **200 OK**: No authentication required - security risk, add `permission_callback`
- ❌ **HTML Response**: WordPress is returning error pages, not JSON

**AUTOMATED TESTING PRE-JS DEVELOPMENT:**
1. **Run test script**: `./test-rest-api.sh` 
2. **Verify 401 responses**: Means endpoints exist and require auth
3. **Fix any 404s**: Route registration problems
4. **Then write JavaScript**: Only after endpoints are confirmed working
## 🚨 CRITICAL: REST API URL VALIDATION (NEVER REPEAT THE ERROR!)

### WORDPRESS REST API URL DISASTERS TO PREVENT

**❌ NEVER USE THESE URL PATTERNS:**
```javascript
// This becomes /wp-admin/wp-json/ in WordPress admin context!
fetch('./wp-json/betterfeed/v1/clear-cache')
// This causes path resolution errors!
fetch('../wp-json/betterfeed/v1/clear-cache')  
// This is completely wrong!
fetch('/wp-admin/wp-json/betterfeed/v1/clear-cache')
// This is context-dependent and unreliable!
fetch('wp-json/betterfeed/v1/clear-cache')
```

**✅ ALWAYS USE THESE ABSOLUTE URL PATTERNS:**
```javascript
// Always correct regardless of WordPress context!
fetch('/wp-json/betterfeed/v1/clear-cache')
fetch('/wp-json/betterfeed/v1/warm-cache')
fetch('/wp-json/betterfeed/v1/export-analytics')
fetch('/wp-json/betterfeed/v1/apply-preset')
```

### CRITICAL: BEFORE ANY WORDPRESS REST API CALL

**MANDATORY CHECKLIST:**
- [ ] URL starts with `/wp-json/` (NOT `./wp-json/` or `../wp-json/`)
- [ ] NEVER uses `/wp-admin/wp-json/` (this path doesn't exist!)
- [ ] Uses absolute URL from root ($/wp-json/...$)
- [ ] Includes namespace `/betterfeed/`
- [ ] Includes version `/v1/`
- [ ] Includes `credentials: 'include'` for WordPress auth
- [ ] Includes `X-WP-Nonce` header
- [ ] Has proper error handling with `.catch()`

### ERROR PREVENTION RULES

**PATTERN 7: REST API URL DISASTERS**
1. CRITICAL: Never use relative `wp-json` paths - they become `/wp-admin/wp-json/` in admin context
2. CRITICAL: Never use `/wp-admin/wp-json/` - this URL doesn't exist in WordPress!
3. CRITICAL: Always use absolute URLs `/wp-json/...` - works everywhere
4. MANDATORY: Test every fetch() URL manually - check Network tab
5. MANDATORY: Look for 404 vs 401 responses - 404 = wrong URL, 401 = auth issue

**WHY THIS ERROR HAPPENS:**
- WordPress admin pages run on `/wp-admin/*` URLs
- Relative `./wp-json/` resolves to `/wp-admin/wp-json/` in admin context
- `/wp-admin/wp-json/` doesn't exist - WordPress REST API is at `/wp-json/`
- Only absolute `/wp-json/` works everywhere

**FAILURE IMMEDIATELY IF:**
- Any fetch() contains `./wp-json/` or `../wp-json/`
- Any fetch() contains `/wp-admin/wp-json/`
- Missing version `/v1/` in REST API calls
- Missing `credentials: 'include'` for WordPress auth

This prevents the exact 404 error that took us 6 steps to debug!

## 🚨 CRITICAL: SETTINGS SAVED MESSAGE REGRESSION PREVENTION

### NEVER REMOVE DUPLICATE MESSAGE FILTERING

**CRITICAL RULE:** The `suppress_duplicate_settings_messages()` method and `removable_query_args` filter are MANDATORY and must NEVER be removed!

**WHY THIS EXISTS:**
- We have 5 separate `register_setting()` calls for different option groups
- Each `register_setting()` generates its own "Settings saved" message  
- Without filtering, users see 5 duplicate messages
- This creates a terrible user experience

**WHAT MUST ALWAYS STAY:**
```php
// In register_settings() method:
add_filter('removable_query_args', array($this, 'suppress_duplicate_settings_messages'));

// At end of class:
public function suppress_duplicate_settings_messages($args) {
    // Suppress duplicate settings-updated messages
    // Keep only the first occurrence, remove extras
}
```

**FAILURE CONDITIONS:**
- If this filtering is removed, users get 5 "Settings saved" messages
- This is a critical UX regression
- NEVER remove this filtering without replacing it with equivalent functionality

**REGENERATION PREVENTION:**
- Always test settings page after ANY admin changes
- Look for multiple "Settings saved" messages
- If you see duplicates, the filtering is missing or broken
- This is a ZERO-TOLERANCE issue - must be fixed immediately

This prevents the exact regression that just occurred!

## 🚨 CRITICAL: WORDPRESS SETTINGS MESSAGES REGRESSION PREVENTION

### NEVER ADD EXPLICIT settings_errors() CALLS

**CRITICAL RULE:** NEVER call `settings_errors()` explicitly on WordPress Settings pages!

**WHY THIS CAUSES DUPLICATES:**
- WordPress automatically displays settings messages on Settings pages
- Explicit `settings_errors()` call creates a second identical message
- Result: User sees duplicate "Settings saved" messages

**WHAT CAUSED THE REGRESSION:**
```php
// ❌ NEVER DO THIS - Causes duplicate messages!
<?php settings_errors(); ?>
```

**WHAT WORDPRESS DOES AUTOMATICALLY:**
- WordPress automatically shows settings messages on Settings pages
- No manual intervention needed
- Just let WordPress handle it

**PREVENTION RULES:**
1. NEVER add `<?php settings_errors(); ?>` to Settings pages
2. WordPress handles settings messages automatically
3. Only use `settings_errors()` on non-Settings pages if needed
4. Test settings pages after ANY admin template changes

**FAILURE CONDITIONS:**
- If `settings_errors()` appears in admin template code
- If users see duplicate "Settings saved" messages
- This is a ZERO-TOLERANCE issue

**THE FIX:**
Simply remove any explicit `settings_errors()` calls from Settings page templates.

This prevents the exact duplicate message issue that just occurred!
