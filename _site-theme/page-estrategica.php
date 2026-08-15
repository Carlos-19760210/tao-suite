<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consultoria Estratégica — Soluções &amp; TAO</title>
<meta name="description" content="Acompanhamento estratégico contínuo para donos e diretores que precisam de direcionamento executivo em decisões que pesam.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/consultoria/estrategica/">
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
      <a href="/consultoria/" class="nav-active">Consultoria <i data-lucide="chevron-down" class="nav-chevron"></i></a>
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
  </ul>
  <div class="header-cta">
    <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa gratuita</a>
  </div>
  <div class="nav-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- HERO -->
<section class="case-hero-section">
  <div class="container">
    <div class="case-hero-inner section-animate">
      <div class="case-breadcrumb"><a href="/consultoria/">Consultoria</a> <span>/</span> Estratégica</div>
      <div class="case-meta-line"><span>CONSULTORIA ESTRATÉGICA</span></div>
      <h1>Direcionamento executivo para decisões que pesam.</h1>
      <p class="case-hero-sub">Algumas decisões não podem errar — e não dá para tomar sozinho com o mesmo nível de clareza de quem está de fora. O acompanhamento estratégico é presença contínua, não consultoria de crise.</p>
      <div class="hero-btns" style="margin-top:32px;">
        <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa estratégica de 30 min</a>
      </div>
    </div>
  </div>
</section>

<!-- PARA QUEM É -->
<section class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">PARA QUEM É</span>
      <h2>Você se reconhece aqui?</h2>
    </div>
    <div class="cards-grid-3">
      <div class="consult-perfil-card section-animate">
        <div class="consult-perfil-icon"><i data-lucide="compass" role="img" aria-hidden="true"></i></div>
        <p>Você tem o negócio rodando mas não tem clareza de para onde está indo. As demandas do dia a dia consomem o tempo que deveria estar no futuro.</p>
      </div>
      <div class="consult-perfil-card section-animate">
        <div class="consult-perfil-icon"><i data-lucide="scale" role="img" aria-hidden="true"></i></div>
        <p>Você está diante de uma decisão de longo prazo — expansão, parceria, mudança de modelo — e precisa de alguém que conheça o contexto antes de opinar.</p>
      </div>
      <div class="consult-perfil-card section-animate">
        <div class="consult-perfil-icon"><i data-lucide="refresh-cw" role="img" aria-hidden="true"></i></div>
        <p>Você já passou por consultoria pontual. Funcionou por um tempo e foi se perdendo. Precisa de uma presença que se mantenha ao longo do tempo.</p>
      </div>
    </div>
  </div>
</section>

<!-- O QUE FAZEMOS -->
<section class="section section-alt">
  <div class="container case-content">
    <div class="section-animate">
      <h2>O que fazemos</h2>
      <ul class="case-list case-list-check">
        <li>Acompanhamento estratégico mensal ou quinzenal com agenda definida</li>
        <li>Revisão periódica de direção, prioridades e resultados</li>
        <li>Apoio direto em decisões críticas com visão externa e histórico do negócio</li>
        <li>Conexão entre estratégia e operação — sem deixar a execução de lado</li>
        <li>Identificação antecipada de riscos antes que virem problema</li>
      </ul>
    </div>
  </div>
</section>

<!-- MÉTODO -->
<section class="section section-white">
  <div class="container case-content">
    <div class="section-animate">
      <h2>Como trabalhamos</h2>
      <div class="case-actions">
        <div class="case-action-item">
          <div class="case-action-num">01</div>
          <div>
            <h3>Alinhamento inicial</h3>
            <p>Entendemos onde o negócio está e o que o dono quer construir. Ponto de partida real — não o que parece bem dizer, mas o que de fato importa.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">02</div>
          <div>
            <h3>Agenda estratégica</h3>
            <p>Definimos o que precisa ser resolvido nos próximos 90 dias. Foco, não lista de desejos. Prioridade que muda toda semana não é prioridade.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">03</div>
          <div>
            <h3>Acompanhamento contínuo</h3>
            <p>Reuniões regulares para revisar rumo, tomar decisões e ajustar prioridades. Com histórico do que foi decidido e por quê.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">04</div>
          <div>
            <h3>Revisão de ciclo</h3>
            <p>A cada ciclo, avaliamos o que funcionou, o que mudou no contexto e o que precisa ser ajustado. Estratégia que não é revisada vira dogma.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CASE -->
<section class="section section-alt">
  <div class="container">
    <div class="section-animate">
      <span class="section-tag">CASE RELACIONADO</span>
      <div class="consult-case-ref">
        <div class="consult-case-ref-body">
          <div class="case-tag" style="margin-bottom:12px;">FARMÁCIA DE MANIPULAÇÃO · CLIENTE DESDE 2018</div>
          <h3>Magis-TAO</h3>
          <p>Seis anos de acompanhamento estratégico contínuo. Cada fase de crescimento trouxe um conjunto diferente de decisões — e a presença que acompanhou cada uma delas.</p>
        </div>
        <a href="/cases/magis-tao/" class="btn-ghost consult-case-ref-btn">Ver case completo</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section section-dark">
  <div class="container cta-final-inner section-animate">
    <h2>Você toma as decisões importantes sozinho?</h2>
    <p class="cta-sub">Uma conversa de 30 minutos é suficiente para entender se o acompanhamento estratégico faz sentido para o seu momento.</p>
    <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary btn-large">Quero minha conversa de 30 min</a>
    <p class="cta-no-friction">Sem compromisso. Sem formulário longo.</p>
  </div>
</section>

<script>
window.addEventListener('scroll', function() { document.getElementById('stao-nav').classList.toggle('scrolled', window.scrollY > 60); });
const observer = new IntersectionObserver((entries) => { entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); }); }, { threshold: 0.1 });
document.querySelectorAll('.section-animate').forEach(el => observer.observe(el));
document.querySelectorAll('.has-dropdown > a').forEach(function(l) { l.addEventListener('click', function(e) { if (window.innerWidth <= 768) { e.preventDefault(); this.parentElement.classList.toggle('dropdown-open'); } }); });
</script>
<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
