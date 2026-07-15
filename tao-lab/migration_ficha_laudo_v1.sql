-- ============================================================================
-- TAO Lab — Ficha técnica da MP (FC81000) + Laudo/certificado por lote (FC21000)
-- RDC 67: especificação da matéria-prima + laudo de análise arquivado por lote.
-- Idempotente. Rodar antes do deploy.
-- ============================================================================

-- 1) Ficha técnica da matéria-prima (especificação) — campos no ativo
ALTER TABLE ativos
    ADD COLUMN IF NOT EXISTS ft_nome_quimico     text,
    ADD COLUMN IF NOT EXISTS ft_formula_molecular text,
    ADD COLUMN IF NOT EXISTS ft_peso_molecular   text,
    ADD COLUMN IF NOT EXISTS ft_caracteres       text,   -- aspecto/cor/odor (organoléptico)
    ADD COLUMN IF NOT EXISTS ft_ponto_fusao      text,
    ADD COLUMN IF NOT EXISTS ft_solubilidade     text,
    ADD COLUMN IF NOT EXISTS ft_ph               text,
    ADD COLUMN IF NOT EXISTS ft_grau_pureza      text,   -- faixa de teor / pureza
    ADD COLUMN IF NOT EXISTS ft_conservacao      text,   -- armazenamento / conservação
    ADD COLUMN IF NOT EXISTS ft_referencias      text,   -- farmacopeia de referência
    ADD COLUMN IF NOT EXISTS ft_revisao          text;   -- versão/data da ficha

-- 2) Laudo/certificado de análise por lote (laudo_url já existe em lab_lotes_mp)
ALTER TABLE lab_lotes_mp
    ADD COLUMN IF NOT EXISTS nr_laudo text;              -- nº do certificado/laudo do fabricante
