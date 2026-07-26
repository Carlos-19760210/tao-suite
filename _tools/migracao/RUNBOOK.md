# Motor de Migração FCerta → TAO Neo (SaaS, multi-tenant)

Onboarding de qualquer farmácia para o TAO Neo, com **fonte plugável**:
- **`fcerta`** — leitura nativa do banco Firebird (a farmácia disponibiliza o `.ibk`/`.ib`).
- **`arquivos`** — a farmácia disponibiliza **CSV/XLSX** (quando não há acesso ao banco, ou vinda de outro ERP).

Ambas alimentam o mesmo **modelo canônico** → mesma carga/validação no TAO. Adicionar um novo ERP no futuro = escrever um novo adapter de fonte (`_prod_<x>` em `migra.py`), sem mudar carga/validação.

## Arquitetura
```
FONTES (adapters)                 CANÔNICO           CARGA/VALIDAÇÃO (TAO)
 fcerta  (Firebird FC03000/FC99*) ─┐
 arquivos(CSV/XLSX + NFe/SNGPC)  ──┼─▶ produtos/fiscal ─▶ upsert fiscal_produtos
 (outro ERP — futuro) ─────────────┘   movimentos ──────▶ SNGPC shadow (gera + compara)
                                        entradas ─────────▶ Recebimento (lote + contas a pagar)
```

## Setup por tenant (1 farmácia = 1 config)
1. Copie `config.exemplo.json` para `config.json` (fica **fora do git**).
2. Preencha: `tenant`, `cliente_id`, `workspace_id`, `cnpj_emitente`, `supabase.url/key`, e a `source`:
   - `tipo: "fcerta"` → caminhos do `gbak`, `fb_lib` (fbembed.dll), `ibk`, `ib`.
   - `tipo: "arquivos"` → `produtos_csv` (ou .xlsx), `sep`, e o `map` (nome da coluna do arquivo → campo canônico).

## Pipeline de onboarding
```
# 1) (fcerta) restaurar o backup do cliente
python migra.py config.json restore

# 2) sincronizar cadastro fiscal dos produtos (fcerta OU arquivos)
python migra.py config.json sync-fiscal

# 3) (fcerta) SNGPC em SOMBRA: gerar e comparar com o XML do sistema atual
python migra.py config.json sngpc-shadow 2026-07-06 2026-07-11 xml_do_fcerta.xml
#   -> repetir a cada ciclo (5 dias) até "identicos" == total e so_* == 0 por N ciclos.

# 3b) só gerar (sem comparar)
python migra.py config.json sngpc-gen 2026-07-06 2026-07-11 saida.xml
```

## Estratégia de corte (SNGPC)
Rodar `sngpc-shadow` a cada ciclo em paralelo ao sistema atual. Quando os XML baterem 100% por N ciclos e o farmacêutico validar, **vira a chave** (transmissão pelo TAO) mantendo o sistema antigo como fallback.

## Provado
- **Magis-TAO (fcerta):** `sngpc-shadow` do período 06–11/jul → **12 saídas + 2 perdas idênticas, 0 divergências**. `sync-fiscal` → 3.507 produtos.

## Preservação / segurança
- `sync-fiscal` faz **upsert** por `(cliente_id, codigo_fc)`: atualiza os campos vindos da fonte e **preserva** o que o contador preencheu (origem, CST/CSOSN, CFOP, alíquota).
- `config.json`, `*.ib/.ibk`, `*.xml`, `*.csv/.xlsx` são **gitignorados** (dados/segredos de tenant).
