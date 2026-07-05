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
            <p>Nenhuma cotação ainda. Comece subindo a planilha de estoque mínimo do Formula Certa.</p>
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
                        <td>
                            <?php if ( ! empty( $p['card_id'] ) && function_exists( 'cbpm_url' ) ) : ?>
                            <a class="taocot-btn" href="<?php echo esc_url( cbpm_url( 'crm-kanban', [ 'action' => 'card', 'id' => $p['card_id'] ] ) ); ?>">Ver conversa</a>
                            <?php endif; ?>
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
                        <td style="text-align:right"><?php echo $it['ult_preco_pago'] !== null ? 'R$ ' . esc_html( number_format( (float) $it['ult_preco_pago'], 4, ',', '.' ) ) : '—'; ?></td>
                        <td><span class="taocot-muted"><?php echo esc_html( $it['origem'] ?? '' ); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="taocot-card">
            <h2>&#x1F4CA; Comparativo</h2>
            <p class="taocot-muted">O comparativo de preços (com a coluna de último valor pago e o melhor fornecedor por item) fica disponível na Fase 2, conforme as propostas dos fornecedores forem cadastradas e processadas.</p>
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
    })();
    </script>
    <?php
}
