<?php
/**
 * API de Busca Global
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query muito curta']);
    exit;
}

$db = Database::getInstance();
$results = [];

// Buscar pessoas
if (can('pessoas', 'view')) {
    $pessoas = $db->fetchAll(
        "SELECT id, nome, email, telefone FROM users 
         WHERE (nome LIKE ? OR email LIKE ? OR telefone LIKE ?) 
         AND status = 'ativo'
         LIMIT 5",
        ["%$query%", "%$query%", "%$query%"]
    );
    
    foreach ($pessoas as $pessoa) {
        $results[] = [
            'type' => 'pessoa',
            'title' => $pessoa['nome'],
            'subtitle' => $pessoa['email'] ?: $pessoa['telefone'],
            'url' => url('/pessoas/ver.php?id=' . $pessoa['id'])
        ];
    }
}

// Buscar eventos
if (can('eventos', 'view')) {
    $eventos = $db->fetchAll(
        "SELECT id, titulo, tipo, DATE_FORMAT(inicio_at, '%d/%m/%Y %H:%i') as data_formatada 
         FROM events 
         WHERE titulo LIKE ? OR descricao LIKE ?
         ORDER BY inicio_at DESC
         LIMIT 5",
        ["%$query%", "%$query%"]
    );
    
    foreach ($eventos as $evento) {
        $results[] = [
            'type' => 'evento',
            'title' => $evento['titulo'],
            'subtitle' => ucfirst($evento['tipo']) . ' - ' . $evento['data_formatada'],
            'url' => url('/eventos/ver.php?id=' . $evento['id'])
        ];
    }
}

// Buscar produtos do almoxarifado
if (can('almoxarifado', 'view')) {
    $produtos = $db->fetchAll(
        "SELECT id, nome, codigo, quantidade FROM inventory_items 
         WHERE nome LIKE ? OR codigo LIKE ? OR descricao LIKE ?
         LIMIT 5",
        ["%$query%", "%$query%", "%$query%"]
    );
    
    foreach ($produtos as $produto) {
        $results[] = [
            'type' => 'produto',
            'title' => $produto['nome'],
            'subtitle' => 'Código: ' . $produto['codigo'] . ' | Qtd: ' . $produto['quantidade'],
            'url' => url('/almoxarifado/ver.php?id=' . $produto['id'])
        ];
    }
}

// Buscar ministérios
$ministerios = $db->fetchAll(
    "SELECT id, nome, descricao FROM ministerios 
     WHERE nome LIKE ? OR descricao LIKE ?
     LIMIT 3",
    ["%$query%", "%$query%"]
);

foreach ($ministerios as $ministerio) {
    $results[] = [
        'type' => 'ministerio',
        'title' => $ministerio['nome'],
        'subtitle' => $ministerio['descricao'] ?: 'Ministério',
        'url' => url('/ministerios/ver.php?id=' . $ministerio['id'])
    ];
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'total' => count($results)
]);
