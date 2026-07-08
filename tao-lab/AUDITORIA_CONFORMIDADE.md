# Auditoria de Conformidade — TAO Lab × Legislação (08/07/2026)
Cruzamento de cada exigência legal com o que foi CONSTRUÍDO. Escopo Magis: Cotia/SP, Simples Nacional,
**sem estéreis, sem homeopatia, sem GLP-1, sem Farmácia Popular** (confirmado Carlos).
Legenda: ✅ pronto e no ar · 🔶 parcial (falta detalhe) · 🔴 não construído · ⚪ procedural (fora do sistema).

---

## 1. RDC 67/2007 — Boas Práticas de Manipulação  →  ✅ COMPLETO (com 3 refinamentos menores)

### Matéria-prima e recebimento
| Exigência | Status | Onde |
|---|---|---|
| Entrada de MP com NF, fabricante/fornecedor, lote | ✅ | Estoque → Entrada de NF (XML) |
| Lote interno por recebimento + rastreio | ✅ | lab_lotes_mp |
| **CQ de recebimento** (quarentena → aprovado/reprovado, com registro de quem e quando) | ✅ | Estoque → Lotes |
| Controle de validade (não usar lote vencido) | ✅ | só lote aprovado e não vencido aparece na pesagem |
| Laudo de análise arquivado por lote | 🔶 | campo existe (laudo_url); falta a tela de **upload do PDF** |
| Qualificação de fornecedores | 🔶 | cadastro completo; falta campo de **avaliação/qualificação** |
| Fracionamento de MP com registro | 🔶 | movimentos de estoque cobrem; sem tela específica de fracionamento |

### Manipulação (Ordem de Manipulação)
| Exigência | Status | Onde |
|---|---|---|
| OM com nº sequencial, paciente, prescritor, data | ✅ | Produção (lab_ordens) |
| **Rastreabilidade: componente → lote de MP** | ✅ | pesagem com lote por item (lab_ordem_itens.lote_mp_id) |
| Quantidade pesada por componente | ✅ | qtd_pesada |
| Quem pesou / manipulou / conferiu | ✅ | pesado_por, manipulador, conferente |
| **Cálculos farmacotécnicos** (fator, equivalência, teor, diluição, densidade, dose máx) | ✅ | Motor v2 (teste de ouro 99,92%) |
| Verificação de dose máxima com alerta | ✅ | dose_max Zanini + alerta no orçamento |
| Fórmulas padrão / oficinais | ✅ | 734 carregadas + botão no editor |
| Prazo de validade da preparação | ✅ | floral 90 / demais 120 dias |
| Preparações intermediárias/bases (produção interna) | 🔶 | tabela existe; sem tela (a Magis produz diluídas — capacidade futura) |

### Rotulagem, documentação e qualidade
| Exigência | Status | Onde |
|---|---|---|
| **Rótulo com todos os dizeres do Anexo I** (paciente, prescritor, composição, lote/validade, posologia, farmácia+RT, advertências, conservação) | ✅ | botão Rótulo na OM (imprimível + auditável) |
| **Livro de receituário** (registro sequencial) | ✅ | Fórmulas → Livro de Receituário |
| Guarda de OMs e registros (mín. 2 anos) | ✅ | banco + backup em nuvem |
| Recolhimento/recall — rastrear lote → pacientes | ✅ | nasce da rastreabilidade lote→OM→paciente |
| Controle de qualidade (peso médio de cápsulas) | ⚪ | manual, fora do sistema (decisão Carlos — item de inspeção da RT) |
| Reclamações/SAC | 🔶 | TAO CRM (falta tipificar "reclamação") |

## 2. Controlados — Portaria 344/98  →  🔴 NÃO CONSTRUÍDO (Pacote 4)
| Exigência | Status |
|---|---|
| Escrituração de TODAS as movimentações de controlados (entrada/saída/perda/transferência) | 🔴 |
| Dados da Notificação de Receita na OM (tipo A/B/especial, nº) + **retenção da receita** | 🔴 (campo receita_url já existe na OM; faltam tipo/nº e a marcação de controlado) |
| Identificação do comprador (nome, documento, endereço) | 🔴 |
| **Balanços BSPO / BMPO** para a VISA | 🔴 |
| Livro de Registro Específico | 🔴 |

## 3. SNGPC — RDC 27/2007 + RDC 22/2014 (obrigatório Sudeste desde 01/09/2025)  →  🔴 NÃO CONSTRUÍDO (Pacote 4)
| Exigência | Status |
|---|---|
| Escrituração eletrônica dos controlados | 🔴 |
| **Transmissão do XML** à ANVISA a cada 1–7 dias (webservice) | 🔴 |
| Validação/rejeição/reenvio + guarda de protocolos | 🔴 |
| Inventário inicial ao migrar de sistema | 🔴 (parte do corte FCerta→TAO) |
| Credenciamento estabelecimento + RT na ANVISA | ⚪ já existe (Magis transmite hoje pelo FCerta) |

## 4. Antimicrobianos — RDC 471/2021  →  🔴 (junto do Pacote 4)
Receita 2 vias/retenção/validade 10 dias + escrituração SNGPC. Mesmos campos de receita da OM.

## 5. GLP-1 (IN 360/2025)  →  ✅ trava existe / N/A na prática
A Magis **não manipula GLP-1**. A trava de substância bloqueada/restrita já existe no Motor v2 (recusa semaglutida/bloqueadas) como salvaguarda.

## 6. Fiscal (Simples Nacional)  →  🔴 (depende de middleware externo)
Emissão NFC-e/NF-e com grupo de rastreabilidade (lote/validade) + relatório de faturamento p/ PGDAS. Contas a Pagar já existe; falta a **emissão fiscal** (via middleware tipo PlugNotas/Focus) e o relatório ao contador de faturamento.

## 7. Demais  →  em grande parte ⚪ (procedural) ou ✅
- **Livro de receituário / licenças / RT**: ✅ (livro pronto; licenças no cadastro da empresa).
- **LGPD** (dado de saúde sensível): 🔶 base de perfis existe; **reforçar trilha de auditoria** de quem acessa dados de paciente.
- PGRSS (resíduos), farmacovigilância: ⚪ procedural.

---

## SÍNTESE — o que falta para não ter nada em aberto
**Bloco grande faltando (o que o Carlos apontou):**
1. **Pacote 4 — Controlados + SNGPC + Antimicrobianos**: escrituração dos controlados, campos de Notificação de Receita e comprador na OM, balanços BSPO/BMPO e **transmissão do XML à ANVISA**. É o único bloco regulatório inteiro ainda não construído. Depende de: schemas XSD públicos da ANVISA + credenciais de transmissão (que a Magis já tem).
2. **Fiscal (NFC-e/NF-e)**: depende de contratar middleware.

**Refinamentos menores (RDC 67 já está completa, mas polir):**
- Upload do PDF do laudo por lote.
- Campo de qualificação do fornecedor.
- Tela de produção interna de diluídas.
- Reforço da trilha de auditoria (LGPD).
- Tipificar "reclamação/evento adverso" no CRM.

**Corte final (virar a chave):** inventário inicial + 1 ciclo em paralelo FCerta×TAO + parecer da RT (RDC 67) e do contador (fiscal).
