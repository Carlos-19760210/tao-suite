<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_orcamento_novo() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $is_modal  = ! empty( $_GET['modal'] );
    $card_id   = sanitize_text_field( $_GET['card_id']   ?? '' );
    $orc_id    = sanitize_text_field( $_GET['orc_id']    ?? '' );
    $pre_nome  = sanitize_text_field( $_GET['nome']      ?? '' );
    $pre_wa    = sanitize_text_field( $_GET['whatsapp']  ?? '' );

    // Modo edição: carrega dados existentes
    $edit_data = null;
    $cliente_id = tao_formula_cliente_id();
    if ( $orc_id && $cliente_id ) {
        $re = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id&limit=1" );
        if ( $re['ok'] && ! empty( $re['data'] ) ) {
            $edit_data = $re['data'][0];
            $pre_nome  = $edit_data['nome_paciente'] ?? $pre_nome;
            $pre_wa    = $edit_data['whatsapp']      ?? $pre_wa;
        }
    }

    // No modo modal: injetar CSS inline diretamente no body (add_action admin_head já disparou)
    if ( $is_modal ) {
        add_action( 'admin_head', function() {
            echo '<style id="taof-modal-override">
                #adminmenuwrap,#adminmenuback,#wpadminbar,#wpfooter{display:none!important}
                #wpcontent,#wpbody-content{margin:0!important;padding:0!important}
                html.wp-toolbar{padding-top:0!important}
                body,html{background:#f0f4f8!important}
            </style>';
        } );
    }

    $formas   = [];
    $capsulas = [];

    if ( $cliente_id ) {
        $r        = tao_formula_api( "/formas_farmaceuticas?cliente_id=eq.$cliente_id&ativo=eq.true&order=nome.asc" );
        $formas   = $r['ok'] ? ( $r['data'] ?? [] ) : [];

        $rc           = tao_formula_api( "/tipos_capsula?cliente_id=eq.$cliente_id&ativo=eq.true&order=tipo.asc,numero.asc&select=tipo,numero,vol_ul,peso_vazio_mg,cdpro_fc" );
        $capsulas_raw = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];

        // ── Preços das cápsulas ──────────────────────────────────────────
        // 1ª tentativa: busca direta por cdpro_fc → ativos.codigo_fc
        $preco_por_codigo = [];
        $codigos = array_values( array_filter( array_column( $capsulas_raw, 'cdpro_fc' ) ) );
        if ( ! empty( $codigos ) ) {
            $in = implode( ',', $codigos );
            $ra = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&codigo_fc=in.($in)&select=codigo_fc,preco_venda" );
            if ( $ra['ok'] ) {
                foreach ( $ra['data'] ?? [] as $a ) {
                    if ( ( $a['preco_venda'] ?? 0 ) > 0 ) {
                        $preco_por_codigo[ $a['codigo_fc'] ] = (float) $a['preco_venda'];
                    }
                }
            }
        }

        // 2ª tentativa (fallback): produtos com "INCOLOR" no nome (padrão de busca do cliente)
        $incolor_ativos = [];
        if ( ! empty( $capsulas_raw ) ) {
            $ri = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&nome=ilike.*INCOLOR*&select=codigo,nome,preco_venda" );
            $incolor_ativos = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];
        }

        // 3ª tentativa (fallback geral): qualquer ativo com "CAP" no nome (para busca por número)
        $cap_ativos = [];
        if ( ! empty( $capsulas_raw ) ) {
            $rg = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&nome=ilike.*CAP*&select=codigo,nome,preco_venda&limit=200" );
            $cap_ativos = $rg['ok'] ? ( $rg['data'] ?? [] ) : [];
        }

        $capsulas = [];
        foreach ( $capsulas_raw as $c ) {
            $numero  = (string)( $c['numero'] ?? '' );
            $tipo_up = strtoupper( $c['tipo'] ?? '' );
            $cdpro   = $c['cdpro_fc'] ?? '';
            $venda_unit = 0.0;
            $num_pat = '/\b' . preg_quote( $numero, '/' ) . '\b/';

            // 1. Direto por código
            if ( $cdpro && isset( $preco_por_codigo[ $cdpro ] ) ) {
                $venda_unit = $preco_por_codigo[ $cdpro ];
            }

            // 2. INCOLOR: tipo + número, depois só número
            if ( $venda_unit == 0 ) {
                foreach ( [ true, false ] as $usaTipo ) {
                    foreach ( $incolor_ativos as $ia ) {
                        $nome = strtoupper( $ia['nome'] ?? '' );
                        if ( ! preg_match( $num_pat, $nome ) ) continue;
                        if ( $usaTipo && $tipo_up &&
                             strpos( $nome, $tipo_up ) === false &&
                             strpos( $nome, substr( $tipo_up, 0, 3 ) ) === false ) continue;
                        $venda_unit = (float)( $ia['preco_venda'] ?? 0 );
                        break;
                    }
                    if ( $venda_unit > 0 ) break;
                }
            }

            // 3. Qualquer produto "CAP": tipo + número, depois só número
            if ( $venda_unit == 0 ) {
                foreach ( [ true, false ] as $usaTipo ) {
                    foreach ( $cap_ativos as $ia ) {
                        $nome = strtoupper( $ia['nome'] ?? '' );
                        if ( ! preg_match( $num_pat, $nome ) ) continue;
                        if ( $usaTipo && $tipo_up &&
                             strpos( $nome, $tipo_up ) === false &&
                             strpos( $nome, substr( $tipo_up, 0, 3 ) ) === false ) continue;
                        $venda_unit = (float)( $ia['preco_venda'] ?? 0 );
                        break;
                    }
                    if ( $venda_unit > 0 ) break;
                }
            }

            $capsulas[] = [
                'tipo'          => $c['tipo'],
                'numero'        => $numero,
                'vol_ul'        => (float) $c['vol_ul'],
                'peso_vazio_mg' => (float)( $c['peso_vazio_mg'] ?? 0 ),
                'cdpro_fc'      => $cdpro,
                'venda_unit'    => $venda_unit,
            ];
        }
    }

    $tipos_display = [
        'cap'     => 'Cápsula',
        'creme'   => 'Creme',
        'locao'   => 'Loção',
        'shampoo' => 'Shampoo',
        'gel'     => 'Gel / Pomada',
        'envelope'=> 'Envelope (Sachê)',
        'solucao' => 'Solução',
        'un'      => 'Unidades',
        'floral'  => 'Floral',
        'duo_cap' => 'Duo Caps',
        'outro'   => 'Outras',
    ];

    $formas_js = [];
    foreach ( $formas as $f ) {
        $tipo = $f['tipo'] ?? 'outro';
        $formas_js[ $f['id'] ] = [
            'id'         => $f['id'],
            'nome'       => $f['nome'],
            'tipo'       => $tipo,
            'tipoLabel'  => $tipos_display[ $tipo ] ?? $tipo,
            'volume'     => $f['volume'] !== null ? (float)$f['volume'] : null,
            'unidVolume' => $f['unidade_volume'] ?? 'g',
            'nCapsulas'  => $f['n_capsulas'] !== null ? (int)$f['n_capsulas'] : null,
            'custoFixo'     => (float)( $f['custo_fixo'] ?? 0 ),
            'custoFixoTipo' => $f['custo_fixo_tipo'] ?? null,
            'valorMinimo'   => isset( $f['valor_minimo'] ) && $f['valor_minimo'] !== null ? (float)$f['valor_minimo'] : null,
            'margemPct'     => (float)( $f['margem_pct'] ?? 30 ),
            'ftenchcap'     => (float)( $f['ftenchcap'] ?? 1 ),
        ];
    }

    $url_lista = tao_formula_url( 'formula-orcamentos' );
    $page_title = $edit_data ? '✏️ Editar Orçamento' : '📝 Novo Orçamento';
    ?>
    <?php if ( $is_modal ) : ?>
    <style>
        #adminmenuwrap,#adminmenuback,#wpadminbar,#wpfooter,
        .notice,.notice-success,.notice-warning,.notice-error,.notice-info,
        .update-nag,.updated,.error,.is-dismissible,
        [id*="sureforms"],[class*="sureforms"],
        .surecart-notice,.woocommerce-message,.woocommerce-info,
        #wpbody-content>.notice,#wpbody-content>.updated{display:none!important}
        #wpcontent,#wpbody-content{margin:0!important;padding:0!important}
        html.wp-toolbar{padding-top:0!important}
        body,html{background:#f0f4f8!important}
    </style>
    <?php endif; ?>
    <div class="wrap taof-wrap">
    <?php if ( $is_modal ) : ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0 6px;border-bottom:1px solid #e2e8f0;margin-bottom:14px">
        <h1 style="margin:0;font-size:18px"><?php echo esc_html( $page_title ); ?>
            <?php if ( $edit_data ) : ?><span style="font-size:13px;color:#64748b;font-weight:400;margin-left:8px">ORC:<?php echo esc_html( $edit_data['numero_orcamento'] ?? '' ); ?></span><?php endif; ?>
        </h1>
        <button type="button" id="taof-modal-close-btn"
                style="background:none;border:1px solid #cbd5e1;border-radius:6px;padding:4px 12px;cursor:pointer;color:#475569;font-size:13px">
            ✕ Fechar
        </button>
    </div>
    <?php else : ?>
    <h1><?php echo esc_html( $page_title ); ?></h1>
    <?php endif; ?>

    <form id="taof-orc-form" autocomplete="off">

    <!-- ══ MASTER ═══════════════════════════════════════════════════════ -->
    <div class="taof-orc-card">

        <input type="hidden" id="taof-card-id" name="card_id" value="<?php echo esc_attr( $card_id ); ?>">
        <input type="hidden" id="taof-orc-id"  name="orc_id"  value="<?php echo esc_attr( $orc_id ); ?>">

        <!-- Linha 1: Cliente + WhatsApp -->
        <div class="taof-row">
            <div class="taof-field" style="flex:3">
                <label class="taof-label">Cliente</label>
                <input type="text" id="taof-nome-paciente" name="nome_paciente"
                       class="taof-inp" placeholder="Nome do paciente"
                       value="<?php echo esc_attr( $pre_nome ); ?>"
                       <?php echo ( $card_id && $pre_nome ) ? 'readonly style="background:#f8fafc;color:#64748b"' : ''; ?>>
            </div>
            <div class="taof-field" style="flex:2">
                <label class="taof-label">WhatsApp</label>
                <input type="text" id="taof-whatsapp" name="whatsapp"
                       class="taof-inp" placeholder="5511999999999"
                       value="<?php echo esc_attr( $pre_wa ); ?>"
                       <?php echo ( $card_id && $pre_wa ) ? 'readonly style="background:#f8fafc;color:#64748b"' : ''; ?>>
            </div>
        </div>

        <!-- Linha 2: Forma + Vol/Qtde + Tipo Cápsula (só caps) + Unidade + Potes -->
        <div class="taof-row-forma">
            <div class="taof-field taof-ff-forma">
                <label class="taof-label">Forma Farmacêutica</label>
                <select id="taof-forma-sel" name="forma_id" class="taof-inp taof-sel">
                    <option value="">— Selecione —</option>
                    <?php foreach ( $formas as $f ) : ?>
                    <option value="<?php echo esc_attr($f['id']); ?>">
                        <?php echo esc_html($f['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="taof-field taof-ff-vol">
                <label class="taof-label">Vol / Qtde</label>
                <input type="number" id="taof-forma-vol" name="forma_vol"
                       class="taof-inp" min="1" step="any" placeholder="Ex: 30">
            </div>
            <div class="taof-field taof-ff-tipo" id="taof-col-tipo" style="display:none">
                <label class="taof-label">Tipo Cápsula</label>
                <select id="taof-forma-tipo" name="forma_tipo" class="taof-inp taof-sel">
                    <option value="">— Selecione —</option>
                </select>
            </div>
            <div class="taof-field taof-ff-unid">
                <label class="taof-label">Unidade</label>
                <select id="taof-forma-unidade" name="forma_unidade" class="taof-inp taof-sel">
                    <option value="">—</option>
                </select>
            </div>
            <div class="taof-field taof-ff-potes">
                <label class="taof-label">Potes</label>
                <input type="number" id="taof-qtde-potes" name="qtde_potes"
                       class="taof-inp taof-inp-num" value="1" min="1" step="1">
            </div>
            <div class="taof-field taof-ff-vdose" id="taof-col-vol-dose" style="display:none">
                <label class="taof-label" title="Volume por dose administrada (ex: 5ml para xarope)">Vol/dose (ml)</label>
                <input type="number" id="taof-vol-dose" name="vol_dose"
                       class="taof-inp taof-inp-num" min="0.1" step="any" placeholder="ex: 5">
            </div>
        </div>

        <?php if ( empty($formas) ) : ?>
        <p class="taof-warn" style="margin:-6px 0 10px">
            &#x26A0; Nenhuma forma farmacêutica cadastrada.
            <a href="<?php echo esc_url(tao_formula_url('formula-formas')); ?>">Cadastrar &rarr;</a>
        </p>
        <?php endif; ?>

        <!-- Linha 3: Observações -->
        <div class="taof-row">
            <div class="taof-field" style="flex:1">
                <label class="taof-label">Observações</label>
                <textarea id="taof-observacoes" name="observacoes" rows="2"
                          class="taof-inp" placeholder="Observações para o farmacêutico ou paciente..."></textarea>
            </div>
        </div>

    </div><!-- .taof-orc-card -->

    <!-- ══ DETAIL — Ingredientes ══════════════════════════════════════ -->
    <div class="taof-orc-card">
        <div class="taof-card-hdr"><h3>Ingredientes da Fórmula</h3></div>
        <div class="taof-table-scroll">
        <table class="wp-list-table widefat taof-detail-table">
            <thead>
                <tr>
                    <th class="col-cod">Cód</th>
                    <th class="col-prod">Produto</th>
                    <th class="col-dose">Dose</th>
                    <th class="col-unid">Unid.</th>
                    <th class="col-fp" title="Fator de Perda">FP</th>
                    <th class="col-total">Total</th>
                    <th class="col-venda">Venda/un</th>
                    <th class="col-custo">Subtotal</th>
                    <th class="col-qsp" title="Marcar como excipiente QSP (auto-calculado)">QSP</th>
                    <th class="col-del"></th>
                </tr>
            </thead>
            <tbody id="taof-itens-body"></tbody>
        </table>
        </div>
        <div class="taof-table-footer">
            <button type="button" class="button" id="taof-btn-add-item">+ Adicionar Ativo</button>
        </div>
        <div id="taof-info-excipiente" style="display:none;margin:6px 0 0;padding:8px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;font-size:13px"></div>
    </div>

    <!-- ══ DETAIL — Cápsulas (só para formas de cápsula) ══════════════ -->
    <div class="taof-orc-card" id="taof-card-capsulas" style="display:none">
        <div class="taof-card-hdr" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <h3 style="margin:0">Cápsulas</h3>
            <span id="taof-cap-sugerida" style="font-size:12px;color:#475569"></span>
        </div>
        <div class="taof-table-scroll">
        <table class="wp-list-table widefat taof-detail-table">
            <thead>
                <tr>
                    <th>Cápsula</th>
                    <th style="width:80px">Volume</th>
                    <th style="width:110px">VOLAPA/dose</th>
                    <th style="width:130px">Cáps/dose</th>
                    <th style="width:90px">Total lote</th>
                    <th style="width:90px">Preço/un</th>
                    <th style="width:100px;text-align:right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td id="taof-caps-nome" style="font-weight:500;color:#334155">—</td>
                    <td id="taof-caps-vol">—</td>
                    <td id="taof-caps-volapa">—</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:4px">
                            <input type="number" id="taof-caps-por-dose" value="1" min="1" max="12"
                                   class="taof-inp taof-inp-num" style="width:56px"
                                   title="Cápsulas por dose (calculado automaticamente)">
                            <button type="button" id="taof-caps-auto-btn"
                                    style="padding:2px 6px;font-size:11px;cursor:pointer;border:1px solid #ccc;border-radius:3px;background:#f5f5f5;line-height:1.6"
                                    title="Recalcular automaticamente">&#8635;</button>
                        </div>
                    </td>
                    <td id="taof-caps-total-un">—</td>
                    <td id="taof-caps-preco-un" style="color:#475569">—</td>
                    <td id="taof-caps-subtotal" style="text-align:right;font-weight:500">R$ 0,00</td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>

    <!-- ══ DETAIL — Embalagem ═════════════════════════════════════════ -->
    <div class="taof-orc-card">
        <div class="taof-card-hdr"><h3>Embalagem</h3></div>
        <div class="taof-table-scroll">
        <table class="wp-list-table widefat taof-detail-table">
            <thead>
                <tr>
                    <th style="width:52%">Embalagem</th>
                    <th style="width:10%">Qtde</th>
                    <th style="width:18%">Custo/un</th>
                    <th style="width:15%;text-align:right">Subtotal</th>
                    <th style="width:5%"></th>
                </tr>
            </thead>
            <tbody id="taof-emb-body"></tbody>
        </table>
        </div>
        <div class="taof-table-footer">
            <button type="button" class="button" id="taof-btn-add-emb">+ Adicionar Embalagem</button>
        </div>
    </div>

    <!-- ══ TOTAIS ═════════════════════════════════════════════════════ -->
    <div class="taof-orc-card taof-totais-card">
        <table class="taof-totais-table">
            <tr>
                <td>Valor Calculado <small>(insumos + embalagem)</small></td>
                <td></td>
                <td id="taof-res-calculado" class="taof-res-val">R$&nbsp;0,00</td>
            </tr>
            <tr>
                <td>(+) Custo Fixo da Forma</td>
                <td>
                    <input type="number" id="taof-custo-fixo-inp" name="custo_fixo"
                           value="0" min="0" step="0.01" class="taof-money-inp"
                           title="Custo fixo — calculado automaticamente pelo % ao lado">
                    <input type="number" id="taof-custo-fixo-pct" value="30" min="0" max="200" step="1"
                           class="taof-pct-inp" style="width:60px" title="Percentual do custo fixo">%
                    <span id="taof-custo-fixo-predef-badge" style="display:none;font-size:11px;color:#0369a1;background:#f0f9ff;border:1px solid #bae6fd;padding:2px 8px;border-radius:4px;margin-left:4px"></span>
                </td>
                <td id="taof-res-custo-fixo" class="taof-res-val">R$&nbsp;0,00</td>
            </tr>
            <tr id="taof-row-caps-custo" style="display:none">
                <td>(+) Cápsulas <small id="taof-caps-custo-label" style="color:#64748b"></small></td>
                <td></td>
                <td id="taof-res-caps-custo" class="taof-res-val">R$&nbsp;0,00</td>
            </tr>
            <tr class="taof-subtotal-row">
                <td>(=) Valor Sub-Total</td>
                <td></td>
                <td id="taof-res-subtotal" class="taof-res-val">R$&nbsp;0,00</td>
            </tr>
            <tr>
                <td>(+) Acréscimo</td>
                <td>
                    <input type="number" id="taof-acrescimo-val-inp" name="acrescimo_val"
                           value="0" min="0" step="0.01" class="taof-money-inp"
                           title="Valor do acréscimo (ou informe o %)">
                    <input type="number" id="taof-acrescimo-pct" name="acrescimo_pct"
                           value="0" min="0" max="500" step="0.1" class="taof-pct-inp" style="width:60px"> %
                </td>
                <td id="taof-res-acrescimo" class="taof-res-val">R$&nbsp;0,00</td>
            </tr>
            <tr>
                <td>(–) Desconto</td>
                <td>
                    <input type="number" id="taof-desconto-val-inp" name="desconto_val"
                           value="0" min="0" step="0.01" class="taof-money-inp"
                           title="Valor do desconto (ou informe o %)">
                    <input type="number" id="taof-desconto-pct" name="desconto_pct"
                           value="0" min="0" max="100" step="0.1" class="taof-pct-inp" style="width:60px"> %
                </td>
                <td id="taof-res-desconto" class="taof-res-val taof-res-desc">R$&nbsp;0,00</td>
            </tr>
            <tr class="taof-final-row">
                <td colspan="2"><strong>VALOR FINAL</strong></td>
                <td id="taof-res-final" class="taof-res-val taof-res-final"><strong>R$&nbsp;0,00</strong></td>
            </tr>
            <tr id="taof-row-val-minimo" style="display:none">
                <td colspan="3" style="padding:4px 0 2px">
                    <div style="background:#fef3c7;color:#92400e;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:600;border:1px solid #fde68a">
                        &#x26A0; Valor m&iacute;nimo da forma aplicado: <span id="taof-val-minimo-num"></span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ══ AÇÕES ══════════════════════════════════════════════════════ -->
    <div class="taof-orc-card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <button type="submit" class="button button-primary button-large" id="taof-orc-salvar">
            <?php echo $edit_data ? '💾 Atualizar Orçamento' : '💾 Salvar Orçamento'; ?>
        </button>
        <button type="button" class="button button-large" id="taof-btn-analise" title="Comparativo de preços por ativo">
            📊 Análise de Preços
        </button>
        <?php if ( $is_modal ) : ?>
        <button type="button" class="button button-large" id="taof-cancel-btn">Cancelar</button>
        <?php else : ?>
        <a href="<?php echo esc_url($url_lista); ?>" class="button button-large">Cancelar</a>
        <?php endif; ?>
        <span class="taof-spinner spinner" style="float:none;visibility:hidden"></span>
        <span class="taof-msg" style="display:none"></span>
    </div>

    </form>

    <!-- ══ MODAL ANÁLISE DE PREÇOS ════════════════════════════════════ -->
    <div id="taof-modal-analise" style="display:none;position:fixed;inset:0;z-index:100000">
        <div class="taof-analise-overlay" style="position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(2px)"></div>
        <div style="position:absolute;inset:0;display:flex;align-items:flex-start;justify-content:center;padding:40px 20px;overflow-y:auto">
            <div style="background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:1100px;overflow:hidden">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;background:#0f172a;color:#f8fafc">
                    <div>
                        <strong style="font-size:16px">📊 Análise de Preços — Ativos do Orçamento</strong>
                        <div style="font-size:12px;color:#94a3b8;margin-top:2px">Preço Compra × Custo × Venda · Subtotal · Margem</div>
                    </div>
                    <button id="taof-analise-fechar" type="button"
                            style="background:none;border:none;color:#94a3b8;font-size:22px;cursor:pointer;line-height:1;padding:4px 8px"
                            title="Fechar">&times;</button>
                </div>
                <div style="overflow-x:auto;padding:0 0 0">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                                <th style="text-align:left;padding:10px 16px;color:#475569;font-weight:600">Item</th>
                                <th style="text-align:right;padding:10px 12px;color:#475569;font-weight:600">Preço Compra<br><small style="font-weight:400">/un</small></th>
                                <th style="text-align:right;padding:10px 12px;color:#475569;font-weight:600">Preço Custo<br><small style="font-weight:400">/un</small></th>
                                <th style="text-align:right;padding:10px 12px;color:#475569;font-weight:600">Preço Venda<br><small style="font-weight:400">/un</small></th>
                                <th style="text-align:right;padding:10px 12px;color:#475569;font-weight:600">Subtotal<br><small style="font-weight:400">venda</small></th>
                                <th style="text-align:right;padding:10px 12px;color:#475569;font-weight:600">Margem<br><small style="font-weight:400">venda÷compra</small></th>
                                <th style="text-align:right;padding:10px 12px;color:#475569;font-weight:600">Margem Bruta<br><small style="font-weight:400">venda−compra/un</small></th>
                            </tr>
                        </thead>
                        <tbody id="taof-analise-body"></tbody>
                    </table>
                </div>

                <!-- Simulação de Margem -->
                <div style="padding:16px 20px;background:#fafafa;border-top:2px solid #e2e8f0">
                    <div style="font-size:13px;font-weight:600;color:#0f172a;margin-bottom:10px">
                        🎯 Simulação de Margem
                        <span id="taof-sim-aviso" style="display:none;margin-left:12px;font-size:11px;color:#d97706;font-weight:400">
                            ⚠ Alguns itens sem Preço de Compra — simulação com base nos valores conhecidos
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:13px">
                        <span>Margem atual: <strong id="taof-sim-margem-atual" style="color:#0369a1">—</strong></span>
                        <span style="display:flex;align-items:center;gap:8px">
                            Simular:
                            <input type="range" id="taof-sim-range" min="0.5" max="15" step="0.05" value="1"
                                   style="width:200px">
                            <input type="number" id="taof-sim-inp" min="0.1" step="0.05" value="1"
                                   style="width:70px;padding:4px 6px;font-size:13px">
                            <span style="color:#64748b">×</span>
                        </span>
                        <span style="font-size:14px">
                            → Novo valor final: <span id="taof-sim-novo"><strong>—</strong></span>
                        </span>
                    </div>
                </div>

                <div style="padding:10px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8">
                    Margem = Preço Venda ÷ Preço Compra (× 1 = sem margem). Simulação: Novo Valor = Total Compra × multiplicador. Verde ≥ 2× · Vermelho &lt; 1×.
                </div>
            </div>
        </div>
    </div>

    <!-- ══ TEMPLATES ══════════════════════════════════════════════════ -->
    <template id="taof-item-tpl">
        <tr class="taof-item-row">
            <td class="col-cod">
                <span class="taof-orc-cod" style="font-size:11px;color:#94a3b8;font-family:monospace">—</span>
            </td>
            <td class="col-prod">
                <div class="taof-ac-wrap">
                    <input type="text" class="taof-orc-ativo-search taof-inp"
                           placeholder="Buscar ativo..." autocomplete="off">
                    <input type="hidden" class="taof-orc-ativo-id">
                    <div class="taof-ac-dropdown" style="display:none"></div>
                </div>
            </td>
            <td class="col-dose">
                <input type="number" class="taof-orc-dose taof-inp taof-inp-num"
                       value="0" min="0" step="any">
            </td>
            <td class="col-unid">
                <select class="taof-orc-dose-unit taof-inp" style="padding:6px 4px">
                    <option value="mg">mg</option>
                    <option value="mcg">mcg</option>
                    <option value="g">g</option>
                    <option value="%">%</option>
                    <option value="UI">UI</option>
                    <option value="UFC">UFC</option>
                    <option value="BLH">BLH</option>
                    <option value="ml">ml</option>
                </select>
            </td>
            <td class="col-fp">
                <span class="taof-orc-fp-label" style="font-size:12px;color:#94a3b8">—</span>
            </td>
            <td class="col-total taof-orc-qtd-total" style="font-size:12px;color:#475569">—</td>
            <td class="col-venda taof-orc-preco-venda" style="text-align:right;color:#0369a1;font-size:12px">—</td>
            <td class="col-custo taof-orc-subtotal" style="text-align:right;font-weight:600">R$&nbsp;0,00</td>
            <td class="col-qsp" style="text-align:center">
                <button type="button" class="taof-btn-qsp button button-small" title="Marcar como excipiente QSP">QSP</button>
            </td>
            <td class="col-del">
                <button type="button" class="taof-btn-del-item button button-small"
                        style="color:#b91c1c;padding:2px 6px" title="Remover">✕</button>
            </td>
        </tr>
    </template>

    <template id="taof-emb-tpl">
        <tr class="taof-emb-row">
            <td>
                <div class="taof-ac-wrap">
                    <input type="text" class="taof-emb-search taof-inp"
                           placeholder="Buscar embalagem..." autocomplete="off">
                    <input type="hidden" class="taof-emb-id">
                    <div class="taof-ac-dropdown" style="display:none"></div>
                </div>
            </td>
            <td>
                <input type="number" class="taof-emb-qty taof-inp taof-inp-num"
                       value="1" min="1" step="1" style="width:70px">
            </td>
            <td class="taof-emb-custo-label" style="color:#64748b;font-size:13px">—</td>
            <td class="taof-emb-subtotal" style="text-align:right;font-weight:600">R$&nbsp;0,00</td>
            <td>
                <button type="button" class="taof-btn-del-emb button button-small"
                        style="color:#b91c1c;padding:2px 6px" title="Remover">✕</button>
            </td>
        </tr>
    </template>

    <style>
    /* ── Card base ────────────────────────────────────────── */
    .taof-wrap { max-width:1100px; }
    .taof-orc-card {
        background:#fff; border:1px solid #e2e8f0; border-radius:10px;
        padding:20px 24px; margin-bottom:14px;
    }
    .taof-card-hdr { margin-bottom:10px; }
    .taof-card-hdr h3 {
        margin:0; font-size:11px; font-weight:700;
        text-transform:uppercase; letter-spacing:.07em; color:#94a3b8;
    }

    /* ── Master flex rows ─────────────────────────────────── */
    .taof-row { display:flex !important; gap:16px; margin-bottom:14px; align-items:flex-end; flex-wrap:wrap; }
    .taof-row:last-child { margin-bottom:0; }
    .taof-field { display:flex !important; flex-direction:column !important; gap:4px !important; }

    /* Linha 2 — flex para suportar campo Tipo ocultável */
    .taof-row-forma {
        display:flex !important; gap:12px; margin-bottom:14px;
        align-items:flex-end !important; flex-wrap:wrap;
    }
    .taof-ff-forma  { flex:1; min-width:180px; }
    .taof-ff-vol    { width:82px;  flex-shrink:0; }
    .taof-ff-tipo   { width:155px; flex-shrink:0; }
    .taof-ff-unid   { width:80px;  flex-shrink:0; }
    .taof-ff-potes  { width:68px;  flex-shrink:0; }
    @media (max-width:700px) {
        .taof-ff-forma,  .taof-ff-vol, .taof-ff-tipo,
        .taof-ff-unid,   .taof-ff-potes { width:calc(50% - 6px); flex:none; }
        .taof-ff-forma { width:100%; }
    }

    .taof-label {
        font-size:11px !important; font-weight:700 !important;
        text-transform:uppercase !important; letter-spacing:.06em !important;
        color:#64748b !important; white-space:nowrap !important;
        display:block !important; margin:0 !important; padding:0 !important;
        line-height:1.4 !important;
    }
    .taof-inp {
        padding:8px 12px !important; border:1px solid #cbd5e1 !important;
        border-radius:6px !important; font-size:14px !important;
        line-height:1.4 !important; box-sizing:border-box !important;
        width:100% !important; background:#fff !important;
        color:#1e293b !important; height:38px !important;
        margin:0 !important; vertical-align:top !important;
        appearance:auto !important; -webkit-appearance:auto !important;
    }
    .taof-inp:focus {
        border-color:#0ea5e9 !important; outline:none !important;
        box-shadow:0 0 0 3px rgba(14,165,233,.15) !important;
    }
    .taof-sel { cursor:pointer !important; }
    .taof-inp-num { width:90px !important; }
    textarea.taof-inp { height:auto !important; resize:vertical !important; }
    .taof-badge-info {
        display:inline-block; padding:8px 12px; background:#f0f9ff;
        border:1px solid #bae6fd; border-radius:6px; font-size:14px;
        font-weight:700; color:#0369a1; min-height:38px; min-width:80px;
        line-height:1.4;
    }
    .taof-warn { color:#dc2626; font-size:12px; margin-top:4px; }

    /* ── Detail table ─────────────────────────────────────── */
    .taof-table-scroll { overflow-x:auto; }
    .taof-detail-table { border-collapse:collapse; min-width:760px; }
    .taof-detail-table th, .taof-detail-table td {
        padding:7px 8px; vertical-align:middle; border-bottom:1px solid #f1f5f9;
    }
    .taof-detail-table th { font-size:11px; color:#94a3b8; font-weight:600; background:#f8fafc; }
    .taof-detail-table .taof-inp { padding:6px 8px; font-size:13px; }

    .col-cod   { width:6%;  min-width:50px; }
    .col-prod  { width:26%; min-width:150px; }
    .col-dose  { width:9%;  min-width:75px; }
    .col-unid  { width:7%;  min-width:60px; }
    .col-fp    { width:5%;  min-width:40px; text-align:center; }
    .col-total { width:10%; min-width:78px; }
    .col-venda { width:10%; min-width:78px; text-align:right; }
    .col-custo { width:10%; min-width:78px; text-align:right; }
    .col-qsp   { width:5%;  min-width:48px; text-align:center; }
    .col-del   { width:4%;  min-width:32px; text-align:center; }

    /* QSP styles */
    .taof-btn-qsp {
        font-size:10px; padding:2px 6px; color:#64748b;
        border-color:#cbd5e1; background:#f8fafc;
    }
    .taof-btn-qsp.ativo {
        background:#0ea5e9; color:#fff; border-color:#0284c7;
        font-weight:700;
    }
    .taof-row-qsp { background:#f0f9ff !important; }
    .taof-row-qsp .taof-orc-dose {
        background:#e0f2fe; color:#0369a1; font-style:italic;
    }

    /* ── Autocomplete ─────────────────────────────────────── */
    .taof-ac-wrap { position:relative; }

    /* ── Cap sugerida ─────────────────────────────────────── */
    .taof-table-footer { margin-top:10px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
    #taof-cap-sugerida {
        font-size:13px; background:#f0fdf4; border:1px solid #86efac;
        border-radius:6px; padding:6px 14px; line-height:1.5;
    }

    /* ── Totais ───────────────────────────────────────────── */
    .taof-totais-card { max-width:580px; margin-left:auto; }
    .taof-totais-table { width:100%; border-collapse:collapse; }
    .taof-totais-table td {
        padding:9px 12px; font-size:14px; border-bottom:1px solid #f1f5f9;
    }
    .taof-totais-table tr:nth-child(even) td { background:#fafafa; }
    .taof-totais-table td:first-child { color:#475569; }
    .taof-totais-table td:nth-child(2) { white-space:nowrap; }
    .taof-res-val { text-align:right; font-weight:600; width:140px; font-variant-numeric:tabular-nums; }
    .taof-subtotal-row td { font-weight:700; background:#f1f5f9!important; border-top:2px solid #e2e8f0!important; border-bottom:2px solid #e2e8f0!important; }
    .taof-final-row td { font-size:17px; background:#f0f9ff!important; border-top:2px solid #0ea5e9!important; }
    .taof-res-desc  { color:#b91c1c; }
    .taof-res-final { color:#0369a1; }

    /* Análise de preços */
    #taof-analise-body tr { border-bottom:1px solid #f1f5f9; }
    #taof-analise-body tr:hover { background:#f8fafc; }
    #taof-analise-body td { padding:10px 16px; vertical-align:middle; }
    #taof-analise-body td:first-child { font-weight:500; color:#1e293b; max-width:280px; }
    #taof-analise-body td:not(:first-child) { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }

    .taof-money-inp {
        width:110px; padding:5px 8px; font-size:13px;
        border:1px solid #cbd5e1; border-radius:5px; text-align:right;
    }
    .taof-money-inp:focus { border-color:#0ea5e9; outline:none; }
    .taof-pct-inp {
        width:68px; padding:5px 8px; font-size:13px;
        border:1px solid #cbd5e1; border-radius:5px; text-align:right;
    }
    .taof-pct-inp:focus { border-color:#0ea5e9; outline:none; }
    </style>

    <script>
    window.taofOrcFormas    = <?php echo wp_json_encode( array_values($formas_js) ); ?>;
    window.taofOrcFormasMap = <?php echo wp_json_encode( $formas_js ); ?>;
    window.taofCapsulas     = <?php echo wp_json_encode( array_values( $capsulas ) ); ?>;
    window.taofOrcListUrl   = <?php echo wp_json_encode( $url_lista ); ?>;
    window.taofIsModal      = <?php echo $is_modal ? 'true' : 'false'; ?>;
    window.taofCardId       = <?php echo wp_json_encode( $card_id ); ?>;
    window.taofEditOrcId    = <?php echo wp_json_encode( $orc_id ); ?>;
    window.taofEditData     = <?php echo $edit_data ? wp_json_encode( $edit_data ) : 'null'; ?>;
    // Tabela estática de embalagens por forma farmacêutica (Embalagens.xlsx)
    window.taofEmbalagens = [
        // Creme → bisnagas
        {tipos:['creme'],             codigo:62732, nome:'BISNAGA PLASTICA 200G',           vol:200, uni:'g'},
        {tipos:['creme'],             codigo:62757, nome:'BISNAGA PLASTICA 100G',           vol:100, uni:'g'},
        {tipos:['creme'],             codigo:62661, nome:'BISNAGA PLASTICA 60G',            vol:60,  uni:'g'},
        {tipos:['creme'],             codigo:62921, nome:'BISNAGA PLASTICA 30G',            vol:30,  uni:'g'},
        {tipos:['creme'],             codigo:63773, nome:'BISNAGA PLASTICA 15G',            vol:15,  uni:'g'},
        {tipos:['creme'],             codigo:10741, nome:'BISNAGA PLASTICA 10G',            vol:10,  uni:'g'},
        // Gel → pump / roller
        {tipos:['gel'],               codigo:10603, nome:'FRASCO PUMP 50ML',               vol:50,  uni:'ml'},
        {tipos:['gel'],               codigo:11543, nome:'FRASCO PUMP 50ML (ALT)',         vol:50,  uni:'ml'},
        {tipos:['gel'],               codigo:10754, nome:'FRASCO PUMP MEG 30ML',           vol:30,  uni:'ml'},
        {tipos:['gel'],               codigo:11542, nome:'FRASCO PUMP TESTOSTERONA 30ML',  vol:30,  uni:'ml'},
        {tipos:['gel'],               codigo:10607, nome:'FRESH ROLLER 15ML',              vol:15,  uni:'ml'},
        {tipos:['gel'],               codigo:11161, nome:'CANETA SPATECH 15ML',            vol:15,  uni:'ml'},
        // Loção → gotejador
        {tipos:['locao'],             codigo:11020, nome:'FRASCO GOTEJADOR 250ML',         vol:250, uni:'ml'},
        {tipos:['locao'],             codigo:64700, nome:'FRASCO GOTEJADOR 120ML',         vol:120, uni:'ml'},
        {tipos:['locao'],             codigo:66517, nome:'FRASCO GOTEJADOR 60ML',          vol:60,  uni:'ml'},
        {tipos:['locao'],             codigo:66516, nome:'FRASCO GOTEJADOR 30ML',          vol:30,  uni:'ml'},
        // Shampoo → frasco shampoo
        {tipos:['shampoo'],           codigo:12702, nome:'FRASCO SHAMPOO 500ML',           vol:500, uni:'ml'},
        {tipos:['shampoo'],           codigo:10954, nome:'FRASCO SHAMPOO 350ML',           vol:350, uni:'ml'},
        {tipos:['shampoo'],           codigo:24492, nome:'FRASCO SHAMPOO/SABONETE 250ML',  vol:250, uni:'ml'},
        {tipos:['shampoo'],           codigo:10585, nome:'FRASCO SHAMPOO/SABONETE 120ML',  vol:120, uni:'ml'},
        // Solução Oral → frasco pet / vidro
        {tipos:['solucao'],           codigo:66780, nome:'FRASCO PET AMBAR 500ML',         vol:500, uni:'ml'},
        {tipos:['solucao'],           codigo:10605, nome:'FRASCO PET AMBAR 250ML',         vol:250, uni:'ml'},
        {tipos:['solucao'],           codigo:11023, nome:'FRASCO PET AMBAR 150ML',         vol:150, uni:'ml'},
        {tipos:['solucao'],           codigo:12593, nome:'FRASCO PET AMBAR 100ML',         vol:100, uni:'ml'},
        {tipos:['solucao'],           codigo:12591, nome:'FRASCO PET AMBAR 60ML',          vol:60,  uni:'ml'},
        {tipos:['solucao'],           codigo:66187, nome:'VIDRO 30ML',                     vol:30,  uni:'ml'},
        {tipos:['solucao'],           codigo:57861, nome:'VIDRO 120ML',                    vol:120, uni:'ml'},
        {tipos:['solucao'],           codigo:29038, nome:'VIDRO 60ML',                     vol:60,  uni:'ml'},
        {tipos:['solucao'],           codigo:66217, nome:'VIDRO 15ML',                     vol:15,  uni:'ml'},
        // Cápsula / Duo Caps → potes
        {tipos:['cap','duo_cap'],     codigo:10593, nome:'POTE 35ML',                      vol:35,  uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:10594, nome:'POTE 60ML',                      vol:60,  uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:63601, nome:'POTE 110ML',                     vol:110, uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:10592, nome:'POTE 160ML',                     vol:160, uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:67848, nome:'POTE 250ML',                     vol:250, uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:63244, nome:'POTE 320ML',                     vol:320, uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:10596, nome:'POTE 500ML',                     vol:500, uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:12856, nome:'POTE 750ML',                     vol:750, uni:'ml'},
        {tipos:['cap','duo_cap'],     codigo:10610, nome:'POTE 1000ML',                    vol:1000,uni:'ml'},
        // Outras (Sérum) → pump / roller
        {tipos:['outro'],             codigo:10603, nome:'FRASCO PUMP 50ML',               vol:50,  uni:'ml'},
        {tipos:['outro'],             codigo:11882, nome:'FRASCO PUMP TUBE PUMP 60ML',     vol:60,  uni:'ml'},
        {tipos:['outro'],             codigo:10754, nome:'FRASCO PUMP MEG 30ML',           vol:30,  uni:'ml'},
        {tipos:['outro'],             codigo:62448, nome:'FRASCO PUMP TUBE 30ML',          vol:30,  uni:'ml'},
        {tipos:['outro'],             codigo:11735, nome:'FRASCO PUMP VD AMBAR 30ML',      vol:30,  uni:'ml'},
        {tipos:['outro'],             codigo:10607, nome:'FRESH ROLLER 15ML',              vol:15,  uni:'ml'},
    ];
    window.taofDbgCapsulas  = <?php echo count($capsulas); ?>;
    window.taofDbgFormas    = <?php echo count($formas_js); ?>;
    </script>
    <?php
    $caps_com_preco = array_filter( $capsulas, fn($c) => ($c['venda_unit'] ?? 0) > 0 );
    $caps_sem_preco = array_filter( $capsulas, fn($c) => ($c['venda_unit'] ?? 0) == 0 );
    $ex_sem = array_slice( array_values($caps_sem_preco), 0, 3 );
    ?>
    <div id="taof-dbg-badge" style="position:fixed;bottom:8px;right:8px;background:#0f172a;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:10px;z-index:9999;font-family:monospace;max-width:340px">
        TAO Fórmulas <?php echo TAOF_VERSION; ?> · <?php echo count($capsulas); ?> caps (<?php echo count($caps_com_preco); ?> c/ preço · <?php echo count($caps_sem_preco); ?> s/ preço)<br>
        <?php if ( $ex_sem ): ?>
        sem preço: <?php foreach($ex_sem as $c) echo htmlspecialchars($c['tipo'].' N'.$c['numero'].' cdpro='.$c['cdpro_fc']).' | '; ?>
        <?php endif; ?>
    </div>
    <?php
}
