# Modelo de Dados (MER) — TAO Neo

**Diagrama interativo (Artifact):** https://claude.ai/code/artifact/64c4ab9b-7faf-40ee-b340-f2148067cfed
(visão geral + um diagrama ER por módulo, com zoom por clique)

**Gerado a partir do banco** (Supabase, projeto Robôs `gclayesytzzpzkjvgede`) — reproduzível:
- o gerador está em `tao-help/kb/arquitetura/mer_gen.py` (extrai o swagger do PostgREST → FKs → mermaid).

## Números
- **116 tabelas**, **154 relações (FK)**, **9 módulos**.

## Hubs (mais referenciados)
| Tabela | FKs que apontam p/ ela | Papel |
|---|---|---|
| `clientes` | 39 | **Tenant** — tudo pertence a um cliente |
| `crm_workspaces` | 15 | Espaço de trabalho do CRM (por negócio) |
| `ativos` | 13 | **Cadastro único** de matéria‑prima |
| `fornecedores` | 9 | **Cadastro único** de fornecedor |
| `crm_cards` / `crm_contatos` | 7 cada | Card do atendimento / contato único |

## Princípios do modelo
1. **Multi‑tenant:** quase toda tabela tem `cliente_id`; isolamento por tenant.
2. **Cadastros únicos compartilhados:** `ativos`, `fornecedores`, `crm_contatos` — usados por vários módulos (a mesma tabela, sem duplicação).
3. **O card do CRM é o costurador:** `crm_cards` liga o atendimento a `orcamentos` (Fórmula), `caixa_vendas` (Caixa) e `entregas` — a jornada comercial passa por ele.
4. **Nomenclatura por prefixo:** `crm_*`, `caixa_*`, `cotacao_*`, `lab_*`, `campanha_*`, `estoque_*`.

## Módulos (contagem de tabelas)
Núcleo/acesso (9) · CRM (28) · Agente/Catálogo (11) · Fórmula/Lab (25) · Caixa/Fiscal (17) · Cotações (9) · Estoque (7) · Campanhas (6) · Entregas (1).

> Detalhamento coluna a coluna das tabelas‑chave: **a fazer** (pedir por tabela/módulo).
