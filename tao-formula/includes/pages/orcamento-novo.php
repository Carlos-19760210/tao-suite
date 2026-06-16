<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_orcamento_novo() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();
    $formas     = [];
    $capsulas   = [];

    if ( $cliente_id ) {
        $r        = tao_formula_api( "/formas_farmaceuticas?cliente_id=eq.$cliente_id&ativo=eq.true&order=nome.asc" );
        $formas   = $r['ok'] ? ( $r['data'] ?? [] ) : [];

        $rc       = tao_formula_api( "/tipos_capsula?cliente_id=eq.$cliente_id&ativo=eq.true&order=tipo.asc,numero.asc&select=tipo,numero,vol_ul,peso_vazio_mg" );
        $capsulas = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];
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
            'volume'     => (float)( $f['volume'] ?? 0 ),
            'unidVolume' => $f['unidade_volume'] ?? 'g',
            'nCapsulas'  => (int)( $f['n_capsulas'] ?? 0 ),
            'custoFixo'  => (float)( $f['custo_fixo'] ?? 0 ),
            'margemPct'  => (float)( $f['margem_pct'] ?? 30 ),
            'ftenchcap'  => (float)( $f['ftenchcap'] ?? 1 ),
        ];
    }

    $url_lista = tao_formula_url( 'formula-orcamentos' );
    ?>
    <div class="wrap taof-wrap">
    <h1>📝 Novo Orçamento</h1>

    <form id="taof-orc-form" autocomplete="off">

    <!-- ══ MASTER ═══════════════════════════════════════════════════ -->
    <div class="taof-orc-card">
        <div class="taof-master-grid">

            <div class="taof-fg taof-fg-2">
                <label>Cliente</label>
                <input type="text" id="taof-nome-paciente" name="nome_paciente" class="taof-inp" placeholder="Nome do paciente">
            </div>
            <div class="taof-fg taof-fg-2">
                <label>WhatsApp</label>
                <input type="text" id="taof-whatsapp" name="whatsapp" class="taof-inp" placeholder="5511999999999">
            </div>

            <div class="taof-fg taof-fg-2">
                <label>Forma Farmacêutica</label>
                <select id="taof-forma-sel" name="forma_id" class="taof-sel">
                    <option value="">— Selecione —</option>
                    <?php foreach ( $formas as $f ) : ?>
                    <option value="<?php echo esc_attr($f['id']); ?>"><?php echo esc_html($f['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ( empty($formas) ) : ?>
                <small style="color:#dc2626;margin-top:4px">Nenhuma forma cadastrada. <a href="<?php echo esc_url(tao_formula_url('formula-formas')); ?>">Cadastrar →</a></small>
                <?php endif; ?>
            </div>

            <div class="taof-fg">
                <label>Volume / Qtde</label>
                <span id="taof-forma-vol-label" class="taof-static-val">—</span>
            </div>
            <div class="taof-fg">
                <label>Tipo</label>
                <span id="taof-forma-tipo-label" class="taof-static-val">—</span>
            </div>
            <div class="taof-fg">
                <label>Qtde Potes / Unid.</label>
                <input type="number" id="taof-qtde-potes" name="qtde_potes" class="taof-inp-short" value="1" min="1" step="1">
            </div>

            <div class="taof-fg taof-fg-full">
                <label>Observações</label>
                <textarea id="taof-observacoes" name="observacoes" rows="2" class="taof-inp" placeholder="Observações para o farmacêutico ou paciente..."></textarea>
            </div>

        </div>
    </div>

    <!-- ══ DETAIL — Ingredientes ══════════════════════════════════════ -->
    <div class="taof-orc-card">
        <div class="taof-card-hdr"><h3>Ingredientes da Fórmula</h3></div>
        <div class="taof-table-wrap">
        <table class="wp-list-table widefat taof-table taof-detail-table" id="taof-itens-table">
            <thead>
                <tr>
                    <th style="width:7%">Cód</th>
                    <th style="width:36%">Produto</th>
                    <th style="width:11%">Qtde / Dose</th>
                    <th style="width:8%">Unid.</th>
                    <th style="width:6%" title="Fator de Perda">FP</th>
                    <th style="width:11%">Total</th>
                    <th style="width:13%;text-align:right">Custo</th>
                    <th style="width:4%"></th>
                </tr>
            </thead>
            <tbody id="taof-itens-body"></tbody>
        </table>
        </div>
        <div class="taof-table-footer">
            <button type="button" class="button" id="taof-btn-add-item">+ Adicionar Ativo</button>
            <span id="taof-cap-sugerida" style="display:none"></span>
        </div>
    </div>

    <!-- ══ DETAIL — Embalagem ═════════════════════════════════════════ -->
    <div class="taof-orc-card">
        <div class="taof-card-hdr"><h3>Embalagem</h3></div>
        <div class="taof-table-wrap">
        <table class="wp-list-table widefat taof-table taof-detail-table" id="taof-emb-table">
            <thead>
                <tr>
                    <th style="width:52%">Embalagem</th>
                    <th style="width:12%">Qtde</th>
                    <th style="width:16%">Custo/un</th>
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
                <td>Valor Calculado (insumos + embalagem)</td>
                <td id="taof-res-calculado" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr>
                <td>(+) Custo Fixo da Forma</td>
                <td id="taof-res-custo-fixo" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr class="taof-subtotal-row">
                <td>(=) Valor Sub-Total</td>
                <td id="taof-res-subtotal" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr>
                <td>
                    (+) Acréscimo
                    <input type="number" id="taof-acrescimo-pct" name="acrescimo_pct" value="0" min="0" max="500" step="0.1" class="taof-pct-inp"> %
                    <span class="taof-pct-hint" id="taof-acrescimo-hint"></span>
                </td>
                <td id="taof-res-acrescimo" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr>
                <td>
                    (–) Desconto
                    <input type="number" id="taof-desconto-pct" name="desconto_pct" value="0" min="0" max="100" step="0.1" class="taof-pct-inp"> %
                    <span class="taof-pct-hint" id="taof-desconto-hint"></span>
                </td>
                <td id="taof-res-desconto" class="taof-res-val taof-desc-val">R$ 0,00</td>
            </tr>
            <tr class="taof-final-row">
                <td><strong>VALOR FINAL</strong></td>
                <td id="taof-res-final" class="taof-res-val taof-final-val"><strong>R$ 0,00</strong></td>
            </tr>
        </table>
    </div>

    <!-- ══ AÇÕES ══════════════════════════════════════════════════════ -->
    <div class="taof-orc-card taof-actions-card">
        <button type="submit" class="button button-primary button-large" id="taof-orc-salvar">💾 Salvar Orçamento</button>
        <a href="<?php echo esc_url($url_lista); ?>" class="button button-large">Cancelar</a>
        <span class="taof-spinner spinner" style="float:none;visibility:hidden"></span>
        <span class="taof-msg" style="display:none"></span>
    </div>

    </form>

    <!-- ══ TEMPLATES ══════════════════════════════════════════════════ -->
    <template id="taof-item-tpl">
        <tr class="taof-item-row">
            <td><span class="taof-orc-cod" style="font-size:11px;color:#94a3b8;font-family:monospace">—</span></td>
            <td>
                <div class="taof-autocomplete-wrap">
                    <input type="text" class="taof-orc-ativo-search" placeholder="Buscar ativo..." autocomplete="off" style="width:100%">
                    <input type="hidden" class="taof-orc-ativo-id">
                    <div class="taof-autocomplete-results" style="display:none"></div>
                </div>
            </td>
            <td><input type="number" class="taof-orc-dose small-text" value="0" min="0" step="any" style="width:78px"></td>
            <td>
                <select class="taof-orc-dose-unit" style="max-width:68px">
                    <option value="mg">mg</option>
                    <option value="mcg">mcg</option>
                    <option value="g">g</option>
                    <option value="UI">UI</option>
                    <option value="UFC">UFC</option>
                    <option value="ml">ml</option>
                </select>
            </td>
            <td><span class="taof-orc-fp-label" style="font-size:12px;color:#94a3b8">—</span></td>
            <td class="taof-orc-qtd-total" style="color:#475569;font-size:12px">—</td>
            <td class="taof-orc-subtotal" style="text-align:right;font-weight:600">R$ 0,00</td>
            <td><button type="button" class="taof-btn-del-item button button-small" style="color:#b91c1c" title="Remover">✕</button></td>
        </tr>
    </template>

    <template id="taof-emb-tpl">
        <tr class="taof-emb-row">
            <td>
                <div class="taof-autocomplete-wrap">
                    <input type="text" class="taof-emb-search" placeholder="Buscar embalagem..." autocomplete="off" style="width:100%">
                    <input type="hidden" class="taof-emb-id">
                    <div class="taof-autocomplete-results" style="display:none"></div>
                </div>
            </td>
            <td><input type="number" class="taof-emb-qty small-text" value="1" min="1" step="1" style="width:65px"></td>
            <td class="taof-emb-custo-label" style="color:#64748b;font-size:12px">—</td>
            <td class="taof-emb-subtotal" style="text-align:right;font-weight:600">R$ 0,00</td>
            <td><button type="button" class="taof-btn-del-emb button button-small" style="color:#b91c1c" title="Remover">✕</button></td>
        </tr>
    </template>

    <style>
    .taof-orc-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;margin-bottom:14px}
    .taof-card-hdr{margin-bottom:10px}
    .taof-card-hdr h3{margin:0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b}

    /* Master grid */
    .taof-master-grid{display:grid;grid-template-columns:2fr 1.5fr 0.9fr 0.7fr;gap:12px 18px;align-items:end}
    .taof-fg{display:flex;flex-direction:column;gap:4px}
    .taof-fg label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
    .taof-fg-2{grid-column:span 2}
    .taof-fg-full{grid-column:1/-1}
    .taof-inp{width:100%;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;box-sizing:border-box}
    .taof-inp:focus,.taof-sel:focus{border-color:#0ea5e9;outline:none;box-shadow:0 0 0 2px rgba(14,165,233,.18)}
    .taof-sel{width:100%;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;background:#fff}
    .taof-inp-short{width:90px;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px}
    .taof-static-val{font-size:15px;font-weight:700;color:#0369a1}

    /* Detail */
    .taof-table-wrap{overflow-x:auto}
    .taof-detail-table input.small-text{padding:5px 7px;border:1px solid #d1d5db;border-radius:4px}
    .taof-detail-table select{padding:5px 6px;border:1px solid #d1d5db;border-radius:4px}
    .taof-table-footer{margin-top:10px;display:flex;gap:14px;align-items:center;flex-wrap:wrap}
    #taof-cap-sugerida{font-size:13px;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:6px 14px}

    /* Autocomplete */
    .taof-autocomplete-wrap{position:relative}
    .taof-autocomplete-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:6px;z-index:300;box-shadow:0 4px 12px rgba(0,0,0,.12);max-height:220px;overflow-y:auto}
    .taof-autocomplete-results .taof-ac-item{padding:7px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9}
    .taof-autocomplete-results .taof-ac-item:hover{background:#f0f9ff}
    .taof-autocomplete-results .taof-ac-item small{color:#94a3b8;margin-left:6px}

    /* Totais */
    .taof-totais-card{max-width:540px;margin-left:auto}
    .taof-totais-table{width:100%;border-collapse:collapse}
    .taof-totais-table td{padding:9px 12px;font-size:14px}
    .taof-totais-table tr:nth-child(odd){background:#f8fafc}
    .taof-res-val{text-align:right;font-weight:600;width:160px;font-variant-numeric:tabular-nums}
    .taof-subtotal-row td{font-weight:700;border-top:2px solid #e2e8f0;border-bottom:2px solid #e2e8f0;background:#f1f5f9!important}
    .taof-final-row td{font-size:18px;background:#f0f9ff!important;border-top:2px solid #0ea5e9}
    .taof-final-val{color:#0369a1}
    .taof-desc-val{color:#b91c1c}
    .taof-pct-inp{width:62px;margin:0 4px;padding:4px 7px;font-size:13px;border:1px solid #d1d5db;border-radius:4px}
    .taof-pct-hint{font-size:12px;color:#94a3b8}
    .taof-actions-card{display:flex;gap:12px;align-items:center;flex-wrap:wrap}

    @media(max-width:900px){
        .taof-master-grid{grid-template-columns:1fr 1fr}
        .taof-fg-2,.taof-fg-full{grid-column:span 1}
        .taof-fg-full{grid-column:1/-1}
        .taof-totais-card{max-width:100%}
    }
    </style>

    <script>
    window.taofOrcFormas  = <?php echo wp_json_encode( $formas_js ); ?>;
    window.taofCapsulas   = <?php echo wp_json_encode( array_values( $capsulas ) ); ?>;
    window.taofOrcListUrl = <?php echo wp_json_encode( $url_lista ); ?>;
    </script>
    <?php
}
