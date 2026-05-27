<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

$workspace_id = sanitize_text_field( $_GET['workspace_id'] ?? '' );

$rw = tao_crm_api( '/crm_workspaces?ativo=eq.true&order=nome.asc' );
$workspaces = $rw['ok'] ? ( $rw['data'] ?? [] ) : [];

$q = '/crm_contatos?order=nome.asc&limit=500';
if ( $workspace_id ) $q .= "&workspace_id=eq.$workspace_id";

$rc = tao_crm_api( $q );
$contatos = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];

$cls_cores = [
    'Excelente'   => '#22c55e',
    'Bom'         => '#3b82f6',
    'Regular'     => '#f59e0b',
    'Ruim'        => '#ef4444',
    'Inadimplente'=> '#6b7280',
];
$nonce = wp_create_nonce( 'tao_crm_nonce' );
$default_ws = $workspace_id ?: ( $workspaces[0]['id'] ?? '' );
?>
<style>
.ct-layout { display:flex; gap:0; min-height:600px; }
.ct-list-panel { flex:1; min-width:0; }
.ct-detail-panel {
  width:400px; flex-shrink:0; background:#fff; border-left:1px solid #e5e7eb;
  position:sticky; top:0; max-height:calc(100vh - 80px); overflow-y:auto;
  border-radius:0 8px 8px 0; display:none;
}
.ct-detail-panel.open { display:block; }
.ct-detail-header { padding:20px 20px 12px; border-bottom:1px solid #f3f4f6; }
.ct-detail-close { float:right; background:none; border:none; cursor:pointer; color:#9ca3af; font-size:18px; line-height:1; }
.ct-detail-name { font-size:17px; font-weight:700; color:#152C42; margin:0 0 4px; }
.ct-detail-meta { font-size:12px; color:#6b7280; }
.ct-detail-section { padding:14px 20px; border-bottom:1px solid #f3f4f6; }
.ct-detail-section h4 { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#9ca3af; margin:0 0 10px; }
.ct-timeline-item { font-size:12px; padding:7px 0; border-bottom:1px solid #f9fafb; display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
.ct-timeline-item:last-child { border-bottom:none; }
.ct-badge { display:inline-block; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:600; color:#fff; white-space:nowrap; }
.ct-empty { font-size:12px; color:#9ca3af; font-style:italic; }
.ct-row.selected { background:#faf7f4 !important; }
.ct-row { cursor:pointer; }
</style>

<div class="cbpm-wrap">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <h1 style="margin:0">Contatos</h1>
  <button class="button button-primary" id="ct-btn-novo">+ Novo Contato</button>
</div>

<div class="cbpm-filters" style="margin-bottom:16px">
  <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="page" value="tao-crm-contatos">
    <?php if ( count( $workspaces ) > 1 ) : ?>
    <select name="workspace_id" onchange="this.form.submit()">
      <option value="">Todos os workspaces</option>
      <?php foreach ( $workspaces as $ws ) : ?>
      <option value="<?php echo esc_attr( $ws['id'] ); ?>" <?php selected( $workspace_id, $ws['id'] ); ?>><?php echo esc_html( $ws['nome'] ); ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="text" id="ct-search" placeholder="Buscar por nome, WhatsApp, e-mail..." style="width:280px" oninput="ctFiltrar()">
    <span style="color:#6b7280;font-size:13px" id="ct-total"><?php echo count( $contatos ); ?> contato(s)</span>
  </form>
</div>

<div class="ct-layout">
  <div class="ct-list-panel">
    <div class="cbpm-table-container">
    <table class="wp-list-table" id="ct-tabela">
    <thead>
    <tr>
      <th>Nome</th>
      <th>WhatsApp</th>
      <th>E-mail</th>
      <th>Cidade</th>
      <th>Classificação</th>
      <th>Atendimentos</th>
      <th>Último atend.</th>
      <th style="width:70px"></th>
    </tr>
    </thead>
    <tbody id="ct-tbody">
    <?php if ( empty( $contatos ) ) : ?>
    <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:32px">Nenhum contato encontrado.</td></tr>
    <?php else : foreach ( $contatos as $ct ) :
        $cls  = $ct['classificacao'] ?? '';
        $cor  = $cls_cores[ $cls ] ?? '#6b7280';
        $ult  = $ct['ultimo_atendimento_em'] ?? '';
        if ( $ult ) { try { $dt = new DateTime($ult); $dt->setTimezone(new DateTimeZone('America/Sao_Paulo')); $ult = $dt->format('d/m/Y'); } catch(Exception $e){} }
        $cidade = trim( implode( ', ', array_filter( [ $ct['bairro']??'', $ct['cidade']??'' ] ) ) ) ?: '—';
        $json   = htmlspecialchars( wp_json_encode( $ct ), ENT_QUOTES );
    ?>
    <tr class="ct-row" data-search="<?php echo esc_attr( strtolower( ($ct['nome']??'') . ' ' . ($ct['whatsapp']??'') . ' ' . ($ct['email']??'') ) ); ?>" data-ct="<?php echo $json; ?>">
      <td>
        <strong><?php echo esc_html( $ct['nome'] ?? '—' ); ?></strong>
        <?php if ( !empty($ct['observacoes']) ) : ?>
        <div style="font-size:11px;color:#6b7280;margin-top:2px"><?php echo esc_html( mb_substr($ct['observacoes'],0,60) . (mb_strlen($ct['observacoes'])>60?'…':'') ); ?></div>
        <?php endif; ?>
      </td>
      <td><?php echo esc_html( $ct['whatsapp'] ?? '—' ); ?></td>
      <td><?php echo esc_html( $ct['email'] ?? '—' ); ?></td>
      <td><?php echo esc_html( $cidade ); ?></td>
      <td><?php if ( $cls ) : ?><span style="background:<?php echo esc_attr($cor); ?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600"><?php echo esc_html($cls); ?></span><?php else : ?>—<?php endif; ?></td>
      <td style="text-align:center"><?php echo esc_html( $ct['total_atendimentos'] ?? 0 ); ?></td>
      <td><?php echo esc_html( $ult ?: '—' ); ?></td>
      <td>
        <button class="button button-small ct-btn-ver" title="Ver perfil" style="margin-right:2px">&#128065;</button>
        <button class="button button-small ct-btn-editar" title="Editar">&#9998;</button>
      </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
    </table>
    </div>
  </div><!-- /.ct-list-panel -->

  <!-- Painel 360° -->
  <div class="ct-detail-panel" id="ct-detail">
    <div class="ct-detail-header">
      <button class="ct-detail-close" id="ct-detail-close">&times;</button>
      <div class="ct-detail-name" id="ct-d-nome">—</div>
      <div class="ct-detail-meta" id="ct-d-meta"></div>
    </div>

    <div class="ct-detail-section" id="ct-d-classificacao-wrap" style="display:none">
      <h4>Classificação</h4>
      <span id="ct-d-classificacao-badge"></span>
    </div>

    <div class="ct-detail-section">
      <h4>Contato</h4>
      <div id="ct-d-info" style="font-size:12px;line-height:1.8;color:#374151"></div>
    </div>

    <div class="ct-detail-section">
      <h4>Endereço</h4>
      <div id="ct-d-endereco" style="font-size:12px;line-height:1.8;color:#374151"></div>
    </div>

    <div class="ct-detail-section">
      <h4>CRM — Cards <span id="ct-d-cards-count" style="color:#b38e6c"></span></h4>
      <div id="ct-d-cards"><span class="ct-empty">Carregando...</span></div>
    </div>

    <div class="ct-detail-section">
      <h4>Pedidos <span id="ct-d-pedidos-count" style="color:#b38e6c"></span></h4>
      <div id="ct-d-pedidos"><span class="ct-empty">Carregando...</span></div>
    </div>

    <div class="ct-detail-section">
      <h4>Leads <span id="ct-d-leads-count" style="color:#b38e6c"></span></h4>
      <div id="ct-d-leads"><span class="ct-empty">Carregando...</span></div>
    </div>

    <div class="ct-detail-section" style="border-bottom:none">
      <button class="button button-small ct-btn-editar-detalhe">&#9998; Editar contato</button>
    </div>
  </div>
</div><!-- /.ct-layout -->

<!-- Modal editar/criar -->
<div id="ct-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;overflow-y:auto">
  <div style="background:#fff;border-radius:10px;max-width:540px;margin:40px auto;padding:28px;position:relative">
    <button id="ct-modal-close" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:#6b7280">&times;</button>
    <h2 style="margin:0 0 20px;font-size:18px" id="ct-modal-title">Novo Contato</h2>
    <input type="hidden" id="ct-id">
    <input type="hidden" id="ct-workspace-id" value="<?php echo esc_attr( $default_ws ); ?>">

    <?php if ( count($workspaces) > 1 ) : ?>
    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:12px">Workspace
      <select id="ct-workspace-sel" style="width:100%;margin-top:4px">
        <?php foreach ( $workspaces as $ws ) : ?>
        <option value="<?php echo esc_attr($ws['id']); ?>" <?php selected($default_ws,$ws['id']); ?>><?php echo esc_html($ws['nome']); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <label style="font-size:13px;font-weight:600;grid-column:1/-1">Nome <span style="color:#ef4444">*</span>
        <input type="text" id="ct-nome" style="width:100%;margin-top:4px" placeholder="Nome completo">
      </label>
      <label style="font-size:13px;font-weight:600">WhatsApp <span style="color:#ef4444">*</span>
        <input type="text" id="ct-whatsapp" style="width:100%;margin-top:4px" placeholder="5511999999999">
      </label>
      <label style="font-size:13px;font-weight:600">E-mail
        <input type="email" id="ct-email" style="width:100%;margin-top:4px">
      </label>
      <label style="font-size:13px;font-weight:600">CPF
        <input type="text" id="ct-cpf" style="width:100%;margin-top:4px" maxlength="14" placeholder="000.000.000-00">
      </label>
      <label style="font-size:13px;font-weight:600">CEP
        <input type="text" id="ct-cep" style="width:100%;margin-top:4px" maxlength="9" placeholder="00000-000">
      </label>
      <label style="font-size:13px;font-weight:600">N&uacute;mero
        <input type="text" id="ct-numero" style="width:100%;margin-top:4px" placeholder="123">
      </label>
      <label style="font-size:13px;font-weight:600;grid-column:1/-1">Logradouro
        <input type="text" id="ct-logradouro" style="width:100%;margin-top:4px" placeholder="Rua, Av, Travessa...">
      </label>
      <label style="font-size:13px;font-weight:600">Complemento
        <input type="text" id="ct-complemento" style="width:100%;margin-top:4px" placeholder="Apto, Bloco...">
      </label>
      <label style="font-size:13px;font-weight:600">Bairro
        <input type="text" id="ct-bairro" style="width:100%;margin-top:4px">
      </label>
      <label style="font-size:13px;font-weight:600;grid-column:1/-1">Cidade
        <input type="text" id="ct-cidade" style="width:100%;margin-top:4px">
      </label>
      <label style="font-size:13px;font-weight:600;grid-column:1/-1">Classifica&ccedil;&atilde;o
        <select id="ct-classificacao" style="width:100%;margin-top:4px">
          <option value="">— Sem classificação —</option>
          <option>Excelente</option><option>Bom</option><option>Regular</option><option>Ruim</option><option>Inadimplente</option>
        </select>
      </label>
      <label style="font-size:13px;font-weight:600;grid-column:1/-1">Observa&ccedil;&atilde;o
        <textarea id="ct-observacoes" rows="3" style="width:100%;margin-top:4px"></textarea>
      </label>
    </div>

    <div style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end">
      <button class="button" id="ct-modal-close2">Cancelar</button>
      <button class="button button-primary" id="ct-btn-salvar">Salvar</button>
    </div>
    <div id="ct-msg" style="margin-top:12px;font-size:13px;display:none"></div>
  </div>
</div>

<script>
(function($){
  var nonce    = '<?php echo esc_js($nonce); ?>';
  var ajaxUrl  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  var clsCores = <?php echo wp_json_encode($cls_cores); ?>;
  var statusPedidoLabel = {novo:'Novo',confirmado:'Confirmado',em_preparo:'Em Preparo',saiu:'Saiu p/ Entrega',entregue:'Entregue',cancelado:'Cancelado'};
  var statusLeadLabel   = {novo:'Novo',contatado:'Contatado',negociando:'Negociando',fechado:'Fechado',perdido:'Perdido'};
  var currentCt = null;

  // ── Filtro de busca ──────────────────────────────────────────────────────────
  window.ctFiltrar = function(){
    var q = document.getElementById('ct-search').value.toLowerCase();
    var rows = document.querySelectorAll('#ct-tbody .ct-row');
    var vis = 0;
    rows.forEach(function(r){
      var show = r.dataset.search.indexOf(q) > -1;
      r.style.display = show ? '' : 'none';
      if(show) vis++;
    });
    document.getElementById('ct-total').textContent = vis + ' contato(s)';
  };

  // ── Abrir painel 360° ────────────────────────────────────────────────────────
  $(document).on('click','.ct-btn-ver', function(){
    var ct = JSON.parse($(this).closest('tr').attr('data-ct') || '{}');
    abrirPerfil(ct);
  });

  $(document).on('click','.ct-row td:not(:last-child)', function(){
    var ct = JSON.parse($(this).closest('tr').attr('data-ct') || '{}');
    abrirPerfil(ct);
  });

  function abrirPerfil(ct){
    currentCt = ct;
    // Highlight row
    $('.ct-row').removeClass('selected');
    $('#ct-tbody .ct-row').filter(function(){ try{ return JSON.parse($(this).attr('data-ct')||'{}').id === ct.id; }catch(e){return false;} }).addClass('selected');

    // Header
    $('#ct-d-nome').text(ct.nome || '—');
    var meta = [];
    if(ct.whatsapp) meta.push(ct.whatsapp);
    if(ct.email)    meta.push(ct.email);
    $('#ct-d-meta').text(meta.join(' · '));

    // Classificação
    if(ct.classificacao){
      $('#ct-d-classificacao-wrap').show();
      var cor = clsCores[ct.classificacao] || '#6b7280';
      $('#ct-d-classificacao-badge').html('<span style="background:'+cor+';color:#fff;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600">'+escHtml(ct.classificacao)+'</span>');
    } else {
      $('#ct-d-classificacao-wrap').hide();
    }

    // Contato info
    var info = [];
    if(ct.cpf)   info.push('<b>CPF:</b> '+escHtml(ct.cpf));
    if(ct.total_atendimentos) info.push('<b>Atendimentos:</b> '+ct.total_atendimentos);
    if(ct.observacoes) info.push('<b>Obs:</b> '+escHtml(ct.observacoes));
    $('#ct-d-info').html(info.length ? info.join('<br>') : '<span class="ct-empty">—</span>');

    // Endereço
    var end = [];
    var logr = [ct.logradouro, ct.numero].filter(Boolean).join(', ');
    if(logr)         end.push(escHtml(logr));
    if(ct.complemento) end.push(escHtml(ct.complemento));
    var loc = [ct.bairro, ct.cidade].filter(Boolean).join(' — ');
    if(loc)          end.push(escHtml(loc));
    if(ct.cep)       end.push('CEP: '+escHtml(ct.cep));
    $('#ct-d-endereco').html(end.length ? end.join('<br>') : '<span class="ct-empty">—</span>');

    // Histórico (loading)
    $('#ct-d-cards,#ct-d-pedidos,#ct-d-leads').html('<span class="ct-empty">Carregando...</span>');
    $('#ct-d-cards-count,#ct-d-pedidos-count,#ct-d-leads-count').text('');

    $('#ct-detail').addClass('open');

    // Busca histórico
    $.post(ajaxUrl, { action:'tao_crm_contato_perfil', nonce:nonce, contato_id:ct.id||'', whatsapp:ct.whatsapp||'' }, function(res){
      if(!res.success) return;
      var d = res.data;

      // Cards
      $('#ct-d-cards-count').text(d.cards.length ? '('+d.cards.length+')' : '');
      if(d.cards.length){
        var html = '';
        d.cards.forEach(function(c){
          var dt = c.criado_em ? new Date(c.criado_em).toLocaleDateString('pt-BR') : '';
          var cor = c.estagio_cor || '#6b7280';
          var status = c.fechado ? '<span style="color:#9ca3af;font-size:10px">Fechado</span>' : '<span class="ct-badge" style="background:'+cor+'">'+escHtml(c.estagio_nome)+'</span>';
          html += '<div class="ct-timeline-item"><div style="flex:1"><div style="font-weight:500">'+escHtml(c.titulo||c.contato_nome||'—')+'</div><div style="color:#9ca3af;font-size:11px">'+dt+'</div></div>'+status+'</div>';
        });
        $('#ct-d-cards').html(html);
      } else {
        $('#ct-d-cards').html('<span class="ct-empty">Nenhum card CRM</span>');
      }

      // Pedidos
      $('#ct-d-pedidos-count').text(d.pedidos.length ? '('+d.pedidos.length+')' : '');
      if(d.pedidos.length){
        var sCores = {novo:'#2271b1',confirmado:'#00a32a',em_preparo:'#b45309',saiu:'#7c3aed',entregue:'#1e3a2f',cancelado:'#cc1818'};
        var html = '';
        d.pedidos.forEach(function(p){
          var dt  = p.criado_em ? new Date(p.criado_em).toLocaleDateString('pt-BR') : '';
          var val = p.valor_total ? 'R$ '+parseFloat(p.valor_total).toFixed(2).replace('.',',') : '';
          var cor = sCores[p.status] || '#888';
          var lbl = statusPedidoLabel[p.status] || p.status;
          html += '<div class="ct-timeline-item"><div style="flex:1"><div style="font-weight:500">'+val+'</div><div style="color:#9ca3af;font-size:11px">'+dt+'</div></div><span class="ct-badge" style="background:'+cor+'">'+escHtml(lbl)+'</span></div>';
        });
        $('#ct-d-pedidos').html(html);
      } else {
        $('#ct-d-pedidos').html('<span class="ct-empty">Nenhum pedido</span>');
      }

      // Leads
      $('#ct-d-leads-count').text(d.leads.length ? '('+d.leads.length+')' : '');
      if(d.leads.length){
        var lCores = {novo:'#2271b1',contatado:'#00a32a',negociando:'#f0b429',fechado:'#3c434a',perdido:'#cc1818'};
        var html = '';
        d.leads.forEach(function(l){
          var dt  = l.criado_em ? new Date(l.criado_em).toLocaleDateString('pt-BR') : '';
          var cor = lCores[l.status] || '#888';
          var lbl = statusLeadLabel[l.status] || l.status;
          html += '<div class="ct-timeline-item"><div style="flex:1"><div style="font-weight:500">'+escHtml(l.interesse||l.nome||'—')+'</div><div style="color:#9ca3af;font-size:11px">'+dt+'</div></div><span class="ct-badge" style="background:'+cor+'">'+escHtml(lbl)+'</span></div>';
        });
        $('#ct-d-leads').html(html);
      } else {
        $('#ct-d-leads').html('<span class="ct-empty">Nenhum lead</span>');
      }
    });
  }

  // ── Fechar painel ────────────────────────────────────────────────────────────
  $('#ct-detail-close').on('click', function(){
    $('#ct-detail').removeClass('open');
    $('.ct-row').removeClass('selected');
    currentCt = null;
  });

  // ── Editar pelo painel ───────────────────────────────────────────────────────
  $(document).on('click','.ct-btn-editar-detalhe', function(){
    if(currentCt) ctAbrirModal(currentCt);
  });

  // ── Novo contato ─────────────────────────────────────────────────────────────
  $('#ct-btn-novo').on('click', function(){ ctAbrirModal(null); });

  // ── Editar pelo botão da linha ────────────────────────────────────────────────
  $(document).on('click','.ct-btn-editar', function(e){
    e.stopPropagation();
    var ct = JSON.parse($(this).closest('tr').attr('data-ct') || '{}');
    ctAbrirModal(ct);
  });

  // ── Fechar modal ─────────────────────────────────────────────────────────────
  $('#ct-modal-close,#ct-modal-close2').on('click', function(){ $('#ct-modal').hide(); });
  $('#ct-modal').on('click', function(e){ if($(e.target).is('#ct-modal')) $(this).hide(); });

  function ctAbrirModal(ct){
    var novo = !ct || !ct.id;
    $('#ct-modal-title').text(novo ? 'Novo Contato' : 'Editar Contato');
    $('#ct-id').val(ct && ct.id ? ct.id : '');
    $('#ct-nome').val(ct && ct.nome ? ct.nome : '');
    $('#ct-whatsapp').val(ct && ct.whatsapp ? ct.whatsapp : '');
    $('#ct-email').val(ct && ct.email ? ct.email : '');
    $('#ct-cpf').val(ct && ct.cpf ? ct.cpf : '');
    $('#ct-cep').val(ct && ct.cep ? ct.cep : '');
    $('#ct-numero').val(ct && ct.numero ? ct.numero : '');
    $('#ct-logradouro').val(ct && ct.logradouro ? ct.logradouro : '');
    $('#ct-complemento').val(ct && ct.complemento ? ct.complemento : '');
    $('#ct-bairro').val(ct && ct.bairro ? ct.bairro : '');
    $('#ct-cidade').val(ct && ct.cidade ? ct.cidade : '');
    $('#ct-classificacao').val(ct && ct.classificacao ? ct.classificacao : '');
    $('#ct-observacoes').val(ct && ct.observacoes ? ct.observacoes : '');
    if(ct && ct.workspace_id) $('#ct-workspace-id').val(ct.workspace_id);
    ctMsg('','');
    $('#ct-modal').fadeIn(150);
    $('#ct-nome').focus();
  }

  function ctMsg(txt, tipo){
    var $m = $('#ct-msg');
    if(!txt){ $m.hide(); return; }
    $m.text(txt).css('color', tipo==='ok'?'#22c55e':'#ef4444').show();
  }

  function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  // ── Salvar ───────────────────────────────────────────────────────────────────
  $('#ct-btn-salvar').on('click', function(){
    var wsId = $('#ct-workspace-sel').length ? $('#ct-workspace-sel').val() : $('#ct-workspace-id').val();
    var data = {
      action:'tao_crm_save_contato', nonce:nonce,
      id:            $('#ct-id').val(),
      workspace_id:  wsId,
      nome:          $('#ct-nome').val().trim(),
      whatsapp:      $('#ct-whatsapp').val().trim(),
      email:         $('#ct-email').val().trim(),
      cpf:           $('#ct-cpf').val().trim(),
      cep:           $('#ct-cep').val().trim(),
      numero:        $('#ct-numero').val().trim(),
      logradouro:    $('#ct-logradouro').val().trim(),
      complemento:   $('#ct-complemento').val().trim(),
      bairro:        $('#ct-bairro').val().trim(),
      cidade:        $('#ct-cidade').val().trim(),
      classificacao: $('#ct-classificacao').val(),
      observacoes:   $('#ct-observacoes').val().trim(),
    };
    if(!data.nome || !data.whatsapp){ ctMsg('Nome e WhatsApp são obrigatórios','err'); return; }
    $('#ct-btn-salvar').prop('disabled',true).text('Salvando...');
    $.post(ajaxUrl, data, function(res){
      if(res.success){
        ctMsg('Contato salvo!','ok');
        setTimeout(function(){ location.reload(); }, 800);
      } else {
        ctMsg(res.data || 'Erro ao salvar','err');
        $('#ct-btn-salvar').prop('disabled',false).text('Salvar');
      }
    });
  });

})(jQuery);
</script>
</div>
