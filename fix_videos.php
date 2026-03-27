<?php
require_once 'confidb.php';

try {
    // Shiko të gjitha ads
    $ads = $pdo->query("SELECT id, ad_title, video_url FROM ads")->fetchAll();
    
    foreach ($ads as $ad) {
        echo "ID: " . $ad['id'] . " | Title: " . $ad['ad_title'] . " | Video URL: " . ($ad['video_url'] ?? 'NULL') . "<br>";
        
        if ($ad['ad_title'] === 'Viva Fresh Store' || stripos($ad['ad_title'], 'viva') !== false) {
            echo "⚠️ FOUND VIVA FRESH STORE<br>";
            echo "Current path: " . $ad['video_url'] . "<br>";
            
            // Check if file exists
            if (file_exists($ad['video_url'])) {
                echo "✅ File exists at: " . $ad['video_url'] . "<br>";
            } else {
                echo "❌ File NOT found at: " . $ad['video_url'] . "<br>";
                
                // Try ads/ folder
                $filename = basename($ad['video_url']);
                if (file_exists('ads/' . $filename)) {
                    echo "✅ Found in ads/ folder: ads/" . $filename . "<br>";
                    
                    // Update database
                    $stmt = $pdo->prepare("UPDATE ads SET video_url = ? WHERE id = ?");
                    $stmt->execute(['ads/' . $filename, $ad['id']]);
                    echo "✅ Updated database with correct path!<br>";
                } else {
                    // List available videos
                    echo "Available videos in ads/:<br>";
                    $videos = glob('ads/*.mp4');
                    foreach ($videos as $v) {
                        echo "  - " . $v . "<br>";
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
