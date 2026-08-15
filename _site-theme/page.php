<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php wp_title('—', true, 'right'); ?>Soluções &amp; TAO</title>
<meta name="robots" content="noindex">
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

<section style="background:var(--color-primary);padding:160px 0 80px;min-height:60vh;display:flex;align-items:center;">
  <div class="container">
    <div style="max-width:640px;">
      <div style="font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:0.12em;color:var(--color-accent);text-transform:uppercase;margin-bottom:20px;">SOLUÇÕES &amp; TAO</div>
      <h1 style="color:#fff;font-size:42px;line-height:1.2;margin-bottom:20px;"><?php the_title(); ?></h1>
      <p style="color:rgba(255,255,255,0.7);font-size:17px;margin-bottom:36px;">Esta página está sendo preparada. Em breve você encontrará aqui o conteúdo completo.</p>
      <a href="/" class="btn-ghost" style="color:rgba(255,255,255,0.85);border:1px solid rgba(255,255,255,0.3);padding:12px 24px;border-radius:8px;font-weight:500;display:inline-block;">← Voltar para o início</a>
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
    <p>© 2026 Soluções &amp; TAO. Todos os direitos reservados.</p>
  </div>
</footer>

<script>
window.addEventListener('scroll', function() {
  document.getElementById('stao-nav').classList.toggle('scrolled', window.scrollY > 60);
});
document.querySelectorAll('.has-dropdown > a').forEach(function(link) {
  link.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
      e.preventDefault();
      this.parentElement.classList.toggle('dropdown-open');
    }
  });
});
</script>
<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
