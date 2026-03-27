<?php
// notifications_helper.php

/**
 * Krijon një njoftim të ri për përdoruesin
 * 
 * @param PDO $pdo Lidhja me databazën
 * @param int $user_id ID e përdoruesit
 * @param string $title Titulli i njoftimit
 * @param string $message Mesazhi i njoftimit
 * @param string $type Lloji (success, info, warning, error)
 * @return bool
 */
function createNotification($pdo, $user_id, $title, $message, $type = 'info') {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)");
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (Exception $e) {
        error_log("Gabim në krijimin e njoftimit: " . $e->getMessage());
        return false;
    }
}

/**
 * Shënon njoftimet si të lexuara
 */
function markNotificationsAsRead($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
}
?>
