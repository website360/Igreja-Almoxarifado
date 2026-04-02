<?php
/**
 * Listagem de Eventos
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('eventos', 'view');

$pageTitle = 'Eventos';
$db = Database::getInstance();

// Filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroMinisterio = $_GET['ministerio'] ?? '';
$filtroPeriodo = $_GET['periodo'] ?? 'proximos';
$busca = $_GET['busca'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));

// Ordenação
$orderBy = $_GET['order'] ?? 'inicio_at';
$orderDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$allowedColumns = ['titulo', 'inicio_at', 'tipo', 'local', 'status'];
if (!in_array($orderBy, $allowedColumns)) $orderBy = 'inicio_at';

// Construir query
$where = ['1=1'];
$params = [];

if ($filtroStatus) {
    $where[] = 'e.status = ?';
    $params[] = $filtroStatus;
}

if ($filtroTipo) {
    $where[] = 'e.tipo = ?';
    $params[] = $filtroTipo;
}

if ($filtroMinisterio) {
    $where[] = 'e.ministerio_responsavel_id = ?';
    $params[] = $filtroMinisterio;
}

if ($filtroPeriodo === 'proximos') {
    $where[] = 'e.inicio_at >= NOW()';
} elseif ($filtroPeriodo === 'passados') {
    $where[] = 'e.inicio_at < NOW()';
} elseif ($filtroPeriodo === 'hoje') {
    $where[] = 'DATE(e.inicio_at) = CURDATE()';
} elseif ($filtroPeriodo === 'semana') {
    $where[] = 'e.inicio_at BETWEEN ? AND ?';
    $params[] = date('Y-m-d', strtotime('monday this week'));
    $params[] = date('Y-m-d 23:59:59', strtotime('sunday this week'));
} elseif ($filtroPeriodo === 'mes') {
    $where[] = 'MONTH(e.inicio_at) = MONTH(NOW()) AND YEAR(e.inicio_at) = YEAR(NOW())';
}

if ($busca) {
    $where[] = '(e.titulo LIKE ? OR e.descricao LIKE ? OR e.local LIKE ?)';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$whereClause = implode(' AND ', $where);

// Contagem total
$countResult = $db->fetch(
    "SELECT COUNT(*) as total FROM events e WHERE {$whereClause}",
    $params
);
$total = $countResult['total'];
$pagination = paginate($total, $page);

// Buscar eventos
$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];

$eventos = $db->fetchAll(
    "SELECT e.*, m.nome as ministerio_nome, u.nome as criado_por_nome
     FROM events e
     LEFT JOIN ministerios m ON e.ministerio_responsavel_id = m.id
     LEFT JOIN users u ON e.created_by = u.id
     WHERE {$whereClause}
     ORDER BY e.{$orderBy} {$orderDir}
     LIMIT ? OFFSET ?",
    $params
);

// Helper para gerar link de ordenação
function sortLink($column, $label, $currentOrder, $currentDir) {
    $newDir = ($currentOrder === $column && $currentDir === 'DESC') ? 'ASC' : 'DESC';
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
        <h1 class="page-title">Eventos</h1>
        <p class="page-subtitle"><?= $total ?> evento(s) encontrado(s)</p>
    </div>
    <div class="btn-group">
        <?php if (can('eventos', 'export')): ?>
        <button class="btn btn-secondary" onclick="exportToExcel('eventosTable', 'eventos')">
            <i data-lucide="download"></i> Exportar
        </button>
        <?php endif; ?>
        <?php if (can('eventos', 'create')): ?>
        <a href="<?= url('/eventos/criar.php') ?>" class="btn btn-primary">
            <i data-lucide="plus"></i> Novo Evento
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="filters-bar">
    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <div class="filter-group">
            <input type="text" name="busca" class="filter-input" placeholder="Buscar..." value="<?= sanitize($busca) ?>">
        </div>
        
        <div class="filter-group">
            <select name="periodo" class="filter-select">
                <option value="proximos" <?= $filtroPeriodo === 'proximos' ? 'selected' : '' ?>>Próximos</option>
                <option value="hoje" <?= $filtroPeriodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                <option value="semana" <?= $filtroPeriodo === 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                <option value="mes" <?= $filtroPeriodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                <option value="passados" <?= $filtroPeriodo === 'passados' ? 'selected' : '' ?>>Passados</option>
                <option value="todos" <?= $filtroPeriodo === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>

        <div class="filter-group">
            <select name="tipo" class="filter-select">
                <option value="">Todos os Tipos</option>
                <?php foreach (EVENT_TYPES as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtroTipo === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <select name="status" class="filter-select">
                <option value="">Todos os Status</option>
                <?php foreach (EVENT_STATUS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtroStatus === $key ? 'selected' : '' ?>><?= $label ?></option>
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
        
        <a href="<?= url('/eventos') ?>" class="btn btn-secondary">
            <i data-lucide="x"></i> Limpar
        </a>
    </form>
</div>

<!-- Lista de Eventos -->
<div class="card">
    <div class="table-wrapper">
        <table class="table" id="eventosTable">
            <thead>
                <tr>
                    <?= sortLink('titulo', 'Evento', $orderBy, $orderDir) ?>
                    <?= sortLink('inicio_at', 'Data/Hora', $orderBy, $orderDir) ?>
                    <?= sortLink('tipo', 'Tipo', $orderBy, $orderDir) ?>
                    <?= sortLink('local', 'Local', $orderBy, $orderDir) ?>
                    <?= sortLink('status', 'Status', $orderBy, $orderDir) ?>
                    <th>Check-in</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($eventos)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="calendar-x"></i>
                            </div>
                            <h3 class="empty-state-title">Nenhum evento encontrado</h3>
                            <p class="empty-state-text">Ajuste os filtros ou crie um novo evento.</p>
                            <?php if (can('eventos', 'create')): ?>
                            <a href="<?= url('/eventos/criar.php') ?>" class="btn btn-primary">
                                <i data-lucide="plus"></i> Novo Evento
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($eventos as $evento): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <div>
                                    <strong><?= sanitize($evento['titulo']) ?></strong>
                                    <?php if ($evento['ministerio_nome']): ?>
                                    <br><small class="text-muted"><?= sanitize($evento['ministerio_nome']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?= formatDate($evento['inicio_at']) ?></strong><br>
                            <small class="text-muted"><?= date('H:i', strtotime($evento['inicio_at'])) ?>
                            <?php if ($evento['fim_at']): ?>
                             - <?= date('H:i', strtotime($evento['fim_at'])) ?>
                            <?php endif; ?>
                            </small>
                        </td>
                        <td><?= EVENT_TYPES[$evento['tipo']] ?? $evento['tipo'] ?></td>
                        <td><?= sanitize($evento['local'] ?? '-') ?></td>
                        <td><?= statusBadge($evento['status']) ?></td>
                        <td><?= statusBadge($evento['status_checkin']) ?></td>
                        <td>
                            <div class="actions">
                                <button class="btn-action btn-action-view" title="Visualizar" onclick="viewEvento(<?= $evento['id'] ?>)">
                                    <i data-lucide="eye"></i>
                                </button>
                                <?php if (can('eventos', 'edit')): ?>
                                <a href="<?= url('/eventos/criar.php?id=' . $evento['id']) ?>" class="btn-action btn-action-edit" title="Editar">
                                    <i data-lucide="edit-2"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (can('presencas', 'view')): ?>
                                <a href="<?= url('/presencas/evento.php?id=' . $evento['id']) ?>" class="btn-action" title="Presenças" style="background: var(--info-bg); color: var(--info);">
                                    <i data-lucide="users"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (can('eventos', 'delete')): ?>
                                <button class="btn-action btn-action-delete" title="Excluir" onclick="confirmDelete('<?= url('/eventos/api.php?action=delete&id=' . $evento['id']) ?>', null, '<?= sanitize($evento['titulo']) ?>')">
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
        <?= paginationHtml($pagination, url('/eventos')) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Visualização Rápida -->
<div id="viewEventoModal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.5);">
    <div class="modal-panel" style="position: fixed; right: 0; top: 0; height: 100vh; width: 450px; max-width: 100%; margin: 0; border-radius: 0; background: white; box-shadow: -4px 0 20px rgba(0,0,0,0.15); animation: slideInRight 0.3s ease;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--gray-200);">
            <h3 class="modal-title" style="margin: 0;">Dados do Evento</h3>
            <button type="button" class="btn btn-icon btn-sm btn-secondary" onclick="closeViewModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body" id="viewEventoContent" style="padding: 20px; overflow-y: auto; height: calc(100vh - 140px);">
            <div class="loading-spinner" style="text-align: center; padding: 40px;">
                <i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i>
                <p>Carregando...</p>
            </div>
        </div>
        <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--gray-200); display: flex; gap: 10px;">
            <a href="#" id="viewEventoBtn" class="btn btn-secondary" style="flex: 1;">
                <i data-lucide="external-link"></i> Ver Detalhes
            </a>
            <a href="#" id="editEventoBtn" class="btn btn-primary" style="flex: 1;">
                <i data-lucide="edit"></i> Editar
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
.evento-header { text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--gray-200); }
.evento-tipo { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: var(--primary-light); color: var(--primary); margin-bottom: 10px; }
.evento-titulo { font-size: 18px; font-weight: 600; margin: 10px 0 5px; }
.evento-data { color: var(--gray-500); font-size: 14px; }
</style>

<script>
const EVENT_TYPES = <?= json_encode(EVENT_TYPES) ?>;
const EVENT_STATUS = <?= json_encode(EVENT_STATUS) ?>;

function viewEvento(id) {
    const modal = document.getElementById('viewEventoModal');
    const content = document.getElementById('viewEventoContent');
    const viewBtn = document.getElementById('viewEventoBtn');
    const editBtn = document.getElementById('editEventoBtn');
    
    modal.style.display = 'flex';
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i data-lucide="loader-2" style="animation: spin 1s linear infinite;"></i><p>Carregando...</p></div>';
    lucide.createIcons();
    
    viewBtn.href = '<?= url('/eventos/ver.php?id=') ?>' + id;
    editBtn.href = '<?= url('/eventos/criar.php?id=') ?>' + id;
    
    fetch('<?= url('/eventos/api.php?action=view&id=') ?>' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderEventoView(data.data);
            } else {
                content.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(err => {
            content.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados.</div>';
        });
}

function renderEventoView(e) {
    const tipoLabel = EVENT_TYPES[e.tipo] || e.tipo;
    const statusLabel = EVENT_STATUS[e.status] || e.status;
    
    let html = `
        <div class="evento-header">
            <span class="evento-tipo">${escapeHtml(tipoLabel)}</span>
            <h3 class="evento-titulo">${escapeHtml(e.titulo)}</h3>
            <p class="evento-data">
                <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                ${formatDateBr(e.inicio_at)}
                ${e.fim_at ? ' - ' + formatDateBr(e.fim_at) : ''}
            </p>
        </div>
        
        <div class="view-section">
            <div class="view-section-title">Informações</div>
            <div class="view-row">
                <span class="view-label">Status</span>
                <span class="view-value">${statusLabel}</span>
            </div>
            <div class="view-row">
                <span class="view-label">Local</span>
                <span class="view-value">${escapeHtml(e.local || '-')}</span>
            </div>
            <div class="view-row">
                <span class="view-label">Ministério</span>
                <span class="view-value">${escapeHtml(e.ministerio_nome || '-')}</span>
            </div>
            <div class="view-row">
                <span class="view-label">Check-in</span>
                <span class="view-value">${e.status_checkin === 'aberto' ? '<span class="badge badge-success">Aberto</span>' : '<span class="badge badge-secondary">Fechado</span>'}</span>
            </div>
        </div>
        
        ${e.descricao ? `
        <div class="view-section">
            <div class="view-section-title">Descrição</div>
            <p style="color: var(--gray-700); font-size: 14px; line-height: 1.6;">${escapeHtml(e.descricao)}</p>
        </div>
        ` : ''}
        
        <div class="view-section">
            <div class="view-section-title">Check-in</div>
            <div class="view-row">
                <span class="view-label">Abre em</span>
                <span class="view-value">${e.checkin_abre_at ? formatDateBr(e.checkin_abre_at) : '-'}</span>
            </div>
            <div class="view-row">
                <span class="view-label">Fecha em</span>
                <span class="view-value">${e.checkin_fecha_at ? formatDateBr(e.checkin_fecha_at) : '-'}</span>
            </div>
        </div>
        
        <div class="view-section">
            <div class="view-section-title">Criação</div>
            <div class="view-row">
                <span class="view-label">Criado por</span>
                <span class="view-value">${escapeHtml(e.criado_por_nome || '-')}</span>
            </div>
            <div class="view-row">
                <span class="view-label">Criado em</span>
                <span class="view-value">${formatDateBr(e.created_at)}</span>
            </div>
        </div>
    `;
    
    document.getElementById('viewEventoContent').innerHTML = html;
    lucide.createIcons();
}

function closeViewModal() {
    document.getElementById('viewEventoModal').style.display = 'none';
}

document.getElementById('viewEventoModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});

function formatDateBr(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
