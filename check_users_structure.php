<?php
require_once 'config.php';
require_once 'confidb.php';

echo "<h2>Struktura e tabelës 'users'</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

$stmt = $pdo->query("DESCRIBE users");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "<td>" . $col['Default'] . "</td>";
    echo "<td>" . $col['Extra'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Kolonat e disponueshme:</h2>";
echo "<ul>";
foreach ($columns as $col) {
    echo "<li>" . $col['Field'] . "</li>";
}
echo "</ul>";
?>
