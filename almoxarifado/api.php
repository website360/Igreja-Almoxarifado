<?php
/**
 * API do Almoxarifado
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

header('Content-Type: application/json');

$db = Database::getInstance();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $action ?: ($input['action'] ?? '');

switch ($action) {
    case 'retirada':
        requirePermission('almoxarifado', 'manage_transactions');
        
        $itemId = intval($input['item_id'] ?? 0);
        $personId = intval($input['person_id'] ?? 0);
        $quantidade = max(1, intval($input['quantidade'] ?? 1));
        $notes = $input['notes'] ?? '';
        $devolverAte = $input['devolver_ate'] ?? null;
        $eventoId = intval($input['evento_id'] ?? 0) ?: null;

        if (!$itemId || !$personId) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $item = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$itemId]);
        if (!$item) {
            jsonResponse(['success' => false, 'message' => 'Item não encontrado'], 404);
        }

        if ($item['status'] !== 'disponivel') {
            jsonResponse(['success' => false, 'message' => 'Item não está disponível'], 400);
        }

        if ($quantidade > $item['quantidade']) {
            jsonResponse(['success' => false, 'message' => 'Quantidade solicitada maior que disponível (' . $item['quantidade'] . ')'], 400);
        }

        // Registrar transação
        $db->insert('inventory_transactions', [
            'item_id' => $itemId,
            'tipo' => 'retirada',
            'quantidade' => $quantidade,
            'retirado_por_person_id' => $personId,
            'responsavel_operacao_user_id' => $currentUser['id'],
            'condition_notes' => $notes,
            'data_hora' => date('Y-m-d H:i:s'),
            'devolver_ate' => $devolverAte ?: null,
            'evento_id' => $eventoId
        ]);

        // Atualizar quantidade do item
        $novaQtd = $item['quantidade'] - $quantidade;
        $novoStatus = $novaQtd <= 0 ? 'emprestado' : 'disponivel';
        $db->update('inventory_items', [
            'quantidade' => max(0, $novaQtd),
            'status' => $novoStatus
        ], 'id = :id', ['id' => $itemId]);

        Audit::log('item_borrowed', 'inventory_items', $itemId, [
            'person_id' => $personId,
            'quantidade' => $quantidade,
            'devolver_ate' => $devolverAte
        ]);

        jsonResponse(['success' => true, 'message' => $quantidade . ' unidade(s) retirada(s) com sucesso']);
        break;

    case 'devolucao':
        requirePermission('almoxarifado', 'manage_transactions');
        
        $itemId = intval($input['item_id'] ?? 0);
        $estado = $input['estado'] ?? 'ok';
        $notes = $input['notes'] ?? '';
        $fotos = $input['fotos'] ?? [];

        if (!$itemId) {
            jsonResponse(['success' => false, 'message' => 'ID do item não informado'], 400);
        }

        $item = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$itemId]);
        if (!$item) {
            jsonResponse(['success' => false, 'message' => 'Item não encontrado'], 404);
        }

        // Buscar última retirada para obter quantidade
        $ultimaRetirada = $db->fetch(
            "SELECT * FROM inventory_transactions WHERE item_id = ? AND tipo = 'retirada' ORDER BY id DESC LIMIT 1",
            [$itemId]
        );
        
        $qtdDevolvida = $ultimaRetirada['quantidade'] ?? 1;

        // Salvar fotos de avaria se houver
        $fotosUrls = [];
        if (!empty($fotos) && $estado !== 'ok') {
            $uploadDir = BASE_PATH . 'uploads/almoxarifado/avarias/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            foreach ($fotos as $fotoBase64) {
                if (preg_match('/^data:image\/(jpeg|png|gif);base64,/', $fotoBase64, $matches)) {
                    $ext = $matches[1];
                    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $fotoBase64);
                    $imageData = base64_decode($base64Data);
                    
                    if ($imageData) {
                        $filename = generateCode() . '.' . $ext;
                        if (file_put_contents($uploadDir . $filename, $imageData)) {
                            $fotosUrls[] = '/uploads/almoxarifado/avarias/' . $filename;
                        }
                    }
                }
            }
        }

        // Registrar devolução
        $transactionData = [
            'item_id' => $itemId,
            'tipo' => 'devolucao',
            'quantidade' => $qtdDevolvida,
            'retirado_por_person_id' => $ultimaRetirada['retirado_por_person_id'] ?? null,
            'responsavel_operacao_user_id' => $currentUser['id'],
            'condition_notes' => $notes,
            'data_hora' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($fotosUrls)) {
            $transactionData['foto_estado_url'] = json_encode($fotosUrls);
        }
        
        $db->insert('inventory_transactions', $transactionData);

        // Atualizar quantidade e status do item
        $novaQtd = $item['quantidade'] + $qtdDevolvida;
        $novoStatus = 'disponivel';
        
        // Se houve avaria grave ou inutilizado, muda status
        if ($estado === 'inutilizado') {
            $novoStatus = 'baixado';
        } elseif ($estado === 'avaria_grave') {
            $novoStatus = 'manutencao';
        }
        
        $db->update('inventory_items', [
            'quantidade' => $novaQtd,
            'status' => $novoStatus
        ], 'id = :id', ['id' => $itemId]);

        Audit::log('item_returned', 'inventory_items', $itemId, [
            'estado' => $estado,
            'quantidade' => $qtdDevolvida,
            'fotos' => $fotosUrls
        ]);

        $msg = 'Item devolvido com sucesso';
        if ($estado === 'inutilizado') {
            $msg = 'Item devolvido e marcado como BAIXADO';
        } elseif ($estado === 'avaria_grave') {
            $msg = 'Item devolvido e enviado para MANUTENÇÃO';
        }

        jsonResponse(['success' => true, 'message' => $msg]);
        break;

    case 'update_transaction':
        requirePermission('almoxarifado', 'manage_transactions');
        
        $id = intval($input['id'] ?? 0);
        $quantidade = intval($input['quantidade'] ?? 1);
        $devolverAte = $input['devolver_ate'] ?? null;
        $notes = $input['notes'] ?? '';

        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $transaction = $db->fetch("SELECT * FROM inventory_transactions WHERE id = ?", [$id]);
        if (!$transaction) {
            jsonResponse(['success' => false, 'message' => 'Movimentação não encontrada'], 404);
        }

        $db->update('inventory_transactions', [
            'quantidade' => $quantidade,
            'devolver_ate' => $devolverAte ?: null,
            'condition_notes' => $notes
        ], 'id = :id', ['id' => $id]);

        Audit::log('update', 'inventory_transactions', $id);

        jsonResponse(['success' => true, 'message' => 'Movimentação atualizada']);
        break;

    case 'delete':
        requirePermission('almoxarifado', 'delete');
        
        $id = intval($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $item = $db->fetch("SELECT * FROM inventory_items WHERE id = ?", [$id]);
        if (!$item) {
            jsonResponse(['success' => false, 'message' => 'Item não encontrado'], 404);
        }

        if ($item['status'] === 'emprestado') {
            jsonResponse(['success' => false, 'message' => 'Não é possível excluir item emprestado'], 400);
        }

        $db->delete('inventory_transactions', 'item_id = ?', [$id]);
        $db->delete('inventory_items', 'id = ?', [$id]);
        
        Audit::log('delete', 'inventory_items', $id);

        jsonResponse(['success' => true, 'message' => 'Item excluído']);
        break;

    case 'historico':
        requirePermission('almoxarifado', 'view');
        
        $itemId = intval($_GET['item_id'] ?? 0);
        if (!$itemId) {
            jsonResponse(['success' => false, 'message' => 'Item não informado'], 400);
        }

        $historico = $db->fetchAll(
            "SELECT t.*, u.nome as pessoa_nome, r.nome as responsavel_nome
             FROM inventory_transactions t
             LEFT JOIN users u ON t.retirado_por_person_id = u.id
             LEFT JOIN users r ON t.responsavel_operacao_user_id = r.id
             WHERE t.item_id = ?
             ORDER BY t.data_hora DESC",
            [$itemId]
        );

        jsonResponse(['success' => true, 'data' => $historico]);
        break;

    case 'emprestados':
        requirePermission('almoxarifado', 'view');
        
        $itens = $db->fetchAll(
            "SELECT i.*, c.nome as categoria_nome,
                    (SELECT u.nome FROM inventory_transactions t 
                     JOIN users u ON t.retirado_por_person_id = u.id 
                     WHERE t.item_id = i.id AND t.tipo = 'retirada' 
                     ORDER BY t.id DESC LIMIT 1) as retirado_por,
                    (SELECT t.devolver_ate FROM inventory_transactions t 
                     WHERE t.item_id = i.id AND t.tipo = 'retirada' 
                     ORDER BY t.id DESC LIMIT 1) as devolver_ate
             FROM inventory_items i
             LEFT JOIN inventory_categories c ON i.categoria_id = c.id
             WHERE i.status = 'emprestado'
             ORDER BY i.nome"
        );

        jsonResponse(['success' => true, 'data' => $itens]);
        break;

    case 'view':
        requirePermission('almoxarifado', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $item = $db->fetch(
            "SELECT i.*, c.nome as categoria_nome
             FROM inventory_items i
             LEFT JOIN inventory_categories c ON i.categoria_id = c.id
             WHERE i.id = ?",
            [$id]
        );

        if (!$item) {
            jsonResponse(['success' => false, 'message' => 'Item não encontrado'], 404);
        }

        // Buscar última transação se emprestado
        if ($item['status'] === 'emprestado') {
            $ultimaRetirada = $db->fetch(
                "SELECT t.*, u.nome as pessoa_nome, t.devolver_ate
                 FROM inventory_transactions t
                 LEFT JOIN users u ON t.retirado_por_person_id = u.id
                 WHERE t.item_id = ? AND t.tipo = 'retirada'
                 ORDER BY t.id DESC LIMIT 1",
                [$id]
            );
            $item['emprestado_para'] = $ultimaRetirada['pessoa_nome'] ?? null;
            $item['devolver_ate'] = $ultimaRetirada['devolver_ate'] ?? null;
        }

        // Contar transações
        $stats = $db->fetch(
            "SELECT COUNT(*) as total_movimentacoes FROM inventory_transactions WHERE item_id = ?",
            [$id]
        );
        $item['total_movimentacoes'] = $stats['total_movimentacoes'];

        jsonResponse(['success' => true, 'data' => $item]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
}
