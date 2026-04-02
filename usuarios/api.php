<?php
/**
 * API de Usuários e Permissões
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

header('Content-Type: application/json');

$db = Database::getInstance();
$permissionsManager = new Permissions();
$authManager = new Auth();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $action ?: ($input['action'] ?? '');

switch ($action) {
    case 'get_user_roles':
        requirePermission('usuarios', 'view');
        
        $userId = intval($_GET['user_id'] ?? 0);
        $roles = $authManager->getUserRoles($userId);
        $roleIds = array_column($roles, 'id');
        
        jsonResponse(['success' => true, 'data' => $roleIds]);
        break;

    case 'sync_user_roles':
        requirePermission('usuarios', 'manage_roles');
        
        $userId = intval($input['user_id'] ?? 0);
        $roleIds = $input['role_ids'] ?? [];

        if (!$userId) {
            jsonResponse(['success' => false, 'message' => 'Usuário não informado'], 400);
        }

        $authManager->syncRoles($userId, $roleIds);
        $permissionsManager->clearCache($userId);

        jsonResponse(['success' => true, 'message' => 'Papéis atualizados']);
        break;

    case 'get_role_permissions':
        requirePermission('usuarios', 'view');
        
        $roleId = intval($_GET['role_id'] ?? 0);
        
        $permissions = $permissionsManager->getAllPermissions();
        $rolePermIds = $permissionsManager->getRolePermissionIds($roleId);

        jsonResponse([
            'success' => true,
            'permissions' => $permissions,
            'role_permissions' => $rolePermIds
        ]);
        break;

    case 'sync_role_permissions':
        requirePermission('usuarios', 'manage_permissions');
        
        $roleId = intval($input['role_id'] ?? 0);
        $permissionIds = $input['permission_ids'] ?? [];

        if (!$roleId) {
            jsonResponse(['success' => false, 'message' => 'Papel não informado'], 400);
        }

        $permissionsManager->syncRolePermissions($roleId, $permissionIds);

        jsonResponse(['success' => true, 'message' => 'Permissões atualizadas']);
        break;

    case 'create_role':
        requirePermission('usuarios', 'manage_roles');
        
        $name = trim($input['name'] ?? '');
        $displayName = trim($input['display_name'] ?? '');
        $description = trim($input['description'] ?? '');

        if (empty($name) || empty($displayName)) {
            jsonResponse(['success' => false, 'message' => 'Nome é obrigatório'], 400);
        }

        // Verificar duplicado
        $existing = $db->fetch("SELECT id FROM roles WHERE name = ?", [$name]);
        if ($existing) {
            jsonResponse(['success' => false, 'message' => 'Já existe um papel com este nome'], 400);
        }

        $roleId = $permissionsManager->createRole([
            'name' => $name,
            'display_name' => $displayName,
            'description' => $description
        ]);

        jsonResponse(['success' => true, 'message' => 'Papel criado', 'id' => $roleId]);
        break;

    case 'update_role':
        requirePermission('usuarios', 'manage_roles');
        
        $id = intval($input['id'] ?? 0);
        $displayName = trim($input['display_name'] ?? '');
        $description = trim($input['description'] ?? '');

        if (!$id || empty($displayName)) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $result = $permissionsManager->updateRole($id, [
            'display_name' => $displayName,
            'description' => $description
        ]);

        if ($result) {
            jsonResponse(['success' => true, 'message' => 'Papel atualizado']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Não é possível editar papéis do sistema'], 400);
        }
        break;

    case 'delete_role':
        requirePermission('usuarios', 'manage_roles');
        
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $result = $permissionsManager->deleteRole($id);
        
        if ($result) {
            jsonResponse(['success' => true, 'message' => 'Papel excluído']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Não é possível excluir papéis do sistema'], 400);
        }
        break;

    case 'add_override':
        requirePermission('usuarios', 'manage_permissions');
        
        $userId = intval($input['user_id'] ?? 0);
        $permissionId = intval($input['permission_id'] ?? 0);
        $granted = (bool)($input['granted'] ?? true);

        if (!$userId || !$permissionId) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $permissionsManager->addUserOverride($userId, $permissionId, $granted);

        jsonResponse(['success' => true, 'message' => 'Override adicionado']);
        break;

    case 'remove_override':
        requirePermission('usuarios', 'manage_permissions');
        
        $userId = intval($input['user_id'] ?? 0);
        $permissionId = intval($input['permission_id'] ?? 0);

        if (!$userId || !$permissionId) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $permissionsManager->removeUserOverride($userId, $permissionId);

        jsonResponse(['success' => true, 'message' => 'Override removido']);
        break;

    case 'get_user_overrides':
        requirePermission('usuarios', 'view');
        
        $userId = intval($_GET['user_id'] ?? 0);
        $overrides = $permissionsManager->getUserOverrides($userId);

        jsonResponse(['success' => true, 'data' => $overrides]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
}
