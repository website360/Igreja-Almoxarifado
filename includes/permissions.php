<?php
/**
 * Sistema de Permissões (RBAC)
 */

class Permissions {
    private $db;
    private static $cache = [];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtém todas as permissões do usuário (papel + overrides)
     */
    public function getUserPermissions(int $userId): array {
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        // Permissões dos papéis
        $rolePermissions = $this->db->fetchAll(
            "SELECT DISTINCT p.module, p.action 
             FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             JOIN user_roles ur ON rp.role_id = ur.role_id
             WHERE ur.user_id = ?",
            [$userId]
        );

        $permissions = [];
        foreach ($rolePermissions as $perm) {
            $key = $perm['module'] . '.' . $perm['action'];
            $permissions[$key] = true;
        }

        // Overrides (podem adicionar ou remover)
        $overrides = $this->db->fetchAll(
            "SELECT p.module, p.action, upo.granted 
             FROM user_permission_overrides upo
             JOIN permissions p ON upo.permission_id = p.id
             WHERE upo.user_id = ?",
            [$userId]
        );

        foreach ($overrides as $override) {
            $key = $override['module'] . '.' . $override['action'];
            if ($override['granted']) {
                $permissions[$key] = true;
            } else {
                unset($permissions[$key]);
            }
        }

        self::$cache[$userId] = $permissions;
        return $permissions;
    }

    /**
     * Verifica se usuário tem permissão
     */
    public function hasPermission(int $userId, string $module, string $action): bool {
        $permissions = $this->getUserPermissions($userId);
        $key = $module . '.' . $action;
        return isset($permissions[$key]);
    }

    /**
     * Verifica múltiplas permissões (qualquer uma)
     */
    public function hasAnyPermission(int $userId, array $checks): bool {
        foreach ($checks as $check) {
            if ($this->hasPermission($userId, $check[0], $check[1])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica múltiplas permissões (todas)
     */
    public function hasAllPermissions(int $userId, array $checks): bool {
        foreach ($checks as $check) {
            if (!$this->hasPermission($userId, $check[0], $check[1])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Retorna módulos acessíveis ao usuário
     */
    public function getAccessibleModules(int $userId): array {
        $permissions = $this->getUserPermissions($userId);
        $modules = [];
        
        foreach ($permissions as $key => $value) {
            [$module, $action] = explode('.', $key);
            if ($action === 'view' && !in_array($module, $modules)) {
                $modules[] = $module;
            }
        }
        
        return $modules;
    }

    /**
     * Retorna menu filtrado por permissões
     */
    public function getFilteredMenu(int $userId): array {
        $accessibleModules = $this->getAccessibleModules($userId);
        $menu = [];
        
        foreach (MODULES as $key => $module) {
            if (in_array($key, $accessibleModules)) {
                $menu[$key] = $module;
            }
        }
        
        return $menu;
    }

    /**
     * Limpa cache de permissões
     */
    public function clearCache(?int $userId = null): void {
        if ($userId) {
            unset(self::$cache[$userId]);
        } else {
            self::$cache = [];
        }
    }

    /**
     * Retorna todas as permissões disponíveis
     */
    public function getAllPermissions(): array {
        return $this->db->fetchAll("SELECT * FROM permissions ORDER BY module, action");
    }

    /**
     * Retorna permissões agrupadas por módulo
     */
    public function getPermissionsGrouped(): array {
        $permissions = $this->getAllPermissions();
        $grouped = [];
        
        foreach ($permissions as $perm) {
            $grouped[$perm['module']][] = $perm;
        }
        
        return $grouped;
    }

    /**
     * Retorna todos os papéis
     */
    public function getAllRoles(): array {
        return $this->db->fetchAll("SELECT * FROM roles ORDER BY id");
    }

    /**
     * Retorna papel por ID
     */
    public function getRole(int $id): ?array {
        return $this->db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
    }

    /**
     * Cria novo papel
     */
    public function createRole(array $data): int {
        $roleId = $this->db->insert('roles', [
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null
        ]);

        Audit::log('role_created', 'roles', $roleId, $data);
        return $roleId;
    }

    /**
     * Atualiza papel
     */
    public function updateRole(int $id, array $data): bool {
        $role = $this->getRole($id);
        if ($role && $role['is_system']) {
            return false; // Não pode editar papéis do sistema
        }

        $affected = $this->db->update('roles', $data, 'id = :id', ['id' => $id]);
        
        if ($affected) {
            Audit::log('role_updated', 'roles', $id, $data);
        }
        
        return $affected > 0;
    }

    /**
     * Exclui papel
     */
    public function deleteRole(int $id): bool {
        $role = $this->getRole($id);
        if ($role && $role['is_system']) {
            return false; // Não pode excluir papéis do sistema
        }

        $affected = $this->db->delete('roles', 'id = ?', [$id]);
        
        if ($affected) {
            Audit::log('role_deleted', 'roles', $id);
            $this->clearCache();
        }
        
        return $affected > 0;
    }

    /**
     * Retorna permissões de um papel
     */
    public function getRolePermissions(int $roleId): array {
        return $this->db->fetchAll(
            "SELECT p.* FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?",
            [$roleId]
        );
    }

    /**
     * Retorna IDs das permissões de um papel
     */
    public function getRolePermissionIds(int $roleId): array {
        $perms = $this->db->fetchAll(
            "SELECT permission_id FROM role_permissions WHERE role_id = ?",
            [$roleId]
        );
        return array_column($perms, 'permission_id');
    }

    /**
     * Sincroniza permissões de um papel
     */
    public function syncRolePermissions(int $roleId, array $permissionIds): void {
        $this->db->delete('role_permissions', 'role_id = ?', [$roleId]);
        
        foreach ($permissionIds as $permId) {
            $this->db->insert('role_permissions', [
                'role_id' => $roleId,
                'permission_id' => $permId
            ]);
        }

        Audit::log('role_permissions_synced', 'roles', $roleId, [
            'permission_ids' => $permissionIds
        ]);
        
        $this->clearCache();
    }

    /**
     * Retorna overrides de um usuário
     */
    public function getUserOverrides(int $userId): array {
        return $this->db->fetchAll(
            "SELECT p.*, upo.granted 
             FROM user_permission_overrides upo
             JOIN permissions p ON upo.permission_id = p.id
             WHERE upo.user_id = ?",
            [$userId]
        );
    }

    /**
     * Adiciona override para usuário
     */
    public function addUserOverride(int $userId, int $permissionId, bool $granted): void {
        try {
            $this->db->insert('user_permission_overrides', [
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'granted' => $granted ? 1 : 0
            ]);
        } catch (Exception $e) {
            $this->db->update('user_permission_overrides', 
                ['granted' => $granted ? 1 : 0], 
                'user_id = :user_id AND permission_id = :perm_id',
                ['user_id' => $userId, 'perm_id' => $permissionId]
            );
        }

        Audit::log('permission_override', 'users', $userId, [
            'permission_id' => $permissionId,
            'granted' => $granted
        ]);
        
        $this->clearCache($userId);
    }

    /**
     * Remove override de usuário
     */
    public function removeUserOverride(int $userId, int $permissionId): void {
        $this->db->delete('user_permission_overrides', 
            'user_id = ? AND permission_id = ?', 
            [$userId, $permissionId]
        );
        $this->clearCache($userId);
    }

    /**
     * Sincroniza overrides de um usuário
     */
    public function syncUserOverrides(int $userId, array $overrides): void {
        $this->db->delete('user_permission_overrides', 'user_id = ?', [$userId]);
        
        foreach ($overrides as $override) {
            $this->db->insert('user_permission_overrides', [
                'user_id' => $userId,
                'permission_id' => $override['permission_id'],
                'granted' => $override['granted'] ? 1 : 0
            ]);
        }

        $this->clearCache($userId);
    }
}

/**
 * Helper global para verificar permissão
 */
function can(string $module, string $action): bool {
    global $userPermissions;
    $key = $module . '.' . $action;
    return isset($userPermissions[$key]);
}

/**
 * Helper para exigir permissão
 */
function requirePermission(string $module, string $action): void {
    if (!can($module, $action)) {
        if (isAjaxRequest()) {
            jsonResponse(['error' => 'Acesso negado'], 403);
        }
        redirect('/acesso-negado.php');
    }
}

/**
 * Helper para verificar qualquer permissão
 */
function canAny(array $checks): bool {
    foreach ($checks as $check) {
        if (can($check[0], $check[1])) {
            return true;
        }
    }
    return false;
}
