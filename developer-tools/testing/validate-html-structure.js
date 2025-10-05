#!/usr/bin/env node

/**
 * HTML Structure Validation Test
 * 
 * This test could have caught the nested forms issue by:
 * 1. Parsing PHP files to extract HTML structure
 * 2. Detecting nested forms
 * 3. Validating form selectors used in JavaScript
 * 4. Checking for common HTML structure issues
 */

const fs = require('fs');
const path = require('path');

// ANSI color codes
const colors = {
    reset: '\x1b[0m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    magenta: '\x1b[35m',
    cyan: '\x1b[36m',
    white: '\x1b[37m'
};

class HTMLStructureValidator {
    constructor() {
        this.errors = [];
        this.warnings = [];
        this.projectRoot = path.join(__dirname, '../../..');
    }

    log(message, color = 'white') {
        console.log(`${colors[color]}${message}${colors.reset}`);
    }

    error(message) {
        this.errors.push(message);
        this.log(`❌ ${message}`, 'red');
    }

    warning(message) {
        this.warnings.push(message);
        this.log(`⚠️  ${message}`, 'yellow');
    }

    success(message) {
        this.log(`✅ ${message}`, 'green');
    }

    /**
     * Test 1: Detect nested forms in PHP files
     */
    testNestedForms() {
        this.log('\n🔍 Testing for nested forms...', 'blue');
        
        const phpFiles = this.findPHPFiles();
        let nestedFormsFound = false;

        phpFiles.forEach(file => {
            const content = fs.readFileSync(file, 'utf8');
            const lines = content.split('\n');
            
            let formDepth = 0;
            let inForm = false;
            
            lines.forEach((line, index) => {
                const lineNum = index + 1;
                
                // Detect form opening
                if (line.includes('<form') && !line.includes('</form')) {
                    if (inForm) {
                        this.error(`Nested form detected in ${path.relative(this.projectRoot, file)}:${lineNum}`);
                        this.error(`  Line ${lineNum}: ${line.trim()}`);
                        nestedFormsFound = true;
                    }
                    formDepth++;
                    inForm = true;
                }
                
                // Detect form closing
                if (line.includes('</form>')) {
                    formDepth--;
                    if (formDepth === 0) {
                        inForm = false;
                    }
                }
            });
        });

        if (!nestedFormsFound) {
            this.success('No nested forms detected');
        }

        return !nestedFormsFound;
    }

    /**
     * Test 2: Validate JavaScript form selectors
     */
    testJavaScriptFormSelectors() {
        this.log('\n🔍 Testing JavaScript form selectors...', 'blue');
        
        const jsFiles = this.findJSFiles();
        let selectorIssues = false;

        jsFiles.forEach(file => {
            const content = fs.readFileSync(file, 'utf8');
            
            // Look for FormData usage
            const formDataMatches = content.match(/new FormData\([^)]+\)/g);
            if (formDataMatches) {
                formDataMatches.forEach(match => {
                    // Check if selector is specific enough
                    if (match.includes('document.querySelector') && 
                        !match.includes('bf-') && 
                        !match.includes('form')) {
                        this.warning(`Generic form selector in ${path.relative(this.projectRoot, file)}: ${match}`);
                        selectorIssues = true;
                    }
                });
            }

            // Look for form-related onclick handlers
            const onclickMatches = content.match(/onclick="[^"]*"/g);
            if (onclickMatches) {
                onclickMatches.forEach(match => {
                    if (match.includes('FormData') || match.includes('form')) {
                        this.warning(`Form-related onclick handler in ${path.relative(this.projectRoot, file)}: ${match}`);
                        selectorIssues = true;
                    }
                });
            }
        });

        if (!selectorIssues) {
            this.success('JavaScript form selectors look good');
        }

        return !selectorIssues;
    }

    /**
     * Test 3: Check for form validation in JavaScript
     */
    testFormValidation() {
        this.log('\n🔍 Testing form validation patterns...', 'blue');
        
        const jsFiles = this.findJSFiles();
        let validationIssues = false;

        jsFiles.forEach(file => {
            const content = fs.readFileSync(file, 'utf8');
            
            // Look for FormData usage without validation
            const formDataRegex = /const\s+form\s*=\s*document\.querySelector\([^)]+\);\s*const\s+formData\s*=\s*new\s+FormData\(form\);/g;
            const matches = content.match(formDataRegex);
            
            if (matches) {
                matches.forEach(match => {
                    if (!match.includes('if (!form)') && !match.includes('if (form)')) {
                        this.error(`FormData used without validation in ${path.relative(this.projectRoot, file)}`);
                        this.error(`  Missing form existence check before FormData construction`);
                        validationIssues = true;
                    }
                });
            }
        });

        if (!validationIssues) {
            this.success('Form validation patterns look good');
        }

        return !validationIssues;
    }

    /**
     * Test 4: Check for proper error handling in form functions
     */
    testFormErrorHandling() {
        this.log('\n🔍 Testing form error handling...', 'blue');
        
        const jsFiles = this.findJSFiles();
        let errorHandlingIssues = false;

        jsFiles.forEach(file => {
            const content = fs.readFileSync(file, 'utf8');
            
            // Look for form-related functions
            const functionRegex = /function\s+(\w*[Ff]orm\w*|\w*[Aa]dd\w*)\s*\([^)]*\)\s*{/g;
            const functions = content.match(functionRegex);
            
            if (functions) {
                functions.forEach(func => {
                    const funcName = func.match(/function\s+(\w+)/)[1];
                    
                    // Check if function has try-catch
                    const funcStart = content.indexOf(func);
                    const funcEnd = content.indexOf('}', funcStart);
                    const funcBody = content.substring(funcStart, funcEnd);
                    
                    if (!funcBody.includes('try {') && funcBody.includes('FormData')) {
                        this.warning(`Form function "${funcName}" in ${path.relative(this.projectRoot, file)} missing try-catch error handling`);
                        errorHandlingIssues = true;
                    }
                });
            }
        });

        if (!errorHandlingIssues) {
            this.success('Form error handling looks good');
        }

        return !errorHandlingIssues;
    }

    /**
     * Test 5: Validate HTML structure patterns
     */
    testHTMLStructurePatterns() {
        this.log('\n🔍 Testing HTML structure patterns...', 'blue');
        
        const phpFiles = this.findPHPFiles();
        let structureIssues = false;

        phpFiles.forEach(file => {
            const content = fs.readFileSync(file, 'utf8');
            
            // Check for proper form structure
            if (content.includes('submit_button') && !content.includes('<form')) {
                this.warning(`submit_button() used without <form> tag in ${path.relative(this.projectRoot, file)}`);
                structureIssues = true;
            }

            // Check for onclick buttons without forms
            if (content.includes('onclick=') && content.includes('form') && !content.includes('<form')) {
                this.warning(`onclick handler with form reference but no <form> tag in ${path.relative(this.projectRoot, file)}`);
                structureIssues = true;
            }
        });

        if (!structureIssues) {
            this.success('HTML structure patterns look good');
        }

        return !structureIssues;
    }

    /**
     * Helper: Find PHP files
     */
    findPHPFiles() {
        const files = [];
        const dirs = ['includes', 'assets'];
        
        dirs.forEach(dir => {
            const dirPath = path.join(this.projectRoot, dir);
            if (fs.existsSync(dirPath)) {
                this.findFiles(dirPath, '.php', files);
            }
        });
        
        return files;
    }

    /**
     * Helper: Find JavaScript files
     */
    findJSFiles() {
        const files = [];
        const dirPath = path.join(this.projectRoot, 'assets', 'js');
        
        if (fs.existsSync(dirPath)) {
            this.findFiles(dirPath, '.js', files);
        }
        
        return files;
    }

    /**
     * Helper: Recursively find files by extension
     */
    findFiles(dir, ext, files) {
        const items = fs.readdirSync(dir);
        
        items.forEach(item => {
            const fullPath = path.join(dir, item);
            const stat = fs.statSync(fullPath);
            
            if (stat.isDirectory()) {
                this.findFiles(fullPath, ext, files);
            } else if (item.endsWith(ext)) {
                files.push(fullPath);
            }
        });
    }

    /**
     * Run all tests
     */
    runAllTests() {
        this.log('🚀 HTML Structure Validation Test Suite', 'cyan');
        this.log('==========================================', 'cyan');
        
        const tests = [
            () => this.testNestedForms(),
            () => this.testJavaScriptFormSelectors(),
            () => this.testFormValidation(),
            () => this.testFormErrorHandling(),
            () => this.testHTMLStructurePatterns()
        ];

        let passed = 0;
        let failed = 0;

        tests.forEach(test => {
            try {
                if (test()) {
                    passed++;
                } else {
                    failed++;
                }
            } catch (error) {
                this.error(`Test failed with error: ${error.message}`);
                failed++;
            }
        });

        // Summary
        this.log('\n📊 HTML Structure Validation Summary', 'blue');
        this.log('====================================', 'blue');
        this.log(`Tests Passed: ${passed}`, passed > 0 ? 'green' : 'white');
        this.log(`Tests Failed: ${failed}`, failed > 0 ? 'red' : 'white');
        this.log(`Errors Found: ${this.errors.length}`, this.errors.length > 0 ? 'red' : 'green');
        this.log(`Warnings Found: ${this.warnings.length}`, this.warnings.length > 0 ? 'yellow' : 'green');

        if (this.errors.length > 0) {
            this.log('\n🚨 Critical Issues Found:', 'red');
            this.errors.forEach(error => this.log(`  ${error}`, 'red'));
        }

        if (this.warnings.length > 0) {
            this.log('\n⚠️  Warnings:', 'yellow');
            this.warnings.forEach(warning => this.log(`  ${warning}`, 'yellow'));
        }

        if (failed === 0 && this.errors.length === 0) {
            this.log('\n🎉 All HTML structure tests passed!', 'green');
            return 0;
        } else {
            this.log('\n❌ HTML structure validation failed!', 'red');
            return 1;
        }
    }
}

// Run the validator
if (require.main === module) {
    const validator = new HTMLStructureValidator();
    process.exit(validator.runAllTests());
}

module.exports = HTMLStructureValidator;
