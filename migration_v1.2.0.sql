-- TAO CRM v1.2.0 — Migration
-- Execute no SQL Editor do Supabase: https://supabase.com/dashboard/project/gclayesytzzpzkjvgede/sql/new

-- 1. Templates de mensagem
CREATE TABLE IF NOT EXISTS crm_msg_templates (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    nome         TEXT NOT NULL,
    conteudo     TEXT NOT NULL,
    criado_em    TIMESTAMPTZ DEFAULT now()
);

-- 2. Webhooks de saída
CREATE TABLE IF NOT EXISTS crm_webhooks_saida (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    nome         TEXT,
    evento       TEXT NOT NULL,
    url          TEXT NOT NULL,
    ativo        BOOLEAN DEFAULT true,
    criado_em    TIMESTAMPTZ DEFAULT now()
);

-- 3. Round-robin de atribuição
CREATE TABLE IF NOT EXISTS crm_round_robin (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    pipeline_id  UUID,
    user_ids     JSONB DEFAULT '[]',
    next_idx     INT DEFAULT 0,
    criado_em    TIMESTAMPTZ DEFAULT now()
);

-- 4. Coluna email_destino na tabela de automações
ALTER TABLE crm_automacoes ADD COLUMN IF NOT EXISTS email_destino TEXT;

-- 5. RLS policies (habilita acesso via service key — mesmo padrão das outras tabelas)
ALTER TABLE crm_msg_templates  ENABLE ROW LEVEL SECURITY;
ALTER TABLE crm_webhooks_saida ENABLE ROW LEVEL SECURITY;
ALTER TABLE crm_round_robin    ENABLE ROW LEVEL SECURITY;

CREATE POLICY IF NOT EXISTS "service_role full access" ON crm_msg_templates  USING (true) WITH CHECK (true);
CREATE POLICY IF NOT EXISTS "service_role full access" ON crm_webhooks_saida USING (true) WITH CHECK (true);
CREATE POLICY IF NOT EXISTS "service_role full access" ON crm_round_robin    USING (true) WITH CHECK (true);
