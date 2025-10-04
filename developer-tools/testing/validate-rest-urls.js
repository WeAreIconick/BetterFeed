#!/usr/bin/env node
/**
 * REST API URL Validation Tool
 * Prevents the exact URL error we experienced!
 */

const fs = require('fs');
const path = require('path');

const FORBIDDEN_URL_PATTERNS = [
    {
        name: 'Relative wp-json paths',
        pattern: /fetch\s*\(\s*['"]\.\/wp-json/g,
        error: 'CRITICAL: "./wp-json/" becomes "/wp-admin/wp-json/" in admin context!'
    },
    {
        name: 'Parent directory wp-json paths', 
        pattern: /fetch\s*\(\s*['"]\.\.\/wp-json/g,
        error: 'CRITICAL: "../wp-json/" causes path resolution errors!'
    },
    {
        name: 'Wrong admin API paths',
        pattern: /fetch\s*\(\s*['"]\/wp-admin\/wp-json/g,
        error: 'CRITICAL: "/wp-admin/wp-json/" is WRONG! Should be "/wp-json/"'
    },
    {
        name: 'Missing leading slash',
        pattern: /fetch\s*\(\s*['"]wp-json/g,
        error: 'ERROR: Missing leading slash "wp-json" becomes context-dependent!'
    }
];

const REQUIRED_URL_PATTERNS = [
    {
        name: 'Correct WordPress REST API URLs',
        pattern: /fetch\s*\(\s*['"]\/wp-json\/([^'"]+)/g,
        validate: function(match) {
            const fullUrl = match[1];
            if (!fullUrl.includes('v1')) {
                return 'WARNING: REST API URL should include version "/v1/"';
            }
            return null;
        }
    }
];

function analyzeJavaScriptFile(filePath) {
    console.log(`🔍 Analyzing REST URL patterns in: ${path.basename(filePath)}`);
    
    if (!fs.existsSync(filePath)) {
        console.log(`❌ File not found: ${filePath}`);
        return { errors: [], warnings: [] };
    }
    
    const content = fs.readFileSync(filePath, 'utf8');
    const errors = [];
    const warnings = [];
    
    // Check forbidden patterns
    FORBIDDEN_URL_PATTERNS.forEach(check => {
        const regex = new RegExp(check.pattern.source, 'g');
        let match;
        while ((match = regex.exec(content)) !== null) {
            errors.push({
                message: check.error,
                line: getLineNumber(content, match.index),
                context: match[0]
            });
        }
    });
    
    // Check required patterns
    REQUIRED_URL_PATTERNS.forEach(check => {
        const regex = new RegExp(check.pattern.source, 'g');
        let match;
        while ((match = regex.exec(content)) !== null) {
            if (check.validate) {
                const result = check.validate(match);
                if (result) {
                    warnings.push({
                        message: result,
                        line: getLineNumber(content, match.index),
                        context: match[0]
                    });
                }
            }
        }
    });
    
    return { errors, warnings };
}

function getLineNumber(content, index) {
    return content.substring(0, index).split('\n').length;
}

function printValidationResults(results, filePath) {
    const { errors, warnings } = results;
    
    console.log(`📋 ${path.basename(filePath)} REST URL Validation:`);
    console.log('==================================================');
    
    if (errors.length === 0 && warnings.length === 0) {
        console.log('✅ All REST API URLs are correctly formatted!');
        return true;
    }
    
    // Print critical errors
    errors.forEach(item => {
        console.log(`❌ CRITICAL ERROR (Line ${item.line}): ${item.message}`);
        console.log(`   Context: ${item.context}`);
    });
    
    // Print warnings
    warnings.forEach(item => {
        console.log(`⚠️  WARNING (Line ${item.line}): ${item.message}`);
        console.log(`   Context: ${item.context}`);
    });
    
    console.log('');
    console.log(`📊 REST URL Summary: ${errors.length} critical error${errors.length !== 1 ? 's' : ''}, ${warnings.length} warning${warnings.length !== 1 ? 's' : ''}`);
    
    return errors.length === 0;
}

function main() {
    const projectDir = path.dirname(process.cwd());
    const jsFiles = [
        path.join(path.dirname(path.dirname(__dirname)), 'assets/js/admin.js')
    ];
    
    console.log('🚨 REST API URL Validation Tool');
    console.log('🛡️ Preventing the /wp-admin/wp-json/ disaster!');
    console.log('=================================================\n');
    
    let allPassed = true;
    
    for (const filePath of jsFiles) {
        if (fs.existsSync(filePath)) {
            const results = analyzeJavaScriptFile(filePath);
            const passed = printValidationResults(results, filePath);
            allPassed = allPassed && passed;
            console.log('');
        } else {
            console.log(`⚠️  File not found: ${path.basename(filePath)}`);
        }
    }
    
    if (!allPassed) {
        console.log('🚨 CRITICAL REST URL ERRORS DETECTED!');
        console.log('Fix these immediately to prevent 404 errors!');
        console.log('# 💡 QUICK FIXES:');
        console.log('# Replace "./wp-json/" with "/wp-json/"');
        console.log('# Replace "/wp-admin/wp-json/" with "/wp-json/"');
        console.log('# Add "/v1/" version to all endpoints');
        process.exit(1);
    } else {
        console.log('🎉 All REST API URLs are perfectly formatted!');
        console.log('✅ No 404 errors from wrong URLs!');
        process.exit(0);
    }
}

if (require.main === module) {
    main();
}

module.exports = { analyzeJavaScriptFile, FORBIDDEN_URL_PATTERNS };
