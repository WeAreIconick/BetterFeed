#!/usr/bin/env node
/**
 * Advanced JavaScript Monitoring & Error Prevention
 * Now includes REST API URL validation!
 */

const fs = require('fs');
const path = require('path');

const VALIDATION_PATTERNS = {
    variableConsistency: [
        {
            name: 'Variable Declaration vs Usage',
            pattern: /var\s+(\w+)\s*=\s*[^;]+;\s*.*if\s*\(\s*(\w+)\s*&&/gs,
            check: function(match) {
                const declared = match[1];
                const used = match[2];
                if (declared !== used) {
                    return `CRITICAL: Variable "${declared}" declared but "${used}" used in same block!`;
                }
                return null;
            }
        }
    ],
    
    restApiPatterns: [
        {
            name: 'Incorrect REST API URLs',
            pattern: /fetch\s*\(\s*['"]\.\/wp-admin\/wp-json/g,
            check: function() {
                return 'CRITICAL: INCORRECT REST API URL! Should be "/wp-json/" NOT "/wp-admin/wp-json/"';
            }
        },
        {
            name: 'Relative vs Absolute URLs',
            pattern: /fetch\s*\(\s*['"]\.\.\/wp-json/g,
            check: function() {
                return 'WARNING: Using relative URL "../wp-json/" might cause issues. Consider "/wp-json/"';
            }
        },
        {
            name: 'WordPress REST API URL Format',
            pattern: /fetch\s*\(\s*['"]\/wp-json\/([^'"]+)\//g,
            check: function(match) {
                const namespace = match[1];
                if (!namespace.includes('/v')) {
                    return `WARNING: REST API namespace "${namespace}" should include version like "/v1"`;
                }
                return null;
            }
        }
    ],

    wordpressPatterns: [
        {
            name: 'Missing Credentials Include',
            pattern: /fetch\([^)]+\s*\{[^}]*credentials:\s*['"][^'"]*['"]/g,
            check: function(match) {
                if (!match[0].includes('credentials: \'include\'')) {
                    return 'CRITICAL: WordPress REST API requires credentials: "include" for authentication';
                }
                return null;
            }
        },
        {
            name: 'Missing Error Handling',
            pattern: /fetch\([^)]+\)[^{]*\.then\s*\([^)]*\)[^.]*(?!\.catch)/g,
            check: function() {
                return 'WARNING: Fetch calls should include .catch() error handling';
            }
        }
    ]
};

function analyzeFileContent(filePath) {
    console.log(`🔍 Analyzing: ${path.basename(filePath)}`);
    
    if (!fs.existsSync(filePath)) {
        console.log(`❌ File not found: ${filePath}`);
        return { errors: [], warnings: [], suggestions: [] };
    }
    
    const content = fs.readFileSync(filePath, 'utf8');
    const errors = [];
    const warnings = [];
    
    // Check each category
    const allChecks = [
        ...VALIDATION_PATTERNS.variableConsistency,
        ...VALIDATION_PATTERNS.restApiPatterns,
        ...VALIDATION_PATTERNS.wordpressPatterns
    ];
    
    for (const check of allChecks) {
        let match;
        const regex = new RegExp(check.pattern.source, check.pattern.flags || 'g');
        
        while ((match = regex.exec(content)) !== null) {
            let result;
            if (typeof check.check === 'function') {
                result = check.check(match);
            } else {
                result = check.message;
            }
            
            if (result) {
                const type = result.includes('CRITICAL') ? 'errors' : 'warnings';
                
                eval(type).push({
                    check: check.name,
                    message: result,
                    line: getLineNumber(content, match.index)
                });
            }
        }
    }
    
    return { errors, warnings };
}

function getLineNumber(content, index) {
    return content.substring(0, index).split('\n').length;
}

function printResults(results, filePath) {
    const { errors, warnings } = results;
    
    console.log(`📋 ${path.basename(filePath)} Analysis Results:`);
    console.log('------------------------------------------------');
    
    if (errors.length === 0 && warnings.length === 0) {
        console.log('✅ No issues detected!');
        return true;
    }
    
    // Print errors
    errors.forEach(item => {
        console.log(`❌ ${item.check} (Line ${item.line}): ${item.message}`);
    });
    
    // Print warnings  
    warnings.forEach(item => {
        console.log(`⚠️  ${item.check} (Line ${item.line}): ${item.message}`);
    });
    
    console.log(`📊 Summary: ${errors.length} error${errors.length !== 1 ? 's' : ''}, ${warnings.length} warning${warnings.length !== 1 ? 's' : ''}`);
    
    return errors.length === 0 && warnings.length === 0;
}

function main() {
    const projectDir = process.cwd();
    const jsFiles = [
        path.join(projectDir, 'assets/js/admin.js')
    ];
    
    console.log('🔍 Advanced JavaScript Monitoring & Error Prevention');
    console.log('🔗 Now includes REST API URL validation!');
    console.log('=====================================================\n');
    
    let allPassed = true;
    
    for (const filePath of jsFiles) {
        if (fs.existsSync(filePath)) {
            const results = analyzeFileContent(filePath);
            const passed = printResults(results, filePath);
            allPassed = allPassed && passed;
            console.log('');
        }
    }
    
    if (!allPassed) {
        console.log('🚨 JavaScript quality issues detected! Review and fix before proceeding.\n');
        process.exit(1);
    } else {
        console.log('🎉 All JavaScript files pass advanced quality checks!\n');
        process.exit(0);
    }
}

if (require.main === module) {
    main();
}

module.exports = { analyzeFileContent, VALIDATION_PATTERNS };
