<?php
/**
 * Página de Acesso Negado
 */
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'includes/init.php';

$pageTitle = 'Acesso Negado';
include BASE_PATH . 'includes/header.php';
?>

<div class="empty-state" style="padding: 80px 20px;">
    <div class="empty-state-icon" style="background: var(--danger-bg);">
        <i data-lucide="shield-off" style="color: var(--danger);"></i>
    </div>
    <h1 class="empty-state-title">Acesso Negado</h1>
    <p class="empty-state-text">
        Você não tem permissão para acessar esta página.<br>
        Se você acredita que isso é um erro, entre em contato com o administrador.
    </p>
    <div class="d-flex gap-1 justify-center">
        <a href="<?= url('/dashboard/index.php') ?>" class="btn btn-primary">
            <i data-lucide="home"></i> Ir para o Dashboard
        </a>
        <a href="javascript:history.back()" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?php include BASE_PATH . 'includes/footer.php'; ?>
