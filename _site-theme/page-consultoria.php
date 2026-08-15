<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consultoria — Soluções &amp; TAO</title>
<meta name="description" content="Consultoria de negócio, processos e estratégia para donos e diretores que precisam de clareza antes de decidir. Cada projeto começa com diagnóstico real.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/consultoria/">
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
      <div class="case-meta-line"><span>CONSULTORIA</span></div>
      <h1>Cada negócio tem um problema diferente.<br>O trabalho começa por entender qual é o seu.</h1>
      <p class="case-hero-sub">Não existe receita pronta. O que funciona para um negócio pode ser indiferente ou prejudicial para outro. Por isso o trabalho sempre começa com diagnóstico — real, não protocolar.</p>
      <div class="hero-btns" style="margin-top:32px;">
        <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa estratégica de 30 min</a>
      </div>
    </div>
  </div>
</section>

<!-- INTRO — COMO TRABALHAMOS -->
<section class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">COMO TRABALHAMOS</span>
      <h2>Presença, não relatório.</h2>
    </div>
    <div class="consult-intro-grid section-animate">
      <div class="consult-intro-text">
        <p>A maioria das consultorias entrega um documento. Nós ficamos até o problema estar resolvido.</p>
        <p>Isso significa estar na operação, nas decisões, nos momentos em que as coisas saem do planejado. Não como observador externo que anota e vai embora — como alguém que tem responsabilidade sobre o resultado.</p>
        <p>Trabalhamos com donos de negócio e diretores que precisam de uma visão externa comprometida — não de mais um parecer técnico para arquivar.</p>
      </div>
      <div class="consult-intro-pilares">
        <div class="consult-pilar-item">
          <i data-lucide="search" role="img" aria-hidden="true"></i>
          <div>
            <strong>Diagnóstico real</strong>
            <span>O trabalho começa por entender o que está acontecendo de fato — não o que parece estar.</span>
          </div>
        </div>
        <div class="consult-pilar-item">
          <i data-lucide="target" role="img" aria-hidden="true"></i>
          <div>
            <strong>Foco em execução</strong>
            <span>Recomendações que podem ser implementadas pelo negócio como ele é hoje, não como deveria ser.</span>
          </div>
        </div>
        <div class="consult-pilar-item">
          <i data-lucide="users" role="img" aria-hidden="true"></i>
          <div>
            <strong>Presença contínua</strong>
            <span>Não é consultoria de crise. É uma relação de trabalho que se mantém ao longo do tempo.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MODALIDADES -->
<section class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">MODALIDADES</span>
      <h2>Onde você está agora?</h2>
      <p class="section-sub">Cada modalidade parte de um momento diferente do negócio. Escolha a que mais se aproxima do seu — ou fale com a gente para entender qual faz sentido.</p>
    </div>

    <div class="consult-modal-grid">

      <a href="/consultoria/negocio/" class="consult-modal-card section-animate">
        <div class="consult-modal-header">
          <div class="consult-modal-icon"><i data-lucide="building-2" role="img" aria-hidden="true"></i></div>
          <span class="consult-modal-label">CONSULTORIA DE NEGÓCIO</span>
        </div>
        <h3>Clareza antes de crescer.</h3>
        <p>Para donos que precisam entender onde o negócio está de fato — modelo, posicionamento, oferta — antes de decidir o próximo passo. Começa com diagnóstico, termina com um plano que pode ser executado.</p>
        <ul class="consult-modal-list">
          <li>Diagnóstico do modelo atual</li>
          <li>Revisão de proposta de valor e posicionamento</li>
          <li>Desenho da oferta e priorização de crescimento</li>
          <li>Acompanhamento da implementação</li>
        </ul>
        <div class="consult-modal-cta">Ver mais <i data-lucide="arrow-right" aria-hidden="true"></i></div>
      </a>

      <a href="/consultoria/processos/" class="consult-modal-card section-animate">
        <div class="consult-modal-header">
          <div class="consult-modal-icon"><i data-lucide="git-branch" role="img" aria-hidden="true"></i></div>
          <span class="consult-modal-label">CONSULTORIA DE PROCESSOS</span>
        </div>
        <h3>Operação que funciona sem você.</h3>
        <p>Para empresas que cresceram e perderam o controle operacional. A operação depende da sua presença? Cada pessoa faz do seu jeito? O problema não é a equipe — é a ausência de processos claros.</p>
        <ul class="consult-modal-list">
          <li>Mapeamento dos processos reais</li>
          <li>Identificação de gargalos e pontos de falha</li>
          <li>Redesenho com foco em autonomia da equipe</li>
          <li>Implantação com acompanhamento</li>
        </ul>
        <div class="consult-modal-cta">Ver mais <i data-lucide="arrow-right" aria-hidden="true"></i></div>
      </a>

      <a href="/consultoria/estrategica/" class="consult-modal-card section-animate">
        <div class="consult-modal-header">
          <div class="consult-modal-icon"><i data-lucide="compass" role="img" aria-hidden="true"></i></div>
          <span class="consult-modal-label">CONSULTORIA ESTRATÉGICA</span>
        </div>
        <h3>Direcionamento para decisões que pesam.</h3>
        <p>Para diretores e sócios que precisam de uma presença estratégica contínua. Algumas decisões não podem errar — e não dá para tomar sozinho com o mesmo nível de clareza de quem está de fora.</p>
        <ul class="consult-modal-list">
          <li>Acompanhamento estratégico mensal ou quinzenal</li>
          <li>Revisão de direção, prioridades e resultados</li>
          <li>Apoio em decisões críticas com visão externa</li>
          <li>Identificação antecipada de riscos</li>
        </ul>
        <div class="consult-modal-cta">Ver mais <i data-lucide="arrow-right" aria-hidden="true"></i></div>
      </a>

    </div>
  </div>
</section>

<!-- NÃO SABE QUAL ESCOLHER -->
<section class="section section-white">
  <div class="container">
    <div class="consult-duvida-block section-animate">
      <div class="consult-duvida-icon"><i data-lucide="help-circle" role="img" aria-hidden="true"></i></div>
      <div class="consult-duvida-body">
        <h2>Não sabe qual modalidade faz sentido para você?</h2>
        <p>Não precisa saber. A conversa inicial de 30 minutos existe exatamente para isso — entender onde o negócio está e qual é o caminho que faz mais sentido para o seu momento.</p>
        <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary" style="margin-top:20px;display:inline-block;">Quero entender qual é o meu caminho</a>
      </div>
    </div>
  </div>
</section>

<!-- CASES -->
<section class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">CASES</span>
      <h2>Negócios reais. Resultados concretos.</h2>
    </div>
    <div class="cards-grid-3">
      <a href="/cases/magis-tao/" class="case-card-link section-animate">
        <div class="case-card">
          <div class="case-tag">FARMÁCIA DE MANIPULAÇÃO</div>
          <h3>Magis-TAO</h3>
          <p>Seis anos de acompanhamento estratégico contínuo. Da estruturação do negócio à automação do atendimento com IA.</p>
          <span class="case-card-more">Ver case <i data-lucide="arrow-right" aria-hidden="true"></i></span>
        </div>
      </a>
      <a href="/cases/rede-construcao/" class="case-card-link section-animate">
        <div class="case-card">
          <div class="case-tag">REDE COM 9 LOJAS</div>
          <h3>Rede de Construção</h3>
          <p>Operação sem rastreabilidade, processos variando entre unidades. Padronização, sistema e controle centralizado.</p>
          <span class="case-card-more">Ver case <i data-lucide="arrow-right" aria-hidden="true"></i></span>
        </div>
      </a>
      <a href="/cases/industrializacao/" class="case-card-link section-animate">
        <div class="case-card">
          <div class="case-tag">INDUSTRIALIZAÇÃO DE DEMANDAS</div>
          <h3>Industrialização</h3>
          <p>Como escalar a entrega de serviços sem crescer proporcionalmente a equipe. Processo que vira produto.</p>
          <span class="case-card-more">Ver case <i data-lucide="arrow-right" aria-hidden="true"></i></span>
        </div>
      </a>
    </div>
    <div style="text-align:center;margin-top:32px;" class="section-animate">
      <a href="/cases/" class="btn-ghost">Ver todos os cases</a>
    </div>
  </div>
</section>

<!-- CTA FINAL -->
<section class="section section-dark">
  <div class="container cta-final-inner section-animate">
    <h2>O primeiro passo é uma conversa.</h2>
    <p class="cta-sub">30 minutos para entender onde está o problema e se faz sentido trabalharmos juntos. Sem compromisso e sem formulário longo.</p>
    <a href="https://wa.me/5511994604521?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary btn-large">Quero minha conversa de 30 min</a>
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
        <li><a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a></li>
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
