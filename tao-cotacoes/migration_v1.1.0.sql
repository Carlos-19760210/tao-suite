-- ============================================================
-- TAO Cotações — migration v1.1.0
-- Chat próprio do módulo: conversa por FORNECEDOR, fora do CRM/Kanban
-- Rodar no SQL Editor do Supabase
-- ============================================================

CREATE TABLE IF NOT EXISTS fornecedor_mensagens (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    fornecedor_id uuid NOT NULL REFERENCES fornecedores(id) ON DELETE CASCADE,
    cotacao_id    uuid REFERENCES cotacoes(id) ON DELETE SET NULL, -- etiqueta: cotação aberta no momento da msg
    instancia_id  uuid,                                            -- crm_instancias por onde a msg passou
    direcao       text NOT NULL,                                   -- in|out
    tipo          text DEFAULT 'text',                             -- text|image|audio|document|video|sticker
    conteudo      text,
    midia_url     text,
    midia_mime    text,
    enviado_por   bigint,                                          -- WP user (msgs out pelo painel)
    lida          boolean DEFAULT false,
    criado_em     timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_forn_msgs_forn   ON fornecedor_mensagens(fornecedor_id, criado_em);
CREATE INDEX IF NOT EXISTS idx_forn_msgs_unread ON fornecedor_mensagens(cliente_id, fornecedor_id)
    WHERE direcao = 'in' AND lida = false;

ALTER TABLE fornecedor_mensagens ENABLE ROW LEVEL SECURITY;

-- Validação: deve retornar 1 linha
SELECT table_name FROM information_schema.tables
WHERE table_schema = 'public' AND table_name = 'fornecedor_mensagens';
