-- ----------------------------------------------------------------------------
-- CAIXA — Campo CM no recibo (valor adicional do recebimento). Carlos 14/08/2026.
-- Registro/auditoria: NÃO altera o saldo da venda nem a distribuição FIFO.
-- Rodar no SQL Editor do Supabase. Reversível: ALTER TABLE caixa_recibos DROP COLUMN valor_cm;
-- ----------------------------------------------------------------------------
ALTER TABLE public.caixa_recibos
  ADD COLUMN IF NOT EXISTS valor_cm numeric NOT NULL DEFAULT 0;
