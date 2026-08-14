<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Soluções &amp; TAO — Consultoria de Negócio, Processos e Estratégia</title>
<meta name="description" content="Consultoria que ajuda donos de negócio a organizar a operação, desenhar estratégia e aplicar tecnologia — quando ela faz sentido. 25 anos de experiência real.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://solucoesetao.com.br/">
<link rel="icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<link rel="shortcut icon" type="image/x-icon" href="https://solucoesetao.com.br/favicon.ico">
<meta property="og:title" content="Soluções &amp; TAO — Consultoria de Negócio, Processos e Estratégia">
<meta property="og:description" content="Consultoria com 25 anos de experiência real. Decisões claras, processos que funcionam, resultado que aparece.">
<meta property="og:url" content="https://solucoesetao.com.br">
<meta property="og:type" content="website">
<meta property="og:image" content="https://solucoesetao.com.br/wp-content/themes/solucoesetao/assets/og-image.png">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Soluções &amp; TAO — Consultoria de Negócio, Processos e Estratégia">
<meta name="twitter:description" content="Decisões claras. Processos que funcionam. Resultado que aparece. Converse com a gente.">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "Soluções & TAO",
      "url": "https://solucoesetao.com.br",
      "logo": "https://solucoesetao.com.br/wp-content/themes/solucoesetao/assets/logo.png",
      "description": "Consultoria de negócio, processos e estratégia",
      "foundingDate": "2025",
      "founder": {
        "@type": "Person",
        "name": "Carlos Carvalho Almeida",
        "jobTitle": "Fundador e Consultor Principal"
      },
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+55-11-93934-2091",
        "contactType": "customer service",
        "availableLanguage": "Portuguese"
      }
    },
    {
      "@type": "ProfessionalService",
      "name": "Soluções & TAO Consultoria",
      "url": "https://solucoesetao.com.br",
      "serviceType": ["Consultoria de Negócio", "Consultoria de Processos", "Consultoria Estratégica"],
      "areaServed": {
        "@type": "Country",
        "name": "Brasil"
      },
      "provider": {
        "@type": "Organization",
        "name": "Soluções & TAO"
      }
    }
  ]
}
</script>
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo get_stylesheet_uri(); ?>?v=<?php echo filemtime(get_stylesheet_directory().'/style.css'); ?>">
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
    <li><a href="/" class="nav-active">Home</a></li>
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
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa gratuita</a>
  </div>
  <div class="nav-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- ===== HERO INSTITUCIONAL ===== -->
<section id="hero" class="hero-inst">
  <div class="container">
    <div class="hero-inst-inner section-animate">
      <div class="hero-label">CONSULTORIA DE NEGÓCIO, PROCESSOS E ESTRATÉGIA</div>
      <h1>Decisões claras.<br>Processos que funcionam.<br>Resultado que aparece.</h1>
      <p class="hero-sub">Soluções &amp; TAO é uma consultoria que ajuda donos de negócio a organizar a operação, desenhar estratégia e aplicar tecnologia — quando ela faz sentido.</p>
      <div class="hero-btns">
        <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary">Conversa estratégica de 30 min</a>
        <a href="#pilares" class="btn-ghost">Conhecer a consultoria</a>
      </div>
    </div>
  </div>
</section>

<!-- ===== PILARES ===== -->
<section id="pilares" class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">MODALIDADES</span>
      <h2>Como trabalhamos</h2>
    </div>
    <div class="cards-grid-3">

      <div class="pilar-card-inst section-animate">
        <div class="pilar-icon"><i data-lucide="briefcase" aria-label="Negócio" role="img"></i></div>
        <h3>Consultoria de Negócio</h3>
        <p>Para donos que precisam de clareza estratégica, revisão de modelo de negócio ou direção em momento de decisão.</p>
        <a href="/consultoria/negocio/" class="card-link">Saiba mais <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
      </div>

      <div class="pilar-card-inst section-animate">
        <div class="pilar-icon"><i data-lucide="workflow" aria-label="Processos" role="img"></i></div>
        <h3>Consultoria de Processos</h3>
        <p>Para empresas que cresceram e perderam o controle operacional. Estruturação, revisão e otimização de processos internos.</p>
        <a href="/consultoria/processos/" class="card-link">Saiba mais <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
      </div>

      <div class="pilar-card-inst section-animate">
        <div class="pilar-icon"><i data-lucide="compass" aria-label="Estratégia" role="img"></i></div>
        <h3>Consultoria Estratégica</h3>
        <p>Para quem precisa de direcionamento executivo contínuo. Acompanhamento estratégico de médio e longo prazo.</p>
        <a href="/consultoria/estrategica/" class="card-link">Saiba mais <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
      </div>

    </div>
  </div>
</section>

<!-- ===== NOSSOS PRODUTOS ===== -->
<section id="produto-inst" class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">NOSSOS PRODUTOS</span>
      <h2>Tecnologia que resolve problema real</h2>
    </div>
    <div class="produtos-grid section-animate" style="display:grid;grid-template-columns:1fr;gap:2rem;margin-top:2rem;">

      <div class="produto-destaque-card" style="display:flex;flex-direction:column;justify-content:space-between;">
        <div class="produto-dest-body">
          <div class="produto-dest-icon"><i data-lucide="network" aria-label="TAO Neo" role="img"></i></div>
          <div class="produto-dest-tag">A SUÍTE COMPLETA DE OPERAÇÃO</div>
          <h2>TAO Neo</h2>
          <p>A operação inteira num ecossistema só, com o CRM como coração: cada frente é um módulo completo, todas conversam entre si, e nada se redigita. A parte fiscal (NFC-e) e o SNGPC estão <strong>homologados</strong> — validados lado a lado com a operação real.</p>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px">
            <?php foreach ( [ 'Agente', 'CRM', 'Campanhas', 'Caixa', 'Pós Vendas', 'Estoque', 'Cotações', 'Fórmulas Farmacêuticas' ] as $fr ) : ?>
            <span style="font-family:'JetBrains Mono',monospace;font-size:11.5px;padding:5px 12px;border-radius:16px;background:var(--color-bg-alt);border:1px solid var(--color-border);color:var(--color-secondary)"><?php echo esc_html( $fr ); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="produto-dest-cta" style="margin-top:18px">
          <a href="/tao-neo/" class="btn-accent">Conhecer o ecossistema TAO Neo</a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ===== CASES ===== -->
<section id="cases" class="section section-white">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">CASES</span>
      <h2>Onde aplicamos na prática</h2>
    </div>
    <div class="cards-grid-2">

      <div class="case-card-inst section-animate">
        <div class="case-tag">CLIENTE DESDE 2018</div>
        <h3>Magis-TAO</h3>
        <p>Farmácia de manipulação em Cotia/SP que cresce 25% ao ano aplicando nossos métodos — e hoje opera com o ecossistema TAO Neo completo: do atendimento no WhatsApp ao fiscal e SNGPC, migrada do sistema anterior com confronto diário, item a item.</p>
        <a href="/cases/magis-tao/" class="card-link">Ver case completo <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
      </div>

      <div class="case-card-inst section-animate">
        <div class="case-tag">CONSULTORIA 2023</div>
        <h3>Rede de Construção</h3>
        <p>Rede com 9 lojas onde Carlos atuou como Diretor de Operações. Transformação operacional completa: definição de funções, revisão de processos, unificação de servidores e implementação de ferramentas de gestão.</p>
        <a href="/cases/rede-construcao/" class="card-link">Ver case completo <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
      </div>

    </div>
  </div>
</section>

<!-- ===== SOBRE PREVIEW ===== -->
<section id="sobre" class="section section-alt">
  <div class="container">
    <div class="section-header section-animate">
      <span class="section-tag">QUEM ESTÁ POR TRÁS</span>
    </div>
    <div class="sobre-preview-grid section-animate">
      <div class="sobre-foto">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/carlos.webp" style="object-position:center 15%" alt="Carlos Carvalho Almeida — Fundador Soluções &amp; TAO" loading="lazy">
      </div>
      <div class="sobre-texto">
        <div class="sobre-nome">Carlos Carvalho Almeida</div>
        <div class="sobre-cargo">Fundador — Soluções &amp; TAO</div>
        <p>25 anos em Accenture, Capgemini e Everis, atendendo Ambev, Bradesco, Sky, TIM, Natura e Serasa. Aprendi consultoria estratégica dentro das maiores organizações do Brasil — e aprendi o que ela não consegue ensinar só dentro de sala.</p>
        <p>Em 2018 fundei a Magis-TAO. Em 2023 atuei como Diretor de Operações em uma rede de materiais de construção à seco com 9 lojas. Em 2024 nasceu a Soluções &amp; TAO: para aplicar em outros negócios o método que já havia provado funcionar nos meus.</p>
        <a href="/sobre/fundador/" class="card-link" style="margin-top:16px;display:inline-flex;align-items:center;gap:6px;color:var(--color-accent);font-weight:500">Ver bio completa <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
      </div>
    </div>
  </div>
</section>

<!-- ===== PROJETO ILUMINAR ===== -->
<section id="iluminar" class="section section-white">
  <div class="container">
    <div class="iluminar-block section-animate">
      <div class="iluminar-icon"><i data-lucide="heart" aria-label="Iluminar" role="img"></i></div>
      <div class="iluminar-label">UMA PALAVRA SOBRE CONSCIÊNCIA</div>
      <h3>Projeto Iluminar</h3>
      <p>Somos consultores que acreditam em resultado com sentido. Essa visão se manifesta também no Projeto Iluminar — iniciativa pessoal de Carlos Almeida, patrocinada pela Magis-TAO, sobre conhecimento e consciência.</p>
      <a href="/projeto-iluminar/" class="iluminar-link">Saiba mais sobre o projeto <i data-lucide="arrow-right" style="width:14px;height:14px;vertical-align:middle"></i></a>
    </div>
  </div>
</section>

<!-- ===== CTA FINAL ===== -->
<section class="section section-alt proposta-personalizada">
  <div class="container" style="text-align:center;">
    <p class="proposta-titulo">Cada projeto é desenhado para o seu caso.</p>
    <p class="proposta-sub">Fale conosco para receber sua proposta personalizada. <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="proposta-link">Falar pelo WhatsApp</a></p>
  </div>
</section>

<section id="cta-final" class="section section-dark">
  <div class="container cta-final-inner section-animate">
    <h2>Pronto para tomar decisões com mais clareza?</h2>
    <p class="cta-sub">Uma conversa de 30 minutos pode mostrar exatamente onde estão os gargalos que travam o crescimento do seu negócio.</p>
    <div class="cta-scarcity"><i data-lucide="timer" aria-label="Tempo" role="img"></i> Atendemos no máximo 8 empresas por mês para garantir qualidade no trabalho.</div>
    <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="btn-primary btn-large">Quero minha conversa estratégica gratuita</a>
    <p class="cta-no-friction">Sem compromisso. Sem formulário longo. Só uma conversa.</p>
    <div class="cta-channels">
      <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank" class="cta-channel">
        <svg viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        WhatsApp
      </a>
      <a href="mailto:contato@solucoesetao.com.br" class="cta-channel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        E-mail
      </a>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer id="footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <img src="<?php echo get_template_directory_uri(); ?>/assets/logo.png" alt="Soluções &amp; TAO" loading="lazy">
      <p>Consultoria de negócio, processos e estratégia. Decisões claras, processos que funcionam, resultado que aparece.</p>
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
        <li><a href="/contato/">Fale Conosco</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© 2026 Soluções &amp; TAO. Todos os direitos reservados. <a href="/politica-de-privacidade/">Política de Privacidade</a> · <a href="/termos-de-uso/">Termos de Uso</a></p>
  </div>
</footer>

<!-- ===== BARRA MOBILE ===== -->
<div class="mobile-cta-bar" id="mobileCta">
  <a href="https://wa.me/5511939342091?text=Ol%C3%A1!%20Gostaria%20de%20agendar%20minha%20conversa%20estrat%C3%A9gica%20gratuita%20de%2030%20min%20com%20a%20Solu%C3%A7%C3%B5es%20%26%20TAO." target="_blank">
    <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="vertical-align:middle;margin-right:8px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    Conversa gratuita de 30 min
  </a>
</div>

<script>
const stickyCTA = document.getElementById('mobileCta');
window.addEventListener('scroll', function() {
  document.getElementById('stao-nav').classList.toggle('scrolled', window.scrollY > 60);
  if (stickyCTA) {
    const scrolled = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
    stickyCTA.classList.toggle('visible', scrolled > 40 && scrolled < 95);
  }
});

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.section-animate').forEach(el => observer.observe(el));

// Dropdown mobile toggle
document.querySelectorAll('.has-dropdown > a').forEach(link => {
  link.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
      e.preventDefault();
      this.parentElement.classList.toggle('dropdown-open');
    }
  });
});

document.querySelectorAll('a[href*="wa.me"]').forEach(btn => {
  btn.addEventListener('click', () => {
    if (typeof gtag !== 'undefined') {
      gtag('event', 'conversion', { event_category: 'CTA', event_label: 'WhatsApp' });
    }
  });
});
</script>

<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
