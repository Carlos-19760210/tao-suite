-- ============================================================================
-- TAO Caixa — corrige o CHECK de status de caixa_recibos (mesmo problema de vendas)
-- ----------------------------------------------------------------------------
-- PROBLEMA: caixa_recibos_status_check aceita só 'aberto','quitado','cancelado'
--   — mas o ESTORNO (tao_caixa_estornar_venda) grava status='estornado'.
--   Resultado: a venda reabre e o pagamento é marcado estornado, mas o RECIBO
--   NÃO fica 'estornado' → a auditoria do estorno (motivo/quem/quando) não persiste.
--
-- CORREÇÃO: adicionar 'estornado' ao conjunto permitido.
-- SEGURO: o único recibo existente é 'quitado' (permanece válido).
-- ============================================================================

ALTER TABLE caixa_recibos DROP CONSTRAINT IF EXISTS caixa_recibos_status_check;

ALTER TABLE caixa_recibos
  ADD CONSTRAINT caixa_recibos_status_check
  CHECK (status IN ('aberto','quitado','estornado','cancelado'));
