#!/bin/bash
# Automated Development Workflow Monitor
# Watches for file changes and runs tests automatically
# Usage: ./watch-and-test.sh

PROJECT_DIR=$(pwd)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}🤖 Automated Development Workflow Monitor${NC}"
echo -e "${BLUE}==========================================${NC}"
echo -e "${YELLOW}Watching for changes in: $PROJECT_DIR${NC}"
echo -e "${YELLOW}Auto-running tests on file changes...${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop monitoring${NC}"
echo ""

# Track last modification times
declare -A LAST_MODIFIED

# Files to watch
files_to_watch=(
    "assets/js/admin.js"
    "includes/class-bf-admin.php"
    "betterfeed.php"
    "developer-tools/testing/*.sh"
    "developer-tools/testing/*.js"
)

function run_quick_tests() {
    echo -e "${BLUE}🔄 File changed - Running quick tests...${NC}"
    
    # 1. JavaScript syntax check
    echo -e "${PURPLE}📝 Testing JavaScript syntax...${NC}"
    if node -c assets/js/admin.js 2>/dev/null; then
        echo -e "${GREEN}  ✅ JavaScript syntax OK${NC}"
    else
        echo -e "${RED}  ❌ JavaScript syntax error detected!${NC}"
        return 1
    fi
    
    # 2. Advanced JS analysis
    echo -e "${PURPLE}📝 Running advanced JS analysis...${NC}"
    if node developer-tools/testing/advanced-js-monitor.js 2>/dev/null; then
        echo -e "${GREEN}  ✅ Advanced JS analysis passed${NC}"
    else
        echo -e "${RED}  ❌ Advanced JS issues detected!${NC}"
        return 1
    fi
    
    # 3. Quick REST API test
    echo -e "${PURPLE}📝 Testing REST API availability...${NC}"
    if curl -s -o /dev/null -w "%{http_code}" "http://localhost:8890/wp-json/betterfeed/v1/" | grep -q "200"; then
        echo -e "${GREEN}  ✅ REST API responsive${NC}"
    else
        echo -e "${RED}  ❌ REST API not accessible!${NC}"
        return 1
    fi
    
    echo -e "${GREEN}🎉 Quick tests passed! Development continuing...${NC}"
    echo ""
    return 0
}

function check_file_changes() {
    local changed_files=()
    
    for file_pattern in "${files_to_watch[@]}"; do
        if [[ "$file_pattern" == *"*"* ]]; then
            # Handle glob patterns
            for file in $file_pattern; do
                if [ -f "$file" ]; then
                    check_single_file "$file" && changed_files+=("$file")
                fi
            done
        else
            # Handle single files
            if [ -f "$file_pattern" ]; then
                check_single_file "$file_pattern" && changed_files+=("$file_pattern")
            fi
        fi
    done
    
    if [ ${#changed_files[@]} -ne 0 ]; then
        echo -e "${YELLOW}📝 Files changed: ${changed_files[*]}${NC}"
        run_quick_tests
    fi
}

function check_single_file() {
    local file="$1"
    local current_time=$(stat -c %Y "$file" 2>/dev/null || stat -f %m "$file" 2>/dev/null)
    local last_time="${LAST_MODIFIED[$file]}"
    
    if [ "$last_time" != "$current_time" ]; then
        LAST_MODIFIED["$file"]="$current_time"
        return 0  # Changed
    fi
    return 1  # Unchanged
}

function run_full_test_suite() {
    echo -e "${CYAN}🏃 Running full test suite...${NC}"
    ./developer-tools/run-all-tests.sh betterfeed
}

# Initial file state tracking
echo -e "${BLUE}📋 Initializing file monitoring...${NC}"
for file_pattern in "${files_to_watch[@]}"; do
    if [[ "$file_pattern" == *"*"* ]]; then
        for file in $file_pattern; do
            [ -f "$file" ] && LAST_MODIFIED["$file"]=$(stat -c %Y "$file" 2>/dev/null || stat -f %m "$file" 2>/dev/null)
        done
    else
        [ -f "$file_pattern" ] && LAST_MODIFIED["$file_pattern"]=$(stat -c %Y "$file_pattern" 2>/dev/null || stat -f %m "$file_pattern" 2>/dev/null)
    fi
done

echo -e "${GREEN}✅ File monitoring initialized${NC}"
echo ""

# Handle Ctrl+C gracefully
trap 'echo -e "\n${CYAN}👋 Stopping development monitor...${NC}"; exit 0' INT

# Main monitoring loop
echo -e "${BLUE}🔍 Starting file change monitoring...${NC}"
echo ""

while true; do
    check_file_changes
    sleep 2
done
