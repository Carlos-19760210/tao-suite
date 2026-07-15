-- ============================================================================
-- TAO Fórmula — validade da fórmula PARAMETRIZÁVEL por forma farmacêutica
-- ----------------------------------------------------------------------------
-- Substitui a regra fixa no código (floral 90 / demais 120) por um parâmetro
-- cadastrável por forma (equivalente ao argumento PRAZOVALxx do FCerta).
-- A validade final da OM continua sendo a MENOR entre este prazo e a validade
-- do lote usado (regra VALIDADELOTE — já implementada em tao_formula_recalc_validade_om).
--
-- Seed: preserva o comportamento atual (floral=90, demais=120), agora editável
-- na tela Cadastros → Formas Farmacêuticas.
-- ============================================================================

ALTER TABLE formas_farmaceuticas ADD COLUMN IF NOT EXISTS validade_dias integer;

UPDATE formas_farmaceuticas SET validade_dias = 90  WHERE tipo = 'floral' AND validade_dias IS NULL;
UPDATE formas_farmaceuticas SET validade_dias = 120 WHERE validade_dias IS NULL;
