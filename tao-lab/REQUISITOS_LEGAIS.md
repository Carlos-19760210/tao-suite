# TAO Neo × Legislação da Farmácia de Manipulação
**Mapa de exigências legais → capacidade de sistema** · compilado em 06/07/2026
Escopo: Magis-TAO (Cotia/SP, Simples Nacional, sem estéreis*, sem homeopatia*, sem Farmácia Popular).
\* confirmar com a RT — hoje a Magis declina colírio/injetável (motivo de perda padrão) e as tabelas de homeopatia do FCerta estão vazias.

Legenda de status: ✅ TAO já cobre · 🔵 no draft do TAO Lab · 🔴 falta desenhar · ⚪ procedural (não é sistema; sistema só arquiva registro)

---

## 1. RDC 67/2007 (+ RDC 87/2008) — Boas Práticas de Manipulação (BPMF)
*Vigente. Revisão em curso atinge o Anexo IV (estéreis) — fora do escopo Magis; acompanhar.*

### 1.1 Matéria-prima e materiais de embalagem
| Exigência | O sistema precisa | FCerta (tabela) | Status TAO |
|---|---|---|---|
| Registro de entrada de MP com NF, fabricante/fornecedor, lote do fabricante | Entrada por NF vinculando lote | FC99S21/estoque | 🔵 `lab_lotes_mp` |
| **Lote interno** atribuído a cada recebimento + rastreio | Numeração interna por recebimento | CTLOT | 🔵 `lab_lotes_mp.lote_interno` |
| Laudo de análise do fornecedor arquivado por lote | Anexo (PDF) por lote | PDFs na pasta FCerta | 🔵 `lab_lotes_mp.laudo_url` |
| Análise/inspeção no recebimento (CQ de MP) com registro de resultado e status (quarentena→aprovado/reprovado) | Status por lote + registro de quem aprovou | — | 🔵 `status` (falta: campos de resultado/aprovador — **ajustar draft**) |
| Qualificação de fornecedores | Cadastro de fornecedores com avaliação | FC02000 | ✅ `fornecedores` (Cotações) — falta campo de qualificação 🔴 |
| Controle de validade de MP (bloqueio de vencido) | Validade por lote + alerta/bloqueio no uso | DTVAL | 🔵 (falta regra de bloqueio na pesagem — **ajustar draft**) |
| Fracionamento de MP com registro | Movimentos de fracionamento | FC12xxx | 🔴 fase Estoque |
| Água purificada: análises periódicas | Registro de análises (data/resultado) | — | ⚪ (arquivo de registros) |

### 1.2 Manipulação (a Ordem de Manipulação)
| Exigência | O sistema precisa | FCerta | Status TAO |
|---|---|---|---|
| OM com nº sequencial, paciente, prescritor, data | Numeração + vínculos | FC12100 (NRRQU) | 🔵 `lab_ordens.numero` |
| **Rastreabilidade: componente → lote de MP usado** | Lote por item da OM | FC12110.NRLOT | 🔵 `lab_ordem_itens.lote_mp_id` |
| Registro da quantidade pesada de cada componente | Qtd pesada por item | FC12110.QTPESA | 🔵 `qtd_pesada` |
| Identificação de quem pesou/manipulou/conferiu (dupla checagem) | Usuários por papel + timestamps | CDFUNMANIP etc. | 🔵 `manipulador_id/conferente_id/pesado_por` |
| **Cálculos farmacotécnicos: fator de correção/equivalência, teor, diluição, densidade, dose unitária** | Motor de cálculo com dados técnicos por MP | FC03000 (FATOR, TEOR, DENSIDADE, DILUICAO), FC03140 (diluições), FC03200 (equivalências) | 🔴 **GAP CENTRAL** — TAO Fórmula calcula cápsula/QSP/VOLAPA, mas NÃO tem fator de correção, equivalência sal↔base, teor e diluição por MP (466 mil itens usam diluição na Magis) |
| Verificação de dose máxima/limites por substância | Base de doses máx. (DCB) + alerta | FC71600 (Zanini), ativos.dose_max | 🔴 (schema TAO tem dose_max vazio — popular e ligar alerta) |
| Fórmulas padrão/oficinais (POP de fórmulas repetidas) | Cadastro de fórmula padrão com componentes | FC05000/FC05100 | 🔴 fase TAO Lab |
| Prazo de validade da preparação conforme critérios | Cálculo de validade por forma/regra | DTVAL | ✅ regra Magis já usada (90d floral/120d demais) → 🔵 formalizar em `dt_validade` |
| Registro de preparações intermediárias/bases (produção interna) | Lote interno de semi-acabado | FC038A2 (vazia na Magis) | 🔵 `lab_lotes_internos` (capacidade pronta; uso futuro) |

### 1.3 Rotulagem (Anexo I, item 17)
| Exigência (conteúdo obrigatório do rótulo) | Status TAO |
|---|---|
| Nome do prescritor; nome do paciente; nº de registro (livro receituário); data de manipulação; prazo de validade; composição qualitativa/quantitativa por unidade; nº de unidades; via de administração/posologia; identificação da farmácia (razão social, CNPJ, endereço); nome do RT + CRF; condições de conservação; frases de advertência aplicáveis (ex.: "USO EXTERNO", agite, controlados: "Venda sob prescrição…") | 🔵 `lab_rotulo_templates` com variáveis + `lab_rotulos.texto` arquivado — **checklist dos dizeres vira template padrão validado com a RT** |

### 1.4 Documentação, escrituração e qualidade
| Exigência | Status TAO |
|---|---|
| **Livro de receituário** (registro sequencial de todas as manipulações — Lei 5.991/73 art. 42 + RDC 67) | 🔵 deriva de `lab_ordens` (relatório "Livro de Receituário" com numeração contínua) — equivalente ao FC7LivroReceituario |
| Guarda de OMs e registros (mín. 2 anos; controlados 2 anos após balanço) | ✅ banco + backup nuvem |
| POPs, autoinspeção, treinamento de pessoal | ⚪ (opcional: repositório de POPs com versão/leitura — fase futura) |
| Controle de qualidade da preparação (organoléptico, peso médio de cápsulas, pH/volume quando aplicável) com registro | ⚪ decisão 06/07: peso médio segue MANUAL (fora do sistema); registro em papel/planilha — **a RT deve manter, é item de inspeção** |
| Registro de reclamações e providências (SAC) | ✅ TAO CRM (conversas/cards) — falta tipificar "reclamação" 🔴 leve |
| Recolhimento de produto (recall) — rastrear lote→pacientes | 🔵 nasce da rastreabilidade (`lab_ordem_itens.lote_mp_id` → ordens → pacientes) |
| Transporte/entrega com condições preservadas | ⚪ (registro de entrega — TAO CRM/Caixa já registram o fluxo) |

## 2. Controlados — Portaria SVS/MS 344/1998 (+ Port. 6/99)
| Exigência | O sistema precisa | Status TAO |
|---|---|---|
| Escrituração de TODAS as movimentações de substâncias/medicamentos controlados (entradas, saídas, perdas, transferências) | Livro eletrônico = SNGPC | 🔴 fase Controlados (espelhar FC99S21-S24) |
| Receituário específico: Notificação de Receita A (amarela)/B (azul)/especial (branca 2 vias) conforme lista; **retenção da receita** | OM de controlado exige dados da notificação (nº, tipo) + arquivo da receita retida | 🔴 campos na OM (`lab_ordens`: tipo_receita, nr_notificacao, receita_url já existe) — **ajustar draft** |
| Identificação completa do comprador (nome, documento, endereço) e do prescritor (conselho/UF) | Campos no atendimento/OM | 🔴 (FC99S22 mostra o modelo: NOMECOMP, TPDOCCOMP, NRDOCCOMP) |
| **Balanços**: BSPO (trimestral/anual) e BMPO — entregues à VISA | Relatórios gerados dos movimentos | 🔴 fase Controlados |
| Armazenamento segregado/chave | ⚪ físico | — |
| Livro de Registro Específico p/ o que não vai no SNGPC (ex.: antimicrobianos quando aplicável) | Relatório equivalente | 🔴 fase Controlados |

## 3. SNGPC — RDC 27/2007 + RDC 22/2014 + retorno 2025
| Exigência | Status TAO |
|---|---|
| Credenciamento do estabelecimento + RT na ANVISA (senha de transmissão) | ⚪ já existe (Magis transmite hoje) — RT manter cadastro atualizado |
| Inventário inicial ao migrar de sistema | 🔴 planejado (corte formal FCerta→TAO com inventário) |
| Transmissão XML a cada 1–7 dias (webservice) — **obrigatória no Sudeste desde 01/09/2025** | 🔴 fase Controlados (schema XML público; wrapper visto em produção na pasta FCerta) |
| Validação/rejeição/reenvio + guarda de protocolos | 🔴 fase Controlados |
| Troca de RT exige finalização/novo inventário | 🔴 fluxo administrativo no módulo |

## 4. Antimicrobianos — RDC 471/2021
| Exigência | Status TAO |
|---|---|
| Receita em 2 vias, retenção da 2ª via, validade 10 dias | 🔴 mesmos campos de receita da OM (tipo=antimicrobiano) |
| Escrituração no SNGPC (antimicrobianos manipulados) | 🔴 fase Controlados |

## 5. GLP-1 / "canetas" — alterações RDC 471 + IN 360/2025 (vigência 2026)
| Exigência | Status TAO |
|---|---|
| **Semaglutida: manipulação PROIBIDA**; tirzepatida restrita (IFA com uso comprovado em medicamento aprovado) | 🔴 flag de substância bloqueada/restrita no cadastro de ativos + trava na OM/orçamento (**decisão de produto: lista de bloqueio por DCB**) |
| Liraglutida/dulaglutida/lixisenatida etc.: receita 2 vias, retenção, validade 90 dias, escrituração SNGPC | 🔴 idem controlados (tipo de receita GLP-1) |
| Receita eletrônica só via serviços integrados ao **SNCR** | ⚪/🔴 validar receita eletrônica (QR) — fase Controlados |
| **CONFIRMAR com a RT: a Magis manipula GLP-1 hoje?** | pendente |

## 6. Demais obrigações
| Norma | Exigência | Status |
|---|---|---|
| Lei 13.021/2014 + Lei 5.991/73 | RT presente, licenças (AFE, licença sanitária estadual/CEVS, CRF-PJ), livro de receituário | ⚪ documental; sistema guarda nº das licenças no cadastro do cliente 🔴 leve |
| RDC 222/2018 | PGRSS (resíduos) — plano e registros | ⚪ |
| RDC 44/2009 | Boas práticas de dispensação/SAC | ✅ parcialmente (CRM) |
| Farmacovigilância (VigiMed) | Notificar eventos adversos | ⚪ (CRM pode tipificar "evento adverso" p/ rastrear 🔴 leve) |
| Fiscal (Simples Nacional) | Emissão NFC-e/NF-e **com grupo de rastreabilidade (lote/validade) p/ medicamentos**; relatório de faturamento p/ PGDAS do contador | 🔴 fase Fiscal (middleware) — FC1E081 mostra o modelo do grupo K |
| LGPD | Dados de saúde de pacientes = dado sensível: acesso por perfil, trilha de auditoria | ✅ base (roles TAO) + 🔴 reforçar trilha no TAO Lab |

---

## Síntese dos GAPS que mudam o desenho (prioridade do Carlos: fórmulas/diluição/produção)
1. **Motor farmacotécnico completo** (o "coração" apontado pelo Carlos): fator de correção, **fator de equivalência sal↔base**, **teor**, **diluições por MP** (85% dos itens da Magis!), densidade, dose máxima com alerta — dados técnicos por MP (FC03000/FC03140/FC03200/FC71xxx) migrando para `ativos` + tabelas satélites. **Este é o pré-requisito de tudo** — sem ele a OM calcula errado.
2. **Fórmulas padrão** (FC05000/FC05100) — cadastro reutilizável.
3. Receita/receituário na OM: tipo de receita, nº de notificação, retenção (arquivo), validade da receita — base p/ 344/471/GLP-1.
4. CQ de recebimento de MP (resultado + aprovador) e bloqueio de lote vencido/reprovado na pesagem.
5. Lista de substâncias bloqueadas/restritas (semaglutida etc.) com trava.
6. Balanços BSPO/BMPO + SNGPC (fase Controlados).
7. Campos de licenças sanitárias no cadastro + qualificação de fornecedor.

**Confirmações pendentes com Carlos/RT:** estéreis? homeopatia? manipula GLP-1? peso médio segue manual (ciente de que é item de inspeção)?
