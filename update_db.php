<?php
// Create video_calls table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS video_calls (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    notary_id INT(11) NOT NULL,
    call_datetime DATETIME NOT NULL,
    room_id VARCHAR(64) NOT NULL,
    subject VARCHAR(255),
    status ENUM('scheduled', 'in-progress', 'ended', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME NULL
)";

require_once 'db_connection.php';
$conn->query($sql);

// Add notification_status column if it does not exist
$checkCol = $conn->query("SHOW COLUMNS FROM video_calls LIKE 'notification_status'");
if ($checkCol->num_rows == 0) {
    $alter = "ALTER TABLE video_calls ADD COLUMN notification_status ENUM('pending', 'accepted', 'rejected', 'notified') DEFAULT 'pending' AFTER status";
    if ($conn->query($alter)) {
        echo "notification_status column added successfully!\n";
    } else {
        echo "Error adding notification_status column: " . $conn->error . "\n";
    }
}

if ($conn->error) {
    echo "Error creating video_calls table: " . $conn->error;
} else {
    echo "Database structure updated successfully!";
}
?>