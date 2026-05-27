-- TAO CRM v1.6.0 — Status de entrega de mensagens
-- Execute no Supabase SQL Editor (idempotente)

-- Adiciona colunas de delivery status às mensagens
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS wamid TEXT DEFAULT NULL;
ALTER TABLE crm_mensagens ADD COLUMN IF NOT EXISTS status_entrega TEXT DEFAULT NULL;
-- status_entrega: NULL | 'pending' | 'sent' | 'delivered' | 'read'

-- Índice para busca rápida por wamid (Evolution webhook update)
CREATE INDEX IF NOT EXISTS idx_crm_mensagens_wamid ON crm_mensagens (wamid)
  WHERE wamid IS NOT NULL;
