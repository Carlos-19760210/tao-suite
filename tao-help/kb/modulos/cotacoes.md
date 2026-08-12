# Cotações (compras de insumos)

O módulo de **Cotações** organiza a compra de insumos de ponta a ponta, dentro do TAO Neo — do cadastro do fornecedor até a **sugestão de pedido**.

## Fluxo geral
1. Cadastrar os **fornecedores** (uma vez; reaproveitados em todas as cotações).
2. Criar uma **cotação** com os itens e definir a **prioridade** de cada um.
3. **Enviar** a solicitação aos fornecedores pelo WhatsApp (com revisão do texto).
4. **Registrar o retorno** de cada fornecedor (PDF/foto lido por IA/modelo, ou manual).
5. **Conferir** a associação de cada item ao ativo do TAO Neo.
6. Analisar o **comparativo** de preços (com frete) e escolher o fornecedor.
7. Gerar a **sugestão de pedido** e **formalizar** o pedido por fornecedor.

## Fornecedores
Menu **Cotações › Fornecedores**. Cadastro único (o mesmo usado na Fórmula). Informe nome, WhatsApp, contato, CNPJ e o **Pedido mínimo (R$)** — usado como alerta na sugestão de pedido.

## Criar cotação e priorizar
Em **Nova Cotação**: adicione os itens, escolha os fornecedores e a instância de WhatsApp. Na tela da cotação, cada item tem uma **prioridade**: `⭐ Urgente`, `15 dias`, `30 dias`, `+30 dias`. Dá para **incluir, excluir e alterar** itens (inclusive selecionar vários e excluir de uma vez).

## Enviar aos fornecedores
Botão **📤 Enviar aos fornecedores** → abre a **revisão do texto**. O envio só acontece ao **confirmar**; também dá para **salvar** o texto ou **copiar** para envio manual. O texto lista só os itens (sem quantidades).

## Registrar o retorno
Botão **📥 Registrar retorno** (ou o menu **Proposta** de cada fornecedor) → escolha o fornecedor e informe a proposta:
- **Modelo aprendido** (sem IA): se o fornecedor já tem o layout aprendido, o PDF é lido de forma determinística.
- **IA**: sem modelo, a IA lê o PDF/foto e extrai itens, preços, fracionamento e validade.
- **Manual**: digitar item a item.

Antes de gravar, o **farmacêutico revisa** (preço, unidade, fracionamento, validade e o ativo). Só ao confirmar os dados entram no comparativo. **Reprocessar com um arquivo novo substitui** a proposta anterior (não duplica); há também **🗑 Excluir processamento**.

## Conferência do farmacêutico
Botão **🔍 Conferência do farmacêutico** (na seção Itens): revisa a associação de cada item retornado ao ativo do TAO Neo. É **opcional** e cada associação já é salva; a correção vira **sinônimo** e as próximas cotações casam sozinhas. 🟢 casado · 🟡 fora da lista · 🔴 não associado.

## Comparativo e frete
O **Comparativo** mostra o preço normalizado de cada fornecedor e destaca o **melhor**. No topo de cada fornecedor, **✎ retorno** abre a edição da proposta e o campo de **Frete** — rateado por valor; o comparativo passa a escolher o melhor pelo **preço com frete** (mostrando também o sem frete). A coluna **MELHOR FORN.** permite **fixar** outro fornecedor por item; **↺ Restaurar sugestões** volta ao automático.

## Sugestão de pedido
Abaixo do comparativo, a **Sugestão de pedido** monta o pedido por fornecedor em duas visões:
- **Por preço** — cada item no fornecedor mais barato.
- **Consolidado** — os favoritos (⭐) definem os fornecedores principais e puxam os demais.

Por fornecedor: produto, qtde de compra (arredondada ao fracionamento, editável), vl unit, **frete do item**, vl total, com **alerta de pedido mínimo**. Dá para **mover** item de fornecedor e ajustar a qtde.

## Formalizar pedido
Em cada fornecedor, **📤 Formalizar pedido** abre um texto com os itens e as quantidades de compra (e o pedido do **espelho** no final). O atendente ajusta e **envia pelo WhatsApp** ou **copia**.
