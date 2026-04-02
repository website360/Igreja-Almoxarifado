<?php
/**
 * Script para povoar fotos de pessoas e itens do almoxarifado
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'config/database.php';

$db = Database::getInstance();

echo "=== Povoando fotos do sistema ===\n\n";

// Criar diretórios se não existirem
$dirPessoas = BASE_PATH . 'uploads/pessoas/';
$dirAlmox = BASE_PATH . 'uploads/almoxarifado/';

if (!is_dir($dirPessoas)) mkdir($dirPessoas, 0755, true);
if (!is_dir($dirAlmox)) mkdir($dirAlmox, 0755, true);

// Fotos para pessoas (usando ui-avatars.com)
echo "Baixando fotos para pessoas...\n";
$pessoas = $db->fetchAll("SELECT id, nome FROM users WHERE foto_url IS NULL OR foto_url = ''");

foreach ($pessoas as $pessoa) {
    $nome = urlencode($pessoa['nome']);
    $cor = substr(md5($pessoa['nome']), 0, 6);
    $avatarUrl = "https://ui-avatars.com/api/?name={$nome}&size=200&background={$cor}&color=fff&bold=true";
    
    $imageData = @file_get_contents($avatarUrl);
    if ($imageData) {
        $filename = 'pessoa_' . $pessoa['id'] . '_' . time() . '.png';
        $filepath = $dirPessoas . $filename;
        
        if (file_put_contents($filepath, $imageData)) {
            $fotoUrl = '/uploads/pessoas/' . $filename;
            $db->update('users', ['foto_url' => $fotoUrl], 'id = :id', ['id' => $pessoa['id']]);
            echo "  ✓ {$pessoa['nome']}\n";
        }
    } else {
        echo "  ✗ Erro ao baixar foto para {$pessoa['nome']}\n";
    }
    usleep(100000); // 100ms delay
}

// Fotos para itens do almoxarifado (usando picsum.photos)
echo "\nBaixando fotos para itens do almoxarifado...\n";
$itens = $db->fetchAll("SELECT id, nome FROM inventory_items WHERE foto_capa_url IS NULL OR foto_capa_url = ''");

$itemImages = [
    'projetor' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=300&h=300&fit=crop',
    'microfone' => 'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?w=300&h=300&fit=crop',
    'cadeira' => 'https://images.unsplash.com/photo-1503602642458-232111445657?w=300&h=300&fit=crop',
    'mesa' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=300&h=300&fit=crop',
    'notebook' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=300&fit=crop',
    'caixa' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=300&h=300&fit=crop',
    'violao' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?w=300&h=300&fit=crop',
    'teclado' => 'https://images.unsplash.com/photo-1520523839897-bd0b52f945a0?w=300&h=300&fit=crop',
    'bateria' => 'https://images.unsplash.com/photo-1519892300165-cb5542fb47c7?w=300&h=300&fit=crop',
    'default' => 'https://picsum.photos/300/300'
];

foreach ($itens as $item) {
    $nomeLower = strtolower($item['nome']);
    $imageUrl = $itemImages['default'];
    
    foreach ($itemImages as $key => $url) {
        if (strpos($nomeLower, $key) !== false) {
            $imageUrl = $url;
            break;
        }
    }
    
    // Usar picsum com seed baseado no ID para consistência
    $imageUrl = "https://picsum.photos/seed/{$item['id']}/300/300";
    
    $imageData = @file_get_contents($imageUrl);
    if ($imageData) {
        $filename = 'item_' . $item['id'] . '_' . time() . '.jpg';
        $filepath = $dirAlmox . $filename;
        
        if (file_put_contents($filepath, $imageData)) {
            $fotoUrl = '/uploads/almoxarifado/' . $filename;
            $db->update('inventory_items', ['foto_capa_url' => $fotoUrl], 'id = :id', ['id' => $item['id']]);
            echo "  ✓ {$item['nome']}\n";
        }
    } else {
        echo "  ✗ Erro ao baixar foto para {$item['nome']}\n";
    }
    usleep(200000); // 200ms delay
}

echo "\n=== Concluído! ===\n";
