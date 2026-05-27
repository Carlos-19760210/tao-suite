<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! cbpm_can_access() ) wp_die( 'Acesso negado.' );
ob_start();
$is_admin_user = current_user_can( 'manage_options' );
$forced_cid    = cbpm_current_cliente_id();
$supabase_url  = cbpm_supabase_url();
$supabase_key  = cbpm_supabase_key();

$cbpm_role = cbpm_current_role();
$is_master = ( $cbpm_role === 'master' );
$is_gestor = ( $cbpm_role === 'gestor' );
$is_op     = ( $cbpm_role === 'operacional' );

$secoes_master = [
    'chatbot-platform-dashboard'       => ['fn'=>'cbpm_page_dashboard',         'label'=>'Dashboard'],
    'chatbot-platform'                 => ['fn'=>'cbpm_page_clientes',          'label'=>'Negócios'],
    'chatbot-platform-categorias'      => ['fn'=>'cbpm_page_categorias',        'label'=>'Categorias'],
    'chatbot-platform-campos-extras'   => ['fn'=>'cbpm_page_campos_extras',     'label'=>'Campos Extras'],
    'chatbot-platform-catalogo'        => ['fn'=>'cbpm_page_catalogo',          'label'=>'Catálogo'],
    'chatbot-platform-disponibilidade' => ['fn'=>'cbpm_page_disponibilidade',   'label'=>'Disponibilidade'],
    'chatbot-platform-conteudo'        => ['fn'=>'cbpm_page_conteudo_dinamico', 'label'=>'Promoções/Avisos'],
    'chatbot-platform-leads'           => ['fn'=>'cbpm_page_leads',             'label'=>'Leads'],
    'chatbot-platform-pedidos'         => ['fn'=>'cbpm_page_pedidos',           'label'=>'Pedidos'],
    'chatbot-platform-historico'       => ['fn'=>'cbpm_page_historico',         'label'=>'Histórico'],
    'chatbot-platform-campanhas'       => ['fn'=>'cbpm_page_campanhas',         'label'=>'Campanhas'],
    'chatbot-platform-listas'          => ['fn'=>'cbpm_page_listas_contatos',   'label'=>'Listas de Contatos'],
    'chatbot-platform-usuarios'        => ['fn'=>'cbpm_page_usuarios',          'label'=>'Usuários'],
    'chatbot-platform-conectores'      => ['fn'=>'cbpm_page_conectores',        'label'=>'Conectores'],
    'chatbot-platform-settings'        => ['fn'=>'cbpm_page_settings',          'label'=>'Configurações'],
    'tao-crm'            => ['fn'=>'tao_crm_page_dashboard',      'label'=>'CRM — Dashboard'],
    'tao-crm-inbox'      => ['fn'=>'tao_crm_page_inbox',          'label'=>'CRM — Inbox'],
    'tao-crm-kanban'     => ['fn'=>'tao_crm_page_kanban_full',    'label'=>'CRM — Kanban'],
    'tao-crm-settings'   => ['fn'=>'tao_crm_page_settings',       'label'=>'CRM — Configurações'],
    'tao-crm-workspaces' => ['fn'=>'tao_crm_settings_workspaces', 'label'=>'CRM — Workspaces'],
    'tao-crm-pipelines'  => ['fn'=>'tao_crm_settings_pipelines',  'label'=>'CRM — Pipelines e Estágios'],
    'tao-crm-campos'     => ['fn'=>'tao_crm_settings_campos',     'label'=>'CRM — Campos'],
    'tao-crm-automacoes' => ['fn'=>'tao_crm_settings_automacoes', 'label'=>'CRM — Automações'],
    'tao-crm-contatos'  => ['fn'=>'tao_crm_page_contatos',       'label'=>'CRM — Contatos'],
];
$secoes_gestor = [
    'chatbot-platform-dashboard'       => ['fn'=>'cbpm_page_dashboard',         'label'=>'Dashboard'],
    'chatbot-platform'                 => ['fn'=>'cbpm_page_clientes',          'label'=>'Meu Negócio'],
    'chatbot-platform-categorias'      => ['fn'=>'cbpm_page_categorias',        'label'=>'Categorias'],
    'chatbot-platform-campos-extras'   => ['fn'=>'cbpm_page_campos_extras',     'label'=>'Campos Extras'],
    'chatbot-platform-catalogo'        => ['fn'=>'cbpm_page_catalogo',          'label'=>'Catálogo'],
    'chatbot-platform-disponibilidade' => ['fn'=>'cbpm_page_disponibilidade',   'label'=>'Disponibilidade'],
    'chatbot-platform-conteudo'        => ['fn'=>'cbpm_page_conteudo_dinamico', 'label'=>'Promoções/Avisos'],
    'chatbot-platform-leads'           => ['fn'=>'cbpm_page_leads',             'label'=>'Leads'],
    'chatbot-platform-pedidos'         => ['fn'=>'cbpm_page_pedidos',           'label'=>'Pedidos'],
    'chatbot-platform-historico'       => ['fn'=>'cbpm_page_historico',         'label'=>'Histórico'],
    'chatbot-platform-campanhas'       => ['fn'=>'cbpm_page_campanhas',         'label'=>'Campanhas'],
    'chatbot-platform-listas'          => ['fn'=>'cbpm_page_listas_contatos',   'label'=>'Listas de Contatos'],
    'chatbot-platform-usuarios'        => ['fn'=>'cbpm_page_usuarios',          'label'=>'Usuários'],
    'tao-crm'          => ['fn'=>'tao_crm_page_dashboard',   'label'=>'CRM — Dashboard'],
    'tao-crm-inbox'    => ['fn'=>'tao_crm_page_inbox',       'label'=>'CRM — Inbox'],
    'tao-crm-kanban'   => ['fn'=>'tao_crm_page_kanban_full', 'label'=>'CRM — Kanban'],
    'tao-crm-contatos' => ['fn'=>'tao_crm_page_contatos',    'label'=>'CRM — Contatos'],
];
$secoes_operacional = [
    'chatbot-platform-dashboard'       => ['fn'=>'cbpm_page_dashboard',         'label'=>'Dashboard'],
    'chatbot-platform-catalogo'        => ['fn'=>'cbpm_page_catalogo',          'label'=>'Catálogo'],
    'chatbot-platform-disponibilidade' => ['fn'=>'cbpm_page_disponibilidade',   'label'=>'Disponibilidade'],
    'chatbot-platform-categorias'      => ['fn'=>'cbpm_page_categorias',        'label'=>'Categorias'],
    'chatbot-platform-conteudo'        => ['fn'=>'cbpm_page_conteudo_dinamico', 'label'=>'Promoções/Avisos'],
    'chatbot-platform-leads'           => ['fn'=>'cbpm_page_leads',             'label'=>'Leads'],
    'chatbot-platform-pedidos'         => ['fn'=>'cbpm_page_pedidos',           'label'=>'Pedidos'],
    'chatbot-platform-historico'       => ['fn'=>'cbpm_page_historico',         'label'=>'Histórico'],
];
$secoes = $is_master ? $secoes_master : ( $is_gestor ? $secoes_gestor : $secoes_operacional );
$page_atual = $_GET['page'] ?? '';
if ( ! isset( $secoes[$page_atual] ) ) $page_atual = array_key_first( $secoes );
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TAO Neo — <?php echo esc_html( $secoes[$page_atual]['label'] ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( CBPM_PLUGIN_URL . 'assets/style.css' ); ?>?v=<?php echo CBPM_VERSION; ?>">
<style>
:root {
  --tao-primary:      #152C42;
  --tao-primary-dark: #0E2233;
  --tao-accent:       #B38E6C;
  --tao-accent-dark:  #8F6E4F;
  --tao-secondary:    #444C57;
  --tao-bg-main:      #FFFFFF;
  --tao-bg-alt:       #F5F4F2;
  --tao-text-main:    #1A1A1A;
  --tao-text-muted:   #6B7280;
  --tao-border:       #E2E0DC;
  --tao-success:      #00a32a;
  --tao-danger:       #d63638;
  --tao-warning:      #996800;
}
*, *::before, *::after { box-sizing: border-box; }
body { margin:0; font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; font-size:14px; background:var(--tao-bg-alt); color:var(--tao-text-main); }
img { max-width:100%; }
a { color:var(--tao-accent); }

.cbpm-layout { display:flex; min-height:100vh; }

/* Sidebar */
.cbpm-sidebar { width:240px; flex-shrink:0; background:var(--tao-primary); display:flex; flex-direction:column; position:sticky; top:0; height:100vh; overflow-y:auto; }
.cbpm-sidebar-logo { padding:18px 20px 14px; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:center; gap:10px; }
.cbpm-sidebar-logo img { height:44px; width:auto; background:#fff; padding:4px 10px; border-radius:6px; }
.cbpm-sidebar-logo .cbpm-logo-text { font-size:11px; font-weight:600; color:rgba(255,255,255,.4); letter-spacing:.08em; text-transform:uppercase; }
.cbpm-sidebar-section { padding:16px 20px 4px; font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,.28); }
.cbpm-sidebar nav { padding:4px 0; flex:1; }
.cbpm-sidebar nav a { display:flex; align-items:center; gap:10px; padding:9px 20px; color:rgba(255,255,255,.65); text-decoration:none; font-size:13px; font-weight:400; border-left:3px solid transparent; transition:all .15s; }
.cbpm-sidebar nav a:hover { color:#fff; background:rgba(255,255,255,.06); }
.cbpm-sidebar nav a.active { color:var(--tao-accent); background:rgba(179,142,108,.12); border-left-color:var(--tao-accent); font-weight:500; }
.cbpm-sidebar-footer { padding:12px 20px; border-top:1px solid rgba(255,255,255,.08); font-size:11px; color:rgba(255,255,255,.4); }
.cbpm-sidebar-footer a { color:rgba(255,255,255,.4); text-decoration:none; }
.cbpm-sidebar-footer a:hover { color:rgba(255,255,255,.8); }

/* Main */
.cbpm-main { flex:1; padding:28px 32px; min-width:0; overflow-x:auto; }
.cbpm-breadcrumb { font-size:12px; color:var(--tao-text-muted); margin-bottom:10px; }
.cbpm-breadcrumb a { color:var(--tao-text-muted); text-decoration:none; }
.cbpm-breadcrumb a:hover { color:var(--tao-accent); }

/* Typography */
.wrap h1, .cbpm-wrap h1 { font-family:'Playfair Display',Georgia,serif; font-size:24px; font-weight:600; color:var(--tao-primary); margin:0 0 20px; line-height:1.2; }
h2 { font-size:18px; margin:20px 0 12px; color:var(--tao-primary); font-family:'Playfair Display',serif; }
h3 { font-size:12px; margin:16px 0 8px; color:var(--tao-text-muted); text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
code { background:var(--tao-bg-alt); border:1px solid var(--tao-border); padding:2px 6px; border-radius:4px; font-size:12px; word-break:break-all; }
hr { border:none; border-top:1px solid var(--tao-border); margin:20px 0; }
label { cursor:pointer; }
optgroup { font-style:normal; font-weight:600; }

/* Inputs */
input[type=text], input[type=url], input[type=email], input[type=password],
input[type=number], input[type=datetime-local], textarea {
  border:1px solid var(--tao-border); border-radius:6px; padding:8px 12px; font-size:13px;
  font-family:'Inter',sans-serif; color:var(--tao-text-main); background:#fff;
  box-sizing:border-box; width:100%; max-width:100%; transition:border-color .15s, box-shadow .15s;
}
select {
  border:1px solid var(--tao-border); border-radius:6px; padding:8px 12px; font-size:13px;
  font-family:'Inter',sans-serif; color:var(--tao-text-main); background:#fff;
  box-sizing:border-box; max-width:100%;
}
input[type=text]:focus, input[type=url]:focus, input[type=email]:focus,
input[type=password]:focus, input[type=number]:focus, input[type=datetime-local]:focus,
textarea:focus, select:focus {
  outline:none; border-color:var(--tao-accent); box-shadow:0 0 0 3px rgba(179,142,108,.15);
}
input.small-text, input[type=number].small-text { width:80px !important; }
input.regular-text, .regular-text { width:100%; max-width:100%; }
textarea { resize:vertical; width:100%; }
.form-table { width:100%; border-collapse:collapse; }
.form-table th { padding:12px 20px 12px 0; text-align:left; width:200px; vertical-align:top; font-weight:600; font-size:13px; color:var(--tao-primary); white-space:nowrap; }
.form-table td { padding:8px 0; word-wrap:break-word; }
.form-table .description { color:var(--tao-text-muted); font-size:12px; margin:4px 0 0; }

/* Buttons */
.button, input[type=submit], button[type=submit] {
  display:inline-block; padding:8px 18px; border-radius:6px;
  border:1.5px solid var(--tao-border); background:#fff; color:var(--tao-secondary);
  cursor:pointer; font-size:13px; font-family:'Inter',sans-serif; font-weight:500;
  text-decoration:none; white-space:nowrap; line-height:1.4; vertical-align:middle; transition:all .15s;
}
.button:hover { background:var(--tao-bg-alt); border-color:var(--tao-accent); color:var(--tao-accent); }
.button-primary, input[type=submit].button-primary, button.button-primary {
  background:var(--tao-accent) !important; color:#fff !important; border-color:var(--tao-accent) !important;
}
.button-primary:hover { background:var(--tao-accent-dark) !important; border-color:var(--tao-accent-dark) !important; }
.button-secondary { border-color:var(--tao-border); color:var(--tao-secondary); background:#fff; }
.button-secondary:hover { border-color:var(--tao-accent); color:var(--tao-accent); }
.button-small { padding:5px 12px !important; font-size:12px !important; }
.page-title-action {
  font-size:12px; font-weight:500; padding:6px 14px; margin-left:12px; vertical-align:middle;
  border:1.5px solid var(--tao-accent); color:var(--tao-accent) !important; border-radius:6px;
  background:transparent; text-decoration:none; display:inline-block; transition:all .15s;
}
.page-title-action:hover { background:var(--tao-accent); color:#fff !important; }
p.submit, .submit { padding:20px 0 0; margin:0; }

/* Notices */
.notice {
  padding:12px 16px; border-left:4px solid var(--tao-secondary); background:#fff;
  margin-bottom:20px; border-radius:0 6px 6px 0; font-size:13px;
  box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.notice p { margin:0; }
.notice-success { border-left-color:var(--tao-success); }
.notice-error   { border-left-color:var(--tao-danger); }
.notice-info    { border-left-color:var(--tao-accent); }

/* Tables */
.cbpm-table-container { overflow-x:auto; width:100%; }
.wp-list-table {
  width:100%; border-collapse:collapse; background:#fff;
  border-radius:8px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06); min-width:500px;
}
.wp-list-table thead tr { background:var(--tao-bg-alt); border-bottom:2px solid var(--tao-border); }
.wp-list-table th { padding:12px 16px; text-align:left; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--tao-secondary); white-space:nowrap; }
.wp-list-table td { padding:12px 16px; font-size:13px; border-bottom:1px solid var(--tao-bg-alt); }
.wp-list-table tbody tr:last-child td { border-bottom:none; }
.wp-list-table tbody tr:hover { background:rgba(245,244,242,.8); }

/* Misc */
.cbpm-filters { margin:12px 0 16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.cbpm-filters select, .cbpm-filters input[type=text] { width:auto; }
.cbpm-chat { width:100%; max-height:65vh; overflow-y:auto; border:1px solid var(--tao-border); background:var(--tao-bg-alt); padding:16px; border-radius:8px; margin-top:16px; box-sizing:border-box; }
.cbpm-msg { margin:10px 0; display:flex; flex-direction:column; }
.cbpm-msg-user { align-items:flex-end; }
.cbpm-msg-bot  { align-items:flex-start; }
.cbpm-msg-bubble { max-width:75%; padding:10px 14px; border-radius:12px; font-size:13px; line-height:1.5; word-wrap:break-word; white-space:pre-wrap; }
.cbpm-msg-user .cbpm-msg-bubble { background:var(--tao-accent); color:#fff; border-bottom-right-radius:3px; }
.cbpm-msg-bot  .cbpm-msg-bubble { background:#fff; color:var(--tao-text-main); border:1px solid var(--tao-border); border-bottom-left-radius:3px; }
.cbpm-msg-meta { font-size:11px; color:var(--tao-text-muted); margin-top:3px; }
.wrap { max-width:100%; }
.cbpm-wrap { width:100%; box-sizing:border-box; }

@media (max-width: 768px) {
  .cbpm-sidebar { width:56px; }
  .cbpm-sidebar-logo .cbpm-logo-text, .cbpm-sidebar nav a span.label,
  .cbpm-sidebar-section, .cbpm-sidebar-footer { display:none; }
  .cbpm-sidebar nav a { justify-content:center; padding:12px 0; }
  .cbpm-sidebar-logo img { height:36px; padding:3px 6px; }
  .cbpm-main { padding:16px; }
}
.cbpm-sidebar nav a.sub {
  padding-left: 36px;
  font-size: 12px;
  color: rgba(255,255,255,.4);
  padding-top: 6px;
  padding-bottom: 6px;
}
.cbpm-sidebar nav a.sub:hover { color: rgba(255,255,255,.7); }
.cbpm-sidebar nav a.sub.active { color: var(--tao-accent); background: rgba(179,142,108,.1); border-left-color: var(--tao-accent); }
.cbpm-sidebar-section { cursor:default; }
.cbpm-nav-group { }
.cbpm-nav-group-btn {
  display:flex; align-items:center; justify-content:space-between;
  padding:6px 20px 4px; cursor:pointer; user-select:none; width:100%; background:none; border:none;
  font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase;
  color:rgba(255,255,255,.28); transition:color .15s;
}
.cbpm-nav-group-btn:hover { color:rgba(255,255,255,.5); }
.cbpm-nav-group-toggle { font-size:9px; transition:transform .2s; display:inline-block; }
.cbpm-nav-group-items { overflow:hidden; transition:max-height .25s ease; }
.cbpm-nav-group-items.crm-grp-collapsed { display:none; }
.cbpm-nav-group-items a { padding-left:36px !important; font-size:12px; color:rgba(255,255,255,.4); }
.cbpm-nav-group-items a:hover { color:rgba(255,255,255,.7); background:rgba(255,255,255,.04); }
.cbpm-nav-group-items a.active { color:var(--tao-accent); background:rgba(179,142,108,.1); border-left-color:var(--tao-accent); }
/* Collapsible section groups (Configurações, Operações) */
.cbpm-nav-section-btn {
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 20px 4px; cursor:pointer; user-select:none; width:100%; background:none; border:none;
  font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase;
  color:rgba(255,255,255,.28); transition:color .15s;
}
.cbpm-nav-section-btn:hover { color:rgba(255,255,255,.5); }
.cbpm-nav-section-toggle { font-size:9px; transition:transform .2s; display:inline-block; }
.cbpm-nav-section-items { overflow:hidden; }
.cbpm-nav-section-items.sgp-collapsed { display:none; }
.cbpm-nav-section-items .cbpm-nav-group-btn { padding-left:28px; }
.cbpm-nav-section-items .cbpm-nav-group-items a { padding-left:48px !important; }
</style>
<?php if ( function_exists('tao_crm_page_kanban') && defined('TAO_CRM_URL') ) : ?>
<link rel="stylesheet" href="<?php echo esc_url( TAO_CRM_URL . 'assets/crm-style.css' ); ?>?v=<?php echo TAO_CRM_VERSION; ?>">
<?php endif; ?>
<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
<script>var ajaxurl = "<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>";</script>
</head>
<body>
<div class="cbpm-layout">
<aside class="cbpm-sidebar">
  <div class="cbpm-sidebar-logo">
    <img src="<?php echo esc_url( home_url( '/wp-content/themes/solucoesetao/assets/logo.png' ) ); ?>" alt="TAO Neo">
    <span class="cbpm-logo-text">Painel</span>
  </div>
  <nav>
    <?php
    function cbpm_nav_item( $slug, $item, $page_atual ) {
      $active = ( $page_atual === $slug ) ? ' active' : '';
      $sub    = ! empty( $item['sub'] ) ? ' sub' : '';
      echo '<a href="' . esc_url( $item['url'] ) . '" class="' . trim( $active . $sub ) . '">'
         . '<span>' . $item['icon'] . '</span>'
         . '<span class="label">' . $item['label'] . '</span>'
         . '</a>';
    }
    function cbpm_nav_group( $id, $label, $content_fn, $page_atual, $slugs_in_group ) {
      $has_active = in_array( $page_atual, $slugs_in_group );
      echo '<div class="cbpm-nav-group">';
      echo '<button type="button" class="cbpm-nav-group-btn" data-grp="' . esc_attr($id) . '">'
         . '<span>' . esc_html($label) . '</span>'
         . '<span class="cbpm-nav-group-toggle" id="cbpm-toggle-' . esc_attr($id) . '">▾</span>'
         . '</button>';
      echo '<div class="cbpm-nav-group-items" id="cbpm-grp-' . esc_attr($id) . '">';
      $content_fn();
      echo '</div></div>';
    }
    function cbpm_nav_section_group( $id, $label, $content_fn ) {
      echo '<div class="cbpm-nav-section-group">';
      echo '<button type="button" class="cbpm-nav-section-btn" data-sgp="' . esc_attr($id) . '">'
         . '<span>' . esc_html($label) . '</span>'
         . '<span class="cbpm-nav-section-toggle" id="cbpm-stoggle-' . esc_attr($id) . '">▾</span>'
         . '</button>';
      echo '<div class="cbpm-nav-section-items" id="cbpm-sgp-' . esc_attr($id) . '">';
      $content_fn();
      echo '</div></div>';
    }

    // ── Visão Geral ─────────────────────────────────────────────────────────────
    echo '<div class="cbpm-sidebar-section">Vis&atilde;o Geral</div>';
    cbpm_nav_item('chatbot-platform-dashboard', ['icon'=>'&#x1F4CA;','label'=>'Dashboard','url'=>cbpm_url('dashboard')], $page_atual);

    // ── Configurações ────────────────────────────────────────────────────────────
    if ( $is_master ) {
        $geral_cfg_slugs = ['chatbot-platform','chatbot-platform-usuarios','chatbot-platform-settings','chatbot-platform-conectores'];
        $neo_cfg_slugs   = ['chatbot-platform-categorias','chatbot-platform-campos-extras','chatbot-platform-catalogo','chatbot-platform-disponibilidade','chatbot-platform-conteudo'];
        cbpm_nav_section_group('sec-cfg', 'Configurações', function() use ($page_atual, $geral_cfg_slugs, $neo_cfg_slugs) {
            cbpm_nav_group('cfg-geral', 'Geral', function() use ($page_atual) {
                cbpm_nav_item('chatbot-platform',            ['icon'=>'&#x1F3E2;','label'=>'Neg&oacute;cios',             'url'=>cbpm_url('negocios')],      $page_atual);
                cbpm_nav_item('chatbot-platform-usuarios',   ['icon'=>'&#x1F465;','label'=>'Usu&aacute;rios',             'url'=>cbpm_url('usuarios')],      $page_atual);
                cbpm_nav_item('chatbot-platform-conectores', ['icon'=>'&#x1F517;','label'=>'Conectores',                  'url'=>cbpm_url('conectores')],    $page_atual);
                cbpm_nav_item('chatbot-platform-settings',   ['icon'=>'&#x2699;', 'label'=>'Configura&ccedil;&otilde;es', 'url'=>cbpm_url('configuracoes')], $page_atual);
            }, $page_atual, $geral_cfg_slugs);
            cbpm_nav_group('cfg-neo', 'TAO NEO', function() use ($page_atual) {
                cbpm_nav_item('chatbot-platform-categorias',    ['icon'=>'&#x1F3F7;','label'=>'Categorias',          'url'=>cbpm_url('categorias')],     $page_atual);
                cbpm_nav_item('chatbot-platform-campos-extras', ['icon'=>'&#x1F9E9;','label'=>'Campos Extras',       'url'=>cbpm_url('campos-extras')],  $page_atual);
                cbpm_nav_item('chatbot-platform-catalogo',        ['icon'=>'&#x1F4CB;','label'=>'Cat&aacute;logo',       'url'=>cbpm_url('catalogo')],       $page_atual);
                cbpm_nav_item('chatbot-platform-disponibilidade', ['icon'=>'&#x1F4C5;','label'=>'Disponibilidade',       'url'=>cbpm_url('disponibilidade')], $page_atual);
                cbpm_nav_item('chatbot-platform-conteudo',        ['icon'=>'&#x1F4E2;','label'=>'Promo&ccedil;&otilde;es','url'=>cbpm_url('conteudo')],       $page_atual);
            }, $page_atual, $neo_cfg_slugs);
            if ( function_exists('tao_crm_page_kanban') ) {
                cbpm_nav_group('cfg-crm', 'TAO CRM', function() use ($page_atual) {
                    cbpm_nav_item('tao-crm-settings', ['icon'=>'&#x2699;','label'=>'Geral','url'=>cbpm_url('crm-settings')], $page_atual);
                }, $page_atual, ['tao-crm-settings']);
            }
        });
    }

    if ( $is_gestor ) {
        $gestor_cfg_slugs = ['chatbot-platform','chatbot-platform-categorias','chatbot-platform-campos-extras','chatbot-platform-catalogo','chatbot-platform-disponibilidade','chatbot-platform-conteudo','chatbot-platform-usuarios'];
        cbpm_nav_section_group('sec-cfg-g', 'Configurações', function() use ($page_atual, $gestor_cfg_slugs) {
            cbpm_nav_group('cfg-gestor', 'Meu Neg&oacute;cio', function() use ($page_atual) {
                cbpm_nav_item('chatbot-platform',               ['icon'=>'&#x1F3E2;','label'=>'Configura&ccedil;&otilde;es','url'=>cbpm_url('negocios')],      $page_atual);
                cbpm_nav_item('chatbot-platform-categorias',    ['icon'=>'&#x1F3F7;','label'=>'Categorias',                 'url'=>cbpm_url('categorias')],    $page_atual);
                cbpm_nav_item('chatbot-platform-campos-extras', ['icon'=>'&#x1F9E9;','label'=>'Campos Extras',              'url'=>cbpm_url('campos-extras')], $page_atual);
                cbpm_nav_item('chatbot-platform-catalogo',        ['icon'=>'&#x1F4CB;','label'=>'Cat&aacute;logo',       'url'=>cbpm_url('catalogo')],       $page_atual);
                cbpm_nav_item('chatbot-platform-disponibilidade', ['icon'=>'&#x1F4C5;','label'=>'Disponibilidade',       'url'=>cbpm_url('disponibilidade')], $page_atual);
                cbpm_nav_item('chatbot-platform-conteudo',        ['icon'=>'&#x1F4E2;','label'=>'Promo&ccedil;&otilde;es','url'=>cbpm_url('conteudo')],       $page_atual);
                cbpm_nav_item('chatbot-platform-usuarios',      ['icon'=>'&#x1F465;','label'=>'Usu&aacute;rios',            'url'=>cbpm_url('usuarios')],      $page_atual);
            }, $page_atual, $gestor_cfg_slugs);
        });
    }

    // ── Operações ────────────────────────────────────────────────────────────────
    $neo_op_slugs = ['chatbot-platform-leads','chatbot-platform-pedidos','chatbot-platform-historico','chatbot-platform-campanhas','chatbot-platform-listas'];
    $crm_op_slugs = ['tao-crm','tao-crm-inbox','tao-crm-kanban','tao-crm-contatos'];
    cbpm_nav_section_group('sec-op', 'Operações', function() use ($page_atual, $is_op, $neo_op_slugs, $crm_op_slugs) {
        cbpm_nav_group('op-neo', 'TAO NEO', function() use ($page_atual, $is_op) {
            cbpm_nav_item('chatbot-platform-leads',     ['icon'=>'&#x1F464;','label'=>'Leads',                  'url'=>cbpm_url('leads')],     $page_atual);
            cbpm_nav_item('chatbot-platform-pedidos',   ['icon'=>'&#x1F6D2;','label'=>'Pedidos',                'url'=>cbpm_url('pedidos')],   $page_atual);
            cbpm_nav_item('chatbot-platform-historico', ['icon'=>'&#x1F4AC;','label'=>'Hist&oacute;rico',       'url'=>cbpm_url('historico')], $page_atual);
            if ( ! $is_op ) {
                cbpm_nav_item('chatbot-platform-campanhas', ['icon'=>'&#x1F4E3;','label'=>'Campanhas','url'=>cbpm_url('campanhas')], $page_atual);
                cbpm_nav_item('chatbot-platform-listas',    ['icon'=>'&#x1F4CB;','label'=>'Listas',   'url'=>cbpm_url('listas')],   $page_atual);
            }
        }, $page_atual, $neo_op_slugs);
        if ( ! $is_op && function_exists('tao_crm_page_kanban') ) {
            cbpm_nav_group('op-crm', 'TAO CRM', function() use ($page_atual) {
                cbpm_nav_item('tao-crm',          ['icon'=>'&#x1F4CA;','label'=>'Dashboard','url'=>cbpm_url('crm')],          $page_atual);
                cbpm_nav_item('tao-crm-inbox',    ['icon'=>'&#x1F4E5;','label'=>'Inbox',    'url'=>cbpm_url('crm-inbox')],    $page_atual);
                cbpm_nav_item('tao-crm-kanban',   ['icon'=>'&#x1F5C2;','label'=>'Kanban',   'url'=>cbpm_url('crm-kanban')],   $page_atual);
                cbpm_nav_item('tao-crm-contatos', ['icon'=>'&#x1F465;','label'=>'Contatos', 'url'=>cbpm_url('crm-contatos')], $page_atual);
            }, $page_atual, $crm_op_slugs);
        }
    });
    ?>
  </nav>
  <div class="cbpm-sidebar-footer">
    <?php echo esc_html( wp_get_current_user()->display_name ); ?><br>
    <a href="<?php echo esc_url( wp_logout_url( home_url( '/robos/' ) ) ); ?>">Sair</a>
    <?php if ( $is_admin_user ) : ?>
      &nbsp;&middot;&nbsp;<a href="<?php echo esc_url( admin_url() ); ?>">wp-admin</a>
    <?php endif; ?>
  </div>
</aside>
<main class="cbpm-main">
  <div class="cbpm-breadcrumb">
    <a href="<?php echo esc_url( cbpm_url() ); ?>">TAO Neo</a>
    <?php if ( $page_atual !== 'chatbot-platform-dashboard' ) : ?>
      &rsaquo; <?php echo esc_html( $secoes[$page_atual]['label'] ); ?>
    <?php endif; ?>
  </div>
  <?php
  $fn = $secoes[$page_atual]['fn'] ?? 'cbpm_page_clientes';
  if ( function_exists( $fn ) ) call_user_func( $fn );
  else echo '<p>P&aacute;gina n&atilde;o encontrada.</p>';
  ?>
</main>
</div>
<?php if ( $forced_cid ) : ?>
<script>document.addEventListener("DOMContentLoaded",function(){
  document.querySelectorAll("select[name='cliente_id']").forEach(function(s){
    var h=document.createElement("input");h.type="hidden";h.name="cliente_id";h.value=s.value;
    s.parentNode.insertBefore(h,s);s.disabled=true;
  });
});</script>
<?php endif; ?>
<script>
window.cbpm = {
  ajax_url:     "<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>",
  nonce:        "<?php echo esc_js( wp_create_nonce( 'cbpm_nonce' ) ); ?>",
  supabase_url: "<?php echo esc_js( $supabase_url ); ?>",
  supabase_key: "<?php echo esc_js( $supabase_key ); ?>"
};
</script>
<script src="<?php echo esc_url( CBPM_PLUGIN_URL . 'assets/script.js' ); ?>?v=<?php echo CBPM_VERSION; ?>"></script>

<?php if ( function_exists('tao_crm_page_kanban') && defined('TAO_CRM_URL') && str_starts_with($page_atual, 'tao-crm') ) : ?>
<script>
window.taoCrm = {
  ajax_url:     "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
  nonce:        "<?php echo esc_js( wp_create_nonce('tao_crm_nonce') ); ?>",
  supabase_url: "<?php echo esc_js( $supabase_url ); ?>",
  supabase_key: "<?php echo esc_js( $supabase_key ); ?>",
  card_base_url:"<?php echo esc_js( function_exists('cbpm_url') ? cbpm_url('crm-kanban', ['action'=>'card','id'=>'']) : '' ); ?>"
};
</script>
<script src="<?php echo esc_url( TAO_CRM_URL . 'assets/crm-script.js' ); ?>?v=<?php echo TAO_CRM_VERSION; ?>"></script>
<?php endif; ?>
<script>
(function(){
  var STORE_KEY = 'cbpm-nav-groups';
  var state = {};
  try { state = JSON.parse(localStorage.getItem(STORE_KEY)||'{}'); } catch(e){}

  document.querySelectorAll('.cbpm-nav-group-btn').forEach(function(btn){
    var id    = btn.dataset.grp;
    var items = document.getElementById('cbpm-grp-' + id);
    var tog   = document.getElementById('cbpm-toggle-' + id);
    if(!items) return;

    // Auto-expand if contains active page
    var hasActive = items.querySelector('a.active');
    var collapsed = hasActive ? false : (state[id] === '1');

    function apply(c){
      items.classList.toggle('crm-grp-collapsed', c);
      if(tog) tog.style.transform = c ? 'rotate(-90deg)' : '';
      state[id] = c ? '1' : '0';
      try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch(e){}
    }
    apply(collapsed);

    btn.addEventListener('click', function(){
      apply(!items.classList.contains('crm-grp-collapsed'));
    });
  });

  // Section groups (Configurações, Operações)
  var STORE_KEY_S = 'cbpm-nav-sections';
  var stateS = {};
  try { stateS = JSON.parse(localStorage.getItem(STORE_KEY_S)||'{}'); } catch(e){}

  document.querySelectorAll('.cbpm-nav-section-btn').forEach(function(btn){
    var id    = btn.dataset.sgp;
    var items = document.getElementById('cbpm-sgp-' + id);
    var tog   = document.getElementById('cbpm-stoggle-' + id);
    if(!items) return;

    var hasActive = items.querySelector('a.active');
    var collapsed = hasActive ? false : (stateS[id] === '1');

    function applyS(c){
      items.classList.toggle('sgp-collapsed', c);
      if(tog) tog.style.transform = c ? 'rotate(-90deg)' : '';
      stateS[id] = c ? '1' : '0';
      try { localStorage.setItem(STORE_KEY_S, JSON.stringify(stateS)); } catch(e){}
    }
    applyS(collapsed);

    btn.addEventListener('click', function(){
      applyS(!items.classList.contains('sgp-collapsed'));
    });
  });
})();
</script>
</body>
</html>
