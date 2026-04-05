# JobScrap - Auto Job Posting Tool

Automatically scrape job notifications and post them to JobOne.in portal.

## 🚀 Deployment to jobone.in Server

### Option 1: Using the deployment script

1. Edit `deploy-to-server.sh` and update:
   - `SERVER_USER` - your SSH username
   - `SERVER_HOST` - jobone.in
   - `SERVER_PATH` - where to deploy (e.g., `/var/www/jobone.in/jobscrap`)

2. Make the script executable and run:
   ```bash
   chmod +x deploy-to-server.sh
   ./deploy-to-server.sh
   ```

### Option 2: Manual deployment via FTP/SFTP

1. Upload all files to your server (e.g., `/var/www/jobone.in/jobscrap/`)
2. SSH into your server
3. Set permissions:
   ```bash
   cd /var/www/jobone.in/jobscrap
   chmod 755 *.php
   chown -R www-data:www-data .
   ```

### Option 3: Using Git on server

```bash
# SSH into your server
ssh user@jobone.in

# Navigate to web directory
cd /var/www/jobone.in

# Clone the repository
git clone https://github.com/jobone2026/jobscrap.git

# Set permissions
cd jobscrap
chmod 755 *.php
chown -R www-data:www-data .
```

## ⚙️ Configuration

1. Edit `config.php` on the server:
   ```bash
   nano /var/www/jobone.in/jobscrap/config.php
   ```

2. Add your API credentials:
   ```php
   define('JOBONE_API_TOKEN', 'your_actual_token_here');
   define('AGENTROUTER_API_KEY', 'your_agentrouter_key_here');
   ```

## 🌐 Nginx Configuration

Add this to your nginx config for jobone.in:

```nginx
location /jobscrap {
    alias /var/www/jobone.in/jobscrap;
    index index.php;
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
    }
}
```

Then reload nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 🔗 Access

After deployment, access at: `https://jobone.in/jobscrap/`

## 📝 Local Development

```bash
cd jobscrap
php -S localhost:8000
```

Open: `http://localhost:8000`

## 🛠️ Features

- Scrape job notifications from any URL
- AI-powered content enhancement
- Auto-detect job type (job, admit card, result, etc.)
- Extract important dates and links
- One-click publishing to JobOne.in
