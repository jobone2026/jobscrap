#!/bin/bash

# Deployment script for jobscrap to jobone.in server
# Usage: ./deploy-to-server.sh

echo "🚀 Deploying jobscrap to jobone.in server..."

# Configuration - Update these with your server details
SERVER_USER="your_username"
SERVER_HOST="jobone.in"
SERVER_PATH="/var/www/jobone.in/jobscrap"  # or wherever you want to deploy

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}📦 Preparing files for deployment...${NC}"

# Create a temporary directory for deployment
TEMP_DIR=$(mktemp -d)
cp -r ./* "$TEMP_DIR/"

# Remove git files and unnecessary files
rm -rf "$TEMP_DIR/.git"
rm -f "$TEMP_DIR/.gitignore"
rm -f "$TEMP_DIR/deploy-to-server.sh"

echo -e "${YELLOW}📤 Uploading files to server...${NC}"

# Create directory on server if it doesn't exist
ssh "$SERVER_USER@$SERVER_HOST" "mkdir -p $SERVER_PATH"

# Upload files using rsync
rsync -avz --progress \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='deploy-to-server.sh' \
  ./* "$SERVER_USER@$SERVER_HOST:$SERVER_PATH/"

echo -e "${YELLOW}🔧 Setting permissions on server...${NC}"

# Set proper permissions
ssh "$SERVER_USER@$SERVER_HOST" << 'ENDSSH'
cd /var/www/jobone.in/jobscrap
chmod 755 *.php
chmod 755 assets
chmod 644 assets/*
chown -R www-data:www-data .
ENDSSH

echo -e "${GREEN}✅ Deployment complete!${NC}"
echo ""
echo "📝 Next steps:"
echo "1. SSH into your server and edit config.php with your API tokens"
echo "2. Access the tool at: https://jobone.in/jobscrap/"
echo ""
echo "To edit config on server:"
echo "  ssh $SERVER_USER@$SERVER_HOST"
echo "  nano $SERVER_PATH/config.php"

# Cleanup
rm -rf "$TEMP_DIR"
