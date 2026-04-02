<?php
/**
 * Listagem de Presenças
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('presencas', 'view');

$pageTitle = 'Presenças';
$db = Database::getInstance();

// Filtros
$filtroEvento = $_GET['evento'] ?? '';
$filtroStatus = $_GET['status'] ?? '';
$filtroPeriodo = $_GET['periodo'] ?? 'mes';
$busca = $_GET['busca'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));

// Ordenação
$orderBy = $_GET['order'] ?? 'evento_data';
$orderDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$allowedColumns = ['pessoa_nome', 'evento_titulo', 'evento_data', 'status', 'checkin_at'];
if (!in_array($orderBy, $allowedColumns)) $orderBy = 'evento_data';

// Construir query
$where = ['1=1'];
$params = [];

// Se não é admin/líder, mostrar apenas presenças próprias
$auth = new Auth();
if (!$auth->hasRole($currentUser['id'], 'admin') && !$auth->hasRole($currentUser['id'], 'lider') && !$auth->hasRole($currentUser['id'], 'secretaria')) {
    $where[] = 'a.person_id = ?';
    $params[] = $currentUser['id'];
}

if ($filtroEvento) {
    $where[] = 'a.event_id = ?';
    $params[] = $filtroEvento;
}

if ($filtroStatus) {
    $where[] = 'a.status = ?';
    $params[] = $filtroStatus;
}

if ($filtroPeriodo === 'hoje') {
    $where[] = 'DATE(e.inicio_at) = CURDATE()';
} elseif ($filtroPeriodo === 'semana') {
    $where[] = 'e.inicio_at BETWEEN ? AND ?';
    $params[] = date('Y-m-d', strtotime('monday this week'));
    $params[] = date('Y-m-d 23:59:59', strtotime('sunday this week'));
} elseif ($filtroPeriodo === 'mes') {
    $where[] = 'MONTH(e.inicio_at) = MONTH(NOW()) AND YEAR(e.inicio_at) = YEAR(NOW())';
}

if ($busca) {
    $where[] = '(u.nome LIKE ? OR e.titulo LIKE ?)';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$whereClause = implode(' AND ', $where);

// Contagem
$countParams = $params;
$countResult = $db->fetch(
    "SELECT COUNT(*) as total FROM attendance a
     JOIN events e ON a.event_id = e.id
     JOIN users u ON a.person_id = u.id
     WHERE {$whereClause}",
    $countParams
);
$total = $countResult['total'];
$pagination = paginate($total, $page);

// Buscar presenças
$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];

$presencas = $db->fetchAll(
    "SELECT a.*, e.titulo as evento_titulo, e.inicio_at as evento_data, 
            u.nome as pessoa_nome, u.email as pessoa_email,
            m.nome as marked_by_nome
     FROM attendance a
     JOIN events e ON a.event_id = e.id
     JOIN users u ON a.person_id = u.id
     LEFT JOIN users m ON a.marked_by = m.id
     WHERE {$whereClause}
     ORDER BY {$orderBy} {$orderDir}
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

// Eventos para filtro
$eventos = $db->fetchAll(
    "SELECT id, titulo, inicio_at FROM events WHERE inicio_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ORDER BY inicio_at DESC"
);

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Presenças</h1>
        <p class="page-subtitle"><?= $total ?> registro(s)</p>
    </div>
    <?php if (can('presencas', 'create')): ?>
    <a href="<?= url('/presencas/checkin.php') ?>" class="btn btn-primary">
        <i data-lucide="check-circle"></i> Fazer Check-in
    </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="filters-bar">
    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <div class="filter-group">
            <input type="text" name="busca" class="filter-input" placeholder="Buscar pessoa ou evento..." value="<?= sanitize($busca) ?>">
        </div>
        
        <div class="filter-group">
            <select name="periodo" class="filter-select">
                <option value="mes" <?= $filtroPeriodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                <option value="semana" <?= $filtroPeriodo === 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                <option value="hoje" <?= $filtroPeriodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                <option value="todos" <?= $filtroPeriodo === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>

        <div class="filter-group">
            <select name="evento" class="filter-select">
                <option value="">Todos os Eventos</option>
                <?php foreach ($eventos as $ev): ?>
                <option value="<?= $ev['id'] ?>" <?= $filtroEvento == $ev['id'] ? 'selected' : '' ?>>
                    <?= sanitize($ev['titulo']) ?> (<?= formatDate($ev['inicio_at']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <select name="status" class="filter-select">
                <option value="">Todos os Status</option>
                <?php foreach (ATTENDANCE_STATUS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtroStatus === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            <i data-lucide="search"></i> Filtrar
        </button>
        
        <a href="<?= url('/presencas') ?>" class="btn btn-secondary">
            <i data-lucide="x"></i> Limpar
        </a>
    </form>
</div>

<!-- Lista -->
<div class="card">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <?= sortLink('pessoa_nome', 'Pessoa', $orderBy, $orderDir) ?>
                    <?= sortLink('evento_titulo', 'Evento', $orderBy, $orderDir) ?>
                    <?= sortLink('evento_data', 'Data do Evento', $orderBy, $orderDir) ?>
                    <?= sortLink('status', 'Status', $orderBy, $orderDir) ?>
                    <th>Método</th>
                    <?= sortLink('checkin_at', 'Check-in em', $orderBy, $orderDir) ?>
                    <?php if (can('presencas', 'edit')): ?>
                    <th class="text-right">Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($presencas)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="check-circle"></i>
                            </div>
                            <h3 class="empty-state-title">Nenhuma presença encontrada</h3>
                            <p class="empty-state-text">Ajuste os filtros ou faça um check-in.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($presencas as $p): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($p['pessoa_nome']) ?>">
                                    <?= getInitials($p['pessoa_nome']) ?>
                                </div>
                                <div>
                                    <strong><?= sanitize($p['pessoa_nome']) ?></strong><br>
                                    <small class="text-muted"><?= sanitize($p['pessoa_email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="<?= url('/eventos/ver.php?id=' . $p['event_id']) ?>" class="text-primary">
                                <?= sanitize($p['evento_titulo']) ?>
                            </a>
                        </td>
                        <td><?= formatDate($p['evento_data']) ?></td>
                        <td><?= statusBadge($p['status']) ?></td>
                        <td><?= CHECKIN_METHODS[$p['checkin_method']] ?? $p['checkin_method'] ?></td>
                        <td>
                            <?= $p['checkin_at'] ? formatDateTime($p['checkin_at']) : '-' ?>
                            <?php if ($p['marked_by_nome']): ?>
                            <br><small class="text-muted">por <?= sanitize($p['marked_by_nome']) ?></small>
                            <?php endif; ?>
                        </td>
                        <?php if (can('presencas', 'edit')): ?>
                        <td>
                            <div class="actions">
                                <button class="btn btn-icon btn-sm btn-secondary" title="Editar" 
                                        onclick="editarPresenca(<?= $p['id'] ?>, '<?= $p['status'] ?>')">
                                    <i data-lucide="edit"></i>
                                </button>
                                <?php if (can('presencas', 'delete')): ?>
                                <button class="btn btn-icon btn-sm btn-outline-danger" title="Excluir"
                                        onclick="confirmDelete('<?= url('/presencas/api.php?action=delete&id=' . $p['id']) ?>')">
                                    <i data-lucide="trash-2"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <?= paginationHtml($pagination, url('/presencas')) ?>
    </div>
    <?php endif; ?>
</div>

<script>
function editarPresenca(id, statusAtual) {
    openModal({
        title: 'Editar Presença',
        body: `
            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="novoStatus" class="form-control">
                    <option value="presente" ${statusAtual === 'presente' ? 'selected' : ''}>Presente</option>
                    <option value="ausente" ${statusAtual === 'ausente' ? 'selected' : ''}>Ausente</option>
                    <option value="justificado" ${statusAtual === 'justificado' ? 'selected' : ''}>Justificado</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Observações</label>
                <textarea id="notas" class="form-control" rows="2"></textarea>
            </div>
        `,
        footer: `
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="salvarPresenca(${id})">Salvar</button>
        `
    });
}

function salvarPresenca(id) {
    const status = document.getElementById('novoStatus').value;
    const notes = document.getElementById('notas').value;

    fetch('<?= url('/presencas/api.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken
        },
        body: JSON.stringify({
            action: 'update',
            id: id,
            status: status,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Presença atualizada', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro ao atualizar', 'error');
        }
    })
    .catch(error => {
        showToast('Erro ao processar requisição', 'error');
    });
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
