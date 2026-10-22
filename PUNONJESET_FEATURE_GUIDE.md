# Noteria - Disponueshmëria e Punonjësve (Employee Availability Feature)

## 📋 Përshkrim i Funksionalitetit

Ky feature zgjeron sistemin e rezervimit për të treguar punonjësit e lirë të zyrës kur një klient zgjedh datën dhe orën.

### Çfarë bëhet tani:
1. ✅ Kur klienti zgjedh zyrën, datën dhe orën
2. ✅ Sistemi shfaq listën e punonjësve aktiv të asaj zyre
3. ✅ Filtron punonjësit që kanë rezervime të tjera në atë orë
4. ✅ Klienti mund të zgjedhë cilin punonjës dëshiron të e trajtojë lëndën e tij
5. ✅ Punonjësi i zgjedhur ruhet me rezervimin

---

## 🔧 Komponenta Të Implementuara

### 1. **API Endpoint** - `/api/get_available_employees.php`
- **Qëllim:** Fetching punonjësat e lirë për një zyrë në një kohë të caktuar
- **Parametrat:**
  - `zyra_id` - ID e zyrës (required)
  - `date` - Data në format YYYY-MM-DD (required)
  - `time` - Ora në format HH:MM (required)
- **Përgjigje:**
  ```json
  {
    "success": true,
    "count": 3,
    "employees": [
      {
        "id": 1,
        "emri": "Avni",
        "mbiemri": "Ramadani",
        "email": "avni.ramadani@gmail.com",
        "telefoni": "+38345555555",
        "pozita": "Noter"
      }
    ]
  }
  ```

### 2. **UI Components** - `/reservation.php`
- Sekcioni "Punonjësit e Lirë" shfaqet pasi klienti zgjedh zyrën, datën dhe orën
- Kortela të interaktive për çdo punonjës me:
  - Emrin dhe mbiemrin
  - Pozitën
  - Email-in
  - Numrin e telefonit
- Punonjësi i zgjedhur nënvizet me kufi jeshil dhe shenjë ✓

### 3. **Database Migration** - `/migration_add_punonjesi_id.sql`
Shton kolonën `punonjesi_id` në tabelën `reservations`:
```sql
ALTER TABLE `reservations` ADD COLUMN `punonjesi_id` INT(11) DEFAULT NULL;
```

---

## 🚀 Si Ta Përdorni

### Për Administratorin:
1. **Ekzekutoni migration-in** (nëse nuk e keni bërë tashmë):
   ```
   1. Hapni phpMyAdmin ose klient tjetër SQL
   2. Shikoni databazën 'Noteria'
   3. Kopjeni dhe ekzekutoni SQL nga migration_add_punonjesi_id.sql
   ```

2. **Sigurohuni** që në tabelën `punetoret` të keni punonjës me:
   - `zyra_id` të saktë
   - `active = 1`

### Për Klientin:
1. Shkoni në faqen e rezervimit (/reservation.php)
2. Zgjidhni shërbimin noterial
3. **Zgjidhni zyrën** → shfaqet lista e punonjësve
4. **Zgjidhni datën** → sistemi filtroi punonjësit
5. **Zgjidhni orën** → shfaqen vetëm ata që janë të lirë
6. **Klikoni punonjësin** e dëshiruar (ato me bordurë të gjelbër)
7. Plotësoni rest të formës dhe rezervoni

---

## 📊 Diagrama e Rrjedhës

```
Klienti Hap Faqen e Rezervimit
         ↓
    Zgjedh Zyrën
         ↓
    Zgjedh Datën
         ↓
    Zgjedh Orën
         ↓
    API Merr Punonjësit → Databaza
         ↓
    Shfaq Lista e Punonjësve të Lirë
         ↓
    Klienti Zgjedh Punonjës
         ↓
    Punonjësi Ruhet me Rezervimin
         ↓
    Klienti Vazhdon me Pagesën
```

---

## 💾 Tabela të Përdorura

| Tabela | Kolona Relevante |
|--------|------------------|
| `punetoret` | id, zyra_id, emri, mbiemri, email, telefoni, pozita, active |
| `reservations` | id, user_id, zyra_id, **punonjesi_id** (i ri), date, time |
| `zyrat` | id, emri, qyteti |

---

## ⚙️ Konfigurimi

Nuk ka konfigurimin shtesë të kërkuar. Sistemi:
- Automatikisht homon API kur ndryshojnë fushat
- Valikon se punonjësi i zgjedhur ekziston dhe është aktiv
- Ruan ID-në e punonjësit nëse zgjidhet, ose NULL nëse nuk zgjidhet

---

## 🐛 Troubleshooting

### Punonjësit nuk shfaqen
- Verifiko se `api/get_available_employees.php` ekziston
- Kontrozo drejtpërdrejtë API: `/api/get_available_employees.php?zyra_id=15&date=2026-03-15&time=09:00`
- Sigurohu se punonjësit kanë `active = 1` në databazë

### Kolona `punonjesi_id` nuk ekziston
- Ekzekuto migration-in: `migration_add_punonjesi_id.sql`
- Shiko database logs për errore

### Punonjësi nuk ruhet
- Verifiko se forma përmban input `<input type="hidden" name="punonjesi_id">`
- Kontrozo server logs në `/error.log`

---

## 📝 Shënime Zhvilluesi

- Sistemi përfshin validim në backend për siguri
- Gjendja e punonjësit nuk merret parasysh (mund të shtohet në të ardhmen)
- Koha e punës (08:00-15:00 ose 08:00-16:00) filtrohet në frontend
- API kthen 200 sukses edhe nëse nuk ka punonjës (ose shtrime për të ardhmen)

---

## 🔮 Përmirësime të Mundshme në të Ardhmen

1. Shtimi i orarit të punës për punonjësit (schedule management)
2. Preferenca të punonjësve (cili lloj shërbimi trajton secili)
3. Raportim për ngarkesën e punonjësve (sa rezervime më ditë)
4. Sistem kalimi automatik i punonjësve kur kanë rezervime
