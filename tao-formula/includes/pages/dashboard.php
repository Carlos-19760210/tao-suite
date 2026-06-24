<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_dashboard() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();
    $hoje_str   = ( new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') ) )->format('Y-m-d');
    $pendentes = $hoje = $aprovados = $total_mes = 0;
    $recentes  = [];

    if ( $cliente_id ) {
        $r    = tao_formula_api( "/orcamentos?cliente_id=eq.$cliente_id&select=id,status,criado_em,nome_paciente,total_orcamento,forma_nome&order=criado_em.desc&limit=200" );
        $orcs = $r['ok'] ? ( $r['data'] ?? [] ) : [];
        $mes  = ( new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') ) )->format('Y-m');

        foreach ( $orcs as $o ) {
            if ( substr( $o['criado_em'] ?? '', 0, 10 ) === $hoje_str ) $hoje++;
            if ( ( $o['status'] ?? '' ) === 'pendente_revisao' ) $pendentes++;
            if ( ( $o['status'] ?? '' ) === 'aprovado_farma' )   $aprovados++;
            if ( substr( $o['criado_em'] ?? '', 0, 7 ) === $mes )
                $total_mes += (float)( $o['total_orcamento'] ?? 0 );
        }
        $recentes = array_slice( $orcs, 0, 8 );
    }

    $url_orc  = tao_formula_url( 'formula-orcamentos' );
    $url_form = tao_formula_url( 'formula-formas' );
    ?>
    <div class="wrap taof-wrap">
    <h1>&#x1F9EA; Fórmulas</h1>

    <div class="taof-kpi-row">
        <div class="taof-kpi taof-kpi-warn">
            <span class="taof-kpi-label">Pendentes revisão</span>
            <span class="taof-kpi-value"><?php echo $pendentes; ?></span>
            <span class="taof-kpi-sub"><a href="<?php echo esc_url($url_orc.'&status=pendente_revisao'); ?>">Ver fila →</a></span>
        </div>
        <div class="taof-kpi">
            <span class="taof-kpi-label">Orçamentos hoje</span>
            <span class="taof-kpi-value"><?php echo $hoje; ?></span>
            <span class="taof-kpi-sub">novos pedidos</span>
        </div>
        <div class="taof-kpi taof-kpi-green">
            <span class="taof-kpi-label">Aprovados</span>
            <span class="taof-kpi-value"><?php echo $aprovados; ?></span>
            <span class="taof-kpi-sub">prontos p/ envio</span>
        </div>
        <div class="taof-kpi taof-kpi-amber">
            <span class="taof-kpi-label">Volume este mês</span>
            <span class="taof-kpi-value">R$&nbsp;<?php echo number_format($total_mes, 0, ',', '.'); ?></span>
            <span class="taof-kpi-sub">total orçado</span>
        </div>
    </div>

    <div style="display:flex;gap:16px;margin-top:24px;flex-wrap:wrap">
        <a href="<?php echo esc_url($url_orc); ?>" class="button button-primary button-large">📋 Ver Orçamentos</a>
        <a href="<?php echo esc_url($url_form); ?>" class="button button-large">⚗️ Formas Farmacêuticas</a>
    </div>

    <?php if ( ! empty($recentes) ) : ?>
    <h2 style="margin-top:28px">Orçamentos recentes</h2>
    <table class="wp-list-table widefat fixed striped taof-table" style="max-width:900px">
        <thead><tr><th>Paciente</th><th>Forma</th><th style="text-align:right">Total</th><th>Status</th><th>Data</th></tr></thead>
        <tbody>
        <?php
        $st_map = [
            'pendente_revisao' => ['⏳ Pendente',  '#fef3c7','#92400e'],
            'aprovado_farma'   => ['✅ Aprovado',   '#dcfce7','#166534'],
            'enviado_paciente' => ['📤 Enviado',    '#dbeafe','#1d4ed8'],
            'aceito_paciente'  => ['🎉 Aceito',     '#dcfce7','#166534'],
            'rejeitado'        => ['❌ Rejeitado',  '#fee2e2','#991b1b'],
        ];
        foreach ( $recentes as $o ) :
            $st  = $o['status'] ?? 'pendente_revisao';
            $stl = $st_map[$st] ?? [$st,'#f1f5f9','#475569'];
            $dt  = ! empty($o['criado_em']) ? wp_date('d/m H:i', strtotime($o['criado_em'])) : '—';
        ?>
        <tr>
            <td><?php echo esc_html($o['nome_paciente']??'—'); ?></td>
            <td><?php echo esc_html($o['forma_nome']??'—'); ?></td>
            <td style="text-align:right">R$&nbsp;<?php echo number_format((float)($o['total_orcamento']??0),2,',','.'); ?></td>
            <td><span style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:20px;background:<?php echo esc_attr($stl[1]); ?>;color:<?php echo esc_attr($stl[2]); ?>"><?php echo esc_html($stl[0]); ?></span></td>
            <td style="font-size:12px;color:#64748b"><?php echo esc_html($dt); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
    <?php
}
