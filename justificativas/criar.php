<?php
/**
 * Criar Justificativa de Ausência
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('justificativas', 'create');

$pageTitle = 'Justificar Ausência';
$db = Database::getInstance();

// Eventos que o usuário não teve presença
$eventosDisponiveis = $db->fetchAll(
    "SELECT e.id, e.titulo, e.inicio_at
     FROM events e
     WHERE e.inicio_at < NOW()
     AND e.id NOT IN (
        SELECT event_id FROM attendance 
        WHERE person_id = ? AND status = 'presente'
     )
     AND e.id NOT IN (
        SELECT event_id FROM absence_justifications 
        WHERE person_id = ?
     )
     ORDER BY e.inicio_at DESC
     LIMIT 20",
    [$currentUser['id'], $currentUser['id']]
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $eventId = intval($_POST['event_id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');

        if (!$eventId) {
            $errors[] = 'Selecione um evento.';
        }
        if (empty($motivo)) {
            $errors[] = 'O motivo é obrigatório.';
        }

        // Verificar se evento existe
        $evento = $db->fetch("SELECT * FROM events WHERE id = ?", [$eventId]);
        if (!$evento) {
            $errors[] = 'Evento não encontrado.';
        }

        // Verificar se já existe justificativa
        $existente = $db->fetch(
            "SELECT * FROM absence_justifications WHERE event_id = ? AND person_id = ?",
            [$eventId, $currentUser['id']]
        );
        if ($existente) {
            $errors[] = 'Você já enviou uma justificativa para este evento.';
        }

        // Upload de anexo
        $anexoUrl = null;
        if (!empty($_FILES['anexo']['name'])) {
            $anexoUrl = uploadFile($_FILES['anexo'], 'justificativas');
            if (!$anexoUrl) {
                $errors[] = 'Erro ao fazer upload do anexo.';
            }
        }

        if (empty($errors)) {
            try {
                $id = $db->insert('absence_justifications', [
                    'event_id' => $eventId,
                    'person_id' => $currentUser['id'],
                    'motivo' => $motivo,
                    'anexo_url' => $anexoUrl,
                    'status' => 'pendente',
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                Audit::log('justification_created', 'absence_justifications', $id);

                setFlash('success', 'Justificativa enviada com sucesso! Aguarde a análise.');
                redirect('/justificativas');
            } catch (Exception $e) {
                $errors[] = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    }
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <p class="page-subtitle">Justifique sua ausência em um evento</p>
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

<?php if (empty($eventosDisponiveis)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">
            <i data-lucide="check-circle"></i>
        </div>
        <h3 class="empty-state-title">Nenhum evento para justificar</h3>
        <p class="empty-state-text">Você está em dia com suas presenças ou já justificou suas ausências.</p>
        <a href="<?= url('/presencas') ?>" class="btn btn-primary">
            <i data-lucide="calendar"></i> Ver Minhas Presenças
        </a>
    </div>
</div>
<?php else: ?>

<form method="POST" enctype="multipart/form-data" class="card">
    <?= csrfField() ?>
    
    <div class="card-body">
        <div class="form-group">
            <label class="form-label required">Evento</label>
            <select name="event_id" class="form-control" required>
                <option value="">Selecione o evento...</option>
                <?php foreach ($eventosDisponiveis as $ev): ?>
                <option value="<?= $ev['id'] ?>" <?= ($_POST['event_id'] ?? '') == $ev['id'] ? 'selected' : '' ?>>
                    <?= sanitize($ev['titulo']) ?> - <?= formatDate($ev['inicio_at']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label required">Motivo da Ausência</label>
            <textarea name="motivo" class="form-control" rows="4" required
                      placeholder="Descreva o motivo da sua ausência..."><?= sanitize($_POST['motivo'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Anexo (opcional)</label>
            <input type="file" name="anexo" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            <small class="form-text">Atestado médico, documento comprobatório, etc.</small>
        </div>
    </div>

    <div class="card-footer">
        <a href="<?= url('/justificativas') ?>" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="send"></i> Enviar Justificativa
        </button>
    </div>
</form>

<?php endif; ?>

<?php include BASE_PATH . 'includes/footer.php'; ?>
