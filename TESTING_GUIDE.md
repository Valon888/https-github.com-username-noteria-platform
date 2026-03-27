# 🧪 TESTING GUIDE - Billing Dashboard Advanced Features

## Quick Start Testing

### 1️⃣ ACCESS THE DASHBOARD
```
URL: http://localhost/noteria/billing_dashboard.php
Username: admin@example.com (must have admin role)
Status: Should see "Admin" badge + Live indicator if auto-payments enabled
```

---

## 🎯 TEST SCENARIOS

### Test 1: Advanced Filtering
**Goal**: Verify all filters work independently and together

```steps:
1. Go to "Pagesat e Fundit" section
2. Test Individual Filters:
   ✓ Search by noter name
   ✓ Search by email
   ✓ Search by transaction ID
   ✓ Filter by Status (Completed, Pending, Failed)
   ✓ Filter by Payment Method (Visa, Mastercard, SEPA, Bank)
   ✓ Filter by Month (Any month)
   ✓ Filter by Amount Range
   ✓ Sort by Date, Amount, Status, Name
   ✓ Sort Direction (ASC/DESC)

3. Test Combined Filters:
   - Status: Completed + Amount: €100-€500
   - Method: Visa + Month: Current
   - Search: "test" + Status: Pending
```

**Expected Result**: ✅ All filters apply correctly, pagination works

---

### Test 2: CSV Export
**Goal**: Verify export functionality

```steps:
1. Apply some filters (e.g., Status = Completed, Month = Current)
2. Click "Shkarko CSV" button
3. Wait for file download
4. Open in Excel or text editor
5. Verify:
   ✓ Headers present
   ✓ Data matches filters
   ✓ Encoding correct (UTF-8)
   ✓ All columns present
```

**Expected Files Generated**:
- `pagesat_YYYY-MM-DD_HH-ii-ss.csv`

---

### Test 3: Real-time Stats API
**Goal**: Verify API returns valid JSON

```bash
# Command line test
curl -X GET http://localhost/noteria/billing_stats_api.php \
  -H "Cookie: PHPSESSID=your_session_id"

# Expected JSON response:
{
  "total_revenue": 15000.00,
  "monthly_revenue": 2500.00,
  "today_revenue": 500.00,
  "pending_count": 5,
  "active_notaries": 45,
  "success_rate": 93.5,
  "timestamp": "2024-03-02 14:30:45",
  "status": "success"
}
```

**Expected Result**: ✅ Valid JSON, all metrics present

---

### Test 4: Advanced Reports Dashboard
**Goal**: Verify reports page displays correctly

```steps:
1. Click "Raportet e Avancuara" button
2. On reports page, verify:
   ✓ Header with title and generation date
   ✓ Metrics Cards (6): Active Notaries, Total Revenue, etc.
   ✓ Revenue by Payment Method table
   ✓ Top Notaries performance table
   ✓ Monthly trends table
   ✓ Print button works
   ✓ Links to JSON and CSV exports
```

**Print Test**:
```steps:
1. Click "Printo Raportin"
2. Print preview should show:
   ✓ All metrics visible
   ✓ No broken layout
   ✓ Colors/formatting intact
   ✓ Tables formatted properly
```

---

### Test 5: Churn Risk Analysis
**Goal**: Verify at-risk notaries are identified

```steps:
1. Scroll to "Churn Risk Analysis" section
2. Verify:
   ✓ Section appears if churn_count > 0
   ✓ List shows notaries with >45 days without payment
   ✓ "Ditë pa pagim" column shows correct count
   ✓ Email buttons are clickable
   ✓ Can send email notifications

3. Test email notification:
   - Click email button
   - Should open mailto: link
   - Email client should open
```

**Expected Result**: ✅ Churn notaries identified, email works

---

### Test 6: Payment Method Analytics
**Goal**: Verify payment method breakdown

```steps:
1. Look for "Metodat e Pagesës (90 ditë)" section
2. Verify for each payment method card:
   ✓ Method name displayed
   ✓ Percentage badge shown
   ✓ Transaction count
   ✓ Successful transactions count
   ✓ Total amount displayed
   ✓ Average amount correct

3. Calculation Check:
   - Total percentages should ≈ 100%
   - Successful <= Total transactions
```

**Expected Result**: ✅ All cards display, math correct

---

### Test 7: Mobile Responsiveness
**Goal**: Verify design works on mobile

```browser:
1. Open DevTools (F12)
2. Set viewport to iPhone 12 (390x844)
3. Test:
   ✓ Layout doesn't break
   ✓ Cards stack vertically
   ✓ Buttons readable
   ✓ Tables scroll horizontally
   ✓ Filters are accessible
   ✓ Text is readable (16px+)

4. Test touch interactions:
   ✓ Buttons clickable
   ✓ Links accessible
   ✓ No overlapping elements
```

**Breakpoints to Test**:
- 320px (iPhone SE)
- 375px (iPhone X)
- 768px (iPad)
- 1024px (Desktop)
- 1440px (Full HD)

---

### Test 8: Form Validation
**Goal**: Verify all form validation works

```steps:
1. Go to configuration section
2. Clear required fields (billing_time, standard_price, etc.)
3. Try to submit
4. Verify:
   ✓ Error message appears
   ✓ Invalid fields highlighted red
   ✓ Form not submitted

5. Fill fields correctly:
   ✓ Fields turn normal color
   ✓ Form can be submitted
   ✓ Success message appears
```

**Expected Result**: ✅ Validation works, visual feedback present

---

### Test 9: Security Checks
**Goal**: Verify security implementations

```tests:
1. SQL Injection Test:
   - Try in search: " OR 1=1; --
   - Expected: Safe query, no data leak

2. XSS Test:
   - Try in search: <script>alert('XSS')</script>
   - Expected: Script escaped, no alert

3. Unauthorized Access:
   - Access API without session
   - Expected: 401 error

4. Session Hijacking:
   - Try with fake PHPSESSID
   - Expected: Redirect to login
```

**Expected Result**: ✅ All security measures working

---

### Test 10: Performance Testing
**Goal**: Verify page loads quickly

```tools:
Using Chrome DevTools:
1. Network tab: Monitor loading
   ✓ Page load < 3 seconds
   ✓ Database queries efficient
   ✓ No duplicate requests

2. Performance tab: Run audit
   ✓ LCP (Largest Contentful Paint) < 2.5s
   ✓ FID (First Input Delay) < 100ms
   ✓ CLS (Cumulative Layout Shift) < 0.1

3. Lighthouse audit:
   ✓ Performance > 80%
   ✓ Accessibility > 85%
```

---

## 📋 BROWSER COMPATIBILITY

Test on these browsers:
- ✓ Chrome 100+ (Latest)
- ✓ Firefox 90+ (Latest)
- ✓ Safari 14+ (macOS/iOS)
- ✓ Edge 100+ (Latest)

---

## 🔍 MANUAL TEST CHECKLIST

Before deployment, verify:

```checkbox:
FUNCTIONALITY
☐ All filters work
☐ Pagination works
☐ Export to CSV works
☐ API responds correctly
☐ Reports load correctly
☐ Churn analysis displays
☐ Email links work
☐ Configuration can be saved

UI/UX
☐ Layouts responsive
☐ Colors consistent
☐ Fonts readable
☐ Animations smooth
☐ Icons display
☐ Badges style correct
☐ Mobile looks good

SECURITY
☐ Session required
☐ Admin role required
☐ Input sanitized
☐ Output escaped
☐ No console errors
☐ No vulnerabilities

PERFORMANCE
☐ Page loads < 3s
☐ No memory leaks
☐ Smooth scrolling
☐ Fast interactions
☐ No lag on filter
☐ CSV downloads quick
```

---

## 🐛 BUG REPORT TEMPLATE

If you find issues:

```
**Title**: [Brief description]

**Steps to Reproduce**:
1. Go to...
2. Click...
3. See...

**Expected Result**:
What should happen

**Actual Result**:
What actually happens

**Browser**: Chrome 100
**OS**: Windows 10
**Screen**: 1920x1080

**Screenshots**: [Attach]
**Console Errors**: [Attach]

**Severity**: Minor/Normal/Critical
```

---

## ✅ FINAL CHECKLIST

Before going live:

- [ ] All tests passed
- [ ] No console errors
- [ ] No security issues
- [ ] Documentation complete
- [ ] Team reviewed
- [ ] Database backed up
- [ ] Monitoring enabled
- [ ] Support team trained

---

## 📞 TEST SUPPORT

Need help testing?
- Slack: #qa-testing
- Email: qa@noteria.com
- Docs: [Testing Wiki]
- Tools: [Test Data Generator]

---

**Test Version**: 2.0
**Last Updated**: 02.03.2024
**Status**: Ready for Testing ✓
