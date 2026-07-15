<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ficha de Manipulação (Ordem de Manipulação) — render server-side, aberta em MODAL.
 * Espelha a ficha do FCerta: cabeçalho (farmácia + requisição + datas), prescritor,
 * paciente+RG, forma/volume/embalagem/cápsula, composição com código/lote/validade/
 * fornecedor + correções, alertas farmacotécnicos (densidade≠lote, dose máx, sinônimo,
 * controlado), cálculos de peso (teórico total/médio/MP) e assinaturas por etapa.
 *
 * Um único ponto de verdade: o card e a tela Produção só abrem o HTML retornado por
 * tao_formula_prod_ficha num modal e imprimem/exportam (window.print → PDF).
 */

/** dd/mm/aaaa a partir de 'YYYY-MM-DD' (ou ISO). Vazio → '—'. */
function tao_formula_fdata( $d ) {
	$d = (string) $d;
	if ( $d === '' ) return '—';
	$p = explode( '-', substr( $d, 0, 10 ) );
	return count( $p ) === 3 ? "{$p[2]}/{$p[1]}/{$p[0]}" : $d;
}
/** número BR com N casas, sem zeros à toa. */
function tao_formula_nb( $v, $dec = 3 ) {
	return number_format( (float) $v, $dec, ',', '.' );
}

/**
 * Monta o HTML COMPLETO da ficha (com <style> embutido e @media print A4).
 * @return string HTML ou '' se a OM não existir.
 */
function tao_formula_render_ficha_html( $cliente_id, $ordem_id ) {
	if ( ! $cliente_id || ! $ordem_id || ! function_exists( 'tao_formula_api' ) ) return '';

	$ro = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id&select=id,numero,orcamento_id,contato_id,prescritor_id,paciente_nome,paciente_whats,forma_farmac,volume,unidade_vol,qtd_unidades,posologia,tp_uso,dt_manipulacao,dt_validade,criado_em,status,controlado,tp_receita,nr_notificacao,comprador_nome,comprador_doc_tp,comprador_doc_nr,modo_preparo&limit=1" );
	if ( ! $ro['ok'] || empty( $ro['data'] ) ) return '';
	$o = $ro['data'][0];

	// data da prescrição + previsão de retirada — buscadas à parte (tolerante se a migration
	// migration_ficha_om_v1 ainda não foi rodada: fica '—' em vez de quebrar a ficha).
	$o['dt_prescricao'] = null; $o['previsao_retirada'] = null;
	$rx = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&select=dt_prescricao,previsao_retirada&limit=1" );
	if ( $rx['ok'] && ! empty( $rx['data'] ) ) {
		$o['dt_prescricao']     = $rx['data'][0]['dt_prescricao'] ?? null;
		$o['previsao_retirada'] = $rx['data'][0]['previsao_retirada'] ?? null;
	}

	// ── prescritor ──
	$presc = null;
	if ( ! empty( $o['prescritor_id'] ) ) {
		$rp = tao_formula_api( "/prescritores?id=eq.{$o['prescritor_id']}&select=tratamento,nome,tipo_registro,nr_registro,uf_registro&limit=1" );
		if ( $rp['ok'] && ! empty( $rp['data'] ) ) $presc = $rp['data'][0];
	}
	// ── paciente RG ──
	$rg = '';
	if ( ! empty( $o['contato_id'] ) ) {
		$rc = tao_formula_api( "/crm_contatos?id=eq.{$o['contato_id']}&select=rg,rg_orgao,rg_uf&limit=1" );
		if ( $rc['ok'] && ! empty( $rc['data'] ) ) {
			$c = $rc['data'][0];
			$rg = trim( ( $c['rg'] ?? '' ) . ( ! empty( $c['rg_orgao'] ) ? ' ' . $c['rg_orgao'] : '' ) . ( ! empty( $c['rg_uf'] ) ? '/' . $c['rg_uf'] : '' ) );
		}
	}
	// ── farmácia (cabeçalho) ──
	$emp = null;
	$re = tao_formula_api( "/empresa_config?cliente_id=eq.$cliente_id&select=razao_social,nome_fantasia,cnpj,rt_nome,rt_crf,rt_uf&limit=1" );
	if ( $re['ok'] && ! empty( $re['data'] ) ) $emp = $re['data'][0];

	// ── itens MP + ativos + lotes ──
	$ri = tao_formula_api( "/lab_ordem_itens?ordem_id=eq.$ordem_id&select=id,ativo_id,descricao,qtd_prescrita,unidade,qtd_pesada,qtd_pesar,unid_pesar,teor_aplic,equiv_aplic,diluicao_aplic,lote_mp_id,eh_qsp,ordem&order=ordem.asc&limit=200" );
	$itens = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];

	$ids = array_values( array_unique( array_filter( array_column( $itens, 'ativo_id' ) ) ) );
	$ativos = []; $lotes_por_id = [];
	if ( $ids ) {
		$ra = tao_formula_api( "/ativos?id=in.(" . implode( ',', $ids ) . ")&select=id,nome,codigo_fc,densidade,dose_max,uni_dose_max,dose_min,uni_dose_min,controlado" );
		foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $ativos[ $a['id'] ] = $a;
	}
	// lotes usados (pelo lote_mp_id gravado) + fornecedor
	$lote_ids = array_values( array_unique( array_filter( array_column( $itens, 'lote_mp_id' ) ) ) );
	$lotes = [];
	if ( $lote_ids ) {
		$rl = tao_formula_api( "/lab_lotes_mp?id=in.(" . implode( ',', $lote_ids ) . ")&select=id,nr_lote,dt_validade,densidade,fornecedor_id,fabricante" );
		$forn_ids = [];
		foreach ( ( $rl['ok'] ? $rl['data'] : [] ) as $l ) { $lotes[ $l['id'] ] = $l; if ( ! empty( $l['fornecedor_id'] ) ) $forn_ids[ $l['fornecedor_id'] ] = 1; }
		if ( $forn_ids ) {
			$rf = tao_formula_api( "/fornecedores?id=in.(" . implode( ',', array_keys( $forn_ids ) ) . ")&select=id,nome,nome_fantasia" );
			$fn = [];
			foreach ( ( $rf['ok'] ? $rf['data'] : [] ) as $f ) $fn[ $f['id'] ] = $f['nome_fantasia'] ?: $f['nome'];
			foreach ( $lotes as &$lx ) $lx['fornecedor'] = $fn[ $lx['fornecedor_id'] ?? '' ] ?? ( $lx['fabricante'] ?? '' );
			unset( $lx );
		}
	}

	// ── embalagem (itens 'emb' do orçamento) ──
	$embs = [];
	if ( ! empty( $o['orcamento_id'] ) ) {
		$roc = tao_formula_api( "/orcamentos?id=eq.{$o['orcamento_id']}&select=itens&limit=1" );
		if ( $roc['ok'] && ! empty( $roc['data'] ) ) {
			$its = $roc['data'][0]['itens'] ?? [];
			if ( is_string( $its ) ) $its = json_decode( $its, true ) ?: [];
			foreach ( (array) $its as $it ) {
				if ( in_array( ( $it['tipo'] ?? 'mp' ), [ 'emb', 'embalagem' ], true ) ) {
					$embs[] = [ 'nome' => $it['nome'] ?? $it['nome_prescricao'] ?? 'Embalagem', 'qtd' => $it['qtd'] ?? $it['qtd_total_g'] ?? 1 ];
				}
			}
		}
	}

	// ── cálculos de peso + alertas ──
	$peso_total = 0.0; $peso_mp = 0.0; $alertas = [];
	foreach ( $itens as $it ) {
		$peso_total += (float) ( $it['qtd_pesar'] ?? 0 );
		if ( empty( $it['eh_qsp'] ) ) $peso_mp += (float) ( $it['qtd_pesar'] ?? 0 );
		$n   = (int) ( $it['ordem'] ?? 0 ) + 1;
		$at  = $ativos[ $it['ativo_id'] ?? '' ] ?? null;
		$lt  = $lotes[ $it['lote_mp_id'] ?? '' ] ?? null;
		// sinônimo usado (prescrição diverge do produto real)
		$nome_real = $at['nome'] ?? '';
		$prescrito = trim( (string) ( $it['descricao'] ?? '' ) );
		if ( $at && $prescrito !== '' && mb_strtoupper( $prescrito ) !== mb_strtoupper( $nome_real ) )
			$alertas[] = [ $n, 'Sinônimo: “' . $prescrito . '” → ' . $nome_real . ( ! empty( $at['codigo_fc'] ) ? ' (cód ' . $at['codigo_fc'] . ')' : '' ), 'info' ];
		// densidade difere do lote
		if ( $at && $lt && $at['densidade'] !== null && ! empty( $lt['densidade'] ) && abs( (float) $at['densidade'] - (float) $lt['densidade'] ) > 0.001 )
			$alertas[] = [ $n, 'Densidade do cadastro (' . tao_formula_nb( $at['densidade'], 2 ) . ') difere do lote (' . tao_formula_nb( $lt['densidade'], 2 ) . ')', 'warn' ];
		// dose máxima
		if ( $at && empty( $it['eh_qsp'] ) ) {
			if ( $at['dose_max'] === null || $at['dose_max'] === '' ) $alertas[] = [ $n, 'Dose máxima não informada no cadastro', 'warn' ];
			elseif ( (float) ( $it['qtd_prescrita'] ?? 0 ) > (float) $at['dose_max'] )
				$alertas[] = [ $n, 'DOSE ACIMA DA MÁXIMA (' . tao_formula_nb( $at['dose_max'], 3 ) . ' ' . ( $at['uni_dose_max'] ?? '' ) . ')', 'crit' ];
		}
		// controlado
		if ( $at && ! empty( $at['controlado'] ) )
			$alertas[] = [ $n, 'VENDA SOB PRESCRIÇÃO — substância controlada (Portaria 344/98)', 'crit' ];
	}
	// nº de unidades p/ peso médio (cápsulas)
	$n_unid = 0;
	if ( preg_match( '/(\d+)/', (string) $o['volume'], $m ) ) $n_unid = (int) $m[1];
	if ( $n_unid <= 0 ) $n_unid = max( 1, (int) ( $o['qtd_unidades'] ?? 1 ) );
	$peso_medio = $n_unid > 0 ? $peso_total / $n_unid : 0;

	// ── uso ──
	$uso = strtolower( (string) ( $o['tp_uso'] ?? '' ) );
	$uso_txt = $uso === 'externo' ? 'USO EXTERNO' : ( $uso === 'veterinario' ? 'USO VETERINÁRIO' : 'USO INTERNO / ORAL' );

	// ══════════════════════════ HTML ══════════════════════════
	$e = fn( $s ) => esc_html( (string) $s );
	ob_start(); ?>
	<style>
	.tfk{--ink:#152229;--mut:#5b6b74;--line:#d6dee2;--petrol:#0e6e6a;--wash:#eef5f4;--crit:#c1394a;--warn:#b0721a;
		font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);font-size:12.5px;line-height:1.4;background:#fff}
	.tfk *{box-sizing:border-box}
	.tfk .doc{max-width:820px;margin:0 auto;padding:4px}
	.tfk .hd{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;border-bottom:2.5px solid var(--petrol);padding-bottom:8px}
	.tfk .hd .farm{font-size:16px;font-weight:800;letter-spacing:-.01em;color:var(--petrol)}
	.tfk .hd .farm small{display:block;font-size:10.5px;font-weight:400;color:var(--mut);letter-spacing:0}
	.tfk .hd .req{text-align:right;flex:none}
	.tfk .hd .req .lab{font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);font-weight:600}
	.tfk .hd .req .om{font-family:ui-monospace,Consolas,monospace;font-size:21px;font-weight:800;color:var(--ink);letter-spacing:.02em}
	.tfk .hd .req .tt{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--petrol);font-weight:700}
	.tfk .grid{display:grid;grid-template-columns:1fr 1fr;gap:2px 20px;margin:9px 0;font-size:12px}
	.tfk .row{display:flex;gap:6px;padding:2.5px 0;border-bottom:1px dotted #e7edef}
	.tfk .row .k{color:var(--mut);min-width:82px;flex:none}
	.tfk .row .v{font-weight:600}
	.tfk .uso{display:inline-block;background:var(--petrol);color:#fff;font-size:10.5px;font-weight:700;padding:1px 9px;border-radius:4px;letter-spacing:.05em}
	.tfk .ctrl{background:var(--crit)}
	.tfk h4{font-size:10.5px;text-transform:uppercase;letter-spacing:.1em;color:var(--petrol);margin:12px 0 5px;border-bottom:1px solid var(--line);padding-bottom:3px}
	.tfk table{width:100%;border-collapse:collapse;font-size:11.5px}
	.tfk th{background:var(--wash);text-align:left;padding:5px 7px;font-size:9.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--mut);border:1px solid var(--line)}
	.tfk td{padding:5px 7px;border:1px solid var(--line);vertical-align:top}
	.tfk td.n{text-align:center;font-weight:700;color:var(--petrol);width:24px}
	.tfk td.r{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
	.tfk .comp .prod{font-weight:700}
	.tfk .comp .presc{color:var(--mut);font-size:10.5px}
	.tfk .comp .corr{color:var(--mut);font-size:10px}
	.tfk .comp .lote{font-size:10.5px}
	.tfk .comp .lote b{font-family:ui-monospace,Consolas,monospace}
	.tfk .sign td{height:34px}
	.tfk .two{display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-top:10px}
	.tfk .alert{font-size:11px;padding:3px 0;border-bottom:1px dotted #e7edef;display:flex;gap:6px}
	.tfk .alert .an{font-weight:700;color:var(--petrol);flex:none}
	.tfk .alert.warn .at{color:var(--warn)} .tfk .alert.crit .at{color:var(--crit);font-weight:700} .tfk .alert.info .at{color:var(--mut)}
	.tfk .pesos{background:var(--wash);border:1px solid var(--line);border-radius:8px;padding:10px 12px}
	.tfk .pesos .pl{display:flex;justify-content:space-between;padding:3px 0;font-size:11.5px}
	.tfk .pesos .pl .pv{font-weight:700;font-variant-numeric:tabular-nums}
	.tfk .pesos .big{font-size:14px;border-top:1px solid var(--line);margin-top:4px;padding-top:6px}
	.tfk .assin{margin-top:14px}
	.tfk .assin table td{text-align:center;font-size:9.5px;color:var(--mut);text-transform:uppercase;letter-spacing:.05em;padding-top:26px}
	.tfk .foot{margin-top:12px;font-size:10px;color:var(--mut);border-top:1px solid var(--line);padding-top:6px}
	.tfk .mp{margin-top:8px;font-size:11px;background:#fffdf5;border:1px solid #f0e6c8;border-radius:6px;padding:7px 10px}
	@media print{
		@page{size:A4 portrait;margin:11mm}
		body *{visibility:hidden!important}
		.tfk,.tfk *{visibility:visible!important}
		.tfk{position:absolute;left:0;top:0;width:100%}
		.tfk .doc{max-width:none}
		.tfk th{-webkit-print-color-adjust:exact;print-color-adjust:exact}
	}
	</style>
	<div class="tfk"><div class="doc">

		<div class="hd">
			<div>
				<div class="farm"><?php echo $e( $emp['nome_fantasia'] ?? $emp['razao_social'] ?? 'Farmácia de Manipulação' ); ?>
					<small><?php echo $e( ( ! empty( $emp['cnpj'] ) ? 'CNPJ ' . $emp['cnpj'] . ' · ' : '' ) . 'RT: ' . ( $emp['rt_nome'] ?? '—' ) . ( ! empty( $emp['rt_crf'] ) ? ' · CRF ' . $emp['rt_crf'] . ( ! empty( $emp['rt_uf'] ) ? '-' . $emp['rt_uf'] : '' ) : '' ) ); ?></small>
				</div>
			</div>
			<div class="req">
				<div class="tt">Ordem de Manipulação</div>
				<div class="lab">Nº da Requisição</div>
				<div class="om"><?php echo $e( $o['numero'] ?? '—' ); ?></div>
				<div class="uso <?php echo ( $uso === 'externo' || $uso === 'veterinario' ) ? '' : ''; ?>"><?php echo $e( $uso_txt ); ?></div>
			</div>
		</div>

		<div class="grid">
			<div>
				<div class="row"><span class="k">Prescritor</span><span class="v"><?php echo $e( $presc ? trim( ( $presc['tratamento'] ?? '' ) . ' ' . $presc['nome'] ) : '—' ); ?></span></div>
				<div class="row"><span class="k">Registro</span><span class="v"><?php echo $e( $presc && $presc['nr_registro'] ? ( ( $presc['tipo_registro'] ?? 'CRM' ) . '-' . ( $presc['uf_registro'] ?? '' ) . '-' . $presc['nr_registro'] ) : '—' ); ?></span></div>
				<div class="row"><span class="k">Paciente</span><span class="v"><?php echo $e( $o['paciente_nome'] ?? '—' ); ?></span></div>
				<div class="row"><span class="k">RG</span><span class="v"><?php echo $e( $rg !== '' ? $rg : '—' ); ?></span></div>
			</div>
			<div>
				<div class="row"><span class="k">Entrada</span><span class="v"><?php echo $e( tao_formula_fdata( $o['criado_em'] ?? '' ) ); ?></span></div>
				<div class="row"><span class="k">Prescrição</span><span class="v"><?php echo $e( tao_formula_fdata( $o['dt_prescricao'] ?? '' ) ); ?></span></div>
				<div class="row"><span class="k">Retirada</span><span class="v"><?php echo $e( tao_formula_fdata( $o['previsao_retirada'] ?? '' ) ); ?></span></div>
				<div class="row"><span class="k">Validade</span><span class="v"><?php echo $e( tao_formula_fdata( $o['dt_validade'] ?? '' ) ); ?></span></div>
			</div>
		</div>

		<div class="grid">
			<div>
				<div class="row"><span class="k">Forma</span><span class="v"><?php echo $e( $o['forma_farmac'] ?? '—' ); ?></span></div>
				<div class="row"><span class="k">Volume</span><span class="v"><?php echo $e( trim( ( $o['volume'] ?? '' ) . ' ' . ( $o['unidade_vol'] ?? '' ) ) ?: '—' ); ?></span></div>
				<div class="row"><span class="k">Quantidade</span><span class="v"><?php echo $e( ( $o['qtd_unidades'] ?? 1 ) . ' un' ); ?></span></div>
			</div>
			<div>
				<div class="row"><span class="k">Embalagem</span><span class="v"><?php echo $embs ? $e( implode( '; ', array_map( fn( $x ) => $x['nome'], $embs ) ) ) : '—'; ?></span></div>
				<div class="row"><span class="k">Posologia</span><span class="v"><?php echo $e( $o['posologia'] ?? '—' ); ?></span></div>
			</div>
		</div>

		<h4>Composição</h4>
		<table class="comp">
			<thead><tr>
				<th>#</th><th>Componente</th><th class="r">Dose</th><th class="r">Qtd a pesar</th>
				<th>Lote / validade / fornecedor</th><th class="r">Pesado</th><th>Visto</th>
			</tr></thead>
			<tbody>
			<?php $i = 0; foreach ( $itens as $it ) : $i++;
				$at = $ativos[ $it['ativo_id'] ?? '' ] ?? null;
				$lt = $lotes[ $it['lote_mp_id'] ?? '' ] ?? null;
				$nome = $at['nome'] ?? $it['descricao'] ?? '—';
				$cod  = $at['codigo_fc'] ?? '';
				$corr = [];
				if ( ! empty( $it['teor_aplic'] ) && (float) $it['teor_aplic'] != 100 ) $corr[] = 'teor ' . tao_formula_nb( $it['teor_aplic'], 1 ) . '%';
				if ( ! empty( $it['equiv_aplic'] ) && (float) $it['equiv_aplic'] != 1 ) $corr[] = 'equiv ×' . tao_formula_nb( $it['equiv_aplic'], 3 );
				if ( ! empty( $it['diluicao_aplic'] ) && (float) $it['diluicao_aplic'] != 1 ) $corr[] = 'dil ×' . tao_formula_nb( $it['diluicao_aplic'], 2 );
			?>
			<tr>
				<td class="n"><?php echo str_pad( $i, 2, '0', STR_PAD_LEFT ); ?></td>
				<td>
					<div class="prod"><?php echo $e( $nome ); ?><?php echo $cod ? ' <span class="corr">cód ' . $e( $cod ) . '</span>' : ''; ?></div>
					<?php if ( ! empty( $it['descricao'] ) && mb_strtoupper( $it['descricao'] ) !== mb_strtoupper( $nome ) ) : ?><div class="presc">prescrição: <?php echo $e( $it['descricao'] ); ?></div><?php endif; ?>
					<?php if ( $corr ) : ?><div class="corr"><?php echo $e( implode( ' · ', $corr ) ); ?></div><?php endif; ?>
				</td>
				<td class="r"><?php echo $it['eh_qsp'] ? 'QSP' : ( $it['qtd_prescrita'] !== null ? $e( tao_formula_nb( $it['qtd_prescrita'], 3 ) . ' ' . ( $it['unidade'] ?? '' ) ) : '—' ); ?></td>
				<td class="r"><b><?php echo $it['qtd_pesar'] !== null ? $e( tao_formula_nb( $it['qtd_pesar'], 3 ) . ' ' . ( $it['unid_pesar'] ?? 'g' ) ) : '—'; ?></b></td>
				<td class="lote"><?php echo $lt ? '<b>' . $e( $lt['nr_lote'] ?? '' ) . '</b> · val ' . $e( tao_formula_fdata( $lt['dt_validade'] ?? '' ) ) . ( ! empty( $lt['fornecedor'] ) ? '<br>' . $e( $lt['fornecedor'] ) : '' ) : '<span class="corr">—</span>'; ?></td>
				<td class="r"><?php echo $it['qtd_pesada'] !== null && $it['qtd_pesada'] !== '' ? $e( tao_formula_nb( $it['qtd_pesada'], 3 ) ) : ''; ?></td>
				<td></td>
			</tr>
			<?php endforeach; ?>
			<?php foreach ( $embs as $emb ) : ?>
			<tr>
				<td class="n">—</td>
				<td class="prod"><?php echo $e( $emb['nome'] ); ?> <span class="corr">(embalagem)</span></td>
				<td class="r"><?php echo $e( (int) $emb['qtd'] ); ?> un</td>
				<td class="r">—</td><td class="lote"><span class="corr">—</span></td><td></td><td></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="two">
			<div>
				<h4>Alertas e observações</h4>
				<?php if ( $alertas ) : foreach ( $alertas as $al ) : ?>
					<div class="alert <?php echo $e( $al[2] ); ?>"><span class="an"><?php echo str_pad( $al[0], 2, '0', STR_PAD_LEFT ); ?></span><span class="at"><?php echo $e( $al[1] ); ?></span></div>
				<?php endforeach; else : ?>
					<div class="alert info"><span class="at">Nenhum alerta.</span></div>
				<?php endif; ?>
				<?php if ( ! empty( $o['controlado'] ) ) : ?>
					<div class="alert crit"><span class="at">Receita <?php echo $e( $o['tp_receita'] ?? '—' ); ?><?php echo ! empty( $o['nr_notificacao'] ) ? ' nº ' . $e( $o['nr_notificacao'] ) : ''; ?><?php echo ! empty( $o['comprador_nome'] ) ? ' · comprador: ' . $e( $o['comprador_nome'] ) : ''; ?></span></div>
				<?php endif; ?>
			</div>
			<div>
				<h4>Cálculos de peso</h4>
				<div class="pesos">
					<div class="pl"><span>Peso total da MP</span><span class="pv"><?php echo $e( tao_formula_nb( $peso_mp, 4 ) ); ?> g</span></div>
					<div class="pl"><span>Peso médio (por unid.)</span><span class="pv"><?php echo $e( tao_formula_nb( $peso_medio, 4 ) ); ?> g</span></div>
					<div class="pl big"><span>Peso teórico total</span><span class="pv"><?php echo $e( tao_formula_nb( $peso_total, 4 ) ); ?> g</span></div>
				</div>
			</div>
		</div>

		<?php if ( ! empty( $o['modo_preparo'] ) ) : ?>
		<div class="mp"><b>Modo de preparo / precauções:</b> <?php echo nl2br( $e( $o['modo_preparo'] ) ); ?></div>
		<?php endif; ?>

		<div class="assin">
			<h4>Rastreabilidade da manipulação</h4>
			<table class="sign"><tbody><tr>
				<td>Pesado por</td><td>Homogeneizado</td><td>Manipulado por</td><td>Conferido por</td><td>Aprovado por (RT)</td>
			</tr></tbody></table>
		</div>

		<div class="foot">Emitida em <?php echo $e( tao_formula_fdata( gmdate( 'Y-m-d' ) ) . ' ' . gmdate( 'H:i' ) ); ?> · OM <?php echo $e( $o['numero'] ?? '' ); ?> · Documento de uso interno (RDC 67/2007).</div>

	</div></div>
	<?php
	return ob_get_clean();
}

/** Handler AJAX — retorna o HTML da ficha p/ abrir no modal. */
add_action( 'wp_ajax_tao_formula_prod_ficha', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
	$cliente_id = tao_formula_cliente_id();
	$ordem_id   = sanitize_text_field( $_GET['ordem_id'] ?? $_POST['ordem_id'] ?? '' );
	$html = tao_formula_render_ficha_html( $cliente_id, $ordem_id );
	$html === '' ? wp_send_json_error( [ 'message' => 'OM não encontrada' ] ) : wp_send_json_success( [ 'html' => $html ] );
} );
