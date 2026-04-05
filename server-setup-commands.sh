#!/bin/bash

# Run these commands on your jobone.in server
# You're currently at: ubuntu@ip-172-26-3-50:~/jobscrap

echo "📍 Current location: ~/jobscrap"
echo "🔧 Setting up jobscrap on server..."

# 1. Set permissions for PHP files
chmod 755 *.php

# 2. Find where your web root is
echo ""
echo "🔍 Finding web root directory..."
if [ -d "/var/www/html" ]; then
    WEB_ROOT="/var/www/html"
elif [ -d "/var/www/jobone.in" ]; then
    WEB_ROOT="/var/www/jobone.in"
elif [ -d "/usr/share/nginx/html" ]; then
    WEB_ROOT="/usr/share/nginx/html"
else
    echo "❓ Web root not found. Common locations:"
    echo "   - /var/www/html"
    echo "   - /var/www/jobone.in"
    echo "   - /usr/share/nginx/html"
    WEB_ROOT="/var/www/html"
fi

echo "📂 Web root: $WEB_ROOT"

# 3. Create symlink or copy files to web root
echo ""
echo "Choose deployment method:"
echo "1. Create symlink (recommended)"
echo "2. Copy files"
read -p "Enter choice (1 or 2): " choice

if [ "$choice" = "1" ]; then
    echo "🔗 Creating symlink..."
    sudo ln -sf ~/jobscrap $WEB_ROOT/jobscrap
    echo "✅ Symlink created: $WEB_ROOT/jobscrap -> ~/jobscrap"
else
    echo "📋 Copying files..."
    sudo cp -r ~/jobscrap $WEB_ROOT/
    echo "✅ Files copied to: $WEB_ROOT/jobscrap"
fi

# 4. Set proper ownership
echo ""
echo "👤 Setting ownership to www-data..."
sudo chown -R www-data:www-data $WEB_ROOT/jobscrap

# 5. Edit config.php
echo ""
echo "📝 Now edit config.php with your API tokens:"
echo "   nano ~/jobscrap/config.php"
echo ""
echo "Add your tokens:"
echo "   JOBONE_API_TOKEN - from your JobOne admin panel"
echo "   AGENTROUTER_API_KEY - from https://agentrouter.org"
echo ""
echo "🌐 Access your tool at: https://jobone.in/jobscrap/"
