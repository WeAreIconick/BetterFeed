# WordPress Plugin Testing Framework

**Comprehensive Testing Guide for WordPress Plugin Development**

## Overview

This testing framework provides automated validation and testing tools for WordPress plugin development, ensuring quality, security, and functionality.

## Testing Tools

### JavaScript Validation (`validate-js.sh`)

**Purpose**: Ensures JavaScript files are syntactically correct and free of common errors.

**Usage**:
```bash
# Validate admin.js (default)
./developer-tools/testing/validate-js.sh

# Validate specific file
./developer-tools/testing/validate-js.sh assets/js/custom.js
```

**What it checks**:
- Syntax errors (missing braces, parentheses)
- Common typos (showsAdminNotice → showAdminNotice)
- Variable naming issues (clearCHacheBtn → clearCacheBtn)
- Function name consistency

### REST API Testing (`test-rest-api.sh`)

**Purpose**: Validates WordPress REST API endpoints are properly configured and accessible.

**Usage**:
```bash
# Test local development
./developer-tools/testing/test-rest-api.sh

# Test remote site
./developer-tools/testing/test-rest-api.sh http://yoursite.com
```

**What it checks**:
- WordPress REST API availability
- Namespace registration
- Individual endpoint accessibility
- Authentication requirements
- Response format validation

### Advanced JavaScript Validator (`wordpress-js-validator.js`)

**Purpose**: Comprehensive JavaScript validation with WordPress-specific error detection.

**Usage**:
```bash
node developer-tools/testing/wordpress-js-validator.js
```

**Features**:
- Multiple file validation
- WordPress-specific error patterns
- Variable naming convention checks
- File existence validation
- Detailed error reporting

## Testing Workflow

### Pre-Development Testing
1. **Run REST API tests** to verify endpoint configuration
2. **Validate JavaScript syntax** before writing functionality
3. **Check authentication** for protected endpoints

### Development Testing
1. **Syntax check** after each JavaScript edit
2. **Test endpoints** before JavaScript integration
3. **Verify authentication** flow works correctly

### Pre-Deployment Testing
1. **Run full validation suite**
2. **Test on different environments**
3. **Verify functionality** across browsers
4. **Check performance impact**

## Expected Results Guide

### REST API Testing Results

| Response | Meaning | Action Required |
|----------|---------|----------------|
| 200 OK | Successful response | ✅ Good |
| 400 Bad Request | Invalid parameters | ⚠️ Check request format |
| 401 Unauthorized | Authentication required | ✅ Expected for protected endpoints |
| 403 Forbidden | Permission denied | ⚠️ Check user capabilities |
| 404 Not Found | Route not registered | ❌ Fix route registration |
| 500 Server Error | Internal error | ❌ Check callback functions |
| HTML Response | Error page returned | ❌ Check URL and WordPress setup |

### JavaScript Validation Results

| Error Type | Example | Solution |
|------------|---------|----------|
| Syntax | `missing )` | Check braces and parentheses |
| Function Name | `showsAdminNotice` | Correct to `showAdminNotice` |
| Variable Name | `clearCHacheBtn` | Correct to `clearCacheBtn` |
| Method Typo | `addEventListener further` | Correct to `addEventListener` |

## Integration with CI/CD

### GitHub Actions Example
```yaml
name: WordPress Plugin Testing
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Test REST API
        run: ./developer-tools/testing/test-rest-api.sh
      - name: Validate JavaScript
        run: ./developer-tools/testing/validate-js.sh
```

### Git Hooks
```bash
#!/bin/sh
# pre-commit hook
./developer-tools/testing/validate-js.sh && \
./developer-tools/testing/test-rest-api.sh
```

## Troubleshooting Common Issues

### REST API Issues
- **401 Errors**: Add `credentials: 'include'` to fetch requests
- **404 Errors**: Check route registration in WordPress
- **HTML Responses**: Verify WordPress REST API is enabled
- **CORS Issues**: Configure proper authentication headers

### JavaScript Issues
- **Syntax Errors**: Use validator to check common patterns
- **Undefined Variables**: Verify variable name consistency
- **Function Errors**: Check function naming conventions
- **Execution Issues**: Test with immediate alerts for debugging

## Customization

### Adding New Endpoints
1. Update `test-rest-api.sh` with new endpoint names
2. Modify expected response validation
3. Test authentication requirements

### Adding JavaScript Rules
1. Edit `wordpress-js-validator.js` with new patterns
2. Add error message definitions
3. Update validation workflow documentation

---

**Framework Version**: 1.0.0  
**Compatibility**: WordPress 6.0+  
**Last Updated**: January 2025
