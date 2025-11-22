<?php
// video_call_notifications_poll.php
require_once 'confidb.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Akses i palejuar.');
}

$user_id = $_SESSION['user_id'];
$roli = null;
$stmt = $pdo->prepare('SELECT roli FROM users WHERE id = ?');
$stmt->execute([$user_id]);
if ($row = $stmt->fetch()) {
    $roli = $row['roli'];
}

if ($roli === 'zyra') {
    // Noteri: njoftime për thirrje të reja
    $stmt = $pdo->prepare("SELECT vc.id, vc.call_datetime, vc.subject, u.emri AS user_name, u.mbiemri AS user_surname
        FROM video_calls vc
        JOIN users u ON vc.user_id = u.id
        WHERE vc.notary_id = ? AND vc.notification_status = 'pending' AND vc.call_datetime > NOW()");
    $stmt->execute([$user_id]);
    if ($stmt->rowCount() > 0) {
        echo '<table><tr><th>Përdoruesi</th><th>Data & Ora</th><th>Tema</th><th>Veprime</th></tr>';
        while ($row = $stmt->fetch()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['user_name'] . ' ' . $row['user_surname']) . '</td>';
            echo '<td>' . date('d.m.Y H:i', strtotime($row['call_datetime'])) . '</td>';
            echo '<td>' . htmlspecialchars($row['subject']) . '</td>';
            echo '<td>';
            echo '<button class="btn-accept-call" data-call-id="' . $row['id'] . '"><i class="fas fa-check"></i> Prano</button> ';
            echo '<button class="btn-reject-call" data-call-id="' . $row['id'] . '"><i class="fas fa-times"></i> Refuzo</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="no-data">Nuk ka njoftime të reja për video thirrje.</div>';
    }
} elseif ($roli === 'perdorues') {
    // Përdoruesi: statusi i thirrjeve të tij
    $stmt = $pdo->prepare("SELECT vc.call_datetime, vc.subject, vc.notification_status, z.emri AS notary_name, z.mbiemri AS notary_surname
        FROM video_calls vc
        JOIN users z ON vc.notary_id = z.id
        WHERE vc.user_id = ? AND vc.call_datetime > NOW() ORDER BY vc.call_datetime DESC");
    $stmt->execute([$user_id]);
    if ($stmt->rowCount() > 0) {
        echo '<table><tr><th>Noteri</th><th>Data & Ora</th><th>Tema</th><th>Statusi</th></tr>';
        while ($row = $stmt->fetch()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['notary_name'] . ' ' . $row['notary_surname']) . '</td>';
            echo '<td>' . date('d.m.Y H:i', strtotime($row['call_datetime'])) . '</td>';
            echo '<td>' . htmlspecialchars($row['subject']) . '</td>';
            echo '<td>';
            if ($row['notification_status'] === 'pending') {
                echo '<span class="status-pending">Në pritje</span>';
            } elseif ($row['notification_status'] === 'accepted') {
                echo '<span class="status-accepted">Pranuar</span>';
            } elseif ($row['notification_status'] === 'rejected') {
                echo '<span class="status-rejected">Refuzuar</span>';
            } else {
                echo htmlspecialchars($row['notification_status']);
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="no-data">Nuk keni video thirrje të planifikuara.</div>';
    }
}
