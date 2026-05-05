# BLBGenSix AI - Deployment Guide

Complete deployment guide for BLBGenSix AI platform covering local development, Docker, Cloudflare Pages, and production server deployment.

---

## 📋 Prerequisites Checklist

Before deploying, ensure you have:

- [ ] **Server**: VPS with 4+ CPU cores, 8GB+ RAM, 100GB+ SSD
- [ ] **OS**: Ubuntu 22.04 LTS (recommended) or Windows Server 2022
- [ ] **Domain**: `blbgensixai.club` with DNS access
- [ ] **Cloudflare Account**: Active account with domain added
- [ ] **SSL Certificate**: Let's Encrypt or Cloudflare SSL
- [ ] **Software**:
  - [ ] PHP 8.2+ with extensions (bcmath, curl, gd, mbstring, pgsql, redis, xml, zip)
  - [ ] Composer 2.x
  - [ ] Node.js 20.x LTS
  - [ ] PostgreSQL 16.x
  - [ ] Redis 7.x
  - [ ] Nginx 1.25+
  - [ ] Docker 24.x + Docker Compose (for containerized deployment)
- [ ] **GitHub Access**: Repository cloned and access configured
- [ ] **Environment Variables**: All required API keys and credentials

---

## 💻 Local Development Setup

### Step 1: Clone Repository

```bash
git clone https://github.com/mgtechgroup/BLBGenSix-AI.git
cd BLBGenSix-AI
```

### Step 2: Configure Environment

```bash
# Copy example environment file
cp .env.example .env

# Edit .env with your local configuration
nano .env
```

Required local environment variables:
```env
APP_NAME="BLBGenSix AI"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=blbgensixai_dev
DB_USERNAME=postgres
DB_PASSWORD=local_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# AI Service APIs (optional for development)
OPENAI_API_KEY=
STABILITY_API_KEY=
REPLICATE_API_KEY=

# Crypto (optional for development)
ETHEREUM_RPC=
SOLANA_RPC=

# Cloudflare (optional for development)
CLOUDFLARE_API_KEY=
CLOUDFLARE_ZONE_ID=
```

### Step 3: Install Dependencies

```bash
# PHP dependencies
composer install

# Node.js dependencies
npm install

# Build frontend assets
npm run dev
```

### Step 4: Database Setup

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database with sample data
php artisan db:seed

# Create storage symlink
php artisan storage:link
```

### Step 5: Start Development Servers

```bash
# Terminal 1: Laravel backend
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Node.js API server
cd node-api
npm install
npm run dev

# Terminal 3: Vue.js hot reload
npm run dev

# Terminal 4: Queue worker
php artisan queue:work --tries=3
```

Access the application at `http://localhost:8000`

---

## 🐳 Docker Compose Deployment (Full Stack)

### Step 1: Prepare Docker Environment

```bash
cd /path/to/BLBGenSix-AI
cp .env.example .env.docker
```

Configure `.env.docker`:
```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=postgres
REDIS_HOST=redis
```

### Step 2: Start Docker Services

```bash
# Build and start all containers
docker-compose up -d --build

# Verify containers are running
docker-compose ps

# View logs
docker-compose logs -f
```

### Step 3: Initialize Docker Environment

```bash
# Run migrations in app container
docker-compose exec app php artisan migrate --force

# Seed database
docker-compose exec app php artisan db:seed --force

# Generate key
docker-compose exec app php artisan key:generate

# Create storage link
docker-compose exec app php artisan storage:link

# Clear caches
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
```

### Step 4: Verify Deployment

```bash
# Test backend
curl http://localhost/api/health

# Test frontend
curl http://localhost

# View container logs
docker-compose logs -f app
docker-compose logs -f nginx
```

### Docker Compose Management Commands

```bash
# Stop all services
docker-compose down

# Restart specific service
docker-compose restart app

# Scale services
docker-compose up -d --scale app=3

# Update and rebuild
git pull
docker-compose up -d --build

# Backup database
docker-compose exec postgres pg_dump -U postgres blbgensixai > backup.sql

# Restore database
docker-compose exec -T postgres psql -U postgres blbgensixai < backup.sql
```

---

## ☁️ Cloudflare Pages Deployment (Frontend)

### Step 1: Connect Repository to Cloudflare Pages

1. Login to [Cloudflare Dashboard](https://dash.cloudflare.com)
2. Navigate to **Pages** → **Create a project** → **Connect to Git**
3. Select GitHub repository: `mgtechgroup/BLBGenSix-AI`
4. Configure build settings:

### Step 2: Configure Build Settings

| Setting | Value |
|---------|-------|
| **Production branch** | `master` |
| **Build command** | `npm run build` |
| **Build output directory** | `dist` |
| **Node.js version** | `20.x` |
| **Environment variables** | See below |

### Step 3: Set Environment Variables

In Cloudflare Pages → Settings → Environment variables:

```env
VITE_API_URL=https://blbgensixai.club/api
VITE_APP_NAME=BLBGenSix AI
VITE_CLOUDFLARE_R2_BUCKET=your_bucket
VITE_STRIPE_PUBLIC_KEY=pk_live_...
```

### Step 4: Configure Custom Domain

1. Navigate to **Pages** → **Your Project** → **Custom domains**
2. Add `blbgensixai.club` and `www.blbgensixai.club`
3. Update DNS at your registrar:
   ```
   CNAME blbgensixai.club → your-project.pages.dev
   CNAME www.blbgensixai.club → your-project.pages.dev
   ```

### Step 5: Enable Cloudflare Features

```bash
# Install Wrangler CLI
npm install -g wrangler

# Login to Cloudflare
wrangler login

# Deploy edge functions (if using Workers)
wrangler deploy
```

---

## 🖥️ Production Server Deployment (45.32.134.145)

### Step 1: Initial Server Setup

```bash
# SSH into server
ssh root@45.32.134.145

# Update system
apt update && apt upgrade -y

# Create non-root user
adduser deployer
usermod -aG sudo deployer

# Configure firewall
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
```

### Step 2: Install Dependencies

```bash
# Add PHP repository
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP and extensions
apt install -y php8.2-fpm php8.2-cli php8.2-common php8.2-curl \
  php8.2-mbstring php8.2-pgsql php8.2-redis php8.2-xml php8.2-zip \
  php8.2-bcmath php8.2-gd unzip git curl

# Install PostgreSQL
apt install -y postgresql postgresql-contrib

# Install Redis
apt install -y redis-server

# Install Nginx
apt install -y nginx

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### Step 3: Configure PostgreSQL

```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE blbgensixai;
CREATE USER blbuser WITH PASSWORD 'secure_password_here';
GRANT ALL PRIVILEGES ON DATABASE blbgensixai TO blbuser;
\q
```

### Step 4: Deploy Application

```bash
# Clone repository
cd /var/www
git clone https://github.com/mgtechgroup/BLBGenSix-AI.git
cd BLBGenSix-AI

# Set permissions
chown -R www-data:www-data /var/www/BLBGenSix-AI
chmod -R 755 /var/www/BLBGenSix-AI

# Install dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Build frontend
npm install
npm run build
```

### Step 5: Configure Environment

```bash
# Copy and edit environment file
cp .env.example .env
nano .env
```

Production `.env` configuration:
```env
APP_NAME="BLBGenSix AI"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://blbgensixai.club

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=blbgensixai
DB_USERNAME=blbuser
DB_PASSWORD=secure_password_here

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mail.blbgensixai.club
MAIL_PORT=587
MAIL_USERNAME=noreply@blbgensixai.club
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@blbgensixai.club
MAIL_FROM_NAME="${APP_NAME}"

# Generate key
sudo -u www-data php artisan key:generate

# Run migrations
sudo -u www-data php artisan migrate --force

# Cache configuration
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### Step 6: Configure Nginx

Create `/etc/nginx/sites-available/blbgensixai.club`:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name blbgensixai.club www.blbgensixai.club;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name blbgensixai.club www.blbgensixai.club;

    root /var/www/BLBGenSix-AI/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/blbgensixai.club/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/blbgensixai.club/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable site:
```bash
ln -s /etc/nginx/sites-available/blbgensixai.club /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

---

## 🔐 SSL Certificate Setup

### Option 1: Let's Encrypt (Recommended)

```bash
# Install Certbot
apt install certbot python3-certbot-nginx -y

# Obtain certificate
certbot --nginx -d blbgensixai.club -d www.blbgensixai.club

# Auto-renewal (add to crontab)
echo "0 12 * * * /usr/bin/certbot renew --quiet" | crontab -
```

### Option 2: Cloudflare SSL (Origin Certificate)

1. In Cloudflare Dashboard → SSL/TLS → Origin Server
2. Create certificate for `blbgensixai.club` and `*.blbgensixai.club`
3. Copy certificate and private key
4. Save to `/etc/ssl/cloudflare/`
5. Update Nginx SSL paths to Cloudflare certificate

Set SSL mode to **Full (Strict)** in Cloudflare dashboard.

---

## 🌐 DNS Configuration for blbgensixai.club

Configure these DNS records in Cloudflare:

| Type | Name | Content | TTL | Proxy Status |
|------|------|---------|-----|--------------|
| A | blbgensixai.club | 45.32.134.145 | Auto | Proxied |
| A | www.blbgensixai.club | 45.32.134.145 | Auto | Proxied |
| A | api.blbgensixai.club | 45.32.134.145 | Auto | Proxied |
| A | admin.blbgensixai.club | 45.32.134.145 | Auto | Proxied |
| MX | blbgensixai.club | mail.blbgensixai.club | Auto | - |
| TXT | blbgensixai.club | v=spf1 include:_spf.cloudflare.net ~all | Auto | - |

---

## 🔧 Systemd Services Setup

### Laravel Queue Worker

Create `/etc/systemd/system/blb-queue.service`:
```ini
[Unit]
Description=BLBGenSix AI Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/BLBGenSix-AI
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Node.js API Service

Create `/etc/systemd/system/blb-api.service`:
```ini
[Unit]
Description=BLBGenSix AI Node API
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/BLBGenSix-AI/node-api
ExecStart=/usr/bin/node server.js
Restart=on-failure
RestartSec=5
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

Enable and start services:
```bash
systemctl daemon-reload
systemctl enable blb-queue blb-api
systemctl start blb-queue blb-api
systemctl status blb-queue blb-api
```

---

## 📊 Monitoring and Logging Setup

### Application Logging

```bash
# Laravel logs
tail -f /var/www/BLBGenSix-AI/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log

# PHP-FPM logs
tail -f /var/log/php8.2-fpm.log
```

### System Monitoring with Prometheus + Grafana

```bash
# Install Node Exporter
cd /tmp
wget https://github.com/prometheus/node_exporter/releases/download/v1.7.0/node_exporter-1.7.0.linux-amd64.tar.gz
tar xvfz node_exporter-*.tar.gz
mv node_exporter-*/node_exporter /usr/local/bin/

# Create systemd service
# (create /etc/systemd/system/node_exporter.service)
systemctl enable node_exporter
systemctl start node_exporter
```

### Health Check Endpoint

The application provides a health check endpoint at `/api/health`:
```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T10:30:00Z",
  "services": {
    "database": "connected",
    "redis": "connected",
    "queue": "running"
  }
}
```

Configure uptime monitoring (UptimeRobot, Pingdom, or Cloudflare Health Checks).

---

## 💾 Backup and Restore Procedures

### Database Backup

```bash
# Automated daily backup script
cat > /usr/local/bin/backup-db.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/var/backups/blbgensixai

mkdir -p $BACKUP_DIR
pg_dump -U blbuser blbgensixai | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete
EOF

chmod +x /usr/local/bin/backup-db.sh

# Add to crontab (daily at 2 AM)
echo "0 2 * * * /usr/local/bin/backup-db.sh" | crontab -
```

### File Backup

```bash
# Backup application files
tar -czf /var/backups/blbgensixai/app_$(date +%Y%m%d).tar.gz \
  /var/www/BLBGenSix-AI \
  --exclude=/var/www/BLBGenSix-AI/node_modules \
  --exclude=/var/www/BLBGenSix-AI/vendor \
  --exclude=/var/www/BLBGenSix-AI/storage/logs
```

### Restore Database

```bash
# Restore from backup
gunzip < /var/backups/blbgensixai/db_20240115_020000.sql.gz | \
  psql -U blbuser blbgensixai
```

### Automated Backup to Cloudflare R2

```bash
# Install AWS CLI
pip install awscli

# Configure for R2
export AWS_ACCESS_KEY_ID=your_r2_access_key
export AWS_SECRET_ACCESS_KEY=your_r2_secret_key
export AWS_ENDPOINT_URL=https://your-account.r2.cloudflarestorage.com

# Upload backup
aws s3 cp /var/backups/blbgensixai/db_latest.sql.gz s3://blbgensixai-backups/
```

---

## 🔍 Troubleshooting Common Issues

### Issue: 502 Bad Gateway
```bash
# Check PHP-FPM status
systemctl status php8.2-fpm

# Check Nginx error log
tail -f /var/log/nginx/error.log

# Restart services
systemctl restart php8.2-fpm nginx
```

### Issue: Database Connection Error
```bash
# Check PostgreSQL status
systemctl status postgresql

# Test connection
psql -U blbuser -d blbgensixai -h 127.0.0.1

# Check pg_hba.conf
nano /etc/postgresql/16/main/pg_hba.conf
```

### Issue: Redis Connection Failed
```bash
# Check Redis status
systemctl status redis

# Test connection
redis-cli ping

# Check Redis configuration
nano /etc/redis/redis.conf
```

### Issue: Queue Jobs Not Processing
```bash
# Check queue worker status
systemctl status blb-queue

# View failed jobs
sudo -u www-data php artisan queue:failed

# Retry failed jobs
sudo -u www-data php artisan queue:retry all
```

### Issue: Permissions Error
```bash
# Fix storage permissions
chown -R www-data:www-data /var/www/BLBGenSix-AI/storage
chmod -R 755 /var/www/BLBGenSix-AI/storage

# Fix bootstrap cache
chown -R www-data:www-data /var/www/BLBGenSix-AI/bootstrap/cache
```

### Issue: SSL Certificate Expired
```bash
# Renew Let's Encrypt certificate
certbot renew --force

# Check certificate expiry
openssl x509 -enddate -noout -in /etc/letsencrypt/live/blbgensixai.club/cert.pem
```

### Performance Optimization

```bash
# Enable Opcache
nano /etc/php/8.2/fpm/conf.d/20-opcache.ini

# Add these settings:
opcache.enable=1
opcache.memory_consumption=512
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0

# Restart PHP-FPM
systemctl restart php8.2-fpm

# Enable Gzip compression in Nginx
nano /etc/nginx/nginx.conf
# Add: gzip on; gzip_types text/plain text/css application/json application/javascript;
```

---

## 📞 Support

For deployment issues:
- **GitHub Issues**: [Report deployment problems](https://github.com/mgtechgroup/BLBGenSix-AI/issues)
- **Documentation**: [Full docs](https://docs.blbgensixai.club)
- **Email**: ops@blbgensixai.club
