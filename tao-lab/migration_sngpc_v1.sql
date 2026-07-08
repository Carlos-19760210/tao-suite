-- ============================================================
-- TAO Lab — PACOTE 4: CONTROLADOS / SNGPC (Portaria 344/98 + RDC 27/22)
-- Espelha FC99S21 (entradas) / FC99S22 (saídas por receita) / FC99S23
-- (transferências) / FC99S24 (perdas). Escrituração + base p/ XML ANVISA.
-- Reusa ativos e lab_ordens. Rodar no SQL Editor. Aditiva.
-- Escopo Magis: controlados Port.344 + antimicrobianos RDC 471 (sem GLP-1/estéreis).
-- ============================================================

-- 1. Marca o ativo como controlado + classe SNGPC
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS controlado    boolean DEFAULT false;
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS classe_sngpc  text;   -- A1,A2,A3,B1,B2,C1,C2,C5,ANTIMICROBIANO...
ALTER TABLE ativos ADD COLUMN IF NOT EXISTS registro_ms   text;   -- nº registro MS (CDREGISTROMS)

-- 2. Livro eletrônico — todos os movimentos de controlados
CREATE TABLE IF NOT EXISTS sngpc_movimentos (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    cliente_id    uuid NOT NULL REFERENCES clientes(id),
    cd_filial     int  NOT NULL DEFAULT 1,
    tipo          text NOT NULL,          -- entrada|saida|perda|transferencia|inventario
    ativo_id      uuid REFERENCES ativos(id),
    dcb           text,
    classe_sngpc  text,
    registro_ms   text,
    nr_lote       text,
    quantidade    numeric NOT NULL,
    unidade       text,
    dt_movimento  date NOT NULL DEFAULT current_date,
    -- ENTRADA (FC99S21)
    fornecedor_cnpj text,
    nf_numero       text,
    -- SAÍDA por receita (FC99S22)
    prescritor_nome     text,
    prescritor_conselho text,   -- CRM/CRO...
    prescritor_nr       text,
    prescritor_uf       text,
    tp_receita          text,   -- notif_A|notif_B|especial_branca|receita_2vias|antimicrobiano
    nr_notificacao      text,
    comprador_nome      text,
    comprador_doc_tp    text,   -- RG|CPF|CNH
    comprador_doc_nr    text,
    comprador_uf        text,
    uso_med             text,   -- interno|externo
    -- PERDA (FC99S24)
    tp_perda            text,   -- quebra|vencimento|roubo|desvio|inutilizacao
    -- TRANSFERÊNCIA (FC99S23)
    cnpj_destino        text,
    -- controle
    origem        text,          -- nf|om|manual|inventario
    ref_id        uuid,          -- entrada_nf / lab_ordens / lote
    transmitido   boolean DEFAULT false,
    dt_transmissao timestamptz,
    protocolo     text,
    criado_por    bigint,
    criado_em     timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_sngpc_mov ON sngpc_movimentos (cliente_id, dt_movimento, tipo);
CREATE INDEX IF NOT EXISTS idx_sngpc_transm ON sngpc_movimentos (cliente_id, transmitido);

-- 3. Dados de Notificação de Receita na OM (controlado) — retenção + comprador
ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS tp_receita       text;
ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS nr_notificacao   text;
ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS comprador_nome   text;
ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS comprador_doc_tp text;
ALTER TABLE lab_ordens ADD COLUMN IF NOT EXISTS comprador_doc_nr text;
-- receita_url já existe (retenção da receita)

ALTER TABLE sngpc_movimentos ENABLE ROW LEVEL SECURITY;

-- Validação
SELECT 'sngpc_movimentos' t, COUNT(*) n FROM information_schema.tables WHERE table_schema='public' AND table_name='sngpc_movimentos'
UNION ALL SELECT 'ativos.controlado', COUNT(*) FROM information_schema.columns WHERE table_name='ativos' AND column_name IN ('controlado','classe_sngpc','registro_ms')
UNION ALL SELECT 'lab_ordens.receita', COUNT(*) FROM information_schema.columns WHERE table_name='lab_ordens' AND column_name IN ('tp_receita','nr_notificacao','comprador_nome');
-- esperado: sngpc_movimentos 1 | ativos.controlado 3 | lab_ordens.receita 3
