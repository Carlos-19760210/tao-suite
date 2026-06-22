<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Conciliação de recebíveis (Fase 3) — bater o que caiu das operadoras + antecipação.
 */
function tao_caixa_page_conciliacao() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid = tao_caixa_cliente_id();
    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };
    $st  = sanitize_text_field( $_GET['st'] ?? 'pendente' );
    if ( ! in_array( $st, [ 'pendente', 'conciliado' ], true ) ) $st = 'pendente';

    $linhas = []; $tot_liq = 0.0; $tot_bruto = 0.0;
    if ( $cid ) {
        // formas (nome + conta_no_dinheiro) e adquirentes (nome)
        $rf = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&select=id,nome,conta_no_dinheiro" );
        $fmap = []; $cash = [];
        foreach ( ( $rf['ok'] ? ( $rf['data'] ?? [] ) : [] ) as $f ) { $fmap[ $f['id'] ] = $f['nome']; if ( ! empty( $f['conta_no_dinheiro'] ) ) $cash[ $f['id'] ] = true; }
        $ra = tao_caixa_api( "/caixa_adquirentes?cliente_id=eq.$cid&select=id,nome" );
        $amap = []; foreach ( ( $ra['ok'] ? ( $ra['data'] ?? [] ) : [] ) as $a ) $amap[ $a['id'] ] = $a['nome'];

        $cf = $st === 'conciliado' ? 'eq.true' : 'eq.false';
        $rp = tao_caixa_api(
            "/caixa_pagamentos?cliente_id=eq.$cid&estornado=eq.false&conciliado=$cf&order=data_prevista_receb.asc&limit=400" .
            "&select=id,forma_pagamento_id,adquirente_id,parcelas,valor_bruto,valor_taxa,valor_liquido,data_prevista_receb,antecipado"
        );
        foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $p ) {
            // dinheiro não precisa conciliar — fora desta tela
            if ( isset( $cash[ $p['forma_pagamento_id'] ] ) ) continue;
            $p['_forma'] = $fmap[ $p['forma_pagamento_id'] ] ?? '—';
            $p['_adq']   = $amap[ $p['adquirente_id'] ] ?? '—';
            $linhas[] = $p;
            $tot_liq   += (float) $p['valor_liquido'];
            $tot_bruto += (float) $p['valor_bruto'];
        }
    }
    $url = function ( $s ) { return esc_url( tao_caixa_url( 'caixa-conciliacao', [ 'st' => $s ] ) ); };
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar"><h1>&#x1F501; Conciliação de Recebíveis</h1></div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php else : ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:16px">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b"><?php echo $st === 'conciliado' ? 'Conciliados' : 'Recebíveis pendentes'; ?></div>
                <strong style="font-size:21px"><?php echo count( $linhas ); ?></strong>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Líquido <?php echo $st === 'conciliado' ? 'conciliado' : 'a receber'; ?></div>
                <strong style="font-size:21px;color:<?php echo $st === 'conciliado' ? '#16a34a' : '#1d4ed8'; ?>"><?php echo $brl( $tot_liq ); ?></strong>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-bottom:14px;font-size:13px">
            <a class="taoc-btn<?php echo $st === 'pendente' ? ' taoc-btn-primary' : ''; ?>" href="<?php echo $url( 'pendente' ); ?>">A receber</a>
            <a class="taoc-btn<?php echo $st === 'conciliado' ? ' taoc-btn-primary' : ''; ?>" href="<?php echo $url( 'conciliado' ); ?>">Conciliados</a>
        </div>

        <?php if ( empty( $linhas ) ) : ?>
        <div class="taoc-empty"><p>Nenhum recebível <?php echo $st === 'conciliado' ? 'conciliado' : 'pendente'; ?>.</p>
        <p style="font-size:12px;color:#94a3b8">Recebíveis vêm de pagamentos em cartão/link/boleto. Dinheiro não entra aqui (controlado no fechamento de caixa).</p></div>
        <?php else : ?>
        <table class="taoc-table">
            <thead><tr>
                <th>Previsto</th><th>Forma</th><th>Operadora</th><th style="text-align:center">Parc.</th>
                <th style="text-align:right">Bruto</th><th style="text-align:right">Taxa</th><th style="text-align:right">Líquido</th>
                <th style="text-align:center;width:170px">Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $linhas as $p ) :
                $dp = $p['data_prevista_receb'] ? date_i18n( 'd/m/Y', strtotime( $p['data_prevista_receb'] ) ) : '—';
                $atrasado = ( $st === 'pendente' && $p['data_prevista_receb'] && $p['data_prevista_receb'] < gmdate( 'Y-m-d' ) ); ?>
                <tr data-id="<?php echo esc_attr( $p['id'] ); ?>">
                    <td style="white-space:nowrap;<?php echo $atrasado ? 'color:#dc2626;font-weight:600' : ''; ?>"><?php echo esc_html( $dp ); ?><?php echo $atrasado ? ' &#9888;' : ''; ?></td>
                    <td><?php echo esc_html( $p['_forma'] ); ?><?php echo ! empty( $p['antecipado'] ) ? ' <span style="font-size:10px;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:8px">antecip.</span>' : ''; ?></td>
                    <td style="color:#475569"><?php echo esc_html( $p['_adq'] ); ?></td>
                    <td style="text-align:center"><?php echo (int) $p['parcelas']; ?>x</td>
                    <td style="text-align:right"><?php echo $brl( $p['valor_bruto'] ); ?></td>
                    <td style="text-align:right;color:#dc2626"><?php echo $brl( $p['valor_taxa'] ); ?></td>
                    <td style="text-align:right;color:#16a34a;font-weight:600"><?php echo $brl( $p['valor_liquido'] ); ?></td>
                    <td style="text-align:center;white-space:nowrap">
                        <?php if ( $st === 'pendente' ) : ?>
                        <button type="button" class="taoc-btn taoc-btn-primary taoc-conc" data-id="<?php echo esc_attr( $p['id'] ); ?>">Conciliar</button>
                        <?php if ( empty( $p['antecipado'] ) ) : ?>
                        <button type="button" class="taoc-btn taoc-antec" data-id="<?php echo esc_attr( $p['id'] ); ?>" style="color:#92400e;border-color:#fde68a">Antecipar</button>
                        <?php endif; ?>
                        <?php else : ?>
                        <button type="button" class="taoc-btn taoc-desc" data-id="<?php echo esc_attr( $p['id'] ); ?>">Desfazer</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        var C = window.taoCaixa || {};
        function call(action, data, btn, confirmMsg){
            if(confirmMsg && !confirm(confirmMsg)) return;
            btn.disabled=true; var t=btn.textContent; btn.textContent='...';
            var fd=new FormData(); fd.append('action',action); fd.append('nonce',C.nonce);
            for(var k in data) fd.append(k,data[k]);
            fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(resp){
                if(resp&&resp.success){ location.reload(); }
                else { btn.disabled=false; btn.textContent=t; alert('Erro: '+((resp&&resp.data)||'falha')); }
            }).catch(function(){ btn.disabled=false; btn.textContent=t; alert('Falha de comunicação'); });
        }
        function bind(sel, fn){ var b=document.querySelectorAll(sel); for(var i=0;i<b.length;i++) b[i].addEventListener('click', fn); }
        bind('.taoc-conc', function(){ call('tao_caixa_conciliar_pagamento', { id:this.getAttribute('data-id'), set:'1' }, this); });
        bind('.taoc-desc', function(){ call('tao_caixa_conciliar_pagamento', { id:this.getAttribute('data-id'), set:'0' }, this); });
        bind('.taoc-antec', function(){ call('tao_caixa_antecipar_pagamento', { id:this.getAttribute('data-id') }, this, 'Antecipar este recebível? A taxa de antecipação da operadora será descontada do líquido.'); });
    })();
    </script>
    <?php
}
