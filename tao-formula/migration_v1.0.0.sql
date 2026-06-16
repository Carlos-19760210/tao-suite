-- TAO Fórmulas — Migration v1.0.0
-- Executar no Supabase SQL Editor
-- Seguro para re-executar: usa IF NOT EXISTS em tudo

-- ════════════════════════════════════════════════════════════
-- 1. ATIVOS (matérias-primas importadas do Firebird)
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS ativos (
    id              uuid        DEFAULT gen_random_uuid() PRIMARY KEY,
    cliente_id      uuid        NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    nome            text        NOT NULL,
    ativo           boolean     NOT NULL DEFAULT true,
    criado_em       timestamptz NOT NULL DEFAULT now()
);

-- Adicionar todas as colunas (seguro se já existirem)
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS codigo_fc       text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS nome_original   text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS unidade         text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS estoque_atual   numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS preco_compra    numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS preco_custo     numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS preco_venda     numeric NOT NULL DEFAULT 0;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS categoria       text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS principio_ativo text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS densidade       numeric NOT NULL DEFAULT 1;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS fator_correcao  numeric NOT NULL DEFAULT 1;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS dcb             text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS dose_min        numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS uni_dose_min    text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS dose_max        numeric;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS uni_dose_max    text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS observacoes     text;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS sincronizado_em timestamptz;

CREATE UNIQUE INDEX IF NOT EXISTS idx_ativos_cliente_codigo ON ativos(cliente_id, codigo_fc);
CREATE        INDEX IF NOT EXISTS idx_ativos_cliente_nome   ON ativos(cliente_id, nome);

-- ════════════════════════════════════════════════════════════
-- 2. FORMAS FARMACÊUTICAS (com custo_fixo configurável)
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS formas_farmaceuticas (
    id         uuid        DEFAULT gen_random_uuid() PRIMARY KEY,
    cliente_id uuid        NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    nome       text        NOT NULL,
    ativo      boolean     NOT NULL DEFAULT true,
    criado_em  timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS tipo           text    NOT NULL DEFAULT 'gel';
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS volume         numeric;
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS unidade_volume text    DEFAULT 'g';
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS n_capsulas     integer;
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS custo_fixo     numeric NOT NULL DEFAULT 0;
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS margem_pct     numeric NOT NULL DEFAULT 30;
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS atualizado_em  timestamptz NOT NULL DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_formas_cliente ON formas_farmaceuticas(cliente_id);

-- ════════════════════════════════════════════════════════════
-- 3. ORÇAMENTOS
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS orcamentos (
    id         uuid        DEFAULT gen_random_uuid() PRIMARY KEY,
    cliente_id uuid        NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    status     text        NOT NULL DEFAULT 'pendente_revisao',
    criado_em  timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS whatsapp            text;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS nome_paciente       text;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS receita_url         text;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS texto_receita       text;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS itens               jsonb;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS forma_id            uuid REFERENCES formas_farmaceuticas(id);
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS forma_nome          text;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS custo_fixo_aplicado numeric;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS total_insumos       numeric;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS total_orcamento     numeric;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS margem_aplicada     numeric;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS observacoes         text;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS farmaceutico_id     bigint;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS atualizado_em       timestamptz NOT NULL DEFAULT now();

CREATE INDEX IF NOT EXISTS idx_orcamentos_cliente_status ON orcamentos(cliente_id, status);
CREATE INDEX IF NOT EXISTS idx_orcamentos_criado_em      ON orcamentos(criado_em DESC);

-- ════════════════════════════════════════════════════════════
-- 4. RECEITA LOGS (auditoria IA Vision)
-- ════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS receita_logs (
    id         uuid        DEFAULT gen_random_uuid() PRIMARY KEY,
    cliente_id uuid        NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    sucesso    boolean     NOT NULL DEFAULT false,
    criado_em  timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE receita_logs ADD COLUMN IF NOT EXISTS orcamento_id      uuid REFERENCES orcamentos(id) ON DELETE SET NULL;
ALTER TABLE receita_logs ADD COLUMN IF NOT EXISTS ia_provider       text;
ALTER TABLE receita_logs ADD COLUMN IF NOT EXISTS ia_modelo         text;
ALTER TABLE receita_logs ADD COLUMN IF NOT EXISTS prompt_tokens     integer;
ALTER TABLE receita_logs ADD COLUMN IF NOT EXISTS completion_tokens integer;
ALTER TABLE receita_logs ADD COLUMN IF NOT EXISTS resposta_raw      text;

-- ════════════════════════════════════════════════════════════
-- 5. RLS
-- ════════════════════════════════════════════════════════════
ALTER TABLE ativos               ENABLE ROW LEVEL SECURITY;
ALTER TABLE formas_farmaceuticas ENABLE ROW LEVEL SECURITY;
ALTER TABLE orcamentos           ENABLE ROW LEVEL SECURITY;
ALTER TABLE receita_logs         ENABLE ROW LEVEL SECURITY;
