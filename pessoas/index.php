<?php
/**
 * Listagem de Pessoas
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('pessoas', 'view');

$pageTitle = 'Pessoas';
$db = Database::getInstance();

// Filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroCargo = $_GET['cargo'] ?? '';
$filtroMinisterio = $_GET['ministerio'] ?? '';
$busca = $_GET['busca'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));

// Ordenação
$orderBy = $_GET['order'] ?? 'nome';
$orderDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$allowedColumns = ['nome', 'email', 'cargo', 'status', 'data_entrada', 'total_presencas'];
if (!in_array($orderBy, $allowedColumns)) $orderBy = 'nome';

// Construir query
$where = ['1=1'];
$params = [];

if ($filtroStatus) {
    $where[] = 'u.status = ?';
    $params[] = $filtroStatus;
}

if ($filtroCargo) {
    $where[] = 'u.cargo = ?';
    $params[] = $filtroCargo;
}

if ($filtroMinisterio) {
    $where[] = 'u.ministerio_id = ?';
    $params[] = $filtroMinisterio;
}

if ($busca) {
    $where[] = '(u.nome LIKE ? OR u.email LIKE ? OR u.telefone_whatsapp LIKE ?)';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$whereClause = implode(' AND ', $where);

// Contagem
$countResult = $db->fetch(
    "SELECT COUNT(*) as total FROM users u WHERE {$whereClause}",
    $params
);
$total = $countResult['total'];
$pagination = paginate($total, $page);

// Buscar pessoas
$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];

$orderColumn = $orderBy === 'total_presencas' ? 'total_presencas' : "u.{$orderBy}";
$pessoas = $db->fetchAll(
    "SELECT u.*, m.nome as ministerio_nome,
            (SELECT COUNT(*) FROM attendance WHERE person_id = u.id AND status = 'presente') as total_presencas
     FROM users u
     LEFT JOIN ministerios m ON u.ministerio_id = m.id
     WHERE {$whereClause}
     ORDER BY {$orderColumn} {$orderDir}
     LIMIT ? OFFSET ?",
    $params
);

// Helper para gerar link de ordenação
function sortLink($column, $label, $currentOrder, $currentDir) {
    $newDir = ($currentOrder === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['order'] = $column;
    $params['dir'] = $newDir;
    $class = $currentOrder === $column ? 'sortable ' . strtolower($currentDir) : 'sortable';
    return '<th class="' . $class . '" onclick="window.location.href=\'?' . http_build_query($params) . '\'">' . $label . '</th>';
}

// Ministérios para filtro
$ministerios = $db->fetchAll("SELECT id, nome FROM ministerios WHERE ativo = 1 ORDER BY nome");

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Pessoas</h1>
        <p class="page-subtitle"><?= $total ?> pessoa(s) cadastrada(s)</p>
    </div>
    <div class="btn-group">
        <?php if (can('pessoas', 'export')): ?>
        <button class="btn btn-secondary" onclick="exportToExcel('pessoasTable', 'pessoas')">
            <i data-lucide="download"></i> Exportar
        </button>
        <?php endif; ?>
        <?php if (can('pessoas', 'create')): ?>
        <a href="<?= url('/pessoas/criar.php') ?>" class="btn btn-primary">
            <i data-lucide="user-plus"></i> Nova Pessoa
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="filters-bar">
    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <div class="filter-group">
            <input type="text" name="busca" class="filter-input" placeholder="Buscar nome, email, telefone..." value="<?= sanitize($busca) ?>">
        </div>
        
        <div class="filter-group">
            <select name="status" class="filter-select">
                <option value="">Todos os Status</option>
                <option value="ativo" <?= $filtroStatus === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                <option value="inativo" <?= $filtroStatus === 'inativo' ? 'selected' : '' ?>>Inativo</option>
            </select>
        </div>

        <div class="filter-group">
            <select name="cargo" class="filter-select">
                <option value="">Todos os Cargos</option>
                <?php foreach (MEMBER_POSITIONS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtroCargo === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <select name="ministerio" class="filter-select">
                <option value="">Todos os Ministérios</option>
                <?php foreach ($ministerios as $min): ?>
                <option value="<?= $min['id'] ?>" <?= $filtroMinisterio == $min['id'] ? 'selected' : '' ?>><?= sanitize($min['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            <i data-lucide="search"></i> Filtrar
        </button>
        
        <a href="<?= url('/pessoas') ?>" class="btn btn-secondary">
            <i data-lucide="x"></i> Limpar
        </a>
    </form>
</div>

<!-- Lista -->
<div class="card">
    <div class="table-wrapper">
        <table class="table" id="pessoasTable">
            <thead>
                <tr>
                    <?= sortLink('nome', 'Nome', $orderBy, $orderDir) ?>
                    <th>Contato</th>
                    <?= sortLink('cargo', 'Cargo', $orderBy, $orderDir) ?>
                    <th>Ministério</th>
                    <?= sortLink('total_presencas', 'Presenças', $orderBy, $orderDir) ?>
                    <?= sortLink('status', 'Status', $orderBy, $orderDir) ?>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pessoas)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="users"></i>
                            </div>
                            <h3 class="empty-state-title">Nenhuma pessoa encontrada</h3>
                            <p class="empty-state-text">Ajuste os filtros ou cadastre uma nova pessoa.</p>
                            <?php if (can('pessoas', 'create')): ?>
                            <a href="<?= url('/pessoas/criar.php') ?>" class="btn btn-primary">
                                <i data-lucide="user-plus"></i> Nova Pessoa
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($pessoas as $pessoa): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <?php if (!empty($pessoa['foto_url'])): ?>
                                <img src="<?= url($pessoa['foto_url']) ?>" alt="<?= sanitize($pessoa['nome']) ?>" class="user-avatar-sm" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($pessoa['nome']) ?>">
                                    <?= getInitials($pessoa['nome']) ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?= sanitize($pessoa['nome']) ?></strong>
                                    <?php if ($pessoa['data_entrada']): ?>
                                    <br><small class="text-muted">Desde <?= formatDatePt($pessoa['data_entrada'], 'M/Y') ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?= sanitize($pessoa['email']) ?><br>
                            <small class="text-muted"><?= formatPhone($pessoa['telefone_whatsapp']) ?></small>
                        </td>
                        <td><?= MEMBER_POSITIONS[$pessoa['cargo']] ?? $pessoa['cargo'] ?></td>
                        <td><?= sanitize($pessoa['ministerio_nome'] ?? '-') ?></td>
                        <td>
                            <span class="badge badge-primary"><?= $pessoa['total_presencas'] ?></span>
                        </td>
                        <td><?= statusBadge($pessoa['status']) ?></td>
                        <td>
                            <div class="actions">
                                <button class="btn-action btn-action-view" title="Visualizar" onclick="viewPessoa(<?= $pessoa['id'] ?>)">
                                    <i data-lucide="eye"></i>
                                </button>
                                <?php if (can('pessoas', 'edit')): ?>
                                <a href="<?= url('/pessoas/criar.php?id=' . $pessoa['id']) ?>" class="btn-action btn-action-edit" title="Editar">
                                    <i data-lucide="edit-2"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (can('pessoas', 'delete') && $pessoa['id'] !== $currentUser['id']): ?>
                                <button class="btn-action btn-action-delete" title="Excluir" onclick="confirmDelete('<?= url('/pessoas/api.php?action=delete&id=' . $pessoa['id']) ?>', null, '<?= sanitize($pessoa['nome']) ?>')">
                                    <i data-lucide="trash-2"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <?= paginationHtml($pagination, url('/pessoas')) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Visualização Rápida -->
<div id="viewPessoaModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.5);">
    <div class="modal-panel" style="position: fixed; right: 0; top: 0; height: 100vh; width: 450px; max-width: 100%; margin: 0; border-radius: 0; background: white; box-shadow: -4px 0 20px rgba(0,0,0,0.15); animation: slideInRight 0.3s ease;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-200);">
            <h3 class="modal-title" style="margin: 0;">Dados da Pessoa</h3>
            <button type="button" class="btn btn-icon btn-sm btn-secondary" onclick="closeViewModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body" id="viewPessoaContent" style="padding: 20px; overflow-y: auto; height: calc(100vh - 140px);">
            <div class="loading-spinner" style="text-align: center; padding: 40px;">
                <i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i>
                <p>Carregando...</p>
            </div>
        </div>
        <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--gray-200);">
            <a href="#" id="editPessoaBtn" class="btn btn-primary">
                <i data-lucide="edit"></i> Editar Pessoa
            </a>
        </div>
    </div>
</div>

<style>
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.view-section { margin-bottom: 20px; }
.view-section-title { font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 12px; }
.view-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--gray-100); }
.view-label { color: var(--gray-600); font-size: 14px; }
.view-value { font-weight: 500; color: var(--gray-900); font-size: 14px; text-align: right; }
.view-avatar { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 600; color: white; }
.view-name { text-align: center; font-size: 18px; font-weight: 600; margin-bottom: 5px; }
.view-email { text-align: center; color: var(--gray-500); font-size: 14px; margin-bottom: 15px; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
.stat-card { background: var(--gray-50); padding: 12px; border-radius: 8px; text-align: center; }
.stat-value { font-size: 20px; font-weight: 700; color: var(--primary); }
.stat-label { font-size: 11px; color: var(--gray-500); text-transform: uppercase; }
.docs-list { display: flex; flex-direction: column; gap: 8px; }
.doc-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: var(--gray-50); border-radius: 8px; }
.doc-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--gray-200); color: var(--gray-600); }
.doc-icon svg { width: 18px; height: 18px; }
.doc-icon.image { background: var(--info-bg); color: var(--info); }
.doc-icon.pdf { background: var(--danger-bg); color: var(--danger); }
.doc-info { flex: 1; min-width: 0; }
.doc-tipo { font-weight: 600; font-size: 13px; color: var(--gray-800); }
.doc-nome { font-size: 11px; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-actions { display: flex; gap: 4px; }
</style>

<script>
const MEMBER_POSITIONS = <?= json_encode(MEMBER_POSITIONS) ?>;

function viewPessoa(id) {
    const modal = document.getElementById('viewPessoaModal');
    const content = document.getElementById('viewPessoaContent');
    const editBtn = document.getElementById('editPessoaBtn');
    
    modal.style.display = 'flex';
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i><p>Carregando...</p></div>';
    lucide.createIcons();
    
    editBtn.href = '<?= url('/pessoas/criar.php?id=') ?>' + id;
    
    fetch('<?= url('/pessoas/api.php?action=view&id=') ?>' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderPessoaView(data.data);
            } else {
                content.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(err => {
            content.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados.</div>';
        });
}

function renderPessoaView(data) {
    const p = data.pessoa;
    const s = data.stats;
    const docs = data.documentos || [];
    const avatarColor = getAvatarColor(p.nome);
    const initials = getInitials(p.nome);
    
    let html = `
        <div style="text-align: center; margin-bottom: 20px;">
            ${p.foto_url 
                ? `<img src="<?= url('') ?>${p.foto_url}" alt="${escapeHtml(p.nome)}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">`
                : `<div class="view-avatar" style="background-color: ${avatarColor}">${initials}</div>`
            }
            <div class="view-name">${escapeHtml(p.nome)}</div>
            <div class="view-email">${escapeHtml(p.email)}</div>
            <span class="badge badge-${p.status === 'ativo' ? 'success' : 'secondary'}">${p.status}</span>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">${s.presentes || 0}</div>
                <div class="stat-label">Presenças</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${s.ausentes || 0}</div>
                <div class="stat-label">Ausências</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${s.justificados || 0}</div>
                <div class="stat-label">Justificados</div>
            </div>
        </div>
        
        <div class="view-section">
            <div class="view-section-title">Informações Pessoais</div>
            <div class="view-row"><span class="view-label">Telefone</span><span class="view-value">${p.telefone_whatsapp || '-'}</span></div>
            <div class="view-row"><span class="view-label">CPF</span><span class="view-value">${p.cpf || '-'}</span></div>
            <div class="view-row"><span class="view-label">Data Nascimento</span><span class="view-value">${formatDate(p.data_nascimento)}</span></div>
        </div>
        
        <div class="view-section">
            <div class="view-section-title">Informações da Igreja</div>
            <div class="view-row"><span class="view-label">Cargo</span><span class="view-value">${MEMBER_POSITIONS[p.cargo] || p.cargo}</span></div>
            <div class="view-row"><span class="view-label">Ministério</span><span class="view-value">${p.ministerio_nome || '-'}</span></div>
            <div class="view-row"><span class="view-label">Data Entrada</span><span class="view-value">${formatDate(p.data_entrada)}</span></div>
            <div class="view-row"><span class="view-label">Data Batismo</span><span class="view-value">${formatDate(p.data_batismo)}</span></div>
        </div>
        
        ${p.logradouro ? `
        <div class="view-section">
            <div class="view-section-title">Endereço</div>
            <div class="view-row"><span class="view-label">Logradouro</span><span class="view-value">${escapeHtml(p.logradouro)}${p.numero ? ', ' + p.numero : ''}</span></div>
            ${p.complemento ? `<div class="view-row"><span class="view-label">Complemento</span><span class="view-value">${escapeHtml(p.complemento)}</span></div>` : ''}
            <div class="view-row"><span class="view-label">Bairro</span><span class="view-value">${escapeHtml(p.bairro || '-')}</span></div>
            <div class="view-row"><span class="view-label">Cidade/UF</span><span class="view-value">${escapeHtml(p.cidade || '-')}${p.estado ? '/' + p.estado : ''}</span></div>
            <div class="view-row"><span class="view-label">CEP</span><span class="view-value">${p.cep || '-'}</span></div>
        </div>
        ` : ''}
        
        <div class="view-section">
            <div class="view-section-title">
                <i data-lucide="file-text" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                Documentos (${docs.length})
            </div>
            ${docs.length === 0 ? `
                <div style="text-align: center; padding: 20px; color: var(--gray-500);">
                    <i data-lucide="file-x" style="width: 32px; height: 32px; margin-bottom: 8px; opacity: 0.5;"></i>
                    <p style="margin: 0; font-size: 13px;">Nenhum documento anexado</p>
                </div>
            ` : `
                <div class="docs-list">
                    ${docs.map(doc => {
                        const ext = doc.arquivo_nome.split('.').pop().toLowerCase();
                        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(ext);
                        const icon = isImage ? 'image' : (ext === 'pdf' ? 'file-text' : 'file');
                        return `
                            <div class="doc-item">
                                <div class="doc-icon ${isImage ? 'image' : (ext === 'pdf' ? 'pdf' : '')}">
                                    <i data-lucide="${icon}"></i>
                                </div>
                                <div class="doc-info">
                                    <div class="doc-tipo">${escapeHtml(doc.tipo_documento)}</div>
                                    <div class="doc-nome">${escapeHtml(doc.arquivo_nome)}</div>
                                </div>
                                <div class="doc-actions">
                                    <a href="<?= url('') ?>${doc.arquivo_url}" target="_blank" class="btn btn-icon btn-sm btn-secondary" title="Visualizar">
                                        <i data-lucide="eye"></i>
                                    </a>
                                    <a href="<?= url('') ?>${doc.arquivo_url}" download class="btn btn-icon btn-sm btn-secondary" title="Baixar">
                                        <i data-lucide="download"></i>
                                    </a>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `}
        </div>
    `;
    
    document.getElementById('viewPessoaContent').innerHTML = html;
    lucide.createIcons();
}

function closeViewModal() {
    document.getElementById('viewPessoaModal').style.display = 'none';
}

function getAvatarColor(name) {
    const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
}

function getInitials(name) {
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(date) {
    if (!date) return '-';
    const d = new Date(date);
    return d.toLocaleDateString('pt-BR');
}

// Fechar modal ao clicar fora (no fundo escuro)
document.getElementById('viewPessoaModal').addEventListener('click', function(e) {
    // Verifica se clicou diretamente no overlay (fundo escuro) e não no painel
    if (e.target.id === 'viewPessoaModal') {
        closeViewModal();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeViewModal();
});
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
