-- ============================================================================
-- CLEANUP do negócio demo "Farmácia Modelo" (screenshots comerciais)
-- Rodar no SQL Editor do Supabase (projeto Robôs).
--
-- Âncora: clientes.instancia_whats = 'farmacia-modelo-demo'
-- Remove SOMENTE linhas ligadas ao cliente/workspace demo, em ordem de FK.
-- Idempotente: rodar de novo não faz nada.
--
-- Depois do SQL, remover o lado WordPress (via SSH):
--   wp user delete gestor.demo --yes
--   wp option delete tao_crm_gestores_ws_<WS_ID>
--   wp option delete tao_crm_pos_vendas_pipeline_<WS_ID>
--   (o WS_ID aparece no SELECT de conferência abaixo, antes de apagar)
-- ============================================================================

-- Conferência (rode antes, se quiser ver o que será removido):
-- SELECT c.id AS cliente_id, w.id AS workspace_id
--   FROM clientes c LEFT JOIN crm_workspaces w ON w.cliente_id = c.id
--  WHERE c.instancia_whats = 'farmacia-modelo-demo';

BEGIN;

WITH cid AS (SELECT id FROM clientes WHERE instancia_whats = 'farmacia-modelo-demo'),
     wid AS (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM cid))

-- ---- CAIXA ----
DELETE FROM caixa_pagamentos    WHERE cliente_id IN (SELECT id FROM cid);
DELETE FROM caixa_recibo_vendas WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM caixa_venda_itens   WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM caixa_vendas        WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM caixa_recibos       WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM caixa_movimentos    WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM caixa_sessoes       WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM caixa_formas_pagamento WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM caixa_adquirentes   WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');

-- ---- CAMPANHAS ----
DELETE FROM campanha_contatos WHERE campanha_id IN
  (SELECT id FROM campanhas WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM campanha_historico_itens WHERE historico_id IN
  (SELECT h.id FROM campanha_historico h JOIN campanhas c ON c.id = h.campanha_id
    WHERE c.cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM campanha_historico WHERE campanha_id IN
  (SELECT id FROM campanhas WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM campanhas WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM lista_contatos_itens WHERE lista_id IN
  (SELECT id FROM listas_contatos WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM listas_contatos WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');

-- ---- CRM ----
DELETE FROM crm_mensagens WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM crm_card_itens WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM crm_historico WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM crm_cards_historico WHERE card_id IN
  (SELECT id FROM crm_cards WHERE workspace_id IN
    (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo')));
DELETE FROM crm_cards WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM crm_contatos WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM crm_estagios WHERE pipeline_id IN
  (SELECT id FROM crm_pipelines WHERE workspace_id IN
    (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo')));
DELETE FROM crm_pipelines WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));

-- ---- RBAC ----
DELETE FROM crm_alcadas WHERE perfil_id IN
  (SELECT id FROM crm_perfis WHERE workspace_id IN
    (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo')));
DELETE FROM crm_permissoes WHERE perfil_id IN
  (SELECT id FROM crm_perfis WHERE workspace_id IN
    (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo')));
DELETE FROM crm_perfil_usuarios WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));
DELETE FROM crm_perfis WHERE workspace_id IN
  (SELECT id FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo'));

-- ---- AGENTE / CATÁLOGO ----
DELETE FROM historico     WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM leads         WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM pedidos       WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM catalogo      WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM campos_extras WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM categorias    WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');

-- ---- NÚCLEO ----
DELETE FROM crm_workspaces WHERE cliente_id IN (SELECT id FROM clientes WHERE instancia_whats='farmacia-modelo-demo');
DELETE FROM clientes       WHERE instancia_whats = 'farmacia-modelo-demo';

COMMIT;
