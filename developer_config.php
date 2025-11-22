<?php
/**
 * Developer Configuration
 * Konfigurimi i zhvilluesve të platformës
 */

// Lista e ID-ve të adminëve që janë zhvillues
$DEVELOPER_IDS = [
    1, // Admin me ID 1
    2, // Admin i ri me ID 2
    3, // Admin i tretë me ID 3
    // Shtoni ID-të e tjera të zhvilluesve këtu
];

// Lista e email-ave të zhvilluesve (nëse përdoret sistemi i email-ave)
$DEVELOPER_EMAILS = [
    'admin@noteria.al',
    'developer@noteria.com', 
    'dev@noteria.com',
    'support@noteria.com',
    'newdev@noteria.com', // Email i zhvilluesit të ri
    // Shtoni email-at e tjera të zhvilluesve këtu
];

/**
 * Kontrollon nëse një admin është zhvillues
 */
function isDeveloper($adminId, $adminEmail = null) {
    global $DEVELOPER_IDS, $DEVELOPER_EMAILS;
    
    // Kontrollo me ID
    if (in_array($adminId, $DEVELOPER_IDS)) {
        return true;
    }
    
    // Kontrollo me email nëse është dhënë
    if ($adminEmail && in_array($adminEmail, $DEVELOPER_EMAILS)) {
        return true;
    }
    
    return false;
}

/**
 * Merr listën e të gjithë zhvilluesve
 */
function getDevelopers() {
    global $DEVELOPER_IDS, $DEVELOPER_EMAILS;
    
    return [
        'ids' => $DEVELOPER_IDS,
        'emails' => $DEVELOPER_EMAILS
    ];
}

/**
 * Shton një zhvillues të ri
 */
function addDeveloper($adminId, $adminEmail = null) {
    global $DEVELOPER_IDS, $DEVELOPER_EMAILS;
    
    if (!in_array($adminId, $DEVELOPER_IDS)) {
        $DEVELOPER_IDS[] = $adminId;
    }
    
    if ($adminEmail && !in_array($adminEmail, $DEVELOPER_EMAILS)) {
        $DEVELOPER_EMAILS[] = $adminEmail;
    }
    
    // Këtu mund të shtoni logjikë për të ruajtur në databazë ose file
    return true;
}

// Eksportimi për përdorim në skedarë të tjerë
if (isset($pdo)) {
    // Nëse ka lidhje me databazë, mund të ruajmë konfigurimin atje
    try {
        $configStmt = $pdo->prepare("
            INSERT INTO billing_config (config_key, config_value, description) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
        ");
        
        $configStmt->execute([
            'developer_ids', 
            implode(',', $DEVELOPER_IDS), 
            'ID-të e adminëve zhvillues',
            implode(',', $DEVELOPER_IDS)
        ]);
        
        $configStmt->execute([
            'developer_emails', 
            implode(',', $DEVELOPER_EMAILS), 
            'Email-at e zhvilluesve',
            implode(',', $DEVELOPER_EMAILS)
        ]);
        
    } catch (PDOException $e) {
        // Nëse ka gabim me databazën, vazhdo pa ruajtur
    }
}
?>