-- ============================================================================
-- TAO Entregas — Fase 2: integração com o Caixa (forma de pagamento carrega o tipo)
-- + vínculo da forma de pagamento e do lançamento na tabela entregas.
-- Idempotente. Rodar antes do deploy da Fase 2.
-- ============================================================================

-- 1) Formas de pagamento "de entrega" no cadastro do Caixa.
--    tipo = natureza (credito/debito, constraint fixa); canal='entrega' distingue de balcão.
--    cliente_id Magis = 62f98634-77ff-42f4-acaf-8561d56583da
INSERT INTO caixa_formas_pagamento (cliente_id, nome, tipo, canal, conta_no_dinheiro, ativo, ordem)
SELECT '62f98634-77ff-42f4-acaf-8561d56583da', 'Crédito Entrega', 'credito', 'entrega', false, true, 20
 WHERE NOT EXISTS (SELECT 1 FROM caixa_formas_pagamento
                   WHERE cliente_id='62f98634-77ff-42f4-acaf-8561d56583da' AND nome='Crédito Entrega');
INSERT INTO caixa_formas_pagamento (cliente_id, nome, tipo, canal, conta_no_dinheiro, ativo, ordem)
SELECT '62f98634-77ff-42f4-acaf-8561d56583da', 'Débito Entrega', 'debito', 'entrega', false, true, 21
 WHERE NOT EXISTS (SELECT 1 FROM caixa_formas_pagamento
                   WHERE cliente_id='62f98634-77ff-42f4-acaf-8561d56583da' AND nome='Débito Entrega');

-- 2) Vínculo da entrega com a forma do Caixa + o lançamento gerado ao pagar
ALTER TABLE entregas
    ADD COLUMN IF NOT EXISTS forma_pagamento_id uuid,   -- -> caixa_formas_pagamento.id (carrega o tipo)
    ADD COLUMN IF NOT EXISTS pago_tipo          text,   -- snapshot do tipo (credito/…/credito_entrega)
    ADD COLUMN IF NOT EXISTS caixa_pagamento_id uuid,   -- lançamento gerado no Caixa quando pago na entrega
    ADD COLUMN IF NOT EXISTS pago_em            timestamptz;
