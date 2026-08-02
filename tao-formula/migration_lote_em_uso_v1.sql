-- Lote "em uso" (frasco aberto) — espelha o INDEMUSO do FCerta.
-- Ganha prioridade sobre o FEFO puro: o sistema consome o lote já aberto até acabar,
-- e só então abre o próximo (o de menor validade). Marcado ao pesar na produção.
ALTER TABLE lab_lotes_mp ADD COLUMN IF NOT EXISTS em_uso boolean NOT NULL DEFAULT false;
