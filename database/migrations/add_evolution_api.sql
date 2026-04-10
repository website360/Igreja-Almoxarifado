-- =====================================================
-- MIGRATION: Suporte a Evolution API
-- =====================================================

-- Adicionar campo para nome da instância Evolution
ALTER TABLE whatsapp_integrations 
    ADD COLUMN instance_name VARCHAR(100) DEFAULT NULL AFTER instance_id,
    ADD COLUMN connection_status VARCHAR(50) DEFAULT 'disconnected' AFTER webhook_secret,
    ADD COLUMN phone_connected VARCHAR(20) DEFAULT NULL AFTER connection_status;

-- Atualizar provider para suportar ambos
ALTER TABLE whatsapp_integrations 
    MODIFY COLUMN provider VARCHAR(50) DEFAULT 'evolution';
