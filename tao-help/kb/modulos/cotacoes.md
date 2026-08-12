# Cotações (compras de insumos)

Guia completo do módulo de **Cotações** — organiza a compra de insumos de ponta a ponta, dentro do TAO Neo: do cadastro do fornecedor até a **formalização do pedido**.

**Onde fica:** menu **Cotações** (submenus: *Cotações*, *Nova Cotação*, *Fornecedores*).

## Visão do fluxo
1. Cadastrar os **fornecedores** (uma vez; reaproveitados em todas as cotações).
2. Criar uma **cotação** com os itens e a **prioridade** de cada um.
3. **Enviar** a solicitação aos fornecedores pelo WhatsApp (com revisão do texto).
4. **Registrar o retorno** de cada fornecedor (PDF/foto por IA/modelo, ou manual).
5. **Conferir** a associação de cada item ao ativo do TAO Neo.
6. Analisar o **comparativo** de preços (com frete) e escolher o fornecedor.
7. Gerar a **sugestão de pedido** e **formalizar** o pedido por fornecedor.

![Lista de cotações](img/cotacoes/lista.png)

---

## 1. Cadastro de Fornecedores
Em **Cotações › Fornecedores**. O fornecedor é um **cadastro único** (a mesma tabela usada na Fórmula) — cadastre uma vez e reutilize.

![Lista de fornecedores](img/cotacoes/forn_lista.png)

Clique em **“+ Novo Fornecedor”** (ou no lápis para editar) e preencha:
- **Nome** e **WhatsApp** (com DDD, só números) — o WhatsApp permite enviar a cotação e receber a proposta pelo próprio módulo.
- **Contato**, **CNPJ** (casa com o XML da NF), razão social, endereço, e‑mail, telefone.
- **Prazo de pagamento**.
- **Pedido mínimo (R$)** — usado como **alerta** na sugestão de pedido (quando o pedido de um fornecedor fica abaixo do mínimo).

![Cadastro do fornecedor (com Pedido mínimo)](img/cotacoes/forn_form.png)

> Fornecedor já usado em uma cotação é **desativado** em vez de excluído, para preservar o histórico.

---

## 2. Criar uma Cotação
Em **Nova Cotação**, monte em três blocos: **itens**, **fornecedores** e **envio**.

![Nova Cotação](img/cotacoes/nova.png)

- **Itens:** importe de uma planilha (código, descrição, unidade, qtd) ou inclua item a item buscando o ativo. Marque ao menos um item **urgente (⭐)**.
- **Fornecedores participantes:** selecione quem vai receber (o sistema sugere os mais frequentes). Navega por teclado (↑/↓ + Enter).
- **Identificação e envio:** título + **instância de WhatsApp**. Ao salvar, você vai para a tela de detalhe.

### Tela de detalhe (onde tudo acontece)
Abrindo a cotação, o topo traz as ações principais (Registrar retorno, Enviar, Concluir, Cancelar).

![Topo da tela da cotação](img/cotacoes/detalhe_topo.png)

---

## 3. Itens e prioridade
Na seção **Itens**, cada item tem uma **prioridade** que organiza a distribuição na sugestão de pedido:
- **0 · ⭐ Urgente** (mandatório) · **1 · 15 dias** · **2 · 30 dias** · **3 · +30 dias**.

Você pode **incluir**, **excluir** e **alterar** qualquer item (descrição, código, qtde, unidade, prioridade). Para excluir vários de uma vez, marque as caixas e use **🗑 Excluir selecionados**. A linha do rodapé permite **incluir um item novo** (com busca de ativo por teclado).

![Seção Itens — prioridade, seleção múltipla, incluir](img/cotacoes/itens.png)

---

## 4. Enviar aos Fornecedores
Botão **📤 Enviar aos fornecedores** → abre a **revisão do texto**. O texto lista **só os itens** (sem quantidades), com ⭐ nos prioritários; `{fornecedor}` é trocado pelo nome de cada um.

![Revisão do texto antes de enviar](img/cotacoes/envio.png)

- **✅ Confirmar e enviar** — só aqui o envio acontece.
- **💾 Salvar (não enviar)** — guarda o texto revisado.
- **📋 Copiar texto** — para envio manual (fornecedor sem WhatsApp).

---

## 5. Registrar o Retorno
Quando o fornecedor responde, clique em **📥 Registrar retorno** (ou no menu **Proposta** de cada fornecedor) e escolha o fornecedor.

![Registrar retorno — escolha do fornecedor](img/cotacoes/registrar_retorno.png)

Informe a proposta por um de três caminhos:
- **Modelo aprendido (sem IA):** se o fornecedor já tem o layout aprendido, o PDF é lido de forma determinística — rápido e sem custo.
- **IA:** sem modelo, a IA lê o PDF/foto e extrai itens, preços, **fracionamento** e validade. A partir daí o layout pode ser registrado como modelo para as próximas vezes.
- **Manual:** digitar item a item.

O sistema **normaliza** os preços (R$/g, R$/ml ou R$/milheiro) e converte o **fracionamento mínimo** (respeitando a unidade informada, g × kg).

> **Antes de gravar, o farmacêutico revisa** os itens lidos (preço, unidade, fracionamento, validade e o ativo casado). Só ao confirmar os dados entram no comparativo. **Reprocessar com um arquivo novo substitui** a proposta anterior (não duplica); há também **🗑 Excluir processamento** para refazer do zero.

---

## 6. Conferência do Farmacêutico
Botão **🔍 Conferência do farmacêutico** (topo da seção Itens): revisa a associação de cada item retornado ao ativo do TAO Neo. É **opcional** e cada associação já é salva na hora; a correção vira **sinônimo** e as próximas cotações casam sozinhas.

![Conferência do farmacêutico](img/cotacoes/conferencia.png)

Status por linha: 🟢 **casado** a um item · 🟡 **fora da lista** (confira) · 🔴 **não associado**. Também dá para **excluir** um preço e **incluir** uma linha nova.

---

## 7. Comparativo e Frete
O **Comparativo** mostra o preço normalizado de cada fornecedor e destaca o **melhor**.

![Comparativo de preços](img/cotacoes/comparativo.png)

- **Verde** = melhor preço do item · **vermelho** = melhor acima do último pago.
- **✎ retorno** (topo do fornecedor): edita a proposta e informa o **Frete** — rateado por valor; o comparativo passa a escolher o melhor pelo **preço com frete** (mostra também o sem frete).

![Editar retorno do fornecedor + Frete](img/cotacoes/editret.png)

- Coluna **MELHOR FORN.**: seletor para **fixar** outro fornecedor por item. **↺ Restaurar sugestões** volta tudo ao automático (menor preço).
- **⬇️ Exportar XLSX** baixa o comparativo completo.

---

## 8. Sugestão de Pedido e Formalização
Abaixo do comparativo, a **Sugestão de pedido** monta o pedido por fornecedor.

![Sugestão de pedido](img/cotacoes/sugestao.png)

**Duas visões:**
- **Por preço** — cada item no fornecedor mais barato.
- **Consolidado** — os favoritos (⭐) definem os fornecedores principais e puxam os demais itens.

Por fornecedor: produto, **qtde de compra** (arredondada ao fracionamento, editável), vl unit (s/ frete), **frete do item**, vl unit c/ frete, vl total — com **subtotal**, **total geral** e **⚠️ alerta de pedido mínimo**. Dá para **mover** um item de fornecedor e ajustar a qtde (recalcula ao vivo).

**Formalizar:** em cada fornecedor, **📤 Formalizar pedido** abre um texto com os itens e as **quantidades de compra**, pedindo o **espelho do pedido** no final. O atendente ajusta e **envia pelo WhatsApp** ou **copia**.

---

## Dicas
- Combos e buscas navegam por **teclado** (↑/↓, Enter, Esc).
- A associação item→ativo é **aprendida** (vira sinônimo): quanto mais você confere, menos correção nas próximas.
- Confira sempre a **unidade** do fracionamento (g × kg).
- A conferência é **opcional** e não trava a operação — mas garante o comparativo correto.
