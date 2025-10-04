#!/bin/bash
# WordPress REST API Development Test Suite
# NOW INCLUDES REST URL VALIDATION!

echo "🚀 BetterFeed Plugin Test Suite"
echo "✅ Includes REST API URL validation (preventing 404 disasters!)"
echo "============================================================"

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"

echo "📁 Found project root: $PROJECT_DIR"

# Check if we're in the right location
if [[ ! -f "$PROJECT_DIR/betterfeed.php" ]]; then
    echo "❌ Not in BetterFeed project root! Run from project root."
    echo "Expected: betterfeed.php should exist in $PROJECT_DIR"
    exit 1
fi

echo ""

# Test 1: JavaScript Syntax Validation
echo "🔍 TEST 1: JavaScript Syntax Validation"
echo "----------------------------------------"
bash "$SCRIPT_DIR/validate-js.sh"
JS_SYNTAX_RESULT=$?

if [[ $JS_SYNTAX_RESULT -eq 0 ]]; then
    echo "✅ JavaScript syntax validation passed"
else
    echo "❌ JavaScript syntax validation failed"
fi

echo ""

# Test 2: REST API URL Validation (NEW!)
echo "🚨 TEST 2: REST API URL Validation (CRITICAL!)"
echo "----------------------------------------------"
node "$SCRIPT_DIR/validate-rest-urls.js" || URL_VALIDATION_RESULT=$?

echo ""

# Test 3: Advanced JavaScript Quality
echo "🔧 TEST 3: Advanced JavaScript Quality"
echo "-------------------------------------"
node "$SCRIPT_DIR/advanced-js-monitor.js"
ADVANCED_JS_RESULT=$?

if [[ $ADVANCED_JS_RESULT -eq 0 ]]; then
    echo "✅ Advanced JavaScript quality checks passed"
else
    echo "❌ Advanced JavaScript quality issues detected"
fi

echo ""

# Test 4: WordPress JavaScript Validation
echo "💎 TEST 4: WordPress JavaScript Validation"
echo "-----------------------------------------"
node "$SCRIPT_DIR/wordpress-js-validator.js"
WP_JS_RESULT=$?

if [[ $WP_JS_RESULT -eq 0 ]]; then
    echo "✅ WordPress JavaScript validation passed"
else
    echo "❌ WordPress JavaScript validation failed"
fi

echo ""

# Summary
echo "📊 TEST RESULTS SUMMARY"
echo "======================="
echo "JavaScript Syntax:     $([[ $JS_SYNTAX_RESULT -eq 0 ]] && echo "✅ PASS" || echo "❌ FAIL")"
echo "REST API URLs:         $([[ $URL_VALIDATION_RESULT -eq 0 ]] && echo "✅ PASS" || echo "❌ FAIL")"
echo "Advanced JS Quality:   $([[ $ADVANCED_JS_RESULT -eq 0 ]] && echo "✅ PASS" || echo "❌ FAIL")"
echo "WordPress JS Rules:    $([[ $WP_JS_RESULT -eq 0 ]] && echo "✅ PASS" || echo "❌ FAIL")"

TOTAL_FAILURES=$((JS_SYNTAX_RESULT + URL_VALIDATION_RESULT + ADVANCED_JS_RESULT + WP_JS_RESULT))

echo ""
if [[ $TOTAL_FAILURES -eq 0 ]]; then
    echo "🎉 ALL TESTS PASSED! Plugin is ready for deployment!"
    echo "✅ No syntax errors"
    echo "✅ No REST URL disasters"  
    echo "✅ No JavaScript quality issues"
    echo "✅ Follows WordPress best practices"
    exit 0
else
    echo "🚨 $TOTAL_FAILURES TEST(S) FAILED!"
    echo "Fix issues before proceeding with development."
    
    if [[ $URL_VALIDATION_RESULT -ne 0 ]]; then
        echo ""
        echo "🔥 CRITICAL REST URL ISSUES DETECTED:"
        echo "💡 Quick Fix Guide:"
        echo "   Replace: './wp-json/' → '/wp-json/'"
        echo "   Replace: '/wp-admin/wp-json/' → '/wp-json/'"
        echo "   Add version: '/betterfeed/' → '/betterfeed/v1/'"
    fi
    
    exit 1
fi
