-- TAO Fórmulas — Migration v1.2.0
-- Executar no Supabase SQL Editor (seguro para re-executar)
-- Pré-requisito: migration_v1.1.0.sql já aplicada

-- ── 1. Campos de cálculo em ativos ───────────────────────────────────────────
-- diluicao: fator de diluição do produto (ex: T3 1:1000 → 1000; puro → 1)
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS diluicao  numeric(12,4) NOT NULL DEFAULT 1.0;
-- teor: pureza em % (100 = puro; 98.5 = 98,5% de pureza)
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS teor      numeric(8,4)  NOT NULL DEFAULT 100.0;

-- ── 2. Tabela de tamanhos de cápsula (sincronizada do FC0H100) ─────────────
CREATE TABLE IF NOT EXISTS tipos_capsula (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id  uuid NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    tipo        text NOT NULL,          -- Gelatinosa, Vegetal, HPMC, Entérica…
    numero      text NOT NULL,          -- 000, 00, 0, 1, 2, 3, 4
    vol_ul      numeric(10,3) NOT NULL, -- volume interno em µL
    peso_vazio_mg numeric(10,4),        -- peso da cápsula vazia em mg
    cdpro_fc    text,                   -- CDPRO no Firebird (para rastreabilidade)
    ativo       boolean NOT NULL DEFAULT true,
    sincronizado_em timestamptz,
    UNIQUE (cliente_id, tipo, numero)
);

CREATE INDEX IF NOT EXISTS idx_tipos_capsula_cliente ON tipos_capsula(cliente_id);

-- ── 3. Campos de cápsula em formas_farmaceuticas ──────────────────────────
-- Tipo de cápsula (material): Gelatinosa, Vegetal, HPMC…
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS tipo_capsula   text;
-- Número (tamanho): 0, 1, 2, 3…
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS numero_capsula text;
-- Volume interno da cápsula em µL (copiado de tipos_capsula ao salvar)
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS vol_cap_ul     numeric(10,3);
-- Fator de enchimento (default 1.0 = 100%; use < 1 se a farmácia não enche 100%)
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS ftenchcap      numeric(6,4) NOT NULL DEFAULT 1.0;
