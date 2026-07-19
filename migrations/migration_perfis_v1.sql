-- ============================================================================
-- migration_perfis_v1.sql — PERFIS DE ACESSO (Fase 1)
-- Desenho aprovado (19/07/2026): negócio × perfil × tela × recurso × permissão.
-- Usuário SEM perfil = comportamento atual; admin WP vê tudo. ADITIVA/IDEMPOTENTE.
-- ============================================================================
CREATE TABLE IF NOT EXISTS crm_perfis (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id uuid NOT NULL,
    nome         text NOT NULL,
    descricao    text,
    ativo        boolean NOT NULL DEFAULT true,
    criado_em    timestamptz DEFAULT now(),
    UNIQUE ( workspace_id, nome )
);

CREATE TABLE IF NOT EXISTS crm_perfil_usuarios (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id uuid NOT NULL,
    perfil_id    uuid NOT NULL REFERENCES crm_perfis(id) ON DELETE CASCADE,
    usuario_id   bigint NOT NULL,
    UNIQUE ( workspace_id, usuario_id )
);

CREATE TABLE IF NOT EXISTS crm_permissoes (
    id        uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    perfil_id uuid NOT NULL REFERENCES crm_perfis(id) ON DELETE CASCADE,
    tela      text NOT NULL,
    recurso   text NOT NULL DEFAULT '*',
    permissao text NOT NULL DEFAULT 'opera' CHECK ( permissao IN ('oculto','leitura','opera') ),
    UNIQUE ( perfil_id, tela, recurso )
);
CREATE INDEX IF NOT EXISTS idx_crm_permissoes ON crm_permissoes ( perfil_id, tela );
