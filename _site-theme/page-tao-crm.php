<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TAO CRM — O coração do ecossistema TAO Neo · Soluções &amp; TAO</title>
<meta name="description" content="TAO CRM: kanban com a conversa de WhatsApp dentro do card, funis totalmente customizados, automações de recompra, atendimento exclusivo e histórico permanente. O centro do ecossistema TAO Neo.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/tao-crm/">
<link rel="icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<link rel="shortcut icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<meta property="og:title" content="TAO CRM — O coração do ecossistema TAO Neo">
<meta property="og:description" content="Kanban com WhatsApp dentro do card, funis desenhados para o seu processo, automações que não esquecem. Totalmente customizado.">
<meta property="og:url" content="https://solucoesetao.com.br/tao-crm/">
<meta property="og:type" content="website">
<meta property="og:image" content="https://solucoesetao.com.br/wp-content/themes/solucoesetao/assets/og-image.png">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="TAO CRM — O coração do ecossistema TAO Neo">
<meta name="twitter:description" content="A conversa, o negócio e a operação no mesmo card — com histórico que nunca se perde.">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "Soluções & TAO",
      "url": "https://solucoesetao.com.br/",
      "logo": "https://solucoesetao.com.br/wp-content/themes/solucoesetao/assets/logo.png"
    },
    {
      "@type": "SoftwareApplication",
      "name": "TAO CRM",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web",
      "description": "CRM conversacional e customizável: kanban com WhatsApp integrado, funis de venda e pós-venda, automações, perfis de acesso e alçadas. Centro do ecossistema TAO Neo."
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "O TAO CRM funciona sozinho ou só com a suíte completa?",
          "acceptedAnswer": { "@type": "Answer", "text": "Funciona sozinho — atendimento, funil, automações e campanhas. E quando a sua operação pedir, as demais frentes do ecossistema TAO Neo (orçamentos, produção, caixa, fiscal) se conectam ao mesmo card, sem troca de sistema." }
        },
        {
          "@type": "Question",
          "name": "O CRM se adapta ao meu processo?",
          "acceptedAnswer": { "@type": "Answer", "text": "Esse é o princípio: funis, estágios, campos, travas, automações, perfis e alçadas são desenhados sobre o seu jeito de operar. O sistema se adapta a você — não o contrário." }
        },
        {
          "@type": "Question",
          "name": "E se dois atendentes responderem o mesmo cliente?",
          "acceptedAnswer": { "@type": "Answer", "text": "Não acontece: o card em atendimento fica travado para um atendente por vez. Quando ele fecha, o card se libera — e todo o histórico permanece." }
        },
        {
          "@type": "Question",
          "name": "O chatbot atrapalha o atendimento humano?",
          "acceptedAnswer": { "@type": "Answer", "text": "Não — eles trabalham no mesmo ciclo. O robô qualifica e resolve o repetitivo; quando o humano assume, a conversa continua no mesmo card, com todo o contexto. O cliente nunca recomeça a história." }
        }
      ]
    }
  ]
}
</script>
<?php wp_head(); ?>
<style>
/* ===== TAO CRM · página do coração (escopo tn-, mesma linguagem da /tao-neo) ===== */
.tn{background:var(--color-bg-alt,#F5F4F2);color:#1A1A1A;font-family:'Inter',-apple-system,sans-serif;font-size:16px;line-height:1.65}
.tn *{box-sizing:border-box}
.tn-wrap{max-width:1080px;margin:0 auto;padding:0 24px}
.tn-eyebrow{font-family:'JetBrains Mono',monospace;font-size:11.5px;letter-spacing:.18em;text-transform:uppercase;color:#B38E6C;display:block}
.tn h1,.tn h2{font-family:'Playfair Display',Georgia,serif;font-weight:700;line-height:1.16;margin:0}
.tn p{margin:0}
.tn ul{margin:0;padding:0}
.tn-lead{font-size:17.5px;color:#6B7280;max-width:64ch}
.tn a.tn-btn{display:inline-block;padding:13px 26px;border-radius:6px;font-weight:600;font-size:15px;text-decoration:none}
.tn-btn-bronze{background:#B38E6C;color:#fff!important}
.tn-btn-bronze:hover{background:#8F6E4F}
.tn-btn-ghost2{border:1px solid rgba(255,255,255,.35);color:#fff!important}
.tn-btn-ghost2:hover{border-color:#fff}
.tn section{padding:72px 0;margin:0}
.tn section.tn-alt{background:#fff}
.tn-sec-head{max-width:72ch;margin:0 0 40px}
.tn-sec-head h2{font-size:clamp(25px,3.2vw,36px);margin:12px 0 14px}

.tn-hero{background:linear-gradient(168deg,#152C42 0%,#0E2233 100%);color:#fff;padding:120px 0 66px}
.tn-hero .tn-wrap{display:grid;grid-template-columns:1fr 1.05fr;gap:56px;align-items:center}
.tn-hero h1{font-size:clamp(32px,4.2vw,46px);margin:14px 0 18px;color:#fff}
.tn-hero h1 em{font-style:normal;color:#B38E6C}
.tn-hero p.tn-sub{color:#C7D2DC;font-size:17.5px;max-width:54ch;margin-bottom:28px}
.tn-ctas{display:flex;gap:14px;flex-wrap:wrap}

/* mini kanban do hero */
.tn-kb{background:#fff;border-radius:12px;box-shadow:0 30px 70px rgba(0,0,0,.45);padding:16px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;color:#1A1A1A;font-size:11px}
.tn-kb .tn-col h4{font-family:'JetBrains Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#6B7280;text-transform:uppercase;margin:0 0 8px;font-weight:600}
.tn-kb .tn-cardk{background:#FBFAF8;border:1px solid #E2E0DC;border-radius:8px;padding:9px 10px;margin-bottom:8px;box-shadow:0 2px 6px rgba(21,44,66,.06)}
.tn-kb .tn-cardk strong{display:block;font-size:11.5px;margin-bottom:3px}
.tn-kb .tn-cardk .tn-meta{color:#6B7280;font-size:9.8px;display:flex;gap:6px;flex-wrap:wrap;font-family:'JetBrains Mono',monospace}
.tn-kb .tn-cardk.tn-hot{border-color:#B38E6C;box-shadow:0 4px 14px rgba(179,142,108,.28)}
.tn-pill{border-radius:9px;padding:1px 7px;font-size:8.8px;background:#EEF1F4;color:#475569}
.tn-pill.tn-verde{background:#E7F4EC;color:#1E7F4F}
.tn-pill.tn-ambar{background:#FFFBEB;color:#B45309}

.tn-det{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}
.tn-det.tn-inv > .tn-tela{order:-1}
.tn-det h2{font-size:clamp(23px,2.8vw,32px);margin:10px 0 12px}
.tn-det ul{list-style:none;display:flex;flex-direction:column;gap:12px;margin-top:18px}
.tn-det li{padding-left:24px;position:relative;font-size:14.5px;color:#374151}
.tn-det li::before{content:"";position:absolute;left:0;top:8px;width:10px;height:10px;border-radius:3px;background:#B38E6C}
.tn-det li strong{color:#1A1A1A}
.tn-tranquilo{margin-top:20px;background:#E7F4EC;border:1px solid #BBDCC8;border-radius:10px;padding:12px 16px;font-size:13.5px;color:#14532D}

.tn-tela{background:#fff;border:1px solid #E2E0DC;border-radius:12px;box-shadow:0 14px 36px rgba(21,44,66,.10);overflow:hidden;font-family:'JetBrains Mono',monospace}
.tn-tela .tn-barra{background:#F5F4F2;border-bottom:1px solid #E2E0DC;padding:9px 14px;font-size:10.5px;letter-spacing:.1em;color:#6B7280;text-transform:uppercase}
.tn-pilha{display:flex;flex-direction:column;gap:8px;padding:14px}
.tn-pilha .tn-item{display:flex;justify-content:space-between;align-items:center;border:1px solid #E2E0DC;border-radius:8px;padding:9px 12px;font-size:11px;gap:10px}
.tn-chip{font-size:9px;border-radius:10px;padding:2px 8px;background:#E7F4EC;color:#1E7F4F;border:1px solid #BBDCC8;white-space:nowrap}
.tn-chip.tn-espera{background:#FFFBEB;color:#B45309;border-color:#FCD34D}
.tn-tela .tn-rodape{padding:10px 14px;border-top:1px solid #E2E0DC;font-size:10px;color:#6B7280}

/* abas do card */
.tn ul.tn-abas{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:8px;list-style:none}
.tn-aba{background:#fff;border:1px solid #E2E0DC;border-radius:20px;padding:9px 18px;font-size:13px;font-weight:600;color:#152C42}
.tn-aba small{font-weight:400;color:#6B7280;display:block;font-size:11px}
.tn p.tn-abas-obs{text-align:center;color:#6B7280;font-size:13.5px;margin-top:18px;max-width:70ch;margin-inline:auto}

/* suite strip */
.tn-suite{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.tn-mini{background:#fff;border:1px solid #E2E0DC;border-radius:10px;padding:14px;text-align:center;text-decoration:none;color:#1A1A1A;transition:transform .18s,border-color .18s;position:relative}
.tn-badge{position:absolute;top:8px;right:8px;font-family:'JetBrains Mono',monospace;font-size:8px;letter-spacing:.08em;background:#EDE9FE;color:#6D28D9;border:1px solid #DDD6FE;border-radius:9px;padding:2px 7px}
.tn-mini:hover{transform:translateY(-2px);border-color:#B38E6C;color:#1A1A1A}
.tn-mini .tn-ic{font-size:19px}
.tn-mini strong{display:block;font-size:12.5px;margin-top:6px}
.tn-mini span{font-size:10.5px;color:#6B7280}

.tn-fim{text-align:center;padding:84px 0}
.tn-fim h2{font-size:clamp(25px,3.2vw,36px);margin:12px 0 16px}
.tn-fim p{color:#6B7280;max-width:56ch;margin:0 auto 28px}

@media (max-width:860px){
  .tn-hero .tn-wrap,.tn-det{grid-template-columns:1fr}
  .tn-det.tn-inv > .tn-tela{order:0}
  .tn-kb{grid-template-columns:1fr 1fr}
  .tn-kb .tn-col:nth-child(3){display:none}
  .tn section{padding:54px 0}
}
</style>
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
      <a href="/consultoria/">Consultoria</a>
      <ul class="dropdown">
        <li><a href="/consultoria/negocio/">Consultoria de Negócio</a></li>
        <li><a href="/consultoria/processos/">Consultoria de Processos</a></li>
        <li><a href="/consultoria/estrategica/">Consultoria Estratégica</a></li>
      </ul>
    </li>
    <li><a href="/tao-neo/">TAO Neo</a></li>
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
    <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20conhecer%20o%20TAO%20CRM." target="_blank" class="btn-primary">Conversa gratuita</a>
  </div>
  <div class="nav-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
    <span></span><span></span><span></span>
  </div>
</nav>

<div class="tn">

<!-- ===== HERO ===== -->
<div class="tn-hero">
  <div class="tn-wrap">
    <div>
      <span class="tn-eyebrow">TAO CRM · o coração do ecossistema</span>
      <h1>A conversa, o negócio e a operação — <em>no mesmo card</em>.</h1>
      <p class="tn-sub">Um CRM totalmente customizado ao seu processo: o WhatsApp vive dentro do card, o funil é desenhado do seu jeito, e as automações lembram do que a equipe não precisa mais lembrar.</p>
      <div class="tn-ctas">
        <a class="tn-btn tn-btn-bronze" href="#card">Ver o card por dentro</a>
        <a class="tn-btn tn-btn-ghost2" href="/tao-neo/">Conhecer o ecossistema completo</a>
      </div>
    </div>

    <div class="tn-kb" aria-label="Exemplo do kanban do TAO CRM">
      <div class="tn-col">
        <h4>Novos contatos</h4>
        <div class="tn-cardk"><strong>João S.</strong><div class="tn-meta"><span class="tn-pill">🤖 chatbot</span><span>09:12</span></div></div>
        <div class="tn-cardk"><strong>Farm. Vida</strong><div class="tn-meta"><span class="tn-pill">campanha</span><span>09:04</span></div></div>
      </div>
      <div class="tn-col">
        <h4>Em negociação</h4>
        <div class="tn-cardk tn-hot"><strong>Maria P.</strong><div class="tn-meta"><span class="tn-pill tn-ambar">orçamento enviado</span><span class="tn-pill">👤 Paula</span></div></div>
        <div class="tn-cardk"><strong>Carlos R.</strong><div class="tn-meta"><span class="tn-pill">áudio transcrito</span></div></div>
      </div>
      <div class="tn-col">
        <h4>Pós-venda</h4>
        <div class="tn-cardk"><strong>Ana L.</strong><div class="tn-meta"><span class="tn-pill tn-verde">✓ em produção</span></div></div>
        <div class="tn-cardk"><strong>Pedro M.</strong><div class="tn-meta"><span class="tn-pill tn-verde">✓ entrega hoje</span></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ===== O CARD ===== -->
<section id="card" class="tn-alt">
  <div class="tn-wrap">
    <div class="tn-sec-head" style="text-align:center;margin-inline:auto">
      <span class="tn-eyebrow">Tudo dentro do card</span>
      <h2>Abra um card e a história inteira está lá.</h2>
    </div>
    <ul class="tn-abas">
      <li class="tn-aba">💬 Conversa<small>WhatsApp completo</small></li>
      <li class="tn-aba">🧪 Orçamentos<small>calculados e aprovados</small></li>
      <li class="tn-aba">🤝 Negociação<small>valores, descontos com alçada</small></li>
      <li class="tn-aba">⚗️ Produção<small>a ordem e o andamento</small></li>
      <li class="tn-aba">💰 Pagamento<small>recebido no caixa</small></li>
      <li class="tn-aba">🛵 Entrega<small>até a porta, com NPS</small></li>
      <li class="tn-aba">📎 Anexos &amp; notas<small>receitas, fotos, contexto</small></li>
    </ul>
    <p class="tn-abas-obs">Qualquer pessoa do time abre o card e entende o cliente em segundos — o que foi conversado, o que foi vendido, o que está em produção e o que falta receber. <strong>O histórico não vai embora com o vendedor.</strong></p>
  </div>
</section>

<!-- ===== CUSTOMIZAÇÃO ===== -->
<section>
  <div class="tn-wrap tn-det">
    <div>
      <span class="tn-eyebrow">Feito sobre o seu processo</span>
      <h2>Customizado de verdade — não “configurável”.</h2>
      <p class="tn-lead">A implantação começa desenhando o seu fluxo, não adaptando você a um modelo pronto. Cada peça do CRM nasce do jeito que a sua operação trabalha.</p>
      <ul>
        <li><strong>Funis de venda e pós-venda</strong> com os estágios do seu processo — e campos obrigatórios por etapa, na hora certa.</li>
        <li><strong>Travas inteligentes:</strong> o card só avança quando o essencial está preenchido — endereço para entrega, pagamento para concluir.</li>
        <li><strong>Automações que não esquecem:</strong> renovação de tratamento no vencimento da receita, resgate de orçamento parado, mensagens por fase da negociação.</li>
        <li><strong>Perfis e alçadas:</strong> cada usuário vê as telas do seu papel; desconto acima do limite pede um gestor.</li>
        <li><strong>Atendimento exclusivo:</strong> card aberto trava para um atendente por vez — sem respostas duplicadas.</li>
        <li><strong>Multi-negócio:</strong> mais de uma operação na mesma conta, com acesso separado por equipe.</li>
      </ul>
      <div class="tn-tranquilo">🕊️ O time novo aprende rápido porque o sistema fala a língua da casa — os nomes, as etapas e as regras são os seus.</div>
    </div>
    <div class="tn-tela" aria-label="Ilustração de automações">
      <div class="tn-barra">Automações · trabalhando de madrugada</div>
      <div class="tn-pilha">
        <div class="tn-item"><span>Receita da Ana vence em 5 dias</span><span class="tn-chip">renovação enviada</span></div>
        <div class="tn-item"><span>Orçamento do Carlos parado há 2 dias</span><span class="tn-chip">resgate enviado</span></div>
        <div class="tn-item"><span>Card ganho · Pedro M.</span><span class="tn-chip">venda criada no caixa</span></div>
        <div class="tn-item"><span>Entrega concluída · Ana L.</span><span class="tn-chip">NPS solicitado</span></div>
        <div class="tn-item"><span>Novo contato fora do horário</span><span class="tn-chip tn-espera">chatbot atendendo</span></div>
      </div>
      <div class="tn-rodape">cada ação fica registrada no card — automação com transparência</div>
    </div>
  </div>
</section>

<!-- ===== CHATBOT + HUMANO ===== -->
<section class="tn-alt">
  <div class="tn-wrap tn-det tn-inv">
    <div class="tn-tela" aria-label="Ilustração de campanhas">
      <div class="tn-barra">Campanhas · o funil sempre abastecido</div>
      <div class="tn-pilha">
        <div class="tn-item"><span>Clientes sem compra há 60 dias</span><span class="tn-chip">segmento · 214 contatos</span></div>
        <div class="tn-item"><span>Campanha “volta do inverno”</span><span class="tn-chip">enviando · 12/min</span></div>
        <div class="tn-item"><span>Respostas chegando</span><span class="tn-chip tn-espera">18 cards abertos</span></div>
        <div class="tn-item"><span>Interessados → funil de vendas</span><span class="tn-chip">automático</span></div>
      </div>
      <div class="tn-rodape">quem responde vira card — e cai direto no atendimento</div>
    </div>
    <div>
      <span class="tn-eyebrow">Robô e humano, juntos</span>
      <h2>O chatbot abre caminho; a equipe fecha com contexto.</h2>
      <p class="tn-lead">A IA atende 24/7 com a voz do seu negócio, entende áudio e resolve o repetitivo. Quando o assunto pede gente, o atendente assume a MESMA conversa — nada se perde no caminho.</p>
      <ul>
        <li><strong>Handoff transparente:</strong> o cliente não percebe costura — é uma conversa só, do robô ao humano.</li>
        <li><strong>Áudio vira texto:</strong> o cliente manda voz, o card registra por escrito.</li>
        <li><strong>Campanhas segmentadas:</strong> mensagens em massa com critério — e cada resposta virando card no funil.</li>
        <li><strong>Cadastro único:</strong> o mesmo cliente em toda a suíte — atendimento, venda, entrega e financeiro enxergam a mesma pessoa.</li>
      </ul>
      <div class="tn-tranquilo">🕊️ Fora do horário, ninguém fica sem resposta — e a equipe chega de manhã com os cards organizados, não com uma pilha de mensagens.</div>
    </div>
  </div>
</section>

<!-- ===== SUITE ===== -->
<section>
  <div class="tn-wrap">
    <div class="tn-sec-head" style="text-align:center;margin-inline:auto">
      <span class="tn-eyebrow">E quando a operação pede mais</span>
      <h2>O coração já vem pronto para o ecossistema.</h2>
      <p class="tn-lead" style="margin-inline:auto">O TAO CRM funciona sozinho — e cada frente do TAO Neo se conecta ao mesmo card quando você quiser dar o próximo passo.</p>
    </div>
    <div class="tn-suite">
      <a class="tn-mini" href="/tao-neo/#operacao"><span class="tn-ic">💰</span><strong>Caixa</strong><span>PDV, recebíveis e NFC-e</span></a>
      <a class="tn-mini" href="/tao-neo/#operacao"><span class="tn-ic">🛵</span><strong>Pós Vendas</strong><span>entregas e NPS</span></a>
      <a class="tn-mini" href="/tao-neo/#operacao"><span class="tn-ic">📦</span><strong>Estoque</strong><span>NF e laudos</span></a>
      <a class="tn-mini" href="/tao-neo/#operacao"><span class="tn-ic">🤝</span><strong>Cotações</strong><span>compras e comparativos</span></a>
      <a class="tn-mini" href="/tao-neo/#analise"><span class="tn-ic">📊</span><strong>Análise</strong><span>painel e projeções</span></a>
      <a class="tn-mini" href="/tao-neo/#formulas"><span class="tn-badge">MANIPULAÇÃO</span><span class="tn-ic">🧪</span><strong>Fórmulas Farmacêuticas</strong><span>orçamento e produção</span></a>
      <a class="tn-mini" href="/tao-neo/#fiscal"><span class="tn-badge">MANIPULAÇÃO</span><span class="tn-ic">🧾</span><strong>SNGPC</strong><span>homologado</span></a>
    </div>
    <p style="text-align:center;font-size:13px;color:#6B7280;margin-top:18px;max-width:70ch;margin-inline:auto">Os módulos de <strong>Fórmulas Farmacêuticas</strong> e <strong>SNGPC</strong> são específicos para farmácias de manipulação. As demais frentes atendem qualquer operação de venda e atendimento — comércio, serviços, clínicas.</p>
  </div>
</section>

<!-- ===== CTA ===== -->
<section class="tn-fim tn-alt">
  <div class="tn-wrap">
    <span class="tn-eyebrow">Quando fizer sentido pra você</span>
    <h2>Veja o seu processo desenhado no TAO CRM.</h2>
    <p>Uma conversa sem compromisso e uma demonstração com a sua realidade — funil, etapas e automações do seu jeito.</p>
    <a class="tn-btn tn-btn-bronze" href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20conhecer%20o%20TAO%20CRM." target="_blank" rel="noopener">Agendar uma conversa</a>
  </div>
</section>

</div><!-- /.tn -->

<!-- ===== FOOTER ===== -->
<footer id="footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <img src="<?php echo get_template_directory_uri(); ?>/assets/logo.png" alt="Soluções &amp; TAO" loading="lazy">
      <p>Inteligência Aplicada ao Crescimento. Tecnologia, estratégia e consciência para transformar seu atendimento em resultado.</p>
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
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contato</h4>
      <ul>
        <li><a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a></li>
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
  <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20conhecer%20o%20TAO%20CRM." target="_blank">
    <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="vertical-align:middle;margin-right:8px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    Conhecer o TAO CRM
  </a>
</div>

<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>if(window.lucide){lucide.createIcons();}</script>
</body>
</html>
