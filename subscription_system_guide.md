# SISTEM I PAGESAVE AUTOMATIKE PËR ABONIME - UDHËZUES INSTALIMI DHE KONFIGURIMI

Ky dokument përshkruan procesin e instalimit dhe konfigurimit të sistemit të pagesave automatike për abonimet e noterëve në platformën Noteria.

## PËRMBLEDHJE

Sistemi i pagesave automatike për abonime përmban këto module:

1. **subscription_processor.php** - Procesori kryesor për pagesat automatike
2. **subscription_settings.php** - Faqja e konfigurimit të sistemit të abonimeve
3. **subscription_custom_prices.php** - Menaxhimi i çmimeve të personalizuara
4. **subscription_payments.php** - Menaxhimi i pagesave dhe historisë
5. **subscription_reports.php** - Raportet dhe statistikat e abonimeve
6. **subscription_reports_export.php** - Eksportimi i raporteve në CSV
7. **subscription_notifications.php** - Dërgimi i njoftimeve për abonimet

## INSTALIMI

### 1. Krijimi i tabelave në databazë

Sistemi krijon automatikisht tabelat e nevojshme nëse ato nuk ekzistojnë. Megjithatë, mund t'i krijoni edhe manualisht duke ekzekutuar këto komanda SQL:

```sql
-- Tabela për pagesat e abonimeve
CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    noteri_id INT NOT NULL,
    payment_date DATETIME NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'test') NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference_id VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (noteri_id) REFERENCES noteri(id) ON DELETE CASCADE
);

-- Tabela për konfigurimet e sistemit
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela për log-et e aktivitetit
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type VARCHAR(50) NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fushat e reja në tabelën noteri
ALTER TABLE noteri ADD COLUMN IF NOT EXISTS subscription_status ENUM('active', 'inactive', 'pending') DEFAULT 'active';
ALTER TABLE noteri ADD COLUMN IF NOT EXISTS last_subscription_payment DATE NULL;
ALTER TABLE noteri ADD COLUMN IF NOT EXISTS custom_price DECIMAL(10, 2) NULL;
ALTER TABLE noteri ADD COLUMN IF NOT EXISTS bank_account VARCHAR(255) NULL;
```

### 2. Shtimi i konfigurimeve fillestare

Ekzekutoni komandat SQL të mëposhtme për të shtuar konfigurimet fillestare:

```sql
-- Konfigurimet fillestare të sistemit të abonimeve
INSERT INTO system_settings (name, value, description) VALUES
('subscription_price', '20.00', 'Çmimi standard i abonimit mujor në EUR'),
('subscription_day', '1', 'Dita e muajit kur procesohen pagesat automatike'),
('subscription_grace_period', '15', 'Periudha e pezullimit në ditë pas skadimit të abonimit'),
('subscription_reminder_days', '3', 'Sa ditë para skadimit të dërgohet kujtesa'),
('subscription_secure_token', UUID(), 'Token-i i sigurisë për aksesin e procesimit automatik'),
('send_payment_confirmation', '1', 'A duhet të dërgohet email konfirmimi pas pagesës'),
('send_subscription_notifications', '1', 'A duhet të dërgohen njoftimet për abonime që skadojnë'),
('system_email', 'info@noteria.al', 'Emaili i sistemit për dërgimin e njoftimeve'),
('system_name', 'Noteria', 'Emri i sistemit për dërgimin e emaileve');
```

## KONFIGURIMI I CRON JOBS

Për të automatizuar procesin e pagesave dhe njoftimeve, duhet të konfiguroni dy Cron Jobs në server.

### 1. Cron Job për procesimin e pagesave

Konfiguroni një Cron Job që të ekzekutohet një herë në ditë (p.sh. në orën 2:00 të mëngjesit) për të procesuar pagesat automatike:

```bash
0 2 * * * /usr/bin/php /path/to/noteria/subscription_processor.php
```

Ose me token të sigurisë nëpërmjet curl:

```bash
0 2 * * * curl -s "https://your-domain.com/noteria/subscription_processor.php?secure_token=YOUR_SECURE_TOKEN"
```

### 2. Cron Job për dërgimin e njoftimeve

Konfiguroni një Cron Job që të ekzekutohet një herë në ditë (p.sh. në orën 10:00 të mëngjesit) për të dërguar njoftimet për abonimet:

```bash
0 10 * * * /usr/bin/php /path/to/noteria/subscription_notifications.php
```

Ose me token të sigurisë nëpërmjet curl:

```bash
0 10 * * * curl -s "https://your-domain.com/noteria/subscription_notifications.php?secure_token=YOUR_SECURE_TOKEN"
```

## TESTIMI I SISTEMIT

Për të testuar sistemin pa procesuar pagesa reale:

1. Shkoni te `subscription_processor.php?test=true` në shfletues
2. Kontrolloni rezultatin në faqe dhe log-et
3. Verifikoni që pagesat test janë shënuar me status "test" në databazë

## AKSESIMI I MODULEVE

Të gjitha modulet e sistemit kërkojnë autorizim administratori për akses:

1. **Konfigurimet e abonimeve**: `subscription_settings.php`
2. **Çmimet e personalizuara**: `subscription_custom_prices.php`
3. **Menaxhimi i pagesave**: `subscription_payments.php`
4. **Raportet dhe statistikat**: `subscription_reports.php`
5. **Procesori (për testim)**: `subscription_processor.php?test=true`
6. **Njoftimet (për testim)**: `subscription_notifications.php`

## FLUKSI I SISTEMIT

1. Çdo ditë, në orën e konfiguruar, `subscription_processor.php` kontrollon nëse është dita e caktuar për të procesuar pagesat.
2. Nëse po, sistemi identifikon noterët me abonime aktive dhe procedon me pagesat automatike.
3. Për secilin noter, sistemi kontrollon nëse ka çmim të personalizuar, përndryshe përdor çmimin standard.
4. Pas procesimit të pagesës, statusi përditësohet dhe dërgohet email konfirmimi nëse është e aktivizuar.
5. Njoftimet për abonimet që skadojnë dërgohen sipas konfigurimit.
6. Administratorët mund të monitorojnë pagesat dhe të gjenerojnë raporte nga ndërfaqet përkatëse.

## SIGURIA

- Të gjitha akseset në fajllat e sistemit kërkojnë autentifikim administratori.
- Procesori i pagesave dhe njoftimet mund të thirren edhe me token të sigurisë për Cron Jobs.
- Aktiviteti ruhet në log për auditim.
- Passwordet dhe të dhënat e ndjeshme nuk ruhen në mënyrë të dukshme.

## ZGJERIMI I SISTEMIT

Sistemi është ndërtuar në mënyrë modulare për të lejuar zgjerime në të ardhmen:

1. **Integrimi me gateways të ndryshme pagesash** - Shtoni kodin për procesim real pagesash në `processActualPayment()` në fajllin `subscription_processor.php`.
2. **Opsione shtesë abonimesh** - Mund të shtohen plane të ndryshme abonimesh duke modifikuar strukturën e databazës dhe logjikën e procesimit.
3. **Raporte të avancuara** - Shtoni raporte dhe analitika të tjera në `subscription_reports.php`.

## TRAJNIMI I GABIMEVE

Gabimet dhe përjashtimet në sistem menaxhohen dhe ruhen:

1. Të gjitha gabimet regjistrohen në fajllin `error.log`.
2. Aktivitetet dhe veprimet kryesore ruhen në tabelën `activity_logs`.
3. Në rastin e dështimit të pagesës, regjistrimet ruhen në databazë për referencë të mëvonshme.

## KONFIGURIMI I PROPOZUAR

Konfigurimet e rekomanduara fillestare:

1. **Çmimi standard i abonimit**: 20 EUR / muaj
2. **Dita e pagesës**: 1 (dita e parë e çdo muaji)
3. **Periudha e pezullimit**: 15 ditë (pas skadimit të abonimit)
4. **Njoftimi para skadimit**: 3 ditë para
5. **Dërgimi i konfirmimeve**: Aktiv

---

Ky sistem është ndërtuar për të menaxhuar në mënyrë efikase pagesat automatike të abonimeve për noterët në platformën Noteria. Për çdo pyetje apo ndihmë shtesë, kontaktoni administratorin e sistemit.