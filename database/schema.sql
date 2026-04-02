-- =====================================================
-- SISTEMA DE GESTÃO DE IGREJA - SCHEMA DO BANCO
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS sistemaigreja2026 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE sistemaigreja2026;

-- =====================================================
-- TABELA: ministerios
-- =====================================================
CREATE TABLE IF NOT EXISTS ministerios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: roles (Papéis)
-- =====================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: permissions (Permissões)
-- =====================================================
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_module_action (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: role_permissions (Permissões por papel)
-- =====================================================
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: users (Usuários do sistema)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    telefone_whatsapp VARCHAR(20),
    cpf VARCHAR(14),
    foto_url VARCHAR(500),
    ministerio_id INT,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    cargo ENUM('membro', 'lider', 'pastor', 'diacono', 'visitante', 'outro') DEFAULT 'membro',
    data_entrada DATE,
    data_nascimento DATE,
    endereco TEXT,
    aceita_whatsapp TINYINT(1) DEFAULT 1,
    aceita_email TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ministerio_id) REFERENCES ministerios(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_cargo (cargo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: user_roles (Papéis do usuário)
-- =====================================================
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_role (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: user_permission_overrides (Overrides por usuário)
-- =====================================================
CREATE TABLE IF NOT EXISTS user_permission_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_permission (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: whatsapp_templates (Templates de mensagem)
-- =====================================================
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    mensagem TEXT NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: events (Eventos)
-- =====================================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('culto', 'reuniao', 'treinamento', 'conferencia', 'retiro', 'ebd', 'outro') DEFAULT 'culto',
    descricao TEXT,
    local VARCHAR(255),
    inicio_at DATETIME NOT NULL,
    fim_at DATETIME,
    ministerio_responsavel_id INT,
    status ENUM('planejado', 'em_andamento', 'concluido', 'cancelado') DEFAULT 'planejado',
    status_checkin ENUM('fechado', 'aberto') DEFAULT 'fechado',
    checkin_abre_at DATETIME,
    checkin_fecha_at DATETIME,
    tolerancia_minutos INT DEFAULT 15,
    whatsapp_template_id INT,
    automation_enabled TINYINT(1) DEFAULT 0,
    imagem_url VARCHAR(500),
    destaque TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ministerio_responsavel_id) REFERENCES ministerios(id) ON DELETE SET NULL,
    FOREIGN KEY (whatsapp_template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_inicio (inicio_at),
    INDEX idx_status (status),
    INDEX idx_status_checkin (status_checkin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: attendance (Presenças)
-- =====================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    person_id INT NOT NULL,
    status ENUM('presente', 'ausente', 'justificado') DEFAULT 'presente',
    checkin_method ENUM('manual', 'qrcode', 'secretaria') DEFAULT 'manual',
    checkin_at DATETIME,
    marked_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_person (event_id, person_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (person_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event (event_id),
    INDEX idx_person (person_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: justification_reasons (Motivos de Justificativa)
-- =====================================================
CREATE TABLE IF NOT EXISTS justification_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    ativo TINYINT(1) DEFAULT 1,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: absence_justifications (Justificativas)
-- =====================================================
CREATE TABLE IF NOT EXISTS absence_justifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    person_id INT NOT NULL,
    reason_id INT,
    motivo TEXT NOT NULL,
    anexo_url VARCHAR(500),
    status ENUM('pendente', 'aprovada', 'recusada') DEFAULT 'pendente',
    reviewed_by INT,
    reviewed_at DATETIME,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (person_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reason_id) REFERENCES justification_reasons(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event (event_id),
    INDEX idx_person (person_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: whatsapp_integrations (Integrações WhatsApp)
-- =====================================================
CREATE TABLE IF NOT EXISTS whatsapp_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) DEFAULT 'zapi',
    instance_id VARCHAR(255),
    device_id VARCHAR(255),
    token VARCHAR(500),
    api_key VARCHAR(500),
    webhook_secret VARCHAR(255),
    ativo TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: message_queue (Fila de mensagens)
-- =====================================================
CREATE TABLE IF NOT EXISTS message_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) DEFAULT 'zapi',
    event_id INT,
    person_id INT,
    phone VARCHAR(20) NOT NULL,
    template_id INT,
    payload_json JSON,
    message_content TEXT,
    status ENUM('pending', 'sent', 'failed', 'retrying', 'delivered', 'read') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_error TEXT,
    sent_at DATETIME,
    delivered_at DATETIME,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (person_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_person (person_id),
    INDEX idx_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: inventory_categories (Categorias do almoxarifado)
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: inventory_items (Itens do almoxarifado)
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    categoria_id INT,
    patrimonio_codigo VARCHAR(50) UNIQUE,
    descricao TEXT,
    status ENUM('disponivel', 'emprestado', 'manutencao', 'baixado') DEFAULT 'disponivel',
    localizacao VARCHAR(255),
    foto_capa_url VARCHAR(500),
    quantidade INT DEFAULT 1,
    valor_estimado DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES inventory_categories(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_patrimonio (patrimonio_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: inventory_transactions (Movimentações)
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    tipo ENUM('retirada', 'devolucao') NOT NULL,
    retirado_por_person_id INT,
    responsavel_operacao_user_id INT,
    condition_notes TEXT,
    foto_estado_url VARCHAR(500),
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    devolver_ate DATETIME,
    evento_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (retirado_por_person_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (responsavel_operacao_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (evento_id) REFERENCES events(id) ON DELETE SET NULL,
    INDEX idx_item (item_id),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: app_settings (Configurações do sistema)
-- =====================================================
CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: audit_logs (Logs de auditoria)
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_actor (actor_user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: pessoa_documentos (Documentos de pessoas)
-- =====================================================
CREATE TABLE IF NOT EXISTS pessoa_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tipo_documento VARCHAR(100) NOT NULL,
    descricao VARCHAR(255),
    arquivo_url VARCHAR(500) NOT NULL,
    arquivo_nome VARCHAR(255) NOT NULL,
    arquivo_tamanho INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_tipo (tipo_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: sessions (Sessões de usuário)
-- =====================================================
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
