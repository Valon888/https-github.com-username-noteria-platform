<?php
require_once 'confidb.php';

try {
    $ads = $pdo->query("SELECT id, ad_title, ad_type, is_active, video_url FROM ads")->fetchAll();
    
    echo "<h2>Të gjitha Reklamat:</h2>";
    foreach ($ads as $ad) {
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
        echo "<b>ID:</b> " . $ad['id'] . " | ";
        echo "<b>Titulli:</b> " . htmlspecialchars($ad['ad_title']) . " | ";
        echo "<b>Tipi:</b> " . $ad['ad_type'] . " | ";
        echo "<b>Aktiv:</b> " . ($ad['is_active'] ? "✅ PO" : "❌ JO") . " | ";
        echo "<b>Video URL:</b> " . htmlspecialchars($ad['video_url'] ?? 'NULL') . "<br>";
        
        // Kontrollo nëse fajlli ekziston
        if (!empty($ad['video_url'])) {
            $video_path = $ad['video_url'];
            if (file_exists($video_path)) {
                echo "✅ Fajlli ekziston: " . $video_path;
            } else {
                echo "❌ Fajlli NUK ekziston: " . $video_path;
                // Try ads/ folder
                $filename = basename($video_path);
                if (file_exists('ads/' . $filename)) {
                    echo " | ✅ Por ekziston në: ads/" . $filename;
                }
            }
        } else {
            echo "⚠️ Nuk ka video URL!";
        }
        echo "</div>";
    }
    
    echo "<h2>Videos në direktori ads/:</h2>";
    $videos = glob('ads/*.mp4');
    if ($videos) {
        foreach ($videos as $v) {
            echo "- " . basename($v) . "<br>";
        }
    } else {
        echo "Nuk ka videos!";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
