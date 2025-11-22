<?php
require_once 'db_connection.php';

echo '<h1>Current Subscription Plans</h1>';

try {
    // Using the mysqli connection from db_connection.php
    // $conn is already defined in db_connection.php
    
    $result = $conn->query('SELECT * FROM abonimet ORDER BY id');
    
    echo '<table border="1" cellpadding="10">';
    echo '<tr><th>ID</th><th>Name</th><th>Price</th><th>Duration</th><th>Description</th><th>Features</th><th>Status</th></tr>';
    
    while ($plan = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $plan['id'] . '</td>';
        echo '<td>' . $plan['emri'] . '</td>';
        echo '<td>' . $plan['cmimi'] . ' €</td>';
        echo '<td>' . $plan['kohezgjatja'] . ' months</td>';
        echo '<td>' . $plan['pershkrimi'] . '</td>';
        echo '<td>' . $plan['karakteristikat'] . '</td>';
        echo '<td>' . $plan['status'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
