<?php
/**
 * Listagem de Justificativas
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('justificativas', 'view');

$pageTitle = 'Justificativas';
$db = Database::getInstance();

// Filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroEvento = $_GET['evento'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));

// Ordenação
$orderBy = $_GET['order'] ?? 'created_at';
$orderDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$allowedColumns = ['pessoa_nome', 'evento_titulo', 'status', 'created_at'];
if (!in_array($orderBy, $allowedColumns)) $orderBy = 'created_at';

$where = ['1=1'];
$params = [];

// Se não é admin/líder, mostrar apenas próprias justificativas
$auth = new Auth();
if (!$auth->hasRole($currentUser['id'], 'admin') && !$auth->hasRole($currentUser['id'], 'lider')) {
    $where[] = 'j.person_id = ?';
    $params[] = $currentUser['id'];
}

if ($filtroStatus) {
    $where[] = 'j.status = ?';
    $params[] = $filtroStatus;
}

if ($filtroEvento) {
    $where[] = 'j.event_id = ?';
    $params[] = $filtroEvento;
}

$whereClause = implode(' AND ', $where);

// Contagem
$countResult = $db->fetch(
    "SELECT COUNT(*) as total FROM absence_justifications j WHERE {$whereClause}",
    $params
);
$total = $countResult['total'];
$pagination = paginate($total, $page);

$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];

$justificativas = $db->fetchAll(
    "SELECT j.*, e.titulo as evento_titulo, e.inicio_at as evento_data,
            u.nome as pessoa_nome, u.email as pessoa_email,
            r.nome as revisor_nome
     FROM absence_justifications j
     JOIN events e ON j.event_id = e.id
     JOIN users u ON j.person_id = u.id
     LEFT JOIN users r ON j.reviewed_by = r.id
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
    "SELECT id, titulo, inicio_at FROM events ORDER BY inicio_at DESC LIMIT 50"
);

// Contadores
$pendentes = $db->fetch("SELECT COUNT(*) as total FROM absence_justifications WHERE status = 'pendente'")['total'];

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Justificativas de Ausência</h1>
        <p class="page-subtitle"><?= $total ?> justificativa(s) • <?= $pendentes ?> pendente(s)</p>
    </div>
    <?php if (can('justificativas', 'create')): ?>
    <a href="<?= url('/justificativas/criar.php') ?>" class="btn btn-primary">
        <i data-lucide="file-plus"></i> Nova Justificativa
    </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="filters-bar">
    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <div class="filter-group">
            <select name="status" class="filter-select">
                <option value="">Todos os Status</option>
                <?php foreach (JUSTIFICATION_STATUS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtroStatus === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
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

        <button type="submit" class="btn btn-secondary">
            <i data-lucide="search"></i> Filtrar
        </button>
        
        <a href="<?= url('/justificativas') ?>" class="btn btn-secondary">
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
                    <th>Motivo</th>
                    <?= sortLink('status', 'Status', $orderBy, $orderDir) ?>
                    <?= sortLink('created_at', 'Data', $orderBy, $orderDir) ?>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($justificativas)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <h3 class="empty-state-title">Nenhuma justificativa encontrada</h3>
                            <p class="empty-state-text">Ajuste os filtros ou crie uma nova justificativa.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($justificativas as $j): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($j['pessoa_nome']) ?>">
                                    <?= getInitials($j['pessoa_nome']) ?>
                                </div>
                                <div>
                                    <strong><?= sanitize($j['pessoa_nome']) ?></strong><br>
                                    <small class="text-muted"><?= sanitize($j['pessoa_email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="<?= url('/eventos/ver.php?id=' . $j['event_id']) ?>" class="text-primary">
                                <?= sanitize($j['evento_titulo']) ?>
                            </a><br>
                            <small class="text-muted"><?= formatDate($j['evento_data']) ?></small>
                        </td>
                        <td>
                            <?= sanitize(substr($j['motivo'], 0, 80)) ?>
                            <?= strlen($j['motivo']) > 80 ? '...' : '' ?>
                            <?php if ($j['anexo_url']): ?>
                            <br><a href="<?= url($j['anexo_url']) ?>" target="_blank" class="text-primary text-sm">
                                <i data-lucide="paperclip"></i> Anexo
                            </a>
                            <?php endif; ?>
                        </td>
                        <td><?= statusBadge($j['status']) ?></td>
                        <td>
                            <?= formatDateTime($j['created_at']) ?>
                            <?php if ($j['revisor_nome']): ?>
                            <br><small class="text-muted">por <?= sanitize($j['revisor_nome']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-icon btn-sm btn-secondary" title="Ver" 
                                        onclick="verJustificativa(<?= $j['id'] ?>)">
                                    <i data-lucide="eye"></i>
                                </button>
                                <?php if ($j['status'] === 'pendente' && can('justificativas', 'approve')): ?>
                                <a href="<?= url('/justificativas/avaliar.php?id=' . $j['id']) ?>" class="btn btn-icon btn-sm btn-primary" title="Avaliar">
                                    <i data-lucide="check-square"></i>
                                </a>
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
        <?= paginationHtml($pagination, url('/justificativas')) ?>
    </div>
    <?php endif; ?>
</div>

<script>
function verJustificativa(id) {
    fetch('<?= url('/justificativas/api.php') ?>?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const j = data.data;
                openModal({
                    title: 'Detalhes da Justificativa',
                    body: `
                        <div class="form-group">
                            <label class="form-label text-muted">Pessoa</label>
                            <p><strong>${j.pessoa_nome}</strong></p>
                        </div>
                        <div class="form-group">
                            <label class="form-label text-muted">Evento</label>
                            <p>${j.evento_titulo} - ${j.evento_data}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label text-muted">Motivo</label>
                            <p>${j.motivo}</p>
                        </div>
                        ${j.anexo_url ? `<div class="form-group"><a href="${j.anexo_url}" target="_blank" class="btn btn-secondary btn-sm"><i data-lucide="paperclip"></i> Ver Anexo</a></div>` : ''}
                        <div class="form-group">
                            <label class="form-label text-muted">Status</label>
                            <p>${j.status_badge}</p>
                        </div>
                        ${j.review_notes ? `<div class="form-group"><label class="form-label text-muted">Observações do Revisor</label><p>${j.review_notes}</p></div>` : ''}
                    `
                });
                lucide.createIcons();
            }
        });
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
