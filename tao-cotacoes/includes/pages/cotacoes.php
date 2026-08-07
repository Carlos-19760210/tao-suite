<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_cot_status_label( $s ) {
    $map = [
        'rascunho'  => 'Rascunho',
        'enviada'   => 'Enviada',
        'recebendo' => 'Recebendo propostas',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
        'pendente'  => 'Pendente',
        'enviado'   => 'Enviado',
        'erro'      => 'Erro no envio',
        'respondeu' => 'Respondeu',
        'processado'=> 'Processado',
    ];
    return $map[ $s ] ?? $s;
}

function tao_cot_dt( $iso ) {
    if ( ! $iso ) return '—';
    return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $iso ) ), 'd/m/Y H:i' );
}

function tao_cotacoes_page_lista() {
    if ( ! tao_cot_pode() ) { echo '<div class="wrap"><p>Sem permissão para operar cotações.</p></div>'; return; }
    tao_cot_assets();

    $cot_id = sanitize_text_field( $_GET['cot'] ?? '' );
    if ( $cot_id ) { tao_cotacoes_render_view( $cot_id ); return; }

    $cid  = tao_cot_cliente_id();
    $rows = [];
    if ( $cid ) {
        $r    = tao_cot_api( "/cotacoes?cliente_id=eq.$cid&order=criado_em.desc&limit=100" );
        $rows = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    }
    ?>
    <div class="wrap taocot-wrap">
        <div class="taocot-bar">
            <h1>&#x1F4CB; Cotações de Compra</h1>
            <div>
                <a class="taocot-btn" href="<?php echo esc_url( tao_cot_url( 'cotacoes-fornecedores' ) ); ?>">Fornecedores</a>
                <a class="taocot-btn taocot-btn-primary" href="<?php echo esc_url( tao_cot_url( 'cotacoes-nova' ) ); ?>">+ Nova Cotação</a>
            </div>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php elseif ( empty( $rows ) ) : ?>
        <div class="taocot-empty">
            <p>Nenhuma cotação ainda. Comece subindo a planilha de estoque mínimo ou montando a lista manualmente.</p>
            <a class="taocot-btn taocot-btn-primary" href="<?php echo esc_url( tao_cot_url( 'cotacoes-nova' ) ); ?>">+ Criar primeira cotação</a>
        </div>
        <?php else : ?>
        <div class="taocot-tscroll">
        <table class="taocot-table">
            <thead>
                <tr><th>Nº</th><th>Título</th><th style="text-align:center">Status</th><th>Criada em</th><th>Enviada em</th><th style="width:110px"></th></tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $c ) :
                $url = tao_cot_url( 'cotacoes', [ 'cot' => $c['id'] ] );
            ?>
                <tr>
                    <td><strong>#<?php echo esc_html( $c['numero'] ?? '' ); ?></strong></td>
                    <td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $c['titulo'] ?? '' ); ?></a></td>
                    <td style="text-align:center"><span class="taocot-pill <?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( tao_cot_status_label( $c['status'] ) ); ?></span></td>
                    <td><?php echo esc_html( tao_cot_dt( $c['criado_em'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( tao_cot_dt( $c['enviado_em'] ?? '' ) ); ?></td>
                    <td><a class="taocot-btn" href="<?php echo esc_url( $url ); ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Detalhe / acompanhamento de uma cotação.
 */
function tao_cotacoes_render_view( $cot_id ) {
    $cid = tao_cot_cliente_id();
    $rc  = $cid ? tao_cot_api( "/cotacoes?id=eq.$cot_id&cliente_id=eq.$cid" ) : [ 'ok' => false, 'data' => [] ];
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) {
        echo '<div class="wrap taocot-wrap"><p>Cotação não encontrada.</p></div>';
        return;
    }
    $cot = $rc['data'][0];

    $rit   = tao_cot_api( "/cotacao_itens?cotacao_id=eq.$cot_id&order=urgente.desc,descricao.asc&limit=500" );
    $itens = $rit['ok'] ? $rit['data'] : [];

    $rf    = tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cot_id&select=*,fornecedores(nome,whatsapp,contato)&order=enviado_em.asc" );
    $parts = $rf['ok'] ? $rf['data'] : [];
    $unread = tao_cot_unread_por_fornecedor( $cid, array_map( fn( $p ) => $p['fornecedor_id'], $parts ) );

    $ri        = tao_cot_api( "/crm_instancias?id=eq.{$cot['instancia_id']}&select=nome,evolution_instancia" );
    $inst_nome = ( $ri['ok'] && ! empty( $ri['data'] ) ) ? ( $ri['data'][0]['nome'] ?: $ri['data'][0]['evolution_instancia'] ) : '—';

    $aberta   = ! in_array( $cot['status'], [ 'concluida', 'cancelada' ], true );
    $pend_env = count( array_filter( $parts, fn( $p ) => in_array( $p['status'], [ 'pendente', 'erro' ], true ) ) );
    ?>
    <div class="wrap taocot-wrap">
        <div class="taocot-bar">
            <h1>&#x1F4CB; Cotação #<?php echo esc_html( $cot['numero'] ?? '' ); ?> — <?php echo esc_html( $cot['titulo'] ?? '' ); ?></h1>
            <a class="taocot-btn" href="<?php echo esc_url( tao_cot_url( 'cotacoes' ) ); ?>">&larr; Voltar</a>
        </div>

        <div class="taocot-card">
            <span class="taocot-pill <?php echo esc_attr( $cot['status'] ); ?>"><?php echo esc_html( tao_cot_status_label( $cot['status'] ) ); ?></span>
            &nbsp; <span class="taocot-muted">Instância: <strong><?php echo esc_html( $inst_nome ); ?></strong>
            &nbsp;·&nbsp; Criada: <?php echo esc_html( tao_cot_dt( $cot['criado_em'] ) ); ?>
            <?php if ( ! empty( $cot['enviado_em'] ) ) : ?>&nbsp;·&nbsp; Enviada: <?php echo esc_html( tao_cot_dt( $cot['enviado_em'] ) ); ?><?php endif; ?>
            </span>
            <div class="taocot-actions">
                <?php if ( $aberta && $pend_env > 0 ) : ?>
                <button class="taocot-btn taocot-btn-primary" id="taocot-btn-enviar">&#x1F4E4; Enviar aos fornecedores (<?php echo $pend_env; ?>)</button>
                <?php endif; ?>
                <?php if ( $aberta ) : ?>
                <button class="taocot-btn" id="taocot-btn-concluir">✔ Marcar concluída</button>
                <button class="taocot-btn taocot-btn-danger" id="taocot-btn-cancelar">Cancelar cotação</button>
                <?php endif; ?>
                <?php if ( $cot['status'] === 'rascunho' ) : ?>
                <button class="taocot-btn taocot-btn-danger" id="taocot-btn-excluir">Excluir rascunho</button>
                <?php endif; ?>
            </div>
            <div class="taocot-status-msg" id="taocot-view-msg"></div>
        </div>

        <div class="taocot-card">
            <h2>Fornecedores (<?php echo count( $parts ); ?>)</h2>
            <?php if ( empty( $parts ) ) : ?><p class="taocot-muted">Nenhum fornecedor vinculado.</p>
            <?php else : ?>
            <div class="taocot-tscroll">
            <table class="taocot-table">
                <thead><tr><th>Fornecedor</th><th>WhatsApp</th><th style="text-align:center">Status</th><th>Enviado</th><th>Respondeu</th><th></th></tr></thead>
                <tbody>
                <?php foreach ( $parts as $p ) : $f = $p['fornecedores'] ?? []; ?>
                    <tr>
                        <td><strong><?php echo esc_html( $f['nome'] ?? '' ); ?></strong>
                            <?php if ( ! empty( $f['contato'] ) ) : ?><span class="taocot-muted"> · <?php echo esc_html( $f['contato'] ); ?></span><?php endif; ?>
                            <?php if ( ! empty( $p['erro'] ) ) : ?><div class="taocot-muted" style="color:#b91c1c"><?php echo esc_html( $p['erro'] ); ?></div><?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $f['whatsapp'] ?? '' ); ?></td>
                        <td style="text-align:center"><span class="taocot-pill <?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( tao_cot_status_label( $p['status'] ) ); ?></span></td>
                        <td><?php echo esc_html( tao_cot_dt( $p['enviado_em'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( tao_cot_dt( $p['respondeu_em'] ?? '' ) ); ?></td>
                        <td style="white-space:nowrap">
                            <button class="taocot-btn" data-cot-chat
                                data-fid="<?php echo esc_attr( $p['fornecedor_id'] ); ?>"
                                data-nome="<?php echo esc_attr( $f['nome'] ?? '' ); ?>"
                                data-cot="<?php echo esc_attr( $cot_id ); ?>">&#x1F4AC; Conversa
                                <?php $u = $unread[ $p['fornecedor_id'] ] ?? 0; if ( $u ) : ?><span class="taocot-badge"><?php echo $u; ?></span><?php endif; ?>
                            </button>
                            <span class="taocot-prop-wrap" style="position:relative;display:inline-block">
                                <button type="button" class="taocot-btn taocot-prop-toggle" data-fid="<?php echo esc_attr( $p['fornecedor_id'] ); ?>">&#x1F4E5; Proposta &#x25BE;</button>
                                <div class="taocot-prop-menu" style="display:none;position:absolute;right:0;top:calc(100% + 2px);z-index:45;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 22px rgba(0,0,0,.14);min-width:210px;overflow:hidden;text-align:left">
                                    <button type="button" class="taocot-prop-upload" data-fid="<?php echo esc_attr( $p['fornecedor_id'] ); ?>" style="display:block;width:100%;text-align:left;border:0;background:transparent;padding:10px 13px;font-size:13px;cursor:pointer;line-height:1.3">&#x1F4CE; Subir arquivo (PDF/foto)<br><span style="font-size:11px;color:#94a3b8">a IA lê e vira preço</span></button>
                                    <button type="button" class="taocot-prop-manual" data-fid="<?php echo esc_attr( $p['fornecedor_id'] ); ?>" data-nome="<?php echo esc_attr( $f['nome'] ?? '' ); ?>" style="display:block;width:100%;text-align:left;border:0;border-top:1px solid #f1f5f9;background:transparent;padding:10px 13px;font-size:13px;cursor:pointer">&#x2328;&#xFE0F; Digitar manual</button>
                                </div>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="taocot-card">
            <h2>Itens (<?php echo count( $itens ); ?>)</h2>
            <div class="taocot-tscroll">
            <table class="taocot-table">
                <thead><tr><th style="width:30px">⭐</th><th>Item</th><th>Cód. FC</th><th style="text-align:right">Qtde</th><th>Un.</th><th style="text-align:right">Últ. pago</th><th>Origem</th></tr></thead>
                <tbody>
                <?php foreach ( $itens as $it ) : ?>
                    <tr class="<?php echo ! empty( $it['urgente'] ) ? 'taocot-urgente' : ''; ?>">
                        <td><?php echo ! empty( $it['urgente'] ) ? '⭐' : ''; ?></td>
                        <td><strong><?php echo esc_html( $it['descricao'] ); ?></strong>
                            <?php if ( empty( $it['ativo_id'] ) ) : ?><span class="taocot-muted">(item livre)</span><?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $it['codigo_fc'] ?? '' ); ?></td>
                        <td style="text-align:right"><?php echo esc_html( number_format( (float) ( $it['qtd'] ?? 0 ), 2, ',', '.' ) ); ?></td>
                        <td><?php echo esc_html( $it['unidade'] ?? '' ); ?></td>
                        <td style="text-align:right"><?php echo $it['ult_preco_pago'] !== null ? 'R$ ' . esc_html( number_format( (float) $it['ult_preco_pago'], 2, ',', '.' ) ) : '—'; ?></td>
                        <td><span class="taocot-muted"><?php echo esc_html( $it['origem'] ?? '' ); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php
        $comp   = tao_cot_comparativo_dados( $cid, $cot_id );
        $fids   = array_keys( $comp['fornecedores'] );
        $tem_precos = ! empty( $fids );
        $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=tao_cot_export_xlsx&cot=' . $cot_id ), 'tao_cot_export_' . $cot_id );
        ?>

        <?php if ( ! empty( $comp['divergencias'] ) ) : ?>
        <div class="taocot-card" style="border-left:4px solid #f59e0b">
            <h2>&#x26A0;&#xFE0F; Divergências — itens do fornecedor sem correspondência (<?php echo count( $comp['divergencias'] ); ?>)</h2>
            <p class="taocot-muted">Associe cada item a um ativo do seu cadastro — a associação vira <strong>sinônimo</strong> e as próximas cotações casam sozinhas. Item que não interessa: exclua.</p>
            <div class="taocot-tscroll">
            <table class="taocot-table">
                <thead><tr><th>Fornecedor</th><th>Item como veio</th><th style="text-align:right">Vl unit</th><th>Unid</th><th style="min-width:300px">Associar ao ativo</th><th></th></tr></thead>
                <tbody>
                <?php foreach ( $comp['divergencias'] as $d ) : ?>
                    <tr data-preco-id="<?php echo esc_attr( $d['id'] ); ?>">
                        <td><?php echo esc_html( $comp['fornecedores'][ $d['fornecedor_id'] ] ?? '' ); ?></td>
                        <td><strong><?php echo esc_html( $d['item_original'] ); ?></strong>
                            <?php if ( ! empty( $d['conversao'] ) ) : ?><div class="taocot-muted"><?php echo esc_html( $d['conversao'] ); ?></div><?php endif; ?></td>
                        <td style="text-align:right"><?php echo esc_html( number_format( (float) $d['vl_unit'], 4, ',', '.' ) ); ?></td>
                        <td><?php echo esc_html( $d['unid'] ); ?></td>
                        <td>
                            <div class="taocot-combo taocot-div-combo">
                                <input type="text" placeholder="Busque o ativo (↑/↓ e Enter)...">
                                <div class="taocot-combo-list"></div>
                            </div>
                        </td>
                        <td><button class="taocot-btn taocot-btn-danger taocot-div-del" title="Excluir este item">✕</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="taocot-card">
            <div class="taocot-bar" style="margin:0 0 10px">
                <h2 style="margin:0">&#x1F4CA; Comparativo</h2>
                <?php if ( $tem_precos ) : ?><a class="taocot-btn" href="<?php echo esc_url( $export_url ); ?>">&#x2B07;&#xFE0F; Exportar XLSX</a><?php endif; ?>
            </div>
            <?php if ( ! $tem_precos ) : ?>
                <p class="taocot-muted">Nenhuma proposta processada ainda. Clique em <strong>"📎 Proposta"</strong> em qualquer fornecedor acima para subir o PDF/foto da cotação — a IA extrai os preços e monta o comparativo. Também dá para digitar manualmente, ou processar um anexo recebido no chat.</p>
            <?php else : ?>
            <p class="taocot-muted">Preços normalizados (R$/g, R$/ml ou R$/milheiro). <span style="background:#dcfce7;padding:1px 6px;border-radius:4px">verde</span> = melhor preço do item; <span style="color:#b91c1c">vermelho</span> = melhor preço acima do último pago.</p>
            <div class="taocot-tscroll">
            <table class="taocot-table">
                <thead>
                    <tr>
                        <th>ATIVO</th><th style="text-align:right">ÚLT. PAGO</th><th>MELHOR FORN.</th>
                        <?php foreach ( $fids as $f ) : ?><th style="text-align:right;border-left:2px solid #e2e8f0"><?php echo esc_html( $comp['fornecedores'][ $f ] ); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $comp['linhas'] as $l ) :
                    $ult   = $l['item']['ult_preco_pago'];
                    $mfid  = $l['melhor_fid'];
                    $mvl   = $mfid ? (float) $l['cells'][ $mfid ]['vl_unit'] : null;
                    $acima = $mvl !== null && $ult !== null && (float) $ult > 0 && $mvl > (float) $ult;
                ?>
                    <tr class="<?php echo ! empty( $l['item']['urgente'] ) ? 'taocot-urgente' : ''; ?>">
                        <td><strong><?php echo esc_html( $l['item']['descricao'] ); ?></strong><?php echo ! empty( $l['item']['urgente'] ) ? ' ⭐' : ''; ?></td>
                        <td style="text-align:right"><?php echo $ult !== null ? number_format( (float) $ult, 4, ',', '.' ) : '—'; ?></td>
                        <td><?php if ( $mfid ) : ?><strong style="<?php echo $acima ? 'color:#b91c1c' : 'color:#166534'; ?>"><?php echo esc_html( $comp['fornecedores'][ $mfid ] ); ?></strong><?php else : ?>—<?php endif; ?></td>
                        <?php foreach ( $fids as $f ) : $p = $l['cells'][ $f ] ?? null; ?>
                        <td style="text-align:right;border-left:2px solid #f1f5f9;<?php echo ( $p && $f === $mfid ) ? 'background:#dcfce7' : ''; ?>">
                            <?php if ( $p ) : ?>
                                <strong><?php echo number_format( (float) $p['vl_unit'], 4, ',', '.' ); ?></strong> <span class="taocot-muted">/<?php echo esc_html( $p['unid'] ); ?></span>
                                <div class="taocot-muted">
                                    <?php if ( $p['qtde_min'] ) echo 'mín ' . number_format( (float) $p['qtde_min'], 0, ',', '.' ) . ' · '; ?>
                                    <?php if ( $p['vl_total'] ) echo 'R$ ' . number_format( (float) $p['vl_total'], 2, ',', '.' ); ?>
                                    <?php if ( $p['validade'] ) echo ' · val ' . esc_html( $p['validade'] ); ?>
                                </div>
                            <?php else : ?>—<?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- input oculto p/ anexar proposta pela lista de fornecedores -->
    <input type="file" id="taocot-prop-file" accept=".pdf,.jpg,.jpeg,.png,.webp" style="display:none">

    <!-- Modal proposta manual -->
    <div id="taocot-manual-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box" style="max-width:760px">
            <h2>Digitar proposta — <span id="taocot-manual-forn"></span></h2>
            <p class="taocot-muted">Informe o preço na unidade em que o fornecedor cotou (o sistema converte p/ g, ml ou milheiro).</p>
            <div class="taocot-tscroll">
            <table class="taocot-table" id="taocot-manual-grid">
                <thead><tr><th style="min-width:240px">Item</th><th>Preço R$</th><th>Por</th><th>Qtde mín</th><th>Validade</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
            </div>
            <div style="margin-top:10px">
                <div class="taocot-combo" style="max-width:380px">
                    <input type="text" id="taocot-manual-add" placeholder="+ Adicionar item: busque o ativo ou digite livre...">
                    <div class="taocot-combo-list"></div>
                </div>
            </div>
            <div class="taocot-actions">
                <button class="taocot-btn taocot-btn-primary" id="taocot-manual-salvar">Salvar proposta</button>
                <button class="taocot-btn" data-cot-cancel>Cancelar</button>
                <span class="taocot-status-msg" id="taocot-manual-msg" style="margin:0"></span>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var C = window.taoCot, ID = <?php echo wp_json_encode( $cot_id ); ?>;
        var msg = document.getElementById('taocot-view-msg');
        function run(btn, action, data, confirmMsg){
            if(confirmMsg && !confirm(confirmMsg)) return;
            btn.disabled = true;
            C.post(action, Object.assign({ id: ID }, data||{})).then(function(r){
                if(r.success){
                    if(action==='tao_cot_excluir_cotacao'){ location.href = <?php echo wp_json_encode( tao_cot_url( 'cotacoes' ) ); ?>; return; }
                    location.reload();
                } else { alert('Erro: '+(r.data||'falha')); btn.disabled=false; }
            }).catch(function(){ alert('Falha de rede'); btn.disabled=false; });
        }
        var b;
        if(b = document.getElementById('taocot-btn-enviar')) b.addEventListener('click', function(){
            msg.textContent = 'Enviando mensagens (aguarde, há pausa entre envios)...';
            run(b, 'tao_cot_enviar_cotacao', {});
        });
        if(b = document.getElementById('taocot-btn-concluir')) b.addEventListener('click', function(){ run(b, 'tao_cot_set_status', {status:'concluida'}, 'Marcar esta cotação como concluída?'); });
        if(b = document.getElementById('taocot-btn-cancelar')) b.addEventListener('click', function(){ run(b, 'tao_cot_set_status', {status:'cancelada'}, 'Cancelar esta cotação?'); });
        if(b = document.getElementById('taocot-btn-excluir')) b.addEventListener('click', function(){ run(b, 'tao_cot_excluir_cotacao', {}, 'Excluir definitivamente este rascunho?'); });

        // ── Menu "Proposta" por fornecedor: abre/fecha o dropdown (upload/manual vêm por delegação) ──
        document.addEventListener('click', function(e){
            var tg = e.target.closest ? e.target.closest('.taocot-prop-toggle') : null;
            var alvo = tg ? tg.parentElement.querySelector('.taocot-prop-menu') : null;
            var menus = document.querySelectorAll('.taocot-prop-menu');
            for(var i=0;i<menus.length;i++){ if(menus[i]!==alvo) menus[i].style.display='none'; }
            if(alvo){ alvo.style.display = (alvo.style.display==='block'?'none':'block'); }
        });

        // ── Proposta por arquivo (upload direto da lista de fornecedores) ──────
        var propFid = null, fileInp = document.getElementById('taocot-prop-file');
        document.addEventListener('click', function(e){
            var t = e.target.closest('.taocot-prop-upload');
            if(t){ propFid = t.getAttribute('data-fid'); fileInp.click(); }
        });
        fileInp.addEventListener('change', function(){
            var f = fileInp.files[0]; if(!f || !propFid) return;
            msg.textContent = 'Extraindo a proposta com IA (pode levar ~30s)...';
            C.postFile('tao_cot_proposta_processar', f, { fornecedor_id: propFid, cotacao_id: ID }).then(function(r){
                if(r.success){
                    msg.textContent = r.data.gravados+' itens processados ('+r.data.divergencias+' divergências).';
                    setTimeout(function(){ location.reload(); }, 800);
                } else { msg.textContent = ''; alert('Erro: '+(r.data||'falha')); }
            }).catch(function(){ msg.textContent=''; alert('Falha de rede'); });
            fileInp.value = '';
        });

        // ── Divergências: combo por linha + excluir ─────────────────────────────
        document.querySelectorAll('.taocot-div-combo input').forEach(function(inp){
            C.combo({ input: inp, permitirLivre: false, onPick: function(rr){
                var tr = inp.closest('tr');
                C.post('tao_cot_divergencia_resolver', { preco_id: tr.getAttribute('data-preco-id'), ativo_id: rr.ativo_id }).then(function(r){
                    if(r.success){ location.reload(); } else alert('Erro: '+(r.data||'falha'));
                });
            }});
        });
        document.addEventListener('click', function(e){
            var t = e.target.closest('.taocot-div-del');
            if(!t) return;
            if(!confirm('Excluir este item da cotação?')) return;
            var tr = t.closest('tr');
            C.post('tao_cot_preco_excluir', { id: tr.getAttribute('data-preco-id') }).then(function(r){
                if(r.success) tr.remove(); else alert('Erro: '+(r.data||'falha'));
            });
        });

        // ── Proposta manual ─────────────────────────────────────────────────────
        var manualFid = null, manualItens = [];
        function manualRender(){
            var tb = document.querySelector('#taocot-manual-grid tbody');
            tb.innerHTML = '';
            manualItens.forEach(function(it, idx){
                var tr = document.createElement('tr');
                function cellInput(prop, type, w, ph){
                    var td = document.createElement('td'), inp = document.createElement('input');
                    inp.type = type; inp.value = it[prop]||''; inp.placeholder = ph||'';
                    if(type==='number'){ inp.step='0.0001'; inp.min='0'; }
                    inp.style.cssText = 'width:'+w+'px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px';
                    inp.addEventListener('change', function(){ it[prop] = inp.value; });
                    td.appendChild(inp); return td;
                }
                var tdN = document.createElement('td'); tdN.innerHTML = '<strong></strong>';
                tdN.querySelector('strong').textContent = it.item; tr.appendChild(tdN);
                tr.appendChild(cellInput('preco','number',90));
                var tdU = document.createElement('td'), sel = document.createElement('select');
                ['kg','g','L','ml','milheiro','unidade'].forEach(function(u){
                    var o = document.createElement('option'); o.value=u; o.textContent=u;
                    if((it.preco_unidade||'kg')===u) o.selected=true; sel.appendChild(o);
                });
                sel.style.cssText='padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px';
                sel.addEventListener('change', function(){ it.preco_unidade = sel.value; });
                tdU.appendChild(sel); tr.appendChild(tdU);
                tr.appendChild(cellInput('frac_min','number',80));
                tr.appendChild(cellInput('validade','text',80,'MM/AAAA'));
                var tdX = document.createElement('td'), bx = document.createElement('button');
                bx.className='taocot-btn taocot-btn-danger'; bx.textContent='✕'; bx.style.padding='3px 8px';
                bx.addEventListener('click', function(){ manualItens.splice(idx,1); manualRender(); });
                tdX.appendChild(bx); tr.appendChild(tdX);
                tb.appendChild(tr);
            });
        }
        document.addEventListener('click', function(e){
            var t = e.target.closest('.taocot-prop-manual');
            if(!t) return;
            manualFid = t.getAttribute('data-fid'); manualItens = [];
            document.getElementById('taocot-manual-forn').textContent = t.getAttribute('data-nome')||'';
            manualRender();
            document.getElementById('taocot-manual-modal').style.display = 'block';
        });
        C.combo({ input: document.getElementById('taocot-manual-add'), permitirLivre: true, onPick: function(rr){
            manualItens.push({ item: rr.nome, ativo_id: rr.ativo_id, preco:'', preco_unidade:'kg', frac_min:'', validade:'' });
            manualRender();
        }});
        document.getElementById('taocot-manual-salvar').addEventListener('click', function(){
            var validos = manualItens.filter(function(i){ return parseFloat(i.preco)>0; });
            if(!validos.length){ alert('Informe o preço de ao menos 1 item.'); return; }
            var st = document.getElementById('taocot-manual-msg');
            st.textContent = 'Salvando...';
            C.post('tao_cot_proposta_manual', { fornecedor_id: manualFid, cotacao_id: ID, itens: JSON.stringify(validos) }).then(function(r){
                if(r.success){ location.reload(); } else { st.textContent=''; alert('Erro: '+(r.data||'falha')); }
            });
        });
    })();
    </script>
    <?php
}
