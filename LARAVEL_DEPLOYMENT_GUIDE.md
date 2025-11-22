# Noteria Platform - Laravel Cloud Deployment

## 🎯 Overview
Professional Kosovo Notary Office Registration Platform built with Laravel 11, featuring SMS verification through multiple providers (IPKO, Vala, Twilio, Infobip) and deployed on Laravel Cloud.

## 🚀 Quick Start Commands

### 1. Create Laravel Project
```bash
composer create-project laravel/laravel noteria-platform
cd noteria-platform
```

### 2. Install Required Packages
```bash
composer require laravel/jetstream
composer require livewire/livewire
composer require twilio/sdk
composer require infobip/infobip-api-php-client
composer require laravel/cashier
composer require spatie/laravel-permission
composer require laravel/horizon
composer require laravel/telescope
composer require spatie/laravel-backup
```

### 3. Setup Jetstream with Livewire
```bash
php artisan jetstream:install livewire
npm install && npm run build
```

### 4. Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### 5. Install Additional Development Tools
```bash
php artisan telescope:install
php artisan horizon:install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

## 📁 Project Structure

```
noteria-platform/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── NotaryRegistrationController.php
│   │   │   ├── SmsVerificationController.php
│   │   │   └── Admin/DashboardController.php
│   │   ├── Livewire/
│   │   │   ├── NotaryRegistrationForm.php
│   │   │   ├── SmsVerificationWidget.php
│   │   │   └── AdminDashboard.php
│   │   └── Requests/
│   │       ├── NotaryRegistrationRequest.php
│   │       └── SmsVerificationRequest.php
│   ├── Models/
│   │   ├── NotaryOffice.php
│   │   ├── PhoneVerificationCode.php
│   │   ├── SmsProviderConfig.php
│   │   └── PhoneVerificationLog.php
│   ├── Services/
│   │   ├── SmsVerificationService.php
│   │   ├── PaymentVerificationService.php
│   │   └── SmsProviderManager.php
│   ├── Jobs/
│   │   ├── SendSmsVerificationJob.php
│   │   └── ProcessPaymentVerificationJob.php
│   ├── Events/
│   │   ├── PhoneVerified.php
│   │   └── PaymentVerified.php
│   └── Notifications/
│       ├── SmsVerificationCode.php
│       └── RegistrationCompleted.php
├── resources/
│   ├── views/
│   │   ├── livewire/
│   │   │   ├── notary-registration-form.blade.php
│   │   │   ├── sms-verification-widget.blade.php
│   │   │   └── admin-dashboard.blade.php
│   │   ├── layouts/
│   │   │   ├── app.blade.php
│   │   │   └── admin.blade.php
│   │   └── admin/
│   │       └── dashboard.blade.php
│   ├── js/
│   │   ├── app.js
│   │   └── components/
│   │       └── sms-timer.js
│   └── css/
│       ├── app.css
│       └── admin.css
├── database/
│   ├── migrations/
│   │   ├── create_notary_offices_table.php
│   │   ├── create_phone_verification_codes_table.php
│   │   ├── create_sms_provider_configs_table.php
│   │   └── create_phone_verification_logs_table.php
│   └── seeders/
│       ├── NotaryOfficeSeeder.php
│       └── SmsProviderSeeder.php
├── routes/
│   ├── web.php
│   ├── api.php
│   └── admin.php
└── tests/
    ├── Feature/
    │   ├── NotaryRegistrationTest.php
    │   └── SmsVerificationTest.php
    └── Unit/
        ├── SmsServiceTest.php
        └── PaymentServiceTest.php
```

## 🔧 Environment Configuration

### .env File
```env
APP_NAME="Noteria Platform"
APP_ENV=production
APP_KEY=base64:generated_key_here
APP_DEBUG=false
APP_URL=https://noteria.laravel.app

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=noteria_platform
DB_USERNAME=root
DB_PASSWORD=

# SMS Providers
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=+1234567890

INFOBIP_API_KEY=your_infobip_key
INFOBIP_BASE_URL=https://api.infobip.com

VALA_API_KEY=your_vala_key
VALA_API_URL=https://api.vala.com

IPKO_API_KEY=your_ipko_key
IPKO_API_URL=https://api.ipko.com

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@noteria.com
MAIL_FROM_NAME="Noteria Platform"

# Payment
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_secret

# Telescope (Development only)
TELESCOPE_ENABLED=true

# Horizon
HORIZON_DOMAIN=your-domain.com
```

## 🚀 Laravel Cloud Deployment Steps

### 1. Prepare Repository
```bash
git init
git add .
git commit -m "Initial Noteria Platform commit"
git branch -M main
git remote add origin https://github.com/yourusername/noteria-platform.git
git push -u origin main
```

### 2. Laravel Cloud Setup
1. Visit [cloud.laravel.com](https://cloud.laravel.com)
2. Sign in with GitHub account
3. Click "Create Project"
4. Connect your GitHub repository
5. Configure environment:
   - **Project Name**: Noteria Platform
   - **Region**: Europe (recommended for Kosovo)
   - **PHP Version**: 8.3
   - **Node Version**: 18
   - **Database**: MySQL 8.0
   - **Redis**: Enabled
   - **Storage**: 10GB (scalable)

### 3. Environment Variables Setup
Configure in Laravel Cloud dashboard:
```
APP_NAME=Noteria Platform
APP_ENV=production
APP_URL=https://noteria.laravel.app
DB_DATABASE=noteria_production
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

Add SMS provider credentials securely through the dashboard.

### 4. Build Configuration
```yaml
# .laravel-cloud.yml
deploy:
  - name: Install Dependencies
    run: composer install --no-dev --optimize-autoloader
  
  - name: Build Assets
    run: npm install && npm run build
  
  - name: Clear Cache
    run: |
      php artisan config:cache
      php artisan route:cache
      php artisan view:cache
  
  - name: Run Migrations
    run: php artisan migrate --force
  
  - name: Seed Database
    run: php artisan db:seed --force

processes:
  web:
    command: php artisan serve --host=0.0.0.0 --port=$PORT
  
  queue:
    command: php artisan horizon
    scale: 2
  
  scheduler:
    command: php artisan schedule:run
    schedule: "* * * * *"
```

### 5. SSL & Domain Configuration
- Laravel Cloud automatically provides SSL certificates
- Configure custom domain: `noteria.ks` or `noteria.com`
- Set up CDN for static assets
- Enable automatic backups

### 6. Monitoring & Scaling
- **Horizon**: Monitor queue performance
- **Telescope**: Debug issues (disable in production)
- **Auto-scaling**: Handle traffic spikes
- **Health checks**: Automatic failover
- **Logs**: Centralized logging

## 📊 Performance Optimizations

### 1. Database Optimization
```php
// Add indexes for better query performance
Schema::table('notary_offices', function (Blueprint $table) {
    $table->index(['city', 'payment_verified']);
    $table->index(['phone_verified', 'created_at']);
    $table->index('transaction_id');
});
```

### 2. Queue Configuration
```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

### 3. Cache Strategy
```php
// Cache SMS provider configurations
Cache::remember('sms_providers', 3600, function () {
    return SmsProviderConfig::active()->get();
});

// Cache notary office statistics
Cache::remember('dashboard_stats', 300, function () {
    return [
        'total_offices' => NotaryOffice::count(),
        'verified_today' => NotaryOffice::whereDate('phone_verified_at', today())->count(),
        'pending_verification' => NotaryOffice::where('phone_verified', false)->count(),
    ];
});
```

## 🔒 Security Features

### 1. Rate Limiting
```php
// routes/api.php
Route::middleware(['throttle:sms'])->group(function () {
    Route::post('/sms/send', [SmsController::class, 'send']);
    Route::post('/sms/resend', [SmsController::class, 'resend']);
});

// app/Providers/RouteServiceProvider.php
RateLimiter::for('sms', function (Request $request) {
    return Limit::perMinute(3)->by($request->ip());
});
```

### 2. Input Validation
```php
// app/Http/Requests/NotaryRegistrationRequest.php
public function rules()
{
    return [
        'name' => 'required|string|max:255|regex:/^[a-zA-ZëËçÇ\s]+$/',
        'city' => 'required|string|in:Prishtina,Prizren,Peja,Gjilan,Mitrovica,Ferizaj,Gjakova',
        'email' => 'required|email|unique:notary_offices,email',
        'phone' => 'required|regex:/^\+383(43|44|45|48|49)\d{6}$/',
        'bank' => 'required|string|in:BKT,NLB,TEB,BPB,PCB',
        'iban' => 'required|string|regex:/^XK05\d{16}$/',
        'payment_amount' => 'required|numeric|min:149|max:149',
        'payment_proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
    ];
}
```

### 3. File Upload Security
```php
// app/Http/Controllers/FileUploadController.php
public function uploadPaymentProof(Request $request)
{
    $file = $request->file('payment_proof');
    
    // Validate file type and size
    $mimeType = $file->getMimeType();
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new ValidationException('Invalid file type');
    }
    
    // Scan for malware (if antivirus service available)
    // Store with unique filename
    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('payment-proofs', $filename, 'secure');
    
    return $path;
}
```

## 📈 Analytics & Reporting

### 1. Dashboard Metrics
```php
// app/Http/Livewire/AdminDashboard.php
public function mount()
{
    $this->stats = [
        'total_registrations' => NotaryOffice::count(),
        'verified_today' => NotaryOffice::whereDate('phone_verified_at', today())->count(),
        'revenue_monthly' => NotaryOffice::where('payment_verified', true)
            ->whereMonth('created_at', now()->month)
            ->sum('payment_amount'),
        'conversion_rate' => $this->calculateConversionRate(),
        'avg_verification_time' => $this->getAverageVerificationTime(),
    ];
}
```

### 2. SMS Analytics
```php
// Track SMS delivery rates by provider
public function getSmsProviderStats()
{
    return PhoneVerificationLog::select('provider', 
        DB::raw('COUNT(*) as sent'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
        DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
    )
    ->groupBy('provider')
    ->get();
}
```

## 🎯 Launch Strategy

### Phase 1: Prishtina (Week 1-2)
- Target 15 largest notary offices
- Offer 1-month free trial
- Direct sales approach
- Monitor system performance

### Phase 2: Major Cities (Week 3-6)
- Expand to Prizren, Peja, Gjilan
- Referral program
- Case studies from Prishtina
- Scale infrastructure

### Phase 3: National Rollout (Week 7-12)
- All remaining cities
- Marketing campaign
- Partner with Kosovo Notary Chamber
- Full feature release

## 💰 Pricing Strategy

### Subscription Tiers
1. **Basic** - €99/month
   - SMS verification
   - Basic dashboard
   - Email support

2. **Professional** - €149/month (Recommended)
   - All Basic features
   - Advanced analytics
   - Priority SMS routing
   - Phone support

3. **Enterprise** - €199/month
   - All Professional features
   - Custom integrations
   - Dedicated account manager
   - SLA guarantee

### Revenue Projections
- **Year 1**: €26,820 - €44,700 (30-50 offices)
- **Year 2**: €89,460 - €134,190 (60-90 offices)
- **Year 5**: €169,860 - €196,680 (114-127 offices)

## 🎉 Success Metrics

### Technical KPIs
- SMS delivery rate: >98%
- Payment verification time: <5 minutes
- System uptime: >99.9%
- Page load time: <2 seconds

### Business KPIs
- Customer acquisition: 10 offices/month
- Churn rate: <5%
- Customer satisfaction: >4.5/5
- Monthly recurring revenue growth: >15%

---

**Ready to revolutionize Kosovo's notary industry! 🚀🇽🇰**

For deployment assistance: contact@noteria.ks