<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_formas() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();
    $formas     = [];
    $capsulas   = [];
    if ( $cliente_id ) {
        $r      = tao_formula_api( "/formas_farmaceuticas?cliente_id=eq.$cliente_id&order=nome.asc" );
        $formas = $r['ok'] ? ( $r['data'] ?? [] ) : [];

        $rc       = tao_formula_api( "/tipos_capsula?cliente_id=eq.$cliente_id&ativo=eq.true&order=tipo.asc,numero.asc&select=tipo,numero,vol_ul,peso_vazio_mg" );
        $capsulas = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];
    }

    $tipos = [
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

    // Tipos únicos de cápsula para o select
    $tipos_cap = array_unique( array_column( $capsulas, 'tipo' ) );
    sort( $tipos_cap );
    ?>
    <div class="wrap taof-wrap">
    <h1 class="wp-heading-inline">&#x1F9EA; Formas Farmacêuticas</h1>
    <button class="page-title-action taof-btn-nova" id="taof-btn-nova-forma">+ Nova Forma</button>
    <hr class="wp-header-end">

    <?php if ( ! $cliente_id ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado. Verifique as configurações do TAO Neo.</p></div>
    <?php endif; ?>

    <!-- ── Formulário (modal) ── -->
    <div id="taof-forma-modal" style="display:none">
        <div class="taof-overlay"></div>
        <div class="taof-modal-box">
            <h2 id="taof-modal-title">Nova Forma Farmacêutica</h2>
            <form id="taof-forma-form">
                <input type="hidden" id="taof-forma-id" name="id">

                <table class="form-table taof-form-table">
                    <tr>
                        <th><label for="taof-nome">Nome *</label></th>
                        <td><input type="text" id="taof-nome" name="nome" class="regular-text" placeholder="Ex: Cápsula Gelatinosa 30 un." required></td>
                    </tr>
                    <tr>
                        <th><label for="taof-tipo">Tipo *</label></th>
                        <td>
                            <select id="taof-tipo" name="tipo">
                                <?php foreach ( $tipos as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <!-- Gel/Solução/Pó: volume total -->
                    <tr id="taof-row-volume">
                        <th><label for="taof-volume">Volume / Peso Total</label></th>
                        <td style="display:flex;gap:8px;align-items:center">
                            <input type="number" id="taof-volume" name="volume" class="small-text" step="0.1" min="0" placeholder="30">
                            <select id="taof-unidade-volume" name="unidade_volume">
                                <option value="g">g</option>
                                <option value="ml">ml</option>
                                <option value="mg">mg</option>
                            </select>
                        </td>
                    </tr>

                    <!-- Cápsula: quantidade -->
                    <tr id="taof-row-capsulas" style="display:none">
                        <th><label for="taof-ncap">Qtd. Cápsulas</label></th>
                        <td><input type="number" id="taof-ncap" name="n_capsulas" class="small-text" min="1" placeholder="30"></td>
                    </tr>

                    <!-- Cápsula: tipo de cápsula -->
                    <tr id="taof-row-cap-tipo" style="display:none">
                        <th><label for="taof-cap-tipo">Tipo de Cápsula</label></th>
                        <td>
                            <select id="taof-cap-tipo" name="tipo_capsula">
                                <option value="">— Selecione o material —</option>
                                <?php foreach ( $tipos_cap as $tc ) : ?>
                                <option value="<?php echo esc_attr($tc); ?>"><?php echo esc_html(ucfirst(strtolower($tc))); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <!-- Cápsula: tamanho (número) — populado via JS conforme tipo -->
                    <tr id="taof-row-cap-numero" style="display:none">
                        <th><label for="taof-cap-numero">Tamanho (Nº)</label></th>
                        <td>
                            <select id="taof-cap-numero" name="numero_capsula">
                                <option value="">— Selecione o tamanho —</option>
                            </select>
                            <input type="hidden" id="taof-cap-vol-ul" name="vol_cap_ul">
                            <span id="taof-cap-vol-info" style="margin-left:10px;font-size:13px;color:#0369a1;font-weight:600"></span>
                        </td>
                    </tr>

                    <!-- Cápsula: fator de enchimento -->
                    <tr id="taof-row-ftenchcap" style="display:none">
                        <th>
                            <label for="taof-ftenchcap">Fator Enchimento</label>
                            <span class="taof-help" title="Percentual do volume interno utilizado. 1.0 = 100% (cápsula cheia). Use 0.9 se a farmácia trabalha com 90% do volume.">?</span>
                        </th>
                        <td>
                            <input type="number" id="taof-ftenchcap" name="ftenchcap" class="small-text" step="0.01" min="0.1" max="1.5" value="1" placeholder="1.0">
                            <p class="description">Volume útil = vol. cápsula × fator. Padrão: 1.0</p>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            <label>Custo Fixo da Forma</label>
                            <span class="taof-help" title="Quando definido (R$ ou %), é exibido no orçamento e o cálculo automático é desativado">?</span>
                        </th>
                        <td>
                            <select id="taof-custo-fixo-tipo" name="custo_fixo_tipo" style="margin-bottom:6px;width:100%;max-width:340px">
                                <option value="">Calcular automaticamente no orçamento</option>
                                <option value="R">Valor fixo (R$) — define o custo de manipulação</option>
                                <option value="pct">Percentual (% sobre MPs)</option>
                            </select>
                            <div id="taof-custo-fixo-val-row" style="display:none;margin-top:4px;display:flex;align-items:center;gap:6px">
                                <input type="number" id="taof-custo-fixo" name="custo_fixo" class="small-text" step="0.01" min="0" placeholder="0,00">
                                <span id="taof-custo-fixo-unit" style="font-size:13px;color:#475569">R$</span>
                            </div>
                            <p class="description" id="taof-custo-fixo-desc" style="display:none">Exibido no orçamento ao selecionar esta forma — cálculo automático desativado</p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="taof-valor-minimo">Valor Mínimo (R$)</label>
                            <span class="taof-help" title="Se o total calculado for inferior a este valor, prevalece o valor mínimo">?</span>
                        </th>
                        <td>
                            <input type="number" id="taof-valor-minimo" name="valor_minimo" class="small-text" step="0.01" min="0" placeholder="0,00">
                            <p class="description">Se o total calculado for inferior, prevalece este valor</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="taof-margem">Margem (%)</label></th>
                        <td>
                            <input type="number" id="taof-margem" name="margem_pct" class="small-text" step="0.1" min="0" max="500" placeholder="30">
                            <p class="description">Margem aplicada sobre (insumos + custo fixo)</p>
                        </td>
                    </tr>
                </table>

                <div class="taof-modal-actions">
                    <button type="submit" class="button button-primary" id="taof-btn-salvar">Salvar</button>
                    <button type="button" class="button" id="taof-btn-cancelar">Cancelar</button>
                    <span class="taof-spinner spinner" style="float:none;visibility:hidden"></span>
                    <span class="taof-msg" style="display:none"></span>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Tabela de formas ── -->
    <?php if ( empty( $formas ) ) : ?>
        <div class="taof-empty-state">
            <p>&#x1F9EA; Nenhuma forma farmacêutica cadastrada ainda.</p>
            <button class="button button-primary taof-btn-nova">+ Cadastrar primeira forma</button>
        </div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped taof-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Volume / Cápsulas</th>
                <th>Cápsula</th>
                <th style="text-align:right">Custo Fixo</th>
                <th style="text-align:right">Val. Mín.</th>
                <th style="text-align:right">Margem</th>
                <th style="text-align:center">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $formas as $f ) :
            $tipo_lbl = $tipos[ $f['tipo'] ?? 'outro' ] ?? $f['tipo'];
            if ( ($f['tipo'] ?? '') === 'cap' ) {
                $vol_str = ( $f['n_capsulas'] ?? '—' ) . ' cáps.';
                $cap_str = '';
                if ( ! empty( $f['tipo_capsula'] ) ) {
                    $cap_str = ucfirst( strtolower( $f['tipo_capsula'] ) ) . ' nº' . ( $f['numero_capsula'] ?? '?' );
                    if ( ! empty( $f['vol_cap_ul'] ) ) {
                        $cap_str .= ' — ' . number_format( (float)$f['vol_cap_ul'], 0, ',', '.' ) . ' µL';
                    }
                }
            } else {
                $vol_str = $f['volume'] ? number_format( (float)$f['volume'], 1, ',', '.' ) . ' ' . ( $f['unidade_volume'] ?? 'g' ) : '—';
                $cap_str = '—';
            }
        ?>
        <tr data-id="<?php echo esc_attr($f['id']); ?>"
            data-nome="<?php echo esc_attr($f['nome']); ?>"
            data-tipo="<?php echo esc_attr($f['tipo'] ?? ''); ?>"
            data-volume="<?php echo esc_attr($f['volume'] ?? ''); ?>"
            data-unidade-volume="<?php echo esc_attr($f['unidade_volume'] ?? 'g'); ?>"
            data-n-capsulas="<?php echo esc_attr($f['n_capsulas'] ?? ''); ?>"
            data-custo-fixo="<?php echo esc_attr($f['custo_fixo'] ?? 0); ?>"
            data-custo-fixo-tipo="<?php echo esc_attr($f['custo_fixo_tipo'] ?? ''); ?>"
            data-valor-minimo="<?php echo esc_attr($f['valor_minimo'] ?? ''); ?>"
            data-margem-pct="<?php echo esc_attr($f['margem_pct'] ?? 30); ?>"
            data-tipo-capsula="<?php echo esc_attr($f['tipo_capsula'] ?? ''); ?>"
            data-numero-capsula="<?php echo esc_attr($f['numero_capsula'] ?? ''); ?>"
            data-vol-cap-ul="<?php echo esc_attr($f['vol_cap_ul'] ?? ''); ?>"
            data-ftenchcap="<?php echo esc_attr($f['ftenchcap'] ?? 1); ?>">
            <td><strong><?php echo esc_html($f['nome']); ?></strong></td>
            <td><?php echo esc_html($tipo_lbl); ?></td>
            <td><?php echo esc_html($vol_str); ?></td>
            <td style="font-size:13px;color:#475569"><?php echo esc_html($cap_str); ?></td>
            <td style="text-align:right;font-size:12px">
                <?php
                $cf_tipo = $f['custo_fixo_tipo'] ?? '';
                $cf_val  = (float)($f['custo_fixo'] ?? 0);
                if ( $cf_tipo === 'R' ) {
                    echo '<span style="color:#0369a1;font-weight:600">R$&nbsp;' . number_format($cf_val,2,',','.') . '</span><br><small style="color:#94a3b8">fixo</small>';
                } elseif ( $cf_tipo === 'pct' ) {
                    echo '<span style="color:#7c3aed;font-weight:600">' . number_format($cf_val,1,',','.') . '%</span><br><small style="color:#94a3b8">sobre MP</small>';
                } else {
                    echo '<span style="color:#94a3b8;font-size:11px">automático</span>';
                }
                ?>
            </td>
            <td style="text-align:right;font-size:12px">
                <?php
                $vm = $f['valor_minimo'] ?? null;
                echo $vm !== null && $vm > 0 ? 'R$&nbsp;' . number_format((float)$vm,2,',','.') : '<span style="color:#94a3b8">—</span>';
                ?>
            </td>
            <td style="text-align:right"><?php echo number_format((float)($f['margem_pct']??30), 1, ',', '.'); ?>%</td>
            <td style="text-align:center">
                <button class="button button-small taof-btn-edit" data-row>✏️ Editar</button>
                <button class="button button-small taof-btn-del" data-row style="color:#b91c1c">🗑️ Excluir</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div style="margin-top:20px;padding:14px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;font-size:13px;color:#0c4a6e">
        <strong>&#x2139;&#xFE0F; Sobre o Fator de Enchimento:</strong>
        Define quanto do volume interno da cápsula é utilizado. Padrão = 1.0 (100%).
        O orçamento soma o VOLAPA de cada ativo e alerta se ultrapassar <em>volume × fator</em>.
    </div>

    </div><!-- .taof-wrap -->

    <script>
    window.taofCapsulas = <?php echo wp_json_encode( array_values( $capsulas ) ); ?>;
    </script>
    <?php
}
