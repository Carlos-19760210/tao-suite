-- TAO Cotações — texto da solicitação salvo na cotação (revisão do farmacêutico)
-- Idempotente/aditiva. Guarda o texto que o farmacêutico revisou/aprovou para envio,
-- permitindo salvar sem enviar (e copiar para envio manual) e reusar depois.

ALTER TABLE cotacoes
    ADD COLUMN IF NOT EXISTS msg_texto text;

COMMENT ON COLUMN cotacoes.msg_texto IS
    'Texto da solicitação revisado pelo farmacêutico (mantém {fornecedor}/{contato} como placeholders). Salvo mesmo sem envio.';
