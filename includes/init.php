<?php
/**
 * Arquivo de Inicialização do Sistema
 * Carrega todas as dependências e inicia a sessão
 */

// Prevenir acesso direto
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}

// Carregar configurações
require_once BASE_PATH . 'config/app.php';
require_once BASE_PATH . 'config/database.php';

// Carregar helpers
require_once BASE_PATH . 'includes/helpers.php';
require_once BASE_PATH . 'includes/auth.php';
require_once BASE_PATH . 'includes/permissions.php';
require_once BASE_PATH . 'includes/audit.php';
require_once BASE_PATH . 'includes/EvolutionAPI.php';

// Configurar exibição de erros
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    session_name(SESSION_NAME);
    session_start();
}

// Verificar expiração de sessão
if (isset($_SESSION['last_activity'])) {
    $inactive = time() - $_SESSION['last_activity'];
    if ($inactive >= SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        if (!isAjaxRequest()) {
            redirect('/login.php?expired=1');
        }
    }
}
$_SESSION['last_activity'] = time();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Carregar usuário atual se logado
$currentUser = null;
$userPermissions = [];
$currentUserUnidade = null;

if (isset($_SESSION['user_id'])) {
    $auth = new Auth();
    $currentUser = $auth->getCurrentUser();
    if ($currentUser) {
        $permissions = new Permissions();
        $userPermissions = $permissions->getUserPermissions($currentUser['id']);
        
        // Carregar unidade do usuário
        if (!empty($currentUser['unidade_id'])) {
            $db = Database::getInstance();
            $currentUserUnidade = $db->fetch("SELECT nome FROM unidades WHERE id = ?", [$currentUser['unidade_id']]);
        }
    }
}
