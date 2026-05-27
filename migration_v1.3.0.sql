-- TAO CRM v1.3.0 — Migration
-- Execute no SQL Editor: https://supabase.com/dashboard/project/gclayesytzzpzkjvgede/sql/new

-- 1. Tags com cores
CREATE TABLE IF NOT EXISTS crm_tags (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    nome         TEXT NOT NULL,
    cor          TEXT DEFAULT '#3b82f6',
    criado_em    TIMESTAMPTZ DEFAULT now()
);

-- 2. Relação card ↔ tag (many-to-many)
CREATE TABLE IF NOT EXISTS crm_cards_tags (
    card_id UUID REFERENCES crm_cards(id) ON DELETE CASCADE,
    tag_id  UUID REFERENCES crm_tags(id)  ON DELETE CASCADE,
    PRIMARY KEY (card_id, tag_id)
);

-- 3. Lembretes / follow-up
CREATE TABLE IF NOT EXISTS crm_lembretes (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    card_id      UUID REFERENCES crm_cards(id) ON DELETE CASCADE,
    user_id      INT  NOT NULL,
    titulo       TEXT NOT NULL,
    descricao    TEXT,
    data_hora    TIMESTAMPTZ NOT NULL,
    completado   BOOLEAN DEFAULT false,
    notificado   BOOLEAN DEFAULT false,
    criado_em    TIMESTAMPTZ DEFAULT now()
);

-- 4. Valor da oportunidade no card
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS valor_oportunidade NUMERIC(15,2);

-- 5. Billing / planos no workspace
ALTER TABLE crm_workspaces ADD COLUMN IF NOT EXISTS plano          TEXT DEFAULT 'free';
ALTER TABLE crm_workspaces ADD COLUMN IF NOT EXISTS plano_expira_em TIMESTAMPTZ;

-- 6. RLS
ALTER TABLE crm_tags        ENABLE ROW LEVEL SECURITY;
ALTER TABLE crm_cards_tags  ENABLE ROW LEVEL SECURITY;
ALTER TABLE crm_lembretes   ENABLE ROW LEVEL SECURITY;

CREATE POLICY "service_role full access" ON crm_tags       USING (true) WITH CHECK (true);
CREATE POLICY "service_role full access" ON crm_cards_tags USING (true) WITH CHECK (true);
CREATE POLICY "service_role full access" ON crm_lembretes  USING (true) WITH CHECK (true);
