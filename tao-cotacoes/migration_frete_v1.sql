-- TAO Cotações — Frete por proposta de fornecedor (rateio proporcional ao valor)
-- Idempotente/aditiva. Um frete por fornecedor por cotação.
-- O comparativo rateia o frete entre os itens do fornecedor em função do VALOR
-- (fator uniforme = 1 + frete / valor_total_do_fornecedor) e passa a escolher o
-- melhor fornecedor pelo preço COM frete (exibindo também o SEM frete).

ALTER TABLE cotacao_fornecedores
    ADD COLUMN IF NOT EXISTS frete numeric NOT NULL DEFAULT 0;

COMMENT ON COLUMN cotacao_fornecedores.frete IS
    'Frete total informado para a proposta deste fornecedor nesta cotação (R$). Rateado por valor no comparativo.';
