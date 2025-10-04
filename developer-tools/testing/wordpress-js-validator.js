#!/usr/bin/env node
/**
 * JavaScript Validation Script  
 * Run: node validate.js
 */

const fs = require('fs');
const path = require('path');

const filesToCheck = [
    '/Users/nick/Documents/GitHub/betterfeed/assets/js/admin.js'
];

console.log('🔍 JavaScript Validation Starting...\n');

let hasErrors = false;

filesToCheck.forEach(file => {
    console.log(`📁 Checking: ${path.basename(file)}`);
    
    if (!fs.existsSync(file)) {
        console.log(`❌ File not found: ${file}\n`);
        hasErrors = true;
        return;
    }
    
    try {
        // Syntax check using node -c equivalent 
        const { spawnSync } = require('child_process');
        const result = spawnSync('node', ['-c', file], { 
            encoding: 'utf8',
            cwd: '/Users/nick/Documents/GitHub/betterfeed'
        });
        
        if (result.stderr && result.stderr.trim()) {
            console.log(`❌ Syntax Error:`);
            console.log(`   ${result.stderr.trim()}\n`);
            hasErrors = true;
            return;
        }
        
        console.log(`✅ Syntax: Valid`);
        
        // Read content for additional checks
        const content = fs.readFileSync(file, 'utf8');
        
        // Common error patterns
        const checks = [
            { pattern: /showsAdminNotice/g, message: 'Function name: "showsAdminNotice" -> "showAdminNotice"' },
            { pattern: /windo1\.URL/g, message: 'Typo: "windo1" -> "window"' },
            { pattern: /clearCHacheBtn/g, message: 'Variable: "clearCHacheBtn" -> "clearCacheBtn"' },
            { pattern: /document\.addEventListener further/g, message: 'Method: "addEventListener further" -> "addEventListener"' },
            { pattern: /\{\}\)/g, message: 'Braces: Check for } followed by )' },
            { pattern: /btn\.textContent = 'SuccessApplying'/g, message: 'Text: Remove "Success" prefix' }
        ];
        
        checks.forEach(check => {
            if (check.pattern.test(content)) {
                console.log(`⚠️  ${check.message}`);
                hasErrors = true;
            }
        });
        
        console.log(`✅ All checks passed\n`);
        
    } catch (error) {
        console.log(`❌ Error checking ${file}: ${error.message}\n`);
        hasErrors = true;
    }
});

if (hasErrors) {
    console.log('🚨 VALIDATION FAILED! Fix errors before proceeding.');
    process.exit(1);
} else {
    console.log('🎉 ALL CHECKS PASSED! JavaScript is clean!');
    process.exit(0);
}
