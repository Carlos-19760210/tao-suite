<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_formas() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();
    $formas     = [];
    if ( $cliente_id ) {
        $r      = tao_formula_api( "/formas_farmaceuticas?cliente_id=eq.$cliente_id&order=nome.asc" );
        $formas = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    }

    $tipos = [
        'gel'     => 'Gel / Creme',
        'cap'     => 'Cápsula',
        'solucao' => 'Solução / Suspensão',
        'po'      => 'Pó / Sachê',
        'outro'   => 'Outro',
    ];
    ?>
    <div class="wrap taof-wrap">
    <h1 class="wp-heading-inline">&#x1F9EA; Formas Farmacêuticas</h1>
    <button class="page-title-action taof-btn-nova" id="taof-btn-nova-forma">+ Nova Forma</button>
    <hr class="wp-header-end">

    <?php if ( ! $cliente_id ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado. Verifique as configurações do TAO Neo.</p></div>
    <?php endif; ?>

    <!-- ── Formulário (oculto por padrão) ── -->
    <div id="taof-forma-modal" style="display:none">
        <div class="taof-overlay"></div>
        <div class="taof-modal-box">
            <h2 id="taof-modal-title">Nova Forma Farmacêutica</h2>
            <form id="taof-forma-form">
                <input type="hidden" id="taof-forma-id" name="id">

                <table class="form-table taof-form-table">
                    <tr>
                        <th><label for="taof-nome">Nome *</label></th>
                        <td><input type="text" id="taof-nome" name="nome" class="regular-text" placeholder="Ex: Gel Manipulado 30g" required></td>
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
                    <tr id="taof-row-volume">
                        <th><label for="taof-volume">Volume</label></th>
                        <td style="display:flex;gap:8px;align-items:center">
                            <input type="number" id="taof-volume" name="volume" class="small-text" step="0.1" min="0" placeholder="30">
                            <select id="taof-unidade-volume" name="unidade_volume">
                                <option value="g">g</option>
                                <option value="ml">ml</option>
                                <option value="mg">mg</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="taof-row-capsulas" style="display:none">
                        <th><label for="taof-ncap">Qtd. Cápsulas</label></th>
                        <td><input type="number" id="taof-ncap" name="n_capsulas" class="small-text" min="1" placeholder="30"></td>
                    </tr>
                    <tr>
                        <th>
                            <label for="taof-custo-fixo">Custo Fixo (R$)</label>
                            <span class="taof-help" title="Honorários fixos da forma — valor somado ao custo dos ativos para compor o preço final">?</span>
                        </th>
                        <td>
                            <input type="number" id="taof-custo-fixo" name="custo_fixo" class="small-text" step="0.01" min="0" placeholder="0,00">
                            <p class="description">Custo fixo adicionado ao custo dos insumos (honorários da manipulação)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="taof-margem">Margem (%)</label></th>
                        <td>
                            <input type="number" id="taof-margem" name="margem_pct" class="small-text" step="0.1" min="0" max="100" placeholder="30">
                            <p class="description">Margem aplicada sobre (insumos + custo fixo) para calcular preço final ao paciente</p>
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
                <th style="text-align:right">Custo Fixo (R$)</th>
                <th style="text-align:right">Margem (%)</th>
                <th style="text-align:center">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $formas as $f ) :
            $tipo_lbl = $tipos[ $f['tipo'] ?? 'outro' ] ?? $f['tipo'];
            if ( $f['tipo'] === 'cap' ) {
                $vol_str = ( $f['n_capsulas'] ?? '—' ) . ' cáps.';
            } else {
                $vol_str = $f['volume'] ? $f['volume'] . ' ' . ( $f['unidade_volume'] ?? 'g' ) : '—';
            }
        ?>
        <tr data-id="<?php echo esc_attr($f['id']); ?>"
            data-nome="<?php echo esc_attr($f['nome']); ?>"
            data-tipo="<?php echo esc_attr($f['tipo']); ?>"
            data-volume="<?php echo esc_attr($f['volume'] ?? ''); ?>"
            data-unidade-volume="<?php echo esc_attr($f['unidade_volume'] ?? 'g'); ?>"
            data-n-capsulas="<?php echo esc_attr($f['n_capsulas'] ?? ''); ?>"
            data-custo-fixo="<?php echo esc_attr($f['custo_fixo'] ?? 0); ?>"
            data-margem-pct="<?php echo esc_attr($f['margem_pct'] ?? 30); ?>">
            <td><strong><?php echo esc_html($f['nome']); ?></strong></td>
            <td><?php echo esc_html($tipo_lbl); ?></td>
            <td><?php echo esc_html($vol_str); ?></td>
            <td style="text-align:right">R$&nbsp;<?php echo number_format((float)($f['custo_fixo']??0), 2, ',', '.'); ?></td>
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
        <strong>&#x2139;&#xFE0F; Sobre o Custo Fixo:</strong>
        O valor aqui configurado representa os <em>honorários da manipulação</em> — custo fixo somado ao custo dos insumos para compor o preço final.
        Exemplo: Gel 30g com insumos = R$&nbsp;45,00 + Custo Fixo = R$&nbsp;22,45 → Total insumos+honorários = R$&nbsp;67,45 → com margem de 30% = R$&nbsp;87,69.
    </div>

    </div><!-- .taof-wrap -->
    <?php
}
