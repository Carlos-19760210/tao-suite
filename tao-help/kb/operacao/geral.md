# Operação do TAO Neo (configuração + dia a dia)

# 1. Antes de começar

A configuração é feita por um Administrador. Faça uma vez, na ordem abaixo, e o sistema fica pronto para operar.

Pré-requisitos:
- Acesso ao portal como Administrador (e-mail e senha).
- Um número de WhatsApp dedicado ao atendimento.
- Os serviços de base (servidor de mensagens/automação) já provisionados pela TAO — você só conecta.
- As informações do negócio: identidade, catálogo/serviços, horário, formas de pagamento e, para manipulação, formas farmacêuticas e tabela de ativos.

Ordem recomendada (cada item é um capítulo a seguir):
1. Cadastrar o Negócio e as Categorias.
1. Criar os Usuários e definir perfis.
1. Conectar o WhatsApp.
1. Configurar o Agente (chatbot).
1. Configurar o CRM (workspace, integração, pipelines, campos, automações, horário…).
1. Configurar as Fórmulas (formas, ativos/sinônimos, IA).
1. Configurar o Caixa (operadoras, taxas, formas de pagamento).
1. Rodar o checklist de go-live.

# 2. Negócio e Categorias

Tudo no TAO Neo pertence a um Negócio. Comece cadastrando o seu.

## 2.1 Cadastrar o Negócio

Vá em Configurações > Geral > Negócios. Crie/edite o negócio e preencha a identidade que o Agente usará no atendimento: nome do negócio, persona (como o robô se apresenta e se comporta), apresentação inicial e as instruções de comportamento. É aqui que o chatbot ganha 'personalidade'.

![](img/operacao/01.png)

*Configurações > Geral > Negócios.*

## 2.2 Categorias

Em Configurações > Agente > Categorias, crie as categorias que organizam o catálogo (ex.: linhas de produto, tipos de serviço). Elas ajudam o Agente a apresentar as opções de forma organizada.

![](img/operacao/02.png)

*Configurações > Agente > Categorias.*

# 3. Usuários e perfis

Em Configurações > Geral > Usuários, cadastre quem vai acessar o sistema. Para cada pessoa: nome, e-mail (que é o login), senha inicial, perfil e negócio.

Perfis disponíveis:
- Operacional (atendente) — opera o dia a dia (Kanban, Inbox, cards, fórmulas); não acessa configurações.
- Gestor — tudo do operacional, mais configurações, usuários e o Caixa.
- Administrador — acesso completo.

O e-mail não pode ser alterado depois de criado (apenas a senha e o nome).

![](img/operacao/03.png)

*Configurações > Geral > Usuários.*

# 4. Conectar o WhatsApp

Em Configurações > Geral > Conectores, ficam os dados de conexão do WhatsApp (servidor de mensagens e instância). É o que liga o Agente e o CRM ao número de atendimento.
1. Confira ou informe os dados do conector (endereço do servidor e nome da instância) — normalmente já vêm pré-configurados pela TAO.
1. Conecte a instância (leitura do QR Code do WhatsApp, quando aplicável).
1. Verifique o indicador de status: a instância precisa aparecer como conectada.

Uma mesma empresa pode ter mais de uma instância (multi-instância) — cada número é identificado automaticamente e direcionado ao negócio correto.

![](img/operacao/04.png)

*Configurações > Geral > Conectores.*

# 5. Configurar o Agente (chatbot)

O Agente é o atendimento automático. Além da persona (definida no Negócio), configure o que ele conhece e quando responde.

## 5.1 Catálogo

Em Configurações > Agente > Catálogo, cadastre os produtos/serviços (nome, preço, descrição) que o Agente pode apresentar e usar para montar pedidos.

![](img/operacao/05.png)

*Configurações > Agente > Catálogo.*

## 5.2 Campos Extras

Em Campos Extras você define informações adicionais que o Agente coleta nos atendimentos/leads (ex.: dados específicos do seu negócio).

![](img/operacao/06.png)

*Configurações > Agente > Campos Extras.*

## 5.3 Disponibilidade

Defina os horários em que o Agente atende. Fora desses horários ele informa o cliente e registra a conversa para retorno.

![](img/operacao/07.png)

*Configurações > Agente > Disponibilidade.*

## 5.4 Promoções e Avisos

Cadastre mensagens de promoções/avisos que o Agente pode comunicar durante o atendimento.

![](img/operacao/08.png)

*Configurações > Agente > Promoções/Avisos.*

# 6. Configurar o CRM

O CRM é onde o atendimento humano acontece. A configuração tem seu próprio menu (Configurações > CRM > Configurações), com uma navegação lateral por seções: Global, Configuração, Operação e Sistema.

## 6.1 Workspaces

O workspace é o 'espaço de trabalho' do CRM, vinculado a um negócio. Em geral já existe um por negócio. Confirme que o seu negócio tem um workspace ativo.

![](img/operacao/09.png)

*CRM > Configurações > Workspaces.*

## 6.2 Integração (conectar o CRM ao WhatsApp)

Na seção Integração ficam a chave de segurança (X-Tao-Key) do workspace e a URL de webhook que recebe as mensagens. Essa configuração faz as mensagens do WhatsApp virarem cards automaticamente. Em geral já vem pronta; confira se a chave existe e se o webhook está apontado corretamente.

![](img/operacao/10.png)

*CRM > Configurações > Integração.*

## 6.3 Pipelines & Estágios

Os pipelines são os funis. O TAO Neo trabalha com dois em série: Funil de Vendas e Pós-Vendas. Os cards ganhos no Funil de Vendas vão automaticamente para o Pós-Vendas.
1. Selecione o pipeline (Funil de Vendas ou Pós-Vendas) nas abas no topo. Para criar outro, use 'Novo pipeline' (nome + ordem).
1. Marque qual pipeline é o de Pós-vendas usando a opção 'Usar pipeline selecionado como Pós-vendas'.
1. Na lista de ESTÁGIOS, defina as etapas do funil: nome, cor e TIPO.
1. O TIPO determina o comportamento da etapa: Normal (etapa comum), Handoff (marca que o atendimento aguarda humano) e os terminais Ganho e Perdido (fecham o card). É o estágio de Ganho que dispara o envio para o Pós-Vendas e a criação da venda no Caixa.

![](img/operacao/11.png)

*CRM > Configurações > Pipelines & Estágios.*

## 6.4 Campos

Em Campos você cria os campos do negócio que aparecem no card (texto, número, seleção, data, arquivo etc.). Pode marcar campos como obrigatórios e vinculá-los a estágios — assim o sistema exige o preenchimento antes de avançar/fechar.

![](img/operacao/12.png)

*CRM > Configurações > Campos.*

## 6.5 Automações

As automações fazem o sistema agir sozinho. Cada automação tem um gatilho e uma ação.
- Gatilhos: tempo numa fase (card parado), cliente sem resposta, entre outros.
- Ações: enviar mensagem (com modelo de texto), mover de fase, atribuir responsável, aplicar tag.

Use-as para padronizar o atendimento (ex.: cobrar retorno após X horas, mover automaticamente, avisar o time).

![](img/operacao/13.png)

*CRM > Configurações > Automações.*

## 6.6 Equipe

Defina quem são os atendentes do workspace e como os cards são distribuídos entre eles.

![](img/operacao/14.png)

*CRM > Configurações > Equipe.*

## 6.7 Horário de atendimento

Configure os dias e horários de funcionamento (por dia da semana, abertura e fechamento). Isso é importante por dois motivos: o sistema responde/aguarda conforme o expediente e os indicadores de tempo dos painéis (TMR e TMA) passam a contar apenas as horas dentro do expediente — não inflando com noites e fins de semana.

![](img/operacao/15.png)

*CRM > Configurações > Horário de atendimento.*

## 6.8 SLA, CSAT, Metas, Tags, Templates e Webhooks

Complete a configuração conforme a necessidade:
- SLA — prazos por estágio (alerta quando um card passa do tempo).
- CSAT — pesquisa de satisfação ao final do atendimento.
- Metas — metas por atendente, acompanhadas no painel.
- Tags — etiquetas para classificar os cards.
- Templates — respostas prontas que o atendente insere no chat do card.
- Webhooks — notificações para sistemas externos quando cards são criados, movidos ou fechados.

![](img/operacao/16.png)

*CRM > Configurações > Tags (exemplo de seção de configuração).*

# 7. Configurar as Fórmulas

Para farmácias de manipulação, configure o módulo Fórmulas antes de cotar.

## 7.1 Formas Farmacêuticas

Em Configurações > Fórmulas > Formas Farmacêuticas, cadastre as formas (cápsula, creme, solução etc.) com seus parâmetros (volume, cápsulas, custo fixo, margem, valor mínimo).

![](img/operacao/17.png)

*Configurações > Fórmulas > Formas Farmacêuticas.*

## 7.2 Ativos e Sinônimos

Em Fórmulas > Ativos ficam as matérias-primas e embalagens (em geral sincronizadas do sistema de origem). Cadastre os SINÔNIMOS de cada ativo: são os nomes alternativos (comercial, abreviação, grafias) que fazem o sistema reconhecer o ativo mesmo quando a receita usa outro nome. É possível gerar sinônimos em massa com IA. Quanto melhor essa base, mais a leitura automática de receitas acerta.

![](img/operacao/18.png)

*Configurações > Fórmulas > Ativos e sinônimos.*

## 7.3 Configurações de Fórmulas (margem e IA)

Defina a margem padrão e as chaves de inteligência artificial. As chaves de IA são o que habilita a leitura de receitas por foto/PDF — o grande diferencial do módulo (ver Parte B, capítulo de Fórmulas).

![](img/operacao/19.png)

*Configurações > Fórmulas > Configurações (margem e chaves de IA).*

# 8. Configurar o Caixa

O Caixa é operado por Administradores e Gestores. Configure nesta ordem:

## 8.1 Operadoras de Cartão

Cadastre as operadoras (adquirentes) com a taxa de antecipação, em Configurações > Caixa > Operadoras de Cartão.

![](img/operacao/20.png)

*Configurações > Caixa > Operadoras de Cartão.*

## 8.2 Formas de Pagamento

Cadastre as formas (dinheiro, PIX, débito, crédito, link, boleto) com o tipo e o canal. Marque quais entram no fechamento de dinheiro da gaveta.

![](img/operacao/21.png)

*Configurações > Caixa > Formas de Pagamento.*

## 8.3 Taxas (MDR)

Para as formas de cartão, cadastre a taxa e o prazo de recebimento por faixa de parcelas (ex.: 1x, 2–3x, 4–6x), ancorados na forma. É isso que calcula o valor líquido e a data prevista de cada recebimento.

![](img/operacao/22.png)

*Configurações > Caixa > Taxas (MDR).*

# 9. Checklist de go-live

Antes de liberar para o time, confirme:
1. Negócio cadastrado com persona/apresentação e categorias.
1. Usuários criados com os perfis corretos.
1. WhatsApp conectado (instância aparece como conectada).
1. Agente com catálogo, disponibilidade e campos extras.
1. CRM: workspace ativo, integração/webhook ok, pipelines (Funil + Pós-vendas) com estágios e os terminais Ganho/Perdido definidos, campos obrigatórios, automações e horário de atendimento.
1. Fórmulas: formas cadastradas, base de ativos/sinônimos e chaves de IA (se for usar leitura de receita).
1. Caixa: operadoras, formas de pagamento e taxas (MDR).
1. LGPD: aviso de privacidade ativo no primeiro contato do Agente e base legal/consentimento para o tratamento dos dados registrados (conversas, cadastro, histórico). Defina também o responsável pelas solicitações de titular (acesso/exclusão) — o direito ao esquecimento é atendido pela Anonimização no cadastro do cliente.
1. Backup e retenção: confirme que a rotina de backup dos dados está ativa e saiba a janela de retenção. Para manipulação, a rastreabilidade das OMs é preservada mesmo após anonimizar o paciente.
1. Contingência: tenha o plano para quando o WhatsApp cair (reconectar a instância nos Conectores) e um canal alternativo de atendimento; combine com a TAO o contato de suporte e o tempo de resposta.
1. Teste de ponta a ponta: mande uma mensagem de teste no WhatsApp e acompanhe o card nascer no CRM; faça um orçamento; feche como Ganho; receba no Caixa.

Observação sobre módulos: o sistema é modular e as telas visíveis seguem o contrato de cada cliente — um módulo não contratado não aparece no menu. Este manual descreve a suíte completa.

# PARTE B — OPERAÇÃO (uso no dia a dia)

# 10. Visão geral da operação

Com o sistema configurado, a operação segue a jornada do cliente. As próximas seções acompanham exatamente esta ordem:
1. O cliente chama no WhatsApp. O Agente atende, responde e qualifica.
1. O atendimento vira um card no CRM (Funil de Vendas). Quando precisa de uma pessoa, fica aguardando atendimento humano.
1. O atendente assume o card, conversa pelo chat e, quando é o caso, cota uma fórmula ali dentro — pela foto/PDF da receita (IA), importando do sistema de origem ou montando manualmente.
1. O orçamento fica preso ao card e vira o valor do negócio. O farmacêutico revisa e aprova.
1. O atendente fecha como Ganho: o card vai para o Pós-Vendas e nasce a venda no Caixa.
1. O Caixa recebe o pagamento e, depois, concilia os cartões.
1. O Pós-Vendas acompanha produção e entrega até o cliente receber.

# 11. Acesso e navegação

## 11.1 Entrar no sistema

Acesse o portal, informe e-mail e senha (o e-mail é o login). Use 'Esqueci minha senha' se precisar.

![](img/operacao/23.png)

*Tela de login do TAO Neo.*

## 11.2 O menu lateral

O menu é organizado por módulo, cada um aparecendo uma vez: Visão Geral, Agente, CRM, Fórmulas, Caixa e Configurações. Em celulares, toque no ícone de menu (≡) para abri-lo.

![](img/operacao/24.png)

*Portal do TAO Neo — menu lateral por módulo.*

# 12. O Agente (atendimento automático no WhatsApp)

O Agente conversa com o cliente no WhatsApp 24h: responde, qualifica e, quando necessário, encaminha para um atendente. Tudo alimenta o CRM.

## 12.1 Painel do Agente

Mostra o desempenho do período: Conversas, Leads, Pedidos, Faturamento, Conversão, TMR (tempo de resposta) e TMA (tempo de atendimento) — estes dois contando apenas o horário de atendimento configurado —, além do gráfico Conversas × Leads × Pedidos por dia e do filtro por negócio.

![](img/operacao/25.png)

*Painel do Agente.*

## 12.2 Leads, Pedidos e Histórico

No módulo Agente você acompanha os Leads captados, os Pedidos registrados e o Histórico completo das conversas.

![](img/operacao/26.png)

*Agente — lista de Leads.*

## 12.3 Do Agente para o atendente

Quando o cliente precisa de uma pessoa, o Agente faz o handoff: marca o atendimento como aguardando humano e o card aparece no CRM para ser assumido.

# 13. CRM — Funil de Vendas (Kanban)

Cada conversa vira um card, que caminha pelas etapas (colunas) do funil até o fechamento.

![](img/operacao/27.png)

*CRM — Funil de Vendas (Kanban).*

## 13.1 Lendo um card

O card mostra nome e telefone do cliente, etapa, etiquetas, valor (quando houver orçamento) e sinais: ponto vermelho = mensagem não lida; destaque = aguardando atendimento humano.

## 13.2 Filtros, busca e atualização

Acima do quadro há filtros (atendente, etapa, status, tag) e busca por nome/telefone. O quadro se atualiza sozinho no intervalo escolhido.

## 13.3 Inbox — a visão de mensagens

O botão Inbox/Kanban alterna a mesma informação para uma lista de conversas ordenada pela última mensagem — ideal para trabalhar 'estilo caixa de entrada'.

![](img/operacao/28.png)

*CRM — visão Inbox (dentro do Kanban).*

# 14. O Card de Atendimento

Abrir um card mostra, de um lado, as informações e campos do negócio; do outro, o chat do WhatsApp.

![](img/operacao/29.png)

*Tela do card — informações e chat do WhatsApp.*

## 14.1 Conversando pelo chat

Responda o cliente diretamente no WhatsApp pelo chat do card; as mensagens chegam e saem em tempo real e ficam no histórico.

## 14.2 Campos e itens do negócio

Preencha os campos do negócio (alguns podem ser obrigatórios) e registre itens. A soma dos itens e dos orçamentos forma o valor da oportunidade.

## 14.3 Mover o card e devolver ao chatbot

Use 'Mover para' para avançar a etapa e 'Devolver ao chatbot' para o Agente voltar a responder automaticamente aquele cliente.

# 15. Fórmulas — cotação de manipulados (integrada ao CRM)

Fórmulas é um módulo tão central quanto o CRM: transforma uma receita em orçamento, dentro do próprio atendimento. O orçamento nasce no card, vira o valor do negócio e, ao fechar a venda, segue para o Caixa — sem digitação dupla. Há três formas de cotar; comece pela mais automática.

## 15.1 A receita vira orçamento — por foto ou PDF (IA)

O maior diferencial: o cliente manda a foto ou o PDF da receita pelo WhatsApp e a inteligência artificial lê a prescrição e monta o orçamento sozinha, pronto para a conferência do farmacêutico.
1. O cliente envia a imagem (JPG/PNG) ou o PDF da receita.
1. A IA identifica a forma farmacêutica, os ativos e as doses.
1. O sistema casa cada ativo com o cadastro — inclusive reconhecendo nomes alternativos (sinônimos).
1. O orçamento é criado em 'pendente de revisão', vinculado ao cliente, para o farmacêutico aprovar.

Formatos: JPG, PNG e PDF (até ~20 MB). A IA acelera, mas a palavra final é do farmacêutico.

## 15.2 Sinônimos — entender o ativo por qualquer nome

Receitas usam nomes variados para o mesmo ativo. A base de sinônimos (configurada na Parte A) é o que faz o reconhecimento — manual ou por IA — acertar mesmo sem o nome oficial.

![](img/operacao/30.png)

*Fórmulas — Ativos e sinônimos.*

## 15.3 Importar do sistema de origem

Se o orçamento já existe no sistema de origem: no card, cole o texto do orçamento. O sistema separa os itens, casa os ativos (por nome, código ou sinônimo), calcula cápsulas e monta o quadro de valores, fechando no valor final do sistema de origem.

## 15.4 Montar manualmente

Em Fórmulas > Novo Orçamento (ou pelo card): escolha a forma e a quantidade; adicione os ativos (dose, unidade, QSP); adicione a embalagem; confira o quadro (Calculado → Custo Fixo → Cápsulas → Sub-Total → Acréscimo → Sem Desconto → Desconto → Valor com Desconto) e salve.

![](img/operacao/31.png)

*Fórmulas — editor de Novo Orçamento.*

## 15.5 O orçamento no card e a revisão

Por qualquer caminho, o orçamento fica preso ao card e vira o valor da oportunidade. Em negociação, todos os orçamentos do card são somados; quando o negócio é ganho e vai ao Pós-Vendas, o valor passa a considerar SÓ os orçamentos aprovados (os que viraram OM). Em Fórmulas > Orçamentos, o farmacêutico Aprova, Rejeita ou Marca como Enviado (status: pendente → aprovado → enviado).
- Aprovar em lote: marque os orçamentos na caixa de seleção e clique em '✅ Aprovar selecionados' — cada um vira uma OM de uma vez. Aprovar é exclusivo do farmacêutico responsável.

![](img/operacao/32.png)

*Fórmulas — Orçamentos e revisão (com seleção em lote).*

# 16. Fechando o negócio

## 16.1 Fechar como Ganho

Quando o cliente fecha a compra, encerre o card como Ganho. Para fechar, o sistema exige pelo menos UM orçamento APROVADO (que virou OM) OU um item de negócio — um orçamento só criado, ainda não aprovado, não fecha o negócio. Também pede o Valor Final e os campos obrigatórios. Ao confirmar: o card vai para o Pós-Vendas (o valor já recalculado só com os orçamentos aprovados) e nasce uma venda a receber no Caixa.

## 16.2 Fechar como Perdido

Se o negócio não avança, encerre como Perdido e informe o motivo. O card sai do funil ativo e fica registrado.

# 17. Pós-Vendas (produção e entrega)

Os cards ganhos caem no funil de Pós-Vendas — com etapas de produção e entrega (ex.: Aguardando Produção, Em Produção, Pronto para Entrega, pesquisa de satisfação). O objetivo é garantir que o cliente receba o pedido.

![](img/operacao/33.png)

*CRM — funil de Pós-Vendas.*

No card há 'Receber pagamento', que leva direto ao recebimento no Caixa.

Este manual cobre a jornada comercial (atendimento → venda → recebimento). A PRODUÇÃO em si — Ordem de Manipulação, pesagem, escolha de lote, ficha, rótulo, controle de estoque e controlados — é detalhada no Manual do TAO Lab (módulo de manipulação), que é o dono desses capítulos.

# 18. Caixa — recebimento e financeiro

Regra de ouro: toda venda nasce de um card (do funil, ao ganhar, ou avulsa de balcão, que cria um card 'Consumidor Final'). O Caixa é operado por gestores e administradores.

## 18.1 Vendas e recebimento

Em Caixa > Vendas, veja as vendas a receber (busca por nome, WhatsApp ou nº da requisição) e clique em Receber: informe forma, parcelas e valor.
- Split: mais de uma forma no mesmo recibo.
- Cupom: receber várias vendas num único recibo.
- A taxa (MDR), o líquido e a data prevista são calculados pela faixa de parcelas.
- Estorno: desfaz um recebimento (com motivo; auditado; reabre a venda).

![](img/operacao/34.png)

*Caixa — Vendas e recebimento.*

## 18.2 Sessão / Fechamento

Abra o caixa com o saldo inicial; ao fechar, informe o valor contado. O sistema mostra o esperado (inicial + dinheiro recebido) e a divergência.

## 18.3 Conciliação e antecipação

Confira os recebíveis de cartão na data prevista (conciliar) e, se quiser, antecipe — o sistema aplica a taxa de antecipação da operadora.

## 18.4 Painel do Caixa

Mostra, por período (Hoje, 7 dias, Mês): vendido, recebido, a receber, taxas e líquido; recebimentos por forma e por origem; e os valores a cair por data. O período 'Hoje' considera o dia atual (do começo do dia até agora).

![](img/operacao/35.png)

*Caixa — Painel financeiro.*

# 19. Contatos (perfil do cliente)

Em CRM > Contatos fica o cadastro central de cada cliente, com o histórico unificado (conversas, leads, pedidos, negócios) — o perfil 360°.

![](img/operacao/36.png)

*CRM — Contatos.*

# 20. Painéis e Indicadores (Dashboards)

Acompanhe a operação pela Visão Geral (consolidado) e pelos Painéis de cada módulo.

## 20.1 Visão Geral

Tela inicial: reúne o desempenho do Agente (conversas, leads, pedidos) e os resultados do CRM (cards abertos, novos leads, conversão, oportunidades, handoff aguardando, ganhos × perdidos).

## 20.2 Painel do CRM

Indicadores do funil: cards por etapa, novos leads, conversão, oportunidades em aberto e ganhos × perdidos. Inclui TMA (criação → resolução do card) e TMR (1ª resposta), ambos contando apenas o horário de atendimento configurado.

![](img/operacao/37.png)

*Painel do CRM — indicadores do funil.*

## 20.3 Painéis do Agente, Fórmulas e Caixa

Cada módulo tem seu painel, já mostrado nos capítulos respectivos: Agente (conversas/leads/pedidos/TMR/TMA), Fórmulas (pendentes/aprovados/volume) e Caixa (vendido/recebido/a receber/líquido).

# 21. Administração e manutenção

As telas de configuração (Parte A) ficam em Configurações e são acessadas por gestores e administradores. No dia a dia, as tarefas administrativas mais comuns são: criar/ajustar usuários, revisar automações e horário, conferir a conexão do WhatsApp e acompanhar os painéis. Para reconfigurar qualquer módulo, volte à Parte A deste manual.

# 22. Apêndice — Rotina diária do operador
1. Abra o Kanban (ou a Inbox) e veja quem aguarda atendimento — priorize as mensagens não lidas.
1. Assuma o card, responda pelo chat e preencha os campos do negócio.
1. Sendo manipulação, cote a fórmula no card (foto/PDF da receita, sistema de origem ou manual) e deixe o farmacêutico aprovar.
1. Fechou a compra? Encerre como Ganho — vai para o Pós-Vendas e gera a venda no Caixa.
1. Receba o pagamento no Caixa e acompanhe a produção/entrega no Pós-Vendas.

Dúvidas comuns: um card que 'sumiu' do funil provavelmente foi fechado e está no Pós-Vendas ou no histórico; se uma mensagem não chega ao cliente, confira a conexão do WhatsApp nos Conectores.
