# BLBGenSix AI - Universal AI Generation + Media Platform

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2+-orange.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![Vue Version](https://img.shields.io/badge/Vue-3.x-green.svg)](https://vuejs.org)

## 🚀 Project Overview

BLBGenSix AI is a comprehensive universal AI generation and media platform that combines cutting-edge artificial intelligence with advanced web scraping capabilities. The platform provides users with tools to generate content, scrape media from 802+ sources, stream music from 28+ platforms, monetize through 10 revenue streams, and transact via 7 cryptocurrency networks.

### Key Highlights
- **Universal AI Generation**: Text, image, video, audio, and code generation
- **802 Integrated Scrapers**: Web, torrent, media, and data scraping
- **28 Music Sources**: Spotify, Apple Music, SoundCloud, YouTube Music, and more
- **10 Revenue Streams**: Subscriptions, pay-per-use, affiliate, ads, crypto payments
- **7 Crypto Networks**: Bitcoin, Ethereum, Solana, Polygon, BSC, Avalanche, Arbitrum

---

## 🏗️ Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Cloudflare Edge                         │
│  (CDN, WAF, DDoS Protection, SSL/TLS, Pages, Workers)        │
└───────────────────────────┬─────────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────────┐
│                    Nginx Reverse Proxy                          │
│              (Load Balancing, Rate Limiting)                    │
└─────────────┬─────────────────────────┬─────────────────────────┘
              │                         │
    ┌─────────▼─────────┐    ┌─────────▼─────────┐
    │  Frontend Layer   │    │   Backend Layer   │
    │  ┌─────────────┐ │    │  ┌─────────────┐ │
    │  │ Vue 3 + Vite│ │    │  │ Laravel 11  │ │
    │  │ Tailwind CSS│ │    │  │ PHP 8.2+    │ │
    │  │ Pinia Store │ │    │  │ Sanctum     │ │
    │  └─────────────┘ │    │  └─────────────┘ │
    └───────────────────┘    └─────────┬─────────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    │                 │                 │
            ┌───────▼───────┐ ┌──────▼──────┐ ┌───────▼────────┐
            │  Node.js API  │ │ PostgreSQL  │ │     Redis      │
            │  (Scrapers,   │ │  (Primary   │ │  (Cache,      │
            │  Music, AI)   │ │   DB)       │ │   Sessions)   │
            └───────┬───────┘ └─────────────┘ └───────────────┘
                    │
            ┌───────▼───────────────────────────────┐
            │        OmniScraper Engine              │
            │  ┌─────────────────────────────────┐   │
            │  │ 802 Scrapers │ WebTorrent │ DHT │   │
            │  │ RSS │ Music │ IMDB │ Search  │   │
            │  └─────────────────────────────────┘   │
            └─────────────────────────────────────────┘
```

---

## ✨ Feature List

### AI Generation Capabilities
- **Text Generation**: Articles, blogs, code, documentation, creative writing
- **Image Generation**: Stable Diffusion, DALL-E, Midjourney-style outputs
- **Video Generation**: Short clips, animations, AI avatars
- **Audio Generation**: Voice synthesis, music composition, sound effects
- **Code Generation**: Python, JavaScript, PHP, Go, Rust, and 40+ languages

### Scraping & Data Collection
- **802 Scrapers**: Covering e-commerce, social media, news, media, torrents
- **WebTorrent Integration**: P2P file sharing and streaming
- **DHT Network**: Distributed hash table for decentralized discovery
- **RSS Aggregation**: Feed parsing and content monitoring
- **IMDB Integration**: Movie, TV show, and celebrity data
- **Unified Search**: Cross-platform search across all sources

### Music Platform
- **28 Music Sources**: Spotify, Apple Music, SoundCloud, Tidal, Deezer, etc.
- **Playlist Management**: Create, share, and collaborate on playlists
- **Audio Streaming**: High-quality streaming with offline support
- **Metadata Enrichment**: Tags, lyrics, artist info, album art

### Revenue & Monetization
- **10 Revenue Streams**:
  1. Monthly/annual subscriptions
  2. Pay-per-generation credits
  3. Affiliate marketing integration
  4. Ad revenue sharing
  5. Crypto payments (BTC, ETH, SOL, etc.)
  6. API access tiers
  7. White-label licensing
  8. Custom integration services
  9. Data export fees
  10. Premium support packages

### Cryptocurrency Integration
- **7 Crypto Networks**: Bitcoin, Ethereum, Solana, Polygon, BSC, Avalanche, Arbitrum
- **Wallet Integration**: Connect MetaMask, Phantom, WalletConnect
- **Smart Contracts**: Automated payments and royalty distribution
- **NFT Support**: Mint and trade AI-generated assets

---

## 🛠️ Tech Stack

| Component          | Technology                          | Version |
|--------------------|-------------------------------------|---------|
| **Backend Framework** | Laravel                            | 11.x    |
| **Frontend Framework** | Vue.js                             | 3.x     |
| **Frontend Build**  | Vite                                | 5.x     |
| **Styling**         | Tailwind CSS                        | 3.x     |
| **State Management** | Pinia                               | 2.x     |
| **API Layer**       | Node.js + Express                   | 20.x    |
| **Database**        | PostgreSQL                          | 16.x    |
| **Cache**           | Redis                               | 7.x     |
| **Search Engine**   | Meilisearch                         | 1.x     |
| **Queue**           | Redis Queue / Laravel Horizon       | -       |
| **Containerization**| Docker + Docker Compose             | 24.x    |
| **Web Server**      | Nginx                               | 1.25+   |
| **PHP Runtime**     | PHP-FPM                             | 8.2+    |
| **CDN/Edge**        | Cloudflare                          | -       |
| **CI/CD**           | GitHub Actions                      | -       |

---

## ⚡ Quick Start

Get the platform running in 3 commands:

```bash
# 1. Clone the repository
git clone https://github.com/mgtechgroup/BLBGenSix-AI.git
cd BLBGenSix-AI

# 2. Setup environment and install dependencies
cp .env.example .env && composer install && npm install

# 3. Start all services with Docker
docker-compose up -d
```

---

## 📦 Full Installation Guide

### Prerequisites
- **OS**: Windows 10/11, macOS 12+, Ubuntu 22.04+, or any Linux distribution
- **PHP**: 8.2+ with extensions (bcmath, curl, gd, mbstring, redis, pgsql, xml, zip)
- **Node.js**: 20.x LTS
- **Composer**: 2.x
- **PostgreSQL**: 16.x
- **Redis**: 7.x
- **Docker**: 24.x + Docker Compose
- **Git**: 2.x

### Step 1: Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=blbgensixai
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Configure Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Configure Cloudflare (optional)
CLOUDFLARE_API_KEY=your_api_key
CLOUDFLARE_ZONE_ID=your_zone_id
```

### Step 2: Install Dependencies

```bash
# PHP dependencies
composer install --optimize-autoloader

# Node.js dependencies
npm install

# Build frontend assets
npm run build
```

### Step 3: Database Setup

```bash
# Run migrations
php artisan migrate

# Seed initial data
php artisan db:seed

# Create storage link
php artisan storage:link
```

### Step 4: Start Services

```bash
# Start Laravel development server
php artisan serve &

# Start Node.js API server
cd node-api && npm start &

# Start queue worker
php artisan queue:work
```

---

## 🐳 Docker Deployment

The platform includes a complete Docker Compose setup for one-command deployment:

```yaml
# docker-compose.yml (included in repository)
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile.app
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
      - ./storage:/var/www/html/storage
    environment:
      - APP_ENV=production
    depends_on:
      - postgres
      - redis

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: blbgensixai
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: secure_password
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d
      - ./storage/app/public:/var/www/html/storage/app/public
    depends_on:
      - app

volumes:
  postgres_data:
  redis_data:
```

Run with:
```bash
docker-compose up -d --build
```

---

## ☁️ Cloudflare Pages Deployment

### Frontend Deployment

1. **Connect Repository**: Link GitHub repo to Cloudflare Pages
2. **Build Configuration**:
   - Build command: `npm run build`
   - Build output directory: `dist`
   - Node.js version: `20.x`
3. **Environment Variables**: Add all required vars from `.env.example`
4. **Custom Domain**: Configure `blbgensixai.club` in Cloudflare DNS

### Edge Functions
Deploy Laravel API as Cloudflare Workers:
```bash
npm install -g wrangler
wrangler login
wrangler deploy
```

---

## 🖥️ Production Server Deployment (45.32.134.145)

### Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y nginx postgresql redis-server php8.2-fpm composer

# Clone repository
cd /var/www
sudo git clone https://github.com/mgtechgroup/BLBGenSix-AI.git
sudo chown -R www-data:www-data BLBGenSix-AI

# Install PHP dependencies
cd BLBGenSix-AI
composer install --no-dev --optimize-autoloader

# Configure Nginx (see nginx/ directory)
sudo cp nginx/blbgensixai.club.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/blbgensixai.club.conf /etc/nginx/sites-enabled/

# Setup SSL with Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d blbgensixai.club -d www.blbgensixai.club

# Start services
sudo systemctl restart nginx php8.2-fpm redis postgresql
```

---

## 📡 API Documentation

### Authentication
All API endpoints require Bearer token authentication:
```
Authorization: Bearer <token>
```

### Endpoint Reference

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| **Auth** |
| POST | `/api/auth/register` | Register new user | No |
| POST | `/api/auth/login` | Login user | No |
| POST | `/api/auth/logout` | Logout user | Yes |
| GET | `/api/auth/user` | Get current user | Yes |
| **Generation** |
| POST | `/api/generate/text` | Generate text content | Yes |
| POST | `/api/generate/image` | Generate image | Yes |
| POST | `/api/generate/video` | Generate video | Yes |
| POST | `/api/generate/audio` | Generate audio | Yes |
| POST | `/api/generate/code` | Generate code | Yes |
| **Scraping** |
| GET | `/api/scrape/search` | Unified search | Yes |
| POST | `/api/scrape/url` | Scrape specific URL | Yes |
| GET | `/api/scrape/torrent` | Search torrents | Yes |
| GET | `/api/scrape/music` | Search music | Yes |
| GET | `/api/scrape/rss` | Fetch RSS feeds | Yes |
| **Music** |
| GET | `/api/music/search` | Search all music sources | Yes |
| GET | `/api/music/playlist` | Get user playlists | Yes |
| POST | `/api/music/playlist` | Create playlist | Yes |
| GET | `/api/music/stream/{id}` | Stream track | Yes |
| **Billing** |
| GET | `/api/billing/plans` | List subscription plans | No |
| POST | `/api/billing/subscribe` | Subscribe to plan | Yes |
| GET | `/api/billing/invoices` | List user invoices | Yes |
| POST | `/api/billing/crypto/pay` | Crypto payment | Yes |
| **Admin** |
| GET | `/api/admin/users` | List all users | Admin |
| GET | `/api/admin/stats` | Platform statistics | Admin |
| POST | `/api/admin/scraper/add` | Add new scraper | Admin |

---

## 📁 Project Structure

```
Brilliantly-Bussy-ImagGoo/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # Laravel controllers
│   │   ├── Middleware/       # Custom middleware
│   │   └── Requests/         # Form requests
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic services
│   └── Providers/            # Service providers
├── Modules/                  # Modular components
│   ├── Generator/            # AI generation module
│   ├── Scraper/              # Scraping module
│   ├── Music/                # Music platform module
│   ├── Billing/              # Billing module
│   └── Admin/                # Admin dashboard module
├── resources/
│   ├── js/                   # Vue 3 components
│   │   ├── components/       # Reusable components
│   │   ├── pages/            # Page components
│   │   ├── stores/           # Pinia stores
│   │   └── router/           # Vue router config
│   ├── css/                  # Stylesheets
│   └── views/                # Laravel Blade views
├── routes/
│   ├── api.php               # API routes
│   └── web.php               # Web routes
├── docker/                   # Docker configurations
├── nginx/                    # Nginx configurations
├── node-api/                 # Node.js API server
├── tests/                    # PHPUnit tests
└── docs/                     # Additional documentation
```

---

## 🔒 Security Features

- **Encryption**: AES-256 encryption for sensitive data
- **Authentication**: Laravel Sanctum token-based auth
- **Rate Limiting**: API rate limiting (60 requests/minute)
- **CORS Protection**: Configurable cross-origin policies
- **XSS Protection**: Input sanitization and output escaping
- **SQL Injection**: Eloquent ORM parameterized queries
- **CSRF Protection**: Token-based CSRF prevention
- **Cloudflare WAF**: Web application firewall
- **DDoS Protection**: Cloudflare DDoS mitigation
- **SSL/TLS**: Force HTTPS with HSTS headers
- **2FA**: Optional two-factor authentication
- **Audit Logs**: Complete action logging

---

## 🤝 Contributing Guidelines

We welcome contributions! Please follow these steps:

1. **Fork the Repository**
   ```bash
   git fork https://github.com/mgtechgroup/BLBGenSix-AI.git
   ```

2. **Create Feature Branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```

3. **Follow Coding Standards**
   - PHP: PSR-12 standard
   - JavaScript: ESLint + Prettier
   - Vue: Vue style guide
   - Commit messages: Conventional Commits

4. **Write Tests**
   ```bash
   # PHP tests
   php artisan test

   # JavaScript tests
   npm run test
   ```

5. **Submit Pull Request**
   - Describe changes clearly
   - Link related issues
   - Ensure CI passes

### Code Review Process
- All PRs require at least 1 reviewer approval
- Automated tests must pass
- Code coverage should not decrease
- Documentation must be updated

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

```
MIT License

Copyright (c) 2024 MG Tech Group

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 📞 Support

- **Documentation**: [docs.blbgensixai.club](https://docs.blbgensixai.club)
- **GitHub Issues**: [Report bugs](https://github.com/mgtechgroup/BLBGenSix-AI/issues)
- **Discord Community**: [Join our server](https://discord.gg/blbgensixai)
- **Email**: support@blbgensixai.club

---

## 🙏 Acknowledgments

- Laravel team for the amazing framework
- Vue.js team for the progressive framework
- Cloudflare for edge infrastructure
- All open-source contributors whose libraries power this platform
