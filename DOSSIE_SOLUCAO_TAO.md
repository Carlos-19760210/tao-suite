# Dossiê da Solução — TAO Neo (Suíte SaaS para Farmácias de Manipulação)

> Documento de contexto para análise externa (inclusive por outra IA). Descreve **conceito, módulos, infraestrutura e estado de implantação** da solução, com foco em subsidiar **posicionamento e precificação**. Português (Brasil).

---

## 1. Resumo executivo

**A gênese é o AGENTE.** O produto **nasceu como um agente de IA no WhatsApp** (uma plataforma de chatbot multi-tenant — tecnicamente o plugin se chama `chatbot-platform`). **Tudo parte do agente**: é ele que conversa com o cliente, tria, responde, transcreve áudio, dispara campanhas e **inicia o fluxo** que alimenta todos os demais módulos. A suíte de gestão (CRM, Fórmula, Caixa, Entregas, Produção, BI) **cresceu ao redor do agente** — não o contrário. Qualquer avaliação de posicionamento/preço deve tratar o agente como o **núcleo e a porta de entrada**, não como um acessório.

**O que é hoje:** um **agente de IA no WhatsApp** acoplado a uma **suíte SaaS vertical, multi-tenant, para farmácias de manipulação**, cobrindo a operação de ponta a ponta — do **atendimento (agente + equipe)** ao **orçamento/manipulação**, **produção e rótulo**, **PDV/financeiro**, **entregas (última milha)** e **inteligência de gestão (BI)**. É "AI-native" e "WhatsApp-first": o cliente conversa pelo WhatsApp, **o agente e a equipe atendem no mesmo lugar**, e o negócio flui pelos módulos sem troca de sistema.

**Como é entregue:** um **portal web** (multi-tenant) onde cada farmácia é um *workspace* isolado, com controle de acesso por perfil. Tecnicamente montado sobre **WordPress (plugins próprios) + banco Supabase (PostgreSQL) + automação WhatsApp (Evolution API + N8N) + IA (Google Gemini / Whisper)**.

**Substituição de ERP legado:** a suíte foi desenhada para **substituir o "Fórmula Certa" (FCerta)**, ERP tradicional do segmento — trazendo o mesmo domínio (motor de manipulação, estoque, produção, fiscal) numa stack moderna, com IA e canais digitais nativos.

**Estágio:** núcleo em **produção real** numa farmácia âncora (Magis-TAO). Vários módulos no ar; alguns recursos atrás de *feature flags*; peças fiscais/regulatórias (SNGPC) em desenvolvimento.

---

## 2. Proposta de valor e público-alvo

**Público-alvo:** farmácias de manipulação (magistrais) de pequeno/médio porte no Brasil — negócios que hoje usam ERPs de manipulação tradicionais (ex.: Fórmula Certa, Vidhas, Trinix/Simformula, HScience) + WhatsApp "no braço" + planilhas.

**Dores que resolve:**
- Atendimento por WhatsApp desorganizado (várias conversas, sem funil, sem histórico).
- Orçamento de fórmula manual, lento e sujeito a erro (cálculo, equivalência de sal, dose máxima, restrições regulatórias).
- Desconexão entre venda, produção, financeiro e entrega (sistemas separados).
- Falta de visão de gestão (faturamento real, margem, motivos de perda, produtividade da equipe).

**Proposta de valor:**
- **Tudo num lugar**: atendimento + orçamento + produção + caixa + entrega + gestão.
- **IA de atendimento** (persona "Dr. TAO") que tria, responde e encaminha para humano.
- **Motor farmacotécnico** que calcula pesagem, equivalências, alerta dose máxima e aplica gates regulatórios (RDC 67).
- **Gestão com BI** próprio: faturamento (fonte Caixa), conversão, perdas por motivo, tempos de atendimento.

---

## 3. Módulos da suíte

> Cada módulo é um plugin WordPress próprio, integrado pelo portal. Status honesto por módulo. **O Agente (3.0) é o núcleo — tudo começa nele.**

### 3.0. O AGENTE de IA (núcleo / porta de entrada) — `chatbot-platform` + N8N
**É a origem do produto e o coração da operação.** Um **agente de IA multi-tenant no WhatsApp** que:
- **Conversa com o cliente** de forma natural (LLM **Google Gemini 2.5 Flash**), tria a intenção, responde dúvidas e **encaminha para humano** (handoff) quando preciso — no mesmo painel do CRM.
- **Entende áudio** (transcrição **Whisper via Groq**) e mídia do WhatsApp.
- É **configurável por tenant** ("Chatbot Genérico", montado em **N8N** — orquestração de fluxos por farmácia; hoje em versão v6 ativa), com **persona própria** para a farmácia (a persona de atendimento **"Dr. TAO"** para a Magis-TAO, em refino).
- Aplica **guardrails determinísticos** (regras críticas fora do LLM) e **detecção de intenções sensíveis** (ex.: pedido de orçamento/prescrição → cria card e chama a equipe).
- **Inicia o fluxo comercial**: da conversa nasce o card no CRM, que vira orçamento, venda, produção e entrega. **O agente é o gatilho de tudo.**
- **Dispara campanhas** (mensagens ativas em massa por WhatsApp) — ver 3.0.1.

**Por que importa para o preço:** o agente é o **wedge** (a dor que abre a porta) e o principal **motor de valor percebido** (reduz trabalho humano de atendimento). É também onde estão os *cost drivers* de IA e de WhatsApp. **Status: em produção** (motor genérico ativo; persona Dr. TAO em refino).

#### 3.0.1. Campanhas (marketing ativo por WhatsApp) — em `chatbot-platform`
Disparo de mensagens ativas/segmentadas pelo WhatsApp (reativação, avisos, promoções), integrado ao agente e às instâncias por workspace. **Observação técnica importante:** Campanhas pertence ao `chatbot-platform` (**não** ao CRM) — são módulos distintos. **Status: em produção.**

### 3.1. Portal / TAO Neo (núcleo multi-tenant) — `chatbot-platform`
O "sistema operacional" da suíte. Entrega o **portal web**, o **roteamento de telas (dispatcher)**, o **multi-tenant** (um workspace por farmácia) e o **controle de acesso por perfil (RBAC)** — 21 telas governadas por perfis, com **alçadas** (ex.: limite de desconto por perfil, em % no orçamento e em R$ no PDV). Também hospeda o motor de chatbot genérico (N8N) e a config de integrações. **Status: em produção.**

### 3.2. TAO CRM — `tao-crm`
Kanban de vendas + **chat WhatsApp nativo** no mesmo painel. Funil de **Vendas** e funil de **Pós-vendas** (o mesmo card cruza os funis quando o negócio é aprovado). Recursos: automações (gatilhos por fase/tempo/sem-resposta), tags, lembretes/follow-up, metas por atendente, comentários internos, transferência, agendamento de mensagem, **NPS pós-venda**, **fluxo de renovação** automático, **dispatch multi-instância** (várias linhas de WhatsApp), campos customizados obrigatórios por fase, e um **hub de Análise/BI** (ver 3.8). **Status: em produção (v1.9.x).**

### 3.3. TAO Fórmula — `tao-formula`
Módulo de **cotação/orçamento de manipulação**. Cadastros (ativos, formas farmacêuticas, prescritores, fornecedores), **motor de cálculo** (pesagem, equivalência sal↔base, alerta de dose máxima, trava de restrição, teor do lote), **leitura de receita por IA**, **gate RDC 67** (regulatório), importação de orçamentos por XLSX, e painel. É o **embrião do "TAO Lab"** (motor completo). **Status: em produção.**

### 3.4. TAO Caixa — `tao-caixa`
**PDV e financeiro**. Cupom com múltiplos cards e split de pagamento, sessão de caixa, estorno auditado, **operadora de cartão como contrato** (taxas por operadora × modalidade × bandeira), **recebíveis** (bruto/líquido, previsão de recebimento), aportes/sangrias, e base para **conciliação** de extrato × recebíveis. A **venda nasce do card ganho** (integra com o CRM). É a **fonte de verdade do faturamento**. **Status: em produção (redesenho de recebíveis concluído; conciliação de extrato pendente).**

### 3.5. TAO Entregas — `tao-entregas`
**Última milha**. Aba de Entrega dentro do card, fluxo de pós-vendas (produção → pronto → em rota → entregue), gatilho na aprovação do negócio, travas de movimentação (ex.: exige endereço para "Pronto p/ Entrega", exige pago para avançar) e **baixa espelhada entre Caixa e Entrega**. **Status: em produção.**

### 3.6. Cotações de Compra — `tao-cotacoes`
**Compras/suprimentos**: pedido de cotação a fornecedores, **propostas geradas/lidas por IA (Gemini)**, chat próprio com o fornecedor (fora do Kanban de vendas), comparativo com "último preço pago" e export XLSX. **Status: Fases 1-2 em produção; sugestão automática de fornecedores/captura de anexo pendente.**

### 3.7. TAO Lab — motor de manipulação (substituto do FCerta)
O **coração farmacotécnico** e de retaguarda: motor de cálculo completo, **estoque** (lotes de MP, FEFO, teor), **produção** (ordem de manipulação, pesagem, conferência), **rótulo/ficha**, e **livro**. Projetado para **substituir o FCerta** com **base de dados própria** (Farmacopeia/DCB/RDC 67) para poder ser comercializado a terceiros. **Status: Pacotes 1-3 construídos e no ar; falta o Pacote 4 (SNGPC/fiscal) para "virar a chave" e substituir 100% o legado.**

### 3.8. Análise / BI — hub de gestão (dentro do TAO CRM)
Central de inteligência de gestão, **restrita a perfis de gestão**, cruzando CRM + Fórmula + Caixa. Dois modos:
- **Modo Simples** (para quem não é analista): KPIs e "perguntas de negócio" prontas com resposta em gráfico + frase automática. Blocos **Financeiro** (faturado/recebido do Caixa, consumo de ativos, custo por forma/classificação) e **Atendimento/Operação** (aprovações, perdas, conversão, TMR, TMA, fila de espera), tudo com **abertura por responsável**.
- **Modo Avançado**: um **cubo OLAP** (tabela dinâmica estilo Excel, biblioteca WebDataRocks) sobre uma **base denormalizada** — o gestor dimensiona qualquer informação em qualquer visão, escolhe o tipo de gráfico e faz *drill* macro↔micro (ex.: telefone → OM → ativo → lote).

Leituras financeiras corretas por **fonte e por data**: **Faturado** = valor emitido no Caixa por data de fechamento; **Recebido** (bruto e líquido) por data de recebimento. Leituras operacionais por **evento**: **Aprovado** = transição do card para o pós-vendas (data da aprovação); **Perdas** = cancelamentos com **motivo, fase de origem, responsável, classificação da formulação e tempo até cancelar**. Filtro-mestre **Aprovadas / Canceladas / Todas**. **Status: em produção.**

### 3.9. Canais adjacentes
- **Loja online** (WooCommerce, Magis-TAO) — canal de e-commerce adjacente, integrável ao agente/CRM.
- *(A persona de atendimento "Dr. TAO" está descrita no núcleo — item 3.0, o Agente.)*

---

## 4. Inteligência artificial

- **LLM principal:** Google **Gemini 2.5 Flash** — chatbot de atendimento, leitura de receita, geração/leitura de propostas de cotação.
- **Áudio:** transcrição por **Whisper (via Groq)** — áudios do WhatsApp viram texto.
- **Guardrails determinísticos** (ex.: detecção de crise no projeto irmão), separando decisão crítica do LLM.
- **Custo de IA é baixo** (ordem de poucos dólares/mês por operação de porte pequeno-médio) — a IA **não** é o principal *cost driver*.

---

## 5. Arquitetura e infraestrutura

**Camadas:**
- **Aplicação/UI:** WordPress + **plugins próprios** (`chatbot-platform`, `tao-crm`, `tao-formula`, `tao-caixa`, `tao-cotacoes`, `tao-entregas`) — o portal e as telas.
- **Banco de dados:** **Supabase (PostgreSQL)** acessado via **PostgREST** (REST). É onde vivem workspaces, cards, mensagens, orçamentos, vendas, pagamentos, estoque, produção, etc.
- **WhatsApp + automação:** **Evolution API** (gateway WhatsApp, não-oficial) + **N8N** (orquestração de fluxos: dispatch, bot, campanhas) — hospedados em **Cloudfy**.
- **IA:** APIs Google Gemini + Groq/Whisper.
- **Hospedagem WP:** servidor de hospedagem (domínio `solucoesetao.com.br`).

**Padrão multi-tenant:** cada farmácia = um **workspace** (`crm_workspaces`), com `cliente_id` próprio; todas as consultas são escopadas por workspace/cliente. As instâncias de WhatsApp são por workspace.

**Fluxo de mensagem (simplificado):** Evolution → webhook do portal → cria/atualiza card + grava mensagem → encaminha (não-bloqueante) para o N8N (bot). Respostas humanas saem pelo chat do card; respostas automáticas pelo bot.

**Deploy/operação:** deploy dos plugins por transferência de arquivo + verificação de sintaxe + limpeza de cache de código (OPcache). Backups versionados em repositório Git privado.

**Observação de custo de infra (para precificação):** os *cost drivers* recorrentes são **Supabase**, **Cloudfy (Evolution+N8N)**, **hospedagem WP**, **APIs de IA** e **WhatsApp**. Hoje o WhatsApp usa Evolution (não-oficial, custo baixo); uma eventual migração para a **API oficial do WhatsApp (Cloud API)** introduz **custo por conversa** e muda a conta.

---

## 6. Multi-tenant, segurança e governança

- **Isolamento por workspace** em toda a camada de dados (cada consulta filtra por tenant).
- **RBAC**: perfis de acesso governando 21 telas no portal; **alçadas** por perfil (limites de desconto em % no orçamento e em R$ no PDV).
- **Níveis**: admin (total) / gestor (vê todo o workspace) / atendente (vê só os próprios cards).
- **Auditoria**: histórico de movimentações de card, estorno auditado no Caixa, log de ações.
- **LGPD**: aceite de termos por workspace; contatos como cadastro único.
- **Análise/BI** restrita a perfis de gestão, também por tenant.

---

## 7. Estado de implantação (honesto)

| Módulo | Status |
|---|---|
| **AGENTE de IA no WhatsApp (núcleo)** | **No ar** (motor genérico ativo; persona Dr. TAO em refino) |
| **Campanhas (disparo ativo WhatsApp)** | **No ar** |
| Portal/TAO Neo (multi-tenant, RBAC) | **No ar** |
| TAO CRM (Kanban + WhatsApp + automações + NPS + renovação) | **No ar** |
| TAO Fórmula (orçamento + motor + receita IA + RDC67) | **No ar** |
| TAO Caixa (PDV + recebíveis bruto/líquido) | **No ar** (conciliação de extrato pendente) |
| TAO Entregas (última milha) | **No ar** |
| Cotações de Compra | **No ar** (Fases 1-2; automações pendentes) |
| Análise / BI | **No ar** |
| TAO Lab — motor/estoque/produção/rótulo/livro | **No ar (Pacotes 1-3)**; **Pacote 4 SNGPC/fiscal pendente** |
| Atendente IA "Dr. TAO" | Em desenvolvimento |
| Loja online (WooCommerce) | Canal adjacente |

**Para substituir 100% um ERP legado (FCerta):** falta principalmente a **camada fiscal/regulatória (SNGPC, notas)** — é o item que trava a "virada de chave" definitiva para operações reguladas.

---

## 8. Diferenciais competitivos

1. **Agente de IA no WhatsApp como núcleo** — não é um "bot" acessório: é a porta de entrada que atende, tria e **inicia toda a operação**. A maioria dos ERPs de manipulação não tem agente de IA nativo e integrado ao fluxo comercial.
2. **WhatsApp-first + IA nativa** do atendimento à gestão (agente + equipe no mesmo painel).
3. **Suíte ponta-a-ponta** (agente → CRM → orçamento → produção → financeiro → entrega → BI) sem integrar 3-4 sistemas.
4. **Motor farmacotécnico** com equivalências, dose máxima, restrição e gate RDC 67.
5. **BI de gestão embutido**, com leituras corretas de faturamento (fonte Caixa) e de perdas por motivo/fase — raro no segmento.
6. **Multi-tenant SaaS moderno** (cloud, deploy contínuo) vs. instalações locais dos ERPs tradicionais.

---

## 9. Insumos para precificação (o que a análise externa precisa considerar)

### 9.1. Métrica de valor (unidade de cobrança)
Candidata natural: **por farmácia (tenant)**, com faixas por **porte** (nº de usuários/atendentes e/ou volume de manipulações/mês). Secundárias: por **usuário**, por **instância de WhatsApp**, por **módulo**.

### 9.2. Eixos de cobrança possíveis (combináveis)
- **Produto de entrada = o AGENTE + atendimento (CRM)**: é o *wedge* que abre a conta (dor de WhatsApp/atendimento). Pode ser vendido sozinho como "agente de IA + CRM no WhatsApp" e expandir para os demais módulos.
- **Assinatura mensal por farmácia**, em **faixas** (starter / pro / enterprise) por porte.
- **Núcleo + módulos (add-ons)**: núcleo = **Agente + CRM** (atendimento) e/ou + Fórmula + Caixa; add-ons = Entregas, Cotações, Análise/BI, Lab (motor/produção).
- **Setup/implantação + migração de dados** do ERP legado (fee único — a migração do FCerta é um ativo/entrega concreta).
- **Consumo** (IA, WhatsApp, instâncias extras) — incluído por faixa ou repassado acima de um limite.
- **Treinamento/suporte** (planos de suporte).

### 9.3. Cost drivers (para estimar margem)
- Infra recorrente: **Supabase**, **Cloudfy (Evolution+N8N)**, **hospedagem WP**, **APIs de IA** (baixo), **WhatsApp** (baixo hoje via Evolution; alto se migrar p/ Cloud API oficial, que cobra por conversa).
- Custo de **suporte/onboarding** por farmácia (humano) — costuma ser o maior custo variável em SaaS vertical.
- **Desenvolvimento/manutenção** contínuo.

### 9.4. Comparáveis (para ancoragem — validar valores atuais)
ERPs de manipulação no Brasil (Fórmula Certa, Vidhas, Trinix/Simformula, HScience e similares) — geralmente **licença + mensalidade + módulos**, modelo mais "on-premise/tradicional". O posicionamento aqui é **SaaS moderno, cloud, AI-native, WhatsApp-first**, o que justifica **premium de modernidade** mas exige **prova de robustez fiscal/regulatória** para competir de igual no núcleo regulado.

### 9.5. Perguntas em aberto (a análise externa deve responder/assumir)
1. Vender **por faixa de porte** ou **por usuário**? Qual o teto de tenants por faixa?
2. O que é **núcleo** e o que é **add-on** pago?
3. Preço de **setup/migração** (o esforço de migrar do FCerta é real e valioso).
4. WhatsApp: manter Evolution (barato) ou oferecer **Cloud API oficial** (repassar custo por conversa)?
5. Como cobrar **IA** (incluir vs. medir consumo)?
6. Posicionamento: **substituir o ERP** (precisa fechar o fiscal/SNGPC) **ou** entrar como **camada de atendimento/CRM+BI** sobre o ERP existente (venda mais rápida, menos risco regulatório)? — essa decisão muda todo o *pricing* e o *go-to-market*.

---

*Fim do dossiê. Material destinado a subsidiar posicionamento e precificação; status descrito reflete o estado de desenvolvimento no momento da redação.*
