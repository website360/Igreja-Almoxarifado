<?php
/**
 * Página Inicial - Redireciona para login ou dashboard
 */
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'includes/init.php';

$auth = new Auth();

if ($auth->check()) {
    redirect('/dashboard/index.php');
} else {
    redirect('/login.php');
}
