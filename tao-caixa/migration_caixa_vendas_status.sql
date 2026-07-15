-- ============================================================================
-- TAO Caixa — corrige o CHECK de status de caixa_vendas
-- ----------------------------------------------------------------------------
-- PROBLEMA: a constraint atual (caixa_vendas_status_check) só aceita
--   'aberta','paga','cancelada' — mas TODO o código do módulo usa
--   'quitada','parcial','estornada'. Resultado: a baixa de pagamento
--   (tao_caixa_receber_venda) cria o recibo mas o UPDATE da venda para
--   'quitada' é REJEITADO → as 197 vendas ficam eternamente "A receber".
--
-- CORREÇÃO: alinhar a constraint ao vocabulário do código.
-- SEGURO: as 197 vendas existentes são todas 'aberta' (permanece válida);
--         nenhuma linha é alterada, só o conjunto permitido é expandido.
-- Idempotente: DROP IF EXISTS antes de recriar.
-- ============================================================================

ALTER TABLE caixa_vendas DROP CONSTRAINT IF EXISTS caixa_vendas_status_check;

ALTER TABLE caixa_vendas
  ADD CONSTRAINT caixa_vendas_status_check
  CHECK (status IN ('aberta','parcial','quitada','cancelada','estornada'));

-- Verificação (opcional): deve retornar 0 linhas fora do conjunto
-- SELECT status, count(*) FROM caixa_vendas
--   WHERE status NOT IN ('aberta','parcial','quitada','cancelada','estornada')
--   GROUP BY status;
