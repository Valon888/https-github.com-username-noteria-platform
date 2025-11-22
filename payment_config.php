<?php
// Konfigurimi i sistemit të verifikimit të pagesave
// filepath: d:\xampp\htdocs\noteria\payment_config.php

return [
    // Konfigurimi i API-ve të bankave
    'bank_apis' => [
        'Banka Ekonomike' => [
            'endpoint' => 'https://api.bek.com.mk/payment/verify',
            'api_key' => getenv('BEK_API_KEY') ?: 'test_key',
            'timeout' => 30,
            'retry_attempts' => 3
        ],
        'Banka për Biznes' => [
            'endpoint' => 'https://api.bpb-bank.com/payment/verify',
            'api_key' => getenv('BPB_API_KEY') ?: 'test_key',
            'timeout' => 30,
            'retry_attempts' => 3
        ],
        'Banka Kombëtare Tregtare (BKT)' => [
            'endpoint' => 'https://api.bkt.com.mk/payment/verify',
            'api_key' => getenv('BKT_API_KEY') ?: 'test_key',
            'timeout' => 30,
            'retry_attempts' => 3
        ],
        'ProCredit Bank' => [
            'endpoint' => 'https://api.procreditbank.com.mk/payment/verify',
            'api_key' => getenv('PROCREDIT_API_KEY') ?: 'test_key',
            'timeout' => 30,
            'retry_attempts' => 3
        ],
        'Raiffeisen Bank' => [
            'endpoint' => 'https://api.raiffeisen.mk/payment/verify',
            'api_key' => getenv('RAIFFEISEN_API_KEY') ?: 'test_key',
            'timeout' => 30,
            'retry_attempts' => 3
        ],
        'TEB Bank' => [
            'endpoint' => 'https://api.tebbank.com/payment/verify',
            'api_key' => getenv('TEB_API_KEY') ?: 'test_key',
            'timeout' => 30,
            'retry_attempts' => 3
        ]
    ],
    
    // Konfigurimi i PayPal
    'paypal' => [
        'client_id' => getenv('PAYPAL_CLIENT_ID') ?: 'test_client_id',
        'client_secret' => getenv('PAYPAL_CLIENT_SECRET') ?: 'test_secret',
        'mode' => getenv('PAYPAL_MODE') ?: 'sandbox', // sandbox ose live
        'endpoint' => getenv('PAYPAL_MODE') === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com'
    ],
    
    // Konfigurimi i procesorëve të kartave
    'card_processors' => [
        'stripe' => [
            'secret_key' => getenv('STRIPE_SECRET_KEY') ?: 'test_key',
            'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: 'test_key',
            'endpoint' => 'https://api.stripe.com/v1'
        ],
        'square' => [
            'access_token' => getenv('SQUARE_ACCESS_TOKEN') ?: 'test_token',
            'application_id' => getenv('SQUARE_APPLICATION_ID') ?: 'test_app_id',
            'environment' => getenv('SQUARE_ENVIRONMENT') ?: 'sandbox'
        ]
    ],
    
    // Rregullat e sigurisë
    'security_rules' => [
        'max_daily_transactions_per_email' => 5,
        'max_amount_per_transaction' => 10000,
        'min_amount_per_transaction' => 10,
        'max_verification_attempts' => 3,
        'transaction_timeout_minutes' => 30,
        'duplicate_check_hours' => 24,
        'require_payment_proof' => true,
        'max_file_size_mb' => 5,
        'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
        'encryption_algorithm' => 'AES-256-CBC'
    ],
    
    // Rregullat e validimit
    'validation_rules' => [
        'iban' => [
            'required_country_code' => 'XK',
            'min_length' => 20,
            'max_length' => 21,
            'check_mod97' => true
        ],
        'phone' => [
            'required_prefix' => '+383',
            'total_length' => 12
        ],
        'email' => [
            'require_confirmation' => true,
            'blocked_domains' => ['tempmail.com', '10minutemail.com']
        ]
    ],
    
    // Konfigurimi i email-ave
    'email' => [
        'smtp_host' => getenv('SMTP_HOST') ?: 'localhost',
        'smtp_port' => getenv('SMTP_PORT') ?: 587,
        'smtp_username' => getenv('SMTP_USERNAME') ?: '',
        'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
        'from_email' => getenv('FROM_EMAIL') ?: 'noreply@noteria.com',
        'from_name' => 'Noteria Platform'
    ],
    
    // Mesazhet e gabimeve në shqip
    'error_messages' => [
        'invalid_transaction_id' => 'ID e transaksionit nuk është e vlefshme.',
        'payment_not_verified' => 'Pagesa nuk mund të verifikohet. Kontrolloni të dhënat.',
        'duplicate_payment' => 'Një pagesë me të njëjtën shumë është bërë kohët e fundit.',
        'amount_too_low' => 'Shuma e pagesës është shumë e ulët.',
        'amount_too_high' => 'Shuma e pagesës tejkalon kufirin maksimal.',
        'invalid_iban' => 'IBAN nuk është i vlefshëm për Kosovën.',
        'invalid_file_type' => 'Tipi i file nuk është i lejuar.',
        'file_too_large' => 'File është shumë i madh.',
        'api_timeout' => 'Verifikimi i pagesës ka tejkaluar kohën e lejuar.',
        'bank_api_error' => 'Gabim në komunikim me bankën.',
        'too_many_attempts' => 'Shumë përpjekje të dështuara. Provoni më vonë.'
    ],
    
    // Mesazhet e suksesit
    'success_messages' => [
        'payment_verified' => 'Pagesa u verifikua me sukses!',
        'registration_complete' => 'Regjistrimi u përfundua me sukses.',
        'email_sent' => 'Email-i i konfirmimit u dërgua.',
        'file_uploaded' => 'Dëshmi e pagesës u ngarkua me sukses.'
    ],
    
    // Konfigurimi i log-ave
    'logging' => [
        'level' => getenv('LOG_LEVEL') ?: 'INFO',
        'file' => __DIR__ . '/logs/payment_system.log',
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'backup_count' => 5,
        'log_sensitive_data' => false // KURRË mos aktivizoni në production
    ],
    
    // URLs për redirect pas pagesës
    'redirect_urls' => [
        'success' => '/success.php',
        'failure' => '/failure.php',
        'pending' => '/pending.php'
    ],
    
    // Kohëzgjatja e sesioneve
    'session' => [
        'payment_session_timeout' => 1800, // 30 minuta
        'max_concurrent_sessions' => 3
    ]
];
?>