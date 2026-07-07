# Confronto FCerta × TAO Neo — Cargas do Pacote 1 (Motor Farmacotécnico)
Gerado automaticamente em 07/07/2026. Fonte FCerta: snapshot do banco de 29/06/2026.

## Carga 1 — Dados técnicos dos ativos (FC03000 → ativos)
| Campo | Conferem | Total FCerta | % |
|---|---|---|---|
| dcb | 351 | 351 | 100% |
| densidade | 535 | 535 | 100% |
| markup | 628 | 629 | 99% |
| diluicao | 109 | 111 | 98% |

Vínculos diluída→pura no TAO: **37** (37 automáticos da carga + eventuais manuais). 
Amostra de vínculos (nome diluída → nome pura):

- VIT B12  1:100 → **VIT B12 MP** (1:100)
- CLONAZEPAM 1:50 → **CLONAZEPAM MP** (1:50)
- TANSULOSINA 1:10 → **TANSULOSINA HCL MP** (1:10)
- BUMETAMIDA 1:10 → **BUMETAMIDA** (1:10)
- BIOTINA 1:10 → **BIOTINA MP** (1:10)
- ANASTROZOL 1:100 → **ANASTROZOL MP** (1:100)
- KELP IODINE MCG 1:10 → **KELP IODINE MP MCG** (1:10)
- BETAMETASONA 17  1:10 → **BETAMETASONA 17 VALERATO** (1:10)
- FINASTERIDA 1:10 → **FINASTERIDA MP** (1:10)
- DEFLAZACORT 1:10 → **DEFLAZACORT** (1:10)

**Divergências carga 1:**
- [markup] VITAMINA K2 1:100/NAO USAR: FCerta=7.0 × TAO=None
- [diluicao] VITAMINA K2 1:100/NAO USAR: FCerta=100.0 × TAO=1
- [diluicao] COLCHICINA 1:100?: FCerta=100.0 × TAO=1

## Carga 2 — Equivalências sal↔base (FC03200 → ativos_sinonimos.fator_equiv)
Aplicáveis ao catálogo TAO: **40** | conferem: **40** (100%)
Sinônimos com fator≠1 no TAO: 40

## Carga 3 — Fórmulas padrão (FC05000/FC05100 → lab_formulas_padrao)
| Lado | Fórmulas | Itens |
|---|---|---|
| FCerta | 734 | 2921 |
| TAO | 734 | 2921 |
- "KIT POTE 1000ML VEDAPACK": itens FCerta=3 × TAO=3 ✓
- "AGUA BORICADA 3%  100 ml": itens FCerta=2 × TAO=2 ✓
- "EXCIPIENTE BASE": itens FCerta=3 × TAO=3 ✓

## Carga 4 — Lotes vivos (FC03140 → lab_lotes_mp)
FCerta (estoque>0, validade≥07/07/2026): **1186** | TAO: **1167** (19 sem ativo no TAO, não migrados)
Amostra conferida (400 mais recentes): **397/398** batem em lote+validade+estoque (99%)
Divergências da amostra:
- CAP GELAT 0 ROSA/BRANCA lote 2510840RA: FCerta val=2030-07-03 est=4730.0 × TAO=presente

## Carga 5 — Lista de bloqueio GLP-1
Nenhuma substância GLP-1 no catálogo Magis (esperado — não manipula). Trava preventiva fica na regra do motor (por DCB/nome no orçamento).

## Carga Histórico — Requisições/fórmulas por cliente (FC07000 + FC12100/FC12110 → hist_*)
Executada 07/07/2026 após migration_historico_v1.sql (rodada pelo Carlos).

| Nível | FCerta | TAO | |
|---|---|---|---|
| clientes com requisição | 7.866 | 7.866 | ✅ 100% |
| fórmulas (requisições 2018→06/2026) | 41.722 | 41.722 | ✅ 100% |
| itens (TPCMP C+E+P) | 243.989 | 243.989 | ✅ 100% |

Amostra CDCLI 1256 (3 últimas): nrrqu/série/data/preço idênticos nos dois lados; itens da req 46455: 5 = 5 ✅.
SERIER alfanumérico do FCerta (0-9 depois A-Z) mapeado p/ int estável (A=10…Z=35); verificado sem colisão.
Itens tipo R (explosão de estoque) e demais internos NÃO migram — ficam no arquivo FCerta.

⚠ **Dados PARCIAIS por natureza** (snapshot do backup). Complemento contínuo:
**tao-lab/sync_historico.py** — rotina incremental idempotente, acionável a cada backup novo do FCerta.
Fonte configurável (--db-dir / --db-file; padrão = pasta DB/ do OneDrive), área de trabalho
C:\Users\carlo\FCertaSync (Firebird embedded + cópia do banco + state.json). Modo --full reconcilia as chaves de fórmula.

**Validação da rotina incremental (07/07):** o ALTERDB.ib do OneDrive (29/06) era mais novo que o snapshot
da 1ª carga (15/06) — a sync trouxe **+52 clientes, +259 fórmulas, +1.466 itens**; última req 46783 de
29/06 idêntica nos dois lados. Confronto pós-sync: fórmulas 41.981 = 41.981 ✅; clientes TAO 7.918 × FCerta
7.917 (1 superset inofensivo — cliente cujas reqs saíram do FCerta); itens TAO 245.455 × FCerta 245.456
(1 item adicionado no FCerta a fórmula antiga — o incremental cobre fórmulas novas; reconciliação
item-a-item de fórmulas editadas fica p/ evolução do --full).

---
**Notas p/ revisão:** 14 diluídas sem vínculo com a pura (associar pela ficha); 2 equivalências com sinônimo apontando p/ outro ativo (AC FOLINICO, VIT B5@) — fator gravado no vínculo existente; 817 itens de fórmula padrão sem ativo no catálogo ativo (ficam por descrição).