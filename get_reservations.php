<?php require 'confidb.php'; $stmt = $pdo->query('DESCRIBE reservations'); $result = $stmt->fetchAll(PDO::FETCH_ASSOC); print_r($result); ?>
