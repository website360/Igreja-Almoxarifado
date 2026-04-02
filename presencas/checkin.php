<?php
/**
 * Página de Check-in
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('presencas', 'create');

$pageTitle = 'Check-in';
$db = Database::getInstance();

$eventoId = intval($_GET['evento'] ?? 0);
$evento = null;
$jaFezCheckin = false;

// Buscar evento específico ou eventos com check-in aberto
if ($eventoId) {
    $evento = $db->fetch(
        "SELECT * FROM events WHERE id = ? AND status_checkin = 'aberto'",
        [$eventoId]
    );
    
    if ($evento) {
        // Verificar se usuário já fez check-in
        $checkinExistente = $db->fetch(
            "SELECT * FROM attendance WHERE event_id = ? AND person_id = ?",
            [$eventoId, $currentUser['id']]
        );
        $jaFezCheckin = $checkinExistente && $checkinExistente['status'] === 'presente';
    }
}

// Buscar todos eventos com check-in aberto
$eventosAbertos = $db->fetchAll(
    "SELECT e.*, m.nome as ministerio_nome,
            (SELECT COUNT(*) FROM attendance WHERE event_id = e.id AND status = 'presente') as total_presentes
     FROM events e
     LEFT JOIN ministerios m ON e.ministerio_responsavel_id = m.id
     WHERE e.status_checkin = 'aberto'
     ORDER BY e.inicio_at ASC"
);

// Processar check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        setFlash('error', 'Token de segurança inválido.');
    } else {
        $eventId = intval($_POST['event_id'] ?? 0);
        $personId = intval($_POST['person_id'] ?? $currentUser['id']);
        $method = $_POST['method'] ?? 'manual';

        // Verificar permissão para check-in de outros
        if ($personId !== $currentUser['id'] && !can('presencas', 'checkin_others')) {
            setFlash('error', 'Você não tem permissão para fazer check-in de outras pessoas.');
            redirect('/presencas/checkin.php?evento=' . $eventId);
        }

        // Verificar evento
        $eventoCheckin = $db->fetch(
            "SELECT * FROM events WHERE id = ? AND status_checkin = 'aberto'",
            [$eventId]
        );

        if (!$eventoCheckin) {
            setFlash('error', 'Evento não encontrado ou check-in fechado.');
        } else {
            // Verificar se já existe
            $existente = $db->fetch(
                "SELECT * FROM attendance WHERE event_id = ? AND person_id = ?",
                [$eventId, $personId]
            );

            if ($existente) {
                // Atualizar
                $db->update('attendance', [
                    'status' => 'presente',
                    'checkin_at' => date('Y-m-d H:i:s'),
                    'checkin_method' => $method,
                    'marked_by' => $currentUser['id']
                ], 'id = :id', ['id' => $existente['id']]);
            } else {
                // Inserir
                $db->insert('attendance', [
                    'event_id' => $eventId,
                    'person_id' => $personId,
                    'status' => 'presente',
                    'checkin_method' => $method,
                    'checkin_at' => date('Y-m-d H:i:s'),
                    'marked_by' => $currentUser['id']
                ]);
            }

            Audit::log('checkin', 'attendance', null, [
                'event_id' => $eventId,
                'person_id' => $personId,
                'method' => $method
            ]);

            setFlash('success', 'Check-in realizado com sucesso!');
            redirect('/presencas/checkin.php?evento=' . $eventId);
        }
    }
}

include BASE_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Check-in</h1>
        <p class="page-subtitle">Registre sua presença nos eventos</p>
    </div>
    <a href="<?= url('/presencas') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left"></i> Voltar
    </a>
</div>

<?php if ($evento && $jaFezCheckin): ?>
<div class="alert alert-success">
    <div class="alert-content">
        <i data-lucide="check-circle"></i>
        <span>Você já fez check-in neste evento!</span>
    </div>
</div>
<?php endif; ?>

<?php if (empty($eventosAbertos)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">
            <i data-lucide="calendar-x"></i>
        </div>
        <h3 class="empty-state-title">Nenhum evento com check-in aberto</h3>
        <p class="empty-state-text">Não há eventos disponíveis para check-in no momento.</p>
        <a href="<?= url('/eventos') ?>" class="btn btn-primary">
            <i data-lucide="calendar"></i> Ver Eventos
        </a>
    </div>
</div>
<?php else: ?>

<div class="grid grid-2" style="gap: 24px;">
    <?php foreach ($eventosAbertos as $ev): ?>
    <?php 
    $meuCheckin = $db->fetch(
        "SELECT * FROM attendance WHERE event_id = ? AND person_id = ? AND status = 'presente'",
        [$ev['id'], $currentUser['id']]
    );
    $jaMarcado = $meuCheckin !== null;
    ?>
    <div class="card <?= $ev['id'] == $eventoId ? 'border-primary' : '' ?>" style="<?= $ev['id'] == $eventoId ? 'border: 2px solid var(--primary);' : '' ?>">
        <div class="card-body">
            <div class="d-flex justify-between align-center mb-2">
                <div>
                    <span class="badge badge-success">Check-in Aberto</span>
                    <?php if ($ev['destaque']): ?>
                    <span class="badge badge-primary">Destaque</span>
                    <?php endif; ?>
                </div>
                <span class="text-muted text-sm"><?= $ev['total_presentes'] ?> presente(s)</span>
            </div>
            
            <h3 class="card-title mb-1"><?= sanitize($ev['titulo']) ?></h3>
            
            <div class="event-meta mb-2">
                <span><i data-lucide="calendar"></i> <?= formatDateTime($ev['inicio_at']) ?></span>
                <?php if ($ev['local']): ?>
                <span><i data-lucide="map-pin"></i> <?= sanitize($ev['local']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($ev['ministerio_nome']): ?>
            <p class="text-muted text-sm mb-2"><?= sanitize($ev['ministerio_nome']) ?></p>
            <?php endif; ?>

            <?php if ($jaMarcado): ?>
            <div class="alert alert-success mb-2" style="margin-bottom: 0;">
                <div class="alert-content">
                    <i data-lucide="check-circle"></i>
                    <span>Você já está presente! (<?= formatDateTime($meuCheckin['checkin_at']) ?>)</span>
                </div>
            </div>
            <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                <input type="hidden" name="method" value="manual">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i data-lucide="check-circle"></i> Fazer Check-in
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (can('presencas', 'checkin_others')): ?>
<hr style="margin: 32px 0; border-color: var(--gray-200);">

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Check-in Manual (Secretaria)</h3>
    </div>
    <div class="card-body">
        <form method="POST" id="checkinManualForm">
            <?= csrfField() ?>
            <input type="hidden" name="method" value="secretaria">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Evento</label>
                    <select name="event_id" class="form-control" required>
                        <option value="">Selecione o evento...</option>
                        <?php foreach ($eventosAbertos as $ev): ?>
                        <option value="<?= $ev['id'] ?>" <?= $eventoId == $ev['id'] ? 'selected' : '' ?>>
                            <?= sanitize($ev['titulo']) ?> (<?= formatDateTime($ev['inicio_at']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Pessoa</label>
                    <select name="person_id" class="form-control" required id="personSelect">
                        <option value="">Selecione a pessoa...</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i data-lucide="check"></i> Registrar Presença
            </button>
        </form>
    </div>
</div>

<script>
// Carregar pessoas quando selecionar evento
document.querySelector('select[name="event_id"]').addEventListener('change', function() {
    const eventId = this.value;
    const personSelect = document.getElementById('personSelect');
    
    if (!eventId) {
        personSelect.innerHTML = '<option value="">Selecione a pessoa...</option>';
        return;
    }
    
    fetch('<?= url('/presencas/api.php') ?>?action=pessoas_sem_checkin&event_id=' + eventId)
        .then(response => response.json())
        .then(data => {
            personSelect.innerHTML = '<option value="">Selecione a pessoa...</option>';
            if (data.success && data.data) {
                data.data.forEach(person => {
                    personSelect.innerHTML += `<option value="${person.id}">${person.nome} (${person.email})</option>`;
                });
            }
        });
});

// Trigger para carregar se já tiver evento selecionado
<?php if ($eventoId): ?>
document.querySelector('select[name="event_id"]').dispatchEvent(new Event('change'));
<?php endif; ?>
</script>
<?php endif; ?>

<?php endif; ?>

<?php include BASE_PATH . 'includes/footer.php'; ?>
