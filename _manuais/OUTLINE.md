

===== Manual_TAO_Neo_v14.docx =====
# Manual Completo - Plataforma TAO Neo
  ## ÍNDICE
  ## PARTE 1 — INFRAESTRUTURA
    ### 1. Visão Geral e Arquitetura
    ### 2. Serviços a Contratar
      #### 2.1 Cloudfy — N8N e Evolution API
      #### 2.2 Supabase — Banco de Dados
      #### 2.3 Provider de IA
      #### 2.4 WordPress Hosting
      #### 2.5 WhatsApp Business
    ### 3. Configurar Supabase
      #### 3.1 Criar as tabelas do Chatbot (SQL inicial)
      #### 3.2 Migration v4 — Endereço e WooCommerce
      #### 3.3 Migration v5 — Campanhas e campo Empresa
      #### 3.4 Migration v6 — TAO CRM ✦ NOVO
      #### 3.5 Desabilitar Row Level Security
    ### 4. Configurar N8N na Cloudfy
      #### 4.1 Primeiro acesso
      #### 4.2 Importar “Chatbot Generico v6”
      #### 4.3 Importar “CBPM - WooCommerce Sync”
      #### 4.4 Importar “CBPM Campanha Disparo”
      #### 4.5 Obter a API Key do N8N
    ### 5. Configurar Evolution API na Cloudfy
      #### 5.1 Verificar funcionamento
      #### 5.2 Configurar webhook por instância (Chatbot)
      #### 5.3 Configurar webhook para o TAO CRM ✦ NOVO
    ### 6. Configurar WordPress e Plugin
      #### 6.1 Instalar WordPress
      #### 6.2 Instalar o plugin chatbot-platform
      #### 6.3 Instalar o plugin tao-crm ✦ NOVO
      #### 6.4 Configuração inicial do chatbot-platform
      #### 6.5 URL de acesso ao painel
  ## PARTE 2 — OPERAÇÃO (Chatbot Platform)
    ### 7. Acessar o Painel
    ### 8. Perfis de Usuário e Gestão de Acessos
      #### 8.1 Três perfis de acesso
      #### 8.2 Criar acesso via portal /robos/usuarios/
      #### 8.3 Resetar senha
    ### 9. Cadastrar Categorias
    ### 10. Cadastrar Negócio
    ### 11. Campos Extras do Catálogo
    ### 12. Cadastrar Catálogo
    ### 13. Configurar Disponibilidade
    ### 14. Promoções e Avisos
    ### 15. Configurar Conectores
    ### 16. Ativar o Robô
      #### 16.1 Criar instância na Evolution API
      #### 16.2 Configurar webhook da instância
      #### 16.3 Verificar ativação
    ### 17. Dashboard — Métricas e Filtros
    ### 18. Campanhas WhatsApp
      #### 18.1 Criar uma campanha
      #### 18.2 Variáveis disponíveis
      #### 18.3 Upload de contatos via CSV
      #### 18.4 Status de campanha
    ### 19. Monitoramento
    ### 20. Diagnóstico e Solução de Problemas
    ### 21. Manutenção Periódica
      #### 21.1 Reconectar WhatsApp (QR Code expirado)
      #### 21.2 Limpeza do histórico
      #### 21.3 Monitorar quotas de IA
      #### 21.4 Backup do banco
  ## PARTE 3 — SUPORTE (Chatbot Platform)
    ### 22. Cenários Comuns de Suporte
    ### 23. Checklist Mensal de Manutenção
  ## PARTE 4 — TAO CRM ✦ NOVO
    ### 24. O Que É o TAO CRM
    ### 25. Configuração Inicial do CRM
      #### 25.1 Verificar instalação
      #### 25.2 Configurações do Workspace
      #### 25.3 Verificar webhook da Evolution
      #### 25.4 Executar migrations SQL
    ### 26. Pipelines, Estágios e Estágios Terminais
      #### 26.1 O que é um pipeline
      #### 26.2 Estágios do Funil de Vendas (11 ativos + 2 terminais)
      #### 26.3 Estágios terminais (tipo ganho/perdido)
      #### 26.4 Configurar estágios via UI
      #### 26.5 Dois pipelines em série: Funil de Vendas → Pós Vendas ✦ NOVO v13
    ### 27. Campos Parametrizáveis
      #### 27.1 Criar campos
      #### 27.2 Vincular campos a estágios
      #### 27.3 Usar campos em automações
    ### 28. Engine de Automações
      #### 28.1 O que são automações
      #### 28.2 Criar uma automação
      #### 28.3 Gatilhos disponíveis
      #### 28.4 Ações disponíveis
      #### 28.5 Processamento da fila
      #### 28.6 20 automações do pipeline Magis-TAO
      #### 28.7 Diagnóstico de automações
    ### 29. Operação Diária — Kanban
      #### 29.1 Acessar o Kanban
      #### 29.2 Card — informações exibidas
      #### 29.3 Mover cards (drag-and-drop)
      #### 29.4 Criar card manual
    ### 30. Operação Diária — Card (Detalhes, Chat e Fechamento)
      #### 30.1 Abrir o card
      #### 30.2 Enviar mensagem via chat
      #### 30.3 Polling de mensagens
      #### 30.4 Fechar Negócio (✅) — fluxo com dois pipelines ✦ ATUALIZADO v13
      #### 30.5 Cancelar Negócio (❌)
      #### 30.6 O que acontece depois de fechar/cancelar
      #### 30.7 Histórico de movimentações
      #### 30.8 💬 Ver Histórico — Acessar Conversa WhatsApp ✦ NOVO v13
    ### 31. Operação Diária — Inbox
      #### 31.1 Acessar o Inbox
      #### 31.2 Badge de notificação
      #### 31.3 Fluxo de atendimento
    ### 32. Migrations SQL do CRM ✦ ATUALIZADO
      #### Bloco 0 — crm_contatos (tabela de contatos — rodar na instalação inicial)
      #### Bloco 1 — Schema (rodar apenas uma vez)
      #### Bloco 2 — Estágios terminais (Magis-TAO)
      #### Bloco 3 — 20 automações (Magis-TAO)
      #### Bloco 4 — Verificação
      #### Bloco v1.7.0 — Multi-instância (crm_instancias.cliente_id) ✦ NOVO
      #### Bloco v1.6.0 — Billing, Dispatch Key, LGPD e Backup ✦ NOVO
      #### Bloco v1.5.0 — Comentários, Metas e Mensagens Agendadas ✦ NOVO
    ### 33. Diagnóstico — TAO CRM
    ### 34. Checklist CRM
  ## 35. Gestão de Contatos (crm_contatos)
    ### 35.1 O que é o cadastro de contatos
    ### 35.2 Como acessar o cadastro de um contato
    ### 35.3 Dados disponíveis no perfil do contato
    ### 35.4 Classificação de clientes
    ### 35.5 Perfil 360° — histórico unificado
    ### 35.6 SQL — Migration crm_contatos
  ## 36. Integração CRM-Chatbot — Contexto Inteligente ✦ NOVO
    ### 36.1 Como funciona
    ### 36.2 O que o chatbot recebe
    ### 36.3 Quando o chatbot NÃO recebe o contexto
    ### 36.4 O que aparece para o robô — exemplo real
    ### 36.5 Como configurar a classificação para funcionar
    ### 36.6 Diagnóstico — Chatbot não está personalizando o atendimento
    ### 38. Multi-instância WhatsApp (TAO Neo + CRM) ✦ NOVO
      #### 38.1 Conceito
      #### 38.2 Cadastrar instância pelo TAO Neo (sem CRM)
      #### 38.3 Cadastrar instância pelo TAO CRM (com workspace)
      #### 38.4 Como o N8N identifica o cliente
      #### 38.5 Como o CRM decide quais instâncias são suas
      #### 38.6 Múltiplas instâncias por cliente
      #### 38.7 Toggle (ativar/desativar instância)
      #### 38.8 Diagnóstico multi-instância
  ## Resumo — Fluxo Completo de Instalação
  ## 37. Metas por Atendente ✦ NOVO
    ### 37.1 O que são metas
    ### 37.2 Como configurar metas
    ### 37.3 Como o realizado é calculado
    ### 37.4 Funcionalidades do card (v1.5.0 — atualizado)
      #### Comentários internos
      #### Agendar mensagem
      #### Reabrir card
      #### Busca global no Kanban
  ## 38. TAO CRM v1.6.0 — Novas Funcionalidades ✦ NOVO
    ### 38.1 X-Tao-Key por workspace
    ### 38.2 Três níveis de acesso
    ### 38.3 Paginação do Kanban
    ### 38.4 Mobile responsivo
    ### 38.5 Billing com crm_planos
    ### 38.6 Backup semanal automático
    ### 38.7 LGPD — Aceite de termos no onboarding
    ### 38.8 Retry engine para Evolution API
    ### 38.9 Documentação de webhooks de saída
    ### 38.10 Deploy atualizado
  ## Glossário
  ## APÊNDICE — Guia de Operação Simplificado (para quem está começando)
    ### A. Rotina diária do operador de CRM (15-20 min por dia)
    ### B. Como cadastrar informações do cliente
    ### C. Como criar um card manual (sem mensagem do WhatsApp)
    ### D. Como verificar se o robô está funcionando
    ### E. Como criar e disparar uma campanha WhatsApp
    ### F. Perguntas frequentes (FAQ do operador)
    ### G. Checklist diário do operador (5 minutos)


===== Manual_TAO_CRM_Neo_v8.docx =====
  ## Sobre este Manual
# Capítulo 1  —  Acesso ao Sistema
  ## 1.1  Como fazer login
  ## 1.2  Visão geral do Portal
    ### Navegacao no Menu
# Capítulo 2  —  Administracao do Sistema
  ## 2.1  Gestao de Usuarios
    ### Perfis de Acesso
    ### Cadastrar Novo Usuario
  ## 2.2  Configuracoes Geral da Plataforma
  ## 2.3  Configuracoes do CRM
    ### Pipelines (Funis)
    ### Estagios (Etapas)
    ### Campos Extras (Campos Obrigatórios)
  ## 2.4  Contatos CRM
# Capítulo 3  —  TAO Neo — Chatbot e Dashboard
  ## 3.1  O que é o TAO Neo
  ## 3.2  Persona Configurada — TAO Neo (Magis-TAO)
    ### Identidade
    ### O que o TAO Neo responde
    ### Comportamentos especiais
  ## 3.3  Dashboard
  ## 3.4  Leads
  ## 3.5  Pedidos
  ## 3.6  Catalogo de Produtos e Controle de Estoque
  ## 3.7  Histórico de Conversas
# Capítulo 4  —  Kanban — Funil de Vendas
  ## 4.1  O que é o Kanban
  ## 4.2  Etapas do Funil de Vendas — MagisTAO
  ## 4.3  Automacoes Ativas no Funil de Vendas
  ## 4.4  Lendo um Card no Kanban
  ## 4.5  Filtros e Busca no Kanban
  ## 4.6  Navegando no Kanban
# Capítulo 5  —  Card de Atendimento
  ## 5.1  Abrindo um Card
  ## 5.2  Estrutura da Tela do Card
    ### Area Superior — Informacoes do Negócio
    ### Área do Meio — Campos do Negócio
    ### Area Inferior — Chat WhatsApp
  ## 5.3  Conversando com o Cliente
  ## 5.4  Preenchendo os Campos do Negócio
  ## 5.5  Movendo o Card de Etapa
  ## 5.6  Devolver ao Chatbot
# Capítulo 6  —  Fechar Negócio
  ## 6.1  Quando Fechar o Negócio
  ## 6.2  Fechar como GANHO (Venda Realizada)
  ## 6.3  Fechar como PERDIDO (Negócio Cancelado)
  ## 6.4  Após o Fechamento
    ### Se fechou como GANHO:
    ### Se fechou como PERDIDO:
# Capítulo 7  —  Inbox — Central de Mensagens
  ## 7.1  O que é a Inbox
  ## 7.2  Usando a Inbox no Dia a Dia
# Capítulo 8  —  Funil de Pos-Vendas
  ## 8.1  O que é o Pos-Vendas
  ## 8.2  Etapas do Funil de Pos-Vendas — MagisTAO
  ## 8.3  Trabalhando no Pos-Vendas
# Capítulo A  —  Apêndice — Regras e Comportamentos do Sistema
  ## A.1  Regras Automáticas do Chatbot (TAO Neo)
  ## A.2  Regras dos Funis de Vendas e Pós-Vendas
    ### Funil de Vendas
    ### Funil de Pos-Vendas
  ## A.3  Regras de Movimentacao de Cards
  ## A.4  Perguntas Frequentes
    ### O card sumiu do kanban. O que aconteceu?
    ### O sistema nao está enviando minha mensagem para o cliente.
    ### O cliente está reclamando que nao recebeu resposta.
    ### Fechei o negócio como Ganho mas o card nao aparece no Pos-Vendas.
    ### Preciso criar um card para um cliente que nao entrou em contato pelo WhatsApp.
    ### O filtro de período nao está mostrando os cards esperados.
# ATUALIZAÇÕES — VERSÃO 2.1  (Junho 2026 / plugin v1.8.0)
# Complemento — Campo Arquivo / Documento  ★ NOVO
  ## Campo Tipo Arquivo / Documento  ★ NOVO
    ### Como criar um campo do tipo Arquivo
    ### Como usar no atendimento
# Complemento — Seção 5.5: Itens do Negócio  ★ NOVO
  ## 5.5  Itens do Negócio  ★ NOVO
    ### Adicionar um item
    ### Tipos de desconto
    ### Total e Valor da Oportunidade
    ### Remover um item
# Complemento — Seção 4.7: Exportar Relatório  ★ NOVO
  ## 4.7  Exportar Relatório  ★ NOVO
    ### Como exportar
    ### Colunas do relatório
    ### Linhas de resumo
# Apêndice Técnico B — Migration v1.8.0  ★ NOVO
  ## B.1  Migration SQL v1.8.0  ★ NOVO
    ### Tabela crm_card_itens
    ### Bucket Supabase Storage


===== VISAO_TECNICA_TAO_NEO_v10.docx =====
# Visão Técnica - Plataforma TAO Neo
  ## ÍNDICE
  ## 1. Princípios de Design e Decisões Arquiteturais
    ### 1.1 Multi-tenant com isolamento por cliente_id
    ### 1.2 Stateless no N8N
    ### 1.3 Contexto re-montado a cada mensagem
    ### 1.4 Provider-agnostic para IA
    ### 1.5 Frontend autônomo (/robos/)
    ### 1.6 Roles hierárquicas (v2)
    ### 1.7 TAO CRM: WP REST substitui dispatcher N8N ✦ NOVO
  ## 2. Diagrama de Componentes e Fluxo Técnico
    ### 2.1 Arquitetura geral
    ### 2.2 Fluxo técnico de uma mensagem (chatbot)
    ### 2.3 Fluxo técnico de uma mensagem (TAO CRM) ✦ NOVO
  ## 3. Evolution API — Como Funciona
  ## 4. N8N — Workflows
    ### 4.1 Chatbot Generico v6 (ID: GzsCTfHpbQNZe9BA — 102 nós) ✦ ATUALIZADO
      #### Nós modificados em Mai 2026 (integração CRM)
    ### 4.2 WooCommerce Sync (ID: 7DxATdRgCwwwAeLl — 11 nós)
    ### 4.3 Campanha Disparo (ID: LXRzDVP6RnXGHPhR — 20 nós)
  ## 5. Supabase — Modelo de Dados Detalhado
    ### 5.1 Relacionamentos entre tabelas (chatbot)
    ### 5.2 Tabela clientes — campos completos
    ### 5.3 Tabelas do Módulo Campanhas
    ### 5.4 Demais tabelas
  ## 6. WordPress Plugin — chatbot-platform v2.3.0
    ### 6.1 Papel do plugin
    ### 6.2 Estrutura de arquivos
    ### 6.3 Roteamento frontend (/robos/)
    ### 6.4 Sistema de Roles v2
    ### 6.5 Funções utilitárias de contexto
    ### 6.6 Design System TAO Neo
    ### 6.7 Login Customizado (/robos/login/)
  ## 7. Dashboard Analítico
  ## 8. Montagem do Contexto de IA
  ## 9. Segurança e Isolamento Multi-tenant
  ## 10. Escalabilidade e Limites
  ## 11. Módulo TAO CRM ✦ ATUALIZADO
    ### 11.1 Visão Geral e Arquitetura
    ### 11.2 Plugin tao-crm — Estrutura de Arquivos ✦ ATUALIZADO
    ### 11.3 REST Endpoint /wp-json/tao-crm/v1/dispatch ✦ ATUALIZADO
    ### 11.4 Supabase — Modelo de Dados CRM ✦ ATUALIZADO
      #### Tabela crm_contatos ✦ NOVO
      #### Tabelas e schema
      #### Relacionamentos CRM
    ### 11.5 Engine de Automações
      #### Gatilhos (tipo)
      #### Ações (acao)
      #### Variáveis de template (campo mensagem)
      #### Funções principais
      #### Ciclo de vida das automações ao mover card
      #### 20 automações do pipeline Magis-TAO
    ### 11.6 Sistema de Estágios Terminais e Fechamento de Cards
      #### Conceito
      #### Comportamento do campo fechado
      #### Interface do card.php — barra de ações
      #### Histórico de movimentações no card
    ### 11.7 Campos Parametrizáveis (crm_campos)
    ### 11.8 Deployment e OPcache
    ### 11.9 crm_contatos — Tabela de Contatos Centralizada ✦ NOVO
      #### Propósito
      #### tao_crm_upsert_contato()
      #### Perfil 360° (tao_crm_contato_perfil)
      #### Vinculação leads/pedidos
    ### 11.10 Integração TAO CRM → N8N (Enriquecimento de Contexto IA) ✦ NOVO
      #### Visão geral
      #### Fluxo de dados
      #### Impacto no comportamento do chatbot
      #### Campos do objeto _crm_contato
    ### 11.11 Deploy Alternativo via WP Admin Plugin Editor ✦ NOVO
      #### Fluxo de autenticação
    ### 11.12 v1.5.0 — Novas Funcionalidades ✦ NOVO
      #### Comentários Internos (crm_comentarios)
      #### Busca Global AJAX (kanban.php)
      #### Reabrir Card (gestor)
      #### Metas por Atendente (crm_metas)
      #### Agendamento de Mensagens (crm_msgs_agendadas)
    ### 11.13 v1.6.0 — Novas Funcionalidades ✦ NOVO
      #### Autenticação X-Tao-Key por Workspace
      #### Três Níveis de Acesso via WP Filter
      #### Paginação do Kanban (20 cards + “Ver mais”)
      #### Kanban Mobile Responsivo
      #### Billing — crm_planos e Enforcement
      #### Backup Semanal via Supabase REST
      #### CSAT — Pesquisa de Satisfação
      #### Retry Engine — tao_crm_evolution_send_with_retry()
      #### HMAC em Webhooks de Saída
      #### Aceite LGPD no Onboarding
      #### Devolver ao Chatbot (Gestores)
      #### Ações em Massa (Bulk)
      #### Horário de Atendimento
      #### SLA por Estágio
    ### 11.14 Multi-instância WhatsApp (TAO Neo + CRM) ✦ NOVO
      #### Arquitetura
      #### Tabela crm_instancias (após migration v1.7.0)
      #### RPC cbpm_find_client_by_instance (plpgsql)
      #### Helper $cbpm_sync_iw (chatbot-platform/clientes.php)
      #### Fluxo: Instância TAO Neo-only (sem workspace CRM)
      #### Fluxo: Instância TAO CRM (com workspace)
      #### CRM Dispatch — Skip de Instâncias TAO Neo-only
      #### tao_crm_ajax_save_instancia — Preenchimento automático de cliente_id
      #### Relacionamentos atualizados
    ### 11.15 Dois pipelines em série e endpoint lead-to-card ✦ NOVO v9
      #### Arquitetura multi-pipeline
      #### Deduplicação escopada por pipeline_id no lead-to-card
      #### Lógica de fechar_card com dois pipelines
      #### Estágios terminais do Pós Vendas (Magis-TAO)
    ### 11.16 chatbot-platform ajax.php — integração CRM sem loopback ✦ NOVO v9
      #### Problema: loopback HTTP causa PHP Fatal Error
      #### Solução: rest_do_request() + set_body()
      #### Geração da URL do card no portal /robos/
      #### Botão “💬 Ver Histórico” em card.php
      #### Barra de Filtros Simplificada (kanban.php)
      #### Colunas Terminais Sempre Visíveis
      #### aplicarFiltros() — Disparo Apenas no Clique
      #### Filtro de Atendentes por Negócio (cbpm_cliente_id)
      #### Setas de Scroll do Board (position:absolute + hover CSS)
      #### Drag-and-Drop — Compatibilidade Firefox
  ## Resumo de Decisões Técnicas ✦ ATUALIZADO