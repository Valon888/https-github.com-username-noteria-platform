<?php
require_once 'db_connection.php';

echo '<h1>Database Table Structure</h1>';

try {
    $result = $conn->query("DESCRIBE abonimet");
    
    echo '<h2>abonimet Table Structure</h2>';
    echo '<table border="1" cellpadding="10">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['Field'] . '</td>';
        echo '<td>' . $row['Type'] . '</td>';
        echo '<td>' . $row['Null'] . '</td>';
        echo '<td>' . $row['Key'] . '</td>';
        echo '<td>' . ($row['Default'] ? $row['Default'] : 'NULL') . '</td>';
        echo '<td>' . $row['Extra'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    echo '<h2>noteri_abonimet Table Structure</h2>';
    $result2 = $conn->query("DESCRIBE noteri_abonimet");
    
    echo '<table border="1" cellpadding="10">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    
    while ($row = $result2->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['Field'] . '</td>';
        echo '<td>' . $row['Type'] . '</td>';
        echo '<td>' . $row['Null'] . '</td>';
        echo '<td>' . $row['Key'] . '</td>';
        echo '<td>' . ($row['Default'] ? $row['Default'] : 'NULL') . '</td>';
        echo '<td>' . $row['Extra'] . '</td>';
        echo '</tr>';
    }
    echo '<table border="1" cellpadding="10">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['Field'] . '</td>';
        echo '<td>' . $row['Type'] . '</td>';
        echo '<td>' . $row['Null'] . '</td>';
        echo '<td>' . $row['Key'] . '</td>';
        echo '<td>' . ($row['Default'] ? $row['Default'] : 'NULL') . '</td>';
        echo '<td>' . $row['Extra'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>