<?php
require_once 'config.php';
require_once 'confidb.php';

try {
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM documents');
    $result = $stmt->fetch();
    echo 'Total documents in database: ' . $result['total'] . PHP_EOL;

    if ($result['total'] > 0) {
        $stmt = $pdo->query('SELECT id, user_id, file_path, file_size, file_type, created_at FROM documents ORDER BY created_at DESC LIMIT 5');
        $docs = $stmt->fetchAll();
        echo 'Last 5 documents:' . PHP_EOL;
        foreach ($docs as $doc) {
            echo '- ID: ' . $doc['id'] . ', User: ' . $doc['user_id'] . ', File: ' . $doc['file_path'] . ', Size: ' . $doc['file_size'] . ' bytes, Type: ' . $doc['file_type'] . ', Date: ' . $doc['created_at'] . PHP_EOL;
        }
    } else {
        echo 'No documents found in database.' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>