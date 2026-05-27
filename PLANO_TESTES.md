# TAO CRM v1.6.0 — Plano de Testes

> Cobertura: todas as funcionalidades da v1.6.0. Organizado por módulo.
> Prioridade: 🔴 crítico · 🟡 importante · 🟢 desejável

---

## 1. Autenticação e Acesso

### 1.1 X-Tao-Key global (🔴)
- [ ] `POST /dispatch` com chave global correta → aceita (200)
- [ ] `POST /dispatch` com chave global errada → rejeita (401)
- [ ] `POST /lead-to-card` com chave global correta → aceita
- [ ] `POST /lead-to-card` sem header → rejeita

### 1.2 X-Tao-Key por workspace (🔴)
- [ ] `POST /dispatch` com `dispatch_key` do workspace → aceita
- [ ] `POST /dispatch` com `dispatch_key` de outro workspace → rejeita
- [ ] `POST /lead-to-card` com `dispatch_key` correto → aceita
- [ ] Backward compat: chave global ainda funciona em instância que usava antes

### 1.3 Controle de acesso (3 níveis) (🔴)
- [ ] Admin → vê menu Configurações
- [ ] Gestor global → vê menu Configurações; vê todos os cards do workspace
- [ ] Gestor do workspace → vê Configurações; vê cards apenas do seu workspace
- [ ] Vendedor → NÃO vê menu Configurações; vê apenas próprios cards
- [ ] Usuário sem papel CRM → não acessa nenhuma tela do plugin

---

## 2. Kanban

### 2.1 Paginação (🟡)
- [ ] Coluna com ≤20 cards → todos visíveis, sem botão "Ver mais"
- [ ] Coluna com 21+ cards → apenas 20 visíveis; botão "Ver mais" presente
- [ ] Clicar "Ver mais" → restante aparece; botão some
- [ ] Após "Ver mais" → filtros (responsável, etiqueta) continuam funcionando corretamente nos cards expandidos
- [ ] Drag-and-drop funciona em cards que estavam ocultos e foram expandidos

### 2.2 Mobile (🟡)
- [ ] ≤900px: kanban tem scroll horizontal; colunas não quebram para baixo
- [ ] ≤600px: colunas com 88vw; input de busca ocupa largura total
- [ ] Scroll tátil entre colunas funciona em iOS Safari e Android Chrome
- [ ] Drag-and-drop funciona no mobile (touch)

### 2.3 Operações básicas (🔴)
- [ ] Criar card → aparece na coluna correta
- [ ] Drag-and-drop → card muda de fase; automações disparam
- [ ] Fechar card → sai do kanban (status='fechado')
- [ ] Filtro "Fechados" → exibe apenas cards fechados
- [ ] Busca global → dropdown com resultados em tempo real (debounce 350ms)

---

## 3. Card

### 3.1 Chat (🔴)
- [ ] Mensagem enviada aparece no chat imediatamente
- [ ] Mensagem recebida (polling) aparece sem recarregar a página
- [ ] Polling adaptativo: 4s ativo → 15s background → 30s inativo há 5 min
- [ ] Notificação sonora/browser ao receber mensagem

### 3.2 Tags (🟡)
- [ ] Adicionar tag ao card → aparece no card do kanban
- [ ] Remover tag → some do card
- [ ] Filtro por tag no kanban funciona corretamente

### 3.3 Lembrete (🟡)
- [ ] Criar lembrete → aparece na lista
- [ ] Cron notifica por e-mail no horário definido
- [ ] Marcar como concluído → some da lista ativa

### 3.4 Comentários internos (🟡)
- [ ] Adicionar comentário → aparece na seção
- [ ] Deletar comentário (apenas o autor ou admin)
- [ ] Comentário NÃO aparece no chat WhatsApp do contato

### 3.5 Agendar mensagem (🟡)
- [ ] Agendar para data futura → entra em `crm_msgs_agendadas`
- [ ] Cron a cada 60s envia no horário → mensagem aparece no chat
- [ ] Mensagem enviada não reaparece na próxima execução do cron

### 3.6 Reabrir card (🟡)
- [ ] Gestor/admin clica "Reabrir" → `fechado=false`, card volta ao kanban
- [ ] Vendedor NÃO vê botão "Reabrir"

### 3.7 Valor de oportunidade (🟢)
- [ ] Salvar valor R$ → persiste; atualiza total na coluna do kanban

---

## 4. Automações

### 4.1 Gatilhos (🔴)
- [ ] `entrar_fase` com delay=0 → executa imediatamente ao mover card
- [ ] `entrar_fase` com delay=5 → entra na fila; executa após 5 min
- [ ] `sair_fase` → executa imediatamente ao sair da fase
- [ ] `tempo_na_fase` → executa após X minutos; NÃO executa se card saiu da fase
- [ ] `recebeu_mensagem` → executa ao receber mensagem `in`
- [ ] `sem_resposta` → cron horário detecta inatividade e executa

### 4.2 Ações (🔴)
- [ ] `enviar_mensagem` → mensagem chega no WhatsApp do contato
- [ ] `enviar_mensagem` com variável `{nome}` → substituída pelo nome do contato
- [ ] `mover_fase` → card muda de fase; automações da nova fase disparam em cascata
- [ ] `atribuir_responsavel` → `responsavel_id` atualizado no card
- [ ] `notificar_email` → e-mail enviado ao responsável

### 4.3 Retry engine (🔴)
- [ ] Simular falha temporária da Evolution API → sistema retenta 2 vezes
- [ ] Após 3 falhas → registra em `tao_evolution_falhas`; não trava o sistema

### 4.4 Cancelamento de fila (🟡)
- [ ] Mover card para outra fase → fila da fase anterior é cancelada
- [ ] Fila pendente de `entrar_fase` não executa se card já mudou de fase

---

## 5. Billing e Planos

### 5.1 UI de planos (🟡)
- [ ] Aba Planos sem workspace selecionado → exibe aviso de seleção
- [ ] Aba Planos com workspace → exibe plano atual, limites, barras de uso
- [ ] Tabela comparativa mostra essencial / profissional / empresarial
- [ ] Admin salva novo plano → `crm_planos` é atualizado (upsert); notificação de sucesso

### 5.2 Enforcement — instâncias (🔴)
- [ ] Workspace com plano essencial (limite=1) com 1 instância → tentar criar segunda → erro com mensagem de upgrade
- [ ] Workspace com limite=0 (empresarial) → criar instância → aceita sem verificação
- [ ] Editar instância existente → nunca bloqueia (edit_id preenchido)

### 5.3 Enforcement — usuários (🔴)
- [ ] Salvar gestores_ws com total (gestores+vendedores) > limite → bloqueia; mostra erro
- [ ] Salvar vendedores com total > limite do workspace → salva mas exibe warning
- [ ] Empresarial (limite=0) → salva sem restrição

### 5.4 Trial (🟢)
- [ ] Criar plano com `trial_ate` no futuro → exibe "em trial até X" na UI
- [ ] `trial_ate` no passado → exibir expirado (sem enforcement automático ainda)

---

## 6. Backup

### 6.1 Geração manual (🟡)
- [ ] Clicar "Gerar agora" → spinner aparece → mensagem de sucesso com nome do arquivo e total de registros
- [ ] Arquivo `.json.gz` criado em `wp-content/uploads/tao-crm-backups/`
- [ ] `.htaccess` no diretório bloqueia acesso direto (retorna 403)
- [ ] Após geração → botão "Baixar último backup" aparece na página (recarregar)

### 6.2 Download (🟡)
- [ ] Clicar "Baixar último backup" → download do `.json.gz`
- [ ] Arquivo descomprimido → JSON válido com chave `tabelas` e `totais`
- [ ] Sem backup gerado → clicar download → erro "Nenhum backup disponível"

### 6.3 Cron semanal (🟢)
- [ ] WP Crontrol mostra `tao_crm_backup_semanal` agendado
- [ ] Após execução → `tao_crm_ultimo_backup` option atualizada
- [ ] Com 8+ backups anteriores → apenas os 7 mais recentes mantidos

---

## 7. LGPD — Onboarding

### 7.1 Aceite de termos (🟡)
- [ ] Novo workspace com `termos_aceitos_em = null` → step 5 exibe tela de termos
- [ ] Checkbox não marcado + submeter → não avança (validação HTML required)
- [ ] Checkbox marcado → PATCH em `crm_workspaces`; grava `termos_aceitos_em` e `termos_versao`
- [ ] Reabrir wizard com aceite já feito → step 5 exibe "Aceito em X" sem o formulário

---

## 8. Webhooks de Saída

### 8.1 Disparo (🟡)
- [ ] Criar card → webhook `card_criado` dispara para URL configurada
- [ ] Fechar card → webhook `card_fechado` dispara
- [ ] Receber mensagem → webhook `mensagem_recebida` dispara

### 8.2 HMAC (🟡)
- [ ] Header `X-Tao-Signature` presente em todos os webhooks
- [ ] Verificar assinatura com secret do workspace → válida
- [ ] Alterar payload → assinatura inválida (verificação detecta)

---

## 9. Docs de Webhooks (Página Admin)

### 9.1 Conteúdo (🟢)
- [ ] Página `tao-crm-docs-wh` acessível por admin e gestor
- [ ] Exibe payloads de exemplo para cada evento
- [ ] Exemplos de verificação HMAC em PHP e Node.js estão presentes
- [ ] Tabela de eventos lista: `card_criado`, `card_fechado`, `card_movido`, `mensagem_recebida`, `mensagem_enviada`

---

## 10. Dashboard e Relatórios

### 10.1 KPIs (🟡)
- [ ] Cards abertos, fechados hoje, valor total, SLA OK/vencido exibidos corretamente
- [ ] Gráficos Chart.js: funil, donut de conversão, linha de leads/semana renderizam

### 10.2 Metas (🟢)
- [ ] Definir meta para atendente → salva em `crm_metas`
- [ ] Comparativo realizado vs meta exibe percentual correto

---

## 11. Importação CSV

### 11.1 (🟢)
- [ ] Upload de CSV válido → cria contatos e cards; deduplica por WhatsApp
- [ ] CSV inválido → erro claro sem crash
- [ ] Relatório pós-importação mostra inseridos, duplicatas, erros

---

## 12. Integrações Externas

### 12.1 N8N Chatbot (🔴)
- [ ] Mensagem recebida → encaminhada ao N8N `/webhook/chatbot-generic` (non-blocking)
- [ ] Falha do N8N não interrompe o salvamento da mensagem no Supabase

### 12.2 N8N Campanha Disparo (🟡)
- [ ] Workflow usa `crm_instancias` para buscar credenciais por campanha
- [ ] Fallback para credenciais globais se `instancia_id` não encontrado

---

## Roteiro de Execução Sugerido

### Smoke test (pré-deploy, ~15 min)
1. Verificar que o plugin ativa sem erro PHP (log limpo)
2. Criar workspace → onboarding step 5 (LGPD)
3. Criar card → enviar mensagem → receber mensagem
4. Dispatch com chave global → aceita; com chave errada → 401
5. Criar instância com limite atingido → erro esperado

### Regressão completa (~2h)
Percorrer todos os itens 🔴 e 🟡 acima em ordem.

### Mobile (~30 min — dispositivo real)
- Kanban com ≥21 cards em 2 colunas
- Scroll tátil horizontal
- Clicar "Ver mais" → verificar filtros
- Enviar mensagem pelo card no mobile

---

## Ambiente de Teste

| Item | Valor |
|---|---|
| Produção | `https://solucoesetao.com.br/robos/?page=tao-crm` |
| Admin | `carlos.carv.almeida@gmail.com` |
| Supabase | SQL Editor em `app.supabase.com` |
| Evolution | `https://evo.solucoesetao.com.br:8082` |
| N8N | `https://crowingbettafish-n8n.cloudfy.live` |
