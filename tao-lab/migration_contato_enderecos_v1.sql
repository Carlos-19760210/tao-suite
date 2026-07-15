-- ============================================================================
-- TAO Entregas — endereços de entrega do cliente (múltiplos por contato).
-- O endereço do cadastro (crm_contatos) é a referência; aqui ficam os adicionais.
-- Idempotente. Rodar antes do deploy.
-- ============================================================================

CREATE TABLE IF NOT EXISTS contato_enderecos (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id  uuid NOT NULL,
    contato_id    uuid NOT NULL,
    apelido       text,                    -- ex: "Casa", "Trabalho"
    cep           text,
    logradouro    text,
    numero        text,
    complemento   text,
    bairro        text,
    cidade        text,
    uf            text,
    principal     boolean DEFAULT false,
    criado_em     timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_contato_enderecos ON contato_enderecos (contato_id);
