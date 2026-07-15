-- ============================================================================
-- TAO Lab — Onda 2b: CID-10 (catálogo + diagnóstico OPCIONAL no orçamento)
-- CID é de domínio público (OMS/Datasus). NUNCA obrigatório. Espelho FC99311.
-- Idempotente. Rodar antes do deploy; a carga do catálogo é à parte (com OK).
-- ============================================================================

-- 1) Catálogo CID-10 (global — referência pública, sem tenant)
CREATE TABLE IF NOT EXISTS cid10 (
    codigo     text PRIMARY KEY,     -- ex: E11.9
    descricao  text NOT NULL,
    capitulo   text,
    busca      text                  -- codigo + descrição normalizada p/ ilike
);
CREATE INDEX IF NOT EXISTS idx_cid10_busca ON cid10 USING gin (to_tsvector('portuguese', coalesce(busca,'')));

-- 2) Diagnóstico opcional no orçamento (e herdado na OM)
ALTER TABLE orcamentos
    ADD COLUMN IF NOT EXISTS cid_codigo    text,
    ADD COLUMN IF NOT EXISTS cid_descricao text;
