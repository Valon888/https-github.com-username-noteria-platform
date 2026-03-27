<?php
/**
 * Advanced Scalability for E-Noteria
 * 
 * 1. Read/Write Splitting
 * 2. Database Sharding (by zyra_id)
 * 3. Connection Pooling
 * 
 * Usage: $db = new DistributedDatabase();
 */

class DistributedDatabase {
    
    private $write_connection;  // Primary (write)
    private $read_connections = [];  // Replicas (read)
    private $shard_connections = [];  // Shard servers
    private $current_read = 0;  // Round-robin read replica
    
    public function __construct() {
        $this->initializeConnections();
    }
    
    /**
     * Initialize all database connections
     */
    private function initializeConnections() {
        try {
            // PRIMARY CONNECTION (Write)
            $this->write_connection = new PDO(
                'mysql:host=localhost;dbname=noteria',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );
            
            // READ REPLICAS (for scaling reads)
            // In production, these would be separate RDS instances
            $read_hosts = [
                'localhost',  // Replica 1
                'localhost',  // Replica 2 (in production, different host)
                'localhost',  // Replica 3
                'localhost'   // Replica 4
            ];
            
            foreach ($read_hosts as $index => $host) {
                try {
                    $this->read_connections[$index] = new PDO(
                        "mysql:host=$host;dbname=noteria",
                        'root',
                        '',
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT => 5
                        ]
                    );
                } catch (PDOException $e) {
                    // If replica fails, skip it
                    error_log("Replica $index connection failed: " . $e->getMessage());
                }
            }
            
            // SHARD CONNECTIONS (for horizontal scaling)
            // For now, use single server; in production, 10+ shard servers
            for ($i = 0; $i < 10; $i++) {
                try {
                    $this->shard_connections[$i] = new PDO(
                        "mysql:host=localhost;dbname=noteria",
                        'root',
                        '',
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        ]
                    );
                } catch (PDOException $e) {
                    error_log("Shard $i connection failed: " . $e->getMessage());
                }
            }
            
        } catch (PDOException $e) {
            die("Database initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get write connection (primary)
     */
    public function write() {
        return $this->write_connection;
    }
    
    /**
     * Get read connection (round-robin replica)
     */
    public function read() {
        if (empty($this->read_connections)) {
            return $this->write_connection;  // Fallback to primary
        }
        
        $conn = $this->read_connections[$this->current_read];
        $this->current_read = ($this->current_read + 1) % count($this->read_connections);
        
        return $conn;
    }
    
    /**
     * Consistent hashing function for sharding
     * @param int $zyra_id Office ID
     * @return int Shard ID (0-99)
     */
    private function getShardId($zyra_id) {
        return $zyra_id % 100;  // 100 shards total
    }
    
    /**
     * Get shard connection for a given zyra_id
     * @param int $zyra_id
     * @return PDO
     */
    private function getShardConnection($zyra_id) {
        $shard_id = $this->getShardId($zyra_id);
        $server_id = intval($shard_id / 10);  // Distribute 100 shards across 10 servers
        
        return $this->shard_connections[$server_id];
    }
    
    /**
     * Get the actual shard table name
     * @param int $zyra_id
     * @return string
     */
    private function getShardTableName($zyra_id) {
        $shard_id = $this->getShardId($zyra_id);
        return "reservations_shard_" . str_pad($shard_id, 2, '0', STR_PAD_LEFT);
    }
    
    // ==================== RESERVATION OPERATIONS ====================
    
    /**
     * Create a new reservation (uses sharding)
     */
    public function createReservation($user_id, $zyra_id, $service, $date, $time) {
        $pdo = $this->getShardConnection($zyra_id);
        $table = $this->getShardTableName($zyra_id);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO $table 
                (user_id, zyra_id, service, date, time, payment_status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            if ($stmt->execute([$user_id, $zyra_id, $service, $date, $time])) {
                return $pdo->lastInsertId();
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Reservation creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reservations by zyra_id (uses sharding)
     */
    public function getReservationsByZyra($zyra_id, $limit = 100, $offset = 0) {
        $pdo = $this->getShardConnection($zyra_id);
        $table = $this->getShardTableName($zyra_id);
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM $table 
                WHERE zyra_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$zyra_id, $limit, $offset]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Get reservations error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get reservation by ID (uses sharding with zyra_id)
     */
    public function getReservation($reservation_id, $zyra_id) {
        $pdo = $this->getShardConnection($zyra_id);
        $table = $this->getShardTableName($zyra_id);
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM $table 
                WHERE id = ? AND zyra_id = ? 
                LIMIT 1
            ");
            
            $stmt->execute([$reservation_id, $zyra_id]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get reservation error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get reservations by user (requires reading all shards)
     */
    public function getReservationsByUser($user_id) {
        $all_reservations = [];
        
        // Scan all shards for user's reservations
        for ($shard = 0; $shard < 100; $shard++) {
            $table = "reservations_shard_" . str_pad($shard, 2, '0', STR_PAD_LEFT);
            $server_id = intval($shard / 10);
            
            try {
                $pdo = $this->shard_connections[$server_id];
                $stmt = $pdo->prepare("
                    SELECT * FROM $table 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC
                ");
                
                $stmt->execute([$user_id]);
                $reservations = $stmt->fetchAll();
                
                if ($reservations) {
                    $all_reservations = array_merge($all_reservations, $reservations);
                }
            } catch (PDOException $e) {
                // Continue scanning other shards
                continue;
            }
        }
        
        // Sort by creation date
        usort($all_reservations, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $all_reservations;
    }
    
    /**
     * Update reservation (uses sharding)
     */
    public function updateReservation($reservation_id, $zyra_id, $data) {
        $pdo = $this->getShardConnection($zyra_id);
        $table = $this->getShardTableName($zyra_id);
        
        try {
            $set_parts = [];
            $params = [];
            
            foreach ($data as $column => $value) {
                $set_parts[] = "$column = ?";
                $params[] = $value;
            }
            
            $params[] = $reservation_id;
            $params[] = $zyra_id;
            
            $stmt = $pdo->prepare("
                UPDATE $table 
                SET " . implode(', ', $set_parts) . " 
                WHERE id = ? AND zyra_id = ?
            ");
            
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Update reservation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update payment status (uses sharding)
     */
    public function updatePaymentStatus($reservation_id, $zyra_id, $status) {
        return $this->updateReservation($reservation_id, $zyra_id, [
            'payment_status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // ==================== GLOBAL QUERIES ====================
    
    /**
     * Get news (uses read replica)
     */
    public function getNews($limit = 10) {
        $pdo = $this->read();
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM news 
                WHERE published = 1 
                ORDER BY date_created DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Get news error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user info (uses read replica)
     */
    public function getUser($user_id) {
        $pdo = $this->read();
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE id = ? 
                LIMIT 1
            ");
            
            $stmt->execute([$user_id]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get office info (uses read replica)
     */
    public function getOffice($zyra_id) {
        $pdo = $this->read();
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM zyrat 
                WHERE id = ? 
                LIMIT 1
            ");
            
            $stmt->execute([$zyra_id]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get office error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Health check - verify all connections
     */
    public function healthCheck() {
        $status = [
            'write' => false,
            'read' => [],
            'shards' => []
        ];
        
        // Check write connection
        try {
            $this->write_connection->query('SELECT 1');
            $status['write'] = true;
        } catch (Exception $e) {
            $status['write'] = false;
        }
        
        // Check read replicas
        foreach ($this->read_connections as $index => $conn) {
            try {
                $conn->query('SELECT 1');
                $status['read'][$index] = true;
            } catch (Exception $e) {
                $status['read'][$index] = false;
            }
        }
        
        // Check shards
        foreach ($this->shard_connections as $index => $conn) {
            try {
                $conn->query('SELECT 1');
                $status['shards'][$index] = true;
            } catch (Exception $e) {
                $status['shards'][$index] = false;
            }
        }
        
        return $status;
    }
}

// ==================== USAGE EXAMPLE ====================

/*
$db = new DistributedDatabase();

// Create reservation (sharded by zyra_id = 5)
$reservation_id = $db->createReservation(
    user_id: 123,
    zyra_id: 5,
    service: 'Vertetim Dokumenti',
    date: '2026-03-15',
    time: '10:00'
);

// Get reservations for a specific office (fast - single shard)
$reservations = $db->getReservationsByZyra(5);

// Get all user's reservations (slower - scans all shards)
$user_reservations = $db->getReservationsByUser(123);

// Get news (uses read replica - fast)
$news = $db->getNews(10);

// Check health
$health = $db->healthCheck();
print_r($health);
*/
?>
