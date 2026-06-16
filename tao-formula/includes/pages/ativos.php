<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_ativos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();
    $busca      = sanitize_text_field( $_GET['s'] ?? '' );
    $ativos     = [];
    $total      = 0;
    $ultima_sync= null;

    if ( $cliente_id ) {
        $qs = "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true&select=id,codigo_fc,nome,unidade,estoque_atual,preco_venda,categoria,sincronizado_em&order=nome.asc&limit=100";
        if ( $busca ) $qs .= '&nome=ilike.*' . urlencode($busca) . '*';
        $r      = tao_formula_api( $qs );
        $ativos = $r['ok'] ? ( $r['data'] ?? [] ) : [];
        if ( $ativos ) {
            $ultima_sync = $ativos[0]['sincronizado_em'] ?? null;
        }
        $rt     = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true&select=id" );
        $total  = $rt['ok'] ? count( $rt['data'] ?? [] ) : 0;
    }
    ?>
    <div class="wrap taof-wrap">
    <h1>💊 Ativos (Matérias-Primas)</h1>

    <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap">
        <div>
            <strong><?php echo number_format($total); ?></strong> ativos cadastrados
            <?php if ($ultima_sync) : ?>
            — última sincronização: <strong><?php echo wp_date('d/m/Y H:i', strtotime($ultima_sync)); ?></strong>
            <?php endif; ?>
        </div>
    </div>

    <!-- Instruções de sincronização -->
    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px 18px;margin-bottom:20px;font-size:13px">
        <strong>🔄 Como sincronizar com Formula Certa:</strong><br>
        Execute o script PowerShell no computador com o Formula Certa instalado:
        <pre style="background:#1e293b;color:#f1f5f9;padding:10px 14px;border-radius:6px;margin:10px 0 0;font-size:12px">.\sincronizar_tao.ps1</pre>
        O script extrai as matérias-primas do Firebird e atualiza esta tabela automaticamente.
    </div>

    <!-- Busca -->
    <form method="get" action="<?php echo esc_url( tao_formula_url('formula-ativos') ); ?>" style="margin-bottom:12px">
        <?php global $cbpm_is_frontend; if ( empty($cbpm_is_frontend) ) : ?><input type="hidden" name="page" value="tao-formula-ativos"><?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($busca); ?>" placeholder="Buscar ativo..." style="width:280px;padding:6px 10px">
        <button type="submit" class="button">Buscar</button>
        <?php if ($busca) : ?><a href="<?php echo esc_url( tao_formula_url('formula-ativos') ); ?>" class="button">✕ Limpar</a><?php endif; ?>
    </form>

    <?php if ( empty($ativos) ) : ?>
        <div class="taof-empty-state">
            <p>Nenhum ativo encontrado<?php echo $busca ? " para \"$busca\"" : ''; ?>.</p>
            <?php if (!$busca) : ?><p>Execute <code>.\sincronizar_tao.ps1</code> para importar as matérias-primas do Formula Certa.</p><?php endif; ?>
        </div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped taof-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nome</th>
                <th>Unidade</th>
                <th style="text-align:right">Estoque</th>
                <th style="text-align:right">Preço Venda (R$)</th>
                <th>Categoria</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $ativos as $a ) : ?>
        <tr>
            <td style="color:#94a3b8;font-size:12px"><?php echo esc_html($a['codigo_fc']??'—'); ?></td>
            <td><strong><?php echo esc_html($a['nome']); ?></strong></td>
            <td><?php echo esc_html($a['unidade']??'—'); ?></td>
            <td style="text-align:right"><?php echo number_format((float)($a['estoque_atual']??0),3,',','.'); ?></td>
            <td style="text-align:right">R$&nbsp;<?php echo number_format((float)($a['preco_venda']??0),2,',','.'); ?></td>
            <td style="font-size:12px;color:#64748b"><?php echo esc_html($a['categoria']??'—'); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (count($ativos) === 100) : ?>
    <p style="color:#64748b;font-size:12px;margin-top:8px">Exibindo 100 resultados. Use a busca para filtrar.</p>
    <?php endif; ?>
    <?php endif; ?>
    </div>
    <?php
}
