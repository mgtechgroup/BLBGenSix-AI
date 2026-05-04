#!/bin/bash
# BLBGenSix AI + UniScrape — Full Server Deployment Script
# Server: 45.32.134.145 | Domain: blbgensixai.club
# Run as root: bash deploy.sh

set -e
SERVER_IP="45.32.134.145"
DOMAIN="blbgensixai.club"
WWW="/var/www/${DOMAIN}"

echo "============================================"
echo " BLBGenSix AI + UniScrape Deployment"
echo " Server: ${SERVER_IP}"
echo " Domain: ${DOMAIN}"
echo "============================================"

# 1. System Update
echo "[1/8] Updating system..."
apt update && apt upgrade -y

# 2. Install Dependencies
echo "[2/8] Installing dependencies..."
apt install -y nginx certbot python3-certbot-nginx postgresql redis-server \
    php8.3 php8.3-fpm php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml \
    php8.3-ctype php8.3-iconv php8.3-intl php8.3-bcmath php8.3-sodium \
    php8.3-zip php8.3-gd php8.3-curl nodejs npm docker.io docker-compose \
    vips libvips-dev python3-pip

pip3 install pyvips stashapp-tools requests cloudscraper beautifulsoup4 lxml

# 3. Clone Repos
echo "[3/8] Cloning repositories..."
mkdir -p ${WWW}
cd ${WWW}

git clone https://github.com/mgtechgroup/BLBGenSix-AI.git backend
git clone https://github.com/mgtechgroup/UniScrape.git scraper
git clone https://github.com/mgtechgroup/CommunityScrapers.git scrapers

# 4. Setup Laravel Backend
echo "[4/8] Setting up Laravel..."
cd ${WWW}/backend
cp .env.example .env
sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=blbgensixai|" .env
sed -i "s|REDIS_HOST=.*|REDIS_HOST=127.0.0.1|" .env
php -r "echo base64_encode(random_bytes(32));" | xargs -I{} sed -i "s|APP_KEY=.*|APP_KEY=base64:{}|" .env

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed
php artisan optimize
chown -R www-data:www-data .
chmod -R 775 storage bootstrap/cache

# 5. Setup UniScrape
echo "[5/8] Setting up UniScrape..."
cd ${WWW}/scraper
npm install
cp .env.example .env
ln -sf ${WWW}/scrapers/scrapers /scrapers

# 6. Setup Nginx
echo "[6/8] Configuring Nginx..."
cp ${WWW}/backend/nginx/blbgensixai.club.conf /etc/nginx/sites-available/${DOMAIN}
ln -sf /etc/nginx/sites-available/${DOMAIN} /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# 7. SSL
echo "[7/8] Obtaining SSL certificate..."
certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} --non-interactive --agree-tos --email admin@${DOMAIN}

# 8. Start Services
echo "[8/8] Starting all services..."
systemctl enable --now php8.3-fpm postgresql redis-server

# Start UniScrape
cd ${WWW}/scraper
cat > /etc/systemd/system/uniscrape.service << SERVICE
[Unit]
Description=UniScrape Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=${WWW}/scraper
Environment=PORT=9876
Environment=SCRAPERS_PATH=${WWW}/scrapers/scrapers
ExecStart=/usr/bin/node server/index.js
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable --now uniscrape

# Start Docker services
cd ${WWW}/scraper
docker-compose -f docker/docker-compose.yml up -d

# Start Horizon
cd ${WWW}/backend
cat > /etc/systemd/system/blbgensixai-horizon.service << HORIZON
[Unit]
Description=BLBGenSix AI Horizon
After=network.target redis-server.service

[Service]
Type=simple
User=www-data
WorkingDirectory=${WWW}/backend
ExecStart=/usr/bin/php artisan horizon
Restart=always

[Install]
WantedBy=multi-user.target
HORIZON

systemctl enable --now blbgensixai-horizon

# Add cron
(crontab -l 2>/dev/null; echo "* * * * * php ${WWW}/backend/artisan schedule:run >> /dev/null 2>&1") | crontab -

echo ""
echo "============================================"
echo " DEPLOYMENT COMPLETE"
echo "============================================"
echo " Site:      https://${DOMAIN}"
echo " API:       https://${DOMAIN}/api/v1/health"
echo " Stremio:   https://${DOMAIN}/stremio/manifest.json"
echo " Stash:     https://${DOMAIN}/stash"
echo " Search:    https://${DOMAIN}/search"
echo ""
echo " To check status:"
echo "   systemctl status uniscrape"
echo "   systemctl status blbgensixai-horizon"
echo "   docker ps"
echo "============================================"
