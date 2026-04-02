<?php
/**
 * Dashboard Principal
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();
requirePermission('dashboard', 'view');

$pageTitle = 'Dashboard';
$db = Database::getInstance();

// Configurar timeout para evitar travamento
try {
    $db->getConnection()->setAttribute(PDO::ATTR_TIMEOUT, 2);
} catch (Exception $e) {
    // Ignorar se driver não suportar
}

// Dados para o dashboard
$hoje = date('Y-m-d');
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$fimSemana = date('Y-m-d', strtotime('sunday this week'));
$inicioMes = date('Y-m-01');
$fimMes = date('Y-m-t');

// Total de membros ativos
try {
    $result = $db->fetch("SELECT COUNT(*) as total FROM users WHERE status = 'ativo'");
    $totalMembros = $result ? $result['total'] : 0;
} catch (Exception $e) {
    $totalMembros = 0;
}

// Eventos da semana
try {
    $eventosSemana = $db->fetchAll(
        "SELECT * FROM events 
         WHERE DATE(inicio_at) BETWEEN ? AND ? 
         ORDER BY inicio_at ASC LIMIT 5",
        [$inicioSemana, $fimSemana]
    );
} catch (Exception $e) {
    $eventosSemana = [];
}

// Próximos eventos
try {
    $proximosEventos = $db->fetchAll(
        "SELECT e.id, e.titulo, e.tipo, e.inicio_at, e.local, m.nome as ministerio_nome 
         FROM events e 
         LEFT JOIN ministerios m ON e.ministerio_responsavel_id = m.id
         WHERE e.inicio_at >= NOW() 
         ORDER BY e.inicio_at ASC LIMIT 5"
    );
} catch (Exception $e) {
    $proximosEventos = [];
}

// Total de eventos do mês
try {
    $result = $db->fetch(
        "SELECT COUNT(*) as total FROM events WHERE DATE(inicio_at) BETWEEN ? AND ?",
        [$inicioMes, $fimMes]
    );
    $totalEventosMes = $result ? $result['total'] : 0;
} catch (Exception $e) {
    $totalEventosMes = 0;
}

// Total de produtos no almoxarifado
try {
    $result = $db->fetch("SELECT COUNT(*) as total FROM inventory_items");
    $totalProdutos = $result ? $result['total'] : 0;
} catch (Exception $e) {
    $totalProdutos = 0;
}

// Justificativas pendentes
try {
    $result = $db->fetch("SELECT COUNT(*) as total FROM absence_justifications WHERE status = 'pendente'");
    $justificativasPendentes = $result ? $result['total'] : 0;
} catch (Exception $e) {
    $justificativasPendentes = 0;
}

// Mensagens com falha
try {
    $result = $db->fetch("SELECT COUNT(*) as total FROM message_queue WHERE status = 'failed'");
    $mensagensFalha = $result ? $result['total'] : 0;
} catch (Exception $e) {
    $mensagensFalha = 0;
}

// Itens emprestados
try {
    $result = $db->fetch("SELECT COUNT(*) as total FROM inventory_items WHERE status = 'emprestado'");
    $itensEmprestados = $result ? $result['total'] : 0;
} catch (Exception $e) {
    $itensEmprestados = 0;
}

include BASE_PATH . 'includes/header.php';
?>

<!-- Welcome Section -->
<div style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); border-radius: 12px; padding: 32px; margin-bottom: 24px; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 600; margin: 0 0 8px 0;">Bem-vindo, <?= sanitize(explode(' ', $currentUser['nome'])[0]) ?></h1>
            <p style="font-size: 0.95rem; opacity: 0.9; margin: 0;"><?= formatDateFull($hoje) ?></p>
        </div>
        <?php if (can('eventos', 'create')): ?>
        <a href="<?= url('/eventos/criar.php') ?>" class="btn btn-lg" style="background: white; color: #1e40af; font-weight: 500;">
            <i data-lucide="plus"></i> Novo Evento
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="background: #eff6ff; width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="users" style="width: 24px; height: 24px; color: #3b82f6;"></i>
            </div>
            <div>
                <div style="font-size: 1.75rem; font-weight: 600; color: #1f2937; margin-bottom: 2px;"><?= number_format($totalMembros, 0, ',', '.') ?></div>
                <div style="color: #6b7280; font-size: 0.875rem;">Membros Ativos</div>
            </div>
        </div>
    </div>

    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="background: #fef3c7; width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="calendar" style="width: 24px; height: 24px; color: #f59e0b;"></i>
            </div>
            <div>
                <div style="font-size: 1.75rem; font-weight: 600; color: #1f2937; margin-bottom: 2px;"><?= $totalEventosMes ?></div>
                <div style="color: #6b7280; font-size: 0.875rem;">Eventos este Mês</div>
            </div>
        </div>
    </div>

    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="background: #f0fdf4; width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="package" style="width: 24px; height: 24px; color: #10b981;"></i>
            </div>
            <div>
                <div style="font-size: 1.75rem; font-weight: 600; color: #1f2937; margin-bottom: 2px;"><?= number_format($totalProdutos, 0, ',', '.') ?></div>
                <div style="color: #6b7280; font-size: 0.875rem;">Produtos</div>
            </div>
        </div>
    </div>

    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="background: #fef2f2; width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="arrow-right-left" style="width: 24px; height: 24px; color: #ef4444;"></i>
            </div>
            <div>
                <div style="font-size: 1.75rem; font-weight: 600; color: #1f2937; margin-bottom: 2px;"><?= $itensEmprestados ?></div>
                <div style="color: #6b7280; font-size: 0.875rem;">Produtos Emprestados</div>
            </div>
        </div>
    </div>
</div>

<!-- Próximos Eventos e Alertas -->
<div style="margin-bottom: 24px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
        <!-- Próximos Eventos -->
        <div class="card mb-3" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="card-header" style="border-bottom: 1px solid #e5e7eb; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600; color: #1f2937;">Próximos Eventos</h3>
                <a href="<?= url('/eventos') ?>" class="text-primary text-sm" style="font-weight: 500;">Ver todos →</a>
            </div>
            <div class="upcoming-events">
                <?php if (empty($proximosEventos)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <p class="text-muted">Nenhum evento agendado</p>
                </div>
                <?php else: ?>
                    <?php foreach ($proximosEventos as $evento): ?>
                    <div class="event-item">
                        <div class="event-date-box">
                            <div class="event-date-month"><?= strtoupper(getMesPt($evento['inicio_at'], true)) ?></div>
                            <div class="event-date-day"><?= date('d', strtotime($evento['inicio_at'])) ?></div>
                        </div>
                        <div class="event-info">
                            <h4><?= sanitize($evento['titulo']) ?></h4>
                            <div class="event-meta">
                                <span><i data-lucide="clock"></i> <?= date('H:i', strtotime($evento['inicio_at'])) ?></span>
                                <?php if ($evento['local']): ?>
                                <span><i data-lucide="map-pin"></i> <?= sanitize($evento['local']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($justificativasPendentes > 0 || $mensagensFalha > 0 || $itensEmprestados > 0): ?>
        <div class="card mb-3" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="card-header" style="border-bottom: 1px solid #e5e7eb; padding: 20px;">
                <h3 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600; color: #1f2937;">Alertas</h3>
            </div>
            <div class="card-body">
                <?php if ($justificativasPendentes > 0 && can('justificativas', 'approve')): ?>
                <div class="alert alert-warning mb-1">
                    <div class="alert-content">
                        <i data-lucide="alert-triangle"></i>
                        <span><?= $justificativasPendentes ?> justificativa(s) pendente(s)</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($mensagensFalha > 0 && can('integracoes', 'view')): ?>
                <div class="alert alert-danger mb-1">
                    <div class="alert-content">
                        <i data-lucide="alert-circle"></i>
                        <span><?= $mensagensFalha ?> falha(s) no envio WhatsApp</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($itensEmprestados > 0 && can('almoxarifado', 'view')): ?>
                <div class="alert alert-info">
                    <div class="alert-content">
                        <i data-lucide="package"></i>
                        <span><?= $itensEmprestados ?> item(ns) emprestado(s)</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Dashboard scripts
</script>

<?php include BASE_PATH . 'includes/footer.php'; ?>
