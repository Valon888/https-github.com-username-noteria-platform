<?php
// Performance monitoring for high-traffic sites
class PerformanceMonitor {
    private static $start_time;
    private static $memory_start;

    public static function start() {
        self::$start_time = microtime(true);
        self::$memory_start = memory_get_usage();
    }

    public static function end() {
        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        $execution_time = ($end_time - self::$start_time) * 1000; // ms
        $memory_used = ($end_memory - self::$memory_start) / 1024 / 1024; // MB

        // Log performance metrics (in production, send to monitoring service)
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';
        error_log(sprintf(
            "Performance: %.2fms, Memory: %.2fMB, URL: %s",
            $execution_time,
            $memory_used,
            $requestUri
        ));

        // Add performance headers for monitoring (only if headers not sent)
        if (!headers_sent()) {
            header("X-Execution-Time: {$execution_time}ms");
            header("X-Memory-Usage: {$memory_used}MB");
        }

        return [
            'execution_time' => $execution_time,
            'memory_used' => $memory_used
        ];
    }
}

// Start performance monitoring
PerformanceMonitor::start();

// Your existing code here...

// End performance monitoring (call at end of script)
register_shutdown_function(function() {
    PerformanceMonitor::end();
});
?>