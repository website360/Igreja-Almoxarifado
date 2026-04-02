<?php
/**
 * Logout
 */
define('BASE_PATH', __DIR__ . '/');
require_once BASE_PATH . 'includes/init.php';

$auth = new Auth();
$auth->logout();

redirect('/login.php');
