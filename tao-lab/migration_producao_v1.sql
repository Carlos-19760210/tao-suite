-- ============================================================
-- TAO Lab — PACOTE 3 / Produção: Ordem de Manipulação (OM) + kanban
-- Espelha FC12100/FC12110 (OM+itens c/ lote e pesagem) + FC12500 (etapas).
-- Reusa prescritores (migration_v3) e lab_lotes_mp (migration_motor_v1).
-- Rodar no SQL Editor. Aditiva.
-- ============================================================

-- Etapas de produção (kanban do laboratório) — FC12500
CREATE TABLE IF NOT EXISTS lab_etapas (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id uuid NOT NULL REFERENCES clientes(id),
    nome       text NOT NULL,
    ordem      int  NOT NULL DEFAULT 0,
    tipo       text DEFAULT 'normal',   -- normal|final|cancelada
    ativo      boolean DEFAULT true
);

-- Ordem de Manipulação — nasce do orçamento aprovado
CREATE TABLE IF NOT EXISTS lab_ordens (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    numero         bigserial,
    cliente_id     uuid NOT NULL REFERENCES clientes(id),
    orcamento_id   uuid REFERENCES orcamentos(id),
    card_id        uuid,
    contato_id     uuid REFERENCES crm_contatos(id),  -- paciente = cadastro único
    paciente_nome  text NOT NULL,
    paciente_whats text,
    prescritor_id  uuid REFERENCES prescritores(id),
    receita_url    text,
    posologia      text,
    tp_uso         text,                 -- interno|externo|veterinario
    forma_farmac   text,
    volume         numeric,
    unidade_vol    text,
    qtd_unidades   numeric,
    dt_manipulacao date,
    dt_validade    date,
    etapa_id       uuid REFERENCES lab_etapas(id),
    status         text DEFAULT 'aberta', -- aberta|concluida|cancelada
    controlado     boolean DEFAULT false, -- item Port.344 → gatilho SNGPC (fase 4)
    baixou_estoque boolean DEFAULT false, -- idempotência da baixa
    manipulador_id bigint,
    conferente_id  bigint,
    criado_por     bigint,
    criado_em      timestamptz DEFAULT now(),
    concluido_em   timestamptz
);
CREATE INDEX IF NOT EXISTS idx_lab_ordens ON lab_ordens (cliente_id, status, etapa_id);

-- Itens da OM — pesagem + lote por componente (rastreabilidade FC12110)
CREATE TABLE IF NOT EXISTS lab_ordem_itens (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ordem_id      uuid NOT NULL REFERENCES lab_ordens(id) ON DELETE CASCADE,
    ativo_id      uuid REFERENCES ativos(id),
    descricao     text NOT NULL,
    qtd_prescrita numeric,
    unidade       text,
    qtd_pesada    numeric,
    lote_mp_id    uuid REFERENCES lab_lotes_mp(id),   -- lote usado = rastreio
    eh_qsp        boolean DEFAULT false,
    custo         numeric,
    pesado_por    bigint,
    pesado_em     timestamptz,
    ordem         int DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_lab_itens_ordem ON lab_ordem_itens (ordem_id);
CREATE INDEX IF NOT EXISTS idx_lab_itens_lote  ON lab_ordem_itens (lote_mp_id);

-- Auditoria do fluxo entre etapas
CREATE TABLE IF NOT EXISTS lab_ordem_etapas (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ordem_id   uuid NOT NULL REFERENCES lab_ordens(id) ON DELETE CASCADE,
    de_etapa   uuid,
    para_etapa uuid,
    usuario_id bigint,
    obs        text,
    criado_em  timestamptz DEFAULT now()
);

-- Rótulo (RDC 67) — template + emitidos
CREATE TABLE IF NOT EXISTS lab_rotulo_templates (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id uuid NOT NULL REFERENCES clientes(id),
    nome       text NOT NULL,
    conteudo   text NOT NULL,
    largura_mm int, altura_mm int,
    ativo      boolean DEFAULT true
);
CREATE TABLE IF NOT EXISTS lab_rotulos (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ordem_id    uuid NOT NULL REFERENCES lab_ordens(id) ON DELETE CASCADE,
    template_id uuid REFERENCES lab_rotulo_templates(id),
    texto       text NOT NULL,
    impresso_em timestamptz,
    impresso_por bigint
);

ALTER TABLE lab_etapas           ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_ordens           ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_ordem_itens      ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_ordem_etapas     ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_rotulo_templates ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_rotulos          ENABLE ROW LEVEL SECURITY;

-- Etapas padrão do laboratório (seed) — cria só se o tenant ainda não tem
INSERT INTO lab_etapas (cliente_id, nome, ordem, tipo)
SELECT '62f98634-77ff-42f4-acaf-8561d56583da', v.nome, v.ord, v.tipo
FROM (VALUES
    ('Conferência', 1, 'normal'),
    ('Pesagem',      2, 'normal'),
    ('Manipulação',  3, 'normal'),
    ('Envase',       4, 'normal'),
    ('Rotulagem',    5, 'normal'),
    ('CQ / Liberação', 6, 'normal'),
    ('Pronto p/ Entrega', 7, 'final'),
    ('Entregue',     8, 'final')
) AS v(nome, ord, tipo)
WHERE NOT EXISTS (SELECT 1 FROM lab_etapas WHERE cliente_id='62f98634-77ff-42f4-acaf-8561d56583da');

-- Validação
SELECT table_name FROM information_schema.tables WHERE table_schema='public'
 AND table_name IN ('lab_etapas','lab_ordens','lab_ordem_itens','lab_ordem_etapas','lab_rotulo_templates','lab_rotulos')
ORDER BY table_name;
