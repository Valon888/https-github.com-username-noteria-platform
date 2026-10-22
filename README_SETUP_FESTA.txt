╔════════════════════════════════════════════════════════════════════════════╗
║          SETUP I SISTEMIT NOTERIA - FESTA E FITËR BAJRAMIT (18.03.2026)   ║
╚════════════════════════════════════════════════════════════════════════════╝

🎯 QËLLIMI
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ky setup përgatit sistemin Noteria për:
1. ✓ Markimin e datës 18 Mars 2026 si festë (Festa e Fitër Bajramit)
2. ✓ Përditësimin e orareve të punës për 19 Mars 2026 dhe tutje (08:00-16:00)
3. ✓ Shfaqjen e mesazhit informues në dashboard


📁 DOSJET QË KËRKOHEN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DOSJE TË PAQENA (tashmë ekzekutuar):
✓ assets/js/government-portal.js - Datë 03-18 shtuar në kosovoHolidays
✓ dashboard.php - Kalendari rikonfiguruar, mesazhi informues shtuar
✓ db/update_schedule_march_19_2026.sql - SQL script për vendosjen e orareve

DOSJE TË REJA:
📄 db/FULL_SETUP_PHPMYADMIN.sql - SETUP PLOTË (recommended)
📄 setup_database.php - Web interface (opsionale)
📄 set_schedule_march_19.php - Web interface vetëm për orarit
📄 PHPMYADMIN_SETUP_GUIDE_VISUAL.txt - Këtu jeni tani
📄 PHPMYADMIN_SETUP_GUIDE_SQ.txt - Udhëzim në shqip


🚀 OPSIONET E SETUP-IT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

OPSIONI 1: PHPMYADMIN (⭐ RECOMMENDED - Më i Sigurt)
════════════════════════════════════════════════════

✅ Përparësi:
- Më i sigurt dhe më i besueshëm
- Nuk varet nga konfigurimi i PHP-it
- Puon në të gjithë sistemet
- Kontrolli i plotë mbi SQL-in

❌ Disavantazh:
- Duhet më shumë veprimesh manuale
- Duhet të kopjosh/pasteosh kodin

INSTRUKSIONET:
1. Hap: http://localhost/phpmyadmin
2. Krijo bazën "noteria" (nëse nuk ekziston)
3. Kliko "Import" ose shko në skedën "SQL"
4. Kopjo përmbajtjen nga:
   📁 D:\Laragon\www\noteria\db\FULL_SETUP_PHPMYADMIN.sql
5. Pastezo në phpMyAdmin dhe kliko "Execute"
6. Gata! 🎉

👉 DETALET: Shiko fajlin PHPMYADMIN_SETUP_GUIDE_VISUAL.txt


OPSIONI 2: SETUP_DATABASE.PHP (Web Interface)
═════════════════════════════════════════════

✅ Përparësi:
- Automatik dhe më i shpejtë
- Nuk duhet copjim/pastim i kodit
- Shfaqet vizualisht e qartë

⚠️ Kërkesa:
- MySQL connection duhet të tunë i konfiguruar
- PHP duhet të ketë akses në database

INSTRUKSIONET:
1. Aço në shfletues:
   http://localhost/noteria/setup_database.php
2. Kliko "📊 Ekzekuto Skemën e Databazës"
3. Kliko "🕐 Vendos Orarit Tani"
4. Gata! 🎉


OPSIONI 3: SETUP + VETEM ORARET (Nëse Schemas Ekzistojnë)
═════════════════════════════════════════════════════════

Nëse databaza dhe tabelat ekzistojnë tashmë:

1. Aço:
   http://localhost/noteria/set_schedule_march_19.php
2. Kliko "✓ Vendos Orarit Tani"
3. Gata! 🎉


✨ NDRYSHIMET QË JANË BËRË TASHMË
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. ✅ assets/js/government-portal.js
   - Shtua: '03-18', // Festa e Fitër Bajramit në kosovoHolidays array
   - Efekti: Portal nuk lejon booking në 18 Mars

2. ✅ dashboard.php
   - Shtua: Holiday validation në createBookingCalendar()
   - Shtua: Mesazh informues profesional sipër kalendarit
   - Efekti: Kalendari çaktivizon 18 Mars dhe shfaq mesazh


❌ NUK ËSHTË BËRË ENDE (Duhet Setup):
────────────────────────────────────

1. ❌ Databasa "noteria" - DUHET KRIJONI
2. ❌ Tabelat SQL - DUHET KRIJONI
3. ❌ Orarit për Mars 19 - DUHET VENDOSUR
   
   👉 ZGJIDHJA: Zgjidh një nga opsionet e mësipërm


🔐 KONTROLLI I SIGURISË
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Këto dosje përmbajnë SQL të vërtet - Verifiko përpara ekzekutimit:

✓ db/FULL_SETUP_PHPMYADMIN.sql
  - Krijo tabela me indekse të plota
  - Vendos orarir për Mars 19
  - Përfshin validacione dhe constraints

✓ db/update_schedule_march_19_2026.sql
  - Vetëm për vendosjen e orareve
  - Deaktivizo orarit e vjetër
  - Krijo të rinj për punonjësit aktivë


📋 CHECKLIST PËRPARA STARTUP-IT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

┌─────────────────────────────────────────────────────────────────────────────┐
│ PRE-SETUP CHECKS                                                            │
├─────────────────────────────────────────────────────────────────────────────┤
│ ☐ Laragon është duke punuar (ose Apache/MySQL)                             │
│ ☐ phpMyAdmin është i aksesueshëm: http://localhost/phpmyadmin             │
│ ☐ Databasa "noteria" ekziston ose mund të krijohet                        │
│ ☐ MySQL user "root" ka akses pa password (standardi)                      │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│ SETUP STEPS (Zgjedh një opsion)                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ ☐ OPSIONI 1: phpMyAdmin (Recommended)                                      │
│     └─ Shto SQL nga FULL_SETUP_PHPMYADMIN.sql                              │
│                                                                             │
│ ☐ OPSIONI 2: Web Interface                                                 │
│     ├─ Hap setup_database.php                                              │
│     ├─ Kliko "Ekzekuto Skemën"                                             │
│     └─ Kliko "Vendos Orarit"                                               │
│                                                                             │
│ ☐ OPSIONI 3: SQL Direct (Nëse schemas ekzistojnë)                          │
│     └─ Shto SQL nga update_schedule_march_19_2026.sql                      │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│ POST-SETUP VERIFICATION                                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│ ☐ Tabela "oraret" ekziston në phpMyAdmin                                   │
│ ☐ Rreshta me data_fillimit = 2026-03-19 ekzistojnë                         │
│ ☐ Orarit janë 08:00 - 16:00 (Hënë-Premte)                                  │
│ ☐ Shtunë dhe Diele janë NULL (pushim)                                      │
│ ☐ Dashboard shfaq mesazhin e Festa e Fitër Bajramit                        │
│ ☐ Data 18 Mars është çaktivizuar në kalendar                              │
│ ☐ Data 19 Mars është aktive në kalendar                                    │
└─────────────────────────────────────────────────────────────────────────────┘


🎯 HAPI I RADHËS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. 📖 Shiko udhëzimin e plotë:
   👉 PHPMYADMIN_SETUP_GUIDE_VISUAL.txt

2. 🔧 Zgjidh e ekzekuto një nga opsionet e setup-it

3. ✅ Verifikua që të gjitha çeçklist-it të jenë të plotsuara

4. 🚀 Joyj në http://localhost/noteria/dashboard.php


🆘 NËSE KA PROBLEME
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PROBLEMI: "Table 'noteria.oraret' doesn't exist"
→ ZGJIDHJA: Ekzekuto sql-in e FULL_SETUP_PHPMYADMIN.sql fillimisht

PROBLEMI: "Access denied for user 'root'@'localhost'"
→ ZGJIDHJA: Kontrollua MySQL password në setup_database.php (line 23)

PROBLEMI: "0 rows affected" kur vendos orarit
→ ZGJIDHJA: Shto punonjës test në tabelën "punonjesit" (shiko PHPMYADMIN_SETUP_GUIDE_VISUAL.txt)

PROBLEMI: Messazhi për Festa nuk shfaqet
→ ZGJIDHJA: Kontrollua se dashboard.php ka mesazhin (duhet të jetë i shtuar)


📞 SUPORTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Dosjet e tjera të dobishme:
- assets/js/government-portal.js
- dashboard.php
- db/noteria_staff_schema.sql
- PHPMYADMIN_SETUP_GUIDE_VISUAL.txt ← Shiko këtë për detalje të plota


╔════════════════════════════════════════════════════════════════════════════╗
║                         🎉 GATI PËR STARTUP! 🎉                           ║
╚════════════════════════════════════════════════════════════════════════════╝
