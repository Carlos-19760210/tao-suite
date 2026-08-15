<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
/**
 * Template Name: Política de Privacidade
 * Description: Política de Privacidade LGPD-compliant
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Política de Privacidade | Soluções &amp; TAO</title>
<meta name="description" content="Política de Privacidade da Soluções &amp; TAO — como coletamos, usamos, armazenamos e protegemos seus dados pessoais em conformidade com a LGPD.">
<meta name="robots" content="noindex, nofollow">
<link rel="canonical" href="<?php echo esc_url( home_url('/politica-de-privacidade/') ); ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo esc_url( home_url('/politica-de-privacidade/') ); ?>">
<meta property="og:title" content="Política de Privacidade | Soluções &amp; TAO">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="Soluções &amp; TAO">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?php echo get_stylesheet_uri(); ?>?v=<?php echo filemtime( get_stylesheet_directory() . '/style.css' ); ?>">

<?php wp_head(); ?>

<style>
:root {
  --color-primary: #152C42;
  --color-accent:  #B38E6C;
  --color-bg-alt:  #F5F4F2;
  --radius:        12px;
}

/* HERO LEGAL --------------------------------------------------------- */
.legal-hero {
  background: var(--color-primary);
  color: #fff;
  padding: 96px 0 52px;
  text-align: center;
}
.legal-hero .legal-eyebrow {
  display: inline-block;
  background: rgba(179,142,108,.2);
  color: var(--color-accent);
  border: 1px solid rgba(179,142,108,.4);
  border-radius: 20px;
  padding: 5px 16px;
  font-size: .82rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  margin-bottom: 18px;
}
.legal-hero h1 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 800;
  margin-bottom: 12px;
}
.legal-hero .legal-date {
  color: rgba(255,255,255,.6);
  font-size: .9rem;
}

/* CONTEÚDO LEGAL ----------------------------------------------------- */
.legal-body {
  padding: 72px 0 96px;
  background: #fff;
}
.legal-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 0 24px;
}
.legal-container h2 {
  font-family: 'Playfair Display', serif;
  font-size: 1.55rem;
  color: var(--color-primary);
  margin: 48px 0 16px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-bg-alt);
}
.legal-container h2:first-of-type { margin-top: 0; }
.legal-container h3 {
  font-size: 1.05rem;
  color: var(--color-primary);
  margin: 28px 0 10px;
  font-weight: 700;
}
.legal-container p {
  font-size: .97rem;
  color: #445;
  line-height: 1.8;
  margin: 0 0 16px;
}
.legal-container ul, .legal-container ol {
  margin: 0 0 18px 0;
  padding-left: 24px;
}
.legal-container li {
  font-size: .97rem;
  color: #445;
  line-height: 1.75;
  margin-bottom: 6px;
}
.legal-container strong { color: var(--color-primary); }
.legal-container a { color: var(--color-accent); text-decoration: underline; }
.legal-container a:hover { color: var(--color-primary); }

.legal-highlight {
  background: var(--color-bg-alt);
  border-left: 4px solid var(--color-accent);
  border-radius: 0 8px 8px 0;
  padding: 18px 22px;
  margin: 24px 0;
}
.legal-highlight p { margin: 0; font-size: .93rem; }

.legal-table-wrap { overflow-x: auto; margin: 24px 0; }
.legal-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .9rem;
  min-width: 480px;
}
.legal-table th {
  background: var(--color-primary);
  color: #fff;
  padding: 11px 16px;
  text-align: left;
  font-weight: 600;
}
.legal-table td {
  padding: 11px 16px;
  border-bottom: 1px solid #e8e6e2;
  color: #445;
  vertical-align: top;
}
.legal-table tr:nth-child(even) td { background: var(--color-bg-alt); }
</style>
</head>
<body <?php body_class('page-politica-privacidade'); ?>>
<?php wp_body_open(); ?>

<!-- NAV -->
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
    <a href="https://wa.me/5511994604521?text=Ol%C3%A1%2C%20tenho%20interesse%20no%20TAO%20CRM." target="_blank" class="btn-primary">Conversa gratuita</a>
  </div>
  <div class="nav-toggle" id="navToggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- HERO LEGAL -->
<section class="legal-hero">
  <div class="container">
    <span class="legal-eyebrow">LGPD &mdash; Lei 13.709/2018</span>
    <h1>Política de Privacidade</h1>
    <p class="legal-date">Última atualização: 29 de maio de 2026 &nbsp;|&nbsp; Vigência: 29/05/2026</p>
  </div>
</section>

<!-- CONTEÚDO -->
<section class="legal-body">
  <div class="legal-container">

    <div class="legal-highlight">
      <p>Esta Política de Privacidade descreve como a <strong>Soluções &amp; TAO</strong> coleta, usa, armazena e protege seus dados pessoais em conformidade com a <strong>Lei Geral de Proteção de Dados Pessoais (LGPD) — Lei nº 13.709/2018</strong>. Ao utilizar nossos serviços, você concorda com os termos desta política.</p>
    </div>

    <!-- 1 -->
    <h2>1. Identificação da Controladora</h2>
    <p>Os seus dados pessoais são tratados por:</p>
    <ul>
      <li><strong>Razão Social:</strong> Soluções &amp; TAO</li>
      <li><strong>Nome Fantasia:</strong> Soluções &amp; TAO / solucoesetao.com.br</li>
      <li><strong>CNPJ:</strong> Não divulgado publicamente neste documento. Disponível mediante solicitação formal.</li>
      <li><strong>Endereço eletrônico:</strong> <a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a></li>
      <li><strong>WhatsApp:</strong> <a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a></li>
      <li><strong>Site:</strong> <a href="https://solucoesetao.com.br" target="_blank">solucoesetao.com.br</a></li>
    </ul>

    <!-- 2 -->
    <h2>2. Dados Pessoais Coletados</h2>
    <p>Podemos coletar os seguintes dados, dependendo do serviço utilizado:</p>

    <h3>2.1 Dados fornecidos diretamente por você</h3>
    <ul>
      <li><strong>Nome completo</strong></li>
      <li><strong>Endereço de e-mail</strong></li>
      <li><strong>Número de WhatsApp / telefone</strong></li>
      <li><strong>Nome da empresa</strong> (quando aplicável)</li>
      <li><strong>Conteúdo das mensagens</strong> enviadas via WhatsApp, formulários ou e-mail</li>
      <li><strong>Informações sobre seu negócio</strong> compartilhadas durante consultorias ou demos</li>
    </ul>

    <h3>2.2 Dados coletados automaticamente</h3>
    <ul>
      <li>Endereço IP e dados de geolocalização aproximada</li>
      <li>Tipo e versão de navegador e sistema operacional</li>
      <li>Páginas visitadas, tempo de permanência e origem do acesso</li>
      <li>Dados de cookies e tecnologias similares (ver Seção 9)</li>
    </ul>

    <h3>2.3 Dados coletados via WhatsApp (plataformas TAO Neo e TAO CRM)</h3>
    <ul>
      <li>Número de telefone / WhatsApp</li>
      <li>Histórico de conversas com o assistente virtual</li>
      <li>Respostas a perguntas de qualificação</li>
      <li>Metadados de mensagens (horário, status de leitura)</li>
    </ul>

    <!-- 3 -->
    <h2>3. Finalidade do Tratamento</h2>
    <p>Seus dados são tratados para as seguintes finalidades:</p>

    <div class="legal-table-wrap">
      <table class="legal-table">
        <thead>
          <tr>
            <th>Finalidade</th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Prestação de serviço</td>
            <td>Operar as plataformas TAO Neo e TAO CRM, executar consultorias contratadas e suporte técnico.</td>
          </tr>
          <tr>
            <td>Comunicação comercial</td>
            <td>Responder dúvidas, enviar propostas, fazer follow-up de negociações em andamento.</td>
          </tr>
          <tr>
            <td>Melhoria do produto</td>
            <td>Analisar padrões de uso para aprimorar funcionalidades das plataformas.</td>
          </tr>
          <tr>
            <td>Cumprimento legal</td>
            <td>Atender obrigações previstas em lei, regulamentos e ordens judiciais.</td>
          </tr>
          <tr>
            <td>Marketing</td>
            <td>Envio de conteúdo relevante, novidades e promoções — sempre com possibilidade de descadastro.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 4 -->
    <h2>4. Base Legal para o Tratamento</h2>
    <p>O tratamento dos seus dados se apoia nas seguintes bases legais previstas na LGPD (art. 7º):</p>
    <ul>
      <li><strong>Consentimento (art. 7º, I):</strong> quando você preenche formulários, inicia conversa via WhatsApp ou aceita cookies não essenciais.</li>
      <li><strong>Execução de contrato (art. 7º, V):</strong> para prestação dos serviços contratados (consultoria, TAO Neo, TAO CRM).</li>
      <li><strong>Cumprimento de obrigação legal (art. 7º, II):</strong> para atender exigências fiscais, trabalhistas ou regulatórias.</li>
      <li><strong>Interesse legítimo (art. 7º, IX):</strong> para prevenção a fraudes, segurança da plataforma e melhoria de produto — desde que não prevaleçam sobre seus direitos fundamentais.</li>
    </ul>

    <!-- 5 -->
    <h2>5. Compartilhamento de Dados</h2>
    <p>Seus dados podem ser compartilhados com os seguintes operadores e parceiros, estritamente para viabilizar a prestação dos nossos serviços:</p>

    <div class="legal-table-wrap">
      <table class="legal-table">
        <thead>
          <tr>
            <th>Operador / Parceiro</th>
            <th>Função</th>
            <th>Dados Envolvidos</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Evolution API</strong></td>
            <td>Gateway de integração com o WhatsApp Business API para envio e recebimento de mensagens.</td>
            <td>Número de WhatsApp, conteúdo das mensagens</td>
          </tr>
          <tr>
            <td><strong>Supabase</strong></td>
            <td>Banco de dados em nuvem utilizado para armazenamento de leads, conversas e dados operacionais do CRM.</td>
            <td>Nome, WhatsApp, e-mail, histórico de conversas, dados do pipeline</td>
          </tr>
          <tr>
            <td><strong>N8N (self-hosted)</strong></td>
            <td>Plataforma de automação para orquestração de fluxos (qualificação de leads, notificações, integrações).</td>
            <td>Dados necessários para execução do fluxo configurado</td>
          </tr>
          <tr>
            <td><strong>Hostinger VPS</strong></td>
            <td>Infraestrutura de hospedagem onde as plataformas são executadas.</td>
            <td>Todos os dados processados pelas plataformas</td>
          </tr>
          <tr>
            <td><strong>Google Analytics / Search Console</strong></td>
            <td>Análise de tráfego e desempenho do site (dados anonimizados).</td>
            <td>IP anonimizado, comportamento de navegação</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p><strong>Importante:</strong> Não vendemos, alugamos ou cedemos seus dados pessoais a terceiros para fins de marketing sem seu consentimento expresso.</p>

    <!-- 6 -->
    <h2>6. Prazo de Retenção dos Dados</h2>
    <p>Seus dados são retidos pelo tempo necessário para cumprir as finalidades para as quais foram coletados, observando os seguintes critérios:</p>
    <ul>
      <li><strong>Dados de contato e comerciais:</strong> mantidos enquanto houver relação ativa ou potencial comercial, e por até <strong>5 anos</strong> após o encerramento.</li>
      <li><strong>Histórico de conversas WhatsApp:</strong> retido por até <strong>2 anos</strong> após o encerramento da relação, salvo obrigação legal diversa.</li>
      <li><strong>Dados de contrato e faturamento:</strong> <strong>5 anos</strong>, conforme exigência fiscal (Lei nº 9.430/96).</li>
      <li><strong>Logs de acesso à plataforma:</strong> <strong>6 meses</strong>, conforme Marco Civil da Internet (Lei nº 12.965/14).</li>
      <li><strong>Dados de cookies:</strong> conforme período configurado em cada cookie (ver Seção 9).</li>
    </ul>
    <p>Após o encerramento do prazo aplicável, os dados são eliminados de forma segura ou anonimizados.</p>

    <!-- 7 -->
    <h2>7. Direitos do Titular dos Dados</h2>
    <p>Nos termos do art. 18 da LGPD, você possui os seguintes direitos em relação aos seus dados pessoais:</p>
    <ul>
      <li><strong>Confirmação e acesso:</strong> saber se tratamos seus dados e obter cópia deles.</li>
      <li><strong>Correção:</strong> solicitar a correção de dados incompletos, inexatos ou desatualizados.</li>
      <li><strong>Anonimização, bloqueio ou eliminação:</strong> de dados desnecessários, excessivos ou tratados em desconformidade com a LGPD.</li>
      <li><strong>Portabilidade:</strong> receber seus dados em formato estruturado e interoperável.</li>
      <li><strong>Eliminação:</strong> pedir a exclusão dos dados tratados com base no consentimento, ressalvadas obrigações legais.</li>
      <li><strong>Revogação do consentimento:</strong> retirar seu consentimento a qualquer momento, sem prejuízo da licitude dos tratamentos realizados anteriormente.</li>
      <li><strong>Oposição:</strong> opor-se ao tratamento realizado com base no interesse legítimo, se este não prevalecer sobre seus direitos.</li>
      <li><strong>Informação sobre compartilhamento:</strong> saber com quais entidades seus dados foram compartilhados.</li>
      <li><strong>Revisão de decisões automatizadas:</strong> solicitar revisão humana de decisões tomadas exclusivamente por sistemas automatizados.</li>
    </ul>

    <!-- 8 -->
    <h2>8. Como Exercer Seus Direitos</h2>
    <p>Para exercer qualquer direito previsto nesta política ou na LGPD, entre em contato conosco pelos seguintes canais:</p>
    <div class="legal-highlight">
      <p>
        <strong>E-mail:</strong> <a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a><br>
        <strong>WhatsApp:</strong> <a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a><br>
        <strong>Assunto da mensagem:</strong> "LGPD — [tipo de solicitação]" (ex.: "LGPD — Acesso aos meus dados")
      </p>
    </div>
    <p>Responderemos sua solicitação no prazo de <strong>15 dias úteis</strong> a contar do recebimento. Em casos de maior complexidade, esse prazo poderá ser prorrogado por igual período, com comunicação prévia ao titular.</p>

    <!-- 9 -->
    <h2>9. Cookies e Tecnologias de Rastreamento</h2>
    <p>Utilizamos cookies e tecnologias similares para melhorar sua experiência no site. Os cookies podem ser:</p>
    <ul>
      <li><strong>Essenciais:</strong> necessários para o funcionamento do site (login, sessão, preferências). Não podem ser desativados.</li>
      <li><strong>Analíticos:</strong> coletam informações sobre como você usa o site (Google Analytics). Podem ser recusados sem impacto funcional.</li>
      <li><strong>De marketing:</strong> utilizados para personalizar anúncios e medir conversões. Requerem seu consentimento expresso.</li>
    </ul>
    <p>Você pode gerenciar suas preferências de cookies pelo banner exibido na primeira visita ao site ou diretamente nas configurações do seu navegador.</p>

    <!-- 10 -->
    <h2>10. Segurança dos Dados</h2>
    <p>Adotamos medidas técnicas e organizacionais adequadas para proteger seus dados contra acesso não autorizado, perda, alteração ou divulgação indevida, incluindo:</p>
    <ul>
      <li>Comunicações criptografadas via HTTPS/TLS</li>
      <li>Controle de acesso baseado em perfis (RBAC) nas plataformas</li>
      <li>Backups regulares com criptografia em repouso</li>
      <li>Monitoramento de acessos e logs de auditoria</li>
      <li>Limitação de acesso aos dados estritamente ao pessoal autorizado</li>
    </ul>
    <p>Em caso de incidente de segurança que possa acarretar risco relevante ao titular, comunicaremos a ocorrência à Autoridade Nacional de Proteção de Dados (ANPD) e ao titular afetado, conforme exigido pela LGPD.</p>

    <!-- 11 -->
    <h2>11. Transferência Internacional de Dados</h2>
    <p>Alguns dos operadores listados na Seção 5 (como Supabase e N8N em instâncias cloud) podem processar dados em servidores localizados fora do Brasil. Nesses casos, garantimos que a transferência ocorre mediante mecanismos adequados de proteção, como cláusulas contratuais padrão ou verificação de que o país receptor oferece nível de proteção equivalente ao da LGPD.</p>

    <!-- 12 -->
    <h2>12. Dados de Crianças e Adolescentes</h2>
    <p>Nossos serviços não são destinados a pessoas menores de 18 anos. Não coletamos dados de crianças ou adolescentes intencionalmente. Se tomarmos conhecimento de que coletamos dados de um menor, eliminaremos essas informações imediatamente.</p>

    <!-- 13 -->
    <h2>13. Alterações a Esta Política</h2>
    <p>Podemos atualizar esta Política de Privacidade periodicamente para refletir mudanças em nossas práticas, tecnologias, exigências legais ou outros fatores. Quando fizermos alterações relevantes:</p>
    <ul>
      <li>Atualizaremos a data de "última atualização" no topo deste documento</li>
      <li>Para clientes ativos, comunicaremos por e-mail ou mensagem no WhatsApp com antecedência mínima de 10 dias</li>
      <li>Alterações substanciais podem requerer novo consentimento</li>
    </ul>
    <p>Recomendamos que você revise esta política periodicamente.</p>

    <!-- 14 -->
    <h2>14. Encarregado de Proteção de Dados (DPO)</h2>
    <p>Para fins de cumprimento do art. 41 da LGPD, o responsável pelo tratamento de dados e pelo canal de comunicação com titulares e com a ANPD é o próprio fundador da Soluções &amp; TAO, acessível pelo e-mail <a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a>.</p>

    <!-- 15 -->
    <h2>15. Foro e Legislação Aplicável</h2>
    <p>Esta Política é regida pela legislação brasileira, em especial pela Lei nº 13.709/2018 (LGPD) e pelo Marco Civil da Internet (Lei nº 12.965/2014). Para dirimir quaisquer controvérsias não resolvidas amigavelmente, fica eleito o foro da Comarca de <strong>São Paulo/SP</strong>.</p>

    <div class="legal-highlight" style="margin-top:48px;">
      <p><strong>Data de vigência desta versão:</strong> 29 de maio de 2026<br>
      Para dúvidas: <a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a> &nbsp;|&nbsp; <a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a></p>
    </div>

  </div>
</section>

<!-- FOOTER -->
<footer id="footer">
  <div class="footer-inner">
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
      <h4>Produtos</h4>
      <ul>
        <li><a href="/tao-neo/">TAO Neo</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Empresa</h4>
      <ul>
        <li><a href="/sobre/fundador/">Sobre o Fundador</a></li>
        <li><a href="/cases/">Cases</a></li>
        <li><a href="/projeto-iluminar/">Projeto Iluminar</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contato</h4>
      <ul>
        <li><a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a></li>
        <li><a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a></li>
        <li><a href="/contato/">Fale Conosco</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© 2026 Soluções &amp; TAO. Todos os direitos reservados.
      <a href="/politica-de-privacidade/">Política de Privacidade</a> ·
      <a href="/termos-de-uso/">Termos de Uso</a></p>
  </div>
</footer>

<?php wp_footer(); ?>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script>
lucide.createIcons();
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.section-animate').forEach(el => observer.observe(el));
document.querySelectorAll('.has-dropdown > a').forEach(link => {
  link.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) { e.preventDefault(); this.parentElement.classList.toggle('dropdown-open'); }
  });
});
window.addEventListener('scroll', function() {
  document.getElementById('stao-nav').classList.toggle('scrolled', window.scrollY > 60);
});
</script>
</body>
</html>
