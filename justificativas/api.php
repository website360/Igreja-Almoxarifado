<?php
/**
 * API de Justificativas
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
    case 'get':
        requirePermission('justificativas', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $j = $db->fetch(
            "SELECT j.*, e.titulo as evento_titulo, e.inicio_at as evento_data,
                    u.nome as pessoa_nome
             FROM absence_justifications j
             JOIN events e ON j.event_id = e.id
             JOIN users u ON j.person_id = u.id
             WHERE j.id = ?",
            [$id]
        );

        if (!$j) {
            jsonResponse(['success' => false, 'message' => 'Justificativa não encontrada'], 404);
        }

        $j['evento_data'] = formatDate($j['evento_data']);
        $j['status_badge'] = statusBadge($j['status']);

        jsonResponse(['success' => true, 'data' => $j]);
        break;

    case 'delete':
        requirePermission('justificativas', 'approve');
        
        $id = intval($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $j = $db->fetch("SELECT * FROM absence_justifications WHERE id = ?", [$id]);
        if (!$j) {
            jsonResponse(['success' => false, 'message' => 'Justificativa não encontrada'], 404);
        }

        $db->delete('absence_justifications', 'id = ?', [$id]);
        Audit::log('delete', 'absence_justifications', $id);

        jsonResponse(['success' => true, 'message' => 'Justificativa excluída']);
        break;

    case 'aprovar':
    case 'recusar':
        requirePermission('justificativas', 'approve');
        
        $id = intval($input['id'] ?? 0);
        $reviewNotes = $input['review_notes'] ?? '';

        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $j = $db->fetch("SELECT * FROM absence_justifications WHERE id = ?", [$id]);
        if (!$j) {
            jsonResponse(['success' => false, 'message' => 'Justificativa não encontrada'], 404);
        }

        $novoStatus = $action === 'aprovar' ? 'aprovada' : 'recusada';

        $db->update('absence_justifications', [
            'status' => $novoStatus,
            'reviewed_by' => $currentUser['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $reviewNotes
        ], 'id = :id', ['id' => $id]);

        if ($novoStatus === 'aprovada') {
            $presenca = $db->fetch(
                "SELECT * FROM attendance WHERE event_id = ? AND person_id = ?",
                [$j['event_id'], $j['person_id']]
            );

            if ($presenca) {
                $db->update('attendance', ['status' => 'justificado'], 'id = :id', ['id' => $presenca['id']]);
            } else {
                $db->insert('attendance', [
                    'event_id' => $j['event_id'],
                    'person_id' => $j['person_id'],
                    'status' => 'justificado',
                    'checkin_method' => 'secretaria',
                    'marked_by' => $currentUser['id']
                ]);
            }
        }

        Audit::log('justification_' . ($novoStatus === 'aprovada' ? 'approved' : 'rejected'), 
                   'absence_justifications', $id);

        jsonResponse(['success' => true, 'message' => 'Justificativa ' . $novoStatus]);
        break;

    case 'pendentes':
        requirePermission('justificativas', 'approve');
        
        $pendentes = $db->fetchAll(
            "SELECT j.id, j.motivo, j.created_at,
                    e.titulo as evento_titulo,
                    u.nome as pessoa_nome
             FROM absence_justifications j
             JOIN events e ON j.event_id = e.id
             JOIN users u ON j.person_id = u.id
             WHERE j.status = 'pendente'
             ORDER BY j.created_at ASC"
        );

        jsonResponse(['success' => true, 'data' => $pendentes]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
}
