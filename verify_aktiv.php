<?php require 'confidb.php'; $stmt = $pdo->query('SELECT id, emri, mbiemri, busy, aktiv FROM users LIMIT 5'); $users = $stmt->fetchAll(PDO::FETCH_ASSOC); print_r($users); ?>
