<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
/**
 * Template Name: Termos de Uso
 * Description: Termos de Uso e Serviço para plataformas TAO Neo e TAO CRM
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Termos de Uso | Soluções &amp; TAO</title>
<meta name="description" content="Termos de Uso dos serviços e plataformas da Soluções &amp; TAO (TAO Neo e TAO CRM). Leia antes de utilizar nossas plataformas.">
<meta name="robots" content="noindex, nofollow">
<link rel="canonical" href="<?php echo esc_url( home_url('/termos-de-uso/') ); ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo esc_url( home_url('/termos-de-uso/') ); ?>">
<meta property="og:title" content="Termos de Uso | Soluções &amp; TAO">
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

.legal-warning {
  background: #fff8f0;
  border-left: 4px solid #e67e22;
  border-radius: 0 8px 8px 0;
  padding: 16px 22px;
  margin: 20px 0;
}
.legal-warning p { margin: 0; font-size: .93rem; color: #7a4000; }

.legal-table-wrap { overflow-x: auto; margin: 24px 0; }
.legal-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .9rem;
  min-width: 440px;
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
<body <?php body_class('page-termos-de-uso'); ?>>
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
    <span class="legal-eyebrow">Contrato de Uso</span>
    <h1>Termos de Uso</h1>
    <p class="legal-date">Última atualização: 29 de maio de 2026 &nbsp;|&nbsp; Vigência: 29/05/2026</p>
  </div>
</section>

<!-- CONTEÚDO -->
<section class="legal-body">
  <div class="legal-container">

    <div class="legal-highlight">
      <p>Ao acessar ou utilizar qualquer plataforma ou serviço da <strong>Soluções &amp; TAO</strong> (incluindo TAO Neo, TAO CRM e serviços de consultoria), você concorda com estes Termos de Uso. Caso não concorde, não utilize nossos serviços. Estes termos constituem um contrato vinculante entre você (pessoa física ou jurídica, doravante "<strong>Contratante</strong>") e a Soluções &amp; TAO (doravante "<strong>Soluções &amp; TAO</strong>" ou "<strong>Prestadora</strong>").</p>
    </div>

    <!-- 1 -->
    <h2>1. Objeto do Contrato</h2>
    <p>Estes Termos regem o acesso e a utilização das seguintes plataformas e serviços fornecidos pela Soluções &amp; TAO:</p>

    <div class="legal-table-wrap">
      <table class="legal-table">
        <thead>
          <tr>
            <th>Produto / Serviço</th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>TAO Neo</strong></td>
            <td>Plataforma SaaS de chatbot para WhatsApp com inteligência artificial, qualificação de leads e automação de atendimento.</td>
          </tr>
          <tr>
            <td><strong>TAO CRM</strong></td>
            <td>CRM conversacional nativo com Kanban, chat WhatsApp integrado, dois pipelines (Funil de Vendas e Pós Vendas) e automações por fase.</td>
          </tr>
          <tr>
            <td><strong>Consultoria</strong></td>
            <td>Serviços de consultoria de negócio, processos e estratégia, conforme escopo acordado em proposta comercial separada.</td>
          </tr>
          <tr>
            <td><strong>Portal do Cliente</strong></td>
            <td>Ambiente de acesso às plataformas contratadas, configurações e relatórios.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p>Cada contratação específica (plano, escopo, volume) é formalizada em proposta comercial ou ordem de serviço, que complementa e integra estes Termos.</p>

    <!-- 2 -->
    <h2>2. Cadastro e Acesso</h2>

    <h3>2.1 Requisitos para cadastro</h3>
    <ul>
      <li>Ser pessoa física maior de 18 anos ou pessoa jurídica devidamente constituída</li>
      <li>Possuir número de WhatsApp Business válido (para uso do TAO Neo e TAO CRM)</li>
      <li>Fornecer informações verdadeiras, precisas, atuais e completas no cadastro</li>
      <li>Aceitar estes Termos de Uso e a <a href="/politica-de-privacidade/">Política de Privacidade</a></li>
    </ul>

    <h3>2.2 Credenciais de acesso</h3>
    <p>O Contratante é responsável pela guarda e sigilo das credenciais de acesso ao portal. Qualquer acesso realizado com suas credenciais será considerado de sua responsabilidade. Em caso de suspeita de comprometimento, comunique imediatamente pelo e-mail <a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a>.</p>

    <h3>2.3 Múltiplos usuários</h3>
    <p>Cada usuário deve possuir credenciais individuais. O compartilhamento de login entre pessoas diferentes é vedado e pode resultar na suspensão do acesso sem direito a reembolso.</p>

    <!-- 3 -->
    <h2>3. Responsabilidades da Soluções &amp; TAO</h2>

    <p>A Soluções &amp; TAO compromete-se a:</p>

    <h3>3.1 Disponibilidade da plataforma</h3>
    <ul>
      <li>Garantir disponibilidade mínima de <strong>99% ao mês</strong> para as plataformas TAO Neo e TAO CRM, excluindo janelas de manutenção programada comunicadas com 24 horas de antecedência.</li>
      <li>Comunicar incidentes e previsão de resolução pelo canal de suporte em até 2 horas da identificação do problema.</li>
    </ul>

    <h3>3.2 Suporte técnico</h3>
    <ul>
      <li>Atendimento via <strong>WhatsApp</strong> em dias úteis, das 9h às 18h (horário de Brasília).</li>
      <li>Prazo de primeira resposta: até <strong>4 horas úteis</strong>.</li>
      <li>Prazo de resolução de incidentes críticos (plataforma inacessível): até <strong>8 horas úteis</strong>.</li>
      <li>Demais solicitações: avaliadas e respondidas em até <strong>2 dias úteis</strong>.</li>
    </ul>

    <h3>3.3 Segurança e confidencialidade</h3>
    <ul>
      <li>Manter confidencialidade dos dados do Contratante e não utilizá-los para fins distintos dos previstos nestes Termos e na Política de Privacidade.</li>
      <li>Adotar medidas de segurança adequadas ao nível de risco, conforme descrito na Política de Privacidade.</li>
    </ul>

    <h3>3.4 Backups</h3>
    <ul>
      <li>Realizar backups automáticos dos dados do Contratante com frequência mínima diária.</li>
      <li>Manter os backups por no mínimo 30 dias.</li>
    </ul>

    <!-- 4 -->
    <h2>4. Responsabilidades do Contratante</h2>

    <p>Ao utilizar as plataformas, o Contratante compromete-se a:</p>

    <h3>4.1 Uso adequado</h3>
    <ul>
      <li>Utilizar os serviços exclusivamente para finalidades lícitas e de acordo com estes Termos.</li>
      <li>Garantir que os destinatários das mensagens enviadas via TAO Neo consentiram em receber comunicações via WhatsApp.</li>
      <li>Respeitar os <a href="https://www.whatsapp.com/legal/business-policy/" target="_blank" rel="noopener noreferrer">Termos de Serviço do WhatsApp Business</a> e as políticas da Meta.</li>
      <li>Manter atualizados seus dados cadastrais e de pagamento.</li>
    </ul>

    <h3>4.2 Vedações expressas</h3>
    <div class="legal-warning">
      <p>As seguintes práticas são estritamente proibidas e podem resultar em suspensão imediata, sem direito a reembolso:</p>
    </div>
    <ul>
      <li>Ceder, sublicenciar, vender ou transferir o acesso à plataforma a terceiros não autorizados</li>
      <li>Utilizar a plataforma para envio de <strong>spam</strong>, mensagens em massa não solicitadas ou comunicações enganosas</li>
      <li>Fazer engenharia reversa, descompilar ou tentar extrair o código-fonte das plataformas</li>
      <li>Usar as plataformas para práticas ilegais, discriminatórias, ofensivas ou que violem direitos de terceiros</li>
      <li>Sobrecarregar intencionalmente a infraestrutura da plataforma</li>
      <li>Tentar acessar dados de outros clientes ou contornar mecanismos de segurança</li>
    </ul>

    <!-- 5 -->
    <h2>5. O que Não Está Incluído no Serviço</h2>
    <p>Para clareza e transparência, os itens abaixo <strong>não fazem parte</strong> da prestação de serviço da Soluções &amp; TAO, salvo previsão expressa em proposta:</p>
    <ul>
      <li><strong>Infraestrutura do número WhatsApp:</strong> o Contratante é responsável por possuir e manter uma conta WhatsApp Business válida (pessoal ou via Business API). Os custos associados à conta e ao uso da API WhatsApp (Meta) são de responsabilidade exclusiva do Contratante.</li>
      <li><strong>Custos de APIs de terceiros:</strong> eventuais custos de uso de APIs externas (como modelos de IA generativa, serviços de SMS, gateways de pagamento) não estão incluídos, salvo indicação expressa.</li>
      <li><strong>Hardware e conectividade:</strong> computadores, smartphones, planos de dados e conexão à internet necessários para acessar a plataforma.</li>
      <li><strong>Treinamento presencial:</strong> sessões de treinamento presencial, salvo quando contratadas separadamente.</li>
      <li><strong>Integrações customizadas:</strong> desenvolvimentos específicos não previstos no plano padrão são cotados separadamente.</li>
    </ul>

    <!-- 6 -->
    <h2>6. Propriedade Intelectual</h2>

    <h3>6.1 Propriedade da Soluções &amp; TAO</h3>
    <p>Todos os direitos de propriedade intelectual relacionados às plataformas TAO Neo e TAO CRM — incluindo código-fonte, design, funcionalidades, marcas, logotipos e documentação — são de propriedade exclusiva da Soluções &amp; TAO ou de seus licenciantes. Nenhuma disposição destes Termos transfere qualquer direito de propriedade intelectual ao Contratante.</p>

    <h3>6.2 Licença de uso</h3>
    <p>A Soluções &amp; TAO concede ao Contratante uma licença de uso <strong>não exclusiva, intransferível, revogável e limitada</strong> para acessar e utilizar as plataformas durante o período de vigência do contrato, exclusivamente para suas finalidades internas de negócio.</p>

    <h3>6.3 Conteúdo do Contratante</h3>
    <p>Os dados, conversas, listas de contatos e conteúdos inseridos pelo Contratante nas plataformas permanecem de sua propriedade. O Contratante concede à Soluções &amp; TAO licença limitada para processar esses dados exclusivamente para a prestação dos serviços contratados.</p>

    <!-- 7 -->
    <h2>7. Dados Pessoais e LGPD</h2>
    <p>O tratamento de dados pessoais no contexto destes Termos é regido pela <a href="/politica-de-privacidade/">Política de Privacidade</a> da Soluções &amp; TAO, que faz parte integrante deste instrumento.</p>
    <p>Na relação entre as partes:</p>
    <ul>
      <li>O <strong>Contratante</strong> atua como <strong>controlador</strong> dos dados pessoais de seus clientes e leads.</li>
      <li>A <strong>Soluções &amp; TAO</strong> atua como <strong>operadora</strong> desses dados, processando-os conforme as instruções do Contratante e as finalidades da plataforma.</li>
    </ul>
    <p>O Contratante é responsável por garantir que possui base legal adequada para coletar e tratar os dados de seus clientes através das plataformas e que esses clientes consentiram em ser contactados via WhatsApp.</p>

    <!-- 8 -->
    <h2>8. Prazo, Renovação e Cancelamento</h2>

    <h3>8.1 Vigência</h3>
    <p>O contrato entra em vigor na data do primeiro pagamento ou da assinatura da proposta comercial e permanece ativo enquanto o plano estiver sendo pago.</p>

    <h3>8.2 Cancelamento pelo Contratante</h3>
    <ul>
      <li>O Contratante pode cancelar o contrato a qualquer momento mediante <strong>aviso prévio de 30 dias</strong>, por escrito (e-mail ou WhatsApp).</li>
      <li>Contratos com fidelidade de 12 meses cumpridos: cancelamento sem multa, com aviso de 30 dias.</li>
      <li>Cancelamento antes de completar 12 meses de contrato: sujeito à multa proporcional prevista na proposta comercial.</li>
      <li>Não há reembolso de mensalidades já pagas.</li>
    </ul>

    <h3>8.3 Cancelamento pela Soluções &amp; TAO</h3>
    <p>A Soluções &amp; TAO pode encerrar o contrato nas seguintes situações:</p>
    <ul>
      <li>Inadimplência superior a <strong>15 dias</strong> após o vencimento, com aviso prévio de 5 dias úteis</li>
      <li>Violação grave destes Termos, com suspensão imediata e rescisão motivada</li>
      <li>Descontinuação do produto, com aviso mínimo de <strong>90 dias</strong> e reembolso proporcional do período não utilizado</li>
    </ul>

    <h3>8.4 Exportação de dados após cancelamento</h3>
    <p>O Contratante tem o prazo de <strong>30 dias</strong> após o cancelamento para exportar seus dados. Após esse prazo, os dados serão eliminados de forma segura conforme a Política de Privacidade.</p>

    <!-- 9 -->
    <h2>9. Pagamentos e Reajuste</h2>
    <ul>
      <li>Os valores são definidos na proposta comercial e cobrados conforme a periodicidade acordada.</li>
      <li>O vencimento padrão é o dia do mês correspondente à contratação, salvo acordo expresso.</li>
      <li>Reajuste anual automático pelo <strong>IPCA</strong> (Índice Nacional de Preços ao Consumidor Amplo) do período, comunicado com 30 dias de antecedência.</li>
      <li>Multa por atraso: <strong>2%</strong> sobre o valor em aberto, acrescida de juros de <strong>1% ao mês</strong>.</li>
    </ul>

    <!-- 10 -->
    <h2>10. Limitação de Responsabilidade</h2>

    <p>A Soluções &amp; TAO não se responsabiliza por:</p>
    <ul>
      <li>Perdas de receita, lucros cessantes ou danos indiretos decorrentes do uso ou indisponibilidade das plataformas</li>
      <li>Bloqueios, restrições ou alterações impostas unilateralmente pelo WhatsApp / Meta que impactem o funcionamento das plataformas</li>
      <li>Resultados comerciais do Contratante — a plataforma é uma ferramenta; os resultados dependem de como ela é utilizada</li>
      <li>Conteúdo publicado, mensagens enviadas e dados inseridos pelo Contratante nas plataformas</li>
      <li>Falhas causadas por terceiros (operadoras de telecomunicações, provedores de internet, Meta/WhatsApp)</li>
      <li>Uso inadequado da plataforma em desconformidade com estes Termos</li>
    </ul>

    <p>Em qualquer hipótese, a responsabilidade total e cumulativa da Soluções &amp; TAO perante o Contratante fica limitada ao valor pago nos últimos <strong>3 meses</strong> de serviço.</p>

    <!-- 11 -->
    <h2>11. Garantias e Isenções</h2>
    <p>As plataformas são fornecidas "<em>no estado em que se encontram</em>" (<em>as is</em>), sem garantia de adequação a uma finalidade específica além das descritas nestes Termos. A Soluções &amp; TAO não garante que as plataformas serão livres de erros em todos os momentos, mas compromete-se a corrigir bugs reportados no menor tempo possível, conforme a criticidade.</p>

    <!-- 12 -->
    <h2>12. Força Maior</h2>
    <p>Nenhuma das partes será responsabilizada por atrasos ou falhas na execução de suas obrigações decorrentes de eventos de força maior ou caso fortuito, incluindo desastres naturais, falhas generalizadas de infraestrutura de internet, pandemias, guerras, ou bloqueios regulatórios imprevisíveis. A parte afetada deve comunicar a outra em até 48 horas e adotar as melhores medidas disponíveis para minimizar o impacto.</p>

    <!-- 13 -->
    <h2>13. Modificações aos Termos</h2>
    <p>A Soluções &amp; TAO pode modificar estes Termos a qualquer momento. As alterações serão comunicadas por:</p>
    <ul>
      <li>E-mail ou WhatsApp ao Contratante com <strong>antecedência mínima de 15 dias</strong> para alterações não substanciais</li>
      <li><strong>30 dias de antecedência</strong> para alterações que impactem o preço, escopo ou condições de cancelamento</li>
    </ul>
    <p>O uso continuado da plataforma após a data de vigência das alterações constitui aceite dos novos termos. Caso não concorde, o Contratante pode rescindir o contrato sem multa dentro do prazo de comunicação.</p>

    <!-- 14 -->
    <h2>14. Foro e Legislação Aplicável</h2>
    <p>Estes Termos são regidos pela <strong>legislação brasileira</strong>. As partes elegem o foro da Comarca de <strong>São Paulo / SP</strong> para dirimir quaisquer controvérsias decorrentes deste instrumento, com renúncia a qualquer outro, por mais privilegiado que seja.</p>
    <p>As partes se comprometem a buscar solução amigável antes de recorrer ao Judiciário, através de mediação extrajudicial no prazo de 30 dias a contar da notificação do conflito.</p>

    <!-- 15 -->
    <h2>15. Disposições Gerais</h2>
    <ul>
      <li><strong>Integralidade:</strong> estes Termos, em conjunto com a Política de Privacidade e a proposta comercial assinada, constituem o acordo completo entre as partes, substituindo quaisquer entendimentos anteriores.</li>
      <li><strong>Separabilidade:</strong> se qualquer cláusula destes Termos for considerada inválida ou inexequível, as demais permanecem em pleno vigor.</li>
      <li><strong>Não-renúncia:</strong> o não exercício de qualquer direito previsto nestes Termos não constitui renúncia a esse direito.</li>
      <li><strong>Cessão:</strong> o Contratante não pode ceder seus direitos e obrigações decorrentes destes Termos sem o consentimento prévio por escrito da Soluções &amp; TAO.</li>
      <li><strong>Comunicações:</strong> todas as comunicações formais entre as partes serão feitas por e-mail (<a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a>) ou via WhatsApp (<a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a>).</li>
    </ul>

    <div class="legal-highlight" style="margin-top:48px;">
      <p><strong>Data de vigência desta versão:</strong> 29 de maio de 2026<br>
      Para dúvidas ou esclarecimentos: <a href="mailto:contato@solucoesetao.com.br">contato@solucoesetao.com.br</a> &nbsp;|&nbsp; <a href="https://wa.me/5511994604521" target="_blank">+55 11 99460-4521</a><br>
      Ver também: <a href="/politica-de-privacidade/">Política de Privacidade</a></p>
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
