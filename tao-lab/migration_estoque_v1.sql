-- ============================================================
-- TAO Lab — PACOTE 2 / FATIA 1: ENTRADA DE NF + estrutura de estoque
-- Desenho aprovado (PACOTE_2_ESTOQUE.md). Rodar no SQL Editor. Aditiva.
-- Preparado p/ filiais (cd_filial default 1; Magis não usa hoje).
-- lab_lotes_mp já existe (recebe os lotes ao efetivar a NF).
-- ============================================================

-- 1. NF de entrada — cabeçalho
CREATE TABLE IF NOT EXISTS estoque_entradas_nf (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id     uuid NOT NULL REFERENCES clientes(id),
    cd_filial      int  NOT NULL DEFAULT 1,
    fornecedor_id  uuid REFERENCES fornecedores(id),
    cnpj_emitente  text,
    chave_nfe      text,                 -- 44 dígitos
    numero         text,
    serie          text,
    dt_emissao     date,
    dt_entrada     date NOT NULL DEFAULT current_date,
    valor_total    numeric,
    status         text NOT NULL DEFAULT 'conferindo', -- conferindo|efetivada|cancelada
    xml_url        text,
    obs            text,
    criado_por     bigint,
    criado_em      timestamptz DEFAULT now(),
    efetivada_em   timestamptz,
    UNIQUE (cliente_id, chave_nfe)
);
CREATE INDEX IF NOT EXISTS idx_ent_nf_cli ON estoque_entradas_nf (cliente_id, status, dt_entrada DESC);

-- 2. Itens da NF (com laudo/grupo K e destino do valor)
CREATE TABLE IF NOT EXISTS estoque_entradas_nf_itens (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    entrada_id     uuid NOT NULL REFERENCES estoque_entradas_nf(id) ON DELETE CASCADE,
    ativo_id       uuid REFERENCES ativos(id),   -- casado na conferência (via de-para)
    cod_fornecedor text,                          -- cProd do XML (sem EAN)
    descr_xml      text,
    quantidade     numeric,
    unidade        text,
    preco_unit     numeric,
    desconto       numeric DEFAULT 0,
    -- laudo / rastreabilidade
    lote           text,
    dt_fab         date,
    dt_val         date,
    teor           numeric,
    densidade      numeric,
    diluicao       numeric,
    -- destino do valor: default 'compra' (Carlos)
    destino_valor  text NOT NULL DEFAULT 'compra', -- custo|compra|ambos
    ordem          int DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_ent_itens ON estoque_entradas_nf_itens (entrada_id);

-- 3. De-para aprendido (fornecedor + código do item no XML -> ativo). Sem EAN (Carlos).
CREATE TABLE IF NOT EXISTS estoque_forn_depara (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id      uuid NOT NULL REFERENCES clientes(id),
    fornecedor_id   uuid NOT NULL REFERENCES fornecedores(id),
    cod_fornecedor  text NOT NULL,
    descr_fornecedor text,
    ativo_id        uuid NOT NULL REFERENCES ativos(id),
    criado_em       timestamptz DEFAULT now(),
    UNIQUE (cliente_id, fornecedor_id, cod_fornecedor)   -- 1 associação por código/fornecedor
);

-- 4. Kardex de movimentos (entrada/saída/ajuste/perda/transferência)
CREATE TABLE IF NOT EXISTS estoque_movimentos (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id   uuid NOT NULL REFERENCES clientes(id),
    cd_filial    int  NOT NULL DEFAULT 1,
    ativo_id     uuid NOT NULL REFERENCES ativos(id),
    lote_id      uuid REFERENCES lab_lotes_mp(id),
    tipo         text NOT NULL,          -- entrada|saida|ajuste|perda|transferencia
    quantidade   numeric NOT NULL,       -- + entra / - sai
    origem       text,                   -- nf|om|inventario|manual
    ref_id       uuid,                   -- id da entrada_nf / om / inventário
    saldo_apos   numeric,
    usuario_id   bigint,
    criado_em    timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_estq_mov ON estoque_movimentos (cliente_id, ativo_id, criado_em DESC);

-- 5. Contas a pagar (duplicatas da NF) — fica no TAO + relatório ao contador (Carlos)
CREATE TABLE IF NOT EXISTS contas_pagar (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id     uuid NOT NULL REFERENCES clientes(id),
    fornecedor_id  uuid REFERENCES fornecedores(id),
    entrada_nf_id  uuid REFERENCES estoque_entradas_nf(id),
    numero_dup     text,
    vencimento     date,
    valor          numeric NOT NULL,
    status         text NOT NULL DEFAULT 'aberto', -- aberto|pago|cancelado
    dt_pagamento   date,
    obs            text,
    criado_em      timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_cpagar ON contas_pagar (cliente_id, status, vencimento);

-- 6. Estoque mínimo/curva por ativo (alerta de reposição → Cotações)
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS est_min numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS est_max numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS curva   text;   -- A|B|C

ALTER TABLE estoque_entradas_nf       ENABLE ROW LEVEL SECURITY;
ALTER TABLE estoque_entradas_nf_itens ENABLE ROW LEVEL SECURITY;
ALTER TABLE estoque_forn_depara       ENABLE ROW LEVEL SECURITY;
ALTER TABLE estoque_movimentos        ENABLE ROW LEVEL SECURITY;
ALTER TABLE contas_pagar              ENABLE ROW LEVEL SECURITY;

-- Validação: deve listar as 5 tabelas novas
SELECT table_name FROM information_schema.tables WHERE table_schema='public'
 AND table_name IN ('estoque_entradas_nf','estoque_entradas_nf_itens','estoque_forn_depara','estoque_movimentos','contas_pagar')
ORDER BY table_name;
