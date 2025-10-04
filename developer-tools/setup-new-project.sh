#!/bin/bash
# WordPress Plugin Development Framework Setup
# Copies framework to new WordPress plugin project
# Usage: ./setup-new-project.sh [target-plugin-directory]

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

FRAMEWORK_DIR="$(dirname "$0")"

echo -e "${BLUE}🚀 WordPress Plugin Development Framework Setup${NC}"
echo -e "${BLUE}=================================================${NC}"
echo ""

# Determine target directory
if [ -n "$1" ]; then
    TARGET_DIR="$1"
elif [ -d "../my-new-plugin" ]; then
    TARGET_DIR="../my-new-plugin"
elif [ -d "../new-plugin" ]; then
    TARGET_DIR="../new-plugin"
else
    echo "Usage: ${YELLOW}./setup-new-project.sh [target-plugin-directory]${NC}"
    echo ""
    echo "Examples:"
    echo "  ${YELLOW}./setup-new-project.sh ../my-awesome-plugin${NC}"
    echo "  ${YELLOW}./setup-new-project.sh /path/to/wordpress/wp-content/plugins/my-plugin${NC}"
    echo ""
    echo "If no directory specified, will look for common new plugin directories."
    exit 1
fi

# Verify target exists
if [ ! -d "$TARGET_DIR" ]; then
    print -e "${RED}❌ Target directory not found: $TARGET_DIR${NC}"
    exit 1
fi

echo -e "${BLUE}📁 Target Plugin Directory: $TARGET_DIR${NC}"

# Verify it looks like a WordPress plugin
if [ ! -f "$TARGET_DIR"/*.php ]; then
    echo -e "${RED}⚠️  Warning: No PHP files found in target directory${NC}"
    echo -e "${YELLOW}Make sure this is a WordPress plugin directory${NC}"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${RED}Setup cancelled.${NC}"
        exit 1
    fi
fi

echo ""

# Copy developer-tools directory
echo -e "${BLUE}📦 Setting up development tools...${NC}"
cp -r "$FRAMEWORK_DIR" "$TARGET_DIR/"
echo -e "${GREEN}✅ Development tools copied${NC}"

# Update scripts for new project location
cd "$TARGET_DIR/developer-tools"
echo -e "${BLUE}⚙️  Configuring for new project...${NC}"

# Update JavaScript validator paths
if [ -f "testing/wordpress-js-validator.js" ]; then
    sed -i.bak "s|/Users/nick/Documents/GitHub/betterfeed|$TARGET_DIR|g" testing/wordpress-js-validator.js
    rm testing/wordpress-js-validator.js.bak
    echo -e "${GREEN}✅ JavaScript validator configured${NC}"
fi

# Update REST API test script  
if [ -f "testing/test-rest-api.sh" ]; then
    # Extract plugin slug for namespace
    MAIN_FILE=$(find .. -maxdepth 1 -name "*.php" -type f | grep -v "readme\|uninstall\|activate" | head -1)
    if [ -f "$MAIN_FILE" ]; then
        PLUGIN_SLUG=$(basename "$MAIN_FILE" .php)
        
        # Update default namespace in script
        sed -i.bak "s/betterfeed\/v1/$PLUGIN_SLUG\/v1/g" testing/test-rest-api.sh
        rm testing/test-rest-api.sh.bak
        echo -e "${GREEN}✅ REST API tester configured for: $PLUGIN_SLUG/v1${NC}"
    fi
fi

# Make scripts executable
chmod +x testing/*.sh
chmod +x run-all-tests.sh 2>/dev/null || true

echo ""
echo -e "${GREEN}🎉 WordPress Plugin Development Framework setup complete!${NC}"
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo "1. ${YELLOW}cd into your plugin directory${NC}"
echo "2. ${YELLOW}Run tests:${NC} ./developer-tools/run-all-tests.sh"
echo "3. ${YELLOW}Develop your plugin${NC} following the framework standards"
echo "4. ${YELLOW}Test regularly:${NC} Use run-all-tests.sh during development"
echo ""
echo -e "${BLUE}📚 Available Documentation:${NC}"
echo "• ${YELLOW}developer-tools/README.md${NC} - Framework overview"
echo "• ${YELLOW}developer-tools/docs/testing-guide.md${NC} - Detailed testing guide"
echo "• ${YELLOW}developer-tools/rules/${NC} - Development standards"
echo ""
echo -e "${GREEN}✨ Happy WordPress Plugin Development! ✨${NC}"
