-- ============================================================================
-- migration_alcadas_v1.sql — ALÇADAS (etapa 1) associadas ao perfil de acesso.
-- Alçada = até quanto o perfil decide sozinho (a permissão diz onde ele chega).
-- Sem linha cadastrada = sem limite (regra de ouro: nada tranca por omissão).
-- ADITIVA/IDEMPOTENTE.
-- ============================================================================
CREATE TABLE IF NOT EXISTS crm_alcadas (
    id        uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    perfil_id uuid NOT NULL REFERENCES crm_perfis(id) ON DELETE CASCADE,
    recurso   text NOT NULL,          -- ex.: orcamento.desconto_pct, pdv.desconto_valor
    limite    numeric NOT NULL,       -- % ou R$ conforme o recurso
    UNIQUE ( perfil_id, recurso )
);
CREATE INDEX IF NOT EXISTS idx_crm_alcadas ON crm_alcadas ( perfil_id );
