# CloudFlare Deployment - STEP-BY-STEP GUIDE

## **PHASE 1: SETUP (Day 1-2)**

### Step 1.1: Create CloudFlare Account

```bash
# 1. Go to https://dash.cloudflare.com/sign-up
# 2. Enter email: your-email@example.com
# 3. Create password
# 4. Accept terms
# 5. Verify email
```

### Step 1.2: Add Domain to CloudFlare

```bash
# cloudflare.com Dashboard:
# 1. Click "Add a domain"
# 2. Enter: noteria.kosove.gov.al
# 3. Select plan: "Pro" (€20/month) or higher
# 4. CloudFlare will show nameservers:
#    - cecilia.ns.cloudflare.com
#    - neil.ns.cloudflare.com
```

### Step 1.3: Update Nameservers at Domain Registrar

```bash
# Login to your domain registrar (GoDaddy, Namecheap, etc.)
# Find: DNS Settings / Nameservers
# Replace old nameservers with CloudFlare ones:
#   Old NS1: xxx.xxx.xxx.xxx
#   Old NS2: xxx.xxx.xxx.xxx
# 
# New NS1: cecilia.ns.cloudflare.com
# New NS2: neil.ns.cloudflare.com

# Verify propagation (takes 5-10 minutes):
nslookup noteria.kosove.gov.al @1.1.1.1
# Should return CloudFlare's IP addresses
```

### Step 1.4: Setup DNS Records in CloudFlare

```bash
# CloudFlare Dashboard → Websites → noteria.kosove.gov.al
# → DNS Records

ADD THESE RECORDS:

1. Main Domain (A Record)
   Type:     A
   Name:     noteria.kosove.gov.al
   Content:  YOUR_SERVER_IP (e.g., 203.0.113.42)
   TTL:      Auto
   Proxy:    Proxied (🟠) - Enable CloudFlare protection

2. WWW Subdomain (CNAME)
   Type:     CNAME
   Name:     www
   Content:  noteria.kosove.gov.al
   TTL:      Auto
   Proxy:    Proxied (🟠)

3. API Subdomain (CNAME or A)
   Type:     CNAME
   Name:     api
   Content:  noteria.kosove.gov.al
   TTL:      Auto
   Proxy:    Proxied (🟠)

4. Mail Exchange (MX) - Optional
   Type:     MX
   Name:     noteria.kosove.gov.al
   Content:  mail.noteria.kosove.gov.al
   Priority: 10
   TTL:      Auto

5. Domain Validation (TXT)
   Type:     TXT
   Name:     noteria.kosove.gov.al
   Content:  v=spf1 include:_spf.google.com ~all
```

**Verify DNS is working:**
```bash
nslookup noteria.kosove.gov.al
# Should resolve to your server IP

nslookup www.noteria.kosove.gov.al
# Should resolve to your server IP
```

---

## **PHASE 2: SSL/TLS SETUP (Day 2)**

### Step 2.1: Configure SSL/TLS in CloudFlare

```bash
# CloudFlare Dashboard → Rules → SSL/TLS

1. Overview:
   ✓ Encryption mode = "Full (strict)"
   ✓ This requires valid SSL on your backend

2. Edge Certificates:
   ✓ Always Use HTTPS = ON
   ✓ Automatic HTTPS Rewrites = ON
   ✓ HSTS = ON (Enable)
   ✓ Minimum TLS Version = 1.2
   ✓ Ciphers = TLS 1.3 preferred

3. Origin Server (Your backend server):
   # You need valid SSL certificate on your backend
   # Options:
   # A) Use Let's Encrypt (free, auto-renew):
   #    - Install Certbot
   #    - Run: certbot certonly -d api.noteria.kosove.gov.al
   #
   # B) Use CloudFlare Origin CA (free):
   #    - CloudFlare → SSL/TLS → Origin Server
   #    - Create Origin CA Certificate
   #    - Install on backend server
```

### Step 2.2: Get Your Origin CA Certificate (Recommended)

```bash
# CloudFlare Dashboard → SSL/TLS → Origin Server
# Click "Create Certificate"

# Step 1: Select hostnames
# ✓ noteria.kosove.gov.al
# ✓ *.noteria.kosove.gov.al
# ✓ api.noteria.kosove.gov.al

# Step 2: Select validity period
# 15 years (maximum)

# Step 3: CloudFlare generates:
# - Private Key (.key file)
# - Certificate (.pem file)

# Download and secure these files:
# - Copy both to your backend server
# - Path: /etc/ssl/certs/noteria-origin.pem
# - Path: /etc/ssl/private/noteria-origin.key

# On your backend server (Ubuntu/Debian):
sudo scp local-certificate.pem user@api.noteria.kosove.gov.al:/tmp/
sudo scp local-key.key user@api.noteria.kosove.gov.al:/tmp/

ssh user@api.noteria.kosove.gov.al
sudo mv /tmp/noteria-origin.pem /etc/ssl/certs/
sudo mv /tmp/noteria-origin.key /etc/ssl/private/
sudo chmod 600 /etc/ssl/private/noteria-origin.key
sudo chown root:root /etc/ssl/certs/noteria-origin.pem
```

---

## **PHASE 3: WORKERS SETUP (Day 3)**

### Step 3.1: Install Wrangler CLI

```bash
# Install Node.js 18+ first
# Download from: https://nodejs.org/

# Then install Wrangler
npm install -g wrangler

# Verify installation
wrangler --version
# Output: wrangler 3.45.0

# Login to CloudFlare
wrangler login

# Browser opens, login with your CloudFlare account
# Grant permission to Wrangler
# Browser shows: "Logged in successfully"
```

### Step 3.2: Configure Wrangler Project

```bash
# In your project directory:
cd d:\Laragon\www\noteria

# Initialize wrangler project (if not done)
wrangler init

# Update wrangler.toml with these values:

# Get your Account ID and Zone ID:
# CloudFlare Dashboard → Account Home
# Copy: Account ID (bottom right)

# CloudFlare Dashboard → noteria.kosove.gov.al
# Copy: Zone ID (right sidebar)

# Update wrangler.toml:
name = "noteria-api"
account_id = "YOUR_ACCOUNT_ID_HERE"        # e.g., abc123def456
type = "javascript"
workers_dev = true
route = "api.noteria.kosove.gov.al/*"
zone_id = "YOUR_ZONE_ID_HERE"             # e.g., xyz789uvw456

[env.production]
route = "https://api.noteria.kosove.gov.al/*"
zone_id = "YOUR_ZONE_ID_HERE"

[build]
command = "npm run build"
main = "src/index.js"

[env.production.vars]
BACKEND_URL = "https://api.noteria.kosove.gov.al"
LOG_LEVEL = "warn"

[env.development.vars]
BACKEND_URL = "http://localhost:8000"
LOG_LEVEL = "debug"
```

### Step 3.3: Install Dependencies

```bash
cd d:\Laragon\www\noteria

# Install npm packages
npm install

# Output should show:
# added X packages in Y seconds

# Verify
npm list
```

### Step 3.4: Configure Environment Variables

```bash
# Create .env file
# (Wrangler automatically reads environment variables)

# Add to wrangler.toml:
[env.production.vars]
BACKEND_URL = "https://api-backend.noteria.kosove.gov.al"
API_VERSION = "1.0.0"
LOG_LEVEL = "warn"
CACHE_TTL = "3600"
PUBLIC_URL = "https://noteria.kosove.gov.al"

# If using secrets (passwords, API keys):
# wrangler secret put SECRET_NAME
# Example:
# wrangler secret put DB_PASSWORD
# Enter password at prompt
```

### Step 3.5: Test Local Worker

```bash
# Start local development server
npm run dev

# Output:
# ⛅ wrangler dev is listening on localhost:8787

# Test in another terminal:
curl http://localhost:8787/health

# Should return:
# {"status":"ok","timestamp":"2024-03-02T...","version":"1.0.0"}

# Stop with: Ctrl+C
```

### Step 3.6: Deploy Worker to CloudFlare

```bash
# Deploy to production
npm run deploy:prod

# OR just dev environment
npm run deploy

# Output shows:
# ✓ Your worker is ready to publish
# ✓ Published noteria-api to:
#   https://api.noteria.kosove.gov.al/*

# Verify deployment:
curl https://api.noteria.kosove.gov.al/health

# Should return JSON response
```

---

## **PHASE 4: PAGES SETUP (Day 3-4)**

### Step 4.1: Setup GitHub Repository

```bash
# Initialize git in your project
cd d:\Laragon\www\noteria
git init

# Add GitHub remote
git remote add origin https://github.com/YOUR_USERNAME/noteria.git

# Create .gitignore
echo "node_modules/
.env
.wrangler/
dist/
.DS_Store
*.log" > .gitignore

# Commit and push
git add .
git commit -m "Initial E-Noteria deployment"
git branch -M main
git push -u origin main
```

### Step 4.2: Connect GitHub to CloudFlare Pages

```bash
# CloudFlare Dashboard → Pages
# Click "Create a project"
# Select "Connect to Git"

# 1. Authorize GitHub
#    - Click "Connect GitHub"
#    - Login to GitHub
#    - Grant CloudFlare access
#    - Select repository: YOUR_USERNAME/noteria

# 2. Build configuration
#    - Build command:  npm run build
#    - Build output directory: ./public
#    - Root directory: /
#    - Environment: Node.js 18

# 3. Environment variables
#    - BACKEND_URL = https://api.noteria.kosove.gov.al
#    - API_VERSION = 1.0.0

# 4. Click "Save and Deploy"
#    - CloudFlare clones your repo
#    - Runs build command
#    - Deploys to Pages CDN
```

### Step 4.3: Verify Pages Deployment

```bash
# After deployment completes:
curl https://noteria-pages.pages.dev

# Should return your frontendapplication

# OR (after custom domain setup):
curl https://noteria.kosove.gov.al
```

### Step 4.4: Custom Domain for Pages

```bash
# CloudFlare Dashboard → Pages → noteria
# → Settings → Domains
# Click "Add custom domain"
# Enter: noteria.kosove.gov.al

# CloudFlare automatically configures DNS
# (No additional setup needed if already using CloudFlare DNS)

# Verify:
curl https://noteria.kosove.gov.al
# Should return your Pages site
```

---

## **PHASE 5: KV CACHE SETUP (Day 4)**

### Step 5.1: Create KV Database

```bash
# Create KV namespace for general cache
wrangler kv:namespace create "CACHE"

# Create KV namespace for sessions
wrangler kv:namespace create "SESSIONS"

# CloudFlare outputs:
# ✓ Created KV namespace 'CACHE'
# ┌───────────────────────────────────┐
# │ id = abcd1234efgh5678ijkl9012mnop │
# └───────────────────────────────────┘
# Add this to your wrangler.toml

# Update wrangler.toml with KV bindings:
[[kv_namespaces]]
binding = "CACHE"
id = "YOUR_CACHE_ID"
preview_id = "YOUR_CACHE_PREVIEW_ID"

[[kv_namespaces]]
binding = "SESSIONS"
id = "YOUR_SESSIONS_ID"
preview_id = "YOUR_SESSIONS_PREVIEW_ID"
```

### Step 5.2: Update Worker Code for KV

```javascript
// Already included in src/index.js
// CacheManager class handles KV operations

// Example usage:
const cache = new CacheManager(env.CACHE);
await cache.set('news:all', newsData, 3600);
const cached = await cache.get('news:all');
await cache.delete('news:all');
```

### Step 5.3: Redeploy Worker with KV

```bash
npm run deploy:prod

# Verify KV is working:
curl https://api.noteria.kosove.gov.al/api/news

# First call: X-Cache: MISS (fetches from backend, stores in KV)
# Second call: X-Cache: HIT (serves from KV cache, 1000x faster)
```

---

## **PHASE 6: WAF & SECURITY (Day 5)**

### Step 6.1: Enable WAF Rules

```bash
# CloudFlare Dashboard → Security → WAF
# → Managed Rulesets

# Enable these presets:
☑ Cloudflare Managed Ruleset
☑ OWASP ModSecurity Core Rule Set
☑ Cloudflare Sensitive Data Protection
☑ Bots

# Each provides protection against:
# - SQLi (SQL Injection)
# - XSS (Cross-Site Scripting)
# - RCE (Remote Code Execution)
# - Bots & scrapers
# - DDoS attacks
```

### Step 6.2: Custom WAF Rules

```bash
# CloudFlare Dashboard → Security → WAF → Custom rules

# Rule 1: Rate Limiting (100 requests/60 seconds)
Field: CF-RAY
Operator: equals
Value: *
Action: Challenge (shows CAPTCHA)
Rate limiting: 100 requests per 60 seconds

# Rule 2: Block non-Kosovo IPs (optional)
Field: CF-IPCountry
Operator: is not equal
Value: AL, RS, ME, MK  # Kosovo neighbors
Action: Block
(Disable if you want international access)

# Rule 3: Protect sensitive paths
Field: URI Path
Operator: contains
Value: /admin, /api/users
Action: Require authentication
```

### Step 6.3: DDoS Protection

```bash
# CloudFlare Dashboard → Security → DDoS Protection
# (All plans include DDoS protection)

# Sensitivity settings:
# - Essentially Off (Balanced, recommended)
# - Medium
# - High
# - I'm Under Attack! (During active attack)

# Status: ✓ Enabled
```

---

## **PHASE 7: MONITORING & ANALYTICS (Day 5)**

### Step 7.1: Setup Real User Monitoring

```bash
# CloudFlare Dashboard → Speed → Web Analytics
# Copy JavaScript snippet:

<script>
  (function() {
    const a = window.cloudflareRUM = window.cloudflareRUM || {};
    a.metrics = a.metrics || {};
    
    a.metrics.timeTillFirstByte = performance.timing.responseStart - performance.timing.navigationStart;
    
    const fp = performance.getEntriesByName('first-paint')[0];
    a.metrics.firstPaint = fp && fp.startTime;
  })();
</script>

# Add to your HTML template (before </head>):
# dashboard.php, index.php, etc.
```

### Step 7.2: View Analytics Dashboard

```bash
# CloudFlare Dashboard → Analytics & Logs

Metrics shown:
- Requests (by type)
- Response time (histogram)
- Cache hit ratio
- Error rates (4xx, 5xx)
- Bot traffic
- DDoS attacks
- SSL/TLS handshakes
- Country breakdown
```

### Step 7.3: Setup Alerts

```bash
# CloudFlare Dashboard → Notifications → Create notification

# Example alerts:
1. High error rate (>5% 5xx errors)
   Trigger: Error rate > 5%
   Notify: email@example.com

2. DDoS attack detected
   Trigger: DDoS attack
   Notify: email@example.com

3. SSL certificate expiring
   Trigger: 30 days before expiration
   Notify: email@example.com

4. Cache hit ratio low
   Trigger: < 80%
   Notify: email@example.com
```

---

## **PHASE 8: DATABASE MIGRATION (Day 6)**

### Step 8.1: Backup Your Database

```bash
# From your backend server (or Laragon):
mysqldump -u root -p noteria > noteria-backup.sql

# Verify backup:
ls -lh noteria-backup.sql
# Should show: noteria-backup.sql (5-50 MB typically)

# Backup to CloudFlare R2 (optional):
wrangler r2 upload noteria-backup.sql noteria-backups/
```

### Step 8.2: Database Strategy

```bash
# 3 Options:

# OPTION A: Keep MySQL on Backend (Recommended for now)
# Pros:
#   - No migration needed
#   - Full PHP compatibility
#   - Easy to manage locally
#
# Cons:
#   - Single point of failure
#   - Scaling needs more work
#
# Setup: No changes needed, keep using PHP + MySQL

# OPTION B: Use CloudFlare D1 (Future)
# Pros:
#   - Managed database
#   - Built-in replication
#   - Better pricing at scale
#
# Cons:
#   - Migration required
#   - Less features than MySQL
#   - Durable Objects learning curve
#
# Setup:
# wrangler d1 create noteria
# wrangler d1 execute noteria --file ./noteria-backup.sql

# OPTION C: Use AWS RDS (Scale to 1M users)
# Pros:
#   - Enterprise features
#   - Auto-scaling
#   - Multi-region
#
# Cons:
#   - More expensive
#   - Complex setup
#
# Setup: See KUBERNETES_DEPLOYMENT_GUIDE.md
```

**RECOMMENDATION: Start with OPTION A (keep MySQL on backend)**

### Step 8.3: Connect Worker to Database

```php
// In your PHP backend:
$db = mysqli_connect(
    'YOUR_SERVER_IP',
    'noteria_user',
    'database_password',
    'noteria'
);

// If behind CloudFlare:
// Make sure backend server allows CloudFlare IPs
// CloudFlare IPs: https://www.cloudflare.com/ips/
```

---

## **PHASE 9: TESTING (Day 6-7)**

### Step 9.1: Full Stack Test

```bash
# 1. Test DNS
nslookup noteria.kosove.gov.al
# Should resolve to CloudFlare IP

# 2. Test SSL
curl -I https://noteria.kosove.gov.al
# Should show TLS 1.3 or 1.2

# 3. Test Pages
curl https://noteria.kosove.gov.al
# Should return HTML

# 4. Test API Worker
curl https://api.noteria.kosove.gov.al/health
# Should return JSON: {"status":"ok"}

# 5. Test Cache
curl -i https://api.noteria.kosove.gov.al/api/news
# First call: X-Cache: MISS
# Second call: X-Cache: HIT

# 6. Test Rate Limiting
for i in {1..150}; do
  curl https://api.noteria.kosove.gov.al/api/news
done
# After 100 requests: Should get 429 Too Many Requests

# 7. Test from different location (VPN)
# Should still work (unless geo-blocked)
```

### Step 9.2: Performance Test

```bash
# Test page load time
curl -w "
Time taken: %{time_total}s
Time to connect: %{time_connect}s
Time to first byte: %{time_starttransfer}s
" -o /dev/null -s https://noteria.kosove.gov.al

# Before CloudFlare: ~2000ms
# After CloudFlare: ~400ms from Kosovo, ~600ms globally
```

### Step 9.3: Security Test

```bash
# SQL Injection test (should be blocked by WAF)
curl "https://api.noteria.kosove.gov.al/api/news?id=1%20OR%201=1"
# Blocked: 403 Forbidden (WAF blocked it)

# XSS test
curl "https://api.noteria.kosove.gov.al/api/news?search=<script>alert('xss')</script>"
# Blocked: 403 Forbidden (WAF blocked it)

# DDoS test (should show challenge)
ab -n 1000 -c 100 https://api.noteria.kosove.gov.al/health
# Shows CAPTCHA challenge after limit
```

---

## **PHASE 10: LAUNCH (Day 7-8)**

### Step 10.1: Pre-Launch Checklist

```
□ Domain registered and pointing to CloudFlare
□ DNS records configured (A, CNAME)
□ SSL/TLS set to "Full (strict)"
□ HTTPS Redirect enabled
□ Worker deployed and tested
□ Pages deployed and tested
□ KV cache working
□ WAF rules enabled
□ Database accessible from CloudFlare
□ Analytics enabled
□ Backups configured
□ Monitoring alerts setup
□ Performance acceptable (< 500ms)
□ Security tests passed
□ High availability tested

Ready to launch: YES ✓
```

### Step 10.2: Go Live

```bash
# Announce the launch:
# Email to users:
# "E-Noteria is now live at noteria.kosove.gov.al"
# "Faster, more secure, better uptime"

# Social media:
# "E-Noteria now powered by CloudFlare Global Network"
# "Available in 200+ locations worldwide"

# Monitor for first 24 hours:
# Check analytics
# Monitor error rates
# Watch performance metrics
```

### Step 10.3: Post-Launch Optimization

```bash
# Week 1: Monitor metrics
# - Response time
# - Cache hit ratio (should be > 80%)
# - Error rates
# - User feedback

# Week 2: Optimize based on data
# - Adjust cache TTLs
# - Refine WAF rules
# - Optimize images/files
# - Enable Mirage (image optimization)

# Week 3: Plan next phase
# - Scale to AWS + RDS?
# - Add database replicas?
# - Setup auto-backup?
```

---

## **COST ESTIMATE**

```
Monthly Costs:

CloudFlare Pro:         €20
CloudFlare Workers:     €0 (free tier includes 100,000 requests/day)
CloudFlare Pages:       €0 (unlimited bandwidth)
CloudFlare KV:          €0 (free tier includes 10GB)
Backend Server (AWS):   €300-500
Domain registration:    €10-20/year
SSL Certificate:        €0 (free CloudFlare Origin CA)

TOTAL: €320-520/month

Capacity: 50,000 - 500,000 users per day
Uptime SLA: 99.95%
DDoS Protection: Unlimited
Security: Enterprise-grade
```

---

## **TROUBLESHOOTING**

### Problem: DNS Not Resolving

```bash
# Check propagation
nslookup noteria.kosove.gov.al

# If still showing old IP:
# 1. Check CloudFlare dashboard shows correct IP
# 2. Wait 24-48 hours for DNS propagation
# 3. Check registrar nameservers are correct
```

### Problem: SSL Certificate Error

```bash
# Wait 24 hours for CloudFlare to issue certificate
# Or manually request in Dashboard → SSL/TLS

# Ensure backend has valid SSL:
openssl s_client -connect api.noteria.kosove.gov.al:443

# Should show: "Verify return code: 0 (ok)"
```

### Problem: Worker 500 Error

```bash
# Check worker logs:
wrangler tail

# Check backend connectivity:
curl https://api.noteria.kosove.gov.al/status

# Should show backend status
```

### Problem: Slow Performance

```bash
# Check cache hit ratio:
CloudFlare Dashboard → Analytics → Cache Analytics
# If < 80%, adjust cache TTLs

# Check origin response time:
# CloudFlare Dashboard → Response time breakdown
# If origin slow, upgrade backend server
```

---

## **SUPPORT CONTACTS**

```
CloudFlare Support:       https://support.cloudflare.com/
CloudFlare Community:     https://community.cloudflare.com/
Wrangler Documentation:   https://developers.cloudflare.com/workers/
Your Registrar Support:   [your registrar contact]
Your Backend Host:        [your host contact]
```

---

**That's it! You're now live on CloudFlare with enterprise-grade security, global CDN, and 99.95% uptime!** 🚀

**Next Steps:** Monitor analytics, gather user feedback, plan scaling to AWS if traffic grows beyond 500K daily users.
