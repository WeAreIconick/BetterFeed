#!/bin/bash
# WordPress REST API Testing Script
# Usage: ./test-rest-api.sh [base-url]
# Examples:
#   ./test-rest-api.sh                           # Test localhost:8890  
#   ./test-rest-api.sh http://example.com        # Test remote site

BASE_URL=${1:-"http://localhost:8890"}
NAMESPACE="betterfeed/v1"
ENDPOINTS=("clear-cache" "warm-cache" "export-analytics" "apply-preset" "export-settings")

echo "🔍 WordPress REST API Testing: $BASE_URL"
echo "📡 Testing BetterFeed Endpoints..."
echo "======================================"

# Test WordPress REST API availability
http_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/wp-json/")
if [ "$http_code" = "200" ]; then
    echo "✅ WordPress REST API: Available ($http_code)"
else
    echo "❌ WordPress REST API: Failed ($http_code)"
    exit 1
fi

echo ""

# Test BetterFeed namespace
http_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/wp-json/$NAMESPACE/")
if [ "$http_code" = "200" ]; then
    echo "✅ BetterFeed Namespace: Available ($http_code)"
    curl -s "$BASE_URL/wp-json/$NAMESPACE/" | grep -o '"'.$NAMESPACE'/[^"]*"' || echo "⚠️  No routes found"
else
    echo "❌ BetterFeed Namespace: Failed ($http_code)"
    echo "   This means plugin not installed or routes not registered"
fi

echo ""

# Test individual endpoints
echo "🔌 Testing Individual Endpoints:"
for endpoint in "${ENDPOINTS[@]}"; do
    url="$BASE_URL/wp-json/$NAMESPACE/$endpoint"
    http_code=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    
    case $http_code in
        200)
            echo "✅ $endpoint: Available ($http_code)"
            ;;
        401)
            echo "🔒 $endpoint: Requires Authentication ($http_code) - This is CORRECT!"
            ;;
        404)
            echo "❌ $endpoint: Route Not Found ($http_code)"
            ;;
        *)
            echo "⚠️  $endpoint: Unexpected Response ($http_code)"
            ;;
    esac
done

echo ""
echo "🧪 Authentication Testing:"
echo "======================================"

# Test endpoints that should require auth
echo "Testing endpoints that SHOULD require authentication..."

for endpoint in "${ENDPOINTS[@]}"; do
    url="$BASE_URL/wp-json/$NAMESPACE/$endpoint"
    
    # Test without authentication
    response=$(curl -s "$url")
    status=$(echo "$response" | grep -c "<!DOCTYPE html>")
    
    if [ "$status" -gt 0 ]; then
        echo "❌ $endpoint: Returns HTML (login page) - Check authentication"
        echo "   Response preview: $(echo "$response" | head -c 100)..."
    elif [ "$response" = "" ]; then
        echo "⚠️  $endpoint: Empty response"
    else
        echo "✅ $endpoint: Returns JSON/structured data"
        echo "$response" | head -c 200
        echo "..."
    fi
    echo ""
done

echo "✨ REST API Testing Complete!"
echo ""
echo "📝 Next Steps:"
echo "1. If endpoints return 401: Authentication is working correctly"
echo "2. If endpoints return HTML: Check WordPress authentication"
echo "3. If endpoints return 404: Check route registration" 
echo "4. Test JavaScript integration with proper credentials and nonces"
