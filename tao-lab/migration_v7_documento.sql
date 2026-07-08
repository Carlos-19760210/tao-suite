-- ============================================================
-- TAO — MIGRATION v7: DOCUMENTO (RG) no contato único (07/07/2026)
-- FCerta guarda CPF em NRCNPJ e RG em NRINSCR (+OERG órgão, +UFRG uf).
-- RG tem cobertura bem maior (1.476 vs 207 CPF). crm_contatos só tinha cpf.
-- Rodar no SQL Editor. Aditiva.
-- ============================================================

ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS rg        text;
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS rg_orgao  text;   -- SSP, DETRAN...
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS rg_uf     text;

SELECT COUNT(*) AS colunas_doc FROM information_schema.columns
 WHERE table_name='crm_contatos' AND column_name IN ('cpf','rg','rg_orgao','rg_uf');
-- esperado: 4 (cpf já existia + 3 novas)
