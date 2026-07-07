# PACOTE 1 — Motor Farmacotécnico
**Proposta para aprovação formal** · 06/07/2026 · nada será executado sem o OK

## O que este pacote entrega
O editor de fórmulas (TAO Fórmula) passa a calcular a **pesagem real** de cada componente — com fator de correção, teor, diluição, equivalência e conversão de unidades (UI/mEq/UFC→g) — e a avisar dose máxima, substância bloqueada e dados técnicos faltantes. É a fundação de OM, estoque e SNGPC.

## 1. Mudanças no banco (migration `migration_motor_v1.sql`, anexa)
| Objeto | O quê | Risco |
|---|---|---|
| `ativos` (ALTER) | + dcb, fator_correcao, teor_pct, densidade*, fator_diluicao, ativo_puro_id, ui_por_g, meq_por_g, ufc_bi_por_g, dose_max_dia, dose_max_unidade, restricao | Baixo — só adiciona colunas com default neutro (×1/100%); nada existente muda |
| `ativos_sinonimos` (ALTER) | + fator_equiv DEFAULT 1 | Baixo |
| `lab_lotes_mp` (CREATE) | Lotes de MP c/ laudo real (teor/densidade/diluição/UI por lote), CQ de recebimento, origem fornecedor/produção interna, vínculo lote_puro | Zero — tabela nova |
| `lab_producoes_internas` (CREATE) | Ordem de produção de diluição (consome lote puro → gera lote diluído) | Zero — tabela nova |
| `lab_formulas_padrao` + `_itens` (CREATE) | Fórmulas padrão (734 na Magis) | Zero — tabelas novas |

\* `ativos` pode já ter densidade (usada no cálculo de cápsulas) — a migration usa `ADD COLUMN IF NOT EXISTS`.

## 2. Carga de dados (FCerta → TAO, com dry-run antes)
Fonte: cópia local do banco (snapshot 29/06) — nada toca o FCerta em produção.
1. **Técnicos por MP** (FC03000 → ativos): fator, teor, densidade, diluição nominal, DCB — 1.735 MPs da Magis.
2. **Diluída → pura** (parse do nome "X 1:N" + FC03140.CDPROPRODPURO): preenche ativo_puro_id + fator_diluicao. Lista de vínculos sai no dry-run p/ conferência.
3. **Equivalências** (FC03200 EQUIV>0 → ativos_sinonimos.fator_equiv).
4. **Fórmulas padrão** (FC05000/FC05100): 734 + 2.921 itens.
5. **Lotes vivos** (FC03140 com estoque>0 e validade futura → lab_lotes_mp): teor/densidade/diluição reais por lote.
6. **Lista de bloqueio** (semaglutida; tirzepatida restrita) — preventiva, já que a Magis não manipula GLP-1.
Cada etapa gera relatório de conferência ANTES de gravar; a carga só roda após seu OK no relatório.

## 3. Mudanças na tela (mínimas — filosofia "necessário e rápido")
- **Editor de fórmulas**: cada linha ganha, discretamente, a **quantidade a pesar** calculada (expandível p/ ver a conta: dose × equiv × fator × 100/teor × diluição). Avisos inline: 🔺 dose acima da máxima · ⛔ substância bloqueada · ⚠️ dado técnico faltante (cai no cálculo nominal). Nada muda no fluxo de quem orça.
- **Ficha do ativo**: seção "Dados técnicos" (editável por gestor) + lista de lotes.
- **Ficha da MP diluída**: botão "Produzir diluição" (lote do puro + qtd + veículo → gera lote interno com validade).
- Nenhuma tela nova de navegação; nenhum campo obrigatório novo no atendimento.

## 4. Validação — o teste de ouro
O FCerta guarda a **pesagem real de 442.873 itens de OM** (QTPESA). Antes de ligar o motor: rodo o cálculo do TAO sobre uma amostra grande (~2.000 itens recentes) e comparo com o que a Magis pesou de fato. Relatório de divergências vai pra você/RT — **é isso que também revela empiricamente a semântica do FATOR 3,5–5** (se multiplica ou não a pesagem). Meta: ≥99% de concordância antes de ativar.

## 5. O que NÃO está neste pacote
OM/etapas/rótulo (pacote seguinte), estoque completo/entrada por NF, fiscal, SNGPC, prescritores/kanban. O draft v1 antigo (`migration_v1.0.0_DRAFT.sql`) fica suspenso — as partes dele entram nos pacotes futuros já ajustadas.

## 6. Rollback
Colunas novas com default neutro = motor desligável (option `tao_lab_motor_ativo`); tabelas novas isoladas; carga reversível (delete por origem). O editor volta ao cálculo atual com um toggle.

## Checklist do seu OK
- [ ] Aprovo a migration (item 1)
- [ ] Aprovo a carga com dry-run (item 2)
- [ ] Aprovo o UX mínimo (item 3)
- [ ] Ciente: FATOR será validado pelo teste de ouro + confirmação da RT antes de ativar o motor
