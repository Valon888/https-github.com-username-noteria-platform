<?php
// Security helper for CSRF, XSS, rate limit, etc.
class SecurityHelper {
    public static function csrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    public static function verifyCsrf($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    public static function sanitize($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    public static function rateLimit($key, $limit = 10, $seconds = 60) {
        $file = sys_get_temp_dir() . '/rate_' . md5($key);
        $now = time();
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : ['ts' => $now, 'count' => 0];
        if ($now - $data['ts'] > $seconds) $data = ['ts' => $now, 'count' => 0];
        if ($data['count'] >= $limit) return false;
        $data['count']++;
        file_put_contents($file, json_encode($data));
        return true;
    }
}
