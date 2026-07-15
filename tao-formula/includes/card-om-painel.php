<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PEÇA 2/3 — Aprovação da formulação a partir do CARD (e da lista de OMs pendentes).
 *
 * Modelo "em testes": tudo atrás da chave tao_formula_om_ganho_ativo() (default OFF).
 * Com a chave desligada NADA aparece no card e NADA muda na operação. Ligada (por workspace),
 * o painel aparece e habilita:
 *   - Aprovar formulação  → move o card Aguardando Produção → Em Produção + histórico (capacidade,
 *     não obrigatória: o card continua podendo ser movido normalmente).
 *   - Estornar aprovação  → volta Em Produção → Aguardando Produção + histórico.
 *
 * A "aprovação" é representada pela FASE do card (Em Produção = aprovado). Auditoria = crm_cards_historico.
 * Sem colunas novas na OM, sem migration.
 */

/** Resolve os estágios de produção do pipeline Pós-vendas do workspace, por NOME (multi-tenant). */
function tao_formula_estagios_producao( $ws ) {
	$pl = $ws ? get_option( 'tao_crm_pos_vendas_pipeline_' . $ws, '' ) : '';
	if ( ! $pl && $ws ) {
		$rp = tao_formula_api( "/crm_pipelines?workspace_id=eq.$ws&ativo=eq.true&order=ordem.asc&select=id&limit=2" );
		$ap = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];
		if ( count( $ap ) >= 2 ) $pl = $ap[1]['id'];
	}
	if ( ! $pl ) return [ 'pipeline' => null, 'aguardando' => null, 'em_producao' => null ];
	$re = tao_formula_api( "/crm_estagios?pipeline_id=eq.$pl&order=ordem.asc&select=id,nome" );
	$aguard = null; $emprod = null;
	foreach ( ( $re['ok'] ? ( $re['data'] ?? [] ) : [] ) as $s ) {
		$n = mb_strtoupper( $s['nome'] ?? '' );
		if ( $aguard === null && mb_strpos( $n, 'AGUARDANDO PRODU' ) !== false ) $aguard = $s['id'];
		if ( $emprod === null && mb_strpos( $n, 'EM PRODU' ) !== false )        $emprod = $s['id'];
	}
	return [ 'pipeline' => $pl, 'aguardando' => $aguard, 'em_producao' => $emprod ];
}

/** Painel de OM/aprovação no card — só quando a chave está ligada para o workspace. */
add_action( 'tao_crm_card_paineis', function ( $card ) {
	if ( ! is_array( $card ) || empty( $card['id'] ) ) return;
	if ( ! function_exists( 'tao_formula_can_access' ) || ! tao_formula_can_access() ) return;
	// Com a chave desligada, o painel só aparece nos cards que JÁ têm OM (os de teste) — os cards
	// normais da operação não mostram nada novo. Com a chave ligada, aparece sempre (permite gerar OM).
	if ( ! tao_formula_om_ganho_ativo( $card['workspace_id'] ?? '' ) ) {
		$rchk = tao_formula_api( "/lab_ordens?card_id=eq.{$card['id']}&select=id&limit=1" );
		if ( ! $rchk['ok'] || empty( $rchk['data'] ) ) return;   // chave OFF + sem OM → oculto
	}
	$cid   = esc_attr( $card['id'] );
	$nonce = wp_create_nonce( 'tao_formula_nonce' );
	$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
	?>
	<div class="crm-itens-section" id="taof-om-section" style="margin-top:10px"
	     data-card="<?php echo $cid; ?>" data-nonce="<?php echo $nonce; ?>" data-ajax="<?php echo $ajax; ?>">
		<div class="crm-itens-header" style="display:flex;align-items:center;justify-content:space-between">
			<strong style="font-size:13px">&#x1F9EA; Ordem de Manipulação</strong>
			<span id="taof-om-msg" style="font-size:11px;color:#94a3b8"></span>
		</div>
		<div id="taof-om-body" style="font-size:12px;color:#94a3b8;padding:4px 0">Carregando…</div>
	</div>
	<script>
	(function(){
		var $s=jQuery('#taof-om-section'), ajax=$s.data('ajax'), nonce=$s.data('nonce'), card=$s.data('card');
		function esc(t){return jQuery('<span>').text(t==null?'':t).html();}
		function msg(t,c){ jQuery('#taof-om-msg').text(t||'').css('color',c||'#94a3b8'); }
		function carregar(){
			jQuery.getJSON(ajax,{action:'tao_formula_card_om_info',nonce:nonce,card_id:card},function(r){
				if(!r||!r.success){ jQuery('#taof-om-body').html('<span style="color:#dc2626">'+esc(r&&r.data||'erro')+'</span>'); return; }
				render(r.data);
			});
		}
		function render(d){
			var b=jQuery('#taof-om-body'); var h='';
			if(!d.tem_om){
				h='<div style="color:#64748b">Sem OM ainda para este card.'+(d.orc_id?' <button type="button" class="button button-small" id="taof-om-gerar">Gerar OM</button>':' (sem orçamento vinculado)')+'</div>';
				b.html(h);
				jQuery('#taof-om-gerar').on('click',function(){ acao('tao_formula_prod_gerar_om',{orc_id:d.orc_id}); });
				return;
			}
			h+='<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px">';
			h+='<div><strong>OM '+esc(d.om.numero||'')+'</strong> · '+esc(d.om.itens)+' itens · fase: '+esc(d.fase_nome||'—')+'</div>';
			h+='<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
			if(d.pode_aprovar) h+='<button type="button" class="button button-primary button-small" id="taof-om-aprovar">✅ Aprovar formulação</button>';
			if(d.pode_estornar) h+='<button type="button" class="button button-small" id="taof-om-estornar">↩ Estornar aprovação</button>';
			h+='<button type="button" class="button button-small taof-om-ficha">🖨 Ficha de Pesagem</button>';
			h+='<button type="button" class="button button-small taof-om-rotulo">🏷 Rótulo</button>';
			h+='</div>';
			h+='<div style="margin-top:4px;font-size:10px;color:#94a3b8">Aprovar move o card para <em>Em Produção</em> (não é obrigatório — o card pode ser movido normalmente).</div>';
			h+='</div>';
			b.html(h);
			jQuery('#taof-om-aprovar').on('click',function(){ acao('tao_formula_card_aprovar',{card_id:card}); });
			jQuery('#taof-om-estornar').on('click',function(){ acao('tao_formula_card_estornar',{card_id:card}); });
			jQuery('.taof-om-ficha').on('click',function(){ abrirFicha(d.om.id); });
			jQuery('.taof-om-rotulo').on('click',function(){ abrirRotulo(d.om.id); });
		}
		function fdata(s){ if(!s)return '—'; var p=(''+s).substr(0,10).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }
		// CSS de impressão robusto — A4 em qualquer impressora (Epson jato/laser). Margens no @page,
		// fundo do cabeçalho forçado, tabela sem quebra no meio de uma linha.
		function taofFichaCSS(){
			return '<style>'+
				'@page{size:A4 portrait;margin:12mm}'+
				'*{box-sizing:border-box}'+
				'body{margin:0;font-family:Arial,Helvetica,sans-serif;color:#000;font-size:12px;line-height:1.35}'+
				'h2{font-size:16px;margin:0 0 4px}'+
				'table{width:100%;border-collapse:collapse}'+
				'th,td{border:1px solid #000;padding:4px 6px;vertical-align:top}'+
				'th{background:#eee!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}'+
				'tr,td,th{page-break-inside:avoid}'+
				'small{font-size:10px;color:#333}'+
				'@media print{body{margin:0}}'+
			'</style>';
		}
		// Ficha de Manipulação — abre em MODAL (render server-side), imprime/exporta PDF
		function abrirFicha(ordem_id){
			var $m = jQuery('#taof-ficha-modal');
			if(!$m.length){
				$m = jQuery('<div id="taof-ficha-modal" style="display:none;position:fixed;inset:0;z-index:100050">'+
					'<div class="tfm-ov" style="position:fixed;inset:0;background:rgba(15,23,42,.55)"></div>'+
					'<div class="tfm-box" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;width:920px;max-width:96vw;max-height:92vh;overflow:auto;padding:18px 20px;box-shadow:0 10px 40px rgba(0,0,0,.3)">'+
					'<div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:12px">'+
					'<button type="button" class="button button-primary tfm-print">🖨 Imprimir / Salvar PDF</button>'+
					'<button type="button" class="button tfm-close">Fechar</button></div>'+
					'<div class="tfm-body"></div></div></div>').appendTo('body');
				$m.on('click','.tfm-ov,.tfm-close',function(){ $m.hide(); });
				$m.on('click','.tfm-print',function(){ window.print(); });
			}
			$m.find('.tfm-body').html('<p style="color:#94a3b8;padding:30px;text-align:center">Carregando ficha…</p>');
			$m.show();
			jQuery.getJSON(ajax,{action:'tao_formula_prod_ficha',nonce:nonce,ordem_id:ordem_id},function(r){
				if(!r||!r.success){ $m.find('.tfm-body').html('<p style="color:#dc2626;padding:20px">'+((r&&r.data&&r.data.message)||'Erro ao abrir a ficha')+'</p>'); return; }
				$m.find('.tfm-body').html(r.data.html);
			});
		}
		// Rótulo RDC 67 (janela imprimível) — reusa o handler prod_rotulo
		function abrirRotulo(ordem_id){
			jQuery.getJSON(ajax,{action:'tao_formula_prod_rotulo',nonce:nonce,ordem_id:ordem_id},function(r){
				if(!r||!r.success){ alert((r&&r.data&&r.data.message)||'Erro'); return; }
				var d=r.data;
				var comp=(d.composicao||[]).map(function(c){return '<div>'+esc(c)+'</div>';}).join('');
				var html='<div style="font-family:Arial,sans-serif;font-size:12px;width:320px;border:1px solid #000;padding:10px;line-height:1.35">'+
					'<div style="font-weight:bold;font-size:13px">'+esc(d.farmacia)+'</div>'+
					'<div style="font-size:10px">CNPJ '+esc(d.cnpj)+' · '+esc(d.farm_end)+'</div>'+
					'<div style="font-size:10px;border-bottom:1px solid #000;padding-bottom:4px;margin-bottom:4px">RT: '+esc(d.rt||'—')+'</div>'+
					'<div><b>OM '+esc(d.om)+'</b> — <b>'+esc(d.advertencia)+'</b></div>'+
					'<div>Paciente: <b>'+esc(d.paciente)+'</b></div>'+
					(d.prescritor?'<div>Prescritor: '+esc(d.prescritor)+'</div>':'')+
					'<div>Fórmula: '+esc(d.formula)+(d.qtd?' — '+esc(d.qtd)+' un':'')+'</div>'+
					'<div style="margin:4px 0"><b>Composição:</b>'+comp+'</div>'+
					(d.posologia?'<div><b>Posologia:</b> '+esc(d.posologia)+'</div>':'')+
					'<div>Manipulado: '+fdata(d.dt_manip)+' · <b>Validade: '+fdata(d.validade)+'</b></div>'+
					'<div style="font-size:10px;margin-top:3px">'+esc(d.conservacao)+'</div></div>';
				if(d.aviso) html='<p style="color:#d97706;font-size:12px">⚠ '+esc(d.aviso)+'</p>'+html;
				var w=window.open('','rotulo','width=420,height=560');
				w.document.write('<html><head><title>Rótulo OM '+esc(d.om)+'</title></head><body onload="window.print()" style="margin:12px">'+html+'</body></html>');
				w.document.close();
			});
		}
		function acao(action,extra){
			msg('processando…');
			var data=jQuery.extend({action:action,nonce:nonce},extra||{});
			jQuery.post(ajax,data,function(r){
				if(r&&r.success){ msg('✔ ok','#16a34a'); carregar(); }
				else { msg('✘ '+((r&&r.data&&(r.data.msg||r.data))||'erro'),'#dc2626'); }
			},'json').fail(function(){ msg('✘ falha','#dc2626'); });
		}
		carregar();
	})();
	</script>
	<?php
}, 5 );   // prioridade 5 → o painel de OM renderiza ANTES do painel de Entrega (prioridade 10)

/** GET — dados da OM do card + decisão dos botões (aprovar/estornar) pela fase atual. */
add_action( 'wp_ajax_tao_formula_card_om_info', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
	$card_id = sanitize_text_field( $_GET['card_id'] ?? '' );
	if ( ! $card_id ) wp_send_json_error( 'card_id ausente' );

	$rc = tao_formula_api( "/crm_cards?id=eq.$card_id&select=id,workspace_id,estagio_id&limit=1" );
	if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
	$card = $rc['data'][0];
	$ws   = $card['workspace_id'] ?? '';
	$est  = tao_formula_estagios_producao( $ws );

	// OM do card (a mais recente)
	$rom = tao_formula_api( "/lab_ordens?card_id=eq.$card_id&select=id,numero,etapa_id&order=criado_em.desc&limit=1" );
	$om  = ( $rom['ok'] && ! empty( $rom['data'] ) ) ? $rom['data'][0] : null;

	// orçamento p/ gerar OM manualmente, se ainda não houver
	$orc_id = null;
	if ( ! $om ) {
		$roc = tao_formula_api( "/orcamentos?card_id=eq.$card_id&select=id&order=criado_em.desc&limit=1" );
		if ( $roc['ok'] && ! empty( $roc['data'] ) ) $orc_id = $roc['data'][0]['id'];
	}

	$n_itens = 0; $fase_nome = '';
	if ( $om ) {
		$ri = tao_formula_api( "/lab_ordem_itens?ordem_id=eq.{$om['id']}&select=id" );
		$n_itens = $ri['ok'] ? count( $ri['data'] ?? [] ) : 0;
		$rn = tao_formula_api( "/crm_estagios?id=eq.{$card['estagio_id']}&select=nome&limit=1" );
		$fase_nome = ( $rn['ok'] && ! empty( $rn['data'] ) ) ? $rn['data'][0]['nome'] : '';
	}

	$pode_aprovar  = $om && $est['aguardando'] && $card['estagio_id'] === $est['aguardando'] && $est['em_producao'];
	$pode_estornar = $om && $est['em_producao'] && $card['estagio_id'] === $est['em_producao'] && $est['aguardando'];

	wp_send_json_success( [
		'tem_om'        => (bool) $om,
		'orc_id'        => $orc_id,
		'om'            => $om ? [ 'id' => $om['id'], 'numero' => $om['numero'] ?? '', 'itens' => $n_itens ] : null,
		'fase_nome'     => $fase_nome,
		'pode_aprovar'  => (bool) $pode_aprovar,
		'pode_estornar' => (bool) $pode_estornar,
	] );
} );

/** POST — aprova a formulação: move o card Aguardando Produção → Em Produção + histórico. */
add_action( 'wp_ajax_tao_formula_card_aprovar', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'msg' => 'Acesso negado' ], 403 );
	tao_formula_card_mover_producao( sanitize_text_field( $_POST['card_id'] ?? '' ), 'aprovar' );
} );

/** POST — estorna a aprovação: volta o card Em Produção → Aguardando Produção + histórico. */
add_action( 'wp_ajax_tao_formula_card_estornar', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'msg' => 'Acesso negado' ], 403 );
	tao_formula_card_mover_producao( sanitize_text_field( $_POST['card_id'] ?? '' ), 'estornar' );
} );

/** Núcleo da aprovação/estorno — move o card entre Aguardando Produção e Em Produção. */
function tao_formula_card_mover_producao( $card_id, $modo ) {
	if ( ! $card_id ) wp_send_json_error( [ 'msg' => 'card_id ausente' ] );
	$rc = tao_formula_api( "/crm_cards?id=eq.$card_id&select=id,workspace_id,estagio_id&limit=1" );
	if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( [ 'msg' => 'Card não encontrado' ] );
	$card = $rc['data'][0];
	$est  = tao_formula_estagios_producao( $card['workspace_id'] ?? '' );
	if ( ! $est['aguardando'] || ! $est['em_producao'] )
		wp_send_json_error( [ 'msg' => 'Estágios de produção não encontrados no funil de Pós-vendas.' ] );

	if ( $modo === 'aprovar' ) {
		if ( $card['estagio_id'] !== $est['aguardando'] )
			wp_send_json_error( [ 'msg' => 'O card precisa estar em "Aguardando Produção" para aprovar a formulação.' ] );
		$de = $est['aguardando']; $para = $est['em_producao']; $motivo = 'Formulação aprovada';
	} else {
		if ( $card['estagio_id'] !== $est['em_producao'] )
			wp_send_json_error( [ 'msg' => 'Só é possível estornar quando o card está em "Em Produção".' ] );
		$de = $est['em_producao']; $para = $est['aguardando']; $motivo = 'Aprovação da formulação estornada';
	}

	$pt = tao_formula_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'estagio_id' => $para, 'movido_em' => gmdate( 'c' ) ] );
	if ( ! $pt['ok'] ) wp_send_json_error( [ 'msg' => 'Falha ao mover o card.' ] );
	tao_formula_api( '/crm_cards_historico', 'POST', [
		'card_id'         => $card_id,
		'de_estagio_id'   => $de,
		'para_estagio_id' => $para,
		'usuario_id'      => get_current_user_id(),
		'motivo'          => $motivo,
	] );
	// dispara automações de entrada de fase, se o CRM estiver disponível (não bloqueia se falhar)
	if ( function_exists( 'tao_crm_disparar_automacoes' ) ) {
		try { tao_crm_disparar_automacoes( $card_id, $para, 'entrar_fase' ); } catch ( \Throwable $e ) {}
	}
	wp_send_json_success( [ 'estagio_id' => $para ] );
}
