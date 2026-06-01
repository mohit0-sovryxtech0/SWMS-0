<?php
// Role-Based Access Control System
class RBAC {
    private static $permissions = null;

    /**
     * Check if current user has a specific permission
     */
    public static function can($permission) {
        $roleId = $_SESSION['role_id'] ?? null;
        if (!$roleId) return false;

        // Super admin has all permissions
        if ($_SESSION['role_slug'] === 'super_admin') return true;

        $perms = self::getRolePermissions($roleId);
        return in_array($permission, $perms);
    }

    /**
     * Check if user has any of the given permissions
     */
    public static function canAny($permissions) {
        foreach ($permissions as $perm) {
            if (self::can($perm)) return true;
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public static function canAll($permissions) {
        foreach ($permissions as $perm) {
            if (!self::can($perm)) return false;
        }
        return true;
    }

    /**
     * Require permission - redirect if not authorized
     */
    public static function requirePermission($permission) {
        if (!self::can($permission)) {
            $message = urlencode('You do not have permission to access this page.');
            redirect(BASE_URL . 'pages/auth/unauthorized.php?msg=' . $message);
        }
    }

    /**
     * Get all permissions for a role
     */
    public static function getRolePermissions($roleId) {
        $cacheKey = "role_perms_{$roleId}";
        if (self::$permissions !== null && isset(self::$permissions[$cacheKey])) {
            return self::$permissions[$cacheKey];
        }

        $perms = db()->fetchAll(
            "SELECT p.slug FROM permissions p 
             JOIN role_permissions rp ON p.id = rp.permission_id 
             WHERE rp.role_id = :role_id",
            ['role_id' => $roleId]
        );

        $result = array_column($perms, 'slug');
        self::$permissions[$cacheKey] = $result;
        return $result;
    }

    /**
     * Assign permission to role
     */
    public static function assignPermission($roleId, $permissionId) {
        if (!db()->exists('role_permissions', 'role_id = :r AND permission_id = :p', 
            ['r' => $roleId, 'p' => $permissionId])) {
            return db()->insert('role_permissions', [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        return false;
    }

    /**
     * Remove permission from role
     */
    public static function revokePermission($roleId, $permissionId) {
        return db()->delete('role_permissions', 
            'role_id = :r AND permission_id = :p',
            ['r' => $roleId, 'p' => $permissionId]
        );
    }

    /**
     * Get all permissions
     */
    public static function getAllPermissions() {
        return db()->fetchAll("SELECT * FROM permissions ORDER BY module, name");
    }

    /**
     * Get all permissions grouped by module
     */
    public static function getPermissionsGrouped() {
        $perms = db()->fetchAll("SELECT * FROM permissions ORDER BY module, name");
        $grouped = [];
        foreach ($perms as $p) {
            $grouped[$p['module']][] = $p;
        }
        return $grouped;
    }

    /**
     * Get all roles
     */
    public static function getAllRoles() {
        return db()->fetchAll("SELECT * FROM roles WHERE deleted_at IS NULL ORDER BY name");
    }

    /**
     * Create a new role
     */
    public static function createRole($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        return db()->insert('roles', $data);
    }

    /**
     * Update a role
     */
    public static function updateRole($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return db()->update('roles', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Delete a role (soft)
     */
    public static function deleteRole($id) {
        return db()->softDelete('roles', $id);
    }
}
