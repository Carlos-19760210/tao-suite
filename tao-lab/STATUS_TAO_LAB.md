# TAO Lab — STATUS CONSOLIDADO (fechamento 08/07/2026)
Substituição do Formula Certa pela plataforma TAO Neo. Este é o retrato do que está **construído e em produção**, o **roteiro de teste** e o que **falta para virar a chave**.

---

## 1. O que está PRONTO e no ar (portal /robos + wp-admin)

### Cadastros
| Módulo | Onde | Estado |
|---|---|---|
| Produtos/Ativos (CRUD + dados farmacotécnicos) | Fórmulas → Ativos | ✅ + sync FCerta |
| Fornecedores (fiscal/CNPJ p/ NF) | Cotações → Fornecedores | ✅ + carga FC02000 (75) |
| Prescritores (CRUD + autocomplete) | Fórmulas → Prescritores | ✅ + carga FC04000 (4.533) |
| Cliente/Paciente ÚNICO (base do CRM) | Histórico / CRM | ✅ junção+enriquecimento (7.353 contatos, nome/RG/endereço) |
| Empresa/Filial + RT (rótulo/fiscal) | Fórmulas → Configurações | ✅ (preencher dados) |

### Motor + Orçamento
- **Motor farmacotécnico v2** (option `tao_formula_motor_v2`): equivalência do sinônimo, dose máx (Zanini), trava restrição/GLP-1, teor do lote FEFO. Teste de ouro 99,92%.
- **Orçamento**: editor com Cliente×Paciente, prescritor, posologia, fórmulas padrão, análise de preços; **Histórico + Repetição** (41.981 fórmulas FCerta).

### Estoque (Pacote 2)
- **Entrada de NF** (Fórmulas → Estoque — Entrada NF): XML NFe → conferência assistida (de-para aprendido, destino do valor) → lotes+kardex+preço+contas a pagar.
- **Lotes** (Estoque — Lotes): CQ de recebimento RDC 67 (aprovar/reprovar), saldo, inventário, kardex.
- **Reposição** (Estoque — Reposição): mínimo/curva → alerta → gera cotação.

### Produção (Pacote 3) — fecha a rastreabilidade legal
- **Produção** (Fórmulas → Produção): OM do orçamento → kanban de 8 etapas → pesagem com lote FEFO (rastreabilidade **lote→OM→paciente**) → baixa de estoque na conclusão.
- **Rótulo RDC 67** (botão na OM): dizeres obrigatórios, imprimível, auditável.
- **Livro de Receituário** (Fórmulas → Livro de Receituário): OMs sequenciais por período, imprimível.

## 2. Migrations rodadas (Supabase)
motor_v1, historico_v1, v2 (orç/fornec/hist), v3 (prescritores), v4 (empresa), v6 (contato único), v7 (RG), estoque_v1, producao_v1. **v5 CANCELADA** (substituída pela v6).

## 3. Pipeline de equalização (recorrente até virar a chave)
`tao-lab/equalizar_cadastros.py` (sync → backfill → junção → enriquecimento). **⚠ rodar FORA do horário de expediente e avisar o Carlos antes** (regra pós-incidente 08/07).

---

## 4. ROTEIRO DE TESTE ponta a ponta (validação do Carlos)
1. **Configurações**: preencher Dados da Farmácia (razão/CNPJ/RT+CRF) e **ligar o Motor v2**.
2. **Produtos**: abrir um ativo, conferir dados técnicos; criar/editar um ativo.
3. **Orçamento**: novo orçamento — buscar ativo (ver equivalência do sinônimo com motor v2), dose alta (alerta), aplicar fórmula padrão, ver análise de preços. Salvar.
4. **Histórico**: buscar um cliente, ver resumo dos ativos, **Repetir** uma fórmula → cai no editor.
5. **Entrada de NF**: importar um XML de NF de compra → conferir associação (associar item novo) → destino do valor → Efetivar. Ver lote criado.
6. **Lotes**: aprovar o lote no CQ; testar inventário e kardex.
7. **Reposição**: definir mínimo de um ativo abaixo do saldo → ver alerta → gerar cotação.
8. **Produção**: gerar OM do orçamento → mover no kanban → na Pesagem, informar pesado + lote → concluir (baixa estoque) → imprimir **Rótulo** → conferir no **Livro de Receituário**.

## 5. Falta para VIRAR A CHAVE (desligar o FCerta)
- **Pacote 4 — Controlados/SNGPC** (última fase regulatória): escrituração (entrada/saída/perda/inventário de controlados), XML de transmissão à ANVISA, balanços BSPO/BMPO, inventário inicial de migração. **Depende de confirmações da RT.**
- **Fiscal**: emissão NFC-e/NF-e via middleware (Simples) + relatório de faturamento p/ o contador.
- **Refinamentos**: explosão de lote na baixa (diluída→MP pura+excipiente); template de rótulo customizável; contas a pagar → relatório ao contador.
- **Validação final**: teste ponta a ponta (acima) + parecer da **farmacêutica RT** (RDC 67) e do **contador** (fiscal). Corte formal com inventário inicial e 1 ciclo em paralelo FCerta×TAO.

## 6. Confirmações pendentes (Carlos / RT)
- OM: gatilho manual (atual) ou automático na aprovação? Baixa de estoque na conclusão (atual) ou no CQ?
- Validade da fórmula: floral 90 / demais 120 (atual) — confirmar com a RT.
- Etapas do kanban de produção: as 8 padrão servem?
- SNGPC: a Magis manipula GLP-1? Estéreis? Homeopatia? (definem o escopo do Pacote 4)
