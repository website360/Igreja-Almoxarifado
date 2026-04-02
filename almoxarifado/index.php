<?php
/**
 * Almoxarifado - Listagem de Itens
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('almoxarifado', 'view');

$pageTitle = 'Almoxarifado';
$db = Database::getInstance();

// Filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroCategoria = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));

// Ordenação
$orderBy = $_GET['order'] ?? 'nome';
$orderDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$allowedColumns = ['nome', 'patrimonio_codigo', 'localizacao', 'status'];
if (!in_array($orderBy, $allowedColumns)) $orderBy = 'nome';

$where = ['1=1'];
$params = [];

if ($filtroStatus) {
    $where[] = 'i.status = ?';
    $params[] = $filtroStatus;
}

if ($filtroCategoria) {
    $where[] = 'i.categoria_id = ?';
    $params[] = $filtroCategoria;
}

if ($busca) {
    $where[] = '(i.nome LIKE ? OR i.patrimonio_codigo LIKE ? OR i.descricao LIKE ?)';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$whereClause = implode(' AND ', $where);

$countResult = $db->fetch(
    "SELECT COUNT(*) as total FROM inventory_items i WHERE {$whereClause}",
    $params
);
$total = $countResult['total'];
$pagination = paginate($total, $page);

$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];

$itens = $db->fetchAll(
    "SELECT i.*, c.nome as categoria_nome
     FROM inventory_items i
     LEFT JOIN inventory_categories c ON i.categoria_id = c.id
     WHERE {$whereClause}
     ORDER BY i.{$orderBy} {$orderDir}
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

$categorias = $db->fetchAll("SELECT id, nome FROM inventory_categories ORDER BY nome");

// Contadores
$emprestados = $db->fetch("SELECT COUNT(*) as total FROM inventory_items WHERE status = 'emprestado'")['total'];
$manutencao = $db->fetch("SELECT COUNT(*) as total FROM inventory_items WHERE status = 'manutencao'")['total'];

// Aba ativa
$abaAtiva = $_GET['aba'] ?? 'itens';

// Filtros de movimentação
$movBusca = $_GET['mov_busca'] ?? '';
$movTipo = $_GET['mov_tipo'] ?? '';
$movStatus = $_GET['mov_status'] ?? '';

// Buscar movimentações se na aba de movimentação
$movimentacoes = [];
if ($abaAtiva === 'movimentacao') {
    $movWhere = ['1=1'];
    $movParams = [];
    
    if ($movBusca) {
        $movWhere[] = '(i.nome LIKE ? OR i.patrimonio_codigo LIKE ? OR p.nome LIKE ?)';
        $movParams[] = "%{$movBusca}%";
        $movParams[] = "%{$movBusca}%";
        $movParams[] = "%{$movBusca}%";
    }
    
    if ($movTipo) {
        $movWhere[] = 't.tipo = ?';
        $movParams[] = $movTipo;
    }
    
    $movWhereClause = implode(' AND ', $movWhere);
    
    $movimentacoes = $db->fetchAll(
        "SELECT t.*, i.nome as item_nome, i.patrimonio_codigo, i.quantidade as qtd_atual, i.status as item_status,
                u.nome as responsavel_nome,
                p.nome as pessoa_nome,
                (SELECT COUNT(*) FROM inventory_transactions t2 
                 WHERE t2.item_id = t.item_id AND t2.tipo = 'devolucao' AND t2.id > t.id) as devolvido
         FROM inventory_transactions t
         LEFT JOIN inventory_items i ON t.item_id = i.id
         LEFT JOIN users u ON t.responsavel_operacao_user_id = u.id
         LEFT JOIN users p ON t.retirado_por_person_id = p.id
         WHERE {$movWhereClause}
         ORDER BY t.data_hora DESC
         LIMIT 100",
        $movParams
    );
    
    // Filtrar por status no PHP (pois depende de cálculo)
    if ($movStatus) {
        $movimentacoes = array_filter($movimentacoes, function($mov) use ($movStatus) {
            if ($mov['tipo'] !== 'retirada') {
                return $movStatus === 'concluido' && $mov['tipo'] === 'devolucao';
            }
            
            if ($mov['devolvido']) {
                return $movStatus === 'devolvido';
            }
            
            if ($mov['devolver_ate']) {
                $atrasado = time() > strtotime($mov['devolver_ate']);
                if ($movStatus === 'atrasado') return $atrasado;
                if ($movStatus === 'no_prazo') return !$atrasado;
            } else {
                if ($movStatus === 'pendente') return true;
            }
            
            return false;
        });
    }
}

// Buscar itens disponíveis para o select de nova movimentação
$itensDisponiveis = $db->fetchAll("SELECT id, nome, patrimonio_codigo, quantidade FROM inventory_items WHERE status = 'disponivel' AND quantidade > 0 ORDER BY nome");

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Almoxarifado</h1>
        <p class="page-subtitle"><?= $total ?> item(ns) • <?= $emprestados ?> emprestado(s) • <?= $manutencao ?> em manutenção</p>
    </div>
    <?php if ($abaAtiva === 'itens' && can('almoxarifado', 'create')): ?>
    <a href="<?= url('/almoxarifado/criar.php') ?>" class="btn btn-primary">
        <i data-lucide="plus"></i> Novo Item
    </a>
    <?php elseif ($abaAtiva === 'movimentacao' && can('almoxarifado', 'manage_transactions')): ?>
    <button type="button" class="btn btn-primary" onclick="abrirNovaMovimentacao()">
        <i data-lucide="plus"></i> Nova Movimentação
    </button>
    <?php endif; ?>
</div>

<!-- Abas -->
<div class="tabs-nav" style="margin-bottom: 20px;">
    <a href="<?= url('/almoxarifado?aba=itens') ?>" class="tab-item <?= $abaAtiva === 'itens' ? 'active' : '' ?>">
        <i data-lucide="package"></i> Itens
    </a>
    <a href="<?= url('/almoxarifado?aba=movimentacao') ?>" class="tab-item <?= $abaAtiva === 'movimentacao' ? 'active' : '' ?>">
        <i data-lucide="arrow-left-right"></i> Movimentação
    </a>
</div>

<?php if ($abaAtiva === 'itens'): ?>
<!-- Filtros -->
<div class="filters-bar">
    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <div class="filter-group">
            <input type="text" name="busca" class="filter-input" placeholder="Buscar item ou patrimônio..." value="<?= sanitize($busca) ?>">
        </div>
        
        <div class="filter-group">
            <select name="status" class="filter-select">
                <option value="">Todos os Status</option>
                <option value="disponivel" <?= $filtroStatus === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
                <option value="emprestado" <?= $filtroStatus === 'emprestado' ? 'selected' : '' ?>>Emprestado</option>
                <option value="manutencao" <?= $filtroStatus === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                <option value="baixado" <?= $filtroStatus === 'baixado' ? 'selected' : '' ?>>Baixado</option>
            </select>
        </div>

        <div class="filter-group">
            <select name="categoria" class="filter-select">
                <option value="">Todas as Categorias</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $filtroCategoria == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            <i data-lucide="search"></i> Filtrar
        </button>
        
        <a href="<?= url('/almoxarifado') ?>" class="btn btn-secondary">
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
                    <?= sortLink('nome', 'Item', $orderBy, $orderDir) ?>
                    <?= sortLink('patrimonio_codigo', 'Patrimônio', $orderBy, $orderDir) ?>
                    <th>Categoria</th>
                    <?= sortLink('localizacao', 'Localização', $orderBy, $orderDir) ?>
                    <?= sortLink('status', 'Status', $orderBy, $orderDir) ?>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itens)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="package"></i>
                            </div>
                            <h3 class="empty-state-title">Nenhum item encontrado</h3>
                            <p class="empty-state-text">Ajuste os filtros ou cadastre um novo item.</p>
                            <?php if (can('almoxarifado', 'create')): ?>
                            <a href="<?= url('/almoxarifado/criar.php') ?>" class="btn btn-primary">
                                <i data-lucide="plus"></i> Novo Item
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itens as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <?php if ($item['foto_capa_url']): ?>
                                <img src="<?= url($item['foto_capa_url']) ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                <div class="user-avatar-sm" style="background-color: var(--gray-200); border-radius: 50%;">
                                    <i data-lucide="package" style="width: 16px; height: 16px; color: var(--gray-500);"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?= sanitize($item['nome']) ?></strong>
                                    <br><small class="text-muted">Qtd: <?= $item['quantidade'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight: 500;"><?= sanitize($item['patrimonio_codigo'] ?? '-') ?></span>
                        </td>
                        <td><?= sanitize($item['categoria_nome'] ?? '-') ?></td>
                        <td><?= sanitize($item['localizacao'] ?? '-') ?></td>
                        <td><?= statusBadge($item['status']) ?></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn-action btn-action-view" title="Ver" onclick="verItem(<?= $item['id'] ?>)">
                                    <i data-lucide="eye"></i>
                                </button>
                                <?php if (can('almoxarifado', 'edit')): ?>
                                <a href="<?= url('/almoxarifado/criar.php?id=' . $item['id']) ?>" class="btn-action btn-action-edit" title="Editar">
                                    <i data-lucide="edit-2"></i>
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
        <?= paginationHtml($pagination, url('/almoxarifado')) ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Aba Movimentação -->
<div class="filters-bar">
    <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <input type="hidden" name="aba" value="movimentacao">
        
        <div class="filter-group">
            <input type="text" name="mov_busca" class="filter-input" placeholder="Buscar item ou pessoa..." value="<?= sanitize($movBusca) ?>">
        </div>
        
        <div class="filter-group">
            <select name="mov_tipo" class="filter-select">
                <option value="">Todos os Tipos</option>
                <option value="retirada" <?= $movTipo === 'retirada' ? 'selected' : '' ?>>Saída</option>
                <option value="devolucao" <?= $movTipo === 'devolucao' ? 'selected' : '' ?>>Entrada</option>
            </select>
        </div>
        
        <div class="filter-group">
            <select name="mov_status" class="filter-select">
                <option value="">Todos os Status</option>
                <option value="pendente" <?= $movStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="no_prazo" <?= $movStatus === 'no_prazo' ? 'selected' : '' ?>>No prazo</option>
                <option value="atrasado" <?= $movStatus === 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
                <option value="devolvido" <?= $movStatus === 'devolvido' ? 'selected' : '' ?>>Devolvido</option>
                <option value="concluido" <?= $movStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            <i data-lucide="search"></i> Filtrar
        </button>
        
        <a href="<?= url('/almoxarifado?aba=movimentacao') ?>" class="btn btn-secondary">
            <i data-lucide="x"></i> Limpar
        </a>
    </form>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Tipo</th>
                    <th>Qtd</th>
                    <th>Pessoa</th>
                    <th>Data/Hora</th>
                    <th>Devolver até</th>
                    <th>Status</th>
                    <th>Observações</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movimentacoes)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted" style="padding: 40px;">
                        <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>Nenhuma movimentação registrada</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($movimentacoes as $mov): 
                        $statusDevolucao = '';
                        $badgeDevolucao = '';
                        if ($mov['tipo'] === 'retirada') {
                            if ($mov['devolvido']) {
                                $statusDevolucao = 'Devolvido';
                                $badgeDevolucao = 'badge-success';
                            } elseif ($mov['devolver_ate']) {
                                $dataLimite = strtotime($mov['devolver_ate']);
                                $agora = time();
                                if ($agora > $dataLimite) {
                                    $statusDevolucao = 'Atrasado';
                                    $badgeDevolucao = 'badge-danger';
                                } else {
                                    $statusDevolucao = 'No prazo';
                                    $badgeDevolucao = 'badge-info';
                                }
                            } else {
                                $statusDevolucao = 'Pendente';
                                $badgeDevolucao = 'badge-warning';
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?= sanitize($mov['item_nome'] ?? 'Item removido') ?></strong>
                            <?php if ($mov['patrimonio_codigo']): ?>
                            <br><small class="text-muted"><?= sanitize($mov['patrimonio_codigo']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($mov['tipo'] === 'retirada'): ?>
                            <span class="badge badge-warning"><i data-lucide="log-out" style="width: 12px; height: 12px;"></i> Saída</span>
                            <?php elseif ($mov['tipo'] === 'devolucao'): ?>
                            <span class="badge badge-success"><i data-lucide="log-in" style="width: 12px; height: 12px;"></i> Entrada</span>
                            <?php else: ?>
                            <span class="badge badge-secondary"><?= ucfirst($mov['tipo']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= $mov['quantidade'] ?? 1 ?></strong></td>
                        <td><?= sanitize($mov['pessoa_nome'] ?? '-') ?></td>
                        <td>
                            <div><?= date('d/m/Y', strtotime($mov['data_hora'])) ?></div>
                            <small class="text-muted"><?= date('H:i', strtotime($mov['data_hora'])) ?></small>
                        </td>
                        <td>
                            <?php if ($mov['tipo'] === 'retirada' && $mov['devolver_ate']): ?>
                                <?= date('d/m/Y H:i', strtotime($mov['devolver_ate'])) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($mov['tipo'] === 'retirada' && $statusDevolucao): ?>
                                <span class="badge <?= $badgeDevolucao ?>"><?= $statusDevolucao ?></span>
                            <?php elseif ($mov['tipo'] === 'devolucao'): ?>
                                <span class="badge badge-success">Concluído</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($mov['condition_notes']): ?>
                            <span class="text-muted" style="font-size: 13px;"><?= sanitize($mov['condition_notes']) ?></span>
                            <?php endif; ?>
                            <?php 
                            $fotos = [];
                            if ($mov['foto_estado_url']) {
                                $fotos = json_decode($mov['foto_estado_url'], true) ?: [$mov['foto_estado_url']];
                            }
                            if (!empty($fotos)): ?>
                            <div style="display: flex; gap: 4px; margin-top: 4px;">
                                <?php foreach (array_slice($fotos, 0, 3) as $foto): ?>
                                <img src="<?= url($foto) ?>" onclick="ampliarFoto('<?= url($foto) ?>')" style="width: 32px; height: 32px; border-radius: 4px; object-fit: cover; cursor: pointer;" title="Clique para ampliar">
                                <?php endforeach; ?>
                                <?php if (count($fotos) > 3): ?>
                                <span class="text-muted" style="font-size: 11px;">+<?= count($fotos) - 3 ?></span>
                                <?php endif; ?>
                            </div>
                            <?php elseif (!$mov['condition_notes']): ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if (can('almoxarifado', 'manage_transactions')): ?>
                                <button class="btn-action btn-action-edit" title="Editar" onclick="editarMovimentacao(<?= $mov['id'] ?>, <?= htmlspecialchars(json_encode($mov), ENT_QUOTES) ?>)">
                                    <i data-lucide="edit-2"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($mov['tipo'] === 'retirada' && !$mov['devolvido'] && can('almoxarifado', 'manage_transactions')): ?>
                                <button class="btn-action" style="background: var(--success); color: white;" title="Devolver" onclick="devolverItem(<?= $mov['item_id'] ?>, '<?= sanitize($mov['item_nome']) ?>')">
                                    <i data-lucide="log-in"></i>
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
</div>
<?php endif; ?>

<script>
// Dados dos itens disponíveis para o select
var itensDisponiveis = <?= json_encode($itensDisponiveis) ?>;

function ampliarFoto(url) {
    openModal({
        title: 'Foto do Estado',
        body: `<div style="text-align: center;"><img src="${url}" style="max-width: 100%; max-height: 70vh; border-radius: 8px;"></div>`,
        footer: `<button class="btn btn-secondary" onclick="closeModal()">Fechar</button>`
    });
}

function editarMovimentacao(id, mov) {
    const devolverAte = mov.devolver_ate ? mov.devolver_ate.replace(' ', 'T').substring(0, 16) : '';
    
    openModal({
        title: 'Editar Movimentação #' + id,
        body: `
            <form id="formEditarMov">
                <div class="form-group">
                    <label class="form-label">Item</label>
                    <input type="text" class="form-control" value="${mov.item_nome || ''}" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <input type="text" class="form-control" value="${mov.tipo === 'retirada' ? 'Saída' : 'Entrada'}" disabled>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Quantidade</label>
                        <input type="number" id="editQtd" class="form-control" value="${mov.quantidade || 1}" min="1">
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Devolver até</label>
                        <input type="datetime-local" id="editDevolverAte" class="form-control" value="${devolverAte}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea id="editObs" class="form-control" rows="3">${mov.condition_notes || ''}</textarea>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="salvarMovimentacao(${id})">Salvar</button>
        `
    });
}

function salvarMovimentacao(id) {
    const quantidade = document.getElementById('editQtd').value;
    const devolverAte = document.getElementById('editDevolverAte').value;
    const notes = document.getElementById('editObs').value;
    
    fetch('<?= url('/almoxarifado/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({
            action: 'update_transaction',
            id: id,
            quantidade: quantidade,
            devolver_ate: devolverAte,
            notes: notes
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Movimentação atualizada com sucesso', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro ao atualizar', 'error');
        }
    });
}

function abrirNovaMovimentacao() {
    var optionsHtml = itensDisponiveis.map(item => 
        `<option value="${item.id}" data-qtd="${item.quantidade}">${item.nome}${item.patrimonio_codigo ? ' (' + item.patrimonio_codigo + ')' : ''} - Qtd: ${item.quantidade}</option>`
    ).join('');
    
    openModal({
        title: 'Nova Movimentação (Retirada)',
        body: `
            <form id="formNovaMovimentacao">
                <div class="form-group">
                    <label class="form-label required">Item</label>
                    <select id="itemSelect" class="form-control" required onchange="atualizarQtdDisponivel()">
                        <option value="">Pesquisar item...</option>
                        ${optionsHtml}
                    </select>
                    <small class="text-muted" id="qtdDisponivelInfo"></small>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label required">Retirado por</label>
                        <select id="retiradoPorNovo" class="form-control" required>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label required">Quantidade</label>
                        <input type="number" id="qtdRetiradaNovo" class="form-control" min="1" max="1" value="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea id="obsRetiradaNovo" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Devolver até</label>
                    <input type="datetime-local" id="devolverAteNovo" class="form-control">
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="confirmarNovaMovimentacao()">Confirmar Retirada</button>
        `
    });
    
    if (typeof lucide !== 'undefined') lucide.createIcons();
    
    // Carregar pessoas
    fetch('<?= url('/pessoas/api.php') ?>?action=list&status=ativo&limit=100')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('retiradoPorNovo');
            if (data.success && data.data) {
                data.data.forEach(p => {
                    select.innerHTML += `<option value="${p.id}">${p.nome}</option>`;
                });
            }
        });
}

function atualizarQtdDisponivel() {
    const select = document.getElementById('itemSelect');
    const option = select.options[select.selectedIndex];
    const qtd = option.dataset.qtd || 1;
    document.getElementById('qtdRetiradaNovo').max = qtd;
    document.getElementById('qtdDisponivelInfo').textContent = qtd > 0 ? `${qtd} disponível(is)` : '';
}

function confirmarNovaMovimentacao() {
    const itemId = document.getElementById('itemSelect').value;
    const personId = document.getElementById('retiradoPorNovo').value;
    const quantidade = parseInt(document.getElementById('qtdRetiradaNovo').value) || 1;
    const notes = document.getElementById('obsRetiradaNovo').value;
    const devolverAte = document.getElementById('devolverAteNovo').value;
    
    if (!itemId) {
        showToast('Selecione um item', 'error');
        return;
    }
    
    if (!personId) {
        showToast('Selecione quem está retirando', 'error');
        return;
    }
    
    fetch('<?= url('/almoxarifado/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({
            action: 'retirada',
            item_id: itemId,
            person_id: personId,
            quantidade: quantidade,
            notes: notes,
            devolver_ate: devolverAte
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Item retirado com sucesso', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro ao retirar', 'error');
        }
    });
}

function retirarItem(id, nome, qtdDisponivel) {
    openModal({
        title: 'Retirar Item: ' + nome,
        body: `
            <form id="formRetirada">
                <div class="alert alert-info" style="margin-bottom: 16px;">
                    <div class="alert-content">
                        <i data-lucide="package"></i>
                        <span><strong>${qtdDisponivel}</strong> unidade(s) disponível(is) para retirada</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label required">Retirado por</label>
                        <select id="retiradoPor" class="form-control" required>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label required">Quantidade</label>
                        <input type="number" id="qtdRetirada" class="form-control" min="1" max="${qtdDisponivel}" value="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea id="obsRetirada" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Devolver até</label>
                    <input type="datetime-local" id="devolverAte" class="form-control">
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="confirmarRetirada(${id}, ${qtdDisponivel})">Confirmar Retirada</button>
        `
    });
    
    if (typeof lucide !== 'undefined') lucide.createIcons();
    
    // Carregar pessoas
    fetch('<?= url('/pessoas/api.php') ?>?action=list&status=ativo&limit=100')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('retiradoPor');
            if (data.success && data.data) {
                data.data.forEach(p => {
                    select.innerHTML += `<option value="${p.id}">${p.nome}</option>`;
                });
            }
        });
}

function confirmarRetirada(itemId, qtdDisponivel) {
    const personId = document.getElementById('retiradoPor').value;
    const quantidade = parseInt(document.getElementById('qtdRetirada').value) || 1;
    const notes = document.getElementById('obsRetirada').value;
    const devolverAte = document.getElementById('devolverAte').value;
    
    if (!personId) {
        showToast('Selecione quem está retirando', 'error');
        return;
    }
    
    if (quantidade < 1 || quantidade > qtdDisponivel) {
        showToast('Quantidade inválida. Disponível: ' + qtdDisponivel, 'error');
        return;
    }
    
    fetch('<?= url('/almoxarifado/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({
            action: 'retirada',
            item_id: itemId,
            person_id: personId,
            quantidade: quantidade,
            notes: notes,
            devolver_ate: devolverAte
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(quantidade + ' unidade(s) retirada(s) com sucesso', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro ao retirar', 'error');
        }
    });
}

function devolverItem(id, nome) {
    openModal({
        title: 'Devolver Item: ' + nome,
        body: `
            <form id="formDevolucao">
                <div class="form-group">
                    <label class="form-label required">Estado do Item</label>
                    <select id="estadoItem" class="form-control" required onchange="toggleAvariaFields()">
                        <option value="">Selecione...</option>
                        <option value="ok">OK - Sem avarias</option>
                        <option value="avaria_leve">Avaria Leve</option>
                        <option value="avaria_grave">Avaria Grave</option>
                        <option value="inutilizado">Inutilizado</option>
                    </select>
                </div>
                
                <div id="avariaFields" style="display: none;">
                    <div class="alert alert-warning" style="margin-bottom: 16px;">
                        <div class="alert-content">
                            <i data-lucide="alert-triangle"></i>
                            <span>Descreva a avaria e adicione fotos do estado atual</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Descrição da Avaria</label>
                        <textarea id="obsDevolucao" class="form-control" rows="3" placeholder="Descreva detalhadamente o problema encontrado..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fotos do Estado</label>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;" id="fotosPreview"></div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-secondary" onclick="tirarFotoAvaria()">
                                <i data-lucide="camera"></i> Tirar Foto
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('inputFotosAvaria').click()">
                                <i data-lucide="image"></i> Escolher Arquivo
                            </button>
                        </div>
                        <input type="file" id="inputFotosAvaria" accept="image/*" multiple style="display: none;" onchange="previewFotosAvaria(this)">
                    </div>
                </div>
            </form>
        `,
        footer: `
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-success" onclick="confirmarDevolucao(${id})">Confirmar Devolução</button>
        `
    });
    
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

var fotosAvariaBase64 = [];

function toggleAvariaFields() {
    const estado = document.getElementById('estadoItem').value;
    const avariaFields = document.getElementById('avariaFields');
    
    if (estado && estado !== 'ok') {
        avariaFields.style.display = 'block';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } else {
        avariaFields.style.display = 'none';
    }
}

function previewFotosAvaria(input) {
    const preview = document.getElementById('fotosPreview');
    
    Array.from(input.files).forEach(file => {
        if (!file.type.match(/image\/(jpeg|png|gif)/)) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            fotosAvariaBase64.push(e.target.result);
            renderFotosPreview();
        };
        reader.readAsDataURL(file);
    });
}

function renderFotosPreview() {
    const preview = document.getElementById('fotosPreview');
    preview.innerHTML = fotosAvariaBase64.map((foto, idx) => `
        <div style="position: relative; width: 80px; height: 80px;">
            <img src="${foto}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
            <button type="button" onclick="removerFotoAvaria(${idx})" style="position: absolute; top: -8px; right: -8px; width: 24px; height: 24px; border-radius: 50%; background: var(--danger); color: white; border: none; cursor: pointer; font-size: 12px;">✕</button>
        </div>
    `).join('');
}

function removerFotoAvaria(idx) {
    fotosAvariaBase64.splice(idx, 1);
    renderFotosPreview();
}

function tirarFotoAvaria() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showToast('Câmera não disponível', 'error');
        return;
    }
    
    const modal = document.createElement('div');
    modal.id = 'cameraAvariaModal';
    modal.innerHTML = `
        <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 10000; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div style="position: relative; max-width: 500px; width: 100%;">
                <video id="cameraAvariaVideo" autoplay playsinline style="width: 100%; border-radius: 12px;"></video>
                <canvas id="cameraAvariaCanvas" style="display: none;"></canvas>
            </div>
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <button type="button" onclick="capturarFotoAvaria()" style="width: 70px; height: 70px; border-radius: 50%; background: white; border: 4px solid var(--primary); cursor: pointer;">
                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary); margin: auto;"></div>
                </button>
            </div>
            <button type="button" onclick="fecharCameraAvaria()" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; color: white; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 20px;">✕</button>
        </div>
    `;
    document.body.appendChild(modal);
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            window.avariaStream = stream;
            document.getElementById('cameraAvariaVideo').srcObject = stream;
        })
        .catch(() => {
            fecharCameraAvaria();
            showToast('Não foi possível acessar a câmera', 'error');
        });
}

function capturarFotoAvaria() {
    const video = document.getElementById('cameraAvariaVideo');
    const canvas = document.getElementById('cameraAvariaCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    
    fotosAvariaBase64.push(canvas.toDataURL('image/jpeg', 0.8));
    renderFotosPreview();
    fecharCameraAvaria();
}

function fecharCameraAvaria() {
    if (window.avariaStream) {
        window.avariaStream.getTracks().forEach(track => track.stop());
        window.avariaStream = null;
    }
    document.getElementById('cameraAvariaModal')?.remove();
}

function confirmarDevolucao(itemId) {
    const estado = document.getElementById('estadoItem').value;
    const notes = document.getElementById('obsDevolucao')?.value || '';
    
    if (!estado) {
        showToast('Selecione o estado do item', 'error');
        return;
    }
    
    if (estado !== 'ok' && !notes.trim()) {
        showToast('Descreva a avaria encontrada', 'error');
        return;
    }
    
    fetch('<?= url('/almoxarifado/api.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({
            action: 'devolucao',
            item_id: itemId,
            estado: estado,
            notes: notes,
            fotos: fotosAvariaBase64
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            fotosAvariaBase64 = [];
            showToast('Item devolvido com sucesso', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro ao devolver', 'error');
        }
    });
}
// Função para visualizar item em modal
function verItem(id) {
    fetch('<?= url('/almoxarifado/api.php') ?>?action=view&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderItemView(data.data);
            } else {
                showToast(data.message || 'Erro ao carregar item', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro ao carregar dados', 'error');
        });
}

function renderItemView(item) {
    const statusLabels = {
        'disponivel': '<span class="badge badge-success">Disponível</span>',
        'emprestado': '<span class="badge badge-warning">Emprestado</span>',
        'manutencao': '<span class="badge badge-info">Manutenção</span>',
        'baixado': '<span class="badge badge-danger">Baixado</span>'
    };
    
    const fotoHtml = item.foto_capa_url 
        ? `<div style="text-align: center; margin-bottom: 16px;"><img src="<?= url('') ?>${item.foto_capa_url}" alt="" style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%;"></div>`
        : `<div style="text-align: center; margin-bottom: 16px;"><div style="width: 120px; height: 120px; background: var(--gray-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
            <i data-lucide="package" style="width: 48px; height: 48px; color: var(--gray-400);"></i>
           </div></div>`;
    
    const emprestadoInfo = item.status === 'emprestado' && item.emprestado_para
        ? `<div class="info-row">
            <span class="info-label">Emprestado para</span>
            <span class="info-value">${item.emprestado_para}</span>
           </div>
           ${item.devolver_ate ? `<div class="info-row">
            <span class="info-label">Devolver até</span>
            <span class="info-value">${new Date(item.devolver_ate).toLocaleDateString('pt-BR')}</span>
           </div>` : ''}`
        : '';
    
    const modalHtml = `
        <div id="itemViewModal" class="modal-overlay" onclick="if(event.target===this)closeItemModal()">
            <div class="modal-panel">
                <div class="modal-header">
                    <h3>Detalhes do Item</h3>
                    <button class="btn btn-icon btn-sm btn-secondary" onclick="closeItemModal()">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    ${fotoHtml}
                    <h2 style="margin: 0 0 8px 0; font-size: 1.25rem;">${item.nome}</h2>
                    <div style="margin-bottom: 16px;">${statusLabels[item.status] || item.status}</div>
                    
                    <div class="info-grid" style="display: flex; flex-direction: column; gap: 12px;">
                        <div class="info-row">
                            <span class="info-label">Patrimônio</span>
                            <span class="info-value" style="font-weight: 500;">${item.patrimonio_codigo || '-'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Categoria</span>
                            <span class="info-value">${item.categoria_nome || '-'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Localização</span>
                            <span class="info-value">${item.localizacao || '-'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Quantidade</span>
                            <span class="info-value">${item.quantidade}</span>
                        </div>
                        ${item.valor_estimado ? `<div class="info-row">
                            <span class="info-label">Valor Estimado</span>
                            <span class="info-value">R$ ${parseFloat(item.valor_estimado).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                        </div>` : ''}
                        ${emprestadoInfo}
                        <div class="info-row">
                            <span class="info-label">Movimentações</span>
                            <span class="info-value">${item.total_movimentacoes} registro(s)</span>
                        </div>
                    </div>
                    
                    ${item.descricao ? `<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
                        <strong style="font-size: 0.875rem; color: var(--gray-600);">Descrição</strong>
                        <p style="margin: 8px 0 0 0; color: var(--gray-700);">${item.descricao}</p>
                    </div>` : ''}
                </div>
                <div class="modal-footer">
                    <a href="<?= url('/almoxarifado/criar.php?id=') ?>${item.id}" class="btn btn-primary">
                        <i data-lucide="edit"></i> Editar
                    </a>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    lucide.createIcons();
    document.body.style.overflow = 'hidden';
    
    document.addEventListener('keydown', handleItemModalEsc);
}

function handleItemModalEsc(e) {
    if (e.key === 'Escape') closeItemModal();
}

function closeItemModal() {
    const modal = document.getElementById('itemViewModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleItemModalEsc);
    }
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
