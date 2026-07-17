-- ============================================================================
-- migration_caixa_operadora_v2.sql — Operadora como CONTRATO + recebíveis
-- ----------------------------------------------------------------------------
-- Desenho fechado com Carlos (17/07/2026):
--   • 2 operadoras (Rede, Cielo; 2 máquinas Rede + 1 Cielo), política = ANTECIPA tudo,
--     MDR com divisão POR BANDEIRA.
--   • Taxa muda de dona: da forma de pagamento → para a OPERADORA
--     (modalidade × bandeira × faixa de parcelas). O modelo antigo (por forma)
--     continua válido como fallback — nada quebra sem cadastro novo.
--   • Recebíveis: espelham COMO a operadora paga (antecipado D+1 ou fluxo 30/60/90),
--     e são o novo alvo da conciliação (extrato × recebíveis).
-- ADITIVA e IDEMPOTENTE.
-- ============================================================================

-- ── Operadora = contrato ────────────────────────────────────────────────────
ALTER TABLE caixa_adquirentes ADD COLUMN IF NOT EXISTS politica_recebimento  text    NOT NULL DEFAULT 'antecipado'; -- antecipado|fluxo
ALTER TABLE caixa_adquirentes ADD COLUMN IF NOT EXISTS antecipacao_modo      text    NOT NULL DEFAULT 'pct_fixo';   -- pct_fixo (% único) | pct_mes (% a.m. × meses antecipados por parcela)
ALTER TABLE caixa_adquirentes ADD COLUMN IF NOT EXISTS prazo_antecipado_dias integer NOT NULL DEFAULT 1;            -- D+1
ALTER TABLE caixa_adquirentes ADD COLUMN IF NOT EXISTS terminais             text;                                  -- lista "Balcão,Entrega" (vazio = extrato único/EC único)

-- ── Taxas: dona = operadora ─────────────────────────────────────────────────
ALTER TABLE caixa_taxas ADD COLUMN IF NOT EXISTS adquirente_id uuid REFERENCES caixa_adquirentes(id);
ALTER TABLE caixa_taxas ADD COLUMN IF NOT EXISTS modalidade    text;   -- debito|credito
ALTER TABLE caixa_taxas ADD COLUMN IF NOT EXISTS bandeira      text;   -- NULL = todas (curinga)
ALTER TABLE caixa_taxas ALTER COLUMN forma_pagamento_id DROP NOT NULL; -- linha nova é da operadora (forma vira legado)

-- ── Pagamento: dimensões novas + custo de antecipação separado do MDR ───────
ALTER TABLE caixa_pagamentos ADD COLUMN IF NOT EXISTS bandeira          text;
ALTER TABLE caixa_pagamentos ADD COLUMN IF NOT EXISTS terminal          text;
ALTER TABLE caixa_pagamentos ADD COLUMN IF NOT EXISTS valor_antecipacao numeric NOT NULL DEFAULT 0;

-- ── Recebíveis (novo alvo da conciliação) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS caixa_recebiveis (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id     uuid NOT NULL,
    pagamento_id   uuid REFERENCES caixa_pagamentos(id),
    adquirente_id  uuid REFERENCES caixa_adquirentes(id),
    parcela_n      integer NOT NULL DEFAULT 1,
    parcelas_total integer NOT NULL DEFAULT 1,
    valor_previsto numeric NOT NULL,
    data_prevista  date    NOT NULL,
    status         text    NOT NULL DEFAULT 'previsto',  -- previsto|recebido|divergente|cancelado
    valor_recebido numeric,
    recebido_em    date,
    obs            text,
    criado_em      timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_caixa_receb ON caixa_recebiveis (cliente_id, adquirente_id, data_prevista, status);
