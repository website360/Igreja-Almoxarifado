<?php
/**
 * Configurações Gerais da Aplicação
 * Sistema de Gestão de Igreja
 */

// Configurações da aplicação
define('APP_NAME', 'Igreja Conectada');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://sistema.ibavivamentomundial.com.br');
define('APP_DEBUG', true);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de sessão
define('SESSION_LIFETIME', 7200); // 2 horas
define('SESSION_NAME', 'igreja_session');

// Configurações de upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Configurações de paginação
define('ITEMS_PER_PAGE', 15);

// Cores do tema
define('PRIMARY_COLOR', '#3B82F6');
define('SECONDARY_COLOR', '#1E40AF');

// Módulos do sistema
define('MODULES', [
    'dashboard' => [
        'name' => 'Dashboard',
        'icon' => 'home',
        'route' => '/dashboard'
    ],
    'pessoas' => [
        'name' => 'Pessoas',
        'icon' => 'users',
        'route' => '/pessoas'
    ],
    'almoxarifado' => [
        'name' => 'Almoxarifado',
        'icon' => 'package',
        'route' => '/almoxarifado'
    ],
    'eventos' => [
        'name' => 'Eventos',
        'icon' => 'calendar',
        'route' => '/eventos'
    ],
    'relatorios' => [
        'name' => 'Relatórios',
        'icon' => 'bar-chart-2',
        'route' => '/relatorios'
    ],
    'configuracoes' => [
        'name' => 'Configurações',
        'icon' => 'settings',
        'route' => '/configuracoes'
    ],
    'usuarios' => [
        'name' => 'Usuários & Permissões',
        'icon' => 'shield',
        'route' => '/usuarios'
    ]
]);

// Ações de permissão
define('PERMISSION_ACTIONS', [
    'view' => 'Visualizar',
    'create' => 'Criar',
    'edit' => 'Editar',
    'delete' => 'Excluir',
    'approve' => 'Aprovar',
    'export' => 'Exportar',
    'send_message' => 'Enviar Mensagem',
    'manage_settings' => 'Gerenciar Configurações'
]);

// Status de eventos
define('EVENT_STATUS', [
    'planejado' => 'Planejado',
    'em_andamento' => 'Em Andamento',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado'
]);

// Status de check-in
define('CHECKIN_STATUS', [
    'fechado' => 'Fechado',
    'aberto' => 'Aberto'
]);

// Status de presença
define('ATTENDANCE_STATUS', [
    'presente' => 'Presente',
    'ausente' => 'Ausente',
    'justificado' => 'Justificado'
]);

// Métodos de check-in
define('CHECKIN_METHODS', [
    'manual' => 'Manual',
    'qrcode' => 'QR Code',
    'secretaria' => 'Secretaria'
]);

// Status de justificativas
define('JUSTIFICATION_STATUS', [
    'pendente' => 'Pendente',
    'aprovada' => 'Aprovada',
    'recusada' => 'Recusada'
]);

// Cargos
define('MEMBER_POSITIONS', [
    'visitante' => 'Visitante',
    'membro' => 'Membro',
    'lider' => 'Líder',
    'pastor' => 'Pastor'
]);

// Tipos de evento
define('EVENT_TYPES', [
    'culto' => 'Culto',
    'reuniao' => 'Reunião',
    'treinamento' => 'Treinamento',
    'conferencia' => 'Conferência',
    'retiro' => 'Retiro',
    'ebd' => 'Escola Bíblica',
    'outro' => 'Outro'
]);
