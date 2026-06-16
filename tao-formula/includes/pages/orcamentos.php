<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_orcamentos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id  = tao_formula_cliente_id();
    $filtro_st   = sanitize_text_field( $_GET['status'] ?? '' );
    $orcamentos  = [];

    $status_opts = [
        ''                  => 'Todos',
        'pendente_revisao'  => '⏳ Pendentes',
        'aprovado_farma'    => '✅ Aprovados',
        'enviado_paciente'  => '📤 Enviados',
        'aceito_paciente'   => '🎉 Aceitos',
        'rejeitado'         => '❌ Rejeitados',
    ];

    if ( $cliente_id ) {
        $qs = "/orcamentos?cliente_id=eq.$cliente_id&select=id,status,criado_em,nome_paciente,whatsapp,forma_nome,total_orcamento,farmaceutico_id&order=criado_em.desc&limit=100";
        if ( $filtro_st ) $qs .= "&status=eq.$filtro_st";
        $r          = tao_formula_api( $qs );
        $orcamentos = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    }

    $st_map = [
        'pendente_revisao' => ['⏳ Pendente',  '#fef3c7','#92400e'],
        'aprovado_farma'   => ['✅ Aprovado',   '#dcfce7','#166534'],
        'enviado_paciente' => ['📤 Enviado',    '#dbeafe','#1d4ed8'],
        'aceito_paciente'  => ['🎉 Aceito',     '#dcfce7','#166534'],
        'rejeitado'        => ['❌ Rejeitado',  '#fee2e2','#991b1b'],
    ];

    $base_url = tao_formula_url( 'formula-orcamentos' );
    $novo_url = tao_formula_url( 'formula-novo-orc' );
    ?>
    <div class="wrap taof-wrap">
    <h1 class="wp-heading-inline">📋 Orçamentos</h1>
    <a href="<?php echo esc_url($novo_url); ?>" class="page-title-action button button-primary">+ Novo Orçamento</a>
    <hr class="wp-header-end">

    <!-- Filtro de status -->
    <ul class="subsubsub" style="margin-bottom:12px">
    <?php foreach ( $status_opts as $val => $lbl ) :
        $qs_url = $val ? add_query_arg('status', $val, $base_url) : $base_url;
        $active = $filtro_st === $val ? ' style="font-weight:700"' : '';
    ?>
        <li><a href="<?php echo esc_url($qs_url); ?>"<?php echo $active; ?>><?php echo esc_html($lbl); ?></a><?php echo $val !== 'rejeitado' ? ' |' : ''; ?></li>
    <?php endforeach; ?>
    </ul>

    <?php if ( empty($orcamentos) ) : ?>
        <div class="taof-empty-state"><p>Nenhum orçamento encontrado.</p></div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped taof-table">
        <thead>
            <tr>
                <th>Paciente</th>
                <th>WhatsApp</th>
                <th>Forma</th>
                <th style="text-align:right">Total</th>
                <th>Status</th>
                <th>Data</th>
                <th style="text-align:center">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $orcamentos as $o ) :
            $st  = $o['status'] ?? 'pendente_revisao';
            $stl = $st_map[$st] ?? [$st,'#f1f5f9','#475569'];
            $dt  = ! empty($o['criado_em']) ? wp_date('d/m/Y H:i', strtotime($o['criado_em'])) : '—';
        ?>
        <tr>
            <td><?php echo esc_html($o['nome_paciente']??'—'); ?></td>
            <td><?php echo esc_html($o['whatsapp']??'—'); ?></td>
            <td><?php echo esc_html($o['forma_nome']??'—'); ?></td>
            <td style="text-align:right">R$&nbsp;<?php echo number_format((float)($o['total_orcamento']??0),2,',','.'); ?></td>
            <td><span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;background:<?php echo esc_attr($stl[1]); ?>;color:<?php echo esc_attr($stl[2]); ?>"><?php echo esc_html($stl[0]); ?></span></td>
            <td style="font-size:12px;color:#64748b"><?php echo esc_html($dt); ?></td>
            <td style="text-align:center">
                <?php if ( $st === 'pendente_revisao' ) : ?>
                <button class="button button-small button-primary taof-orc-aprovar"
                        data-id="<?php echo esc_attr($o['id']); ?>">✅ Aprovar</button>
                <button class="button button-small taof-orc-rejeitar"
                        data-id="<?php echo esc_attr($o['id']); ?>" style="color:#b91c1c">❌ Rejeitar</button>
                <?php elseif ( $st === 'aprovado_farma' ) : ?>
                <button class="button button-small taof-orc-enviar"
                        data-id="<?php echo esc_attr($o['id']); ?>">📤 Marcar Enviado</button>
                <?php else : ?>
                <span style="font-size:11px;color:#94a3b8">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
    <?php
}
