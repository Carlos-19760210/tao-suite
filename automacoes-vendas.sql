-- ============================================================
-- TAO CRM — SQL de configuração (execute no Supabase Editor)
-- Pipeline: Vendas | Workspace: Magis-TAO
-- ============================================================

-- ─── 1. SCHEMA: novos campos ──────────────────────────────────────────────────

-- Tipo de estágio: normal | ganho | perdido
ALTER TABLE crm_estagios
  ADD COLUMN IF NOT EXISTS tipo text DEFAULT 'normal'
  CHECK (tipo IN ('normal', 'ganho', 'perdido'));

-- Card fechado (venda concluída ou cancelado)
ALTER TABLE crm_cards
  ADD COLUMN IF NOT EXISTS fechado boolean DEFAULT false;

-- Motivo de movimentação (para histórico de fechamento)
ALTER TABLE crm_cards_historico
  ADD COLUMN IF NOT EXISTS motivo text;

-- ─── 2. ESTÁGIOS TERMINAIS ────────────────────────────────────────────────────
-- Cria somente se ainda não existirem

INSERT INTO crm_estagios (pipeline_id, nome, cor, ordem, tipo)
SELECT 'aabfe3ea-d16d-4785-a803-1a0476a3016e','Venda Concluída','#22c55e',11,'ganho'
WHERE NOT EXISTS (
  SELECT 1 FROM crm_estagios
  WHERE pipeline_id = 'aabfe3ea-d16d-4785-a803-1a0476a3016e' AND nome = 'Venda Concluída'
);

INSERT INTO crm_estagios (pipeline_id, nome, cor, ordem, tipo)
SELECT 'aabfe3ea-d16d-4785-a803-1a0476a3016e','Cards Cancelados','#94a3b8',12,'perdido'
WHERE NOT EXISTS (
  SELECT 1 FROM crm_estagios
  WHERE pipeline_id = 'aabfe3ea-d16d-4785-a803-1a0476a3016e' AND nome = 'Cards Cancelados'
);

-- Se os estágios já existiam, garantir que têm o tipo correto:
UPDATE crm_estagios SET tipo = 'ganho'   WHERE pipeline_id = 'aabfe3ea-d16d-4785-a803-1a0476a3016e' AND nome = 'Venda Concluída';
UPDATE crm_estagios SET tipo = 'perdido' WHERE pipeline_id = 'aabfe3ea-d16d-4785-a803-1a0476a3016e' AND nome = 'Cards Cancelados';

-- ─── 3. AUTOMAÇÕES — limpar existentes e recriar ──────────────────────────────
-- ATENÇÃO: remove todas as automações deste pipeline antes de inserir.
-- Se você já criou automações manualmente, comente a linha abaixo.
DELETE FROM crm_automacoes WHERE pipeline_id = 'aabfe3ea-d16d-4785-a803-1a0476a3016e';

-- IDs de referência:
--   WS  : 7c4cae7f-7591-4955-8d7a-c8c5e19cf62d
--   PL  : aabfe3ea-d16d-4785-a803-1a0476a3016e
-- Estágios:
--   Novos Leads              : f17b7d31-d90d-4af4-8fb2-3e1bee24b87c  (gerenciado pelo N8N)
--   Aguardando Atendimento   : 74199a5c-02d2-4299-a4a1-277e4c1498fb
--   Em Conversa              : 50471dab-3ba5-4b24-80ba-6eb08567b109
--   Aguarda Resp Conversa    : 45677bbb-b0be-4f05-83cc-9d9849cd324a
--   Flw Orçamento Enviado    : bab3df8c-034e-46a6-8bb0-c46c0707f56f
--   Aguardando Análise Téc.  : 8bfda91c-6cab-4104-97ca-b0d9e7413ce3
--   Desconto Final           : f8382103-3fde-4adf-9d8e-7c4c3ee788c2
--   Última Tentativa         : 7a4fec8c-5589-4299-9372-82c7f1a1e9d3
--   Em Negociação            : a5cb7462-4272-4692-8c77-ccd69dcc14b8
--   Aguard Resp Negociação   : ea04794f-e078-4951-a419-1c6bb391eaee
--   Aguardando Aprovação     : 6776dd96-ecfb-48a7-a09f-791a7d92309f

INSERT INTO crm_automacoes
  (workspace_id, pipeline_id, estagio_id, nome, tipo, delay_minutos, acao, mensagem, para_estagio_id, ativo, ordem)
VALUES

-- ─── AGUARDANDO ATENDIMENTO ───────────────────────────────────────────────────
-- entrar_fase: ativo=FALSE porque o N8N (Dr. TAO) já envia a mensagem de handoff.
-- Ative quando migrar as mensagens para o TAO CRM.
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '74199a5c-02d2-4299-a4a1-277e4c1498fb',
  'Boas-vindas atendimento humano',
  'entrar_fase', 0, 'enviar_mensagem',
  'Olá, {nome}! 😊 Identificamos sua solicitação e já estamos conectando você com um de nossos atendentes. Aguarde um momento, por favor!',
  NULL, false, 1
),
-- tempo_na_fase 30min: lembrete de espera (ativo por padrão)
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '74199a5c-02d2-4299-a4a1-277e4c1498fb',
  'Lembrete — espera 30min',
  'tempo_na_fase', 30, 'enviar_mensagem',
  '{nome}, pedimos desculpas pela espera! 🙏 Um atendente já vai te ajudar em instantes.',
  NULL, true, 2
),

-- ─── AGUARDA RESP CONVERSA ────────────────────────────────────────────────────
-- recebeu_mensagem: cliente respondeu → volta para Em Conversa
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '45677bbb-b0be-4f05-83cc-9d9849cd324a',
  'Cliente respondeu → Em Conversa',
  'recebeu_mensagem', 0, 'mover_fase',
  NULL,
  '50471dab-3ba5-4b24-80ba-6eb08567b109', true, 1
),
-- tempo_na_fase 24h: follow-up se cliente não respondeu
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '45677bbb-b0be-4f05-83cc-9d9849cd324a',
  'Follow-up 24h sem resposta',
  'tempo_na_fase', 1440, 'enviar_mensagem',
  'Oi, {nome}! Tudo bem? 😊 Ainda posso te ajudar com alguma coisa?',
  NULL, true, 2
),

-- ─── FLW ORÇAMENTO ENVIADO ────────────────────────────────────────────────────
-- entrar_fase: confirmação de envio do orçamento
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'bab3df8c-034e-46a6-8bb0-c46c0707f56f',
  'Confirmação envio de orçamento',
  'entrar_fase', 0, 'enviar_mensagem',
  'Perfeito, {nome}! Acabamos de enviar o orçamento. 📋 Fique à vontade para avaliar e qualquer dúvida é só me chamar!',
  NULL, true, 1
),
-- tempo_na_fase 30min: follow-up se cliente não respondeu
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'bab3df8c-034e-46a6-8bb0-c46c0707f56f',
  'Follow-up orçamento 30min',
  'tempo_na_fase', 30, 'enviar_mensagem',
  'Oi, {nome}! Você chegou a avaliar o orçamento que preparamos? 😊 Qualquer dúvida ou ajuste é só me chamar!',
  NULL, true, 2
),
-- tempo_na_fase 24h: sem resposta → move para Desconto Final
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'bab3df8c-034e-46a6-8bb0-c46c0707f56f',
  'Sem resposta 24h → Desconto Final',
  'tempo_na_fase', 1440, 'mover_fase',
  NULL,
  'f8382103-3fde-4adf-9d8e-7c4c3ee788c2', true, 3
),
-- recebeu_mensagem: cliente respondeu → Em Negociação
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'bab3df8c-034e-46a6-8bb0-c46c0707f56f',
  'Cliente respondeu → Em Negociação',
  'recebeu_mensagem', 0, 'mover_fase',
  NULL,
  'a5cb7462-4272-4692-8c77-ccd69dcc14b8', true, 4
),

-- ─── AGUARDANDO ANÁLISE TÉCNICA ───────────────────────────────────────────────
-- entrar_fase: avisa o cliente que está em análise
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '8bfda91c-6cab-4104-97ca-b0d9e7413ce3',
  'Aviso análise técnica',
  'entrar_fase', 0, 'enviar_mensagem',
  '{nome}, seu pedido está em análise técnica com nossa equipe. ⚗️ Em breve retornaremos com uma resposta. Obrigado pela paciência!',
  NULL, true, 1
),
-- tempo_na_fase 8h: lembrete se ainda em análise
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '8bfda91c-6cab-4104-97ca-b0d9e7413ce3',
  'Follow-up análise 8h',
  'tempo_na_fase', 480, 'enviar_mensagem',
  '{nome}, nossa equipe técnica ainda está avaliando seu pedido com cuidado. 🔬 Assim que tivermos um retorno te avisamos imediatamente!',
  NULL, true, 2
),

-- ─── DESCONTO FINAL ───────────────────────────────────────────────────────────
-- entrar_fase: oferta especial
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'f8382103-3fde-4adf-9d8e-7c4c3ee788c2',
  'Oferta especial — Desconto Final',
  'entrar_fase', 0, 'enviar_mensagem',
  'Oi, {nome}! 🌟 Temos uma condição especial para você hoje. Posso te apresentar uma proposta diferenciada? É por tempo limitado!',
  NULL, true, 1
),
-- recebeu_mensagem: cliente respondeu → Em Negociação
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'f8382103-3fde-4adf-9d8e-7c4c3ee788c2',
  'Cliente respondeu → Em Negociação',
  'recebeu_mensagem', 0, 'mover_fase',
  NULL,
  'a5cb7462-4272-4692-8c77-ccd69dcc14b8', true, 2
),
-- tempo_na_fase 24h: sem resposta → Última Tentativa
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'f8382103-3fde-4adf-9d8e-7c4c3ee788c2',
  'Sem resposta 24h → Última Tentativa',
  'tempo_na_fase', 1440, 'mover_fase',
  NULL,
  '7a4fec8c-5589-4299-9372-82c7f1a1e9d3', true, 3
),

-- ─── ÚLTIMA TENTATIVA ─────────────────────────────────────────────────────────
-- entrar_fase: última tentativa de contato
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '7a4fec8c-5589-4299-9372-82c7f1a1e9d3',
  'Última tentativa de contato',
  'entrar_fase', 0, 'enviar_mensagem',
  '{nome}, passamos para dar um último olá! 👋 Ainda temos interesse em ajudar você. Caso queira conversar, é só responder esta mensagem. 😊',
  NULL, true, 1
),
-- recebeu_mensagem: cliente respondeu → Em Negociação
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '7a4fec8c-5589-4299-9372-82c7f1a1e9d3',
  'Cliente respondeu → Em Negociação',
  'recebeu_mensagem', 0, 'mover_fase',
  NULL,
  'a5cb7462-4272-4692-8c77-ccd69dcc14b8', true, 2
),

-- ─── EM NEGOCIAÇÃO ────────────────────────────────────────────────────────────
-- tempo_na_fase 24h: follow-up se parou de responder
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'a5cb7462-4272-4692-8c77-ccd69dcc14b8',
  'Follow-up negociação 24h',
  'tempo_na_fase', 1440, 'enviar_mensagem',
  'Oi, {nome}! Passando para ver se ficou alguma dúvida sobre nossa proposta. 😊 Podemos avançar?',
  NULL, true, 1
),

-- ─── AGUARD RESP NEGOCIAÇÃO ───────────────────────────────────────────────────
-- recebeu_mensagem: cliente respondeu → Em Negociação
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'ea04794f-e078-4951-a419-1c6bb391eaee',
  'Cliente respondeu → Em Negociação',
  'recebeu_mensagem', 0, 'mover_fase',
  NULL,
  'a5cb7462-4272-4692-8c77-ccd69dcc14b8', true, 1
),
-- tempo_na_fase 24h: follow-up se cliente não respondeu
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  'ea04794f-e078-4951-a419-1c6bb391eaee',
  'Follow-up aguardando negociação 24h',
  'tempo_na_fase', 1440, 'enviar_mensagem',
  'Oi, {nome}! Você teve a oportunidade de avaliar nossa proposta? 🤝 Estou aqui para ajudar no que precisar!',
  NULL, true, 2
),

-- ─── AGUARDANDO APROVAÇÃO ─────────────────────────────────────────────────────
-- entrar_fase: avisa que está em aprovação
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '6776dd96-ecfb-48a7-a09f-791a7d92309f',
  'Aviso aprovação pendente',
  'entrar_fase', 0, 'enviar_mensagem',
  '{nome}, seu pedido foi encaminhado para aprovação final! ✅ Assim que confirmado te avisamos imediatamente. Obrigado!',
  NULL, true, 1
),
-- tempo_na_fase 8h: lembrete se ainda aguardando
(
  '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d',
  'aabfe3ea-d16d-4785-a803-1a0476a3016e',
  '6776dd96-ecfb-48a7-a09f-791a7d92309f',
  'Follow-up aprovação 8h',
  'tempo_na_fase', 480, 'enviar_mensagem',
  '{nome}, nossa equipe ainda está processando sua aprovação. 📋 Em breve você receberá a confirmação!',
  NULL, true, 2
);

-- ─── 4. VERIFICAÇÃO ───────────────────────────────────────────────────────────
-- Execute após o INSERT para confirmar:

SELECT e.nome AS fase, a.nome AS automacao, a.tipo, a.delay_minutos, a.acao, a.ativo
FROM crm_automacoes a
JOIN crm_estagios e ON e.id = a.estagio_id
WHERE a.pipeline_id = 'aabfe3ea-d16d-4785-a803-1a0476a3016e'
ORDER BY e.ordem, a.ordem;
