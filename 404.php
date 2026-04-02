<?php
/**
 * Página 404 - Não Encontrado
 */
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'config/app.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body style="background: var(--gray-100);">
    <div class="empty-state" style="min-height: 100vh; display: flex; flex-direction: column; justify-content: center;">
        <div class="empty-state-icon" style="background: var(--warning-bg);">
            <i data-lucide="file-question" style="color: var(--warning);"></i>
        </div>
        <h1 class="empty-state-title">Página não encontrada</h1>
        <p class="empty-state-text">
            A página que você está procurando não existe ou foi movida.
        </p>
        <div class="d-flex gap-1 justify-center">
            <a href="<?= APP_URL ?>/" class="btn btn-primary">
                <i data-lucide="home"></i> Página Inicial
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i data-lucide="arrow-left"></i> Voltar
            </a>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
