<?php
require_once 'db_connection.php';

echo '<h1>Check User Subscriptions</h1>';

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'noteri_abonimet'");
    
    if ($tableCheck->num_rows > 0) {
        $result = $conn->query('SELECT na.*, a.emri as abonimi_emri, a.cmimi 
                               FROM noteri_abonimet na 
                               LEFT JOIN abonimet a ON na.abonim_id = a.id 
                               ORDER BY na.id DESC LIMIT 10');
        
        if ($result->num_rows > 0) {
            echo '<h2>Recent Subscriptions</h2>';
            echo '<table border="1" cellpadding="10">';
            echo '<tr>
                    <th>ID</th>
                    <th>Noteri ID</th>
                    <th>Subscription ID</th>
                    <th>Subscription Name</th>
                    <th>Price</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                  </tr>';
            
            while ($sub = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $sub['id'] . '</td>';
                echo '<td>' . $sub['noter_id'] . '</td>';
                echo '<td>' . $sub['abonim_id'] . '</td>';
                echo '<td>' . $sub['abonimi_emri'] . '</td>';
                echo '<td>' . $sub['cmimi'] . ' €</td>';
                echo '<td>' . $sub['data_fillimit'] . '</td>';
                echo '<td>' . $sub['data_mbarimit'] . '</td>';
                echo '<td>' . $sub['status'] . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        } else {
            echo '<p>No subscriptions found.</p>';
        }
    } else {
        echo '<p>Notary subscriptions table does not exist.</p>';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>