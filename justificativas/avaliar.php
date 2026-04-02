<?php
/**
 * Avaliar Justificativa
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('justificativas', 'approve');

$pageTitle = 'Avaliar Justificativa';
$db = Database::getInstance();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect('/justificativas');
}

$justificativa = $db->fetch(
    "SELECT j.*, e.titulo as evento_titulo, e.inicio_at as evento_data,
            u.nome as pessoa_nome, u.email as pessoa_email, u.telefone_whatsapp
     FROM absence_justifications j
     JOIN events e ON j.event_id = e.id
     JOIN users u ON j.person_id = u.id
     WHERE j.id = ?",
    [$id]
);

if (!$justificativa) {
    setFlash('error', 'Justificativa não encontrada.');
    redirect('/justificativas');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $acao = $_POST['acao'] ?? '';
        $reviewNotes = trim($_POST['review_notes'] ?? '');

        if (!in_array($acao, ['aprovar', 'recusar'])) {
            $errors[] = 'Ação inválida.';
        }

        if (empty($errors)) {
            $novoStatus = $acao === 'aprovar' ? 'aprovada' : 'recusada';

            $db->update('absence_justifications', [
                'status' => $novoStatus,
                'reviewed_by' => $currentUser['id'],
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => $reviewNotes
            ], 'id = :id', ['id' => $id]);

            // Atualizar presença se aprovada
            if ($novoStatus === 'aprovada') {
                $presenca = $db->fetch(
                    "SELECT * FROM attendance WHERE event_id = ? AND person_id = ?",
                    [$justificativa['event_id'], $justificativa['person_id']]
                );

                if ($presenca) {
                    $db->update('attendance', ['status' => 'justificado'], 'id = :id', ['id' => $presenca['id']]);
                } else {
                    $db->insert('attendance', [
                        'event_id' => $justificativa['event_id'],
                        'person_id' => $justificativa['person_id'],
                        'status' => 'justificado',
                        'checkin_method' => 'secretaria',
                        'marked_by' => $currentUser['id']
                    ]);
                }
            }

            Audit::log('justification_' . ($novoStatus === 'aprovada' ? 'approved' : 'rejected'), 
                       'absence_justifications', $id, ['notes' => $reviewNotes]);

            setFlash('success', 'Justificativa ' . ($novoStatus === 'aprovada' ? 'aprovada' : 'recusada') . ' com sucesso!');
            redirect('/justificativas');
        }
    }
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <p class="page-subtitle">Analise e decida sobre a justificativa</p>
    </div>
    <a href="<?= url('/justificativas') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <div class="alert-content">
        <i data-lucide="alert-circle"></i>
        <div>
            <?php foreach ($errors as $error): ?>
            <div><?= sanitize($error) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-3" style="gap: 24px;">
    <div style="grid-column: span 2;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detalhes da Justificativa</h3>
                <?= statusBadge($justificativa['status']) ?>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label text-muted">Pessoa</label>
                        <div class="d-flex align-center gap-1">
                            <div class="user-avatar" style="background-color: <?= getAvatarColor($justificativa['pessoa_nome']) ?>">
                                <?= getInitials($justificativa['pessoa_nome']) ?>
                            </div>
                            <div>
                                <strong><?= sanitize($justificativa['pessoa_nome']) ?></strong><br>
                                <small class="text-muted"><?= sanitize($justificativa['pessoa_email']) ?></small><br>
                                <small class="text-muted"><?= formatPhone($justificativa['telefone_whatsapp']) ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label text-muted">Evento</label>
                        <p>
                            <strong><?= sanitize($justificativa['evento_titulo']) ?></strong><br>
                            <small class="text-muted"><?= formatDateTime($justificativa['evento_data']) ?></small>
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label text-muted">Motivo Apresentado</label>
                    <div style="background: var(--gray-50); padding: 16px; border-radius: var(--border-radius); border-left: 4px solid var(--primary);">
                        <?= nl2br(sanitize($justificativa['motivo'])) ?>
                    </div>
                </div>

                <?php if ($justificativa['anexo_url']): ?>
                <div class="form-group">
                    <label class="form-label text-muted">Anexo</label>
                    <a href="<?= url($justificativa['anexo_url']) ?>" target="_blank" class="btn btn-secondary">
                        <i data-lucide="paperclip"></i> Visualizar Anexo
                    </a>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label text-muted">Enviado em</label>
                    <p><?= formatDateTime($justificativa['created_at']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div>
        <?php if ($justificativa['status'] === 'pendente'): ?>
        <form method="POST" class="card">
            <?= csrfField() ?>
            <div class="card-header">
                <h3 class="card-title">Decisão</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Observações (opcional)</label>
                    <textarea name="review_notes" class="form-control" rows="3"
                              placeholder="Adicione uma observação para o membro..."><?= sanitize($_POST['review_notes'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-1" style="flex-direction: column;">
                    <button type="submit" name="acao" value="aprovar" class="btn btn-success">
                        <i data-lucide="check"></i> Aprovar Justificativa
                    </button>
                    <button type="submit" name="acao" value="recusar" class="btn btn-danger">
                        <i data-lucide="x"></i> Recusar Justificativa
                    </button>
                </div>
            </div>
        </form>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Resultado</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label text-muted">Status</label>
                    <p><?= statusBadge($justificativa['status']) ?></p>
                </div>
                <?php if ($justificativa['reviewed_at']): ?>
                <div class="form-group">
                    <label class="form-label text-muted">Avaliado em</label>
                    <p><?= formatDateTime($justificativa['reviewed_at']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($justificativa['review_notes']): ?>
                <div class="form-group">
                    <label class="form-label text-muted">Observações</label>
                    <p><?= nl2br(sanitize($justificativa['review_notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
