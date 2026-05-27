-- TAO CRM v1.5.0 — Migration
-- Execute no SQL Editor: https://supabase.com/dashboard/project/gclayesytzzpzkjvgede/sql/new

-- 1. Comentários internos (notas da equipe, não enviadas ao cliente)
CREATE TABLE IF NOT EXISTS crm_comentarios (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    card_id      UUID REFERENCES crm_cards(id) ON DELETE CASCADE,
    user_id      INT NOT NULL,
    conteudo     TEXT NOT NULL,
    criado_em    TIMESTAMPTZ DEFAULT now()
);
ALTER TABLE crm_comentarios ENABLE ROW LEVEL SECURITY;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE tablename='crm_comentarios' AND policyname='service_role full access') THEN
        EXECUTE 'CREATE POLICY "service_role full access" ON crm_comentarios USING (true) WITH CHECK (true)';
    END IF;
END $$;

-- 2. Metas por atendente (mensal)
CREATE TABLE IF NOT EXISTS crm_metas (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    user_id      INT NOT NULL,
    mes          INT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    ano          INT NOT NULL,
    meta_cards   INT DEFAULT 0,
    meta_valor   NUMERIC(15,2) DEFAULT 0,
    criado_em    TIMESTAMPTZ DEFAULT now(),
    UNIQUE (workspace_id, user_id, mes, ano)
);
ALTER TABLE crm_metas ENABLE ROW LEVEL SECURITY;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE tablename='crm_metas' AND policyname='service_role full access') THEN
        EXECUTE 'CREATE POLICY "service_role full access" ON crm_metas USING (true) WITH CHECK (true)';
    END IF;
END $$;

-- 3. Mensagens agendadas
CREATE TABLE IF NOT EXISTS crm_msgs_agendadas (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id  UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    card_id       UUID REFERENCES crm_cards(id) ON DELETE CASCADE,
    user_id       INT NOT NULL,
    conteudo      TEXT NOT NULL,
    agendado_para TIMESTAMPTZ NOT NULL,
    enviado       BOOLEAN DEFAULT false,
    enviado_em    TIMESTAMPTZ,
    erro          TEXT,
    criado_em     TIMESTAMPTZ DEFAULT now()
);
ALTER TABLE crm_msgs_agendadas ENABLE ROW LEVEL SECURITY;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE tablename='crm_msgs_agendadas' AND policyname='service_role full access') THEN
        EXECUTE 'CREATE POLICY "service_role full access" ON crm_msgs_agendadas USING (true) WITH CHECK (true)';
    END IF;
END $$;

-- 4. Índices úteis
CREATE INDEX IF NOT EXISTS crm_comentarios_card ON crm_comentarios(card_id, criado_em DESC);
CREATE INDEX IF NOT EXISTS crm_metas_ws_user    ON crm_metas(workspace_id, user_id, ano, mes);
CREATE INDEX IF NOT EXISTS crm_msgs_ag_pendente ON crm_msgs_agendadas(agendado_para) WHERE enviado = false;
