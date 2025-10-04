# WordPress Plugin Framework Migration Guide

**Complete Guide for Porting Development Framework to New WordPress Projects**

## Overview

This guide shows you how to take the complete development framework from BetterFeed and use it in any new WordPress plugin project. The framework provides automated testing, coding standards, and best practices.

## Migration Methods

### Method 1: Automated Framework Setup (Easiest)

For new WordPress plugin projects:

```bash
# 1. Create new plugin directory
mkdir /path/to/wp-content/plugins/my-awesome-plugin
cd /path/to/wp-content/plugins/my-awesome-plugin

# 2. Initialize WordPress plugin structure
echo "<?php
/**
 * Plugin Name: My Awesome Plugin
 * Description: Description of my awesome plugin.
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}" > my-awesome-plugin.php

# 3. Copy framework from BetterFeed
cp -r /path/to/betterfeed/developer-tools ./

# 4. Configure framework for new project
./developer-tools/setup-new-project.sh .

# 5. Run complete test suite
./developer-tools/run-all-tests.sh my-awesome-plugin
```

### Method 2: Manual Framework Migration

#### Step 1: Copy Framework Files

```bash
# Copy entire developer-tools directory
cp -r betterfeed/developer-tools/ new-plugin/developer-tools/

# Make scripts executable
chmod +x new-plugin/developer-tools/testing/*.sh
chmod +x new-plugin/developer-tools/setup-new-project.sh
chmod +x new-plugin/developer-tools/run-all-tests.sh
```

#### Step 2: Update Configuration

**Update JavaScript Validator** (`testing/wordpress-js-validator.js`):
```javascript
// Change this line:
const filesToCheck = [
    '/Users/nick/Documents/GitHub/betterfeed/assets/js/admin.js'  // OLD PATH
];

// To:
const filesToCheck = [
    '/path/to/new-plugin/assets/js/admin.js'  // NEW PATH
];
```

**Update REST API Tester** (`testing/test-rest-api.sh`):
```bash
# Change namespace in script:
NAMESPACE="betterfeed/v1"  # OLD NAMESPACE

# To:
NAMESPACE="my-awesome-plugin/v1"  # NEW NAMESPACE
```

#### Step 3: Update Plugin-Specific Rules

Copy and customize rules for your plugin:

```bash
# Copy framework rules
cp developer-tools/rules/wordpress-development-rules.md developer-tools/rules/generic-wordpress-rules.md

# Create plugin-specific rules
cp developer-tools/rules/betterfeed-specific-rules.md developer-tools/rules/my-plugin-rules.md

# Edit my-plugin-rules.md with your plugin details:
# - Plugin slug/namespace
# - Class naming conventions  
# - REST API endpoints
# - File organization patterns
```

### Method 3: Framework Only (Minimal Setup)

For existing WordPress plugins:

```bash
# Just copy testing tools (no plugin files)
cp betterfeed/developer-tools/testing/ new-plugin/testing/
cp betterfeed/developer-tools/run-all-tests.sh new-plugin/

# Update paths and run
cd new-plugin
# Edit run-all-tests.sh paths as needed
./run-all-tests.sh
```

## Testing Framework Features

### Automated Test Suite (`run-all-tests.sh`)

**Runs Complete Quality Analysis:**

1. **JavaScript Syntax Validation**
   - Node.js syntax checking
   - Common typo detection
   - WordPress-specific error patterns

2. **REST API Endpoint Testing**
   - Endpoint accessibility
   - Authentication requirements
   - Response format validation

3. **WordPress Environment Checks**
   - WordPress REST API connectivity
   - Admin panel accessibility
   - File permissions validation

4. **Advanced Feature Testing**
   - Error response validation
   - Security header verification
   - Performance impact testing

### Usage Examples

```bash
# Auto-detect project and run all tests
./developer-tools/run-all-tests.sh

# Specific project testing
./developer-tools/run-all-tests.sh my-plugin

# Show detailed help
./developer-tools/run-all-tests.sh @

# Individual test components
./developer-tools/testing/validate-js.sh           # JavaScript only
./developer-tools/testing/test-rest-api.sh         # REST API only
node developer-tools/testing/wordpress-js-validator.js  # Advanced JS analysis
```

## Configuration Customization

### Plugin-Specific Adaptations

**REST API Namespace Configuration:**
```javascript
// In your WordPress plugin main file
add_action('rest_api_init', function() {
    register_rest_route('my-plugin/v1', '/my-endpoint', array(
        'methods' => 'POST',
        'callback' => array($this, 'my_callback'),
        'permission_callback' => array($this, 'check_admin_permissions'),
    ));
});
```

**JavaScript Configuration Updates:**
```javascript
// Update JavaScript validator patterns for your plugin
const checks = [
    { pattern: /myPluginFunction/g, message: 'Function name: myPluginFunction' },
    { pattern: /my_plugin_btn/g, message: 'Button: my_plugin_btn' }
];
```

### CI/CD Integration

**GitHub Actions Example:**
```yaml
name: WordPress Plugin Quality Assurance
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup WordPress Testing Environment
        run: |
          # Setup WordPress locally
          # Install plugin
      - name: Run Complete Test Suite
        run: ./developer-tools/run-all-tests.sh
      - name: Upload Test Results
        uses: actions/upload-artifact@v2
        with:
          name: test-results
          path: test-results-*.log
```

**Git Pre-commit Hook:**
```bash
#!/bin/sh
# .git/hooks/pre-commit
echo "Running WordPress Plugin Quality Tests..."
./developer-tools/run-all-tests.sh
if [ $? -ne 0 ]; then
    echo "❌ Tests failed. Commit blocked."
    exit 1
fi
echo "✅ All tests passed!"
```

## Advanced Usage

### Extending the Framework

**Adding Custom Validators:**

1. Create new validator in `testing/custom-validator.sh`
2. Update `run-all-tests.sh` to include new test
3. Document new validator in framework rules

**Project-Specific Validation:**

1. Add plugin-specific patterns to JavaScript validator
2. Update REST API endpoints list for testing
3. Create custom WordPress environment checks

### Multi-Environment Testing

```bash
# Test on multiple WordPress environments
for env in dev staging production; do
    echo "Testing on $env environment..."
    ./developer-tools/run-all-tests.sh $env
done
```

## Troubleshooting

### Common Issues

**Permission Errors:**
```bash
# Fix script permissions
chmod +x developer-tools/run-all-tests.sh
chmod +x developer-tools/testing/*.sh
```

**Path Issues:**
```bash
# Debug path problems
./developer-tools/run-all-tests.sh @  # Shows configuration
ls -la developer-tools/testing/       # Verify files exist
```

**WordPress Connection Issues:**
```bash
# Test WordPress connectivity
curl -I http://your-wordpress-site.com/wp-json/
curl -I http://your-wordpress-site.com/wp-admin/
```

## Benefits of Framework Adoption

### Quality Assurance
- ✅ Consistent code quality across projects
- ✅ Automated error detection and prevention
- ✅ WordPress best practices enforcement

### Development Speed
- ✅ Ready-to-use testing infrastructure
- ✅ Automated validation reduces debugging time
- ✅ Professional development workflow

### Maintenance
- ✅ Standardized troubleshooting procedures
- ✅ Easy CI/CD integration
- ✅ Comprehensive documentation

---

**Framework Version**: 1.0.0  
**WordPress Compatibility**: 6.0+  
**Licensed**: WordPress Plugin Development Standards

**Support**: See individual script documentation for detailed usage instructions.
