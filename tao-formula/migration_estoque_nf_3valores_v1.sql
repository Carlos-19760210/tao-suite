-- Entrada de NF: os 3 valores por item (custo/compra/compra-c-frete) + frete rateado.
-- Substitui o antigo modelo 'destino_valor'. Aditiva e idempotente.
alter table estoque_entradas_nf_itens add column if not exists valor_compra       numeric; -- pago s/ frete (unit)
alter table estoque_entradas_nf_itens add column if not exists frete_rateado       numeric; -- rateio do frete por valor (unit)
alter table estoque_entradas_nf_itens add column if not exists valor_compra_frete  numeric; -- compra + frete = BASE de venda
alter table estoque_entradas_nf_itens add column if not exists valor_custo         numeric; -- mercado (= compra se sem ref.)
