<?php
// Audit log model (immutable)
class AuditLog {
    public $id, $user_id, $event, $ip_address, $device_info, $meta, $created_at;
    public static function log($userId, $event, $ip, $ua, $meta = []) {
        $entry = date('c') . " | $userId | $event | $ip | $ua | " . json_encode($meta) . "\n";
        file_put_contents(__DIR__ . '/../../logs/audit.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
