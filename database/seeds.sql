-- =====================================================
-- SISTEMA DE GESTÃO DE IGREJA - SEEDS INICIAIS
-- =====================================================

USE sistemaigreja2026;

-- =====================================================
-- ROLES PADRÃO
-- =====================================================
INSERT INTO roles (name, display_name, description, is_system) VALUES
('admin', 'Administrador', 'Acesso total ao sistema, configurações e automações', 1),
('lider', 'Líder', 'Gerencia eventos, aprova justificativas, visualiza relatórios', 1),
('secretaria', 'Secretaria', 'Cadastra pessoas, faz check-in, gera relatórios', 1),
('membro', 'Membro', 'Visualiza eventos, faz check-in próprio, justifica ausências', 1);

-- =====================================================
-- PERMISSÕES
-- =====================================================
INSERT INTO permissions (module, action, description) VALUES
-- Dashboard
('dashboard', 'view', 'Visualizar dashboard'),
-- Eventos
('eventos', 'view', 'Visualizar eventos'),
('eventos', 'create', 'Criar eventos'),
('eventos', 'edit', 'Editar eventos'),
('eventos', 'delete', 'Excluir eventos'),
('eventos', 'export', 'Exportar eventos'),
-- Presenças
('presencas', 'view', 'Visualizar presenças'),
('presencas', 'create', 'Registrar presença'),
('presencas', 'edit', 'Editar presenças'),
('presencas', 'delete', 'Excluir presenças'),
('presencas', 'checkin_others', 'Fazer check-in de outros'),
-- Justificativas
('justificativas', 'view', 'Visualizar justificativas'),
('justificativas', 'create', 'Criar justificativas'),
('justificativas', 'approve', 'Aprovar/Recusar justificativas'),
-- Pessoas
('pessoas', 'view', 'Visualizar pessoas'),
('pessoas', 'create', 'Cadastrar pessoas'),
('pessoas', 'edit', 'Editar pessoas'),
('pessoas', 'delete', 'Excluir pessoas'),
('pessoas', 'view_cpf', 'Visualizar CPF'),
('pessoas', 'export', 'Exportar pessoas'),
-- Almoxarifado
('almoxarifado', 'view', 'Visualizar itens'),
('almoxarifado', 'create', 'Cadastrar itens'),
('almoxarifado', 'edit', 'Editar itens'),
('almoxarifado', 'delete', 'Excluir itens'),
('almoxarifado', 'manage_transactions', 'Gerenciar retiradas/devoluções'),
-- Relatórios
('relatorios', 'view', 'Visualizar relatórios'),
('relatorios', 'export', 'Exportar relatórios'),
-- Integrações
('integracoes', 'view', 'Visualizar integrações'),
('integracoes', 'manage_settings', 'Gerenciar integrações'),
('integracoes', 'send_message', 'Enviar mensagens'),
-- Configurações
('configuracoes', 'view', 'Visualizar configurações'),
('configuracoes', 'manage_settings', 'Alterar configurações'),
-- Usuários e Permissões
('usuarios', 'view', 'Visualizar usuários'),
('usuarios', 'create', 'Criar usuários'),
('usuarios', 'edit', 'Editar usuários'),
('usuarios', 'delete', 'Excluir usuários'),
('usuarios', 'manage_roles', 'Gerenciar papéis'),
('usuarios', 'manage_permissions', 'Gerenciar permissões');

-- =====================================================
-- PERMISSÕES POR PAPEL - ADMIN (Todas)
-- =====================================================
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- =====================================================
-- PERMISSÕES POR PAPEL - LÍDER
-- =====================================================
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE 
    (module = 'dashboard' AND action = 'view') OR
    (module = 'eventos') OR
    (module = 'presencas') OR
    (module = 'justificativas') OR
    (module = 'pessoas' AND action IN ('view', 'create', 'edit', 'view_cpf', 'export')) OR
    (module = 'almoxarifado') OR
    (module = 'relatorios') OR
    (module = 'usuarios' AND action = 'view');

-- =====================================================
-- PERMISSÕES POR PAPEL - SECRETARIA
-- =====================================================
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE 
    (module = 'dashboard' AND action = 'view') OR
    (module = 'eventos' AND action IN ('view', 'export')) OR
    (module = 'presencas' AND action IN ('view', 'create', 'edit', 'checkin_others')) OR
    (module = 'justificativas' AND action = 'view') OR
    (module = 'pessoas' AND action IN ('view', 'create', 'edit', 'export')) OR
    (module = 'almoxarifado' AND action IN ('view', 'create', 'edit', 'manage_transactions')) OR
    (module = 'relatorios' AND action IN ('view', 'export'));

-- =====================================================
-- PERMISSÕES POR PAPEL - MEMBRO
-- =====================================================
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE 
    (module = 'dashboard' AND action = 'view') OR
    (module = 'eventos' AND action = 'view') OR
    (module = 'presencas' AND action IN ('view', 'create')) OR
    (module = 'justificativas' AND action IN ('view', 'create'));

-- =====================================================
-- MINISTÉRIOS PADRÃO
-- =====================================================
INSERT INTO ministerios (nome, descricao) VALUES
('Louvor', 'Ministério de louvor e adoração'),
('Infantil', 'Ministério infantil e escola dominical'),
('Jovens', 'Ministério de jovens e adolescentes'),
('Mulheres', 'Ministério de mulheres'),
('Homens', 'Ministério de homens'),
('Casais', 'Ministério de casais'),
('Missões', 'Ministério de missões'),
('Intercessão', 'Ministério de intercessão'),
('Diaconia', 'Ministério de diaconia e assistência social'),
('Comunicação', 'Ministério de comunicação e mídias');

-- =====================================================
-- USUÁRIO ADMIN PADRÃO
-- Senha: admin123 (hash bcrypt)
-- =====================================================
INSERT INTO users (email, password, nome, cargo, status, data_entrada) VALUES
('admin@igreja.com', '$2y$10$MwgRCwzGr74NzNZ2SA8h6uJfHcQnEXw61NmZTDhaVMXgmE0yoaW8O', 'Administrador do Sistema', 'pastor', 'ativo', CURDATE());

INSERT INTO user_roles (user_id, role_id) VALUES (1, 1);

-- =====================================================
-- TEMPLATES DE WHATSAPP PADRÃO
-- =====================================================
INSERT INTO whatsapp_templates (nome, mensagem) VALUES
('Lembrete de Presença', 'Olá {{nome}}! 👋\n\nNotamos que você ainda não fez seu check-in no evento "{{evento}}" que está acontecendo agora.\n\nData: {{data}}\nHorário: {{hora}}\n\nSentimos sua falta! Se não puder comparecer, não esqueça de justificar sua ausência.\n\n🙏 Deus abençoe!'),
('Justificativa Aprovada', 'Olá {{nome}}! ✅\n\nSua justificativa de ausência para o evento "{{evento}}" foi aprovada.\n\nObrigado por nos informar!'),
('Justificativa Recusada', 'Olá {{nome}}! ❌\n\nSua justificativa de ausência para o evento "{{evento}}" foi recusada.\n\nMotivo: {{motivo_recusa}}\n\nEm caso de dúvidas, procure a secretaria da igreja.');

-- =====================================================
-- CONFIGURAÇÕES PADRÃO
-- =====================================================
INSERT INTO app_settings (setting_key, setting_value, setting_type, description) VALUES
('checkin_abre_offset_min', '0', 'int', 'Minutos antes do início para abrir check-in'),
('checkin_fecha_offset_min', '120', 'int', 'Minutos após o início para fechar check-in'),
('tolerancia_whatsapp_min', '15', 'int', 'Minutos de tolerância antes de enviar WhatsApp'),
('sessao_timeout_min', '120', 'int', 'Tempo de inatividade para expirar sessão'),
('igreja_nome', 'Igreja Conectada', 'string', 'Nome da igreja'),
('igreja_logo_url', '', 'string', 'URL do logo da igreja'),
('smtp_host', '', 'string', 'Host do servidor SMTP'),
('smtp_port', '587', 'int', 'Porta do servidor SMTP'),
('smtp_user', '', 'string', 'Usuário SMTP'),
('smtp_pass', '', 'string', 'Senha SMTP'),
('smtp_from_email', '', 'string', 'Email de envio'),
('smtp_from_name', '', 'string', 'Nome de envio'),
('whatsapp_enabled', '0', 'bool', 'Automação WhatsApp habilitada'),
('email_enabled', '0', 'bool', 'Notificações por email habilitadas');

-- =====================================================
-- CATEGORIAS DO ALMOXARIFADO
-- =====================================================
INSERT INTO inventory_categories (nome, descricao) VALUES
('Equipamentos de Som', 'Microfones, caixas de som, mesas de som, etc'),
('Instrumentos Musicais', 'Violões, guitarras, teclados, bateria, etc'),
('Móveis', 'Cadeiras, mesas, púlpitos, etc'),
('Equipamentos de Vídeo', 'Projetores, TVs, câmeras, etc'),
('Material de Escritório', 'Papéis, canetas, grampeadores, etc'),
('Material de Limpeza', 'Produtos e equipamentos de limpeza'),
('Decoração', 'Itens decorativos para eventos'),
('Outros', 'Outros itens');
