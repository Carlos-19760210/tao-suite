-- ============================================================
-- TAO — MIGRATION v3: CADASTRO DE PRESCRITORES (07/07/2026)
-- Espelha FC04000 (+FC04400 endereço): tratamento, tipo/nº/UF do
-- registro, especialidade, contato, endereço. Carga inicial do FCerta.
-- Rodar no SQL Editor do Supabase. 100% aditiva.
-- ============================================================

CREATE TABLE IF NOT EXISTS prescritores (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id     uuid NOT NULL REFERENCES clientes(id),
    tratamento     text,                        -- Dr, Dra, Sr, Sra...
    nome           text NOT NULL,
    tipo_registro  text,                        -- CRM, CRO, CRMV, CRN, CRF...
    nr_registro    text,
    uf_registro    text,
    especialidade  text,
    celular        text,
    telefone       text,
    email          text,
    endereco       text,
    cidade         text,
    uf             text,
    cep            text,
    obs            text,
    codigo_fc      text,                        -- chave FCerta: PFCRM|UFCRM|NRCRM
    ativo          boolean DEFAULT true,
    criado_em      timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_prescritores_nome ON prescritores (cliente_id, nome);
CREATE INDEX IF NOT EXISTS idx_prescritores_reg  ON prescritores (cliente_id, nr_registro);
CREATE UNIQUE INDEX IF NOT EXISTS uq_prescritores_fc ON prescritores (cliente_id, codigo_fc) WHERE codigo_fc IS NOT NULL;

ALTER TABLE prescritores ENABLE ROW LEVEL SECURITY;

-- Orçamento aponta p/ o cadastro (o texto orcamentos.prescritor continua p/ exibição)
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS prescritor_id uuid REFERENCES prescritores(id);

-- Validação
SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name='prescritores';
