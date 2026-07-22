<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Painel do módulo Fórmulas — indicadores gerenciais.
 *
 * Situação da fórmula = situação do CARD do CRM vinculado (NÃO o orcamentos.status,
 * reservado ao gate de revisão do farmacêutico). Espelha o dashboard do CRM:
 *   aprovada   = card que ENTROU no funil de Pós-vendas (ou estágio ganho) DENTRO do período
 *   reprovada  = card que entrou em estágio 'perdido' DENTRO do período
 *   negociacao = card em aberto AGORA (snapshot) — e orçamentos sem card
 *
 * aprovada/reprovada são por EVENTO no período (via crm_cards_historico), igual o CRM
 * mede ganhos/perdidos; "negociação" é o retrato atual ("Em aberto agora" do CRM) e por
 * isso não muda com o período. "Fórmulas orçadas" conta as criadas no período.
 */

/** Valor efetivo do orçamento: usa o valor final do Formula Certa quando houver, senão o total calculado. */
function tao_formula_valor_efetivo( $o ) {
    $fc = (float) ( $o['valor_final_fc'] ?? 0 );
    return $fc > 0 ? $fc : (float) ( $o['total_orcamento'] ?? 0 );
}

/** Formata gramas: usa kg quando >= 1000 g. */
function tao_formula_fmt_g( $g ) {
    $g = (float) $g;
    if ( $g >= 1000 ) return number_format( $g / 1000, 3, ',', '.' ) . ' kg';
    return number_format( $g, $g < 10 ? 3 : 1, ',', '.' ) . ' g';
}

/**
 * Conjuntos de situação dos cards do cliente, no padrão do dashboard do CRM.
 * ganho/perdido = entrada no estágio dentro do período (evento, crm_cards_historico);
 * aberto = cards em aberto AGORA (snapshot). Somente leitura de tabelas do CRM.
 *
 * @return array [ 'ganho'=>[card_id=>true], 'perdido'=>[card_id=>true], 'aberto'=>[card_id=>true] ]
 */
function tao_formula_situacao_cards( $cliente_id, $desde, $ate_ts = null ) {
    $vazio = [ 'ganho' => [], 'perdido' => [], 'aberto' => [] ];

    $rw    = tao_formula_api( "/crm_workspaces?cliente_id=eq.$cliente_id&select=id&limit=1" );
    $ws_id = ( $rw['ok'] && ! empty( $rw['data'] ) ) ? $rw['data'][0]['id'] : '';
    if ( ! $ws_id ) return $vazio;

    // Pipelines do workspace + identificação do funil de Pós-vendas
    $pr  = tao_formula_api( "/crm_pipelines?workspace_id=eq.$ws_id&order=ordem.asc&select=id,ativo" );
    $pls = $pr['ok'] ? ( $pr['data'] ?? [] ) : [];
    $pipe_ids = array_column( $pls, 'id' );
    if ( empty( $pipe_ids ) ) return $vazio;

    $pos_pl_id = get_option( 'tao_crm_pos_vendas_pipeline_' . $ws_id, '' );
    if ( ! $pos_pl_id ) {
        $ativos_pl = array_values( array_filter( $pls, fn( $p ) => ! empty( $p['ativo'] ) ) );
        if ( count( $ativos_pl ) >= 2 ) $pos_pl_id = $ativos_pl[1]['id'];   // heurística: 2º pipeline ativo
    }

    // Estágios → conjuntos (pós-vendas, vendas, ganho, perdido)
    $er = tao_formula_api( "/crm_estagios?pipeline_id=in.(" . implode( ',', $pipe_ids ) . ")&select=id,tipo,pipeline_id" );
    $pos_set = $vendas_set = $ganho_set = $perd_set = [];
    foreach ( ( $er['ok'] ? ( $er['data'] ?? [] ) : [] ) as $e ) {
        $eid = $e['id'];
        if ( $pos_pl_id && $e['pipeline_id'] === $pos_pl_id ) $pos_set[ $eid ] = true;
        else                                                  $vendas_set[ $eid ] = true;
        if ( ( $e['tipo'] ?? '' ) === 'ganho' )   $ganho_set[ $eid ] = true;
        if ( ( $e['tipo'] ?? '' ) === 'perdido' ) $perd_set[ $eid ] = true;
    }

    // Eventos no período: entrada em pós-vendas (vindo de Vendas) / ganho / perdido
    $ganho = $perdido = [];
    $para_in = array_keys( $pos_set + $ganho_set + $perd_set );
    if ( $para_in ) {
        $hr = tao_formula_api(
            "/crm_cards_historico?para_estagio_id=in.(" . implode( ',', $para_in ) . ")"
            . "&criado_em=gte." . urlencode( $desde )
            . ( $ate_ts ? "&criado_em=lte." . urlencode( $ate_ts ) : "" )
            . "&select=card_id,de_estagio_id,para_estagio_id&limit=10000"
        );
        foreach ( ( $hr['ok'] ? ( $hr['data'] ?? [] ) : [] ) as $h ) {
            $cid = $h['card_id'] ?? ''; if ( ! $cid ) continue;
            $para = $h['para_estagio_id'] ?? ''; $de = $h['de_estagio_id'] ?? '';
            $is_ganho = ( isset( $pos_set[ $para ] ) && isset( $vendas_set[ $de ] ) ) || isset( $ganho_set[ $para ] );
            if ( $is_ganho )                   $ganho[ $cid ]   = true;
            if ( isset( $perd_set[ $para ] ) ) $perdido[ $cid ] = true;
        }
    }

    // Abertos agora (snapshot)
    $aberto = [];
    $ar = tao_formula_api( "/crm_cards?workspace_id=eq.$ws_id&fechado=eq.false&select=id&limit=5000" );
    foreach ( ( $ar['ok'] ? ( $ar['data'] ?? [] ) : [] ) as $c ) $aberto[ $c['id'] ] = true;

    return [ 'ganho' => $ganho, 'perdido' => $perdido, 'aberto' => $aberto ];
}

function tao_formula_page_dashboard() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();

    // ── Período: presets (dias) + Mês corrente (periodo=mes) + intervalo (de/ate) ──
    $periodos = [ 1 => 'Hoje', 7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 180 => '6 meses' ];
    $_tz_sp = new DateTimeZone( 'America/Sao_Paulo' ); $_tz_utc = new DateTimeZone( 'UTC' );
    $_brt = function ( $ymd, $hms ) use ( $_tz_sp, $_tz_utc ) { $d = DateTime::createFromFormat( 'Y-m-d H:i:s', $ymd . ' ' . $hms, $_tz_sp ); if ( ! $d ) return null; $d->setTimezone( $_tz_utc ); return $d->format( 'c' ); };
    $de_g    = sanitize_text_field( $_GET['de']  ?? '' );
    $ate_g   = sanitize_text_field( $_GET['ate'] ?? '' );
    $periodo = sanitize_text_field( $_GET['periodo'] ?? '' );
    $dias    = (int) ( $_GET['dias'] ?? 30 ); if ( ! isset( $periodos[ $dias ] ) ) $dias = 30;
    $ate_ts  = gmdate( 'c' );
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $de_g ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ate_g ) ) {
        $periodo = 'custom';
        $desde   = $_brt( $de_g, '00:00:00' ) ?: gmdate( 'c', strtotime( '-30 days' ) );
        $ate_ts  = $_brt( $ate_g, '23:59:59' ) ?: gmdate( 'c' );
    } elseif ( $periodo === 'mes' ) {
        $_i = new DateTime( 'first day of this month', $_tz_sp ); $_i->setTime( 0, 0, 0 ); $_i->setTimezone( $_tz_utc ); $desde = $_i->format( 'c' );
    } elseif ( $dias === 1 ) {
        $_d0 = new DateTime( 'now', $_tz_sp ); $_d0->setTime( 0, 0, 0 ); $_d0->setTimezone( $_tz_utc ); $desde = $_d0->format( 'c' );
    } else {
        $desde = gmdate( 'c', strtotime( "-{$dias} days" ) );
    }

    // Acumuladores
    $tot   = [ 'aprovada' => 0,   'reprovada' => 0,   'negociacao' => 0 ];   // contagem de fórmulas
    $val   = [ 'aprovada' => 0.0, 'reprovada' => 0.0, 'negociacao' => 0.0 ]; // R$
    $orcadas = 0;
    $ativos  = [];   // chave => [nome, qApr,qRep,qNeg, vApr,vRep,vNeg]
    $formas  = [];   // forma_nome => [qtd, valor]
    $recentes = [];

    if ( $cliente_id ) {
        // Situação dos cards no padrão CRM (ganho/perdido por evento no período; aberto = agora)
        $sit = tao_formula_situacao_cards( $cliente_id, $desde, $ate_ts );

        // Todas as fórmulas do cliente — a classificação por card não depende da data de
        // criação do orçamento; "Fórmulas orçadas" conta separadamente as criadas no período.
        $select = 'id,status,criado_em,nome_paciente,forma_nome,'
                . 'total_orcamento,valor_final_fc,numero_orcamento,card_id,itens';
        $r    = tao_formula_api(
            "/orcamentos?cliente_id=eq.$cliente_id&select=$select&order=criado_em.desc&limit=5000"
        );
        $orcs = $r['ok'] ? ( $r['data'] ?? [] ) : [];

        foreach ( $orcs as $o ) {
            if ( ( $o['criado_em'] ?? '' ) >= $desde && ( $o['criado_em'] ?? '' ) <= $ate_ts ) $orcadas++;

            $cid = $o['card_id'] ?? '';
            if ( isset( $sit['ganho'][ $cid ] ) )                        $bucket = 'aprovada';
            elseif ( isset( $sit['perdido'][ $cid ] ) )                  $bucket = 'reprovada';
            elseif ( $cid === '' || isset( $sit['aberto'][ $cid ] ) )    $bucket = 'negociacao';
            else continue;   // card fechado sem evento no período → fora das 3 situações

            $tot[ $bucket ]++;
            $valor = tao_formula_valor_efetivo( $o );
            $val[ $bucket ] += $valor;

            // Distribuição por forma farmacêutica
            $fnome = trim( (string) ( $o['forma_nome'] ?? '' ) ) ?: '— sem forma —';
            if ( ! isset( $formas[ $fnome ] ) ) $formas[ $fnome ] = [ 'qtd' => 0, 'valor' => 0.0 ];
            $formas[ $fnome ]['qtd']++;
            $formas[ $fnome ]['valor'] += $valor;

            // Ativos: agrega por orçamento (conta a fórmula 1x por ativo; soma todo o volume)
            $itens  = is_array( $o['itens'] ?? null ) ? $o['itens'] : [];
            $vistos = [];
            $sfx = $bucket === 'aprovada' ? 'Apr' : ( $bucket === 'reprovada' ? 'Rep' : 'Neg' );
            foreach ( $itens as $it ) {
                if ( ( $it['tipo'] ?? 'mp' ) !== 'mp' ) continue;   // ignora embalagem
                if ( ! empty( $it['is_qsp'] ) ) continue;            // ignora QSP/excipiente base
                $aid   = $it['ativo_id'] ?? '';
                $aname = trim( (string) ( $it['nome'] ?? $it['nome_prescricao'] ?? '' ) );
                if ( $aid === '' && $aname === '' ) continue;
                $key = $aid !== '' ? 'id:' . $aid : 'nm:' . mb_strtolower( $aname );

                if ( ! isset( $ativos[ $key ] ) ) {
                    $ativos[ $key ] = [ 'nome' => $aname ?: '(sem nome)',
                        'qApr' => 0, 'qRep' => 0, 'qNeg' => 0,
                        'vApr' => 0.0, 'vRep' => 0.0, 'vNeg' => 0.0 ];
                }
                if ( $aname && $ativos[ $key ]['nome'] === '(sem nome)' ) $ativos[ $key ]['nome'] = $aname;

                $ativos[ $key ][ 'v' . $sfx ] += (float) ( $it['qtd_total_g'] ?? 0 );
                if ( empty( $vistos[ $key ] ) ) {            // conta a fórmula uma única vez por ativo
                    $ativos[ $key ][ 'q' . $sfx ]++;
                    $vistos[ $key ] = true;
                }
            }
        }
        $recentes = array_slice( $orcs, 0, 8 );
    }

    // Ordena ativos por nº de fórmulas (mais utilizados), desempate por volume total
    uasort( $ativos, function ( $a, $b ) {
        $qa = $a['qApr'] + $a['qRep'] + $a['qNeg'];
        $qb = $b['qApr'] + $b['qRep'] + $b['qNeg'];
        if ( $qa !== $qb ) return $qb - $qa;
        return ( $b['vApr'] + $b['vRep'] + $b['vNeg'] ) <=> ( $a['vApr'] + $a['vRep'] + $a['vNeg'] );
    } );
    $ativos_top = array_slice( $ativos, 0, 30 );

    // Ordena formas por quantidade
    uasort( $formas, fn( $a, $b ) => $b['qtd'] <=> $a['qtd'] );

    // Dados para os gráficos
    $chart_sit = [
        'labels' => [ 'Aprovadas', 'Em negociação', 'Reprovadas' ],
        'qtd'    => [ $tot['aprovada'], $tot['negociacao'], $tot['reprovada'] ],
    ];
    $chart_forma = [
        'labels' => array_keys( $formas ),
        'qtd'    => array_map( fn( $f ) => $f['qtd'], array_values( $formas ) ),
    ];

    $url_orc     = tao_formula_url( 'formula-orcamentos' );
    $url_form    = tao_formula_url( 'formula-formas' );
    $is_frontend = ! empty( $GLOBALS['cbpm_is_frontend'] );
    $form_action = $is_frontend ? tao_formula_url( 'formula-dashboard' ) : admin_url( 'admin.php' );

    $st_map = [
        'pendente_revisao' => ['⏳ Pendente',  '#fef3c7','#92400e'],
        'aprovado_farma'   => ['✅ Aprovado',   '#dcfce7','#166534'],
        'enviado_paciente' => ['📤 Enviado',    '#dbeafe','#1d4ed8'],
        'aceito_paciente'  => ['🎉 Aceito',     '#dcfce7','#166534'],
        'rejeitado'        => ['❌ Rejeitado',  '#fee2e2','#991b1b'],
    ];
    ?>
    <div class="wrap taof-wrap">

    <div class="taof-dash-hdr" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <h1 style="margin:0">&#x1F9EA; Fórmulas — Painel</h1>
        <form method="get" action="<?php echo esc_url( $form_action ); ?>" class="taof-period-form" style="display:flex;align-items:center;gap:8px">
            <?php if ( ! $is_frontend ) : ?>
            <input type="hidden" name="page" value="tao-formula">
            <?php endif; ?>
            <label style="font-size:13px;color:#64748b">Período:</label>
            <input type="hidden" name="dias"    value="<?php echo esc_attr( $periodo === '' ? $dias : '' ); ?>">
            <input type="hidden" name="periodo" value="<?php echo esc_attr( $periodo === 'mes' ? 'mes' : ( $periodo === 'custom' ? 'custom' : '' ) ); ?>">
            <?php $_sel = $periodo === 'mes' ? 'mes' : ( $periodo === 'custom' ? 'custom' : 'd' . $dias ); ?>
            <select onchange="taofPeriodo(this)" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px">
                <option value="d1"   <?php selected( $_sel, 'd1' ); ?>>Hoje</option>
                <option value="d7"   <?php selected( $_sel, 'd7' ); ?>>7 dias</option>
                <option value="d30"  <?php selected( $_sel, 'd30' ); ?>>30 dias</option>
                <option value="d90"  <?php selected( $_sel, 'd90' ); ?>>90 dias</option>
                <option value="d180" <?php selected( $_sel, 'd180' ); ?>>6 meses</option>
                <option value="mes"  <?php selected( $_sel, 'mes' ); ?>>Mês corrente</option>
                <option value="custom" <?php selected( $_sel, 'custom' ); ?>>Período específico…</option>
            </select>
            <span id="taof-range" style="<?php echo $periodo === 'custom' ? '' : 'display:none'; ?>">
                <input type="date" name="de"  value="<?php echo esc_attr( $de_g ); ?>"  style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                <span style="color:#94a3b8;font-size:12px">até</span>
                <input type="date" name="ate" value="<?php echo esc_attr( $ate_g ); ?>" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                <button type="submit" class="button" style="font-size:12px" onclick="this.form.periodo.value='custom'">Aplicar</button>
            </span>
            <script>
            function taofPeriodo(sel){ var f=sel.form,v=sel.value,r=document.getElementById('taof-range');
                if(v==='custom'){ if(r)r.style.display=''; return; } if(r)r.style.display='none';
                f.de.value='';f.ate.value=''; if(v==='mes'){f.periodo.value='mes';f.dias.value='';} else {f.periodo.value='';f.dias.value=v.substring(1);} f.submit(); }
            </script>
        </form>
    </div>

    <!-- ── KPIs do período ─────────────────────────────────────────────────── -->
    <div class="taof-kpi-row">
        <div class="taof-kpi">
            <span class="taof-kpi-label">Fórmulas orçadas</span>
            <span class="taof-kpi-value"><?php echo $orcadas; ?></span>
            <span class="taof-kpi-sub">no período</span>
        </div>
        <div class="taof-kpi taof-kpi-green">
            <span class="taof-kpi-label">Aprovadas</span>
            <span class="taof-kpi-value"><?php echo $tot['aprovada']; ?></span>
            <span class="taof-kpi-sub">R$&nbsp;<?php echo number_format( $val['aprovada'], 2, ',', '.' ); ?></span>
        </div>
        <div class="taof-kpi taof-kpi-amber">
            <span class="taof-kpi-label">Em negociação</span>
            <span class="taof-kpi-value"><?php echo $tot['negociacao']; ?></span>
            <span class="taof-kpi-sub">R$&nbsp;<?php echo number_format( $val['negociacao'], 2, ',', '.' ); ?></span>
        </div>
        <div class="taof-kpi taof-kpi-warn">
            <span class="taof-kpi-label">Reprovadas</span>
            <span class="taof-kpi-value"><?php echo $tot['reprovada']; ?></span>
            <span class="taof-kpi-sub">R$&nbsp;<?php echo number_format( $val['reprovada'], 2, ',', '.' ); ?></span>
        </div>
    </div>
    <p style="color:#94a3b8;font-size:12px;margin:8px 2px 0">
        Aprovadas e reprovadas = movimentações no período selecionado (mesma régua do CRM). Em negociação = fórmulas com card em aberto no momento.
    </p>

    <?php if ( $orcadas === 0 && array_sum( $tot ) === 0 ) : ?>
        <div class="taof-empty-state" style="margin-top:24px"><p>Nenhum orçamento no período selecionado.</p></div>
    <?php else : ?>

    <!-- ── Gráficos ────────────────────────────────────────────────────────── -->
    <div style="display:flex;gap:20px;margin-top:24px;flex-wrap:wrap">
        <div style="flex:1;min-width:280px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px">
            <h3 style="margin:0 0 10px">Distribuição por situação</h3>
            <div style="position:relative;height:240px"><canvas id="taofChartSit"></canvas></div>
        </div>
        <div style="flex:1;min-width:280px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px">
            <h3 style="margin:0 0 10px">Fórmulas por forma farmacêutica</h3>
            <div style="position:relative;height:240px"><canvas id="taofChartForma"></canvas></div>
        </div>
    </div>

    <!-- ── Distribuição por situação (Qtde e R$) ────────────────────────────── -->
    <h2 style="margin-top:28px">Distribuição por situação</h2>
    <table class="wp-list-table widefat fixed striped taof-table" style="max-width:520px">
        <thead><tr><th>Situação</th><th style="text-align:right">Qtde</th><th style="text-align:right">Valor</th></tr></thead>
        <tbody>
        <?php
        $sit_rows = [
            [ 'Aprovadas',     'aprovada',   '#166534' ],
            [ 'Em negociação', 'negociacao', '#92400e' ],
            [ 'Reprovadas',    'reprovada',  '#991b1b' ],
        ];
        foreach ( $sit_rows as $sr ) : ?>
            <tr>
                <td style="font-weight:600;color:<?php echo $sr[2]; ?>"><?php echo esc_html( $sr[0] ); ?></td>
                <td style="text-align:right;font-weight:600"><?php echo (int) $tot[ $sr[1] ]; ?></td>
                <td style="text-align:right">R$&nbsp;<?php echo number_format( $val[ $sr[1] ], 2, ',', '.' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ── Distribuição por forma farmacêutica ──────────────────────────────── -->
    <h2 style="margin-top:28px">Distribuição por forma farmacêutica</h2>
    <table class="wp-list-table widefat fixed striped taof-table" style="max-width:620px">
        <thead><tr><th>Forma</th><th style="text-align:right">Qtde</th><th style="text-align:right">Valor</th></tr></thead>
        <tbody>
        <?php foreach ( $formas as $fn => $fd ) : ?>
            <tr>
                <td><?php echo esc_html( $fn ); ?></td>
                <td style="text-align:right;font-weight:600"><?php echo (int) $fd['qtd']; ?></td>
                <td style="text-align:right">R$&nbsp;<?php echo number_format( $fd['valor'], 2, ',', '.' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ── Ativos mais utilizados ───────────────────────────────────────────── -->
    <h2 style="margin-top:28px">Ativos mais utilizados</h2>
    <p style="color:#64748b;font-size:13px;margin:-6px 0 10px">
        Volume = quantidade total (massa) do ativo somada nas fórmulas de cada situação. Exclui QSP/excipiente base. Top <?php echo count( $ativos_top ); ?>.
    </p>
    <div class="taof-tscroll" style="overflow-x:auto">
    <table class="wp-list-table widefat fixed striped taof-table" style="min-width:760px">
        <thead><tr>
            <th>Ativo</th>
            <th style="text-align:right" title="Fórmulas aprovadas com o ativo">Fórm. aprov.</th>
            <th style="text-align:right" title="Fórmulas reprovadas com o ativo">Fórm. reprov.</th>
            <th style="text-align:right" title="Fórmulas em negociação com o ativo">Fórm. negoc.</th>
            <th style="text-align:right" title="Volume utilizado em fórmulas aprovadas">Vol. aprov.</th>
            <th style="text-align:right" title="Volume previsto em fórmulas reprovadas">Vol. reprov.</th>
            <th style="text-align:right" title="Volume em fórmulas em negociação">Vol. negoc.</th>
        </tr></thead>
        <tbody>
        <?php foreach ( $ativos_top as $a ) : ?>
            <tr>
                <td style="font-weight:600"><?php echo esc_html( $a['nome'] ); ?></td>
                <td style="text-align:right;color:#166534"><?php echo (int) $a['qApr']; ?></td>
                <td style="text-align:right;color:#991b1b"><?php echo (int) $a['qRep']; ?></td>
                <td style="text-align:right;color:#92400e"><?php echo (int) $a['qNeg']; ?></td>
                <td style="text-align:right;color:#166534"><?php echo $a['vApr'] > 0 ? esc_html( tao_formula_fmt_g( $a['vApr'] ) ) : '—'; ?></td>
                <td style="text-align:right;color:#991b1b"><?php echo $a['vRep'] > 0 ? esc_html( tao_formula_fmt_g( $a['vRep'] ) ) : '—'; ?></td>
                <td style="text-align:right;color:#92400e"><?php echo $a['vNeg'] > 0 ? esc_html( tao_formula_fmt_g( $a['vNeg'] ) ) : '—'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php endif; ?>

    <div style="display:flex;gap:16px;margin-top:24px;flex-wrap:wrap">
        <a href="<?php echo esc_url( $url_orc ); ?>" class="button button-primary button-large">📋 Ver Orçamentos</a>
        <a href="<?php echo esc_url( $url_form ); ?>" class="button button-large">⚗️ Formas Farmacêuticas</a>
    </div>

    <?php if ( ! empty( $recentes ) ) : ?>
    <h2 style="margin-top:28px">Orçamentos recentes</h2>
    <table class="wp-list-table widefat fixed striped taof-table" style="max-width:900px">
        <thead><tr><th>Requisição</th><th>Paciente</th><th>Forma</th><th style="text-align:right">Total</th><th>Status</th><th>Data</th></tr></thead>
        <tbody>
        <?php
        foreach ( $recentes as $o ) :
            $st  = $o['status'] ?? 'pendente_revisao';
            $stl = $st_map[$st] ?? [$st,'#f1f5f9','#475569'];
            $dt  = ! empty($o['criado_em']) ? wp_date('d/m H:i', strtotime($o['criado_em'])) : '—';
            $_np = explode( '-', preg_replace( '/^ORC:?\s*/i', '', (string)($o['numero_orcamento'] ?? '') ) );
            $req = count($_np) >= 2 ? $_np[1] : '';
        ?>
        <tr>
            <td style="font-weight:600"><?php echo $req !== '' ? esc_html($req) : '<span style="color:#cbd5e1">—</span>'; ?></td>
            <td><?php echo esc_html($o['nome_paciente']??'—'); ?></td>
            <td><?php echo esc_html($o['forma_nome']??'—'); ?></td>
            <td style="text-align:right">R$&nbsp;<?php echo number_format( tao_formula_valor_efetivo( $o ),2,',','.'); ?></td>
            <td><span style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:20px;background:<?php echo esc_attr($stl[1]); ?>;color:<?php echo esc_attr($stl[2]); ?>"><?php echo esc_html($stl[0]); ?></span></td>
            <td style="font-size:12px;color:#64748b"><?php echo esc_html($dt); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>

    <?php if ( $orcadas > 0 || array_sum( $tot ) > 0 ) : ?>
    <script>
    (function () {
        var SIT   = <?php echo wp_json_encode( $chart_sit ); ?>;
        var FORMA = <?php echo wp_json_encode( $chart_forma ); ?>;
        function draw() {
            if ( ! window.Chart ) return;
            var cS = document.getElementById('taofChartSit');
            if ( cS ) new Chart( cS, {
                type: 'doughnut',
                data: { labels: SIT.labels, datasets: [{ data: SIT.qtd,
                    backgroundColor: ['#22c55e','#f59e0b','#ef4444'] }] },
                options: { responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } } }
            } );
            var cF = document.getElementById('taofChartForma');
            if ( cF ) new Chart( cF, {
                type: 'bar',
                data: { labels: FORMA.labels, datasets: [{ label: 'Fórmulas', data: FORMA.qtd,
                    backgroundColor: '#6366f1' }] },
                options: { responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            } );
        }
        function regDL(){ if ( window.ChartDataLabels && window.Chart && ! window.__taofDL ) { window.__taofDL = 1;
            Chart.register( ChartDataLabels );
            Chart.defaults.set( 'plugins.datalabels', { color:'#0f172a', font:{ size:10, weight:'700' }, anchor:'end', align:'end', clamp:true, clip:false,
                formatter:function(v){ if(v==null||v===0) return ''; return (typeof v==='number') ? v.toLocaleString('pt-BR') : v; } } ); } }
        function loadDL(cb){ if ( window.ChartDataLabels ) { regDL(); cb(); return; }
            var p=document.createElement('script'); p.src='https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js';
            p.onload=function(){ regDL(); cb(); }; p.onerror=cb; document.head.appendChild(p); }
        if ( window.Chart ) { loadDL( draw ); }
        else {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            s.onload = function(){ loadDL( draw ); };
            document.head.appendChild(s);
        }
    })();
    </script>
    <?php endif; ?>
    <?php
}
