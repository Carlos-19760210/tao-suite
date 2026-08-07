-- TAO Cotações — Modelos de Proposta (aprendizado de layout, sem IA a cada importação)
-- Espelha o padrão de laudo_modelos (tao-formula). Idempotente/aditiva.
-- O "layout aprendido" de cada fornecedor: regras determinísticas + assinatura textual.

CREATE TABLE IF NOT EXISTS cotacao_modelos (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id      uuid NOT NULL,
    fornecedor_id   uuid,
    fornecedor_cnpj text,                 -- casamento alternativo por CNPJ (só dígitos)
    nome            text NOT NULL DEFAULT 'Modelo',
    tipo            text NOT NULL DEFAULT 'linha',   -- 'linha' (regex por linha) | 'colunas' (cabeçalho-âncora)
    ativo           boolean NOT NULL DEFAULT true,
    regras          jsonb NOT NULL DEFAULT '{}'::jsonb,  -- o coração do molde (ver abaixo)
    assinatura      text,                 -- trecho curto/único p/ auto-detectar entre modelos do mesmo fornecedor
    origem          text DEFAULT 'ia',    -- 'ia' | 'seed' (transcrito do consolidar.py) | 'manual'
    criado_por      bigint,
    criado_em       timestamptz NOT NULL DEFAULT now(),
    atualizado_em   timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_cotacao_modelos_forn
    ON cotacao_modelos (cliente_id, fornecedor_id);
CREATE INDEX IF NOT EXISTS idx_cotacao_modelos_cnpj
    ON cotacao_modelos (cliente_id, fornecedor_cnpj);

-- Formato de `regras`:
--  tipo='linha':
--    { "linha_regex": "<regex por linha, com grupos>",
--      "grupos": { "item":1, "preco":2, "unidade":3, "qtde_min":4, "validade":5 },
--      "unidade_fixa": "kg|g|ml|milheiro",   -- opcional (quando a un. não está na linha)
--      "cap_milheiro": true,                 -- opcional (linha de cápsula = milheiro)
--      "pular": ["produto","total","validade"] }  -- palavras de cabeçalho/rodapé a ignorar
--  tipo='colunas':
--    { "header_kw": ["descricao","produto"],       -- como achar a linha de cabeçalho
--      "col": { "nome":["produto","descricao"], "preco":["valor unit","preco"],
--               "preco_kg":["valor kg"], "unidade":["unid","medida"],
--               "qtde":["qtde","quant"], "validade":["validade","valid"] },
--      "cap_milheiro": true }
-- Assinatura: substring exata que só aparece NESSE layout (ex.: "Valor KG", "LOTE PN:", "│Item│").
