-- =====================================================
-- MIGRATION: Atualizar Cargos do Sistema
-- =====================================================

-- Atualizar ENUM da coluna cargo com os novos cargos
ALTER TABLE users MODIFY COLUMN cargo ENUM(
    'bispo',
    'pastor',
    'missionaria',
    'obreiro',
    'candidato_obreiro',
    'colaborador',
    'gideao',
    'conselheiro_sja',
    'conselheiro_gta',
    'jovem',
    'adolescente',
    'tia_min_infantil'
) DEFAULT 'colaborador';
