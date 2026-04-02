<?php
/**
 * Redirecionamento para edição de evento
 * Este arquivo existe apenas para compatibilidade com links antigos
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

$id = intval($_GET['id'] ?? 0);
if ($id) {
    header("Location: " . url("/eventos/criar.php?id={$id}"));
} else {
    header("Location: " . url("/eventos"));
}
exit;
