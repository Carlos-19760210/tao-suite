-- ============================================================
-- TAO — MIGRATION v5: DADOS BÁSICOS DO CLIENTE/PACIENTE (07/07/2026)
-- Cadastro SUPERFICIAL (não é atenção farmacêutica): características de saúde
-- comuns p/ contexto do atendimento. Estende hist_clientes (já tem os 7.918).
-- Rodar no SQL Editor. Aditiva.
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
