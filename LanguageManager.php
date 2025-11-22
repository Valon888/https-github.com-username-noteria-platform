<?php
/**
 * Multi-language Support System
 * Suporta për: Albanian (SQ), English (EN), French (FR), German (DE)
 */

class LanguageManager {
    private $current_lang = 'sq';
    private $supported_langs = ['sq', 'en', 'fr', 'de'];
    private $translations = [];
    
    public function __construct() {
        // Load language from cookie or session, default to Albanian
        if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $this->supported_langs)) {
            $this->current_lang = $_COOKIE['lang'];
        } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $this->supported_langs)) {
            $this->current_lang = $_SESSION['lang'];
        }
        
        // Load translations
        $this->loadTranslations();
    }
    
    /**
     * Load translation file for current language
     */
    private function loadTranslations() {
        $file = __DIR__ . "/lang/{$this->current_lang}.json";
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $this->translations = json_decode($content, true);
        }
    }
    
    /**
     * Get translated string
     * Usage: t('welcome.title') or t('common.hello', ['name' => 'John'])
     */
    public function get($key, $variables = []) {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Return key if translation not found
            }
        }
        
        // Replace variables
        foreach ($variables as $var => $val) {
            $value = str_replace('{' . $var . '}', $val, $value);
        }
        
        return $value;
    }
    
    /**
     * Set language
     */
    public function setLanguage($lang) {
        if (in_array($lang, $this->supported_langs)) {
            $this->current_lang = $lang;
            $_SESSION['lang'] = $lang;
            setcookie('lang', $lang, time() + (365 * 24 * 60 * 60), '/');
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->current_lang;
    }
    
    /**
     * Get all supported languages
     */
    public function getSupportedLanguages() {
        return $this->supported_langs;
    }
    
    /**
     * Get language name
     */
    public static function getLanguageName($lang_code) {
        $names = [
            'sq' => 'Shqip',
            'en' => 'English',
            'fr' => 'Français',
            'de' => 'Deutsch'
        ];
        return $names[$lang_code] ?? $lang_code;
    }
}

// Global translation helper
function t($key, $variables = []) {
    global $lang_manager;
    if (!isset($lang_manager)) {
        $lang_manager = new LanguageManager();
    }
    return $lang_manager->get($key, $variables);
}

// Global language setter
function setLang($lang) {
    global $lang_manager;
    if (!isset($lang_manager)) {
        $lang_manager = new LanguageManager();
    }
    return $lang_manager->setLanguage($lang);
}

// Initialize language manager in session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$lang_manager = new LanguageManager();

?>
