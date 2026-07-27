# Confronto FCerta × TAO Neo — Cargas do Pacote 1 (Motor Farmacotécnico)
Gerado automaticamente em 2026-07-27. Fonte FCerta: backup restaurado em C:\Users\carlo\FCertaSync\fcerta_atual.ib.

## Carga 1 — Dados técnicos dos ativos (FC03000 → ativos)
| Campo | Conferem | Total FCerta | % |
|---|---|---|---|
| dcb | 351 | 351 | 100% |
| densidade | 535 | 535 | 100% |
| markup | 629 | 630 | 99% |
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
| FCerta | 737 | 2932 |
| TAO | 734 | 2921 |
- "KIT POTE 1000ML VEDAPACK": itens FCerta=3 × TAO=3 ✓
- "AGUA BORICADA 3%  100 ml": itens FCerta=2 × TAO=2 ✓
- "EXCIPIENTE BASE": itens FCerta=3 × TAO=3 ✓

## Carga 4 — Lotes vivos (FC03140 → lab_lotes_mp)
FCerta (estoque>0, validade≥07/07/2026): **1212** | TAO: **1280** (19 sem ativo no TAO, não migrados)
Amostra conferida (400 mais recentes): **396/398** batem em lote+validade+estoque (99%)
Divergências da amostra:
- CAP ENTERICA 1 INCOLOR lote C25080151: FCerta val=2028-08-17 est=2000.0 × TAO=presente
- CAP GELAT 0 ROSA/BRANCA lote 2510840RA: FCerta val=2030-07-03 est=3800.0 × TAO=presente

## Carga 5 — Lista de bloqueio GLP-1
Nenhuma substância GLP-1 no catálogo Magis (esperado — não manipula). Trava preventiva fica na regra do motor (por DCB/nome no orçamento).

---
**Notas p/ revisão:** 14 diluídas sem vínculo com a pura (associar pela ficha); 2 equivalências com sinônimo apontando p/ outro ativo (AC FOLINICO, VIT B5@) — fator gravado no vínculo existente; 817 itens de fórmula padrão sem ativo no catálogo ativo (ficam por descrição).