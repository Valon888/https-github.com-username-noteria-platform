# 🎯 Sistemi i Reklamave - Dokumentacioni

Ky dokument përshkruan si të përdorni sistemin e ri të reklamave për Noteria.

## 📋 Përmbajtja
1. [Instalimi](#instalimi)
2. [Menaxhimi i Reklamave](#menaxhimi-i-reklamave)
3. [Shfaqja e Reklamave](#shfaqja-e-reklamave)
4. [Analytics & Statistika](#analytics--statistika)

---

## ⚙️ Instalimi

### Hapi 1: Krijoni Tabelat e Database

Vizitoni këtë link në shfletuesin tuaj:
```
http://localhost/noteria/setup_ads_database.php
```

Kjo do të krijoni automatikisht këto tabela:
- ✅ `advertisers` - Biznesat e reklamuesve
- ✅ `advertisements` - Reklamat aktuale
- ✅ `ad_placements` - Vendndodhja e shfaqjes
- ✅ `ad_impressions` - Statistika të shikimeve

### Hapi 2: Aksesohet Admin Panel

Hyni në admin panel për menaxhimin e reklamave:
```
http://localhost/noteria/admin_ads.php
```

**Kërkesat:**
- Duhet të jeni i logyrë si admin
- Duhet të keni akses në databazën Noteria

---

## 🏢 Menaxhimi i Reklamave

### Shtim Biznesi Reklamues të Ri

1. Hyni në `admin_ads.php`
2. Klikoni në tab-in "🏢 Bizneset e Reklamuesve"
3. Plotësoni formën:
   - **Emri i Biznesit** - p.sh. "Banka e Kosovës"
   - **Email i Kontaktit** - Për komunikim
   - **Telefoni** (opsional) - Nr. i telefonit
   - **Faqja e Uebit** (opsional) - Website-i i biznesit

4. Klikoni "➕ Shto Biznesin"

### Shtim Reklame të Re

1. Hyni në `admin_ads.php`
2. Klikoni në tab-in "➕ Shtim Reklame"
3. Plotësoni këto fusha:

| Fusha | Përshkrimi | Shembull |
|-------|-----------|---------|
| **Biznesi Reklamues** * | Zgjidhni biznesin | Banka e Kosovës |
| **Titulli** * | Titulli i reklamës | "Shërbimet Bankare të Sigurta" |
| **Përshkrimi** | Përshkrimi i detajuar | Max 500 karaktere |
| **URL-i i Imazhit** | Link i imazhit | https://example.com/img.jpg |
| **URL-i i Lidhjes** * | Ku të shkoj me klik | https://bank.com |
| **Tipi i Reklamës** | Kartë / Banner / Modal | Card (parazgjedhje) |
| **Placement** | Ku të shfaqet | Dashboard Sidebar |
| **Target Role** | Për cilat role | Të gjithë |
| **Data Fillimi** * | Kur fillon shfaqja | 2026-03-05 |
| **Data Përfundimi** | Kur përfundon | (opsional) |

4. Klikoni "✅ Shtimi i Reklamës"

### Redaktim Reklame

1. Hyni në "📋 Lista e Reklamave"
2. Klikoni "Redakto" në reklamin që dëshironi të ndryshoni
3. Bëni ndryshimet e dëshiruar
4. Klikoni "✅ Ruaje Ndryshimet"

### Fshirja e Reklame

1. Hyni në "📋 Lista e Reklamave"
2. Klikoni "Fshi" në reklamin e dëshiruar
3. Potresoi fshirjen

---

## 👁️ Shfaqja e Reklamave

### Përditësimi i Dashboard.php

Në `dashboard.php`, reklamat e partnerëve shfaqen në dy vende:

#### 1. **Dashboard Sidebar** (Ana e djathtë)
```php
<?php
$sidebar_ads = getAdsForPlacement($pdo, 'dashboard_sidebar', $_SESSION['roli'] ?? 'all', 2);
if (!empty($sidebar_ads)):
    // Shfaq reklamat
endif;
?>
```

#### 2. **Dashboard Main** (Seksion kryesor)
```php
<?php
echo getAdCSS(); // Ngarko CSS për reklamat
?>
```

### Faqja e Marketplace

Vizitoni marketplace-in për të parë të gjitha reklamat:
```
http://localhost/noteria/marketplace.php
```

Përdoruesit mund të:
- ✅ Filtrojnë reklamat sipas tipit
- ✅ Klikojnë për më shumë informacione
- ✅ Shikojnë statistika të reklamave

---

## 📊 Analytics & Statistika

### Statistika të Disponueshme

Sistemi automatikisht regjistron:

| Statistikë | Përshkrimi | Vendndodhja |
|-----------|-----------|-----------|
| **Total Impressions** | Numri i herëve që reklama shikohet | Tabela: ad_impressions |
| **Total Clicks** | Numri i click-ëve në reklam | Tabela: advertisements |
| **Click-Through Rate** | % e këlikuesve |Kalkulohet: clicks/impressions |
| **User IP & Agent** | Informacioni i pajisjeve | Tabela: ad_impressions |

### Shikimi i Statistikave

1. Hyni në `admin_ads.php` > "📋 Lista e Reklamave"
2. Kolonat shfaqojnë:
   - 👁 **Impressions** - Shikimet
   - 🔗 **Clicks** - Click-et
   - **Statusi** - Aktive/Paused/Draft
   - **Dako Fillimi/Përfundimi** - Periudha e shfaqjes

---

## 🔧 Funksionet e Disponueshme

### `getAdsForPlacement($pdo, $placement, $role, $limit)`
Merr reklamat për një vendndodhje specifike.

**Parametra:**
- `$pdo` - Koneksioni i PDO
- `$placement` - Vendndodhja (dashboard_sidebar, marketplace, etj)
- `$role` - Roli i përdoruesit (all, user, noter, admin)
- `$limit` - Numri maksimal i reklamave

**Shembull:**
```php
$ads = getAdsForPlacement($pdo, 'dashboard_sidebar', 'all', 3);
```

### `recordAdImpression($pdo, $ad_id, $placement, $user_id)`
Regjistron një shkim të reklamës.

**Shembull:**
```php
recordAdImpression($pdo, 123, 'dashboard_sidebar', $_SESSION['user_id']);
```

### `recordAdClick($pdo, $ad_id, $impression_id)`
Regjistron një klik në reklam.

**Shembull:**
```php
recordAdClick($pdo, 123);
```

---

## 🚀 Shembull i Plotë

### Shtim Reklame në Faqe Custom

```html
<?php
require_once 'confidb.php';
require_once 'ad_helper.php';

$pdo = new PDO($dsn, $dbUser, $dbPass, $options);
$ads = getAdsForPlacement($pdo, 'my_custom_location', 'all', 5);

foreach ($ads as $ad):
    recordAdImpression($pdo, $ad['id'], 'my_custom_location');
?>
    <div class="ad-card">
        <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="">
        <h3><?php echo htmlspecialchars($ad['title']); ?></h3>
        <p><?php echo htmlspecialchars($ad['description']); ?></p>
        <a href="<?php echo htmlspecialchars($ad['cta_url']); ?>" 
           onclick="recordAdClick(<?php echo $ad['id']; ?>)">
            Më shumë info
        </a>
    </div>
<?php endforeach; ?>
```

---

## ⚡ Direktat që mund të modifikoni

### Statuset e Reklamave
```php
// Në ad_status enum (advertisements table)
'active'   // Aktive
'paused'   // E ndërprerë
'draft'    // Broshurë
```

### Llojet e Reklamave
```php
// Në ad_type enum (advertisements table)
'card'     // Kartë
'banner'   // Banner
'modal'    // Modal
```

### Vendndodhjet
```php
// Në placement_location
'dashboard_sidebar'    // Dashboard anë e djathtë
'dashboard_main'       // Dashboard seksion kryesor
'reservation_page'     // Faqja e rezervimit
'marketplace'          // Marketplace
```

---

## 🆘 Troubleshooting

### Problema: "Tabela nuk ekziston"
**Zgjidhja:** Ekzekutoni `setup_ads_database.php` përsëri

### Problema: "Admin akses u refuzua"
**Zgjidhja:** Kontrolloni se `$_SESSION['roli']` = 'admin'

### Problema: "Reklamat nuk shfaqen"
**Zgjidhja:** 
1. Kontrolloni statusin të jetë 'active'
2. Kontrolloni datatë fillimi dhe përfundimi
3. Kontrolloni rollin e targetimit

---

## 📞 Shënimet

- Reklamat aktive shfaqen automatikisht
- Të dhënat hyshen në `ad_impressions` kur shfaqet reklama
- Click-et numërohen automatikisht në `advertisements`
- Sistemat filtra sipas datës dhe rolit automatikisht

---

## 📝 Lidhje të Shpejta

- [Admin Panel](admin_ads.php)
- [Setup Database](setup_ads_database.php)
- [Marketplace](marketplace.php)
- [Dashboard](dashboard.php)

---

**Versioni:** 1.0  
**Data e Azhurnimit:** Marsi 2026  
**Krimuesi:** Noteria Development Team
