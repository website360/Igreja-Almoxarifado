-- Migração: Adicionar tabela de motivos de justificativa
-- Data: 2026-03-27

-- Criar tabela de motivos de justificativa
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

-- Adicionar campo reason_id na tabela absence_justifications
ALTER TABLE absence_justifications 
ADD COLUMN reason_id INT AFTER person_id,
ADD FOREIGN KEY (reason_id) REFERENCES justification_reasons(id) ON DELETE SET NULL;

-- Inserir motivos padrão
INSERT INTO justification_reasons (nome, descricao, ordem) VALUES
('Doença', 'Problemas de saúde que impedem a participação', 1),
('Viagem', 'Viagem pessoal ou profissional', 2),
('Trabalho', 'Compromissos profissionais', 3),
('Família', 'Compromissos familiares', 4),
('Outro', 'Outros motivos não listados', 5);
