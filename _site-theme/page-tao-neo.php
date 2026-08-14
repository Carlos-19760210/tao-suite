<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TAO Neo — A suíte que opera sua empresa inteira · Soluções &amp; TAO</title>
<meta name="description" content="TAO Neo é o ecossistema da Soluções &amp; TAO: atendimento com IA, orçamentos, produção, estoque, caixa, fiscal (NFC-e) e SNGPC homologados — tudo conectado ao CRM, num fluxo só.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/tao-neo/">
<link rel="icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<link rel="shortcut icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<meta property="og:title" content="TAO Neo — A suíte que opera sua empresa inteira">
<meta property="og:description" content="Atendimento, orçamento, produção, estoque, caixa, fiscal e entrega num ecossistema só, com o CRM no centro. Fiscal e SNGPC homologados.">
<meta property="og:url" content="https://solucoesetao.com.br/tao-neo/">
<meta property="og:type" content="website">
<meta property="og:image" content="https://solucoesetao.com.br/wp-content/themes/solucoesetao/assets/og-image.png">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="TAO Neo — A suíte que opera sua empresa inteira">
<meta name="twitter:description" content="Do WhatsApp ao SNGPC: um ecossistema conectado pelo CRM. Migração em espelho, sem salto no escuro.">
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
      "name": "TAO Neo",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web",
      "description": "Suíte de operação completa: CRM conversacional, orçamentos de manipulação, produção, estoque com NF e laudos, caixa/PDV, fiscal NFC-e e SNGPC homologados, entregas e análise."
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "O TAO Neo substitui o meu sistema atual?",
          "acceptedAnswer": { "@type": "Answer", "text": "Sim — por etapas e sem salto no escuro. O TAO importa a sua base, roda em paralelo com o sistema atual e um confronto diário compara os dois, item a item. Você só desliga o antigo quando os relatórios batem." }
        },
        {
          "@type": "Question",
          "name": "A parte fiscal e o SNGPC estão prontos?",
          "acceptedAnswer": { "@type": "Answer", "text": "Sim. A emissão de NFC-e e a escrituração/transmissão do SNGPC estão homologadas — validadas contra a operação real, em modo sombra, movimento a movimento." }
        },
        {
          "@type": "Question",
          "name": "Preciso adotar tudo de uma vez?",
          "acceptedAnswer": { "@type": "Answer", "text": "Não. O ecossistema é modular: dá para começar pelo atendimento e CRM e ligar as demais frentes — orçamentos, produção, caixa, fiscal — no ritmo da sua operação." }
        },
        {
          "@type": "Question",
          "name": "O sistema se adapta ao meu processo?",
          "acceptedAnswer": { "@type": "Answer", "text": "Esse é o princípio do TAO Neo: funis, campos, travas, automações, perfis e alçadas são desenhados sobre o seu jeito de operar — não o contrário." }
        }
      ]
    }
  ]
}
</script>
<?php wp_head(); ?>
<style>
/* ===== TAO NEO · página do ecossistema (escopo tn-) ===== */
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

/* hero */
.tn-hero{background:linear-gradient(168deg,#152C42 0%,#0E2233 100%);color:#fff;padding:120px 0 66px}
.tn-hero .tn-wrap{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center}
.tn-hero h1{font-size:clamp(32px,4.4vw,48px);margin:14px 0 18px;color:#fff}
.tn-hero h1 em{font-style:normal;color:#B38E6C}
.tn-hero p.tn-sub{color:#C7D2DC;font-size:17.5px;max-width:54ch;margin-bottom:28px}
.tn-ctas{display:flex;gap:14px;flex-wrap:wrap}
.tn ul.tn-selos{display:flex;gap:10px;flex-wrap:wrap;margin-top:30px;list-style:none}
.tn-selo{font-family:'JetBrains Mono',monospace;font-size:11.5px;letter-spacing:.04em;padding:6px 12px;border-radius:20px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);color:#DCE5EC}
.tn-selo.tn-ok{background:rgba(30,127,79,.25);border-color:rgba(110,200,150,.45);color:#BFE8CF}

/* card do hero */
.tn-cardzao{background:#fff;border-radius:12px;color:#1A1A1A;box-shadow:0 30px 70px rgba(0,0,0,.45);overflow:hidden;font-size:13px}
.tn-cardzao .tn-topo{display:flex;justify-content:space-between;align-items:center;background:#F5F4F2;border-bottom:1px solid #E2E0DC;padding:10px 16px}
.tn-cardzao .tn-topo strong{font-size:14px}
.tn-estagio{font-family:'JetBrains Mono',monospace;font-size:10.5px;background:#152C42;color:#fff;border-radius:12px;padding:3px 10px}
.tn-zap{padding:12px 16px;display:flex;flex-direction:column;gap:8px;background:#FBFAF8}
.tn-msg{max-width:84%;padding:8px 12px;border-radius:10px;line-height:1.45}
.tn-msg.tn-in{background:#EEF1F4;border-top-left-radius:2px}
.tn-msg.tn-out{background:#DCEAE0;align-self:flex-end;border-top-right-radius:2px}
.tn-msg .tn-quem{font-family:'JetBrains Mono',monospace;font-size:9.5px;color:#6B7280;display:block;margin-bottom:2px;letter-spacing:.06em}
.tn-orc{border-top:1px solid #E2E0DC;padding:12px 16px}
.tn-orc .tn-linha{display:flex;justify-content:space-between;font-family:'JetBrains Mono',monospace;font-size:11.5px;padding:3px 0;color:#374151}
.tn-orc .tn-linha.tn-total{border-top:1px dashed #E2E0DC;margin-top:6px;padding-top:8px;font-weight:700;color:#1A1A1A}
.tn-trilha{display:flex;gap:6px;flex-wrap:wrap;padding:12px 16px;background:#F5F4F2;border-top:1px solid #E2E0DC}
.tn-tag{font-family:'JetBrains Mono',monospace;font-size:9.8px;padding:3px 9px;border-radius:11px;background:#fff;border:1px solid #E2E0DC;color:#4B5563}
.tn-tag.tn-on{background:#E7F4EC;border-color:#BBDCC8;color:#1E7F4F}

/* ecossistema */
.tn-eco{position:relative;max-width:960px;margin:0 auto}
.tn-eco svg.tn-fios{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}
.tn-eco svg.tn-fios line{stroke:#B38E6C;stroke-width:1.6;opacity:.5}
.tn-eco-grid{position:relative;display:grid;grid-template-columns:repeat(3,1fr);gap:26px 34px;align-items:stretch}
.tn-caixa{display:block;text-decoration:none;color:#1A1A1A;background:#fff;border:1px solid #E2E0DC;border-radius:12px;padding:18px 18px 16px;box-shadow:0 3px 12px rgba(21,44,66,.06);transition:transform .18s,box-shadow .18s,border-color .18s;position:relative}
.tn-caixa:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(21,44,66,.14);border-color:#B38E6C;color:#1A1A1A}
.tn-caixa .tn-ic{font-size:22px}
.tn-caixa h3{font-size:15.5px;margin:8px 0 4px;font-family:'Inter',sans-serif;font-weight:700}
.tn-caixa p{font-size:12.8px;color:#6B7280;line-height:1.5}
.tn-caixa .tn-ver{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.1em;color:#8F6E4F;display:block;margin-top:10px}
.tn-caixa.tn-core{background:#152C42;color:#fff;border-color:#152C42;box-shadow:0 16px 40px rgba(14,34,51,.35);text-align:center;padding:26px 20px}
.tn-caixa.tn-core h3{font-family:'Playfair Display',Georgia,serif;font-size:21px;color:#fff}
.tn-caixa.tn-core p{color:#C7D2DC}
.tn-pulso{width:11px;height:11px;border-radius:50%;background:#B38E6C;margin:0 auto 10px;box-shadow:0 0 0 6px rgba(179,142,108,.25)}
.tn-selo-mini{position:absolute;top:12px;right:12px;font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:.06em;background:#E7F4EC;color:#1E7F4F;border:1px solid #BBDCC8;border-radius:10px;padding:2px 8px}
.tn-selo-mini.tn-roxo{background:#EDE9FE;color:#6D28D9;border-color:#DDD6FE}
.tn p.tn-eco-legenda{text-align:center;font-family:'JetBrains Mono',monospace;font-size:11px;color:#6B7280;margin-top:22px;letter-spacing:.05em}

/* fluxo */
.tn ul.tn-fita{display:flex;align-items:center;flex-wrap:wrap;justify-content:center;margin-top:8px;list-style:none;gap:0}
.tn-passo{background:#fff;border:1px solid #E2E0DC;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:600;color:#152C42}
.tn-passo small{display:block;font-weight:400;color:#6B7280;font-size:11px}
.tn-seta{color:#B38E6C;font-size:18px;padding:0 10px;font-weight:700}
.tn p.tn-fita-obs{text-align:center;color:#6B7280;font-size:13.5px;margin-top:18px}

/* detalhes */
.tn-det{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}
.tn-det.tn-inv > .tn-tela{order:-1}
.tn-det h2{font-size:clamp(23px,2.8vw,32px);margin:10px 0 12px}
.tn-det ul{list-style:none;display:flex;flex-direction:column;gap:12px;margin-top:18px}
.tn-det li{padding-left:24px;position:relative;font-size:14.5px;color:#374151}
.tn-det li::before{content:"";position:absolute;left:0;top:8px;width:10px;height:10px;border-radius:3px;background:#B38E6C}
.tn-det li strong{color:#1A1A1A}
.tn-tranquilo{margin-top:20px;background:#E7F4EC;border:1px solid #BBDCC8;border-radius:10px;padding:12px 16px;font-size:13.5px;color:#14532D}

/* telas ilustrativas */
.tn-tela{background:#fff;border:1px solid #E2E0DC;border-radius:12px;box-shadow:0 14px 36px rgba(21,44,66,.10);overflow:hidden;font-family:'JetBrains Mono',monospace}
.tn-tela .tn-barra{background:#F5F4F2;border-bottom:1px solid #E2E0DC;padding:9px 14px;font-size:10.5px;letter-spacing:.1em;color:#6B7280;text-transform:uppercase}
.tn-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:14px}
.tn-kpi{border:1px solid #E2E0DC;border-radius:8px;padding:10px 12px}
.tn-kpi .tn-l{font-size:9px;color:#6B7280;letter-spacing:.06em;text-transform:uppercase}
.tn-kpi .tn-v{font-size:16px;font-weight:700;color:#152C42;font-variant-numeric:tabular-nums}
.tn-kpi .tn-s{font-size:9px;color:#6B7280}
.tn-kpi.tn-destaque{background:#FFFBEB;border-color:#FCD34D}
.tn-kpi.tn-destaque .tn-v{color:#B45309}
.tn-tela .tn-rodape{padding:10px 14px;border-top:1px solid #E2E0DC;font-size:10px;color:#6B7280}
.tn-tela table{width:100%;border-collapse:collapse;font-size:10.5px;margin:0}
.tn-tela th{text-align:left;background:#F5F4F2;color:#6B7280;padding:7px 12px;font-weight:600;border-bottom:1px solid #E2E0DC}
.tn-tela td{padding:7px 12px;border-bottom:1px solid #F1F0ED;color:#374151;font-variant-numeric:tabular-nums}
.tn-tela td.tn-okc{color:#1E7F4F;font-weight:700}
.tn-pilha{display:flex;flex-direction:column;gap:8px;padding:14px}
.tn-pilha .tn-item{display:flex;justify-content:space-between;align-items:center;border:1px solid #E2E0DC;border-radius:8px;padding:9px 12px;font-size:11px;gap:10px}
.tn-chip{font-size:9px;border-radius:10px;padding:2px 8px;background:#E7F4EC;color:#1E7F4F;border:1px solid #BBDCC8;white-space:nowrap}
.tn-chip.tn-espera{background:#FFFBEB;color:#B45309;border-color:#FCD34D}

/* segurança */
.tn-seg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}
.tn-seg{background:#fff;border:1px solid #E2E0DC;border-radius:12px;padding:22px}
.tn-seg .tn-ic{font-size:20px}
.tn-seg h3{font-size:15.5px;margin:10px 0 6px;font-family:'Inter',sans-serif}
.tn-seg p{font-size:13.5px;color:#6B7280}

.tn-fim{text-align:center;padding:84px 0}
.tn-fim h2{font-size:clamp(25px,3.2vw,36px);margin:12px 0 16px}
.tn-fim p{color:#6B7280;max-width:56ch;margin:0 auto 28px}

@media (max-width:860px){
  .tn-hero .tn-wrap,.tn-det{grid-template-columns:1fr}
  .tn-det.tn-inv > .tn-tela{order:0}
  .tn-eco-grid{grid-template-columns:1fr 1fr}
  .tn-eco svg.tn-fios{display:none}
  .tn section{padding:54px 0}
  .tn-seta{display:none}
  .tn-fita{gap:8px}
}
@media (prefers-reduced-motion:reduce){.tn-caixa{transition:none}}
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
    <li><a href="/tao-neo/" class="nav-active">TAO Neo</a></li>
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
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20conhecer%20o%20TAO%20Neo." target="_blank" class="btn-primary">Conversa gratuita</a>
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
      <span class="tn-eyebrow">TAO Neo · o ecossistema</span>
      <h1>Um ambiente onde a sua operação <em>flui inteira</em> — e você está no controle.</h1>
      <p class="tn-sub">Atendimento, orçamento, produção, estoque, caixa, fiscal e entrega trabalhando como um organismo só, com o CRM no centro. Nada se redigita, nada se perde — e cada passo fica registrado.</p>
      <div class="tn-ctas">
        <a class="tn-btn tn-btn-bronze" href="#ecossistema">Conhecer o ecossistema</a>
        <a class="tn-btn tn-btn-ghost2" href="#seguranca">Por que é seguro migrar</a>
      </div>
      <ul class="tn-selos">
        <li class="tn-selo tn-ok">✓ Fiscal NFC-e homologado</li>
        <li class="tn-selo tn-ok">✓ SNGPC homologado</li>
        <li class="tn-selo">RDC 67 no fluxo</li>
      </ul>
    </div>

    <div class="tn-cardzao" aria-label="Exemplo de card do TAO Neo">
      <div class="tn-topo"><strong>Maria P. · (11) 9····-5398</strong><span class="tn-estagio">Orçamento enviado</span></div>
      <div class="tn-zap">
        <div class="tn-msg tn-in"><span class="tn-quem">CLIENTE · 09:41</span>Bom dia! Tenho uma receita nova, consigo orçamento hoje?</div>
        <div class="tn-msg tn-out"><span class="tn-quem">ATENDENTE · 09:43</span>Bom dia, Maria! Recebi a foto da receita — já preparei seu orçamento, segue 👇</div>
      </div>
      <div class="tn-orc">
        <div class="tn-linha"><span>MAGNÉSIO TREONATO 400mg · 60 cáps</span><span>R$ 109,00</span></div>
        <div class="tn-linha"><span>VIT D3 3000UI + K2 · 60 cáps</span><span>R$ 94,20</span></div>
        <div class="tn-linha tn-total"><span>Total</span><span>R$ 203,20</span></div>
      </div>
      <div class="tn-trilha">
        <span class="tn-tag tn-on">✓ receita lida por IA</span><span class="tn-tag tn-on">✓ orçamento no card</span>
        <span class="tn-tag">produção</span><span class="tn-tag">fiscal</span><span class="tn-tag">caixa</span><span class="tn-tag">entrega</span>
      </div>
    </div>
  </div>
</div>

<!-- ===== ECOSSISTEMA ===== -->
<section id="ecossistema">
  <div class="tn-wrap">
    <div class="tn-sec-head" style="text-align:center;margin-inline:auto">
      <span class="tn-eyebrow">O ecossistema</span>
      <h2>Oito frentes, uma suíte.</h2>
      <p class="tn-lead" style="margin-inline:auto">Cada caixa é um módulo completo — e todas se ligam pela suíte TAO Neo, no centro. Clique em qualquer uma para ver como funciona por dentro.</p>
    </div>

    <div class="tn-eco">
      <svg class="tn-fios" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <line x1="50" y1="50" x2="16" y2="13"/><line x1="50" y1="50" x2="50" y2="13"/><line x1="50" y1="50" x2="84" y2="13"/>
        <line x1="50" y1="50" x2="16" y2="50"/><line x1="50" y1="50" x2="84" y2="50"/>
        <line x1="50" y1="50" x2="16" y2="87"/><line x1="50" y1="50" x2="50" y2="87"/><line x1="50" y1="50" x2="84" y2="87"/>
      </svg>
      <div class="tn-eco-grid">
        <a class="tn-caixa" href="#atendimento"><span class="tn-ic">💬</span><h3>Agente</h3><p>Atendimento com IA e a voz do seu negócio, 24/7 — áudio transcrito, humano assume sem o cliente recomeçar.</p><span class="tn-ver">VER POR DENTRO ↓</span></a>
        <a class="tn-caixa" href="/tao-crm/"><span class="tn-ic">🫀</span><h3>CRM</h3><p>O coração operacional: card, conversa, funil e histórico — onde tudo se encontra.</p><span class="tn-ver">VER POR DENTRO →</span></a>
        <a class="tn-caixa" href="#atendimento"><span class="tn-ic">📣</span><h3>Campanhas</h3><p>Disparos segmentados no WhatsApp — e cada resposta vira card no funil, pronta pro atendimento.</p><span class="tn-ver">VER POR DENTRO ↓</span></a>

        <a class="tn-caixa" href="#formulas"><span class="tn-selo-mini tn-roxo">MANIPULAÇÃO</span><span class="tn-ic">🧪</span><h3>Fórmulas Farmacêuticas</h3><p>Orçamento calculado no card, produção com ficha de pesagem — aderente à legislação vigente (RDC 67, SNGPC homologado).</p><span class="tn-ver">VER POR DENTRO ↓</span></a>
        <div class="tn-caixa tn-core" role="presentation">
          <div class="tn-pulso"></div>
          <h3>TAO Neo</h3>
          <p>A suíte. Oito frentes ligadas num organismo só — os dados fluem, nada se redigita.</p>
        </div>
        <a class="tn-caixa" href="#operacao"><span class="tn-selo-mini">✓ NFC-E</span><span class="tn-ic">💰</span><h3>Caixa</h3><p>PDV com split, taxas reais das operadoras, recebíveis dia a dia — e a NFC-e homologada saindo da própria venda.</p><span class="tn-ver">VER POR DENTRO ↓</span></a>

        <a class="tn-caixa" href="#operacao"><span class="tn-ic">🛵</span><h3>Pós Vendas</h3><p>Entrega até a porta do cliente, travas de endereço e pagamento, e o NPS fechando o ciclo.</p><span class="tn-ver">VER POR DENTRO ↓</span></a>
        <a class="tn-caixa" href="#operacao"><span class="tn-ic">📦</span><h3>Estoque</h3><p>Nota do fornecedor entra por XML; laudo acompanha o lote; kardex de cada item.</p><span class="tn-ver">VER POR DENTRO ↓</span></a>
        <a class="tn-caixa" href="#operacao"><span class="tn-ic">🤝</span><h3>Cotações</h3><p>Compra com cotação a fornecedores, comparativo lado a lado e último preço pago.</p><span class="tn-ver">VER POR DENTRO ↓</span></a>
      </div>
      <p class="tn-eco-legenda">— todas as caixas se ligam: cadastro único de cliente, card único, fluxo único · o CRM é o coração; a suíte é o organismo —</p>
      <p style="text-align:center;font-size:13px;color:#6B7280;margin-top:14px;max-width:74ch;margin-inline:auto">A suíte nasceu na <strong>farmácia de manipulação</strong> — por isso os módulos de Fórmulas Farmacêuticas e SNGPC, específicos desse universo. Todas as demais frentes atendem <strong>qualquer operação</strong> de venda e atendimento: comércio, serviços, clínicas.</p>
    </div>
  </div>
</section>

<!-- ===== FLUXO ===== -->
<section class="tn-alt" id="atendimento">
  <div class="tn-wrap">
    <div class="tn-sec-head" style="text-align:center;margin-inline:auto">
      <span class="tn-eyebrow">A ligação entre as frentes</span>
      <h2>O trabalho flui — as telas se encarregam do resto.</h2>
    </div>
    <ul class="tn-fita">
      <li class="tn-passo">Mensagem<small>vira card</small></li><li class="tn-seta" aria-hidden="true">→</li>
      <li class="tn-passo">Receita<small>vira orçamento</small></li><li class="tn-seta" aria-hidden="true">→</li>
      <li class="tn-passo">Aprovação<small>vira ordem de produção</small></li><li class="tn-seta" aria-hidden="true">→</li>
      <li class="tn-passo">Venda<small>vira NFC-e + SNGPC</small></li><li class="tn-seta" aria-hidden="true">→</li>
      <li class="tn-passo">Pagamento<small>vira recebível no caixa</small></li><li class="tn-seta" aria-hidden="true">→</li>
      <li class="tn-passo">Entrega<small>vira NPS e recompra</small></li>
    </ul>
    <p class="tn-fita-obs">Em cada passagem, o sistema carrega os dados adiante e <strong>trava o que não pode seguir</strong> — pedido sem endereço não sai, controlado sem receita não fecha, orçamento sem revisão não aprova. É a segurança embutida no fluxo, não uma conferência no fim.</p>
  </div>
</section>

<!-- ===== DASHBOARD ===== -->
<section id="dashboard">
  <div class="tn-wrap tn-det">
    <div>
      <span class="tn-eyebrow">Painel do dia</span>
      <h2>Você abre o sistema e o dia se explica sozinho.</h2>
      <p class="tn-lead">O painel do CRM mostra a operação e o dinheiro na mesma tela — sem depender de planilha nem de perguntar pra equipe.</p>
      <ul>
        <li><strong>O funil ao vivo:</strong> cards em atendimento, novos contatos do dia, taxa de conversão e tempo médio até fechar.</li>
        <li><strong>O dinheiro de verdade:</strong> movimentado no caixa, recebido bruto e líquido (depois das taxas de cartão), e o que segue em aberto.</li>
        <li><strong>Projeção do mês</strong> por dias corridos e por dias úteis, com a média diária de venda — você sabe no dia 10 se o mês fecha bem.</li>
        <li><strong>Ticket médio do mês</strong> e valor ganho por semana, pra acompanhar o ritmo do time.</li>
      </ul>
      <div class="tn-tranquilo">🕊️ Números que a gestão entende de relance — cada cartão explica de onde o valor vem ao passar o mouse.</div>
    </div>
    <div class="tn-tela" aria-label="Ilustração do painel">
      <div class="tn-barra">CRM · Painel — Hoje</div>
      <div class="tn-kpis">
        <div class="tn-kpi"><div class="tn-l">Em atendimento</div><div class="tn-v">38</div><div class="tn-s">cards ativos</div></div>
        <div class="tn-kpi"><div class="tn-l">Movimentado</div><div class="tn-v">R$ 8.140</div><div class="tn-s">faturado hoje</div></div>
        <div class="tn-kpi"><div class="tn-l">Recebido líquido</div><div class="tn-v">R$ 6.925</div><div class="tn-s">após taxas</div></div>
        <div class="tn-kpi tn-destaque"><div class="tn-l">Projeção do mês</div><div class="tn-v">R$ 174 mil</div><div class="tn-s">por dias úteis</div></div>
        <div class="tn-kpi"><div class="tn-l">Ticket médio</div><div class="tn-v">R$ 217</div><div class="tn-s">no mês</div></div>
        <div class="tn-kpi"><div class="tn-l">Conversão</div><div class="tn-v">41%</div><div class="tn-s">orçamentos → venda</div></div>
      </div>
      <div class="tn-rodape">projeção: realizado ÷ dias decorridos × dias do mês · corridos e úteis lado a lado</div>
    </div>
  </div>
</section>

<!-- ===== FÓRMULAS ===== -->
<section class="tn-alt" id="formulas">
  <div class="tn-wrap tn-det tn-inv">
    <div class="tn-tela" aria-label="Ilustração do orçamento de fórmula">
      <div class="tn-barra">Fórmula · Orçamento no card</div>
      <table>
        <thead><tr><th>Ativo</th><th>Dose</th><th>Cálculo</th><th></th></tr></thead>
        <tbody>
          <tr><td>MAGNÉSIO TREONATO</td><td>400 mg</td><td>24,0 g p/ 60 cáps</td><td class="tn-okc">✓</td></tr>
          <tr><td>VIT D3 1:100</td><td>3000 UI</td><td>0,45 g · diluição aplicada</td><td class="tn-okc">✓</td></tr>
          <tr><td>VIT K2 (MK-7)</td><td>200 mcg</td><td>0,92 g · equivalência</td><td class="tn-okc">✓</td></tr>
          <tr><td>TCM (veículo)</td><td>qsp</td><td>completa a cápsula nº 1</td><td class="tn-okc">✓</td></tr>
        </tbody>
      </table>
      <div class="tn-rodape">cápsula ideal sugerida · excipiente automático · validações antes de aprovar</div>
    </div>
    <div>
      <span class="tn-eyebrow">Fórmulas &amp; Orçamentos</span>
      <h2>O conhecimento da bancada, embutido no orçamento.</h2>
      <p class="tn-lead">O atendente não faz conta: o motor de cálculo faz o trabalho técnico enquanto a conversa acontece — e o farmacêutico revisa e aprova com tudo na tela.</p>
      <ul>
        <li><strong>Receita por foto:</strong> a IA lê a prescrição e monta a fórmula — o atendente só confere.</li>
        <li><strong>Cálculo farmacêutico completo:</strong> doses, veículo (qsp), diluições 1:N, equivalências de sais, unidades UI/UFC, cápsula ideal e excipiente — as regras da manipulação, aplicadas sozinhas.</li>
        <li><strong>Cadastro técnico vivo:</strong> ativos com sinônimos, fatores de equivalência, densidades e restrições — o sistema avisa quando algo falta.</li>
        <li><strong>Aprovação consciente:</strong> o farmacêutico abre o orçamento, revisa item a item e aprova — só então vira ordem de produção.</li>
        <li><strong>Histórico do cliente:</strong> repetir uma fórmula antiga é um clique; a recompra no fim do tratamento é automática.</li>
        <li><strong>Compras conectadas:</strong> cotações com fornecedores, comparativo e último preço pago, no mesmo ambiente.</li>
      </ul>
      <div class="tn-tranquilo">🕊️ O gate da RDC 67 acompanha o fluxo: ativo sem cadastro completo não avança — e ninguém precisa lembrar disso.</div>
    </div>
  </div>
</section>

<!-- ===== OPERAÇÃO ===== -->
<section id="operacao">
  <div class="tn-wrap tn-det">
    <div>
      <span class="tn-eyebrow">Menu Operação</span>
      <h2>A rotina da casa, organizada em um só lugar.</h2>
      <p class="tn-lead">Produção, estoque, caixa e entregas são o menu Operação — a bancada digital de quem toca o dia a dia. Cada tela mostra a fila do jeito que a equipe trabalha.</p>
      <ul>
        <li><strong>Produção:</strong> a fila de ordens de manipulação com ficha de pesagem calculada, lote, validade, rótulo e livro de registro. Concluiu, o estoque baixa sozinho.</li>
        <li><strong>Estoque:</strong> entrada de mercadoria pelo XML da NF do fornecedor, com lote e laudo do fabricante vinculados desde a chegada. Kardex de cada item.</li>
        <li><strong>Caixa:</strong> sessão com abertura e fechamento, aportes e sangrias auditados, recebimento com split de formas e cupom que cobre várias vendas. Estorno só com motivo — nada se apaga.</li>
        <li><strong>Recebíveis:</strong> o contrato real de cada operadora (taxa por modalidade, bandeira e parcela) e a agenda do que vai cair, dia a dia, com conciliação.</li>
        <li><strong>Entregas:</strong> etapas do pedido até a porta do cliente, com travas de endereço e pagamento — e o NPS fechando o ciclo.</li>
      </ul>
      <div class="tn-tranquilo">🕊️ As travas cuidam da equipe: o sistema segura o passo errado na hora, em vez de cobrar a correção depois.</div>
    </div>
    <div class="tn-tela" aria-label="Ilustração da fila de operação">
      <div class="tn-barra">Operação · o dia correndo</div>
      <div class="tn-pilha">
        <div class="tn-item"><span>OM 047789 · cápsulas · 60un</span><span class="tn-chip">pesagem ok</span></div>
        <div class="tn-item"><span>OM 047791 · solução oral · 30ml</span><span class="tn-chip tn-espera">em produção</span></div>
        <div class="tn-item"><span>NF 12.408 · fornecedor Insumos SP</span><span class="tn-chip">laudo vinculado</span></div>
        <div class="tn-item"><span>Caixa · sessão aberta 08:02</span><span class="tn-chip">R$ 6.925 líquido</span></div>
        <div class="tn-item"><span>Entrega · Maria P. · motoboy</span><span class="tn-chip tn-espera">saiu p/ entrega</span></div>
        <div class="tn-item"><span>A cair amanhã · operadora</span><span class="tn-chip">R$ 2.310</span></div>
      </div>
      <div class="tn-rodape">cada linha abre no card do pedido — contexto completo a um clique</div>
    </div>
  </div>
</section>

<!-- ===== FISCAL ===== -->
<section class="tn-alt" id="fiscal">
  <div class="tn-wrap tn-det tn-inv">
    <div class="tn-tela" aria-label="Ilustração do confronto">
      <div class="tn-barra">Homologação · confronto diário</div>
      <table>
        <thead><tr><th>Verificação</th><th>Sistema atual</th><th>TAO Neo</th><th></th></tr></thead>
        <tbody>
          <tr><td>Valores do dia</td><td>R$ 8.140,00</td><td>R$ 8.140,00</td><td class="tn-okc">✓</td></tr>
          <tr><td>Pesagens item a item</td><td>30 itens</td><td>30 itens</td><td class="tn-okc">✓</td></tr>
          <tr><td>SNGPC (modo sombra)</td><td>arquivo oficial</td><td>arquivo idêntico</td><td class="tn-okc">✓</td></tr>
          <tr><td>NFC-e emitidas</td><td>—</td><td>autorizadas</td><td class="tn-okc">✓</td></tr>
        </tbody>
      </table>
      <div class="tn-rodape">relatório gerado todos os dias durante a implantação — você acompanha cada linha</div>
    </div>
    <div>
      <span class="tn-eyebrow">Fiscal &amp; SNGPC · homologados</span>
      <h2>A parte que não pode falhar — provada antes de assumir.</h2>
      <p class="tn-lead">Emissão de NFC-e e escrituração do SNGPC integradas à venda — e homologadas do jeito mais exigente que existe: rodando em paralelo com a operação real.</p>
      <ul>
        <li><strong>NFC-e da própria venda:</strong> o cupom fiscal sai do mesmo pedido, com NCM, CFOP e tributação já no cadastro do produto.</li>
        <li><strong>SNGPC sem sustos:</strong> controlados escriturados movimento a movimento e transmitidos — com as notificações de receita amarradas ao orçamento.</li>
        <li><strong>Homologação em modo sombra:</strong> o TAO gerou os mesmos arquivos que o sistema anterior, dia após dia, até a comparação bater 100% — só então assume.</li>
      </ul>
      <div class="tn-tranquilo">🕊️ Você nunca dá um salto no escuro: o confronto diário mostra, por escrito, que os dois sistemas dizem a mesma coisa.</div>
    </div>
  </div>
</section>

<!-- ===== ANÁLISE ===== -->
<section id="analise">
  <div class="tn-wrap tn-det">
    <div>
      <span class="tn-eyebrow">Menu Análise</span>
      <h2>Perguntas do dono, respondidas em dois cliques.</h2>
      <p class="tn-lead">Dois modos, um princípio: o dinheiro vem do caixa — o que você vê é o que aconteceu de verdade, não uma estimativa.</p>
      <ul>
        <li><strong>Modo simples:</strong> as perguntas prontas do dia a dia — quanto vendi, quanto recebi, por atendente, por forma de pagamento, por período. Sem configurar nada.</li>
        <li><strong>Modo avançado:</strong> um cubo dinâmico em português, de arrastar e soltar — cruze produto × atendente × mês, taxas por operadora, o que a pergunta pedir.</li>
        <li><strong>Financeiro e operação juntos:</strong> faturado por fechamento, recebido bruto e líquido por recebimento, consumo de insumos por produção.</li>
        <li><strong>Exportação a um clique:</strong> qualquer visão vira planilha — inclusive a lista do que está em aberto pra receber.</li>
      </ul>
      <div class="tn-tranquilo">🕊️ A mesma régua em todas as telas: painel, caixa e análise batem entre si — porque bebem da mesma fonte.</div>
    </div>
    <div class="tn-tela" aria-label="Ilustração do menu de análise">
      <div class="tn-barra">Análise · modo simples</div>
      <div class="tn-pilha">
        <div class="tn-item"><span>Quanto vendi este mês?</span><span class="tn-chip">R$ 96.400</span></div>
        <div class="tn-item"><span>Quanto recebi, líquido de taxas?</span><span class="tn-chip">R$ 81.720</span></div>
        <div class="tn-item"><span>Quem mais converteu no mês?</span><span class="tn-chip">Paula · 47%</span></div>
        <div class="tn-item"><span>Qual forma de pagamento cresce?</span><span class="tn-chip">Pix · +18%</span></div>
        <div class="tn-item"><span>O que está em aberto pra receber?</span><span class="tn-chip tn-espera">exportar planilha</span></div>
      </div>
      <div class="tn-rodape">modo avançado: cubo dinâmico de arrastar e soltar, em português</div>
    </div>
  </div>
</section>

<!-- ===== SEGURANÇA ===== -->
<section class="tn-alt" id="seguranca">
  <div class="tn-wrap">
    <div class="tn-sec-head">
      <span class="tn-eyebrow">Pra você ficar tranquilo</span>
      <h2>Segurança não é um recurso — é o jeito como tudo foi construído.</h2>
    </div>
    <div class="tn-seg-grid">
      <div class="tn-seg"><span class="tn-ic">🪞</span><h3>Migração em espelho</h3><p>Seu sistema atual continua rodando enquanto o TAO importa a base e opera em paralelo. O confronto diário compara tudo, item a item. Você decide a virada — com relatório na mão.</p></div>
      <div class="tn-seg"><span class="tn-ic">📜</span><h3>Nada se apaga</h3><p>Estorno com motivo e autor, movimentos de caixa auditados, histórico de conversa permanente. O sistema lembra — para proteger quem opera.</p></div>
      <div class="tn-seg"><span class="tn-ic">🔐</span><h3>Cada um no seu quadrado</h3><p>Perfis de acesso por tela, alçadas de desconto por pessoa, card em atendimento travado para um atendente por vez.</p></div>
      <div class="tn-seg"><span class="tn-ic">🤝</span><h3>Gente por perto</h3><p>Implantação acompanhada de quem conhece o seu processo — a suíte se adapta ao seu jeito de operar, não o contrário.</p></div>
    </div>
  </div>
</section>

<!-- ===== CTA ===== -->
<section class="tn-fim">
  <div class="tn-wrap">
    <span class="tn-eyebrow">Quando fizer sentido pra você</span>
    <h2>Venha ver o ecossistema rodando de verdade.</h2>
    <p>Uma conversa sem compromisso, uma demonstração com a sua realidade — e, se avançarmos, a migração em espelho até você se sentir em casa.</p>
    <a class="tn-btn tn-btn-bronze" href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20conhecer%20o%20TAO%20Neo." target="_blank" rel="noopener">Agendar uma conversa</a>
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
  <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20conhecer%20o%20TAO%20Neo." target="_blank">
    <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="vertical-align:middle;margin-right:8px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    Conhecer o TAO Neo
  </a>
</div>

<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>if(window.lucide){lucide.createIcons();}</script>
</body>
</html>
