-- Sinônimos: variante de ativo por FORMA (cápsula) — motor de associação da receita/IA.
-- Um sinônimo aponta para `ativo_id` (alvo padrão: gel/creme/tópico) e, opcionalmente,
-- para `ativo_id_cap` (alvo quando a fórmula for CÁPSULA).
-- Ex.: "VITAMINA C" -> LIPOSSOMAL (padrão) e, em cápsula, -> REVESTIDA.
-- Idempotente / aditivo.

ALTER TABLE ativos_sinonimos
  ADD COLUMN IF NOT EXISTS ativo_id_cap uuid NULL REFERENCES ativos(id);

CREATE INDEX IF NOT EXISTS idx_ativos_sinonimos_ativo_id_cap
  ON ativos_sinonimos (ativo_id_cap);

COMMENT ON COLUMN ativos_sinonimos.ativo_id_cap IS
  'Ativo alternativo usado quando a forma farmacêutica for cápsula; NULL usa ativo_id.';
