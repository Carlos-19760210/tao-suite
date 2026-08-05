-- ============================================================
-- TAO Lab — PACOTE 4 / FASE A: fechamento de período SNGPC
-- Cria o "arquivo de transmissão" (equivalente FC99S20 do FCerta) e
-- completa o livro (sngpc_movimentos) com os campos que o schema
-- oficial <mensagemSNGPC xmlns="urn:sngpc-schema"> exige na SAÍDA.
-- Aditiva e idempotente. Rodar no SQL Editor do Supabase.
-- NÃO transmite nada — só habilita gerar/fechar/confrontar em sombra.
-- ============================================================

-- 1. Arquivo de período (5 dias) — cabeçalho de cada transmissão
CREATE TABLE IF NOT EXISTS sngpc_arquivos (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    cd_filial     int  NOT NULL DEFAULT 1,
    periodo_ini   date NOT NULL,
    periodo_fim   date NOT NULL,
    sequencial    int  NOT NULL,                       -- nº sequencial do arquivo (por filial)
    nome_arquivo  text,                                -- SNGPCddmmaaaaN
    hash          text,                                -- sha1 do XML gerado
    xml           text,                                -- o mensagemSNGPC (schema oficial)
    schema_ver    text DEFAULT 'urn:sngpc-schema',
    qtd_saidas    int DEFAULT 0,
    qtd_perdas    int DEFAULT 0,
    qtd_entradas  int DEFAULT 0,
    status        text NOT NULL DEFAULT 'gerado',      -- gerado|transmitido|aceito|rejeitado
    dt_receb      timestamptz,                         -- data de recebimento na ANVISA
    protocolo     text,
    retorno       text,                                -- MSGRETORNO da ANVISA
    criado_por    bigint,
    criado_em     timestamptz DEFAULT now()
);
CREATE INDEX  IF NOT EXISTS idx_sngpc_arq      ON sngpc_arquivos (cliente_id, periodo_ini);
CREATE UNIQUE INDEX IF NOT EXISTS idx_sngpc_arq_seq ON sngpc_arquivos (cliente_id, cd_filial, sequencial);
ALTER TABLE sngpc_arquivos ENABLE ROW LEVEL SECURITY;

-- 2. Liga cada movimento ao arquivo em que foi fechado/transmitido
ALTER TABLE sngpc_movimentos ADD COLUMN IF NOT EXISTS arquivo_id uuid REFERENCES sngpc_arquivos(id);

-- 3. Campos que o schema oficial de SAÍDA exige e o livro ainda não tinha
--    (preenchidos pela escrituração da OM; nullable p/ não travar o que já roda)
ALTER TABLE sngpc_movimentos ADD COLUMN IF NOT EXISTS dt_prescricao      date;   -- DTPRESCR
ALTER TABLE sngpc_movimentos ADD COLUMN IF NOT EXISTS orgao_expedidor    text;   -- UFORGAO (órgão emissor do doc do comprador)
ALTER TABLE sngpc_movimentos ADD COLUMN IF NOT EXISTS uso_prolongado     boolean DEFAULT false; -- INDUSOPROL
ALTER TABLE sngpc_movimentos ADD COLUMN IF NOT EXISTS unidade_farmac     text;   -- TPUNIDAFAR (unidade farmacotécnica: cap, ml...)
ALTER TABLE sngpc_movimentos ADD COLUMN IF NOT EXISTS qtd_unidades_farmac numeric; -- QUANTFAR (nº de unidades farmacotécnicas)
ALTER TABLE sngpc_movimentos ADD COLUMN IF NOT EXISTS cnpj_forn_insumo   text;   -- CNPJFORNEC do insumo usado na manipulação (saída/perda)

-- Validação
SELECT 'sngpc_arquivos'            t, COUNT(*) n FROM information_schema.tables  WHERE table_schema='public' AND table_name='sngpc_arquivos'
UNION ALL
SELECT 'sngpc_movimentos.arquivo_id', COUNT(*)   FROM information_schema.columns WHERE table_name='sngpc_movimentos' AND column_name='arquivo_id'
UNION ALL
SELECT 'sngpc_movimentos.novos6',     COUNT(*)   FROM information_schema.columns WHERE table_name='sngpc_movimentos'
   AND column_name IN ('dt_prescricao','orgao_expedidor','uso_prolongado','unidade_farmac','qtd_unidades_farmac','cnpj_forn_insumo');
-- esperado: sngpc_arquivos 1 | arquivo_id 1 | novos6 6
