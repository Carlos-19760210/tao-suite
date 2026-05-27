-- TAO CRM v1.4.0 — Migration
-- Execute no SQL Editor: https://supabase.com/dashboard/project/gclayesytzzpzkjvgede/sql/new
--
-- NOTA: Se você já executou a primeira parte (itens 1-4 abaixo), pule para o item 5.

-- 1. Coluna para automação sem-resposta
ALTER TABLE crm_automacoes ADD COLUMN IF NOT EXISTS horas_sem_resposta INT DEFAULT 24;

-- 2. Meta JSON no card (usado pela automação sem-resposta para evitar spam)
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS meta JSONB DEFAULT '{}';

-- 3. Tabela de histórico
CREATE TABLE IF NOT EXISTS crm_historico (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID REFERENCES crm_workspaces(id) ON DELETE CASCADE,
    card_id      UUID REFERENCES crm_cards(id) ON DELETE CASCADE,
    user_id      INT,
    tipo         TEXT NOT NULL DEFAULT 'movimentacao',
    de           TEXT,
    para         TEXT,
    criado_em    TIMESTAMPTZ DEFAULT now()
);

-- 4. RLS para historico
ALTER TABLE crm_historico ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies
        WHERE tablename = 'crm_historico'
          AND policyname = 'service_role full access'
    ) THEN
        EXECUTE 'CREATE POLICY "service_role full access" ON crm_historico USING (true) WITH CHECK (true)';
    END IF;
END $$;

-- 5. Coluna status no card (controla aberto/fechado/arquivado via texto)
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'aberto';

-- Sincroniza com coluna fechado legada (se existir)
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'crm_cards' AND column_name = 'fechado'
    ) THEN
        UPDATE crm_cards SET status = 'fechado' WHERE fechado = true AND status = 'aberto';
    END IF;
END $$;

-- 6. Índices de performance
CREATE INDEX IF NOT EXISTS crm_cards_status_ws ON crm_cards(workspace_id, status);

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'crm_mensagens' AND column_name = 'criado_em'
    ) THEN
        EXECUTE 'CREATE INDEX IF NOT EXISTS crm_mensagens_card_criado ON crm_mensagens(card_id, criado_em DESC)';
    END IF;
END $$;
