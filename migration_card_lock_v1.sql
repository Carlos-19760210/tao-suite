-- ============================================================
-- TAO CRM — LOCK de card (atendimento exclusivo)
-- Impede que dois atendentes atuem no MESMO card ao mesmo tempo
-- (ex.: dois enviarem mensagem em sequência). Aditiva e idempotente.
-- Rodar no SQL Editor do Supabase.
-- ============================================================

ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS lock_user_id   bigint;
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS lock_user_nome text;
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS lock_em        timestamptz;

-- Validação
SELECT 'crm_cards.lock', COUNT(*) FROM information_schema.columns
 WHERE table_name='crm_cards' AND column_name IN ('lock_user_id','lock_user_nome','lock_em');
-- esperado: 3
