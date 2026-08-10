-- TAO Cotações — fornecedor escolhido manualmente por item no comparativo
-- Idempotente/aditiva. NULL = automático (melhor preço/menor). Quando preenchido,
-- o comparativo marca esse fornecedor como o escolhido e a sugestão de pedido o respeita.
-- "Restaurar sugestões" volta todos para NULL (automático).

ALTER TABLE cotacao_itens
    ADD COLUMN IF NOT EXISTS fornecedor_escolhido uuid;

COMMENT ON COLUMN cotacao_itens.fornecedor_escolhido IS
    'Fornecedor escolhido manualmente no comparativo (override). NULL = automático (menor preço).';
