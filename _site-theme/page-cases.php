<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cases — Soluções &amp; TAO</title>
<meta name="description" content="Onde aplicamos na prática. Dois casos reais de operação estruturada — uma farmácia de manipulação e uma rede com 9 lojas.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/cases/">
<link rel="icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo get_stylesheet_uri(); ?>?v=<?php echo filemtime(get_stylesheet_directory().'/style.css'); ?>">
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

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
    <li><a href="/tao-crm/">TAO CRM</a></li>
    <li><a href="/cases/" class="nav-active">Cases</a></li>
    <li class="has-dropdown">
      <a href="/sobre/fundador/">Sobre <i data-lucide="chevron-down" class="nav-chevron"></i></a>
      <ul class="dropdown">
        <li><a href="/sobre/fundador/">Fundador</a></li>
        <li><a href="/projeto-iluminar/">Projeto Iluminar</a></li>
      </ul>
    </li>
    <li><a href="/contato/">Contato</a></li>
  </ul>
  <div class="header-cta">
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa gratuita</a>
  </div>
  <div class="nav-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- HERO -->
<section style="background:var(--color-primary);padding:140px 0 72px;">
  <div class="container">
    <div class="section-animate" style="max-width:640px;">
      <div style="font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:0.12em;color:var(--color-accent);text-transform:uppercase;margin-bottom:20px;">CASES</div>
      <h1 style="color:#fff;font-size:44px;line-height:1.2;margin-bottom:20px;">Onde aplicamos<br>na prática.</h1>
      <p style="color:rgba(255,255,255,0.7);font-size:18px;line-height:1.7;">Dois casos reais. Problemas reais de operação. O que foi feito e o que mudou — sem exagero.</p>
    </div>
  </div>
</section>

<!-- CASES -->
<section class="section section-white">
  <div class="container">
    <div class="section-tag" style="margin-bottom:24px;">OPERAÇÃO &amp; ESTRATÉGIA</div>
    <div class="cards-grid-2">

      <a href="/cases/magis-tao/" class="case-card-inst case-card-link section-animate">
        <div class="case-tag">CLIENTE DESDE 2018 · FARMÁCIA DE MANIPULAÇÃO</div>
        <h3>Magis-TAO</h3>
        <p>Crescimento médio de 25% ao ano por seis anos — e hoje a operação roda inteira no ecossistema TAO Neo: do atendimento no WhatsApp ao fiscal e SNGPC, migrada do sistema anterior com confronto diário, item a item.</p>
        <div class="card-link" style="margin-top:16px;display:inline-flex;align-items:center;gap:6px;color:var(--color-accent);font-weight:500;font-size:15px;">
          Ver case completo <i data-lucide="arrow-right" style="width:15px;height:15px"></i>
        </div>
      </a>

      <a href="/cases/rede-construcao/" class="case-card-inst case-card-link section-animate">
        <div class="case-tag">CONSULTORIA 2023 · MATERIAIS DE CONSTRUÇÃO À SECO</div>
        <h3>Rede de Construção</h3>
        <p>Rede com 9 lojas operando de forma independente, sem padrão e sem controle centralizado. Estruturação operacional completa: servidores, processos e sistema de gestão de demandas.</p>
        <div class="card-link" style="margin-top:16px;display:inline-flex;align-items:center;gap:6px;color:var(--color-accent);font-weight:500;font-size:15px;">
          Ver case completo <i data-lucide="arrow-right" style="width:15px;height:15px"></i>
        </div>
      </a>

    </div>

    <div class="section-tag" style="margin-top:56px;margin-bottom:24px;">ESCALA CORPORATIVA</div>
    <div class="cards-grid-2">

      <a href="/cases/industrializacao/" class="case-card-inst case-card-link section-animate">
        <div class="case-tag">GRANDE CONSULTORIA · SETOR BANCÁRIO</div>
        <h3>Industrialização de Demandas</h3>
        <p>40% de aumento de produção sem contratar nenhuma pessoa. Um modelo de pool e triagem que transformou como o trabalho era organizado — sem adicionar capacidade, apenas revisando o processo.</p>
        <div class="card-link" style="margin-top:16px;display:inline-flex;align-items:center;gap:6px;color:var(--color-accent);font-weight:500;font-size:15px;">
          Ver case completo <i data-lucide="arrow-right" style="width:15px;height:15px"></i>
        </div>
      </a>

    </div>

    <div class="section-tag" style="margin-top:56px;margin-bottom:24px;">ARQUITETURA DE SISTEMA</div>
    <div class="cards-grid-2">

      <a href="/cases/iluminar/" class="case-card-inst case-card-link section-animate">
        <div class="case-tag">MODELO ORIGINAL · INICIATIVA SOCIAL</div>
        <h3>Projeto Iluminar</h3>
        <p>Mecanismo de multiplicação de conhecimento criado do zero — com rastreabilidade completa em cadeia. Irradiador, Herdeiro da Luz, Corrente da Luz. Um modelo replicável para campanhas sociais e programas de capacitação.</p>
        <div class="card-link" style="margin-top:16px;display:inline-flex;align-items:center;gap:6px;color:var(--color-accent);font-weight:500;font-size:15px;">
          Ver case completo <i data-lucide="arrow-right" style="width:15px;height:15px"></i>
        </div>
      </a>

    </div>
  </div>
</section>

<!-- CTA -->
<section class="section section-alt">
  <div class="container" style="text-align:center;max-width:600px;margin:0 auto;">
    <h2 style="margin-bottom:16px;">Seu negócio tem um problema parecido?</h2>
    <p style="color:var(--color-secondary);margin-bottom:32px;">Uma conversa de 30 minutos é suficiente para entender o que está travando a operação.</p>
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Quero minha conversa de 30 min</a>
  </div>
</section>

<footer id="footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <img src="<?php echo get_template_directory_uri(); ?>/assets/logo.png" alt="Soluções &amp; TAO" loading="lazy">
      <p>Consultoria de negócio, processos e estratégia.</p>
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

<script>
window.addEventListener('scroll', function() {
  document.getElementById('stao-nav').classList.toggle('scrolled', window.scrollY > 60);
});
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.section-animate').forEach(el => observer.observe(el));
document.querySelectorAll('.has-dropdown > a').forEach(function(link) {
  link.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) { e.preventDefault(); this.parentElement.classList.toggle('dropdown-open'); }
  });
});
</script>
<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
