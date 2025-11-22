<?php
/**
 * Create DocuSign Envelopes Table
 */

require_once 'db_connection.php';

$sql = "CREATE TABLE IF NOT EXISTS docusign_envelopes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    call_id VARCHAR(50) NOT NULL,
    envelope_id VARCHAR(100) NOT NULL UNIQUE,
    document_name VARCHAR(255) NOT NULL,
    signer_email VARCHAR(255) NOT NULL,
    signer_name VARCHAR(255) NOT NULL,
    status ENUM('sent', 'delivered', 'signed', 'completed', 'declined', 'voided') DEFAULT 'sent',
    signed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES video_calls(call_id),
    INDEX idx_envelope_id (envelope_id),
    INDEX idx_status (status)
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ DocuSign Envelopes table created successfully!";
} else {
    echo "❌ Error creating table: " . $conn->error;
}

$conn->close();
?>
