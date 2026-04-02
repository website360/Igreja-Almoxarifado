-- Migração: Criar tabela pessoa_documentos
-- Data: 2026-01-19

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
