-- ── Moldes de laudo por fornecedor (extração determinística de Certificados de Análise) ──
-- A IA entra 1x (na definição do molde); as importações seguintes são determinísticas.
-- N moldes por fornecedor (variações de layout). Casamento com o lote da NF por nome+lote.

CREATE TABLE IF NOT EXISTS laudo_modelos (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id      uuid NOT NULL,
    fornecedor_id   uuid,                       -- opcional; casa também por CNPJ
    fornecedor_cnpj text,                       -- CNPJ (só dígitos) do fornecedor da NF
    nome            text NOT NULL,              -- nome/versão do molde (ex.: "PN v1")
    ativo           boolean NOT NULL DEFAULT true,
    tipo            text NOT NULL DEFAULT 'texto',   -- 'texto' | 'scan'
    multi_ativo     boolean NOT NULL DEFAULT false,  -- 1 PDF com vários ativos?
    -- regras (JSONB): { split_inicio: regex de PÁGINA que inicia novo laudo,
    --   campos: { nome:{regex}, lote:{regex}, dt_validade:{regex,tipo:'data_br'}, ... },
    --   ensaios: { inicio: regex do bloco } }
    regras          jsonb NOT NULL DEFAULT '{}'::jsonb,
    assinatura      text,                       -- trecho característico p/ auto-detectar o molde
    criado_por      bigint,
    criado_em       timestamptz NOT NULL DEFAULT now(),
    atualizado_em   timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_laudo_modelos_cli_forn ON laudo_modelos (cliente_id, fornecedor_id);
CREATE INDEX IF NOT EXISTS idx_laudo_modelos_cli_cnpj ON laudo_modelos (cliente_id, fornecedor_cnpj);
