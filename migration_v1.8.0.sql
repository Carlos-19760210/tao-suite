-- TAO CRM v1.8.0 — Itens de Venda por Card
-- Execute no Supabase SQL Editor (idempotente)

-- ─── TABELA: crm_card_itens ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS crm_card_itens (
    id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    card_id          UUID        NOT NULL,
    workspace_id     UUID        NOT NULL,
    -- catalogo_id: referência opcional ao produto do TAO Neo (catalogo.id).
    -- NULL = item digitado manualmente (CRM funciona sem TAO Neo).
    -- Quando preenchido, descricao e preco_unitario são snapshot do momento da venda.
    catalogo_id      UUID        NULL,
    descricao        TEXT        NOT NULL DEFAULT '',
    quantidade       NUMERIC(10,3) NOT NULL DEFAULT 1,
    preco_unitario   NUMERIC(12,2) NOT NULL DEFAULT 0,
    desconto_tipo    TEXT        NOT NULL DEFAULT 'pct'
                                 CHECK (desconto_tipo IN ('pct','valor')),
    desconto_valor   NUMERIC(10,2) NOT NULL DEFAULT 0,
    total            NUMERIC(12,2) NOT NULL DEFAULT 0,
    ordem            INT         NOT NULL DEFAULT 0,
    criado_em        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Índices
CREATE INDEX IF NOT EXISTS idx_crm_card_itens_card      ON crm_card_itens(card_id);
CREATE INDEX IF NOT EXISTS idx_crm_card_itens_workspace  ON crm_card_itens(workspace_id);

-- RLS — mesma política dos outros módulos CRM
ALTER TABLE crm_card_itens ENABLE ROW LEVEL SECURITY;

-- Política permissiva para service role (N8N, backend WP)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies
        WHERE tablename = 'crm_card_itens' AND policyname = 'crm_card_itens_service_all'
    ) THEN
        CREATE POLICY crm_card_itens_service_all
            ON crm_card_itens
            FOR ALL
            TO service_role
            USING (true)
            WITH CHECK (true);
    END IF;
END $$;

-- ─── SUPABASE STORAGE BUCKET: tao-crm-campos ──────────────────────────────────
-- Execute separadamente no Storage > New bucket se ainda não existir:
--   nome: tao-crm-campos
--   public: false  (acesso via URL assinada)
--   file size limit: 20 MB
--   allowed mime types: deixar em branco (aceita tudo)
--
-- Após criar o bucket, adicione a policy:
--   INSERT/SELECT/DELETE para service_role (anon não precisa de acesso)

-- ─── NOTA ─────────────────────────────────────────────────────────────────────
-- crm_card_itens.total é calculado e gravado pelo backend (PHP/JS) e NÃO é
-- uma coluna GENERATED ALWAYS AS, pois a REST API do Supabase não aceita INSERT
-- em colunas geradas. O cálculo segue a fórmula:
--   desconto_tipo = 'pct'   → total = qtd × preco × (1 - desc/100)
--   desconto_tipo = 'valor' → total = qtd × preco − desc
