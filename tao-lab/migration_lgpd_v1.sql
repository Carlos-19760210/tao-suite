-- ============================================================================
-- TAO Lab — Onda 2a: LGPD (consentimento + anonimização + trilha de acesso)
-- Dado de saúde é sensível (LGPD art. 11). Espelho FC07I00.
-- Idempotente. Rodar antes do deploy.
-- ============================================================================

-- 1) Consentimento e anonimização no contato (base única = crm_contatos)
ALTER TABLE crm_contatos
    ADD COLUMN IF NOT EXISTS consentimento   boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS consent_data    date,
    ADD COLUMN IF NOT EXISTS consent_canal   text,          -- verbal | whatsapp | formulario | termo assinado
    ADD COLUMN IF NOT EXISTS anonimizado     boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS anonimizado_em  timestamptz;

-- 2) Trilha de acesso a dado sensível (quem abriu os dados de saúde de um paciente)
CREATE TABLE IF NOT EXISTS lgpd_acessos (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id   uuid NOT NULL,
    contato_id   uuid,
    usuario_id   bigint,
    usuario_nome text,
    acao         text,                -- visualizou | editou | anonimizou
    criado_em    timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_lgpd_acessos ON lgpd_acessos (cliente_id, contato_id, criado_em);
