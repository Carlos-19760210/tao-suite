# GAPS LEGAIS/EXIGIDOS — TAO Fórmula vs FCerta (Apostila 2017 + RDC 67/2007, Port. 344/98)

Levantamento 13/07/2026. Escopo: só o que é **legal/exigido** (fiscal deixado de fora, por decisão do Carlos).
Base: apostila Essencial FórmulaCerta 6.0 + confronto com o código atual (motor_v2 LIGADO em produção).

## ✅ Já atende
Rótulo dispensação (RDC 67) · Ordem de Manipulação (pesagem por lote) · Livro de receituário ·
Equivalência sal↔base · CQ insumos (quarentena/aprova-reprova/laudo/ficha técnica) ·
Diluições/produção interna (inclui alta potência) · Rastreabilidade de lote (MP→OM→paciente) ·
Qualificação de fornecedor (AFE/AE/licença) · Gate farmacêutico no orçamento.

## ❌ GAPS a tratar (priorizados)

### Ordem de execução (Carlos 13/07): A→B→C→D. Regra = mesma do FCerta.

**A. Validade da fórmula por lote — ✅ FEITO 13/07.** Regra FCerta VALIDADELOTE (Tabela 31 / arg PRAZOVALxx + VALIDADELOTE, confirmado no Parametros.pdf p151): na conclusão da OM, `dt_validade = MENOR(validade por forma, menor validade dos lotes de MP pesados)`. Função `tao_formula_recalc_validade_om()` (ajax.php, chamada em prod_mover quando etapa final); alerta no front (producao.php) quando o lote reduz a validade. Testado com lote real: 2027-09-28→2027-07-30. Rótulo usa dt_validade da OM (já reflete).

**B. Trava de controlado sem receita/registro — ✅ FEITO 13/07.** Port. 344: na conclusão da OM controlada (prod_mover etapa final), TRAVA se faltar tp_receita/nr_notificacao/comprador/prescritor (wp_send_json_error code=controlado_incompleto). Bloco "🔒 Receita controlada" no modal da OM (producao.php, só se controlado) + endpoint tao_formula_prod_receita_ctrl salva os dados (usados na escrituração SNGPC). Badge Controlado no cabeçalho. Testado: sem dados bloqueia, com dados libera.

**C. Modo de preparo / precauções na OM — ✅ FEITO 13/07.** Parametrizável: `formas_farmaceuticas.modo_preparo` (padrão por forma, editável em Cadastros→Formas) + `lab_ordens.modo_preparo` (herda da forma na criação da OM, editável no modal da produção via tao_formula_prod_modo_preparo). Migration modo_preparo rodada. Testado: grava na forma, herda/persiste na OM.

**D. Fator de correção na pesagem** — RESOLVIDO/confirmado: a ficha de pesagem do FCerta (Parametros.pdf p148, arg IMPFICCODPRO) já mostra a quantidade calculada/corrigida; o Carlos confirmou que o valor calculado já considera isso. No TAO teor+diluição já corrigem a pesagem — `fator_correcao` é campo redundante (NÃO aplicar, senão corrige em dobro). Ação: só remover/ocultar o campo da UI p/ não confundir (baixa prioridade).

### Logo em seguida
5. **SNGPC — transmissão webservice ANVISA (hoje XML manual) + balanço BSPO trimestral** (só há BMPO).
6. **Alertas: dose MÍNIMA (só há máxima) + particularidades configuráveis do fármaco** (base específica, baixo índice terapêutico) com opção de bloquear.

## Removido do levantamento
- ~~Liberação farmacêutica do produto acabado~~ — NÃO é gap de software. Resolvido por PROCESSO/POP; a movimentação do card para "Pronto para Entrega" é o ponto de liberação (FCerta também não tem função de software para isso). Decisão do Carlos 13/07.
