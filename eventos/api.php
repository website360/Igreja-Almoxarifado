<?php
/**
 * API de Eventos
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

header('Content-Type: application/json');

$db = Database::getInstance();

// Ação via GET ou POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $action ?: ($input['action'] ?? '');

switch ($action) {
    case 'delete':
        requirePermission('eventos', 'delete');
        
        $id = intval($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $evento = $db->fetch("SELECT * FROM events WHERE id = ?", [$id]);
        if (!$evento) {
            jsonResponse(['success' => false, 'message' => 'Evento não encontrado'], 404);
        }

        // Verifica se há presenças
        $presencas = $db->fetch("SELECT COUNT(*) as total FROM attendance WHERE event_id = ?", [$id]);
        if ($presencas['total'] > 0) {
            jsonResponse(['success' => false, 'message' => 'Não é possível excluir evento com presenças registradas'], 400);
        }

        $db->delete('events', 'id = ?', [$id]);
        Audit::log('delete', 'events', $id, $evento);

        jsonResponse(['success' => true, 'message' => 'Evento excluído com sucesso']);
        break;

    case 'toggle_checkin':
        requirePermission('eventos', 'edit');
        
        $id = intval($input['id'] ?? 0);
        $status = $input['status'] ?? '';

        if (!$id || !in_array($status, ['aberto', 'fechado'])) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $evento = $db->fetch("SELECT * FROM events WHERE id = ?", [$id]);
        if (!$evento) {
            jsonResponse(['success' => false, 'message' => 'Evento não encontrado'], 404);
        }

        $db->update('events', ['status_checkin' => $status], 'id = :id', ['id' => $id]);
        Audit::log('toggle_checkin', 'events', $id, ['status' => $status]);

        $msg = $status === 'aberto' ? 'Check-in aberto' : 'Check-in fechado';
        jsonResponse(['success' => true, 'message' => $msg]);
        break;

    case 'update_status':
        requirePermission('eventos', 'edit');
        
        $id = intval($input['id'] ?? 0);
        $status = $input['status'] ?? '';

        if (!$id || !array_key_exists($status, EVENT_STATUS)) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $db->update('events', ['status' => $status], 'id = :id', ['id' => $id]);
        Audit::log('update_status', 'events', $id, ['status' => $status]);

        jsonResponse(['success' => true, 'message' => 'Status atualizado']);
        break;

    case 'list':
        requirePermission('eventos', 'view');
        
        $limit = min(100, intval($_GET['limit'] ?? 10));
        $offset = intval($_GET['offset'] ?? 0);
        $status = $_GET['status'] ?? '';
        $upcoming = isset($_GET['upcoming']);

        $where = ['1=1'];
        $params = [];

        if ($status) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if ($upcoming) {
            $where[] = 'inicio_at >= NOW()';
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $eventos = $db->fetchAll(
            "SELECT id, titulo, tipo, inicio_at, fim_at, local, status, status_checkin
             FROM events WHERE {$whereClause}
             ORDER BY inicio_at ASC
             LIMIT ? OFFSET ?",
            $params
        );

        jsonResponse(['success' => true, 'data' => $eventos]);
        break;

    case 'get':
        requirePermission('eventos', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $evento = $db->fetch("SELECT * FROM events WHERE id = ?", [$id]);
        if (!$evento) {
            jsonResponse(['success' => false, 'message' => 'Evento não encontrado'], 404);
        }

        jsonResponse(['success' => true, 'data' => $evento]);
        break;

    case 'view':
        requirePermission('eventos', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $evento = $db->fetch(
            "SELECT e.*, m.nome as ministerio_nome, u.nome as criado_por_nome
             FROM events e
             LEFT JOIN ministerios m ON e.ministerio_responsavel_id = m.id
             LEFT JOIN users u ON e.created_by = u.id
             WHERE e.id = ?",
            [$id]
        );
        
        if (!$evento) {
            jsonResponse(['success' => false, 'message' => 'Evento não encontrado'], 404);
        }

        jsonResponse(['success' => true, 'data' => $evento]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
}
