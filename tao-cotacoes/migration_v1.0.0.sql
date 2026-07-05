-- ============================================================
-- TAO Cotações — migration v1.0.0 (Fase 1)
-- Rodar no SQL Editor do Supabase (projeto gclayesytzzpzkjvgede)
-- ============================================================

-- Fornecedores de insumos (por cliente/tenant)
CREATE TABLE IF NOT EXISTS fornecedores (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id  uuid NOT NULL REFERENCES clientes(id),
    nome        text NOT NULL,
    whatsapp    text NOT NULL,
    contato     text,
    obs         text,
    ativo       boolean DEFAULT true,
    criado_em   timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_fornecedores_cliente ON fornecedores(cliente_id);

-- Cotações (cabeçalho)
CREATE TABLE IF NOT EXISTS cotacoes (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    numero       bigserial,
    cliente_id   uuid NOT NULL REFERENCES clientes(id),
    titulo       text,
    status       text NOT NULL DEFAULT 'rascunho',  -- rascunho|enviada|recebendo|concluida|cancelada
    instancia_id uuid,                              -- crm_instancias.id escolhida na criação
    criado_por   bigint,                            -- WP user id
    criado_em    timestamptz DEFAULT now(),
    enviado_em   timestamptz,
    concluido_em timestamptz
);
CREATE INDEX IF NOT EXISTS idx_cotacoes_cliente ON cotacoes(cliente_id, status);

-- Itens da cotação (ativo_id NULL = item livre, somente desta cotação)
CREATE TABLE IF NOT EXISTS cotacao_itens (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cotacao_id     uuid NOT NULL REFERENCES cotacoes(id) ON DELETE CASCADE,
    ativo_id       uuid REFERENCES ativos(id),
    codigo_fc      text,
    descricao      text NOT NULL,
    unidade        text,
    qtd            numeric DEFAULT 0,
    urgente        boolean DEFAULT false,
    ult_preco_pago numeric,                         -- benchmark (último pago) no momento da cotação
    origem         text DEFAULT 'planilha',         -- planilha|manual
    criado_em      timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_cotacao_itens_cot ON cotacao_itens(cotacao_id);

-- Fornecedores participantes de cada cotação
CREATE TABLE IF NOT EXISTS cotacao_fornecedores (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cotacao_id    uuid NOT NULL REFERENCES cotacoes(id) ON DELETE CASCADE,
    fornecedor_id uuid NOT NULL REFERENCES fornecedores(id),
    status        text NOT NULL DEFAULT 'pendente', -- pendente|enviado|erro|respondeu|processado
    msg_enviada   text,
    erro          text,
    card_id       uuid,
    enviado_em    timestamptz,
    respondeu_em  timestamptz,
    UNIQUE (cotacao_id, fornecedor_id)
);
CREATE INDEX IF NOT EXISTS idx_cotacao_forn_cot ON cotacao_fornecedores(cotacao_id);

-- Propostas recebidas (Fase 2 — criada já para não precisar de nova migration)
CREATE TABLE IF NOT EXISTS cotacao_propostas (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cotacao_id    uuid NOT NULL REFERENCES cotacoes(id) ON DELETE CASCADE,
    fornecedor_id uuid NOT NULL REFERENCES fornecedores(id),
    origem        text NOT NULL DEFAULT 'pdf',      -- pdf|imagem|manual
    arquivo_url   text,
    status        text NOT NULL DEFAULT 'pendente', -- pendente|processada|erro
    erro          text,
    processado_em timestamptz,
    criado_em     timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_cotacao_prop_cot ON cotacao_propostas(cotacao_id);

-- Preços extraídos/digitados — grão do comparativo (Fase 2)
CREATE TABLE IF NOT EXISTS cotacao_precos (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cotacao_id      uuid NOT NULL REFERENCES cotacoes(id) ON DELETE CASCADE,
    fornecedor_id   uuid NOT NULL REFERENCES fornecedores(id),
    proposta_id     uuid REFERENCES cotacao_propostas(id) ON DELETE SET NULL,
    cotacao_item_id uuid REFERENCES cotacao_itens(id) ON DELETE SET NULL,
    ativo_id        uuid REFERENCES ativos(id),
    item_original   text,                           -- texto como veio do fornecedor
    vl_unit         numeric,                        -- normalizado: R$/g, R$/ml ou R$/milheiro
    unid            text,                           -- g|ml|milheiro
    qtde_min        numeric,
    vl_total        numeric,
    validade        text,
    conversao       text,                           -- log da conversão de unidade aplicada
    criado_em       timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_cotacao_precos_cot ON cotacao_precos(cotacao_id);

-- Histórico de preços por ativo × fornecedor (alimentado a cada proposta processada)
CREATE TABLE IF NOT EXISTS precos_historico (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    ativo_id      uuid REFERENCES ativos(id),
    fornecedor_id uuid REFERENCES fornecedores(id),
    cotacao_id    uuid REFERENCES cotacoes(id) ON DELETE SET NULL,
    preco         numeric NOT NULL,
    unid          text,
    registrado_em timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_precos_hist_ativo ON precos_historico(cliente_id, ativo_id);

-- RLS (acesso só via service role, como as demais tabelas TAO)
ALTER TABLE fornecedores        ENABLE ROW LEVEL SECURITY;
ALTER TABLE cotacoes            ENABLE ROW LEVEL SECURITY;
ALTER TABLE cotacao_itens       ENABLE ROW LEVEL SECURITY;
ALTER TABLE cotacao_fornecedores ENABLE ROW LEVEL SECURITY;
ALTER TABLE cotacao_propostas   ENABLE ROW LEVEL SECURITY;
ALTER TABLE cotacao_precos      ENABLE ROW LEVEL SECURITY;
ALTER TABLE precos_historico    ENABLE ROW LEVEL SECURITY;

-- Validação: deve retornar 7 linhas
SELECT table_name FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_name IN ('fornecedores','cotacoes','cotacao_itens','cotacao_fornecedores',
                     'cotacao_propostas','cotacao_precos','precos_historico')
ORDER BY table_name;
