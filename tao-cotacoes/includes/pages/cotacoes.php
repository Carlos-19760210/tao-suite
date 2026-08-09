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
                <?php if ( $aberta ) : ?>
                <button class="taocot-btn taocot-btn-primary" id="taocot-btn-retorno">&#x1F4E5; Registrar retorno de fornecedor</button>
                <?php endif; ?>
                <?php if ( $aberta && $pend_env > 0 ) : ?>
                <button class="taocot-btn" id="taocot-btn-enviar">&#x1F4E4; Enviar aos fornecedores (<?php echo $pend_env; ?>)</button>
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
            <div class="taocot-bar" style="margin:0 0 10px">
                <h2 style="margin:0">Itens (<?php echo count( $itens ); ?>)</h2>
                <button type="button" class="taocot-btn taocot-btn-danger" id="taocot-itens-del-sel" style="display:none">🗑 Excluir selecionados (<span id="taocot-itens-sel-n">0</span>)</button>
            </div>
            <div class="taocot-tscroll">
            <table class="taocot-table">
                <thead><tr><th style="width:28px;text-align:center"><input type="checkbox" id="taocot-itens-chkall" title="Selecionar todos"></th><th style="width:34px" title="Urgente / mandatório">⭐</th><th style="min-width:220px">Item</th><th>Cód. FC</th><th style="text-align:right">Qtde</th><th>Un.</th><th style="text-align:right">Últ. pago</th><th>Origem</th><th style="width:36px"></th></tr></thead>
                <tbody id="taocot-itens-tbody">
                <?php foreach ( $itens as $it ) : ?>
                    <tr class="<?php echo ! empty( $it['urgente'] ) ? 'taocot-urgente' : ''; ?>" data-item-id="<?php echo esc_attr( $it['id'] ); ?>" data-ativo-id="<?php echo esc_attr( $it['ativo_id'] ?? '' ); ?>">
                        <td style="text-align:center"><input type="checkbox" class="taocot-it-chk"></td>
                        <td style="text-align:center"><button type="button" class="taocot-it-star" title="Marcar/desmarcar urgente" style="border:0;background:transparent;cursor:pointer;font-size:15px;opacity:<?php echo ! empty( $it['urgente'] ) ? '1' : '.3'; ?>">⭐</button></td>
                        <td><input class="taocot-it-desc" value="<?php echo esc_attr( $it['descricao'] ); ?>" style="width:100%;min-width:180px;font-weight:600;padding:3px 5px;border:1px solid #e2e8f0;border-radius:5px">
                            <?php if ( empty( $it['ativo_id'] ) ) : ?><span class="taocot-muted taocot-it-livre">(item livre)</span><?php endif; ?>
                        </td>
                        <td><input class="taocot-it-cod" value="<?php echo esc_attr( $it['codigo_fc'] ?? '' ); ?>" style="width:80px;padding:3px 5px;border:1px solid #e2e8f0;border-radius:5px"></td>
                        <td style="text-align:right"><input class="taocot-it-qtd" type="number" step="0.01" min="0" value="<?php echo esc_attr( round( (float) ( $it['qtd'] ?? 0 ), 2 ) ); ?>" style="width:76px;text-align:right;padding:3px 5px;border:1px solid #e2e8f0;border-radius:5px"></td>
                        <td><input class="taocot-it-un" value="<?php echo esc_attr( $it['unidade'] ?? '' ); ?>" style="width:64px;padding:3px 5px;border:1px solid #e2e8f0;border-radius:5px"></td>
                        <td style="text-align:right"><?php echo $it['ult_preco_pago'] !== null ? 'R$ ' . esc_html( number_format( (float) $it['ult_preco_pago'], 2, ',', '.' ) ) : '—'; ?></td>
                        <td><span class="taocot-muted"><?php echo esc_html( $it['origem'] ?? '' ); ?></span></td>
                        <td style="text-align:center"><button type="button" class="taocot-it-del" title="Excluir item" style="border:0;background:transparent;cursor:pointer;color:#dc2626;font-size:14px">🗑</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc">
                        <td></td>
                        <td style="text-align:center"><button type="button" id="taocot-add-star" title="Urgente" style="border:0;background:transparent;cursor:pointer;font-size:15px;opacity:.3">⭐</button></td>
                        <td><input id="taocot-add-desc" placeholder="incluir item… (digite p/ buscar ativo ↑/↓ Enter)" autocomplete="off" style="width:100%;min-width:180px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px"></td>
                        <td><input id="taocot-add-cod" placeholder="cód." style="width:80px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px"></td>
                        <td style="text-align:right"><input id="taocot-add-qtd" type="number" step="0.01" min="0" placeholder="0" style="width:76px;text-align:right;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px"></td>
                        <td><input id="taocot-add-un" placeholder="un." style="width:64px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px"></td>
                        <td colspan="2"><span class="taocot-muted" id="taocot-add-hint">novo item manual</span></td>
                        <td style="text-align:center"><button type="button" id="taocot-add-btn" class="taocot-btn taocot-btn-primary" style="padding:4px 9px">➕</button></td>
                    </tr>
                </tfoot>
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
                <div style="display:flex;gap:8px">
                <?php if ( ! empty( $comp['conferencia'] ) ) : ?><button type="button" class="taocot-btn taocot-btn-primary" id="taocot-conf-abrir">&#x1F50D; Conferência do farmacêutico</button><?php endif; ?>
                <?php if ( $tem_precos ) : ?><a class="taocot-btn" href="<?php echo esc_url( $export_url ); ?>">&#x2B07;&#xFE0F; Exportar XLSX</a><?php endif; ?>
                </div>
            </div>
            <?php if ( ! $tem_precos ) : ?>
                <p class="taocot-muted">Nenhuma proposta processada ainda. Clique em <strong>"📥 Registrar retorno de fornecedor"</strong> (no topo) — escolha o fornecedor e suba o PDF/foto (a IA extrai os preços) ou digite manualmente. Vale mesmo sem ter enviado pelo módulo. Também dá para processar um anexo recebido no chat.</p>
            <?php else : ?>
            <p class="taocot-muted">Preços normalizados (R$/g, R$/ml ou R$/milheiro). <span style="background:#dcfce7;padding:1px 6px;border-radius:4px">verde</span> = melhor preço do item; <span style="color:#b91c1c">vermelho</span> = melhor preço acima do último pago. Quando há <strong>frete</strong>, o melhor é escolhido pelo <strong>valor com frete</strong> (rateado por valor); o valor sem frete aparece ao lado. Clique em <strong>✎ retorno</strong> no topo de cada fornecedor para editar a proposta e informar o frete.</p>
            <div class="taocot-tscroll">
            <table class="taocot-table">
                <thead>
                    <tr>
                        <th>ATIVO</th><th style="text-align:right">ÚLT. PAGO</th><th>MELHOR FORN.</th>
                        <?php foreach ( $fids as $f ) : $fr = $comp['frete'][ $f ] ?? 0; ?>
                        <th style="text-align:right;border-left:2px solid #e2e8f0">
                            <?php echo esc_html( $comp['fornecedores'][ $f ] ); ?>
                            <button type="button" class="taocot-ret-edit" data-fid="<?php echo esc_attr( $f ); ?>" title="Editar retorno / frete deste fornecedor" style="border:0;background:transparent;cursor:pointer;color:#2563eb;font-size:11px;padding:0 2px">✎&nbsp;retorno</button>
                            <?php if ( $fr > 0 ) : ?><div class="taocot-muted" style="font-weight:400;font-size:11px">frete R$ <?php echo number_format( (float) $fr, 2, ',', '.' ); ?></div><?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $comp['linhas'] as $l ) :
                    $ult   = $l['item']['ult_preco_pago'];
                    $mfid  = $l['melhor_fid'];
                    $mvl   = $mfid ? (float) ( $l['cells'][ $mfid ]['vl_com_frete'] ?? $l['cells'][ $mfid ]['vl_unit'] ) : null;
                    $acima = $mvl !== null && $ult !== null && (float) $ult > 0 && $mvl > (float) $ult;
                ?>
                    <tr class="<?php echo ! empty( $l['item']['urgente'] ) ? 'taocot-urgente' : ''; ?>">
                        <td><strong><?php echo esc_html( $l['item']['descricao'] ); ?></strong><?php echo ! empty( $l['item']['urgente'] ) ? ' ⭐' : ''; ?></td>
                        <td style="text-align:right"><?php echo $ult !== null ? number_format( (float) $ult, 4, ',', '.' ) : '—'; ?></td>
                        <td><?php if ( $mfid ) : ?><strong style="<?php echo $acima ? 'color:#b91c1c' : 'color:#166534'; ?>"><?php echo esc_html( $comp['fornecedores'][ $mfid ] ); ?></strong><?php else : ?>—<?php endif; ?></td>
                        <?php foreach ( $fids as $f ) : $p = $l['cells'][ $f ] ?? null; ?>
                        <td style="text-align:right;border-left:2px solid #f1f5f9;<?php echo ( $p && $f === $mfid ) ? 'background:#dcfce7' : ''; ?>">
                            <?php if ( $p ) : ?>
                                <button type="button" class="taocot-preco-edit" title="Editar este preço"
                                    data-id="<?php echo esc_attr( $p['id'] ); ?>"
                                    data-vl="<?php echo esc_attr( $p['vl_unit'] ); ?>"
                                    data-unid="<?php echo esc_attr( $p['unid'] ); ?>"
                                    data-qtde="<?php echo esc_attr( $p['qtde_min'] ?? '' ); ?>"
                                    data-val="<?php echo esc_attr( $p['validade'] ?? '' ); ?>"
                                    style="float:left;border:0;background:transparent;cursor:pointer;color:#94a3b8;font-size:12px;padding:0 2px">✎</button>
                                <?php $temfrete = isset( $p['vl_com_frete'] ) && (float) $p['vl_com_frete'] > (float) $p['vl_unit'] + 0.0000001; ?>
                                <?php if ( $temfrete ) : ?>
                                    <strong title="valor com frete"><?php echo number_format( (float) $p['vl_com_frete'], 4, ',', '.' ); ?></strong> <span class="taocot-muted">/<?php echo esc_html( $p['unid'] ); ?> c/ frete</span>
                                    <div class="taocot-muted" style="font-size:11px">sem frete <?php echo number_format( (float) $p['vl_unit'], 4, ',', '.' ); ?></div>
                                <?php else : ?>
                                    <strong><?php echo number_format( (float) $p['vl_unit'], 4, ',', '.' ); ?></strong> <span class="taocot-muted">/<?php echo esc_html( $p['unid'] ); ?></span>
                                <?php endif; ?>
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

    <!-- Modal CONFERÊNCIA do farmacêutico -->
    <?php if ( ! empty( $comp['conferencia'] ) ) :
        $conf_pend = 0; foreach ( $comp['conferencia'] as $c ) if ( $c['status'] !== 'ok' ) $conf_pend++;
    ?>
    <div id="taocot-conf-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box" style="max-width:920px;max-height:90vh;display:flex;flex-direction:column">
            <h2 style="flex:0 0 auto;display:flex;justify-content:space-between;align-items:center;gap:10px">
                <span>🔍 Conferência do farmacêutico <span class="taocot-muted" style="font-weight:400;font-size:12px">(opcional — cada associação já é salva; pode fechar e continuar depois)</span></span>
                <button type="button" id="taocot-conf-x" title="Salvar e fechar" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:#64748b;line-height:1">✕</button>
            </h2>
            <p class="taocot-muted" style="flex:0 0 auto">Revise a associação de <strong>cada item retornado</strong> ao ativo do TAO Neo. Corrija onde necessário — a correção vira <strong>sinônimo</strong> e as próximas cotações casam sozinhas. <span style="color:#b45309">🟡 fora da lista</span> = associado a um ativo que não está entre os itens desta cotação (confira se está certo); <span style="color:#b91c1c">🔴 não associado</span> = precisa associar.</p>
            <div class="taocot-tscroll" style="overflow:auto;flex:1 1 auto;min-height:0">
            <table class="taocot-table" id="taocot-conf-grid">
                <thead><tr><th style="width:70px">Status</th><th>Fornecedor</th><th style="min-width:200px">Item como veio</th><th style="text-align:right">Vl unit</th><th style="min-width:280px">Ativo TAO Neo</th><th style="width:36px"></th></tr></thead>
                <tbody>
                <?php foreach ( $comp['conferencia'] as $c ) :
                    $badge = $c['status'] === 'ok' ? '🟢' : ( $c['status'] === 'fora' ? '🟡' : '🔴' );
                    $rowbg = $c['status'] === 'nao_assoc' ? 'background:#fef2f2' : ( $c['status'] === 'fora' ? 'background:#fffbeb' : '' );
                ?>
                    <tr data-preco-id="<?php echo esc_attr( $c['id'] ); ?>" data-status="<?php echo esc_attr( $c['status'] ); ?>" style="<?php echo $rowbg; ?>">
                        <td style="text-align:center;font-size:15px"><span class="taocot-conf-badge"><?php echo $badge; ?></span></td>
                        <td><?php echo esc_html( $c['fornecedor'] ); ?></td>
                        <td><strong><?php echo esc_html( $c['item_original'] ); ?></strong></td>
                        <td style="text-align:right"><?php echo esc_html( number_format( (float) $c['vl_unit'], 4, ',', '.' ) ); ?> <span class="taocot-muted">/<?php echo esc_html( $c['unid'] ); ?></span></td>
                        <td>
                            <input type="text" class="taocot-conf-ativo" autocomplete="off" placeholder="buscar ativo… (↑/↓ Enter)" value="<?php echo esc_attr( $c['ativo_nome'] ?? '' ); ?>" style="width:100%;min-width:240px;padding:5px 8px;border:1px solid <?php echo $c['status'] === 'ok' ? '#cbd5e1' : '#f59e0b'; ?>;border-radius:6px">
                            <?php if ( $c['status'] === 'ok' && $c['item_desc'] ) : ?><div class="taocot-muted" style="font-size:11px">item da cotação: <?php echo esc_html( $c['item_desc'] ); ?></div><?php endif; ?>
                        </td>
                        <td style="text-align:center"><button type="button" class="taocot-conf-del" title="Excluir este preço" style="border:0;background:transparent;cursor:pointer;color:#dc2626;font-size:14px">🗑</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc;border-top:2px solid #e2e8f0">
                        <td style="text-align:center;font-size:15px">➕</td>
                        <td>
                            <select id="taocot-nl-forn" style="width:100%;padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px">
                                <option value="">— fornecedor —</option>
                                <?php foreach ( $comp['fornecedores'] as $ffid => $fnome ) : ?>
                                    <option value="<?php echo esc_attr( $ffid ); ?>"><?php echo esc_html( $fnome ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" id="taocot-nl-item" autocomplete="off" placeholder="ativo (↑/↓ Enter) ou descrição livre" style="width:100%;min-width:180px;padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px"></td>
                        <td colspan="2">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:12px;color:#64748b">
                                R$ <input type="number" step="0.0001" min="0" id="taocot-nl-preco" placeholder="preço" style="width:82px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px">
                                por
                                <select id="taocot-nl-unid" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px"><?php foreach ( ['kg','g','mg','L','ml','milheiro','unidade','pacote'] as $u ) echo '<option'.($u==='kg'?' selected':'').'>'.$u.'</option>'; ?></select>
                                · frac. mín <input type="number" step="0.01" min="0" id="taocot-nl-frac" placeholder="qtde" style="width:70px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px">
                                <select id="taocot-nl-fracun" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px"><option value="">(mesma un.)</option><?php foreach ( ['kg','g','mg','L','ml','mil','unidade'] as $u ) echo '<option>'.$u.'</option>'; ?></select>
                                · val <input type="text" id="taocot-nl-val" placeholder="MM/AAAA" style="width:74px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px">
                            </div>
                        </td>
                        <td style="text-align:center"><button type="button" class="taocot-btn taocot-btn-primary" id="taocot-nl-add" style="padding:4px 9px">➕</button></td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <div class="taocot-actions" style="justify-content:space-between;flex:0 0 auto">
                <span class="taocot-muted" id="taocot-conf-resumo"><?php echo (int) $conf_pend; ?> item(ns) a revisar</span>
                <button class="taocot-btn taocot-btn-primary" id="taocot-conf-fechar">Salvar e fechar</button>
            </div>
        </div>
    </div>
    <script>window.TAOCOT_CONF_PEND = <?php echo (int) $conf_pend; ?>;</script>
    <?php endif; ?>

    <!-- Modal EDITAR RETORNO do fornecedor (proposta + frete) -->
    <?php if ( $tem_precos ) :
        $ret_data = [];
        foreach ( $comp['conferencia'] as $c ) {
            $rfid = $c['fornecedor_id'];
            if ( ! isset( $ret_data[ $rfid ] ) ) $ret_data[ $rfid ] = [ 'nome' => $c['fornecedor'], 'frete' => (float) ( $comp['frete'][ $rfid ] ?? 0 ), 'linhas' => [] ];
            $ret_data[ $rfid ]['linhas'][] = [ 'id' => $c['id'], 'item' => $c['item_original'], 'vl_unit' => $c['vl_unit'], 'unid' => $c['unid'], 'qtde_min' => $c['qtde_min'], 'validade' => $c['validade'] ];
        }
    ?>
    <div id="taocot-editret-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box" style="max-width:840px;max-height:90vh;display:flex;flex-direction:column">
            <h2 style="flex:0 0 auto">✎ Editar retorno — <span id="taocot-editret-nome"></span></h2>
            <div style="flex:0 0 auto;margin:0 0 12px;padding:10px 12px;background:#f8fafc;border-radius:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <label style="font-weight:600">🚚 Frete total desta proposta: R$
                    <input type="number" step="0.01" min="0" id="taocot-editret-frete" style="width:110px;padding:5px 7px;border:1px solid #cbd5e1;border-radius:6px">
                </label>
                <span class="taocot-muted" style="font-size:12px">rateado entre os itens <strong>proporcional ao valor</strong> — o comparativo passa a escolher o melhor pelo preço com frete.</span>
            </div>
            <div class="taocot-tscroll" style="overflow:auto;flex:1 1 auto;min-height:0">
            <table class="taocot-table" id="taocot-editret-grid">
                <thead><tr><th style="min-width:200px">Item retornado</th><th style="text-align:right">Preço</th><th>Un.</th><th style="text-align:right">Frac. mín</th><th>Validade</th><th style="width:34px"></th></tr></thead>
                <tbody></tbody>
            </table>
            </div>
            <div class="taocot-actions" style="flex:0 0 auto">
                <button class="taocot-btn" id="taocot-editret-cancel">Cancelar</button>
                <button class="taocot-btn taocot-btn-primary" id="taocot-editret-salvar">Salvar retorno</button>
            </div>
        </div>
    </div>
    <script>window.TAOCOT_RET = <?php echo wp_json_encode( $ret_data ); ?>;</script>
    <?php endif; ?>

    <!-- Modal ENVIO — revisão do texto antes de enviar -->
    <div id="taocot-envio-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box" style="max-width:640px;max-height:90vh;display:flex;flex-direction:column">
            <h2 style="flex:0 0 auto">📤 Enviar aos fornecedores — revisão do texto</h2>
            <p class="taocot-muted" style="flex:0 0 auto">Revise o texto que será enviado. <code>{fornecedor}</code> é trocado pelo nome de cada fornecedor no envio. O envio <strong>só ocorre</strong> ao clicar <strong>Confirmar e enviar</strong>. Você também pode apenas <strong>salvar</strong> o texto e copiá-lo para enviar manualmente.</p>
            <textarea id="taocot-envio-msg" spellcheck="false" style="flex:1 1 auto;min-height:200px;width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;box-sizing:border-box"></textarea>
            <div id="taocot-envio-dest" class="taocot-muted" style="flex:0 0 auto;margin:8px 0;font-size:12px"></div>
            <div class="taocot-actions" style="flex:0 0 auto;flex-wrap:wrap;gap:8px">
                <button class="taocot-btn" id="taocot-envio-cancel">Cancelar</button>
                <button class="taocot-btn" id="taocot-envio-copiar">📋 Copiar texto</button>
                <button class="taocot-btn" id="taocot-envio-salvar">💾 Salvar (não enviar)</button>
                <button class="taocot-btn taocot-btn-primary" id="taocot-envio-enviar">✅ Confirmar e enviar</button>
            </div>
            <div class="taocot-status-msg" id="taocot-envio-status" style="flex:0 0 auto"></div>
        </div>
    </div>

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

    <!-- Modal: registrar retorno de fornecedor (escolher fornecedor -> processar) -->
    <div id="taocot-retorno-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box" style="max-width:520px">
            <h2>&#x1F4E5; Registrar retorno de fornecedor</h2>
            <p class="taocot-muted">Escolha o fornecedor que respondeu e informe a proposta. Não precisa ter enviado pelo módulo — vale para negociações feitas por fora.</p>

            <div id="taocot-ret-step1">
                <?php if ( ! empty( $parts ) ) : ?>
                <div style="margin-bottom:10px">
                    <div class="taocot-muted" style="font-size:12px;margin-bottom:4px">Fornecedores desta cotação</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px">
                        <?php foreach ( $parts as $p ) : $f = $p['fornecedores'] ?? []; ?>
                        <button type="button" class="taocot-btn taocot-ret-forn"
                            data-fid="<?php echo esc_attr( $p['fornecedor_id'] ); ?>"
                            data-nome="<?php echo esc_attr( $f['nome'] ?? '' ); ?>"><?php echo esc_html( $f['nome'] ?? '' ); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="taocot-muted" style="font-size:12px;margin-bottom:4px">Ou busque qualquer fornecedor cadastrado</div>
                <div class="taocot-combo">
                    <input type="text" id="taocot-ret-busca" placeholder="Digite o nome do fornecedor...">
                    <div class="taocot-combo-list"></div>
                </div>
            </div>

            <div id="taocot-ret-step2" style="display:none">
                <p style="margin:4px 0 12px">Fornecedor: <strong id="taocot-ret-nome"></strong></p>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="button" class="taocot-btn taocot-btn-primary" id="taocot-ret-upload">&#x1F4CE; Subir arquivo (PDF/foto) — a IA lê</button>
                    <button type="button" class="taocot-btn" id="taocot-ret-manual">&#x2328;&#xFE0F; Digitar manual</button>
                </div>
                <div class="taocot-actions" style="margin-top:14px">
                    <button type="button" class="taocot-btn" id="taocot-ret-voltar">&larr; Trocar fornecedor</button>
                </div>
            </div>

            <div class="taocot-actions" style="margin-top:14px">
                <button class="taocot-btn" data-cot-cancel>Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal: editar um preço do comparativo a qualquer momento -->
    <div id="taocot-preco-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box" style="max-width:420px">
            <h2>✎ Editar preço</h2>
            <input type="hidden" id="taocot-pe-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <label>Valor unit. R$<br><input id="taocot-pe-vl" type="number" step="0.0001" min="0" style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:5px"></label>
                <label>Unidade<br>
                    <select id="taocot-pe-unid" style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:5px">
                        <?php foreach ( [ 'g', 'ml', 'milheiro', 'kg', 'l', 'unidade' ] as $u ) : ?><option value="<?php echo $u; ?>"><?php echo $u; ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Qtde mín<br><input id="taocot-pe-qtde" type="number" step="0.01" min="0" style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:5px"></label>
                <label>Validade<br><input id="taocot-pe-val" type="text" placeholder="MM/AAAA" style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:5px"></label>
            </div>
            <div class="taocot-actions" style="margin-top:14px">
                <button class="taocot-btn taocot-btn-primary" id="taocot-pe-salvar">Salvar</button>
                <button class="taocot-btn" data-cot-cancel>Cancelar</button>
                <span class="taocot-status-msg" id="taocot-pe-msg" style="margin:0"></span>
            </div>
        </div>
    </div>

    <!-- Modal: revisão do farmacêutico ANTES de gravar no BD -->
    <div id="taocot-rev-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box" style="max-width:900px">
            <h2>✔ Conferir antes de importar — <span id="taocot-rev-forn"></span> <span id="taocot-rev-via" style="font-size:12px;color:#64748b;font-weight:400"></span></h2>
            <p class="taocot-muted">Revise os preços e o ativo casado. Nada é gravado até você clicar em <b>Importar</b>. Ajuste o que precisar; itens sem ativo aparecem em <span style="color:#b45309">laranja</span> — associe ou desmarque.</p>
            <div class="taocot-tscroll" style="max-height:52vh;overflow:auto">
            <table class="taocot-table" id="taocot-rev-grid">
                <thead><tr><th style="width:30px"></th><th style="min-width:210px">Item (fornecedor)</th><th>Preço R$</th><th>Por</th><th>Qtde mín</th><th>Validade</th><th style="min-width:230px">Ativo casado</th></tr></thead>
                <tbody></tbody>
            </table>
            </div>
            <div class="taocot-actions" style="margin-top:12px">
                <button class="taocot-btn taocot-btn-primary" id="taocot-rev-importar">📥 Importar <span id="taocot-rev-cnt"></span></button>
                <button class="taocot-btn" data-cot-cancel>Cancelar</button>
                <span class="taocot-status-msg" id="taocot-rev-msg" style="margin:0"></span>
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
        // Enviar aos fornecedores → abre a REVISÃO do texto (envio só na confirmação)
        if(b = document.getElementById('taocot-btn-enviar')) b.addEventListener('click', function(){
            var em=document.getElementById('taocot-envio-modal'), ta=document.getElementById('taocot-envio-msg'),
                dest=document.getElementById('taocot-envio-dest'), st=document.getElementById('taocot-envio-status');
            st.textContent=''; ta.value='Carregando…'; em.style.display='block';
            C.post('tao_cot_preview_msg', { id: ID }).then(function(r){
                if(!r.success){ em.style.display='none'; alert('Erro: '+(r.data||'falha')); return; }
                ta.value = (r.data.msg_texto && r.data.msg_texto.trim()) ? r.data.msg_texto : r.data.msg;
                var linhas = (r.data.fornecedores||[]).map(function(f){
                    var wa = f.tem_wa ? '✅ WhatsApp' : '⚠️ sem WhatsApp — copie e envie manual';
                    var stx = (f.status==='enviado') ? ' (já enviado)' : (f.status==='respondeu' ? ' (respondeu)' : '');
                    return '• '+f.nome+' — '+wa+stx;
                });
                dest.innerHTML = '<strong>Destinatários:</strong><br>'+(linhas.length?linhas.join('<br>'):'—');
            }).catch(function(){ em.style.display='none'; alert('Falha de rede'); });
        });
        (function(){
            var em=document.getElementById('taocot-envio-modal'); if(!em) return;
            var ta=document.getElementById('taocot-envio-msg'), st=document.getElementById('taocot-envio-status');
            function close(){ em.style.display='none'; }
            document.getElementById('taocot-envio-cancel').addEventListener('click', close);
            em.querySelector('.taocot-overlay').addEventListener('click', function(e){ e.stopPropagation(); }); // clicar atrás não fecha
            document.getElementById('taocot-envio-copiar').addEventListener('click', function(){
                ta.focus(); ta.select(); var ok=false; try{ ok=document.execCommand('copy'); }catch(e){}
                if(navigator.clipboard){ navigator.clipboard.writeText(ta.value); ok=true; }
                st.textContent = ok ? 'Texto copiado.' : 'Selecione e copie manualmente (Ctrl+C).';
            });
            document.getElementById('taocot-envio-salvar').addEventListener('click', function(){
                var bt=this; bt.disabled=true; st.textContent='Salvando…';
                C.post('tao_cot_salvar_msg', { id: ID, msg_texto: ta.value }).then(function(r){
                    bt.disabled=false; st.textContent = r.success ? 'Texto salvo (não enviado). Pode copiar e enviar manualmente.' : ('Erro: '+(r.data||'falha'));
                });
            });
            document.getElementById('taocot-envio-enviar').addEventListener('click', function(){
                if(!confirm('Confirmar o ENVIO da mensagem aos fornecedores pendentes?')) return;
                var bt=this; bt.disabled=true; st.textContent='Enviando (há pausa entre envios)…';
                C.post('tao_cot_enviar_cotacao', { id: ID, msg_custom: ta.value }).then(function(r){
                    if(r.success){ location.reload(); } else { st.textContent=''; alert('Erro: '+(r.data||'falha')); bt.disabled=false; }
                }).catch(function(){ st.textContent=''; alert('Falha de rede'); bt.disabled=false; });
            });
        })();
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

        // ── Proposta por arquivo → PRÉVIA → revisão do farmacêutico → importar ──
        var propFid = null, propNome = '', fileInp = document.getElementById('taocot-prop-file');
        document.addEventListener('click', function(e){
            var t = e.target.closest('.taocot-prop-upload');
            if(t){ propFid = t.getAttribute('data-fid'); var tr=t.closest('tr'); propNome = tr? (tr.querySelector('td strong')||{}).textContent||'' : ''; fileInp.click(); }
        });

        // pdf.js lazy — extrai o texto no navegador p/ o caminho determinístico (sem IA)
        var _pdf=null;
        function pdfjs(){ if(_pdf) return _pdf; _pdf=new Promise(function(res,rej){
            var s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
            s.onload=function(){ try{ pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js'; res(); }catch(e){ rej(e);} }; s.onerror=rej; document.head.appendChild(s); }); return _pdf; }
        // Reconstrói LINHAS por posição vertical (Y) — pdf.js entrega fragmentos por célula;
        // sem isto a página vira uma linha só e os modelos (1 regex por linha de produto) não casam.
        function itemsToLines(items){
            var arr=[]; items.forEach(function(it){ var s=it.str; if(s&&s.trim()!==''){ arr.push({x:it.transform[4], y:it.transform[5], s:s}); } });
            arr.sort(function(a,b){ return (b.y-a.y) || (a.x-b.x); });   // topo→base, esq→dir
            var lines=[], cur=[], lastY=null;
            arr.forEach(function(o){ if(lastY===null || Math.abs(o.y-lastY)<=3){ cur.push(o); } else { lines.push(cur); cur=[o]; } lastY=o.y; });
            if(cur.length) lines.push(cur);
            return lines.map(function(row){ return row.sort(function(a,b){return a.x-b.x;}).map(function(o){return o.s;}).join(' '); }).join('\n');
        }
        function pdfText(file){ if(!/\.pdf$/i.test(file.name)) return Promise.resolve(null); return pdfjs().then(function(){ return new Promise(function(res){
            var fr=new FileReader(); fr.onload=function(){ pdfjsLib.getDocument({data:new Uint8Array(fr.result)}).promise.then(function(pdf){
                var out=[], ch=Promise.resolve(); for(var i=1;i<=pdf.numPages;i++){ (function(n){ ch=ch.then(function(){ return pdf.getPage(n).then(function(p){ return p.getTextContent().then(function(tc){ out[n-1]=itemsToLines(tc.items); }); }); }); })(i); }
                ch.then(function(){ res(out); }).catch(function(){ res(null); }); }).catch(function(){ res(null); }); }; fr.onerror=function(){ res(null); }; fr.readAsArrayBuffer(file);
        }); }).catch(function(){ return null; }); }

        fileInp.addEventListener('change', function(){
            var f = fileInp.files[0]; if(!f || !propFid) return;
            msg.textContent = 'Lendo a proposta…';
            pdfText(f).then(function(pags){
                var extra = { fornecedor_id: propFid, cotacao_id: ID };
                if(pags && pags.join('').replace(/\s/g,'').length >= 50){ extra.paginas = JSON.stringify(pags); }
                msg.textContent = extra.paginas ? 'Aplicando o modelo do fornecedor…' : 'Extraindo com IA (pode levar ~30s)…';
                C.postFile('tao_cot_proposta_preview', f, extra).then(function(r){
                    fileInp.value = '';
                    if(!r.success){ msg.textContent=''; alert('Erro: '+(r.data||'falha')); return; }
                    msg.textContent = '';
                    var itens = r.data.itens||[];
                    if(!itens.length){
                        alert('Nenhum item de preço foi encontrado neste arquivo.\n\nConfira se é a cotação com a TABELA DE PRODUTOS — arquivos que são só carta/aviso, capa ou imagem sem texto não têm itens para importar. Você também pode digitar manualmente.');
                        return;
                    }
                    abrirRevisao(propFid, propNome, itens, r.data.via);
                }).catch(function(){ msg.textContent=''; alert('Falha de rede'); fileInp.value=''; });
            });
        });

        // ── Combo de ativo com dropdown FIXO no body (escapa do overflow da tabela) + teclado ──
        //    Setas ↑/↓ navegam, Enter escolhe, Esc fecha; reposiciona no scroll. Regra: [[combo teclado]].
        var _acPop=null, _acInp=null;
        function acPop(){ if(_acPop) return _acPop;
            _acPop=document.createElement('div');
            _acPop.style.cssText='position:fixed;z-index:100001;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 10px 26px rgba(0,0,0,.18);max-height:240px;overflow:auto;display:none;font-size:13px;min-width:180px';
            document.body.appendChild(_acPop);
            window.addEventListener('scroll', function(){ if(_acPop.style.display!=='none'&&_acInp) place(_acInp); }, true);
            window.addEventListener('resize', function(){ if(_acPop.style.display!=='none'&&_acInp) place(_acInp); });
            return _acPop; }
        function place(inp){ var r=inp.getBoundingClientRect(); var p=acPop(); p.style.left=r.left+'px'; p.style.top=(r.bottom+2)+'px'; p.style.minWidth=r.width+'px'; }
        function taocotAtivoCombo(inp, onPick){
            var pop=acPop(), results=[], sel=-1, timer=null;
            function paint(){ Array.prototype.forEach.call(pop.children,function(c,i){ var on=(i===sel);
                c.style.background=on?'#2563eb':'#fff'; c.style.color=on?'#fff':'#334155';
                var sp=c.querySelector('span'); if(sp) sp.style.color=on?'#dbeafe':'#94a3b8'; });
                var e=pop.children[sel]; if(e){ var pt=e.offsetTop, pb=pt+e.offsetHeight;
                    if(pt<pop.scrollTop) pop.scrollTop=pt; else if(pb>pop.scrollTop+pop.clientHeight) pop.scrollTop=pb-pop.clientHeight; } }
            function render(){ pop.innerHTML=''; results.forEach(function(a,i){ var d=document.createElement('div'); d.style.cssText='padding:7px 10px;cursor:pointer;color:#334155';
                d.innerHTML='<strong></strong><span style="color:#94a3b8"></span>'; d.querySelector('strong').textContent=a.nome; d.querySelector('span').textContent=a.codigo_fc?(' #'+a.codigo_fc):'';
                d.addEventListener('mousedown', function(ev){ ev.preventDefault(); choose(i); });
                d.addEventListener('mousemove', function(){ if(sel!==i){ sel=i; paint(); } });
                pop.appendChild(d); }); place(inp); pop.style.display=results.length?'block':'none'; paint(); }
            function close(){ pop.style.display='none'; results=[]; sel=-1; if(_acInp===inp)_acInp=null; }
            function choose(i){ var a=results[i]; if(!a) return; close(); onPick({ativo_id:a.id, nome:a.nome, codigo_fc:a.codigo_fc}); }
            inp.addEventListener('input', function(){ clearTimeout(timer); var q=inp.value.trim(); if(q.length<2){ close(); return; }
                _acInp=inp; timer=setTimeout(function(){ C.post('tao_cot_search_ativos',{q:q}).then(function(r){ if(_acInp!==inp) return; results=r.success?(r.data||[]):[]; sel=results.length?0:-1; render(); }); }, 250); });
            inp.addEventListener('keydown', function(e){ if(pop.style.display==='none'||_acInp!==inp||!results.length) return;
                if(e.key==='ArrowDown'){ e.preventDefault(); sel=Math.min(sel+1,results.length-1); paint(); }
                else if(e.key==='ArrowUp'){ e.preventDefault(); sel=Math.max(sel-1,0); paint(); }
                else if(e.key==='Enter'){ e.preventDefault(); if(sel>=0) choose(sel); }
                else if(e.key==='Escape'){ close(); } });
            inp.addEventListener('blur', function(){ setTimeout(function(){ if(_acInp===inp) close(); }, 180); });
        }

        // ── Revisão do farmacêutico (edição inline) — grava só ao Importar ──────
        var revFid=null, revItens=[];
        function revRender(){
            var tb=document.querySelector('#taocot-rev-grid tbody'); tb.innerHTML='';
            revItens.forEach(function(it, idx){
                var tr=document.createElement('tr');
                if(!it.ativo_id) tr.style.background='#fff7ed';
                var tdX=document.createElement('td'); var bx=document.createElement('button'); bx.className='taocot-btn taocot-btn-danger'; bx.textContent='✕'; bx.style.padding='2px 7px';
                bx.addEventListener('click', function(){ revItens.splice(idx,1); revRender(); }); tdX.appendChild(bx); tr.appendChild(tdX);
                var tdN=document.createElement('td'); tdN.innerHTML='<strong></strong>'; tdN.querySelector('strong').textContent=it.item; tr.appendChild(tdN);
                function inp(prop,type,w){ var td=document.createElement('td'),el=document.createElement('input'); el.type=type; el.value=it[prop]==null?'':it[prop]; if(type==='number'){el.step='0.0001';el.min='0';} el.style.cssText='width:'+w+'px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px'; el.addEventListener('change',function(){ it[prop]=el.value; }); td.appendChild(el); return td; }
                tr.appendChild(inp('preco','number',90));
                var tdU=document.createElement('td'),sel=document.createElement('select'); ['kg','g','L','ml','milheiro','unidade'].forEach(function(u){ var o=document.createElement('option'); o.value=u;o.textContent=u; if((it.preco_unidade||'g')===u)o.selected=true; sel.appendChild(o); }); sel.style.cssText='padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px'; sel.addEventListener('change',function(){ it.preco_unidade=sel.value; }); tdU.appendChild(sel); tr.appendChild(tdU);
                tr.appendChild(inp('frac_min','text',70));
                tr.appendChild(inp('validade','text',70));
                var tdA=document.createElement('td');
                var ai=document.createElement('input'); ai.type='text'; ai.placeholder='buscar ativo… (↑/↓ e Enter)'; ai.value=it.ativo_nome||'';
                ai.style.cssText='width:230px;max-width:100%;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px';
                tdA.appendChild(ai); tr.appendChild(tdA);
                taocotAtivoCombo(ai, function(rr){ it.ativo_id=rr.ativo_id; it.ativo_nome=rr.nome; ai.value=rr.nome; tr.style.background=''; });
                tb.appendChild(tr);
            });
            document.getElementById('taocot-rev-cnt').textContent = '('+revItens.length+')';
        }
        function abrirRevisao(fid, nome, itens, via){
            revFid=fid; revItens=itens.map(function(i){ return { item:i.item, preco:i.preco, preco_unidade:i.preco_unidade||'g', frac_min:i.frac_min||'', validade:i.validade||'', ativo_id:i.ativo_id||null, ativo_nome:i.ativo_nome||'' }; });
            document.getElementById('taocot-rev-forn').textContent = nome||'';
            document.getElementById('taocot-rev-via').textContent = via==='modelo' ? '· lido pelo modelo (sem IA)' : '· lido pela IA';
            revRender();
            document.getElementById('taocot-rev-modal').style.display='block';
        }
        document.getElementById('taocot-rev-importar').addEventListener('click', function(){
            var validos = revItens.filter(function(i){ return parseFloat(i.preco)>0; });
            if(!validos.length){ alert('Nenhum item com preço válido.'); return; }
            var st=document.getElementById('taocot-rev-msg'); st.textContent='Importando…';
            C.post('tao_cot_proposta_manual', { fornecedor_id: revFid, cotacao_id: ID, itens: JSON.stringify(validos) }).then(function(r){
                if(r.success){ location.reload(); } else { st.textContent=''; alert('Erro: '+(r.data||'falha')); }
            }).catch(function(){ st.textContent=''; alert('Falha de rede'); });
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

        // ── Conferência do farmacêutico (abre ao carregar + botão) ──────────────
        (function(){
            var modal=document.getElementById('taocot-conf-modal'); if(!modal) return;
            var confMudou=false;
            function open(){ modal.classList.add('taocot-open'); modal.style.display='block'; }
            function close(){ modal.classList.remove('taocot-open'); modal.style.display='none'; if(confMudou) location.reload(); }
            function resumo(){
                var pend = modal.querySelectorAll('#taocot-conf-grid tbody tr[data-status="nao_assoc"], #taocot-conf-grid tbody tr[data-status="fora"]').length;
                var el=document.getElementById('taocot-conf-resumo'); if(el) el.textContent = pend + ' item(ns) a revisar';
            }
            // combo por linha (teclado) → reassocia
            modal.querySelectorAll('.taocot-conf-ativo').forEach(function(inp){
                taocotAtivoCombo(inp, function(rr){
                    var tr=inp.closest('tr');
                    C.post('tao_cot_divergencia_resolver', { preco_id: tr.getAttribute('data-preco-id'), ativo_id: rr.ativo_id }).then(function(r){
                        if(r.success){
                            confMudou=true; inp.value=rr.nome; inp.style.borderColor='#16a34a';
                            tr.setAttribute('data-status','ok'); tr.style.background='';
                            var b=tr.querySelector('.taocot-conf-badge'); if(b) b.textContent='🟢';
                            resumo();
                        } else alert('Erro: '+(r.data||'falha'));
                    });
                });
            });
            // excluir preço
            modal.querySelectorAll('.taocot-conf-del').forEach(function(btn){
                btn.addEventListener('click', function(){
                    if(!confirm('Excluir este preço do fornecedor?')) return;
                    var tr=btn.closest('tr');
                    C.post('tao_cot_preco_excluir', { id: tr.getAttribute('data-preco-id') }).then(function(r){
                        if(r.success){ confMudou=true; tr.remove(); resumo(); } else alert('Erro: '+(r.data||'falha'));
                    });
                });
            });
            // ── Incluir nova LINHA de preço (retorno manual do fornecedor) ──────
            var nlItem=document.getElementById('taocot-nl-item'), nlAtivo='';
            if(nlItem){
                taocotAtivoCombo(nlItem, function(rr){ nlAtivo=rr.ativo_id; nlItem.value=rr.nome; });
                nlItem.addEventListener('input', function(){ nlAtivo=''; });
            }
            var nlAdd=document.getElementById('taocot-nl-add');
            if(nlAdd) nlAdd.addEventListener('click', function(){
                var forn=document.getElementById('taocot-nl-forn').value;
                var item=(nlItem.value||'').trim();
                var preco=document.getElementById('taocot-nl-preco').value;
                if(!forn){ alert('Selecione o fornecedor'); return; }
                if(!preco){ alert('Informe o preço'); return; }
                if(!item && !nlAtivo){ alert('Informe o ativo ou a descrição do item'); return; }
                nlAdd.disabled=true;
                C.post('tao_cot_preco_add', {
                    cotacao_id: ID, fornecedor_id: forn, ativo_id: nlAtivo, item: item, preco: preco,
                    preco_unidade: document.getElementById('taocot-nl-unid').value,
                    frac_min: document.getElementById('taocot-nl-frac').value,
                    frac_unidade: document.getElementById('taocot-nl-fracun').value,
                    validade: document.getElementById('taocot-nl-val').value
                }).then(function(r){ nlAdd.disabled=false; if(r.success){ confMudou=true; location.reload(); } else alert('Erro: '+(r.data||'falha')); });
            });

            var ab=document.getElementById('taocot-conf-abrir'); if(ab) ab.addEventListener('click', open);
            var fe=document.getElementById('taocot-conf-fechar'); if(fe) fe.addEventListener('click', close);
            var cx=document.getElementById('taocot-conf-x'); if(cx) cx.addEventListener('click', close);
            // clicar ATRÁS (overlay) NÃO fecha — só fecha pelos botões "Salvar e fechar" / ✕
            modal.querySelector('.taocot-overlay').addEventListener('click', function(e){ e.stopPropagation(); });
            // abre SOMENTE pelo botão (não abre sozinho ao carregar)
        })();

        // ── Editar RETORNO do fornecedor (proposta + frete) ─────────────────────
        (function(){
            var modal=document.getElementById('taocot-editret-modal'); if(!modal || !window.TAOCOT_RET) return;
            var nome=document.getElementById('taocot-editret-nome'),
                freteI=document.getElementById('taocot-editret-frete'),
                tb=modal.querySelector('#taocot-editret-grid tbody'), curFid=null;
            function unidSel(v){ var s=document.createElement('select'); s.className='er-un'; s.style.cssText='padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px';
                ['g','ml','milheiro','unidade','kg','mg','L'].forEach(function(u){ var o=document.createElement('option'); o.value=u;o.textContent=u; if((v||'g')===u)o.selected=true; s.appendChild(o); }); return s; }
            function open(fid){
                var d=window.TAOCOT_RET[fid]; if(!d) return;
                curFid=fid; nome.textContent=d.nome||''; freteI.value=d.frete||''; tb.innerHTML='';
                d.linhas.forEach(function(l){
                    var tr=document.createElement('tr'); tr.setAttribute('data-preco-id', l.id); tr.dataset.dirty='0';
                    var tdI=document.createElement('td'); tdI.innerHTML='<strong></strong>'; tdI.querySelector('strong').textContent=l.item; tr.appendChild(tdI);
                    function inp(cls,val,type,w,align){ var td=document.createElement('td'); if(align)td.style.textAlign=align;
                        var el=document.createElement('input'); el.className=cls; el.type=type||'text'; if(type==='number'){el.step='0.0001';el.min='0';}
                        el.value=(val==null?'':val); el.style.cssText='width:'+w+'px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px'+(align==='right'?';text-align:right':'');
                        el.addEventListener('change',function(){ tr.dataset.dirty='1'; }); td.appendChild(el); tr.appendChild(td); return el; }
                    inp('er-vl', l.vl_unit, 'number', 92, 'right');
                    var tdU=document.createElement('td'); var us=unidSel(l.unid); us.addEventListener('change',function(){tr.dataset.dirty='1';}); tdU.appendChild(us); tr.appendChild(tdU);
                    inp('er-frac', l.qtde_min, 'number', 86, 'right');
                    inp('er-val', l.validade, 'text', 80);
                    var tdX=document.createElement('td'); tdX.style.textAlign='center';
                    var bx=document.createElement('button'); bx.type='button'; bx.textContent='🗑'; bx.title='Excluir este preço'; bx.style.cssText='border:0;background:transparent;cursor:pointer;color:#dc2626;font-size:13px';
                    bx.addEventListener('click',function(){ if(!confirm('Excluir este preço?'))return; C.post('tao_cot_preco_excluir',{id:l.id}).then(function(r){ if(r.success){ tr.remove(); } else alert('Erro: '+(r.data||'falha')); }); });
                    tdX.appendChild(bx); tr.appendChild(tdX); tb.appendChild(tr);
                });
                modal.style.display='block';
            }
            function close(){ modal.style.display='none'; }
            document.querySelectorAll('.taocot-ret-edit').forEach(function(b){ b.addEventListener('click',function(){ open(b.getAttribute('data-fid')); }); });
            document.getElementById('taocot-editret-cancel').addEventListener('click', close);
            // clicar atrás não fecha (evita perder edições não salvas) — use Cancelar/Salvar
            modal.querySelector('.taocot-overlay').addEventListener('click', function(e){ e.stopPropagation(); });
            document.getElementById('taocot-editret-salvar').addEventListener('click', function(){
                var btn=this; btn.disabled=true; var jobs=[];
                jobs.push(C.post('tao_cot_frete_salvar',{ cotacao_id:ID, fornecedor_id:curFid, frete:freteI.value||'0' }));
                tb.querySelectorAll('tr').forEach(function(tr){
                    if(tr.dataset.dirty!=='1') return;
                    jobs.push(C.post('tao_cot_preco_editar',{ id: tr.getAttribute('data-preco-id'),
                        vl_unit: tr.querySelector('.er-vl').value, unid: tr.querySelector('.er-un').value,
                        qtde_min: tr.querySelector('.er-frac').value, validade: tr.querySelector('.er-val').value }));
                });
                Promise.all(jobs).then(function(){ location.reload(); }).catch(function(){ btn.disabled=false; alert('Falha ao salvar'); });
            });
        })();

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
        function abrirManual(fid, nome){
            manualFid = fid; manualItens = [];
            document.getElementById('taocot-manual-forn').textContent = nome||'';
            manualRender();
            document.getElementById('taocot-manual-modal').style.display = 'block';
        }
        document.addEventListener('click', function(e){
            var t = e.target.closest('.taocot-prop-manual');
            if(!t) return;
            abrirManual(t.getAttribute('data-fid'), t.getAttribute('data-nome'));
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

        // ── Incluir / excluir / alterar ITENS da cotação a qualquer momento ─────
        function flash(el, ok){ if(!el) return; el.style.borderColor = ok?'#16a34a':'#dc2626'; setTimeout(function(){ el.style.borderColor='#e2e8f0'; }, 1200); }
        function salvarItem(tr, campo, valor, el){
            var id = tr.getAttribute('data-item-id'); if(!id) return;
            var d = { id: id }; d[campo] = valor;
            C.post('tao_cot_item_editar', d).then(function(r){ flash(el, r.success); });
        }
        function bindLinha(tr){
            var q=tr.querySelector('.taocot-it-qtd'), u=tr.querySelector('.taocot-it-un'),
                de=tr.querySelector('.taocot-it-desc'), co=tr.querySelector('.taocot-it-cod'),
                st=tr.querySelector('.taocot-it-star'), dl=tr.querySelector('.taocot-it-del');
            if(q)  q.addEventListener('change', function(){ salvarItem(tr,'qtd',q.value,q); });
            if(u)  u.addEventListener('change', function(){ salvarItem(tr,'unidade',u.value,u); });
            if(de) de.addEventListener('change', function(){ salvarItem(tr,'descricao',de.value,de); });
            if(co) co.addEventListener('change', function(){ salvarItem(tr,'codigo_fc',co.value,co); });
            if(st) st.addEventListener('click', function(){
                var novo = st.style.opacity!=='1';
                C.post('tao_cot_item_editar', { id: tr.getAttribute('data-item-id'), urgente: novo?'1':'0' }).then(function(r){
                    if(r.success){ st.style.opacity = novo?'1':'.3'; tr.classList.toggle('taocot-urgente', novo); }
                });
            });
            if(dl) dl.addEventListener('click', function(){
                if(!confirm('Excluir este item da cotação?')) return;
                C.post('tao_cot_item_excluir', { id: tr.getAttribute('data-item-id') }).then(function(r){
                    if(r.success){ tr.remove(); } else { alert('Erro: '+(r.data||'falha')); }
                });
            });
        }
        document.querySelectorAll('#taocot-itens-tbody tr').forEach(bindLinha);

        // ── Seleção múltipla de itens + excluir vários de uma vez ───────────────
        (function(){
            var tbody=document.getElementById('taocot-itens-tbody'); if(!tbody) return;
            var chkAll=document.getElementById('taocot-itens-chkall'),
                btnDel=document.getElementById('taocot-itens-del-sel'),
                nSpan=document.getElementById('taocot-itens-sel-n');
            function chks(){ return Array.prototype.slice.call(tbody.querySelectorAll('.taocot-it-chk')); }
            function selecionados(){ return chks().filter(function(c){ return c.checked; }); }
            function atualiza(){
                var n=selecionados().length;
                if(nSpan) nSpan.textContent=n;
                if(btnDel) btnDel.style.display = n>0 ? '' : 'none';
                if(chkAll){ var todos=chks(); chkAll.checked = todos.length>0 && n===todos.length; chkAll.indeterminate = n>0 && n<todos.length; }
            }
            tbody.addEventListener('change', function(e){ if(e.target.classList.contains('taocot-it-chk')) atualiza(); });
            if(chkAll) chkAll.addEventListener('change', function(){ chks().forEach(function(c){ c.checked=chkAll.checked; }); atualiza(); });
            if(btnDel) btnDel.addEventListener('click', function(){
                var trs=selecionados().map(function(c){ return c.closest('tr'); });
                var ids=trs.map(function(tr){ return tr.getAttribute('data-item-id'); });
                if(!ids.length) return;
                if(!confirm('Excluir '+ids.length+' item(ns) selecionado(s) da cotação?')) return;
                btnDel.disabled=true;
                C.post('tao_cot_itens_excluir', { ids: JSON.stringify(ids) }).then(function(r){
                    btnDel.disabled=false;
                    if(r.success){ trs.forEach(function(tr){ tr.remove(); }); atualiza(); }
                    else alert('Erro: '+(r.data||'falha'));
                });
            });
        })();

        // ── Linha de inclusão (rodapé) — combo de ativo por teclado ─────────────
        (function(){
            var desc=document.getElementById('taocot-add-desc'), cod=document.getElementById('taocot-add-cod'),
                qtd=document.getElementById('taocot-add-qtd'), un=document.getElementById('taocot-add-un'),
                star=document.getElementById('taocot-add-star'), btn=document.getElementById('taocot-add-btn'),
                hint=document.getElementById('taocot-add-hint');
            if(!desc||!btn) return;
            var novoAtivoId='', urg=false;
            star.addEventListener('click', function(){ urg=!urg; star.style.opacity=urg?'1':'.3'; });
            // ao escolher um ativo do combo, guarda o id, casa código/unidade e nome
            taocotAtivoCombo(desc, function(rr){ novoAtivoId=rr.ativo_id; desc.value=rr.nome; if(rr.codigo_fc) cod.value=rr.codigo_fc; hint.textContent='vinculado ao ativo'; });
            desc.addEventListener('input', function(){ novoAtivoId=''; hint.textContent='novo item manual'; });
            btn.addEventListener('click', function(){
                var d = (desc.value||'').trim(); if(!d){ desc.focus(); return; }
                btn.disabled=true;
                C.post('tao_cot_item_add', {
                    cotacao_id: ID, descricao: d, ativo_id: novoAtivoId, codigo_fc: cod.value,
                    qtd: qtd.value, unidade: un.value, urgente: urg?'1':'0'
                }).then(function(r){ btn.disabled=false; if(r.success){ location.reload(); } else { alert('Erro: '+(r.data||'falha')); } });
            });
            desc.addEventListener('keydown', function(e){ if(e.key==='Enter' && !novoAtivoId){ /* deixa o combo tratar quando há seleção */ } });
        })();

        // ── Editar PREÇO do comparativo a qualquer momento ──────────────────────
        document.addEventListener('click', function(e){
            var t = e.target.closest('.taocot-preco-edit'); if(!t) return;
            document.getElementById('taocot-pe-id').value = t.getAttribute('data-id');
            document.getElementById('taocot-pe-vl').value = t.getAttribute('data-vl')||'';
            var us=document.getElementById('taocot-pe-unid'); us.value = (t.getAttribute('data-unid')||'g');
            document.getElementById('taocot-pe-qtde').value = t.getAttribute('data-qtde')||'';
            document.getElementById('taocot-pe-val').value = t.getAttribute('data-val')||'';
            document.getElementById('taocot-pe-msg').textContent = '';
            document.getElementById('taocot-preco-modal').style.display = 'block';
        });
        document.getElementById('taocot-pe-salvar').addEventListener('click', function(){
            var st = document.getElementById('taocot-pe-msg'); st.textContent='Salvando…';
            C.post('tao_cot_preco_editar', {
                id: document.getElementById('taocot-pe-id').value,
                vl_unit: document.getElementById('taocot-pe-vl').value,
                unid: document.getElementById('taocot-pe-unid').value,
                qtde_min: document.getElementById('taocot-pe-qtde').value,
                validade: document.getElementById('taocot-pe-val').value
            }).then(function(r){
                if(r.success){ location.reload(); } else { st.textContent=''; alert('Erro: '+(r.data||'falha')); }
            }).catch(function(){ st.textContent=''; alert('Falha de rede'); });
        });

        // ── Registrar retorno: escolher fornecedor -> subir arquivo / digitar ────
        var retModal = document.getElementById('taocot-retorno-modal');
        function retStep(n){
            document.getElementById('taocot-ret-step1').style.display = n===1?'block':'none';
            document.getElementById('taocot-ret-step2').style.display = n===2?'block':'none';
        }
        function retEscolher(fid, nome){
            document.getElementById('taocot-ret-nome').textContent = nome||'';
            retModal.dataset.fid = fid; retModal.dataset.nome = nome||'';
            retStep(2);
        }
        if(b = document.getElementById('taocot-btn-retorno')) b.addEventListener('click', function(){
            retStep(1); document.getElementById('taocot-ret-busca').value = '';
            retModal.style.display = 'block';
        });
        document.addEventListener('click', function(e){
            var t = e.target.closest('.taocot-ret-forn');
            if(t) retEscolher(t.getAttribute('data-fid'), t.getAttribute('data-nome'));
        });
        if(b = document.getElementById('taocot-ret-voltar')) b.addEventListener('click', function(){ retStep(1); });
        if(b = document.getElementById('taocot-ret-upload')) b.addEventListener('click', function(){
            propFid = retModal.dataset.fid;
            retModal.style.display = 'none';
            fileInp.click();
        });
        if(b = document.getElementById('taocot-ret-manual')) b.addEventListener('click', function(){
            retModal.style.display = 'none';
            abrirManual(retModal.dataset.fid, retModal.dataset.nome);
        });

        // combo dedicado de fornecedor (busca em todos) — COM navegação por setas (↑/↓/Enter/Esc).
        // REGRA do projeto: todo combo/autocomplete deve navegar e selecionar pelo teclado.
        (function(){
            var inp = document.getElementById('taocot-ret-busca');
            if(!inp) return;
            var box = inp.closest('.taocot-combo'), list = box.querySelector('.taocot-combo-list'), timer=null;
            var results=[], sel=-1;
            function render(){
                list.innerHTML='';
                results.forEach(function(f,i){
                    var d=document.createElement('div');
                    d.className='taocot-opt'+(i===sel?' sel':'');
                    d.innerHTML='<strong></strong><span class="cod"></span>';
                    d.querySelector('strong').textContent=f.nome;
                    d.querySelector('.cod').textContent=f.contato?(' · '+f.contato):'';
                    d.addEventListener('mousedown', function(ev){ ev.preventDefault(); pick(i); });
                    d.addEventListener('mousemove', function(){ if(sel!==i){ sel=i; paint(); } });
                    list.appendChild(d);
                });
                list.style.display=results.length?'block':'none';
            }
            function paint(){ Array.prototype.forEach.call(list.children,function(c,i){ c.className='taocot-opt'+(i===sel?' sel':''); }); var el=list.children[sel]; if(el&&el.scrollIntoView) el.scrollIntoView({block:'nearest'}); }
            function pick(i){ var f=results[i]; if(!f) return; list.style.display='none'; results=[]; sel=-1; retEscolher(f.id, f.nome); }
            inp.addEventListener('input', function(){
                clearTimeout(timer);
                var q = inp.value.trim();
                if(q.length < 2){ list.style.display='none'; list.innerHTML=''; results=[]; sel=-1; return; }
                timer = setTimeout(function(){
                    C.post('tao_cot_search_fornecedores', { q:q }).then(function(r){
                        results = r.success ? (r.data||[]) : [];
                        sel = results.length ? 0 : -1;
                        render();
                    });
                }, 280);
            });
            inp.addEventListener('keydown', function(e){
                if(list.style.display==='none' || !results.length) return;
                if(e.key==='ArrowDown'){ e.preventDefault(); sel=Math.min(sel+1,results.length-1); paint(); }
                else if(e.key==='ArrowUp'){ e.preventDefault(); sel=Math.max(sel-1,0); paint(); }
                else if(e.key==='Enter'){ e.preventDefault(); if(sel>=0) pick(sel); }
                else if(e.key==='Escape'){ list.style.display='none'; }
            });
            inp.addEventListener('blur', function(){ setTimeout(function(){ list.style.display='none'; }, 180); });
        })();
    })();
    </script>
    <?php
}
