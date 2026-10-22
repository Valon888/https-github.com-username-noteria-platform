<?php
require 'db_connection.php';
$res = $conn->query('DESCRIBE video_calls');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
