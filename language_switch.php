<?php
/**
 * Language Switcher
 * Handles language switching for the platform
 */

session_start();

$lang = $_GET['lang'] ?? $_POST['lang'] ?? null;
$supported_langs = ['sq', 'en', 'fr', 'de'];

if ($lang && in_array($lang, $supported_langs)) {
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time() + (365 * 24 * 60 * 60), '/');
    
    // Redirect back to referrer or index
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: " . $referrer);
} else {
    // Show language selector
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Select Language - Noteria</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Montserrat', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            
            .language-selector {
                background: white;
                border-radius: 16px;
                padding: 40px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 400px;
            }
            
            h1 {
                color: #333;
                margin-bottom: 8px;
                font-size: 1.8rem;
            }
            
            p {
                color: #666;
                margin-bottom: 30px;
            }
            
            .languages {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .language-btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 16px;
                border-radius: 8px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
                display: block;
                font-size: 0.95rem;
            }
            
            .language-btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            }
        </style>
    </head>
    <body>
        <div class="language-selector">
            <h1>🌍 Gjuha / Language</h1>
            <p>Zgjidh gjuhën tuaj / Choose your language</p>
            
            <div class="languages">
                <a href="?lang=sq" class="language-btn">🇦🇱 Shqip</a>
                <a href="?lang=en" class="language-btn">🇬🇧 English</a>
                <a href="?lang=fr" class="language-btn">🇫🇷 Français</a>
                <a href="?lang=de" class="language-btn">🇩🇪 Deutsch</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
