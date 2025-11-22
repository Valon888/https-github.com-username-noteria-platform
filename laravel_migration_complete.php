<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create notary_offices table (main registration table)
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
            
            // Indexes for performance
            $table->index(['city', 'payment_verified']);
            $table->index(['phone_verified', 'created_at']);
            $table->index('transaction_id');
            $table->index('email');
        });

        // Create phone_verification_codes table
        Schema::create('phone_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->string('verification_code', 6);
            $table->string('transaction_id');
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['phone_number', 'transaction_id']);
            $table->index('expires_at');
            $table->index('verification_code');
        });

        // Create sms_provider_configs table
        Schema::create('sms_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name'); // 'twilio', 'infobip', 'vala', 'ipko'
            $table->json('config'); // Encrypted configuration
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1); // Higher number = higher priority
            $table->decimal('cost_per_sms', 8, 4)->nullable();
            $table->integer('daily_limit')->nullable();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->timestamps();
            
            $table->index(['is_active', 'priority']);
        });

        // Create phone_verification_logs table
        Schema::create('phone_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->string('verification_code', 6);
            $table->string('transaction_id');
            $table->string('provider'); // Which SMS provider was used
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed']);
            $table->text('provider_response')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->decimal('cost', 8, 4)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            // Indexes for analytics and performance
            $table->index(['phone_number', 'created_at']);
            $table->index(['provider', 'status']);
            $table->index('transaction_id');
            $table->index('sent_at');
        });

        // Create subscription_plans table for future use
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'Basic', 'Professional', 'Enterprise'
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 8, 2); // Monthly price in EUR
            $table->json('features'); // List of features
            $table->integer('sms_limit_monthly')->nullable();
            $table->boolean('priority_support')->default(false);
            $table->boolean('advanced_analytics')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create user_subscriptions table for future use
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['active', 'cancelled', 'expired', 'suspended']);
            $table->decimal('amount_paid', 8, 2);
            $table->string('payment_method')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('ends_at');
        });

        // Create system_settings table
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type')->default('string'); // 'string', 'json', 'boolean', 'integer'
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create audit_logs table for security
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable(); // 'admin', 'user', 'system'
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action'); // 'created', 'updated', 'deleted', 'verified'
            $table->string('model_type'); // 'NotaryOffice', 'PhoneVerificationCode'
            $table->unsignedBigInteger('model_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
            $table->index(['user_type', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('phone_verification_logs');
        Schema::dropIfExists('sms_provider_configs');
        Schema::dropIfExists('phone_verification_codes');
        Schema::dropIfExists('notary_offices');
    }
};