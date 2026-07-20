<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sessão de Caixa (Fase 2) — abertura, conferência e fechamento diário.
 */
function tao_caixa_page_sessao() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid = tao_caixa_cliente_id();
    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };

    $sessao   = $cid ? tao_caixa_sessao_aberta( $cid ) : null;
    $recebido = 0.0; $cash = 0.0; $n_recibos = 0; $movs = []; $mov_liq = 0.0;
    if ( $sessao ) {
        $sid = $sessao['id'];
        $rr = tao_caixa_api( "/caixa_recibos?sessao_id=eq.$sid&cliente_id=eq.$cid&status=neq.estornado&select=valor_total" );
        foreach ( ( $rr['ok'] ? ( $rr['data'] ?? [] ) : [] ) as $r ) { $recebido += (float) $r['valor_total']; $n_recibos++; }
        $cash = tao_caixa_dinheiro_da_sessao( $cid, $sid );
        $rm = tao_caixa_api( "/caixa_movimentos?sessao_id=eq.$sid&cliente_id=eq.$cid&order=criado_em.desc&select=tipo,valor,motivo,criado_em" );
        $movs = $rm['ok'] ? ( $rm['data'] ?? [] ) : [];
        foreach ( $movs as $m ) { $mov_liq += ( $m['tipo'] === 'sangria' ? -1 : 1 ) * (float) $m['valor']; }
        $mov_liq = round( $mov_liq, 2 );
    }
    $gaveta = $sessao ? round( (float) $sessao['saldo_inicial'] + $cash + $mov_liq, 2 ) : 0.0;

    // Últimas sessões fechadas
    $fechadas = [];
    if ( $cid ) {
        $rf = tao_caixa_api( "/caixa_sessoes?cliente_id=eq.$cid&status=eq.fechada&order=fechado_em.desc&limit=10&select=aberto_em,fechado_em,saldo_inicial,saldo_final_calculado,saldo_final_informado,divergencia" );
        $fechadas = $rf['ok'] ? ( $rf['data'] ?? [] ) : [];
    }
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar"><h1>&#x1F5C4; Sessão de Caixa</h1></div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php elseif ( ! $sessao ) : ?>

        <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;max-width:460px">
            <h2 style="margin:0 0 4px;font-size:16px">Caixa fechado</h2>
            <p style="font-size:13px;color:#64748b;margin:0 0 14px">Abra o caixa para começar a registrar recebimentos do dia.</p>
            <div class="taoc-field">
                <label>Saldo inicial / troco (R$)</label>
                <input type="number" id="sx-saldo" min="0" step="0.01" value="0,00" style="width:160px;padding:7px;border:1px solid #cbd5e1;border-radius:6px">
            </div>
            <div class="taoc-field" style="margin-top:8px">
                <label>Observações (opcional)</label>
                <input type="text" id="sx-obs" style="width:100%;padding:7px;border:1px solid #cbd5e1;border-radius:6px">
            </div>
            <div class="taoc-actions" style="margin-top:14px">
                <button type="button" id="sx-abrir" class="taoc-btn taoc-btn-primary">Abrir caixa</button>
            </div>
            <p id="sx-msg" style="display:none;margin-top:10px;font-size:13px"></p>
        </div>

        <?php else : ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:18px">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Aberto desde</div>
                <strong style="font-size:16px"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $sessao['aberto_em'] ) ) ); ?></strong>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Saldo inicial</div>
                <strong style="font-size:20px"><?php echo $brl( $sessao['saldo_inicial'] ); ?></strong>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Recebido (<?php echo $n_recibos; ?> recibos)</div>
                <strong style="font-size:20px;color:#16a34a"><?php echo $brl( $recebido ); ?></strong>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Dinheiro recebido</div>
                <strong style="font-size:20px"><?php echo $brl( $cash ); ?></strong>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Aportes − sangrias</div>
                <strong style="font-size:20px;color:<?php echo $mov_liq < 0 ? '#dc2626' : '#0f172a'; ?>"><?php echo $brl( $mov_liq ); ?></strong>
            </div>
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Esperado na gaveta</div>
                <strong style="font-size:20px;color:#1d4ed8"><?php echo $brl( $gaveta ); ?></strong>
                <div style="font-size:11px;color:#94a3b8">inicial + dinheiro + aportes − sangrias</div>
            </div>
        </div>

        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start">
        <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;flex:1 1 320px;max-width:460px">
            <h2 style="margin:0 0 4px;font-size:16px">Aporte / Sangria</h2>
            <p style="font-size:13px;color:#64748b;margin:0 0 14px">Entrada (troco, reforço) ou retirada de dinheiro da gaveta. Lançou errado? Lance o movimento inverso.</p>
            <div class="taoc-field">
                <label>Tipo</label>
                <select id="mv-tipo" style="padding:7px;border:1px solid #cbd5e1;border-radius:6px">
                    <option value="aporte">Aporte (entrada)</option>
                    <option value="sangria">Sangria (retirada)</option>
                </select>
            </div>
            <div class="taoc-field" style="margin-top:8px">
                <label>Valor (R$)</label>
                <input type="number" id="mv-valor" min="0" step="0.01" placeholder="0,00" style="width:160px;padding:7px;border:1px solid #cbd5e1;border-radius:6px">
            </div>
            <div class="taoc-field" style="margin-top:8px">
                <label>Motivo <span id="mv-motivo-req" style="color:#94a3b8">(obrigatório na sangria)</span></label>
                <input type="text" id="mv-motivo" placeholder="ex.: depósito no banco, troco, pagamento motoboy" style="width:100%;padding:7px;border:1px solid #cbd5e1;border-radius:6px">
            </div>
            <div class="taoc-actions" style="margin-top:14px">
                <button type="button" id="mv-lancar" class="taoc-btn taoc-btn-primary">Lançar</button>
            </div>
            <p id="mv-msg" style="display:none;margin-top:10px;font-size:13px"></p>
            <?php if ( $movs ) : ?>
            <table class="taoc-table" style="margin-top:16px">
                <thead><tr><th>Hora</th><th>Tipo</th><th style="text-align:right">Valor</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php foreach ( $movs as $m ) : $sang = $m['tipo'] === 'sangria'; ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( 'd/m H:i', strtotime( $m['criado_em'] ) ) ); ?></td>
                        <td style="color:<?php echo $sang ? '#dc2626' : '#16a34a'; ?>;font-weight:600"><?php echo $sang ? 'Sangria' : 'Aporte'; ?></td>
                        <td style="text-align:right"><?php echo ( $sang ? '−' : '+' ) . ' ' . $brl( $m['valor'] ); ?></td>
                        <td><?php echo esc_html( $m['motivo'] ?? '—' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;flex:1 1 320px;max-width:460px">
            <h2 style="margin:0 0 12px;font-size:16px">Fechar caixa</h2>
            <div class="taoc-field">
                <label>Valor contado na gaveta (R$)</label>
                <input type="number" id="sx-contado" min="0" step="0.01" placeholder="0,00" style="width:160px;padding:7px;border:1px solid #cbd5e1;border-radius:6px">
            </div>
            <p style="font-size:12px;color:#64748b;margin:8px 0 0">Esperado: <strong><?php echo $brl( $gaveta ); ?></strong> — a diferença (sobra/falta) será registrada.</p>
            <div class="taoc-actions" style="margin-top:14px">
                <button type="button" id="sx-fechar" class="taoc-btn taoc-btn-primary">Fechar caixa</button>
            </div>
            <p id="sx-msg" style="display:none;margin-top:10px;font-size:13px"></p>
        </div>
        </div>

        <?php endif; ?>

        <?php if ( $fechadas ) : ?>
        <h2 style="font-size:15px;margin:24px 0 8px">Últimos fechamentos</h2>
        <table class="taoc-table">
            <thead><tr><th>Abertura</th><th>Fechamento</th><th style="text-align:right">Inicial</th><th style="text-align:right">Calculado</th><th style="text-align:right">Informado</th><th style="text-align:right">Diferença</th></tr></thead>
            <tbody>
            <?php foreach ( $fechadas as $f ) :
                $d = (float) ( $f['divergencia'] ?? 0 );
                $cor = abs( $d ) < 0.005 ? '#16a34a' : ( $d < 0 ? '#dc2626' : '#92400e' ); ?>
                <tr>
                    <td><?php echo esc_html( $f['aberto_em'] ? date_i18n( 'd/m H:i', strtotime( $f['aberto_em'] ) ) : '—' ); ?></td>
                    <td><?php echo esc_html( $f['fechado_em'] ? date_i18n( 'd/m H:i', strtotime( $f['fechado_em'] ) ) : '—' ); ?></td>
                    <td style="text-align:right"><?php echo $brl( $f['saldo_inicial'] ); ?></td>
                    <td style="text-align:right"><?php echo $brl( $f['saldo_final_calculado'] ); ?></td>
                    <td style="text-align:right"><?php echo $brl( $f['saldo_final_informado'] ); ?></td>
                    <td style="text-align:right;color:<?php echo $cor; ?>;font-weight:600"><?php echo $brl( $d ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        var C = window.taoCaixa || {};
        function post(action, data, btn){
            btn.disabled=true; var t=btn.textContent; btn.textContent='Processando...';
            var fd=new FormData(); fd.append('action',action); fd.append('nonce',C.nonce);
            for(var k in data) fd.append(k,data[k]);
            var msg=document.getElementById('sx-msg');
            fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(resp){
                if(resp&&resp.success){ location.reload(); }
                else { btn.disabled=false; btn.textContent=t; if(msg){ msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='Erro: '+((resp&&resp.data)||'falha'); } }
            }).catch(function(){ btn.disabled=false; btn.textContent=t; if(msg){ msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='Falha de comunicação'; } });
        }
        var ab=document.getElementById('sx-abrir');
        if(ab) ab.addEventListener('click',function(){ post('tao_caixa_abrir_sessao',{ saldo_inicial:document.getElementById('sx-saldo').value, observacoes:document.getElementById('sx-obs').value }, ab); });
        var mv=document.getElementById('mv-lancar');
        if(mv) mv.addEventListener('click',function(){
            var tipo=document.getElementById('mv-tipo').value,
                val=document.getElementById('mv-valor').value,
                mot=document.getElementById('mv-motivo').value.trim(),
                msg=document.getElementById('mv-msg');
            function err(t){ msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent=t; }
            if(!val||parseFloat(val.replace(',','.'))<=0) return err('Informe um valor maior que zero.');
            if(tipo==='sangria'&&!mot) return err('Informe o motivo da sangria.');
            if(tipo==='sangria'&&!confirm('Confirmar sangria de R$ '+val+'?')) return;
            var fd=new FormData(); fd.append('action','tao_caixa_lancar_movimento'); fd.append('nonce',C.nonce);
            fd.append('tipo',tipo); fd.append('valor',val); fd.append('motivo',mot);
            mv.disabled=true; mv.textContent='Lançando...';
            fetch(C.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(resp){
                if(resp&&resp.success){ location.reload(); }
                else { mv.disabled=false; mv.textContent='Lançar'; err('Erro: '+((resp&&resp.data)||'falha')); }
            }).catch(function(){ mv.disabled=false; mv.textContent='Lançar'; err('Falha de comunicação'); });
        });
        var fc=document.getElementById('sx-fechar');
        if(fc) fc.addEventListener('click',function(){
            var v=document.getElementById('sx-contado').value;
            if(v===''){ alert('Informe o valor contado.'); return; }
            if(!confirm('Fechar o caixa agora?')) return;
            post('tao_caixa_fechar_sessao',{ saldo_final_informado:v }, fc);
        });
    })();
    </script>
    <?php
}
