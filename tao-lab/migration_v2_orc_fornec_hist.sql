-- ============================================================
-- TAO — MIGRATION CONSOLIDADA v2 (07/07/2026)
-- 1) Fornecedor detalhado (base p/ importar NF de entrada)
-- 2) Orçamento: prescritor opcional + posologia + Cliente×Paciente
-- 3) Histórico: tipo de cápsula + prescritor (repetição exata)
-- Rodar no SQL Editor do Supabase. 100% aditiva.
-- ============================================================

-- 1. Fornecedores — dados fiscais/contato p/ casar com o XML da NF (emitente)
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS cnpj            text;   -- só dígitos (chave do XML)
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS razao_social    text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS nome_fantasia   text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS inscr_estadual  text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS endereco        text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS cidade          text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS uf              text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS cep             text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS telefone        text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS email           text;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS prazo_pagamento text;   -- ex: 28/35/42 dias
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS codigo_fc       text;   -- FORNECID do FCerta (carga)
CREATE INDEX IF NOT EXISTS idx_fornecedores_cnpj ON fornecedores (cliente_id, cnpj);

-- 2. Orçamentos — prescritor NÃO obrigatório; cliente (contratante) ≠ paciente (quem usa)
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS posologia    text;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS prescritor   text;   -- nome + conselho/UF livre (ex: DR FULANO CRM 12345/SP)
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS nome_cliente text;   -- quem contrata (ex: mãe); nome_paciente = quem usa (ex: filho)

-- 3. Histórico — repetição exata + prescritor da fórmula origem
ALTER TABLE hist_formulas ADD COLUMN IF NOT EXISTS tpcap      text;  -- tipo cápsula FCerta (G=gelatinosa...)
ALTER TABLE hist_formulas ADD COLUMN IF NOT EXISTS prescritor text;  -- nome + CRM/UF (join FC04000)

-- Validação
SELECT 'fornecedores' t, COUNT(*) n FROM information_schema.columns WHERE table_name='fornecedores' AND column_name IN ('cnpj','razao_social','prazo_pagamento')
UNION ALL
SELECT 'orcamentos', COUNT(*) FROM information_schema.columns WHERE table_name='orcamentos' AND column_name IN ('posologia','prescritor','nome_cliente')
UNION ALL
SELECT 'hist_formulas', COUNT(*) FROM information_schema.columns WHERE table_name='hist_formulas' AND column_name IN ('tpcap','prescritor');
-- esperado: fornecedores 3 | orcamentos 3 | hist_formulas 2
