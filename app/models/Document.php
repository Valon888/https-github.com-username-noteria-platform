<?php
// Document model
class Document {
    public $id, $user_id, $filename, $hash, $locked;
    public static function hashFile($filePath) {
        return hash_file('sha256', $filePath);
    }
}
