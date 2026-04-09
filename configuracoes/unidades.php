<?php
/**
 * Gerenciamento de Unidades
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('configuracoes', 'manage_settings');

$pageTitle = 'Unidades';
$db = Database::getInstance();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $data = [
            'nome' => trim($_POST['nome']),
            'descricao' => trim($_POST['descricao'] ?? ''),
            'endereco' => trim($_POST['endereco'] ?? ''),
            'responsavel_id' => !empty($_POST['responsavel_id']) ? intval($_POST['responsavel_id']) : null,
            'ativo' => isset($_POST['ativo']) ? 1 : 0
        ];
        
        try {
            if ($action === 'create') {
                $db->insert('unidades', $data);
                setFlash('success', 'Unidade cadastrada com sucesso!');
            } else {
                $db->update('unidades', $data, 'id = ?', [$id]);
                setFlash('success', 'Unidade atualizada com sucesso!');
            }
            redirect('/configuracoes/unidades.php');
        } catch (Exception $e) {
            setFlash('error', 'Erro ao salvar unidade: ' . $e->getMessage());
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        try {
            $db->delete('unidades', 'id = ?', [$id]);
            setFlash('success', 'Unidade excluída com sucesso!');
            redirect('/configuracoes/unidades.php');
        } catch (Exception $e) {
            setFlash('error', 'Erro ao excluir unidade: ' . $e->getMessage());
        }
    }
}

// Buscar unidades
$unidades = $db->fetchAll("
    SELECT u.*, 
           resp.nome as responsavel_nome,
           (SELECT COUNT(*) FROM users WHERE unidade_id = u.id) as total_pessoas
    FROM unidades u
    LEFT JOIN users resp ON u.responsavel_id = resp.id
    ORDER BY u.nome ASC
");

// Buscar usuários para select de responsável
$usuarios = $db->fetchAll("
    SELECT id, nome, cargo 
    FROM users 
    WHERE status = 'ativo' AND cargo IN ('pastor', 'lider', 'diacono')
    ORDER BY nome ASC
");

require_once BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i data-lucide="map-pin"></i> Unidades</h1>
        <p class="page-subtitle">Gerencie as unidades da igreja</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModalUnidade()">
        <i data-lucide="plus"></i> Nova Unidade
    </button>
</div>

<?php if (hasFlash()): ?>
    <div class="alert alert-<?= getFlash('type') ?>">
        <?= getFlash('message') ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($unidades)): ?>
            <div class="empty-state">
                <i data-lucide="map-pin" style="width: 64px; height: 64px; opacity: 0.3;"></i>
                <h3>Nenhuma unidade cadastrada</h3>
                <p>Comece cadastrando a primeira unidade da igreja.</p>
                <button class="btn btn-primary" onclick="abrirModalUnidade()">
                    <i data-lucide="plus"></i> Cadastrar Primeira Unidade
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Responsável</th>
                            <th>Pessoas</th>
                            <th>Status</th>
                            <th width="120">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unidades as $unidade): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($unidade['nome']) ?></strong>
                                <?php if ($unidade['endereco']): ?>
                                    <br><small class="text-muted"><?= sanitize($unidade['endereco']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $unidade['responsavel_nome'] ? sanitize($unidade['responsavel_nome']) : '-' ?></td>
                            <td><?= $unidade['total_pessoas'] ?></td>
                            <td>
                                <span class="badge badge-<?= $unidade['ativo'] ? 'success' : 'secondary' ?>">
                                    <?= $unidade['ativo'] ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick='editarUnidade(<?= json_encode($unidade) ?>)' title="Editar">
                                    <i data-lucide="edit-2"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="excluirUnidade(<?= $unidade['id'] ?>, '<?= sanitize($unidade['nome']) ?>')" title="Excluir">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Unidade -->
<div id="modalUnidade" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Unidade</h2>
            <button class="modal-close" onclick="fecharModalUnidade()">&times;</button>
        </div>
        <form method="POST" id="formUnidade">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="unidadeId">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Nome da Unidade</label>
                    <input type="text" name="nome" id="unidadeNome" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" id="unidadeDescricao" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <textarea name="endereco" id="unidadeEndereco" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Responsável</label>
                    <select name="responsavel_id" id="unidadeResponsavel" class="form-control">
                        <option value="">Selecione...</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario['id'] ?>"><?= sanitize($usuario['nome']) ?> (<?= ucfirst($usuario['cargo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-check-label">
                        <input type="checkbox" name="ativo" id="unidadeAtivo" checked>
                        Unidade ativa
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalUnidade()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalUnidade() {
    console.log('abrirModalUnidade chamada');
    const modal = document.getElementById('modalUnidade');
    const title = document.getElementById('modalTitle');
    const form = document.getElementById('formUnidade');
    const action = document.getElementById('formAction');
    const id = document.getElementById('unidadeId');
    const ativo = document.getElementById('unidadeAtivo');
    
    if (!modal) {
        console.error('Modal não encontrado');
        alert('Erro: Modal não encontrado. Recarregue a página.');
        return;
    }
    
    title.textContent = 'Nova Unidade';
    action.value = 'create';
    form.reset();
    id.value = '';
    ativo.checked = true;
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    console.log('Modal aberto', 'display:', modal.style.display, 'z-index:', window.getComputedStyle(modal).zIndex);
}

function editarUnidade(unidade) {
    document.getElementById('modalTitle').textContent = 'Editar Unidade';
    document.getElementById('formAction').value = 'update';
    document.getElementById('unidadeId').value = unidade.id;
    document.getElementById('unidadeNome').value = unidade.nome;
    document.getElementById('unidadeDescricao').value = unidade.descricao || '';
    document.getElementById('unidadeEndereco').value = unidade.endereco || '';
    document.getElementById('unidadeResponsavel').value = unidade.responsavel_id || '';
    document.getElementById('unidadeAtivo').checked = unidade.ativo == 1;
    document.getElementById('modalUnidade').style.display = 'flex';
}

function fecharModalUnidade() {
    document.getElementById('modalUnidade').style.display = 'none';
}

function excluirUnidade(id, nome) {
    if (confirm('Tem certeza que deseja excluir a unidade "' + nome + '"?\n\nAs pessoas associadas a esta unidade não serão excluídas.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fechar modal ao clicar fora
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalUnidade');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalUnidade();
            }
        });
    }
});
</script>

<style>
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #374151;
}

.empty-state p {
    color: #6b7280;
    margin-bottom: 24px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999 !important;
    align-items: center;
    justify-content: center;
}

.modal[style*="display: flex"] {
    display: flex !important;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}

.modal-close:hover {
    background: #f3f4f6;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}
</style>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>
