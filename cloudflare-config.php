<?php
/**
 * Cloudflare Integration for Noteria Platform
 * This file contains Cloudflare-specific configurations and utilities
 * for security, performance, and bot management.
 *
 * @author Noteria Development Team
 * @version 1.0
 * @since 2025-12-31
 */

/**
 * Cloudflare Security Headers Class
 * Handles security-related headers and client information
 */
class CloudflareSecurity
{
    /**
     * Adds comprehensive security headers to HTTP responses
     * Includes CSP, HSTS, and other security measures
     */
    public static function addSecurityHeaders()
    {
        // Basic Security Headers
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

        // Advanced Security Headers
        header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Expect-CT: max-age=86400, enforce');

        // Content Security Policy with Cloudflare Turnstile support
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://challenges.cloudflare.com; ";
        $csp .= "script-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://challenges.cloudflare.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com; ";
        $csp .= "img-src 'self' data: https: blob:; ";
        $csp .= "connect-src 'self' https://challenges.cloudflare.com; ";
        $csp .= "frame-src 'self' https://challenges.cloudflare.com; ";
        $csp .= "frame-ancestors 'self'; ";
        $csp .= "object-src 'none'; ";
        $csp .= "base-uri 'self'; ";
        $csp .= "form-action 'self'; ";
        $csp .= "upgrade-insecure-requests;";

        header('Content-Security-Policy: ' . $csp);

        // Cloudflare specific headers
        if (!isset($_SERVER['HTTP_CF_RAY'])) {
            header('CF-Cache-Status: DYNAMIC');
            header('CF-RAY: ' . substr(md5(uniqid()), 0, 16) . '-PRG');
        }
    }

    /**
     * Retrieves the real client IP address through Cloudflare
     *
     * @return string Client IP address
     */
    public static function getClientIP()
    {
        // Get real client IP through Cloudflare
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Checks if the current request is coming through Cloudflare
     *
     * @return bool True if request is through Cloudflare
     */
    public static function isCloudflareRequest()
    {
        return isset($_SERVER['HTTP_CF_RAY']);
    }

    /**
     * Gets the client's country code from Cloudflare
     *
     * @return string Country code (ISO 3166-1 alpha-2)
     */
    public static function getCloudflareCountry()
    {
        return $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'XX';
    }

    /**
     * Gets the Cloudflare Ray ID for request tracing
     *
     * @return string Ray ID
     */
    public static function getCloudflareRayID()
    {
        return $_SERVER['HTTP_CF_RAY'] ?? substr(md5(uniqid()), 0, 16);
    }

    /**
     * Implements basic rate limiting for requests
     * Uses session-based tracking for simplicity
     *
     * @param int $maxRequests Maximum requests per time window
     * @param int $timeWindow Time window in seconds
     * @return bool True if request is allowed, false if rate limited
     */
    public static function checkRateLimit($maxRequests = 100, $timeWindow = 3600)
    {
        $clientIP = self::getClientIP();
        $sessionKey = 'rate_limit_' . md5($clientIP);

        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [
                'count' => 1,
                'reset_time' => time() + $timeWindow
            ];
            return true;
        }

        $rateData = $_SESSION[$sessionKey];

        if (time() > $rateData['reset_time']) {
            // Reset the counter
            $_SESSION[$sessionKey] = [
                'count' => 1,
                'reset_time' => time() + $timeWindow
            ];
            return true;
        }

        if ($rateData['count'] >= $maxRequests) {
            return false; // Rate limit exceeded
        }

        $_SESSION[$sessionKey]['count']++;
        return true;
    }

    /**
     * Logs security events for monitoring
     *
     * @param string $event Event description
     * @param array $data Additional data
     */
    public static function logSecurityEvent($event, $data = [])
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'country' => self::getCloudflareCountry(),
            'ray_id' => self::getCloudflareRayID(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ];

        error_log('Cloudflare Security Event: ' . json_encode($logData));
    }
}

/**
 * Cloudflare Performance Optimization Class
 * Handles performance-related features like Rocket Loader and Analytics
 */
class CloudflarePerformance
{
    /**
     * Adds Cloudflare Rocket Loader script for performance optimization
     */
    public static function addRocketLoader()
    {
        echo '<script>(function(d,s){d.addEventListener(\'DOMContentLoaded\',function(){var e=d.createElement(s);e.src=\'https://ajax.cloudflare.com/cdn-cgi/scripts/95c75768/cloudflare-static/rocket-loader.min.js\';d.getElementsByTagName(\'head\')[0].appendChild(e);});})(document,\'script\');</script>';
    }

    /**
     * Adds Cloudflare Analytics beacon
     *
     * @param string $token Cloudflare Analytics token
     */
    public static function addAnalytics($token = 'your-cloudflare-analytics-token')
    {
        if ($token !== 'your-cloudflare-analytics-token') {
            echo '<script defer src=\'https://static.cloudflareinsights.com/beacon.min.js\' data-cf-beacon=\'{"token": "' . $token . '"}\'></script>';
        }
    }

    /**
     * Optimizes image URLs using Cloudflare Image Optimization
     *
     * @param string $imageUrl Original image URL
     * @return string Optimized image URL
     */
    public static function optimizeImages($imageUrl)
    {
        // Cloudflare Image Optimization (Polish)
        // In production, this would use Cloudflare's Image API
        return $imageUrl . '?w=800&q=80&f=auto';
    }
}

/**
 * Cloudflare Bot Management Class
 * Handles bot detection and management features
 */
class CloudflareBotManagement
{
    /**
     * Gets the bot management score from Cloudflare
     *
     * @return string Bot score ('good', 'bad', 'suspicious', or 'unknown')
     */
    public static function getBotScore()
    {
        return $_SERVER['HTTP_CF_BOT_MANAGEMENT'] ?? 'unknown';
    }

    /**
     * Checks if the request is from a bot
     *
     * @return bool True if request is from a bot
     */
    public static function isBot()
    {
        $botScore = self::getBotScore();
        return $botScore === 'bad' || $botScore === 'suspicious';
    }

    /**
     * Checks if the request is from a verified bot
     *
     * @return bool True if request is from a verified bot
     */
    public static function isVerifiedBot()
    {
        return isset($_SERVER['HTTP_CF_VERIFIED_BOT']);
    }
}

// Usage example:
// CloudflareSecurity::addSecurityHeaders();
// $clientIP = CloudflareSecurity::getClientIP();
// $country = CloudflareSecurity::getCloudflareCountry();

?>