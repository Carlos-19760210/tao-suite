-- ============================================================================
-- migration_reconciliacao_schema_v1.sql
-- ----------------------------------------------------------------------------
-- Reconcilia o schema VERSIONADO com as colunas que o código já grava em
-- produção mas que foram aplicadas À MÃO no Supabase (sem migration).
-- Item 3 da auditoria FCerta × TAO Neo (15/07/2026).
--
-- Característica: ADITIVA e IDEMPOTENTE (ADD COLUMN IF NOT EXISTS).
--   • No banco ATUAL (produção): roda como NO-OP — as colunas já existem,
--     nada muda. Serve para VERSIONAR o que existe.
--   • Num banco DO ZERO: recria as colunas que faltariam.
--
-- NÃO altera lab_ordens.numero (é decisão de TIPO, tratada à parte — ver rodapé).
-- NÃO cria ativos.codigo (é bug de código: select deveria usar codigo_fc).
--
-- Tipos inferidos do uso real no PHP (POST/PATCH em tao-formula/includes/ajax.php).
-- ============================================================================

-- ── orcamentos ──────────────────────────────────────────────────────────────
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS numero_orcamento       text;         -- nº textual "202606-0001-01"
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS tipo_entrada           text;         -- origem do orçamento
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS forma_vol              numeric;      -- volume da forma
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS forma_unidade          text;         -- g / ml / caps
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS qtde_potes             integer;      -- nº de unidades/potes
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS acrescimo_aplicado     numeric;      -- acréscimo em R$
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS desconto_pct           numeric;      -- desconto %
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS desconto_fc            numeric;      -- desconto em R$
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS valor_final_fc         numeric;      -- valor FINAL (usado pelo Caixa/Kanban)
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS aprovado_em            timestamptz;  -- avaliação farmacêutica
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS enviado_em             timestamptz;  -- envio ao paciente
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS motivo_rejeicao        text;         -- motivo quando rejeitado
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS medicamento_controlado boolean DEFAULT false;  -- flag 344/98

-- ── formas_farmaceuticas ────────────────────────────────────────────────────
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS custo_fixo_tipo text;      -- 'R' (R$ fixo) | 'pct' (% s/ MP)
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS valor_minimo    numeric;   -- piso do orçamento p/ a forma
ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS modo_preparo    text;      -- modo de preparo padrão (RDC 67)

-- ── lab_ordens ──────────────────────────────────────────────────────────────
ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS modo_preparo text;                   -- herdado da forma na OM

-- ── lab_ordem_itens ─────────────────────────────────────────────────────────
ALTER TABLE lab_ordem_itens ADD COLUMN IF NOT EXISTS qtd_pesar      numeric;         -- quantidade a pesar (g)
ALTER TABLE lab_ordem_itens ADD COLUMN IF NOT EXISTS unid_pesar     text;            -- unidade de pesagem
ALTER TABLE lab_ordem_itens ADD COLUMN IF NOT EXISTS teor_aplic     numeric;         -- teor aplicado no cálculo (%)
ALTER TABLE lab_ordem_itens ADD COLUMN IF NOT EXISTS equiv_aplic    numeric;         -- fator de equivalência aplicado
ALTER TABLE lab_ordem_itens ADD COLUMN IF NOT EXISTS diluicao_aplic numeric;         -- fator de diluição aplicado

-- ── ativos ──────────────────────────────────────────────────────────────────
-- unidade_padrao: a migration_v1.1.0 cria o CHECK sobre esta coluna, mas nunca a
-- declarou (ADD COLUMN). Declaramos aqui. Num rebuild do zero, RODAR ESTA
-- RECONCILIAÇÃO ANTES da v1.1.0 (senão o CHECK falha por coluna inexistente).
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS unidade_padrao    text;                  -- mg/g/mcg/UI/un
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS custo_por_unidade numeric;               -- custo por unidade

-- ── lab_ordens.numero → mesmo número do orçamento (decisão Carlos, 15/07) ────
-- Hoje numero é bigserial (bigint) e não aceita o número TEXTUAL do orçamento
-- ("202606-0001-01"); o código cai num fallback e a OM ganha um sequencial próprio.
-- Convertendo para text, a OM passa a herdar EXATAMENTE o número do orçamento
-- aprovado. Conversão segura (int→text). Remove o default de sequência e o NOT NULL
-- (o número vem do orçamento; nullable é só salvaguarda p/ orçamento sem número —
-- evita bloquear a criação da OM). O código já grava numero_orcamento no campo;
-- após esta conversão, o insert do texto passa a funcionar e o fallback fica dormente.
ALTER TABLE lab_ordens ALTER COLUMN numero DROP DEFAULT;
ALTER TABLE lab_ordens ALTER COLUMN numero TYPE text USING numero::text;
ALTER TABLE lab_ordens ALTER COLUMN numero DROP NOT NULL;
-- (A sequência lab_ordens_numero_seq fica órfã e inócua; pode ser removida à parte.)
-- ============================================================================
