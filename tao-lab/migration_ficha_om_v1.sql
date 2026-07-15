-- ============================================================================
-- migration_ficha_om_v1.sql
-- ----------------------------------------------------------------------------
-- Campos da Ordem de Manipulação / ficha de pesagem que faltavam frente ao FCerta:
--   • data da prescrição (DT.PRESC do FCerta) — quando o médico prescreveu
--   • previsão de retirada (RETIRADA do FCerta) — prazo prometido ao paciente
-- Informados no orçamento e herdados pela OM (a ficha lê da OM). Aditiva/idempotente.
-- ============================================================================

ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS dt_prescricao     date;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS previsao_retirada date;

ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS dt_prescricao     date;
ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS previsao_retirada date;
