<?php
/**
 * BACKEND OPTIMIZATION CONFIGURATION
 * Për mbështetjen e 1M+ daily calls me concurrent handling
 * 
 * Konfigurimi i PHP, MySQL, dhe application-level optimizations
 */

// ============================================================================
// 1. PHP CONFIGURATION OPTIMIZATIONS
// ============================================================================

// Mirë për produksion, vendos këto në php.ini:
/*
[PHP]
max_execution_time = 300
max_input_time = 60
memory_limit = 2G
post_max_size = 100M
upload_max_filesize = 100M

[Opcache]
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.fast_shutdown = 1
opcache.revalidate_freq = 0

[Database]
default_socket_timeout = 5
pdo_mysql.socket = /var/run/mysqld/mysqld.sock

[Session]
session.gc_maxlifetime = 86400
session.save_path = "/tmp/php-sessions"
session.serialize_handler = php_serialize
*/

// ============================================================================
// 2. CONNECTION POOLING - Connection Management Class
// ============================================================================

class DatabaseConnectionPool {
    private static $instance = null;
    private $pool = [];
    private $maxConnections = 500;
    private $minConnections = 50;
    private $connectionTimeout = 5;
    private $idleTimeout = 300;
    
    private function __construct() {
        // Initialize minimum connections
        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->createConnection();
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function createConnection() {
        try {
            $conn = new PDO(
                'mysql:host=db-primary.internal;dbname=noteria;charset=utf8mb4',
                'app_user',
                'secure_password_here',
                [
                    PDO::ATTR_TIMEOUT => $this->connectionTimeout,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]
            );
            
            // Test connection
            $conn->query('SELECT 1');
            
            return [
                'connection' => $conn,
                'created_at' => time(),
                'last_used' => time(),
                'in_use' => false
            ];
        } catch (PDOException $e) {
            error_log("Connection pool error: " . $e->getMessage());
            return null;
        }
    }
    
    public function getConnection() {
        // Try to get idle connection
        foreach ($this->pool as $key => $connData) {
            if (!$connData['in_use']) {
                // Check if connection is still valid
                if ((time() - $connData['last_used']) < $this->idleTimeout) {
                    $this->pool[$key]['in_use'] = true;
                    $this->pool[$key]['last_used'] = time();
                    return $this->pool[$key]['connection'];
                } else {
                    // Remove stale connection
                    unset($this->pool[$key]);
                }
            }
        }
        
        // Create new connection if under limit
        if (count($this->pool) < $this->maxConnections) {
            $connData = $this->createConnection();
            if ($connData) {
                $connData['in_use'] = true;
                $this->pool[] = $connData;
                return end($this->pool)['connection'];
            }
        }
        
        // Wait for available connection
        $waitTime = 0;
        $maxWait = 30; // seconds
        
        while ($waitTime < $maxWait) {
            foreach ($this->pool as $key => $connData) {
                if (!$connData['in_use']) {
                    $this->pool[$key]['in_use'] = true;
                    $this->pool[$key]['last_used'] = time();
                    return $connData['connection'];
                }
            }
            sleep(1);
            $waitTime++;
        }
        
        throw new Exception("No database connection available after {$maxWait}s");
    }
    
    public function releaseConnection(&$conn) {
        foreach ($this->pool as &$connData) {
            if ($connData['connection'] === $conn) {
                $connData['in_use'] = false;
                $connData['last_used'] = time();
                break;
            }
        }
    }
    
    public function getPoolStats() {
        $stats = [
            'total' => count($this->pool),
            'in_use' => 0,
            'idle' => 0,
            'created_at' => time()
        ];
        
        foreach ($this->pool as $connData) {
            if ($connData['in_use']) {
                $stats['in_use']++;
            } else {
                $stats['idle']++;
            }
        }
        
        return $stats;
    }
}

// ============================================================================
// 3. OPTIMIZED QUERY HELPER
// ============================================================================

class OptimizedQueryHelper {
    private $pool;
    
    public function __construct() {
        $this->pool = DatabaseConnectionPool::getInstance();
    }
    
    /**
     * Execute with prepared statement and connection reuse
     */
    public function execute($query, $params = [], $fetchMode = null) {
        $conn = $this->pool->getConnection();
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            if ($fetchMode === 'all') {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($fetchMode === 'one') {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($fetchMode === 'count') {
                return $stmt->rowCount();
            } else {
                return $stmt;
            }
        } finally {
            $this->pool->releaseConnection($conn);
        }
    }
    
    /**
     * Batch insert për më shumë performancë
     */
    public function batchInsert($table, $columns, $data) {
        $conn = $this->pool->getConnection();
        
        try {
            $conn->beginTransaction();
            
            $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $query = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES " . 
                     implode(',', array_fill(0, count($data), $placeholders));
            
            $flat = [];
            foreach ($data as $row) {
                $flat = array_merge($flat, $row);
            }
            
            $stmt = $conn->prepare($query);
            $stmt->execute($flat);
            
            $conn->commit();
            return $stmt->rowCount();
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        } finally {
            $this->pool->releaseConnection($conn);
        }
    }
}

// ============================================================================
// 4. CALL TRACKING OPTIMIZATION
// ============================================================================

class CallTracker {
    private $queryHelper;
    private $redisCache;
    
    public function __construct() {
        $this->queryHelper = new OptimizedQueryHelper();
        $this->redisCache = $this->getRedisClient();
    }
    
    private function getRedisClient() {
        try {
            $redis = new Redis();
            $redis->connect('redis-cluster.internal', 6379, 2);
            $redis->setOption(Redis::OPT_COMPRESSION, 1);
            return $redis;
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create video call record with minimal latency
     */
    public function createCall($callId, $roomId, $userId) {
        // Cache in Redis first (fastest)
        if ($this->redisCache) {
            $this->redisCache->setex(
                "call:$callId",
                3600, // 1 hour TTL
                json_encode([
                    'call_id' => $callId,
                    'room_id' => $roomId,
                    'user_id' => $userId,
                    'start_time' => time(),
                    'status' => 'active'
                ])
            );
        }
        
        // Async write to database (non-blocking)
        $this->asyncDatabaseWrite($callId, $roomId, $userId);
        
        return true;
    }
    
    private function asyncDatabaseWrite($callId, $roomId, $userId) {
        // Queue for batch processing
        if ($this->redisCache) {
            $this->redisCache->lpush('call_queue:pending', json_encode([
                'call_id' => $callId,
                'room_id' => $roomId,
                'user_id' => $userId,
                'timestamp' => microtime(true)
            ]));
        }
    }
    
    /**
     * Process batched calls every 5 seconds (reduces database load)
     */
    public function processBatchedCalls() {
        if (!$this->redisCache) return;
        
        $batchSize = 1000;
        $calls = $this->redisCache->lrange('call_queue:pending', 0, $batchSize - 1);
        
        if (empty($calls)) return;
        
        try {
            $data = [];
            foreach ($calls as $call) {
                $decoded = json_decode($call, true);
                $data[] = [
                    $decoded['call_id'],
                    $decoded['room_id'],
                    $decoded['user_id'],
                    date('Y-m-d H:i:s'),
                    'active'
                ];
            }
            
            // Batch insert
            $query = "INSERT INTO video_calls_optimized 
                     (call_id, room, user_id, start_time, status) 
                     VALUES (?, ?, ?, ?, ?)";
            
            $this->queryHelper->batchInsert(
                'video_calls_optimized',
                ['call_id', 'room', 'user_id', 'start_time', 'status'],
                array_map(function($row) {
                    return array_combine(
                        ['call_id', 'room', 'user_id', 'start_time', 'status'],
                        $row
                    );
                }, $data)
            );
            
            // Remove from queue
            $this->redisCache->ltrim('call_queue:pending', $batchSize, -1);
        } catch (Exception $e) {
            error_log("Batch call processing error: " . $e->getMessage());
        }
    }
    
    /**
     * Update call quality metrics
     */
    public function updateQualityMetrics($callId, $metrics) {
        if ($this->redisCache) {
            $this->redisCache->hset(
                "call_metrics:$callId",
                'bandwidth', $metrics['bandwidth'],
                'latency', $metrics['latency'],
                'packet_loss', $metrics['packet_loss'],
                'last_update', time()
            );
        }
    }
}

// ============================================================================
// 5. RESPONSE COMPRESSION & CACHING
// ============================================================================

// Enable output compression automatically
if (!headers_sent() && extension_loaded('zlib')) {
    ob_start('ob_gzhandler');
}

// Set cache headers for static content
function setCacheHeaders($maxAge = 86400) {
    header("Cache-Control: public, max-age=$maxAge, immutable");
    header("ETag: " . md5($_SERVER['REQUEST_URI']));
    header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge));
}

// ============================================================================
// 6. RATE LIMITING
// ============================================================================

class RateLimiter {
    private $redis;
    private $limits = [
        'api_call' => ['requests' => 1000, 'window' => 60],
        'auth' => ['requests' => 10, 'window' => 60],
        'video_create' => ['requests' => 100, 'window' => 3600]
    ];
    
    public function __construct() {
        try {
            $this->redis = new Redis();
            $this->redis->connect('redis-cluster.internal', 6379, 2);
        } catch (Exception $e) {
            error_log("Redis rate limiter connection failed: " . $e->getMessage());
        }
    }
    
    public function isAllowed($userId, $action) {
        if (!$this->redis) return true;
        
        $limit = $this->limits[$action] ?? $this->limits['api_call'];
        $key = "ratelimit:$action:$userId";
        
        $current = $this->redis->incr($key);
        
        if ($current === 1) {
            $this->redis->expire($key, $limit['window']);
        }
        
        return $current <= $limit['requests'];
    }
    
    public function getRemainingRequests($userId, $action) {
        if (!$this->redis) return -1;
        
        $limit = $this->limits[$action] ?? $this->limits['api_call'];
        $key = "ratelimit:$action:$userId";
        
        $current = $this->redis->get($key) ?: 0;
        return max(0, $limit['requests'] - $current);
    }
}

// ============================================================================
// USAGE EXAMPLE
// ============================================================================

/*
// Initialize call tracker
$tracker = new CallTracker();

// Create a call
$callId = 'call_' . uniqid();
$tracker->createCall($callId, 'noteria-kosove-01', 'user123');

// Update metrics
$tracker->updateQualityMetrics($callId, [
    'bandwidth' => 2.5,
    'latency' => 45,
    'packet_loss' => 0.5
]);

// Check rate limits
$limiter = new RateLimiter();
if ($limiter->isAllowed('user123', 'video_create')) {
    // Allow video call creation
} else {
    http_response_code(429);
    die('Too many requests');
}

// Database queries with connection pooling
$queryHelper = new OptimizedQueryHelper();
$users = $queryHelper->execute(
    'SELECT * FROM users WHERE status = ? LIMIT 1000',
    ['active'],
    'all'
);

// Pool stats for monitoring
$pool = DatabaseConnectionPool::getInstance();
error_log('Connection pool stats: ' . json_encode($pool->getPoolStats()));

// Create a Redis instance
$redis = new Redis();

// Connect to Redis server (default localhost:6379)
$redis->connect('127.0.0.1', 6379);

// Example: Set and get a value
$redis->set('key', 'value');
$value = $redis->get('key');
echo $value; // Outputs: value
*/

?>
