<?php
/**
 * API de Pessoas
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

requireAuth();

header('Content-Type: application/json');

$db = Database::getInstance();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $action ?: ($input['action'] ?? '');

// Debug log
error_log("API pessoas - Action: {$action}, Method: {$_SERVER['REQUEST_METHOD']}, POST: " . json_encode($_POST) . ", GET: " . json_encode($_GET));

switch ($action) {
    case 'delete':
        requirePermission('pessoas', 'delete');
        
        $id = intval($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        if ($id === $currentUser['id']) {
            jsonResponse(['success' => false, 'message' => 'Você não pode excluir a si mesmo'], 400);
        }

        $pessoa = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$pessoa) {
            jsonResponse(['success' => false, 'message' => 'Pessoa não encontrada'], 404);
        }

        // Verificar dependências
        $presencas = $db->fetch("SELECT COUNT(*) as total FROM attendance WHERE person_id = ?", [$id]);
        if ($presencas['total'] > 0) {
            // Anonimizar em vez de excluir
            $db->update('users', [
                'nome' => 'Usuário Removido',
                'email' => 'removed_' . $id . '@removed.local',
                'cpf' => null,
                'telefone_whatsapp' => null,
                'status' => 'inativo',
                'foto_url' => null
            ], 'id = :id', ['id' => $id]);
            
            Audit::log('anonymize', 'users', $id);
            jsonResponse(['success' => true, 'message' => 'Pessoa anonimizada (possui histórico)']);
        } else {
            $db->delete('user_roles', 'user_id = ?', [$id]);
            $db->delete('users', 'id = ?', [$id]);
            Audit::log('delete', 'users', $id);
            jsonResponse(['success' => true, 'message' => 'Pessoa excluída']);
        }
        break;

    case 'toggle_status':
        requirePermission('pessoas', 'edit');
        
        $id = intval($input['id'] ?? 0);
        $status = $input['status'] ?? '';

        if (!$id || !in_array($status, ['ativo', 'inativo'])) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
        }

        $db->update('users', ['status' => $status], 'id = :id', ['id' => $id]);
        Audit::log('toggle_status', 'users', $id, ['status' => $status]);

        jsonResponse(['success' => true, 'message' => 'Status atualizado']);
        break;

    case 'search':
        requirePermission('pessoas', 'view');
        
        $termo = $_GET['q'] ?? '';
        $limit = min(50, intval($_GET['limit'] ?? 20));

        if (strlen($termo) < 2) {
            jsonResponse(['success' => true, 'data' => []]);
        }

        $pessoas = $db->fetchAll(
            "SELECT id, nome, email, telefone_whatsapp, cargo, status
             FROM users
             WHERE status = 'ativo' AND (nome LIKE ? OR email LIKE ?)
             ORDER BY nome
             LIMIT ?",
            ["%{$termo}%", "%{$termo}%", $limit]
        );

        jsonResponse(['success' => true, 'data' => $pessoas]);
        break;

    case 'list':
        requirePermission('pessoas', 'view');
        
        $limit = min(100, intval($_GET['limit'] ?? 50));
        $offset = intval($_GET['offset'] ?? 0);
        $status = $_GET['status'] ?? 'ativo';

        $where = ['1=1'];
        $params = [];

        if ($status) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $pessoas = $db->fetchAll(
            "SELECT id, nome, email, telefone_whatsapp, cargo, status
             FROM users WHERE {$whereClause}
             ORDER BY nome
             LIMIT ? OFFSET ?",
            $params
        );

        jsonResponse(['success' => true, 'data' => $pessoas]);
        break;

    case 'export':
        requirePermission('pessoas', 'export');
        
        $pessoas = $db->fetchAll(
            "SELECT u.nome, u.email, u.telefone_whatsapp, u.cargo, 
                    m.nome as ministerio, u.status, u.data_entrada
             FROM users u
             LEFT JOIN ministerios m ON u.ministerio_id = m.id
             ORDER BY u.nome"
        );

        $headers = ['Nome', 'Email', 'Telefone', 'Cargo', 'Ministério', 'Status', 'Data Entrada'];
        exportToCsv($pessoas, 'pessoas_' . date('Y-m-d') . '.csv', $headers);
        break;

    case 'view':
        requirePermission('pessoas', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $pessoa = $db->fetch(
            "SELECT u.*, m.nome as ministerio_nome
             FROM users u
             LEFT JOIN ministerios m ON u.ministerio_id = m.id
             WHERE u.id = ?",
            [$id]
        );

        if (!$pessoa) {
            jsonResponse(['success' => false, 'message' => 'Pessoa não encontrada'], 404);
        }

        // Buscar estatísticas de presença
        $stats = $db->fetch(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
                COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
                COUNT(CASE WHEN status = 'justificado' THEN 1 END) as justificados
             FROM attendance WHERE person_id = ?",
            [$id]
        );

        // Buscar documentos
        $documentos = $db->fetchAll(
            "SELECT id, tipo_documento, descricao, arquivo_url, arquivo_nome, arquivo_tamanho, created_at
             FROM pessoa_documentos
             WHERE user_id = ?
             ORDER BY created_at DESC",
            [$id]
        );

        // Remover senha do retorno
        unset($pessoa['password']);

        jsonResponse(['success' => true, 'data' => [
            'pessoa' => $pessoa,
            'stats' => $stats,
            'documentos' => $documentos
        ]]);
        break;

    case 'frequencia':
        requirePermission('pessoas', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $frequencia = $db->fetchAll(
            "SELECT e.titulo, e.inicio_at, a.status, a.checkin_at
             FROM attendance a
             JOIN events e ON a.event_id = e.id
             WHERE a.person_id = ?
             ORDER BY e.inicio_at DESC
             LIMIT 50",
            [$id]
        );

        $stats = $db->fetch(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'presente' THEN 1 END) as presentes,
                COUNT(CASE WHEN status = 'ausente' THEN 1 END) as ausentes,
                COUNT(CASE WHEN status = 'justificado' THEN 1 END) as justificados
             FROM attendance WHERE person_id = ?",
            [$id]
        );

        jsonResponse(['success' => true, 'data' => [
            'historico' => $frequencia,
            'stats' => $stats
        ]]);
        break;

    case 'documentos_list':
        requirePermission('pessoas', 'view');
        
        $userId = intval($_GET['user_id'] ?? 0);
        if (!$userId) {
            jsonResponse(['success' => false, 'message' => 'ID do usuário não informado'], 400);
        }

        $documentos = $db->fetchAll(
            "SELECT pd.*, u.nome as uploaded_by_nome
             FROM pessoa_documentos pd
             LEFT JOIN users u ON pd.created_by = u.id
             WHERE pd.user_id = ?
             ORDER BY pd.created_at DESC",
            [$userId]
        );

        jsonResponse(['success' => true, 'data' => $documentos]);
        break;

    case 'documento_upload':
        requirePermission('pessoas', 'edit');
        
        $userId = intval($_POST['user_id'] ?? 0);
        $tipoDocumento = trim($_POST['tipo_documento'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if (!$userId) {
            jsonResponse(['success' => false, 'message' => 'ID do usuário não informado'], 400);
        }

        if (empty($tipoDocumento)) {
            jsonResponse(['success' => false, 'message' => 'Tipo de documento é obrigatório'], 400);
        }

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'Arquivo não enviado ou erro no upload'], 400);
        }

        $uploadError = null;
        $arquivoUrl = uploadFileWithError($_FILES['arquivo'], 'documentos', $uploadError);
        
        if (!$arquivoUrl) {
            jsonResponse(['success' => false, 'message' => $uploadError ?? 'Erro ao fazer upload'], 400);
        }

        $docId = $db->insert('pessoa_documentos', [
            'user_id' => $userId,
            'tipo_documento' => $tipoDocumento,
            'descricao' => $descricao,
            'arquivo_url' => $arquivoUrl,
            'arquivo_nome' => $_FILES['arquivo']['name'],
            'arquivo_tamanho' => $_FILES['arquivo']['size'],
            'created_by' => $currentUser['id']
        ]);

        Audit::log('create', 'pessoa_documentos', $docId, ['user_id' => $userId, 'tipo' => $tipoDocumento]);

        jsonResponse(['success' => true, 'message' => 'Documento anexado com sucesso', 'id' => $docId]);
        break;

    case 'documento_delete':
        requirePermission('pessoas', 'edit');
        
        $docId = intval($_POST['id'] ?? $_GET['id'] ?? $input['id'] ?? 0);
        if (!$docId) {
            jsonResponse(['success' => false, 'message' => 'ID do documento não informado', 'debug' => ['post' => $_POST, 'get' => $_GET]], 400);
        }

        $documento = $db->fetch("SELECT * FROM pessoa_documentos WHERE id = ?", [$docId]);
        if (!$documento) {
            jsonResponse(['success' => false, 'message' => 'Documento não encontrado', 'id_recebido' => $docId], 404);
        }

        // Deletar arquivo físico
        if ($documento['arquivo_url']) {
            deleteFile($documento['arquivo_url']);
        }

        $deleted = $db->delete('pessoa_documentos', 'id = ?', [$docId]);
        Audit::log('delete', 'pessoa_documentos', $docId);

        jsonResponse(['success' => true, 'message' => 'Documento excluído', 'deleted' => $deleted]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
}
