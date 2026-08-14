<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Case Projeto Iluminar — Soluções &amp; TAO</title>
<meta name="description" content="Arquitetura de um mecanismo de multiplicação de conhecimento com rastreabilidade. Um modelo criado do zero — replicável para campanhas sociais e corporativas.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/cases/iluminar/">
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
<section class="case-hero-section">
  <div class="container">
    <div class="case-hero-inner section-animate">
      <div class="case-breadcrumb"><a href="/cases/">Cases</a> <span>/</span> Projeto Iluminar</div>
      <div class="case-meta-line">
        <span>Arquitetura de sistema</span>
        <span class="sep">·</span>
        <span>Iniciativa social</span>
        <span class="sep">·</span>
        <span>Modelo original</span>
      </div>
      <h1>Um mecanismo de multiplicação criado do zero.</h1>
      <p class="case-hero-sub">O Projeto Iluminar não é só uma iniciativa de distribuição de livros. É a prova de que é possível desenhar um sistema de propagação com rastreabilidade, intenção e escala — e que esse modelo pode ser replicado em outros contextos.</p>
    </div>
  </div>
</section>

<!-- CONTEXTO -->
<section class="section section-white">
  <div class="container case-content">
    <div class="section-animate">
      <h2>O contexto</h2>
      <p>Conhecimento que não circula não cumpre nenhum propósito. O livro <em>"O Conhecimento (como você nunca conheceu)"</em> existia — mas um livro sem um mecanismo de distribuição intencional chega a quem já está procurando, não a quem precisa.</p>
      <p>O desafio não era publicar. Era criar um sistema onde o conhecimento se propagasse por escolha, não por acaso — e onde cada movimento fosse rastreável, de forma que o impacto real pudesse ser medido.</p>
      <p>Não havia modelo pronto para isso. Foi preciso criar.</p>
    </div>
  </div>
</section>

<!-- PROBLEMAS -->
<section class="section section-alt">
  <div class="container case-content">
    <div class="section-animate">
      <h2>O problema que o modelo resolve</h2>
      <ul class="case-list">
        <li>Iniciativas sociais escalam por volume, não por intenção — chegam a muitos, impactam poucos</li>
        <li>Distribuição gratuita sem rastreabilidade não permite medir impacto real</li>
        <li>Quem recebe não tem contexto de por que recebeu — o gesto se perde</li>
        <li>Quem patrocina não sabe o que aconteceu com o que financiou</li>
        <li>Não existe mecanismo natural de propagação — cada receptor é um ponto final</li>
      </ul>
    </div>
  </div>
</section>

<!-- AÇÃO -->
<section class="section section-white">
  <div class="container case-content">
    <div class="section-animate">
      <h2>O que foi desenhado</h2>
      <div class="case-actions">
        <div class="case-action-item">
          <div class="case-action-num">01</div>
          <div>
            <h3>Arquitetura do mecanismo</h3>
            <p>Criação do modelo em três papéis: Irradiador (quem patrocina), Herdeiro da Luz (quem recebe) e a Corrente da Luz (a cadeia que se forma). Cada papel tem uma função clara e uma jornada própria dentro do sistema.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">02</div>
          <div>
            <h3>Rastreabilidade completa</h3>
            <p>Cada livro tem origem registrada. Cada Herdeiro sabe de qual Irradiador veio. Cada geração da corrente é visível. Não é só distribuição — é um grafo de propagação auditável.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">03</div>
          <div>
            <h3>Propagação por escolha</h3>
            <p>Herdeiros podem tornar-se Irradiadores — mas por decisão própria, não por obrigação. Isso preserva a intenção do gesto e filtra a propagação por genuíno interesse, não por pressão social.</p>
          </div>
        </div>
        <div class="case-action-item">
          <div class="case-action-num">04</div>
          <div>
            <h3>Portal e infraestrutura</h3>
            <p>Desenvolvimento do portal <a href="https://iluminar.social.br" target="_blank" rel="noopener" style="color:var(--color-accent)">iluminar.social.br</a> para operacionalizar o mecanismo — cadastro de Herdeiros, gestão de Irradiadores e visualização da corrente.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- RESULTADOS -->
<section class="section section-alt">
  <div class="container case-content">
    <div class="section-animate">
      <h2>O que o modelo entregou</h2>
      <ul class="case-list case-list-check">
        <li>Sistema operacional com mecanismo de propagação rastreável</li>
        <li>Cada livro distribuído com origem, destino e histórico registrados</li>
        <li>Modelo validado em ambiente real — não é conceito, é operação ativa</li>
        <li>Arquitetura documentada e replicável com adaptações mínimas</li>
      </ul>
    </div>
  </div>
</section>

<!-- APRENDIZADO -->
<section class="section section-white">
  <div class="container">
    <div class="case-learning-block section-animate">
      <i data-lucide="quote" aria-hidden="true"></i>
      <blockquote>Qualquer iniciativa pode escalar por volume. Escalar com rastreabilidade e intenção exige arquitetura. A diferença entre um gesto e um movimento é o sistema que existe por trás.</blockquote>
    </div>
  </div>
</section>

<!-- REPLICABILIDADE -->
<section class="section section-alt">
  <div class="container">
    <div class="section-animate">
      <span class="section-tag">APLICAÇÃO DO MODELO</span>
      <div class="consult-case-ref" style="margin-top:20px;">
        <div class="consult-case-ref-body">
          <h3>Onde esse modelo pode ser aplicado</h3>
          <p>O mecanismo criado para o Iluminar — patrocinador, receptor, cadeia rastreável — não é exclusivo de projetos de livro. Com poucas adaptações, funciona para: programas de capacitação corporativa onde colaboradores treinam outros colaboradores; campanhas de saúde com propagação por indicação rastreada; iniciativas de responsabilidade social com impacto auditável; ou qualquer projeto que precise escalar com intenção e medir o que realmente chegou.</p>
        </div>
        <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Tenho%20interesse%20em%20conversar%20sobre%20o%20modelo%20Iluminar." target="_blank" class="btn-ghost consult-case-ref-btn">Quero conversar sobre isso</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section section-dark">
  <div class="container cta-final-inner section-animate">
    <h2>Precisa criar um sistema que não existe ainda?</h2>
    <p class="cta-sub">Às vezes o problema não é organizar o que existe — é desenhar o que precisa ser criado. Uma conversa de 30 minutos mostra se é o caso.</p>
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
        <li><a href="/projeto-iluminar/">Projeto Iluminar</a></li>
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
