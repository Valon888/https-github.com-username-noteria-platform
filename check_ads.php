<?php
require_once 'confidb.php';

try {
    $ads = $pdo->query("SELECT id, ad_title, ad_type, video_url, is_active FROM ads")->fetchAll();
    
    echo "<h2>Reklamat në database:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Titulli</th><th>Tipi</th><th>Video URL</th><th>Aktiv</th><th>Fajlli Ekziston</th></tr>";
    
    foreach ($ads as $ad) {
        $file_exists = !empty($ad['video_url']) && file_exists($ad['video_url']) ? "✅ PO" : "❌ JO";
        echo "<tr>";
        echo "<td>" . $ad['id'] . "</td>";
        echo "<td>" . htmlspecialchars($ad['ad_title']) . "</td>";
        echo "<td>" . $ad['ad_type'] . "</td>";
        echo "<td>" . htmlspecialchars($ad['video_url']) . "</td>";
        echo "<td>" . ($ad['is_active'] ? "PO" : "JO") . "</td>";
        echo "<td>" . $file_exists . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Kontrollo video files në direktori
    echo "<h2>Video files në direktori:</h2>";
    $videos = glob("videos/*.mp4");
    if ($videos) {
        foreach ($videos as $v) {
            echo "- " . $v . "<br>";
        }
    } else {
        echo "Nuk ka video files në direktori 'videos/'";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
