<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Projeto Iluminar — Soluções &amp; TAO</title>
<meta name="description" content="O Projeto Iluminar criou um mecanismo de multiplicação de conhecimento com rastreabilidade. Herdeiro, Irradiador, Corrente da Luz — um modelo replicável para campanhas sociais.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/projeto-iluminar/">
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
<section class="iluminar-page-hero">
  <div class="container">
    <div class="section-animate" style="max-width:720px;">
      <div class="case-meta-line" style="margin-bottom:20px;"><span>INICIATIVA PESSOAL · CARLOS ALMEIDA</span><span class="sep">·</span><span>PATROCINADA PELA MAGIS-TAO</span></div>
      <h1 style="color:#fff;font-size:48px;line-height:1.15;margin-bottom:20px;">Projeto Iluminar</h1>
      <p style="color:rgba(255,255,255,0.75);font-size:19px;line-height:1.75;max-width:600px;">O conhecimento só cumpre seu propósito quando circula. O Projeto Iluminar construiu um mecanismo para que isso aconteça — com escala, rastreabilidade e intenção.</p>
    </div>
  </div>
</section>

<!-- O QUE É -->
<section class="section section-white">
  <div class="container">
    <div class="fundador-bio section-animate">
      <h2>O que é o Projeto Iluminar</h2>
      <p>O Projeto Iluminar nasceu do livro <em>"O Conhecimento (como você nunca conheceu)"</em>, de Carlos Almeida. Não é um projeto editorial — é um movimento de multiplicação.</p>
      <p>O livro não é vendido. É patrocinado. Alguém decide que outra pessoa merece recebê-lo — e ao receber, essa pessoa entra na Corrente da Luz. Pode, se quiser, tornar-se também um Irradiador e levar o conhecimento adiante.</p>
      <p>É uma cadeia com rastreabilidade. Cada livro tem origem. Cada pessoa tem um caminho. Nenhuma ação se perde.</p>
    </div>
  </div>
</section>

<!-- O MECANISMO -->
<section class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">O MECANISMO</span>
      <h2>Como a Corrente da Luz funciona</h2>
    </div>
    <div class="case-actions section-animate" style="max-width:760px;">
      <div class="case-action-item">
        <div class="case-action-num" style="color:#B38E6C;">01</div>
        <div>
          <h3>Irradiador</h3>
          <p>É quem decide patrocinar o livro para alguém. Pode ser uma pessoa, uma empresa ou uma iniciativa. O Irradiador não vende — ele escolhe a quem quer levar o conhecimento. A Magis-TAO é o Irradiador fundador do projeto.</p>
        </div>
      </div>
      <div class="case-action-item">
        <div class="case-action-num" style="color:#B38E6C;">02</div>
        <div>
          <h3>Herdeiro da Luz</h3>
          <p>É quem recebe o livro. Não comprou — foi escolhido. Isso muda a forma como o conhecimento chega. Cada Herdeiro sabe de onde veio o livro que está lendo e pode ver o impacto da corrente da qual faz parte.</p>
        </div>
      </div>
      <div class="case-action-item">
        <div class="case-action-num" style="color:#B38E6C;">03</div>
        <div>
          <h3>Corrente da Luz</h3>
          <p>Cada Herdeiro pode tornar-se Irradiador e levar o livro adiante. A corrente cresce com rastreabilidade completa — é possível ver quem recebeu de quem, quantas gerações o conhecimento percorreu e qual foi o alcance real do movimento.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- O LIVRO -->
<section class="section section-white">
  <div class="container">
    <div class="iluminar-livro-grid section-animate">
      <div class="iluminar-livro-capa">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/livro.jpg" alt="O Conhecimento — como você nunca conheceu" loading="lazy">
      </div>
      <div class="iluminar-livro-texto">
        <div class="case-tag" style="margin-bottom:16px;">O LIVRO</div>
        <h2 style="font-size:28px;margin-bottom:16px;">"O Conhecimento<br>como você nunca conheceu"</h2>
        <p style="color:var(--color-secondary);font-size:17px;line-height:1.8;margin-bottom:24px;">Não é autoajuda. Não é manual. É uma reflexão séria sobre como o conhecimento funciona — como é bloqueado na maioria das pessoas e como pode ser desbloqueado.</p>
        <p style="color:var(--color-secondary);font-size:17px;line-height:1.8;margin-bottom:32px;">O livro é o ponto de partida do movimento. Quem o lê com atenção entende por que o Projeto Iluminar existe e por que o mecanismo funciona da forma que funciona.</p>
        <a href="https://iluminar.social.br/receber-livro/" target="_blank" rel="noopener" class="btn-primary" style="display:inline-block;">Quero ser um Herdeiro da Luz</a>
      </div>
    </div>
  </div>
</section>

<!-- CASE DE INOVAÇÃO -->
<section class="section section-alt">
  <div class="container">
    <div class="section-animate">
      <span class="section-tag">POR QUE ISSO IMPORTA</span>
      <div class="consult-case-ref" style="margin-top:20px;">
        <div class="consult-case-ref-body">
          <h3>Um modelo replicável</h3>
          <p>O mecanismo criado para o Iluminar — patrocinador, receptor, rastreabilidade em cadeia — não é exclusivo de projetos de livro. Com poucas adaptações, o mesmo modelo pode ser aplicado em campanhas sociais, programas de capacitação corporativa, iniciativas de saúde pública ou qualquer movimento que precise escalar com intenção e controle.</p>
          <p style="margin-top:12px;margin-bottom:0;">A rastreabilidade não é detalhe — é o que transforma um ato isolado em movimento mensurável.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA DUPLO -->
<section class="section section-dark">
  <div class="container section-animate">
    <div class="iluminar-cta-grid">
      <div class="iluminar-cta-item">
        <div style="font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:var(--color-accent);margin-bottom:12px;">FAZER PARTE</div>
        <h3 style="color:#fff;margin-bottom:12px;">Quero ser um Herdeiro da Luz</h3>
        <p style="color:rgba(255,255,255,0.65);font-size:15px;margin-bottom:24px;">Cadastre-se para receber o livro gratuitamente e entrar na Corrente da Luz.</p>
        <a href="https://iluminar.social.br/receber-livro/" target="_blank" rel="noopener" class="btn-primary">Quero o livro</a>
      </div>
      <div class="iluminar-cta-divider"></div>
      <div class="iluminar-cta-item">
        <div style="font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:var(--color-accent);margin-bottom:12px;">PORTAL COMPLETO</div>
        <h3 style="color:#fff;margin-bottom:12px;">Conhecer o Projeto Iluminar</h3>
        <p style="color:rgba(255,255,255,0.65);font-size:15px;margin-bottom:24px;">Veja o movimento completo, os Irradiadores ativos e a Corrente da Luz em tempo real.</p>
        <a href="https://iluminar.social.br" target="_blank" rel="noopener" class="btn-ghost" style="border-color:rgba(255,255,255,0.3);color:rgba(255,255,255,0.85);">Visitar iluminar.social.br</a>
      </div>
    </div>
  </div>
</section>

<!-- CONEXÃO COM SOLUÇÕES & TAO -->
<section class="section section-white">
  <div class="container">
    <div class="iluminar-block section-animate">
      <div class="iluminar-icon"><i data-lucide="heart" aria-hidden="true"></i></div>
      <div class="iluminar-label">POR QUE APARECE NESTE SITE</div>
      <h3>Resultado com sentido</h3>
      <p>A filosofia que orienta a Soluções &amp; TAO — resolver problemas com consciência, não só com técnica — nasceu da mesma visão que deu origem ao Iluminar. São iniciativas diferentes, mas partem do mesmo lugar: a crença de que o que você constrói deve ter impacto além de você.</p>
    </div>
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
        <li><a href="/projeto-iluminar/">Projeto Iluminar</a></li>
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
