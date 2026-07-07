-- ============================================================
-- TAO Lab — HISTÓRICO DO CLIENTE (consulta + repetição)
-- Fonte: FCerta FC07000 (clientes) + FC12100/FC12110 (requisições)
-- Rodar no SQL Editor do Supabase. Aditivo: não toca em nada existente.
-- ============================================================

-- 1. Clientes do histórico FCerta (17.786 na Magis; só 89 têm fone → busca é por NOME)
CREATE TABLE IF NOT EXISTS hist_clientes (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id  uuid NOT NULL REFERENCES clientes(id),
    cdcli       int  NOT NULL,                 -- código do cliente no FCerta
    nome        text NOT NULL,
    dt_nascimento date,
    email       text,
    observacoes text,
    criado_em   timestamptz DEFAULT now(),
    UNIQUE (cliente_id, cdcli)
);
CREATE INDEX IF NOT EXISTS idx_hist_cli_nome ON hist_clientes (cliente_id, nome);

-- 2. Fórmulas produzidas (requisições FC12100 — o que foi de fato vendido/manipulado)
CREATE TABLE IF NOT EXISTS hist_formulas (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id      uuid NOT NULL REFERENCES clientes(id),
    hist_cliente_id uuid REFERENCES hist_clientes(id),   -- NULL = req sem CDCLI (busca por nome_paciente)
    cdcli           int,
    nrrqu           int NOT NULL,
    serier          int NOT NULL DEFAULT 0,              -- série da repetição no FCerta
    nrorc           int,
    dt_cadastro     date,
    dt_retirada     date,
    volume          numeric,
    univol          text,                                -- CAP/G/ML/UN... (unidade FCerta)
    qt_potes        int,
    posologia       text,
    nome_paciente   text,
    preco_cobrado   numeric,
    preco_custo     numeric,
    ind_repet       boolean DEFAULT false,               -- já era uma repetição no FCerta
    dt_validade     date,
    criado_em       timestamptz DEFAULT now(),
    UNIQUE (cliente_id, nrrqu, serier)
);
CREATE INDEX IF NOT EXISTS idx_hist_form_cli  ON hist_formulas (cliente_id, hist_cliente_id, dt_cadastro DESC);
CREATE INDEX IF NOT EXISTS idx_hist_form_nome ON hist_formulas (cliente_id, nome_paciente);

-- 3. Itens (FC12110 TPCMP C=componente, E=embalagem, P=cápsula; explosão R fica no FCerta)
CREATE TABLE IF NOT EXISTS hist_formulas_itens (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    formula_id uuid NOT NULL REFERENCES hist_formulas(id) ON DELETE CASCADE,
    tpcmp      text,          -- C | E | P
    codigo_fc  text,          -- casa com ativos.codigo_fc do sync
    descr      text,
    dose       numeric,
    unidade    text,          -- MG/G/MCG/ML/%/UI/CAP/UN...
    qt_real    numeric,       -- pesagem real registrada no FCerta
    is_qsp     boolean DEFAULT false,
    ordem      int DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_hist_itens_form ON hist_formulas_itens (formula_id);

ALTER TABLE hist_clientes        ENABLE ROW LEVEL SECURITY;
ALTER TABLE hist_formulas        ENABLE ROW LEVEL SECURITY;
ALTER TABLE hist_formulas_itens  ENABLE ROW LEVEL SECURITY;

-- Validação: deve listar as 3 tabelas
SELECT table_name FROM information_schema.tables WHERE table_schema='public'
 AND table_name IN ('hist_clientes','hist_formulas','hist_formulas_itens')
ORDER BY table_name;
