<?php
require_once 'confidb.php';

try {
    // Get all videos
    $videos = glob('ads/*.mp4');
    $video_mapping = [
        'Facebook 903956761957358(1080p).mp4' => 'Viva Fresh Store', // Supozojmë që ky është Viva
        'Facebook 1574145720244620(1080p).mp4' => 'Telkos', 
        'Facebook 852085621105180(1080p).mp4' => 'Reklama 3'
    ];
    
    echo "<h2>Përditësim Video URLs në Database</h2>";
    
    foreach ($videos as $video_path) {
        $filename = basename($video_path);
        $correct_path = 'ads/' . $filename;
        
        echo "Video: " . $filename . "<br>";
        
        // Find ads that might use this video
        $ads = $pdo->query("SELECT id, ad_title, video_url FROM ads")->fetchAll();
        
        foreach ($ads as $ad) {
            $current_video = $ad['video_url'];
            
            // If video_url contains the filename or is empty, update it
            if (empty($current_video) || stripos($current_video, $filename) !== false) {
                echo "  - Azhurni ID " . $ad['id'] . " (" . htmlspecialchars($ad['ad_title']) . ") me: " . $correct_path . "<br>";
                
                $stmt = $pdo->prepare("UPDATE ads SET video_url = ? WHERE id = ?");
                $stmt->execute([$correct_path, $ad['id']]);
            }
        }
    }
    
    echo "<h2>Të dhënat e përditësuara:</h2>";
    $all_ads = $pdo->query("SELECT id, ad_title, video_url, is_active FROM ads ORDER BY id")->fetchAll();
    foreach ($all_ads as $ad) {
        echo "ID " . $ad['id'] . ": " . htmlspecialchars($ad['ad_title']) . " -> " . htmlspecialchars($ad['video_url']) . " (Aktiv: " . ($ad['is_active'] ? 'PO' : 'JO') . ")<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
