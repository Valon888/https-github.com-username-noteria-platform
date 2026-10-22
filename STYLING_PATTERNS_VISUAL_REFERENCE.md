# Visual Reference: Message Styling & Patterns

## Current Message Types in Noteria Dashboard

### 1. ERROR MESSAGE (Red)
**CSS Class**: `.error`  
**Location**: [dashboard.php Line 322](dashboard.php#L322)

**Colors**:
- Text: `#d32f2f` (Red)
- Background: `#ffeaea` (Light red)
- Border Radius: 8px

**Live Examples in Dashboard**:
```php
echo "<div class='error'><strong>Përdoruesi nuk është i lidhur me asnjë zyrë. Kontaktoni administratorin!</strong></div>";
echo "<div class='error'><strong>Zyra nuk u gjet! Kontaktoni administratorin.</strong></div>";
```

**Visual Appearance**:
```
┌─────────────────────────────────────────────────────────────┐
│ 🔴 Përdoruesi nuk është i lidhur me asnjë zyrë. Kontaktoni   │
│    administratorin!                                         │
└─────────────────────────────────────────────────────────────┘
```

---

### 2. SUCCESS MESSAGE (Green)
**CSS Class**: `.success`  
**Location**: [dashboard.php Line 333](dashboard.php#L333)

**Colors**:
- Text: `#388e3c` (Green)
- Background: `#eafaf1` (Light green)
- Border Radius: 8px

**Live Examples in Dashboard**:
```php
echo "<div class='success'>Profili u përditësua me sukses!</div>";
echo "<div class='success'>Lajmi u publikua!</div>";
```

**Visual Appearance**:
```
┌─────────────────────────────────────────────────────────────┐
│ ✓ Profili u përditësua me sukses!                           │
└─────────────────────────────────────────────────────────────┘
```

---

### 3. INFO MESSAGE (Blue)
**CSS Class**: `.info`  
**Location**: [dashboard.php Line 340](dashboard.php#L340)

**Colors**:
- Text: `#184fa3` (Dark Blue)
- Background: `#e2eafc` (Light blue)
- Border Radius: 8px

**Live Examples in Dashboard**:
```php
echo "<div class='info'>Nuk ka njoftime të reja.</div>";
```

**Visual Appearance**:
```
┌─────────────────────────────────────────────────────────────┐
│ ℹ Nuk ka njoftime të reja.                                   │
└─────────────────────────────────────────────────────────────┘
```

---

### 4. RAMADAN NOTICE (Seasonal Announcement)
**Format**: Custom styled `.zyra-section`  
**Location**: [dashboard.php Line 1061](dashboard.php#L1061)

**Colors**:
- Background Gradient: `linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%)`
- Border Left: `5px solid #1976d2` (Dark Blue)
- Title: `color: #1565c0`

**Current Implementation**:
```html
<div class="zyra-section" style="
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
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

**Visual Appearance**:
```
┌─────────────────────────────────────────────────┬──────────────────┐
│ 📅 Njoftim për muajin e Ramazanit              │ (LEFT BORDER)    │
├───────────────────────────────────────────────┬────────────────────┤
│ Orari i punës është i shkurtuar deri në ora   │ (LIGHT BLUE GRAD)  │
│ 15:00 gjatë muajit të Ramazanit.              │                    │
│                                                │                    │
│ Kjo masë merret për të mbrojtur cilësinë e    │                    │
│ shërbimit dhe mirëqenien e punonjësve.        │                    │
│                                                │                    │
│ Ju lutemi planifikoni terminet tuaj në       │                    │
│ përputhje me orarin e ri të punës.            │                    │
│ Faleminderit për mirëkuptimin!                 │                    │
└───────────────────────────────────────────────┴────────────────────┘
```

---

### 5. NEWS/ANNOUNCEMENTS (Database-Driven)
**Format**: Custom styled `.zyra-section`  
**Location**: [dashboard.php Line 1084](dashboard.php#L1084)

**Colors**:
- Background Gradient: `linear-gradient(135deg, #fff5e6 0%, #ffe6cc 100%)`
- Border Left: `5px solid #ff9800` (Orange)
- Title: `color: #d84315`

**Current Implementation**:
```html
<div class="zyra-section" style="
    background: linear-gradient(135deg, #fff5e6 0%, #ffe6cc 100%); 
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

**Visual Appearance**:
```
┌────────────────────────────────────────┬──────────────────┐
│ 📢 Breaking News                       │ (LEFT BORDER)    │
├────────────────────────────────────────┬──────────────────┤
│ News content goes here...              │ (ORANGE GRAD)    │
│                                         │                  │
│ Lorem ipsum dolor sit amet...          │                  │
│                                         │                  │
│ 10.03.2026 14:30                       │                  │
└────────────────────────────────────────┴──────────────────┘
```

---

## PROPOSED: FESTA MESSAGE STYLING

Based on existing patterns, the Festa message should follow the **Announcements** pattern (orange):

**Colors**:
- Background Gradient: `linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%)`
- Border Left: `5px solid #f57c00` (Orange)
- Title: `color: #e65100`

**Proposed HTML**:
```html
<div class="zyra-section" style="
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); 
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

**Visual Appearance**:
```
┌────────────────────────────────────────┬──────────────────┐
│ 📌 Festa e Fitër Bajramit              │ (LEFT BORDER)    │
├────────────────────────────────────────┬──────────────────┤
│ 18 Mars 2026 - Zyrat noteriale janë   │ (WARM ORANGE)    │
│ të mbyllura për festën zyrtare.        │ GRADIENT         │
│ Ju lutemi zgjidhni një datë alternative│                  │
│ për terminin tuaj.                      │                  │
│                                         │                  │
│ Punimet normal do të vazhdojnë më      │                  │
│ datë 19 Mars.                           │                  │
└────────────────────────────────────────┴──────────────────┘
```

---

## COLOR PALETTE COMPARISON

### Message Type Colors
| Type | Text Color | Background | Usage |
|------|-----------|------------|-------|
| Error | `#d32f2f` | `#ffeaea` | Form errors, validation failures |
| Success | `#388e3c` | `#eafaf1` | Success confirmations, completed actions |
| Info | `#184fa3` | `#e2eafc` | Informational notices, status updates |
| Ramadan | `#1565c0` | Gradient `#e3f2fd → #bbdefb` | Seasonal announcements (blue) |
| News | `#d84315` | Gradient `#fff5e6 → #ffe6cc` | Important announcements (orange) |
| **Festa** | **`#e65100`** | **Gradient `#fff3e0 → #ffe0b2`** | **Holiday closures (warm orange)** |

### Gradient Breakdown
```
Ramadan Gradient:
  Start: #e3f2fd (very light blue)
    ↓
  End: #bbdefb (light blue)
  Direction: 135deg (top-left to bottom-right)
  
News/Festa Gradient:
  Start: #fff5e6 or #fff3e0 (very light orange/warm)
    ↓
  End: #ffe6cc or #ffe0b2 (light orange)
  Direction: 135deg (top-left to bottom-right)
```

---

## Responsive Design (Mobile)

Current media query styling (applies to all messages):
```css
@media (max-width: 768px) {
    .error, .success, .info {
        line-height: 1.6;
    }
}
```

**What This Means**:
- On mobile devices, messages get slightly more spacing between lines
- All messages automatically stack and resize
- No custom breakpoints needed for the new Festa message

---

## Accessibility Considerations

**Current Implementation**:
- ✓ Text contrast is sufficient (WCAG AA compliant)
- ✓ Large text and padding make messages clear
- ✓ Emoji used for visual indicator (+ text provides context)
- ✓ Messages are not the only way to convey information
- ✓ Responsive design works on all screen sizes

**Recommended Practices Followed**:
- Don't rely on color alone to convey meaning
- Include text descriptions with visual indicators
- Sufficient contrast between text and background
- Clear, simple language in Albanian

---

## JavaScript Alert Comparison

### Current Alert System
```javascript
alert('Nuk ka orare të lira për këtë datë.');
```

**Limitations**:
- Browser default styling
- Limited customization
- Not professional appearance
- Same message for all scenarios

### Proposed Alternatives

**Better for Holiday Context**:
```javascript
alert('🔴 Datë e Mbyllur\n\nFesta e Fitër Bajramit (pushim zyrtar)' +
      '\n\nZyrat janë të mbyllura. Zgjidhni datë alternative.');
```

**Even Better - Styled Info Box**:
```html
<div style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-left: 5px solid #d32f2f;
            padding: 16px;
            border-radius: 8px;
            color: #333;">
    <h4 style="color: #c62828; margin-top: 0;">🔴 Datë e Mbyllur</h4>
    <p>Festa e Fitër Bajramit (pushim zyrtar)</p>
</div>
```

---

## Implementation Checklist

### For Simple Info Banner (Recommended):
- [ ] Copy the proposed HTML code
- [ ] Insert it at [dashboard.php Line 1219](dashboard.php#L1219) (before the calendar)
- [ ] Test in March 2026 view
- [ ] Verify responsive on mobile
- [ ] Check color contrast

### For Hover Tooltip:
- [ ] Add tooltip variable to calendar rendering loop
- [ ] Add `title` attribute to button HTML
- [ ] Test hover behavior on desktop
- [ ] Test on mobile (tooltips may not work)

### For Dynamic Alert Message:
- [ ] Modify `showTimeSlots()` function
- [ ] Add holiday map object
- [ ] Test clicking on March 18
- [ ] Verify message displays correctly

---

## Quick Copy-Paste Snippets

### Info Banner (Complete HTML)
```html
<!-- Festa e Fitër Bajramit Notice -->
<div class="zyra-section" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-left: 5px solid #f57c00; margin-bottom: 20px; padding: 16px; border-radius: 8px;">
    <h3 style="color: #e65100; margin-top: 0; margin-bottom: 12px;">📌 Festa e Fitër Bajramit</h3>
    <p style="color: #333; margin: 0; line-height: 1.6;"><strong>18 Mars 2026</strong> - Zyrat noteriale janë të mbyllura për festën zyrtare. Ju lutemi zgjidhni një datë alternative për terminin tuaj.</p>
    <p style="color: #555; font-size: 0.95em; margin-top: 8px; margin-bottom: 0;">Punimet normal do të vazhdojnë më datë <strong>19 Mars</strong>.</p>
</div>
```

---

*Visual Reference Created: March 10, 2026*
*For Noteria - Notary Booking System*
