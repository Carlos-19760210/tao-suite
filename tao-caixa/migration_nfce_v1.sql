-- Migration NFC-e (modelo 65) — base para emissão via gateway (Focus NFe).
-- Aditiva e idempotente. NÃO emite nada; só cria a estrutura.
-- Complementa: migration_fiscal_produtos_v1.sql (parametrização fiscal por produto).

-- 1) Config do emitente por cliente/workspace (dados e credencial do gateway).
--    O certificado A1 e o CSC ficam no painel do Focus; aqui guardamos o vínculo.
create table if not exists caixa_emitente_fiscal (
  id uuid primary key default gen_random_uuid(),
  cliente_id       uuid not null,
  workspace_id     uuid,
  cnpj             text,
  inscricao_estadual text,
  regime           text default 'simples',   -- 'simples' (CSOSN) | 'normal' (CST)
  gateway          text default 'focus',      -- fornecedor de emissão
  focus_token      text,                      -- token (segredo) — homologação/produção
  serie            text default '1',
  ambiente         text default 'homologacao',-- 'homologacao' | 'producao'
  ativo            boolean default false,     -- só emite quando ligado
  atualizado_em    timestamptz default now(),
  unique (cliente_id)
);
create index if not exists idx_caixa_emitente_ws on caixa_emitente_fiscal (workspace_id);

-- 2) Campos fiscais na venda (resultado da emissão NFC-e).
alter table caixa_vendas add column if not exists nfce_status      text;        -- pendente|processando|autorizada|rejeitada|cancelada|contingencia
alter table caixa_vendas add column if not exists nfce_ref         text;        -- referência única enviada ao gateway
alter table caixa_vendas add column if not exists nfce_chave       text;        -- chave de acesso (44 díg)
alter table caixa_vendas add column if not exists nfce_protocolo   text;
alter table caixa_vendas add column if not exists nfce_numero      integer;
alter table caixa_vendas add column if not exists nfce_serie       text;
alter table caixa_vendas add column if not exists nfce_danfe_url   text;        -- PDF/HTML com QR Code
alter table caixa_vendas add column if not exists nfce_xml_url     text;
alter table caixa_vendas add column if not exists nfce_erro        text;        -- motivo da rejeição
alter table caixa_vendas add column if not exists nfce_ambiente    text;        -- homologacao|producao no momento da emissão
alter table caixa_vendas add column if not exists nfce_emitido_em  timestamptz;
alter table caixa_vendas add column if not exists nfce_cancelado_em timestamptz;

create index if not exists idx_caixa_vendas_nfce_status on caixa_vendas (cliente_id, nfce_status);
