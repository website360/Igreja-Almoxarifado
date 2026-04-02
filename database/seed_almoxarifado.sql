-- Seed de Itens do Almoxarifado
-- Sistema Igreja 2026

USE sistemaigreja2026;

-- Criar categorias se não existirem
INSERT IGNORE INTO inventory_categories (id, nome, descricao) VALUES
(1, 'Equipamentos de Som', 'Microfones, caixas de som, mesas de som'),
(2, 'Equipamentos de Vídeo', 'Projetores, câmeras, telas'),
(3, 'Instrumentos Musicais', 'Violões, teclados, baterias'),
(4, 'Mobiliário', 'Mesas, cadeiras, púlpitos'),
(5, 'Material de Escritório', 'Papelaria, computadores'),
(6, 'Utensílios de Cozinha', 'Panelas, pratos, talheres'),
(7, 'Decoração', 'Arranjos, tapetes, cortinas'),
(8, 'Iluminação', 'Refletores, luminárias'),
(9, 'Ferramentas', 'Ferramentas diversas'),
(10, 'Material de Limpeza', 'Produtos e equipamentos de limpeza');

-- Inserir 50 itens do almoxarifado
INSERT INTO inventory_items (nome, categoria_id, patrimonio_codigo, descricao, status, localizacao, quantidade, valor_estimado, created_at) VALUES

-- Equipamentos de Som (1)
('Microfone Shure SM58', 1, 'PAT-001', 'Microfone dinâmico profissional para vocal', 'disponivel', 'Armário de Som - Prateleira 1', 4, 850.00, NOW()),
('Microfone sem Fio Duplo', 1, 'PAT-002', 'Sistema de microfone sem fio com 2 bastões', 'disponivel', 'Armário de Som - Prateleira 1', 2, 1200.00, NOW()),
('Mesa de Som Digital 16 Canais', 1, 'PAT-003', 'Mesa Behringer X32 Compact', 'disponivel', 'Sala de Som', 1, 8500.00, NOW()),
('Caixa de Som Ativa 15"', 1, 'PAT-004', 'Caixa JBL PRX815 1500W', 'disponivel', 'Depósito - Área A', 4, 3500.00, NOW()),
('Pedestal de Microfone', 1, 'PAT-005', 'Pedestal girafa com base tripé', 'disponivel', 'Armário de Som - Prateleira 2', 8, 120.00, NOW()),
('Cabo XLR 10m', 1, 'PAT-006', 'Cabo balanceado para microfone', 'disponivel', 'Armário de Som - Gaveta 1', 15, 45.00, NOW()),
('Direct Box Passivo', 1, 'PAT-007', 'DI Box para instrumentos', 'disponivel', 'Armário de Som - Prateleira 2', 4, 180.00, NOW()),
('Amplificador de Fone', 1, 'PAT-008', 'Amplificador 8 canais para retorno', 'disponivel', 'Sala de Som', 1, 650.00, NOW()),

-- Equipamentos de Vídeo (2)
('Projetor Epson 4000 Lumens', 2, 'PAT-009', 'Projetor Full HD para auditório', 'disponivel', 'Sala de Projeção', 2, 4500.00, NOW()),
('Tela de Projeção 3x2m', 2, 'PAT-010', 'Tela retrátil com tripé', 'disponivel', 'Depósito - Área B', 2, 800.00, NOW()),
('Câmera Sony Full HD', 2, 'PAT-011', 'Filmadora profissional para transmissões', 'emprestado', 'Em uso - Equipe de Mídia', 1, 3200.00, NOW()),
('Tripé para Câmera', 2, 'PAT-012', 'Tripé profissional com cabeça fluida', 'disponivel', 'Sala de Mídia', 2, 450.00, NOW()),
('Notebook Dell i7', 2, 'PAT-013', 'Notebook para apresentações e mídia', 'disponivel', 'Sala de Mídia', 2, 4800.00, NOW()),
('Switcher de Vídeo ATEM Mini', 2, 'PAT-014', 'Mesa de corte para transmissão ao vivo', 'disponivel', 'Sala de Mídia', 1, 2800.00, NOW()),

-- Instrumentos Musicais (3)
('Teclado Yamaha PSR-S970', 3, 'PAT-015', 'Teclado arranjador profissional', 'disponivel', 'Sala de Música', 1, 5500.00, NOW()),
('Violão Takamine', 3, 'PAT-016', 'Violão eletroacústico aço', 'disponivel', 'Sala de Música - Suporte 1', 2, 1800.00, NOW()),
('Guitarra Fender Stratocaster', 3, 'PAT-017', 'Guitarra elétrica com case', 'disponivel', 'Sala de Música - Suporte 2', 1, 3500.00, NOW()),
('Contrabaixo Fender Jazz', 3, 'PAT-018', 'Baixo elétrico 4 cordas', 'disponivel', 'Sala de Música - Suporte 3', 1, 2800.00, NOW()),
('Bateria Pearl Export', 3, 'PAT-019', 'Bateria acústica completa 5 peças', 'disponivel', 'Palco - Área de Bateria', 1, 4500.00, NOW()),
('Amplificador de Guitarra', 3, 'PAT-020', 'Cubo Marshall 100W', 'disponivel', 'Sala de Música', 1, 2200.00, NOW()),
('Amplificador de Baixo', 3, 'PAT-021', 'Cubo Hartke 250W', 'disponivel', 'Sala de Música', 1, 1800.00, NOW()),
('Cajón Percussion', 3, 'PAT-022', 'Cajón acústico profissional', 'disponivel', 'Sala de Música', 2, 350.00, NOW()),

-- Mobiliário (4)
('Cadeira Estofada', 4, 'PAT-023', 'Cadeira acolchoada para templo', 'disponivel', 'Templo Principal', 200, 150.00, NOW()),
('Mesa Retangular 2m', 4, 'PAT-024', 'Mesa dobrável para eventos', 'disponivel', 'Depósito - Área C', 15, 280.00, NOW()),
('Púlpito de Acrílico', 4, 'PAT-025', 'Púlpito transparente moderno', 'disponivel', 'Palco Principal', 1, 1200.00, NOW()),
('Mesa de Apoio', 4, 'PAT-026', 'Mesa pequena para santa ceia', 'disponivel', 'Depósito - Área C', 4, 180.00, NOW()),
('Cadeira de Escritório', 4, 'PAT-027', 'Cadeira giratória com rodízios', 'disponivel', 'Secretaria', 5, 450.00, NOW()),
('Armário de Aço 2 portas', 4, 'PAT-028', 'Armário para documentos', 'disponivel', 'Secretaria', 3, 650.00, NOW()),
('Quadro Branco 2x1m', 4, 'PAT-029', 'Quadro magnético para salas', 'disponivel', 'Sala de Aula 1', 3, 320.00, NOW()),

-- Material de Escritório (5)
('Impressora Multifuncional', 5, 'PAT-030', 'Impressora HP LaserJet Pro', 'disponivel', 'Secretaria', 1, 2800.00, NOW()),
('Computador Desktop', 5, 'PAT-031', 'PC i5 para secretaria', 'disponivel', 'Secretaria', 2, 3200.00, NOW()),
('Telefone PABX', 5, 'PAT-032', 'Central telefônica 8 ramais', 'disponivel', 'Secretaria', 1, 1500.00, NOW()),
('Calculadora de Mesa', 5, 'PAT-033', 'Calculadora com impressão', 'disponivel', 'Tesouraria', 2, 280.00, NOW()),

-- Utensílios de Cozinha (6)
('Fogão Industrial 6 Bocas', 6, 'PAT-034', 'Fogão em aço inox', 'disponivel', 'Cozinha', 1, 2200.00, NOW()),
('Geladeira Duplex', 6, 'PAT-035', 'Geladeira Consul 450L', 'disponivel', 'Cozinha', 1, 2800.00, NOW()),
('Freezer Horizontal', 6, 'PAT-036', 'Freezer 400L', 'disponivel', 'Cozinha', 1, 2400.00, NOW()),
('Panela Grande 50L', 6, 'PAT-037', 'Panela de alumínio para eventos', 'disponivel', 'Cozinha - Armário 2', 4, 180.00, NOW()),
('Jogo de Pratos (50 un)', 6, 'PAT-038', 'Pratos de porcelana branca', 'disponivel', 'Cozinha - Armário 1', 2, 350.00, NOW()),
('Jogo de Talheres (50 un)', 6, 'PAT-039', 'Talheres em inox', 'disponivel', 'Cozinha - Gaveta 1', 2, 280.00, NOW()),
('Cafeteira Industrial', 6, 'PAT-040', 'Cafeteira 10L', 'disponivel', 'Cozinha', 2, 450.00, NOW()),

-- Decoração (7)
('Tapete Vermelho 10m', 7, 'PAT-041', 'Passadeira para cerimônias', 'disponivel', 'Depósito - Área D', 2, 800.00, NOW()),
('Arranjo de Flores Artificial', 7, 'PAT-042', 'Arranjo grande para altar', 'disponivel', 'Depósito - Área D', 6, 250.00, NOW()),
('Cortina de Veludo 3m', 7, 'PAT-043', 'Cortina para palco', 'disponivel', 'Instalada no Palco', 2, 1200.00, NOW()),

-- Iluminação (8)
('Refletor LED PAR 64', 8, 'PAT-044', 'Refletor RGB para palco', 'disponivel', 'Armário de Iluminação', 8, 280.00, NOW()),
('Moving Head', 8, 'PAT-045', 'Iluminação móvel para eventos', 'manutencao', 'Em manutenção externa', 2, 1800.00, NOW()),
('Mesa de Luz DMX', 8, 'PAT-046', 'Controlador de iluminação 24 canais', 'disponivel', 'Sala de Som', 1, 1200.00, NOW()),

-- Ferramentas (9)
('Furadeira de Impacto', 9, 'PAT-047', 'Furadeira Bosch 750W', 'disponivel', 'Sala de Ferramentas', 1, 450.00, NOW()),
('Caixa de Ferramentas Completa', 9, 'PAT-048', 'Kit com 150 peças', 'disponivel', 'Sala de Ferramentas', 2, 380.00, NOW()),
('Escada de Alumínio 8 degraus', 9, 'PAT-049', 'Escada extensível', 'disponivel', 'Depósito - Área E', 2, 320.00, NOW()),

-- Material de Limpeza (10)
('Aspirador de Pó Industrial', 10, 'PAT-050', 'Aspirador 20L', 'disponivel', 'Almoxarifado de Limpeza', 2, 650.00, NOW());

-- Registrar algumas transações de exemplo
INSERT INTO inventory_transactions (item_id, tipo, retirado_por_person_id, responsavel_operacao_user_id, condition_notes, data_hora) 
SELECT 
    (SELECT id FROM inventory_items WHERE patrimonio_codigo = 'PAT-011'),
    'retirada',
    (SELECT id FROM users WHERE cargo = 'membro' LIMIT 1),
    (SELECT id FROM users WHERE cargo = 'admin' LIMIT 1),
    'Retirado para gravação do culto de domingo',
    DATE_SUB(NOW(), INTERVAL 2 DAY)
WHERE EXISTS (SELECT 1 FROM inventory_items WHERE patrimonio_codigo = 'PAT-011');
