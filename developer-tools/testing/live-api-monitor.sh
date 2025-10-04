#!/bin/bash
# Live REST API Endpoint Monitoring
# Continuously tests REST endpoints for availability and authentication
# Usage: ./live-api-monitor.sh [base-url] [namespace]

BASE_URL=${1:-"http://localhost:8890"}
NAMESPACE=${2:-"betterfeed/v1"}

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Endpoints to monitor
declare -A ENDPOINTS
ENDPOINTS[clear-cache]="POST"
ENDPOINTS[warm-cache]="POST"  
ENDPOINTS[export-analytics]="GET"
ENDPOINTS[apply-preset]="POST"
ENDPOINTS[export-settings]="GET"

echo -e "${CYAN}🔍 Live REST API Endpoint Monitoring${NC}"
echo -e "${BLUE}=====================================${NC}"
echo -e "${YELLOW}Base URL: $BASE_URL${NC}"
echo -e "${YELLOW}Namespace: $NAMESPACE${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop monitoring${NC}"
echo ""

# Track previous status for change detection
declare -A LAST_STATUS

while true; do
    echo -e "${BLUE}⏰ $(date '+%H:%M:%S') - Testing endpoints...${NC}"
    
    all_healthy=true
    
    for endpoint in "${!ENDPOINTS[@]}"; do
        method="${ENDPOINTS[$endpoint]}"
        url="$BASE_URL/wp-json/$NAMESPACE/$endpoint"
        
        # Test endpoint
        response=$(curl -s -o /dev/null -w "%{http_code}:%{content_type}" -X "$method" "$url")
        status_code=$(echo "$response" | cut -d: -f1)
        content_type=$(echo "$response" | cut -d: -f2)
        
        # Check if status changed
        old_status="${LAST_STATUS[$endpoint]}"
        LAST_STATUS[$endpoint]="$status_code"
        
        # Interpret status
        case $status_code in
            200)
                status_emoji="🟢"
                status_text="OK"
                ;;
            401)
                status_emoji="🔒"
                status_text="Auth Required"
                ;;
            404)
                status_emoji="❌"
                status_text="Not Found"
                all_healthy=false
                ;;
            *)
                status_emoji="⚠️"
                status_text="Unknown ($status_code)"
                all_healthy=false
                ;;
        esac
        
        # Show status with change indicator
        change_indicator=""
        if [ "$old_status" != "" ] && [ "$old_status" != "$status_code" ]; then
            change_indicator=" ${YELLOW}↗️${NC}"
        fi
        
        echo -e "  ${status_emoji} $endpoint ($method): ${status_text}${change_indicator}"
        
        # Validate content type for non-404 responses
        if [ "$status_code" != "404" ] && [[ "$content_type" != *"application/json"* ]]; then
            echo -e "    ⚠️  ${YELLOW}Invalid content type: $content_type (expected JSON)${NC}"
            all_healthy=false
        fi
    done
    
    # Overall health status
    if $all_healthy; then
        echo -e "  ${GREEN}✅ All endpoints healthy${NC}"
    else
        echo -e "  ${RED}❌ Some endpoints have issues${NC}"
    fi
    
    echo ""
    sleep 5
done
