# TAO Lab — PACOTE 2: ESTOQUE (desenho para aprovação)
Rascunho 07/07/2026 · baseado na cadeia de estoque do FCerta (rastreada) + especificações do Carlos.
Status: **DESENHO — nada executado.** Aguarda OK formal antes de migration/código.

---

## 1. Objetivo
Assumir o controle de estoque hoje no FCerta: **entrada de NF (compra) → lote com laudo → estoque → baixa por produção/venda → mínimo/curva**, alimentando o motor farmacotécnico (lotes FEFO), as Cotações (Últ. Pago) e o financeiro (contas a pagar).

## 2. Escopo
**DENTRO:** entrada de NF por XML + conferência assistida, lotes de MP (laudo/validade/CQ), kardex de movimentos, saldo por ativo e por lote, inventário, estoque mínimo/curva, contas a pagar das duplicatas, baixa por OM (liga com o Pacote 3).
**FORA (decisão Carlos):** balança, peso médio, PCP. Transferência entre filiais só se confirmado.

## 3. Modelo FCerta decifrado (referência)
Cadeia rastreada na NF 984338: **FC11000** cab (FLAGATU=S=efetivada) → **FC11100** itens (PRUNI + laudo por item: NRLOT/DTFAB/DTVAL/TEOR/DILUI/DENSI) → cria **FC03140** lote (ciclo STLOT **P**=novo→**L**=liberado→**B**=baixado = FEFO) → **FC03100** estoque do produto (ESTAT + ESTMI/ESTMA) → **FC03110** kardex mensal → **FC11200** duplicatas (financeiro) → **FC99S21** SNGPC p/ controlados.
Baixa na produção: **FC12110 TPCMP=R** = explosão de estoque (diluída baixa a MP primária + excipiente). XML é padrão (505/605 NFs com CHAVENFE). Casamento item→produto no FCerta é **assistido** (não há de-para automático).

## 4. Arquitetura TAO proposta

### 4.1 Tabelas
- **`lab_lotes_mp`** (JÁ EXISTE, 1.167 lotes vivos) — recebe os lotes ao efetivar a NF. Já tem nf_numero, nf_chave, fornecedor_id, laudo (teor/densidade/diluição), validade, status CQ (quarentena→aprovado).
- **`estoque_entradas_nf`** (NOVO — cabeçalho): fornecedor_id, cnpj_emitente, chave_nfe, numero, serie, dt_emissao, dt_entrada, valor_total, status (`rascunho`|`conferindo`|`efetivada`|`cancelada`), xml_url, criado_por.
- **`estoque_entradas_nf_itens`** (NOVO): entrada_id, **ativo_id** (casado), cod_xml, ean, descr_xml, quantidade, unidade, preco_unit, desconto, **lote, dt_fab, dt_val, teor, densidade, diluicao** (laudo/grupo K), **destino_valor** (`custo`|`compra`|`ambos`).
- **`estoque_forn_depara`** (NOVO — de-para aprendido): fornecedor_id, **cod_fornecedor** (cProd do XML — a Magis **NÃO usa EAN**, decisão Carlos), descr_fornecedor, **ativo_id**, criado_em. **Chave (fornecedor_id, cod_fornecedor)** → na próxima NF já vem associado. É a memória da associação.
- **`estoque_movimentos`** (NOVO — kardex): ativo_id, lote_id, tipo (`entrada`|`saida`|`ajuste`|`perda`|`transferencia`), quantidade (±), origem (`nf`|`om`|`inventario`|`manual`), ref_id, saldo_apos, dt, usuario.
- **`contas_pagar`** (NOVO — financeiro): fornecedor_id, entrada_nf_id, numero_dup, vencimento, valor, status (`aberto`|`pago`), dt_pagamento. (O TAO Caixa hoje só faz PDV/venda — contas a pagar é novo.) **Fica no TAO (decisão Carlos) + relatório exportável ao contador.**
- **ALTER `ativos`**: est_min, est_max, curva (A/B/C) — para alerta de reposição.

### 4.2 Fluxo de ENTRADA DE NF (o coração do pacote)
1. **Upload do XML** da NFe (ou digitação manual da nota).
2. **Parse**: emitente (CNPJ → casa com `fornecedores`; se novo, oferece cadastro), itens (cod/EAN/descr/qtd/valor + rastreabilidade grupo K: lote/fabricação/validade quando houver no XML).
3. **Tela de conferência — SEMPRE lista TODOS os itens da NF** (regra Carlos):
   - Cada item já vem **pré-associado** pelo de-para (`estoque_forn_depara`) quando o fornecedor+código já foi visto antes.
   - Itens **sem associação** abrem busca de ativo (autocomplete) para o farmacêutico associar. **A associação é gravada no de-para e só é feita uma vez** — nas próximas NFs daquele fornecedor o item já vem casado.
   - O farmacêutico **valida a lista inteira** (associação + lote + validade + teor do laudo + preço) antes de efetivar.
   - **Destino do valor por item** (regra Carlos): seletor **Custo / Compra / Ambos** — define se o PRUNI da NF atualiza `custo_por_unidade`, `preco_compra`, ou os dois no cadastro do ativo. **Default = Compra** (decisão Carlos: "normalmente compra, mas existe a variação"), ajustável por item na conferência.
4. **CQ de recebimento (RDC 67)**: lote entra `quarentena` → farmacêutico aprova/reprova (registro de quem e quando).
5. **Efetivar** (idempotente, transacional-lógico):
   - cria os **lotes** em `lab_lotes_mp` (com laudo real);
   - lança **movimentos de entrada** no kardex e atualiza **saldo** do ativo e do lote;
   - atualiza **preço** do ativo conforme destino_valor escolhido;
   - gera **contas a pagar** das duplicatas;
   - grava o **Últ. Pago** que as Cotações (Fase 2) consomem.

### 4.3 Baixa de estoque
- Por **OM/produção** (Pacote 3) ou aprovação de orçamento: baixa **FEFO por lote**, com **explosão tipo TPCMP=R** (diluída → MP primária pura + excipiente) — modelo já decifrado.
- **Inventário**: contagem → gera movimento de ajuste (com trilha).

### 4.4 Mínimo / curva → Cotações
est_min/est_max/curva por ativo → **alerta de reposição** → realimenta o módulo **Cotações** (já existe): o que está abaixo do mínimo vira sugestão de cotação.

## 5. Integrações
- **Cotações**: PRUNI da NF → `Últ. Pago` (Fase 2 das cotações já previa).
- **Motor farmacotécnico / FEFO**: a entrada passa a alimentar de verdade os `lab_lotes_mp` que o Motor v2 já consulta (teor real do lote).
- **Financeiro**: duplicatas → `contas_pagar`.
- **SNGPC** (Pacote 4): entrada de controlado alimenta a escrituração.

## 6. Aderência legal (RDC 67)
Rastreabilidade de entrada: NF + fornecedor + **lote do fabricante** + validade + laudo por lote; CQ de recebimento com aprovador; bloqueio de lote vencido/reprovado na baixa. Fecha o elo de **entrada** da rastreabilidade (o elo de **consumo** lote→OM→paciente vem no Pacote 3).

## 7. Migrations previstas
`estoque_v1.sql`: cria estoque_entradas_nf(+itens), estoque_forn_depara, estoque_movimentos, contas_pagar; ALTER ativos (est_min/est_max/curva). `lab_lotes_mp` já existe.

## 8. Fatiamento (flags, testável em paralelo ao FCerta)
- **Fatia 1 — Entrada de NF**: upload XML + parse + tela de conferência (de-para aprendido + destino do valor) + cria lotes. *Sem baixa ainda.*
- **Fatia 2 — Kardex + saldo + inventário**.
- **Fatia 3 — Contas a pagar + mínimo/curva → Cotações**.
- **Fatia 4 — Baixa por OM** (integra com o Pacote 3).
Cada fatia atrás de option OFF; roda em paralelo ao FCerta até validação.

## 9. Decisões do Carlos (07/07) — incorporadas
1. **Sem EAN** → casamento por de-para aprendido (fornecedor_id + cProd do XML); 1ª vez manual, depois automático.
2. **Laudo registrado por lote** → CQ de recebimento por lote confirmado (lab_lotes_mp já tem os campos).
3. **Contas a pagar fica no TAO** + relatório exportável ao contador.
4. **Destino do valor: default Compra**, com variação por item (custo/compra/ambos) na conferência.

5. **Filiais**: a Magis-TAO **não tem** filiais hoje, mas o Carlos pediu o sistema **preparado** para isso.
   → todas as tabelas de estoque levam **`cd_filial`** (default 1, nullable), lote/saldo/movimento por filial,
   e a transferência entre filiais fica prevista no modelo (implementação só quando houver 2ª filial).
