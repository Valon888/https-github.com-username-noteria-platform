# Implementation Guide: Adding Festa e Fitër Bajramit Message

## Summary
The date March 18 (Festa e Fitër Bajramit / Eid al-Fitr) is **already disabled** in your booking calendar. However, users see no explanation for why the date is grayed out. This guide shows how to add a professional informational message.

---

## OPTION 1: Add Info Banner (Simple, Recommended)

### What It Looks Like
A professional orange-themed notice appears above the calendar to inform users that March 18 is unavailable due to the holiday.

### Implementation

**File to Edit**: `d:\Laragon\www\noteria\dashboard.php`

**Find This Line** (approximately line 1219):
```html
</div>

<!-- Kalendari -->
<div id="kalendar-booking" style="margin-top:20px;background:#fff;padding:20px;...
```

**Insert This Code BEFORE the Calendar**:
```html
<!-- Festa e Fitër Bajramit Notice -->
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
```

### Testing
1. Go to the booking calendar
2. You should see the orange notice above the calendar
3. Navigate to March 2026
4. March 18 should be grayed out (disabled)
5. Other dates should be clickable

**Pros**: 
✓ Clear and visible  
✓ Professional appearance  
✓ No JavaScript needed  
✓ Easy to modify or remove  
✓ Works on all devices  

**Cons**: 
✗ Always visible (even when March isn't shown)  
✗ Takes up space

---

## OPTION 2: Enhanced Hover Tooltip (Minimal Code)

### What It Looks Like
When user hovers over the grayed-out March 18 date button, a browser tooltip appears explaining the closure.

### Implementation

**File to Edit**: `d:\Laragon\www\noteria\dashboard.php`

**Find This Section** (approximately line 1314-1380):
```javascript
// Kosovo public holidays
const kosovoHolidays = [
    '01-01', // Vit i Ri
    ...
];
```

**Find This Code** (approximately line 1365):
```javascript
html += '<td style="padding:5px;text-align:center;background:#fafbfc;">';
html += '<button type="button" class="calendar-day-btn" data-date="' + dateStr + '" style="' + btnStyle + '" ' + (isWeekend || isBooked || isBeforeToday || isHoliday ? 'disabled' : '') + '>' + currentDay + '</button>';
html += '</td>';
```

**Replace With**:
```javascript
html += '<td style="padding:5px;text-align:center;background:#fafbfc;">';

// Create tooltip text for disabled dates
let tooltipText = '';
if (isHoliday) {
    tooltipText = dateFormatted === '03-18' ? 'Festa e Fitër Bajramit (Pushim Zyrtar)' : 'Festë Zyrtare';
} else if (isWeekend) {
    tooltipText = 'Fundjavë - Zyrat noteriale janë të mbyllura';
} else if (isBeforeToday) {
    tooltipText = 'Datë e kaluar';
} else if (isBooked) {
    tooltipText = 'Të gjitha oraret janë të zëna';
}

html += '<button type="button" class="calendar-day-btn" data-date="' + dateStr + '" style="' + btnStyle + '" title="' + tooltipText + '" ' + (isWeekend || isBooked || isBeforeToday || isHoliday ? 'disabled' : '') + '>' + currentDay + '</button>';
html += '</td>';
```

### Testing
1. Hover over March 18, 2026
2. Browser tooltip should appear with text: "Festa e Fitër Bajramit (Pushim Zyrtar)"
3. Repeat for other disabled dates (weekends, past dates, etc.)

**Pros**: 
✓ Minimal code change  
✓ No UI clutter  
✓ Context-aware  
✓ Works for all disabled reasons  

**Cons**: 
✗ Only visible on hover (desktop)  
✗ Not visible on mobile  
✗ Browser tooltip styling limited  

---

## OPTION 3: Dynamic Alert Message (Responsive)

### What It Looks Like
When user clicks on a disabled holiday date, a more informative message appears instead of generic "No slots available."

### Implementation

**File to Edit**: `d:\Laragon\www\noteria\dashboard.php`

**Find This Function** (approximately line 1424-1428):
```javascript
function showTimeSlots(zyraId, date) {
    fetch('get_time_slots.php?zyra_id=' + encodeURIComponent(zyraId) + '&date=' + encodeURIComponent(date))
        .then(res => res.json())
        .then(data => {
            if (data.slots && data.slots.length > 0) {
                showSlotSelector(zyraId, date, data.slots);
            } else {
                alert('Nuk ka orare të lira për këtë datë.');
            }
        });
}
```

**Replace With**:
```javascript
function showTimeSlots(zyraId, date) {
    fetch('get_time_slots.php?zyra_id=' + encodeURIComponent(zyraId) + '&date=' + encodeURIComponent(date))
        .then(res => res.json())
        .then(data => {
            if (data.slots && data.slots.length > 0) {
                showSlotSelector(zyraId, date, data.slots);
            } else {
                // Check why no slots available
                const dateFormatted = date.substring(5, 10); // MM-DD format
                const kosovoHolidays = [
                    '01-01', '01-02', '02-17', '03-08', '03-18',
                    '04-09', '05-01', '05-09', '06-12', '09-28', '12-25'
                ];
                
                const holidayMap = {
                    '03-18': 'Festa e Fitër Bajramit',
                    '01-01': 'Viti i Ri',
                    '03-08': 'Dita e Grave',
                    // Add others as needed
                };
                
                if (kosovoHolidays.includes(dateFormatted)) {
                    const holidayName = holidayMap[dateFormatted] || 'Festë Zyrtare';
                    alert('🔴 DATË E MBYLLUR\n\n' + holidayName + 
                          '\n(Pushim Zyrtar në Kosovë)\n\n' +
                          'Ju lutemi zgjidhni një datë alternative.');
                } else {
                    alert('Nuk ka orare të lira për këtë datë.');
                }
            }
        });
}
```

### Testing
1. Open booking calendar
2. Click on March 18, 2026 (disabled date)
3. Alert should appear with "🔴 DATË E MBYLLUR" and "Festa e Fitër Bajramit"
4. Click on another disabled date (e.g., weekend) to see generic message

**Pros**: 
✓ Informative at moment of need  
✓ Context-aware  
✓ No extra UI clutter  

**Cons**: 
✗ Alert boxes are less elegant  
✗ JavaScript modification required  

---

## OPTION 4: Styled Info Box (Most Professional)

### What It Looks Like
When user clicks disabled holiday, a professional styled box appears below the calendar explaining the closure instead of a browser alert.

### Implementation (Two-Part)

#### Part 1: Add HTML Container

**File**: `d:\Laragon\www\noteria\dashboard.php`

**Find** (approximately line 1213):
```html
<div id="kalendar-booking" style="margin-top:20px;...">
```

**Add This Before It**:
```html
<div id="holiday-notice" style="display:none; background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); border-left: 5px solid #d32f2f; padding: 16px; border-radius: 8px; margin-top: 20px;">
    <h4 id="holiday-title" style="color: #c62828; margin-top: 0; margin-bottom: 8px;">🔴 Datë e Mbyllur</h4>
    <p id="holiday-text" style="margin: 0; color: #333; line-height: 1.6;"></p>
</div>

<div id="kalendar-booking" style="margin-top:20px;...">
```

#### Part 2: Modify JavaScript

**Find** (approximately line 1424-1428):
```javascript
function showTimeSlots(zyraId, date) {
    fetch('get_time_slots.php?zyra_id=' + encodeURIComponent(zyraId) + '&date=' + encodeURIComponent(date))
        .then(res => res.json())
        .then(data => {
            if (data.slots && data.slots.length > 0) {
                showSlotSelector(zyraId, date, data.slots);
            } else {
                alert('Nuk ka orare të lira për këtë datë.');
            }
        });
}
```

**Replace With**:
```javascript
function showTimeSlots(zyraId, date) {
    fetch('get_time_slots.php?zyra_id=' + encodeURIComponent(zyraId) + '&date=' + encodeURIComponent(date))
        .then(res => res.json())
        .then(data => {
            if (data.slots && data.slots.length > 0) {
                showSlotSelector(zyraId, date, data.slots);
                // Hide notice if previously shown
                document.getElementById('holiday-notice').style.display = 'none';
            } else {
                // Check if holiday
                const dateFormatted = date.substring(5, 10);
                const kosovoHolidays = [
                    '01-01', '01-02', '02-17', '03-08', '03-18',
                    '04-09', '05-01', '05-09', '06-12', '09-28', '12-25'
                ];
                
                if (kosovoHolidays.includes(dateFormatted)) {
                    const holidayTexts = {
                        '03-18': 'Festa e Fitër Bajramit (Pushim Zyrtar)\n\nZyrat noteriale janë të mbyllura më 18 mars. Ju lutemi zgjidhni një datë alternative. Shërbimi normal përfsuretsja më 19 mars.'
                    };
                    
                    document.getElementById('holiday-title').textContent = '🔴 Datë e Mbyllur';
                    document.getElementById('holiday-text').textContent = holidayTexts[dateFormatted] || 'Kjo datë është festë zyrtare. Ju lutemi zgjidhni një datë tjetër.';
                    document.getElementById('holiday-notice').style.display = 'block';
                } else {
                    alert('Nuk ka orare të lira për këtë datë.');
                }
            }
        });
}
```

### Testing
1. Click on March 18, 2026
2. Red info box should appear with "Festa e Fitër Bajramit" message
3. Click on a date with available slots - notice should hide

**Pros**: 
✓ Professional appearance  
✓ No alert dialogs  
✓ Styled to match site  
✓ Fully customizable  

**Cons**: 
✗ More complex JavaScript  
✗ Requires DOM manipulation  

---

## RECOMMENDED IMPLEMENTATION

**Best for this project**: **Option 1 (Info Banner)**

**Why**:
- ✓ Simple (just HTML, no JavaScript)
- ✓ Clear and prominent
- ✓ Matches existing site style (Ramadan notice)
- ✓ Professional appearance
- ✓ No maintenance/updates needed

**Secondary choice**: **Option 2 (Hover Tooltip)**
- Good for minimal visual impact
- Works well with existing styling
- Provides context on hover

---

## Testing Checklist

After implementing any option:

- [ ] Navigate to March 2026 in booking calendar
- [ ] Verify March 18 is grayed out (disabled)
- [ ] Verify other dates in March are clickable
- [ ] Check message displays (depending on option chosen)
- [ ] Test on mobile device (responsive)
- [ ] Verify message in Albanian is grammatically correct
- [ ] Test color contrast (accessibility)
- [ ] Clear browser cache (Ctrl+Shift+Delete)

---

## Additional Notes

### Holiday List is Already Complete
The date `03-18` is already in the hardcoded holiday list in your calendar:

```javascript
const kosovoHolidays = [
    ...
    '03-18', // Festa e Fitër Bajramit
    ...
];
```

So the date is **already disabled** - you just need to add an explanation!

### Consider Consolidating Holiday Lists
You have duplicate holiday lists in:
1. [dashboard.php](dashboard.php#L1314)
2. [assets/js/government-portal.js](assets/js/government-portal.js#L358)

Future improvement: Create a shared `constants.js` or fetch from backend API to maintain a single source of truth.

---

## Color Reference

| Component | Color | Hex |
|-----------|-------|-----|
| Banner Background (Start) | Warm Orange | #fff3e0 |
| Banner Background (End) | Light Orange | #ffe0b2 |
| Banner Border | Dark Orange | #f57c00 |
| Title Text | Very Dark Orange | #e65100 |
| Body Text | Dark Gray | #333 |
| Secondary Text | Medium Gray | #555 |

This matches the "Announcements" section already in your dashboard for consistency.

---

*Last Updated: March 10, 2026*
*For Noteria Project - Notary Booking System*
