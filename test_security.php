<?php
// test_security.php
// Ky skript simulon kërkesa me IP të ndryshme për të testuar SecurityHeaders.php

$testCases = [
    // [IP, Pershkrimi]
    ['185.220.101.1', 'VPN IP nga lista (duhet të bllokohet nëse vendi është i bllokuar)'],
    ['8.8.8.8', 'Google DNS (duhet të lejohet nëse vendi nuk është i bllokuar)'],
    ['37.123.222.1', 'IP nga Serbia (duhet të bllokohet)'],
    ['104.244.72.115', 'VPN IP nga lista (duhet të bllokohet nëse vendi është i bllokuar)'],
    ['217.24.17.1', 'IP nga Shqipëria (duhet të lejohet)'],
];

foreach ($testCases as $case) {
    $ip = $case[0];
    $desc = $case[1];
    // Simulo $_SERVER['REMOTE_ADDR']
    $_SERVER['REMOTE_ADDR'] = $ip;
    ob_start();
    include 'SecurityHeaders.php';
    $output = ob_get_clean();
    echo "Test: $desc\n";
    echo "IP: $ip\n";
    echo "Rezultati: $output\n";
    echo str_repeat('-', 40) . "\n";
}
