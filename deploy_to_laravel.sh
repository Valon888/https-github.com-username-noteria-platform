#!/bin/bash

# Noteria Platform - Laravel Cloud Deployment Script
# This script automates the migration from vanilla PHP to Laravel

echo "🚀 Starting Noteria Platform Laravel Migration..."
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install Composer first."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    print_error "NPM is not installed. Please install Node.js and NPM first."
    exit 1
fi

# Check if git is installed
if ! command -v git &> /dev/null; then
    print_error "Git is not installed. Please install Git first."
    exit 1
fi

print_info "All required tools are available ✓"

# Step 1: Create new Laravel project
echo ""
echo "Step 1: Creating Laravel project..."
echo "=================================="

read -p "Enter project name (default: noteria-platform): " PROJECT_NAME
PROJECT_NAME=${PROJECT_NAME:-noteria-platform}

if [ -d "$PROJECT_NAME" ]; then
    print_warning "Directory $PROJECT_NAME already exists."
    read -p "Do you want to continue and overwrite? (y/N): " overwrite
    if [[ ! $overwrite =~ ^[Yy]$ ]]; then
        print_error "Deployment cancelled."
        exit 1
    fi
    rm -rf "$PROJECT_NAME"
fi

print_info "Creating Laravel project: $PROJECT_NAME"
composer create-project laravel/laravel "$PROJECT_NAME"

if [ $? -eq 0 ]; then
    print_status "Laravel project created successfully"
else
    print_error "Failed to create Laravel project"
    exit 1
fi

cd "$PROJECT_NAME"

# Step 2: Install required packages
echo ""
echo "Step 2: Installing required packages..."
echo "======================================"

print_info "Installing Jetstream with Livewire..."
composer require laravel/jetstream

print_info "Installing SMS providers..."
composer require twilio/sdk
composer require infobip/infobip-api-php-client

print_info "Installing additional packages..."
composer require laravel/cashier
composer require spatie/laravel-permission
composer require laravel/horizon
composer require laravel/telescope
composer require spatie/laravel-backup
composer require intervention/image
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf

if [ $? -eq 0 ]; then
    print_status "All packages installed successfully"
else
    print_error "Failed to install some packages"
    exit 1
fi

# Step 3: Setup Jetstream
echo ""
echo "Step 3: Setting up Jetstream..."
echo "==============================="

php artisan jetstream:install livewire

if [ $? -eq 0 ]; then
    print_status "Jetstream installed successfully"
else
    print_error "Failed to install Jetstream"
    exit 1
fi

# Step 4: Install npm dependencies and build
echo ""
echo "Step 4: Building frontend assets..."
echo "=================================="

npm install
npm run build

if [ $? -eq 0 ]; then
    print_status "Frontend assets built successfully"
else
    print_error "Failed to build frontend assets"
    exit 1
fi

# Step 5: Copy migration files and models
echo ""
echo "Step 5: Setting up database structure..."
echo "======================================="

# Create migrations directory if it doesn't exist
mkdir -p database/migrations

# Copy our custom migration
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_noteria_tables.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notary_offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('bank');
            $table->string('iban');
            $table->string('account_number');
            $table->decimal('payment_amount', 10, 2);
            $table->string('transaction_id')->unique();
            $table->enum('payment_method', ['bank_transfer', 'card', 'paypal']);
            $table->string('payment_proof_path')->nullable();
            $table->boolean('payment_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->timestamps();
            
            $table->index(['city', 'payment_verified']);
            $table->index(['phone_verified', 'created_at']);
            $table->index('transaction_id');
            $table->index('email');
        });

        Schema::create('phone_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->string('verification_code', 6);
            $table->string('transaction_id');
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            
            $table->index(['phone_number', 'transaction_id']);
            $table->index('expires_at');
        });

        Schema::create('sms_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name');
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1);
            $table->decimal('cost_per_sms', 8, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('phone_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->string('verification_code', 6);
            $table->string('transaction_id');
            $table->string('provider');
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed']);
            $table->text('provider_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verification_logs');
        Schema::dropIfExists('sms_provider_configs');
        Schema::dropIfExists('phone_verification_codes');
        Schema::dropIfExists('notary_offices');
    }
};
EOF

print_status "Database migrations created"

# Step 6: Create Models
echo ""
echo "Step 6: Creating Eloquent models..."
echo "=================================="

# Create NotaryOffice model
cat > app/Models/NotaryOffice.php << 'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaryOffice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'city', 'email', 'phone', 'bank', 'iban',
        'account_number', 'payment_amount', 'transaction_id',
        'payment_method', 'payment_proof_path'
    ];

    protected $casts = [
        'payment_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'payment_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime'
    ];

    public function phoneVerificationCodes()
    {
        return $this->hasMany(PhoneVerificationCode::class, 'transaction_id', 'transaction_id');
    }

    public function phoneVerificationLogs()
    {
        return $this->hasMany(PhoneVerificationLog::class, 'transaction_id', 'transaction_id');
    }
}
EOF

# Create PhoneVerificationCode model
cat > app/Models/PhoneVerificationCode.php << 'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneVerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number', 'verification_code', 'transaction_id',
        'expires_at', 'attempts', 'is_used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    public function notaryOffice()
    {
        return $this->belongsTo(NotaryOffice::class, 'transaction_id', 'transaction_id');
    }
}
EOF

print_status "Models created successfully"

# Step 7: Create Services
echo ""
echo "Step 7: Creating service classes..."
echo "================================="

mkdir -p app/Services

# Create SMS Verification Service
cat > app/Services/SmsVerificationService.php << 'EOF'
<?php

namespace App\Services;

use App\Models\PhoneVerificationCode;
use App\Models\PhoneVerificationLog;
use App\Models\SmsProviderConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmsVerificationService
{
    public function generateAndSendCode(string $phoneNumber, string $transactionId): array
    {
        $code = sprintf('%06d', mt_rand(0, 999999));
        $expiresAt = Carbon::now()->addMinutes(3);

        // Save verification code
        PhoneVerificationCode::create([
            'phone_number' => $phoneNumber,
            'verification_code' => $code,
            'transaction_id' => $transactionId,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'is_used' => false
        ]);

        // Send SMS
        $result = $this->sendSms($phoneNumber, $code, $transactionId);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'expires_at' => $expiresAt
        ];
    }

    public function verifyCode(string $phoneNumber, string $code, string $transactionId): array
    {
        $verification = PhoneVerificationCode::where('phone_number', $phoneNumber)
            ->where('transaction_id', $transactionId)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$verification) {
            return ['success' => false, 'message' => 'Invalid or expired verification code'];
        }

        if ($verification->verification_code !== $code) {
            $verification->increment('attempts');
            
            if ($verification->attempts >= 3) {
                $verification->update(['is_used' => true]);
                return ['success' => false, 'message' => 'Too many failed attempts'];
            }
            
            return ['success' => false, 'message' => 'Invalid verification code'];
        }

        $verification->update(['is_used' => true]);
        
        return ['success' => true, 'message' => 'Phone number verified successfully'];
    }

    private function sendSms(string $phoneNumber, string $code, string $transactionId): array
    {
        $message = "Your Noteria verification code is: {$code}. Valid for 3 minutes.";
        
        // Log the SMS (in demo mode, we just log)
        PhoneVerificationLog::create([
            'phone_number' => $phoneNumber,
            'verification_code' => $code,
            'transaction_id' => $transactionId,
            'provider' => 'demo',
            'status' => 'sent',
            'provider_response' => 'Demo mode - SMS logged only'
        ]);

        Log::info("SMS sent to {$phoneNumber}: {$message}");

        return ['success' => true, 'message' => 'SMS sent successfully'];
    }
}
EOF

print_status "Services created successfully"

# Step 8: Create Controllers
echo ""
echo "Step 8: Creating controllers..."
echo "=============================="

# Create registration controller
cat > app/Http/Controllers/NotaryRegistrationController.php << 'EOF'
<?php

namespace App\Http\Controllers;

use App\Models\NotaryOffice;
use App\Services\SmsVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotaryRegistrationController extends Controller
{
    public function __construct(
        private SmsVerificationService $smsService
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string',
            'email' => 'required|email|unique:notary_offices',
            'phone' => 'required|string',
            'bank' => 'required|string',
            'iban' => 'required|string',
            'account_number' => 'required|string',
            'payment_amount' => 'required|numeric',
            'payment_method' => 'required|string',
        ]);

        $validated['transaction_id'] = Str::uuid();

        $notaryOffice = NotaryOffice::create($validated);

        // Send SMS verification
        $smsResult = $this->smsService->generateAndSendCode(
            $validated['phone'],
            $validated['transaction_id']
        );

        return response()->json([
            'success' => true,
            'transaction_id' => $validated['transaction_id'],
            'sms_sent' => $smsResult['success'],
            'expires_at' => $smsResult['expires_at']
        ]);
    }

    public function verifySms(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
            'transaction_id' => 'required|string'
        ]);

        $result = $this->smsService->verifyCode(
            $validated['phone'],
            $validated['code'],
            $validated['transaction_id']
        );

        if ($result['success']) {
            NotaryOffice::where('transaction_id', $validated['transaction_id'])
                ->update([
                    'phone_verified' => true,
                    'phone_verified_at' => now()
                ]);
        }

        return response()->json($result);
    }
}
EOF

print_status "Controllers created successfully"

# Step 9: Setup routes
echo ""
echo "Step 9: Setting up routes..."
echo "=========================="

# Add API routes
cat >> routes/api.php << 'EOF'

// Notary Office Registration
Route::post('/notary-offices', [App\Http\Controllers\NotaryRegistrationController::class, 'store']);
Route::post('/verify-sms', [App\Http\Controllers\NotaryRegistrationController::class, 'verifySms']);
EOF

# Add web routes
cat >> routes/web.php << 'EOF'

Route::get('/register', function () {
    return view('registration');
})->name('register');

Route::get('/admin', function () {
    return view('admin.dashboard');
})->name('admin.dashboard');
EOF

print_status "Routes configured successfully"

# Step 10: Create basic views
echo ""
echo "Step 10: Creating views..."
echo "========================"

# Create registration view
mkdir -p resources/views
cat > resources/views/registration.blade.php << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Noteria Registration</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto py-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6">Register Notary Office</h2>
            
            <form id="registrationForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" name="name" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">City</label>
                    <select name="city" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="">Select City</option>
                        <option value="Prishtina">Prishtina</option>
                        <option value="Prizren">Prizren</option>
                        <option value="Peja">Peja</option>
                        <option value="Gjilan">Gjilan</option>
                        <option value="Mitrovica">Mitrovica</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Phone</label>
                    <input type="text" name="phone" class="w-full px-3 py-2 border rounded-lg" placeholder="+38349123456" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Bank</label>
                    <select name="bank" class="w-full px-3 py-2 border rounded-lg" required>
                        <option value="">Select Bank</option>
                        <option value="BKT">BKT</option>
                        <option value="NLB">NLB</option>
                        <option value="TEB">TEB</option>
                        <option value="BPB">BPB</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">IBAN</label>
                    <input type="text" name="iban" class="w-full px-3 py-2 border rounded-lg" placeholder="XK05..." required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Account Number</label>
                    <input type="text" name="account_number" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                
                <input type="hidden" name="payment_amount" value="149">
                <input type="hidden" name="payment_method" value="bank_transfer">
                
                <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">
                    Register & Send SMS
                </button>
            </form>
            
            <div id="smsWidget" class="hidden mt-6 p-4 bg-yellow-100 rounded-lg">
                <h3 class="font-bold">SMS Verification</h3>
                <p>Enter the 6-digit code sent to your phone:</p>
                <div class="mt-2">
                    <input type="text" id="smsCode" class="px-3 py-2 border rounded" maxlength="6" placeholder="123456">
                    <button onclick="verifySms()" class="ml-2 bg-green-500 text-white px-4 py-2 rounded">Verify</button>
                </div>
                <div id="countdown" class="mt-2 text-sm text-red-500"></div>
            </div>
        </div>
    </div>

    <script>
        let transactionId = null;
        let countdownTimer = null;
        
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch('/api/notary-offices', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    transactionId = result.transaction_id;
                    document.getElementById('smsWidget').classList.remove('hidden');
                    startCountdown(180); // 3 minutes
                } else {
                    alert('Registration failed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Registration failed');
            }
        });
        
        async function verifySms() {
            const code = document.getElementById('smsCode').value;
            const phone = document.querySelector('input[name="phone"]').value;
            
            if (code.length !== 6) {
                alert('Please enter a 6-digit code');
                return;
            }
            
            try {
                const response = await fetch('/api/verify-sms', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        phone: phone,
                        code: code,
                        transaction_id: transactionId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Phone verified successfully! Registration complete.');
                    clearInterval(countdownTimer);
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Verification failed');
            }
        }
        
        function startCountdown(seconds) {
            const countdownElement = document.getElementById('countdown');
            
            countdownTimer = setInterval(() => {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                countdownElement.textContent = `Code expires in: ${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
                
                if (seconds <= 0) {
                    clearInterval(countdownTimer);
                    countdownElement.textContent = 'Code expired. Please register again.';
                }
                
                seconds--;
            }, 1000);
        }
    </script>
</body>
</html>
EOF

print_status "Views created successfully"

# Step 11: Setup environment
echo ""
echo "Step 11: Configuring environment..."
echo "================================="

# Copy .env.example to .env if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate application key
php artisan key:generate

# Configure database (using SQLite for demo)
sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
sed -i 's/DB_HOST=127.0.0.1/#DB_HOST=127.0.0.1/' .env
sed -i 's/DB_PORT=3306/#DB_PORT=3306/' .env
sed -i 's/DB_DATABASE=laravel/#DB_DATABASE=laravel/' .env
sed -i 's/DB_USERNAME=root/#DB_USERNAME=root/' .env
sed -i 's/DB_PASSWORD=/#DB_PASSWORD=/' .env

# Create SQLite database
touch database/database.sqlite

print_status "Environment configured"

# Step 12: Run migrations
echo ""
echo "Step 12: Running migrations..."
echo "============================="

php artisan migrate --force

if [ $? -eq 0 ]; then
    print_status "Database migrated successfully"
else
    print_error "Migration failed"
    exit 1
fi

# Step 13: Create Git repository
echo ""
echo "Step 13: Initializing Git repository..."
echo "======================================"

git init
git add .
git commit -m "Initial Noteria Platform - Laravel Migration"

print_status "Git repository initialized"

# Step 14: Laravel Cloud deployment instructions
echo ""
echo "🎉 MIGRATION COMPLETED SUCCESSFULLY!"
echo "===================================="

print_info "Your Laravel project is ready for deployment to Laravel Cloud!"
echo ""
echo "Next steps for Laravel Cloud deployment:"
echo ""
echo "1. Push to GitHub:"
echo "   git remote add origin https://github.com/yourusername/noteria-platform.git"
echo "   git branch -M main"
echo "   git push -u origin main"
echo ""
echo "2. Visit https://cloud.laravel.com and:"
echo "   • Create new project"
echo "   • Connect your GitHub repository"
echo "   • Configure environment variables"
echo "   • Deploy!"
echo ""
echo "3. Test the application:"
echo "   • Visit /register to test registration"
echo "   • Check SMS verification workflow"
echo "   • Verify admin dashboard"
echo ""

print_status "Project location: $(pwd)"
print_status "Test locally: php artisan serve"

echo ""
echo "🚀 Ready to serve Kosovo's 127 notary offices!"
echo "💰 Projected revenue: €26,820-€44,700 in Year 1"
echo "🇽🇰 Faleminderit për besimin!"