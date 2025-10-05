#!/bin/bash

# Test for Nested Forms Issue
# This test could have caught the FormData TypeError by detecting nested forms

echo "🔍 Testing for Nested Forms Issue"
echo "=================================="

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ERRORS=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_error() {
    echo -e "${RED}❌ $1${NC}"
    ERRORS=$((ERRORS + 1))
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Test 1: Check for nested forms in PHP files
echo ""
echo "🔍 Test 1: Checking for nested forms in PHP files..."

NESTED_FORMS_FOUND=false

# Find all PHP files in includes directory
find "$PROJECT_ROOT/includes" -name "*.php" -type f | while read -r file; do
    # Check for nested forms using a simple depth counter
    form_depth=0
    line_number=0
    
    while IFS= read -r line; do
        line_number=$((line_number + 1))
        
        # Count form opening tags
        if [[ $line =~ \<form ]]; then
            form_depth=$((form_depth + 1))
            
            # If we're already in a form, this is nested
            if [[ $form_depth -gt 1 ]]; then
                print_error "Nested form detected in $(basename "$file"):$line_number"
                print_error "  Line $line_number: $line"
                NESTED_FORMS_FOUND=true
            fi
        fi
        
        # Count form closing tags
        if [[ $line =~ \</form\> ]]; then
            form_depth=$((form_depth - 1))
        fi
        
    done < "$file"
done

if [[ $NESTED_FORMS_FOUND == false ]]; then
    print_success "No nested forms detected in PHP files"
fi

# Test 2: Check for FormData usage without form validation
echo ""
echo "🔍 Test 2: Checking FormData usage patterns..."

FORM_DATA_ISSUES=0

# Check JavaScript files for FormData usage
find "$PROJECT_ROOT/assets/js" -name "*.js" -type f | while read -r file; do
    line_number=0
    
    while IFS= read -r line; do
        line_number=$((line_number + 1))
        
        # Look for FormData usage
        if [[ $line =~ new\ FormData ]]; then
            # Check if there's form validation before this line
            validation_found=false
            
            # Look backwards in the file for form validation
            head -n $line_number "$file" | tail -n 5 | grep -q "if.*form" && validation_found=true
            
            if [[ $validation_found == false ]]; then
                print_warning "FormData used without validation in $(basename "$file"):$line_number"
                print_warning "  Line $line_number: $line"
                FORM_DATA_ISSUES=$((FORM_DATA_ISSUES + 1))
            fi
        fi
    done < "$file"
done

if [[ $FORM_DATA_ISSUES -eq 0 ]]; then
    print_success "FormData usage patterns look good"
fi

# Test 3: Check for onclick handlers with form references
echo ""
echo "🔍 Test 3: Checking onclick handlers with form references..."

ONCLICK_ISSUES=0

# Check PHP files for onclick handlers
find "$PROJECT_ROOT/includes" -name "*.php" -type f | while read -r file; do
    line_number=0
    
    while IFS= read -r line; do
        line_number=$((line_number + 1))
        
        # Look for onclick handlers that might reference forms
        if [[ $line =~ onclick=.*[Ff]orm ]]; then
            print_warning "onclick handler with form reference in $(basename "$file"):$line_number"
            print_warning "  Line $line_number: $line"
            ONCLICK_ISSUES=$((ONCLICK_ISSUES + 1))
        fi
    done < "$file"
done

if [[ $ONCLICK_ISSUES -eq 0 ]]; then
    print_success "onclick handlers look good"
fi

# Test 4: Check for proper error handling in JavaScript
echo ""
echo "🔍 Test 4: Checking error handling patterns..."

ERROR_HANDLING_ISSUES=0

# Check JavaScript files for error handling
find "$PROJECT_ROOT/assets/js" -name "*.js" -type f | while read -r file; do
    line_number=0
    
    while IFS= read -r line; do
        line_number=$((line_number + 1))
        
        # Look for functions that might need error handling
        if [[ $line =~ function.*[Aa]dd.*\( ]]; then
            # Check if this function has try-catch
            function_name=$(echo "$line" | grep -o 'function [a-zA-Z_][a-zA-Z0-9_]*' | cut -d' ' -f2)
            
            # Look for try-catch in the next 20 lines
            tail -n +$line_number "$file" | head -n 20 | grep -q "try {" || {
                print_warning "Function '$function_name' might need try-catch error handling in $(basename "$file"):$line_number"
                ERROR_HANDLING_ISSUES=$((ERROR_HANDLING_ISSUES + 1))
            }
        fi
    done < "$file"
done

if [[ $ERROR_HANDLING_ISSUES -eq 0 ]]; then
    print_success "Error handling patterns look good"
fi

# Test 5: Check for HTML structure issues
echo ""
echo "🔍 Test 5: Checking HTML structure patterns..."

HTML_ISSUES=0

# Check PHP files for HTML structure issues
find "$PROJECT_ROOT/includes" -name "*.php" -type f | while read -r file; do
    line_number=0
    
    while IFS= read -r line; do
        line_number=$((line_number + 1))
        
        # Check for submit_button without form
        if [[ $line =~ submit_button ]]; then
            # Check if there's a form tag nearby
            head -n $line_number "$file" | tail -n 10 | grep -q "<form" || {
                print_warning "submit_button() used without <form> tag in $(basename "$file"):$line_number"
                print_warning "  Line $line_number: $line"
                HTML_ISSUES=$((HTML_ISSUES + 1))
            }
        fi
    done < "$file"
done

if [[ $HTML_ISSUES -eq 0 ]]; then
    print_success "HTML structure patterns look good"
fi

# Summary
echo ""
echo "📊 Nested Forms Test Summary"
echo "============================"

if [[ $ERRORS -eq 0 ]]; then
    print_success "All nested forms tests passed!"
    print_info "No FormData TypeError issues detected"
    exit 0
else
    print_error "$ERRORS issue(s) found that could cause FormData errors"
    print_info "This test could have prevented the FormData TypeError issue"
    exit 1
fi
