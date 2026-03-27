<?php
// Role-based access control
class RoleMiddleware {
    public static function check($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
}
