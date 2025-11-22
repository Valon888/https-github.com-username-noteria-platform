<?php
// Database structure checker for abonim-related tables
// This is a temporary file to help diagnose database structure issues

require_once 'config.php';

echo "<h2>Abonim Tables Structure Check</h2>";

try {
    // Check zyrat table structure
    $tables = ['zyrat', 'payment_logs', 'noteri_abonimet', 'abonimet'];
    
    foreach ($tables as $table) {
        echo "<h3>Table: {$table}</h3>";
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() == 0) {
            echo "<p style='color:red'>Table '{$table}' does not exist!</p>";
            continue;
        }
        
        // List columns
        echo "<h4>Columns:</h4>";
        echo "<ul>";
        $columns = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "<li><strong>{$column['Field']}</strong> - {$column['Type']} ";
            if ($column['Key'] == 'PRI') echo "(PRIMARY KEY) ";
            if ($column['Null'] == 'NO') echo "(NOT NULL) ";
            echo "</li>";
        }
        echo "</ul>";
    }
    
    // Check if abonim_id column exists in specific tables
    $checkTables = ['zyrat', 'payment_logs'];
    foreach ($checkTables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'abonim_id'");
        echo "<p>Table '{$table}' " . ($stmt->rowCount() > 0 ? 
            "<span style='color:green'>HAS</span>" : 
            "<span style='color:red'>DOES NOT HAVE</span>") . 
            " the 'abonim_id' column.</p>";
    }
    
    // Show abonimet table content
    echo "<h3>All Available Abonimet:</h3>";
    try {
        $records = $pdo->query("SELECT * FROM abonimet")->fetchAll(PDO::FETCH_ASSOC);
        if (count($records) > 0) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr>";
            foreach (array_keys($records[0]) as $key) {
                echo "<th>{$key}</th>";
            }
            echo "</tr>";
            
            foreach ($records as $record) {
                echo "<tr>";
                foreach ($record as $value) {
                    echo "<td>" . (strlen($value) > 100 ? substr($value, 0, 100) . "..." : $value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No abonimet records found.</p>";
        }
    } catch (PDOException $e) {
        echo "<p>Error retrieving abonimet records: " . $e->getMessage() . "</p>";
    }
    
    // Show session values
    echo "<h3>Current Session Abonim Values:</h3>";
    echo "<pre>";
    if (isset($_SESSION['selected_abonim'])) {
        print_r($_SESSION['selected_abonim']);
    } else {
        echo "No session abonim data found.";
    }
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}

// Add abonim_id column to tables if missing
echo "<h3>Database Fix Actions:</h3>";
echo "<form method='post'>";
echo "<input type='hidden' name='fix_action' value='add_column'>";
echo "<button type='submit' style='padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer;'>Add missing abonim_id column to tables</button>";
echo "</form>";

// Process fix action
if (isset($_POST['fix_action']) && $_POST['fix_action'] == 'add_column') {
    try {
        $pdo->beginTransaction();
        
        // Check and add column to zyrat table
        $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'abonim_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE zyrat ADD COLUMN abonim_id INT(11) NULL");
            echo "<p style='color:green'>Added abonim_id column to zyrat table.</p>";
        }
        
        // Check and add column to payment_logs table
        $stmt = $pdo->query("SHOW COLUMNS FROM payment_logs LIKE 'abonim_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE payment_logs ADD COLUMN abonim_id INT(11) NULL");
            echo "<p style='color:green'>Added abonim_id column to payment_logs table.</p>";
        }
        
        $pdo->commit();
        echo "<p style='color:green'><strong>Database structure fixed successfully!</strong></p>";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<p style='color:red'>Error fixing database structure: " . $e->getMessage() . "</p>";
    }
}

echo "<p><small>Generated: " . date('Y-m-d H:i:s') . "</small></p>";
?>

<p><a href="javascript:history.back()">Go Back</a> | <a href="zyrat_register.php">Return to Registration</a></p>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2 { color: #333; }
    h3 { color: #3366cc; margin-top: 20px; border-bottom: 1px solid #ccc; }
    h4 { margin-bottom: 5px; }
    ul { background-color: #f9f9f9; padding: 10px 20px; border-radius: 5px; }
    li { margin-bottom: 5px; }
    pre { background-color: #f0f0f0; padding: 10px; overflow: auto; border-radius: 3px; }
    .success { color: green; }
    .error { color: red; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    th { background-color: #f2f2f2; text-align: left; }
    tr:nth-child(even) { background-color: #f9f9f9; }
</style>