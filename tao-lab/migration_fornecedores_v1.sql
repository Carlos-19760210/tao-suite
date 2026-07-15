-- ============================================================================
-- TAO Lab — Fornecedores: campos fiscais detalhados + qualificação RDC 67
-- Estende a tabela `fornecedores` (que hoje só tem o básico criado via NF).
-- Fonte de referência: FCerta FC02000. Idempotente (IF NOT EXISTS).
-- ============================================================================

ALTER TABLE fornecedores
    -- Identificação / tipo
    ADD COLUMN IF NOT EXISTS tipo_pessoa      text DEFAULT 'PJ',          -- PJ | PF
    ADD COLUMN IF NOT EXISTS tipo             text,                       -- fabricante | distribuidor | importador | transportadora | outro
    ADD COLUMN IF NOT EXISTS inscr_municipal  text,
    ADD COLUMN IF NOT EXISTS crt              text,                       -- 1=Simples, 2=Simples excesso, 3=Regime Normal
    ADD COLUMN IF NOT EXISTS suframa          text,
    ADD COLUMN IF NOT EXISTS reg_mapa         text,                       -- registro MAPA (veterinário/agro)
    -- Endereço detalhado (o básico endereco/cidade/uf/cep já existe)
    ADD COLUMN IF NOT EXISTS endereco_nr      text,
    ADD COLUMN IF NOT EXISTS complemento      text,
    ADD COLUMN IF NOT EXISTS bairro           text,
    -- Contato extra
    ADD COLUMN IF NOT EXISTS telefone2        text,
    ADD COLUMN IF NOT EXISTS site             text,
    -- Comercial
    ADD COLUMN IF NOT EXISTS valor_min_pedido numeric,
    -- Licenças sanitárias / RDC 67 (recebimento e qualificação de fornecedor)
    ADD COLUMN IF NOT EXISTS afe              text,                       -- Autorização de Funcionamento (ANVISA)
    ADD COLUMN IF NOT EXISTS afe_validade     date,
    ADD COLUMN IF NOT EXISTS autoriz_especial text,                       -- AE p/ controlados (Portaria 344/98)
    ADD COLUMN IF NOT EXISTS ae_validade      date,
    ADD COLUMN IF NOT EXISTS licenca_sanitaria text,                      -- alvará / licença VISA estadual/municipal
    ADD COLUMN IF NOT EXISTS licenca_validade date,
    -- Qualificação de fornecedor (RDC 67 exige avaliação)
    ADD COLUMN IF NOT EXISTS qualificado      boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS qualif_data      date,
    ADD COLUMN IF NOT EXISTS qualif_por       text,
    ADD COLUMN IF NOT EXISTS qualif_obs       text;

-- índice para busca por CNPJ (casamento da NF)
CREATE INDEX IF NOT EXISTS idx_fornecedores_cnpj ON fornecedores (cliente_id, cnpj);
