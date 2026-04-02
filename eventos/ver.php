<?php
/**
 * Visualizar Evento
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('eventos', 'view');

$pageTitle = 'Detalhes do Evento';
$db = Database::getInstance();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect('/eventos');
}

$evento = $db->fetch(
    "SELECT e.*, m.nome as ministerio_nome, u.nome as criado_por_nome
     FROM events e
     LEFT JOIN ministerios m ON e.ministerio_responsavel_id = m.id
     LEFT JOIN users u ON e.created_by = u.id
     WHERE e.id = ?",
    [$id]
);

if (!$evento) {
    setFlash('error', 'Evento não encontrado.');
    redirect('/eventos');
}

// Estatísticas de presença (total de participantes + status)
$totalParticipantes = $db->fetch(
    "SELECT COUNT(*) as total FROM event_participants WHERE event_id = ?",
    [$id]
);

$presencas = $db->fetch(
    "SELECT 
        COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
        COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
        COUNT(CASE WHEN status = 'justificado' THEN 1 END) as justificados
     FROM attendance WHERE event_id = ?",
    [$id]
);

$presencas['total'] = $totalParticipantes['total'];

// Últimas presenças (buscar de event_participants com LEFT JOIN em attendance)
$ultimasPresencas = $db->fetchAll(
    "SELECT u.nome, u.email, a.status, a.checkin_method, a.checkin_at
     FROM event_participants ep
     JOIN users u ON ep.user_id = u.id
     LEFT JOIN attendance a ON a.event_id = ep.event_id AND a.person_id = ep.user_id
     WHERE ep.event_id = ?
     ORDER BY a.checkin_at DESC, u.nome ASC
     LIMIT 10",
    [$id]
);

// Justificativas
$justificativas = $db->fetchAll(
    "SELECT j.*, u.nome as pessoa_nome
     FROM absence_justifications j
     JOIN users u ON j.person_id = u.id
     WHERE j.event_id = ?
     ORDER BY j.created_at DESC",
    [$id]
);

// Template de WhatsApp
$template = null;
if ($evento['whatsapp_template_id']) {
    $template = $db->fetch("SELECT * FROM whatsapp_templates WHERE id = ?", [$evento['whatsapp_template_id']]);
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= sanitize($evento['titulo']) ?></h1>
        <p class="page-subtitle"><?= EVENT_TYPES[$evento['tipo']] ?? $evento['tipo'] ?> • <?= formatDateTime($evento['inicio_at']) ?></p>
    </div>
    <div class="btn-group">
        <a href="<?= url('/eventos') ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i> Voltar
        </a>
        <?php if (can('eventos', 'edit')): ?>
        <a href="<?= url('/eventos/criar.php?id=' . $evento['id']) ?>" class="btn btn-primary">
            <i data-lucide="edit"></i> Editar
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Primeira linha: Informações, Estatísticas e Ações -->
<div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Informações do Evento -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="calendar" style="width: 18px; height: 18px;"></i> Informações do Evento</h3>
            <div class="d-flex gap-1">
                <?= statusBadge($evento['status']) ?>
            </div>
        </div>
        <div class="card-body">
            <?php if ($evento['imagem_url']): ?>
            <div style="width: 100% !important; height: 180px; overflow: hidden; border-radius: 16px !important; margin: 0 0 20px 0 !important; padding: 0 !important; float: none !important; position: relative;">
                <img src="<?= url($evento['imagem_url']) ?>" alt="<?= sanitize($evento['titulo']) ?>" 
                     style="width: 100% !important; height: 100% !important; object-fit: none; object-position: center; display: block !important; margin: 0 !important; padding: 0 !important; float: none !important;">
            </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label text-muted">Data e Hora de Início</label>
                    <p class="font-semibold"><?= formatDateTime($evento['inicio_at'], 'd/m/Y \à\s H:i') ?></p>
                </div>
                <?php if ($evento['fim_at']): ?>
                <div class="form-group">
                    <label class="form-label text-muted">Data e Hora de Término</label>
                    <p class="font-semibold"><?= formatDateTime($evento['fim_at'], 'd/m/Y \à\s H:i') ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label text-muted">Local</label>
                    <p class="font-semibold"><?= sanitize($evento['local'] ?? 'Não informado') ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label text-muted">Ministério</label>
                    <p class="font-semibold"><?= sanitize($evento['ministerio_nome'] ?? 'Não definido') ?></p>
                </div>
            </div>

            <?php if ($evento['descricao']): ?>
            <div class="form-group">
                <label class="form-label text-muted">Descrição</label>
                <p><?= nl2br(sanitize($evento['descricao'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="bar-chart-3" style="width: 18px; height: 18px;"></i> Estatísticas</h3>
        </div>
        <div class="card-body">
            <div class="stat-card mb-2" style="padding: 12px;">
                <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
                    <i data-lucide="users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $presencas['total'] ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="stat-card mb-2" style="padding: 12px;">
                <div class="stat-icon success">
                    <i data-lucide="check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $presencas['presentes'] ?></div>
                    <div class="stat-label">Presentes</div>
                </div>
            </div>
            <div class="stat-card mb-2" style="padding: 12px;">
                <div class="stat-icon danger">
                    <i data-lucide="x-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $presencas['ausentes'] ?></div>
                    <div class="stat-label">Ausentes</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 12px;">
                <div class="stat-icon warning">
                    <i data-lucide="file-text"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $presencas['justificados'] ?></div>
                    <div class="stat-label">Justificados</div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code e Ações -->
    <div>
        <!-- QR Code -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i data-lucide="qr-code" style="width: 18px; height: 18px;"></i> QR Code</h3>
            </div>
            <div class="card-body text-center" style="padding: 16px;">
                <div id="qrcode"></div>
                <button class="btn btn-secondary btn-sm mt-2" style="width: 100%;" onclick="copyToClipboard('<?= url('/presencas/checkin.php?evento=' . $evento['id']) ?>')">
                    <i data-lucide="copy"></i> Copiar Link
                </button>
            </div>
        </div>

        <!-- Ações -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i data-lucide="zap" style="width: 18px; height: 18px;"></i> Ações</h3>
            </div>
            <div class="card-body" style="padding: 16px;">
                <?php if (can('presencas', 'checkin_others')): ?>
                <a href="<?= url('/presencas/evento.php?id=' . $evento['id']) ?>" class="btn btn-primary btn-sm mb-1" style="width: 100%;">
                    <i data-lucide="check-square"></i> Gerenciar Presenças
                </a>
                <?php endif; ?>
                
                <?php if (can('relatorios', 'export')): ?>
                <a href="<?= url('/eventos/exportar.php?id=' . $evento['id']) ?>" class="btn btn-secondary btn-sm mb-1" style="width: 100%;">
                    <i data-lucide="download"></i> Exportar
                </a>
                <?php endif; ?>
                
                <?php if (can('eventos', 'delete')): ?>
                <button class="btn btn-outline-danger btn-sm" style="width: 100%;" onclick="confirmDelete('<?= url('/eventos/api.php?action=delete&id=' . $evento['id']) ?>', '<?= url('/eventos') ?>')">
                    <i data-lucide="trash-2"></i> Excluir
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Segunda linha: Presenças Recentes -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="users"></i> Presenças Recentes</h3>
    </div>
    <div>
        <?php if (empty($ultimasPresencas)): ?>
        <div class="empty-state" style="padding: 40px;">
            <div class="empty-state-icon">
                <i data-lucide="users"></i>
            </div>
            <h3 class="empty-state-title">Nenhuma presença registrada</h3>
            <p class="empty-state-text">Ainda não há check-ins para este evento.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pessoa</th>
                        <th>Status</th>
                        <th>Método</th>
                        <th>Data/Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimasPresencas as $p): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($p['nome']) ?>">
                                    <?= getInitials($p['nome']) ?>
                                </div>
                                <div>
                                    <strong><?= sanitize($p['nome']) ?></strong><br>
                                    <small class="text-muted"><?= sanitize($p['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= statusBadge($p['status']) ?></td>
                        <td><?= CHECKIN_METHODS[$p['checkin_method']] ?? $p['checkin_method'] ?></td>
                        <td><?= $p['checkin_at'] ? formatDateTime($p['checkin_at']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($presencas['total'] > 10): ?>
        <div class="card-footer">
            <a href="<?= url('/presencas/evento.php?id=' . $evento['id']) ?>" class="btn btn-secondary">
                Ver todas as presenças
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gerar QR Code
    generateQRCode('qrcode', '<?= url('/presencas/checkin.php?evento=' . $evento['id']) ?>', 180);
    
    // Sistema de abas
    document.querySelectorAll('.tab-item').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
            document.getElementById(tabId).style.display = 'block';
            
            lucide.createIcons();
        });
    });
});

function toggleCheckin(status) {
    fetch('<?= url('/eventos/api.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken
        },
        body: JSON.stringify({
            action: 'toggle_checkin',
            id: <?= $evento['id'] ?>,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Erro ao alterar status', 'error');
        }
    })
    .catch(error => {
        showToast('Erro ao processar requisição', 'error');
    });
}
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
