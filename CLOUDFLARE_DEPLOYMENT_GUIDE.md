# E-Noteria CloudFlare Deployment Guide

## 1. CLOUDFLARE SETUP

### Step 1: Domain Setup (noteria.kosove.gov.al)

```bash
# 1. Create CloudFlare account
# Visit: https://dash.cloudflare.com/sign-up

# 2. Add your domain
# Domain: noteria.kosove.gov.al

# 3. Change nameservers at your registrar to:
# - cecilia.ns.cloudflare.com
# - neil.ns.cloudflare.com

# 4. Wait for DNS propagation (5-10 minutes)
nslookup noteria.kosove.gov.al @1.1.1.1  # Verify
```

### Step 2: DNS Records (Via CloudFlare Dashboard)

```
Type    Name                    Content                     TTL     Proxy
────────────────────────────────────────────────────────────────────────
A       noteria.kosove.gov.al   [Your Server IP]            Auto    Proxied (🟠)
CNAME   www                     noteria.kosove.gov.al       Auto    Proxied (🟠)
A       api                     [Your API Server IP]        Auto    Proxied (🟠)
MX      noteria.kosove.gov.al   mail.noteria.kosove.gov.al  Auto    -
TXT     -                       v=spf1 include:_spf.google.com ~all
```

### Step 3: SSL/TLS Configuration

```
Dashboard → SSL/TLS

1. Select "Full (strict)" mode
2. Enable "Always Use HTTPS"
3. Enable "Automatic HTTPS Rewrites"
4. Enable "HSTS" (Strict-Transport-Security)
5. Enable "Minimum TLS Version 1.2"
```

---

## 2. CLOUDFLARE WORKERS (Serverless Functions)

### Setup Workers for PHP Backend

```bash
# 1. Install Wrangler (CloudFlare CLI)
npm install -g wrangler

# 2. Login
wrangler login

# 3. Create worker project
wrangler init noteria-api

# 4. Configure wrangler.toml
```

### wrangler.toml

```toml
name = "noteria-api"
type = "javascript"
account_id = "your-account-id"
workers_dev = true
route = "api.noteria.kosove.gov.al/*"
zone_id = "your-zone-id"

[env.production]
name = "noteria-api-prod"
route = "https://api.noteria.kosove.gov.al/*"

[build]
command = "npm run build"
cwd = "./"
main = "src/index.js"

[triggers]
crons = ["0 */6 * * *"]  # Cron job every 6 hours

[[r2_buckets]]
binding = "BUCKET"
bucket_name = "noteria-files"

[[d1_databases]]
binding = "DB"
database_name = "noteria"
database_id = "your-db-id"

[[kv_namespaces]]
binding = "CACHE"
id = "your-kv-id"
preview_id = "your-preview-id"
```

---

## 3. CLOUDFLARE PAGES (Static + Dynamic)

### Option A: Deploy Static Site

```bash
# 1. Create GitHub deployment
git init
git add .
git commit -m "Initial commit"
git push origin main

# 2. Link to CloudFlare Pages
# Dashboard → Pages → Connect to Git
# Select repo: your-account/noteria
# Build settings:
#   - Build command: npm run build
#   - Build output directory: dist/

# 3. Auto-deploy on push
# (Every push to main = automatic deploy)
```

### Option B: Custom Build (Recommended)

```json
{
  "name": "noteria",
  "version": "1.0.0",
  "scripts": {
    "build": "webpack --mode production",
    "dev": "webpack --mode development --watch",
    "deploy": "wrangler publish"
  },
  "dependencies": {
    "cloudflare": "^latest",
    "axios": "^1.0.0"
  }
}
```

---

## 4. DATABASE CONNECTION (CloudFlare D1)

### Create D1 Database

```bash
# 1. Create database
wrangler d1 create noteria

# 2. Backup existing database
mysqldump -u root noteria > noteria-backup.sql

# 3. Import schema
wrangler d1 execute noteria --file ./noteria-backup.sql

# 4. Verify
wrangler d1 query noteria "SELECT COUNT(*) FROM users;"
```

### Update PHP to Use CloudFlare D1

```php
<?php
// config-cloudflare.php

// CloudFlare D1 (SQLite - compatible with PHP PDO)
$db_config = [
    'host' => 'localhost',  // CloudFlare handles routing
    'path' => ':memory:',   // Or use file path
    'user' => '',           // Not needed for D1
    'pass' => '',
    'db' => 'noteria'
];

// Use SQLite instead of MySQL
$dsn = "sqlite:noteria.db";

$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
```

---

## 5. CLOUDFLARE KV (Caching Layer)

### Setup Key-Value Store

```javascript
// src/kv-cache.js

export class CacheManager {
    constructor(kv_namespace) {
        this.kv = kv_namespace;
    }

    async set(key, value, ttl = 3600) {
        await this.kv.put(key, JSON.stringify(value), {
            expirationTtl: ttl
        });
    }

    async get(key) {
        const value = await this.kv.get(key);
        return value ? JSON.parse(value) : null;
    }

    async delete(key) {
        await this.kv.delete(key);
    }
}

// Usage
const cache = new CacheManager(CACHE);
await cache.set(`news:${id}`, newsData, 86400);  // 24h TTL
const cached = await cache.get(`news:${id}`);
```

---

## 6. CLOUDFLARE WORKERS API (PHP Proxy)

### Worker Handler: src/index.js

```javascript
import Router from 'itty-router';
import { CacheManager } from './kv-cache';

const router = Router();
const cache = new CacheManager(CACHE);

// ==================== RESERVATIONS ====================

router.post('/api/reservations', async (request, env) => {
    const data = await request.json();
    
    try {
        // Call PHP backend via fetch
        const response = await fetch('https://api-backend.noteria.kosove.gov.al/api/reservations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Forwarded-For': request.headers.get('cf-connecting-ip')
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        // Cache result
        if (result.success) {
            await cache.set(`reservation:${result.id}`, result, 3600);
        }
        
        return new Response(JSON.stringify(result), {
            status: response.status,
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });
    } catch (error) {
        return new Response(JSON.stringify({ error: error.message }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        });
    }
});

router.get('/api/news', async (request, env) => {
    const cacheKey = 'news:all';
    
    // Check cache first
    const cached = await cache.get(cacheKey);
    if (cached) {
        return new Response(JSON.stringify(cached), {
            headers: {
                'Content-Type': 'application/json',
                'X-Cache': 'HIT',
                'Cache-Control': 'public, max-age=86400'
            }
        });
    }
    
    // Fetch from backend
    const response = await fetch('https://api-backend.noteria.kosove.gov.al/api/news');
    const news = await response.json();
    
    // Cache for 24 hours
    await cache.set(cacheKey, news, 86400);
    
    return new Response(JSON.stringify(news), {
        headers: {
            'Content-Type': 'application/json',
            'X-Cache': 'MISS',
            'Cache-Control': 'public, max-age=86400'
        }
    });
});

router.get('/health', () => {
    return new Response(JSON.stringify({ status: 'ok' }), {
        headers: { 'Content-Type': 'application/json' }
    });
});

// 404 handler
router.all('*', () => {
    return new Response('Not Found', { status: 404 });
});

export default router;
```

---

## 7. CLOUDFLARE WAF (Web Application Firewall)

### Setup Security Rules

```
Dashboard → Security → WAF Rules

Enable presets:
✓ Cloudflare Managed Ruleset
✓ OWASP ModSecurity
✓ Cloudflare Sensitive Data Protection

Custom Rules:
- Block requests from non-Kosovo IPs (optional)
- Rate limit: 100 requests per 60 seconds per IP
- Block known threats
- Protect against SQLi, XSS, DDoS
```

---

## 8. CLOUDFLARE ANALYTICS & MONITORING

### Real User Monitoring (RUM)

```javascript
// Inject into base HTML template
<script>
  window.cloudflareRUM = window.cloudflareRUM || {};
  window.cloudflareRUM.q = [];
  
  (function() {
    const a = window.cloudflareRUM;
    a.metrics = {
      timeTillFirstByte: performance.timing.responseStart - performance.timing.navigationStart,
      firstPaint: performance.getEntriesByName('first-paint')[0]?.startTime,
      firstContentfulPaint: performance.getEntriesByName('first-contentful-paint')[0]?.startTime
    };
  })();
</script>
```

---

## 9. DEPLOYMENT ARCHITECTURE

```
                    ┌──────────────────────┐
                    │   noteria.kosove.   │
                    │  gov.al (CloudFlare) │
                    └──────────┬───────────┘
                               │
                    ┌──────────▼───────────┐
                    │  CloudFlare Edge     │
                    │  (200+ locations)    │
                    │  - DDoS Protection   │
                    │  - WAF               │
                    │  - Cache             │
                    └──────────┬───────────┘
                               │
                ┌──────────────┼──────────────┐
                │              │              │
         ┌──────▼────┐  ┌──────▼────┐  ┌────▼───────┐
         │  Workers  │  │   Pages   │  │ KV Cache   │
         │ (PHP API) │  │ (Static)  │  │ (Sessions) │
         └──────┬────┘  └─────┬─────┘  └────┬───────┘
                │             │             │
                └─────────────┼─────────────┘
                              │
                     ┌────────▼────────┐
                     │ Backend Server  │
                     │ (PHP + MySQL)   │
                     │ (AWS/Linode)    │
                     └─────────────────┘
```

---

## 10. MIGRATION STEPS

### Phase 1: Prepare (Week 1)

```bash
# 1. Backup database
mysqldump -u root noteria > noteria-backup.sql

# 2. Create GitHub repo
git init
git add .
git commit -m "Initial E-Noteria deployment"

# 3. Setup CloudFlare account
# Create account
# Add domain
# Update nameservers
```

### Phase 2: Deploy Workers (Week 1)

```bash
# 1. Install Wrangler
npm install -g wrangler

# 2. Create worker
wrangler init noteria-api

# 3. Configure wrangler.toml
# (Copy config from above)

# 4. Deploy
wrangler publish

# 5. Test
curl https://api.noteria.kosove.gov.al/health
```

### Phase 3: Deploy Database (Week 1-2)

```bash
# Option A: Use CloudFlare D1
wrangler d1 create noteria
wrangler d1 execute noteria --file ./noteria-backup.sql

# Option B: Keep MySQL on backend
# Update connection strings
# Point to backend server IP
```

### Phase 4: Deploy Pages (Week 2)

```bash
# 1. Connect GitHub repo to Pages
# Dashboard → Pages → Connect to Git

# 2. Configure build
# Build command: npm run build
# Output directory: ./public

# 3. Auto-deploy
# Every push triggers deployment
```

### Phase 5: Setup SSL & Security (Week 2)

```bash
# 1. SSL/TLS settings
# Full (strict) mode

# 2. Enable HTTPS redirects
# Always Use HTTPS

# 3. WAF rules
# Manage Ruleset
# Create custom rules

# 4. Rate limiting
# 100 requests / 60 seconds per IP
```

---

## 11. COST ANALYSIS

```
CloudFlare Pricing (Monthly):

Base Plan:         $0 (Free tier)
Pro Plan:          $20
Business Plan:     $200
Enterprise:        Contact sales

For E-Noteria:
- Pro Plan: $20/month
  ✓ Unlimited bandwidth
  ✓ 1M workers/day
  ✓ DDoS protection
  ✓ WAF (limited)
  
OR

- Business Plan: $200/month
  ✓ Advanced WAF
  ✓ Rate limiting
  ✓ Page rules (unlimited)
  ✓ Priority support

+ Backend Server (AWS/Linode): $300-500/month

TOTAL: €320-720/month

vs.

Laragon (now):     €0
Single Cloud VM:   €300-500
CloudFlare+VM:     €320-720
Full Kubernetes:   €15,000-30,000+
```

---

## 12. PERFORMANCE EXPECTED

```
Before (Laragon):
- First Load:       2-3 seconds
- Cache Hit:        N/A
- Global latency:   500ms+

After (CloudFlare):
- First Load:       <500ms (global average)
- Cache Hit:        <50ms
- DDoS Protected:   ✓ Yes
- Uptime SLA:       99.95%

Improvement:
- Page Speed:       4-6x faster
- Global reach:     200+ locations
- Security:         Enterprise-grade
```

---

## 13. MONITORING

```bash
# CloudFlare Analytics
Dashboard → Analytics & Logs

Metrics:
- Requests
- Response time
- Cache hit ratio
- Errors
- Bot traffic
- DDoS attacks
- SSL/TLS handshakes

Alerts:
- High error rate (>5%)
- DDoS attack
- SSL certificate expiring
- Cache hit rate <80%
```

---

## 14. FINAL CHECKLIST

```
□ CloudFlare account created
□ Domain added and nameservers updated
□ DNS records configured
□ SSL/TLS set to "Full (strict)"
□ Workers deployed and tested
□ Pages connected to GitHub
□ D1 database created and populated
□ KV cache configured
□ WAF rules enabled
□ Rate limiting active
□ SSL certificate valid
□ Monitoring enabled
□ Backup automated
□ Custom domain pointing to CloudFlare

Status: READY FOR PRODUCTION
```

---

**E-Noteria CloudFlare Setup = €320-720/month**
**Capacity = 50,000-500,000 users/day**
**Performance = 4-6x faster globally**
**Security = Enterprise-grade protection**

Dëshironi të fillojmë?
