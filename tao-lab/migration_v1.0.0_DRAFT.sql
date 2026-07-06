-- ============================================================
-- TAO Lab — migration v1.0.0 (DRAFT p/ revisão — NÃO RODAR ainda)
-- Substitui o laboratório do Formula Certa: OM + etapas + lotes +
-- rótulo + prescritores (ranking/visitação/comissão).
-- Espelha o modelo provado do FCerta: FC12100/12110 (OM+itens c/ lote
-- e pesagem), FC12500 (etapas), FC12300 (rótulo), FC038A2 (lotes
-- internos), FC04000/04200 (prescritores + visitação).
-- ============================================================

-- Prescritores (médicos, dentistas, veterinários, nutricionistas)
CREATE TABLE IF NOT EXISTS prescritores (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    nome          text NOT NULL,
    conselho      text NOT NULL DEFAULT 'CRM',   -- CRM|CRO|CRMV|CRN|OUTRO
    nr_conselho   text NOT NULL,
    uf_conselho   text NOT NULL,
    especialidade text,
    whatsapp      text,
    email         text,
    endereco      jsonb,
    comissao_pct  numeric DEFAULT 0,             -- capacidade de repasse (hoje 0 p/ todos, herdado do FCerta)
    obs           text,
    ativo         boolean DEFAULT true,
    criado_em     timestamptz DEFAULT now(),
    UNIQUE (cliente_id, conselho, nr_conselho, uf_conselho)
);

-- Visitação a prescritores (relacionamento — FC04200 tem 4.146 registros na Magis)
CREATE TABLE IF NOT EXISTS prescritor_visitas (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    prescritor_id uuid NOT NULL REFERENCES prescritores(id) ON DELETE CASCADE,
    data_visita   date NOT NULL,
    usuario_id    bigint,                        -- quem visitou (WP user)
    obs           text,
    criado_em     timestamptz DEFAULT now()
);

-- Ledger de comissões (nasce pronto; ativa quando comissao_pct > 0)
CREATE TABLE IF NOT EXISTS prescritor_comissoes (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    prescritor_id uuid NOT NULL REFERENCES prescritores(id),
    ordem_id      uuid,                          -- lab_ordens
    competencia   date NOT NULL,                 -- mês de referência (dia 01)
    valor_base    numeric NOT NULL,
    pct           numeric NOT NULL,
    valor         numeric NOT NULL,
    status        text DEFAULT 'aberta',         -- aberta|paga|cancelada
    pago_em       timestamptz,
    criado_em     timestamptz DEFAULT now()
);

-- Lotes de matéria-prima (rastreabilidade: entrada por NF → uso na OM)
CREATE TABLE IF NOT EXISTS lab_lotes_mp (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id     uuid NOT NULL REFERENCES clientes(id),
    ativo_id       uuid REFERENCES ativos(id),
    nr_lote        text NOT NULL,                -- lote do fabricante
    lote_interno   text,                         -- controle interno (CTLOT do FCerta)
    fornecedor_id  uuid REFERENCES fornecedores(id),
    nf_numero      text,
    nf_chave       text,                         -- chave NF-e (grupo K exige na revenda)
    fabricante     text,
    dt_fabricacao  date,
    dt_validade    date NOT NULL,
    qtd_inicial    numeric NOT NULL,
    qtd_atual      numeric NOT NULL,
    unidade        text NOT NULL DEFAULT 'g',
    laudo_url      text,                         -- PDF do laudo de análise
    status         text DEFAULT 'ativo',         -- ativo|esgotado|vencido|quarentena|reprovado
    criado_em      timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_lotes_mp_ativo ON lab_lotes_mp(cliente_id, ativo_id, status);

-- Lotes produzidos internamente (bases/semi-acabados — FC038A2)
CREATE TABLE IF NOT EXISTS lab_lotes_internos (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    ativo_id      uuid REFERENCES ativos(id),
    nr_lote       text NOT NULL,
    dt_producao   date NOT NULL,
    dt_validade   date NOT NULL,
    qtd_inicial   numeric NOT NULL,
    qtd_atual     numeric NOT NULL,
    unidade       text NOT NULL DEFAULT 'g',
    ordem_id      uuid,                          -- OM que produziu (rastreio reverso)
    obs           text,
    criado_em     timestamptz DEFAULT now()
);

-- Etapas de produção configuráveis (FC12500 — o kanban do laboratório)
CREATE TABLE IF NOT EXISTS lab_etapas (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id uuid NOT NULL REFERENCES clientes(id),
    nome       text NOT NULL,                    -- ex: Conferência, Pesagem, Manipulação, Envase, Rotulagem, CQ, Liberada, Entregue
    ordem      int NOT NULL DEFAULT 0,
    tipo       text DEFAULT 'normal',            -- normal|final|cancelada
    ativo      boolean DEFAULT true
);

-- Ordem de Manipulação (FC12100) — nasce do orçamento aprovado no CRM
CREATE TABLE IF NOT EXISTS lab_ordens (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    numero         bigserial,
    cliente_id     uuid NOT NULL REFERENCES clientes(id),
    orcamento_id   uuid REFERENCES orcamentos(id),
    card_id        uuid,                         -- crm_cards
    paciente_nome  text NOT NULL,
    paciente_whats text,
    prescritor_id  uuid REFERENCES prescritores(id),
    receita_url    text,                         -- imagem/PDF da receita
    posologia      text,
    tp_uso         text,                         -- interno|externo|veterinario
    forma_farmac   text,
    volume         numeric,
    unidade_vol    text,
    qtd_unidades   numeric,                      -- nº cápsulas/envelopes etc.
    dt_manipulacao date,
    dt_validade    date,                         -- validade da fórmula (RDC 67: floral 90d / demais 120d — regra da Magis)
    etapa_id       uuid REFERENCES lab_etapas(id),
    status         text DEFAULT 'aberta',        -- aberta|concluida|cancelada
    controlado     boolean DEFAULT false,        -- tem item Port. 344 (gatilho SNGPC futuro)
    manipulador_id bigint,                       -- WP user que manipulou
    conferente_id  bigint,                       -- farmacêutico que conferiu/liberou
    criado_por     bigint,
    criado_em      timestamptz DEFAULT now(),
    concluido_em   timestamptz
);
CREATE INDEX IF NOT EXISTS idx_lab_ordens_cliente ON lab_ordens(cliente_id, status, etapa_id);

-- Itens da OM (FC12110 — rastreabilidade: pesagem + lote por componente)
CREATE TABLE IF NOT EXISTS lab_ordem_itens (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ordem_id     uuid NOT NULL REFERENCES lab_ordens(id) ON DELETE CASCADE,
    ativo_id     uuid REFERENCES ativos(id),
    descricao    text NOT NULL,
    qtd_prescrita numeric,
    unidade      text,
    qtd_pesada   numeric,                        -- QTPESA
    lote_mp_id   uuid REFERENCES lab_lotes_mp(id),      -- NRLOT (rastreio!)
    lote_interno_id uuid REFERENCES lab_lotes_internos(id),
    eh_qsp       boolean DEFAULT false,
    custo        numeric,
    pesado_por   bigint,
    pesado_em    timestamptz
);
CREATE INDEX IF NOT EXISTS idx_lab_itens_ordem ON lab_ordem_itens(ordem_id);
CREATE INDEX IF NOT EXISTS idx_lab_itens_lote  ON lab_ordem_itens(lote_mp_id);

-- Movimentação entre etapas (auditoria do fluxo — FC12500)
CREATE TABLE IF NOT EXISTS lab_ordem_etapas (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ordem_id   uuid NOT NULL REFERENCES lab_ordens(id) ON DELETE CASCADE,
    de_etapa   uuid,
    para_etapa uuid,
    usuario_id bigint,
    obs        text,
    criado_em  timestamptz DEFAULT now()
);

-- Templates e rótulos emitidos (FC12300)
CREATE TABLE IF NOT EXISTS lab_rotulo_templates (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id uuid NOT NULL REFERENCES clientes(id),
    nome       text NOT NULL,
    conteudo   text NOT NULL,                    -- template com variáveis {paciente} {formula} {lote} {validade} {posologia}...
    largura_mm int, altura_mm int,
    ativo      boolean DEFAULT true
);
CREATE TABLE IF NOT EXISTS lab_rotulos (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ordem_id    uuid NOT NULL REFERENCES lab_ordens(id) ON DELETE CASCADE,
    template_id uuid REFERENCES lab_rotulo_templates(id),
    texto       text NOT NULL,                   -- texto final renderizado (auditável)
    impresso_em timestamptz,
    impresso_por bigint
);

-- RLS padrão TAO
ALTER TABLE prescritores          ENABLE ROW LEVEL SECURITY;
ALTER TABLE prescritor_visitas    ENABLE ROW LEVEL SECURITY;
ALTER TABLE prescritor_comissoes  ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_lotes_mp          ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_lotes_internos    ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_etapas            ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_ordens            ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_ordem_itens       ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_ordem_etapas      ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_rotulo_templates  ENABLE ROW LEVEL SECURITY;
ALTER TABLE lab_rotulos           ENABLE ROW LEVEL SECURITY;

-- NOTAS DE DESIGN (para revisão do Carlos + farmacêutica RT):
-- 1. Ranking de receituário por prescritor (FC04100) NÃO é tabela: deriva de
--    lab_ordens agrupado por prescritor_id/mês (view/tela).
-- 2. SNGPC fica p/ migration própria (fase 4): sngpc_movimentos espelhando
--    FC99S21-S24 (entrada/saida_receita/transferencia/perda) + inventário.
-- 3. Estoque (entrada NF → lab_lotes_mp + saldo em ativos) entra na fase 2
--    do plano, com tabelas estoque_* próprias.
-- 4. Peso médio/balança: fora do escopo (decisão 06/07) — registro de CQ
--    manual permanece fora do sistema.
