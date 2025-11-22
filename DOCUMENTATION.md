# 📋 Dokumentacioni i Platformës Noteria
## Sistemi i Verifikimit të Pagesave Online

### 🎯 Përmbledhje e Sistemit

Platforma Noteria ofron një sistem të avancuar për regjistrimin dhe verifikimin e pagesave për zyrat e noterisë në Kosovë. Sistemi përmban:

- ✅ Verifikim të plotë të IBAN-it me algoritmin mod-97
- ✅ Ngarkimi dhe validimi i dëshmive të pagesës
- ✅ Sistemi i auditimit dhe logimit
- ✅ Mbrojtje kundër spam-it dhe sulmeve
- ✅ Email konfirmimi (konfigurohet sipas nevojës)

---

### 🚀 Hapat e Instalimit

#### 1. Përgatitja e Mjedisit
```bash
# Sigurohuni që XAMPP është i instaluar dhe aktiv
# MySQL dhe Apache duhet të jenë në punë
```

#### 2. Konfigurimi i Databazës
```php
// Hapni: d:\xampp\htdocs\noteria\payment_config.php
// Përditësoni të dhënat e databazës:
$db_host = 'localhost';
$db_name = 'noteria';
$db_user = 'root';
$db_pass = '';
```

#### 3. Krijoni Tabelat
Ekzekutoni: `http://localhost/noteria/setup_payment_tables.php`

#### 4. Testoni Sistemin
Hapni: `http://localhost/noteria/test_dashboard.php`

---

### 🔧 Konfigurimi i Email-it

#### Konfigurimi Bazik (Test Mode)
Email sistemi fillimisht është në modalitetin test (vetëm log).

#### Aktivizimi i Email-ave (Produksion)
```php
// Hapni: d:\xampp\htdocs\noteria\email_config.php
// Ndryshoni:
$email_config = [
    'smtp_enabled' => true, // Ndryshoni nga false në true
    'smtp_host' => 'smtp.gmail.com',
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    // ... të tjerat
];
```

#### Gmail Setup (Opsional)
1. Aktivizoni 2-Factor Authentication në Gmail
2. Krijoni një "App Password"
3. Përdorni App Password në vend të password-it tuaj normal

---

### 💳 Metodat e Pagesës të Mbështetura

#### 1. Transferta Bankare
- **IBAN Kosovo:** XK + 2 shifra kontroll + 4 shifra banke + 10 shifra llogarie
- **Validimi:** Algoritmi mod-97 për verifikimin e IBAN-it
- **Format i Pranuar:** XK051212012345678906

#### 2. PayPal
- **Email:** Adresa e email-it të PayPal
- **Transaction ID:** ID e transaksionit nga PayPal

#### 3. Kartat e Kreditit
- **Të mbështetura:** Visa, MasterCard, American Express
- **Validimi:** Algoritmi Luhn për numrin e kartës

---

### 📁 Struktura e Fajllave

```
d:\xampp\htdocs\noteria\
├── zyrat_register.php          # Forma kryesore e regjistrimit
├── PaymentVerificationAdvanced.php # Klasa për verifikimin e pagesave
├── payment_config.php          # Konfigurimi i databazës
├── email_config.php            # Konfigurimi i email-it
├── setup_payment_tables.php    # Krijimi i tabelave
├── test_dashboard.php          # Dashboard i testimit
├── test_payment_system.php     # Test sistemi
└── uploads/                    # Direktoria për ngarkimet
    └── payment_proofs/         # Dëshmi të pagesave
```

---

### 🗄️ Struktura e Databazës

#### payment_logs
| Kolona | Tipi | Përshkrimi |
|--------|------|------------|
| id | INT PRIMARY KEY | ID unike |
| user_email | VARCHAR(255) | Email i përdoruesit |
| office_name | VARCHAR(255) | Emri i zyrës |
| payment_method | VARCHAR(50) | Metoda e pagesës |
| payment_amount | DECIMAL(10,2) | Shuma e pagesës |
| payment_details | TEXT | Të dhënat e pagesës |
| transaction_id | VARCHAR(100) | ID e transaksionit |
| verification_status | ENUM | pending/verified/rejected |
| file_path | VARCHAR(500) | Rruga e fajllit |
| created_at | TIMESTAMP | Koha e krijimit |

#### payment_audit_log
| Kolona | Tipi | Përshkrimi |
|--------|------|------------|
| id | INT PRIMARY KEY | ID unike |
| user_email | VARCHAR(255) | Email i përdoruesit |
| action | VARCHAR(100) | Veprimi |
| details | TEXT | Detajet |
| ip_address | VARCHAR(45) | IP adresa |
| user_agent | VARCHAR(500) | User Agent |
| created_at | TIMESTAMP | Koha |

#### security_settings
| Kolona | Tipi | Përshkrimi |
|--------|------|------------|
| id | INT PRIMARY KEY | ID unike |
| setting_name | VARCHAR(100) | Emri i konfigurimit |
| setting_value | VARCHAR(255) | Vlera |
| description | TEXT | Përshkrimi |
| updated_at | TIMESTAMP | Koha e përditësimit |

---

### 🔒 Veçoritë e Sigurisë

#### Rate Limiting
- **5 përpjekje maksimum** për email brenda 3 minutave (optimizuar nga 24 orët)
- **Kontrolli i duplikateve** në bazë të transaction ID
- **Verifikimi i IP adresës** për auditim

#### Validimi i Fajllave
- **Formate të lejuara:** PDF, JPG, JPEG, PNG
- **Madhësia maksimale:** 5MB
- **Kontroll MIME type** për sigurinë
- **Emra unikë** për të shmangur konfliktet

#### IBAN Validation
```php
// Algoritmi mod-97 për IBAN Kosovo
function validateIBANAdvanced($iban) {
    // 1. Kontrollo formatin XK + 20 karaktere
    // 2. Llogarit checksum me mod-97
    // 3. Kontrollo bankën dhe llojin e llogarisë
}
```

---

### 🧪 Testimi i Sistemit

#### Test Dashboard
Hapni: `http://localhost/noteria/test_dashboard.php`

**Kontrollon:**
- ✅ Lidhjen me databazën
- ✅ Ekzistimin e tabelave
- ✅ Sistemin e pagesave
- ✅ Konfigurimin e email-it
- ✅ Të dhënat e fundit

#### Test Manual
1. **IBAN Valid:** XK051212012345678906
2. **IBAN Invalid:** XK051212012345678907
3. **Email Test:** test@example.com
4. **Fajlli Test:** PDF, JPG (nën 5MB)

---

### 🚨 Zgjidhja e Problemeve

#### Problemi: "Table doesn't exist"
```bash
# Zgjidhja:
1. Hapni: http://localhost/noteria/setup_payment_tables.php
2. Ose ekzekutoni create_payment_tables.sql në phpMyAdmin
```

#### Problemi: "Failed to connect to mailserver"
```php
// Zgjidhja 1: Çaktivizoni email-et
$email_config['smtp_enabled'] = false;

// Zgjidhja 2: Konfiguroni SMTP saktë
// Shikoni seksionin "Konfigurimi i Email-it"
```

#### Problemi: "File upload failed"
```php
// Kontrolloni:
1. Direktoria uploads/payment_proofs/ ekziston?
2. Ka leje shkrimi (chmod 755)?
3. Fajlli është nën 5MB?
4. Formati është i pranuar (PDF/JPG/PNG)?
```

#### Problemi: "IBAN invalid"
```php
// IBAN i saktë për Kosovë:
XK + 2 shifra + 4 shifra banke + 12 shifra llogarie
Shembull: XK051212012345678906
```

---

### 📞 Mbështetje Teknike

#### Log Files
- **PHP Errors:** `d:\xampp\php\logs\php_error_log`
- **Apache Errors:** `d:\xampp\apache\logs\error.log`
- **Payment Logs:** Në databazë `payment_audit_log`

#### Debugging Mode
```php
// Shtoni në fillim të fajllit për debug:
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

#### Backup Database
```sql
-- Backup i rëndësishëm para ndryshimeve
mysqldump -u root noteria > backup_noteria.sql
```

---

### 🔄 Maintenance

#### Daily Tasks
- Kontrolloni payment_logs për transaksione të reja
- Verifikoni dëshmi të pagesave të ngarkuara
- Përcaktoni statusin: verified/rejected

#### Weekly Tasks
- Pastroni fajllat e vjetër nga uploads/
- Bëni backup të databazës
- Kontrolloni log files për gabime

#### Monthly Tasks
- Analizoni statistikat e pagesave
- Përditësoni konfiguracionet e sigurisë
- Rishikoni rate limits

---

### 📈 Statistikat

```sql
-- Numri total i regjistruar
SELECT COUNT(*) FROM payment_logs;

-- Regjistrime sot
SELECT COUNT(*) FROM payment_logs WHERE DATE(created_at) = CURDATE();

-- Pagesave të verifikuara
SELECT COUNT(*) FROM payment_logs WHERE verification_status = 'verified';

-- Metodat më të përdorura
SELECT payment_method, COUNT(*) 
FROM payment_logs 
GROUP BY payment_method 
ORDER BY COUNT(*) DESC;
```

---

### 🆕 Versioni dhe Update

**Versioni Aktual:** 1.0.0  
**Data e Update:** Janar 2025  
**Compatibility:** PHP 7.4+, MySQL 5.7+

#### Update Notes
- ✅ Sistemi bazik i pagesave implementuar
- ✅ IBAN validation për Kosovë
- ✅ Rate limiting dhe siguria
- ⏳ SMTP email konfigurimi opsional
- ⏳ Admin panel për menaxhimin e pagesave

---

*Dokumentacioni u përgatit për platformën Noteria - Sistemi i Verifikimit të Pagesave Online*