<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vendas do Caixa — listagem (somente leitura, Fase 1).
 * Mostra as vendas geradas a partir dos cards ganhos (origem=funil) e futuras avulsas.
 */

function tao_caixa_venda_status_badge( $st ) {
    $map = [
        'aberta'    => [ 'A receber', '#dbeafe', '#1d4ed8' ],
        'parcial'   => [ 'Parcial',   '#fef3c7', '#92400e' ],
        'quitada'   => [ 'Quitada',   '#dcfce7', '#166534' ],
        'cancelada' => [ 'Cancelada', '#f1f5f9', '#64748b' ],
        'estornada' => [ 'Estornada', '#fee2e2', '#991b1b' ],
    ];
    $m = $map[ $st ] ?? [ $st ?: '—', '#f1f5f9', '#64748b' ];
    return '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:' . $m[1] . ';color:' . $m[2] . '">' . esc_html( $m[0] ) . '</span>';
}

function tao_caixa_venda_origem_badge( $o ) {
    $funil = ( $o !== 'avulsa' );
    $label = $funil ? '&#x1F517; Funil' : '&#x1F6D2; Avulsa';
    $bg    = $funil ? '#ede9fe' : '#ecfeff';
    $fg    = $funil ? '#6d28d9' : '#0e7490';
    return '<span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;background:' . $bg . ';color:' . $fg . '">' . $label . '</span>';
}

function tao_caixa_page_vendas() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid         = tao_caixa_cliente_id();
    $status      = sanitize_text_field( $_GET['status'] ?? '' );
    $origem      = sanitize_text_field( $_GET['origem'] ?? '' );
    $card_filtro = sanitize_text_field( $_GET['card'] ?? '' );
    $auto_receber = '';

    // Filtro de período (mesmo conceito do Painel; default HOJE — Carlos 14/08).
    // Vindo de um card (?card=…) não filtra por data: o atalho de recebimento precisa achar a venda.
    $p     = sanitize_text_field( $_GET['p'] ?? 'hoje' );
    $de_g  = sanitize_text_field( $_GET['de']  ?? '' );
    $ate_g = sanitize_text_field( $_GET['ate'] ?? '' );
    $tz = new DateTimeZone( 'America/Sao_Paulo' );
    $now_sp = new DateTime( 'now', $tz );
    $hoje = $now_sp->format( 'Y-m-d' );
    $ate_d = $hoje; $de = '';
    if ( $card_filtro ) { $p = 'todos'; }
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $de_g ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ate_g ) ) {
        $p = 'custom'; $de = $de_g; $ate_d = $ate_g;
    }
    elseif ( $p === 'todos' ) { $de = ''; }
    elseif ( $p === '7d' )    { $de = ( clone $now_sp )->modify( '-6 days'  )->format( 'Y-m-d' ); }
    elseif ( $p === '30d' )   { $de = ( clone $now_sp )->modify( '-29 days' )->format( 'Y-m-d' ); }
    elseif ( $p === 'mes' )   { $de = $now_sp->format( 'Y-m-01' ); }
    else                      { $p = 'hoje'; $de = $hoje; }
    $flt_data = $de ? ( '&criado_em=gte.' . $de . 'T00:00:00-03:00&criado_em=lte.' . $ate_d . 'T23:59:59-03:00' ) : '';

    // URL do export XLSX com os MESMOS filtros da tela (capturada aqui, antes de $p ser reutilizada)
    $export_url = admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_filter( [
        'action' => 'tao_caixa_export_vendas',
        'nonce'  => wp_create_nonce( 'tao_caixa_nonce' ),
        'p'      => $p,
        'de'     => $p === 'custom' ? $de : '',
        'ate'    => $p === 'custom' ? $ate_d : '',
        'status' => $status,
        'origem' => $origem,
        'card'   => $card_filtro,
    ] ) );

    $vendas      = [];
    $formas      = [];
    $taxas       = [];
    $req_map     = [];
    $tot_geral   = 0.0;
    $tot_receber = 0.0;

    if ( $cid ) {
        $flt  = $status ? '&status=eq.' . rawurlencode( $status ) : '';
        $flt .= $origem ? '&origem=eq.' . rawurlencode( $origem ) : '';
        $flt .= $card_filtro ? '&card_id=eq.' . rawurlencode( $card_filtro ) : '';
        $rv = tao_caixa_api(
            "/caixa_vendas?cliente_id=eq.$cid$flt$flt_data&order=criado_em.desc&limit=300" .
            "&select=id,card_id,cliente_nome,whatsapp,valor_total,valor_pago,status,origem,criado_em"
        );
        $vendas = $rv['ok'] ? ( $rv['data'] ?? [] ) : [];
        // Regra 01/09: venda aberta/parcial de card acompanha o Valor Final ATUAL do card
        if ( function_exists( 'tao_caixa_sync_vendas_com_cards' ) ) {
            $vendas = tao_caixa_sync_vendas_com_cards( $cid, $vendas );
        }
        foreach ( $vendas as $v ) {
            $tot_geral += floatval( $v['valor_total'] ?? 0 );
            if ( in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true ) ) {
                $tot_receber += floatval( $v['valor_total'] ?? 0 ) - floatval( $v['valor_pago'] ?? 0 );
            }
        }
        // Formas + faixas de taxa (para o modal "Receber")
        $rf = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&ativo=eq.true&order=ordem.asc,nome.asc&select=id,nome,tipo,adquirente_id,taxa_pct,prazo_recebimento_dias" );
        $formas = $rf['ok'] ? ( $rf['data'] ?? [] ) : [];
        // Forma de cartão só entra no PDV se a OPERADORA dela estiver ativa (forma sem operadora passa)
        $radq = tao_caixa_api( "/caixa_adquirentes?cliente_id=eq.$cid&ativo=eq.true&select=id" );
        $adq_ativos = array_flip( array_column( $radq['ok'] ? ( $radq['data'] ?? [] ) : [], 'id' ) );
        $formas = array_values( array_filter( $formas, function ( $f ) use ( $adq_ativos ) {
            $aid = $f['adquirente_id'] ?? '';
            return ! $aid || isset( $adq_ativos[ $aid ] );
        } ) );
        $rtx = tao_caixa_api( "/caixa_taxas?cliente_id=eq.$cid&ativo=eq.true&select=forma_pagamento_id,adquirente_id,modalidade,bandeira,parcela_min,parcela_max,taxa_pct,prazo_recebimento_dias" );
        $taxas = $rtx['ok'] ? ( $rtx['data'] ?? [] ) : [];

        // Vindo de um card (?card=…) com exatamente 1 venda em aberto → abre o Receber direto
        if ( $card_filtro ) {
            $abertas = array_values( array_filter( $vendas, function ( $v ) {
                return in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true )
                    && ( floatval( $v['valor_total'] ?? 0 ) - floatval( $v['valor_pago'] ?? 0 ) ) > 0.005;
            } ) );
            if ( count( $abertas ) === 1 ) $auto_receber = $abertas[0]['id'];
        }

        // Número da Requisição (campo CRM chave=numero_requisicao) por card
        $card_ids = array_values( array_filter( array_map( function ( $v ) { return $v['card_id'] ?? ''; }, $vendas ) ) );
        if ( $card_ids ) {
            $rcd = tao_caixa_api( "/crm_campos_definicao?chave=eq.numero_requisicao&select=id" );
            $campo_ids = $rcd['ok'] ? array_column( $rcd['data'] ?? [], 'id' ) : [];
            if ( $campo_ids ) {
                $rvv = tao_caixa_api(
                    "/crm_cards_valores?card_id=in.(" . implode( ',', $card_ids ) . ")" .
                    "&campo_id=in.(" . implode( ',', $campo_ids ) . ")&select=card_id,valor"
                );
                foreach ( ( $rvv['ok'] ? ( $rvv['data'] ?? [] ) : [] ) as $row ) {
                    if ( ! empty( $row['valor'] ) ) $req_map[ $row['card_id'] ] = $row['valor'];
                }
            }
        }
    }

    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };
    // Links de status/origem preservam o período escolhido
    $purl = [];
    if ( $p === 'custom' )    $purl = [ 'p' => 'custom', 'de' => $de, 'ate' => $ate_d ];
    elseif ( $p !== 'hoje' )  $purl = [ 'p' => $p ];
    $url = function ( $params ) use ( $purl ) { return esc_url( tao_caixa_url( 'caixa-vendas', $params + $purl ) ); };
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F9FE; Vendas do Caixa</h1>
            <input type="search" id="taoc-venda-busca" placeholder="&#x1F50D; Buscar cliente, WhatsApp ou nº pedido..." autocomplete="off"
                   style="padding:7px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;min-width:260px">
            <button type="button" id="taoc-nova-avulsa" class="taoc-btn taoc-btn-primary">&#x1F6D2; Venda avulsa</button>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php else : ?>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:18px">
            <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Vendas listadas</div>
                <strong style="font-size:22px"><?php echo count( $vendas ); ?></strong>
            </div>
            <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Valor total</div>
                <strong style="font-size:22px"><?php echo $brl( $tot_geral ); ?></strong>
            </div>
            <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">A receber (aberto/parcial)</div>
                <strong style="font-size:22px;color:#1d4ed8"><?php echo $brl( $tot_receber ); ?></strong>
            </div>
        </div>

        <!-- Filtros -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;font-size:13px">
            <span style="color:#64748b;align-self:center">Período:</span>
            <?php
            // base p/ troca de período preservando status/origem
            $pb = []; if ( $status ) $pb['status'] = $status; if ( $origem ) $pb['origem'] = $origem;
            $_vbu = tao_caixa_url( 'caixa-vendas', $pb ); $_vsep = ( strpos( $_vbu, '?' ) !== false ) ? '&' : '?';
            ?>
            <select onchange="taocVPeriodo(this)" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px">
                <option value="hoje"  <?php selected( $p, 'hoje' ); ?>>Hoje</option>
                <option value="7d"    <?php selected( $p, '7d' ); ?>>7 dias</option>
                <option value="30d"   <?php selected( $p, '30d' ); ?>>30 dias</option>
                <option value="mes"   <?php selected( $p, 'mes' ); ?>>Mês corrente</option>
                <option value="todos" <?php selected( $p, 'todos' ); ?>>Todas (300 últimas)</option>
                <option value="custom" <?php selected( $p, 'custom' ); ?>>Período específico…</option>
            </select>
            <span id="taoc-v-range" style="align-items:center;gap:4px;display:<?php echo $p === 'custom' ? 'inline-flex' : 'none'; ?>">
                <input type="date" id="taoc-v-de"  value="<?php echo esc_attr( $p === 'custom' ? $de : '' ); ?>"    style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                <span style="color:#94a3b8;font-size:12px">até</span>
                <input type="date" id="taoc-v-ate" value="<?php echo esc_attr( $p === 'custom' ? $ate_d : '' ); ?>" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                <a class="taoc-btn taoc-btn-primary" href="#" onclick="taocVApply();return false">Aplicar</a>
            </span>
            <script>
            var TAOC_VBASE = <?php echo wp_json_encode( $_vbu . $_vsep ); ?>;
            function taocVPeriodo(sel){ var v=sel.value, r=document.getElementById('taoc-v-range');
                if(v==='custom'){ if(r) r.style.display='inline-flex'; return; }
                window.location = TAOC_VBASE + 'p=' + v; }
            function taocVApply(){ var de=document.getElementById('taoc-v-de').value, ate=document.getElementById('taoc-v-ate').value;
                if(!de||!ate){ alert('Informe as duas datas.'); return; }
                window.location = TAOC_VBASE + 'p=custom&de=' + de + '&ate=' + ate; }
            </script>
            <span style="width:1px;background:#e2e8f0;margin:0 4px"></span>
            <span style="color:#64748b;align-self:center">Status:</span>
            <a class="taoc-btn<?php echo $status===''?' taoc-btn-primary':''; ?>" href="<?php echo $url( $origem?[ 'origem'=>$origem ]:[] ); ?>">Todos</a>
            <?php foreach ( [ 'aberta'=>'A receber', 'parcial'=>'Parcial', 'quitada'=>'Quitada', 'cancelada'=>'Cancelada' ] as $sk => $sl ) :
                $p = [ 'status' => $sk ]; if ( $origem ) $p['origem'] = $origem; ?>
            <a class="taoc-btn<?php echo $status===$sk?' taoc-btn-primary':''; ?>" href="<?php echo $url( $p ); ?>"><?php echo esc_html( $sl ); ?></a>
            <?php endforeach; ?>
            <span style="width:1px;background:#e2e8f0;margin:0 4px"></span>
            <span style="color:#64748b;align-self:center">Origem:</span>
            <a class="taoc-btn<?php echo $origem===''?' taoc-btn-primary':''; ?>" href="<?php echo $url( $status?[ 'status'=>$status ]:[] ); ?>">Todas</a>
            <?php foreach ( [ 'funil'=>'Funil', 'avulsa'=>'Avulsa' ] as $ok => $ol ) :
                $p = [ 'origem' => $ok ]; if ( $status ) $p['status'] = $status; ?>
            <a class="taoc-btn<?php echo $origem===$ok?' taoc-btn-primary':''; ?>" href="<?php echo $url( $p ); ?>"><?php echo esc_html( $ol ); ?></a>
            <?php endforeach; ?>
            <span style="width:1px;background:#e2e8f0;margin:0 4px"></span>
            <a class="taoc-btn" href="<?php echo esc_url( $export_url ); ?>" title="Baixa em XLSX exatamente o que está filtrado na tela">&#x2B07; Exportar XLSX</a>
        </div>

        <div id="taoc-sel-bar" style="display:none;align-items:center;gap:12px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px">
            <strong id="taoc-sel-info"></strong>
            <button type="button" id="taoc-receber-cupom" class="taoc-btn taoc-btn-primary">Receber selecionadas (cupom)</button>
            <button type="button" id="taoc-sel-clear" class="taoc-btn">Limpar seleção</button>
        </div>

        <?php if ( empty( $vendas ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma venda encontrada<?php echo ( $status || $origem ) ? ' com esse filtro' : ''; ?>.</p>
            <p style="font-size:12px;color:#94a3b8">As vendas nascem automaticamente quando um card é fechado como Ganho (cruza para o Pós-vendas).</p>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr>
                    <th style="width:30px;text-align:center"><input type="checkbox" id="taoc-vsel-all" title="Selecionar todas (visíveis)"></th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Nº Req.</th>
                    <th>WhatsApp</th>
                    <th style="text-align:center">Origem</th>
                    <th style="text-align:center">Status</th>
                    <th style="text-align:right">Total</th>
                    <th style="text-align:right">Pago</th>
                    <th style="text-align:right">A receber</th>
                    <th style="text-align:center;width:96px">Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $vendas as $v ) :
                $total = floatval( $v['valor_total'] ?? 0 );
                $pago  = floatval( $v['valor_pago'] ?? 0 );
                $receber = max( 0, $total - $pago );
                $dt = ! empty( $v['criado_em'] ) ? date_i18n( 'd/m/Y H:i', strtotime( $v['criado_em'] ) ) : '—';
                $req = $req_map[ $v['card_id'] ?? '' ] ?? '';
                $search = mb_strtolower( trim( ( $v['cliente_nome'] ?? '' ) . ' ' . ( $v['whatsapp'] ?? '' ) . ' ' . $req ) );
            ?>
                <tr data-search="<?php echo esc_attr( $search ); ?>">
                    <td style="text-align:center">
                        <?php if ( in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true ) && $receber > 0 ) : ?>
                        <input type="checkbox" class="taoc-vsel" data-venda="<?php echo esc_attr( $v['id'] ); ?>" data-aberto="<?php echo esc_attr( number_format( $receber, 2, '.', '' ) ); ?>" data-cliente="<?php echo esc_attr( $v['cliente_nome'] ?: '—' ); ?>">
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap"><?php echo esc_html( $dt ); ?></td>
                    <td><strong><?php echo esc_html( $v['cliente_nome'] ?: '—' ); ?></strong></td>
                    <td style="font-weight:600;color:#0f172a"><?php echo esc_html( $req ?: '—' ); ?></td>
                    <td style="color:#475569"><?php echo esc_html( $v['whatsapp'] ?: '—' ); ?></td>
                    <td style="text-align:center"><?php echo tao_caixa_venda_origem_badge( $v['origem'] ?? '' ); ?></td>
                    <td style="text-align:center"><?php echo tao_caixa_venda_status_badge( $v['status'] ?? '' ); ?></td>
                    <td style="text-align:right;font-weight:700"><?php echo $brl( $total ); ?></td>
                    <td style="text-align:right;color:#16a34a"><?php echo $brl( $pago ); ?></td>
                    <td style="text-align:right;color:<?php echo $receber > 0 ? '#1d4ed8' : '#94a3b8'; ?>"><?php echo $brl( $receber ); ?></td>
                    <td style="text-align:center;white-space:nowrap">
                        <?php if ( in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true ) && $receber > 0 ) : ?>
                        <button type="button" class="taoc-btn taoc-btn-primary taoc-receber"
                                data-venda="<?php echo esc_attr( $v['id'] ); ?>"
                                data-cliente="<?php echo esc_attr( $v['cliente_nome'] ?: '—' ); ?>"
                                data-aberto="<?php echo esc_attr( number_format( $receber, 2, '.', '' ) ); ?>">Receber</button>
                        <?php endif; ?>
                        <?php if ( $pago > 0 && ! in_array( $v['status'] ?? '', [ 'cancelada', 'estornada' ], true ) ) : ?>
                        <button type="button" class="taoc-btn taoc-estornar" data-venda="<?php echo esc_attr( $v['id'] ); ?>"
                                style="color:#dc2626;border-color:#fecaca">&#x21A9; Estornar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:11px;color:#94a3b8;margin-top:10px">Exibindo as <?php echo count( $vendas ); ?> vendas mais recentes (máx. 300). Tela somente leitura — baixa de pagamento e estorno virão no PDV (Fase 1, passo 4).</p>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Modal: Receber pagamento (com split) -->
    <div id="taoc-receber-modal" class="taoc-modal">
        <div class="taoc-overlay"></div>
        <div class="taoc-box" style="max-width:580px">
            <h2>&#x1F4B3; Receber pagamento</h2>
            <p id="taoc-rec-info" style="font-size:13px;color:#475569;margin:0 0 12px"></p>
            <input type="hidden" id="taoc-rec-venda">
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                <div style="flex:2;min-width:180px;position:relative">
                    <label style="font-size:11px;color:#64748b;display:block">Nome do cliente</label>
                    <input type="text" id="taoc-rec-nome" placeholder="Nome completo" autocomplete="off"
                           style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:4px">
                    <div id="taoc-rec-nome-sug" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.12);max-height:180px;overflow:auto;z-index:50"></div>
                </div>
                <div style="flex:1;min-width:140px">
                    <label style="font-size:11px;color:#64748b;display:block">CPF/CNPJ do cliente</label>
                    <input type="text" id="taoc-rec-cpf" placeholder="CPF ou CNPJ" maxlength="18" autocomplete="off"
                           style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:4px">
                </div>
                <div style="width:140px">
                    <label style="font-size:11px;color:#64748b;display:block">Data do pagamento</label>
                    <input type="date" id="taoc-rec-data" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"
                           value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"
                           style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:4px">
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                <div style="width:170px">
                    <label style="font-size:11px;color:#64748b;display:block">Desconto adicional</label>
                    <span style="display:flex;gap:4px">
                        <input type="number" id="taoc-rec-desc" min="0" step="0.01" value="0"
                               style="flex:1;min-width:0;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:right">
                        <select id="taoc-rec-desc-tipo" style="width:52px;padding:5px;border:1px solid #cbd5e1;border-radius:4px">
                            <option value="valor">R$</option>
                            <option value="pct">%</option>
                        </select>
                    </span>
                </div>
                <div style="width:170px">
                    <label style="font-size:11px;color:#64748b;display:block" title="Acréscimo do recebimento">CM</label>
                    <span style="display:flex;gap:4px">
                        <input type="number" id="taoc-rec-cm" min="0" step="0.01" value="0"
                               style="flex:1;min-width:0;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:right">
                        <select id="taoc-rec-cm-tipo" style="width:52px;padding:5px;border:1px solid #cbd5e1;border-radius:4px">
                            <option value="valor">R$</option>
                            <option value="pct">%</option>
                        </select>
                    </span>
                </div>
                <div style="width:100px">
                    <label style="font-size:11px;color:#64748b;display:block">Taxas</label>
                    <select id="taoc-rec-cupom" style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:4px">
                        <option value="0">Não</option>
                        <option value="1">Sim</option>
                    </select>
                </div>
            </div>
            <div id="taoc-pag-linhas"></div>
            <button type="button" id="taoc-add-pag" class="taoc-btn" style="margin-top:4px">+ Adicionar forma (split)</button>
            <div id="taoc-rec-resumo" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;font-size:13px;margin:12px 0;color:#334155"></div>
            <div class="taoc-actions">
                <button type="button" id="taoc-rec-confirm" class="taoc-btn taoc-btn-primary">Confirmar recebimento</button>
                <button type="button" id="taoc-rec-cancel" class="taoc-btn">Cancelar</button>
            </div>
            <p id="taoc-rec-msg" style="display:none;margin-top:10px;font-size:13px"></p>
        </div>
    </div>

    <!-- Modal: Venda avulsa (balcão) -->
    <div id="taoc-avulsa-modal" class="taoc-modal">
        <div class="taoc-overlay"></div>
        <div class="taoc-box" style="max-width:600px">
            <h2>&#x1F6D2; Venda avulsa (balcão)</h2>
            <div class="taoc-field taoc-field-inline">
                <div style="flex:2"><label>Cliente</label><input type="text" id="av-cliente" placeholder="Consumidor Final" style="width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:4px"></div>
                <div style="flex:1"><label>WhatsApp (opcional)</label><input type="text" id="av-whats" style="width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:4px"></div>
            </div>
            <label style="font-size:12px;font-weight:600;color:#475569;margin:12px 0 4px;display:block">Itens</label>
            <div id="av-itens"></div>
            <button type="button" id="av-add-item" class="taoc-btn" style="margin-top:4px">+ Item</button>
            <div id="av-total" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;font-size:14px;margin:12px 0;text-align:right">Total: <strong>R$ 0,00</strong></div>
            <div class="taoc-actions">
                <button type="button" id="av-confirm" class="taoc-btn taoc-btn-primary">Criar venda</button>
                <button type="button" id="av-cancel" class="taoc-btn">Cancelar</button>
            </div>
            <p id="av-msg" style="display:none;margin-top:10px;font-size:13px"></p>
        </div>
    </div>

    <script>
    (function(){
        var FORMAS = <?php echo wp_json_encode( $formas ); ?>;
        var TAXAS  = <?php echo wp_json_encode( $taxas ); ?>;
        var AUTO_RECEBER = <?php echo wp_json_encode( $auto_receber ); ?>;
        var C = window.taoCaixa || {};
        var modal = document.getElementById('taoc-receber-modal');

        function brl(v){ return 'R$ ' + (parseFloat(v)||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
        // Validação de CPF (11 díg.) e CNPJ (14 díg.) pelos dígitos verificadores
        function docValido(doc){
            var d = (doc||'').replace(/\D/g,'');
            if(!d) return true;                       // vazio é permitido
            function calc(base, pesos){ var s=0; for(var i=0;i<pesos.length;i++) s+=parseInt(base[i])*pesos[i]; var r=s%11; return r<2?0:11-r; }
            if(d.length===11){
                if(/^(\d)\1{10}$/.test(d)) return false;
                var p1=[10,9,8,7,6,5,4,3,2], p2=[11,10,9,8,7,6,5,4,3,2];
                return calc(d,p1)===parseInt(d[9]) && calc(d,p2)===parseInt(d[10]);
            }
            if(d.length===14){
                if(/^(\d)\1{13}$/.test(d)) return false;
                var q1=[5,4,3,2,9,8,7,6,5,4,3,2], q2=[6,5,4,3,2,9,8,7,6,5,4,3,2];
                return calc(d,q1)===parseInt(d[12]) && calc(d,q2)===parseInt(d[13]);
            }
            return false;                              // tamanho inválido
        }

        // ── Busca por texto (como no Kanban): casa por texto OU por dígitos ──
        var busca = document.getElementById('taoc-venda-busca');
        if(busca){
            busca.addEventListener('input', function(){
                var q = this.value.toLowerCase().trim(), qd = q.replace(/\D/g,'');
                var rows = document.querySelectorAll('table.taoc-table tbody tr');
                for(var i=0;i<rows.length;i++){
                    var sd = rows[i].getAttribute('data-search') || '';
                    rows[i].style.display = (!q || sd.indexOf(q)!==-1 || (qd.length>=3 && sd.replace(/\D/g,'').indexOf(qd)!==-1)) ? '' : 'none';
                }
            });
        }
        if(!modal) return;

        var info   = document.getElementById('taoc-rec-info');
        var vInp   = document.getElementById('taoc-rec-venda');
        var box    = document.getElementById('taoc-pag-linhas');
        var resumo = document.getElementById('taoc-rec-resumo');
        var msg    = document.getElementById('taoc-rec-msg');
        var saldo  = 0;

        function formaOpts(){
            var h = '<option value="">— Forma —</option>';
            FORMAS.forEach(function(f){ h += '<option value="'+f.id+'">' + String(f.nome).replace(/</g,'&lt;') + '</option>'; });
            return h;
        }
        function ehCartao(f){ return f && (f.tipo==='debito'||f.tipo==='credito') && f.adquirente_id; }
        function resolveLinha(fid, parc, band){
            var f = FORMAS.filter(function(x){ return x.id===fid; })[0];
            var taxa = f ? parseFloat(f.taxa_pct||0) : 0, prazo = f ? parseInt(f.prazo_recebimento_dias||0) : 0;
            // Modelo novo: taxa da OPERADORA (modalidade × bandeira × faixa) — bandeira exata vence o curinga
            var fx = null;
            if(ehCartao(f)){
                var cand = TAXAS.filter(function(t){
                    return t.adquirente_id===f.adquirente_id && t.modalidade===f.tipo
                        && parc>=t.parcela_min && parc<=t.parcela_max
                        && (!t.bandeira || (band && t.bandeira.toUpperCase()===band.toUpperCase()));
                });
                cand.sort(function(a,b){ return (b.bandeira?1:0)-(a.bandeira?1:0) || b.parcela_min-a.parcela_min; });
                fx = cand[0] || null;
            }
            if(!fx){ // legado: taxa presa à forma
                fx = TAXAS.filter(function(t){ return t.forma_pagamento_id===fid && parc>=t.parcela_min && parc<=t.parcela_max; })
                          .sort(function(a,b){ return b.parcela_min-a.parcela_min; })[0] || null;
            }
            if(fx){ taxa=parseFloat(fx.taxa_pct); prazo=parseInt(fx.prazo_recebimento_dias); }
            return { taxa:taxa, prazo:prazo };
        }
        // Desconto adicional e CM aceitam R$ ou % (percentual calculado sobre o Valor final/saldo)
        function _valTipo(inpId, selId){
            var v = parseFloat((document.getElementById(inpId)||{}).value||'0')||0;
            var t = (document.getElementById(selId)||{}).value||'valor';
            var r = t==='pct' ? saldo*v/100 : v;
            return Math.round(Math.max(0,r)*100)/100;
        }
        function descReais(){ return _valTipo('taoc-rec-desc','taoc-rec-desc-tipo'); }
        function cmReais(){   return _valTipo('taoc-rec-cm','taoc-rec-cm-tipo'); }
        function recalc(){
            var soma = 0;
            var linhas = box.querySelectorAll('.taoc-pag-linha');
            for(var i=0;i<linhas.length;i++){
                var div = linhas[i];
                var fid = div.querySelector('.pag-forma').value;
                var parc = parseInt(div.querySelector('.pag-parc').value||1);
                var val = parseFloat((div.querySelector('.pag-valor').value||'0').replace(',','.'))||0;
                soma += val;
                // Bandeira: só aparece em cartão com operadora vinculada
                var f = FORMAS.filter(function(x){ return x.id===fid; })[0];
                var bsel = div.querySelector('.pag-band');
                bsel.style.display = ehCartao(f) ? '' : 'none';
                var band = ehCartao(f) ? bsel.value : '';
                var prev = div.querySelector('.pag-prev');
                if(fid && val>0){ var r = resolveLinha(fid,parc,band); prev.innerHTML = 'taxa '+r.taxa.toFixed(2).replace('.',',')+'% · líquido '+brl(val-val*r.taxa/100)+' · '+r.prazo+'d'; }
                else prev.innerHTML = '';
            }
            soma = Math.round(soma*100)/100;
            var descAd = descReais();
            var cmVal  = cmReais();
            // Regra 01/09: A receber = Valor final (saldo) + CM − Desconto adicional
            var aReceber = Math.round((saldo+cmVal-descAd)*100)/100;
            var falta = Math.round((aReceber-soma)*100)/100;
            resumo.innerHTML = 'Valor final: <strong>'+brl(saldo)+'</strong>'
                + (cmVal>0 ? ' &nbsp;+&nbsp; CM: <strong style="color:#7c3aed">'+brl(cmVal)+'</strong>' : '')
                + (descAd>0 ? ' &nbsp;&minus;&nbsp; Desconto: <strong style="color:#0369a1">'+brl(descAd)+'</strong>' : '')
                + ' &nbsp;=&nbsp; A receber: <strong>'+brl(aReceber)+'</strong>'
                + ' &nbsp;&middot;&nbsp; Pagamentos: <strong>'+brl(soma)+'</strong>'
                + ' &nbsp;&middot;&nbsp; '
                + (falta < -0.005
                    ? 'Excede: <strong style="color:#dc2626">'+brl(-falta)+'</strong>'
                    : 'Falta: <strong style="color:'+(Math.abs(falta)<0.005?'#16a34a':'#92400e')+'">'+brl(falta)+'</strong>');
        }
        function addLinha(valor){
            var div = document.createElement('div');
            div.className = 'taoc-pag-linha';
            div.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap';
            div.innerHTML =
                '<select class="pag-forma" style="flex:2;min-width:120px;padding:5px;border:1px solid #cbd5e1;border-radius:4px">'+formaOpts()+'</select>'
              + '<select class="pag-band" title="bandeira" style="display:none;width:92px;padding:5px;border:1px solid #cbd5e1;border-radius:4px">'
              +   '<option value="">Bandeira…</option><option value="VISA">Visa</option><option value="MASTER">Master</option>'
              +   '<option value="ELO">Elo</option><option value="HIPER">Hiper</option><option value="AMEX">Amex</option>'
              + '</select>'
              + '<input class="pag-parc" type="number" min="1" max="24" value="1" title="parcelas" style="width:46px;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:center">'
              + '<input class="pag-valor" type="number" min="0" step="0.01" value="'+(valor!=null?valor.toFixed(2):'')+'" placeholder="valor" style="width:96px;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:right">'
              + '<button type="button" class="pag-rm taoc-btn" title="remover" style="padding:4px 9px">&#x2715;</button>'
              + '<div class="pag-prev" style="flex-basis:100%;font-size:11px;color:#64748b"></div>';
            box.appendChild(div);
            div.querySelector('.pag-forma').addEventListener('change', recalc);
            div.querySelector('.pag-band').addEventListener('change', recalc);
            div.querySelector('.pag-parc').addEventListener('input', recalc);
            div.querySelector('.pag-valor').addEventListener('input', recalc);
            div.querySelector('.pag-rm').addEventListener('click', function(){ div.remove(); recalc(); });
            recalc();
        }
        var selVendas = [];
        function openModal(ids, saldoTotal, label){
            selVendas = ids; saldo = Math.round(saldoTotal*100)/100;
            info.innerHTML = label;
            box.innerHTML=''; msg.style.display='none';
            var _n=document.getElementById('taoc-rec-nome');  if(_n) _n.value='';
            var _ns=document.getElementById('taoc-rec-nome-sug'); if(_ns) _ns.style.display='none';
            var _c=document.getElementById('taoc-rec-cpf');   if(_c) _c.value='';
            var _d=document.getElementById('taoc-rec-desc');  if(_d) _d.value='0';
            var _dtp=document.getElementById('taoc-rec-desc-tipo'); if(_dtp) _dtp.value='valor';
            var _cm=document.getElementById('taoc-rec-cm');   if(_cm) _cm.value='0';
            var _cmt=document.getElementById('taoc-rec-cm-tipo'); if(_cmt) _cmt.value='valor';
            var _dt=document.getElementById('taoc-rec-data'); if(_dt) _dt.value=_dt.getAttribute('max');
            var _cf=document.getElementById('taoc-rec-cupom');if(_cf) _cf.value='0';
            addLinha(saldo);
            modal.style.display='block';
        }
        ['taoc-rec-desc','taoc-rec-desc-tipo','taoc-rec-cm','taoc-rec-cm-tipo'].forEach(function(id){
            var el=document.getElementById(id);
            if(el) el.addEventListener(el.tagName==='SELECT'?'change':'input', function(){ recalc(); });
        });

        // ── Nome ↔ CPF (busca no cadastro único de contatos) ──
        var nomeInp = document.getElementById('taoc-rec-nome');
        var cpfInp  = document.getElementById('taoc-rec-cpf');
        var sugBox  = document.getElementById('taoc-rec-nome-sug');
        var sugSel  = -1, sugItens = [], buscaTimer;
        function fecharSug(){ if(sugBox){ sugBox.style.display='none'; sugBox.innerHTML=''; } sugSel=-1; sugItens=[]; }
        function pintarSug(){
            var divs = sugBox ? sugBox.querySelectorAll('.taoc-sug-item') : [];
            for(var i=0;i<divs.length;i++) divs[i].style.background = (i===sugSel)?'#eef2ff':'#fff';
        }
        function aplicarContato(c){
            if(nomeInp && c.nome) nomeInp.value = c.nome;
            if(cpfInp  && c.cpf)  cpfInp.value  = c.cpf;
            fecharSug();
        }
        function buscarContato(q, porCpf){
            var fd = new FormData();
            fd.append('action','tao_caixa_buscar_contato'); fd.append('nonce',C.nonce);
            fd.append(porCpf?'cpf':'q', q);
            return fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){ return r.json(); });
        }
        if(nomeInp && sugBox){
            nomeInp.addEventListener('input', function(){
                clearTimeout(buscaTimer);
                var q = nomeInp.value.trim();
                if(q.length < 3){ fecharSug(); return; }
                buscaTimer = setTimeout(function(){
                    buscarContato(q, false).then(function(resp){
                        if(!resp || !resp.success || !resp.data || !resp.data.length){ fecharSug(); return; }
                        sugItens = resp.data; sugSel = -1;
                        sugBox.innerHTML = '';
                        resp.data.forEach(function(c, i){
                            var d = document.createElement('div');
                            d.className = 'taoc-sug-item';
                            d.style.cssText = 'padding:6px 10px;font-size:13px;cursor:pointer;border-bottom:1px solid #f1f5f9';
                            d.innerHTML = '<strong>'+c.nome+'</strong>'+(c.cpf?' <span style="color:#64748b">· '+c.cpf+'</span>':'');
                            d.addEventListener('mousedown', function(ev){ ev.preventDefault(); aplicarContato(c); });
                            d.addEventListener('mouseenter', function(){ sugSel=i; pintarSug(); });
                            sugBox.appendChild(d);
                        });
                        sugBox.style.display='block';
                    }).catch(function(){ fecharSug(); });
                }, 250);
            });
            // Navegação por teclado: ↑/↓ + Enter/Esc
            nomeInp.addEventListener('keydown', function(ev){
                if(sugBox.style.display==='none' || !sugItens.length) return;
                if(ev.key==='ArrowDown'){ ev.preventDefault(); sugSel=Math.min(sugSel+1,sugItens.length-1); pintarSug(); }
                else if(ev.key==='ArrowUp'){ ev.preventDefault(); sugSel=Math.max(sugSel-1,0); pintarSug(); }
                else if(ev.key==='Enter'){ ev.preventDefault(); if(sugSel>=0) aplicarContato(sugItens[sugSel]); }
                else if(ev.key==='Escape'){ fecharSug(); }
            });
            nomeInp.addEventListener('blur', function(){ setTimeout(fecharSug, 150); });
        }
        if(cpfInp){
            cpfInp.addEventListener('input', function(){
                clearTimeout(buscaTimer);
                var dig = (cpfInp.value||'').replace(/\D/g,'');
                if(!(dig.length===11||dig.length===14) || !docValido(cpfInp.value)) return;
                buscaTimer = setTimeout(function(){
                    buscarContato(dig, true).then(function(resp){
                        if(resp && resp.success && resp.data && resp.data.length && nomeInp && !nomeInp.value.trim()){
                            nomeInp.value = resp.data[0].nome || '';
                        }
                    }).catch(function(){});
                }, 250);
            });
        }
        var btns = document.querySelectorAll('.taoc-receber');
        for(var i=0;i<btns.length;i++){
            btns[i].addEventListener('click', function(){
                var ab = parseFloat(this.getAttribute('data-aberto')||'0');
                openModal([this.getAttribute('data-venda')], ab,
                    '<strong>'+this.getAttribute('data-cliente')+'</strong> &middot; a receber: <strong>'+brl(ab)+'</strong>');
            });
        }
        document.getElementById('taoc-add-pag').addEventListener('click', function(){ addLinha(null); });

        // ── Estorno (auditado) ──
        var estBtns = document.querySelectorAll('.taoc-estornar');
        for(var e=0;e<estBtns.length;e++){
            estBtns[e].addEventListener('click', function(){
                var vid = this.getAttribute('data-venda');
                var motivo = prompt('Estorno (auditado). Informe o motivo:');
                if(motivo===null) return;
                if(!motivo.trim()){ alert('Informe o motivo do estorno.'); return; }
                var b=this; b.disabled=true; b.textContent='...';
                var fd=new FormData(); fd.append('action','tao_caixa_estornar_venda'); fd.append('nonce',C.nonce);
                fd.append('venda_id',vid); fd.append('motivo',motivo);
                fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(resp){
                    if(resp&&resp.success){
                        if(resp.data&&resp.data.vendas_afetadas>1){ alert('Estorno feito. '+resp.data.vendas_afetadas+' vendas foram reabertas (o recibo cobria várias).'); }
                        location.reload();
                    } else { b.disabled=false; b.innerHTML='&#x21A9; Estornar'; alert('Erro: '+((resp&&resp.data)||'falha')); }
                }).catch(function(){ b.disabled=false; b.innerHTML='&#x21A9; Estornar'; alert('Falha de comunicação'); });
            });
        }

        // ── Seleção múltipla → cupom cobrindo várias vendas ──
        function checkedSel(){ return Array.prototype.slice.call(document.querySelectorAll('.taoc-vsel:checked')); }
        function atualizaSelBar(){
            var bar = document.getElementById('taoc-sel-bar'); if(!bar) return;
            var sel = checkedSel();
            if(!sel.length){ bar.style.display='none'; return; }
            var soma = 0; sel.forEach(function(c){ soma += parseFloat(c.getAttribute('data-aberto')||'0'); });
            document.getElementById('taoc-sel-info').textContent = sel.length + ' venda(s) · total a receber ' + brl(soma);
            bar.style.display='flex';
        }
        var allCb = document.getElementById('taoc-vsel-all');
        if(allCb){ allCb.addEventListener('change', function(){
            var rows = document.querySelectorAll('table.taoc-table tbody tr');
            for(var i=0;i<rows.length;i++){ if(rows[i].style.display!=='none'){ var c=rows[i].querySelector('.taoc-vsel'); if(c) c.checked=allCb.checked; } }
            atualizaSelBar();
        }); }
        document.addEventListener('change', function(e){ if(e.target && e.target.classList && e.target.classList.contains('taoc-vsel')) atualizaSelBar(); });
        var cupomBtn = document.getElementById('taoc-receber-cupom');
        if(cupomBtn){ cupomBtn.addEventListener('click', function(){
            var sel = checkedSel(); if(!sel.length) return;
            var ids = sel.map(function(c){ return c.getAttribute('data-venda'); });
            var soma = 0; sel.forEach(function(c){ soma += parseFloat(c.getAttribute('data-aberto')||'0'); });
            openModal(ids, soma, '<strong>'+sel.length+' venda(s)</strong> &middot; total a receber: <strong>'+brl(soma)+'</strong>');
        }); }
        var selClear = document.getElementById('taoc-sel-clear');
        if(selClear){ selClear.addEventListener('click', function(){
            var cbs=document.querySelectorAll('.taoc-vsel'); for(var i=0;i<cbs.length;i++) cbs[i].checked=false;
            if(allCb) allCb.checked=false; atualizaSelBar();
        }); }
        function close(){ modal.style.display='none'; }
        document.getElementById('taoc-rec-cancel').addEventListener('click', close);
        modal.querySelector('.taoc-overlay').addEventListener('click', close);

        document.getElementById('taoc-rec-confirm').addEventListener('click', function(){
            var pags = [], linhas = box.querySelectorAll('.taoc-pag-linha');
            for(var i=0;i<linhas.length;i++){
                var div = linhas[i];
                var fid = div.querySelector('.pag-forma').value;
                var parc = parseInt(div.querySelector('.pag-parc').value||1);
                var val = parseFloat((div.querySelector('.pag-valor').value||'0').replace(',','.'))||0;
                var f = FORMAS.filter(function(x){ return x.id===fid; })[0];
                var band = ehCartao(f) ? div.querySelector('.pag-band').value : '';
                if(fid && val>0) pags.push({ forma_pagamento_id:fid, parcelas:parc, valor:val, bandeira:band });
            }
            if(!pags.length){ alert('Informe ao menos uma forma com valor.'); return; }
            var _cpfEl  = document.getElementById('taoc-rec-cpf');
            var _nomeEl = document.getElementById('taoc-rec-nome');
            if(_cpfEl && !docValido(_cpfEl.value)){
                alert('CPF/CNPJ inválido — confira os dígitos.');
                _cpfEl.style.borderColor = '#dc2626'; _cpfEl.focus(); return;
            }
            if(_cpfEl) _cpfEl.style.borderColor = '#cbd5e1';
            // CPF válido informado → nome do cliente é obrigatório (grava no cadastro único)
            if(_cpfEl && (_cpfEl.value||'').replace(/\D/g,'').length>0 && _nomeEl && !_nomeEl.value.trim()){
                alert('Informe o nome do cliente (CPF válido informado).');
                _nomeEl.style.borderColor = '#dc2626'; _nomeEl.focus(); return;
            }
            if(_nomeEl) _nomeEl.style.borderColor = '#cbd5e1';
            var soma = 0; pags.forEach(function(p){ soma += p.valor; });
            var descAd = descReais();
            var cmConf = cmReais();
            // Regra 01/09: pagamentos não podem exceder A receber = Valor final + CM − desconto
            if(soma > saldo + cmConf - descAd + 0.005){ alert('Pagamentos ('+brl(soma)+') excedem o valor a receber ('+brl(Math.round((saldo+cmConf-descAd)*100)/100)+').'); return; }
            var dtPag = (document.getElementById('taoc-rec-data')||{}).value||'';
            var cb = document.getElementById('taoc-rec-confirm'); cb.disabled=true; cb.textContent='Processando...';
            var fd = new FormData();
            fd.append('action','tao_caixa_receber_venda'); fd.append('nonce',C.nonce);
            fd.append('venda_ids',JSON.stringify(selVendas)); fd.append('pagamentos',JSON.stringify(pags));
            fd.append('cpf_pagador',(document.getElementById('taoc-rec-cpf')||{}).value||'');
            fd.append('nome_pagador',(_nomeEl||{}).value||'');
            fd.append('data_pagamento',dtPag);
            fd.append('desconto_adicional',descAd);
            fd.append('valor_cm',cmConf);
            fd.append('cupom_fiscal',(document.getElementById('taoc-rec-cupom')||{}).value||'0');
            fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    cb.disabled=false; cb.textContent='Confirmar recebimento';
                    if(resp && resp.success){ location.reload(); }
                    else { msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='Erro: '+((resp&&resp.data)||'falha'); }
                })
                .catch(function(){ cb.disabled=false; cb.textContent='Confirmar recebimento'; msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='Falha de comunicação'; });
        });

        // Atalho card → pagamento: abre o Receber automaticamente
        if(AUTO_RECEBER){ var ab=document.querySelector('.taoc-receber[data-venda="'+AUTO_RECEBER+'"]'); if(ab) ab.click(); }

        // ── Venda avulsa (balcão) ──
        var avModal=document.getElementById('taoc-avulsa-modal');
        if(avModal){
            var avItens=document.getElementById('av-itens');
            var avTotalEl=document.getElementById('av-total');
            var avMsg=document.getElementById('av-msg');
            function avTotal(){
                var t=0, ls=avItens.querySelectorAll('.av-linha');
                for(var i=0;i<ls.length;i++){
                    var q=parseFloat((ls[i].querySelector('.av-qtd').value||'1').replace(',','.'))||1;
                    var v=parseFloat((ls[i].querySelector('.av-valor').value||'0').replace(',','.'))||0;
                    t+=q*v;
                }
                avTotalEl.innerHTML='Total: <strong>'+brl(t)+'</strong>';
            }
            function avAdd(){
                var d=document.createElement('div'); d.className='av-linha';
                d.style.cssText='display:flex;gap:6px;margin-bottom:6px';
                d.innerHTML='<input class="av-desc" type="text" placeholder="Descrição" style="flex:3;padding:5px;border:1px solid #cbd5e1;border-radius:4px">'
                  +'<input class="av-qtd" type="number" min="1" step="1" value="1" title="qtd" style="width:54px;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:center">'
                  +'<input class="av-valor" type="number" min="0" step="0.01" placeholder="valor un." style="width:100px;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:right">'
                  +'<button type="button" class="av-rm taoc-btn" style="padding:4px 9px">&#x2715;</button>';
                avItens.appendChild(d);
                d.querySelector('.av-qtd').addEventListener('input',avTotal);
                d.querySelector('.av-valor').addEventListener('input',avTotal);
                d.querySelector('.av-rm').addEventListener('click',function(){ d.remove(); avTotal(); });
                avTotal();
            }
            document.getElementById('taoc-nova-avulsa').addEventListener('click',function(){
                document.getElementById('av-cliente').value=''; document.getElementById('av-whats').value='';
                avItens.innerHTML=''; avMsg.style.display='none'; avAdd();
                avModal.style.display='block';
            });
            document.getElementById('av-add-item').addEventListener('click',avAdd);
            function avClose(){ avModal.style.display='none'; }
            document.getElementById('av-cancel').addEventListener('click',avClose);
            avModal.querySelector('.taoc-overlay').addEventListener('click',avClose);
            document.getElementById('av-confirm').addEventListener('click',function(){
                var itens=[], ls=avItens.querySelectorAll('.av-linha');
                for(var i=0;i<ls.length;i++){
                    var desc=ls[i].querySelector('.av-desc').value.trim();
                    var q=parseFloat((ls[i].querySelector('.av-qtd').value||'1').replace(',','.'))||1;
                    var v=parseFloat((ls[i].querySelector('.av-valor').value||'0').replace(',','.'))||0;
                    if(desc&&v>0) itens.push({descricao:desc,quantidade:q,valor_unitario:v});
                }
                if(!itens.length){ alert('Adicione ao menos um item com descrição e valor.'); return; }
                var b=document.getElementById('av-confirm'); b.disabled=true; b.textContent='Criando...';
                var fd=new FormData(); fd.append('action','tao_caixa_venda_avulsa'); fd.append('nonce',C.nonce);
                fd.append('cliente_nome',document.getElementById('av-cliente').value);
                fd.append('whatsapp',document.getElementById('av-whats').value);
                fd.append('itens',JSON.stringify(itens));
                fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(resp){
                    b.disabled=false; b.textContent='Criar venda';
                    if(resp&&resp.success){ location.reload(); }
                    else { avMsg.style.display='block'; avMsg.style.color='#dc2626'; avMsg.textContent='Erro: '+((resp&&resp.data)||'falha'); }
                }).catch(function(){ b.disabled=false; b.textContent='Criar venda'; avMsg.style.display='block'; avMsg.style.color='#dc2626'; avMsg.textContent='Falha de comunicação'; });
            });
        }
    })();
    </script>
    <?php
}
