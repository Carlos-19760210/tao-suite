<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consultoria de Processos — Soluções &amp; TAO</title>
<meta name="description" content="Mapeamento, redesenho e implantação de processos. Para empresas que cresceram e perderam o controle operacional.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/consultoria/processos/">
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
      <div class="case-breadcrumb"><a href="/consultoria/">Consultoria</a> <span>/</span> Processos</div>
      <div class="case-meta-line"><span>CONSULTORIA DE PROCESSOS</span></div>
      <h1>Processo é o que faz a empresa funcionar sem você.</h1>
      <p class="case-hero-sub">Quando a operação depende da sua presença para funcionar, o problema não é a equipe — é a ausência de processos claros. Isso tem solução, e ela começa com entender o que realmente acontece no dia a dia.</p>
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
        <div class="consult-perfil-icon"><i data-lucide="user-check" role="img" aria-hidden="true"></i></div>
        <p>Você é o gargalo. Toda decisão passa por você. Se você sai de férias por uma semana, a operação trava ou vai na direção errada.</p>
      </div>
      <div class="consult-perfil-card section-animate">
        <div class="consult-perfil-icon"><i data-lucide="trending-up" role="img" aria-hidden="true"></i></div>
        <p>Sua empresa cresceu rápido e os processos não acompanharam. O que funcionava com 5 pessoas não funciona mais com 20.</p>
      </div>
      <div class="consult-perfil-card section-animate">
        <div class="consult-perfil-icon"><i data-lucide="shuffle" role="img" aria-hidden="true"></i></div>
        <p>Você tem equipe, mas cada pessoa faz do seu jeito. O resultado varia e você não consegue identificar onde está o problema.</p>
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
        <li>Mapeamento dos processos atuais como realmente funcionam — não como deveriam</li>
        <li>Identificação de gargalos, redundâncias e pontos de falha reais</li>
        <li>Redesenho dos processos com foco em autonomia da equipe</li>
        <li>Implementação com acompanhamento — não só documentação entregue em PDF</li>
        <li>Integração com tecnologia quando faz sentido, não como padrão</li>
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
            <h3>Mapeamento</h3>
            <p>Levantamos o que existe de fato. Conversamos com quem executa, não só com quem gerencia. Processo na prática é diferente do processo no papel.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">02</div>
          <div>
            <h3>Diagnóstico</h3>
            <p>Identificamos onde está o gargalo real. Frequentemente não é onde parece. A causa raiz costuma estar um ou dois passos antes do problema visível.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">03</div>
          <div>
            <h3>Redesenho</h3>
            <p>Novos fluxos documentados, claros e testados antes de virar padrão. Sem burocracia desnecessária — processo que ninguém segue não é processo.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">04</div>
          <div>
            <h3>Implantação</h3>
            <p>Acompanhamos a adoção. Treinamento, ajuste fino, resistência esperada tratada como parte do processo — não como obstáculo.</p>
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
          <div class="case-tag" style="margin-bottom:12px;">MATERIAIS DE CONSTRUÇÃO À SECO · REDE COM 9 LOJAS</div>
          <h3>Rede de Construção</h3>
          <p>Operação baseada em WhatsApp, sem rastreabilidade e sem padrão entre as unidades. Unificação de servidores, implantação de sistema de tickets e padronização de processos nas 9 lojas.</p>
        </div>
        <a href="/cases/rede-construcao/" class="btn-ghost consult-case-ref-btn">Ver case completo</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section section-dark">
  <div class="container cta-final-inner section-animate">
    <h2>Sua operação ainda depende de você para funcionar?</h2>
    <p class="cta-sub">Uma conversa de 30 minutos mostra onde estão os gargalos e o que é possível resolver.</p>
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
