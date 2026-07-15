<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Dados agregados do painel de entregas (por período). */
add_action( 'wp_ajax_tao_entregas_painel_dados', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_entregas_nonce', 'nonce' );
    if ( ! tao_entregas_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $ws = function_exists( 'tao_crm_get_workspace' ) ? tao_crm_get_workspace() : null;
    if ( ! $ws || empty( $ws['id'] ) ) wp_send_json_error( [ 'message' => 'Workspace não encontrado' ] );
    $wsid = $ws['id'];
    $de  = sanitize_text_field( $_GET['de']  ?? gmdate( 'Y-m-01' ) );
    $ate = sanitize_text_field( $_GET['ate'] ?? gmdate( 'Y-m-d' ) );

    $r = tao_entregas_api(
        "/entregas?workspace_id=eq.$wsid&criado_em=gte.$de&criado_em=lte.{$ate}T23:59:59" .
        "&select=tipo,status,custo,valor_receber,pago,entregador,dt_entrega,criado_em&order=criado_em.desc&limit=5000"
    );
    $lista = $r['ok'] ? ( $r['data'] ?? [] ) : [];

    $tipos = tao_entregas_tipos();
    $por_status = [ 'pendente' => 0, 'em_rota' => 0, 'entregue' => 0, 'nao_entregue' => 0 ];
    $por_tipo = []; $por_dia = []; $custo_total = 0.0; $receber_total = 0.0; $pago_total = 0.0;
    foreach ( $lista as $e ) {
        $st = $e['status'] ?? 'pendente';
        if ( isset( $por_status[ $st ] ) ) $por_status[ $st ]++;
        $tp = $e['tipo'] ?: '—';
        if ( ! isset( $por_tipo[ $tp ] ) ) $por_tipo[ $tp ] = [ 'qtd' => 0, 'custo' => 0.0 ];
        $por_tipo[ $tp ]['qtd']++; $por_tipo[ $tp ]['custo'] += (float) ( $e['custo'] ?? 0 );
        $dia = substr( $e['criado_em'] ?? '', 0, 10 );
        if ( $dia ) { if ( ! isset( $por_dia[ $dia ] ) ) $por_dia[ $dia ] = 0; $por_dia[ $dia ]++; }
        $custo_total   += (float) ( $e['custo'] ?? 0 );
        $receber_total += (float) ( $e['valor_receber'] ?? 0 );
        if ( ! empty( $e['pago'] ) ) $pago_total += (float) ( $e['valor_receber'] ?? 0 );
    }
    krsort( $por_dia );
    // rótulos amigáveis de tipo
    $por_tipo_lbl = [];
    foreach ( $por_tipo as $k => $v ) $por_tipo_lbl[ $tipos[ $k ] ?? $k ] = $v;

    wp_send_json_success( [
        'total' => count( $lista ), 'por_status' => $por_status, 'por_tipo' => $por_tipo_lbl,
        'por_dia' => $por_dia, 'custo_total' => $custo_total, 'receber_total' => $receber_total, 'pago_total' => $pago_total,
    ] );
} );

/** Tela do painel gerencial de entregas. */
function tao_entregas_page_painel() {
    if ( ! tao_entregas_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    $nonce = wp_create_nonce( 'tao_entregas_nonce' );
    $ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
    ?>
    <div class="wrap taoent-wrap">
    <h1>🚚 Entregas</h1>
    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <label style="font-size:12px">De <input type="date" id="taoent-de" value="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>"></label>
        <label style="font-size:12px">Até <input type="date" id="taoent-ate" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></label>
        <button type="button" class="button" id="taoent-filtrar">Filtrar</button>
    </div>
    <div id="taoent-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div><h2 style="font-size:15px">Por tipo</h2><div id="taoent-tipo"></div></div>
        <div><h2 style="font-size:15px">Por dia</h2><div id="taoent-dia"></div></div>
    </div>
    <style>
    .taoent-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px}
    .taoent-kpi .v{font-size:22px;font-weight:700;color:#0f172a}.taoent-kpi .l{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
    .taoent-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taoent-tb th,.taoent-tb td{padding:6px 10px;border-bottom:1px solid #f1f5f9;text-align:left}.taoent-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    </style>
    <script>
    jQuery(function($){
        var ajax='<?php echo $ajax; ?>', nonce='<?php echo $nonce; ?>';
        function brl(n){return 'R$ '+parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function fdata(d){var p=String(d).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
        function carregar(){
            $.getJSON(ajax,{action:'tao_entregas_painel_dados',nonce:nonce,de:$('#taoent-de').val(),ate:$('#taoent-ate').val()},function(r){
                if(!r.success){$('#taoent-cards').html('<p style="color:#dc2626">'+esc(r.data&&r.data.message||'erro')+'</p>');return;}
                var d=r.data, s=d.por_status;
                var kpis=[['Total',d.total],['Pendentes',s.pendente],['Em rota',s.em_rota],['Entregues',s.entregue],['Não entregues',s.nao_entregue],['Custo (frete)',brl(d.custo_total)],['A receber',brl(d.receber_total)],['Recebido',brl(d.pago_total)]];
                $('#taoent-cards').html(kpis.map(function(k){return '<div class="taoent-kpi"><div class="v">'+esc(k[1])+'</div><div class="l">'+esc(k[0])+'</div></div>';}).join(''));
                var tr=Object.keys(d.por_tipo).map(function(t){var v=d.por_tipo[t];return '<tr><td>'+esc(t)+'</td><td style="text-align:right">'+v.qtd+'</td><td style="text-align:right">'+brl(v.custo)+'</td></tr>';}).join('');
                $('#taoent-tipo').html('<table class="taoent-tb"><tr><th>Tipo</th><th>Qtd</th><th>Custo</th></tr>'+(tr||'<tr><td colspan=3 style="color:#94a3b8">sem dados</td></tr>')+'</table>');
                var dr=Object.keys(d.por_dia).map(function(dia){return '<tr><td>'+fdata(dia)+'</td><td style="text-align:right">'+d.por_dia[dia]+'</td></tr>';}).join('');
                $('#taoent-dia').html('<table class="taoent-tb"><tr><th>Dia</th><th>Entregas</th></tr>'+(dr||'<tr><td colspan=2 style="color:#94a3b8">sem dados</td></tr>')+'</table>');
            });
        }
        $('#taoent-filtrar').on('click',carregar);
        carregar();
    });
    </script>
    </div>
    <?php
}
