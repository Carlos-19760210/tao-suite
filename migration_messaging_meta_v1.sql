-- TAO Neo — Mensageria multi-provider (Evolution + Meta Cloud API) — Bloco 1
-- ADITIVA e idempotente. NÃO altera o comportamento do Evolution:
--   - todas as colunas novas nascem com default coerente ('evolution' onde aplica);
--   - nenhum índice único sobre dados existentes (evita falha por duplicado legado).
-- Rodar no Supabase (SQL editor). O bloco "DOWN" no fim reverte tudo.

-- ── crm_instancias: provider + config pública da Meta (segredos vão p/ wp_options) ──
ALTER TABLE crm_instancias ADD COLUMN IF NOT EXISTS provider              text NOT NULL DEFAULT 'evolution';
ALTER TABLE crm_instancias ADD COLUMN IF NOT EXISTS meta_phone_number_id  text;
ALTER TABLE crm_instancias ADD COLUMN IF NOT EXISTS meta_waba_id          text;
ALTER TABLE crm_instancias ADD COLUMN IF NOT EXISTS meta_graph_version    text DEFAULT 'v26.0';

-- ── crm_cards: provider da conversa + janela de 24h ──
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS provider               text NOT NULL DEFAULT 'evolution';
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS ultima_msg_recebida_em timestamptz;
ALTER TABLE crm_cards ADD COLUMN IF NOT EXISTS janela_expira_em       timestamptz;

-- ── crm_mensagens: provider, erros e timestamps de transição de status ──
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS provider     text NOT NULL DEFAULT 'evolution';
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS erro_codigo  text;
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS erro_detalhe text;
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS entregue_em  timestamptz;
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS lido_em      timestamptz;
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS falhou_em    timestamptz;
-- índice NÃO-único p/ localizar mensagem por wamid ao processar status (idempotência é no app)
CREATE INDEX IF NOT EXISTS idx_crm_mensagens_wamid ON crm_mensagens (workspace_id, wamid) WHERE wamid IS NOT NULL;

-- ── crm_contatos: opt-in (reaproveita consent_*, adiciona status/origem explícitos) ──
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS optin_status text DEFAULT 'nao_solicitado';  -- nao_solicitado|aceito|recusado|revogado
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS optin_origem text;                            -- balcao|site|whatsapp|importacao
ALTER TABLE crm_contatos ADD COLUMN IF NOT EXISTS optin_data   timestamptz;

-- ── wa_templates: modelos de mensagem da Meta (aprovados 1x) ──
CREATE TABLE IF NOT EXISTS wa_templates (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id     uuid,
    nome             text NOT NULL,                         -- slug, ex.: orcamento_pronto
    categoria        text NOT NULL DEFAULT 'utility',       -- marketing|utility|authentication
    idioma           text NOT NULL DEFAULT 'pt_BR',
    corpo            text NOT NULL DEFAULT '',              -- com {{1}}, {{2}}...
    componentes      jsonb DEFAULT '{}'::jsonb,             -- header/footer/botões
    status_aprovacao text NOT NULL DEFAULT 'rascunho',      -- rascunho|submetido|aprovado|rejeitado|pausado
    external_id      text,                                  -- id do template na Meta (após sync)
    criado_em        timestamptz NOT NULL DEFAULT now(),
    atualizado_em    timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_wa_templates_ws ON wa_templates (workspace_id, nome);

-- ── wa_webhook_eventos: fila de eventos brutos da Meta (200 rápido → processa async) ──
CREATE TABLE IF NOT EXISTS wa_webhook_eventos (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id  uuid,
    external_id   text,                                     -- wamid (idempotência)
    tipo          text,                                     -- message|status|unknown
    payload       jsonb NOT NULL,
    recebido_em   timestamptz NOT NULL DEFAULT now(),
    processado_em timestamptz,
    erro          text
);
-- idempotência real do webhook: um wamid só processa uma vez
CREATE UNIQUE INDEX IF NOT EXISTS uq_wa_webhook_extid ON wa_webhook_eventos (external_id) WHERE external_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_wa_webhook_pendentes ON wa_webhook_eventos (processado_em) WHERE processado_em IS NULL;

-- ── campanhas: schema p/ Meta (motor fica p/ bloco futuro no chatbot-platform) ──
ALTER TABLE campanhas         ADD COLUMN IF NOT EXISTS template_id uuid;
ALTER TABLE campanhas         ADD COLUMN IF NOT EXISTS provider    text DEFAULT 'evolution';
ALTER TABLE campanha_contatos ADD COLUMN IF NOT EXISTS external_id     text;
ALTER TABLE campanha_contatos ADD COLUMN IF NOT EXISTS status_entrega  text;

-- ═══════════════════════════════════════════════════════════════════════════════
-- DOWN (reverter) — rodar SÓ se quiser desfazer o Bloco 1:
-- DROP TABLE IF EXISTS wa_webhook_eventos;
-- DROP TABLE IF EXISTS wa_templates;
-- ALTER TABLE campanha_contatos DROP COLUMN IF EXISTS external_id, DROP COLUMN IF EXISTS status_entrega;
-- ALTER TABLE campanhas DROP COLUMN IF EXISTS template_id, DROP COLUMN IF EXISTS provider;
-- ALTER TABLE crm_contatos DROP COLUMN IF EXISTS optin_status, DROP COLUMN IF EXISTS optin_origem, DROP COLUMN IF EXISTS optin_data;
-- DROP INDEX IF EXISTS idx_crm_mensagens_wamid;
-- ALTER TABLE crm_mensagens DROP COLUMN IF EXISTS provider, DROP COLUMN IF EXISTS erro_codigo, DROP COLUMN IF EXISTS erro_detalhe,
--   DROP COLUMN IF EXISTS entregue_em, DROP COLUMN IF EXISTS lido_em, DROP COLUMN IF EXISTS falhou_em;
-- ALTER TABLE crm_cards DROP COLUMN IF EXISTS provider, DROP COLUMN IF EXISTS ultima_msg_recebida_em, DROP COLUMN IF EXISTS janela_expira_em;
-- ALTER TABLE crm_instancias DROP COLUMN IF EXISTS provider, DROP COLUMN IF EXISTS meta_phone_number_id,
--   DROP COLUMN IF EXISTS meta_waba_id, DROP COLUMN IF EXISTS meta_graph_version;
