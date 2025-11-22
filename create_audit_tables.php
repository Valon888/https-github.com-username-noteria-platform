<?php
/**
 * Create Audit Trail Tables
 */

require_once 'db_connection.php';

// Create audit log table
$sql_audit_log = "CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    details JSON,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    INDEX idx_user_action (user_id, action)
)";

// Create compliance reports table
$sql_compliance = "CREATE TABLE IF NOT EXISTS compliance_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(100) NOT NULL,
    report_name VARCHAR(255),
    start_date DATE,
    end_date DATE,
    report_data JSON,
    generated_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id),
    INDEX idx_report_type (report_type),
    INDEX idx_created_at (created_at)
)";

// Create data retention policy table
$sql_retention = "CREATE TABLE IF NOT EXISTS data_retention_policy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_type VARCHAR(100) NOT NULL,
    retention_days INT DEFAULT 365,
    auto_delete BOOLEAN DEFAULT TRUE,
    last_cleanup DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_data_type (data_type)
)";

// Execute table creations
$results = [];

if ($conn->query($sql_audit_log) === TRUE) {
    $results[] = "✅ Audit Log table created successfully!";
} else {
    $results[] = "❌ Error creating Audit Log table: " . $conn->error;
}

if ($conn->query($sql_compliance) === TRUE) {
    $results[] = "✅ Compliance Reports table created successfully!";
} else {
    $results[] = "❌ Error creating Compliance Reports table: " . $conn->error;
}

if ($conn->query($sql_retention) === TRUE) {
    $results[] = "✅ Data Retention Policy table created successfully!";
} else {
    $results[] = "❌ Error creating Data Retention Policy table: " . $conn->error;
}

// Insert default retention policies
$retention_policies = [
    ['user_logs', 365],
    ['payment_records', 2555], // 7 years for financial records
    ['video_calls', 365],
    ['documents', 1825], // 5 years
    ['signatures', 2555], // 7 years for legal documents
    ['security_events', 730] // 2 years
];

foreach ($retention_policies as $policy) {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO data_retention_policy (data_type, retention_days) 
        VALUES (?, ?)
    ");
    $stmt->bind_param("si", $policy[0], $policy[1]);
    $stmt->execute();
    $stmt->close();
}

$results[] = "✅ Default retention policies inserted!";

$conn->close();

// Display results
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo ".success { color: green; } .error { color: red; }";
echo "</style>";

foreach ($results as $result) {
    if (strpos($result, '✅') === 0) {
        echo "<div class='success'>$result</div>";
    } else {
        echo "<div class='error'>$result</div>";
    }
}

?>
