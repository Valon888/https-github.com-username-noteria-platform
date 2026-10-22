# TECHNICAL_HANDOVER.md - Dokumentacioni Teknik Komplet

## 📋 Noteria Platform - Technical Handover Guide

**Data**: 3 Mars 2026  
**Versioni**: 2.0 Production-Ready  
**Target Audience**: Technical Team, Developers, DevOps Engineers

---

## 1. TECHNOLOGY STACK

### 1.1 Backend

```
☑ PHP 8.0+
  - Framework: Vanilla PHP (Non-MVC, procedural)
  - Performance: Optimized for shared hosting
  - Security: Built-in input validation, SQL injection protection
  - Sessions: Server-side PHP session management
  - Rate limiting: Implemented on payment endpoints

☑ MySQL 8.0+
  - Database: Innodb engine
  - Charset: utf8mb4 (Albanian, Serbian, English support)
  - Stored Procedures: Yes (for complex operations)
  - Views: Yes (for reporting)
  - Indexes: Optimized for queries
  - Backup: Daily automated via cron

☑ APIs Integrated:
  - Paysera (Payment Processing)
  - Raiffesen Bank (Payments)
  - BKT Bank (Payments)
  - DocuSign (E-signatures)
  - Jitsi Meet (Video calling)
  - Tawk.to (Live chat)
```

### 1.2 Frontend

```
☑ HTML5 / CSS3 / JavaScript (ES6+)
  - Bootstrap 5.3.2 (CSS framework)
  - jQuery 3.6+ (DOM manipulation)
  - FullCalendar 6.1.11 (Calendar widget)
  - AJAX (Fetch API & XMLHttpRequest)
  - Responsive design (mobile-first)

☑ Libraries:
  - Font Awesome 6.4 (Icons)
  - Chart.js (Analytics/reporting charts)
  - DataTables (Advanced tables)
  - SweetAlert (User notifications)
  - Select2 (Enhanced dropdowns)
```

### 1.3 Infrastructure

```
☑ Server Requirements:
  - OS: Linux (Ubuntu 20.04+ recommended)
  - PHP Extensions:
    * PDO_MySQL
    * cURL
    * OpenSSL
    * GD (image processing)
    * JSON
    * Fileinfo
    * Curl
    * mbstring
    * zip

☑ Recommended Hosting:
  - CPU: 2+ cores
  - RAM: 4GB minimum
  - Storage: 50GB+ SSD
  - Bandwidth: 100Mbps+
  - Uptime SLA: 99.5%+

☑ Deployment:
  - Docker containers available
  - Kubernetes ready
  - Cloudflare integration
  - SSL/TLS (LetsEncrypt)
```

---

## 2. SYSTEM ARCHITECTURE

### 2.1 Directory Structure

```
noteria/
├── docs/                    # Documentation files
├── downloads/              # Downloaded files
├── email_logs/            # Email logger
├── faturat/               # Invoices storage
├── images/                # Static images
├── includes/              # Reusable components
├── js/                    # JavaScript files
├── lang/                  # Language files
├── logs/                  # Application logs
├── pdfs/                  # Generated PDFs
├── signatures/            # E-signature files
├── temp/                  # Temporary files
├── uploads/               # User uploads
├── vendor/                # Composer dependencies
│
├── admin_*.php           # Admin modules
├── api_*.php             # API endpoints
├── config.php            # Configuration
├── confidb.php           # Database connection
├── dashboard.php         # Main dashboard
├── login.php             # Authentication
├── process_payment.php   # Payment processing
├── reservation.php       # Booking system
├── status.php            # Status checker
├── video_call.php        # Video module
│
├── .env                  # Environment variables
├── .htaccess            # Apache rewrite rules
├── composer.json        # Dependencies
├── package.json         # NPM packages
└── README.md            # Documentation
```

### 2.2 Database Schema Highlights

**Tabela Kryesore:**
- `users` (2000+ records) - User management
- `reservations` (10000+ records) - Booking system
- `payments` (5000+ records) - Payment tracking
- `payment_logs` (detailed audit trail)
- `zyrat` (offices) - Office/branch management
- `news` - Announcements
- `messages` - User communications
- `reports` - Admin reporting

---

## 3. INSTALLATION & SETUP

### 3.1 Prerequisites

```bash
# Check PHP version
php -v  # Should be 8.0+

# Check MySQL
mysql --version  # Should be 8.0+

# Check extensions
php -m | grep -E "PDO|cURL|OpenSSL|mysql"
```

### 3.2 Installation Steps

```bash
# 1. Clone/Download code
cd /var/www/html
# Extract or git clone

# 2. Install dependencies
composer install

# 3. Create environment file
cp .env.example .env
# Edit .env with database credentials

# 4. Create database
mysql -u root -p
CREATE DATABASE noteria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# 5. Import schema
mysql -u root -p noteria < sql/noteria_schema.sql

# 6. Set permissions
chmod -R 755 uploads/
chmod -R 755 temp/
chmod -R 755 logs/
chmod 644 .env

# 7. Test
php -S localhost:8000
# Visit http://localhost:8000/index.php
```

### 3.3 Configuration Files (Important)

**config.php**
```php
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'noteria_user');
define('DB_PASS', 'strong_pass_123');
define('DB_NAME', 'noteria');

// Email
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);

// Payment APIs
define('PAYSERA_PROJECT_ID', 'your_id');
define('PAYSERA_SIGN_PASSWORD', 'your_pwd');
```

**.env File**
```
DB_HOST=localhost
DB_NAME=noteria
DB_USER=noteria_user
DB_PASS=your_secure_password
APP_URL=https://noteria.example.com
SESSION_TIMEOUT=1800
JWT_SECRET=your_secret_key
```

---

## 4. DATABASE CONNECTION

### 4.1 PDO Connection (confidb.php)

The system uses **PDO (PHP Data Objects)** for database:

```php
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed");
}
```

### 4.2 Security Features

- ✅ Prepared statements (prevent SQL injection)
- ✅ PDO parameterized queries
- ✅ Input validation
- ✅ CSRF token validation
- ✅ Rate limiting on sensitive endpoints
- ✅ SQL query logging

---

## 5. KEY MODULES & FUNCTIONS

### 5.1 Authentication Module

**Files:**
- `login.php` - User login
- `register.php` - User registration
- `forgot_password.php` - Password reset
- `session_helper.php` - Session management
- `mfa_helper.php` - Multi-factor authentication

**Key Functions:**
```php
// Verify password
password_verify($input, $hash)

// Hash password
password_hash($pass, PASSWORD_BCRYPT)

// Check session timeout
checkSessionTimeout($timeout, $redirect)

// Verify CSRF token
verifyCSRFToken($token)
```

### 5.2 Payment Processing (process_payment.php)

**Functions:**
```php
function processPayment($data)          // Main payment processor
function logPaymentAttempt($pdo, ...)   // Log payment attempts
function logPaymentRouting($pdo, ...)   // Log office routing
function setReservationPaymentStatus()  // Update reservation status
```

**Payment Gateway Endpoints:**
- Paysera: `/paysera_pay.php`
- Raiffesen: `/raiffeisen_callback.php`
- BKT: `/bkt_payment.php`

**Status Values:**
- `pending` - Awaiting payment
- `completed` - Successfully processed
- `failed` - Payment declined
- `refunded` - Money returned

### 5.3 Reservation System (reservation.php)

**Core Functions:**
```php
function bookReservation($user_id, $data)  // Create booking
function approveReservation($id)           // Admin approval
function rejectReservation($id)            // Decline booking
function getAvailableSlots($zyra_id, $date) // Check availability
```

### 5.4 Video Calling (video_call.php)

**Integration:**
- Jitsi Meet WebRTC
- Room identification: `noteria_${user_id}`
- Recording: Disabled by default
- Max participants: 10

### 5.5 E-Signature Integration (docusign_*.php)

**Endpoints:**
- `docusign_auth.php` - OAuth authentication
- `docusign_send_document.php` - Send for signing
- `docusign_callback.php` - Webhook handler
- `docusign_get_signed_document.php` - Retrieve signed doc

---

## 6. API ENDPOINTS

### 6.1 Public APIs

```
POST /api_check_status.php
  - Check reservation/payment status
  - Param: reference_number

GET /get_available_slots.php
  - Get available booking times
  - Params: zyra_id, date

GET /get_time_slots.php
  - Get hourly slots
  - Params: zyra_id, date

POST /check_slot.php
  - Verify slot availability
  - Params: zyra_id, date, time
```

### 6.2 Admin APIs

```
POST /admin_dashboard.php
  - Dashboard data
  - Requires: Admin session

GET /statistics.php
  - Revenue & usage stats
  - Requires: Admin access

POST /api_update_status.php
  - Update reservation status
  - Requires: Admin auth
```

### 6.3 Payment APIs

```
POST /process_payment.php
  - Process payment
  - Params: reservation_id, amount, method

POST /payment_confirmation.php
  - Confirm payment status
  - Callback from banks

POST /paysera_callback.php
  - Paysera webhook
  - Signature verification
```

---

## 7. SECURITY FEATURES

### 7.1 Authentication & Authorization

- ✅ Password hashing (bcrypt)
- ✅ Session management (server-side)
- ✅ Multi-factor authentication (MFA)
- ✅ CSRF token validation
- ✅ Role-based access control (RBAC)

### 7.2 Data Protection

- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection
- ✅ HTTPS/TLS enforcement
- ✅ Input sanitization
- ✅ Output encoding

### 7.3 Rate Limiting

**Payment endpoints:**
- Max 5 attempts per hour per user
- 10 minute cooldown after 3 failures

**API endpoints:**
- 100 requests per minute per IP
- 1000 requests per hour per user

### 7.4 Audit Logging

**Logged Events:**
- User login/logout
- Payment attempts
- Admin actions
- Data modifications
- API calls

**Log Location:** `/logs/` directory

---

## 8. MAINTENANCE & OPERATIONS

### 8.1 Database Maintenance

```bash
# Daily backup (automated via cron)
0 2 * * * /usr/bin/mysqldump -u noteria_user -p noteria > /backup/daily.sql

# Weekly optimization
0 3 * * 0 mysql -u root -p -e "OPTIMIZE TABLE noteria.*;"

# Clean old logs
0 4 * * * find /var/www/noteria/logs -mtime +30 -delete
```

### 8.2 Performance Optimization

- ✅ Database indexing (optimized queries)
- ✅ Caching layer (Redis optional)
- ✅ CDN integration (Cloudflare)
- ✅ Lazy loading (images)
- ✅ Minified assets (JS, CSS)

### 8.3 Monitoring & Alerts

**Recommended Tools:**
- New Relic (Performance APM)
- Sentry (Error tracking)
- Grafana (Metrics visualization)
- ELK Stack (Logging)

---

## 9. UPDATING & PATCHING

### 9.1 PHP Dependencies

```bash
# Update Composer dependencies
composer update

# Check for security updates
composer audit

# Lock versions
composer lock
```

### 9.2 Security Patches

- Regular review of dependencies
- Security advisories monitoring
- Immediate patching of vulnerabilities
- Testing before production deployment

---

## 10. SUPPORT & TROUBLESHOOTING

### 10.1 Common Issues

| Problema | Zgjidhje |
|----------|----------|
| White page | Check `error_log`, verify PHP version |
| Database connection error | Check credentials in `.env`, test connection |
| Payment fails | Verify API credentials, check rate limiting |
| Session timeout | Adjust `SESSION_TIMEOUT` in config |
| Video calling not working | Check Jitsi Meet service, firewall ports |

### 10.2 Log Checking

```bash
# PHP errors
tail -f /var/log/apache2/error.log

# Application logs
tail -f /var/www/noteria/logs/app.log

# Payment logs
grep "payment" /var/www/noteria/logs/*

# Database queries
grep "Query" /var/log/mysql/query.log
```

---

## 11. DEPLOYMENT GUIDE

### 11.1 Staging Deployment

```bash
# 1. Clone to staging
git clone [repo] /var/www/staging/noteria

# 2. Install dependencies
cd /var/www/staging/noteria
composer install --no-dev

# 3. Configure
cp .env.staging .env

# 4. Test
php -m | grep PDO

# 5. Run tests
php tests/unit_tests.php
```

### 11.2 Production Deployment

```bash
# 1. Backup current production
mysqldump noteria > /backup/prod_backup_$(date +%Y%m%d).sql

# 2. Deploy new version
git pull origin main
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php migrate.php

# 4. Clear caches
rm -rf /var/www/noteria/temp/*

# 5. Verify
curl https://noteria.example.com/health.php
```

---

## 12. DOCUMENTATION INVENTORY

**Included Documentation:**
- ✅ API Reference
- ✅ Database Schema Diagrams
- ✅ Security Audit Report
- ✅ Performance Benchmarks
- ✅ Architecture Overview
- ✅ Code Comments (inline)
- ✅ Deployment Guides
- ✅ Troubleshooting Guide

---

## 13. NEXT STEPS FOR BUYER

- [ ] Review system requirements
- [ ] Install locally/staging
- [ ] Test core modules
- [ ] Configure payment gateways
- [ ] Set up email notifications
- [ ] Configure video calling
- [ ] Deploy to production
- [ ] Train support team
- [ ] Launch user migration

---

**Technical Support Available**: 30 days post-purchase  
**Contact**: technical-support@noteria.dev  
**SLA**: 24-hour response time for critical issues

---

**Document Version**: 1.0  
**Last Updated**: March 3, 2026  
**Status**: COMPLETE
