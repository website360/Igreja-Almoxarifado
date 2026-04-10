<?php
/**
 * Evolution API Service
 * Gerencia instâncias WhatsApp via Evolution API
 */
class EvolutionAPI {
    
    private string $baseUrl;
    private string $apiKey;
    
    public function __construct() {
        // Buscar configurações do banco de dados
        $db = Database::getInstance();
        $urlSetting = $db->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'evolution_api_url'");
        $keySetting = $db->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'evolution_api_key'");
        
        $this->baseUrl = rtrim($urlSetting['setting_value'] ?? EVOLUTION_API_URL ?? '', '/');
        $this->apiKey = $keySetting['setting_value'] ?? EVOLUTION_API_KEY ?? '';
        
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new Exception('Evolution API não configurada. Configure em Configurações > API Evolution.');
        }
    }
    
    /**
     * Criar nova instância
     */
    public function createInstance(string $instanceName): array {
        return $this->request('POST', '/instance/create', [
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true
        ]);
    }
    
    /**
     * Obter QR Code para conexão
     */
    public function getQrCode(string $instanceName): array {
        return $this->request('GET', "/instance/connect/{$instanceName}");
    }
    
    /**
     * Verificar status da conexão
     */
    public function getConnectionStatus(string $instanceName): array {
        return $this->request('GET', "/instance/connectionState/{$instanceName}");
    }
    
    /**
     * Desconectar instância (logout)
     */
    public function logoutInstance(string $instanceName): array {
        return $this->request('DELETE', "/instance/logout/{$instanceName}");
    }
    
    /**
     * Deletar instância
     */
    public function deleteInstance(string $instanceName): array {
        return $this->request('DELETE', "/instance/delete/{$instanceName}");
    }
    
    /**
     * Enviar mensagem de texto
     */
    public function sendText(string $instanceName, string $phone, string $message): array {
        return $this->request('POST', "/message/sendText/{$instanceName}", [
            'number' => $phone,
            'text' => $message
        ]);
    }
    
    /**
     * Obter informações da instância
     */
    public function fetchInstance(string $instanceName): array {
        return $this->request('GET', "/instance/fetchInstances?instanceName={$instanceName}");
    }
    
    /**
     * Requisição HTTP genérica
     */
    private function request(string $method, string $endpoint, array $data = []): array {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey
        ];
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Erro de conexão: ' . $curlError,
                'http_code' => 0
            ];
        }
        
        $result = json_decode($response, true) ?? [];
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $result,
                'http_code' => $httpCode
            ];
        }
        
        $errorMsg = $result['response']['message'][0] 
            ?? $result['message'] 
            ?? $result['error'] 
            ?? "HTTP {$httpCode}";
            
        return [
            'success' => false,
            'error' => $errorMsg,
            'http_code' => $httpCode,
            'data' => $result
        ];
    }
}
