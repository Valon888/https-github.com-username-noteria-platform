<?php
require_once 'confidb.php';

try {
    echo "<h2>Reklamat Aktuale në Database:</h2>";
    $ads = $pdo->query("SELECT id, ad_title, video_url, is_active FROM ads")->fetchAll();
    
    foreach ($ads as $ad) {
        echo "ID: " . $ad['id'] . " | Title: " . htmlspecialchars($ad['ad_title']) . " | ";
        echo "Video URL: " . htmlspecialchars($ad['video_url'] ?? 'NULL') . " | ";
        echo "Aktiv: " . ($ad['is_active'] ? 'YES' : 'NO') . "<br>";
    }
    
    // List available videos
    echo "<h2>Available Videos in ads/ folder:</h2>";
    $videos = glob('ads/*.mp4');
    foreach ($videos as $v) {
        echo "- " . basename($v) . "<br>";
    }
    
    // Fix all ads with correct video paths
    echo "<h2>Fixing video paths...</h2>";
    
    $videos_files = [
        'Facebook 903956761957358(1080p).mp4',
        'Facebook 1574145720244620(1080p).mp4',
        'Facebook 852085621105180(1080p).mp4'
    ];
    
    $stmt = $pdo->prepare("UPDATE ads SET video_url = ? WHERE id = ?");
    
    foreach ($ads as $index => $ad) {
        if ($index < count($videos_files)) {
            $new_path = 'ads/' . $videos_files[$index];
            $stmt->execute([$new_path, $ad['id']]);
            echo "Updated ID " . $ad['id'] . " with: " . $new_path . "<br>";
        }
    }
    
    echo "<h2>Updated Database:</h2>";
    $updated_ads = $pdo->query("SELECT id, ad_title, video_url, is_active FROM ads")->fetchAll();
    foreach ($updated_ads as $ad) {
        echo "ID: " . $ad['id'] . " | " . htmlspecialchars($ad['ad_title']) . " | " . htmlspecialchars($ad['video_url']) . "<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
