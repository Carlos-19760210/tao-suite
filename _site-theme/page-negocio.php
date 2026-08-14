<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consultoria de Negócio — Soluções &amp; TAO</title>
<meta name="description" content="Diagnóstico, posicionamento e planejamento de crescimento. Para donos de negócio que precisam de clareza antes de decidir.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/consultoria/negocio/">
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
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa gratuita</a>
  </div>
  <div class="nav-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- HERO -->
<section class="case-hero-section">
  <div class="container">
    <div class="case-hero-inner section-animate">
      <div class="case-breadcrumb"><a href="/consultoria/">Consultoria</a> <span>/</span> Negócio</div>
      <div class="case-meta-line"><span>CONSULTORIA DE NEGÓCIO</span></div>
      <h1>Seu negócio precisa de clareza antes de crescer.</h1>
      <p class="case-hero-sub">Crescimento sem direção é esforço desperdiçado. O trabalho começa com diagnóstico — entender onde o negócio está, o que funciona e o que trava antes de decidir o próximo passo.</p>
      <div class="hero-btns" style="margin-top:32px;">
        <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa estratégica de 30 min</a>
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
        <div class="consult-perfil-icon"><i data-lucide="help-circle" role="img" aria-hidden="true"></i></div>
        <p>Seu negócio está crescendo mas você não sabe exatamente por quê. E tem receio de mudar alguma coisa e perder o que está funcionando.</p>
      </div>
      <div class="consult-perfil-card section-animate">
        <div class="consult-perfil-icon"><i data-lucide="git-branch" role="img" aria-hidden="true"></i></div>
        <p>Você está diante de uma decisão importante — novo mercado, mudança de modelo, expansão — e precisa de uma visão externa antes de agir.</p>
      </div>
      <div class="consult-perfil-card section-animate">
        <div class="consult-perfil-icon"><i data-lucide="battery-low" role="img" aria-hidden="true"></i></div>
        <p>Você trabalha mais do que nunca mas o resultado não acompanha o esforço. Algo está desalinhado — e não está claro o quê.</p>
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
        <li>Diagnóstico do modelo de negócio atual — como realmente funciona, não como deveria</li>
        <li>Revisão de proposta de valor e clareza de posicionamento</li>
        <li>Desenho de oferta com definição de público e diferenciação real</li>
        <li>Planejamento de crescimento com foco em execução</li>
        <li>Apoio direto em decisões estratégicas com histórico e contexto do negócio</li>
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
            <h3>Diagnóstico</h3>
            <p>Entendemos o negócio como ele é na prática — não como o dono imagina que é. Conversas, dados, observação da operação real.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">02</div>
          <div>
            <h3>Análise</h3>
            <p>Identificamos o que funciona, o que trava e o que está sendo ignorado. Sem filtro e sem defender o que já existe só porque existe.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">03</div>
          <div>
            <h3>Desenho</h3>
            <p>Proposta concreta de modelo, oferta e posicionamento. Com prioridades claras — não uma lista de tudo que seria bom fazer.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">04</div>
          <div>
            <h3>Acompanhamento</h3>
            <p>Implementação com presença. Decisões tomadas junto, ajustes feitos no caminho. Não é consultoria que termina com a entrega do documento.</p>
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
          <p>Rede que cresceu por adição — uma loja aqui, outra ali — sem parar para organizar o que havia construído. O trabalho foi estruturar a operação e dar controle real à gestão central.</p>
        </div>
        <a href="/cases/rede-construcao/" class="btn-ghost consult-case-ref-btn">Ver case completo</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section section-dark">
  <div class="container cta-final-inner section-animate">
    <h2>Pronto para ter clareza sobre o próximo passo?</h2>
    <p class="cta-sub">30 minutos são suficientes para entender onde está o problema e se faz sentido trabalharmos juntos.</p>
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary btn-large">Quero minha conversa de 30 min</a>
    <p class="cta-no-friction">Sem compromisso. Sem formulário longo.</p>
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
