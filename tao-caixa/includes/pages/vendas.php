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
            "/caixa_vendas?cliente_id=eq.$cid$flt&order=criado_em.desc&limit=300" .
            "&select=id,card_id,cliente_nome,whatsapp,valor_total,valor_pago,status,origem,criado_em"
        );
        $vendas = $rv['ok'] ? ( $rv['data'] ?? [] ) : [];
        foreach ( $vendas as $v ) {
            $tot_geral += floatval( $v['valor_total'] ?? 0 );
            if ( in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true ) ) {
                $tot_receber += floatval( $v['valor_total'] ?? 0 ) - floatval( $v['valor_pago'] ?? 0 );
            }
        }
        // Formas + faixas de taxa (para o modal "Receber")
        $rf = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&ativo=eq.true&order=ordem.asc,nome.asc&select=id,nome,tipo,taxa_pct,prazo_recebimento_dias" );
        $formas = $rf['ok'] ? ( $rf['data'] ?? [] ) : [];
        $rtx = tao_caixa_api( "/caixa_taxas?cliente_id=eq.$cid&ativo=eq.true&select=forma_pagamento_id,parcela_min,parcela_max,taxa_pct,prazo_recebimento_dias" );
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
    $url = function ( $params ) { return esc_url( tao_caixa_url( 'caixa-vendas', $params ) ); };
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F9FE; Vendas do Caixa</h1>
            <input type="search" id="taoc-venda-busca" placeholder="&#x1F50D; Buscar cliente, WhatsApp ou nº pedido..." autocomplete="off"
                   style="padding:7px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;min-width:300px">
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

    <script>
    (function(){
        var FORMAS = <?php echo wp_json_encode( $formas ); ?>;
        var TAXAS  = <?php echo wp_json_encode( $taxas ); ?>;
        var AUTO_RECEBER = <?php echo wp_json_encode( $auto_receber ); ?>;
        var C = window.taoCaixa || {};
        var modal = document.getElementById('taoc-receber-modal');

        function brl(v){ return 'R$ ' + (parseFloat(v)||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }

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
        function resolveLinha(fid, parc){
            var f = FORMAS.filter(function(x){ return x.id===fid; })[0];
            var taxa = f ? parseFloat(f.taxa_pct||0) : 0, prazo = f ? parseInt(f.prazo_recebimento_dias||0) : 0;
            var fx = TAXAS.filter(function(t){ return t.forma_pagamento_id===fid && parc>=t.parcela_min && parc<=t.parcela_max; })
                          .sort(function(a,b){ return b.parcela_min-a.parcela_min; })[0];
            if(fx){ taxa=parseFloat(fx.taxa_pct); prazo=parseInt(fx.prazo_recebimento_dias); }
            return { taxa:taxa, prazo:prazo };
        }
        function recalc(){
            var soma = 0;
            var linhas = box.querySelectorAll('.taoc-pag-linha');
            for(var i=0;i<linhas.length;i++){
                var div = linhas[i];
                var fid = div.querySelector('.pag-forma').value;
                var parc = parseInt(div.querySelector('.pag-parc').value||1);
                var val = parseFloat((div.querySelector('.pag-valor').value||'0').replace(',','.'))||0;
                soma += val;
                var prev = div.querySelector('.pag-prev');
                if(fid && val>0){ var r = resolveLinha(fid,parc); prev.innerHTML = 'taxa '+r.taxa.toFixed(2).replace('.',',')+'% · líquido '+brl(val-val*r.taxa/100)+' · '+r.prazo+'d'; }
                else prev.innerHTML = '';
            }
            soma = Math.round(soma*100)/100;
            var falta = Math.round((saldo-soma)*100)/100;
            resumo.innerHTML = 'Saldo: <strong>'+brl(saldo)+'</strong> &nbsp;&middot;&nbsp; Pagamentos: <strong>'+brl(soma)+'</strong> &nbsp;&middot;&nbsp; '
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
              + '<input class="pag-parc" type="number" min="1" max="24" value="1" title="parcelas" style="width:46px;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:center">'
              + '<input class="pag-valor" type="number" min="0" step="0.01" value="'+(valor!=null?valor.toFixed(2):'')+'" placeholder="valor" style="width:96px;padding:5px;border:1px solid #cbd5e1;border-radius:4px;text-align:right">'
              + '<button type="button" class="pag-rm taoc-btn" title="remover" style="padding:4px 9px">&#x2715;</button>'
              + '<div class="pag-prev" style="flex-basis:100%;font-size:11px;color:#64748b"></div>';
            box.appendChild(div);
            div.querySelector('.pag-forma').addEventListener('change', recalc);
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
            addLinha(saldo);
            modal.style.display='block';
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
                if(fid && val>0) pags.push({ forma_pagamento_id:fid, parcelas:parc, valor:val });
            }
            if(!pags.length){ alert('Informe ao menos uma forma com valor.'); return; }
            var soma = 0; pags.forEach(function(p){ soma += p.valor; });
            if(soma > saldo + 0.005){ alert('Total dos pagamentos ('+brl(soma)+') excede o saldo ('+brl(saldo)+').'); return; }
            var cb = document.getElementById('taoc-rec-confirm'); cb.disabled=true; cb.textContent='Processando...';
            var fd = new FormData();
            fd.append('action','tao_caixa_receber_venda'); fd.append('nonce',C.nonce);
            fd.append('venda_ids',JSON.stringify(selVendas)); fd.append('pagamentos',JSON.stringify(pags));
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
    })();
    </script>
    <?php
}
