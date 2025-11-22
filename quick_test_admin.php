<?php
require_once 'confidb.php';

echo "=== QUICK ADMIN TEST ===\n\n";

// Test 1: Check if admins table exists and has data
echo "[1] Check admins table:\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    $count = $stmt->fetchColumn();
    echo "  Total admins: " . $count . "\n";
    
    if ($count == 0) {
        echo "  WARNING: No admins found!\n";
    } else {
        $stmt = $pdo->query("SELECT id, email, emri FROM admins LIMIT 3");
        while ($row = $stmt->fetch()) {
            echo "    - ID " . $row['id'] . ": " . $row['email'] . " (" . $row['emri'] . ")\n";
        }
    }
} catch (PDOException $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Check password hash for admin@noteria.al
echo "[2] Check admin@noteria.al password:\n";
try {
    $stmt = $pdo->prepare("SELECT id, email, password FROM admins WHERE email = ?");
    $stmt->execute(['admin@noteria.al']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "  Found: " . $admin['email'] . "\n";
        echo "  Password hash: " . substr($admin['password'], 0, 30) . "...\n";
        
        // Test password
        $test_pwd = "Admin@2025";
        if (password_verify($test_pwd, $admin['password'])) {
            echo "  Password verification: OK\n";
        } else {
            echo "  Password verification: FAILED\n";
        }
    } else {
        echo "  ERROR: admin@noteria.al not found\n";
    }
} catch (PDOException $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Check isDeveloper function
echo "[3] Check isDeveloper function:\n";
require_once 'developer_config.php';
$isDev = isDeveloper(1, 'admin@noteria.al');
echo "  isDeveloper(1, 'admin@noteria.al'): " . ($isDev ? "TRUE" : "FALSE") . "\n";
echo "\n";

echo "=== END TEST ===\n";
?>
