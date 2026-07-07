-- ============================================================
-- TAO Lab — PACOTE 1: Motor Farmacotécnico
-- PROPOSTA — rodar SOMENTE após OK formal do Carlos
-- ============================================================

-- 1. Dados técnicos por ativo (defaults neutros: nada muda até a carga)
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS dcb              text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS fator_correcao   numeric DEFAULT 1;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS teor_pct         numeric DEFAULT 100;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS densidade        numeric DEFAULT 1;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS fator_diluicao   numeric DEFAULT 1;   -- >1 = MP diluída (1:N)
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS ativo_puro_id    uuid REFERENCES ativos(id);
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS ui_por_g         numeric;             -- UI por grama
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS meq_por_g        numeric;             -- mEq por grama
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS ufc_bi_por_g     numeric;             -- bilhões UFC por grama
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS dose_max_dia     numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS dose_max_unidade text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS restricao        text;                -- NULL|bloqueada|restrita|controlada_344:<lista>

-- 2. Equivalência sal<->base pelo nome prescrito
ALTER TABLE ativos_sinonimos ADD COLUMN IF NOT EXISTS fator_equiv numeric DEFAULT 1;

-- 3. Lotes de MP — laudo real por lote + CQ de recebimento + produção interna
CREATE TABLE IF NOT EXISTS lab_lotes_mp (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id      uuid NOT NULL REFERENCES clientes(id),
    ativo_id        uuid NOT NULL REFERENCES ativos(id),
    nr_lote         text NOT NULL,                    -- lote do fabricante (ou interno, se produção)
    lote_interno    text,                             -- controle sequencial da farmácia
    origem          text NOT NULL DEFAULT 'fornecedor', -- fornecedor|producao_interna
    lote_puro_id    uuid REFERENCES lab_lotes_mp(id), -- diluição interna: lote puro de origem
    fornecedor_id   uuid REFERENCES fornecedores(id),
    nf_numero       text,
    nf_chave        text,
    fabricante      text,
    dt_fabricacao   date,
    dt_validade     date NOT NULL,
    qtd_inicial     numeric NOT NULL,
    qtd_atual       numeric NOT NULL,
    unidade         text NOT NULL DEFAULT 'g',
    -- laudo real do lote (prevalece sobre o nominal do ativo no cálculo)
    teor_pct        numeric,
    densidade       numeric,
    fator_diluicao  numeric,
    ui_por_g        numeric,
    meq_por_g       numeric,
    ufc_bi_por_g    numeric,
    laudo_url       text,
    -- CQ de recebimento (RDC 67)
    status          text NOT NULL DEFAULT 'quarentena', -- quarentena|aprovado|reprovado|esgotado|vencido
    qc_resultado    text,
    qc_aprovado_por bigint,
    qc_em           timestamptz,
    criado_em       timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_lab_lotes_ativo ON lab_lotes_mp(cliente_id, ativo_id, status);

-- 4. Produção interna de diluição (consome lote puro -> gera lote diluído)
CREATE TABLE IF NOT EXISTS lab_producoes_internas (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    numero          bigserial,
    cliente_id      uuid NOT NULL REFERENCES clientes(id),
    ativo_id        uuid NOT NULL REFERENCES ativos(id),       -- a MP diluída produzida
    lote_gerado_id  uuid REFERENCES lab_lotes_mp(id),
    lote_puro_id    uuid REFERENCES lab_lotes_mp(id),
    qtd_puro        numeric NOT NULL,
    veiculo_ativo_id uuid REFERENCES ativos(id),
    lote_veiculo_id uuid REFERENCES lab_lotes_mp(id),
    qtd_veiculo     numeric,
    qtd_produzida   numeric NOT NULL,
    unidade         text NOT NULL DEFAULT 'g',
    dt_producao     date NOT NULL,
    dt_validade     date NOT NULL,
    produzido_por   bigint,
    conferido_por   bigint,
    obs             text,
    criado_em       timestamptz DEFAULT now()
);

-- 5. Fórmulas padrão (FC05000/FC05100 — 734 na Magis)
CREATE TABLE IF NOT EXISTS lab_formulas_padrao (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id   uuid NOT NULL REFERENCES clientes(id),
    nome         text NOT NULL,
    forma_farmac text,
    volume       numeric,
    unidade      text,
    tipo_capsula text,
    posologia    text,
    tem_qsp      boolean DEFAULT false,
    obs          text,
    ativo        boolean DEFAULT true,
    criado_em    timestamptz DEFAULT now()
);
CREATE TABLE IF NOT EXISTS lab_formulas_padrao_itens (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    formula_id uuid NOT NULL REFERENCES lab_formulas_padrao(id) ON DELETE CASCADE,
    ativo_id   uuid REFERENCES ativos(id),
    descricao  text NOT NULL,
    qtd        numeric,
    unidade    text,
    eh_qsp     boolean DEFAULT false,
    ordem      int DEFAULT 0
);

ALTER TABLE lab_lotes_mp            ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_producoes_internas  ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_formulas_padrao     ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_formulas_padrao_itens ENABLE ROW LEVEL SECURITY;

-- Validação: deve listar as 4 tabelas novas
SELECT table_name FROM information_schema.tables WHERE table_schema='public'
 AND table_name IN ('lab_lotes_mp','lab_producoes_internas','lab_formulas_padrao','lab_formulas_padrao_itens')
ORDER BY table_name;
