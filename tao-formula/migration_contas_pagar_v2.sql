-- ============================================================================
-- migration_contas_pagar_v2.sql — Contas a Pagar COMPLETO (todas as contas)
-- ----------------------------------------------------------------------------
-- Pedido do Carlos (18/08/2026): o módulo passa a controlar TODAS as contas da
-- farmácia — não só duplicatas de NF. Lançamento manual + categorias + parcelas
-- + baixa com data/forma/valor + sangria automática no Caixa + recorrências.
-- ADITIVA e IDEMPOTENTE (contas de NF existentes não mudam de comportamento).
-- ============================================================================

-- ── contas_pagar: novas colunas ──────────────────────────────────────────────
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS origem            text NOT NULL DEFAULT 'nf';  -- nf | manual | recorrencia
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS descricao         text;                        -- "Aluguel agosto", "Energia"
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS credor            text;                        -- credor livre (sem cadastro de fornecedor)
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS categoria_id      uuid;                        -- FK lógica p/ contas_categorias
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS parcela_n         smallint;                    -- 1..N (null = à vista)
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS parcelas_total    smallint;
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS forma_pagamento_id uuid;                       -- caixa_formas_pagamento usada na baixa
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS valor_pago        numeric;                     -- aceita parcial/juros/desconto
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS pago_por          bigint;                      -- WP user da baixa
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS caixa_movimento_id uuid;                       -- sangria gerada (desfeita no reabrir)
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS recorrencia_id    uuid;                        -- de qual recorrência nasceu
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS confirmar_valor   boolean NOT NULL DEFAULT false; -- recorrência de valor variável
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS criado_por        bigint;

CREATE INDEX IF NOT EXISTS idx_cp_cli_status_venc ON contas_pagar (cliente_id, status, vencimento);
CREATE INDEX IF NOT EXISTS idx_cp_recorrencia     ON contas_pagar (recorrencia_id) WHERE recorrencia_id IS NOT NULL;

-- ── Categorias financeiras (plano simples, editável por cliente) ─────────────
CREATE TABLE IF NOT EXISTS contas_categorias (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id  uuid NOT NULL,
    nome        text NOT NULL,
    ordem       smallint NOT NULL DEFAULT 0,
    ativo       boolean NOT NULL DEFAULT true,
    criado_em   timestamptz DEFAULT now(),
    UNIQUE (cliente_id, nome)
);
CREATE INDEX IF NOT EXISTS idx_contas_cat ON contas_categorias (cliente_id, ativo, ordem);

-- ── Recorrências (despesas fixas — bloco 2, tabela já criada agora) ──────────
CREATE TABLE IF NOT EXISTS contas_recorrencias (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL,
    descricao     text NOT NULL,               -- "Aluguel"
    credor        text,
    categoria_id  uuid,
    valor         numeric,                     -- null = valor a confirmar todo mês
    dia_vencimento smallint NOT NULL CHECK (dia_vencimento BETWEEN 1 AND 28),
    antecip_dias  smallint NOT NULL DEFAULT 7, -- gera a conta N dias antes
    ativo         boolean NOT NULL DEFAULT true,
    ultima_gerada text,                        -- 'YYYY-MM' do último mês gerado (idempotência do cron)
    criado_por    bigint,
    criado_em     timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_contas_rec ON contas_recorrencias (cliente_id, ativo);
