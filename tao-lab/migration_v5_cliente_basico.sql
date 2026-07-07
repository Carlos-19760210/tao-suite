-- ============================================================
-- ⛔ CANCELADA — NÃO RODAR. Substituída por migration_v6_contato_unico.sql.
-- Motivo: punha os dados de saúde no hist_clientes, criando cadastro de pessoa
-- PARALELO. Correção (Carlos): a pessoa é ÚNICA em crm_contatos (base do CRM).
-- Os campos de saúde foram para crm_contatos na v6.
-- ============================================================
-- (conteúdo original mantido só como referência histórica — não executar)
-- ============================================================
-- TAO — MIGRATION v5: DADOS BÁSICOS DO CLIENTE/PACIENTE (07/07/2026)
-- ============================================================

ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS whatsapp        text;
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS sexo            text;   -- M | F | (vazio)
-- Características de saúde comuns (bool simples — marca/desmarca)
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS saude_obesidade  boolean DEFAULT false;
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS saude_colesterol boolean DEFAULT false;
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS saude_pressao    boolean DEFAULT false;
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS saude_diabetes   boolean DEFAULT false;
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS alergias         text;   -- texto livre curto
-- Origem: FCerta (carga) ou cadastro novo no TAO
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS origem          text DEFAULT 'fcerta';

SELECT COUNT(*) AS colunas_novas FROM information_schema.columns
 WHERE table_name='hist_clientes'
 AND column_name IN ('whatsapp','sexo','saude_obesidade','saude_colesterol','saude_pressao','saude_diabetes','alergias','origem');
-- esperado: 8
