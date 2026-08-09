-- TAO Cotações — prioridade dos itens (0-3) + pedido mínimo do fornecedor
-- Idempotente/aditiva.
--   cotacao_itens.prioridade: 0=Urgente (⭐, = favorito de hoje), 1=15 dias, 2=30 dias, 3=Acima 30 dias
--   fornecedores.pedido_minimo: valor mínimo de pedido/faturamento (só gera ALERTA na sugestão)

ALTER TABLE cotacao_itens
    ADD COLUMN IF NOT EXISTS prioridade smallint NOT NULL DEFAULT 3;

-- alinha o que já é urgente (⭐) com a prioridade 0
UPDATE cotacao_itens SET prioridade = 0 WHERE urgente = true AND prioridade = 3;

ALTER TABLE fornecedores
    ADD COLUMN IF NOT EXISTS pedido_minimo numeric NOT NULL DEFAULT 0;

COMMENT ON COLUMN cotacao_itens.prioridade IS '0=Urgente(⭐) 1=15d 2=30d 3=>30d — organiza a distribuição da sugestão de pedido';
COMMENT ON COLUMN fornecedores.pedido_minimo IS 'Valor mínimo de pedido do fornecedor (R$). Só gera alerta quando o pedido sugerido fica abaixo.';
