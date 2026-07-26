-- SNGPC (controlados / ANVISA) — TAO Caixa. Aditiva e idempotente.
-- Estratégia SOMBRA: conviver com o sistema atual (importar o XML gerado por ele)
-- até a geração nativa bater 100%; transmissão fica INERTE atrás de flag (ativo=false).

-- Configuração do emissor/transmissor. Transmissão desligada por padrão.
create table if not exists caixa_sngpc_config (
  cliente_id      uuid primary key,
  cnpj_emissor    text,
  cpf_transmissor text,
  usuario         text,            -- usuário do webservice SNGPC (transmissão)
  senha           text,            -- senha do webservice (guardar cifrada em produção)
  ambiente        text default 'homologacao',
  ativo           boolean not null default false,  -- FLAG: liga a transmissão
  atualizado_em   timestamptz default now()
);

-- Fechamentos por período (ciclo de até 5 dias). Guarda o XML e o resumo.
create table if not exists caixa_sngpc_fechamentos (
  id uuid primary key default gen_random_uuid(),
  cliente_id       uuid not null,
  data_inicio      date not null,
  data_fim         date not null,
  origem           text not null default 'importado', -- importado | gerado
  qtd_saidas       int  default 0,   -- saidaInsumoVendaAoConsumidor
  qtd_perdas       int  default 0,   -- saidaInsumoPerda
  qtd_entradas     int  default 0,   -- entradaInsumo
  qtd_medicamentos int  default 0,   -- movimentacao de medicamento
  status           text not null default 'rascunho', -- rascunho | validado | transmitido
  protocolo        text,
  xml              text,
  criado_por       bigint,
  criado_em        timestamptz default now(),
  transmitido_em   timestamptz
);
create index if not exists idx_sngpc_fech_cli on caixa_sngpc_fechamentos (cliente_id, data_inicio desc);
