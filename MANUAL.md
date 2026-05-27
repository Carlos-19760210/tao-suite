# TAO CRM — Manual de Operação e Configuração

> **Versão 1.6.0 — Maio 2026**

---

## 1. Visão Geral

TAO CRM é um plugin WordPress que adiciona um pipeline Kanban com chat WhatsApp nativo ao painel admin. É multi-tenant (cada cliente tem seu próprio **workspace**) e se integra com:

- **Supabase** — banco de dados e API REST (PostgREST)
- **Evolution API** — envio e recebimento de mensagens WhatsApp
- **N8N** — chatbot (recebe forward das mensagens para resposta automática)

### Fluxo principal de mensagens

```
WhatsApp (cliente)
    │
    ▼
Evolution API (instância por workspace)
    │
    ▼  POST /wp-json/tao-crm/v1/dispatch
    │  Header: X-Tao-Key: <global-key ou dispatch_key do workspace>
    │
WordPress (tao_crm_rest_dispatch)
    ├── [non-blocking] → N8N /webhook/chatbot-generic  (chatbot responde)
    └── [sync] → Supabase crm_mensagens               (histórico salvo)
                       │
                       └── dispara automações (entrar_fase, recebeu_mensagem…)
```

---

## 2. Estrutura de Dados (Supabase)

| Tabela | Descrição |
|---|---|
| `crm_workspaces` | Um por cliente/negócio; config Evolution API; dispatch_key LGPD |
| `crm_planos` | Plano de billing por workspace (essencial/profissional/empresarial) |
| `crm_instancias` | Instâncias WhatsApp adicionais por workspace |
| `crm_pipelines` | Funil de vendas (um ou mais por workspace) |
| `crm_estagios` | Fases do funil (ordem, cor, nome, tipo: normal/ganho/perdido/handoff) |
| `crm_cards` | Leads/oportunidades; status, fechado, valor_oportunidade |
| `crm_mensagens` | Histórico completo de conversa (direcao: in/out) |
| `crm_cards_historico` | Log de movimentações entre fases |
| `crm_automacoes` | Regras de automação scoped por workspace/pipeline/estágio |
| `crm_automacoes_fila` | Fila de execução das automações com delay |
| `crm_tags` | Etiquetas coloridas por workspace |
| `crm_cards_tags` | M:M card ↔ tag |
| `crm_lembretes` | Follow-ups com notificação por e-mail |
| `crm_comentarios` | Notas internas da equipe (não enviadas ao cliente) |
| `crm_metas` | Metas mensais de cards/valor por atendente |
| `crm_msgs_agendadas` | Mensagens WhatsApp agendadas (data/hora futura) |
| `crm_msg_templates` | Templates de mensagem reutilizáveis |
| `crm_webhooks_saida` | Webhooks de saída por evento (com HMAC signing) |
| `crm_round_robin` | Configuração de atribuição round-robin por pipeline |
| `crm_contatos` | Contatos com endereço completo e classificação |
| `crm_campos_definicao` | Campos parametrizáveis criados pelo admin |
| `crm_campos_estagio` | Vínculo campo ↔ fase (visível, obrigatório, ordem) |
| `crm_cards_valores` | Valores preenchidos por card (upsert por card_id+campo_id) |

### Campos importantes do card (`crm_cards`)

| Campo | Significado |
|---|---|
| `contato_whatsapp` | Número E.164 sem `+` (ex: `5511999999999`) |
| `estagio_id` | Fase atual no funil |
| `responsavel_id` | ID WordPress do atendente responsável |
| `ultima_mensagem_em` | Timestamp da última mensagem do contato |
| `ultima_leitura_em` | Quando o atendente abriu o card |
| `atendimento_humano` | `true` quando transferido para humano |
| `status` | `'aberto'` ou `'fechado'` (sincronizado com `fechado`) |
| `valor_oportunidade` | Valor R$ da oportunidade |
| `instancia_id` | FK para `crm_instancias` (qual WhatsApp responde) |

### Planos disponíveis (`crm_planos.plano`)

| Plano | Usuários | Instâncias WA | Campanhas/mês |
|---|---|---|---|
| `essencial` | 3 | 1 | 0 |
| `profissional` | 10 | 3 | 5 |
| `empresarial` | ilimitado | ilimitado | ilimitado |

> **Enforcement ativo (v1.6.0):** criar nova instância ou adicionar gestor além do limite retorna erro com mensagem de upgrade.

---

## 3. Controle de Acesso

Três níveis:

| Nível | Como identificado | O que pode |
|---|---|---|
| **Admin WP** | `manage_options` | Acesso total: configurações, todos os workspaces |
| **Gestor** | `tao_crm_gestores_global` ou `tao_crm_gestores_ws_{id}` | Kanban, cards de todos os atendentes, menu Configurações |
| **Vendedor** | `tao_crm_vendedores_global` | Kanban, apenas seus próprios cards |

Os papéis são armazenados em WP Options e o filtro `user_has_cap` gera a capability `tao_crm_gestor` dinamicamente.

---

## 4. Módulo de Automações

### 4.1 Gatilhos

| Tipo | Quando dispara | Delay? |
|---|---|---|
| `entrar_fase` | Card chega na fase | Sim |
| `sair_fase` | Card sai da fase | Não — sempre imediato |
| `tempo_na_fase` | X minutos após entrar | Sim (obrigatório) |
| `recebeu_mensagem` | Mensagem `in` do contato chega | Não |
| `sem_resposta` | Cron horário verifica inatividade | Config: `horas_sem_resposta` |

### 4.2 Ações

| Ação | O que faz |
|---|---|
| `enviar_mensagem` | Envia texto via WhatsApp (Evolution); retenta até 3x com backoff |
| `mover_fase` | Move card para outra fase; dispara automações em cascata |
| `atribuir_responsavel` | Atribui atendente ao card |
| `notificar_email` | Envia e-mail ao responsável |
| `atribuir_responsavel_rr` | Atribui via round-robin |

### 4.3 Variáveis de template

| Variável | Substituído por |
|---|---|
| `{nome}` | contato_nome do card |
| `{telefone}` | contato_whatsapp |
| `{titulo}` | titulo do card |
| `{campo:chave}` | Valor de campo parametrizável |

### 4.4 Retry Engine (v1.6.0)

Todas as mensagens enviadas via Evolution usam `tao_crm_evolution_send_with_retry()`:
- Tentativa 1: imediata
- Tentativa 2: após 2 segundos
- Tentativa 3: após 4 segundos
- Falhas persistentes registradas na tabela `tao_evolution_falhas`

---

## 5. WP-Cron Jobs

| Hook | Frequência | Função |
|---|---|---|
| `tao_crm_processar_fila` | 60s | Executa automações agendadas (até 50/ciclo) |
| `tao_crm_check_lembretes` | 60s | Notifica lembretes vencidos por e-mail |
| `tao_crm_processar_agendadas` | 60s | Envia mensagens agendadas via Evolution |
| `tao_crm_check_instances` | 3600s | Monitora desconexão de instâncias Evolution |
| `tao_crm_check_sem_resposta` | 3600s | Automação sem-resposta |
| `tao_crm_limpeza_semanal` | semanal | Remove histórico >7d, fila >30d, agendadas enviadas >30d |
| `tao_crm_backup_semanal` | semanal | Gera backup gzip de todas as tabelas via Supabase REST |

**Cron real no VPS** (obrigatório para automações de tempo funcionarem):
```
*/1 * * * * curl -s https://solucoesetao.com.br/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```
Configurar em: cPanel → Cron Jobs (Hostinger).

---

## 6. Configuração Inicial — Passo a Passo

Acesse: **WP Admin → CRM → ⚙ Configurações**

### 6.1 Aba Workspaces

Configure por negócio:

| Campo | Valor |
|---|---|
| Nome | Nome do negócio (ex: "Magis-TAO") |
| Evolution URL | URL base da Evolution API |
| Evolution Key | API key da instância |
| Evolution Instância | Nome da instância (ex: `TAO-Neo-2`) |

Após criar o workspace, um `dispatch_key` único é gerado automaticamente.

### 6.2 Aba Pipelines & Estágios

- Selecione o workspace na barra lateral
- Crie pipelines e fases (nome, cor, tipo: normal/ganho/perdido/handoff, ordem)
- Aplique o template "Farmácia de Manipulação" para criar 8 fases padrão

### 6.3 Aba Equipe

- **Gestores globais**: acesso a todos os workspaces
- **Gestores do workspace**: acesso a um workspace específico
- **Vendedores**: veem apenas seus próprios cards

> Limite por plano é verificado automaticamente ao salvar.

### 6.4 Aba Planos

- Exibe plano atual, limites e uso
- Admin pode trocar o plano (upsert em `crm_planos`)
- Trial: defina `trial_ate` para data de expiração

### 6.5 Aba Integração

- URL N8N, Chave de Dispatch global
- Endpoint REST para configurar na Evolution API
- **Backup**: botão "Gerar agora" cria backup gzip; último backup disponível para download

### 6.6 Aba Docs Webhooks

Acesse **CRM → Docs Webhooks** para ver payloads completos, exemplos de verificação HMAC em PHP e Node.js, e lista de eventos disponíveis.

### 6.7 Onboarding (novo workspace)

Wizard de 5 passos:
1. Criar workspace
2. Configurar pipeline
3. Conectar Evolution API
4. Configurar N8N
5. **Aceite LGPD** (obrigatório — grava `termos_aceitos_em`)

---

## 7. Autenticação do Dispatch

O endpoint `/wp-json/tao-crm/v1/dispatch` aceita dois formatos de chave no header `X-Tao-Key`:

1. **Chave global** — configurada em Configurações → Integração (retrocompatível)
2. **Dispatch key do workspace** — chave individual gerada por workspace (`crm_workspaces.dispatch_key`)

A chave do workspace tem prioridade quando reconhecida. Ambas aceitam também via query param `?key=`.

O endpoint `/wp-json/tao-crm/v1/lead-to-card` segue a mesma lógica.

---

## 8. Webhooks de Saída (HMAC)

Eventos disponíveis: `card_criado`, `card_fechado`, `card_movido`, `mensagem_recebida`, `mensagem_enviada`

Cada webhook é assinado com `X-Tao-Signature: hmac-sha256=HASH` usando o secret do workspace.

**Verificação em PHP:**
```php
$payload = file_get_contents('php://input');
$expected = 'hmac-sha256=' . hash_hmac('sha256', $payload, $SECRET);
if (!hash_equals($expected, $_SERVER['HTTP_X_TAO_SIGNATURE'])) {
    http_response_code(401); exit;
}
```

---

## 9. Kanban — Uso

- Drag-and-drop entre colunas para mover card de fase
- **Paginação**: cada coluna exibe 20 cards; botão "Ver mais" carrega o restante
- **Filtros**: por fase, responsável, etiqueta, status (aberto/fechado)
- **Busca global** (input no topbar): busca por nome, WhatsApp ou título em tempo real
- **Mobile** (≤900px): scroll horizontal tátil entre colunas; colunas 88vw no mobile

---

## 10. Card — Recursos

- **Chat**: polling adaptativo (4s ativo → 15s background → 30s inativo há 5 min)
- **Tags**: adicionar/remover etiquetas coloridas
- **Lembrete**: criar follow-up com data/hora e notificação por e-mail
- **Comentários internos**: notas da equipe (não enviadas ao WhatsApp)
- **Valor de oportunidade**: campo R$ no card
- **Agendar mensagem**: escolher data/hora futura para envio
- **Transferir card**: mover para outro atendente com e-mail automático + histórico
- **Fechar / Reabrir card**: gestores podem reverter `fechado=true`

---

## 11. Backup de Dados

Backup automático semanal (cron `tao_crm_backup_semanal`) via Supabase REST API:
- 11 tabelas exportadas em JSON comprimido com gzip
- Mantém os 7 backups mais recentes
- Armazenado em `wp-content/uploads/tao-crm-backups/` (protegido por `.htaccess`)
- Acesso em: Configurações → Integração → Backup de Dados

Para gerar manualmente ou baixar: botões na aba Integração.

---

## 12. AJAX Actions Completo

**Cards:** `tao_crm_move_card`, `tao_crm_create_card`, `tao_crm_fechar_card`, `tao_crm_reabrir_card`, `tao_crm_transferir_card`, `tao_crm_bulk_action`

**Chat:** `tao_crm_send_message`, `tao_crm_send_attachment`, `tao_crm_poll_messages`, `tao_crm_mark_read`

**Campos:** `tao_crm_save_valor`, `tao_crm_save_campo_def`, `tao_crm_delete_campo`, `tao_crm_update_card_info`, `tao_crm_save_responsavel`

**Tags:** `tao_crm_get_tags`, `tao_crm_save_tag`, `tao_crm_delete_tag`, `tao_crm_get_card_tags`, `tao_crm_set_card_tags`

**Lembretes:** `tao_crm_get_lembretes`, `tao_crm_save_lembrete`, `tao_crm_complete_lembrete`, `tao_crm_delete_lembrete`

**Comentários:** `tao_crm_get_comentarios`, `tao_crm_save_comentario`, `tao_crm_delete_comentario`

**Automações:** `tao_crm_save_automacao`, `tao_crm_delete_automacao`

**Templates:** `tao_crm_get_templates`, `tao_crm_save_template`, `tao_crm_delete_template`

**Agendamento:** `tao_crm_save_msg_agendada`

**Metas:** `tao_crm_get_metas`, `tao_crm_save_meta`

**Webhooks saída:** `tao_crm_get_webhooks_saida`, `tao_crm_save_webhook_saida`, `tao_crm_delete_webhook_saida`

**Round-robin:** `tao_crm_get_round_robin`, `tao_crm_save_round_robin`

**Instâncias:** `tao_crm_save_instancia`, `tao_crm_delete_instancia`

**Billing:** `tao_crm_get_plano_info`, `tao_crm_admin_set_plano`

**Busca:** `tao_crm_search_global`

**Contatos:** `tao_crm_save_contato`, `tao_crm_contato_perfil`

**Backup:** `tao_crm_run_backup`

**Histórico:** `tao_crm_get_historico`

**Utilitários:** `tao_crm_inbox_count`, `tao_crm_kanban_check`, `tao_crm_get_csat_stats`, `tao_crm_save_nota`, `tao_crm_devolver_chatbot`, `tao_crm_import_csv`

---

## 13. REST Endpoints

| Rota | Método | Autenticação |
|---|---|---|
| `/wp-json/tao-crm/v1/dispatch` | POST | `X-Tao-Key` (global ou por workspace) |
| `/wp-json/tao-crm/v1/lead-to-card` | POST | `X-Tao-Key` (global ou por workspace) |

---

## 14. Deploy

### Script (método recomendado)

```bash
python C:\tmp\deploy_batch_v170.py
```

Deploya automaticamente: `settings.php`, `kanban.php`, `onboarding.php`, `crm-script.js`, `crm-style.css`, `tao-crm.php`, `functions.php`

### SCP direto (arquivos fora do batch)

```bash
scp -i C:\Users\carlo\.ssh\tao_crm_deploy -P 65002 <local> u178063243@solucoesetao.com.br:<server>
```

Servidor: `/home/u178063243/domains/solucoesetao.com.br/public_html/wp-content/plugins/tao-crm/`

### Limpar OPcache após deploy

```
https://solucoesetao.com.br/wp-content/plugins/tao-crm/clear-opcache.php
```
(Protegido por variável de ambiente `OPCACHE_SECRET`)

---

## 15. Diagnóstico e Monitoramento

### 15.1 Fila de automações (Supabase)

```sql
-- Pendentes
SELECT f.id, f.card_id, f.estagio_id, f.executar_em,
       a.nome AS automacao, a.tipo, a.acao
FROM crm_automacoes_fila f
JOIN crm_automacoes a ON a.id = f.automacao_id
WHERE f.executado_em IS NULL
ORDER BY f.executar_em ASC;

-- Recentes
SELECT f.card_id, a.nome, f.resultado, f.detalhe, f.executado_em
FROM crm_automacoes_fila f
JOIN crm_automacoes a ON a.id = f.automacao_id
WHERE f.executado_em IS NOT NULL
ORDER BY f.executado_em DESC LIMIT 50;
```

### 15.2 Falhas de envio Evolution

```sql
SELECT * FROM tao_evolution_falhas ORDER BY criado_em DESC LIMIT 20;
```

### 15.3 Verificar plano ativo

```sql
SELECT w.nome, p.plano, p.ativo, p.trial_ate,
       p.limite_usuarios, p.limite_instancias, p.limite_campanhas_mes
FROM crm_planos p JOIN crm_workspaces w ON w.id = p.workspace_id
WHERE p.ativo = true;
```

### 15.4 Problemas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| Automação não dispara | OPcache com versão antiga | Acessar `clear-opcache.php` + Ctrl+F5 |
| Mensagem enviada mesmo com resposta | Card não mudou de fase | Criar automação `recebeu_mensagem` → `mover_fase` |
| Webhook Evolution não chega | URL ou X-Tao-Key errados | Verificar dispatch_key do workspace ou chave global |
| Fila não processa | WP-Cron parado | Verificar cron real no cPanel; WP Crontrol |
| Limite de instâncias ao criar | Plano atingido | Upgrade via Configurações → Planos |
| Limite de usuários ao salvar equipe | Plano atingido | Upgrade ou remover usuários inativos |

### 15.5 SQL de manutenção

```sql
-- Cancelar fila de um card (emergência)
DELETE FROM crm_automacoes_fila
WHERE card_id = 'UUID' AND executado_em IS NULL;

-- Reativar automação desativada
UPDATE crm_automacoes SET ativo = true WHERE id = 'UUID';

-- Deduplicar estágios
SELECT pipeline_id, nome, COUNT(*) FROM crm_estagios
GROUP BY pipeline_id, nome HAVING COUNT(*) > 1;
```

---

## 16. Histórico de Versões

| Versão | Data | Destaques |
|---|---|---|
| v1.1.0 | 2026-01 | MVP: Kanban, chat WhatsApp, automações básicas |
| v1.2.0 | 2026-02 | Webhooks saída, templates, round-robin, CSV export, SLA |
| v1.3.0 | 2026-03 | Tags, lembretes, valor oportunidade, dashboard Chart.js |
| v1.4.0 | 2026-04 | Transfer card, CSV import, relatório CSV, aba Planos |
| v1.5.0 | 2026-05 | Comentários internos, busca global, reabrir card, metas, agendamento |
| v1.6.0 | 2026-05 | X-Tao-Key por workspace, mobile CSS, paginação kanban, 3 níveis acesso, retry engine, backup semanal, LGPD onboarding, billing UI com `crm_planos`, docs webhooks, enforcement de limites |
