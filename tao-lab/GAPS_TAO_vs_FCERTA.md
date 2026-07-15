# Gaps do TAO Lab × FCerta — avaliação do sistema todo (08/07/2026)

Cruzamento factual: **inventário do plugin tao-formula** (72 endpoints, 16 telas) × **uso real do FCerta**
(273 tabelas com dados, de 566). Base: rowcount de todas as tabelas + colunas + código do TAO.
Escopo Magis: Cotia/SP, Simples Nacional, sem estéreis/homeopatia/GLP-1/antibióticos/Farmácia Popular.

Legenda: 🔴 não construído · 🔶 parcial/refinamento · ✅ coberto · ⚪ fora de escopo (outro módulo/decisão).

---

## A. GAPS REAIS — a Magis usa no FCerta e o TAO Lab não cobre

| # | Gap | FCerta (tabela / uso) | Situação no TAO | Prioridade |
|---|-----|----------------------|-----------------|-----------|
| 1 | ~~**CRUD de Fornecedor**~~ | FC02000 (1.309), FC02100, FC02200 preço×fornecedor (7.480) | ✅ **CONSTRUÍDO 08/07** (tela Fornecedores no portal + wp-admin; dados fiscais completos + licenças AFE/AE/VISA c/ validade + qualificação RDC 67; migration_fornecedores_v1). Falta só a aba de **preço por fornecedor** (FC02200) — fase 2. | ✅ |
| 2 | ~~**Produção interna de bases/diluições**~~ | FC18000 ordem produção (1.423) + FC18100 + FC19000 | ✅ **CONSTRUÍDO 08/07** (tela Produção Interna: escolhe diluído+qtd → receita escalada das fórmulas padrão → pesagem FEFO → baixa insumos + gera lote próprio PI-AAAAMM-NNN com teor/fator/lote_puro_id; migration_producao_interna_v1). Pendente: escrituração SNGPC de transformação de controlados (validar c/ RT). | ✅ |
| 3 | ~~**Certificado/Laudo por lote + Ficha técnica da MP**~~ | FC21000 (1.212), FC81000 (898) | ✅ **CONSTRUÍDO 08/07** (Lotes→Laudo: upload PDF+nº certificado por lote; Ativos→Ficha técnica RDC 67: fórmula/peso molecular, PF, pH, pureza, caracteres, solubilidade, conservação, referências; migration_ficha_laudo_v1). | ✅ |
| 4 | **Emissão fiscal NF-e / NFC-e de venda** | FC1E000..E090 NFe (17k), **FCT0001 NCM/tributação (11k)** | TAO só importa NF de **compra** (entrada). Não emite nota de venda nem escritura fiscal. Depende de middleware (PlugNotas/Focus). | 🔴 ALTA (p/ virar a chave) |
| 5 | ~~**Rotina de Inventário em massa**~~ | FC1D000 (16.457) | ✅ **CONSTRUÍDO 08/07** (Estoque→Inventário: sessão congela saldo, planilha de contagem c/ busca, fechar aplica ajustes em lote no kardex; migration_inventario_precos_v1). | ✅ |
| 6 | ~~**Histórico de reajuste de preços**~~ | FC03160 (47.123) | ✅ **CONSTRUÍDO 08/07** (Ativos→📈 Histórico; grava em cada NF e alteração manual; tabela ativo_precos_hist). | ✅ |
| 7 | **Interação medicamentosa** (alerta no orçamento) | FC71400 (28.837 pares Zanini) | Motor não checa. Base Zanini é LICENCIADA (não reusar) — fica de fora ou licenciar base própria. | 🔶 BAIXA (clínico) |
| 8 | ~~**LGPD — consentimento formal**~~ | FC07I00 (714) | ✅ **CONSTRUÍDO 08/07** (cadastro cliente: consentimento+data+canal; botão Anonimizar só master; trilha lgpd_acessos no acesso a dado sensível; migration_lgpd_v1). | ✅ |
| 9 | ~~**CID-10 / diagnóstico na receita**~~ | FC99310/99311 (12.423) | ✅ **CONSTRUÍDO 08/07** (editor: campo CID opcional c/ autocomplete; catálogo 12.422 códigos carregado — CID público; migration_cid_v1). SEMPRE opcional. | ✅ |
| 10 | **Entregas / delivery ao paciente** | FC12400 (9.942) + FC12410 (18.058) + FC12420 | Sem controle de entrega/roteiro. Provável escopo do CRM, não do Lab. | ⚪/🔶 avaliar |
| 11 | **Transmissão automática SNGPC ao webservice ANVISA** | FC99Sxx + integração | TAO gera o **XML** para envio manual; falta o webservice. (já mapeado no Pacote 4) | 🔶 MÉDIA |

---

## B. Coberto — pelo TAO Lab ou por outro módulo da suíte (NÃO são gaps)

| FCerta | Onde está no TAO |
|--------|------------------|
| Produtos FC03000 / sinônimos FC03200 | ✅ Ativos + Sinônimos |
| Clientes FC07000 / endereços-tel FC07200 / acumulados FC07100 | ✅ Cliente único (crm_contatos) + junção |
| Prescritores FC04000 / especialidades FC04300 | ✅ Prescritores |
| Orçamento FC15000/15100/**15110 (550k)** | ✅ Editor de Orçamento |
| OM/requisição FC12000/12100/**12110 (443k)** / etapas FC12500 / rótulo FC12300 | ✅ Produção (OM, Kanban, pesagem, rótulo) |
| Fórmulas padrão FC05000/05100 | ✅ (carregam no editor) |
| Estoque saldo FC03100 / kardex FC03110 / lotes FC03140 / controlados FC03120 | ✅ Estoque (saldo, kardex, lotes, SNGPC) |
| Compras NF FC11000/11020(XML)/11100 / duplicatas FC11200 | ✅ Entrada NF + Contas a Pagar |
| SNGPC saídas FC99S22 / perdas FC99S24 | ✅ Controlados/SNGPC |
| Catálogo técnico Zanini FC71xxx (dose, sinônimo) | 🔶 parcial (dose máx + sinônimos carregados; resto é referência) |
| **PDV/Caixa FC31xxx, contas a receber FC17xxx, fluxo FC41000** | ⚪ **TAO Caixa** (outro módulo) |
| **E-commerce/pedido web FC0Mxxx (79k itens)** | ⚪ Loja WooCommerce Magis-TAO |
| CRM/analytics cliente FC07D00, mensageria FC0B200 | ⚪ TAO CRM |
| Contabilidade/plano de contas FC0Cxxx, conta corrente FC42000 | ⚪ contábil externo |
| Visita médica/representante FC04200 | ⚪ fora de escopo |

---

## C. Fora de escopo por decisão do Carlos
Balança/peso médio/PCP, Farmácia Popular, GLP-1, estéreis, homeopatia, antibióticos (antimicrobianos).

---

## SÍNTESE — o que atacar para não faltar nada frente ao FCerta

**Bloqueadores para virar a chave (ALTA):**
1. **CRUD de Fornecedor** (pedido, e necessário para qualificação RDC 67 + preço por fornecedor).
2. **Emissão fiscal NF-e/NFC-e de venda** (único módulo de sistema não construído; depende de middleware).
3. **Produção interna de diluições/bases** (a Magis manipula diluídos — hoje entram como compra).

**Conformidade a fechar (MÉDIA):**
4. Upload de laudo/certificado por lote + ficha técnica da MP (RDC 67).
5. Rotina de inventário em massa.
6. Transmissão automática do SNGPC ao webservice ANVISA.

**Refinamentos (BAIXA):**
7. Histórico de reajuste de preços · 8. Interação medicamentosa · 9. LGPD consentimento · 10. CID-10 · 11. Entregas.
