-- ============================================================================
-- migration_caixa_movimentos_v1.sql — Aportes e sangrias na sessão de caixa
-- ----------------------------------------------------------------------------
-- Pedido do Carlos (20/07/2026): lançar entradas (aporte/suprimento) e saídas
-- (sangria) de dinheiro na gaveta durante a sessão. Entram no "esperado na
-- gaveta" e no saldo_final_calculado do fechamento.
-- Sem exclusão/edição: lançou errado, lança o movimento inverso (auditável).
-- ADITIVA e IDEMPOTENTE.
-- ============================================================================

CREATE TABLE IF NOT EXISTS caixa_movimentos (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id  uuid NOT NULL,
    sessao_id   uuid NOT NULL REFERENCES caixa_sessoes(id),
    tipo        text NOT NULL CHECK (tipo IN ('aporte','sangria')),
    valor       numeric NOT NULL CHECK (valor > 0),
    motivo      text,
    operador_id bigint,
    criado_em   timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_caixa_mov ON caixa_movimentos (cliente_id, sessao_id, criado_em);
