<?php
/**
 * PHP Syntax Fixer - Detect and Fix Missing Closing Braces
 * Utility për rregullimin automatik të kllapave të humbura në PHP files
 * 
 * @author GitHub Copilot
 * @date 2025-11-17
 */

// ==========================================
// KONFIGURIMI
// ==========================================
$file_path = 'zyrat_register.php';  // Ndryshoje emrin e fajllit sipas nevojës
$backup_enabled = true;              // Krijo backup të fajllit origjinal
$verbose = true;                     // Log detaljet e operacionit

// ==========================================
// LEXO FAJLLIN
// ==========================================
if (!file_exists($file_path)) {
    die("ERROR: File '$file_path' does not exist.\n");
}

$content = file_get_contents($file_path);
if ($content === false) {
    die("ERROR: Could not read file '$file_path'.\n");
}

if ($verbose) {
    echo "=== PHP Syntax Fixer ===\n";
    echo "File: $file_path\n";
    echo "Size: " . strlen($content) . " bytes\n\n";
}

// ==========================================
// ANALIZA KLLAPAVE
// ==========================================
$open_braces = substr_count($content, '{');
$close_braces = substr_count($content, '}');
$difference = $open_braces - $close_braces;

if ($verbose) {
    echo "=== Brace Analysis ===\n";
    echo "Open braces:  $open_braces\n";
    echo "Close braces: $close_braces\n";
    echo "Difference:   $difference\n\n";
}

// ==========================================
// LINJA PER LINJA ANALIZA
// ==========================================
$lines = explode("\n", $content);
$brace_count = 0;
$in_php = false;
$problem_line = null;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    $line_num = $i + 1;
    
    // Detekto hyrje/dalje PHP
    if (strpos($line, '<?php') !== false || strpos($line, '<?') !== false) {
        $in_php = true;
    }
    if (strpos($line, '?>') !== false) {
        $in_php = false;
    }
    
    if ($in_php) {
        $open_in_line = substr_count($line, '{');
        $close_in_line = substr_count($line, '}');
        $brace_count += $open_in_line - $close_in_line;
        
        if ($verbose && ($open_in_line > 0 || $close_in_line > 0)) {
            echo "Line $line_num: {$open_in_line} open, {$close_in_line} close, balance = $brace_count\n";
        }
        
        if ($brace_count < 0) {
            echo "ERROR: Too many closing braces at line $line_num\n";
            echo "Line content: " . trim($line) . "\n";
            die("Fix this error manually before continuing.\n");
        }
    }
}

echo "\n=== Final Result ===\n";
echo "Final brace balance: $brace_count\n\n";

// ==========================================
// RREGULLIM
// ==========================================
if ($brace_count > 0) {
    echo "Action: Adding $brace_count closing brace(s)...\n\n";
    
    $fixed_content = $content;
    $pos = strrpos($fixed_content, '?>');
    
    if ($pos !== false) {
        // Krijo backup
        if ($backup_enabled) {
            $backup_file = $file_path . '.backup';
            if (copy($file_path, $backup_file)) {
                echo "✓ Backup created: $backup_file\n";
            } else {
                echo "✗ Warning: Could not create backup.\n";
            }
        }
        
        // Shto kllapat
        $closing_braces = str_repeat('}', $brace_count);
        $fixed_content = substr_replace($fixed_content, "\n$closing_braces\n", $pos, 0);
        
        // Ruaj fajllin
        if (file_put_contents($file_path, $fixed_content)) {
            echo "✓ File fixed and saved successfully!\n";
            echo "✓ Added $brace_count closing brace(s)\n";
        } else {
            echo "✗ Error: Could not write to file '$file_path'.\n";
            die();
        }
    } else {
        echo "✗ Error: Could not find closing PHP tag '?>' in file.\n";
        die();
    }
} elseif ($brace_count === 0) {
    echo "✓ No issues found! All braces are properly balanced.\n";
} else {
    echo "✗ Error: Found $brace_count too many closing braces.\n";
    echo "✗ Please fix this manually.\n";
    die();
}

echo "\n=== Process Complete ===\n";
