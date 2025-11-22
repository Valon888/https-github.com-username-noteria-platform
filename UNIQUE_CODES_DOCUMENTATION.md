# 📋 Dokumentacioni i Sistemit të Kodeve Unike

## Përmbledhje

Sistemi i kodeve unike është një funksionalitet i avancuar i sigurisë që lejon përdoruesit të kyçen duke përdorur një kod unik si alternativa ndaj Captcha-s. Çdo përdorues mund të ketë mbi 1 milion kode unike të ndryshme.

---

## 🎯 Karakteristikat Kryesore

- **1M+ Kodet për Përdorues**: Çdo përdorues mund të marrë më shumë se 1 milion kode unike
- **Kodet Unike**: Secili kod është UNIQUE në bazën e të dhënave
- **Verifikim Sigurie**: Kodet nuk mund të përdoren dy herë
- **Gjenerim Efikas**: Bulk insert me 5000 kode për grupe
- **Statusi Kodit**: Kodet markohen si të përdorur pasi të kyçen
- **Administrim i Lehtë**: Interfacë admin për menaxhim të kodeve

---

## 🗄️ Struktura e Bazës të Dhënash

### Tabela: `user_unique_codes`

```sql
CREATE TABLE user_unique_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used BOOLEAN DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_code (user_id, code)
);
```

**Kolonat:**
- `id`: Identifikuesi unik i rekordit
- `user_id`: Referenca ndaj përdoruesit (FK ndaj `users.id`)
- `code`: Kodi unik (16 karaktere heksadecimal të mëdha)
- `generated_at`: Data dhe koha e gjenerimit
- `used`: 0 = në dispozicion, 1 = përdorur
- `idx_user_code`: Indeksi për kërkime të shpejta

---

## 📁 Skedarët e Sistemit

### 1. **generate_user_codes.php** - Gjenerim i Kodeve

Skript për të gjeneruar 1M+ kode unike për përdorues.

**Përdorimi përmes CLI:**
```bash
php generate_user_codes.php <user_id> [count]
```

**Shembull:**
```bash
php generate_user_codes.php 1 1000000
```

**Përdorimi përmes Web (Admin):**
```
http://localhost/noteria/generate_user_codes.php?user_id=1&count=1000000
```

**Karakteristikat:**
- Gjeneron kode unike në grupe të 5000
- Të dhëna transaksionale (rollback nëse dështim)
- Kontrollon duplikatë përmes UNIQUE constraint
- Përfaqësim përparimi në real-time

### 2. **admin_unique_codes.php** - Administrim

Interfacë admin për menaxhim të kodeve.

**Aksesi:**
```
http://localhost/noteria/admin_unique_codes.php
```

**Funksionalitetet:**
- Gjenero kode për përdorues spesifik
- Gjenero kode për të gjithë përdoruesit
- Shfaq statistikat e kodeve
- Monitoro përdorimin e kodeve

### 3. **test_unique_codes.php** - Testim

Skript testimi për të verifikuar funksionimin e sistemit.

**Përdorimi:**
```bash
php test_unique_codes.php
```

**Kontrollet:**
1. Kontrollim i përdoruesit test
2. Kontrollim i kodeve ekzistues
3. Gjenerim i kodeve unike (100K për test)
4. Marrje i kodit për testim
5. Statistika e kodeve
6. Testim i kyçjes me kodin

### 4. **export_user_codes.php** - Eksport

Skript për të eksportuar kodet e një përdoruesi në CSV ose JSON.

**Përdorimi:**
```bash
php export_user_codes.php <user_id> [format]
```

**Shembuj:**
```bash
php export_user_codes.php 1 csv
php export_user_codes.php 1 json
```

---

## 🔐 Integrimi në Login

### Metodat e Kyçjes

Formulari i kyçjes tani suporton dy metoda:

#### 1. **Metoda Standarde** (Default)
- Email + Fjalëkalim + Captcha (6 karaktere)
- Foto Letërnjoftimi/Pasaporta
- Numri Personal (10 shifra)

#### 2. **Metoda me Kod Unik**
- Email + Fjalëkalim + Kod Unik (16 karaktere)
- Foto Letërnjoftimi/Pasaporta
- Numri Personal (10 shifra)

### Ndryshimi Dinamik

```javascript
// Në login.php ekziston switch dinamik:
document.querySelector('input[name="login_method"]').addEventListener('change', toggleLoginMethod);
```

Kur ndryshon metoda:
- Captcha fshihet/shfaqet
- Kodi Unik fshihet/shfaqet
- Kërkesat e obligueshme përditësohen dinamikisht

---

## 🚀 Procesi i Kyçjes me Kod Unik

```
1. Përdoruesi përfillon formularin e kyçjes
2. Zgjedh metodën "Kod Unik"
3. Shkruan email, fjalëkalim, kod, informacionin personal
4. Server kontrollon:
   - Email dhe fjalëkalim janë të saktë
   - Rol përputhet
   - Fotoja është e vlefshme
   - Numri personal është i vlefshëm
5. Kontrollon kodin:
   - SELECT FROM user_unique_codes WHERE code = ? AND used = 0
6. Nëse kodi ekziston:
   - UPDATE user_unique_codes SET used = 1 WHERE code = ?
   - Kyçje e suksesshme
7. Nëse kodi nuk ekziston ose është përdorur:
   - Gabim: "Kodi unik nuk është i vlefshëm"
```

---

## 📊 Statuset dhe Statistika

### Shfaq Statistikat

Në `admin_unique_codes.php`:

```
Përdorues Aktiv: 1
Kodet Totale: 1,000,000
Kodet në Dispozicion: 999,995
Kodet e Përdorur: 5
```

### Përqindja e Përdorimit

```
Formula: (used_codes / total_codes) * 100
Shembull: (5 / 1,000,000) * 100 = 0.0005%
```

---

## ⚙️ Konfigurim i Avancuar

### Gjatësia e Kodit

Kodet aktual janë 16 karaktere heksadecimal:
```php
$code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
// Shembull: A7F3B2E9D4C1F6A8
```

**Kombinime të mundshme:** 16^16 = 1.8 x 10^19 (18 trilionë!)

### Pika Referimi

Për të ndryshuar gjatësinë:

**Më e shkurtër (12 karaktere):**
```php
$code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
```

**Më e gjatë (20 karaktere):**
```php
$code = strtoupper(substr(bin2hex(random_bytes(10)), 0, 20));
```

---

## 🔧 Komanda SQL Të Dobishme

### Merr Statistika për Përdorues

```sql
SELECT 
    u.id,
    u.emri,
    u.mbiemri,
    COUNT(uuc.id) as total_codes,
    SUM(CASE WHEN uuc.used = 0 THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN uuc.used = 1 THEN 1 ELSE 0 END) as used
FROM users u
LEFT JOIN user_unique_codes uuc ON u.id = uuc.user_id
WHERE u.status = 'aktiv'
GROUP BY u.id;
```

### Gjenero Kode Direkte (SQL)

```sql
-- Gjenero 1000 kode për user_id = 1
INSERT INTO user_unique_codes (user_id, code)
SELECT 1, UPPER(CONCAT(
    LPAD(HEX(RAND() * 281474976710655), 12, '0'),
    LPAD(HEX(RAND() * 281474976710655), 12, '0')
)) FROM (
    SELECT 1 FROM users 
    WHERE id = 1 
    LIMIT 1000
) t;
```

### Marko të Gjithë Kodet si të Përdorur

```sql
UPDATE user_unique_codes 
SET used = 1 
WHERE user_id = 1 AND used = 0;
```

### Zbrazi të Gjithë Kodet

```sql
DELETE FROM user_unique_codes 
WHERE user_id = 1;
```

---

## 🔒 Konsiderata Sigurie

1. **UNIQUE Constraint**: Çdo kod mund të përdoret vetëm një herë
2. **Status i Kodit**: Kodet markohen si të përdorur menjëherë
3. **Transaksionet**: Bulk inserts nuk ndodhin pa transaksion
4. **FK Constraint**: Nëse përdoruesi fshihet, kodet fshihen automatikisht
5. **Logging**: Kyçja me kod regjistrohet në audit_log

---

## 📱 Përdorimi nga Kënd i Përdoruesit

### Marrja e Kodeve

1. Kontaktojini administratorin
2. Administratori gjeneron kodet përmes `admin_unique_codes.php`
3. Administratori i dërgon kodet sipas ndonjë kanali të sigurt

### Kyçja me Kod

1. Shkoni në login.php
2. Zgjidhni "Kod Unik" si metodë kyçje
3. Futni email, fjalëkalim, kod, informacionin personal
4. Klikoni "Kyçu"

---

## 📈 Rritje Përfeksionale

Sistemi mund të rritet në:
- **Më shumë përdorues**: Thjesht gjenero kode për përdoruesit e rinj
- **Më shumë kode për përdorues**: Rriti `code_count` në `generate_user_codes.php`
- **Kodet me rrjedhë kohore**: Shto kolona si `expires_at` në user_unique_codes
- **Kodet të organizuar sipas kërkesës**: Shto kolona si `category` ose `campaign`

---

## 🆘 Troubleshooting

### Problem: "Kodi unik nuk është i vlefshëm"
**Zgjidhja**: Verifikoni se kodi:
- Ekziston në bazën e të dhënave
- Nuk është përdorur tashmë (used = 0)
- Përket përdoruesit të saktë

### Problem: "Duplicate entry for key 'code'"
**Zgjidhja**: Kodet janë të gjeneruar tashmë. Kontrolloni:
```sql
SELECT COUNT(*) FROM user_unique_codes WHERE user_id = 1;
```

### Problem: Gjenerim i ngadalshëm
**Zgjidhja**: Zvogëloni `batch_size` në `generate_user_codes.php` nga 5000 në 1000.

---

## 📚 Referencat

- **Tabela**: `user_unique_codes` në bazën e të dhënave `noteria`
- **Skedarë**: 
  - generate_user_codes.php
  - admin_unique_codes.php
  - test_unique_codes.php
  - export_user_codes.php
- **Integrim**: login.php

---

**Përditesuar**: 2024
**Versioni**: 1.0
**Gjuhë**: Shqip
