# -*- coding: utf-8 -*-
"""
Capítulo REUTILIZÁVEL "Operando pelo card" — serve ao manual do TAO Lab (Fórmula) e ao
manual do TAO CRM. Chame capitulo_card(H, incluir_formula=True|False), passando um dict H
com os helpers do gerador: h1, h2, h3, para, passo, nota, tabela_campos, imagem, page_break.

  - incluir_formula=True  → inclui os blocos de orçamento/receita (manual do TAO Lab).
  - incluir_formula=False → só a operação geral do card (manual do TAO CRM).
"""

def capitulo_card(H, incluir_formula=True):
    h1  = H['h1'];  h3 = H['h3'];  para = H['para']
    passo = H['passo'];  nota = H['nota'];  tab = H['tabela_campos']
    imagem = H['imagem'];  pb = H['page_break']

    pb(); h1('O card do cliente — operando pelo CRM')
    para('O card é a central do atendimento: cada cliente/negócio tem um card no Kanban, e é nele que acontece TODA a operação — a conversa por WhatsApp, o registro do que foi combinado, o orçamento, o fechamento da venda e o acompanhamento pós-venda. Quem atende passa a maior parte do tempo aqui, sem precisar abrir outras telas.')
    imagem('card_ficha', 'A ficha do card — conversa, dados do cliente e o negócio')

    h3('Onde fica e como abrir')
    para('O Kanban (menu CRM) mostra os cards em colunas — uma para cada fase do funil (ex.: Aguardando Atendimento → Em Conversa → Orçamento Enviado …; e, no Pós-vendas: Aguardando Produção → Em Produção → Pronto para Entrega → Entregue → NPS).')
    passo('Clique em um card para abrir a ficha completa.')
    passo('Use a busca no topo do Kanban para achar um card por nome, WhatsApp ou número da requisição.')
    passo('Arraste o card entre as colunas para mudar a fase — ou use os botões de avançar/fechar dentro da própria ficha.')

    h3('A conversa (WhatsApp)')
    para('A ficha traz o chat com o cliente, como no WhatsApp: as mensagens entram e saem por ali, e tudo fica registrado.')
    passo('Digite no campo de mensagem e clique em "Enviar" para responder o cliente.')
    passo('Anexe imagens/arquivos pelo clipe.')
    passo('Nota interna: alterne para o modo nota para escrever um recado que SÓ a equipe vê (não vai ao cliente).')
    passo('Agendar mensagem (⏰): programe uma mensagem para uma data/hora futura (ex.: lembrete de retorno).')

    h3('Organização do atendimento')
    para('A ficha reúne os recursos para conduzir e não perder o atendimento:')
    tab([
        ('Responsável', False, 'Quem faz qualquer alteração no card vira o responsável por ele. Só a equipe do negócio aparece na lista de responsáveis.'),
        ('Tags (etiquetas)', False, 'Marcadores coloridos para classificar o card (campanha, prioridade, origem…).'),
        ('Lembretes 🔔', False, 'Agende um follow-up com data/hora — o sistema notifica quando vence.'),
        ('Comentários internos', False, 'Notas da equipe sobre o atendimento; o cliente não vê.'),
        ('Histórico', False, 'Linha do tempo com tudo que aconteceu no card: mudanças de fase, orçamentos, responsável, notas.'),
    ])

    if incluir_formula:
        h3('Montar o orçamento da fórmula (no card)')
        para('No bloco "🧪 Orçamentos Fórmula" o atendente monta o(s) orçamento(s) direto no card, sem sair da conversa. Há três caminhos:')
        passo('Processar Receita (IA): no bloco "🔬 Processar Receita", arraste (ou cole) a FOTO ou o PDF da receita e clique em processar — a IA lê a prescrição e monta o orçamento sozinha; depois você só revisa os ativos e a dose.')
        passo('Nova fórmula (manual): abre o editor de orçamento (o mesmo do cap. 6) DENTRO do card, para montar item a item.')
        passo('Importar (texto): botão "📋 Importar" — cola o texto de um orçamento do Formula Certa (formato ORC:…), durante a fase de convivência.')
        para('A lista mostra cada orçamento do card com o valor; dá para editar, selecionar e excluir. Botões da seção:')
        tab([
            ('🔗 Associar pendentes', False, 'Aparece quando um item não foi reconhecido (um sinônimo sem ativo). Abre a associação e salva o sinônimo para as próximas vezes.'),
            ('🔄 Reprocessar', False, 'Re-tenta associar os itens pendentes usando os sinônimos atuais e recalcula os valores.'),
            ('📊 Análise de preços', False, 'Resumo de margem de TODOS os orçamentos do card (custo, venda, margem em % e R$) e um campo para comparar com o preço do concorrente.'),
            ('🗑 Excluir selecionados', False, 'Remove os orçamentos marcados.'),
        ])

        h3('Enviar o orçamento ao cliente')
        passo('Marque o(s) orçamento(s) e clique em "📤 Enviar WhatsApp".')
        passo('Abre uma PRÉVIA da mensagem para revisão: o resumo da fórmula e os três valores — Valor, Desconto e Valor com desconto. Ajuste o texto se quiser e confirme para enviar pela própria conversa.')
        nota('A revisão antes do envio é o momento do farmacêutico/atendente conferir a mensagem — nada é enviado ao cliente sem essa confirmação.')

    h3('Campos obrigatórios (o checklist da fase)')
    para('Em algumas fases (especialmente no Pós-vendas), o card exige campos obrigatórios para avançar — é o checklist de conferência do atendimento.')
    passo('Uma faixa âmbar no card lista os campos que faltam na fase atual; preencha-os na própria ficha (eles salvam sozinhos). Ao responder cada um, ele fica verde.')
    passo('O card NÃO avança de fase, nem fecha como ganho, enquanto houver campo obrigatório vazio.')

    h3('Fechar o negócio')
    passo('Ganho: clique em fechar como Ganho. O sistema pede para CONFIRMAR o Valor Final e preencher o checklist; então o card cruza para o funil de Pós-vendas.' + (' Nesse momento nascem, automaticamente, a venda no Caixa (cap. 22) e a entrega (cap. 21).' if incluir_formula else ''))
    passo('Perdido: ao cancelar, é obrigatório informar o MOTIVO (lista pré-definida; "Falta de Insumo" pede qual insumo faltou).')
    passo('Reabrir: um card fechado pode ser reaberto pelo gestor, voltando à fase de origem.')

    if incluir_formula:
        h3('Pós-venda no card')
        para('Depois do ganho, o próprio card conduz a entrega e o pagamento — sem trocar de tela:')
        passo('Aba "🚚 Entrega": tipo (Cliente/Correio/Motoboy/Balcão), endereço (CEP preenche sozinho) e forma de pagamento (cap. 21).')
        passo('Receber pagamento: dá baixa da venda no Caixa direto do card (cap. 22).')
        nota('Quando o fluxo novo de OM estiver ligado, o card também mostra o painel "🧪 Ordem de Manipulação" para aprovar a formulação e imprimir a ficha de pesagem e o rótulo — ver o capítulo de Produção.')
