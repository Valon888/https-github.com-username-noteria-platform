# Noteria Advertising Pricing System Documentation

## Overview
Complete pricing model implementation for the Noteria advertising platform with €300/month Professional subscription tier as the recommended plan.

## Pricing Tiers

### 1. **Starter Plan** - €99/month
- Up to 5 active ads
- Basic analytics
- 10,000 impressions per month
- Limited support
- No A/B testing

### 2. **Professional Plan** - €300/month ⭐ RECOMMENDED
- Up to 20 active ads
- Advanced analytics in real-time
- Unlimited impressions
- A/B testing capability
- Priority support
- Dedicated account assistance

### 3. **Enterprise Plan** - €999/month
- Unlimited ads
- Full advanced analytics
- Dedicated account manager
- Custom targeting options
- API access
- Premium support

## Database Schema

### New Tables Created
- **pricing_plans** - Stores all available pricing tiers
- **ad_payments** - Tracks subscriptions and payments (updated with plan_id column)

### Pricing Plans Table
```sql
CREATE TABLE pricing_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    billing_period VARCHAR(20) DEFAULT 'monthly',
    features TEXT,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

### Ad Payments Table (Updated)
```sql
ALTER TABLE ad_payments ADD COLUMN plan_id INT(11) DEFAULT 2 AFTER advertiser_id
```

## Core Files

### 1. **pricing_helper.php** - Helper Functions
Main utility file with all pricing-related functions:

**Functions:**
- `getPricingPlans($pdo)` - Get all active pricing plans
- `getPricingPlanById($pdo, $plan_id)` - Get specific plan details
- `getSubscriptionStatus($pdo, $advertiser_id)` - Check current subscription
- `isSubscriptionActive($pdo, $advertiser_id)` - Verify if subscription is valid
- `createSubscription($pdo, $advertiser_id, $plan_id)` - Create new subscription
- `markSubscriptionAsPaid($pdo, $payment_id)` - Mark payment as completed
- `generateInvoice($pdo, $advertiser_id)` - Generate subscription invoice
- `getPricingTableHTML($currency)` - HTML rendering of pricing cards
- `getPricingCSS()` - Styling for pricing display
- `getPricingJS()` - JavaScript for plan selection

### 2. **business_advertising.php** (Updated)
Advertiser portal with integrated pricing display:

**New Features:**
- Prominent pricing section showing all 3 tiers
- Subscription status display for logged-in advertisers
- Current subscription information (plan, price, end date)
- Renewal/reactivation buttons for expired subscriptions
- Pricing details with €300/month highlighted
- Terms and conditions for Professional package

**Sections:**
- Registration form for new businesses (with pricing intro)
- Subscription status display (active/inactive/none)
- Feature grid (Users, Analytics, Results)
- Ad creation form
- Ads management table
- Pricing plans section
- Help/contact section

### 3. **select_plan.php** - Plan Selection Page
Comprehensive plan selection interface:

**Features:**
- All 3 pricing tiers displayed side-by-side
- Professional tier highlighted as recommended
- Detailed feature comparison table
- Plan selection buttons
- Subscription status tracking
- Admin controls (mark paid, renew, suspend)
- Responsive grid layout

**Comparison Table Shows:**
- Monthly price
- Number of active ads
- Monthly ad uploads limit
- Analytics capabilities
- A/B testing availability
- Support priority
- Dedicated account manager
- API access

### 4. **admin_invoicing.php** - Admin Panel
Complete subscription and billing management dashboard:

**Features:**
- Real-time statistics dashboard:
  * Total revenue (all time)
  * Monthly revenue
  * Active subscriptions count
  * Pending payments count

- Subscription management table with:
  * Business name and contact
  * Current pricing plan
  * Monthly cost
  * Subscription status (paid, pending, suspended)
  * Subscription period
  * Last payment date
  * Admin action buttons

- Admin Actions:
  * Mark payment as paid (for pending subscriptions)
  * Renew expired subscriptions
  * Suspend subscriptions for non-payers
  * View subscription details

### 5. **setup_pricing.php** - Initial Setup Script
Initializes pricing system on first run:
- Creates pricing_plans table
- Inserts default pricing tiers
- Adds monthly_price and next_payment_date columns
- Verifies table structure

### 6. **test_pricing.php** - Test Suite
Comprehensive testing script:
- Creates test advertiser
- Tests plan retrieval
- Tests subscription creation
- Tests status checking
- Tests payment marking
- Tests invoice generation

## Subscription Lifecycle

### 1. **Registration** (business_advertising.php)
- New advertiser registers company
- Shown pricing options
- Selects desired plan

### 2. **Plan Selection** (select_plan.php)
- View detailed comparison table
- Select pricing tier
- Subscription created with "pending" status
- Invoice generated

### 3. **Payment Processing**
- Advertiser makes payment via external gateway
- Admin marks subscription as "paid"
- Status updates to active
- Ads enabled for display

### 4. **Active Subscription** (monthly)
- Ads display on platform
- Impressions and clicks tracked
- Analytics available in real-time
- Monthly invoice generated

### 5. **Renewal** (at month end)
- Subscription end date reached
- Admin option to auto-renew or let expire
- Non-renewed subscriptions marked as "expired"
- Ads hidden from display

### 6. **Suspension** (non-payment)
- Admin can suspend subscription for non-payers
- Ads immediately hidden
- Status marked as "suspended"
- Advertiser notified

## Cost Calculation

**Monthly Subscription Revenue:**
- Professional Plan (Recommended): €300/advertiser/month
- Example: 10 advertisers × €300 = €3,000/month

**Features Included at €300/month:**
- Up to 20 active ad campaigns
- Unlimited daily impressions
- Advanced real-time analytics
- A/B testing capabilities
- Priority customer support
- Placement in prime locations (dashboard, login page)

## Integration Points

### 1. **With Dashboards**
- `admin_dashboard.php` - Can link to admin_invoicing.php for revenue overview
- `business_advertising.php` - Full integration complete
- `select_plan.php` - Accessible from advertising portal

### 2. **Payment Gateway** (Future Implementation)
- Can integrate Stripe, PayPal, or custom payment processor
- Webhook callback to mark subscriptions as paid
- Automatic renewal processing

### 3. **Invoice System** (Future Enhancement)
- PDF invoice generation
- Email delivery to advertisers
- Recurring invoicing automation

## Security Features

- SQL injection prevention via prepared statements
- Session validation before subscription access
- Admin-only access to billing dashboard
- Role-based access control (advertiser vs admin)
- Error logging without exposing sensitive data

## Testing Results

✓ All 3 pricing plans retrievable
✓ Subscriptions created successfully  
✓ Status tracking working correctly
✓ Invoice generation functional
✓ Active subscription verification accurate
✓ Payment marking functional
✓ Database schema updates applied

## Files Deployed

1. ✅ pricing_helper.php - Helper functions
2. ✅ business_advertising.php - Updated with pricing
3. ✅ select_plan.php - Plan selection page
4. ✅ admin_invoicing.php - Admin dashboard
5. ✅ setup_pricing.php - Database initialization
6. ✅ fix_ad_payments_schema.php - Schema updates
7. ✅ test_pricing.php - Test suite

## Next Steps (Optional Enhancements)

1. **Payment Gateway Integration**
   - Stripe API integration
   - PayPal integration
   - Manual payment processing

2. **Automated Invoicing**
   - PDF invoice generation
   - Email delivery system
   - Invoice history per advertiser

3. **Advanced Analytics**
   - ROI calculation
   - Cost per impression/click
   - Performance trend charts

4. **Subscription Management**
   - Self-service subscription upgrades/downgrades
   - Proration calculations
   - Usage-based additional charges

5. **Compliance Features**
   - VAT/Tax calculations
   - Invoice numbering compliance
   - Financial reporting

## Current Status

✅ **PRODUCTION READY** - Pricing system fully implemented and tested
- All database tables created
- All helper functions working
- Admin and advertiser interfaces functional
- Payment tracking system operational
- Invoice generation system ready
- Git repository updated with all changes
