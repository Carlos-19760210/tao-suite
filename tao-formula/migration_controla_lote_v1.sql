-- Controle de lote por produto (espelha o FCerta: matéria-prima controla lote / rastreio).
-- Quando true, o sistema escolhe o lote LIBERADO (aprovado) por FEFO na produção e
-- aplica teor/fator do lote. Default true (todo ativo de manipulação rastreia lote).
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS controla_lote boolean NOT NULL DEFAULT true;
