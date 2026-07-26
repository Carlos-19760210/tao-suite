<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tela SNGPC — central de fechamento dos controlados dentro da plataforma.
 * Hoje opera em modo SOMBRA: importa o XML do sistema atual, resume/valida e
 * mantém o histórico. Geração nativa e transmissão entram conforme a produção migra
 * e a flag de transmissão é ativada.
 */
function tao_caixa_page_sngpc() {
    if ( ! function_exists( 'tao_caixa_pode_operar' ) || ! tao_caixa_pode_operar() ) {
        echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return;
    }
    if ( function_exists( 'tao_caixa_assets' ) ) tao_caixa_assets();

    $cid   = tao_caixa_cliente_id();
    $nonce = wp_create_nonce( 'tao_caixa_nonce' );
    $ajax  = admin_url( 'admin-ajax.php' );
    $cfg   = tao_caixa_sngpc_config();

    // Período padrão: ciclo de 5 dias terminando hoje.
    $hoje = current_time( 'Y-m-d' );
    $ini  = date( 'Y-m-d', strtotime( $hoje . ' -4 days' ) );

    $rf = tao_caixa_api( "/caixa_sngpc_fechamentos?cliente_id=eq.$cid&select=id,data_inicio,data_fim,origem,qtd_saidas,qtd_perdas,qtd_entradas,qtd_medicamentos,status,criado_em&order=data_inicio.desc&limit=50" );
    $migr_ok = $rf['ok'];
    $fechs   = $migr_ok ? ( $rf['data'] ?? [] ) : [];

    $on  = $cfg && ! empty( $cfg['ativo'] ) && ! empty( $cfg['usuario'] );
    $amb = $cfg['ambiente'] ?? 'homologacao';
    $cor = $on ? ( $amb === 'producao' ? '#166534' : '#92400e' ) : '#991b1b';
    $bg  = $on ? ( $amb === 'producao' ? '#dcfce7' : '#fef3c7' ) : '#fee2e2';
    $txt = $on ? ( 'Transmissão ATIVA — ' . strtoupper( $amb ) ) : 'Transmissão DESLIGADA (modo sombra)';
    ?>
    <div class="wrap">
        <h1 style="margin-bottom:4px">&#x1F48A; SNGPC — Controlados</h1>
        <p style="margin:0 0 12px;color:#64748b;font-size:13px">Fechamento e escrituração dos controlados (mensagemSNGPC). Modo sombra: importe o XML do sistema atual para conferir, validar e guardar o histórico na plataforma.</p>

        <?php if ( ! $migr_ok ) : ?>
            <div class="notice notice-error"><p><b>Migration pendente.</b> Rode <code>migration_sngpc_v1.sql</code> no Supabase para habilitar a tela.</p></div>
            </div><?php return; endif; ?>

        <div style="display:inline-block;margin-bottom:14px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;background:<?php echo $bg; ?>;color:<?php echo $cor; ?>">
            <?php echo esc_html( $txt ); ?>
        </div>

        <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start">

            <!-- Importar XML (modo sombra) -->
            <div style="flex:1;min-width:340px;max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <h2 style="margin:0 0 8px;font-size:15px">Importar XML do sistema atual</h2>
                <p style="margin:0 0 10px;color:#64748b;font-size:12px">Selecione o arquivo <code>mensagemSNGPC</code> (.xml) gerado no sistema atual. Ele será lido, resumido e guardado como fechamento do período (sem transmitir).</p>
                <input type="file" id="sngpc-file" accept=".xml,text/xml" style="margin-bottom:10px">
                <div id="sngpc-resumo" style="display:none;margin:10px 0;padding:10px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px"></div>
                <button class="button button-primary" id="sngpc-importar" disabled>Importar fechamento</button>
                <div id="sngpc-msg" style="margin-top:8px;font-size:12px"></div>
            </div>

            <!-- O que o TAO já registra -->
            <div style="flex:1;min-width:300px;max-width:460px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <h2 style="margin:0 0 8px;font-size:15px">Geração nativa (em preparação)</h2>
                <p style="margin:0 0 10px;color:#64748b;font-size:12px">O TAO já registra as <b>entradas</b> de insumo pelo <a href="<?php echo esc_url( admin_url( 'admin.php?page=tao-caixa-recebimento' ) ); ?>">Recebimento de NF</a>. As <b>saídas</b> (venda ao consumidor e consumo na manipulação) passam a alimentar o SNGPC nativo conforme a produção migra para o TAO. Até lá, use o modo sombra.</p>
                <div style="font-size:12px;color:#334155">
                    <b>Ciclo sugerido:</b> a cada 5 dias.<br>
                    <b>Período atual:</b> <?php echo esc_html( date( 'd/m/Y', strtotime( $ini ) ) . ' a ' . date( 'd/m/Y', strtotime( $hoje ) ) ); ?>
                </div>
            </div>
        </div>

        <!-- Histórico -->
        <h2 style="margin:20px 0 8px;font-size:15px">Fechamentos</h2>
        <table class="widefat striped" style="max-width:980px">
            <thead><tr>
                <th>Período</th><th>Origem</th><th>Saídas</th><th>Perdas</th><th>Entradas</th><th>Medic.</th><th>Status</th><th style="width:120px">Ações</th>
            </tr></thead>
            <tbody>
            <?php if ( ! $fechs ) : ?>
                <tr><td colspan="8" style="color:#94a3b8">Nenhum fechamento ainda. Importe o primeiro XML acima.</td></tr>
            <?php else : foreach ( $fechs as $f ) :
                $badges = [ 'rascunho' => [ 'Rascunho', '#f1f5f9', '#64748b' ], 'validado' => [ 'Validado', '#dbeafe', '#1d4ed8' ], 'transmitido' => [ 'Transmitido', '#dcfce7', '#166534' ] ];
                $b = $badges[ $f['status'] ?? '' ] ?? [ $f['status'], '#f1f5f9', '#64748b' ];
            ?>
                <tr>
                    <td><?php echo esc_html( date( 'd/m/Y', strtotime( $f['data_inicio'] ) ) . ' – ' . date( 'd/m/Y', strtotime( $f['data_fim'] ) ) ); ?></td>
                    <td style="text-transform:capitalize"><?php echo esc_html( $f['origem'] ); ?></td>
                    <td><?php echo (int) $f['qtd_saidas']; ?></td>
                    <td><?php echo (int) $f['qtd_perdas']; ?></td>
                    <td><?php echo (int) $f['qtd_entradas']; ?></td>
                    <td><?php echo (int) $f['qtd_medicamentos']; ?></td>
                    <td><span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:<?php echo $b[1]; ?>;color:<?php echo $b[2]; ?>"><?php echo esc_html( $b[0] ); ?></span></td>
                    <td><button class="button button-small sngpc-baixar" data-id="<?php echo esc_attr( $f['id'] ); ?>">Baixar XML</button></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    (function(){
        var ajax=<?php echo wp_json_encode( $ajax ); ?>, nonce=<?php echo wp_json_encode( $nonce ); ?>;
        var xmlAtual=null;
        function post(action,extra,cb){
            var body='action='+action+'&nonce='+encodeURIComponent(nonce)+(extra||'');
            fetch(ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
            .then(function(r){return r.json();}).then(cb).catch(function(){cb({success:false,data:'falha de rede'});});
        }
        var file=document.getElementById('sngpc-file'), resumo=document.getElementById('sngpc-resumo'),
            btn=document.getElementById('sngpc-importar'), msg=document.getElementById('sngpc-msg');
        file.addEventListener('change',function(){
            var f=file.files[0]; resumo.style.display='none'; btn.disabled=true; msg.textContent='';
            if(!f) return;
            var rd=new FileReader();
            rd.onload=function(){ xmlAtual=rd.result; btn.disabled=false;
                resumo.style.display='block'; resumo.innerHTML='Arquivo pronto: <b>'+f.name+'</b> ('+Math.round(f.size/1024)+' KB). Clique em Importar para validar e resumir.'; };
            rd.readAsText(f,'ISO-8859-1');
        });
        btn.addEventListener('click',function(){
            if(!xmlAtual) return;
            btn.disabled=true; btn.textContent='Importando…'; msg.textContent='';
            post('tao_caixa_sngpc_importar','&xml='+encodeURIComponent(xmlAtual),function(r){
                btn.textContent='Importar fechamento';
                if(r.success){
                    var s=r.data.resumo;
                    resumo.style.display='block';
                    resumo.innerHTML='<b>Período '+s.data_inicio+' a '+s.data_fim+'</b> · CNPJ '+(s.cnpj||'—')+'<br>'+
                        'Saídas: <b>'+s.saidas+'</b> · Perdas: <b>'+s.perdas+'</b> · Entradas: <b>'+s.entradas+'</b> · Medicamentos: <b>'+s.medicamentos+'</b>';
                    msg.style.color='#166534'; msg.textContent='Fechamento importado e validado.';
                    setTimeout(function(){location.reload();},1200);
                } else { btn.disabled=false; msg.style.color='#991b1b'; msg.textContent='Erro: '+(r.data||''); }
            });
        });
        document.addEventListener('click',function(e){
            if(!e.target.classList.contains('sngpc-baixar')) return;
            var id=e.target.dataset.id, b=e.target; b.disabled=true;
            post('tao_caixa_sngpc_baixar','&id='+encodeURIComponent(id),function(r){
                b.disabled=false;
                if(r.success){
                    var blob=new Blob([r.data.xml],{type:'application/xml'}), u=URL.createObjectURL(blob);
                    var a=document.createElement('a'); a.href=u; a.download=r.data.nome; document.body.appendChild(a); a.click();
                    document.body.removeChild(a); URL.revokeObjectURL(u);
                } else { alert('Erro: '+(r.data||'')); }
            });
        });
    })();
    </script>
    <?php
}
