<?php
/**
 * Gerenciar Presenças de um Evento
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('presencas', 'checkin_others');

$db = Database::getInstance();

$eventoId = intval($_GET['id'] ?? 0);
if (!$eventoId) {
    setFlash('error', 'Evento não informado');
    redirect('/eventos');
}

$evento = $db->fetch("SELECT * FROM events WHERE id = ?", [$eventoId]);
if (!$evento) {
    setFlash('error', 'Evento não encontrado');
    redirect('/eventos');
}

$pageTitle = 'Presenças: ' . $evento['titulo'];

// Buscar motivos de justificativa
$motivos = $db->fetchAll(
    "SELECT id, nome, descricao FROM justification_reasons WHERE ativo = 1 ORDER BY ordem, nome"
);

// Buscar justificativas pendentes
$justificativasPendentes = $db->fetchAll(
    "SELECT aj.*, u.nome as pessoa_nome, u.foto_url, jr.nome as motivo_nome
     FROM absence_justifications aj
     JOIN users u ON aj.person_id = u.id
     LEFT JOIN justification_reasons jr ON aj.reason_id = jr.id
     WHERE aj.event_id = ? AND aj.status = 'pendente'
     ORDER BY aj.created_at DESC",
    [$eventoId]
);

// Buscar participantes do evento
$participantes = $db->fetchAll(
    "SELECT ep.user_id, u.nome, u.email, u.foto_url
     FROM event_participants ep
     JOIN users u ON ep.user_id = u.id
     WHERE ep.event_id = ?
     ORDER BY u.nome",
    [$eventoId]
);

// Buscar presenças já registradas com justificativas
$presencasRegistradas = $db->fetchAll(
    "SELECT a.*, u.nome as pessoa_nome, u.email as pessoa_email, u.foto_url,
            m.nome as marked_by_nome,
            aj.id as justificativa_id, aj.reason_id, aj.motivo as justificativa_texto,
            jr.nome as motivo_nome
     FROM attendance a
     JOIN users u ON a.person_id = u.id
     LEFT JOIN users m ON a.marked_by = m.id
     LEFT JOIN absence_justifications aj ON aj.event_id = a.event_id AND aj.person_id = a.person_id
     LEFT JOIN justification_reasons jr ON aj.reason_id = jr.id
     WHERE a.event_id = ?",
    [$eventoId]
);

// Criar array indexado por person_id para facilitar busca
$presencasPorPessoa = [];
foreach ($presencasRegistradas as $p) {
    $presencasPorPessoa[$p['person_id']] = $p;
}

// Montar lista completa de presenças (participantes + status)
$presencas = [];
foreach ($participantes as $participante) {
    if (isset($presencasPorPessoa[$participante['user_id']])) {
        // Já tem presença registrada
        $presencas[] = $presencasPorPessoa[$participante['user_id']];
    } else {
        // Participante sem presença registrada ainda
        $presencas[] = [
            'person_id' => $participante['user_id'],
            'pessoa_nome' => $participante['nome'],
            'pessoa_email' => $participante['email'],
            'foto_url' => $participante['foto_url'],
            'status' => null,
            'checkin_at' => null,
            'checkin_method' => null,
            'marked_by_nome' => null
        ];
    }
}

// Buscar pessoas que podem ser adicionadas (não são participantes ainda)
$pessoasSemPresenca = $db->fetchAll(
    "SELECT u.id, u.nome, u.foto_url FROM users u
     WHERE u.status = 'ativo'
     AND u.id NOT IN (SELECT user_id FROM event_participants WHERE event_id = ?)
     ORDER BY u.nome",
    [$eventoId]
);

// Estatísticas
$stats = [
    'total' => count($presencas),
    'presente' => 0,
    'ausente' => 0,
    'justificado' => 0
];
foreach ($presencas as $p) {
    if (isset($stats[$p['status']])) {
        $stats[$p['status']]++;
    }
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        setFlash('error', 'Token inválido');
        redirect('/presencas/evento.php?id=' . $eventoId);
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'marcar_presenca') {
        $personId = intval($_POST['person_id'] ?? 0);
        $status = $_POST['status'] ?? 'presente';
        $reasonId = intval($_POST['reason_id'] ?? 0);
        $observacoes = trim($_POST['observacoes'] ?? '');
        
        if ($personId) {
            // Verificar se a pessoa é participante do evento, se não for, adicionar
            $isParticipant = $db->fetch(
                "SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?",
                [$eventoId, $personId]
            );
            
            if (!$isParticipant) {
                $db->insert('event_participants', [
                    'event_id' => $eventoId,
                    'user_id' => $personId
                ]);
            }
            
            $existing = $db->fetch(
                "SELECT id FROM attendance WHERE event_id = ? AND person_id = ?",
                [$eventoId, $personId]
            );
            
            if ($existing) {
                $db->update('attendance', [
                    'status' => $status,
                    'marked_by' => $currentUser['id'],
                    'checkin_at' => $status === 'presente' ? date('Y-m-d H:i:s') : null
                ], 'id = :id', ['id' => $existing['id']]);
            } else {
                $db->insert('attendance', [
                    'event_id' => $eventoId,
                    'person_id' => $personId,
                    'status' => $status,
                    'marked_by' => $currentUser['id'],
                    'checkin_at' => $status === 'presente' ? date('Y-m-d H:i:s') : null
                ]);
            }
            
            // Se for justificado, criar registro de justificativa
            if ($status === 'justificado') {
                if ($reasonId) {
                    $motivoNome = $db->fetch("SELECT nome FROM justification_reasons WHERE id = ?", [$reasonId]);
                    $motivo = $motivoNome['nome'] ?? 'Justificado';
                    if ($observacoes) {
                        $motivo .= ': ' . $observacoes;
                    }
                    
                    // Verificar se já existe justificativa
                    $existingJust = $db->fetch(
                        "SELECT id FROM absence_justifications WHERE event_id = ? AND person_id = ?",
                        [$eventoId, $personId]
                    );
                    
                    if ($existingJust) {
                        $db->update('absence_justifications', [
                            'reason_id' => $reasonId,
                            'motivo' => $motivo,
                            'status' => 'pendente'
                        ], 'id = :id', ['id' => $existingJust['id']]);
                    } else {
                        $db->insert('absence_justifications', [
                            'event_id' => $eventoId,
                            'person_id' => $personId,
                            'reason_id' => $reasonId,
                            'motivo' => $motivo,
                            'status' => 'pendente'
                        ]);
                    }
                } else {
                    setFlash('error', 'Selecione um motivo para a justificativa');
                    redirect('/presencas/evento.php?id=' . $eventoId);
                }
            }
            
            setFlash('success', 'Presença registrada');
        }
        redirect('/presencas/evento.php?id=' . $eventoId);
    }
    
    if ($action === 'alterar_status') {
        $presencaId = intval($_POST['presenca_id'] ?? 0);
        $status = $_POST['status'] ?? 'presente';
        $reasonId = intval($_POST['reason_id'] ?? 0);
        $observacoes = trim($_POST['observacoes'] ?? '');
        
        if ($presencaId) {
            // Buscar person_id da presença
            $presenca = $db->fetch("SELECT person_id FROM attendance WHERE id = ?", [$presencaId]);
            
            $db->update('attendance', [
                'status' => $status,
                'marked_by' => $currentUser['id'],
                'checkin_at' => $status === 'presente' ? date('Y-m-d H:i:s') : null
            ], 'id = :id', ['id' => $presencaId]);
            
            // Se for justificado, criar registro de justificativa
            if ($status === 'justificado' && $presenca) {
                if ($reasonId) {
                    $motivoNome = $db->fetch("SELECT nome FROM justification_reasons WHERE id = ?", [$reasonId]);
                    $motivo = $motivoNome['nome'] ?? 'Justificado';
                    if ($observacoes) {
                        $motivo .= ': ' . $observacoes;
                    }
                    
                    // Verificar se já existe justificativa
                    $existingJust = $db->fetch(
                        "SELECT id FROM absence_justifications WHERE event_id = ? AND person_id = ?",
                        [$eventoId, $presenca['person_id']]
                    );
                    
                    if ($existingJust) {
                        $db->update('absence_justifications', [
                            'reason_id' => $reasonId,
                            'motivo' => $motivo,
                            'status' => 'pendente'
                        ], 'id = :id', ['id' => $existingJust['id']]);
                    } else {
                        $db->insert('absence_justifications', [
                            'event_id' => $eventoId,
                            'person_id' => $presenca['person_id'],
                            'reason_id' => $reasonId,
                            'motivo' => $motivo,
                            'status' => 'pendente'
                        ]);
                    }
                } else {
                    setFlash('error', 'Selecione um motivo para a justificativa');
                    redirect('/presencas/evento.php?id=' . $eventoId);
                }
            }
            
            setFlash('success', 'Status atualizado');
        }
        redirect('/presencas/evento.php?id=' . $eventoId);
    }
    
    if ($action === 'remover_presenca') {
        $presencaId = intval($_POST['presenca_id'] ?? 0);
        if ($presencaId) {
            $db->delete('attendance', 'id = ?', [$presencaId]);
            setFlash('success', 'Registro removido');
        }
        redirect('/presencas/evento.php?id=' . $eventoId);
    }
    
    if ($action === 'avaliar_justificativa') {
        $justificativaId = intval($_POST['justificativa_id'] ?? 0);
        $novoStatus = $_POST['novo_status'] ?? '';
        
        if ($justificativaId && in_array($novoStatus, ['aprovada', 'recusada'])) {
            $db->update('absence_justifications', [
                'status' => $novoStatus,
                'reviewed_by' => $currentUser['id'],
                'reviewed_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $justificativaId]);
            
            setFlash('success', 'Justificativa ' . ($novoStatus === 'aprovada' ? 'aprovada' : 'recusada'));
        }
        redirect('/presencas/evento.php?id=' . $eventoId);
    }
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Presenças do Evento</h1>
        <p class="page-subtitle"><?= sanitize($evento['titulo']) ?> - <?= formatDateFull($evento['inicio_at']) ?></p>
    </div>
    <a href="<?= url('/eventos/ver.php?id=' . $eventoId) ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left"></i> Voltar ao Evento
    </a>
</div>

<!-- Estatísticas -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
            <i data-lucide="users"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Registros</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
            <i data-lucide="check-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['presente'] ?></div>
            <div class="stat-label">Presentes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
            <i data-lucide="x-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['ausente'] ?></div>
            <div class="stat-label">Ausentes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
            <i data-lucide="file-text"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['justificado'] ?></div>
            <div class="stat-label">Justificados</div>
        </div>
    </div>
</div>


<div class="row" style="display: grid; grid-template-columns: 1fr 350px; gap: 24px;">
    <!-- Lista de Presenças -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Lista de Presenças</h3>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pessoa</th>
                        <th>Status</th>
                        <th>Motivo</th>
                        <th>Data/Hora</th>
                        <th>Registrado por</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($presencas)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 40px;">
                            <i data-lucide="clipboard-list" style="width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                            <p>Nenhuma presença registrada</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($presencas as $p): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($p['foto_url'])): ?>
                                <img src="<?= url($p['foto_url']) ?>" alt="" class="user-avatar-sm" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($p['pessoa_nome']) ?>; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; color: white;">
                                    <?= getInitials($p['pessoa_nome']) ?>
                                </div>
                                <?php endif; ?>
                                <span><?= sanitize($p['pessoa_nome']) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($p['status']): ?>
                                <?php
                                $statusBadge = [
                                    'presente' => ['class' => 'success', 'label' => 'Presente'],
                                    'ausente' => ['class' => 'danger', 'label' => 'Ausente'],
                                    'justificado' => ['class' => 'warning', 'label' => 'Justificado']
                                ];
                                $badge = $statusBadge[$p['status']] ?? ['class' => 'secondary', 'label' => $p['status']];
                                ?>
                                <span class="badge badge-<?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['status'] === 'justificado' && !empty($p['motivo_nome'])): ?>
                                <strong><?= sanitize($p['motivo_nome']) ?></strong>
                                <?php 
                                $observacoes = substr($p['justificativa_texto'] ?? '', strlen($p['motivo_nome']) + 2);
                                if ($observacoes): ?>
                                    <br><small class="text-muted"><?= sanitize($observacoes) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $p['checkin_at'] ? formatDateTime($p['checkin_at']) : '-' ?></td>
                        <td><?= sanitize($p['marked_by_nome'] ?? '-') ?></td>
                        <td>
                            <div style="display: flex; gap: 4px; justify-content: flex-end;">
                                <?php if (isset($p['id']) && $p['id']): ?>
                                    <!-- Já tem presença registrada -->
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="alterar_status">
                                        <input type="hidden" name="presenca_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="status" value="presente">
                                        <button type="submit" class="btn-status <?= $p['status'] === 'presente' ? 'active success' : '' ?>" title="Presente">
                                            <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="alterar_status">
                                        <input type="hidden" name="presenca_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="status" value="ausente">
                                        <button type="submit" class="btn-status <?= $p['status'] === 'ausente' ? 'active danger' : '' ?>" title="Ausente">
                                            <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn-status <?= $p['status'] === 'justificado' ? 'active warning' : '' ?>" 
                                            onclick="abrirModalJustificativa(<?= $p['id'] ?>, <?= $p['person_id'] ?>, 'alterar', <?= $p['reason_id'] ?? 'null' ?>, '<?= addslashes($p['justificativa_texto'] ?? '') ?>')" title="Justificado">
                                        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                    </button>
                                <?php else: ?>
                                    <!-- Participante sem presença -->
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="marcar_presenca">
                                        <input type="hidden" name="person_id" value="<?= $p['person_id'] ?>">
                                        <input type="hidden" name="status" value="presente">
                                        <button type="submit" class="btn-status" title="Marcar Presente">
                                            <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="marcar_presenca">
                                        <input type="hidden" name="person_id" value="<?= $p['person_id'] ?>">
                                        <input type="hidden" name="status" value="ausente">
                                        <button type="submit" class="btn-status" title="Marcar Ausente">
                                            <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn-status" 
                                            onclick="abrirModalJustificativa(null, <?= $p['person_id'] ?>, 'marcar', null, '')" title="Marcar Justificado">
                                        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
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

    <!-- Adicionar Presença -->
    <div class="card" style="height: fit-content;">
        <div class="card-header">
            <h3 class="card-title">Adicionar Presença</h3>
        </div>
        <div class="card-body">
            <?php if (empty($pessoasSemPresenca)): ?>
            <p class="text-muted text-center">Todas as pessoas já foram registradas</p>
            <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="marcar_presenca">
                
                <div class="form-group">
                    <label class="form-label">Pessoa</label>
                    <select name="person_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($pessoasSemPresenca as $pessoa): ?>
                        <option value="<?= $pessoa['id'] ?>"><?= sanitize($pessoa['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="presente">Presente</option>
                        <option value="ausente">Ausente</option>
                        <option value="justificado">Justificado</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i data-lucide="plus"></i> Adicionar
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Justificativa -->
<div class="modal-backdrop" id="modalJustificativaBackdrop"></div>
<div class="modal" id="modalJustificativa">
    <div class="modal-content" style="max-width: 500px; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div class="modal-header">
            <h3><i data-lucide="file-text"></i> Selecionar Motivo da Justificativa</h3>
            <button type="button" class="modal-close" onclick="fecharModalJustificativa()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form method="POST" id="formJustificativa">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="justificativaAction">
            <input type="hidden" name="presenca_id" id="justificativaPresencaId">
            <input type="hidden" name="person_id" id="justificativaPersonId">
            <input type="hidden" name="status" value="justificado">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Motivo</label>
                    <select name="reason_id" class="form-control" required>
                        <option value="">Selecione o motivo...</option>
                        <?php foreach ($motivos as $motivo): ?>
                        <option value="<?= $motivo['id'] ?>" title="<?= sanitize($motivo['descricao'] ?? '') ?>">
                            <?= sanitize($motivo['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Observações (opcional)</label>
                    <textarea name="observacoes" class="form-control" rows="3" 
                              placeholder="Adicione detalhes adicionais se necessário..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalJustificativa()">Cancelar</button>
                <button type="submit" class="btn btn-warning">
                    <i data-lucide="check"></i> Confirmar Justificativa
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalJustificativa(presencaId, personId, acao, reasonId, justificativaTexto) {
    const modal = document.getElementById('modalJustificativa');
    const backdrop = document.getElementById('modalJustificativaBackdrop');
    const form = document.getElementById('formJustificativa');
    
    // Definir ação (marcar_presenca ou alterar_status)
    document.getElementById('justificativaAction').value = acao === 'marcar' ? 'marcar_presenca' : 'alterar_status';
    document.getElementById('justificativaPresencaId').value = presencaId || '';
    document.getElementById('justificativaPersonId').value = personId || '';
    
    // Carregar dados existentes ou resetar
    if (reasonId) {
        form.querySelector('select[name="reason_id"]').value = reasonId;
        
        // Extrair observações do texto da justificativa
        if (justificativaTexto) {
            const parts = justificativaTexto.split(': ');
            const observacoes = parts.length > 1 ? parts.slice(1).join(': ') : '';
            form.querySelector('textarea[name="observacoes"]').value = observacoes;
        } else {
            form.querySelector('textarea[name="observacoes"]').value = '';
        }
    } else {
        form.querySelector('select[name="reason_id"]').value = '';
        form.querySelector('textarea[name="observacoes"]').value = '';
    }
    
    // Mostrar modal
    modal.classList.add('show');
    backdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function fecharModalJustificativa() {
    const modal = document.getElementById('modalJustificativa');
    const backdrop = document.getElementById('modalJustificativaBackdrop');
    
    modal.classList.remove('show');
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
}
</script>

<style>
.btn-status {
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--gray-600);
}

.btn-status:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
}

.btn-status.active.success {
    background: #16a34a;
    border-color: #16a34a;
    color: white;
}

.btn-status.active.danger {
    background: #dc2626;
    border-color: #dc2626;
    color: white;
}

.btn-status.active.warning {
    background: #d97706;
    border-color: #d97706;
    color: white;
}

.btn-status.active:hover {
    opacity: 0.9;
}
</style>

<?php include BASE_PATH . 'includes/footer.php'; ?>
