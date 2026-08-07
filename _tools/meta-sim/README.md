# Mensageria multi-provider (Evolution + Meta Cloud) — operação

Entrega dos Blocos 1–3. **Nada quebra o Evolution**: todo o código novo é aditivo e só roda
no caminho da Meta. O provider é escolhido **por instância** (`crm_instancias.provider`),
então dá pra rodar **Evolution puro, Meta puro ou híbrido** ao mesmo tempo.

## 1. Configuração (não há `.env` no projeto → usamos WP options)

Segredos/config ficam em `wp_options` (nunca no git). IDs públicos ficam em `crm_instancias`.

| Onde | Chave | Descrição |
|---|---|---|
| `crm_instancias.provider` | `evolution` \| `meta_cloud` | provider daquela instância |
| `crm_instancias.meta_phone_number_id` | — | Phone Number ID da Meta |
| `crm_instancias.meta_waba_id` | — | WABA ID |
| `crm_instancias.meta_graph_version` | `v26.0` | versão da Graph API |
| wp_option | `tao_crm_meta_token` ou `tao_crm_meta_token_{id}` | Access Token (segredo) |
| wp_option | `tao_crm_meta_app_secret` | App Secret (valida HMAC do webhook) |
| wp_option | `tao_crm_meta_verify_token` | token de verificação do webhook |
| wp_option | `tao_crm_meta_api_base` | default `https://graph.facebook.com`; aponte ao **mock** nos testes |
| wp_option | `tao_crm_meta_forward_n8n` | `1` para encaminhar entrada ao agente N8N |

## 2. Antes de tudo: rodar a migration
`migration_messaging_meta_v1.sql` no Supabase (aditiva/reversível). Sem ela, o Meta não persiste
(o Evolution segue normal).

## 3. Rodar o simulador local (sem credenciais da Meta)

**Mock do endpoint de envio da Meta** (emula `POST /{versao}/{phone_number_id}/messages`):
```
SIM_WEBHOOK_URL=https://solucoesetao.com.br/wp-json/tao-crm/v1/meta-webhook \
SIM_APP_SECRET=devsecret SIM_PNID=123456 \
php -S 127.0.0.1:8089 _tools/meta-sim/mock-graph.php
```
No WP, aponte `tao_crm_meta_api_base = http://127.0.0.1:8089` e `tao_crm_meta_app_secret = devsecret`.
Ao enviar uma mensagem por um card `meta_cloud`, o mock responde com `wamid` fake e devolve
`sent → delivered → read` ao webhook (a mensagem marca entregue/lida sozinha).

## 4. Injetar payloads de teste no webhook

```
php _tools/meta-sim/inject.php payloads/msg_texto.json  "" devsecret
php _tools/meta-sim/inject.php payloads/status_delivered.json "" devsecret
```
(2º arg = URL do webhook; 3º = app_secret p/ assinar. Sem secret → modo dev, sem assinatura.)
Payloads inclusos: `msg_texto`, `msg_imagem`, `msg_interactive`, `status_delivered`, `status_read`, `status_failed`.
Para processarem por completo, uma `crm_instancias` precisa ter `provider=meta_cloud` e
`meta_phone_number_id=123456` (o PNID dos payloads).

## 5. Testes

```
php _tools/meta-sim/run-unit.php     # E.164 + regra da janela 24h (sem WP/DB)
```
Integração (com WP + migration): use o injetor acima e confira Inbox/`crm_mensagens`.

## 6. Alternar provider
Basta trocar `crm_instancias.provider` da instância (`evolution`↔`meta_cloud`). Cards herdam o
provider; o envio do Inbox roteia sozinho. Híbrido = ter as duas instâncias ativas.

## 7. Checklist quando as credenciais reais da Meta chegarem
- [ ] Preencher wp_options: `tao_crm_meta_token`, `tao_crm_meta_app_secret`, `tao_crm_meta_verify_token`
- [ ] Voltar `tao_crm_meta_api_base` para `https://graph.facebook.com`
- [ ] `crm_instancias`: setar `provider=meta_cloud`, `meta_phone_number_id`, `meta_waba_id`
- [ ] Cadastrar o webhook público no painel da Meta: `https://solucoesetao.com.br/wp-json/tao-crm/v1/meta-webhook`
- [ ] Assinar o campo **messages** na configuração de webhooks
- [ ] Teste ponta a ponta com o número de teste da Meta
- [ ] Cadastrar/submeter os templates (tabela `wa_templates`) e aguardar aprovação
