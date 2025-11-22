<?php
// Script to automatically fix raportet.php by changing log_time to created_at
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Path to raportet.php file
$file = __DIR__ . '/raportet.php';

try {
    // Read the file contents
    $content = file_get_contents($file);
    
    if ($content === false) {
        die("Could not read file: $file");
    }
    
    // Make a backup of the original file
    $backup = $file . '.backup.' . date('YmdHis');
    if (!copy($file, $backup)) {
        die("Could not create backup file: $backup");
    }
    
    echo "<h2>Created backup: " . basename($backup) . "</h2>";
    
    // Replace log_time with created_at
    $modified = str_replace('pl.log_time', 'pl.created_at', $content);
    $modified = str_replace('log_time BETWEEN', 'created_at BETWEEN', $modified);
    
    // Write the modified content back to the file
    if (file_put_contents($file, $modified) === false) {
        die("Could not write to file: $file");
    }
    
    echo "<h2>✅ Successfully fixed raportet.php</h2>";
    echo "<p>All occurrences of 'pl.log_time' and 'log_time BETWEEN' were replaced with 'pl.created_at' and 'created_at BETWEEN' respectively.</p>";
    
    // Count replacements
    $countPlLogTime = substr_count($content, 'pl.log_time');
    $countLogTimeBetween = substr_count($content, 'log_time BETWEEN');
    
    echo "<p>Replacements made:</p>";
    echo "<ul>";
    echo "<li>'pl.log_time' → 'pl.created_at': $countPlLogTime occurrences</li>";
    echo "<li>'log_time BETWEEN' → 'created_at BETWEEN': $countLogTimeBetween occurrences</li>";
    echo "</ul>";
    
    echo "<p>You can test the reports page now to verify the fix: <a href='raportet.php'>Open Reports</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>