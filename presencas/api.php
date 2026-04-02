<?php
/**
 * API de Presenças
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
    case 'delete':
        requirePermission('presencas', 'delete');
        
        $id = intval($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $presenca = $db->fetch("SELECT * FROM attendance WHERE id = ?", [$id]);
        if (!$presenca) {
            jsonResponse(['success' => false, 'message' => 'Registro não encontrado'], 404);
        }

        $db->delete('attendance', 'id = ?', [$id]);
        Audit::log('delete', 'attendance', $id, $presenca);

        jsonResponse(['success' => true, 'message' => 'Registro excluído']);
        break;

    case 'update':
        requirePermission('presencas', 'edit');
        
        $id = intval($input['id'] ?? 0);
        $status = $input['status'] ?? '';
        $notes = $input['notes'] ?? '';

        if (!$id || !array_key_exists($status, ATTENDANCE_STATUS)) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $presenca = $db->fetch("SELECT * FROM attendance WHERE id = ?", [$id]);
        if (!$presenca) {
            jsonResponse(['success' => false, 'message' => 'Registro não encontrado'], 404);
        }

        $db->update('attendance', [
            'status' => $status,
            'notes' => $notes,
            'marked_by' => $currentUser['id']
        ], 'id = :id', ['id' => $id]);

        Audit::log('update', 'attendance', $id, [
            'old_status' => $presenca['status'],
            'new_status' => $status
        ]);

        jsonResponse(['success' => true, 'message' => 'Presença atualizada']);
        break;

    case 'checkin':
        requirePermission('presencas', 'create');
        
        $eventId = intval($input['event_id'] ?? 0);
        $personId = intval($input['person_id'] ?? $currentUser['id']);
        $method = $input['method'] ?? 'manual';

        if ($personId !== $currentUser['id'] && !can('presencas', 'checkin_others')) {
            jsonResponse(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $evento = $db->fetch(
            "SELECT * FROM events WHERE id = ? AND status_checkin = 'aberto'",
            [$eventId]
        );

        if (!$evento) {
            jsonResponse(['success' => false, 'message' => 'Evento não encontrado ou check-in fechado'], 400);
        }

        $existente = $db->fetch(
            "SELECT * FROM attendance WHERE event_id = ? AND person_id = ?",
            [$eventId, $personId]
        );

        if ($existente) {
            $db->update('attendance', [
                'status' => 'presente',
                'checkin_at' => date('Y-m-d H:i:s'),
                'checkin_method' => $method,
                'marked_by' => $currentUser['id']
            ], 'id = :id', ['id' => $existente['id']]);
        } else {
            $db->insert('attendance', [
                'event_id' => $eventId,
                'person_id' => $personId,
                'status' => 'presente',
                'checkin_method' => $method,
                'checkin_at' => date('Y-m-d H:i:s'),
                'marked_by' => $currentUser['id']
            ]);
        }

        Audit::log('checkin', 'attendance', null, [
            'event_id' => $eventId,
            'person_id' => $personId
        ]);

        jsonResponse(['success' => true, 'message' => 'Check-in realizado']);
        break;

    case 'checkin_massa':
        requirePermission('presencas', 'checkin_others');
        
        $eventId = intval($input['event_id'] ?? 0);
        $personIds = $input['person_ids'] ?? [];

        if (!$eventId || empty($personIds)) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $evento = $db->fetch("SELECT * FROM events WHERE id = ?", [$eventId]);
        if (!$evento) {
            jsonResponse(['success' => false, 'message' => 'Evento não encontrado'], 404);
        }

        $count = 0;
        foreach ($personIds as $personId) {
            $personId = intval($personId);
            $existente = $db->fetch(
                "SELECT * FROM attendance WHERE event_id = ? AND person_id = ?",
                [$eventId, $personId]
            );

            if ($existente) {
                $db->update('attendance', [
                    'status' => 'presente',
                    'checkin_at' => date('Y-m-d H:i:s'),
                    'checkin_method' => 'secretaria',
                    'marked_by' => $currentUser['id']
                ], 'id = :id', ['id' => $existente['id']]);
            } else {
                $db->insert('attendance', [
                    'event_id' => $eventId,
                    'person_id' => $personId,
                    'status' => 'presente',
                    'checkin_method' => 'secretaria',
                    'checkin_at' => date('Y-m-d H:i:s'),
                    'marked_by' => $currentUser['id']
                ]);
            }
            $count++;
        }

        Audit::log('checkin_massa', 'attendance', null, [
            'event_id' => $eventId,
            'count' => $count
        ]);

        jsonResponse(['success' => true, 'message' => "{$count} check-in(s) realizado(s)"]);
        break;

    case 'pessoas_sem_checkin':
        requirePermission('presencas', 'checkin_others');
        
        $eventId = intval($_GET['event_id'] ?? 0);
        if (!$eventId) {
            jsonResponse(['success' => false, 'message' => 'Evento não informado'], 400);
        }

        $pessoas = $db->fetchAll(
            "SELECT u.id, u.nome, u.email 
             FROM users u
             WHERE u.status = 'ativo'
             AND u.id NOT IN (
                SELECT person_id FROM attendance 
                WHERE event_id = ? AND status = 'presente'
             )
             ORDER BY u.nome
             LIMIT 100",
            [$eventId]
        );

        jsonResponse(['success' => true, 'data' => $pessoas]);
        break;

    case 'stats':
        requirePermission('presencas', 'view');
        
        $eventId = intval($_GET['event_id'] ?? 0);
        
        if ($eventId) {
            $stats = $db->fetch(
                "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
                    COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
                    COUNT(CASE WHEN status = 'justificado' THEN 1 END) as justificados
                 FROM attendance WHERE event_id = ?",
                [$eventId]
            );
        } else {
            $stats = $db->fetch(
                "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
                    COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
                    COUNT(CASE WHEN status = 'justificado' THEN 1 END) as justificados
                 FROM attendance a
                 JOIN events e ON a.event_id = e.id
                 WHERE MONTH(e.inicio_at) = MONTH(NOW())"
            );
        }

        jsonResponse(['success' => true, 'data' => $stats]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
}
