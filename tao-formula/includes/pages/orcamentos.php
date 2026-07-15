<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_orcamentos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id  = tao_formula_cliente_id();
    $filtro_st   = sanitize_text_field( $_GET['status'] ?? '' );
    $filtro_de   = sanitize_text_field( $_GET['de'] ?? '' );
    $filtro_ate  = sanitize_text_field( $_GET['ate'] ?? '' );
    $orcamentos  = [];
    $size        = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $off         = max( 0, intval( $_GET['off'] ?? 0 ) );
    $total_orc   = 0;

    $status_opts = [
        ''                  => 'Todos',
        'pendente_revisao'  => 'Pendentes',
        'aprovado_farma'    => 'Aprovados',
        'enviado_paciente'  => 'Enviados',
        'aceito_paciente'   => 'Aceitos',
        'rejeitado'         => 'Rejeitados',
    ];

    if ( $cliente_id ) {
        $qs = "/orcamentos?cliente_id=eq.$cliente_id&select=id,status,criado_em,nome_paciente,whatsapp,forma_nome,total_orcamento,farmaceutico_id,numero_orcamento,receita_url,medicamento_controlado,aprovado_em,motivo_rejeicao&order=criado_em.desc&limit=$size&offset=$off";
        if ( $filtro_st ) $qs .= "&status=eq.$filtro_st";
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filtro_de ) )  $qs .= '&criado_em=gte.' . $filtro_de . 'T00:00:00';
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filtro_ate ) ) $qs .= '&criado_em=lte.' . $filtro_ate . 'T23:59:59';
        $r          = tao_formula_api( $qs, 'GET', null, true );
        $orcamentos = $r['ok'] ? ( $r['data'] ?? [] ) : [];
        $total_orc  = $r['ok'] ? (int) $r['total'] : 0;
    }

    $st_map = [
        'pendente_revisao' => ['Pendente',  '#fef3c7','#92400e', '⏳'],
        'aprovado_farma'   => ['Aprovado',  '#dcfce7','#166534', '✅'],
        'enviado_paciente' => ['Enviado',   '#dbeafe','#1d4ed8', '📤'],
        'aceito_paciente'  => ['Aceito',    '#dcfce7','#166534', '🎉'],
        'rejeitado'        => ['Rejeitado', '#fee2e2','#991b1b', '❌'],
    ];

    $base_url = tao_formula_url( 'formula-orcamentos' );
    $novo_url = tao_formula_url( 'formula-novo-orc' );
    ?>
    <div class="taof-wrap">

    <!-- ── Cabeçalho ─────────────────────────────────────────────────── -->
    <div class="taof-pg-hdr">
        <h1 class="taof-pg-title">📋 Orçamentos</h1>
        <div style="display:flex;gap:8px;align-items:center">
            <button type="button" id="taof-orc-import-receita" class="taof-btn" title="Enviar foto/PDF de uma receita — a IA interpreta e cria os orçamentos">📄 Importar receita</button>
            <input type="file" id="taof-orc-receita-file" accept="image/*,.pdf" style="display:none">
            <a href="<?php echo esc_url($novo_url); ?>" class="taof-btn taof-btn-primary">+ Novo Orçamento</a>
        </div>
    </div>

    <?php $per = array_filter( [ 'de' => $filtro_de, 'ate' => $filtro_ate ] ); ?>
    <!-- ── Filtros de status ──────────────────────────────────────────── -->
    <div class="taof-filter-bar">
    <?php foreach ( $status_opts as $val => $lbl ) :
        $url    = add_query_arg( array_merge( $per, $val ? [ 'status' => $val ] : [] ), $base_url );
        $active = $filtro_st === $val ? ' taof-filter-active' : '';
    ?>
        <a href="<?php echo esc_url($url); ?>" class="taof-filter-tab<?php echo $active; ?>"><?php echo esc_html($lbl); ?></a>
    <?php endforeach; ?>
    </div>

    <!-- ── Filtro por período ─────────────────────────────────────────── -->
    <form method="get" action="<?php echo esc_url($base_url); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 14px;font-size:13px;color:#475569">
        <?php global $cbpm_is_frontend; if ( empty($cbpm_is_frontend) ) : ?><input type="hidden" name="page" value="tao-formula-orcamentos"><?php endif; ?>
        <?php if ( $filtro_st ) : ?><input type="hidden" name="status" value="<?php echo esc_attr($filtro_st); ?>"><?php endif; ?>
        <label>De <input type="date" name="de" value="<?php echo esc_attr($filtro_de); ?>" style="padding:3px 6px"></label>
        <label>Até <input type="date" name="ate" value="<?php echo esc_attr($filtro_ate); ?>" style="padding:3px 6px"></label>
        <button type="submit" class="taof-btn taof-btn-sm">Filtrar período</button>
        <?php if ( $filtro_de || $filtro_ate ) : ?>
        <a href="<?php echo esc_url( $filtro_st ? add_query_arg( 'status', $filtro_st, $base_url ) : $base_url ); ?>" class="taof-btn taof-btn-sm">✕ Limpar período</a>
        <?php endif; ?>
    </form>

    <?php if ( empty($orcamentos) ) : ?>
        <div class="taof-empty-state"><p>Nenhum orçamento encontrado.</p></div>
    <?php else : ?>

    <!-- ── Tabela ────────────────────────────────────────────────────── -->
    <div class="taof-table-wrap">
    <table class="taof-list-table">
        <thead>
            <tr>
                <th>Requisição</th>
                <th>Paciente</th>
                <th>WhatsApp</th>
                <th>Forma</th>
                <th class="taof-col-r">Total</th>
                <th>Status</th>
                <th>Data</th>
                <th class="taof-col-c">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $orcamentos as $o ) :
            $st  = $o['status'] ?? 'pendente_revisao';
            $stl = $st_map[$st] ?? [$st,'#f1f5f9','#475569',''];
            $dt  = ! empty($o['criado_em']) ? wp_date('d/m/Y H:i', strtotime($o['criado_em'])) : '—';
            $_np = explode( '-', preg_replace( '/^ORC:?\s*/i', '', (string)($o['numero_orcamento'] ?? '') ) );
            $req = count($_np) >= 2 ? $_np[1] : '';
        ?>
        <tr>
            <td style="font-weight:600"><?php echo $req !== '' ? esc_html($req) : '<span style="color:#cbd5e1">—</span>'; ?></td>
            <td class="taof-td-nome"><?php echo esc_html($o['nome_paciente']??'—'); ?></td>
            <td class="taof-td-fone"><?php echo esc_html($o['whatsapp']??'—'); ?></td>
            <td><?php echo esc_html($o['forma_nome']??'—'); ?></td>
            <td class="taof-col-r taof-td-total">R$&nbsp;<?php echo number_format((float)($o['total_orcamento']??0),2,',','.'); ?></td>
            <td>
                <span class="taof-badge" style="background:<?php echo esc_attr($stl[1]); ?>;color:<?php echo esc_attr($stl[2]); ?>"<?php
                    echo ( $st === 'rejeitado' && ! empty($o['motivo_rejeicao']) ) ? ' title="Motivo: '.esc_attr($o['motivo_rejeicao']).'"' : ''; ?>>
                    <?php echo esc_html($stl[3].' '.$stl[0]); ?>
                </span>
                <?php
                if ( ! empty( $o['medicamento_controlado'] ) ) {
                    echo '<span class="taof-badge" style="background:#fee2e2;color:#991b1b" title="Medicamento sujeito a controle especial (Portaria 344/98)">🔒 Controlado</span>';
                }
                if ( ! empty( $o['receita_url'] ) ) {
                    echo '<a class="taof-badge" style="background:#e0e7ff;color:#3730a3;text-decoration:none" target="_blank" rel="noopener" href="'.esc_url($o['receita_url']).'" title="Ver receita">📄 Receita</a>';
                }
                if ( $st === 'aprovado_farma' && ! empty($o['aprovado_em']) ) {
                    $_ap_nome = '';
                    if ( ! empty($o['farmaceutico_id']) && ( $_u = get_userdata( (int)$o['farmaceutico_id'] ) ) ) $_ap_nome = $_u->display_name;
                    echo '<div class="taof-aprov-info" style="font-size:11px;color:#166534;margin-top:3px">✔ '
                        . esc_html( $_ap_nome ? $_ap_nome.' · ' : '' ) . esc_html( wp_date('d/m H:i', strtotime($o['aprovado_em'])) ) . '</div>';
                }
                ?>
            </td>
            <td class="taof-td-dt"><?php echo esc_html($dt); ?></td>
            <td class="taof-col-c taof-td-acoes">
                <a class="taof-btn taof-btn-sm" href="<?php echo esc_url( add_query_arg( 'orc_id', $o['id'], $novo_url ) ); ?>" title="Abrir/editar fórmula">✎ Editar</a>
                <?php if ( $st === 'pendente_revisao' ) : ?>
                <button class="taof-btn taof-btn-sm taof-btn-primary taof-orc-aprovar"
                        data-id="<?php echo esc_attr($o['id']); ?>">✅ Aprovar e Enviar</button>
                <button class="taof-btn taof-btn-sm taof-btn-danger taof-orc-rejeitar"
                        data-id="<?php echo esc_attr($o['id']); ?>">❌ Rejeitar</button>
                <?php else : ?>
                <span class="taof-td-sem-acao">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
    $paginas = max( 1, (int) ceil( $total_orc / $size ) );
    $pg_cur  = (int) floor( $off / $size ) + 1;
    $pg_base = array_filter( [ 'status' => $filtro_st, 'de' => $filtro_de, 'ate' => $filtro_ate ] );
    $prev_off = max( 0, $off - $size );
    $next_off = $off + $size;
    ?>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:14px 0;font-size:13px;flex-wrap:wrap">
        <label>Itens por página:
            <select onchange="location.href=this.value" style="padding:3px 6px">
                <?php foreach ( [ 20, 30, 50 ] as $s ) :
                    $u = esc_url( add_query_arg( $pg_base + [ 'size' => $s, 'off' => 0 ], $base_url ) ); ?>
                    <option value="<?php echo $u; ?>"<?php selected( $size, $s ); ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ( $pg_cur > 1 ) : ?>
            <a class="taof-btn taof-btn-sm" href="<?php echo esc_url( add_query_arg( $pg_base + [ 'size' => $size, 'off' => $prev_off ], $base_url ) ); ?>">‹ Anterior</a>
        <?php else : ?>
            <span class="taof-btn taof-btn-sm" style="opacity:.5;pointer-events:none">‹ Anterior</span>
        <?php endif; ?>
        <span style="color:#64748b">Página <?php echo $pg_cur; ?> de <?php echo $paginas; ?></span>
        <?php if ( $pg_cur < $paginas ) : ?>
            <a class="taof-btn taof-btn-sm" href="<?php echo esc_url( add_query_arg( $pg_base + [ 'size' => $size, 'off' => $next_off ], $base_url ) ); ?>">Próxima ›</a>
        <?php else : ?>
            <span class="taof-btn taof-btn-sm" style="opacity:.5;pointer-events:none">Próxima ›</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    </div>

    <script>
    (function($){
        var ajaxUrl = (typeof taoFormula !== 'undefined') ? taoFormula.ajaxUrl : '/wp-admin/admin-ajax.php';
        var nonce   = (typeof taoFormula !== 'undefined') ? taoFormula.nonce : '';

        function updateStatus(id, status, $btn, extra) {
            var txt = $btn.text();
            $btn.prop('disabled', true).text('...');
            var payload = $.extend({ action:'tao_formula_update_orc_status', nonce:nonce, id:id, status:status }, extra||{});
            $.post(ajaxUrl, payload, function(r) {
                if (r.success) location.reload();
                else { alert('Erro: ' + (r.data||'?')); $btn.prop('disabled',false).text(txt); }
            });
        }

        $(document).on('click', '.taof-orc-aprovar', function() {
            if (!confirm('Atesto que avaliei a prescrição e a fórmula está farmacotecnicamente adequada para manipulação.\n\nAo aprovar, o orçamento é liberado para envio ao paciente. Confirmar?')) return;
            updateStatus($(this).data('id'), 'aprovado_farma', $(this));
        });
        $(document).on('click', '.taof-orc-rejeitar', function() {
            var motivo = prompt('Motivo da rejeição (obrigatório):', '');
            if (motivo === null) return;
            motivo = (motivo || '').trim();
            if (!motivo) { alert('É necessário informar o motivo da rejeição.'); return; }
            updateStatus($(this).data('id'), 'rejeitado', $(this), { motivo: motivo });
        });

        // Importar receita — a IA interpreta o PDF/foto e cria os orçamentos (mesmo motor do card)
        var $imp = $('#taof-orc-import-receita'), $impFile = $('#taof-orc-receita-file');
        $imp.on('click', function(){ $impFile.trigger('click'); });
        $impFile.on('change', function(){
            var f = this.files[0]; this.value = ''; if(!f) return;
            var t0 = $imp.text();
            $imp.prop('disabled', true).text('🤖 Analisando receita…');
            var fd = new FormData();
            fd.append('action', 'tao_formula_processar_receita');
            fd.append('nonce', nonce);
            fd.append('receita_file', f);
            fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    $imp.prop('disabled', false).text(t0);
                    if(resp.success){
                        var d = resp.data || {};
                        var nums = (d.orcamentos||[]).map(function(o){ return o.numero; }).join(', ');
                        var msg = '✅ ' + ((d.orcamentos && d.orcamentos.length) || 0) + ' orçamento(s) criado(s)' + (nums ? ': '+nums : '') + '.';
                        if(d.nao_encontrados && d.nao_encontrados.length) msg += '\n⚠ Ativos a revisar na edição: ' + d.nao_encontrados.join(', ');
                        alert(msg); location.reload();
                    } else {
                        alert('❌ ' + ((resp.data && resp.data.message) || 'Não foi possível interpretar a receita.'));
                    }
                })
                .catch(function(){ $imp.prop('disabled', false).text(t0); alert('❌ Falha de comunicação ao processar a receita.'); });
        });
    })(jQuery);
    </script>
    <?php
}
