<?php
/**
 * API de Integrações
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
    case 'save_whatsapp':
        requirePermission('integracoes', 'manage_settings');
        
        $data = [
            'provider' => 'zapi',
            'instance_id' => trim($_POST['instance_id'] ?? $input['instance_id'] ?? ''),
            'token' => trim($_POST['token'] ?? $input['token'] ?? ''),
            'api_key' => trim($_POST['api_key'] ?? $input['api_key'] ?? ''),
            'ativo' => isset($_POST['ativo']) || isset($input['ativo']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $existing = $db->fetch("SELECT id FROM whatsapp_integrations LIMIT 1");
        
        if ($existing) {
            $db->update('whatsapp_integrations', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->insert('whatsapp_integrations', $data);
        }

        Audit::log('settings_updated', 'whatsapp_integrations', null);

        if (isAjaxRequest()) {
            jsonResponse(['success' => true, 'message' => 'Configurações salvas']);
        } else {
            setFlash('success', 'Configurações do WhatsApp salvas!');
            redirect('/integracoes');
        }
        break;

    case 'test_whatsapp':
        requirePermission('integracoes', 'send_message');
        
        $phone = cleanPhone($input['phone'] ?? '');
        if (!$phone) {
            jsonResponse(['success' => false, 'message' => 'Telefone não informado'], 400);
        }

        $config = $db->fetch("SELECT * FROM whatsapp_integrations WHERE ativo = 1 LIMIT 1");
        if (!$config) {
            jsonResponse(['success' => false, 'message' => 'WhatsApp não configurado ou inativo'], 400);
        }

        // Validar configurações
        if (empty($config['instance_id']) || empty($config['token'])) {
            jsonResponse([
                'success' => false, 
                'message' => 'Configuração incompleta',
                'error' => 'Instance ID ou Token não configurados'
            ], 400);
        }

        // Enviar via Z-API
        $phone = formatPhoneWhatsApp($phone);
        $message = $input['message'] ?? "🔔 Teste do Sistema Igreja Conectada\n\nSe você recebeu esta mensagem, a integração está funcionando corretamente!";

        $result = sendWhatsAppMessage($config, $phone, $message);

        if ($result['success']) {
            jsonResponse([
                'success' => true, 
                'message' => 'Mensagem enviada com sucesso',
                'message_id' => $result['message_id'] ?? null,
                'phone' => $phone
            ]);
        } else {
            jsonResponse([
                'success' => false, 
                'message' => $result['error'] ?? 'Erro ao enviar mensagem',
                'error' => $result['error'] ?? 'Erro desconhecido',
                'http_code' => $result['http_code'] ?? null,
                'phone' => $phone
            ]);
        }
        break;

    case 'create_template':
    case 'update_template':
        requirePermission('integracoes', 'manage_settings');
        
        $id = intval($input['id'] ?? 0);
        $data = [
            'nome' => trim($input['nome'] ?? ''),
            'mensagem' => trim($input['mensagem'] ?? ''),
            'ativo' => ($input['ativo'] ?? 0) ? 1 : 0
        ];

        if (empty($data['nome']) || empty($data['mensagem'])) {
            jsonResponse(['success' => false, 'message' => 'Nome e mensagem são obrigatórios'], 400);
        }

        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $db->update('whatsapp_templates', $data, 'id = :id', ['id' => $id]);
            Audit::log('update', 'whatsapp_templates', $id);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $db->insert('whatsapp_templates', $data);
            Audit::log('create', 'whatsapp_templates', $id);
        }

        jsonResponse(['success' => true, 'message' => 'Template salvo', 'id' => $id]);
        break;

    case 'get_template':
        requirePermission('integracoes', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        $template = $db->fetch("SELECT * FROM whatsapp_templates WHERE id = ?", [$id]);
        
        if (!$template) {
            jsonResponse(['success' => false, 'message' => 'Template não encontrado'], 404);
        }

        jsonResponse(['success' => true, 'data' => $template]);
        break;

    case 'delete_template':
        requirePermission('integracoes', 'manage_settings');
        
        $id = intval($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'ID não informado'], 400);
        }

        $db->delete('whatsapp_templates', 'id = ?', [$id]);
        Audit::log('delete', 'whatsapp_templates', $id);

        jsonResponse(['success' => true, 'message' => 'Template excluído']);
        break;

    case 'send_message':
        requirePermission('integracoes', 'send_message');
        
        $personId = intval($input['person_id'] ?? 0);
        $eventId = intval($input['event_id'] ?? 0);
        $templateId = intval($input['template_id'] ?? 0);
        $customMessage = $input['message'] ?? '';

        $person = $db->fetch("SELECT * FROM users WHERE id = ?", [$personId]);
        if (!$person || !$person['telefone_whatsapp']) {
            jsonResponse(['success' => false, 'message' => 'Pessoa não encontrada ou sem telefone'], 400);
        }

        if (!$person['aceita_whatsapp']) {
            jsonResponse(['success' => false, 'message' => 'Pessoa não aceita mensagens WhatsApp'], 400);
        }

        $message = $customMessage;
        if ($templateId) {
            $template = $db->fetch("SELECT * FROM whatsapp_templates WHERE id = ?", [$templateId]);
            if ($template) {
                $message = $template['mensagem'];
                // Substituir variáveis
                $message = str_replace('{{nome}}', $person['nome'], $message);
                if ($eventId) {
                    $event = $db->fetch("SELECT * FROM events WHERE id = ?", [$eventId]);
                    if ($event) {
                        $message = str_replace('{{evento}}', $event['titulo'], $message);
                        $message = str_replace('{{data}}', formatDate($event['inicio_at']), $message);
                        $message = str_replace('{{hora}}', date('H:i', strtotime($event['inicio_at'])), $message);
                    }
                }
            }
        }

        // Adicionar à fila
        $db->insert('message_queue', [
            'provider' => 'zapi',
            'event_id' => $eventId ?: null,
            'person_id' => $personId,
            'phone' => formatPhoneWhatsApp($person['telefone_whatsapp']),
            'template_id' => $templateId ?: null,
            'message_content' => $message,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Audit::log('message_queued', 'message_queue', null, [
            'person_id' => $personId,
            'event_id' => $eventId
        ]);

        jsonResponse(['success' => true, 'message' => 'Mensagem adicionada à fila']);
        break;

    case 'retry_message':
        requirePermission('integracoes', 'send_message');
        
        $id = intval($input['id'] ?? 0);
        $msg = $db->fetch("SELECT * FROM message_queue WHERE id = ?", [$id]);
        
        if (!$msg) {
            jsonResponse(['success' => false, 'message' => 'Mensagem não encontrada'], 404);
        }

        $db->update('message_queue', [
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null
        ], 'id = :id', ['id' => $id]);

        jsonResponse(['success' => true, 'message' => 'Mensagem reenfileirada']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
}

/**
 * Envia mensagem via Z-API
 */
function sendWhatsAppMessage(array $config, string $phone, string $message): array {
    $url = "https://api.z-api.io/instances/{$config['instance_id']}/token/{$config['token']}/send-text";
    
    $data = [
        'phone' => $phone,
        'message' => $message
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Client-Token: ' . $config['api_key']
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'Erro de conexão: ' . $curlError];
    }

    if (!$response) {
        return ['success' => false, 'error' => 'Resposta vazia da API'];
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true, 
            'data' => $result,
            'message_id' => $result['messageId'] ?? null
        ];
    }

    // Erro da API
    $errorMsg = 'Erro ao enviar mensagem';
    if (isset($result['message'])) {
        $errorMsg = $result['message'];
    } elseif (isset($result['error'])) {
        $errorMsg = $result['error'];
    } elseif ($httpCode) {
        $errorMsg = "HTTP {$httpCode}: " . ($response ?: 'Sem resposta');
    }

    return [
        'success' => false, 
        'error' => $errorMsg,
        'http_code' => $httpCode,
        'response' => $result
    ];
}
