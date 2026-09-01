-- ----------------------------------------------------------------------------
-- CRM — Acréscimo no card (Carlos 01/09/2026).
-- Composição do valor do card:
--   Subtotal    = itens do negócio + orçamentos (vendas: todos | pós-vendas: aprovados) + Acréscimo
--   Valor Final = Subtotal − Descontos
-- Aditiva e idempotente. Rodar no SQL Editor do Supabase.
-- Reversível: ALTER TABLE crm_cards DROP COLUMN acrescimo, DROP COLUMN acrescimo_tipo;
-- ----------------------------------------------------------------------------
ALTER TABLE public.crm_cards
  ADD COLUMN IF NOT EXISTS acrescimo      numeric NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS acrescimo_tipo text    NOT NULL DEFAULT 'valor';
