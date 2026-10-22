<?php
// User model with password hashing and 2FA fields
class User {
    public $id, $name, $email, $password, $role, $two_factor_secret, $id_document;
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
