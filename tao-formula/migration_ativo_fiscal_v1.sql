-- Config fiscal como ATRIBUTO do ativo (cadastro único do ativo). Aditiva e idempotente.
-- Consolida a tabela paralela fiscal_produtos DENTRO de ativos e a aposenta (sem dropar).
-- Os 3 valores (custo/compra/compra-com-frete) já são colunas do ativo:
--   preco_custo (mercado) · preco_compra (pago s/ frete) · custo_com_frete (base de venda).

-- 1) Colunas fiscais no ativo
alter table ativos add column if not exists ncm            text;
alter table ativos add column if not exists cest           text;
alter table ativos add column if not exists gtin           text;
alter table ativos add column if not exists cst_pis        text;
alter table ativos add column if not exists cst_cofins     text;
alter table ativos add column if not exists cst_icms       text;   -- CST (regime normal)
alter table ativos add column if not exists csosn          text;   -- CSOSN (Simples)
alter table ativos add column if not exists cfop_venda     text;
alter table ativos add column if not exists aliquota_icms  numeric;
alter table ativos add column if not exists icms_origem    text;   -- 0..8 (origem da mercadoria)
alter table ativos add column if not exists ind_iss        boolean default false;
alter table ativos add column if not exists icms_cod_fcerta text;  -- código interno do FCerta (referência)
alter table ativos add column if not exists fiscal_revisado boolean default false;
alter table ativos add column if not exists fiscal_obs     text;

-- 2) Migrar fiscal_produtos -> ativos (por cliente_id + codigo_fc), sem sobrescrever o já preenchido.
update ativos a set
    ncm             = coalesce(a.ncm,            f.ncm),
    cest            = coalesce(a.cest,           f.cest),
    gtin            = coalesce(a.gtin,           f.gtin),
    cst_pis         = coalesce(a.cst_pis,        f.cst_pis),
    cst_cofins      = coalesce(a.cst_cofins,     f.cst_cofins),
    cst_icms        = coalesce(a.cst_icms,       f.cst_icms),
    csosn           = coalesce(a.csosn,          f.csosn),
    cfop_venda      = coalesce(a.cfop_venda,     f.cfop_venda),
    aliquota_icms   = coalesce(a.aliquota_icms,  f.aliquota_icms),
    icms_origem     = coalesce(a.icms_origem,    f.origem),
    ind_iss         = coalesce(a.ind_iss,        f.ind_iss),
    icms_cod_fcerta = coalesce(a.icms_cod_fcerta,f.icms_cod_fcerta),
    fiscal_revisado = coalesce(a.fiscal_revisado,f.revisado_contador),
    fiscal_obs      = coalesce(a.fiscal_obs,     f.obs)
from fiscal_produtos f
where f.cliente_id = a.cliente_id
  and f.codigo_fc  = a.codigo_fc
  and f.codigo_fc is not null and f.codigo_fc <> '';

-- 3) fiscal_produtos fica APOSENTADA (não é mais lida/gravada). Mantida como backup até a
--    validação; dropar só depois de OK explícito:  -- drop table fiscal_produtos;
