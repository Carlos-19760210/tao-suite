-- ============================================================================
-- migration_caixa_recibo_campos_v1.sql — campos do RECEBIMENTO no PDV
-- (pedido Carlos 18/07): CPF do cliente, data do pagamento (pode ser retroativa),
-- desconto adicional concedido no ato e flag Taxas/Cupom Fiscal (Sim/Não).
-- ADITIVA e IDEMPOTENTE. Sem ela: PDV segue funcionando (fallback), mas
-- desconto no recebimento fica bloqueado até rodar.
-- ============================================================================
ALTER TABLE caixa_recibos ADD COLUMN IF NOT EXISTS cpf_pagador    text;
ALTER TABLE caixa_recibos ADD COLUMN IF NOT EXISTS data_pagamento date;
ALTER TABLE caixa_recibos ADD COLUMN IF NOT EXISTS desconto       numeric NOT NULL DEFAULT 0;
ALTER TABLE caixa_recibos ADD COLUMN IF NOT EXISTS cupom_fiscal   boolean NOT NULL DEFAULT false;
