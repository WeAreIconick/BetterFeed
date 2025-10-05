/**
 * Feature Completeness Validation Script
 * 
 * This script validates that all user-facing features are fully implemented
 * by checking for onclick handlers and their corresponding JavaScript functions.
 */

const fs = require('fs');
const path = require('path');

console.log('🔍 Feature Completeness Validation');
console.log('=====================================\n');

// Read the admin.js file
const adminJsPath = path.join(__dirname, '../../assets/js/admin.js');
const adminJsContent = fs.readFileSync(adminJsPath, 'utf8');

// Read PHP files for onclick handlers
const includesPath = path.join(__dirname, '../../includes/');
const phpFiles = fs.readdirSync(includesPath).filter(file => file.endsWith('.php'));

let allOnclickHandlers = [];
let allJavaScriptFunctions = [];

// Extract JavaScript functions from admin.js
const functionMatches = adminJsContent.match(/function\s+(\w+)\s*\(/g);
if (functionMatches) {
    allJavaScriptFunctions = functionMatches.map(match => {
        const funcName = match.match(/function\s+(\w+)\s*\(/)[1];
        return funcName;
    });
}

console.log(`📋 Found ${allJavaScriptFunctions.length} JavaScript functions:`);
allJavaScriptFunctions.forEach(func => console.log(`   - ${func}()`));

// Extract onclick handlers from PHP files
phpFiles.forEach(file => {
    const filePath = path.join(includesPath, file);
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Find onclick handlers
    const onclickMatches = content.match(/onclick\s*=\s*["']([^"']+)["']/g);
    if (onclickMatches) {
        onclickMatches.forEach(match => {
            const handler = match.match(/onclick\s*=\s*["']([^"']+)["']/)[1];
            // Extract function name (handle cases like "return confirm(")
            let funcName = handler.split('(')[0];
            
            // Handle special cases like "return confirm("
            if (funcName.includes('return ')) {
                funcName = funcName.split('return ')[1];
            }
            
            allOnclickHandlers.push({
                file: file,
                handler: handler,
                functionName: funcName
            });
        });
    }
});

console.log(`\n📋 Found ${allOnclickHandlers.length} onclick handlers:`);
allOnclickHandlers.forEach(item => {
    console.log(`   - ${item.functionName}() in ${item.file}`);
});

// Validate completeness
console.log('\n🔍 Validation Results:');
console.log('====================');

let issuesFound = 0;

// Browser built-in functions that don't need implementation
const builtInFunctions = ['return', 'confirm', 'alert', 'prompt', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval'];

allOnclickHandlers.forEach(item => {
    const { functionName, handler, file } = item;
    
    // Skip built-in browser functions
    if (builtInFunctions.includes(functionName)) {
        console.log(`✅ OK: ${functionName}() is a browser built-in function`);
        return;
    }
    
    if (!allJavaScriptFunctions.includes(functionName)) {
        console.log(`❌ MISSING: ${functionName}() called in ${file} but not implemented in admin.js`);
        console.log(`   Handler: ${handler}`);
        issuesFound++;
    } else {
        console.log(`✅ OK: ${functionName}() is implemented`);
    }
});

// Check for unused JavaScript functions
const usedFunctions = allOnclickHandlers.map(item => item.functionName);
const unusedFunctions = allJavaScriptFunctions.filter(func => !usedFunctions.includes(func));

if (unusedFunctions.length > 0) {
    console.log('\n⚠️  Unused JavaScript functions:');
    unusedFunctions.forEach(func => {
        console.log(`   - ${func}()`);
    });
}

// Summary
console.log('\n📊 Summary:');
console.log('===========');
console.log(`Total onclick handlers: ${allOnclickHandlers.length}`);
console.log(`Total JavaScript functions: ${allJavaScriptFunctions.length}`);
console.log(`Issues found: ${issuesFound}`);

if (issuesFound === 0) {
    console.log('\n🎉 All features are complete! No missing implementations found.');
    process.exit(0);
} else {
    console.log(`\n❌ ${issuesFound} incomplete features found. Fix before deployment.`);
    process.exit(1);
}
