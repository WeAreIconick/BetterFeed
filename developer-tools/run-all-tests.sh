#!/bin/bash
# WordPress Plugin Complete Testing Suite Runner
# Usage: ./run-all-tests.sh [project-slug || @]
# Examples:
#   ./run-all-tests.sh                    # Auto-detect project
#   ./run-all-tests.sh my-plugin         # Specific project
#   ./run-all-tests.sh @                 # Show usage info

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# Configuration
PROJECT_SLUG=""
BASE_URL="http://localhost:8890"
NAMESPACE="betterfeed/v1"

# Show banner
echo -e "${CYAN}🚀 WordPress Plugin Complete Testing Suite${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Function to print test header
print_test_header() {
    echo -e "${PURPLE}📋 Running: $1${NC}"
    echo "------------------------------------------------"
}

# Function to print test result
print_test_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2: PASSED${NC}"
    else
        echo -e "${RED}❌ $2: FAILED${NC}"
    fi
    echo ""
}

# Function to auto-detect project details
auto_detect_project() {
    echo -e "${YELLOW}🔍 Auto-detecting project configuration...${NC}"
    
    # Check if we're in a WordPress plugin directory
    if [ -f "*.php" ] && (grep -q "Plugin Name:" *.php 2>/dev/null); then
        PROJECT_SLUG=$(basename "$PWD")
        echo -e "${GREEN}📁 Plugin detected: $PROJECT_SLUG${NC}"
        
        # Try to extract namespace from main PHP file
        MAIN_FILE=$(find . -maxdepth 1 -name "*.php" -type f | grep -v "readme\|uninstall\|activate" | head -1)
        if [ -f "$MAIN_FILE" ]; then
            NAMESPACE_LINE=$(grep -i "namespace\|rest.*route" "$MAIN_FILE" | head -1)
            if [[ $NAMESPACE_LINE =~ ([a-zA-Z0-9\-]+)/v[0-9]+ ]]; then
                NAMESPACE="${BASH_REMATCH[0]}"
                echo -e "${GREEN}🎯 REST namespace detected: $NAMESPACE${NC}"
            fi
        fi
        
        # Try to detect WordPress URL from wp-config or .env
        if [ -f "wp-config.php" ]; then
            BASE_URL=$(grep -o "WP_HOME.*['\"][^'\"]*['\"]" wp-config.php | cut -d"'" -f2 | head -1 2>/dev/null || echo "http://localhost:8890")
        elif [ -f "../wp-config.php" ]; then
            BASE_URL="http://localhost:8890"
            echo -e "${YELLOW}⚠️  WordPress config detected in parent directory${NC}"
        fi
    else
        echo -e "${RED}❌ Not in a WordPress plugin directory${NC}"
        echo "Usage: ${WHITE}./run-all-tests.sh [plugin-slug]${NC}"
        echo ""
        echo "Examples:"
        echo "  ${WHITE}./run-all-tests.sh my-awesome-plugin${NC}"
        echo "  ${WHITE}./run-all-tests.sh betterfeed${NC}"
        exit 1
    fi
    echo ""
}

# Function to update scripts for project
update_scripts_for_project() {
    echo -e "${BLUE}⚙️  Updating test scripts for project: $PROJECT_SLUG${NC}"
    
    # Update REST API test script
    if [ -f "test-rest-api.sh" ]; then
        sed -i.bak "s/betterfeed\/v1/$NAMESPACE/g" test-rest-api.sh
        echo -e "${GREEN}✅ Updated REST API namespace: $NAMESPACE${NC}"
    fi
    
    # Update JavaScript validator
    if [ -f "wordpress-js-validator.js" ]; then
        # Update JS file paths if they exist
        if [ -f "assets/js/admin.js" ]; then
            sed -i.bak "s|/Users/nick/Documents/GitHub/betterfeed|$(pwd)|g" wordpress-js-validator.js
            echo -e "${GREEN}✅ Updated JS validator paths${NC}"
        fi
    fi
    
    echo ""
}

# Parse arguments
if [ "$1" = "@" ] || [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    echo "WordPress Plugin Complete Testing Suite"
    echo ""
    echo "Usage:"
    echo "  ./run-all-tests.sh                    - Auto-detect project"
    echo "  ./run-all-tests.sh [plugin-slug]      - Specific project"
    echo "  ./run-all-tests.sh @                  - Show this help"
    echo ""
    echo "What it tests:"
    echo "  ✅ JavaScript syntax validation"
    echo "  ✅ REST API endpoint validation" 
    echo "  ✅ Authentication testing"
    echo "  ✅ Error response validation"
    echo ""
    echo "Requirements:"
    echo "  • WordPress development environment"
    echo "  • Node.js installed"
    echo "  • Plugin installed and activated"
    echo ""
    exit 0
elif [ -n "$1" ]; then
    PROJECT_SLUG="$1"
    echo -e "${CYAN}🎯 Testing project: $PROJECT_SLUG${NC}"
    echo ""
else
    auto_detect_project
fi

# Track test results
TESTS_RAN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Test Results Log
RESULTS_LOG="test-results-$(date +%Y%m%d-%H%M%S).log"
echo "WordPress Plugin Testing Results - $(date)" > "$RESULTS_LOG"
echo "Project: $PROJECT_SLUG" >> "$RESULTS_LOG"
echo "Namespace: $NAMESPACE" >> "$RESULTS_LOG"
echo "Base URL: $BASE_URL" >> "$RESULTS_LOG"
echo "----------------------------------------" >> "$RESULTS_LOG"

echo -e "${BLUE}📋 Starting Complete Testing Suite...${NC}"
echo "Logging results to: $RESULTS_LOG"
echo ""

# 1. JavaScript Validation
TESTS_RAN=$((TESTS_RAN + 1))
print_test_header "JavaScript Syntax Validation"
if [ -f "./testing/validate-js.sh" ]; then
    ./testing/validate-js.sh 2>&1 | tee -a "$RESULTS_LOG"
    JS_RESULT=$?
    print_test_result $JS_RESULT "JavaScript Validation"
    if [ $JS_RESULT -eq 0 ]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
else
    echo -e "${RED}❌ JavaScript validator not found${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# 2. Advanced JavaScript Validation
TESTS_RAN=$((TESTS_RAN + 1))
print_test_header "Advanced JavaScript Analysis"
if [ -f "./testing/wordpress-js-validator.js" ]; then
    node ./testing/wordpress-js-validator.js 2>&1 | tee -a "$RESULTS_LOG"
    ADV_JS_RESULT=$?
    print_test_result $ADV_JS_RESULT "Advanced JS Analysis"
    if [ $ADV_JS_RESULT -eq 0 ]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
else
    echo -e "${RED}❌ Advanced JavaScript validator not found${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# 3. REST API Testing
TESTS_RAN=$((TESTS_RAN + 1))
print_test_header "REST API Endpoint Validation"
if [ -f "./testing/test-rest-api.sh" ]; then
    # Update namespace in script
    sed -i.tmp "s/betterfeed\/v1/$NAMESPACE/g" ./testing/test-rest-api.sh 2>/dev/null || true
    ./testing/test-rest-api.sh "$BASE_URL" 2>&1 | tee -a "$RESULTS_LOG"
    REST_RESULT=$?
    # Restore original if backup exists
    [ -f "./testing/test-rest-api.sh.tmp" ] && mv ./testing/test-rest-api.sh.tmp ./testing/test-rest-api.sh
    
    print_test_result $REST_RESULT "REST API Testing"
    if [ $REST_RESULT -eq 0 ]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
else
    echo -e "${RED}❌ REST API tester not found${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# 4. File Permissions Check
TESTS_RAN=$((TESTS_RAN + 1))
print_test_header "File Permissions Validation"
WRITABLE_DIRS=("wp-content/uploads" "wp-content/cache")
PERM_ERRORS=0

for dir in "${WRITABLE_DIRS[@]}"; do
    if [ -d "$dir" ] && [ ! -w "$dir" ]; then
        echo -e "${RED}❌ Directory not writable: $dir${NC}"
        PERM_ERRORS=$((PERM_ERRORS + 1))
    elif [ -d "$dir" ]; then
        echo -e "${GREEN}✅ Directory writable: $dir${NC}"
    fi
done

if [ $PERM_ERRORS -eq 0 ]; then
    print_test_result 0 "File Permissions"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    print_test_result 1 "File Permissions"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# 5. WordPress Environment Check
TESTS_RAN=$((TESTS_RAN + 1))
print_test_header "WordPress Environment Validation"
if curl -s "$BASE_URL/wp-json/" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ WordPress REST API accessible${NC}"
    WP_APIS_OK=0
else
    echo -e "${RED}❌ WordPress REST API not accessible${NC}"
    WP_APIS_OK=1
fi

if curl -s "$BASE_URL/wp-admin/" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ WordPress admin accessible${NC}"
    WP_ADMIN_OK=0
else
    echo -e "${RED}❌ WordPress admin not accessible${NC}"
    WP_ADMIN_OK=1
fi

if [ $WP_APIS_OK -eq 0 ] && [ $WP_ADMIN_OK -eq 0 ]; then
    print_test_result 0 "WordPress Environment"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    print_test_result 1 "WordPress Environment"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo "----------------------------------------" >> "$RESULTS_LOG"
echo "Test Summary:" >> "$RESULTS_LOG"
echo "Total Tests: $TESTS_RAN" >> "$RESULTS_LOG"
echo "Passed: $TESTS_PASSED" >> "$RESULTS_LOG"
echo "Failed: $TESTS_FAILED" >> "$RESULTS_LOG"

# Final Results Summary
echo -e "${PURPLE}📊 TEST SUITE SUMMARY${NC}"
echo "=================================================="
echo -e "${WHITE}Total Tests Run:${NC} $TESTS_RAN"
echo -e "${GREEN}Tests Passed:${NC} $TESTS_PASSED"
echo -e "${RED}Tests Failed:${NC} $TESTS_FAILED"
echo ""
echo -e "${BLUE}📋 Detailed log saved to: ${RESULTS_LOG}${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED! Your plugin is ready for production! 🎉${NC}"
    echo -e "${BLUE}✨ WordPress Plugin Quality Standard: EXCELLENT${NC}"
    exit 0
else
    echo -e "${RED}🚨 SOME TESTS FAILED! Please review and fix issues above. 🚨${NC}"
    echo -e "${YELLOW}💪 Fix the failures and run again to achieve WordPress Plugin Excellence!${NC}"
    exit 1
fi
