<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_orcamento_novo() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();
    $formas     = [];
    if ( $cliente_id ) {
        $r      = tao_formula_api( "/formas_farmaceuticas?cliente_id=eq.$cliente_id&ativo=eq.true&order=nome.asc" );
        $formas = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    }

    // Serializa formas para JS (multiplicador e custo_fixo por id)
    $formas_js = [];
    foreach ( $formas as $f ) {
        $formas_js[ $f['id'] ] = [
            'id'            => $f['id'],
            'nome'          => $f['nome'],
            'tipo'          => $f['tipo'] ?? 'outro',
            'volume'        => (float)( $f['volume'] ?? 0 ),
            'unidVolume'    => $f['unidade_volume'] ?? 'g',
            'nCapsulas'     => (int)( $f['n_capsulas'] ?? 0 ),
            'custoFixo'     => (float)( $f['custo_fixo'] ?? 0 ),
            'margemPct'     => (float)( $f['margem_pct'] ?? 30 ),
            'tipoCapsula'   => $f['tipo_capsula'] ?? '',
            'numeroCapsula' => $f['numero_capsula'] ?? '',
            'volCapUl'      => isset( $f['vol_cap_ul'] ) ? (float)$f['vol_cap_ul'] : null,
            'ftenchcap'     => (float)( $f['ftenchcap'] ?? 1 ),
        ];
    }

    $url_lista = tao_formula_url( 'formula-orcamentos' );
    ?>
    <div class="wrap taof-wrap">
    <h1>📝 Novo Orçamento</h1>

    <form id="taof-orc-form" autocomplete="off">

    <!-- ── Dados do paciente ─────────────────────────────────────────────── -->
    <div class="taof-orc-section">
        <h2>Paciente</h2>
        <table class="form-table taof-form-table">
            <tr>
                <th><label for="taof-nome-paciente">Nome</label></th>
                <td><input type="text" id="taof-nome-paciente" name="nome_paciente" class="regular-text" placeholder="Nome do paciente"></td>
            </tr>
            <tr>
                <th><label for="taof-whatsapp">WhatsApp</label></th>
                <td><input type="text" id="taof-whatsapp" name="whatsapp" class="regular-text" placeholder="5511999999999"></td>
            </tr>
        </table>
    </div>

    <!-- ── Forma Farmacêutica ────────────────────────────────────────────── -->
    <div class="taof-orc-section">
        <h2>Forma Farmacêutica</h2>
        <table class="form-table taof-form-table">
            <tr>
                <th><label for="taof-forma-sel">Forma</label></th>
                <td>
                    <select id="taof-forma-sel" name="forma_id" style="min-width:280px">
                        <option value="">— Selecione —</option>
                        <?php foreach ( $formas as $f ) : ?>
                        <option value="<?php echo esc_attr($f['id']); ?>"><?php echo esc_html($f['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="taof-forma-info" style="margin-left:12px;font-size:13px;color:#64748b"></span>
                    <div id="taof-forma-mult-badge" style="display:none;margin-top:8px;font-size:13px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:6px 12px;display:inline-block">
                        <strong id="taof-forma-mult-txt"></strong>
                    </div>
                    <?php if ( empty($formas) ) : ?>
                    <p class="description" style="color:#dc2626">Nenhuma forma cadastrada. <a href="<?php echo esc_url(tao_formula_url('formula-formas')); ?>">Cadastrar formas →</a></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── Itens da Receita ──────────────────────────────────────────────── -->
    <div class="taof-orc-section">
        <h2>Itens da Receita</h2>

        <div class="taof-table-container">
        <table class="wp-list-table widefat taof-table" id="taof-itens-table">
            <thead>
                <tr>
                    <th style="width:26%">Ativo (Matéria-Prima)</th>
                    <th style="width:11%">Dose</th>
                    <th style="width:7%">Unid.</th>
                    <th style="width:6%" title="Fator de Perda">FP</th>
                    <th style="width:10%">Qtd. Total</th>
                    <th style="width:9%" title="Volume aparente por cápsula (µL)">VOLAPA</th>
                    <th style="width:13%" title="Custo por unidade padrão">Custo/unid</th>
                    <th style="width:10%;text-align:right">Subtotal</th>
                    <th style="width:2%"></th>
                </tr>
            </thead>
            <tbody id="taof-itens-body">
                <!-- linhas inseridas via JS -->
            </tbody>
        </table>
        </div>

        <p style="margin-top:10px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <button type="button" class="button" id="taof-btn-add-item">+ Adicionar Ativo</button>
            <span id="taof-cap-aviso" style="display:none;font-size:13px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:5px 12px"></span>
        </p>
    </div>

    <!-- ── Embalagens ───────────────────────────────────────────────────── -->
    <div class="taof-orc-section">
        <h2>Embalagens</h2>

        <div class="taof-table-container">
        <table class="wp-list-table widefat taof-table" id="taof-emb-table">
            <thead>
                <tr>
                    <th style="width:40%">Embalagem</th>
                    <th style="width:12%">Quantidade</th>
                    <th style="width:13%">Custo/unid</th>
                    <th style="width:12%;text-align:right">Subtotal</th>
                    <th style="width:3%"></th>
                </tr>
            </thead>
            <tbody id="taof-emb-body">
                <!-- linhas inseridas via JS -->
            </tbody>
        </table>
        </div>

        <p style="margin-top:10px">
            <button type="button" class="button" id="taof-btn-add-emb">+ Adicionar Embalagem</button>
        </p>
    </div>

    <!-- ── Resumo ────────────────────────────────────────────────────────── -->
    <div class="taof-orc-section">
        <h2>Resumo do Orçamento</h2>
        <table class="taof-resumo">
            <tr>
                <td>Total Insumos</td>
                <td id="taof-res-insumos" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr>
                <td>(+) Custo Fixo da Forma</td>
                <td id="taof-res-custo-fixo" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr class="taof-res-base-row">
                <td>(=) Base</td>
                <td id="taof-res-base" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr>
                <td>
                    Margem
                    <input type="number" id="taof-margem-input" name="margem_pct" value="30" min="0" max="300" step="0.1"
                           style="width:60px;margin:0 6px;padding:2px 6px;font-size:13px">%
                </td>
                <td id="taof-res-margem" class="taof-res-val">R$ 0,00</td>
            </tr>
            <tr class="taof-res-total-row">
                <td><strong>TOTAL</strong></td>
                <td id="taof-res-total" class="taof-res-val taof-res-total"><strong>R$ 0,00</strong></td>
            </tr>
        </table>
    </div>

    <!-- ── Observações ───────────────────────────────────────────────────── -->
    <div class="taof-orc-section">
        <h2>Observações</h2>
        <textarea name="observacoes" id="taof-observacoes" rows="3" style="width:100%;max-width:700px"
                  placeholder="Observações para o farmacêutico ou paciente..."></textarea>
    </div>

    <!-- ── Ações ─────────────────────────────────────────────────────────── -->
    <div class="taof-orc-section" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <button type="submit" class="button button-primary button-large" id="taof-orc-salvar">
            💾 Salvar Orçamento
        </button>
        <a href="<?php echo esc_url($url_lista); ?>" class="button button-large">Cancelar</a>
        <span class="taof-spinner spinner" style="float:none;visibility:hidden"></span>
        <span class="taof-msg" style="display:none"></span>
    </div>

    </form><!-- #taof-orc-form -->
    </div><!-- .taof-wrap -->

    <!-- Template de linha de item (oculto) -->
    <template id="taof-item-tpl">
        <tr class="taof-item-row">
            <td class="taof-td-ativo">
                <div class="taof-autocomplete-wrap">
                    <input type="text" class="taof-orc-ativo-search" placeholder="Buscar ativo..." autocomplete="off" style="width:100%">
                    <input type="hidden" class="taof-orc-ativo-id">
                    <input type="hidden" class="taof-orc-unid-padrao">
                    <input type="hidden" class="taof-orc-custo-unit">
                    <div class="taof-autocomplete-results" style="display:none"></div>
                </div>
            </td>
            <td><input type="number" class="taof-orc-dose small-text" value="0" min="0" step="any"></td>
            <td>
                <select class="taof-orc-dose-unit">
                    <option value="mg">mg</option>
                    <option value="mcg">mcg</option>
                    <option value="g">g</option>
                    <option value="UI">UI</option>
                    <option value="ml">ml</option>
                </select>
            </td>
            <td><input type="number" class="taof-orc-fp small-text" value="1" min="0.01" step="0.001"></td>
            <td class="taof-orc-qtd-total" style="color:#475569;font-size:12px">—</td>
            <td class="taof-orc-volapa" style="color:#6366f1;font-size:12px">—</td>
            <td class="taof-orc-custo-unit-label" style="color:#64748b;font-size:12px">—</td>
            <td class="taof-orc-subtotal" style="text-align:right;font-weight:600">R$ 0,00</td>
            <td><button type="button" class="taof-btn-del-item button button-small" style="color:#b91c1c" title="Remover">✕</button></td>
        </tr>
    </template>

    <!-- Template de linha de embalagem (oculto) -->
    <template id="taof-emb-tpl">
        <tr class="taof-emb-row">
            <td class="taof-td-emb">
                <div class="taof-autocomplete-wrap">
                    <input type="text" class="taof-emb-search" placeholder="Buscar embalagem..." autocomplete="off" style="width:100%">
                    <input type="hidden" class="taof-emb-id">
                    <div class="taof-autocomplete-results" style="display:none"></div>
                </div>
            </td>
            <td><input type="number" class="taof-emb-qty small-text" value="1" min="1" step="1" style="width:60px"></td>
            <td class="taof-emb-custo-label" style="color:#64748b;font-size:12px">—</td>
            <td class="taof-emb-subtotal" style="text-align:right;font-weight:600">R$ 0,00</td>
            <td><button type="button" class="taof-btn-del-emb button button-small" style="color:#b91c1c" title="Remover">✕</button></td>
        </tr>
    </template>

    <style>
    .taof-orc-section { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 22px; margin-bottom:16px; }
    .taof-orc-section h2 { margin:0 0 14px; font-size:15px; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:8px; }
    .taof-resumo { width:100%; max-width:400px; border-collapse:collapse; }
    .taof-resumo td { padding:7px 12px; font-size:14px; }
    .taof-resumo tr:nth-child(odd) { background:#f8fafc; }
    .taof-res-val { text-align:right; font-weight:600; width:140px; }
    .taof-res-base-row td { border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; }
    .taof-res-total-row td { font-size:18px; background:#f0f9ff; border-top:2px solid #0ea5e9; }
    .taof-res-total { color:#0369a1; }
    .taof-autocomplete-wrap { position:relative; }
    .taof-autocomplete-results {
        position:absolute; top:100%; left:0; right:0; background:#fff;
        border:1px solid #e2e8f0; border-radius:6px; z-index:200;
        box-shadow:0 4px 12px rgba(0,0,0,.12); max-height:220px; overflow-y:auto;
    }
    .taof-autocomplete-results .taof-ac-item {
        padding:7px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f1f5f9;
    }
    .taof-autocomplete-results .taof-ac-item:hover { background:#f0f9ff; }
    .taof-autocomplete-results .taof-ac-item small { color:#94a3b8; margin-left:6px; }
    #taof-itens-table input.small-text { width:70px !important; }
    #taof-itens-table select { max-width:70px; padding:4px 6px; }
    #taof-forma-mult-badge { display:none; }
    </style>

    <script>
    window.taofOrcFormas = <?php echo wp_json_encode( $formas_js ); ?>;
    window.taofOrcListUrl = <?php echo wp_json_encode( $url_lista ); ?>;
    </script>
    <?php
}
