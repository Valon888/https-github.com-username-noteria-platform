# 📋 SISTEMI I MENAXHIMIT TË ABONIMEVE - NOTERIA

## Përmbledhje

Ky sistem ofron një menaxhim të plotë të paketave abonimesh, zbritjeve, faturimit automatik dhe kujetesave të pagesave.

---

## 🎯 Karakteristikat Kryesore

### 1. **Pakete Abonimesh të Predefinuara**
- **Starter** - 9.99€/muaj për filluese
- **Pro** - 29.99€/muaj për profesionalë
- **Premium** - 99.99€/muaj për biznese të mëdha
- Personalizim i plotë i çmimeve, features, dhe limiteve

### 2. **Sistem Zbritjesh & Promocionesh**
- Zbritje në përqindje (%) ose shume fikse (€)
- Kode promosioni me limite përdimesh
- Përqindje specifike për plane të zgjedhura
- Periudha vlefshmërie të kontrolluara

### 3. **Faturim Automatik**
- Gjenero fatura të reja çdo muaj/vit
- Tatim automatik (20%)
- Zbritje të aplikuara automatikisht
- Numërim automatik i faturave

### 4. **Menaxhimi i Pagesave**
- Kujtesa të planifikuara (3 ditë përpara, 1 ditë përpara)
- Detektim automatik i pagesa vonuese
- Penalitete për vonesa (0.5% për ditë, max 10%)
- Plane pagesash me këste

### 5. **Automatizimi Ditor**
- Kontrollo abonimet e skaduar
- Dërgo kujtesa për pagesa të afërta
- Procesim i pagesa vonuese
- Përditëso statusin e abonimeve

---

## 📁 Struktura e Kodit

```
/noteria
├── /classes
│   ├── SubscriptionPlan.php          # Menaxhim pakete
│   ├── DiscountManager.php           # Menaxhim zbritjesh
│   └── BillingAutomation.php         # Automatizim faturimi
├── /api
│   ├── SubscriptionPlansController.php  # API për pakete
│   ├── DiscountsController.php          # API për zbritje
│   └── BillingController.php            # API për faturim
├── /admin
│   └── subscriptions_billing.php     # Dashboard admin
├── /cron
│   └── billing_cron.php              # Detyra ditore
└── /sql
    └── subscription_plans_migration.sql  # Migrimin DB
```

---

## 🗄️ Struktura e Bazës Të Dhanave

### `subscription_plans`
```sql
- id: INT
- name: VARCHAR (Emri paketës)
- slug: VARCHAR (URL slug)
- monthly_price: DECIMAL
- yearly_price: DECIMAL
- features: JSON (Lista e features)
- support_level: ENUM (email, priority, dedicated)
- trial_days: INT (Ditë prove)
- is_active: BOOLEAN
```

### `subscription` (përditësohet)
```sql
- plan_id: INT (Foreign Key → subscription_plans)
- billing_cycle: ENUM (monthly, yearly)
- discount_id: INT (Foreign Key → discounts)
- next_billing_date: DATETIME
```

### `discounts`
```sql
- code: VARCHAR (Kodi i zbritjes)
- discount_type: ENUM (percentage, fixed)
- discount_value: DECIMAL
- valid_from / valid_until: DATETIME
- applicable_plans: JSON
- max_uses: INT (-1 = unlimited)
```

### `payment_reminders`
```sql
- subscription_id: INT
- invoice_id: INT
- reminder_type: ENUM (3days_before, 1day_before, 1day_after, etc)
- scheduled_date: DATETIME
- status: ENUM (scheduled, sent, failed)
```

### `payment_delays`
```sql
- invoice_id: INT
- days_overdue: INT
- penalty_fee: DECIMAL
- first_overdue_date: DATE
- payment_plan_amount: DECIMAL
- payment_plan_installments: INT
```

---

## 🚀 Si të Përdorni

### 1. **Instalim i Bazës Të Dhanave**

Ekzekuto migrimin SQL:
```bash
mysql -u user -p database < sql/subscription_plans_migration.sql
```

Ose përmes phpMyAdmin drag & drop filen `subscription_plans_migration.sql`

### 2. **Krijo Pakete Standarde**

Hyr në: `/admin/subscriptions_billing.php`
- Kliko butonin "Krijo Pakete Standarde"
- Sistemi do të krijon tre pakete (Starter, Pro, Premium)

### 3. **Krijo Zbritje & Promocione**

```php
// Përmes dashboarboard-it:
1. Shko në tab-in "Zbritje"
2. Kliko "Krijo Zbritje të Re"
3. Plotëso të dhënat:
   - Kod: SUMMER2024
   - Emri: Zbritje Verore
   - Vlera: 20 (%)
   - Vlefshmëri: 01.06.2024 - 31.08.2024
4. Ruaj
```

### 4. **Konfiguro Automatizimlj Faturimi**

**Opsioni 1: Linux/Server (Recommended)**

Shto në crontab:
```bash
crontab -e
# Shto këtë linjë:
0 2 * * * php /home/user/noteria/cron/billing_cron.php
```

**Opsioni 2: Windows**

Përdor Task Scheduler:
```
Program: C:\PHP\php.exe
Arguments: C:\path\to\noteria\cron\billing_cron.php
Schedule: Çdo ditë në 02:00
```

**Opsioni 3: Thirje përmes HTTP**

Thrrje manualisht:
```
GET https://noteria.com/cron/billing_cron.php?key=YOUR_CRON_KEY
```

### 5. **Menaxhimi i Paketave**

```php
$plans = new SubscriptionPlan($pdo);

// Krijo paketë
$plans->createPlan([
    'name' => 'Pro',
    'slug' => 'pro',
    'monthly_price' => 29.99,
    'yearly_price' => 299.99,
    'features' => ['Feature 1', 'Feature 2'],
    'support_level' => 'priority',
    'trial_days' => 30
]);

// Merr të gjitha pakete
$allPlans = $plans->getActivePlans();

// Përditëso paketë
$plans->updatePlan($planId, [
    'monthly_price' => 34.99
]);
```

### 6. **Menaxhimi i Zbritjeve**

```php
$discounts = new DiscountManager($pdo);

// Krijo zbritje
$discounts->createDiscount([
    'code' => 'WELCOME10',
    'name' => 'Zbritje Mirëseardhje',
    'discount_type' => 'percentage',
    'discount_value' => 10,
    'valid_from' => '2024-01-01 00:00:00',
    'valid_until' => '2024-12-31 23:59:59'
]);

// Validimi i kodit
$result = $discounts->validateDiscount('WELCOME10', $planId, $months);
if ($result['valid']) {
    echo "Kodi është i vlefshëm!";
}

// Apliko zbritje në abonimin
$discounts->applyDiscountToSubscription($discountId, $subscriptionId, $zyraId);
```

### 7. **Faturim Automatik**

```php
$billing = new BillingAutomation($pdo);

// Gjenero faturë
$invoiceId = $billing->generateInvoice($subscriptionId, $planId);

// Kontrollo pagesat vonuese
$overdueCount = $billing->checkOverduePayments();

// Dërgo kujtesa të planifikuara
$count = $billing->sendScheduledReminders();

// Dërgo kujetsa për pagesa vonuese
$count = $billing->sendOverdueReminders();

// Kontrollo abonimet e afärta të skadimit
$expiring = $billing->checkExpiringSubscriptions(14); // 14 ditë përpara

// Krijo plan pagesash për vonesa
$billing->createPaymentPlan($delayId, 3); // 3 këste
```

---

## 🔧 API Endpoints

### Subscription Plans API
```
GET  /api/SubscriptionPlansController.php?action=list
GET  /api/SubscriptionPlansController.php?action=get&id=1
POST /api/SubscriptionPlansController.php?action=create
POST /api/SubscriptionPlansController.php?action=update
POST /api/SubscriptionPlansController.php?action=delete
POST /api/SubscriptionPlansController.php?action=create_defaults
```

### Discounts API
```
GET  /api/DiscountsController.php?action=list
GET  /api/DiscountsController.php?action=validate&code=SUMMER&plan_id=1
POST /api/DiscountsController.php?action=create
POST /api/DiscountsController.php?action=update
POST /api/DiscountsController.php?action=delete
```

### Billing API
```
POST /api/BillingController.php?action=generate_invoice
POST /api/BillingController.php?action=check_overdue
POST /api/BillingController.php?action=send_reminders
POST /api/BillingController.php?action=send_overdue_reminders
POST /api/BillingController.php?action=check_expiring
POST /api/BillingController.php?action=run_daily_tasks
```

---

## 📧 Konfigurimi i Email-it

Sistemi përdor PHPMailer për dërgimin e kujtesave. Konfiguro në `config.php`:

```php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your_email@gmail.com');
define('MAIL_PASSWORD', 'your_password');
define('MAIL_FROM', 'noreply@noteria.com');
define('MAIL_FROM_NAME', 'Noteria');
```

---

## ⚙️ Penalitete për Vonesa

- **Norma**: 0.5% për ditë von
- **Maksimumi**: 10% e shumës totale
- **Shembull**: 100€ von për 20 ditë = 10€ penalitet

---

## 📊 Dashboard Admin

Hyr në: `/admin/subscriptions_billing.php`

**Funksionalitete**:
- ✅ Shikesë të gjitha pakete
- ✅ Krijo/ndrysho/fshi pakete
- ✅ Menaxho zbritje
- ✅ Kontrollo statistika faturimi
- ✅ Ekzekuto detyra manuale
- ✅ Shikueshmëri i pagesa vonuese

---

## 🔐 Siguria

1. **CSRF Protection** - Të gjithë formet duhen:
   ```php
   <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
   ```

2. **Admin Only** - Të gjithë endpoints kontrollojnë:
   ```php
   if (!isset($_SESSION['admin_id'])) {
       exit('Unauthorized');
   }
   ```

3. **SQL Injection Protection** - Të gjitha queries përdorin prepared statements

4. **Cron Secret Key** - Cron job kontroller:
   ```php
   $CRON_SECRET_KEY = 'YOUR_SECRET_KEY_HERE';
   ```

---

## 🐛 Troubleshooting

**Problem**: Fatura nuk gjenerohen automatikisht
- Kontrolloni nëse `next_billing_date` është i saktë
- Verifikoni nëse cron job po ekzekutohet
- Shikesë logs në `cron/billing_cron.log`

**Problem**: Kujtesa nuk dërgohen
- Kontrolloni konfigurimin e SMTP-t
- Verifikoni emails në `payment_reminders` tabela
- Shikesë `error_log`

**Problem**: Zbritot nuk po aplikohen
- Verifikoni `valid_from` dhe `valid_until` datat
- Kontrolloni nëse kodi u aplikua në `subscription.discount_id`
- Verifikoni `applies_to` dhe `applicable_plans`

---

## 📈 Përditësimet në Ardhmen

- [ ] Braintree integration
- [ ] Recurring billing në Stripe
- [ ] SMS reminders
- [ ] Invoice PDF generation
- [ ] Payment installment plans UI
- [ ] Dunning management
- [ ] Revenue analytics

---

## 📞 Suport

Për pyetje ose probleme: support@noteria.com

---

**Versioni**: 1.0  
**Data e Ruajtjes**: 14.03.2026  
**Autori**: Noteria Development Team
