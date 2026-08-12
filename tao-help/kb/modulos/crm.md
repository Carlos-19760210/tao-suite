# CRM — Atendimento (Kanban + WhatsApp)

# Apresentação

O TAO CRM organiza todo o atendimento e as vendas da sua empresa em um funil visual (Kanban), com o chat de WhatsApp integrado. Cada cliente vira um card, e é nele que a equipe conversa, registra o combinado, acompanha a negociação e fecha a venda — sem trocar de tela.

Este manual descreve, tela a tela e campo a campo, como operar o CRM: o Kanban, o card do cliente, os contatos, as configurações do funil (pipelines, estágios e campos), as automações, as mensagens e os indicadores.

### Convenções

• Campos marcados com * são obrigatórios.

• Onde houver "Regra:", trata-se de um comportamento automático ou validação do sistema.

*• O contato (cliente) é ÚNICO em toda a solução — o mesmo do card, do Agente de WhatsApp e das Campanhas.*

# Primeiros passos — acesso e navegação

O TAO CRM é usado pelo navegador (Chrome, Edge ou Firefox), no computador ou no celular — não há nada para instalar.

### Como entrar
1. Abra o endereço do portal (fornecido pelo administrador) e faça login com e-mail e senha.
1. A tela inicial mostra o menu CRM (Painel e Kanban) e, para gestores, o menu Configurações.

**Regra: **O acesso é por perfil: o gestor vê todos os cards e as Configurações; o atendente vê apenas os cards sob sua responsabilidade.

### Como navegar

O menu CRM tem dois itens de operação — Painel (indicadores) e Kanban (o funil) — e, para gestores, Configurações (funil, campos, automações, equipe, integração).

**Regra: **Em qualquer lista há uma busca no topo; nos campos que buscam pessoas, navegue pelo teclado (↑↓/Enter).

# Como funciona — o funil de atendimento

Entenda o caminho de um atendimento antes de entrar nas telas. Cada etapa é detalhada adiante.
1. CHEGA O CONTATO. O cliente manda mensagem no WhatsApp; o sistema cria (ou reabre) um card no funil, já com o contato identificado pelo número.
1. ATENDIMENTO. A equipe conversa pelo card, registra notas internas e vai movendo o card pelas fases (ex.: Aguardando Atendimento → Em Conversa → Orçamento Enviado → Negociação).
1. FECHAMENTO. O card é fechado como GANHO (venda) ou PERDIDO (com motivo). Ganhos podem cruzar automaticamente para um funil de Pós-vendas.
1. PÓS E RETENÇÃO. Automações e campanhas cuidam do follow-up, do NPS e da reativação de clientes.

**Regra: **Em uma linha: Contato → Atendimento (card + WhatsApp) → Ganho/Perdido → Pós-venda. Tudo acontece no card.

# O card do cliente — operando pelo CRM

O card é a central do atendimento: cada cliente/negócio tem um card no Kanban, e é nele que acontece TODA a operação — a conversa por WhatsApp, o registro do que foi combinado, o orçamento, o fechamento da venda e o acompanhamento pós-venda. Quem atende passa a maior parte do tempo aqui, sem precisar abrir outras telas.

![](img/crm/01.png)

*Figura — A ficha do card — conversa, dados do cliente e o negócio*

### Onde fica e como abrir

O Kanban (menu CRM) mostra os cards em colunas — uma para cada fase do funil (ex.: Aguardando Atendimento → Em Conversa → Orçamento Enviado …; e, no Pós-vendas: Aguardando Produção → Em Produção → Pronto para Entrega → Entregue → NPS).
1. Clique em um card para abrir a ficha completa.
1. Use a busca no topo do Kanban para achar um card por nome, WhatsApp ou número da requisição.
1. Arraste o card entre as colunas para mudar a fase — ou use os botões de avançar/fechar dentro da própria ficha.

### A conversa (WhatsApp)

A ficha traz o chat com o cliente, como no WhatsApp: as mensagens entram e saem por ali, e tudo fica registrado.
1. Digite no campo de mensagem e clique em "Enviar" para responder o cliente.
1. Anexe imagens/arquivos pelo clipe.
1. Nota interna: alterne para o modo nota para escrever um recado que SÓ a equipe vê (não vai ao cliente).
1. Agendar mensagem (⏰): programe uma mensagem para uma data/hora futura (ex.: lembrete de retorno).

### Organização do atendimento

A ficha reúne os recursos para conduzir e não perder o atendimento:

| Item | Obrig. | Descrição e regra |
| --- | --- | --- |
| Responsável | — | Quem faz qualquer alteração no card vira o responsável por ele. Só a equipe do negócio aparece na lista de responsáveis. |
| Tags (etiquetas) | — | Marcadores coloridos para classificar o card (campanha, prioridade, origem…). |
| Lembretes 🔔 | — | Agende um follow-up com data/hora — o sistema notifica quando vence. |
| Comentários internos | — | Notas da equipe sobre o atendimento; o cliente não vê. |
| Histórico | — | Linha do tempo com tudo que aconteceu no card: mudanças de fase, orçamentos, responsável, notas. |

### Campos obrigatórios (o checklist da fase)

Em algumas fases (especialmente no Pós-vendas), o card exige campos obrigatórios para avançar — é o checklist de conferência do atendimento.
1. Uma faixa âmbar no card lista os campos que faltam na fase atual; preencha-os na própria ficha (eles salvam sozinhos). Ao responder cada um, ele fica verde.
1. O card NÃO avança de fase, nem fecha como ganho, enquanto houver campo obrigatório vazio.

### Fechar o negócio
1. Ganho: clique em fechar como Ganho. O sistema pede para CONFIRMAR o Valor Final e preencher o checklist; então o card cruza para o funil de Pós-vendas.
1. Perdido: ao cancelar, é obrigatório informar o MOTIVO (lista pré-definida; "Falta de Insumo" pede qual insumo faltou).
1. Reabrir: um card fechado pode ser reaberto pelo gestor, voltando à fase de origem.

# O Kanban

Menu CRM → Kanban. A visão em colunas de todo o funil: cada coluna é uma fase; cada cartão, um atendimento. É a tela onde a equipe trabalha o dia inteiro.

![](img/crm/02.png)

*Figura — O Kanban — funil em colunas, filtros e busca (dados dos clientes ocultados)*

### Trabalhar os cards
1. Clique em um card para abrir a ficha; ARRASTE-o entre as colunas para mudar a fase.
1. Ao mover para uma coluna de Ganho/Perdido (ou pelos botões da ficha), o sistema pede a confirmação do valor (ganho) ou o motivo (perdido).
1. Use "+ Novo Card" para abrir um atendimento manualmente; "Inbox" mostra as conversas com mensagens não lidas.

### Buscar e filtrar
1. A busca do topo localiza por nome, WhatsApp ou número da requisição.
1. Os filtros (Atendente, Fase, Status, Tag) focam a visão; "Mostrar colunas encerradas" exibe os ganhos/perdidos.
1. O intervalo de atualização automática é configurável (10 s a desligado). Alterne entre os funis pelas abas (Funil de Vendas / Pós-vendas).

**Regra: **Cada alteração num card define quem o alterou como responsável. Um ponto vermelho no card indica mensagem não lida.

# Contatos

Menu Cadastros → Clientes/Contatos. A base ÚNICA de pessoas — o mesmo contato do card, do Agente e das Campanhas. Não crie cadastros paralelos.

![](img/crm/03.png)

*Figura — Lista de contatos (WhatsApp ocultado)*
1. Busque por nome, WhatsApp ou e-mail; filtre por workspace; "+ Novo Contato" para cadastrar.

| Item | Obrig. | Descrição e regra |
| --- | --- | --- |
| Nome | Sim | Nome do contato. |
| WhatsApp | Sim | CHAVE ÚNICA do cadastro — o sistema não duplica pessoas com o mesmo número. |
| E-mail / Cidade | — | Dados de contato e localização. |
| Classificação | — | Marcadores do relacionamento (ex.: cliente, lead). |
| Endereço completo | — | Usado por módulos como Entregas. |

**Regra: **Ao salvar, os dados valem para o CRM, o Agente e as Campanhas — é o mesmo contato.

# Pipelines e Estágios

Configurações → Pipelines. Você define os funis (pipelines) e as fases (estágios) de cada um. É a espinha dorsal do CRM.

![](img/crm/04.png)

*Figura — Configuração de pipelines e estágios*
1. Crie um pipeline pelo campo "Novo pipeline" (nome + ordem + Criar). O 1º pipeline costuma ser o Funil de Vendas; o 2º, o Pós-vendas.
1. No pipeline selecionado, adicione/renomeie/reordene os estágios e escolha a COR de cada um.
1. Marque "Usar pipeline selecionado como Pós-vendas" para que os cards GANHOS cruzem automaticamente para ele.

### Tipo do estágio

O TIPO de cada estágio controla o comportamento do card ao chegar nele:

| Item | Obrig. | Descrição e regra |
| --- | --- | --- |
| Normal | — | Fase comum de andamento (ex.: Em Conversa, Negociação). |
| Ganho | — | Fecha o card como venda. Ao entrar, dispara os módulos ligados (venda, entrega, etc.). |
| Perdido | — | Fecha o card como perdido — exige motivo. |
| Handoff | — | Fase de entrada do atendimento humano (onde o card chega quando o cliente pede uma pessoa). |

**Regra: **Cards ganhos no Funil de Vendas cruzam para a 1ª fase do pipeline marcado como Pós-vendas. Os cards apontam para o ID da fase — renomear/reordenar não perde cards.

# Campos personalizados (checklist por fase)

Configurações → Campos. Crie campos próprios e associe a cada fase — é como você monta o checklist de conferência que o card exige.

![](img/crm/05.png)

*Figura — Campos personalizados e associação por estágio*
1. Crie um campo definindo o Nome, o Tipo (texto, número, Sim/Não, lista de opções) e, se for lista, as opções.
1. Associe o campo a um ou mais estágios. Para cada associação, marque "na entrada" (aparece ao chegar na fase) e "obrigatório" (exige preenchimento).

**Regra: **É esse checklist que aparece no card (faixa âmbar): o card não avança de fase — nem fecha como ganho — enquanto houver campo obrigatório vazio na fase atual.

# Automações

Configurações → Automações. Regras que agem sozinhas sobre os cards, sem intervenção da equipe.

![](img/crm/06.png)

*Figura — Cadastro de automações (gatilho → condição → ação)*

Cada regra é: um GATILHO + uma condição (fase/tempo) + uma AÇÃO. Ative para valer.

### Gatilhos

| Item | Obrig. | Descrição e regra |
| --- | --- | --- |
| Entrar na fase | — | Dispara quando o card chega a um estágio. |
| Tempo na fase | — | Dispara após X minutos/horas parado na fase (ex.: 72 h). |
| Recebeu mensagem | — | Dispara quando o cliente responde. |
| Enviou mensagem | — | Dispara quando o atendente responde pelo CRM. |
| Sem resposta | — | Dispara após um período sem resposta do cliente. |

### Ações

| Item | Obrig. | Descrição e regra |
| --- | --- | --- |
| Enviar mensagem | — | Manda uma mensagem de WhatsApp (usa um template/texto). |
| Mover de fase | — | Move o card para outro estágio. |
| Atribuir responsável | — | Define/rodízio (round-robin) do responsável. |
| Notificar e-mail | — | Avisa a equipe por e-mail. |
| Fechar como perdido | — | Fecha o card com um motivo padrão. |

**Regra: **Exemplo clássico: gatilho "Tempo na fase = 72 h" na coluna "Aguarda Resposta" → ação "Fechar como perdido" com o motivo "Não responde os contatos".

# Tags, Lembretes e Comentários

Recursos do card para organizar o atendimento e não perder o timing (também citados no capítulo do card). As Tags são cadastradas em Configurações → Tags.

| Item | Obrig. | Descrição e regra |
| --- | --- | --- |
| Tags | — | Etiquetas coloridas por workspace, para classificar e filtrar cards no Kanban. |
| Lembretes | — | Follow-up com data/hora e notificação por e-mail quando vence. |
| Comentários internos | — | Notas da equipe no card; o cliente não vê. |

# Mensagens, Templates e Agendamento

A conversa do card usa o WhatsApp integrado (via a instância configurada na Integração). Para agilizar, use templates e agendamento.

![](img/crm/07.png)

*Figura — Templates de mensagem*
1. Templates (Configurações → Templates): cadastre mensagens prontas para reutilizar no atendimento e nas automações.
1. Agendamento: no card, programe uma mensagem para uma data/hora futura (o sistema envia sozinho).

**Regra: **O envio respeita o horário de atendimento configurado (Configurações → Horário).

# Painel e Indicadores

Menu CRM → Painel. Os números do funil, com filtro de período (hoje, 7, 30, 90 dias).

![](img/crm/08.png)

*Figura — Painel de indicadores do CRM*

| Item | Obrig. | Descrição e regra |
| --- | --- | --- |
| Cards abertos / Novos leads | — | Volume no pipeline e entradas no período. |
| Taxa de conversão | — | Ganhos ÷ decididos no período. |
| Em aberto / Receita gerada | — | Valor das oportunidades ativas e receita dos ganhos. |
| Tempo até ganho / TMR | — | Tempo médio criação→ganho e tempo da 1ª resposta. |
| Perdas por motivo | — | Ranking dos motivos de perda (qtde e valor). |
| NPS / Renovações | — | Satisfação e eficiência da retenção (quando há dados no período). |

**Regra: **O painel também mostra o status das conexões de WhatsApp e o total de cards aguardando atendimento humano (handoff).

# Equipe e Permissões

Configurações → Equipe. Define quem é gestor e quem é atendente/vendedor no workspace.

![](img/crm/09.png)

*Figura — Equipe do workspace*

**Regra: **Gestor vê todos os cards do workspace e acessa as Configurações; o atendente vê apenas os cards sob sua responsabilidade. Os selects de responsável mostram só a equipe do negócio.

# Configurações e Integração

Configurações → Integração. Conexão do WhatsApp (instâncias), webhooks de saída, e a chave de recebimento de mensagens.

![](img/crm/10.png)

*Figura — Integração — instâncias de WhatsApp e webhooks*
1. Conecte a(s) instância(s) de WhatsApp (Evolution) — o Kanban recebe as mensagens por elas.
1. Configure os webhooks de saída (por evento) quando quiser integrar com sistemas externos.
1. Defina o horário de atendimento (Configurações → Horário) — automações e envios o respeitam.

**Regra: **Outras abas de Configurações: Workspaces (negócios), Planos, SLA, CSAT/NPS, Metas, LGPD e Logs.
