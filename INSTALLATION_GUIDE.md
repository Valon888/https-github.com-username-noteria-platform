# Udhëzues Instalimi - Sistem Verifikimi të Pagesave Online

## 1. Përgatitja e Mjedisit

### Kërkesa të Sistemit
- PHP 7.4 ose më i ri
- MySQL 5.7 ose më i ri / MariaDB 10.2+
- Ekstensions: PDO, cURL, OpenSSL, JSON, BCMath
- Apache/Nginx me mod_rewrite të aktivizuar

### Instalimi i Ekstensions të Nevojshme
```bash
# Ubuntu/Debian
sudo apt-get install php-curl php-pdo-mysql php-bcmath php-openssl

# CentOS/RHEL
sudo yum install php-curl php-pdo php-bcmath php-openssl
```

## 2. Konfigurimi i Bazës së të Dhënave

### Krijimi i Tabelave
```bash
# Ekzekutoni SQL script-in
mysql -u username -p database_name < create_payment_tables.sql
```

### Verifikimi i Instalimit
```sql
-- Kontrolloni nëse tabelat janë krijuar
SHOW TABLES LIKE '%payment%';
SHOW TABLES LIKE 'zyrat';

-- Verifikoni strukturën
DESCRIBE payment_logs;
DESCRIBE zyrat;
```

## 3. Konfigurimi i Variablave të Mjedisit

### Krijoni file .env në rrënjën e projektit
```env
# Të dhënat e bazës së të dhënave
DB_HOST=localhost
DB_NAME=noteria_db
DB_USER=your_username
DB_PASS=your_password

# API Keys për bankat (duhet të merren nga bankat)
BEK_API_KEY=your_bek_api_key
BPB_API_KEY=your_bpb_api_key
BKT_API_KEY=your_bkt_api_key
PROCREDIT_API_KEY=your_procredit_api_key
RAIFFEISEN_API_KEY=your_raiffeisen_api_key
TEB_API_KEY=your_teb_api_key

# PayPal Configuration
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
PAYPAL_MODE=sandbox  # ose 'live' për production

# Stripe Configuration
STRIPE_SECRET_KEY=your_stripe_secret_key
STRIPE_PUBLISHABLE_KEY=your_stripe_publishable_key

# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
FROM_EMAIL=noreply@noteria.com

# Security Settings
ENCRYPTION_KEY=your_32_character_encryption_key
LOG_LEVEL=INFO
```

### Ngarkimi i variablave në PHP
```php
// Shtoni në fillim të config.php
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}
```

## 4. Konfigurimi i Sigurisë

### Vendosja e të Drejtave të File-ave
```bash
# Direktoria e upload-ave
mkdir -p uploads/payment_proofs
chmod 755 uploads/
chmod 755 uploads/payment_proofs/

# Log files
mkdir -p logs
chmod 755 logs/
touch logs/payment_system.log
chmod 644 logs/payment_system.log

# Konfigurimi files
chmod 600 .env
chmod 644 payment_config.php
```

### Konfigurimi i Apache (.htaccess)
```apache
# Krijoni .htaccess në direktori upload
<Files "*">
    Order Deny,Allow
    Deny from all
</Files>

<FilesMatch "\.(pdf|jpg|jpeg|png)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Hiq aksesin në files e konfigurimit
<Files ".env">
    Order Deny,Allow
    Deny from all
</Files>
```

## 5. Testimi i Sistemit

### Test i Bazik
```php
<?php
// test_payment_system.php
require_once 'config.php';
require_once 'PaymentVerificationAdvanced.php';

try {
    $verifier = new PaymentVerificationAdvanced($pdo);
    
    // Test IBAN validation
    $test_iban = 'XK051212012345678906';
    $iban_valid = $verifier->validateIBANAdvanced($test_iban);
    echo "IBAN Test: " . ($iban_valid ? "VALID" : "INVALID") . "\n";
    
    // Test transaction ID generation
    $transaction_id = $verifier->generateSecureTransactionId();
    echo "Generated Transaction ID: " . $transaction_id . "\n";
    
    echo "Sistema është e gatshme për përdorim!\n";
    
} catch (Exception $e) {
    echo "Gabim: " . $e->getMessage() . "\n";
}
?>
```

### Test i API-ve të Bankave
```bash
# Testoni lidhjen me API-të e bankave
curl -X POST https://api.bek.com.mk/payment/verify \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

## 6. Monitorimi dhe Mirëmbajtja

### Log Monitoring
```bash
# Monitoroni log-et e sistemit
tail -f logs/payment_system.log

# Analizoni gabime të shpeshta
grep "ERROR" logs/payment_system.log | tail -20
```

### Database Maintenance
```sql
-- Pastro transaksionet e vjetra (mbi 1 vit)
DELETE FROM payment_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR) 
AND status NOT IN ('completed', 'pending');

-- Optimizo tabelat
OPTIMIZE TABLE payment_logs, zyrat, payment_audit_log;
```

### Performance Monitoring
```sql
-- Kontrolloni performancën e query-ve
SELECT 
    COUNT(*) as total_payments,
    AVG(verification_attempts) as avg_attempts,
    payment_method,
    status
FROM payment_logs 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY payment_method, status;
```

## 7. Backup dhe Recovery

### Backup Script
```bash
#!/bin/bash
# backup_payment_system.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/noteria"

# Database backup
mysqldump -u username -p database_name > $BACKUP_DIR/db_backup_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz \
    uploads/ logs/ .env payment_config.php

# Mbaj vetëm 30 ditët e fundit
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

## 8. Troubleshooting

### Problemet e Shpeshta

1. **IBAN Validation Fails**
   - Kontrolloni që IBAN të fillojë me 'XK'
   - Verifikoni gjatësinë (20-21 karaktere)

2. **API Connection Timeout**
   - Rritni timeout në konfiguraion
   - Kontrolloni network connectivity

3. **File Upload Errors**
   - Kontrolloni PHP upload limits
   - Verifikoni të drejtat e direktorisë

4. **Database Connection Issues**
   - Kontrolloni kredencialet në .env
   - Verifikoni lidhjen e MySQL

### Log Messages të Rëndësishme
```
INFO: Payment verification started for transaction: TXN_xxx
WARNING: Bank API slow response (>10s)
ERROR: Failed to verify payment after 3 attempts
ERROR: Database connection failed
```

## 9. Deployment në Production

### Checklist para Deployment
- [ ] Ndryshoni të gjitha password-at default
- [ ] Aktivizoni HTTPS (SSL certificate)
- [ ] Çaktivizoni error display (`display_errors = 0`)
- [ ] Konfiguroni log rotation
- [ ] Testoni backup dhe recovery
- [ ] Vendosni monitoring alerts
- [ ] Dokumentoni të gjitha API keys

### Production Optimizations
```php
// Në fillim të config.php për production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php/noteria_errors.log');

// Aktivizo OPcache
ini_set('opcache.enable', 1);
ini_set('opcache.validate_timestamps', 0);
```

Ky sistem siguron verifikim të qëndrueshëm dhe të sigurt të pagesave online për platformën e noterisë në Kosovë.