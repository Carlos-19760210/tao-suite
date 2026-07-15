-- ============================================================================
-- TAO Entregas — módulo transversal plugável (painel no card, como o Fórmula).
-- Fluxo sobre o funil Pos Vendas + tabela própria de entrega por card.
-- Robusta/idempotente (sem bloco DO). Rodar antes do deploy.
-- ============================================================================

-- 1) Entrega vinculada ao card (opcional — "pode ou não existir", como o orçamento).
--    Tabela do módulo próprio tao-entregas (à parte); referencia o card/contato do CRM.
CREATE TABLE IF NOT EXISTS entregas (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id   uuid NOT NULL,
    card_id        uuid,
    contato_id     uuid,
    origem         text,                        -- formula | caixa | crm | agente
    ref_id         uuid,
    tipo           text,                        -- propria_cliente | uber | 99 | loggi | motoboy | correios
    custo          numeric,
    valor_receber  numeric,
    forma_pagamento text,
    pago           boolean DEFAULT false,
    entregador     text,
    endereco       text,
    rastreio       text,
    comprovante_url text,
    status         text DEFAULT 'pendente',     -- pendente | em_rota | entregue | nao_entregue
    dt_saida       timestamptz,
    dt_entrega     timestamptz,
    obs            text,
    criado_por     bigint,
    criado_em      timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_entregas_card ON entregas (card_id);
CREATE INDEX IF NOT EXISTS idx_entregas_ws   ON entregas (workspace_id, status, dt_entrega);

-- 2) Abre espaço no funil Pos Vendas (NPS e seguintes vão +3) — só se ainda não inserido.
UPDATE crm_estagios SET ordem = ordem + 3
 WHERE pipeline_id = '36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6' AND ordem >= 4
   AND NOT EXISTS (SELECT 1 FROM crm_estagios
                   WHERE pipeline_id = '36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6' AND nome = 'Em Rota');

-- 3) Insere os 3 estágios de entrega (idempotente via NOT EXISTS)
INSERT INTO crm_estagios (pipeline_id, nome, cor, tipo, ordem)
SELECT '36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6', 'Em Rota', '#f59e0b', 'normal', 4
 WHERE NOT EXISTS (SELECT 1 FROM crm_estagios WHERE pipeline_id='36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6' AND nome='Em Rota');
INSERT INTO crm_estagios (pipeline_id, nome, cor, tipo, ordem)
SELECT '36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6', 'Entregue', '#22c55e', 'normal', 5
 WHERE NOT EXISTS (SELECT 1 FROM crm_estagios WHERE pipeline_id='36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6' AND nome='Entregue');
INSERT INTO crm_estagios (pipeline_id, nome, cor, tipo, ordem)
SELECT '36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6', 'Nao Entregue', '#ef4444', 'normal', 6
 WHERE NOT EXISTS (SELECT 1 FROM crm_estagios WHERE pipeline_id='36e1a7f1-fd5c-4174-8407-87f3bc4eb5a6' AND nome='Nao Entregue');
