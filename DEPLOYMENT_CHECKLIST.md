# E-Noteria Full Deployment Checklist

## **📋 COMPLETE DEPLOYMENT - CHECK EVERYTHING OFF**

This is your master checklist for launching E-Noteria on CloudFlare + AWS.

---

## **PHASE 1: PRE-DEPLOYMENT (Day 1)**

### Prerequisites
```
□ Node.js 18+ installed locally
□ npm installed
□ Git installed
□ AWS account created (https://aws.amazon.com/)
□ CloudFlare account created (https://dash.cloudflare.com/)
□ Domain registered (noteria.kosove.gov.al)
□ Domain admin access (registrar login)
□ 2-3 hours available for setup
```

### Read Documentation
```
□ INDEX_CLOUDFLARE_DEPLOYMENT.md
□ CLOUDFLARE_GETTING_STARTED.md
□ CLOUDFLARE_EXECUTIVE_SUMMARY.md
□ AWS_SETUP_GUIDE.md (at least sections 1-3)
□ Understand architecture & timeline
```

### Prepare Accounts
```
□ AWS account created
□ Created AWS IAM user (noteria-admin)
□ Downloaded AWS access keys (save securely!)
□ CloudFlare account created
□ CloudFlare Pro plan selected
□ GitHub account created (for Pages deployment)
```

---

## **PHASE 2: AWS BACKEND SETUP (Days 2-3)**

### AWS Infrastructure
```
□ Created EC2 security group (noteria-public)
   └─ Port 80 (HTTP)
   └─ Port 443 (HTTPS)
   └─ Port 22 (SSH from your IP)
   └─ Port 3306 (MySQL)

□ Created EC2 key pair (noteria-key.pem)
   └─ Downloaded & saved securely
   └─ Set permissions: chmod 400

□ Launched EC2 instance
   └─ Instance type: t3.xlarge
   └─ AMI: Ubuntu 24.04 LTS
   └─ Storage: 100GB
   └─ Security group: noteria-public
   └─ Public IP: Enabled
   └─ Note: Public IP address
```

### Server Software Installation
```
□ Connected to server via SSH
□ Updated system: apt update && apt upgrade
□ Installed PHP 8.2-fpm
   └─ Verified: php -v
□ Installed MySQL Server
   └─ Ran: mysql_secure_installation
   └─ Secured: Removed test DB, anonymous users
□ Installed Nginx
   └─ Verified: http://[IP] returns Nginx page
□ Installed Git
   └─ Verified: git --version
```

### PHP & Nginx Configuration
```
□ Created Nginx virtualhost (/etc/nginx/sites-available/noteria)
   └─ Server name: api.noteria.kosove.gov.al
   └─ Root: /var/www/noteria/public
   └─ PHP-FPM: Socket connection
   └─ Security headers: X-Frame-Options, etc.
   └─ CloudFlare real IP config: set_real_ip_from

□ Enabled virtualhost
   └─ Created symlink to sites-enabled
   └─ Disabled default site
   └─ Tested: nginx -t (syntax OK)
   └─ Reloaded: systemctl reload nginx

□ Updated PHP configuration
   └─ max_execution_time: 60
   └─ memory_limit: 512M
   └─ upload_max_filesize: 100M
   └─ post_max_size: 100M

□ Verified PHP-FPM running
   └─ systemctl status php8.2-fpm (active)
```

### E-Noteria Code Deployment
```
□ Cloned repository to /var/www/noteria
   └─ git clone https://github.com/…/noteria.git
   └─ OR uploaded files via SCP

□ Set correct permissions
   └─ chown -R www-data:www-data /var/www/noteria
   └─ chmod -R 755 /var/www/noteria

□ Created required directories
   └─ /var/www/noteria/storage/logs
   └─ /var/www/noteria/storage/uploads
   └─ Permissions: chmod 777

□ Verified code is accessible
   └─ Files present in /var/www/noteria
   └─ No permission errors
```

### MySQL Database Setup
```
□ Created database
   └─ CREATE DATABASE noteria CHARACTER SET utf8mb4
   └─ COLLATE utf8mb4_unicode_ci

□ Created database user
   └─ Username: noteria_user
   └─ Password: [STRONG_PASSWORD]
   └─ Granted ALL PRIVILEGES on noteria.*

□ Imported database schema
   └─ mysqldump imported: noteria-backup.sql
   └─ Tables created: users, reservations, news, etc.
   └─ Verified: SELECT COUNT(*) FROM users

□ Setup automated backups
   └─ Created /etc/cron.daily/backup-noteria
   └─ Backup location: /backups/noteria
   └─ Retention: 30 days
```

### SSL/TLS Certificate
```
□ Created Origin CA certificate from CloudFlare
   └─ Hostnames: *.noteria.kosove.gov.al, api.noteria.kosove.gov.al
   └─ Validity: 15 years
   └─ Downloaded certificate & key

□ Uploaded to server
   └─ /etc/ssl/certs/noteria-origin.pem
   └─ /etc/ssl/private/noteria-origin.key
   └─ Permissions: 600 for private key

□ Updated Nginx for HTTPS
   └─ Added SSL server block (port 443)
   └─ ssl_certificate paths correct
   └─ TLS 1.2+ configured
   └─ HTTP to HTTPS redirect enabled
   □ Tested: openssl s_client -connect api.noteria.kosove.gov.al:443

□ Verified SSL
   └─ Certificate valid & not self-signed
   └─ Chain complete
```

### Firewall & Security
```
□ Enabled UFW firewall
   └─ sudo ufw enable

□ Allowed required ports
   └─ 22 (SSH from your IP only)
   └─ 80 (HTTP)
   └─ 443 (HTTPS)

□ Security updates scheduled
   └─ Unattended upgrades enabled
   └─ Auto-reboot on kernel updates: disabled

□ Fail2ban installed (optional)
   └─ Protects against brute force
   └─ Monitors SSH, Nginx
```

### Server Monitoring
```
□ CloudWatch agent installed
   └─ Monitoring EC2 metrics

□ Log rotation configured
   └─ /var/log/nginx, PHP-FPM logs rotate daily
   └─ Retention: 30 days

□ Health check endpoint created
   └─ /var/www/noteria/health.php
   └─ Returns: {"status":"ok"}
   └─ Checks: PHP, MySQL, uptime
```

### AWS Monitoring & Alerts
```
□ CloudWatch alarms created
   └─ CPU > 80%: Send email alert
   └─ Disk space < 10%: Send alert
   └─ Network errors increase: Alert
   □ Email confirmed for notifications

□ Billing alerts setup
   └─ Alert if costs exceed €600/month
```

---

## **PHASE 3: CLOUDFLARE SETUP (Days 3-4)**

### Domain Configuration
```
□ Added domain to CloudFlare
   └─ Domain: noteria.kosove.gov.al
   └─ Plan selected: Pro (€20/month)
   └─ Nameservers provided by CloudFlare

□ Updated nameservers at registrar
   └─ OLD NS servers removed
   └─ NEW NS servers added:
      └─ cecilia.ns.cloudflare.com
      └─ neil.ns.cloudflare.com
   └─ Changes propagated (check: nslookup)

□ Verified DNS propagation
   └─ nslookup noteria.kosove.gov.al @1.1.1.1
   └─ Returns CloudFlare IPs
   └─ Wait time: 5-30 minutes typically
```

### CloudFlare DNS Records
```
□ Created A record (main domain)
   □ Type: A
   □ Name: noteria.kosove.gov.al
   □ Content: [Your AWS public IP]
   □ TTL: Auto
   □ Proxy: Proxied (🟠)

□ Created CNAME for www
   □ Type: CNAME
   □ Name: www
   □ Content: noteria.kosove.gov.al
   □ TTL: Auto
   □ Proxy: Proxied (🟠)

□ Created API subdomain
   □ Type: CNAME
   □ Name: api
   □ Content: noteria.kosove.gov.al
   □ TTL: Auto
   □ Proxy: Proxied (🟠)

□ Verified DNS resolution
   □ nslookup www.noteria.kosove.gov.al
   □ nslookup api.noteria.kosove.gov.al
   □ All resolve to CloudFlare IPs
```

### SSL/TLS Configuration
```
□ Set SSL/TLS mode
   □ Mode: Full (strict)
   □ This requires valid SSL on your origin

□ Enabled HTTPS redirects
   □ Always Use HTTPS: ON
   □ Automatic HTTPS Rewrites: ON

□ Configured TLS settings
   □ Minimum TLS Version: 1.2
   □ HSTS: Enabled (max-age: 31536000)
   □ HSTS Preload: Enabled

□ Verified SSL configuration
   □ Test: curl -I https://noteria.kosove.gov.al
   □ Should show: 200 OK, TLS 1.3 or 1.2
```

### Workers Deployment
```
□ Logged into CloudFlare via Wrangler
   □ Command: wrangler login
   □ Browser authenticated

□ Updated wrangler.toml
   □ account_id: [Your account ID]
   □ zone_id: [Your zone ID]
   □ BACKEND_URL: https://203.0.113.42
   □ All environment variables filled

□ Installed npm dependencies
   □ npm install (itty-router, etc.)
   □ node_modules created

□ Created/updated src/index.js
   □ API gateway implementation
   □ Caching logic
   □ Rate limiting
   □ Error handling

□ Tested Workers locally
   □ npm run dev
   □ curl http://localhost:8787/health
   □ Returned: {"status":"ok"}

□ Deployed to CloudFlare
   □ npm run deploy:prod
   □ Wrangler shows: ✓ Published
   □ URL: https://api.noteria.kosove.gov.al/*

□ Verified Worker endpoints
   □ curl https://api.noteria.kosove.gov.al/health
   □ curl https://api.noteria.kosove.gov.al/api/news
   □ All working (200 responses)
```

### KV Cache Setup
```
□ Created KV namespaces
   □ CACHE namespace created
   □ SESSIONS namespace created
   □ Copied namespace IDs

□ Updated wrangler.toml with KV bindings
   □ [[kv_namespaces]] entries added
   □ binding names: CACHE, SESSIONS
   □ preview_id values set

□ Configured cache TTLs
   □ Public data: 86400 (24 hours)
   □ User-specific: 600 (10 minutes)
   □ Sessions: 3600 (1 hour)

□ Tested caching
   □ First request: X-Cache: MISS
   □ Second request: X-Cache: HIT
   □ Confirmed caching working
```

### Pages Setup (Optional - for static frontend)
```
□ Pushed code to GitHub
   □ Repository created: YOUR_USERNAME/noteria
   □ Code pushed to main branch
   □ .gitignore configured

□ Connected GitHub to CloudFlare Pages
   □ CloudFlare Dashboard → Pages
   □ Create project → Connect to Git
   □ Selected repository
   □ Authorized GitHub access

□ Configured build settings
   □ Build command: npm run build
   □ Output directory: ./public
   □ Environment: Node.js 18

□ Deployed Pages
   □ CloudFlare auto-deployed
   □ Site available at: noteria.pages.dev
   □ Custom domain: noteria.kosove.gov.al

□ Verified Pages deployment
   □ Visit: https://noteria.kosove.gov.al
   □ Site loading correctly
```

### WAF Configuration
```
□ Enabled Managed Rulesets
   □ Cloudflare Managed Ruleset: ON
   □ OWASP ModSecurity Core Rule Set: ON
   □ Sensitive Data Protection: ON
   □ Bots: ON

□ Created custom WAF rules
   □ Rate limiting: 100 requests/60 seconds
   □ IP reputation: Block high-risk
   □ Geo-blocking: (Optional) Enable

□ Tested WAF protection
   □ SQLi test blocked (403 Forbidden)
   □ XSS test blocked (403 Forbidden)
   □ Normal traffic: Working (200 OK)
```

### Analytics & Monitoring
```
□ Enabled CloudFlare Analytics
   □ Dashboard → Analytics & Logs
   □ Metrics visible:
      □ Request count
      □ Response time
      □ Cache hit ratio
      □ Error rates
      □ Bot traffic

□ Setup Real User Monitoring
   □ JavaScript snippet added to HTML
   □ Collecting performance metrics
   □ Data visible in dashboard

□ Configured alerts
   □ Alert if error rate > 5%
   □ Alert if DDoS attack detected
   □ Alert if SSL cert expiring
   □ Email alerts working
```

---

## **PHASE 4: TESTING & VERIFICATION (Days 5-6)**

### DNS Verification
```
□ DNS resolution working
   □ nslookup noteria.kosove.gov.al
   □ nslookup api.noteria.kosove.gov.al
   □ nslookup www.noteria.kosove.gov.al
   □ All resolve to CloudFlare IPs

□ DNS propagation complete
   □ Global DNS checkers show CloudFlare
   □ No old nameserver records
   □ TTL propagated globally
```

### SSL/TLS Verification
```
□ Certificate validity
   □ openssl s_client -connect api.noteria.kosove.gov.al:443
   □ Shows: "Verify return code: 0 (ok)"
   □ Subject CN matches domain
   □ Not self-signed

□ SSL rating
   □ https://www.ssllabs.com/ssltest/
   □ Entered: api.noteria.kosove.gov.al
   □ Result: A or A+ rating
   □ TLS 1.2/1.3 supported
   □ No weak ciphers
```

### Performance Testing
```
□ Page load time
   □ curl -w "Time: %{time_total}s\n" https://api.noteria.kosove.gov.al/health
   □ From Kosovo: < 500ms
   □ From Europe: < 800ms
   □ From USA: < 1000ms

□ Cache effectiveness
   □ First request: X-Cache: MISS (slow)
   □ Second request: X-Cache: HIT (fast)
   □ Cache hit ratio in dashboard: > 85%

□ Database response time
   □ SELECT query: < 50ms
   □ INSERT query: < 100ms
   □ Complex query: < 500ms

□ Load test
   □ Basic load test: 100 concurrent users
   □ Extended load test: 1,000 concurrent users
   □ Server stable, no 5xx errors
   □ Response time consistent

□ Database connections
   □ Connection pooling working
   □ Max connections: 100
   □ Current active: 10-20
   □ No "too many connections" errors
```

### Security Testing
```
□ SQL Injection test
   □ Input: SELECT * FROM users WHERE id=1 OR 1=1
   □ Result: WAF blocks (403)
   □ No data exposure

□ XSS attack test
   □ Input: <script>alert('xss')</script>
   □ Result: WAF sanitizes or blocks
   □ No script execution

□ CSRF test
   □ Cross-site request: Blocked or verified
   □ CSRF tokens working
   □ Form submitted from another domain: Blocked

□ Rate limiting test
   □ 100 requests per minute: OK
   □ 101+ requests per minute: 429 or CAPTCHA
   □ Rate limiting enforced

□ DDoS simulation (be careful!)
   □ Fake traffic spike: Handled
   □ CloudFlare mitigates
   □ Site remains accessible

□ Port scanning
   □ Common ports scan: Only 80, 443 open
   □ SSH (22): Not exposed to internet (CloudFlare only)
   □ MySQL (3306): Not exposed to internet

□ SSL certificate chain
   □ Root cert: CloudFlare Inc. (RSA Root CA)
   □ Intermediate: CloudFlare Origin CA
   □ Leaf cert: noteria.kosove.gov.al
   □ Chain valid & complete
```

### API Functionality Testing
```
□ Health endpoint
   □ GET /health → 200 OK
   □ Returns: {"status":"ok"}
   □ Response time: < 50ms

□ News endpoints
   □ GET /api/news → 200 OK
   □ X-Cache: HIT/MISS visible
   □ Returns JSON array
   □ Pagination working

□ Reservation endpoints
   □ GET /api/reservations/:id → 200 OK
   □ POST /api/reservations → 201 Created
   □ PUT /api/reservations/:id → 200 OK
   □ DELETE /api/reservations/:id → 204 No Content

□ User endpoints
   □ GET /api/users/:id → 200 OK
   □ POST /api/users → 201 Created
   □ Authentication: Working
   □ Authorization: Working

□ Error handling
   □ Invalid ID: 404 Not Found
   □ Missing fields: 400 Bad Request
   □ Server error: 500 Internal Server Error
   □ Error messages: Clear & helpful
```

### CloudFlare Analytics
```
□ Traffic metrics visible
   □ Requests/second: Normal range
   □ Bandwidth: Reasonable
   □ Unique visitors: Expected range

□ Cache metrics
   □ Cache hit ratio: > 85%
   □ Cache bypass reasons: Normal
   □ Static content cached

□ Error metrics
   □ 4xx errors: < 5%
   □ 5xx errors: < 1%
   □ Connection issues: Minimal

□ Security metrics
   □ Threats blocked: > 100/day
   □ Attack types blocked: SQLi, XSS, etc.
   □ Geo-blocked requests: (if configured)

□ Performance
   □ Avg response time: < 200ms
   □ P95 response time: < 500ms
   □ P99 response time: < 1000ms
```

### User Testing
```
□ Desktop browser testing
   □ Chrome: Working ✓
   □ Firefox: Working ✓
   □ Safari: Working ✓
   □ Edge: Working ✓

□ Mobile browser testing
   □ iPhone Safari: Working ✓
   □ Android Chrome: Working ✓
   □ Responsive design: Verified ✓

□ API client testing
   □ Postman: Can make requests ✓
   □ cURL: Working ✓
   □ Node.js fetch: Working ✓
   □ Python requests: Working ✓

□ Real user feedback
   □ Asked team to test
   □ Collected feedback
   □ No critical issues found
   □ Performance praised
```

---

## **PHASE 5: GO LIVE (Day 7-8)**

### Pre-Launch Checklist
```
□ All items in phases 1-4 complete
□ No critical errors in logs
□ Performance benchmarks met
□ Security tests passed
□ Backups working
□ Monitoring alerts configured
□ Emergency contact list prepared
□ Team trained on operations
```

### Launch Authorization
```
□ Technical sign-off: _______________
□ Management sign-off: _______________
□ Date approved: _______________
□ Launch time: _______________
□ Expected duration: 0 minutes (zero-downtime)
```

### Go Live Steps
```
□ Announce on social media
   □ Post: "E-Noteria is now live on CloudFlare!"
   □ Announcement: New features & improvements

□ Send email notification
   □ To all users
   □ Subject: "E-Noteria is faster than ever!"
   □ Include: Performance improvements, features

□ Monitor during launch
   □ Watch CloudFlare dashboard
   □ Monitor error rates
   □ Check response times
   □ Watch for user complaints

□ First hour checklist
   □ No unexpected errors
   □ Performance as expected
   □ Users can access platform
   □ Payment processing working
   □ Email notifications sending

□ First 24 hours checklist
   □ Monitor continuously
   □ Respond to user questions
   □ Check analytics
   □ Verify all systems stable
   □ Collect user feedback
```

### Post-Launch (Week 1)

```
□ Daily monitoring
   □ ErrorRate < 1%
   □ Response time: Target met
   □ Cache hit ratio: > 85%
   □ Uptime: 100%

□ User feedback collection
   □ Survey: "How is the new site?"
   □ Support tickets: Any issues?
   □ Social media: What do users say?

□ Performance tuning
   □ Adjust cache TTLs if needed
   □ Optimize slow endpoints
   □ Add indexes if needed

□ Team training
   □ Dashboard access
   □ Alert response procedures
   □ Log monitoring
   □ Metrics interpretation
```

---

## **PHASE 6: HANDOFF & DOCUMENTATION (Day 8+)**

### Documentation
```
□ Operations manual created
   □ How to restart services
   □ How to check logs
   □ How to scale infrastructure
   □ How to respond to alerts

□ Troubleshooting guide updated
   □ Common issues documented
   □ Solutions provided
   □ Emergency contacts listed

□ Architecture diagram
   □ Shows: CloudFlare → Workers → AWS
   □ Shows: Database → Backups
   □ Shows: Monitoring → Alerts

□ Runbook created
   □ Step-by-step procedures
   □ Alert response procedures
   □ Incident management
```

### Team Training
```
□ Operations team trained
   □ CloudFlare dashboard
   □ Monitoring & alerts
   □ Log analysis
   □ Scaling procedures

□ Development team trained
   □ Worker deployment
   □ Database access
   □ Code deployment
   □ Debugging procedures

□ Management trained
   □ Cost monitoring
   □ Performance metrics
   □ Capacity planning
   □ ROI discussion
```

### Knowledge Transfer
```
□ Admin access transferred
   □ CloudFlare: Admin role
   □ AWS: IAM permissions
   □ GitHub: Repository access
   □ DNS: Registrar access

□ Documentation delivered
   □ All guides
   □ Configuration files
   □ Access credentials (encrypted)
   □ Contact information
```

---

## **SUCCESS CRITERIA (All must be ✅)**

### Performance
```
□ Page load time < 500ms (globally)
□ Cache hit ratio > 85%
□ Server response time < 200ms
□ Database queries < 100ms
```

### Reliability
```
□ Uptime: 99.95%
□ Error rate: < 1%
□ ZeroDDoS incidents
□ Zero unplanned downtime
```

### Security
```
□ SSL certificate: Valid
□ No security vulnerabilities
□ WAF: Active & blocking threats
□ Rate limiting: Working
```

### Cost
```
□ Monthly cost: €372-400
□ No unexpected charges
□ Billing alerts: Working
□ Cost tracking: Implemented
```

---

## **SIGN-OFF**

```
Technical Lead: _________________________ Date: _______
Operations Manager: ______________________ Date: _______
Project Manager: ________________________ Date: _______

Status: ✅ READY FOR LAUNCH
```

---

**This checklist represents ~100 hours of work across:**
- AWS infrastructure setup (20 hours)
- CloudFlare configuration (15 hours)
- Code deployment & testing (30 hours)
- Security & monitoring (15 hours)
- Documentation & training (10 hours)
- Buffer for troubleshooting (10 hours)

**Timeline: 7-10 working days (1-2 weeks wall-clock time)**

**Next Step: Start with PHASE 1 items!**
