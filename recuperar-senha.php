<?php
/**
 * Recuperar Senha
 */
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'includes/init.php';

$auth = new Auth();

if ($auth->check()) {
    redirect('/dashboard/index.php');
}

$error = '';
$success = '';
$step = 'email';

// Verificar token
if (isset($_GET['token']) && isset($_GET['email'])) {
    $step = 'reset';
    $token = $_GET['token'];
    $email = $_GET['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Token de segurança inválido.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'request') {
            $email = trim($_POST['email'] ?? '');
            
            if (empty($email)) {
                $error = 'Informe seu email.';
            } elseif (!isValidEmail($email)) {
                $error = 'Email inválido.';
            } else {
                $token = $auth->createResetToken($email);
                
                if ($token) {
                    // Aqui você enviaria o email com o link
                    // Por enquanto, apenas mostramos uma mensagem de sucesso
                    $success = 'Se este email estiver cadastrado, você receberá um link para redefinir sua senha.';
                    
                    // Em produção, enviar email:
                    // $resetLink = APP_URL . "/recuperar-senha.php?email=" . urlencode($email) . "&token=" . $token;
                    // sendEmail($email, "Recuperar Senha", "Clique aqui: $resetLink");
                } else {
                    $success = 'Se este email estiver cadastrado, você receberá um link para redefinir sua senha.';
                }
            }

        } elseif ($action === 'reset') {
            $email = $_POST['email'] ?? '';
            $token = $_POST['token'] ?? '';
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';

            if (strlen($novaSenha) < 6) {
                $error = 'A senha deve ter pelo menos 6 caracteres.';
            } elseif ($novaSenha !== $confirmarSenha) {
                $error = 'As senhas não coincidem.';
            } elseif ($auth->resetPassword($email, $token, $novaSenha)) {
                $success = 'Senha alterada com sucesso! Você já pode fazer login.';
                $step = 'done';
            } else {
                $error = 'Link inválido ou expirado. Solicite um novo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="login-page">
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

                <?php if ($step === 'email'): ?>
                <h1 class="login-title">Esqueceu sua senha?</h1>
                <p class="login-subtitle">Digite seu email e enviaremos instruções para redefinir sua senha.</p>

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
                <?php else: ?>
                <form method="POST" class="login-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="request">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="seu@email.com" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i data-lucide="mail"></i> Enviar Instruções
                    </button>
                </form>
                <?php endif; ?>

                <?php elseif ($step === 'reset'): ?>
                <h1 class="login-title">Redefinir Senha</h1>
                <p class="login-subtitle">Digite sua nova senha.</p>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <div class="alert-content">
                        <i data-lucide="alert-circle"></i>
                        <span><?= sanitize($error) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="email" value="<?= sanitize($email) ?>">
                    <input type="hidden" name="token" value="<?= sanitize($token) ?>">
                    
                    <div class="form-group">
                        <label for="nova_senha" class="form-label">Nova Senha</label>
                        <input type="password" id="nova_senha" name="nova_senha" class="form-control" 
                               placeholder="Mínimo 6 caracteres" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" 
                               placeholder="Digite novamente" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i data-lucide="lock"></i> Redefinir Senha
                    </button>
                </form>

                <?php elseif ($step === 'done'): ?>
                <div class="text-center">
                    <div class="empty-state-icon" style="background: var(--success-bg); margin: 0 auto 20px;">
                        <i data-lucide="check-circle" style="color: var(--success);"></i>
                    </div>
                    <h1 class="login-title">Senha Alterada!</h1>
                    <p class="login-subtitle"><?= sanitize($success) ?></p>
                    <a href="<?= url('/login.php') ?>" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i data-lucide="log-in"></i> Fazer Login
                    </a>
                </div>
                <?php endif; ?>

                <div class="login-links mt-2">
                    <a href="<?= url('/login.php') ?>">← Voltar para o login</a>
                </div>
            </div>
        </section>

        <section class="login-image-section">
            <div class="login-image-content">
                <div class="login-image-badge">COMUNIDADE ONLINE</div>
                <blockquote class="login-image-quote">
                    "O Senhor é o meu pastor; nada me faltará."
                </blockquote>
                <p class="login-image-text">Salmo 23:1</p>
            </div>
        </section>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
