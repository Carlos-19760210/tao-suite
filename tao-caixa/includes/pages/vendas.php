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

    $cid    = tao_caixa_cliente_id();
    $status = sanitize_text_field( $_GET['status'] ?? '' );
    $origem = sanitize_text_field( $_GET['origem'] ?? '' );

    $vendas      = [];
    $formas      = [];
    $taxas       = [];
    $tot_geral   = 0.0;
    $tot_receber = 0.0;

    if ( $cid ) {
        $flt  = $status ? '&status=eq.' . rawurlencode( $status ) : '';
        $flt .= $origem ? '&origem=eq.' . rawurlencode( $origem ) : '';
        $rv = tao_caixa_api(
            "/caixa_vendas?cliente_id=eq.$cid$flt&order=criado_em.desc&limit=300" .
            "&select=id,paciente_nome,whatsapp,valor_total,valor_pago,status,origem,criado_em"
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
    }

    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };
    $url = function ( $params ) { return esc_url( tao_caixa_url( 'caixa-vendas', $params ) ); };
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F9FE; Vendas do Caixa</h1>
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

        <?php if ( empty( $vendas ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma venda encontrada<?php echo ( $status || $origem ) ? ' com esse filtro' : ''; ?>.</p>
            <p style="font-size:12px;color:#94a3b8">As vendas nascem automaticamente quando um card é fechado como Ganho (cruza para o Pós-vendas).</p>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Paciente</th>
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
            ?>
                <tr>
                    <td style="white-space:nowrap"><?php echo esc_html( $dt ); ?></td>
                    <td><strong><?php echo esc_html( $v['paciente_nome'] ?: '—' ); ?></strong></td>
                    <td style="color:#475569"><?php echo esc_html( $v['whatsapp'] ?: '—' ); ?></td>
                    <td style="text-align:center"><?php echo tao_caixa_venda_origem_badge( $v['origem'] ?? '' ); ?></td>
                    <td style="text-align:center"><?php echo tao_caixa_venda_status_badge( $v['status'] ?? '' ); ?></td>
                    <td style="text-align:right;font-weight:700"><?php echo $brl( $total ); ?></td>
                    <td style="text-align:right;color:#16a34a"><?php echo $brl( $pago ); ?></td>
                    <td style="text-align:right;color:<?php echo $receber > 0 ? '#1d4ed8' : '#94a3b8'; ?>"><?php echo $brl( $receber ); ?></td>
                    <td style="text-align:center">
                        <?php if ( in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true ) && $receber > 0 ) : ?>
                        <button type="button" class="taoc-btn taoc-btn-primary taoc-receber"
                                data-venda="<?php echo esc_attr( $v['id'] ); ?>"
                                data-paciente="<?php echo esc_attr( $v['paciente_nome'] ?: '—' ); ?>"
                                data-aberto="<?php echo esc_attr( number_format( $receber, 2, '.', '' ) ); ?>">Receber</button>
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

    <!-- Modal: Receber pagamento -->
    <div id="taoc-receber-modal" class="taoc-modal">
        <div class="taoc-overlay"></div>
        <div class="taoc-box">
            <h2>&#x1F4B3; Receber pagamento</h2>
            <p id="taoc-rec-info" style="font-size:13px;color:#475569;margin:0 0 14px"></p>
            <form id="taoc-receber-form" autocomplete="off">
                <input type="hidden" name="venda_id">
                <div class="taoc-field">
                    <label>Forma de pagamento *</label>
                    <select name="forma_pagamento_id" required>
                        <option value="">— Selecione —</option>
                        <?php foreach ( $formas as $f ) : ?>
                        <option value="<?php echo esc_attr( $f['id'] ); ?>"><?php echo esc_html( $f['nome'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="taoc-field taoc-field-inline">
                    <div style="flex:1">
                        <label>Parcelas</label>
                        <input type="number" name="parcelas" min="1" max="24" step="1" value="1">
                    </div>
                    <div style="flex:1">
                        <label>Valor (R$) *</label>
                        <input type="number" name="valor" min="0" step="0.01" required>
                    </div>
                </div>
                <div id="taoc-rec-preview" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;font-size:13px;margin:2px 0 12px;color:#334155"></div>
                <div class="taoc-actions">
                    <button type="submit" class="taoc-btn taoc-btn-primary">Confirmar recebimento</button>
                    <button type="button" class="taoc-btn" id="taoc-rec-cancel">Cancelar</button>
                </div>
                <p id="taoc-rec-msg" style="display:none;margin-top:10px;font-size:13px"></p>
            </form>
        </div>
    </div>

    <script>
    (function(){
        var FORMAS = <?php echo wp_json_encode( $formas ); ?>;
        var TAXAS  = <?php echo wp_json_encode( $taxas ); ?>;
        var C = window.taoCaixa || {};
        var modal = document.getElementById('taoc-receber-modal');
        if(!modal) return;
        var form = document.getElementById('taoc-receber-form');
        var info = document.getElementById('taoc-rec-info');
        var prev = document.getElementById('taoc-rec-preview');
        var msg  = document.getElementById('taoc-rec-msg');
        var fSel = form.forma_pagamento_id, pInp = form.parcelas, vInp = form.valor;

        function brl(v){ return 'R$ ' + (parseFloat(v)||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
        function resolve(){
            var fid = fSel.value, parc = parseInt(pInp.value||1), valor = parseFloat((vInp.value||'0').replace(',','.'))||0;
            var f = FORMAS.filter(function(x){ return x.id===fid; })[0];
            var taxa = f ? parseFloat(f.taxa_pct||0) : 0, prazo = f ? parseInt(f.prazo_recebimento_dias||0) : 0;
            var faixa = TAXAS.filter(function(t){ return t.forma_pagamento_id===fid && parc>=t.parcela_min && parc<=t.parcela_max; })
                             .sort(function(a,b){ return b.parcela_min-a.parcela_min; })[0];
            if(faixa){ taxa=parseFloat(faixa.taxa_pct); prazo=parseInt(faixa.prazo_recebimento_dias); }
            return { taxa:taxa, prazo:prazo, valor:valor };
        }
        function preview(){
            if(!fSel.value){ prev.innerHTML = '<span style="color:#94a3b8">Selecione a forma para ver taxa e líquido.</span>'; return; }
            var r = resolve(), vt = r.valor*r.taxa/100, liq = r.valor-vt;
            var d = new Date(Date.now() + r.prazo*86400000);
            var dd = ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear();
            prev.innerHTML = 'Taxa: <strong>'+r.taxa.toFixed(3).replace('.',',')+'%</strong> &nbsp;&middot;&nbsp; '
                + 'Líquido: <strong style="color:#16a34a">'+brl(liq)+'</strong> &nbsp;&middot;&nbsp; '
                + 'Recebe em <strong>'+r.prazo+' dia(s)</strong> ('+dd+')';
        }
        fSel.addEventListener('change', preview); pInp.addEventListener('input', preview); vInp.addEventListener('input', preview);

        var btns = document.querySelectorAll('.taoc-receber');
        for(var i=0;i<btns.length;i++){
            btns[i].addEventListener('click', function(){
                var aberto = parseFloat(this.getAttribute('data-aberto')||'0');
                form.venda_id.value = this.getAttribute('data-venda');
                info.innerHTML = '<strong>'+this.getAttribute('data-paciente')+'</strong> &middot; a receber: <strong>'+brl(aberto)+'</strong>';
                fSel.value=''; pInp.value=1; vInp.value=aberto.toFixed(2); msg.style.display='none';
                preview();
                modal.style.display='block';
            });
        }
        function close(){ modal.style.display='none'; }
        document.getElementById('taoc-rec-cancel').addEventListener('click', close);
        modal.querySelector('.taoc-overlay').addEventListener('click', close);

        form.addEventListener('submit', function(e){
            e.preventDefault();
            if(!fSel.value){ alert('Selecione a forma de pagamento'); return; }
            var sb = form.querySelector('button[type=submit]'); sb.disabled=true; sb.textContent='Processando...';
            var fd = new FormData();
            fd.append('action','tao_caixa_receber_venda'); fd.append('nonce',C.nonce);
            fd.append('venda_id',form.venda_id.value); fd.append('forma_pagamento_id',fSel.value);
            fd.append('parcelas',pInp.value||1); fd.append('valor',vInp.value||0);
            fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    sb.disabled=false; sb.textContent='Confirmar recebimento';
                    if(resp && resp.success){ location.reload(); }
                    else { msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='Erro: '+((resp&&resp.data)||'falha'); }
                })
                .catch(function(){ sb.disabled=false; sb.textContent='Confirmar recebimento'; msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='Falha de comunicação'; });
        });
    })();
    </script>
    <?php
}
