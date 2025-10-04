# BetterFeed Plugin Specific Rules

**Plugin-Specific Configuration and Standards**

## BetterFeed-Specific Patterns

### Plugin Constants
- ✅ **Version**: Always increment for cache busting (BF_VERSION)
- ✅ **URL Constants**: Use BF_PLUGIN_URL and BF_PLUGIN_BASENAME consistently
- ✅ **Namespace**: Use 'betterfeed/v1' for all REST API routes

### BetterFeed Class Patterns
- ✅ **Singleton Pattern**: Use `BF_Class::instance()` for all classes
- ✅ **Hook Management**: Use `BF_Admin::$hooks_initialized` to prevent duplicates
- ✅ **Analytics Integration**: Use `BF_Analytics::instance()` for data collection

### REST API Endpoints
- ✅ **Base Path**: `/wp-json/betterfeed/v1/`
- ✅ **Endpoints**: clear-cache, warm-cache, export-analytics, apply-preset, export-settings
- ✅ **Authentication**: Use `check_admin_permissions()` with `current_user_can('manage_options')`

### Analytics Data Management
- ✅ **Storage**: Use WordPress transients for analytics data
- ✅ **Export Formats**: Support CSV and JSON formats
- ✅ **Data Retention**: Use `DAY_IN_SECONDS` for temporary storage
- ✅ **Cleanup**: Implement daily cron cleanup for old analytics data

### Cache Management
- ✅ **Cache Clearing**: Use WordPress cache functions (`wp_cache_delete()`, `delete_transient()`)
- ✅ **Transient Management**: Iterate through `wp_load_alloptions()` for manual deletion
- ✅ **Performance**: Avoid direct database queries in favor of WordPress APIs

### Admin Interface Standards
- ✅ **Tab Navigation**: Use 'bf-settings' page with tab system
- ✅ **Button IDs**: Prefix all buttons with 'bf-' (bf-clear-cache, bf-warm-cache, etc.)
- ✅ **Form Fields**: Use 'bf-' prefix for all form field names
- ✅ **JavaScript**: Use 'bf-' prefix for JavaScript variables and functions

### File Organization
- ✅ **Classes**: All in /includes/ with class-bf- prefix
- ✅ **Assets**: CSS and JS in /assets/ with version numbers
- ✅ **Templates**: PHP templates in /templates/ directory
- ✅ **Languages**: Translation files in /languages/ directory

### Testing Configuration
- ✅ **Analytics Testing**: Generate sample analytics data for testing
- ✅ **Export Testing**: Test CSV and JSON export functionality
- ✅ **Cache Testing**: Verify cache clearing and warming operations
- ✅ **Admin Testing**: Test all admin buttons and functionality

---

**Note**: These rules are specific to BetterFeed plugin architecture and should not be copied to other projects without modification.
