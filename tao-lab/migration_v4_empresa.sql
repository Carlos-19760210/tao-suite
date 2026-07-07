-- ============================================================
-- TAO — MIGRATION v4: CADASTRO DA EMPRESA / FILIAL (07/07/2026)
-- Dados da farmácia p/ RÓTULO (RDC 67: identificação + RT/CRF) e FISCAL (NF).
-- Espelha FC01000. 1 registro por tenant. Rodar no SQL Editor. Aditiva.
-- ============================================================

CREATE TABLE IF NOT EXISTS empresa_config (
    cliente_id      uuid PRIMARY KEY REFERENCES clientes(id),
    razao_social    text,
    nome_fantasia   text,
    cnpj            text,
    inscr_estadual  text,
    inscr_municipal text,
    endereco        text,
    bairro          text,
    cidade          text,
    uf              text,
    cep             text,
    telefone        text,
    email           text,
    -- Responsável Técnico (obrigatório no rótulo — RDC 67)
    rt_nome         text,
    rt_crf          text,
    rt_uf           text,
    -- Licenças sanitárias (guarda dos números p/ conformidade)
    licenca_afe     text,   -- Autorização de Funcionamento (ANVISA)
    licenca_cevs    text,   -- Licença sanitária estadual/municipal (CEVS)
    licenca_crf_pj  text,   -- CRF pessoa jurídica
    autorizacao_esp text,   -- Autorização Especial (controlados), se houver
    atualizado_em   timestamptz DEFAULT now()
);

ALTER TABLE empresa_config ENABLE ROW LEVEL SECURITY;

SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name='empresa_config';
