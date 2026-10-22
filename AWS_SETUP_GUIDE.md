# AWS Setup Guide - E-Noteria Backend Infrastructure

## **🏗️ COMPLETE AWS SETUP (€350-400/month)**

This guide walks you through setting up a production-ready AWS backend for E-Noteria.

---

## **STEP 1: Create AWS Account (5 minutes)**

### 1.1: Create Account
```
1. Go to: https://aws.amazon.com/
2. Click: "Create an AWS Account"
3. Email: your-email@example.com
4. Password: Strong password (min 8 chars)
5. Account name: Noteria
6. Continue → Verify email → Done
```

### 1.2: Setup Billing Alerts
```
AWS Console → Billing & Cost Management → Billing Preferences
☑ Receive CloudWatch Alarms
☑ Alert when usage exceeds: €500
```

### 1.3: Create IAM User (for security)
```
AWS Console → IAM → Users → Create User
- Username: noteria-admin
- Attach policy: AdministratorAccess
- Create access key → Save CSV file (keep safe!)
```

---

## **STEP 2: Launch EC2 Instance (10 minutes)**

### 2.1: Create Security Group
```
AWS Console → EC2 → Security Groups → Create Security Group

Name:       noteria-public
Description: E-Noteria public access
VPC:        Default

Inbound Rules:
┌─────────┬──────┬─────────┬─────────────┬─────────────┐
│ Type    │ Port │ Protocol│ CIDR        │ Description │
├─────────┼──────┼─────────┼─────────────┼─────────────┤
│ HTTP    │ 80   │ TCP     │ 0.0.0.0/0   │ HTTP access │
│ HTTPS   │ 443  │ TCP     │ 0.0.0.0/0   │ HTTPS acces │
│ SSH     │ 22   │ TCP     │ Your IP/32  │ SSH only    │
│ MySQL   │ 3306 │ TCP     │ 0.0.0.0/0   │ DB access   │
└─────────┴──────┴─────────┴─────────────┴─────────────┘

Create → Done
```

### 2.2: Create Key Pair (for SSH)
```
AWS Console → EC2 → Key Pairs → Create Key Pair

Name:              noteria-key
File Format:       .pem (for Linux/Mac) or .ppk (for Windows)
Download:          Save securely (noteria-key.pem)
Permission:        chmod 400 noteria-key.pem
```

### 2.3: Launch EC2 Instance
```
AWS Console → EC2 → Instances → Launch Instance

1. Name:                        noteria-server
2. AMI:                         Ubuntu Server 24.04 LTS (Free tier)
3. Instance Type:               t3.xlarge
                                (16GB RAM, 4 vCPU)
                                Cost: €350-400/month
4. Key Pair:                    noteria-key
5. Security Group:              noteria-public
6. Storage:                     100GB (gp3 SSD)
7. VPC:                         Default
8. Public IP:                   Enable
9. Monitoring:                  Enable detailed monitoring
10. Launch → Done

⏳ Wait 2-3 minutes for instance to start
```

### 2.4: Get Your Public IP
```
AWS Console → EC2 → Instances
- Click your instance
- Copy: Public IPv4 address (e.g., 203.0.113.42)
- This is your BACKEND_URL for CloudFlare!
```

---

## **STEP 3: Connect to Server (5 minutes)**

### 3.1: SSH Connection
```bash
# On your local machine:
chmod 400 noteria-key.pem

# Connect to server:
ssh -i noteria-key.pem ubuntu@203.0.113.42

# Expected: ubuntu@ip-172-31-xx-xx:~$
```

### 3.2: Update System
```bash
sudo apt update
sudo apt upgrade -y
sudo reboot
```

### 3.3: Reconnect After Reboot
```bash
ssh -i noteria-key.pem ubuntu@203.0.113.42
```

---

## **STEP 4: Install Software Stack (15 minutes)**

### 4.1: Install PHP 8.2
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update

sudo apt install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip

# Verify installation:
php -v
# Output: PHP 8.2.x
```

### 4.2: Install MySQL Server
```bash
sudo apt install -y mysql-server

# Secure MySQL:
sudo mysql_secure_installation

# When prompted:
# Validate password plugin: n
# Remove anonymous users: y
# Disable remote root login: y
# Remove test database: y
# Reload privilege tables: y
```

### 4.3: Install Nginx
```bash
sudo apt install -y nginx

# Start Nginx:
sudo systemctl start nginx
sudo systemctl enable nginx

# Test Nginx:
# Visit: http://203.0.113.42
# Should show: Nginx welcome page
```

### 4.4: Install Git
```bash
sudo apt install -y git

# Verify:
git --version
```

---

## **STEP 5: Configure PHP-FPM & Nginx (10 minutes)**

### 5.1: Create Nginx Virtualhost
```bash
sudo nano /etc/nginx/sites-available/noteria

# Paste this:
server {
    listen 80;
    listen [::]:80;

    server_name api.noteria.kosove.gov.al;
    root /var/www/noteria/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # CloudFlare real IP
    set_real_ip_from 173.245.48.0/20;
    set_real_ip_from 103.21.244.0/22;
    set_real_ip_from 103.22.200.0/22;
    real_ip_header CF-Connecting-IP;
}

# Save: Ctrl+X → Y → Enter
```

### 5.2: Enable Virtualhost
```bash
sudo ln -s /etc/nginx/sites-available/noteria /etc/nginx/sites-enabled/

# Disable default site:
sudo rm /etc/nginx/sites-enabled/default

# Test Nginx config:
sudo nginx -t
# Output: OK

# Reload:
sudo systemctl reload nginx
```

### 5.3: Update PHP-FPM
```bash
sudo nano /etc/php/8.2/fpm/php.ini

# Find and update:
max_execution_time = 60
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M

# Save and restart:
sudo systemctl restart php8.2-fpm
```

---

## **STEP 6: Deploy E-Noteria Code (10 minutes)**

### 6.1: Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/YOUR_USERNAME/noteria.git

# OR upload files via SCP:
scp -i noteria-key.pem -r ./noteria ubuntu@203.0.113.42:/tmp/
ssh -i noteria-key.pem ubuntu@203.0.113.42 "sudo mv /tmp/noteria /var/www/"
```

### 6.2: Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/noteria
sudo chmod -R 755 /var/www/noteria

# Create public folder if needed:
mkdir -p /var/www/noteria/public
sudo chown www-data:www-data /var/www/noteria/public
```

### 6.3: Create Directories
```bash
mkdir -p /var/www/noteria/storage/logs
mkdir -p /var/www/noteria/storage/uploads
chmod 777 /var/www/noteria/storage/logs
chmod 777 /var/www/noteria/storage/uploads
```

---

## **STEP 7: Setup MySQL Database (5 minutes)**

### 7.1: Create Database & User
```bash
sudo mysql -u root

# In MySQL prompt:
CREATE DATABASE noteria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'noteria_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON noteria.* TO 'noteria_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 7.2: Import Database
```bash
# Upload your database backup:
scp -i noteria-key.pem ./noteria-backup.sql ubuntu@203.0.113.42:/tmp/

# Import:
ssh -i noteria-key.pem ubuntu@203.0.113.42
sudo mysql -u root noteria < /tmp/noteria-backup.sql

# Verify:
sudo mysql -u root -e "USE noteria; SELECT COUNT(*) FROM users;"
```

### 7.3: Create Database Backups
```bash
sudo nano /etc/cron.daily/backup-noteria

# Paste:
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/noteria"
mkdir -p $BACKUP_DIR
mysqldump -u noteria_user -pPASSWORD noteria | gzip > $BACKUP_DIR/noteria_$TIMESTAMP.sql.gz

# Keep only last 30 days:
find $BACKUP_DIR -mtime +30 -delete

# Save and make executable:
sudo chmod +x /etc/cron.daily/backup-noteria
```

---

## **STEP 8: Install SSL Certificate (5 minutes)**

### 8.1: Install Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx

# Create certificate from CloudFlare Origin CA (recommended):
# 1. CloudFlare Dashboard → SSL/TLS → Origin Server
# 2. Create Certificate
# 3. Select hostnames: *.noteria.kosove.gov.al, api.noteria.kosove.gov.al
# 4. Download files

# Upload certificate:
scp -i noteria-key.pem noteria-origin.pem ubuntu@203.0.113.42:/tmp/
scp -i noteria-key.pem noteria-origin.key ubuntu@203.0.113.42:/tmp/

# Install on server:
ssh -i noteria-key.pem ubuntu@203.0.113.42
sudo cp /tmp/noteria-origin.pem /etc/ssl/certs/
sudo cp /tmp/noteria-origin.key /etc/ssl/private/
sudo chmod 600 /etc/ssl/private/noteria-origin.key
```

### 8.2: Update Nginx for HTTPS
```bash
sudo nano /etc/nginx/sites-available/noteria

# Add this after HTTP server block:
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name api.noteria.kosove.gov.al;
    root /var/www/noteria/public;
    index index.php index.html;

    ssl_certificate /etc/ssl/certs/noteria-origin.pem;
    ssl_certificate_key /etc/ssl/private/noteria-origin.key;

    # SSL configuration:
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Rest of config same as HTTP block above...
}

# Redirect HTTP to HTTPS:
# In HTTP server block, change first line to:
return 301 https://$server_name$request_uri;

# Reload:
sudo nginx -t
sudo systemctl reload nginx
```

---

## **STEP 9: Configure UFW Firewall (5 minutes)**

### 9.1: Enable UFW
```bash
sudo ufw enable

# Allow SSH:
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS:
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow MySQL (if needed):
sudo ufw allow 3306/tcp

# Check status:
sudo ufw status
```

---

## **STEP 10: Connect to CloudFlare (5 minutes)**

### 10.1: Update CloudFlare DNS
```
CloudFlare Dashboard → noteria.kosove.gov.al → DNS

Update Records:
┌──────┬───────────────────────┬──────────────────┬─────┐
│ Type │ Name                  │ Content          │ TTL │
├──────┼───────────────────────┼──────────────────┼─────┤
│ A    │ api.noteria.kosove... │ 203.0.113.42     │ Auto│
│ A    │ noteria.kosove...     │ CloudFlare IP    │ Auto│
└──────┴───────────────────────┴──────────────────┴─────┘

Proxy Status: Proxied (🟠)
```

### 10.2: Update Worker Configuration
```
Edit: wrangler.toml

[env.production.vars]
BACKEND_URL = "https://203.0.113.42"
LOG_LEVEL = "warn"
CACHE_TTL = "3600"

Deploy:
wrangler publish --env production
```

### 10.3: Test Connection
```bash
# From your local machine:
curl https://api.noteria.kosove.gov.al/health

# Should return:
{"status":"ok","timestamp":"...","version":"1.0.0"}
```

---

## **STEP 11: Setup Monitoring (10 minutes)**

### 11.1: Install Monitoring Tools
```bash
ssh -i noteria-key.pem ubuntu@203.0.113.42

# CloudWatch agent:
sudo apt install -y amazon-cloudwatch-agent

# Configure:
sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
    -a fetch-config \
    -m ec2 \
    -s
```

### 11.2: Setup Log Rotation
```bash
sudo nano /etc/logrotate.d/noteria

# Paste:
/var/www/noteria/storage/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}

# Test:
sudo logrotate -f /etc/logrotate.d/noteria
```

### 11.3: Create Health Check Script
```bash
sudo nano /var/www/noteria/health.php

# Paste:
<?php
header('Content-Type: application/json');

$status = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'php' => phpversion(),
    'database' => 'checking'
];

try {
    $pdo = new PDO('mysql:host=localhost;dbname=noteria', 'noteria_user', 'PASSWORD');
    $result = $pdo->query('SELECT 1');
    $status['database'] = $result ? 'ok' : 'error';
} catch (Exception $e) {
    $status['database'] = 'error: ' . $e->getMessage();
}

echo json_encode($status);
?>

# Make accessible:
sudo chown www-data:www-data /var/www/noteria/health.php
```

---

## **STEP 12: Final Testing (10 minutes)**

### 12.1: Test All Endpoints
```bash
# Health check:
curl https://api.noteria.kosove.gov.al/health

# News endpoint:
curl https://api.noteria.kosove.gov.al/api/news

# Reservation endpoint:
curl -X POST https://api.noteria.kosove.gov.al/api/reservations \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"zyra_id":1,"date":"2024-03-15"}'

# Test SSL:
openssl s_client -connect api.noteria.kosove.gov.al:443
# Should show: "Verify return code: 0 (ok)"

# Test CloudWatch:
aws cloudwatch list-metrics --namespace AWS/EC2 --region us-east-1
```

### 12.2: Performance Test
```bash
# Test response time:
curl -w "
Time: %{time_total}s
Connect: %{time_connect}s
Transfer: %{time_starttransfer}s
" -o /dev/null -s https://api.noteria.kosove.gov.al/health

# Expected:
# Time: ~200ms (from localhost) / ~500ms (from USA)
```

---

## **COST SUMMARY**

```
AWS EC2 (t3.xlarge):      €350/month
  ├─ Compute
  ├─ Storage (100GB)
  ├─ Data transfer
  └─ Monitoring

Annual Cost:               €4,200
Per User (100K):           €0.042/year
```

---

## **MAINTENANCE CHECKLIST**

```
Daily:
  ☐ Check error logs: tail -f /var/log/nginx/error.log
  ☐ Check PHP-FPM: systemctl status php8.2-fpm
  ☐ Check MySQL: systemctl status mysql

Weekly:
  ☐ Check disk space: df -h
  ☐ Check CloudFlare cache hit ratio
  ☐ Review CloudWatch metrics
  ☐ Verify backups done

Monthly:
  ☐ Security updates: sudo apt update && apt upgrade
  ☐ Review costs in AWS Billing
  ☐ Check SSL certificate expiration
  ☐ Performance review

Quarterly:
  ☐ Database optimization: ANALYZE TABLE
  ☐ Security audit
  ☐ Capacity planning
```

---

## **TROUBLESHOOTING**

### **Nginx not starting**
```bash
sudo nginx -t          # Check syntax
sudo systemctl status nginx
sudo tail -f /var/log/nginx/error.log
```

### **PHP-FPM issues**
```bash
sudo systemctl status php8.2-fpm
sudo tail -f /var/log/php8.2-fpm.log
```

### **MySQL connection errors**
```bash
sudo mysql -u root
SHOW DATABASES;
SELECT user, host FROM mysql.user;
```

### **CloudFlare showing 500 error**
```bash
1. Check backend logs:
   sudo tail -f /var/log/nginx/error.log
2. Check PHP errors:
   sudo tail -f /var/log/php8.2-fpm.log
3. Check MySQL:
   sudo systemctl status mysql
```

---

**Status: ✅ AWS SETUP COMPLETE**

**Your backend server is ready for CloudFlare!**

**Next Step:** Deploy CloudFlare Workers + Pages using CLOUDFLARE_DEPLOYMENT_STEPS.md
