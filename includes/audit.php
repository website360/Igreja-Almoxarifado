<?php
/**
 * Sistema de Auditoria
 */

class Audit {
    /**
     * Registra log de auditoria
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $metadata = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $db = Database::getInstance();
            
            $db->insert('audit_logs', [
                'actor_user_id' => $_SESSION['user_id'] ?? null,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'metadata_json' => $metadata ? json_encode($metadata) : null
            ]);
        } catch (Exception $e) {
            // Silently fail - não deve interromper operação principal
            if (APP_DEBUG) {
                error_log("Audit log error: " . $e->getMessage());
            }
        }
    }

    /**
     * Busca logs de auditoria
     */
    public static function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array {
        $db = Database::getInstance();
        
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'al.actor_user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'al.entity_type = ?';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $where[] = 'al.entity_id = ?';
            $params[] = $filters['entity_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.created_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        return $db->fetchAll(
            "SELECT al.*, u.nome as actor_name, u.email as actor_email
             FROM audit_logs al
             LEFT JOIN users u ON al.actor_user_id = u.id
             WHERE {$whereClause}
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Conta logs de auditoria
     */
    public static function countLogs(array $filters = []): int {
        $db = Database::getInstance();
        
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'actor_user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        
        $result = $db->fetch(
            "SELECT COUNT(*) as total FROM audit_logs WHERE {$whereClause}",
            $params
        );

        return $result['total'] ?? 0;
    }

    /**
     * Retorna ações disponíveis para filtro
     */
    public static function getActions(): array {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action");
    }

    /**
     * Retorna tipos de entidade para filtro
     */
    public static function getEntityTypes(): array {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type");
    }

    /**
     * Traduz ação para exibição
     */
    public static function translateAction(string $action): string {
        $translations = [
            'login' => 'Login',
            'logout' => 'Logout',
            'register' => 'Registro',
            'password_changed' => 'Senha alterada',
            'password_reset' => 'Senha redefinida',
            'role_assigned' => 'Papel atribuído',
            'role_removed' => 'Papel removido',
            'role_created' => 'Papel criado',
            'role_updated' => 'Papel atualizado',
            'role_deleted' => 'Papel excluído',
            'role_permissions_synced' => 'Permissões sincronizadas',
            'permission_override' => 'Override de permissão',
            'create' => 'Criação',
            'update' => 'Atualização',
            'delete' => 'Exclusão',
            'checkin' => 'Check-in',
            'checkin_manual' => 'Check-in manual',
            'justification_created' => 'Justificativa criada',
            'justification_approved' => 'Justificativa aprovada',
            'justification_rejected' => 'Justificativa recusada',
            'message_sent' => 'Mensagem enviada',
            'message_failed' => 'Falha no envio',
            'item_borrowed' => 'Item emprestado',
            'item_returned' => 'Item devolvido',
            'settings_updated' => 'Configurações atualizadas',
            'import' => 'Importação',
            'export' => 'Exportação'
        ];

        return $translations[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Traduz tipo de entidade para exibição
     */
    public static function translateEntityType(string $type): string {
        $translations = [
            'users' => 'Usuários',
            'user_roles' => 'Papéis do Usuário',
            'roles' => 'Papéis',
            'permissions' => 'Permissões',
            'events' => 'Eventos',
            'attendance' => 'Presenças',
            'absence_justifications' => 'Justificativas',
            'ministerios' => 'Ministérios',
            'inventory_items' => 'Itens do Almoxarifado',
            'inventory_transactions' => 'Movimentações',
            'whatsapp_templates' => 'Templates WhatsApp',
            'whatsapp_integrations' => 'Integrações WhatsApp',
            'message_queue' => 'Fila de Mensagens',
            'app_settings' => 'Configurações'
        ];

        return $translations[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Limpa logs antigos
     */
    public static function cleanOldLogs(int $daysToKeep = 90): int {
        $db = Database::getInstance();
        $cutoff = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        
        return $db->delete('audit_logs', 'DATE(created_at) < ?', [$cutoff]);
    }

    /**
     * Exporta logs para array
     */
    public static function exportLogs(array $filters = []): array {
        $db = Database::getInstance();
        
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.created_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        return $db->fetchAll(
            "SELECT 
                al.created_at as 'Data/Hora',
                u.nome as 'Usuário',
                al.action as 'Ação',
                al.entity_type as 'Entidade',
                al.entity_id as 'ID',
                al.ip_address as 'IP'
             FROM audit_logs al
             LEFT JOIN users u ON al.actor_user_id = u.id
             WHERE {$whereClause}
             ORDER BY al.created_at DESC",
            $params
        );
    }
}
