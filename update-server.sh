#!/bin/bash

# Quick update script to push scrape.php changes to server
echo "🔄 Updating scrape.php on jobone.in server..."

# Copy the updated file to server
scp scrape.php ubuntu@jobone.in:~/jobscrap/

# Also update in the public directory
ssh ubuntu@jobone.in << 'ENDSSH'
sudo cp ~/jobscrap/scrape.php /var/www/jobone/public/jobscrap/
sudo chown ubuntu:www-data /var/www/jobone/public/jobscrap/scrape.php
sudo chmod 755 /var/www/jobone/public/jobscrap/scrape.php
echo "✅ scrape.php updated successfully!"
ENDSSH

echo "🎉 Update complete! Test at https://jobone.in/jobscrap/"
