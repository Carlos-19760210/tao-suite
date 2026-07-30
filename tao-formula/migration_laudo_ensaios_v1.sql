-- Ensaios detalhados do laudo (Certificado de Análise) por lote de MP.
-- Um laudo → N ensaios (teste × especificação × resultado × conforme). Alimenta o CQ RDC 67.
CREATE TABLE IF NOT EXISTS lab_laudo_ensaios (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    lote_id       uuid NOT NULL REFERENCES lab_lotes_mp(id) ON DELETE CASCADE,
    teste         text,
    especificacao text,
    resultado     text,
    conforme      boolean,        -- true=atende, false=fora, null=não julgado
    criado_em     timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_lab_laudo_ensaios_lote ON lab_laudo_ensaios(lote_id);
