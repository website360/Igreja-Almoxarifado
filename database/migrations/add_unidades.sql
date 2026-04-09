-- =====================================================
-- MIGRATION: Adicionar Sistema de Unidades
-- =====================================================

-- Criar tabela de unidades
CREATE TABLE IF NOT EXISTS unidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    endereco TEXT,
    responsavel_id INT,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (responsavel_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar coluna unidade_id na tabela users
ALTER TABLE users ADD COLUMN unidade_id INT NULL AFTER ministerio_id;
ALTER TABLE users ADD FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE SET NULL;
ALTER TABLE users ADD INDEX idx_unidade (unidade_id);
