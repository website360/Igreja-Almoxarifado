<?php
/**
 * Página de Login
 */
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'includes/init.php';

$auth = new Auth();

// Se já estiver logado, redireciona
if ($auth->check()) {
    redirect('/dashboard');
}

$error = '';
$success = '';

// Mensagem de sessão expirada
if (isset($_GET['expired'])) {
    $error = 'Sua sessão expirou. Por favor, faça login novamente.';
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Token de segurança inválido. Tente novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = 'Por favor, preencha todos os campos.';
        } elseif ($auth->attempt($email, $password, $remember)) {
            redirect('/dashboard');
        } else {
            $error = 'Email ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="login-page">
        <!-- Formulário de Login -->
        <section class="login-form-section">
            <div class="login-form-container">
                <div class="login-logo">
                    <div class="logo-icon">
                        <i data-lucide="church"></i>
                    </div>
                    <div class="logo-text">
                        <span class="logo-title"><?= APP_NAME ?></span>
                    </div>
                </div>

                <h1 class="login-title">Bem-vindo de volta</h1>
                <p class="login-subtitle">Acesse sua conta para se conectar com a comunidade e acompanhar os eventos.</p>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <div class="alert-content">
                        <i data-lucide="alert-circle"></i>
                        <span><?= sanitize($error) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <div class="alert-content">
                        <i data-lucide="check-circle"></i>
                        <span><?= sanitize($success) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <?= csrfField() ?>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">E-mail ou Usuário</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i data-lucide="user"></i>
                            </span>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                placeholder="exemplo@email.com"
                                value="<?= sanitize($_POST['email'] ?? '') ?>"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i data-lucide="lock"></i>
                            </span>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Digite sua senha"
                                required
                            >
                            <button type="button" class="input-group-text" id="togglePassword" style="cursor: pointer;">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-between align-center mb-2">
                        <div class="form-check">
                            <input type="checkbox" id="remember" name="remember" class="form-check-input">
                            <label for="remember" class="form-check-label">Lembrar-me</label>
                        </div>
                        <a href="<?= url('/recuperar-senha.php') ?>" class="text-primary text-sm font-medium">Esqueci minha senha</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        Entrar
                    </button>
                </form>

                <div class="login-divider">OU CONTINUE COM</div>

                <a href="<?= url('/cadastro.php') ?>" class="btn btn-secondary" style="width: 100%;">
                    <i data-lucide="user-plus"></i>
                    Criar nova conta
                </a>

                <div class="login-links mt-2">
                    <a href="#">Termos de Uso</a> · 
                    <a href="#">Privacidade</a> · 
                    <a href="#">Ajuda</a>
                </div>
            </div>
        </section>

        <!-- Imagem/Banner -->
        <section class="login-image-section">
            <div class="login-image-content">
                <div class="login-image-badge">COMUNIDADE ONLINE</div>
                
                <blockquote class="login-image-quote">
                    "Onde dois ou três estiverem reunidos em meu nome, ali estou no meio deles."
                </blockquote>
                
                <p class="login-image-text">
                    Junte-se a nós para cultos, grupos de oração e eventos comunitários. 
                    Sua jornada de fé começa aqui.
                </p>

                <div class="login-image-stats">
                    <div class="user-avatar-sm" style="background-color: #3B82F6;">JS</div>
                    <div class="user-avatar-sm" style="background-color: #10B981;">MS</div>
                    <div class="user-avatar-sm" style="background-color: #F59E0B;">CO</div>
                    <span>+2k membros ativos</span>
                </div>
            </div>
        </section>
    </div>

    <script>
        lucide.createIcons();

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('svg');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        });
    </script>
</body>
</html>
