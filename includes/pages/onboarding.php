<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) { echo '<div class="wrap"><p>Acesso negado.</p></div>'; return; }

$step  = max( 1, min( 5, intval( $_GET['step'] ?? 1 ) ) );
$rws   = tao_crm_api( '/crm_workspaces?ativo=eq.true&order=nome.asc&limit=1' );
$ws    = ( $rws['ok'] && ! empty( $rws['data'] ) ) ? $rws['data'][0] : null;
$ws_id = $ws['id'] ?? '';
?>
<div class="wrap tao-crm-wrap">
<div class="tao-crm-topbar">
    <h1>&#x1F680; Configuração inicial do TAO CRM</h1>
    <p style="color:#64748b;margin-top:4px">Siga os passos abaixo para configurar seu CRM em minutos.</p>
</div>

<!-- Progress bar -->
<div class="crm-wizard-progress">
    <?php
    $steps_labels = ['Workspace','WhatsApp','Pipeline','Equipe','Pronto!'];
    foreach ( $steps_labels as $i => $label ) :
        $num = $i + 1;
        $cls = $num < $step ? 'done' : ( $num === $step ? 'active' : '' );
    ?>
    <div class="crm-wizard-step <?php echo $cls; ?>">
        <div class="crm-wizard-bubble"><?php echo $num < $step ? '&#x2713;' : $num; ?></div>
        <span><?php echo esc_html($label); ?></span>
    </div>
    <?php if ( $i < 4 ) echo '<div class="crm-wizard-line' . ($num < $step ? ' done' : '') . '"></div>'; ?>
    <?php endforeach; ?>
</div>

<div class="crm-wizard-body">
<?php if ( $step === 1 ) : ?>
    <h2>1. Seu workspace</h2>
    <p>O workspace é o ambiente de trabalho da sua empresa no CRM.</p>
    <?php if ( $ws ) : ?>
    <div class="notice notice-success inline"><p>&#x2714; Workspace <strong><?php echo esc_html($ws['nome']); ?></strong> configurado.</p></div>
    <a href="?page=tao-crm-onboarding&step=2" class="button button-primary" style="margin-top:12px">Próximo &rarr;</a>
    <?php else : ?>
    <div class="notice notice-warning inline"><p>Nenhum workspace criado. <a href="<?php echo admin_url('admin.php?page=tao-crm-settings&tab=workspaces'); ?>">Criar workspace</a></p></div>
    <?php endif; ?>

<?php elseif ( $step === 2 ) : ?>
    <h2>2. Conectar WhatsApp</h2>
    <p>Configure a instância do Evolution API para enviar e receber mensagens.</p>
    <?php
    $ws_data = $ws ? tao_crm_api( "/crm_workspaces?id=eq.$ws_id&select=evolution_instancia,evolution_url" ) : null;
    $tem_evo  = $ws_data && ! empty( $ws_data['data'][0]['evolution_instancia'] );
    ?>
    <?php if ( $tem_evo ) : ?>
    <div class="notice notice-success inline"><p>&#x2714; Instância WhatsApp configurada: <strong><?php echo esc_html($ws_data['data'][0]['evolution_instancia']); ?></strong></p></div>
    <?php else : ?>
    <div class="notice notice-warning inline"><p>Nenhuma instância configurada.</p></div>
    <?php endif; ?>
    <p style="margin-top:12px"><a href="<?php echo admin_url('admin.php?page=tao-crm-settings&tab=workspaces'); ?>" class="button">Configurar instância WhatsApp</a></p>
    <div style="margin-top:16px;display:flex;gap:10px">
        <a href="?page=tao-crm-onboarding&step=1" class="button">&larr; Voltar</a>
        <a href="?page=tao-crm-onboarding&step=3" class="button button-primary">Próximo &rarr;</a>
    </div>

<?php elseif ( $step === 3 ) : ?>
    <h2>3. Pipeline de vendas</h2>
    <p>O pipeline define o fluxo dos seus leads até o fechamento.</p>
    <?php
    $rp = $ws_id ? tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&select=id,nome&limit=5" ) : ['ok'=>false];
    $pipelines = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];
    ?>
    <?php if ( $pipelines ) : ?>
    <div class="notice notice-success inline"><p>&#x2714; <?php echo count($pipelines); ?> pipeline(s): <strong><?php echo esc_html(implode(', ', array_column($pipelines,'nome'))); ?></strong></p></div>
    <?php else : ?>
    <div class="notice notice-warning inline"><p>Nenhum pipeline criado.</p></div>
    <?php endif; ?>
    <p style="margin-top:12px"><a href="<?php echo admin_url('admin.php?page=tao-crm-settings&tab=pipelines'); ?>" class="button">Configurar pipeline</a></p>
    <div style="margin-top:16px;display:flex;gap:10px">
        <a href="?page=tao-crm-onboarding&step=2" class="button">&larr; Voltar</a>
        <a href="?page=tao-crm-onboarding&step=4" class="button button-primary">Próximo &rarr;</a>
    </div>

<?php elseif ( $step === 4 ) : ?>
    <h2>4. Configure sua equipe</h2>
    <p>Gestores veem todos os cards; atendentes veem apenas os próprios.</p>
    <?php
    $gestores_g  = (array) get_option( 'tao_crm_gestores_global', [] );
    $gestores_ws = $ws_id ? (array) get_option( "tao_crm_gestores_ws_$ws_id", [] ) : [];
    $total_g     = count( $gestores_g ) + count( $gestores_ws );
    ?>
    <?php if ( $total_g > 0 ) : ?>
    <div class="notice notice-success inline"><p>&#x2714; <?php echo $total_g; ?> gestor(es) configurado(s).</p></div>
    <?php else : ?>
    <div class="notice notice-info inline"><p>Apenas admins WP são gestores por padrão.</p></div>
    <?php endif; ?>
    <p style="margin-top:12px"><a href="<?php echo admin_url('admin.php?page=tao-crm-settings&tab=equipe'); ?>" class="button">Configurar equipe</a></p>
    <div style="margin-top:16px;display:flex;gap:10px">
        <a href="?page=tao-crm-onboarding&step=3" class="button">&larr; Voltar</a>
        <a href="?page=tao-crm-onboarding&step=5" class="button button-primary">Próximo &rarr;</a>
    </div>

<?php elseif ( $step === 5 ) : ?>
    <?php
    // Verifica aceite LGPD
    $termos_aceitos = $ws ? tao_crm_api( "/crm_workspaces?id=eq.$ws_id&select=termos_aceitos_em,termos_versao&limit=1" ) : [ 'ok' => false ];
    $termos_em      = ( $termos_aceitos['ok'] && ! empty( $termos_aceitos['data'] ) ) ? $termos_aceitos['data'][0]['termos_aceitos_em'] : null;

    // Processar POST de aceite
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['aceitar_termos_nonce'] ) ) {
        if ( wp_verify_nonce( sanitize_text_field( $_POST['aceitar_termos_nonce'] ), 'tao_crm_aceitar_termos' ) && $ws_id ) {
            if ( ! empty( $_POST['aceitar_lgpd'] ) ) {
                tao_crm_api( "/crm_workspaces?id=eq.$ws_id", 'PATCH', [
                    'termos_aceitos_em' => gmdate( 'c' ),
                    'termos_versao'     => '1.0',
                ] );
                $termos_em = gmdate( 'c' );
            }
        }
    }
    ?>
    <?php if ( ! $termos_em ) : ?>
    <h2>5. Termos de Uso &amp; LGPD</h2>
    <p style="color:#475569">Antes de começar, o responsável pela conta precisa aceitar os Termos de Uso e a Política de Privacidade do TAO CRM.</p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;max-width:560px;margin-bottom:16px;font-size:13px;color:#334155;max-height:200px;overflow-y:auto;line-height:1.7">
        <strong>Resumo dos pontos principais:</strong>
        <ul style="padding-left:18px;margin:8px 0">
            <li>Os dados dos seus clientes ficam armazenados no Supabase (EU-West-1) com criptografia em trânsito e em repouso.</li>
            <li>Você é o controlador dos dados; o TAO CRM atua como operador.</li>
            <li>Mensagens WhatsApp são processadas via Evolution API na sua infraestrutura.</li>
            <li>Você pode solicitar exportação ou exclusão dos dados a qualquer momento.</li>
            <li>A plataforma segue a Lei Geral de Proteção de Dados (LGPD — Lei 13.709/2018).</li>
        </ul>
        <a href="https://solucoesetao.com.br/termos" target="_blank" style="color:#3b82f6">Ler Termos completos &rarr;</a>
    </div>
    <form method="post" action="?page=tao-crm-onboarding&step=5">
        <?php wp_nonce_field( 'tao_crm_aceitar_termos', 'aceitar_termos_nonce' ); ?>
        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;cursor:pointer;margin-bottom:16px">
            <input type="checkbox" name="aceitar_lgpd" value="1" required style="width:18px;height:18px;accent-color:#3b82f6">
            Li e aceito os Termos de Uso e a Política de Privacidade do TAO CRM.
        </label>
        <div style="display:flex;gap:10px;align-items:center">
            <button type="submit" class="button button-primary button-large">Aceitar e continuar</button>
            <a href="?page=tao-crm-onboarding&step=4" class="button">&larr; Voltar</a>
        </div>
    </form>
    <?php else : ?>
    <div style="text-align:center;padding:40px 20px">
        <div style="font-size:64px;margin-bottom:16px">&#x1F389;</div>
        <h2 style="color:#16a34a">Tudo configurado!</h2>
        <div class="notice notice-success inline" style="max-width:420px;margin:0 auto 16px;text-align:left">
            <p>&#x2714; Termos aceitos em <?php echo esc_html( date( 'd/m/Y', strtotime($termos_em) ) ); ?>.</p>
        </div>
        <p style="color:#475569;max-width:400px;margin:0 auto 24px">Seu TAO CRM está pronto para receber leads e gerenciar atendimentos via WhatsApp.</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="<?php echo admin_url('admin.php?page=tao-crm'); ?>" class="button button-primary button-large">Ir para o Dashboard</a>
            <a href="<?php echo admin_url('admin.php?page=tao-crm-kanban'); ?>" class="button button-large">Abrir Kanban</a>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div><!-- .crm-wizard-body -->
</div><!-- .wrap -->
