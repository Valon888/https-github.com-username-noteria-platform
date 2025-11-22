<?php require 'confidb.php'; $stmt = $pdo->query('DESCRIBE users'); $result = $stmt->fetchAll(PDO::FETCH_ASSOC); print_r($result); ?>
