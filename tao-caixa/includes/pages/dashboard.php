<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_page_dashboard() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid = tao_caixa_cliente_id();
    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };

    // Período: presets + Mês corrente + Período específico (de/ate). Default = HOJE (Carlos 14/08)
    $p      = sanitize_text_field( $_GET['p'] ?? 'hoje' );
    $de_g   = sanitize_text_field( $_GET['de']  ?? '' );
    $ate_g  = sanitize_text_field( $_GET['ate'] ?? '' );
    $tz = new DateTimeZone( 'America/Sao_Paulo' );
    $now_sp = new DateTime( 'now', $tz );
    $hoje = $now_sp->format( 'Y-m-d' );
    $ate_d = $hoje;
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $de_g ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ate_g ) ) {
        $p = 'custom'; $de = $de_g; $ate_d = $ate_g; $label = 'Período específico';
    }
    elseif ( $p === 'hoje' )  { $de = $hoje; $label = 'Hoje'; }
    elseif ( $p === '7d' )    { $de = ( clone $now_sp )->modify( '-6 days'  )->format( 'Y-m-d' ); $label = 'Últimos 7 dias'; }
    elseif ( $p === '30d' )   { $de = ( clone $now_sp )->modify( '-29 days' )->format( 'Y-m-d' ); $label = 'Últimos 30 dias'; }
    elseif ( $p === '90d' )   { $de = ( clone $now_sp )->modify( '-89 days' )->format( 'Y-m-d' ); $label = 'Últimos 90 dias'; }
    else                      { $p = 'mes'; $de = $now_sp->format( 'Y-m-01' ); $label = 'Mês corrente'; }
    $de_iso  = $de    . 'T00:00:00-03:00';
    $ate_iso = $ate_d . 'T23:59:59-03:00';

    $vendas = []; $pagtos = []; $formas_map = []; $abertas_glob = []; $req_map = [];
    if ( $cid ) {
        $rv = tao_caixa_api( "/caixa_vendas?cliente_id=eq.$cid&criado_em=gte.$de_iso&criado_em=lte.$ate_iso&select=valor_total,valor_pago,status,origem&limit=2000" );
        $vendas = $rv['ok'] ? ( $rv['data'] ?? [] ) : [];
        $rp = tao_caixa_api( "/caixa_pagamentos?cliente_id=eq.$cid&criado_em=gte.$de_iso&criado_em=lte.$ate_iso&estornado=eq.false&select=forma_pagamento_id,modalidade,parcelas,bandeira,valor_bruto,valor_taxa,valor_liquido,data_prevista_receb,conciliado,criado_em,caixa_recibos(pagador_nome,data_pagamento)&order=criado_em.desc&limit=5000" );
        $pagtos = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];
        $rf = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&select=id,nome,canal" );
        foreach ( ( $rf['ok'] ? ( $rf['data'] ?? [] ) : [] ) as $f ) $formas_map[ $f['id'] ] = $f['nome'];

        // EM ABERTO GLOBAL (ignora o filtro de período): toda venda aberta/parcial da base
        $ra = tao_caixa_api( "/caixa_vendas?cliente_id=eq.$cid&status=in.(aberta,parcial)&select=id,card_id,cliente_nome,whatsapp,valor_total,valor_pago,status,origem,criado_em&order=criado_em.asc&limit=2000" );
        $abertas_glob = $ra['ok'] ? ( $ra['data'] ?? [] ) : [];
        // Nº da Requisição (campo CRM) das vendas em aberto
        $card_ids = array_values( array_filter( array_unique( array_map( function ( $v ) { return $v['card_id'] ?? ''; }, $abertas_glob ) ) ) );
        if ( $card_ids ) {
            $rcd = tao_caixa_api( "/crm_campos_definicao?chave=eq.numero_requisicao&select=id" );
            $campo_ids = $rcd['ok'] ? array_column( $rcd['data'] ?? [], 'id' ) : [];
            if ( $campo_ids ) {
                foreach ( array_chunk( $card_ids, 100 ) as $chunk ) {
                    $rvv = tao_caixa_api( "/crm_cards_valores?card_id=in.(" . implode( ',', $chunk ) . ")&campo_id=in.(" . implode( ',', $campo_ids ) . ")&select=card_id,valor" );
                    foreach ( ( $rvv['ok'] ? ( $rvv['data'] ?? [] ) : [] ) as $row ) {
                        if ( ! empty( $row['valor'] ) ) $req_map[ $row['card_id'] ] = $row['valor'];
                    }
                }
            }
        }
    }
    $tot_aberto_glob = 0.0; $n_aberto_glob = 0;
    foreach ( $abertas_glob as $v ) {
        $sal = max( 0, (float) ( $v['valor_total'] ?? 0 ) - (float) ( $v['valor_pago'] ?? 0 ) );
        if ( $sal > 0.005 ) { $tot_aberto_glob += $sal; $n_aberto_glob++; }
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

    // "A cair" é visão de FUTURO — considera TODA a base (independe do filtro de período),
    // senão pagamentos lançados fora do período sumiam e os retroativos confundiam (Carlos 14/08).
    $a_cair = [];
    if ( $cid ) {
        $rfut = tao_caixa_api( "/caixa_pagamentos?cliente_id=eq.$cid&estornado=eq.false&conciliado=eq.false&data_prevista_receb=gte.$hoje&select=valor_liquido,data_prevista_receb&limit=5000" );
        foreach ( ( $rfut['ok'] ? ( $rfut['data'] ?? [] ) : [] ) as $fp ) {
            $dpf = $fp['data_prevista_receb'] ?? '';
            if ( ! $dpf ) continue;
            if ( ! isset( $a_cair[ $dpf ] ) ) $a_cair[ $dpf ] = 0.0;
            $a_cair[ $dpf ] += (float) ( $fp['valor_liquido'] ?? 0 );
        }
    }

    // Pagamentos por forma + lista de recebimentos (estes sim, do período filtrado)
    $por_forma = []; $tot_bruto = 0.0; $tot_taxa = 0.0; $tot_liq = 0.0;
    $hoje_d = $hoje; $recebimentos = [];
    foreach ( $pagtos as $pg ) {
        $fid = $pg['forma_pagamento_id'] ?? ''; $nome = $formas_map[ $fid ] ?? '—';
        if ( ! isset( $por_forma[ $nome ] ) ) $por_forma[ $nome ] = [ 'n' => 0, 'bruto' => 0.0, 'taxa' => 0.0, 'liq' => 0.0 ];
        $b = (float) ( $pg['valor_bruto'] ?? 0 ); $t = (float) ( $pg['valor_taxa'] ?? 0 ); $l = (float) ( $pg['valor_liquido'] ?? 0 );
        $por_forma[ $nome ]['n']++; $por_forma[ $nome ]['bruto'] += $b; $por_forma[ $nome ]['taxa'] += $t; $por_forma[ $nome ]['liq'] += $l;
        $tot_bruto += $b; $tot_taxa += $t; $tot_liq += $l;
        $rec = $pg['caixa_recibos'] ?? [];
        $mod = $pg['modalidade'] ?? ''; $par = (int) ( $pg['parcelas'] ?? 1 );
        $mlabel = $mod ? ucfirst( $mod ) : '—';
        if ( in_array( $mod, [ 'credito', 'debito' ], true ) ) { if ( $pg['bandeira'] ?? '' ) $mlabel .= ' ' . $pg['bandeira']; if ( $par > 1 ) $mlabel .= " ({$par}x)"; }
        $recebimentos[] = [
            'data'     => $rec['data_pagamento'] ?? substr( (string) ( $pg['criado_em'] ?? '' ), 0, 10 ),
            'pagador'  => $rec['pagador_nome'] ?? '—',
            'forma'    => $nome,
            'modal'    => $mlabel,
            'bruto'    => $b, 'taxa' => $t, 'liq' => $l,
            'concil'   => ! empty( $pg['conciliado'] ),
        ];
    }
    arsort( $por_forma ); // por valor? mantém ordem de inserção; ok
    ksort( $a_cair );

    $url = function ( $pp ) { return esc_url( tao_caixa_url( 'caixa-dashboard', [ 'p' => $pp ] ) ); };
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F4B0; Caixa</h1>
            <div style="display:flex;gap:6px;font-size:13px;align-items:center">
                <?php $_bu = tao_caixa_url( 'caixa-dashboard' ); $_sep = ( strpos( $_bu, '?' ) !== false ) ? '&' : '?'; ?>
                <select onchange="taocPeriodo(this)" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px">
                    <option value="hoje" <?php selected( $p, 'hoje' ); ?>>Hoje</option>
                    <option value="7d"   <?php selected( $p, '7d' ); ?>>7 dias</option>
                    <option value="30d"  <?php selected( $p, '30d' ); ?>>30 dias</option>
                    <option value="90d"  <?php selected( $p, '90d' ); ?>>90 dias</option>
                    <option value="mes"  <?php selected( $p, 'mes' ); ?>>Mês corrente</option>
                    <option value="custom" <?php selected( $p, 'custom' ); ?>>Período específico…</option>
                </select>
                <span id="taoc-range" style="<?php echo $p === 'custom' ? '' : 'display:none'; ?>;align-items:center;gap:4px;display:<?php echo $p === 'custom' ? 'inline-flex' : 'none'; ?>">
                    <input type="date" id="taoc-de"  value="<?php echo esc_attr( $p === 'custom' ? $de : '' ); ?>"    style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                    <span style="color:#94a3b8;font-size:12px">até</span>
                    <input type="date" id="taoc-ate" value="<?php echo esc_attr( $p === 'custom' ? $ate_d : '' ); ?>" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                    <a class="taoc-btn taoc-btn-primary" href="#" onclick="taocApply();return false">Aplicar</a>
                </span>
                <a class="taoc-btn" href="<?php echo esc_url( tao_caixa_url( 'caixa-vendas' ) ); ?>">Ver vendas &rarr;</a>
                <script>
                var TAOC_BASE = <?php echo wp_json_encode( $_bu . $_sep ); ?>;
                function taocPeriodo(sel){ var v=sel.value,r=document.getElementById('taoc-range');
                    if(v==='custom'){ if(r)r.style.display='inline-flex'; return; }
                    window.location = TAOC_BASE + 'p=' + v; }
                function taocApply(){ var de=document.getElementById('taoc-de').value, ate=document.getElementById('taoc-ate').value;
                    if(!de||!ate){ alert('Informe as duas datas.'); return; }
                    window.location = TAOC_BASE + 'p=custom&de=' + de + '&ate=' + ate; }
                </script>
            </div>
        </div>
        <p style="color:#64748b;font-size:13px;margin:-6px 0 16px">Período: <strong><?php echo esc_html( $label ); ?></strong></p>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php else : ?>

        <!-- KPIs (Carlos 14/08): vendas, vendido, recebido bruto POR TIPO, taxas, líquido, em aberto GLOBAL -->
        <?php
        // sub-linha do Recebido bruto: abertura por tipo/forma de recebimento
        $sub_formas = [];
        foreach ( $por_forma as $nome_f => $af ) $sub_formas[] = esc_html( $nome_f ) . ' ' . $brl( $af['bruto'] );
        $sub_origem = 'Funil ' . $brl( $por_origem['funil']['v'] ) . ' · Avulsa ' . $brl( $por_origem['avulsa']['v'] );
        $kpis = [
            [ 'Vendas no período', $n_vendas, '#1e293b', 'Vendas que passaram no caixa' ],
            [ 'Vendido no período', $brl( $tot_vendido ), '#1e293b', $sub_origem ],
            [ 'Recebido (bruto)', $brl( $tot_bruto ), '#16a34a', $sub_formas ? implode( ' · ', $sub_formas ) : 'Sem recebimentos' ],
            [ 'Taxas', $brl( $tot_taxa ), '#dc2626', 'Sobre os recebimentos do período' ],
            [ 'Líquido recebido', $brl( $tot_liq ), '#16a34a', 'Bruto − taxas' ],
        ];
        ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:20px">
            <?php foreach ( $kpis as $k ) : ?>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b"><?php echo esc_html( $k[0] ); ?></div>
                <strong style="font-size:21px;color:<?php echo $k[2]; ?>"><?php echo esc_html( $k[1] ); ?></strong>
                <div style="font-size:10.5px;color:#94a3b8;margin-top:4px;line-height:1.5"><?php echo wp_kses_post( $k[3] ); ?></div>
            </div>
            <?php endforeach; ?>
            <div onclick="var s=document.getElementById('taoc-abertos-sec');s.style.display=s.style.display==='none'?'block':'none';"
                 title="Clique para ver os detalhes"
                 style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:16px;cursor:pointer">
                <div style="font-size:12px;color:#92400e">Em aberto de recebimento (geral)</div>
                <strong style="font-size:21px;color:#b45309"><?php echo esc_html( $brl( $tot_aberto_glob ) ); ?></strong>
                <div style="font-size:10.5px;color:#b45309;margin-top:4px"><?php echo (int) $n_aberto_glob; ?> venda(s) · toda a base, fora do filtro · clique p/ detalhes</div>
            </div>
        </div>

        <!-- Em aberto de recebimento — detalhe (toda a base) + exportação -->
        <div id="taoc-abertos-sec" style="display:none;margin-bottom:22px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <h2 style="font-size:15px;margin:0">&#x23F3; Em aberto de recebimento (<?php echo (int) $n_aberto_glob; ?>)</h2>
                <a class="taoc-btn" href="<?php echo esc_url( admin_url( 'admin-ajax.php' ) . '?action=tao_caixa_export_pendentes&nonce=' . wp_create_nonce( 'tao_caixa_nonce' ) ); ?>">&#x2B07; Exportar XLSX</a>
            </div>
            <?php if ( ! $n_aberto_glob ) : ?>
            <p style="font-size:13px;color:#94a3b8">Nenhuma venda com recebimento em aberto. &#x1F389;</p>
            <?php else : ?>
            <div style="max-height:380px;overflow:auto;border:1px solid #fcd34d;border-radius:10px">
            <table class="taoc-table" style="margin:0">
                <thead><tr>
                    <th>Data</th><th>Cliente</th><th>Nº Req.</th><th>WhatsApp</th><th>Origem</th><th>Status</th>
                    <th style="text-align:right">Total</th><th style="text-align:right">Pago</th><th style="text-align:right">Em aberto</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $abertas_glob as $v ) :
                    $sal = max( 0, (float) ( $v['valor_total'] ?? 0 ) - (float) ( $v['valor_pago'] ?? 0 ) );
                    if ( $sal <= 0.005 ) continue; ?>
                    <tr>
                        <td style="white-space:nowrap"><?php echo esc_html( ! empty( $v['criado_em'] ) ? date_i18n( 'd/m/Y', strtotime( $v['criado_em'] ) ) : '—' ); ?></td>
                        <td><strong><?php echo esc_html( $v['cliente_nome'] ?: '—' ); ?></strong></td>
                        <td><?php echo esc_html( $req_map[ $v['card_id'] ?? '' ] ?? '—' ); ?></td>
                        <td style="color:#475569"><?php echo esc_html( $v['whatsapp'] ?: '—' ); ?></td>
                        <td><?php echo ( ( $v['origem'] ?? '' ) === 'avulsa' ) ? 'Avulsa' : 'Funil'; ?></td>
                        <td><?php echo esc_html( ( $v['status'] ?? '' ) === 'parcial' ? 'Parcial' : 'A receber' ); ?></td>
                        <td style="text-align:right"><?php echo $brl( $v['valor_total'] ?? 0 ); ?></td>
                        <td style="text-align:right;color:#16a34a"><?php echo $brl( $v['valor_pago'] ?? 0 ); ?></td>
                        <td style="text-align:right;color:#b45309;font-weight:700"><?php echo $brl( $sal ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr style="background:#fffbeb;font-weight:700">
                    <td colspan="8">Total em aberto (<?php echo (int) $n_aberto_glob; ?>)</td>
                    <td style="text-align:right;color:#b45309"><?php echo $brl( $tot_aberto_glob ); ?></td>
                </tr></tfoot>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recebimentos no período (o que entrou) -->
        <h2 style="font-size:15px;margin:0 0 8px">&#x1F4E5; Recebimentos no período (<?php echo count( $recebimentos ); ?>)</h2>
        <?php if ( empty( $recebimentos ) ) : ?>
        <p style="font-size:13px;color:#94a3b8;margin-bottom:20px">Nenhum recebimento no período.</p>
        <?php else : ?>
        <div style="max-height:360px;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:22px">
        <table class="taoc-table" style="margin:0">
            <thead><tr>
                <th>Data</th><th>Pagador</th><th>Forma</th><th>Modalidade</th>
                <th style="text-align:right">Bruto</th><th style="text-align:right">Taxa</th><th style="text-align:right">Líquido</th><th style="text-align:center" title="Conciliado">&#x2705;</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $recebimentos as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r['data'] ? date_i18n( 'd/m/Y', strtotime( $r['data'] ) ) : '—' ); ?></td>
                    <td><strong><?php echo esc_html( $r['pagador'] ); ?></strong></td>
                    <td><?php echo esc_html( $r['forma'] ); ?></td>
                    <td style="color:#64748b"><?php echo esc_html( $r['modal'] ); ?></td>
                    <td style="text-align:right"><?php echo $brl( $r['bruto'] ); ?></td>
                    <td style="text-align:right;color:#dc2626"><?php echo $brl( $r['taxa'] ); ?></td>
                    <td style="text-align:right;color:#16a34a;font-weight:600"><?php echo $brl( $r['liq'] ); ?></td>
                    <td style="text-align:center"><?php echo $r['concil'] ? '&#x2705;' : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr style="background:#f8fafc;font-weight:700">
                <td colspan="4">Total (<?php echo count( $recebimentos ); ?>)</td>
                <td style="text-align:right"><?php echo $brl( $tot_bruto ); ?></td>
                <td style="text-align:right;color:#dc2626"><?php echo $brl( $tot_taxa ); ?></td>
                <td style="text-align:right;color:#16a34a"><?php echo $brl( $tot_liq ); ?></td>
                <td></td>
            </tr></tfoot>
        </table>
        </div>
        <?php endif; ?>

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

                <h2 style="font-size:15px;margin:0 0 8px">A cair (líquido por data prevista — toda a base, não conciliados)</h2>
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
