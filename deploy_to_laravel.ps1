# Noteria Platform - Laravel Cloud Deployment (Windows PowerShell Script)

Write-Host "🚀 Starting Noteria Platform Laravel Migration..." -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Green

# Function to print colored output
function Write-Success {
    param([string]$Message)
    Write-Host "✓ $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "⚠ $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "✗ $Message" -ForegroundColor Red
}

function Write-Info {
    param([string]$Message)
    Write-Host "ℹ $Message" -ForegroundColor Blue
}

# Check if composer is installed
try {
    composer --version | Out-Null
    Write-Success "Composer is available"
} catch {
    Write-Error "Composer is not installed. Please install Composer first."
    exit 1
}

# Check if npm is installed
try {
    npm --version | Out-Null
    Write-Success "NPM is available"
} catch {
    Write-Error "NPM is not installed. Please install Node.js and NPM first."
    exit 1
}

# Check if git is installed
try {
    git --version | Out-Null
    Write-Success "Git is available"
} catch {
    Write-Error "Git is not installed. Please install Git first."
    exit 1
}

Write-Info "All required tools are available ✓"

# Step 1: Create new Laravel project
Write-Host ""
Write-Host "Step 1: Creating Laravel project..." -ForegroundColor Yellow
Write-Host "==================================" -ForegroundColor Yellow

$ProjectName = Read-Host "Enter project name (default: noteria-platform)"
if ([string]::IsNullOrEmpty($ProjectName)) {
    $ProjectName = "noteria-platform"
}

if (Test-Path $ProjectName) {
    Write-Warning "Directory $ProjectName already exists."
    $overwrite = Read-Host "Do you want to continue and overwrite? (y/N)"
    if ($overwrite -ne "y" -and $overwrite -ne "Y") {
        Write-Error "Deployment cancelled."
        exit 1
    }
    Remove-Item -Recurse -Force $ProjectName
}

Write-Info "Creating Laravel project: $ProjectName"
composer create-project laravel/laravel $ProjectName

if ($LASTEXITCODE -eq 0) {
    Write-Success "Laravel project created successfully"
} else {
    Write-Error "Failed to create Laravel project"
    exit 1
}

Set-Location $ProjectName

# Step 2: Install required packages
Write-Host ""
Write-Host "Step 2: Installing required packages..." -ForegroundColor Yellow
Write-Host "======================================" -ForegroundColor Yellow

Write-Info "Installing Jetstream with Livewire..."
composer require laravel/jetstream

Write-Info "Installing SMS providers..."
composer require twilio/sdk
composer require infobip/infobip-api-php-client

Write-Info "Installing additional packages..."
composer require laravel/cashier
composer require spatie/laravel-permission
composer require laravel/horizon
composer require laravel/telescope
composer require spatie/laravel-backup
composer require intervention/image
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf

if ($LASTEXITCODE -eq 0) {
    Write-Success "All packages installed successfully"
} else {
    Write-Error "Failed to install some packages"
    exit 1
}

# Step 3: Setup Jetstream
Write-Host ""
Write-Host "Step 3: Setting up Jetstream..." -ForegroundColor Yellow
Write-Host "===============================" -ForegroundColor Yellow

php artisan jetstream:install livewire

if ($LASTEXITCODE -eq 0) {
    Write-Success "Jetstream installed successfully"
} else {
    Write-Error "Failed to install Jetstream"
    exit 1
}

# Step 4: Install npm dependencies and build
Write-Host ""
Write-Host "Step 4: Building frontend assets..." -ForegroundColor Yellow
Write-Host "==================================" -ForegroundColor Yellow

npm install
npm run build

if ($LASTEXITCODE -eq 0) {
    Write-Success "Frontend assets built successfully"
} else {
    Write-Error "Failed to build frontend assets"
    exit 1
}

# Step 5: Create migration file
Write-Host ""
Write-Host "Step 5: Setting up database structure..." -ForegroundColor Yellow
Write-Host "=======================================" -ForegroundColor Yellow

$timestamp = Get-Date -Format "yyyy_MM_dd_HHmmss"
$migrationFile = "database/migrations/${timestamp}_create_noteria_tables.php"

@"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notary_offices', function (Blueprint `$table) {
            `$table->id();
            `$table->string('name');
            `$table->string('city');
            `$table->string('email')->unique();
            `$table->string('phone');
            `$table->string('bank');
            `$table->string('iban');
            `$table->string('account_number');
            `$table->decimal('payment_amount', 10, 2);
            `$table->string('transaction_id')->unique();
            `$table->enum('payment_method', ['bank_transfer', 'card', 'paypal']);
            `$table->string('payment_proof_path')->nullable();
            `$table->boolean('payment_verified')->default(false);
            `$table->boolean('phone_verified')->default(false);
            `$table->timestamp('phone_verified_at')->nullable();
            `$table->timestamp('payment_verified_at')->nullable();
            `$table->timestamps();
            
            `$table->index(['city', 'payment_verified']);
            `$table->index(['phone_verified', 'created_at']);
            `$table->index('transaction_id');
            `$table->index('email');
        });

        Schema::create('phone_verification_codes', function (Blueprint `$table) {
            `$table->id();
            `$table->string('phone_number');
            `$table->string('verification_code', 6);
            `$table->string('transaction_id');
            `$table->timestamp('expires_at');
            `$table->integer('attempts')->default(0);
            `$table->boolean('is_used')->default(false);
            `$table->timestamps();
            
            `$table->index(['phone_number', 'transaction_id']);
            `$table->index('expires_at');
        });

        Schema::create('sms_provider_configs', function (Blueprint `$table) {
            `$table->id();
            `$table->string('provider_name');
            `$table->json('config');
            `$table->boolean('is_active')->default(true);
            `$table->integer('priority')->default(1);
            `$table->decimal('cost_per_sms', 8, 4)->nullable();
            `$table->timestamps();
        });

        Schema::create('phone_verification_logs', function (Blueprint `$table) {
            `$table->id();
            `$table->string('phone_number');
            `$table->string('verification_code', 6);
            `$table->string('transaction_id');
            `$table->string('provider');
            `$table->enum('status', ['pending', 'sent', 'delivered', 'failed']);
            `$table->text('provider_response')->nullable();
            `$table->timestamps();
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
"@ | Out-File -FilePath $migrationFile -Encoding UTF8

Write-Success "Database migrations created"

# Continue with rest of setup...
Write-Host ""
Write-Host "🎉 BASIC SETUP COMPLETED!" -ForegroundColor Green
Write-Host "=========================" -ForegroundColor Green

Write-Info "Next steps:"
Write-Host "1. Run: php artisan migrate" -ForegroundColor Cyan
Write-Host "2. Run: php artisan serve" -ForegroundColor Cyan
Write-Host "3. Visit: http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "For full Laravel Cloud deployment, run the complete setup script." -ForegroundColor Yellow
Write-Host ""
Write-Success "Project location: $(Get-Location)"

Write-Host ""
Write-Host "🚀 Ready for Laravel Cloud deployment!" -ForegroundColor Green
Write-Host "💰 Target: Kosovo's 127 notary offices" -ForegroundColor Green
Write-Host "🇽🇰 Success is coming!" -ForegroundColor Green