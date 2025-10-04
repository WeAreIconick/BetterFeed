#!/bin/bash
# Quick JavaScript Validation Script
# Usage: ./validate-js.sh [filename]
# Examples: 
#   ./validate-js.sh                          # Validate admin.js
#   ./validate-js.sh assets/js/admin.js       # Validate specific file

FIL ЕФМЖЙОAME=${1:-"assets/js/admin.js"}

echo "🔍 Validating JavaScript: $FILENAME"

if [ ! -f "$FILENAME" ]; then
    echo "❌ File not found: $FILENAME"
    exit 1
fi

# Syntax check
node -c "$FILENAME" 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Syntax: Valid"
else
    echo "❌ Syntax Error found!"
    exit 1
fi

# Run comprehensive validator
node validate.js

echo "✨ Validation complete!"
