-- ============================================================================
-- TAO Lab — Ativo: excipiente associado (1, opcional) + bloqueio para manipulação.
-- Idempotente. Rodar antes do deploy.
-- ============================================================================

ALTER TABLE ativos
    ADD COLUMN IF NOT EXISTS excipiente_id     uuid,     -- -> ativos.id (embalagem/excipiente sugerido no orçamento)
    ADD COLUMN IF NOT EXISTS bloqueado         boolean DEFAULT false,  -- bloqueia manipulação; alerta no orçamento
    ADD COLUMN IF NOT EXISTS bloqueado_motivo  text;
