<?php
/**
 * Header do Sistema
 */
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
$permissions = new Permissions();
$menu = isset($currentUser) ? $permissions->getFilteredMenu($currentUser['id']) : [];
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= getSetting('igreja_nome', APP_NAME) ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <?php 
                    $logoUrl = getSetting('igreja_logo_url');
                    if ($logoUrl): 
                    ?>
                        <div style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: transparent;">
                            <img src="<?= url($logoUrl) ?>" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                    <?php else: ?>
                        <div class="logo-icon">
                            <i data-lucide="church"></i>
                        </div>
                    <?php endif; ?>
                    <div class="logo-text">
                        <span class="logo-title"><?= getSetting('igreja_nome', APP_NAME) ?></span>
                        <span class="logo-subtitle"><?= $currentUserUnidade['nome'] ?? 'Sistema de Gestão' ?></span>
                    </div>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i data-lucide="menu"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-title">Menu Principal</span>
                    <?php foreach ($menu as $key => $item): ?>
                        <?php 
                        $isActive = strpos($_SERVER['REQUEST_URI'], $item['route']) !== false;
                        $activeClass = $isActive ? 'active' : '';
                        ?>
                        <a href="<?= url($item['route']) ?>" class="nav-link <?= $activeClass ?>">
                            <i data-lucide="<?= $item['icon'] ?>"></i>
                            <span><?= $item['name'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="nav-section">
                    <span class="nav-section-title">Conta</span>
                    <a href="<?= url('/perfil.php') ?>" class="nav-link">
                        <i data-lucide="user"></i>
                        <span>Meu Perfil</span>
                    </a>
                    <a href="<?= url('/logout.php') ?>" class="nav-link">
                        <i data-lucide="log-out"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </nav>

            <?php if ($currentUser): ?>
            <div class="sidebar-footer">
                <div class="user-info">
                    <?php if (!empty($currentUser['foto_url'])): ?>
                    <img src="<?= url($currentUser['foto_url']) ?>" alt="<?= sanitize($currentUser['nome']) ?>" class="user-avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                    <div class="user-avatar" style="background-color: <?= getAvatarColor($currentUser['nome']) ?>">
                        <?= getInitials($currentUser['nome']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="user-details">
                        <span class="user-name"><?= sanitize($currentUser['nome']) ?></span>
                        <span class="user-email"><?= sanitize($currentUser['email']) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i data-lucide="menu"></i>
                    </button>
                    <div class="breadcrumb">
                        <a href="<?= url('/dashboard') ?>"><i data-lucide="home"></i></a>
                        <span class="breadcrumb-separator">/</span>
                        <span class="breadcrumb-current"><?= $pageTitle ?></span>
                    </div>
                </div>

                <div class="header-center">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" placeholder="Buscar..." id="globalSearch">
                    </div>
                </div>

                <div class="header-right">
                    <div class="header-dropdown">
                        <button class="header-btn user-btn" id="userDropdownBtn">
                            <?php if (!empty($currentUser['foto_url'])): ?>
                            <img src="<?= url($currentUser['foto_url']) ?>" alt="" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                            <div class="user-avatar-sm" style="background-color: <?= getAvatarColor($currentUser['nome'] ?? 'U') ?>">
                                <?= getInitials($currentUser['nome'] ?? 'U') ?>
                            </div>
                            <?php endif; ?>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" id="userDropdown">
                            <a href="<?= url('/perfil.php') ?>" class="dropdown-item">
                                <i data-lucide="user"></i> Meu Perfil
                            </a>
                            <a href="<?= url('/configuracoes') ?>" class="dropdown-item">
                                <i data-lucide="settings"></i> Configurações
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?= url('/logout.php') ?>" class="dropdown-item text-danger">
                                <i data-lucide="log-out"></i> Sair
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <?php if ($flash): ?>
                <script>
                    window._flashMessage = { message: '<?= addslashes($flash['message']) ?>', type: '<?= $flash['type'] ?>' };
                </script>
                <?php endif; ?>
