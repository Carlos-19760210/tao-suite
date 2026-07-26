<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tela Fiscal (NFC-e) — emissão sob demanda / em lote.
 * Lista vendas e permite Emitir / Consultar / Cancelar via gateway (Focus).
 * Segura: inerte enquanto o emitente não estiver configurado; avisa se faltar migration.
 */
function tao_caixa_page_fiscal() {
    if ( ! function_exists( 'tao_caixa_pode_operar' ) || ! tao_caixa_pode_operar() ) {
        echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return;
    }
    if ( function_exists( 'tao_caixa_assets' ) ) tao_caixa_assets();

    $cid   = tao_caixa_cliente_id();
    $nonce = wp_create_nonce( 'tao_caixa_nonce' );
    $ajax  = admin_url( 'admin-ajax.php' );
    $cfg   = tao_caixa_fiscal_config();

    // Testa se a migration rodou (colunas nfce_* existem).
    $rv = tao_caixa_api( "/caixa_vendas?cliente_id=eq.$cid&select=id,card_id,cliente_nome,valor_total,status,nfce_status,nfce_chave,nfce_danfe_url,nfce_erro,criado_em&order=criado_em.desc&limit=100" );
    $migr_ok = $rv['ok'];
    $vendas  = $migr_ok ? ( $rv['data'] ?? [] ) : [];
    ?>
    <div class="wrap">
        <h1 style="margin-bottom:4px">&#x1F9FE; Fiscal — NFC-e</h1>
        <p style="margin:0 0 12px;color:#64748b;font-size:13px">Emissão de cupom fiscal (NFC-e / modelo 65) via gateway. Emita sob demanda ou em lote.</p>

        <?php if ( ! $migr_ok ) : ?>
            <div class="notice notice-error"><p><b>Migrations fiscais pendentes.</b> Rode <code>migration_nfce_v1.sql</code> (e <code>migration_fiscal_produtos_v1.sql</code>) no Supabase para habilitar a tela.</p></div>
        <?php elseif ( ! $cfg ) : ?>
            <div class="notice notice-warning"><p><b>Emitente não configurado.</b> Cadastre CNPJ/IE/regime, o <b>token do Focus</b> e ligue <code>ativo</code> em <code>caixa_emitente_fiscal</code> (homologação primeiro).</p></div>
        <?php else :
            $amb = $cfg['ambiente'] ?? 'homologacao';
            $on  = ! empty( $cfg['ativo'] ) && ! empty( $cfg['focus_token'] );
            $cor = $on ? ( $amb === 'producao' ? '#166534' : '#92400e' ) : '#991b1b';
            $bg  = $on ? ( $amb === 'producao' ? '#dcfce7' : '#fef3c7' ) : '#fee2e2';
            $txt = ! $on ? 'DESLIGADO (configure token + ative)' : ( 'ATIVO — ambiente ' . strtoupper( $amb ) );
        ?>
            <div style="display:inline-block;margin-bottom:12px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;background:<?php echo $bg; ?>;color:<?php echo $cor; ?>">
                Emissão: <?php echo esc_html( $txt ); ?> · gateway <?php echo esc_html( $cfg['gateway'] ?? 'focus' ); ?>
            </div>
        <?php endif; ?>

        <?php if ( $migr_ok ) : ?>
        <table class="widefat striped" style="max-width:1100px">
            <thead><tr>
                <th>Venda</th><th>Cliente</th><th>Valor</th><th>Pgto</th><th>NFC-e</th><th style="width:260px">Ações</th>
            </tr></thead>
            <tbody>
            <?php if ( ! $vendas ) : ?>
                <tr><td colspan="6" style="color:#94a3b8">Nenhuma venda no período.</td></tr>
            <?php else : foreach ( $vendas as $v ) :
                $st = $v['nfce_status'] ?? '';
                $badges = [
                    ''            => [ '— não emitida', '#f1f5f9', '#64748b' ],
                    'processando' => [ 'Processando',   '#dbeafe', '#1d4ed8' ],
                    'autorizada'  => [ 'Autorizada',    '#dcfce7', '#166534' ],
                    'rejeitada'   => [ 'Rejeitada',     '#fee2e2', '#991b1b' ],
                    'cancelada'   => [ 'Cancelada',     '#f1f5f9', '#64748b' ],
                ];
                $b = $badges[ $st ] ?? [ $st, '#f1f5f9', '#64748b' ];
                $vid = esc_attr( $v['id'] );
            ?>
                <tr data-venda="<?php echo $vid; ?>">
                    <td style="font-family:monospace;font-size:11px"><?php echo esc_html( substr( $v['id'], 0, 8 ) ); ?></td>
                    <td><?php echo esc_html( $v['cliente_nome'] ?: '—' ); ?></td>
                    <td>R$ <?php echo number_format( (float) ( $v['valor_total'] ?? 0 ), 2, ',', '.' ); ?></td>
                    <td><?php echo esc_html( $v['status'] ?? '' ); ?></td>
                    <td>
                        <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:<?php echo $b[1]; ?>;color:<?php echo $b[2]; ?>"><?php echo esc_html( $b[0] ); ?></span>
                        <?php if ( ! empty( $v['nfce_erro'] ) ) : ?><div style="font-size:11px;color:#991b1b;margin-top:3px"><?php echo esc_html( mb_substr( $v['nfce_erro'], 0, 80 ) ); ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if ( in_array( $st, [ '', 'rejeitada' ], true ) ) : ?>
                            <button class="button button-primary button-small fisc-emitir">Emitir NFC-e</button>
                        <?php elseif ( $st === 'processando' ) : ?>
                            <button class="button button-small fisc-consultar">Consultar</button>
                        <?php elseif ( $st === 'autorizada' ) : ?>
                            <?php if ( ! empty( $v['nfce_danfe_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $v['nfce_danfe_url'] ); ?>" target="_blank">DANFE</a><?php endif; ?>
                            <button class="button button-small fisc-cancelar" style="color:#dc2626">Cancelar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        var ajax=<?php echo wp_json_encode( $ajax ); ?>, nonce=<?php echo wp_json_encode( $nonce ); ?>;
        function post(action,vid,extra,cb){
            var body='action='+action+'&nonce='+encodeURIComponent(nonce)+'&venda_id='+encodeURIComponent(vid)+(extra||'');
            fetch(ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
            .then(function(r){return r.json();}).then(cb).catch(function(){cb({success:false,data:'falha de rede'});});
        }
        function row(el){ return el.closest('tr'); }
        document.addEventListener('click',function(e){
            var t=e.target;
            if(t.classList.contains('fisc-emitir')){
                t.disabled=true; t.textContent='Emitindo…';
                post('tao_caixa_nfce_emitir', row(t).dataset.venda, '', function(r){
                    if(r.success){ location.reload(); } else { alert('Erro: '+(r.data||'')); t.disabled=false; t.textContent='Emitir NFC-e'; }
                });
            } else if(t.classList.contains('fisc-consultar')){
                t.disabled=true; post('tao_caixa_nfce_consultar', row(t).dataset.venda, '', function(){ location.reload(); });
            } else if(t.classList.contains('fisc-cancelar')){
                var m=prompt('Justificativa do cancelamento (mín. 15 caracteres):','');
                if(m===null) return;
                post('tao_caixa_nfce_cancelar', row(t).dataset.venda, '&motivo='+encodeURIComponent(m), function(r){
                    if(r.success){ location.reload(); } else { alert('Erro: '+(r.data||'')); }
                });
            }
        });
    })();
    </script>
    <?php
}
