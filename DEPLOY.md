# Deployment Guide - BLBGenSix AI

## Prerequisites
- PHP 8.3+
- PostgreSQL 15+
- Redis 7+
- Node.js 20+
- Composer 2.6+
- Nginx or Apache

## 1. Server Setup

```bash
# Install PHP 8.3
sudo apt install php8.3 php8.3-{fpm,pgsql,redis,mbstring,xml,ctype,iconv,intl,bcmath,sodium,zip,gd,curl}

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## 2. Deploy Code

```bash
# Clone from GitHub
git clone https://github.com/YOUR_USERNAME/BLBGenSix-AI.git
cd BLBGenSix-AI

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Setup environment
cp .env.example .env
php artisan key:generate

# Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-www storage bootstrap/cache
```

## 3. Database

```bash
# Create PostgreSQL database
sudo -u postgres psql
CREATE DATABASE blbgensixai;
CREATE USER blbgensixai_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE blbgensixai TO blbgensixai_user;
\q

# Run migrations + seeders
php artisan migrate --seed
```

## 4. Configuration

```bash
# Edit .env
nano .env
```

Set:
- `APP_URL=https://blbgensixai.club`
- Database credentials
- Redis credentials
- OpenAI API key
- Stripe keys
- Crypto deposit addresses

## 5. Nginx

```bash
# Copy nginx config
sudo cp nginx/blbgensixai.club.conf /etc/nginx/sites-available/blbgensixai.club
sudo ln -s /etc/nginx/sites-available/blbgensixai.club /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 6. SSL

```bash
# Let's Encrypt
sudo certbot --nginx -d blbgensixai.club -d www.blbgensixai.club
```

## 7. Queue Worker

```bash
# Start Horizon
php artisan horizon

# Or use supervisor
sudo apt install supervisor
```

Add `/etc/supervisor/conf.d/blbgensixai.conf`:
```
[program:blbgensixai-horizon]
process_name=%(program_name)s
command=php /var/www/blbgensixai.club/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/blbgensixai.club/storage/logs/horizon.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start blbgensixai-horizon
```

## 8. Cron

```bash
crontab -e
```

Add:
```
* * * * * cd /var/www/blbgensixai.club && php artisan schedule:run >> /dev/null 2>&1
```

## 9. First Launch

Visit `https://blbgensixai.club` and you should see the landing page.

API health check:
```bash
curl https://blbgensixai.club/api/v1/health
```

Expected response:
```json
{"status":"healthy","timestamp":"2026-05-02T..."}
```

## 10. Revenue Activation Checklist

- [ ] Stripe account connected + products created
- [ ] PayPal developer app created
- [ ] Crypto deposit addresses configured in .env
- [ ] At least one income platform API key configured (OnlyFans/Fansly)
- [ ] Ad network accounts created (Adsterra/ExoClick/TrafficJunky)
- [ ] Admin user registered and verified
- [ ] First subscription plan active
- [ ] Cron running for auto-posting
- [ ] Horizon queue worker running
