# Motor Farmacotécnico TAO — design v1
Baseado nos dados REAIS do FCerta da Magis (06/07/2026). É o pré-requisito de todo o resto do TAO Lab.

## Como a Magis trabalha hoje (extraído do banco)

1. **Diluição é um PRODUTO, não um atributo da fórmula.** A MP diluída é cadastrada como item próprio — "FINASTERIDA 1:10", "VIT B12 1:50", "PICOLINATO DE CROMO 1:100", "METILCOBALAMINA 1:20", "BIOTINA 1:10" — com o fator no campo DILUI (10, 50, 100, 20). 466.594 itens de fórmula (85%) usam MP diluída.
2. **A "produção interna" da Magis = fabricar lotes da MP diluída**: lotes com numeração interna curta (1639, 1640, 1642, 1643, 1644...), validade própria e estoque próprio, produzidos a partir do lote da MP pura. O FCerta tem vínculo formal lote-diluído→lote-puro (CDPROPRODPURO), usado em 89 lotes.
3. **Dados técnicos vivem em 2 níveis:**
   - **Produto (FC03000)**: fator de correção (980 MPs com FATOR ≠ 1; ex.: RESVERATROL 5, ARGIRE 4.5 — ⚠️ validar semântica com a RT), diluição nominal, teor nominal, densidade, DCB (1.880 preenchidos).
   - **LOTE (FC03140, 15.169 lotes)**: teor REAL do laudo (ex.: AC LÁTICO teor 85%, densidade 1,21), diluição, densidade, e **conversões de unidade por lote**: UI/g, mEq/g, bilhões UFC/g (probióticos), UTR, MLH — porque cada laudo traz a potência real.
4. **Equivalência (sal↔base) via sinônimo**: FC03200 (4.147 regs) = sinônimo do produto com campo EQUIV (fator aplicado quando a receita usa o nome alternativo). Espelho direto do nosso `ativos_sinonimos` — que ganha o fator.

## O cálculo que o motor precisa fazer (pesagem real)

```
qtd_a_pesar = dose_prescrita
            × fator_equivalencia (se o nome prescrito é sal/éster ≠ forma em estoque)
            × fator_correcao (do produto)
            × (100 / teor_do_LOTE)           ← teor real do laudo
            × fator_diluicao (se MP diluída: 1:10 → ×10 já embutido no produto diluído)
com conversões:
  dose em UI/mEq/UFC → gramas via ui_por_g / meq_por_g / ufc_por_g DO LOTE
  volume ↔ massa via densidade DO LOTE (líquidos)
```
E validações: **dose máxima** por substância (alerta/bloqueio), substância **proibida/restrita** (semaglutida etc.), lote **vencido/reprovado** bloqueado.

## Mudanças de schema (v2 — substitui parte do draft v1)

### `ativos` (ALTER — já existe)
```
dcb text, fator_correcao numeric DEFAULT 1, teor_pct numeric DEFAULT 100,
densidade numeric DEFAULT 1, fator_diluicao numeric DEFAULT 1,      -- >1 = MP diluída
ativo_puro_id uuid REFERENCES ativos(id),                            -- diluída → pura
ui_por_g numeric, meq_por_g numeric, ufc_bi_por_g numeric,           -- conversões nominais
dose_max_dia numeric, dose_max_unidade text,                         -- alerta RDC
restricao text  -- NULL | 'bloqueada' (semaglutida) | 'restrita' | 'controlada_344:<lista>'
```

### `lab_lotes_mp` (ajuste do draft v1)
```
+ origem text DEFAULT 'fornecedor'      -- fornecedor | producao_interna
+ lote_puro_id uuid REFERENCES lab_lotes_mp(id)   -- diluição interna: rastreio ao lote puro
+ teor_pct numeric, densidade numeric, fator_diluicao numeric        -- LAUDO REAL do lote
+ ui_por_g numeric, meq_por_g numeric, ufc_bi_por_g numeric
+ qc_resultado text, qc_aprovado_por bigint, qc_em timestamptz       -- CQ de recebimento
```

### `lab_producoes_internas` (nova — a OM de diluição)
```
id, cliente_id, numero bigserial, ativo_id (a diluída), lote_gerado_id,
lote_puro_id (consumido), qtd_puro_usada, qtd_veiculo, veiculo_ativo_id,
qtd_produzida, dt_producao, dt_validade, produzido_por, conferido_por, obs
```
→ gera `lab_lotes_mp` origem='producao_interna' e BAIXA o lote puro. Rastreio completo: fórmula → lote diluído → lote puro → NF do fornecedor.

### `ativos_sinonimos` (ALTER)
```
+ fator_equiv numeric DEFAULT 1   -- equivalência sal↔base quando prescrito pelo sinônimo
```

### `lab_formulas_padrao` + `lab_formulas_padrao_itens` (novas — FC05000/FC05100, 734+2.921 na Magis)
```
cab: id, cliente_id, nome, forma_farmac, volume, unidade, tipo_capsula, posologia, qsp bool, obs, ativo
itens: id, formula_id, ativo_id, descricao, qtd, unidade, eh_qsp, ordem
```

## Migração de dados (FCerta → TAO)
| Origem | Destino | Volume |
|---|---|---|
| FC03000 (FATOR/TEOR/DILUICAO/DENSIDADE/CDDCB) | ativos.* | 1.735 ativos da Magis |
| FC03140 lotes ATIVOS (ESTAT>0, não vencidos) | lab_lotes_mp | ~centenas |
| FC03200 (EQUIV>0) | ativos_sinonimos.fator_equiv | subset dos 4.147 |
| FC05000/FC05100 | lab_formulas_padrao(+itens) | 734 + 2.921 |
| FC71600 (Zanini dose máx) | ativos.dose_max_dia (revisão RT) | 19.596 substâncias catálogo |
| Vínculo nome "X 1:N" → produto puro | ativos.ativo_puro_id + fator_diluicao | parse do nome + validação RT |

## Integração com o que já existe
- **TAO Fórmula (editor/orçamento)**: o motor entra ANTES da OM — o orçamento já calcula com fator/teor/diluição/equivalência (hoje só faz cápsula/QSP/VOLAPA). O custo real por g da diluída é derivado do puro + veículo.
- **Cotações**: MP diluída nunca é comprada — cotação sempre sugere a PURA (ativo_puro_id).
- **OM (draft v1)**: `lab_ordem_itens.qtd_pesada` passa a ser CALCULADA pelo motor e conferida na pesagem.

## Validações pendentes com a RT
1. Semântica do FATOR 3.5–5 nos cosméticos (correção? uso?).
2. Regra de validade da diluição interna (hoje: lotes 1:N com ~1-2 anos — qual a política?).
3. Revisão das doses máximas antes de ativar o alerta (base Zanini está desatualizada?).
