-- TAO Fórmulas — Migration v1.1.0
-- Executar no Supabase SQL Editor (seguro para re-executar)

-- 1. Coluna grupo (M=matéria-prima, E=embalagem, D=diluente, O=outros, R=rótulo)
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS grupo text NOT NULL DEFAULT 'M';

-- 2. Amplia constraint unidade_padrao para aceitar 'un' (unidade de contagem — embalagens)
ALTER TABLE ativos DROP CONSTRAINT IF EXISTS ativos_unidade_padrao_check;
ALTER TABLE ativos ADD CONSTRAINT ativos_unidade_padrao_check
    CHECK (unidade_padrao IN ('mg', 'g', 'mcg', 'UI', 'un'));

-- 3. Índice para busca rápida por grupo
CREATE INDEX IF NOT EXISTS idx_ativos_grupo ON ativos(cliente_id, grupo);
