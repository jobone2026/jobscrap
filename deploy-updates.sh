#!/bin/bash

# Deploy updates to server
# Run this from your LOCAL machine

echo "🚀 Deploying updates to jobone.in server..."

# Files to update
FILES="scrape.php config.php"

echo "📤 Uploading files..."
for file in $FILES; do
    echo "  → $file"
    scp $file ubuntu@jobone.in:~/jobscrap/
done

echo "📋 Copying to public directory on server..."
ssh ubuntu@jobone.in << 'ENDSSH'
cd ~/jobscrap
sudo cp scrape.php config.php /var/www/jobone/public/jobscrap/
sudo chown ubuntu:www-data /var/www/jobone/public/jobscrap/*
sudo chmod 755 /var/www/jobone/public/jobscrap/*.php
echo "✅ Files updated successfully!"
ENDSSH

echo ""
echo "🎉 Deployment complete!"
echo ""
echo "New features enabled:"
echo "  ✅ Strict AI content filtering (removes spam/promotional content)"
echo "  ✅ Auto-add Telegram & WhatsApp links to every post"
echo "  ✅ Configurable AI instructions"
echo ""
echo "Test at: https://jobone.in/jobscrap/"
