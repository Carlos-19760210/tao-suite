<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TAO CRM — CRM Conversacional com WhatsApp Integrado · Soluções &amp; TAO</title>
<meta name="description" content="TAO CRM: Kanban com chat WhatsApp nativo no card e dois pipelines em série. O lead chega do WhatsApp, o card aparece no Kanban, o vendedor fecha.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/tao-crm/">
<link rel="icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<link rel="shortcut icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<meta property="og:title" content="TAO CRM — CRM Conversacional com WhatsApp Integrado">
<meta property="og:description" content="Kanban + chat WhatsApp nativo. O lead chega, o card aparece, o vendedor fecha.">
<meta property="og:url" content="https://solucoesetao.com.br/tao-crm/">
<meta property="og:type" content="website">
<meta property="og:image" content="https://solucoesetao.com.br/wp-content/themes/solucoesetao/assets/og-image.png">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="TAO CRM — CRM Conversacional com WhatsApp Integrado">
<meta name="twitter:description" content="Kanban + chat WhatsApp nativo. Do primeiro Oi ao cliente fidelizado sem nada se perder.">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "Soluções & TAO",
      "url": "https://solucoesetao.com.br/tao-crm/",
      "logo": "https://solucoesetao.com.br/wp-content/themes/solucoesetao/assets/logo.png",
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+55-11-93934-2091",
        "contactType": "customer service",
        "availableLanguage": "Portuguese"
      }
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Preciso ter o TAO Neo para usar o TAO CRM?",
          "acceptedAnswer": { "@type": "Answer", "text": "Não é obrigatório, mas é onde o valor aparece integralmente. Com os dois juntos, o card é criado automaticamente com o histórico da conversa e os campos preenchidos. Sem o TAO Neo, o CRM funciona normalmente mas a entrada de leads é manual ou via webhook de outro sistema." }
        },
        {
          "@type": "Question",
          "name": "Qual a diferença entre Funil de Vendas e Pós Vendas?",
          "acceptedAnswer": { "@type": "Answer", "text": "São dois Kanbans em série. Funil de Vendas: do primeiro contato ao fechamento (prospecção, qualificação, proposta, negociação, fechamento). Pós Vendas: gestão do cliente após o fechamento (onboarding, acompanhamento, renovação, CSAT). O card migra automaticamente quando o negócio é marcado como ganho." }
        },
        {
          "@type": "Question",
          "name": "Quantos usuários posso ter no sistema?",
          "acceptedAnswer": { "@type": "Answer", "text": "O número de usuários depende do plano. A conversa gratuita serve para entender o tamanho do time e montar uma proposta sem cobrar por usuário que você não vai usar." }
        },
        {
          "@type": "Question",
          "name": "Como o chat WhatsApp funciona dentro do card?",
          "acceptedAnswer": { "@type": "Answer", "text": "Seu número de WhatsApp Business é conectado via Evolution API. Cada mensagem que chega aparece no card correspondente. Você responde direto pelo TAO CRM — a mensagem sai do seu número normal, como se fosse pelo WhatsApp." }
        },
        {
          "@type": "Question",
          "name": "Posso importar minha base atual de clientes?",
          "acceptedAnswer": { "@type": "Answer", "text": "Sim. Importamos via CSV com deduplicação automática por número de WhatsApp. Clientes com o mesmo número não são duplicados. O processo é assistido para garantir que os campos mapeiem corretamente ao seu pipeline." }
        }
      ]
    }
  ]
}
</script>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php wp_body_open(); ?>

<!-- ===== HEADER NAV ===== -->
<nav id="stao-nav">
  <div class="nav-logo">
    <a href="/"><img src="<?php echo get_template_directory_uri(); ?>/assets/logo.png" alt="Soluções &amp; TAO" loading="eager"></a>
    <span class="brand-tagline">Consultoria em processos, tecnologia e atendimento.</span>
  </div>
  <ul class="nav-menu" id="navMenu">
    <li><a href="/">Home</a></li>
    <li class="has-dropdown">
      <a href="/consultoria/">Consultoria <i data-lucide="chevron-down" class="nav-chevron"></i></a>
      <ul class="dropdown">
        <li><a href="/consultoria/negocio/">Consultoria de Negócio</a></li>
        <li><a href="/consultoria/processos/">Consultoria de Processos</a></li>
        <li><a href="/consultoria/estrategica/">Consultoria Estratégica</a></li>
      </ul>
    </li>
    <li><a href="/tao-neo/">TAO Neo</a></li>
    <li><a href="/tao-crm/" class="nav-active">TAO CRM</a></li>
    <li><a href="/cases/">Cases</a></li>
    <li class="has-dropdown">
      <a href="/sobre/fundador/">Sobre <i data-lucide="chevron-down" class="nav-chevron"></i></a>
      <ul class="dropdown">
        <li><a href="/sobre/fundador/">Fundador</a></li>
        <li><a href="/projeto-iluminar/">Projeto Iluminar</a></li>
      </ul>
    </li>
    <li><a href="/contato/">Contato</a></li>
    <li class="nav-entrar-mobile">
      <?php
      $pm = ( is_user_logged_in() && function_exists('cbpm_can_access') && cbpm_can_access() )
          ? home_url('/robos/') : home_url('/robos/login/');
      $tm = ( is_user_logged_in() && function_exists('cbpm_can_access') && cbpm_can_access() )
          ? 'Portal &rarr;' : 'Entrar';
      ?>
      <a href="<?php echo esc_url($pm); ?>" style="color:var(--color-accent);font-weight:600"><?php echo $tm; ?></a>
    </li>
  </ul>
  <div class="header-cta">
    <?php
    $portal_url = ( is_user_logged_in() && function_exists('cbpm_can_access') && cbpm_can_access() )
        ? home_url('/robos/') : home_url('/robos/login/');
    $portal_txt = ( is_user_logged_in() && function_exists('cbpm_can_access') && cbpm_can_access() )
        ? 'Portal &rarr;' : 'Entrar';
    ?>
    <a href="<?php echo esc_url($portal_url); ?>" class="btn-entrar"><?php echo $portal_txt; ?></a>
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="btn-primary">Conversa gratuita</a>
  </div>
  <div class="nav-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- ===== SEÇÃO 1 — HERO ===== -->
<section id="hero">
  <div class="hero-inner">
    <div class="hero-content section-animate">
      <div class="hero-label">SEUS LEADS ESTÃO MORRENDO — E NINGUÉM ESTÁ VENDO</div>
      <h1>Seu time vende pelo WhatsApp.<br>Mas ninguém sabe<br>onde cada negócio <em class="accent-word">morreu</em>.</h1>
      <p class="hero-sub">O TAO CRM conecta cada conversa do WhatsApp a um card no Kanban em tempo real. Você enxerga o funil. O vendedor tem contexto. Nenhum lead cai no esquecimento — nunca mais.</p>
      <div class="hero-btns">
        <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="btn-primary">Quero ver como funciona</a>
        <a href="#produto" class="btn-ghost">Ver funcionalidades</a>
      </div>
      <div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:20px;font-size:0.85rem;color:var(--color-text-muted);">
        <span>✓ Kanban + WhatsApp num só sistema</span>
        <span>✓ Card criado automaticamente</span>
        <span>✓ Zero histórico perdido</span>
      </div>
    </div>
    <div class="hero-mockup section-animate">
      <div class="chat-mock">
        <div class="chat-mock-header">
          <div class="chat-mock-avatar">📋</div>
          <div>
            <div class="chat-mock-name">Maria Santos — Em Negociação</div>
            <div class="chat-mock-sub">Funil de Vendas · card criado automaticamente</div>
          </div>
        </div>
        <div class="chat-mock-body chat-simulation">
          <div class="chat-bubble bot">Olá! Recebemos seu contato 👋 Qual produto você procura? <span class="chat-time">14:02</span></div>
          <div class="chat-bubble user">Quero orçamento pra fórmula de emagrecimento <span class="chat-time">14:03</span></div>
          <div class="chat-bubble bot">Perfeito! Já anotei aqui no sistema. Nossa equipe entra em contato hoje. Horário preferido? <span class="chat-time">14:03</span></div>
          <div class="chat-bubble user">Pode ser qualquer hora à tarde <span class="chat-time">14:04</span></div>
          <div class="chat-bubble bot">Oi Maria! Aqui é a equipe da Magis-TAO 😊 Vi no card que você quer fórmula de emagrecimento — posso te enviar o orçamento agora? <span class="chat-time">14:31</span></div>
          <div class="typing-indicator" aria-hidden="true"><span></span><span></span><span></span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 1b — SHOWCASE REAL ===== -->
<section id="showcase" class="section section-alt" style="padding-top:48px;padding-bottom:64px">
  <div class="container">
    <div class="section-header section-animate" style="margin-bottom:16px">
      <span class="section-tag">OPERAÇÃO REAL</span>
      <h2>O sistema que você vai operar todo dia</h2>
      <p>Sem demo fabricada. Isso é o TAO CRM em produção na Magis-TAO.</p>
    </div>

    <div class="showcase-tabs section-animate" style="display:flex;gap:12px;justify-content:center;margin-bottom:32px;flex-wrap:wrap">
      <button class="showcase-tab active" onclick="showTab(this,'kanban')" style="padding:10px 20px;border:2px solid var(--color-primary);background:var(--color-primary);color:#fff;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600">Kanban</button>
      <button class="showcase-tab" onclick="showTab(this,'card')" style="padding:10px 20px;border:2px solid var(--color-primary);background:transparent;color:var(--color-primary);border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600">Card + Chat</button>
      <button class="showcase-tab" onclick="showTab(this,'dashboard')" style="padding:10px 20px;border:2px solid var(--color-primary);background:transparent;color:var(--color-primary);border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600">Dashboard</button>
    </div>

    <div id="tab-kanban" class="showcase-panel section-animate" style="text-align:center">
      <figure style="margin:0;position:relative;display:inline-block;width:100%">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/ss_12_kanban.png"
             alt="TAO CRM — Kanban com funil de vendas e cards por fase"
             loading="lazy"
             style="width:100%;max-width:1100px;border-radius:12px;box-shadow:0 8px 40px rgba(21,44,66,0.18);border:1px solid var(--color-border)">
        <figcaption style="margin-top:16px;font-size:0.9rem;color:var(--color-text-muted)">Funil Kanban — visão em tempo real de todos os leads por fase. Funil de Vendas e Pós Vendas em abas separadas.</figcaption>
      </figure>
    </div>

    <div id="tab-card" class="showcase-panel section-animate" style="text-align:center;display:none">
      <figure style="margin:0;position:relative;display:inline-block;width:100%">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/ss_05b_card_chat.png"
             alt="TAO CRM — Card aberto com chat WhatsApp nativo integrado"
             loading="lazy"
             style="width:100%;max-width:1100px;border-radius:12px;box-shadow:0 8px 40px rgba(21,44,66,0.18);border:1px solid var(--color-border)">
        <figcaption style="margin-top:16px;font-size:0.9rem;color:var(--color-text-muted)">Card aberto — dados do lead à esquerda, chat WhatsApp nativo à direita. O vendedor responde sem sair do sistema.</figcaption>
      </figure>
    </div>

    <div id="tab-dashboard" class="showcase-panel section-animate" style="text-align:center;display:none">
      <figure style="margin:0;position:relative;display:inline-block;width:100%">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/ss_09_dashboard.png"
             alt="TAO CRM — Dashboard com KPIs, atividade e leads por status"
             loading="lazy"
             style="width:100%;max-width:1100px;border-radius:12px;box-shadow:0 8px 40px rgba(21,44,66,0.18);border:1px solid var(--color-border)">
        <figcaption style="margin-top:16px;font-size:0.9rem;color:var(--color-text-muted)">Dashboard — conversas, leads e pedidos dos últimos 30 dias. Gráfico de atividade e distribuição por status em tempo real.</figcaption>
      </figure>
    </div>

    <div style="text-align:center;margin-top:36px">
      <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="btn-primary">Quero operar assim também</a>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 2 — PROBLEMA ===== -->
<section id="problema" class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">O PROBLEMA</span>
      <h2>Você reconhece algum desses cenários?</h2>
    </div>
    <div class="cards-grid-3">
      <div class="problem-card section-animate">
        <div class="problem-icon"><i data-lucide="smartphone" aria-label="Histórico" role="img"></i></div>
        <h3>O histórico some com o vendedor</h3>
        <p>Seu melhor vendedor pede demissão. Leva o WhatsApp pessoal. E junto com ele vão 6 meses de negociações, preferências de clientes e acordos informais. Você começa do zero — e o cliente também.</p>
      </div>
      <div class="problem-card section-animate">
        <div class="problem-icon"><i data-lucide="eye-off" aria-label="Visibilidade" role="img"></i></div>
        <h3>Funil invisível, gestão no escuro</h3>
        <p>Você pergunta: "quantos leads abertos temos?". Ninguém sabe ao certo. Cada vendedor tem uma versão. A planilha está desatualizada. E os negócios esfriam em silêncio enquanto a equipe acha que está tudo sob controle.</p>
      </div>
      <div class="problem-card section-animate">
        <div class="problem-icon"><i data-lucide="git-fork" aria-label="Duplicação" role="img"></i></div>
        <h3>Chatbot qualifica, vendedor recomeça</h3>
        <p>O robô fez toda a qualificação. O cliente respondeu tudo. Mas quando o vendedor abre o WhatsApp pessoal, não tem contexto. Ele precisa perguntar tudo de novo. O cliente se irrita. O negócio esfria — e morre.</p>
      </div>
    </div>

    <div class="section-animate" style="background:var(--color-primary);color:#fff;border-radius:12px;padding:32px 40px;text-align:center;margin-top:48px;">
      <p style="font-size:1.25rem;font-weight:700;margin-bottom:8px">Cada lead sem follow-up em 24h tem <span style="color:var(--color-accent)">73%</span> menos chance de fechar.</p>
      <p style="opacity:0.85;font-size:0.97rem">Com o TAO CRM, você vê quais leads estão parados — e age antes de perdê-los para sempre.</p>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 3 — SOLUÇÃO ===== -->
<section id="solucao" class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">POR QUE O TAO CRM É DIFERENTE</span>
      <h2>Visibilidade total. Operação que não depende de memória.</h2>
    </div>
    <div class="cards-grid-3">
      <div class="pilar-card section-animate">
        <div class="pilar-icon"><i data-lucide="layout-kanban" aria-label="Kanban" role="img"></i></div>
        <h3>Visibilidade real do funil</h3>
        <p>Kanban com todos os leads em tempo real. Você enxerga onde cada card está, quem tocou por último, há quanto tempo está sem resposta — e age antes de perder o negócio. Sem reunião para saber o número.</p>
      </div>
      <div class="pilar-card section-animate">
        <div class="pilar-icon"><i data-lucide="message-circle" aria-label="Chat" role="img"></i></div>
        <h3>Conversa dentro do card</h3>
        <p>Chat WhatsApp nativo dentro do card, com o histórico completo. O vendedor lê o que o chatbot qualificou, responde em segundos e registra tudo — sem WhatsApp pessoal, sem contexto perdido, sem segundo aplicativo.</p>
      </div>
      <div class="pilar-card section-animate">
        <div class="pilar-icon"><i data-lucide="zap" aria-label="Automação" role="img"></i></div>
        <h3>Automação que não esquece</h3>
        <p>Alertas automáticos quando um lead fica horas sem resposta. Round-robin de atribuição. Mensagens por fase. O CRM cuida do que a equipe esquece — e protege cada negócio da inércia.</p>
      </div>
    </div>
    <div class="cards-grid-3 resultado-cards" style="margin-top:56px;padding-top:48px;border-top:1px solid var(--color-border)">
      <div class="pilar-card section-animate">
        <div class="pilar-icon"><i data-lucide="eye" aria-label="Funil" role="img"></i></div>
        <h3>Você enxerga o funil real</h3>
        <p>Leads abertos, fase de cada um, conversão por atendente. Sem achismo, sem planilha desatualizada — os dados aparecem em tempo real. Você decide com informação, não com suposição.</p>
      </div>
      <div class="pilar-card section-animate">
        <div class="pilar-icon"><i data-lucide="bot" aria-label="Chatbot" role="img"></i></div>
        <h3>Chatbot e vendedor no mesmo ciclo</h3>
        <p>O card nasce da conversa do TAO Neo. Quando o vendedor abre, o histórico já está lá. Zero atrito na passagem do robô para o humano — o cliente não repete nada, a venda não resfria.</p>
      </div>
      <div class="pilar-card section-animate">
        <div class="pilar-icon"><i data-lucide="users" aria-label="Equipe" role="img"></i></div>
        <h3>Histórico que não some nunca</h3>
        <p>Cada conversa, movimentação e nota fica no card. Quando o vendedor sai, o próximo abre e continua de onde parou — sem recomeçar do zero, sem perder o relacionamento construído.</p>
      </div>
    </div>

    <div class="section-animate" style="background:var(--color-primary);color:#fff;border-radius:12px;padding:36px 40px;margin-top:48px;">
      <p style="font-size:1.15rem;font-weight:600;margin-bottom:12px;color:var(--color-accent)">Imagine abrir o painel amanhã de manhã:</p>
      <p style="line-height:1.85;opacity:0.92;font-size:1rem">Você vê exatamente quantos leads estão em cada fase. Quem está sem resposta há 12 horas — antes que esfrie. Qual atendente está travado. Qual funil está convertendo melhor. <strong style="color:var(--color-accent)">Você para de gerir no escuro. Você passa a decidir com dados.</strong></p>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 4 — PRODUTO ===== -->
<section id="produto" class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">PRODUTO</span>
      <h2>TAO CRM — Gestão Comercial Conversacional</h2>
      <p>Tudo que uma equipe comercial precisa — integrado ao WhatsApp que ela já usa, sem trocar de aplicativo.</p>
    </div>
    <div class="produto-grid">
      <div class="produto-features section-animate">
        <ul class="feature-list">
          <li>Dois pipelines em série: Funil de Vendas completo + Pós Vendas — do "Oi" ao cliente fidelizado</li>
          <li>Chat WhatsApp nativo dentro do card — responde sem sair do sistema, sem segundo app</li>
          <li>Card criado automaticamente quando o lead entra pelo TAO Neo — zero digitação manual</li>
          <li>Histórico completo da conversa do chatbot já no card — o vendedor começa informado</li>
          <li>Alerta automático de leads parados — você sabe antes de perder o negócio</li>
          <li>Round-robin de atribuição — distribuição justa, sem acúmulo em um único atendente</li>
          <li>Agendamento de mensagens — follow-up no momento certo, sem precisar lembrar</li>
          <li>Dashboard com KPIs reais: conversão, tempo de resposta, performance por atendente</li>
          <li>Busca global instantânea por nome, WhatsApp ou status do card</li>
          <li>Importação CSV com dedup automático — sua base migra sem duplicação</li>
          <li>Controle de acesso: gestor vê tudo, atendente vê seus cards</li>
          <li>Comentários internos — notas que só a equipe vê, nunca o cliente</li>
        </ul>
        <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="btn-primary">Quero meu CRM conversacional</a>
      </div>
      <div class="produto-mockup section-animate">
        <div class="chat-mock">
          <div class="chat-mock-header">
            <div class="chat-mock-avatar">🗂</div>
            <div>
              <div class="chat-mock-name">Ana Rodrigues — Em Proposta</div>
              <div class="chat-mock-sub">Funil de Vendas · responsável: João</div>
            </div>
          </div>
          <div class="chat-mock-body chat-simulation">
            <div class="chat-bubble bot">Oi! Tenho interesse nos planos corporativos de vocês</div>
            <div class="chat-bubble user">Quantas pessoas você precisa atender no time?</div>
            <div class="chat-bubble bot">São 8 pessoas no meu time comercial</div>
            <div class="chat-bubble user">Perfeito, Ana! Preparei uma proposta customizada pra 8 usuários. Posso te enviar agora?</div>
            <div class="typing-indicator" aria-hidden="true"><span></span><span></span><span></span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="steps-grid section-animate">
      <div class="step-item">
        <div class="step-number">01</div>
        <h3>Diagnóstico</h3>
        <p>Mapeamos sua operação comercial: como os leads chegam, como a equipe toca cada um e onde os negócios morrem — com dados, não suposição.</p>
      </div>
      <div class="step-item">
        <div class="step-number">02</div>
        <h3>Configuração</h3>
        <p>Montamos pipelines, fases, automações e conectamos seu WhatsApp. Você aprova cada etapa antes de ir ao ar. Nada genérico.</p>
      </div>
      <div class="step-item">
        <div class="step-number">03</div>
        <h3>Operação</h3>
        <p>Time treinado, sistema no ar em até 10 dias úteis, suporte contínuo. O CRM evolui junto com o seu processo — você não opera sozinho.</p>
      </div>
    </div>

    <div class="dif-bloco section-animate">
      <p class="dif-kicker">Por que o TAO CRM não é um CRM comum.</p>
      <div class="dif-colunas">
        <div class="dif-lado dif-antes">
          <span class="dif-tag">CRM comum</span>
          <p>Ficha de cliente separada da conversa. Você exporta o WhatsApp, cola em notas, atualiza campos na mão. O chatbot não fala com o CRM. Dois mundos separados — e os leads caem no meio.</p>
        </div>
        <div class="dif-seta" aria-hidden="true">→</div>
        <div class="dif-lado dif-depois">
          <span class="dif-tag">TAO CRM</span>
          <p>O card nasce da conversa. O histórico do chatbot já está lá. Você envia WhatsApp sem sair do sistema. Robô e vendedor falam a mesma língua desde o primeiro contato. Zero atrito, zero lead perdido.</p>
        </div>
      </div>
      <p class="dif-fecho">Não é só organização. É o ciclo completo — do primeiro "Oi" ao cliente fidelizado — sem nada se perder pelo caminho. Nenhum lead invisível. Nenhuma venda que escorregou sem que ninguém soubesse.</p>
    </div>

    <div class="faq-section section-animate">
      <h3>Perguntas frequentes sobre o TAO CRM</h3>
      <div class="faq-accordion" id="faqAccordion">
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFaq(this)">Preciso ter o TAO Neo para usar o TAO CRM? <span class="faq-icon">+</span></button>
          <div class="faq-answer"><p>Não é obrigatório, mas é onde o valor aparece integralmente. Com os dois juntos, o card é criado automaticamente com o histórico da conversa e os campos preenchidos — sem digitar nada. Sem o TAO Neo, o CRM funciona normalmente, mas a entrada de leads é manual ou via webhook de outro sistema.</p></div>
        </div>
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFaq(this)">Qual a diferença entre Funil de Vendas e Pós Vendas? <span class="faq-icon">+</span></button>
          <div class="faq-answer"><p>São dois Kanbans em série. <strong>Funil de Vendas:</strong> do primeiro contato ao fechamento — prospecção, qualificação, proposta, negociação, fechamento. <strong>Pós Vendas:</strong> gestão do cliente após o fechamento — onboarding, acompanhamento, renovação, CSAT. O card migra automaticamente quando o negócio é marcado como ganho.</p></div>
        </div>
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFaq(this)">Quantos usuários posso ter no sistema? <span class="faq-icon">+</span></button>
          <div class="faq-answer"><p>O número de usuários depende do plano. Temos controle por perfil — gestor vê todos os cards do workspace, atendente vê apenas os seus. A conversa gratuita serve para entender o tamanho do time e montar uma proposta sem cobrar por usuário que você não vai usar.</p></div>
        </div>
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFaq(this)">Como o chat WhatsApp funciona dentro do card? <span class="faq-icon">+</span></button>
          <div class="faq-answer"><p>Seu número de WhatsApp Business é conectado via Evolution API. Cada mensagem que chega ao seu número aparece no card correspondente. Você responde direto pelo TAO CRM — a mensagem sai do seu número normal, como se fosse pelo WhatsApp. Sem duplicação, sem segundo aplicativo.</p></div>
        </div>
        <div class="faq-item">
          <button class="faq-question" onclick="toggleFaq(this)">Posso importar minha base atual de clientes? <span class="faq-icon">+</span></button>
          <div class="faq-answer"><p>Sim. Importamos via CSV com deduplicação automática por número de WhatsApp. Clientes com o mesmo número não são duplicados. O processo é assistido pela nossa equipe para garantir que os campos mapeiem corretamente ao seu pipeline — sem perder dados na migração.</p></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 5 — CASE ===== -->
<section id="case" class="section section-dark">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">CASE DE SUCESSO</span>
    </div>
    <div class="case-featured section-animate">
      <span class="case-featured-tag">CASE DE SUCESSO</span>
      <h2 class="case-featured-title">Como a Magis-TAO estruturou sua gestão comercial</h2>
      <p class="case-featured-sub">Farmácia de manipulação em Cotia/SP. Antes: gestão pelo WhatsApp pessoal, funil invisível, histórico que some quando o atendente sai. Hoje: 40% mais leads atendidos, 100% do histórico preservado e crescimento de 25% ao ano com a mesma estrutura de equipe.</p>
      <div class="case-featured-divider"></div>
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-value" data-target="40" data-decimal="0">0</div>
          <div class="metric-suffix">%</div>
          <div class="metric-label">Mais leads atendidos no mesmo turno após implantação</div>
        </div>
        <div class="metric-card">
          <div class="metric-value" data-target="100" data-decimal="0">0</div>
          <div class="metric-suffix">%</div>
          <div class="metric-label">Do histórico preservado — nenhum lead perdido na troca de atendente</div>
        </div>
        <div class="metric-card">
          <div class="metric-value" data-target="25" data-decimal="0">0</div>
          <div class="metric-suffix">% a.a.</div>
          <div class="metric-label">Crescimento anual contínuo com a mesma estrutura de equipe</div>
        </div>
      </div>
      <blockquote class="case-featured-quote">
        "A gente sabia que perdia lead. Não sabia quanto — porque era invisível. Com o TAO CRM, eu enxergo cada negócio que está em andamento. E quando algo está travando, eu sei no mesmo dia — não na semana seguinte."
        <cite>— Carlos Carvalho Almeida, fundador Magis-TAO e Soluções &amp; TAO</cite>
      </blockquote>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 6 — SEGMENTOS ===== -->
<section id="segmentos" class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">SEGMENTOS ONDE ATUAMOS</span>
      <h2>Uma solução que se adapta ao seu funil</h2>
    </div>
    <div class="segmentos-grid section-animate">
      <div class="segmento-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        <span>Farmácias e distribuidoras</span>
      </div>
      <div class="segmento-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
        <span>Clínicas e consultórios</span>
      </div>
      <div class="segmento-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Imobiliárias e corretores</span>
      </div>
      <div class="segmento-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM21 17a2 2 0 11-4 0 2 2 0 014 0M1 3h1l3.5 10h11L21 7H6"/></svg>
        <span>Concessionárias e revendas</span>
      </div>
      <div class="segmento-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        <span>Serviços B2B e agências</span>
      </div>
      <div class="segmento-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <span>Varejo e e-commerce</span>
      </div>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 7 — PARA QUEM ===== -->
<section id="para-quem" class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">PARA QUEM É</span>
      <h2>Isso foi feito para você se...</h2>
    </div>
    <div class="cards-grid-3">
      <div class="perfil-card section-animate">
        <div class="perfil-icon"><i data-lucide="smartphone" aria-label="WhatsApp" role="img"></i></div>
        <p>Você tem uma equipe que vende pelo WhatsApp e não tem visibilidade do funil — não sabe quantos leads estão abertos, quem está tocando cada um, ou onde os negócios estão travando e morrendo.</p>
      </div>
      <div class="perfil-card section-animate">
        <div class="perfil-icon"><i data-lucide="bot" aria-label="Chatbot" role="img"></i></div>
        <p>Você já usa o TAO Neo e quer que o lead qualificado pelo chatbot vire automaticamente um card no Kanban — com o histórico completo, campos preenchidos e o contato pronto para o vendedor fechar.</p>
      </div>
      <div class="perfil-card section-animate">
        <div class="perfil-icon"><i data-lucide="file-spreadsheet" aria-label="Planilha" role="img"></i></div>
        <p>Você usa planilha e WhatsApp pessoal para gestão comercial. Você sabe que quando o vendedor sair, o histórico vai junto. E você quer mudar isso antes que aconteça — não depois de perder a equipe e os leads.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 8 — PROCESSO ===== -->
<section id="processo" class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">PROCESSO</span>
      <h2>Como funciona trabalhar com a gente</h2>
    </div>
    <div class="processo-timeline section-animate">
      <div class="processo-step">
        <div class="processo-num">01</div>
        <div class="processo-badge">Gratuito · 30 min</div>
        <h3>Conversa Estratégica</h3>
        <p>Entendemos como seu time vende hoje, como os leads chegam e onde os negócios morrem. Você sai com diagnóstico claro — não com proposta genérica.</p>
      </div>
      <div class="processo-step">
        <div class="processo-num">02</div>
        <div class="processo-badge">Personalizado</div>
        <h3>Configuração Sob Medida</h3>
        <p>Montamos pipelines, fases, automações e conectamos seu WhatsApp. Você aprova cada etapa antes de ir ao ar. Nada é ativado sem o seu aval.</p>
      </div>
      <div class="processo-step">
        <div class="processo-num">03</div>
        <div class="processo-badge">Em até 10 dias úteis</div>
        <h3>Operação e Suporte</h3>
        <p>Time treinado, sistema no ar, suporte contínuo. O CRM evolui junto com o seu processo — você não opera sozinho depois da entrega, nunca.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 9 — SOBRE ===== -->
<section id="sobre" class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">QUEM ESTÁ POR TRÁS</span>
      <h2>Fundado por quem já viveu o problema</h2>
    </div>
    <div class="sobre-grid section-animate">
      <div class="sobre-foto">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/carlos.webp" style="object-position:center 15%" alt="Carlos Almeida — Fundador Soluções &amp; TAO" loading="lazy">
      </div>
      <div class="sobre-texto">
        <div class="sobre-nome">Carlos Carvalho Almeida</div>
        <div class="sobre-cargo">Fundador — Soluções &amp; TAO</div>
        <p>Mais de 25 anos em Accenture, Capgemini e Everis, atendendo Ambev, Bradesco, Sky e Natura. Em 2018 fundei a Magis-TAO — farmácia de manipulação com crescimento de 25% ao ano. Sei o que é perder negócio por falta de visibilidade comercial.</p>
        <p>O TAO CRM nasceu dessa dor: precisava enxergar meu funil, registrar cada negociação e não depender de planilha nem da memória do vendedor. Testamos primeiro no próprio negócio — só depois colocamos à venda. O que você vê aqui é o que eu uso todo dia.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== SEÇÃO 10 — CTA FINAL ===== -->
<section class="section section-alt proposta-personalizada">
  <div class="container" style="text-align:center;">
    <p class="proposta-titulo">Cada implantação é desenhada para o seu funil.</p>
    <p class="proposta-sub">Fale conosco para receber sua proposta personalizada. <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="proposta-link">Falar pelo WhatsApp</a></p>
  </div>
</section>

<section id="cta-final" class="section section-dark">
  <div class="container cta-final-inner section-animate">
    <h2>Seu funil está perdendo negócios que você nem sabe que existem.</h2>
    <p class="cta-sub">Uma conversa de 30 minutos mostra exatamente quantos leads seus estão morrendo no silêncio do WhatsApp — e como mudar isso hoje.</p>
    <div class="cta-scarcity"><i data-lucide="timer" aria-label="Tempo" role="img"></i> Atendemos no máximo 8 empresas por mês para garantir qualidade na implantação.</div>
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="btn-primary btn-large">Quero minha conversa estratégica gratuita</a>
    <p class="cta-no-friction">Sem compromisso. Sem formulário longo. Só uma conversa.</p>
    <div class="cta-channels">
      <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="cta-channel">
        <svg viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        WhatsApp
      </a>
      <a href="mailto:contato@solucoesetao.com.br" class="cta-channel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        E-mail
      </a>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer id="footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <img src="<?php echo get_template_directory_uri(); ?>/assets/logo.png" alt="Soluções &amp; TAO" loading="lazy">
      <p>Inteligência Aplicada ao Crescimento. Tecnologia, estratégia e consciência para transformar sua operação comercial em resultado.</p>
    </div>
    <div class="footer-col">
      <h4>Consultoria</h4>
      <ul>
        <li><a href="/consultoria/negocio/">Negócio</a></li>
        <li><a href="/consultoria/processos/">Processos</a></li>
        <li><a href="/consultoria/estrategica/">Estratégica</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Empresa</h4>
      <ul>
        <li><a href="/sobre/fundador/">Sobre o Fundador</a></li>
        <li><a href="/cases/">Cases</a></li>
        <li><a href="/contato/">Fale Conosco</a></li>
        <li><a href="/tao-neo/">TAO Neo</a></li>
        <li><a href="/tao-crm/">TAO CRM</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contato</h4>
      <ul>
        <li><a href="https://wa.me/5511939342091" target="_blank">+55 11 93934-2091</a></li>
        <li><a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© 2026 Soluções &amp; TAO. Todos os direitos reservados. <a href="/politica-de-privacidade/">Política de Privacidade</a> · <a href="/termos-de-uso/">Termos de Uso</a></p>
  </div>
</footer>

<!-- ===== BARRA MOBILE ===== -->
<div class="mobile-cta-bar" id="mobileCta">
  <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank">
    <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="vertical-align:middle;margin-right:8px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    Quero ver o TAO CRM funcionando
  </a>
</div>

<!-- ===== POP-UP EXIT INTENT ===== -->
<div id="exit-popup" style="display:none">
  <div class="exit-overlay" onclick="closeExitPopup()"></div>
  <div class="exit-card">
    <button class="exit-close" onclick="closeExitPopup()">✕</button>
    <h3>Antes de fechar — uma pergunta rápida:</h3>
    <p>Você sabe quantos leads do seu time estão sem follow-up agora?</p>
    <p style="font-size:15px;color:var(--color-secondary);margin-top:-8px;">Cada dia sem visibilidade é um negócio que esfria. Uma conversa de 30 minutos mostra exatamente onde estão essas perdas — e como resolver.</p>
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20sobre%20o%20TAO%20CRM." target="_blank" class="btn-primary">Quero minha conversa gratuita de 30 min</a>
    <a href="#" onclick="closeExitPopup(); return false;" class="exit-dismiss">Não, obrigado</a>
  </div>
</div>

<script>
// ===== SHOWCASE TABS =====
function showTab(btn, id) {
  document.querySelectorAll('.showcase-panel').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.showcase-tab').forEach(b => {
    b.style.background = 'transparent';
    b.style.color = 'var(--color-primary)';
  });
  document.getElementById('tab-' + id).style.display = 'block';
  btn.style.background = 'var(--color-primary)';
  btn.style.color = '#fff';
}

// ===== NAV SCROLL + STICKY CTA =====
const stickyCTA = document.getElementById('mobileCta');
window.addEventListener('scroll', function() {
  document.getElementById('stao-nav').classList.toggle('scrolled', window.scrollY > 60);
  if (stickyCTA) {
    const scrolled = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
    stickyCTA.classList.toggle('visible', scrolled > 40 && scrolled < 95);
  }
});

// ===== FADE-IN SCROLL =====
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) entry.target.classList.add('visible');
  });
}, { threshold: 0.1 });
document.querySelectorAll('.section-animate').forEach(el => observer.observe(el));

// ===== COUNTER ANIMATION =====
function animateCounter(el, target, decimal, duration) {
  const start = performance.now();
  const step = (now) => {
    const progress = Math.min((now - start) / duration, 1);
    const ease = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
    el.textContent = (ease * target).toFixed(decimal);
    if (progress < 1) {
      requestAnimationFrame(step);
    } else {
      el.textContent = target.toFixed(decimal);
      el.classList.add('counter-done');
    }
  };
  requestAnimationFrame(step);
}
const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting && !entry.target.dataset.animated) {
      entry.target.dataset.animated = 'true';
      entry.target.querySelectorAll('.metric-value').forEach(el => {
        animateCounter(el, parseFloat(el.dataset.target), parseInt(el.dataset.decimal), 1800);
      });
      counterObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.3 });
const metricsEl = document.querySelector('.metrics-grid');
if (metricsEl) counterObserver.observe(metricsEl);

// ===== CHAT ANIMATION =====
function animateChat(container) {
  const messages = container.querySelectorAll('.chat-bubble');
  const typing = container.querySelector('.typing-indicator');
  messages.forEach(m => { m.style.opacity = '0'; m.style.transform = 'translateY(10px)'; });
  let delay = 300;
  messages.forEach((msg) => {
    const isBot = msg.classList.contains('bot');
    if (isBot && typing) {
      setTimeout(() => typing.classList.add('active'), delay);
      delay += 700;
    }
    setTimeout(() => {
      if (isBot && typing) typing.classList.remove('active');
      msg.style.opacity = '1';
      msg.style.transform = 'translateY(0)';
    }, delay);
    delay += 1200;
  });
}
const chatObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting && !entry.target.dataset.animated) {
      entry.target.dataset.animated = 'true';
      animateChat(entry.target);
    }
  });
}, { threshold: 0.4 });
document.querySelectorAll('.chat-simulation').forEach(c => chatObserver.observe(c));

// ===== FAQ ACCORDION =====
function toggleFaq(btn) {
  const item = btn.parentElement;
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(i => {
    i.classList.remove('open');
    i.querySelector('.faq-icon').textContent = '+';
  });
  if (!isOpen) {
    item.classList.add('open');
    btn.querySelector('.faq-icon').textContent = '−';
  }
}

// ===== EXIT INTENT =====
let exitShown = false;
document.addEventListener('mouseleave', (e) => {
  if (e.clientY < 10 && !exitShown && !sessionStorage.getItem('exitShown')) {
    document.getElementById('exit-popup').style.display = 'flex';
    exitShown = true;
    sessionStorage.setItem('exitShown', 'true');
  }
});
function closeExitPopup() {
  document.getElementById('exit-popup').style.display = 'none';
}

// ===== DROPDOWN MOBILE =====
document.querySelectorAll('.has-dropdown > a').forEach(function(link) {
  link.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
      e.preventDefault();
      this.parentElement.classList.toggle('dropdown-open');
    }
  });
});

// ===== CTA CLICK TRACKING =====
document.querySelectorAll('a[href*="wa.me"]').forEach(btn => {
  btn.addEventListener('click', () => {
    if (typeof gtag !== 'undefined') {
      gtag('event', 'conversion', { event_category: 'CTA', event_label: 'WhatsApp TAO CRM' });
    }
  });
});
</script>

<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
