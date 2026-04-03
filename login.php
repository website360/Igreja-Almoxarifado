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
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; background: #f8fafc; }

        .login-container {
            display: flex;
            min-height: 100vh;
        }

        /* === LEFT SIDE - Image Panel === */
        .login-banner {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            min-height: 100vh;
        }

        .login-banner-bg {
            position: absolute;
            inset: 0;
            background: url('https://images.unsplash.com/photo-1438232992991-995b7058bbb3?w=1200&q=80') center/cover no-repeat;
            filter: brightness(0.5);
        }

        .login-banner-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(99, 47, 163, 0.85) 0%, rgba(59, 130, 246, 0.7) 50%, rgba(16, 185, 129, 0.5) 100%);
        }

        .login-banner-glow {
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            top: 20%;
            left: 10%;
            animation: glowPulse 6s ease-in-out infinite;
        }

        .login-banner-glow-2 {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139,92,246,0.3) 0%, transparent 70%);
            bottom: 10%;
            right: 5%;
            animation: glowPulse 8s ease-in-out infinite reverse;
        }

        @keyframes glowPulse {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.15); }
        }

        .login-banner-dots {
            position: absolute;
            top: 30px;
            right: 30px;
            display: grid;
            grid-template-columns: repeat(5, 8px);
            gap: 8px;
            opacity: 0.3;
        }
        .login-banner-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
        }

        .login-banner-content {
            position: relative;
            z-index: 2;
            color: white;
            padding: 48px;
            max-width: 520px;
            text-align: center;
        }

        .login-banner-content .logo-igreja {
            max-width: 320px;
            margin: 0 auto 32px;
        }

        .login-banner-content .logo-igreja img {
            width: 100%;
            height: auto;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
        }

        .login-banner-content h1 {
            font-size: 2.1rem;
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.2;
            letter-spacing: -0.5px;
            white-space: nowrap;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .login-banner-content .subtitle {
            font-size: 1.15rem;
            opacity: 0.9;
            margin-bottom: 32px;
            line-height: 1.7;
        }

        .login-banner-content blockquote {
            font-size: 1.05rem;
            font-style: italic;
            opacity: 0.95;
            line-height: 1.7;
            padding: 20px 24px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            border-left: 3px solid rgba(255,255,255,0.5);
            backdrop-filter: blur(5px);
            margin-bottom: 12px;
        }

        .login-banner-content .verse-ref {
            font-size: 0.85rem;
            opacity: 0.7;
            font-style: normal;
        }

        .login-banner-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 120px;
            opacity: 0.1;
        }

        /* === RIGHT SIDE - Form Panel === */
        .login-form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #fff;
            max-width: 520px;
        }

        .login-form-wrapper {
            width: 100%;
            max-width: 380px;
        }

        .login-form-wrapper h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .login-form-wrapper .form-subtitle {
            color: #9ca3af;
            font-size: 0.9rem;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #9ca3af;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background: #f9fafb;
            transition: all 0.2s;
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: #3B82F6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .input-wrapper input::placeholder {
            color: #c4c9d4;
        }

        .input-wrapper .toggle-pass {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            padding: 4px;
            display: flex;
        }
        .input-wrapper .toggle-pass:hover { color: #6b7280; }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 0.85rem;
        }

        .form-options label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
            cursor: pointer;
        }

        .form-options label input[type="checkbox"] {
            accent-color: #3B82F6;
            width: 16px;
            height: 16px;
        }

        .form-options a {
            color: #3B82F6;
            text-decoration: none;
            font-weight: 500;
        }
        .form-options a:hover { text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.35);
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #1D4ED8 0%, #1E40AF 100%);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45);
            transform: translateY(-1px);
        }

        .btn-login:active { transform: translateY(0); }

        .alert-error {
            padding: 12px 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            color: #dc2626;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success-msg {
            padding: 12px 16px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            color: #16a34a;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .login-footer {
            text-align: center;
            margin-top: 32px;
            font-size: 0.85rem;
            color: #9ca3af;
        }

        .login-footer a {
            color: #3B82F6;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover { text-decoration: underline; }

        /* === RESPONSIVE === */
        @media (max-width: 900px) {
            .login-container { flex-direction: column; }
            .login-banner { min-height: 280px; }
            .login-banner-content { padding: 32px 24px; }
            .login-banner-content h1 { font-size: 1.6rem; }
            .login-banner-content blockquote { font-size: 0.9rem; padding: 14px 18px; }
            .login-form-panel { max-width: 100%; padding: 32px 24px; }
        }

        @media (max-width: 480px) {
            .login-banner-content h1 { font-size: 1.35rem; }
            .login-banner-content .subtitle { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- LEFT: Image Banner -->
        <div class="login-banner">
            <div class="login-banner-bg"></div>
            <div class="login-banner-gradient"></div>
            <div class="login-banner-glow"></div>
            <div class="login-banner-glow-2"></div>
            <div class="login-banner-dots">
                <span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span>
            </div>

            <div class="login-banner-content">
                <div class="logo-igreja">
                    <img src="<?= url('/assets/img/logo-igreja.png') ?>" alt="Igreja Batista Avivamento Mundial">
                </div>
                <h1>Bem-vindo de volta!</h1>
                <p class="subtitle">Acesse sua conta para se conectar com a comunidade e acompanhar os eventos.</p>

            </div>

            <svg class="login-banner-wave" viewBox="0 0 1440 120" preserveAspectRatio="none">
                <path d="M0,60 C360,120 720,0 1080,60 C1260,90 1380,60 1440,40 L1440,120 L0,120 Z" fill="white"/>
            </svg>
        </div>

        <!-- RIGHT: Form -->
        <div class="login-form-panel">
            <div class="login-form-wrapper">
                <h2>Entrar</h2>
                <p class="form-subtitle">Informe suas credenciais para acessar o sistema.</p>

                <?php if ($error): ?>
                <div class="alert-error">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
                    <span><?= sanitize($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert-success-msg">
                    <i data-lucide="check-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
                    <span><?= sanitize($success) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <div class="input-wrapper">
                            <i data-lucide="mail"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="exemplo@email.com"
                                value="<?= sanitize($_POST['email'] ?? '') ?>"
                                required 
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Senha</label>
                        <div class="input-wrapper">
                            <i data-lucide="lock"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Digite sua senha"
                                required
                            >
                            <button type="button" class="toggle-pass" id="togglePassword">
                                <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label>
                            <input type="checkbox" name="remember" id="remember">
                            Lembrar-me
                        </label>
                        <a href="<?= url('/recuperar-senha.php') ?>">Esqueci a senha</a>
                    </div>

                    <button type="submit" class="btn-login">Entrar</button>
                </form>

                <div class="login-footer">
                    <p>Desenvolvido por <a href="https://www.agenciamay.com.br" target="_blank" style="font-weight: 600; color: #9ca3af; text-decoration: none;">Agência May</a> 💛</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

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
