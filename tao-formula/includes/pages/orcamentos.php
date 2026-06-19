<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_orcamentos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id  = tao_formula_cliente_id();
    $filtro_st   = sanitize_text_field( $_GET['status'] ?? '' );
    $orcamentos  = [];

    $status_opts = [
        ''                  => 'Todos',
        'pendente_revisao'  => 'Pendentes',
        'aprovado_farma'    => 'Aprovados',
        'enviado_paciente'  => 'Enviados',
        'aceito_paciente'   => 'Aceitos',
        'rejeitado'         => 'Rejeitados',
    ];

    if ( $cliente_id ) {
        $qs = "/orcamentos?cliente_id=eq.$cliente_id&select=id,status,criado_em,nome_paciente,whatsapp,forma_nome,total_orcamento,farmaceutico_id&order=criado_em.desc&limit=100";
        if ( $filtro_st ) $qs .= "&status=eq.$filtro_st";
        $r          = tao_formula_api( $qs );
        $orcamentos = $r['ok'] ? ( $r['data'] ?? [] ) : [];
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
        <a href="<?php echo esc_url($novo_url); ?>" class="taof-btn taof-btn-primary">+ Novo Orçamento</a>
    </div>

    <!-- ── Filtros de status ──────────────────────────────────────────── -->
    <div class="taof-filter-bar">
    <?php foreach ( $status_opts as $val => $lbl ) :
        $url    = $val ? add_query_arg('status', $val, $base_url) : $base_url;
        $active = $filtro_st === $val ? ' taof-filter-active' : '';
    ?>
        <a href="<?php echo esc_url($url); ?>" class="taof-filter-tab<?php echo $active; ?>"><?php echo esc_html($lbl); ?></a>
    <?php endforeach; ?>
    </div>

    <?php if ( empty($orcamentos) ) : ?>
        <div class="taof-empty-state"><p>Nenhum orçamento encontrado.</p></div>
    <?php else : ?>

    <!-- ── Tabela ────────────────────────────────────────────────────── -->
    <div class="taof-table-wrap">
    <table class="taof-list-table">
        <thead>
            <tr>
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
        ?>
        <tr>
            <td class="taof-td-nome"><?php echo esc_html($o['nome_paciente']??'—'); ?></td>
            <td class="taof-td-fone"><?php echo esc_html($o['whatsapp']??'—'); ?></td>
            <td><?php echo esc_html($o['forma_nome']??'—'); ?></td>
            <td class="taof-col-r taof-td-total">R$&nbsp;<?php echo number_format((float)($o['total_orcamento']??0),2,',','.'); ?></td>
            <td>
                <span class="taof-badge" style="background:<?php echo esc_attr($stl[1]); ?>;color:<?php echo esc_attr($stl[2]); ?>">
                    <?php echo esc_html($stl[3].' '.$stl[0]); ?>
                </span>
            </td>
            <td class="taof-td-dt"><?php echo esc_html($dt); ?></td>
            <td class="taof-col-c taof-td-acoes">
                <?php if ( $st === 'pendente_revisao' ) : ?>
                <button class="taof-btn taof-btn-sm taof-btn-primary taof-orc-aprovar"
                        data-id="<?php echo esc_attr($o['id']); ?>">✅ Aprovar</button>
                <button class="taof-btn taof-btn-sm taof-btn-danger taof-orc-rejeitar"
                        data-id="<?php echo esc_attr($o['id']); ?>">❌ Rejeitar</button>
                <?php elseif ( $st === 'aprovado_farma' ) : ?>
                <button class="taof-btn taof-btn-sm taof-orc-enviar"
                        data-id="<?php echo esc_attr($o['id']); ?>">📤 Marcar Enviado</button>
                <?php else : ?>
                <span class="taof-td-sem-acao">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    </div>

    <script>
    (function($){
        var ajaxUrl = (typeof taoFormula !== 'undefined') ? taoFormula.ajaxUrl : '/wp-admin/admin-ajax.php';
        var nonce   = (typeof taoFormula !== 'undefined') ? taoFormula.nonce : '';

        function updateStatus(id, status, $btn) {
            $btn.prop('disabled', true).text('...');
            $.post(ajaxUrl, { action:'tao_formula_update_orc_status', nonce:nonce, id:id, status:status },
            function(r) {
                if (r.success) location.reload();
                else { alert('Erro: ' + (r.data||'?')); $btn.prop('disabled',false); }
            });
        }

        $(document).on('click', '.taof-orc-aprovar', function() {
            if (!confirm('Aprovar este orçamento?')) return;
            updateStatus($(this).data('id'), 'aprovado_farma', $(this));
        });
        $(document).on('click', '.taof-orc-rejeitar', function() {
            if (!confirm('Rejeitar este orçamento?')) return;
            updateStatus($(this).data('id'), 'rejeitado', $(this));
        });
        $(document).on('click', '.taof-orc-enviar', function() {
            updateStatus($(this).data('id'), 'enviado_paciente', $(this));
        });
    })(jQuery);
    </script>
    <?php
}
