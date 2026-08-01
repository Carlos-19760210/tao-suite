-- ── Unidades de Medida (CRUD) — padroniza as unidades e alimenta a conversão ──
-- Fim do texto livre no cadastro do produto: unidade de compra/venda viram combo.
-- A conversão (tao_formula_conv_unid) passa a ler os fatores DAQUI.

CREATE TABLE IF NOT EXISTS unidades_medida (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id  uuid NOT NULL,
    sigla       text NOT NULL,                    -- G, ML, UN, KG, LT, MI...
    nome        text NOT NULL,                    -- Grama, Mililitro, Unidade...
    dimensao    text NOT NULL,                    -- 'massa' | 'volume' | 'contagem'
    fator_base  numeric NOT NULL DEFAULT 1,       -- fator p/ a base da dimensão (G=1, KG=1000, ML=1, LT=1000, UN=1, MI=1000)
    ativo       boolean NOT NULL DEFAULT true,
    criado_em   timestamptz NOT NULL DEFAULT now(),
    UNIQUE ( cliente_id, sigla )
);
CREATE INDEX IF NOT EXISTS idx_unidades_medida_cli ON unidades_medida ( cliente_id, ativo );
