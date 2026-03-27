# 🔧 Zgjidhja e Gabimit të Regjistrimit të Reklamuesve

## 📋 Përmbledhje
Gabimi "Ndodhi një gabim gjatë regjistrimit" ndodh zakonisht sepse tabela `advertisers` nuk ekziston ose ka probleme me strukturën e saj.

---

## ✅ Hapat e Setupit

### 1️⃣ Kontrolloni Statusin e Sistemit
Aksesu këtë URL në shfletuesin tuaj:
```
http://localhost/noteria/diagnostic.php
```

Kjo do të kontrollojë:
- ✅ Lidhjen me databazën
- ✅ Ekzistencën e tabelës `advertisers`
- ✅ Ekstensimet e PHP
- ✅ Lejet e fajlleve
- 🧪 Do të bëjë një test INSERT automatik

### 2️⃣ Krijoni Tabelën (nëse nuk ekziston)
Nëse `diagnostic.php` tregon se tabela nuk ekziston, aksesu:
```
http://localhost/noteria/setup_advertisers_table.php
```

Kjo script automatikisht do të:
- 🏗️ Krijon tabelën `advertisers`
- 📋 Shfaq strukturën e tabelës
- ✅ Konfirmon suksesin

### 3️⃣ Testoni Formën
Pasi tabela të jetë e settuar, testoni formën në:
```
http://localhost/noteria/test_advertiser_form.php
```

Këtu mund të:
- 📝 Plotësoni të dhënat e provës
- 📤 Dorëzoni formën
- ✅ Shihni nëse inserto ndodh me sukses

### 4️⃣ Përdorni Formën Aktuale
Nëse testi kalon, përdorni formën aktuale në:
```
http://localhost/noteria/become-advertiser.php
```

---

## 🐛 Troubleshooting

### Problemë: "Gabim në lidhjen me bazën e të dhënave"
**Zgjidhje:**
- Kontrolloni që `confidb.php` të ketë të dhënat e sakta të lidhjes
- Verifikoni që serveri MySQL është qysh
- Kontrolloni credentiale (përdorues/fjalëkalim)

### Problemë: "Tabela nuk ekziston"
**Zgjidhje:**
- Aksesu `setup_advertisers_table.php` (see step 2 above)

### Problemë: "Email është tashmë i regjistruar"
**Zgjidhje:**
- Përdorni një email të ndryshëm
- Ose fshini rekordin e vjetër: `DELETE FROM advertisers WHERE email = 'email@example.com'`

### Problemë: "Fusha të këruara nuk janë plotësuar"
**Zgjidhje:**
- Plotësoni të gjitha fushat e kërkuara (*)
- Emri i Kompanisë
- Email
- Kategoria

### Problemë: "Email nuk është në formatin e saktë"
**Zgjidhje:**
- Përdorni format të saktë: info@kompania.com

---

## 📊 Struktura e Tabelës

```sql
CREATE TABLE advertisers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    website VARCHAR(255),
    category VARCHAR(100),
    description LONGTEXT,
    business_registration VARCHAR(100),
    subscription_status VARCHAR(50) DEFAULT 'pending',
    subscription_plan VARCHAR(50),
    payment_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (subscription_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔗 Links të Përdorshme

| Link | Qëllimi |
|------|---------|
| `/diagnostic.php` | Kontrollimi i sistemit |
| `/setup_advertisers_table.php` | Krimi i tabelës |
| `/test_advertiser_form.php` | Testimi i formës |
| `/become-advertiser.php` | Forma aktuale e reklamuesve |

---

## 📞 Suport

Nëse problemat vazhdojnë:

1. Kontrolloni error logs:
   - `php error_log`
   - MySQL error logs

2. Verifikoni SQL manual:
   ```sql
   SHOW TABLES LIKE 'advertisers';
   DESC advertisers;
   SELECT * FROM advertisers LIMIT 5;
   ```

3. Kontrolloni permissions:
   ```bash
   chmod 755 become-advertiser.php
   chmod 755 setup_advertisers_table.php
   chmod 755 diagnostic.php
   ```

---

## 🎯 Përfundim

Ndjekin këtë proces rend dhe problemi duhet të zgjidhet! ✅

Nëse na duhet më shumë ndihmë, kontaktoni administratorin tuaj lokal.
