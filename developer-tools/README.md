# WordPress Developer Tools Framework

**Portable Testing and Standards Framework for WordPress Plugin Development**

## Quick Start

### Method 1: Automated Setup (Recommended)
```bash
# From BetterFeed project
cd /path/to/new-wordpress-plugin
/path/to/betterfeed/developer-tools/setup-new-project.sh .

# Then run complete test suite
./developer-tools/run-all-tests.sh
```

### Method 2: Manual Setup
1. **Copy this directory** to your new WordPress project
2. **Update file paths** in testing scripts for your project structure  
3. **Run complete tests**: `./developer-tools/run-all-tests.sh`

### Method 3: Single Command Testing
```bash
# Run entire test suite with one command
./developer-tools/run-all-tests.sh [plugin-slug]

# Auto-detect project
./developer-tools/run-all-tests.sh

# Show help
./developer-tools/run-all-tests.sh @
```

## Directory Structure

```
developer-tools/
├── testing/           # Testing and validation tools
│   ├── validate-js.sh                    # Quick JavaScript validation
│   ├── test-rest-api.sh                  # REST API endpoint testing
│   ├── wordpress-js-validator.js         # Advanced JavaScript validator
│   ├── advanced-js-monitor.js           # Enhanced error prevention monitoring
│   └── live-api-monitor.sh              # Real-time REST API monitoring
├── rules/            # Development standards and rules
│   ├── wordpress-development-rules.md    # Generic WordPress standards
│   └── betterfeed-specific-rules.md      # Plugin-specific patterns
├── docs/             # Documentation and guides
│   └── testing-guide.md                   # Comprehensive testing guide
└── README.md         # This file
```

## Testing Tools

### JavaScript Validation
```bash
# Quick syntax check
./testing/validate-js.sh

# Comprehensive validation
node testing/wordpress-js-validator.js
```

### REST API Testing
```bash
# Test local development
./testing/test-rest-api.sh

# Test remote site
./testing/test-rest-api.sh http://your-site.com
```

## Development Standards

### Workflow Integration
- **Pre-development**: Test REST endpoints before JavaScript
- **Development**: Validate JavaScript syntax after each change
- **Pre-deployment**: Run full validation suite

### WordPress Best Practices
- Use WordPress functions over native PHP
- Implement proper authentication and security
- Follow WordPress coding standards
- Optimize performance and caching

## Customization

Quick project setup:
1. Copy `../rules/betterfeed-specific-rules.md` to your project
2. Rename and customize for your plugin's namespace
3. Update testing scripts with your endpoint names
4. Modify validation patterns for your coding style

## Contributing

To extend this framework:
1. **Add new validation patterns** to `wordpress-js-validator.js`
2. **Update REST API tests** in `test-rest-api.sh`
3. **Document new standards** in appropriate rules files

---

**Framework Version**: 1.0.0  
**WordPress Compatible**: 6.0+  
**Last Updated**: January 2025
