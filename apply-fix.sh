#!/bin/bash

# Run this script ON THE SERVER to apply the fixes
# Usage: bash apply-fix.sh

echo "🔧 Applying content cleaning fixes to scrape.php..."

cd ~/jobscrap

# Backup original
cp scrape.php scrape.php.backup

# Apply fixes using sed
echo "📝 Updating text removal patterns..."

# Update the textNodes check section
sed -i "s/str_contains(\$val, 'android app')/str_contains(\$val, 'android app') || \n            str_contains(\$val, 'mobile app') ||\n            str_contains(\$val, 'download mobile') ||\n            str_contains(\$val, 'arattai channel') ||\n            str_contains(\$val, 'join arattai') ||\n            str_contains(\$val, 'sarkari result')/g" scrape.php

echo "📋 Copying to public directory..."
sudo cp scrape.php /var/www/jobone/public/jobscrap/
sudo chown ubuntu:www-data /var/www/jobone/public/jobscrap/scrape.php
sudo chmod 755 /var/www/jobone/public/jobscrap/scrape.php

echo "✅ Fixes applied! Backup saved as scrape.php.backup"
echo "🌐 Test at: https://jobone.in/jobscrap/"
