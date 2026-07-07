-- ============================================================
-- TAO — MIGRATION v6: CADASTRO ÚNICO DE PESSOA (07/07/2026)
-- Princípio (Carlos): a pessoa vive em crm_contatos (base do CRM);
-- todos os módulos referenciam ela. Corrige o desvio da v5 (que pôs
-- saúde no hist_clientes). Rodar no SQL Editor. 100% aditiva.
-- ⚠️ NÃO rodar migration_v5_cliente_basico.sql — foi substituída por esta.
-- ============================================================

-- 1. Contato do CRM ganha os dados básicos/saúde (antes destinados ao hist_clientes)
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS data_nascimento  date;
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS sexo             text;   -- M | F
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS saude_obesidade  boolean DEFAULT false;
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS saude_colesterol boolean DEFAULT false;
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS saude_pressao    boolean DEFAULT false;
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS saude_diabetes   boolean DEFAULT false;
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS alergias         text;
-- Origem do contato: 'crm' (WhatsApp/manual) | 'fcerta' (carga histórica) —
-- permite campanhas FILTRAREM os importados (sem opt-in) e não disparar indevidamente.
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS origem           text DEFAULT 'crm';
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS cdcli_fcerta     int;    -- rastro do CDCLI de origem

-- 2. hist_clientes deixa de ser cadastro de pessoa: vira índice de histórico
--    apontando p/ o contato único (a "pessoa"). NULL enquanto não reconciliado.
ALTER TABLE hist_clientes ADD COLUMN IF NOT EXISTS contato_id uuid REFERENCES crm_contatos(id);
CREATE INDEX IF NOT EXISTS idx_hist_cli_contato ON hist_clientes (contato_id);

-- 3. Orçamento aponta p/ o contato único (nome_paciente vira só rótulo de exibição)
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS contato_id uuid REFERENCES crm_contatos(id);

-- Índice de busca por whatsapp dentro do workspace (chave da junção)
CREATE INDEX IF NOT EXISTS idx_crm_contatos_wpp ON crm_contatos (workspace_id, whatsapp);

-- Validação
SELECT 'crm_contatos' t, COUNT(*) n FROM information_schema.columns WHERE table_name='crm_contatos' AND column_name IN ('data_nascimento','saude_obesidade','origem','cdcli_fcerta')
UNION ALL SELECT 'hist_clientes', COUNT(*) FROM information_schema.columns WHERE table_name='hist_clientes' AND column_name='contato_id'
UNION ALL SELECT 'orcamentos', COUNT(*) FROM information_schema.columns WHERE table_name='orcamentos' AND column_name='contato_id';
-- esperado: crm_contatos 4 | hist_clientes 1 | orcamentos 1
