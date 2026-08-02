-- ── Certificado de Análise (laudo) completo por lote de MP — armazenamento RDC 67 ──
-- 1 laudo ↔ 1 lote (lab_lotes_mp). Guarda o cabeçalho completo + conservação + conclusão
-- + responsável técnico + URL do PDF arquivado. Os ensaios ficam em lab_laudo_ensaios.
-- Preenchido só APÓS a confirmação da farmacêutica na tela de revisão.

CREATE TABLE IF NOT EXISTS lab_laudos (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id        uuid NOT NULL REFERENCES clientes(id),
    lote_id           uuid REFERENCES lab_lotes_mp(id) ON DELETE CASCADE,
    ativo_id          uuid,
    molde_id          uuid,                 -- laudo_modelos.id que extraiu (null = IA/manual)
    nf_chave          text,                 -- chave da NF de origem
    -- identificação
    produto_nome      text,                 -- "Produto:" (nome no laudo)
    nome_cientifico   text,
    sinonimia         text,
    parte_utilizada   text,
    dcb               text,
    cas               text,
    formula_molecular text,
    peso_molecular    text,
    -- lotes / datas
    lote_original     text,
    lote_interno      text,
    dt_fabricacao     date,
    dt_validade       date,
    dt_emissao        date,
    -- origem
    origem            text,
    procedencia       text,
    fabricante        text,
    -- conservação / natureza
    conservacao_temp  text,
    conservacao_umid  text,
    higroscopico      boolean,
    fotossensivel     boolean,
    -- conclusão
    conclusao         text,
    resultado         text,                 -- 'aprovado' | 'reprovado' | null
    conforme_geral    boolean,              -- todos os ensaios dentro da especificação?
    rt_nome           text,                 -- responsável técnico
    rt_crf            text,
    -- arquivo
    pdf_url           text,                 -- URL no Storage (bucket laudos)
    pdf_nome          text,                 -- nome do arquivo original
    texto_extraido    text,                 -- texto bruto (fallback/auditoria)
    conferido_por     bigint,               -- user que confirmou na revisão
    conferido_em      timestamptz,
    criado_em         timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_lab_laudos_lote ON lab_laudos (lote_id);
CREATE INDEX IF NOT EXISTS idx_lab_laudos_cli_nf ON lab_laudos (cliente_id, nf_chave);

-- Campo de destino do arquivo caso a coluna laudo_url do lote não exista ainda:
ALTER TABLE lab_lotes_mp ADD COLUMN IF NOT EXISTS laudo_url text;
ALTER TABLE lab_lotes_mp ADD COLUMN IF NOT EXISTS nr_laudo text;
