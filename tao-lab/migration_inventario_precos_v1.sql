-- ============================================================================
-- TAO Lab — Onda 1: Inventário em massa (FC1D000) + Histórico de preços (FC03160)
-- Idempotente. Rodar antes do deploy da Onda 1.
-- ============================================================================

-- 1) Sessão de inventário (contagem geral)
CREATE TABLE IF NOT EXISTS lab_inventario (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id     uuid NOT NULL,
    descricao      text,
    status         text DEFAULT 'aberta',    -- aberta | fechada | cancelada
    filtro         text,                      -- descrição do escopo (ex: 'todos', 'curva A')
    total_itens    integer DEFAULT 0,
    total_diverg   integer DEFAULT 0,
    dt_abertura    timestamptz DEFAULT now(),
    dt_fechamento  timestamptz,
    responsavel    text,
    criado_por     bigint
);
CREATE INDEX IF NOT EXISTS idx_lab_inventario_cli ON lab_inventario (cliente_id, status);

-- 2) Itens do inventário (snapshot congelado + contagem)
CREATE TABLE IF NOT EXISTS lab_inventario_itens (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    inventario_id uuid NOT NULL,
    lote_id       uuid,
    ativo_id      uuid,
    ativo_nome    text,
    nr_lote       text,
    unidade       text,
    qtd_sistema   numeric DEFAULT 0,   -- saldo congelado na abertura
    qtd_contada   numeric,             -- null = ainda não contado
    diferenca     numeric,             -- qtd_contada - qtd_sistema
    contado       boolean DEFAULT false
);
CREATE INDEX IF NOT EXISTS idx_lab_inventario_itens ON lab_inventario_itens (inventario_id);

-- 3) Histórico de reajuste de preços (custo/venda por data)
CREATE TABLE IF NOT EXISTS ativo_precos_hist (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id     uuid NOT NULL,
    ativo_id       uuid NOT NULL,
    dt             date DEFAULT CURRENT_DATE,
    preco_compra   numeric,
    custo_unidade  numeric,
    preco_venda    numeric,
    origem         text,               -- nf | manual | reposicao
    fornecedor_id  uuid,
    nf_numero      text,
    usuario_id     bigint,
    criado_em      timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_ativo_precos_hist ON ativo_precos_hist (cliente_id, ativo_id, dt);
