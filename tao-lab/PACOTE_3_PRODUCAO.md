# TAO Lab — PACOTE 3: PRODUÇÃO / Ordem de Manipulação (desenho)
Rascunho 08/07/2026. Base: draft migration_v1.0.0 + modelo FCerta (FC12100/12110/12500).
Fecha o elo legal de rastreabilidade **lote→OM→paciente** (task #5) e a baixa de estoque (Fatia 4 do Pacote 2).

---

## 1. Objetivo
Substituir o laboratório do FCerta: a **Ordem de Manipulação (OM)** que nasce do orçamento aprovado, passa pelo **kanban de produção**, registra **pesagem + lote por componente** (rastreabilidade), **baixa o estoque** ao produzir, emite o **rótulo RDC 67** e alimenta o **livro de receituário**.

## 2. Escopo (fatias)
- **Fatia A — OM + kanban** (núcleo): OM do orçamento aprovado; etapas configuráveis; pesagem c/ lote por item; baixa de estoque FEFO ao concluir (com explosão de diluída→MP primária, modelo FC12110 TPCMP=R). *Peso médio/balança FORA (decisão Carlos).*
- **Fatia B — Rótulo RDC 67**: template com variáveis + rótulo renderizado/auditável por OM.
- **Fatia C — Livro de receituário**: relatório sequencial das OMs (Lei 5.991 art.42 + RDC 67) — equivalente ao FC7LivroReceituario.

## 3. Tabelas (migration_producao_v1.sql — Fatia A)
- **lab_etapas** — kanban configurável (seed: Conferência→Pesagem→Manipulação→Envase→Rotulagem→CQ/Liberação→Pronto→Entregue).
- **lab_ordens** — a OM: orcamento_id, card_id, **contato_id (paciente = cadastro único)**, prescritor_id (v3), forma/volume/qtd, posologia, validade, etapa atual, controlado (gatilho SNGPC fase 4), manipulador/conferente, `baixou_estoque` (idempotência).
- **lab_ordem_itens** — pesagem + **lote_mp_id por componente** (o elo de rastreabilidade).
- **lab_ordem_etapas** — auditoria do fluxo (quem moveu, quando).
- **lab_rotulo_templates / lab_rotulos** — Fatia B.
Reusa: `prescritores` (v3), `lab_lotes_mp` (motor_v1), `estoque_movimentos` (estoque_v1).

## 4. Fluxos
### 4.1 OM nasce do orçamento aprovado
Quando um orçamento é aprovado (status), gera uma OM: copia paciente (contato_id), forma, itens (ativo/dose→qtd_prescrita), prescritor, posologia. Entra na 1ª etapa (Conferência).

### 4.2 Kanban de produção
OM anda pelas etapas (arrastar/botão). Cada movimento grava em lab_ordem_etapas. Papéis: manipulador (pesa/manipula) e conferente (farmacêutico que libera no CQ).

### 4.3 Pesagem com lote (rastreabilidade)
Na etapa Pesagem, cada componente recebe: qtd_pesada + **lote FEFO sugerido** (lab_lotes_mp aprovado, validade mais próxima). O farmacêutico confirma/troca o lote. Isso é o **lote→OM** — e a OM→paciente via contato_id.

### 4.4 Baixa de estoque (Fatia 4 do Pacote 2, integrada aqui)
Ao concluir a OM (ou na etapa CQ/Liberação), baixa `lab_lotes_mp.qtd_atual` do lote usado por item + lança `estoque_movimentos` (tipo=saida, origem=om). **Explosão**: item diluído baixa a MP primária pura + excipiente (modelo TPCMP=R decifrado). `baixou_estoque` garante idempotência (não baixa 2x).

### 4.5 Rótulo (Fatia B)
Ao chegar em Rotulagem, renderiza o template com dizeres obrigatórios RDC 67 (paciente, prescritor, composição, lote/validade, posologia, farmácia+RT, advertências). Texto final gravado em lab_rotulos (auditável).

## 5. Aderência legal fecha aqui
- **Rastreabilidade fim-a-fim** (task #5): lab_ordem_itens.lote_mp_id → lab_ordens.contato_id → recall por lote possível.
- **Livro de receituário** (Fatia C): numeração sequencial das OMs.
- **Rótulo RDC 67** (Fatia B): dizeres obrigatórios + RT (empresa_config).
- **CQ**: lote aprovado (Fatia 2 estoque) + conferente na liberação da OM.

## 6. Pontos a confirmar (Carlos / RT)
- Gatilho da OM: aprovação do orçamento é automática (status muda → cria OM) ou manual (botão "Gerar OM")?
- Etapas do kanban: as 8 padrão servem ou a Magis usa outro fluxo?
- Baixa de estoque: na conclusão da OM ou na liberação do CQ?
- Validade da fórmula: regra Magis (floral 90d / demais 120d) — confirmar.
