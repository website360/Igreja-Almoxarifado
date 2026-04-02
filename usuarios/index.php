<?php
/**
 * Usuários e Permissões
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('usuarios', 'view');

$pageTitle = 'Usuários & Permissões';
$db = Database::getInstance();
$permissionsManager = new Permissions();

// Tabs
$tab = $_GET['tab'] ?? 'usuarios';

// Usuários
$usuarios = $db->fetchAll(
    "SELECT u.*, GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles_names
     FROM users u
     LEFT JOIN user_roles ur ON u.id = ur.user_id
     LEFT JOIN roles r ON ur.role_id = r.id
     GROUP BY u.id
     ORDER BY u.nome"
);

// Papéis
$roles = $permissionsManager->getAllRoles();

// Permissões agrupadas
$permissionsGrouped = $permissionsManager->getPermissionsGrouped();

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Usuários & Permissões</h1>
        <p class="page-subtitle">Gerenciamento de acesso ao sistema</p>
    </div>
</div>

<div class="tabs mb-3">
    <a href="?tab=usuarios" class="tab-link <?= $tab === 'usuarios' ? 'active' : '' ?>">
        <i data-lucide="users"></i> Usuários
    </a>
    <a href="?tab=papeis" class="tab-link <?= $tab === 'papeis' ? 'active' : '' ?>">
        <i data-lucide="shield"></i> Papéis
    </a>
    <a href="?tab=matriz" class="tab-link <?= $tab === 'matriz' ? 'active' : '' ?>">
        <i data-lucide="grid"></i> Matriz de Permissões
    </a>
</div>

<?php if ($tab === 'usuarios'): ?>
<!-- Lista de Usuários -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Usuários do Sistema</h3>
        <?php if (can('usuarios', 'create')): ?>
        <a href="<?= url('/pessoas/criar.php') ?>" class="btn btn-sm btn-primary">
            <i data-lucide="user-plus"></i> Novo Usuário
        </a>
        <?php endif; ?>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Email</th>
                    <th>Papéis</th>
                    <th>Status</th>
                    <th>Último Login</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $user): ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-1">
                            <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($user['nome']) ?>">
                                <?= getInitials($user['nome']) ?>
                            </div>
                            <strong><?= sanitize($user['nome']) ?></strong>
                        </div>
                    </td>
                    <td><?= sanitize($user['email']) ?></td>
                    <td>
                        <?php 
                        $rolesArray = explode(', ', $user['roles_names'] ?? '');
                        foreach ($rolesArray as $roleName): 
                            if ($roleName):
                        ?>
                        <span class="badge badge-primary"><?= sanitize($roleName) ?></span>
                        <?php endif; endforeach; ?>
                    </td>
                    <td><?= statusBadge($user['status']) ?></td>
                    <td><?= $user['last_login_at'] ? timeAgo($user['last_login_at']) : 'Nunca' ?></td>
                    <td>
                        <div class="actions">
                            <?php if (can('usuarios', 'edit')): ?>
                            <button class="btn btn-icon btn-sm btn-secondary" title="Gerenciar Papéis" 
                                    onclick="gerenciarPapeis(<?= $user['id'] ?>, '<?= sanitize($user['nome']) ?>')">
                                <i data-lucide="shield"></i>
                            </button>
                            <a href="<?= url('/pessoas/criar.php?id=' . $user['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Editar">
                                <i data-lucide="edit"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'papeis'): ?>
<!-- Lista de Papéis -->
<div class="grid grid-2" style="gap: 24px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Papéis do Sistema</h3>
            <?php if (can('usuarios', 'manage_roles')): ?>
            <button class="btn btn-sm btn-primary" onclick="novoPapel()">
                <i data-lucide="plus"></i> Novo Papel
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php foreach ($roles as $role): ?>
            <div class="d-flex justify-between align-center mb-2" style="padding: 16px; background: var(--gray-50); border-radius: var(--border-radius);">
                <div>
                    <strong><?= sanitize($role['display_name']) ?></strong>
                    <?php if ($role['is_system']): ?>
                    <span class="badge badge-secondary">Sistema</span>
                    <?php endif; ?>
                    <br><small class="text-muted"><?= sanitize($role['description'] ?? '') ?></small>
                </div>
                <?php if (can('usuarios', 'manage_permissions') && !$role['is_system']): ?>
                <div class="d-flex gap-1">
                    <button class="btn btn-icon btn-sm btn-secondary" onclick="editarPermissoesPapel(<?= $role['id'] ?>, '<?= sanitize($role['display_name']) ?>')">
                        <i data-lucide="key"></i>
                    </button>
                    <button class="btn btn-icon btn-sm btn-outline-danger" onclick="excluirPapel(<?= $role['id'] ?>)">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
                <?php elseif (can('usuarios', 'manage_permissions')): ?>
                <button class="btn btn-sm btn-secondary" onclick="editarPermissoesPapel(<?= $role['id'] ?>, '<?= sanitize($role['display_name']) ?>')">
                    <i data-lucide="key"></i> Ver Permissões
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ações Disponíveis</h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2">Ações que podem ser atribuídas por módulo:</p>
            <?php foreach (PERMISSION_ACTIONS as $key => $label): ?>
            <div class="d-flex align-center gap-1 mb-1">
                <code><?= $key ?></code>
                <span class="text-muted">- <?= $label ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($tab === 'matriz'): ?>
<!-- Matriz de Permissões -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Matriz de Permissões</h3>
    </div>
    <div class="table-wrapper" style="overflow-x: auto;">
        <table class="table" style="min-width: 800px;">
            <thead>
                <tr>
                    <th>Módulo / Ação</th>
                    <?php foreach ($roles as $role): ?>
                    <th class="text-center" style="min-width: 100px;"><?= sanitize($role['display_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissionsGrouped as $module => $perms): ?>
                <tr style="background: var(--gray-50);">
                    <td colspan="<?= count($roles) + 1 ?>">
                        <strong><?= MODULES[$module]['name'] ?? ucfirst($module) ?></strong>
                    </td>
                </tr>
                <?php foreach ($perms as $perm): ?>
                <tr>
                    <td style="padding-left: 24px;">
                        <?= PERMISSION_ACTIONS[$perm['action']] ?? $perm['action'] ?>
                        <small class="text-muted">(<?= $perm['action'] ?>)</small>
                    </td>
                    <?php foreach ($roles as $role): 
                        $rolePermIds = $permissionsManager->getRolePermissionIds($role['id']);
                        $hasPermission = in_array($perm['id'], $rolePermIds);
                    ?>
                    <td class="text-center">
                        <?php if ($hasPermission): ?>
                        <i data-lucide="check" style="color: var(--success);"></i>
                        <?php else: ?>
                        <i data-lucide="x" style="color: var(--gray-300);"></i>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const roles = <?= json_encode($roles) ?>;

function gerenciarPapeis(userId, userName) {
    fetch('<?= url('/usuarios/api.php') ?>?action=get_user_roles&user_id=' + userId)
        .then(r => r.json())
        .then(data => {
            const userRoles = data.data || [];
            let checkboxes = '';
            roles.forEach(role => {
                const checked = userRoles.includes(role.id) ? 'checked' : '';
                checkboxes += `
                    <div class="form-check mb-1">
                        <input type="checkbox" id="role_${role.id}" value="${role.id}" class="form-check-input role-checkbox" ${checked}>
                        <label for="role_${role.id}" class="form-check-label">
                            <strong>${role.display_name}</strong>
                            <br><small class="text-muted">${role.description || ''}</small>
                        </label>
                    </div>
                `;
            });
            
            openModal({
                title: 'Papéis de ' + userName,
                body: checkboxes,
                footer: `
                    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button class="btn btn-primary" onclick="salvarPapeisUsuario(${userId})">Salvar</button>
                `
            });
        });
}

function salvarPapeisUsuario(userId) {
    const roleIds = [];
    document.querySelectorAll('.role-checkbox:checked').forEach(cb => {
        roleIds.push(parseInt(cb.value));
    });
    
    fetch('<?= url('/usuarios/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({ action: 'sync_user_roles', user_id: userId, role_ids: roleIds })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Papéis atualizados!', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro', 'error');
        }
    });
}

function novoPapel() {
    openModal({
        title: 'Novo Papel',
        body: `
            <div class="form-group">
                <label class="form-label required">Nome Interno</label>
                <input type="text" id="roleName" class="form-control" placeholder="ex: coordenador">
            </div>
            <div class="form-group">
                <label class="form-label required">Nome de Exibição</label>
                <input type="text" id="roleDisplayName" class="form-control" placeholder="ex: Coordenador">
            </div>
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea id="roleDescription" class="form-control" rows="2"></textarea>
            </div>
        `,
        footer: `
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="salvarPapel()">Criar</button>
        `
    });
}

function salvarPapel() {
    fetch('<?= url('/usuarios/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({
            action: 'create_role',
            name: document.getElementById('roleName').value,
            display_name: document.getElementById('roleDisplayName').value,
            description: document.getElementById('roleDescription').value
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Papel criado!', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro', 'error');
        }
    });
}

function excluirPapel(id) {
    confirmAction('Excluir este papel?', () => {
        fetch('<?= url('/usuarios/api.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
            body: JSON.stringify({ action: 'delete_role', id: id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Papel excluído!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Erro', 'error');
            }
        });
    });
}

function editarPermissoesPapel(roleId, roleName) {
    fetch('<?= url('/usuarios/api.php') ?>?action=get_role_permissions&role_id=' + roleId)
        .then(r => r.json())
        .then(data => {
            const perms = data.permissions || [];
            const rolePerms = data.role_permissions || [];
            
            let html = '<div style="max-height: 400px; overflow-y: auto;">';
            let currentModule = '';
            
            perms.forEach(p => {
                if (p.module !== currentModule) {
                    if (currentModule) html += '</div>';
                    html += `<div class="mb-2"><strong>${p.module.toUpperCase()}</strong></div><div style="padding-left: 16px;">`;
                    currentModule = p.module;
                }
                const checked = rolePerms.includes(p.id) ? 'checked' : '';
                html += `
                    <div class="form-check">
                        <input type="checkbox" id="perm_${p.id}" value="${p.id}" class="form-check-input perm-checkbox" ${checked}>
                        <label for="perm_${p.id}" class="form-check-label">${p.action}</label>
                    </div>
                `;
            });
            html += '</div></div>';
            
            openModal({
                title: 'Permissões: ' + roleName,
                body: html,
                footer: `
                    <button class="btn btn-secondary" onclick="closeModal()">Fechar</button>
                    <button class="btn btn-primary" onclick="salvarPermissoesPapel(${roleId})">Salvar</button>
                `,
                size: 'lg'
            });
        });
}

function salvarPermissoesPapel(roleId) {
    const permIds = [];
    document.querySelectorAll('.perm-checkbox:checked').forEach(cb => {
        permIds.push(parseInt(cb.value));
    });
    
    fetch('<?= url('/usuarios/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({ action: 'sync_role_permissions', role_id: roleId, permission_ids: permIds })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Permissões atualizadas!', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro', 'error');
        }
    });
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
