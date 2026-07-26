-- Recebimento de NF (entrada) — Fase 2. Aditiva e idempotente.

-- De/para com APRENDIZADO: fornecedor+código do produto -> ativo do TAO.
create table if not exists recebimento_depara (
  id uuid primary key default gen_random_uuid(),
  cliente_id      uuid not null,
  fornecedor_cnpj text not null,
  cprod           text not null,          -- código do produto no fornecedor
  ativo_id        uuid,                   -- ativo do TAO associado
  ignorar         boolean default false,  -- item que não estoca (frete/serviço/bonif.)
  atualizado_em   timestamptz default now(),
  unique (cliente_id, fornecedor_cnpj, cprod)
);
create index if not exists idx_receb_depara_cli on recebimento_depara (cliente_id, fornecedor_cnpj);

-- Cabeçalho do recebimento processado.
create table if not exists recebimento_nf (
  id uuid primary key default gen_random_uuid(),
  cliente_id      uuid not null,
  fornecedor_cnpj text, fornecedor_nome text,
  numero text, serie text, chave text, emissao date,
  valor_produtos numeric, valor_frete numeric, valor_total numeric,
  status text default 'processado',
  criado_por bigint, criado_em timestamptz default now(),
  unique (cliente_id, chave)
);

-- Itens do recebimento (com os 3 valores por unidade).
create table if not exists recebimento_nf_itens (
  id uuid primary key default gen_random_uuid(),
  recebimento_id uuid not null, cliente_id uuid,
  ativo_id uuid, cprod text, descricao text, ncm text,
  lote text, validade date, quantidade numeric, unidade text,
  valor_custo numeric,          -- valor de mercado (referência)
  valor_compra numeric,         -- efetivamente pago, SEM frete
  frete_rateado numeric,        -- frete rateado por valor (unitário)
  valor_compra_frete numeric,   -- compra + frete = BASE do preço de venda
  criado_em timestamptz default now()
);

-- Contas a pagar (parcelas da NF). Completa o "sai" no Caixa.
create table if not exists contas_a_pagar (
  id uuid primary key default gen_random_uuid(),
  cliente_id uuid not null, fornecedor_cnpj text, fornecedor_nome text,
  recebimento_id uuid, origem text default 'nf_entrada',
  parcela text, vencimento date, valor numeric,
  status text default 'aberto', pago_em timestamptz,
  criado_em timestamptz default now()
);
create index if not exists idx_cap_cli_venc on contas_a_pagar (cliente_id, status, vencimento);

-- Base do preço de venda no ativo = valor de compra com frete (do último recebimento).
alter table ativos add column if not exists custo_com_frete numeric;
alter table ativos add column if not exists custo_com_frete_em timestamptz;
