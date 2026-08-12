# Modelo de Dados (MER)

Ecossistema **multi-tenant** no Supabase/PostgreSQL: **116 tabelas** e **154 relações (FK)**. Tudo pertence a um **cliente** (tenant); os cadastros de **ativos**, **fornecedores** e **contatos** são únicos e compartilhados entre os módulos. **Clique em qualquer diagrama para ampliar** (zoom).

## Hubs (mais referenciados)

| Tabela | FKs que apontam p/ ela | Papel |
| --- | --- | --- |
| clientes | 39 | Tenant — tudo pertence a um cliente |
| crm_workspaces | 15 | Espaço de trabalho do CRM (por negócio) |
| ativos | 13 | Cadastro único de matéria-prima |
| fornecedores | 9 | Cadastro único de fornecedor |
| crm_cards / crm_contatos | 7 | Card do atendimento / contato único |

## Visão geral (hubs & módulos)

```mermaid
erDiagram
  clientes ||--o{ crm_workspaces : "tenant"
  clientes ||--o{ ativos : "cadastro unico"
  clientes ||--o{ fornecedores : "cadastro unico"
  clientes ||--o{ crm_contatos : "cadastro unico"
  crm_workspaces ||--o{ crm_cards : "CRM"
  crm_contatos ||--o{ crm_cards : "contato"
  crm_cards ||--o{ orcamentos : "Formula"
  crm_cards ||--o{ caixa_vendas : "Caixa"
  crm_cards ||--o{ entregas : "Entregas"
  ativos ||--o{ cotacao_precos : "Cotacoes"
  ativos ||--o{ orcamentos : "itens"
  fornecedores ||--o{ cotacoes : "Compras"
  caixa_vendas ||--o{ caixa_recibos : "recebimento"
```

## Núcleo — Tenant & Acesso  (9 tabelas)

```mermaid
erDiagram
  crm_perfis ||--o{ crm_alcadas : "perfil_id"
  clientes ||--o{ empresa_config : "cliente_id"
  crm_workspaces ||--o{ crm_planos : "workspace_id"
  clientes ||--o{ crm_workspaces : "cliente_id"
  crm_perfis ||--o{ crm_permissoes : "perfil_id"
  crm_perfis ||--o{ crm_perfil_usuarios : "perfil_id"
  lgpd_acessos {
    uuid id PK
  }
```

**Liga-se a (outros módulos):** `conectores_saida`

## CRM  (28 tabelas)

```mermaid
erDiagram
  crm_cards ||--o{ crm_comentarios : "card_id"
  crm_cards ||--o{ crm_historico : "card_id"
  crm_cards ||--o{ crm_cards_historico : "card_id"
  crm_estagios ||--o{ crm_cards_historico : "para_estagio_id"
  crm_pipelines ||--o{ crm_estagios : "pipeline_id"
  crm_cards ||--o{ crm_msgs_agendadas : "card_id"
  crm_contatos ||--o{ hist_clientes : "contato_id"
  crm_cards ||--o{ crm_cards_tags : "card_id"
  crm_tags ||--o{ crm_cards_tags : "tag_id"
  crm_cards ||--o{ crm_lembretes : "card_id"
  crm_pipelines ||--o{ crm_cards : "pipeline_id"
  crm_estagios ||--o{ crm_cards : "estagio_id"
  crm_instancias ||--o{ crm_cards : "instancia_id"
  crm_contatos ||--o{ crm_cards : "contato_id"
  crm_contatos ||--o{ historico : "contato_id"
  crm_cards ||--o{ crm_mensagens : "card_id"
  cid10 {
    uuid id PK
  }
  contato_enderecos {
    uuid id PK
  }
  crm_automacoes {
    uuid id PK
  }
  crm_automacoes_fila {
    uuid id PK
  }
  crm_campos_definicao {
    uuid id PK
  }
  crm_campos_estagio {
    uuid id PK
  }
  crm_card_itens {
    uuid id PK
  }
  crm_cards_valores {
    uuid id PK
  }
  crm_metas {
    uuid id PK
  }
  crm_msg_templates {
    uuid id PK
  }
  crm_nps {
    uuid id PK
  }
  crm_round_robin {
    uuid id PK
  }
  crm_webhooks_saida {
    uuid id PK
  }
```

**Liga-se a (outros módulos):** `clientes`, `crm_workspaces`

## Agente / Catálogo  (11 tabelas)

```mermaid
erDiagram
  catalogo ||--o{ catalogo_disponibilidade : "catalogo_id"
  catalogo ||--o{ catalogo_componentes : "produto_final_id"
  catalogo ||--o{ catalogo_componentes : "componente_id"
  campos_extras {
    uuid id PK
  }
  categorias {
    uuid id PK
  }
  cliente_produtos {
    uuid id PK
  }
  conectores_saida {
    uuid id PK
  }
  conteudo_dinamico {
    uuid id PK
  }
  leads {
    uuid id PK
  }
  mensagens_buffer {
    uuid id PK
  }
  pedidos {
    uuid id PK
  }
```

**Liga-se a (outros módulos):** `clientes`, `crm_contatos`

## Fórmula / Lab (manipulação)  (28 tabelas)

```mermaid
erDiagram
  hist_formulas ||--o{ hist_formulas_itens : "formula_id"
  lab_formulas_padrao ||--o{ lab_formulas_padrao_itens : "formula_id"
  ativos ||--o{ lab_formulas_padrao_itens : "ativo_id"
  orcamentos ||--o{ lab_ordens : "orcamento_id"
  prescritores ||--o{ lab_ordens : "prescritor_id"
  lab_etapas ||--o{ lab_ordens : "etapa_id"
  ativos ||--o{ lab_lotes_mp : "ativo_id"
  lab_lotes_mp ||--o{ lab_lotes_mp : "lote_puro_id"
  lab_lotes_mp ||--o{ lab_laudo_ensaios : "lote_id"
  ativos ||--o{ ativos : "ativo_puro_id"
  lab_ordens ||--o{ lab_rotulos : "ordem_id"
  lab_rotulo_templates ||--o{ lab_rotulos : "template_id"
  orcamentos ||--o{ receita_logs : "orcamento_id"
  lab_lotes_mp ||--o{ lab_laudos : "lote_id"
  lab_ordens ||--o{ lab_ordem_itens : "ordem_id"
  ativos ||--o{ lab_ordem_itens : "ativo_id"
  lab_lotes_mp ||--o{ lab_ordem_itens : "lote_mp_id"
  ativos ||--o{ lab_producoes_internas : "ativo_id"
  lab_lotes_mp ||--o{ lab_producoes_internas : "lote_gerado_id"
  lab_lotes_mp ||--o{ lab_producoes_internas : "lote_puro_id"
  ativos ||--o{ lab_producoes_internas : "veiculo_ativo_id"
  lab_lotes_mp ||--o{ lab_producoes_internas : "lote_veiculo_id"
  formas_farmaceuticas ||--o{ orcamentos : "forma_id"
  prescritores ||--o{ orcamentos : "prescritor_id"
  lab_ordens ||--o{ lab_ordem_etapas : "ordem_id"
  ativo_precos_hist {
    uuid id PK
  }
  ativos_sinonimos {
    uuid id PK
  }
  lab_inventario {
    uuid id PK
  }
  lab_inventario_itens {
    uuid id PK
  }
  lab_producao {
    uuid id PK
  }
  lab_producao_itens {
    uuid id PK
  }
  laudo_modelos {
    uuid id PK
  }
  tipos_capsula {
    uuid id PK
  }
  unidades_medida {
    uuid id PK
  }
```

**Liga-se a (outros módulos):** `clientes`, `crm_contatos`, `fornecedores`, `hist_clientes`

## Caixa / Financeiro / Fiscal  (17 tabelas)

```mermaid
erDiagram
  caixa_recibos ||--o{ caixa_recibo_vendas : "recibo_id"
  caixa_vendas ||--o{ caixa_recibo_vendas : "venda_id"
  caixa_recibos ||--o{ caixa_pagamentos : "recibo_id"
  caixa_formas_pagamento ||--o{ caixa_pagamentos : "forma_pagamento_id"
  caixa_adquirentes ||--o{ caixa_pagamentos : "adquirente_id"
  caixa_pagamentos ||--o{ caixa_recebiveis : "pagamento_id"
  caixa_adquirentes ||--o{ caixa_recebiveis : "adquirente_id"
  caixa_adquirentes ||--o{ caixa_formas_pagamento : "adquirente_id"
  caixa_sessoes ||--o{ caixa_recibos : "sessao_id"
  caixa_vendas ||--o{ caixa_venda_itens : "venda_id"
  sngpc_arquivos ||--o{ sngpc_movimentos : "arquivo_id"
  caixa_formas_pagamento ||--o{ caixa_taxas : "forma_pagamento_id"
  caixa_adquirentes ||--o{ caixa_taxas : "adquirente_id"
  caixa_sessoes ||--o{ caixa_movimentos : "sessao_id"
  caixa_emitente_fiscal {
    uuid id PK
  }
  contas_a_pagar {
    uuid id PK
  }
  contas_pagar {
    uuid id PK
  }
  fiscal_produtos {
    uuid id PK
  }
```

**Liga-se a (outros módulos):** `ativos`, `clientes`, `estoque_entradas_nf`, `fornecedores`

## Cotações (compras)  (9 tabelas)

```mermaid
erDiagram
  cotacoes ||--o{ cotacao_precos : "cotacao_id"
  fornecedores ||--o{ cotacao_precos : "fornecedor_id"
  cotacao_propostas ||--o{ cotacao_precos : "proposta_id"
  cotacao_itens ||--o{ cotacao_precos : "cotacao_item_id"
  cotacoes ||--o{ cotacao_itens : "cotacao_id"
  cotacoes ||--o{ cotacao_propostas : "cotacao_id"
  fornecedores ||--o{ cotacao_propostas : "fornecedor_id"
  fornecedores ||--o{ fornecedor_mensagens : "fornecedor_id"
  cotacoes ||--o{ fornecedor_mensagens : "cotacao_id"
  cotacoes ||--o{ cotacao_fornecedores : "cotacao_id"
  fornecedores ||--o{ cotacao_fornecedores : "fornecedor_id"
  fornecedores ||--o{ precos_historico : "fornecedor_id"
  cotacoes ||--o{ precos_historico : "cotacao_id"
  cotacao_modelos {
    uuid id PK
  }
```

**Liga-se a (outros módulos):** `ativos`, `clientes`

## Estoque  (7 tabelas)

```mermaid
erDiagram
  estoque_entradas_nf ||--o{ estoque_entradas_nf_itens : "entrada_id"
  estoque_forn_depara {
    uuid id PK
  }
  estoque_movimentos {
    uuid id PK
  }
  recebimento_depara {
    uuid id PK
  }
  recebimento_nf {
    uuid id PK
  }
  recebimento_nf_itens {
    uuid id PK
  }
```

**Liga-se a (outros módulos):** `ativos`, `clientes`, `fornecedores`, `lab_lotes_mp`

## Campanhas  (6 tabelas)

```mermaid
erDiagram
  campanhas ||--o{ campanha_contatos : "campanha_id"
  listas_contatos ||--o{ campanhas : "lista_id"
  campanhas ||--o{ campanha_historico : "campanha_id"
  campanha_historico ||--o{ campanha_historico_itens : "historico_id"
  listas_contatos ||--o{ lista_contatos_itens : "lista_id"
```

**Liga-se a (outros módulos):** `clientes`, `crm_instancias`

## Entregas  (1 tabelas)

```mermaid
erDiagram
  entregas {
    uuid id PK
  }
```
