-- Adicionar campo quantidade à tabela inventory_transactions
ALTER TABLE inventory_transactions 
ADD COLUMN quantidade INT DEFAULT 1 AFTER tipo;
