# Visual Architecture Guide - Noteria Message Systems

## System Overview Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          DASHBOARD.PHP PAGE LOAD                            │
└──────────────────────────────┬──────────────────────────────────────────────┘
                               │
                    ┌──────────┴──────────┐
                    ↓                      ↓
        ┌──────────────────────┐  ┌──────────────────────┐
        │  Check URL Params    │  │  Session Validation  │
        │  (?error=...)        │  │  User Authentication │
        └──────────┬───────────┘  └──────────┬───────────┘
                   │                         │
                   └──────────────┬──────────┘
                                  ↓
                    ┌─────────────────────────────┐
                    │  ANNOUNCEMENTS SECTION      │
                    │  (New message goes here!)   │
                    │ ┌─────────────────────────┐ │
                    │ │ • Ramadan Notice        │ │
                    │ │ • Holiday Closures      │ │  ← ADD FESTA MESSAGE HERE
                    │ │ • News (from DB)        │ │
                    │ └─────────────────────────┘ │
                    └─────────────────────────────┘
                                  ↓
                    ┌─────────────────────────────┐
                    │  BOOKING CALENDAR           │
                    │  (Interactive Date Select)  │
                    │ ┌─────────────────────────┐ │
                    │ │ Displays:               │ │
                    │ │ • All dates for month   │ │
                    │ │ • Disabled (gray):      │ │
                    │ │   - Weekends            │ │
                    │ │   - Past dates          │ │
                    │ │   - Booked dates        │ │
                    │ │   - HOLIDAYS (03-18)    │ │
                    │ │ • Enabled (blue):       │ │
                    │ │   - Available booking   │ │
                    │ │   - dates               │ │
                    │ └─────────────────────────┘ │
                    └─────────────────────────────┘
                                  ↓
                    ┌─────────────────────────────┐
                    │  TIME SLOTS & SERVICES      │
                    │  (Selection after date)     │
                    └─────────────────────────────┘
                                  ↓
                    ┌─────────────────────────────┐
                    │  FORM VALIDATION & ERRORS   │
                    │  (Red/Green/Blue messages)  │
                    └─────────────────────────────┘
```

---

## Message Types by Layer

### LAYER 1: Announcements (STATIC/SEASONAL)

```
                    ANNOUNCEMENTS LAYER
                          │
                ┌─────────┼─────────┐
                ↓         ↓         ↓
          Ramadan       Holidays    News
          Notice        Closures    Feed
            (Blue)       (Orange)   (Orange)
            
┌──────────────────────────────────────────────────┐
│ 📌 [ICON] Title                                  │
├──────────────────────────────────────────────────┤
│ Primary message text                           │
│ Context and explanation                        │
│ Call-to-action or next steps                   │
└──────────────────────────────────────────────────┘

Styling:
• Gradient background
• Left accent border (5px)
• Custom color scheme per type
• Prominent emoji icon
• Responsive typography
```

### LAYER 2: Calendar Interface (INTERACTIVE)

```
              BOOKING CALENDAR LAYER
                      │
        ┌─────────────────────────────┐
        │  Navigation Controls        │
        │  ◀ [Month Year] ▶           │
        └──────────────┬──────────────┘
                       ↓
        ┌─────────────────────────────┐
        │  Day Headers                │
        │ H M W T F S S              │
        └──────────────┬──────────────┘
                       ↓
        ┌─────────────────────────────┐
        │  Calendar Grid              │
        │                             │
        │  [ 1] [ 2] [ 3] [ 4] [ 5]  │
        │  ┌──┐ ┌──┐ ┌──┐ ┌──┐ ┌──┐  │
        │  │1 │ │2 │ │3 │ │4 │ │5 │  │
        │  └──┘ └──┘ └──┘ └──┘ └──┘  │
        │                             │
        │  [ 8] [ 9] [10] [11] [12]  │
        │  ┌──┐ ┌──┐ ┌──┐ ┌──┐ ┌────┐│
        │  │8 │ │9 │ │10│ │11│ │ 18 │← DISABLED (Holiday)
        │  └──┘ └──┘ └──┘ └──┘ └────┘│
        │                             │
        └─────────────────────────────┘

Button States:
ENABLED:  Blue border, white bg    | CLICKABLE
DISABLED: Gray border, gray bg     | GRAYED OUT
          Text: #999
          Cursor: not-allowed
          
Disable Reasons:
• Weekend (Sat/Sun)
• Past date
• Booked (no slots)
• Holiday (03-18) ← FESTA
```

### LAYER 3: Validation & Alerts (DYNAMIC)

```
         FORM VALIDATION LAYER
                  │
    ┌─────────────┼─────────────┐
    ↓             ↓             ↓
  ERROR        SUCCESS         INFO
  (Red)        (Green)         (Blue)

ERROR EXAMPLE:
┌────────────────────────────────────────────────┐
│ 🔴 Përdoruesi nuk është i lidhur me asnjë zyrë │
│    Kontaktoni administratorin!                 │
└────────────────────────────────────────────────┘
Background: #ffeaea (light red)
Text: #d32f2f (dark red)

SUCCESS EXAMPLE:
┌────────────────────────────────────────────────┐
│ ✓ Profili u përditësua me sukses!             │
└────────────────────────────────────────────────┘
Background: #eafaf1 (light green)
Text: #388e3c (dark green)

INFO EXAMPLE:
┌────────────────────────────────────────────────┐
│ ℹ Nuk ka njoftime të reja.                     │
└────────────────────────────────────────────────┘
Background: #e2eafc (light blue)
Text: #184fa3 (dark blue)

JAVASCRIPT ALERT EXAMPLE:
    ┌─────────────────────────┐
    │ 🔴 Message             │
    │                         │
    │ Nuk ka orare të lira    │
    │ për këtë datë.          │
    │                         │
    │  [   OK   ]             │
    └─────────────────────────┘
```

---

## Message Type Matrix

```
╔════════════════╦════════════╦════════════╦═══════════════════╗
║ Message Type   ║ Location   ║ Visibility ║ Styling           ║
╠════════════════╬════════════╬════════════╬═══════════════════╣
║ ERROR          ║ Layer 3    ║ Always     ║ Red/White div     ║
║ .error         ║ Form area  ║ Prominent  ║ #d32f2f/#ffeaea   ║
║                ║            ║            ║                   ║
║ SUCCESS        ║ Layer 3    ║ Always     ║ Green/White div   ║
║ .success       ║ Form area  ║ Prominent  ║ #388e3c/#eafaf1   ║
║                ║            ║            ║                   ║
║ INFO           ║ Layer 3    ║ Always     ║ Blue/White div    ║
║ .info          ║ Form area  ║ Prominent  ║ #184fa3/#e2eafc   ║
║                ║            ║            ║                   ║
║ ALERT (JS)     ║ Layer 3    ║ On-demand  ║ Browser modal     ║
║ alert()        ║ Overlay    ║ Blocking   ║ Native styling    ║
║                ║            ║            ║                   ║
║ RAMADAN        ║ Layer 1    ║ Always     ║ Gradient box      ║
║ Notice         ║ Top        ║ Prominent  ║ Blue/Gradient     ║
║ .zyra-section  ║            ║            ║ #1976d2 border    ║
║                ║            ║            ║                   ║
║ NEWS/HOLIDAY   ║ Layer 1    ║ Always     ║ Gradient box      ║
║ Announcement   ║ Top        ║ Prominent  ║ Orange/Gradient   ║
║ .zyra-section  ║            ║            ║ #ff9800 or #f57c00║
║                ║            ║            ║ BORDER            ║
║                ║            ║            ║                   ║
║ TOOLTIP        ║ Layer 2/3  ║ Hover      ║ Browser tooltip   ║
║ title=""       ║ Button     ║ Desktop    ║ Native styling    ║
╚════════════════╩════════════╩════════════╩═══════════════════╝
```

---

## Data Flow for Holiday Disabling

```
┌─────────────────────────────────────┐
│ Dashboard.php Loads                 │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│ Calendar JavaScript Initializes     │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│ Define Holiday Array:               │
│ const kosovoHolidays = [            │
│   '01-01', '01-02',                 │
│   '02-17', '03-08',                 │
│   '03-18',  ← FESTA                 │
│   '04-09', '05-01',                 │
│   '05-09', '06-12',                 │
│   '09-28', '12-25'                  │
│ ];                                  │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│ Fetch Available Slots API           │
│ get_available_slots.php             │
│ (Returns: booked dates)             │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│ Render Calendar Grid                │
│ For each date in month:             │
└──────────────┬──────────────────────┘
               │
        ┌──────┴──────┐
        ↓             ↓
    ┌───────┐    ┌──────────────┐
    │ Check │    │ Check Date   │
    │ Type  │    │ Conditions   │
    └───┬───┘    └──────┬───────┘
        │               │
        ↓               ↓
    Is Weekend?    ┌─────────────┐
    Is Past?       │ Is Holiday? │─→ Match MM-DD
    Is Booked?     │ (MM-DD)     │   against array
    Is Holiday?    └─────────────┘
        │               │
        └───────┬───────┘
                ↓
    ┌──────────────────────────┐
    │ Apply Button State       │
    ├──────────────────────────┤
    │ IF any condition = TRUE: │
    │   • Gray background      │
    │   • Gray border          │
    │   • Disable button       │
    │   • Add title="" tooltip  │ ← COULD ADD HERE
    │                          │
    │ IF all conditions = FALSE│
    │   • Blue background      │
    │   • Blue border          │
    │   • Enable button        │
    │   • Clickable            │
    └──────────────────────────┘
                ↓
    ┌──────────────────────────┐
    │ Render HTML Button       │
    │ <button data-date="...   │
    │   style="..."            │
    │   disabled/...           │
    │   >{{ day }}</button>     │
    └──────────────────────────┘
                ↓
    ┌──────────────────────────┐
    │ Attach Event Listeners   │
    │ onClick handlers         │
    │ (if not disabled)        │
    └──────────────────────────┘
                ↓
    ┌──────────────────────────┐
    │ User Sees Calendar       │
    │ March 18 is GRAYED OUT   │
    │ (No explanation shown)   │
    └──────────────────────────┘
                ↓
    ┌──────────────────────────┐
    │ ← WE'RE ADDING MESSAGE   │
    │   RIGHT HERE ↑           │
    │   A NOTICE BLOCK         │
    │   BEFORE THE CALENDAR    │
    └──────────────────────────┘
```

---

## Implementation Integration Points

```
                        DASHBOARD.PHP
                         (Page Render)
                              │
                ┌─────────────┼─────────────┐
                ↓             ↓             ↓
            PHP Code      HTML Body     JavaScript
                │             │             │
                │             ↓             │
                │    ┌──────────────────┐  │
                │    │  ANNOUNCEMENTS   │  │
                │    │  SECTION          │  │
                │    │ (HTML only OR    │  │
                │    │  PHP-driven DB)  │  │
                │    │                  │  │
                │    │ ← INSERT MSG     │  │
                │    │   HERE           │  │
                │    └──────────────────┘  │
                │             │             │
                │             ↓             │
                │    ┌──────────────────┐  │
                │    │ Booking Calendar │  │
                │    │ Container        │  │
                │    │ <div id=         │  │
                │    │  "kalendar-      │  │
                │    │  booking">       │  │
                │    └──────────────────┘  │
                │             │             │
                │             │             ↓
                │             │      Rendered by JS:
                │             │      createBookingCalendar()
                │             │      renderCalendar()
                │             │      Check kosovoHolidays
                │             │      Apply styles
                │             │
                └─────────────┴─────────────┘
                              │
                              ↓
                   User sees complete
                   dashboard with:
                   1. Message (new)
                   2. Calendar (with
                      disabled 03-18)
                   3. Booking form
```

---

## Color Palette Gradient Breakdown

```
RAMADAN NOTICE (Blue Theme)
┌──────────────────────────────────────────┐
│ ███████████████████████████████████████ │  Start: #e3f2fd
│ ███████████████████████████████████████ │  ↓ (135deg gradient)
│ ███████████████████████████████████████ │  End: #bbdefb
└──────────────────────────────────────────┘
Direction: 135° (top-left to bottom-right)

NEWS/FESTA NOTICE (Orange Theme)
┌──────────────────────────────────────────┐
│ ███████████████████████████████████████ │  Start: #fff3e0
│ ███████████████████████████████████████ │  ↓ (135deg gradient)
│ ███████████████████████████████████████ │  End: #ffe0b2
└──────────────────────────────────────────┘
Direction: 135° (top-left to bottom-right)

LEFT BORDER ACCENT
┌──────────────────────────────────────────┐
│█│ ▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂│
│█│ Content area                         │
│█│ 5px wide accent border               │
│█│ Color: #1976d2 (Ramadan)             │
│█│ Color: #f57c00 (Festa)               │
│█│ ▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂▂│
└──────────────────────────────────────────┘
```

---

## User Journey: Booking with Holiday

```
START: User Opens Dashboard
    │
    ├─→ See Announcements
    │   ├─→ Ramadan Notice (if active)
    │   ├─→ Festa Notice (← NEW MESSAGE)
    │   └─→ Latest News
    │
    ├─→ Select Office
    │   └─→ Calendar Loads
    │
    ├─→ Navigate to March 2026
    │   └─→ See Calendar Grid
    │       ├─→ Blue dates = available
    │       └─→ Gray dates = disabled
    │           ├─→ 1-17: Check availability
    │           ├─→ 18: GRAY (Holiday) ← See notice above
    │           └─→ 19+: Check availability
    │
    └─→ Outcomes:
        A) Click available date → Select time/service → Book ✓
        B) Click gray date → No action (disabled) ✓
        C) Read notice → Understand March 18 closure ✓
```

---

## Testing Diagram

```
BEFORE IMPLEMENTATION:
┌──────────────────────────────────────────┐
│ Dashboard                                │
│ ┌──────────────────────────────────────┐ │
│ │ Ramadan Notice                       │ │
│ └──────────────────────────────────────┘ │
│ ┌──────────────────────────────────────┐ │
│ │ Calendar (March 2026)                │ │
│ │                                      │ │
│ │ [1][2][3][4][5]                     │ │
│ │ [8][9][10][11][] [18 GRAY] [25]    │ │
│ │                                      │ │
│ │ NO EXPLANATION FOR GRAY DATE!        │ │
│ └──────────────────────────────────────┘ │
└──────────────────────────────────────────┘

AFTER IMPLEMENTATION:
┌──────────────────────────────────────────┐
│ Dashboard                                │
│ ┌──────────────────────────────────────┐ │
│ │ Ramadan Notice                       │ │
│ └──────────────────────────────────────┘ │
│ ┌──────────────────────────────────────┐ │ ← NEW
│ │ 📌 Festa e Fitër Bajramit            │ │
│ │ 18 Mars - Zyrat mbyllur              │ │
│ │ Zgjidh datë alternative              │ │
│ └──────────────────────────────────────┘ │
│ ┌──────────────────────────────────────┐ │
│ │ Calendar (March 2026)                │ │
│ │                                      │ │
│ │ [1][2][3][4][5]                     │ │
│ │ [8][9][10][11][] [18 GRAY] [25]    │ │
│ │                                      │ │
│ │ ✓ CLEAR EXPLANATION PROVIDED         │ │
│ └──────────────────────────────────────┘ │
└──────────────────────────────────────────┘
```

---

## File Integration Summary

```
dashboard.php
├── Header (PHP session/config)
├── CSS Styles (.error, .success, .info)
├── HTML Body
│   ├── Ramadan Notice [Line 1061-1070]
│   ├── News Section [Line 1084-1105]
│   │
│   ├── [← INSERT FESTA MESSAGE HERE] [Before Line 1213]
│   │
│   └── Booking Calendar [Line 1213+]
│       ├── Calendar div container
│       └── JavaScript initialization
│           └── kosovoHolidays array [Line 1314]
│
└── JavaScript (Calendar rendering)
    ├── createBookingCalendar()
    ├── renderCalendar()
    │   ├── Check isHoliday
    │   ├── Apply button styling
    │   └── Render <button> elements
    ├── showTimeSlots()
    └── Event handlers
```

---

**Created**: March 10, 2026  
**Project**: Noteria - Notary Booking System
