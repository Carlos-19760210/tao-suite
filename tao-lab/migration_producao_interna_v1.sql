-- ============================================================================
-- TAO Lab — Produção Interna (diluições e bases manipuladas na farmácia)
-- Espelho do FC18000/FC18100 do FCerta. A "receita" reaproveita lab_formulas_padrao.
-- O diluído produzido nasce como lote em lab_lotes_mp (origem=producao_interna).
-- Idempotente. Rodar antes do deploy do módulo.
-- ============================================================================

-- 1) Ativo: marca quais são produzidos internamente + vínculo à receita
ALTER TABLE ativos
    ADD COLUMN IF NOT EXISTS produzido_interno       boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS formula_producao_id     uuid,     -- -> lab_formulas_padrao.id (a receita)
    ADD COLUMN IF NOT EXISTS validade_producao_dias  integer DEFAULT 90;

-- 2) Ordem de Produção Interna (cabeçalho)
CREATE TABLE IF NOT EXISTS lab_producao (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id      uuid NOT NULL,
    ativo_id        uuid NOT NULL,                 -- o diluído/base que será produzido
    formula_id      uuid,                          -- receita usada (lab_formulas_padrao)
    quantidade      numeric NOT NULL,              -- quanto produzir (na unidade do ativo)
    unidade         text DEFAULT 'g',
    nr_lote         text,                          -- lote gerado (PI-AAAAMM-NNN)
    lote_gerado_id  uuid,                          -- -> lab_lotes_mp criado na conclusão
    teor_pct        numeric,                       -- teor do principal no produzido
    fator_diluicao  numeric,                       -- fator (total/puro)
    dt_producao     date DEFAULT CURRENT_DATE,
    dt_validade     date,
    status          text DEFAULT 'aberta',         -- aberta | pesagem | concluida | cancelada
    responsavel     text,
    obs             text,
    criado_por      bigint,
    criado_em       timestamptz DEFAULT now(),
    concluida_em    timestamptz
);
CREATE INDEX IF NOT EXISTS idx_lab_producao_cli    ON lab_producao (cliente_id, status);
CREATE INDEX IF NOT EXISTS idx_lab_producao_ativo  ON lab_producao (cliente_id, ativo_id);

-- 3) Insumos da ordem (o que entra: principal puro + veículo/excipiente)
CREATE TABLE IF NOT EXISTS lab_producao_itens (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    producao_id   uuid NOT NULL,
    ativo_id      uuid,
    descricao     text,
    qtd_teorica   numeric,          -- calculada pela receita escalada
    qtd_pesada    numeric,
    unidade       text DEFAULT 'g',
    lote_mp_id    uuid,             -- lote consumido (FEFO)
    eh_qsp        boolean DEFAULT false,
    ordem         integer DEFAULT 0,
    pesado_por    bigint,
    pesado_em     timestamptz
);
CREATE INDEX IF NOT EXISTS idx_lab_producao_itens ON lab_producao_itens (producao_id);
