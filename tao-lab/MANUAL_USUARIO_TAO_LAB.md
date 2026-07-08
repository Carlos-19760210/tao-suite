# Manual do Usuário — TAO Lab (Farmácia de Manipulação)
Guia das telas do módulo Fórmulas/Estoque/Produção. Acesso: portal **solucoesetao.com.br/robos** → menu **Fórmulas**.
Versão 08/07/2026.

---

## Índice
1. Primeiros passos (Configurações)
2. Cadastros (Produtos, Prescritores, Fornecedores, Cliente)
3. Orçamento de fórmula
4. Histórico e Repetição
5. Estoque — Entrada de NF
6. Estoque — Lotes (CQ, inventário, kardex)
7. Estoque — Reposição (mínimo/cotação)
8. Produção — Ordem de Manipulação
9. Rótulo e Livro de Receituário
10. Contas a Pagar

---

## 1. Primeiros passos — Configurações
Menu **Fórmulas → Configurações**.
- **Dados da Farmácia**: preencha razão social, CNPJ, IE, endereço, e principalmente o **Responsável Técnico (RT) + CRF** — esses dados saem no rótulo (obrigatório pela RDC 67). Sem eles o rótulo avisa que faltam.
- **Motor farmacotécnico v2**: marque para ativar equivalência do sinônimo, alerta de dose máxima, trava de substância restrita e teor do lote no cálculo. Desmarcado, o cálculo fica como o padrão simples.
- **Margem padrão** e **chave de IA** (para leitura de receitas) também ficam aqui.

## 2. Cadastros
### Produtos (Fórmulas → Ativos)
Lista de matérias-primas e embalagens. Clique no nome para ver detalhes. **+ Novo Ativo** cria; **Editar produto** altera. Campos técnicos (DCB, diluição, teor, densidade, dose máxima, restrição) alimentam o motor de cálculo.

### Prescritores (Fórmulas → Prescritores)
CRUD de médicos/dentistas/veterinários. **+ Novo Prescritor**: tratamento (Dr/Dra), tipo de registro (CRM/CRO/CRN…), número, UF, especialidade, contato e endereço. No orçamento você busca o prescritor por nome ou número.

### Fornecedores (Cotações → Fornecedores)
Cadastro com **CNPJ** (essencial — é o que casa com a NF de entrada), razão social, endereço e contato.

### Cliente / Paciente
O cliente é **único em toda a solução** (o mesmo do CRM/Agente/Campanha). No **Histórico** você edita os dados de um cliente (nome, WhatsApp, características de saúde como obesidade/colesterol/pressão/diabetes, alergias) — isso vale para todos os módulos.

## 3. Orçamento de fórmula (Fórmulas → Novo Orçamento)
1. **Paciente** (quem usa) e, se diferente, **Cliente** (quem contrata — ex.: mãe/filho). **Prescritor** (opcional) e **Posologia**.
2. **Forma farmacêutica** e volume/quantidade.
3. **Adicionar Ativo**: digite o nome, escolha da lista. Informe a dose e a unidade. Marque **QSP** no excipiente.
4. **📋 Fórmula padrão**: aplica uma fórmula pronta (as 734 do FCerta).
5. **Embalagens** e **cápsulas** são sugeridas automaticamente.
6. **Análise de Preços**: mostra margem por item e permite simular/aplicar margem.
7. **Salvar** — o orçamento fica disponível para aprovação e produção.

## 4. Histórico e Repetição (Fórmulas → Histórico)
Busque o cliente pelo nome (navegue com as **setas** ↑↓). A lista mostra as fórmulas passadas (data, resumo dos ativos, valor). Expanda para ver componentes e posologia.
- **↻ Repetir**: recria o orçamento no editor com a mesma forma, cápsula e itens (só a data muda); os preços são recalculados pela tabela atual e os valores da última aprovação ficam nas observações.

## 5. Estoque — Entrada de NF (Fórmulas → Estoque — Entrada NF)
1. **Carregar** o arquivo **XML** da nota fiscal de compra.
2. O sistema identifica o **fornecedor** pelo CNPJ (se não existir, cadastre em Cotações → Fornecedores com esse CNPJ e recarregue).
3. **Conferência**: cada item vem pré-associado se o fornecedor já foi usado antes; os novos, associe ao ativo (busca por nome — a associação é **lembrada** para as próximas notas). Escolha o **destino do valor** de cada item (Compra é o padrão; pode ser Custo ou Ambos).
4. **Efetivar**: cria os lotes, lança o estoque, atualiza os preços e gera as **contas a pagar** das duplicatas.

## 6. Estoque — Lotes (Fórmulas → Estoque — Lotes)
Lotes de matéria-prima com validade e saldo.
- **CQ de recebimento**: lotes novos entram em **quarentena**; clique **✔ Aprovar** (ou reprovar) — só lote aprovado é usado na produção (RDC 67).
- **⚖ Inventário**: informe a quantidade real contada — gera um ajuste automático com trilha.
- **↔ Kardex**: extrato de entradas/saídas do ativo.
- Validade a vencer (< 90 dias) aparece em vermelho.

## 7. Estoque — Reposição (Fórmulas → Estoque — Reposição)
- **Definir mínimo**: busque um ativo e informe estoque mínimo, máximo e curva (A/B/C).
- Os itens **abaixo do mínimo** aparecem destacados, com a quantidade sugerida de compra.
- Marque os itens e **🛒 Gerar cotação** — cria uma cotação no módulo Cotações já com os produtos e o último preço pago, pronta para enviar aos fornecedores.

## 8. Produção — Ordem de Manipulação (Fórmulas → Produção)
1. **Gerar OM**: busque o orçamento (nº ou paciente) e confirme — cria a Ordem de Manipulação com validade calculada (floral 90 dias / demais 120).
2. **Kanban**: a OM aparece na 1ª etapa. As colunas são as etapas (Conferência → Pesagem → … → Entregue).
3. Clique na OM para abrir. Na **Pesagem**, informe a quantidade pesada de cada componente e escolha o **lote usado** (só lotes aprovados; o de validade mais próxima vem sugerido). Isso é a **rastreabilidade** (lote → OM → paciente).
4. **Mover para etapa**: avança a OM. Ao chegar numa etapa **final**, a OM é concluída e o **estoque é baixado** dos lotes pesados.
5. **🏷 Rótulo (RDC 67)**: abre o rótulo pronto para impressão.

## 9. Rótulo e Livro de Receituário
- **Rótulo** (botão na OM): traz farmácia/CNPJ/RT, paciente, prescritor, composição, validade, posologia, advertência e conservação — imprima direto.
- **Livro de Receituário** (Fórmulas → Livro de Receituário): relatório sequencial das OMs por período, imprimível — o registro legal das manipulações.

## 10. Contas a Pagar (Fórmulas → Contas a Pagar)
As duplicatas das notas de compra. Filtre por situação/vencimento, marque **✔ pagar** quando quitar, e use **🖨 Relatório** para imprimir/enviar ao contador. Contas vencidas em aberto aparecem em vermelho.

---
**Dica geral:** todas as telas funcionam no portal (celular e computador). Em caso de dúvida sobre um dado que veio do Formula Certa, o Histórico e os cadastros preservam a origem.
