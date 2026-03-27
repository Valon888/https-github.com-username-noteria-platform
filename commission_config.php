<?php
/**
 * Platform Commission Configuration
 * Kommision i Platformës - Varësisht nga Metodat e Pagesës
 */

return [
    // Payment methods with commission rates (%)
    "bank_transfer" => [
        "name_sq" => "Transferim Bankomi",
        "name_en" => "Bank Transfer",
        "commission" => 2.0,  // 2% commission
    ],
    "card" => [
        "name_sq" => "Kartë Krediti/Debiti",
        "name_en" => "Credit/Debit Card",
        "commission" => 3.5,  // 3.5% commission
    ],
    "paysera" => [
        "name_sq" => "Paysera",
        "name_en" => "Paysera",
        "commission" => 4.0,  // 4% commission
    ],
    "paypal" => [
        "name_sq" => "PayPal",
        "name_en" => "PayPal",
        "commission" => 4.0,  // 4% commission
    ],
    "cash" => [
        "name_sq" => "Para në Dorë",
        "name_en" => "Cash in Hand",
        "commission" => 5.0,  // 5% commission
    ],
];
