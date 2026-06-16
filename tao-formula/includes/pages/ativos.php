<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_ativos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }

    $cliente_id = tao_formula_cliente_id();
    $busca      = sanitize_text_field( $_GET['s'] ?? '' );
    $filtro_gr  = sanitize_text_field( $_GET['grupo'] ?? '' );
    $ativos     = [];
    $total_mp   = 0;
    $total_emb  = 0;
    $ultima_sync= null;

    if ( $cliente_id ) {
        $qs = "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true&select=id,codigo_fc,nome,unidade,unidade_padrao,estoque_atual,preco_venda,custo_por_unidade,categoria,grupo,sincronizado_em&order=nome.asc&limit=150";
        if ( $busca )     $qs .= '&nome=ilike.*' . urlencode($busca) . '*';
        if ( $filtro_gr ) $qs .= "&grupo=eq.$filtro_gr";
        $r      = tao_formula_api( $qs );
        $ativos = $r['ok'] ? ( $r['data'] ?? [] ) : [];
        if ( $ativos ) $ultima_sync = $ativos[0]['sincronizado_em'] ?? null;

        $rt = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true&grupo=eq.M&select=id" );
        $total_mp  = $rt['ok'] ? count( $rt['data'] ?? [] ) : 0;
        $re = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true&grupo=eq.E&select=id" );
        $total_emb = $re['ok'] ? count( $re['data'] ?? [] ) : 0;
    }

    $base_url = tao_formula_url( 'formula-ativos' );
    ?>
    <div class="wrap taof-wrap">
    <h1>💊 Ativos (Matérias-Primas &amp; Embalagens)</h1>

    <div style="display:flex;align-items:center;gap:20px;margin-bottom:14px;flex-wrap:wrap;font-size:13px">
        <span><strong><?php echo number_format($total_mp); ?></strong> MPs</span>
        <span><strong><?php echo number_format($total_emb); ?></strong> Embalagens</span>
        <?php if ($ultima_sync) : ?>
        <span style="color:#64748b">Última sync: <strong><?php echo wp_date('d/m/Y H:i', strtotime($ultima_sync)); ?></strong></span>
        <?php endif; ?>
    </div>

    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px">
        <strong>🔄 Sincronização:</strong>
        Execute <code>.\sincronizar_tao.ps1</code> no computador com o Formula Certa. Sincroniza MPs e embalagens automaticamente.
    </div>

    <form method="get" action="<?php echo esc_url($base_url); ?>" style="margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <?php global $cbpm_is_frontend; if ( empty($cbpm_is_frontend) ) : ?><input type="hidden" name="page" value="tao-formula-ativos"><?php endif; ?>
        <select name="grupo" style="padding:6px 10px">
            <option value="">Todos os grupos</option>
            <option value="M" <?php selected($filtro_gr,'M'); ?>>Matérias-Primas</option>
            <option value="E" <?php selected($filtro_gr,'E'); ?>>Embalagens</option>
        </select>
        <input type="search" name="s" value="<?php echo esc_attr($busca); ?>" placeholder="Buscar por nome..." style="width:260px;padding:6px 10px">
        <button type="submit" class="button">Buscar</button>
        <?php if ($busca || $filtro_gr) : ?><a href="<?php echo esc_url($base_url); ?>" class="button">✕ Limpar</a><?php endif; ?>
    </form>

    <?php if ( empty($ativos) ) : ?>
        <div class="taof-empty-state"><p>Nenhum ativo encontrado<?php echo ($busca||$filtro_gr) ? '' : ' — execute o script de sincronização'; ?>.</p></div>
    <?php else : ?>
    <div class="taof-table-container">
    <table class="wp-list-table widefat fixed striped taof-table">
        <thead>
            <tr>
                <th style="width:8%">Código</th>
                <th style="width:29%">Nome <small style="font-weight:400;color:#94a3b8">(clique para detalhes)</small></th>
                <th style="width:6%">Grupo</th>
                <th style="width:10%">Unidade</th>
                <th style="width:10%;text-align:right">Estoque</th>
                <th style="width:12%;text-align:right">Custo/unid</th>
                <th style="width:10%;text-align:right">Preço Venda</th>
                <th>Categoria</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $ativos as $a ) : $gr = $a['grupo'] ?? 'M'; ?>
        <tr>
            <td style="color:#94a3b8;font-size:12px"><?php echo esc_html($a['codigo_fc']??'—'); ?></td>
            <td>
                <a href="#" class="taof-ativo-link" data-id="<?php echo esc_attr($a['id']); ?>"
                   style="font-weight:600;text-decoration:none;color:#2271b1"><?php echo esc_html($a['nome']); ?></a>
            </td>
            <td><?php echo $gr==='E' ? '<span style="font-size:11px;background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px">EMB</span>' : '<span style="font-size:11px;background:#f0fdf4;color:#166534;padding:1px 6px;border-radius:4px">MP</span>'; ?></td>
            <td style="font-size:12px"><?php echo esc_html($a['unidade']??'—'); ?> <span style="color:#94a3b8">(<?php echo esc_html($a['unidade_padrao']??''); ?>)</span></td>
            <td style="text-align:right"><?php echo number_format((float)($a['estoque_atual']??0),3,',','.'); ?></td>
            <td style="text-align:right;font-family:monospace">R$&nbsp;<?php echo number_format((float)($a['custo_por_unidade']??0),4,',','.'); ?></td>
            <td style="text-align:right;font-family:monospace">R$&nbsp;<?php echo number_format((float)($a['preco_venda']??0),2,',','.'); ?></td>
            <td style="font-size:12px;color:#64748b"><?php echo esc_html($a['categoria']??'—'); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if (count($ativos) >= 150) : ?><p style="color:#64748b;font-size:12px;margin-top:6px">Exibindo 150 resultados. Use a busca para filtrar.</p><?php endif; ?>
    <?php endif; ?>
    </div>

    <div id="taof-ativo-modal" style="display:none">
        <div class="taof-overlay"></div>
        <div class="taof-modal-box" style="width:700px;max-width:98vw">
            <div id="taof-ativo-modal-body" style="min-height:160px"></div>
            <div style="text-align:right;margin-top:14px;border-top:1px solid #e2e8f0;padding-top:10px">
                <button class="button" id="taof-ativo-modal-close">Fechar</button>
            </div>
        </div>
    </div>
    <script>
    (function($){
        if(typeof taoFormula==='undefined')return;
        function fmtN(n,d){return parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d});}
        function campo(lbl,val){if(val===null||val===undefined||val==='')return '';return '<div><span style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px">'+lbl+'</span><br><strong style="font-size:14px">'+val+'</strong></div>';}
        function grid(n,items){var f=items.filter(Boolean);if(!f.length)return '';return '<div style="display:grid;grid-template-columns:repeat('+n+',1fr);gap:12px 20px;margin-bottom:14px">'+f.join('')+'</div>';}
        $(document).on('click','.taof-ativo-link',function(e){
            e.preventDefault();
            var id=$(this).data('id');
            $('#taof-ativo-modal-body').html('<p style="text-align:center;padding:40px 0;color:#94a3b8">Carregando...</p>');
            $('#taof-ativo-modal').show();
            $.getJSON(taoFormula.ajaxUrl,{action:'tao_formula_get_ativo',nonce:taoFormula.nonce,id:id},function(r){
                if(!r.success){$('#taof-ativo-modal-body').html('<p style="color:#dc2626">Erro ao carregar.</p>');return;}
                var a=r.data;
                var badge=a.grupo==='E'?'<span style="background:#e0f2fe;color:#0369a1;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">Embalagem</span>':'<span style="background:#f0fdf4;color:#166534;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">Matéria-Prima</span>';
                var html='<h2 style="margin:0 0 4px;font-size:19px">'+a.nome+'</h2>';
                html+='<div style="margin-bottom:14px">'+badge+(a.codigo_fc?' <span style="color:#94a3b8;font-size:12px;margin-left:8px">FC: '+a.codigo_fc+'</span>':'')+'</div>';
                html+=grid(3,[campo('Unidade FC',a.unidade||'—'),campo('Unidade Padrão',a.unidade_padrao||'—'),campo('Estoque',a.estoque_atual!==null?fmtN(a.estoque_atual,3)+' '+(a.unidade||''):'—')]);
                html+=grid(3,[campo('Custo / '+(a.unidade_padrao||'unid'),'R$ '+fmtN(a.custo_por_unidade,4)),campo('Preço Compra',a.preco_compra?'R$ '+fmtN(a.preco_compra,2):'—'),campo('Preço Venda','R$ '+fmtN(a.preco_venda,2))]);
                if(a.grupo!=='E'){
                    html+=grid(4,[campo('Fator Correção',a.fator_correcao),campo('Fator Perda',a.fator_perda),campo('Densidade',a.densidade),campo('DCB',a.dcb||'—')]);
                    html+=grid(3,[campo('Dose Mínima',a.dose_min?a.dose_min+(a.uni_dose_min?' '+a.uni_dose_min:''):'—'),campo('Dose Máxima',a.dose_max?a.dose_max+(a.uni_dose_max?' '+a.uni_dose_max:''):'—'),campo('Princípio Ativo',a.principio_ativo||'—')]);
                }
                html+=grid(2,[campo('Categoria',a.categoria||'—'),campo('Classe Terapêutica',a.classe_terapeutica||'—')]);
                if(a.observacoes)html+='<div style="background:#f8fafc;border-radius:6px;padding:10px 14px;margin-top:4px"><span style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px">Observações</span><p style="margin:4px 0 0;font-size:13px">'+a.observacoes+'</p></div>';
                if(a.sincronizado_em){var d=new Date(a.sincronizado_em);html+='<p style="color:#94a3b8;font-size:11px;margin-top:14px">Sincronizado em '+d.toLocaleString('pt-BR')+'</p>';}
                $('#taof-ativo-modal-body').html(html);
            });
        });
        $('#taof-ativo-modal-close,#taof-ativo-modal .taof-overlay').on('click',function(){$('#taof-ativo-modal').hide();});
    })(jQuery);
    </script>
    <?php
}
