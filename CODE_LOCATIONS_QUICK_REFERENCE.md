# Quick Reference: Code Locations for Messages & Alerts

## 1. EXISTING ERROR MESSAGE DISPLAY (in booking form context)

### Location: [dashboard.php](dashboard.php#L1111)
**When**: Office validation fails
```php
echo "<div class='error'><strong>Përdoruesi nuk është i lidhur me asnjë zyrë. Kontaktoni administratorin!</strong></div>";
```

### Location: [dashboard.php](dashboard.php#L1177)  
**When**: Office not found
```php
echo "<div class='error'><strong>Zyra nuk u gjet! Kontaktoni administratorin.</strong></div>";
```

---

## 2. SEASONAL/SPECIAL ANNOUNCEMENTS (Ramadan Example)

### Location: [dashboard.php](dashboard.php#L1061-1070)
**Pattern for adding special notices:**

```html
<div class="zyra-section" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
                                  border-left: 5px solid #1976d2; 
                                  margin-bottom: 20px;">
    <h2 style="color: #1565c0; margin-top: 0;">📅 Njoftim për muajin e Ramazanit</h2>
    <div style="color: #333; line-height: 1.6;">
        <p><strong>Orari i punës është i shkurtuar deri në ora 15:00 gjatë muajit të Ramazanit.</strong></p>
        <p>Kjo masë merret për të mbrojtur cilësinë e shërbimit dhe mirëqenien e punonjësve.</p>
        <p style="color: #555; font-size: 0.95em; margin-top: 12px;">
            Ju lutemi planifikoni terminet tuaj në përputhje me orarin e ri të punës. Faleminderit për mirëkuptimin!
        </p>
    </div>
</div>
```

---

## 3. DATABASE-DRIVEN NEWS ANNOUNCEMENTS

### Location: [dashboard.php](dashboard.php#L1084-1105)
**Source**: `news` table with multilingual support (`title_sq`, `content_sq`, etc.)

```php
<div class="zyra-section" style="background: linear-gradient(135deg, #fff5e6 0%, #ffe6cc 100%); 
                                  border-left: 5px solid #ff9800; 
                                  margin-bottom: 20px;">
    <h2 style="color: #d84315; margin-top: 0;">📢 <?php echo htmlspecialchars($title); ?></h2>
    <div style="color: #333; line-height: 1.6;">
        <?php echo nl2br(htmlspecialchars($content)); ?>
    </div>
    <p style="color: #888; font-size: 0.9em; margin-top: 10px;">
        <?php echo date('d.m.Y H:i', strtotime($announcement['date_created'])); ?>
    </p>
</div>
```

---

## 4. CALENDAR JAVASCRIPT ALERTS

### Location: [dashboard.php](dashboard.php#L1424-1428)
**When**: User clicks on date with no available time slots

```javascript
function showTimeSlots(zyraId, date) {
    fetch('get_time_slots.php?zyra_id=' + encodeURIComponent(zyraId) + '&date=' + encodeURIComponent(date))
        .then(res => res.json())
        .then(data => {
            if (data.slots && data.slots.length > 0) {
                showSlotSelector(zyraId, date, data.slots);
            } else {
                alert('Nuk ka orare të lira për këtë datë.');  // ← GENERIC MESSAGE
            }
        });
}
```

---

## 5. FORM VALIDATION ALERTS

### Location: [dashboard.php](dashboard.php#L1525-1528)
**When**: User tries to submit form with missing fields

```javascript
bookingForm.addEventListener('submit', function(e) {
    if (!document.getElementById('form_date').value || !document.getElementById('form_time').value) {
        e.preventDefault();
        alert('Ju lutemi zgjidhni një datë dhe orë!');
    } else if (!document.getElementById('calendar_service').value) {
        e.preventDefault();
        alert('Ju lutemi zgjidhni një shërbim!');
    }
});
```

---

## 6. CSS STYLING FOR ALL MESSAGE TYPES

### Location: [dashboard.php](dashboard.php#L322-345)

```css
/* ERROR - Red background with dark red text */
.error {
    color: #d32f2f;
    background: #ffeaea;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 18px;
    font-size: 1rem;
    text-align: center;
}

/* SUCCESS - Green background with dark green text */
.success {
    color: #388e3c;
    background: #eafaf1;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 18px;
    font-size: 1rem;
    text-align: center;
}

/* INFO - Light blue background with dark blue text */
.info {
    color: #184fa3;
    background: #e2eafc;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 18px;
    font-size: 1rem;
    text-align: center;
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .error, .success, .info {
        line-height: 1.6;
    }
}
```

---

## 7. CALENDAR DATE DISABLING LOGIC

### Location: [dashboard.php](dashboard.php#L1314-1380)

#### Holiday Configuration (ALREADY INCLUDES 03-18)
```javascript
const kosovoHolidays = [
    '01-01', // Vit i Ri
    '01-02', // Dita e Përzierjes së Kombeve
    '02-17', // Dita e Pavarësisë së Kosovës
    '03-08', // Dita e Grave
    '03-18', // Festa e Fitër Bajramit  ← TARGET DATE
    '04-09', // Dita e Përkujtimit të Masakrës së Reçakut
    '05-01', // Dita e Punëtorëve
    '05-09', // Dita e Evropës
    '06-12', // Dita e Çlirimit
    '09-28', // Dita e Kushtetutës
    '12-25'  // Krishtlindje
];
```

#### Disabled Date Styling
```javascript
if (isWeekend || isBooked || isBeforeToday || isHoliday) {
    btnStyle = 'padding:12px;width:100%;border:2px solid #ccc;background:#f0f0f0;color:#999;border-radius:8px;cursor:not-allowed;font-weight:600;font-size:1rem;';
}

html += '<button ... style="' + btnStyle + '" ' + (isWeekend || isBooked || isBeforeToday || isHoliday ? 'disabled' : '') + '>' + currentDay + '</button>';
```

---

## 8. WHERE TO ADD FESTA E FITËR BAJRAMIT MESSAGE

### BEST LOCATION: Before Calendar Block

**File**: [dashboard.php](dashboard.php#L1183-1214)

Insert new `.zyra-section` div **AFTER** the price list link and **BEFORE** the `<div id="kalendar-booking">`:

```html
<!-- SUGGESTED INSERTION POINT (after line 1219) -->

<div class="zyra-section" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); 
                                  border-left: 5px solid #f57c00; 
                                  margin-bottom: 20px;
                                  padding: 16px;
                                  border-radius: 8px;">
    <h3 style="color: #e65100; margin-top: 0; margin-bottom: 12px;">
        📌 Festa e Fitër Bajramit
    </h3>
    <p style="color: #333; margin: 0; line-height: 1.6;">
        <strong>18 Mars 2026</strong> - Zyrat noteriale janë të mbyllura për festën zyrtare. 
        Ju lutemi zgjidhni një datë alternative për terminin tuaj.
    </p>
    <p style="color: #555; font-size: 0.95em; margin-top: 8px; margin-bottom: 0;">
        Punimet normal do të vazhdojnë më datë <strong>19 Mars</strong>.
    </p>
</div>

<!-- Calendar rendering starts below -->
<div id="kalendar-booking" style="margin-top:20px;background:#fff;padding:20px;..."></div>
```

---

## 9. ALTERNATIVE: Improve Backend Validation

### File: [get_time_slots.php](get_time_slots.php)
**Currently**: Only checks for weekends (line 23-26)

```php
// Check if it's weekday
$dayOfWeek = date('N', strtotime($date));
if ($dayOfWeek >= 6) {
    echo json_encode(['slots' => []]);
    exit;
}
```

**SHOULD ADD**: Holiday validation
```php
// Define holidays
$kosovoHolidays = [
    '01-01', '01-02', '02-17', '03-08', '03-18',
    '04-09', '05-01', '05-09', '06-12', '09-28', '12-25'
];

// Check if date is holiday
$dateFormatted = date('m-d', strtotime($date));
if (in_array($dateFormatted, $kosovoHolidays)) {
    echo json_encode(['slots' => [], 'reason' => 'holiday']);
    exit;
}
```

---

## 10. DUPLICATE HOLIDAY LISTS (Consolidation Opportunity)

### Location 1: [dashboard.php](dashboard.php#L1314)
```javascript
const kosovoHolidays = [ /* 11 holidays */ ];
```

### Location 2: [assets/js/government-portal.js](assets/js/government-portal.js#L358)
```javascript
const kosovoHolidays = [ /* identical list */ ];
```

**Problem**: Duplicate data in two JavaScript files  
**Solution**: Create shared `constants.js` or fetch from backend API

---

## Color Reference for Festa Message

| Element | Color | Use |
|---------|-------|-----|
| Background | #fff3e0 → #ffe0b2 | Warm orange gradient |
| Border | #f57c00 | Orange accent |
| Title | #e65100 | Dark orange |
| Text | #333 | Dark gray |
| Secondary | #555 | Medium gray |

This matches the "Announcements" section pattern already used in dashboard.
