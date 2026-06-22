<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_page_dashboard() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid = tao_caixa_cliente_id();
    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };

    // Período (presets)
    $p = sanitize_text_field( $_GET['p'] ?? 'mes' );
    $hoje = gmdate( 'Y-m-d' );
    if ( $p === 'hoje' )      { $de = $hoje; $label = 'Hoje'; }
    elseif ( $p === '7d' )    { $de = gmdate( 'Y-m-d', time() - 6 * 86400 ); $label = 'Últimos 7 dias'; }
    else                      { $p = 'mes'; $de = gmdate( 'Y-m-01' ); $label = 'Este mês'; }
    $de_iso  = $de . 'T00:00:00';
    $ate_iso = $hoje . 'T23:59:59';

    $vendas = []; $pagtos = []; $formas_map = [];
    if ( $cid ) {
        $rv = tao_caixa_api( "/caixa_vendas?cliente_id=eq.$cid&criado_em=gte.$de_iso&criado_em=lte.$ate_iso&select=valor_total,valor_pago,status,origem&limit=2000" );
        $vendas = $rv['ok'] ? ( $rv['data'] ?? [] ) : [];
        $rp = tao_caixa_api( "/caixa_pagamentos?cliente_id=eq.$cid&criado_em=gte.$de_iso&criado_em=lte.$ate_iso&estornado=eq.false&select=forma_pagamento_id,valor_bruto,valor_taxa,valor_liquido,data_prevista_receb&limit=5000" );
        $pagtos = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];
        $rf = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&select=id,nome,canal" );
        foreach ( ( $rf['ok'] ? ( $rf['data'] ?? [] ) : [] ) as $f ) $formas_map[ $f['id'] ] = $f['nome'];
    }

    // KPIs de vendas
    $n_vendas = count( $vendas ); $tot_vendido = 0.0; $tot_pago = 0.0; $tot_receber = 0.0;
    $por_origem = [ 'funil' => [ 'n' => 0, 'v' => 0.0 ], 'avulsa' => [ 'n' => 0, 'v' => 0.0 ] ];
    foreach ( $vendas as $v ) {
        $vt = (float) ( $v['valor_total'] ?? 0 ); $vp = (float) ( $v['valor_pago'] ?? 0 );
        $tot_vendido += $vt; $tot_pago += $vp;
        if ( in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true ) ) $tot_receber += ( $vt - $vp );
        $o = ( ( $v['origem'] ?? '' ) === 'avulsa' ) ? 'avulsa' : 'funil';
        $por_origem[ $o ]['n']++; $por_origem[ $o ]['v'] += $vt;
    }

    // Pagamentos por forma + a receber das operadoras por data prevista
    $por_forma = []; $tot_bruto = 0.0; $tot_taxa = 0.0; $tot_liq = 0.0;
    $a_cair = []; $hoje_d = gmdate( 'Y-m-d' );
    foreach ( $pagtos as $pg ) {
        $fid = $pg['forma_pagamento_id'] ?? ''; $nome = $formas_map[ $fid ] ?? '—';
        if ( ! isset( $por_forma[ $nome ] ) ) $por_forma[ $nome ] = [ 'n' => 0, 'bruto' => 0.0, 'taxa' => 0.0, 'liq' => 0.0 ];
        $b = (float) ( $pg['valor_bruto'] ?? 0 ); $t = (float) ( $pg['valor_taxa'] ?? 0 ); $l = (float) ( $pg['valor_liquido'] ?? 0 );
        $por_forma[ $nome ]['n']++; $por_forma[ $nome ]['bruto'] += $b; $por_forma[ $nome ]['taxa'] += $t; $por_forma[ $nome ]['liq'] += $l;
        $tot_bruto += $b; $tot_taxa += $t; $tot_liq += $l;
        $dp = $pg['data_prevista_receb'] ?? '';
        if ( $dp && $dp >= $hoje_d ) { if ( ! isset( $a_cair[ $dp ] ) ) $a_cair[ $dp ] = 0.0; $a_cair[ $dp ] += $l; }
    }
    arsort( $por_forma ); // por valor? mantém ordem de inserção; ok
    ksort( $a_cair );

    $url = function ( $pp ) { return esc_url( tao_caixa_url( 'caixa-dashboard', [ 'p' => $pp ] ) ); };
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F4B0; TAO Caixa</h1>
            <div style="display:flex;gap:6px;font-size:13px">
                <?php foreach ( [ 'hoje' => 'Hoje', '7d' => '7 dias', 'mes' => 'Mês' ] as $pk => $pl ) : ?>
                <a class="taoc-btn<?php echo $p === $pk ? ' taoc-btn-primary' : ''; ?>" href="<?php echo $url( $pk ); ?>"><?php echo esc_html( $pl ); ?></a>
                <?php endforeach; ?>
                <a class="taoc-btn" href="<?php echo esc_url( tao_caixa_url( 'caixa-vendas' ) ); ?>">Ver vendas &rarr;</a>
            </div>
        </div>
        <p style="color:#64748b;font-size:13px;margin:-6px 0 16px">Período: <strong><?php echo esc_html( $label ); ?></strong></p>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php else : ?>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:20px">
            <?php
            $kpis = [
                [ 'Vendas', $n_vendas, '#1e293b' ],
                [ 'Vendido', $brl( $tot_vendido ), '#1e293b' ],
                [ 'Recebido (bruto)', $brl( $tot_pago ), '#16a34a' ],
                [ 'A receber', $brl( $tot_receber ), '#1d4ed8' ],
                [ 'Taxas no período', $brl( $tot_taxa ), '#dc2626' ],
                [ 'Líquido', $brl( $tot_liq ), '#16a34a' ],
            ];
            foreach ( $kpis as $k ) : ?>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b"><?php echo esc_html( $k[0] ); ?></div>
                <strong style="font-size:21px;color:<?php echo $k[2]; ?>"><?php echo esc_html( $k[1] ); ?></strong>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px">
            <!-- Por forma de pagamento -->
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Por forma de pagamento</h2>
                <?php if ( empty( $por_forma ) ) : ?>
                <p style="font-size:13px;color:#94a3b8">Nenhum recebimento no período.</p>
                <?php else : ?>
                <table class="taoc-table">
                    <thead><tr><th>Forma</th><th style="text-align:center">Nº</th><th style="text-align:right">Bruto</th><th style="text-align:right">Taxa</th><th style="text-align:right">Líquido</th></tr></thead>
                    <tbody>
                    <?php foreach ( $por_forma as $nome => $a ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $nome ); ?></strong></td>
                            <td style="text-align:center"><?php echo (int) $a['n']; ?></td>
                            <td style="text-align:right"><?php echo $brl( $a['bruto'] ); ?></td>
                            <td style="text-align:right;color:#dc2626"><?php echo $brl( $a['taxa'] ); ?></td>
                            <td style="text-align:right;color:#16a34a;font-weight:600"><?php echo $brl( $a['liq'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Por origem + a cair -->
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Por origem</h2>
                <table class="taoc-table" style="margin-bottom:18px">
                    <thead><tr><th>Origem</th><th style="text-align:center">Nº</th><th style="text-align:right">Valor</th></tr></thead>
                    <tbody>
                        <tr><td>&#x1F517; Funil</td><td style="text-align:center"><?php echo $por_origem['funil']['n']; ?></td><td style="text-align:right"><?php echo $brl( $por_origem['funil']['v'] ); ?></td></tr>
                        <tr><td>&#x1F6D2; Avulsa</td><td style="text-align:center"><?php echo $por_origem['avulsa']['n']; ?></td><td style="text-align:right"><?php echo $brl( $por_origem['avulsa']['v'] ); ?></td></tr>
                    </tbody>
                </table>

                <h2 style="font-size:15px;margin:0 0 8px">A cair (líquido por data prevista)</h2>
                <?php if ( empty( $a_cair ) ) : ?>
                <p style="font-size:13px;color:#94a3b8">Sem recebimentos futuros previstos.</p>
                <?php else : ?>
                <table class="taoc-table">
                    <thead><tr><th>Data prevista</th><th style="text-align:right">Líquido</th></tr></thead>
                    <tbody>
                    <?php foreach ( array_slice( $a_cair, 0, 12, true ) as $d => $val ) : ?>
                        <tr><td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $d ) ) ); ?></td><td style="text-align:right;color:#16a34a"><?php echo $brl( $val ); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

        <!-- Configuração -->
        <h2 style="font-size:15px;margin:26px 0 8px">Configuração</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px">
            <a href="<?php echo esc_url( tao_caixa_url( 'caixa-adquirentes' ) ); ?>" class="taoc-card" style="display:block;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-decoration:none;color:#1e293b">
                <div style="font-size:20px">&#x1F3E6;</div><strong style="display:block;margin-top:6px">Operadoras de Cartão</strong>
            </a>
            <a href="<?php echo esc_url( tao_caixa_url( 'caixa-taxas' ) ); ?>" class="taoc-card" style="display:block;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-decoration:none;color:#1e293b">
                <div style="font-size:20px">&#x1F4CA;</div><strong style="display:block;margin-top:6px">Taxas (MDR)</strong>
            </a>
            <a href="<?php echo esc_url( tao_caixa_url( 'caixa-formas' ) ); ?>" class="taoc-card" style="display:block;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-decoration:none;color:#1e293b">
                <div style="font-size:20px">&#x1F4B3;</div><strong style="display:block;margin-top:6px">Formas de Pagamento</strong>
            </a>
        </div>
    </div>
    <?php
}
