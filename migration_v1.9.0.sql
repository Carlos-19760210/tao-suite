-- ══════════════════════════════════════════════════════════════════════════
-- Migration v1.9.0 — Equalização com automações do Bitrix (Jul 2026)
-- Novo gatilho:  enviou_mensagem  (atendente respondeu pelo CRM → mover fase)
-- Nova ação:     fechar_perdido   (ex.: Última Tentativa 5 dias sem resposta)
-- Rodar no Supabase SQL Editor.
-- ══════════════════════════════════════════════════════════════════════════

-- 1. Amplia o CHECK de tipo (gatilho)
ALTER TABLE crm_automacoes DROP CONSTRAINT IF EXISTS crm_automacoes_tipo_check;
ALTER TABLE crm_automacoes ADD CONSTRAINT crm_automacoes_tipo_check
  CHECK (tipo IN ('entrar_fase','sair_fase','tempo_na_fase',
                  'recebeu_mensagem','enviou_mensagem','sem_resposta'));

-- 2. Amplia o CHECK de acao
ALTER TABLE crm_automacoes DROP CONSTRAINT IF EXISTS crm_automacoes_acao_check;
ALTER TABLE crm_automacoes ADD CONSTRAINT crm_automacoes_acao_check
  CHECK (acao IN ('enviar_mensagem','mover_fase','atribuir_responsavel',
                  'notificar_email','atribuir_responsavel_rr','fechar_perdido'));
