-- ============================================================================
-- migration_orc_controlado_v1.sql — dados de RECEITUÁRIO DE CONTROLE (344/98)
-- capturados já no ORÇAMENTO (pedido Carlos 18/07): editor permite informar/editar
-- receita e comprador; a OM herda na criação (lab_ordens já tem as colunas — Pacote 4).
-- ADITIVA e IDEMPOTENTE.
-- ============================================================================
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS tp_receita       text;  -- A | B | B2 | C1 | C2 ...
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS nr_notificacao   text;  -- nº da notificação de receita
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS comprador_nome   text;  -- quem compra (pode ≠ paciente)
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS comprador_doc_tp text;  -- RG | CPF | CNH
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS comprador_doc_nr text;
