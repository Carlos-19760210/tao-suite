-- TAO CRM — Tabelas Supabase
-- Execute no SQL Editor do Supabase (https://supabase.com/dashboard → SQL Editor)
-- Não modifica nenhuma tabela existente

-- ── Workspaces ───────────────────────────────────────────────────────────────
create table if not exists crm_workspaces (
    id                   uuid default gen_random_uuid() primary key,
    cliente_id           uuid references clientes(id) on delete set null,
    nome                 text not null,
    evolution_url        text,
    evolution_key        text,
    evolution_instancia  text,
    ativo                boolean default true,
    criado_em            timestamptz default now()
);

-- ── Pipelines ────────────────────────────────────────────────────────────────
create table if not exists crm_pipelines (
    id            uuid default gen_random_uuid() primary key,
    workspace_id  uuid not null references crm_workspaces(id) on delete cascade,
    nome          text not null,
    ordem         int  default 0,
    ativo         boolean default true,
    criado_em     timestamptz default now()
);

-- ── Estágios ─────────────────────────────────────────────────────────────────
create table if not exists crm_estagios (
    id            uuid default gen_random_uuid() primary key,
    pipeline_id   uuid not null references crm_pipelines(id) on delete cascade,
    nome          text not null,
    cor           text default '#6366f1',
    tipo          text default 'normal' check (tipo in ('normal','won','lost')),
    ordem         int  default 0,
    criado_em     timestamptz default now()
);

-- ── Cards ─────────────────────────────────────────────────────────────────────
create table if not exists crm_cards (
    id                  uuid default gen_random_uuid() primary key,
    workspace_id        uuid not null references crm_workspaces(id) on delete cascade,
    pipeline_id         uuid not null references crm_pipelines(id) on delete cascade,
    estagio_id          uuid not null references crm_estagios(id) on delete restrict,
    titulo              text,
    contato_nome        text,
    contato_whatsapp    text,
    responsavel_id      bigint,       -- WordPress user ID
    criado_em           timestamptz default now(),
    movido_em           timestamptz default now()
);

create index if not exists idx_crm_cards_pipeline  on crm_cards(pipeline_id);
create index if not exists idx_crm_cards_estagio   on crm_cards(estagio_id);
create index if not exists idx_crm_cards_workspace on crm_cards(workspace_id);

-- ── Mensagens ─────────────────────────────────────────────────────────────────
create table if not exists crm_mensagens (
    id              uuid default gen_random_uuid() primary key,
    card_id         uuid not null references crm_cards(id) on delete cascade,
    workspace_id    uuid not null references crm_workspaces(id) on delete cascade,
    direcao         text default 'in' check (direcao in ('in','out')),
    tipo            text default 'text' check (tipo in ('text','image','audio','document','video','sticker')),
    conteudo        text,
    midia_url       text,
    remetente_nome  text,
    enviado_em      timestamptz default now()
);

create index if not exists idx_crm_mensagens_card on crm_mensagens(card_id, enviado_em);

-- ── Histórico de movimentações ────────────────────────────────────────────────
create table if not exists crm_cards_historico (
    id                uuid default gen_random_uuid() primary key,
    card_id           uuid not null references crm_cards(id) on delete cascade,
    de_estagio_id     uuid,
    para_estagio_id   uuid references crm_estagios(id) on delete set null,
    usuario_id        bigint,
    obs               text,
    criado_em         timestamptz default now()
);

create index if not exists idx_crm_historico_card on crm_cards_historico(card_id, criado_em desc);
