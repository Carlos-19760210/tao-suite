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

    <div style="display:flex;gap:4px;border-bottom:2px solid #e2e8f0;margin:0 0 16px">
        <button type="button" class="taof-tab-btn" data-pane="ativos"
                style="background:none;border:none;border-bottom:2px solid #2271b1;padding:8px 16px;cursor:pointer;font-size:14px;color:#2271b1;font-weight:600;margin-bottom:-2px">Ativos</button>
        <button type="button" class="taof-tab-btn" data-pane="sinonimos"
                style="background:none;border:none;border-bottom:2px solid transparent;padding:8px 16px;cursor:pointer;font-size:14px;color:#475569;margin-bottom:-2px">Sinônimos</button>
    </div>

    <div id="taof-pane-ativos">

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
        <select name="grupo" style="padding:6px 10px" onchange="this.form.submit()">
            <option value="">Todos os grupos</option>
            <option value="M" <?php selected($filtro_gr,'M'); ?>>Matérias-Primas</option>
            <option value="E" <?php selected($filtro_gr,'E'); ?>>Embalagens</option>
        </select>
        <div style="position:relative;display:inline-block">
            <input type="search" id="taof-s-inp" name="s"
                   value="<?php echo esc_attr($busca); ?>"
                   placeholder="Buscar por nome ou código…"
                   autocomplete="off"
                   style="width:300px;padding:6px 10px">
            <div id="taof-s-dd"
                 style="display:none;position:absolute;top:calc(100% + 2px);left:0;min-width:100%;width:480px;max-width:90vw;
                        background:#fff;border:1px solid #e2e8f0;border-radius:8px;
                        box-shadow:0 4px 16px rgba(0,0,0,.14);z-index:9999;max-height:320px;overflow-y:auto"></div>
        </div>
        <button type="submit" class="button">Buscar</button>
        <?php if ($busca || $filtro_gr) : ?><a href="<?php echo esc_url($base_url); ?>" class="button">✕ Limpar</a><?php endif; ?>
        <span id="taof-s-count" style="font-size:12px;color:#64748b;display:none"></span>
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
        <tbody id="taof-tbody">
        <?php foreach ( $ativos as $a ) : $gr = $a['grupo'] ?? 'M'; ?>
        <tr data-nome="<?php echo esc_attr( strtolower( $a['nome'] ?? '' ) ); ?>"
            data-cod="<?php echo esc_attr( strtolower( $a['codigo_fc'] ?? '' ) ); ?>">
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
    </div><!-- /taof-pane-ativos -->

    <div id="taof-pane-sinonimos" style="display:none">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
            <input type="search" id="taof-sin-q" placeholder="Buscar sinônimo..." style="width:260px;padding:6px 10px">
            <label style="font-size:13px;display:flex;align-items:center;gap:5px"><input type="checkbox" id="taof-sin-sem"> Somente sem associação</label>
            <button type="button" class="button" id="taof-sin-buscar">Buscar</button>
            <button type="button" class="button button-primary" id="taof-sin-novo-btn">+ Novo sinônimo</button>
            <span id="taof-sin-count" style="font-size:12px;color:#64748b"></span>
        </div>

        <div id="taof-sin-novo-form" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;position:relative">
            <input type="text" id="taof-sin-novo-nome" placeholder="Sinônimo" style="width:230px;padding:6px 10px">
            <span style="margin:0 6px;color:#64748b">→ Ativo:</span>
            <input type="text" id="taof-sin-novo-ativo-q" placeholder="buscar ativo (opcional)" autocomplete="off" style="width:240px;padding:6px 10px">
            <input type="hidden" id="taof-sin-novo-ativo-id">
            <span id="taof-sin-novo-ativo-sel" style="font-size:12px;color:#16a34a"></span>
            <button type="button" class="button button-primary" id="taof-sin-novo-salvar" style="margin-left:6px">Criar</button>
            <div id="taof-sin-novo-ativo-dd" style="display:none;position:absolute;top:46px;left:300px;min-width:280px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);z-index:60;max-height:240px;overflow:auto"></div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th style="width:30%" id="taof-sin-th1">Sinônimo</th><th id="taof-sin-th2">Ativo associado</th><th style="width:250px"></th></tr></thead>
            <tbody id="taof-sin-tbody"><tr><td colspan="3" style="color:#94a3b8">Carregando...</td></tr></tbody>
        </table>
    </div>
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

    <style>
    .taof-s-item{padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;font-size:13px;line-height:1.4}
    .taof-s-item:last-child{border-bottom:none}
    .taof-s-item.taof-s-hl{background:#eff6ff}
    .taof-s-item:hover{background:#f8fafc}
    .taof-s-item.taof-s-hl{background:#eff6ff!important}
    </style>
    <script>
    (function(){
        var _nonce   = '<?php echo esc_js( wp_create_nonce('tao_formula_nonce') ); ?>';
        var _ajaxUrl = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
        var _ready   = false;

        function taofAtivosSetup() {
            if (_ready || typeof jQuery === 'undefined') return;
            _ready = true;
            var $ = jQuery;

            function fmtN(n,d){return parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d});}
            function campo(lbl,val){if(val===null||val===undefined||val==='')return '';return '<div><span style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px">'+lbl+'</span><br><strong style="font-size:14px">'+val+'</strong></div>';}
            function grid(n,items){var f=items.filter(Boolean);if(!f.length)return '';return '<div style="display:grid;grid-template-columns:repeat('+n+',1fr);gap:12px 20px;margin-bottom:14px">'+f.join('')+'</div>';}
            function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

            function renderSinonimos(id, sins) {
                var tags = sins.map(function(s){
                    return '<span style="display:inline-flex;align-items:center;gap:4px;background:#e0f2fe;color:#0369a1;border-radius:12px;padding:2px 10px;margin:2px;font-size:12px">' +
                        escH(s.sinonimo) +
                        '<button class="taof-sin-del-modal" data-sid="'+s.id+'" data-aid="'+id+'" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;line-height:1;padding:0 2px 0 4px">&times;</button>' +
                        '</span>';
                }).join('');
                $('#taof-sin-ativo-tags').html(tags || '<span style="color:#94a3b8;font-size:12px">Nenhum sinônimo. Adicione abaixo.</span>');
                $('#taof-sin-ativo-count').text('(' + sins.length + ')');
            }

            function reloadSinonimos(id) {
                $.post(_ajaxUrl, {action:'tao_formula_listar_sinonimos', nonce:_nonce, ativo_id:id}, function(r){
                    renderSinonimos(id, r.success ? r.data : []);
                });
            }

            function renderModalAtivo(a) {
                var badge=a.grupo==='E'?'<span style="background:#e0f2fe;color:#0369a1;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">Embalagem</span>':'<span style="background:#f0fdf4;color:#166534;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">Matéria-Prima</span>';
                var html='<h2 style="margin:0 0 4px;font-size:19px">'+escH(a.nome)+'</h2>';
                html+='<div style="margin-bottom:14px">'+badge+(a.codigo_fc?' <span style="color:#94a3b8;font-size:12px;margin-left:8px">FC: '+escH(a.codigo_fc)+'</span>':'')+'</div>';
                html+=grid(3,[campo('Unidade FC',a.unidade||'—'),campo('Unidade Padrão',a.unidade_padrao||'—'),campo('Estoque',a.estoque_atual!==null?fmtN(a.estoque_atual,3)+' '+(a.unidade||''):'—')]);
                html+=grid(3,[campo('Custo / '+(a.unidade_padrao||'unid'),'R$ '+fmtN(a.custo_por_unidade,4)),campo('Preço Compra',a.preco_compra?'R$ '+fmtN(a.preco_compra,2):'—'),campo('Preço Venda','R$ '+fmtN(a.preco_venda,2))]);
                if(a.grupo!=='E'){
                    html+=grid(4,[campo('Fator Correção',a.fator_correcao),campo('Fator Perda',a.fator_perda),campo('Densidade',a.densidade),campo('DCB',a.dcb||'—')]);
                    html+=grid(3,[campo('Dose Mínima',a.dose_min?a.dose_min+(a.uni_dose_min?' '+a.uni_dose_min:''):'—'),campo('Dose Máxima',a.dose_max?a.dose_max+(a.uni_dose_max?' '+a.uni_dose_max:''):'—'),campo('Princípio Ativo',a.principio_ativo||'—')]);
                }
                html+=grid(2,[campo('Categoria',a.categoria||'—'),campo('Classe Terapêutica',a.classe_terapeutica||'—')]);
                if(a.observacoes)html+='<div style="background:#f8fafc;border-radius:6px;padding:10px 14px;margin-top:4px"><span style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px">Observações</span><p style="margin:4px 0 0;font-size:13px">'+escH(a.observacoes)+'</p></div>';
                if(a.sincronizado_em){var d=new Date(a.sincronizado_em);html+='<p style="color:#94a3b8;font-size:11px;margin-top:8px">Sincronizado em '+d.toLocaleString('pt-BR')+'</p>';}
                html += '<div style="border-top:1px solid #e2e8f0;margin-top:16px;padding-top:14px">';
                html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">';
                html += '<strong style="font-size:13px">&#x1F3F7; Sin&ocirc;nimos <span id="taof-sin-ativo-count" style="font-size:11px;color:#94a3b8;font-weight:400"></span></strong>';
                html += '</div>';
                html += '<div id="taof-sin-ativo-tags" style="margin-bottom:10px;min-height:24px"><span style="color:#94a3b8;font-size:12px">Carregando...</span></div>';
                html += '<div style="display:flex;gap:6px;align-items:center">';
                html += '<input type="text" id="taof-sin-ativo-inp" placeholder="Novo sin&ocirc;nimo (ex: VIT D3)" style="font-size:12px;width:230px;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px">';
                html += '<button type="button" class="button button-small" id="taof-sin-ativo-add" data-aid="'+a.id+'">+ Adicionar</button>';
                html += '<span id="taof-sin-ativo-msg" style="font-size:12px"></span>';
                html += '</div>';
                html += '</div>';
                $('#taof-ativo-modal-body').html(html);
                reloadSinonimos(a.id);
            }

            function abrirModalAtivo(id) {
                $('#taof-ativo-modal-body').html('<p style="text-align:center;padding:40px 0;color:#94a3b8">Carregando...</p>');
                $('#taof-ativo-modal').show();
                $.getJSON(_ajaxUrl,{action:'tao_formula_get_ativo',nonce:_nonce,id:id},function(r){
                    if(!r.success){$('#taof-ativo-modal-body').html('<p style="color:#dc2626">Erro ao carregar: '+(r.data||'desconhecido')+'</p>');return;}
                    renderModalAtivo(r.data);
                }).fail(function(){
                    $('#taof-ativo-modal-body').html('<p style="color:#dc2626">Falha na comunicação com o servidor.</p>');
                });
            }

            $(document).on('click','.taof-ativo-link',function(e){
                e.preventDefault();
                abrirModalAtivo($(this).data('id'));
            });

            // ── Busca dinâmica: filtra tabela + dropdown com setas ────────
            var $inp   = $('#taof-s-inp');
            var $dd    = $('#taof-s-dd');
            var $count = $('#taof-s-count');
            var $rows  = $('#taof-tbody tr');
            var _timer = null;
            var _idx   = -1;

            function ddItems(){ return $dd.find('.taof-s-item[data-sel]'); }

            function ddHighlight(idx){
                var $items = ddItems();
                $items.removeClass('taof-s-hl');
                if(idx>=0 && idx<$items.length) $items.eq(idx).addClass('taof-s-hl');
                _idx = idx;
            }

            function ddSelect($item){
                var id = $item.data('id');
                $dd.hide().empty();
                _idx = -1;
                if(id) abrirModalAtivo(id);
            }

            function filterRows(q){
                if(!q){ $rows.show(); $count.hide(); return; }
                var vis = 0;
                $rows.each(function(){
                    var $tr  = $(this);
                    var nome = $tr.data('nome') || '';
                    var cod  = $tr.data('cod')  || '';
                    var show = nome.indexOf(q) !== -1 || cod.indexOf(q) !== -1;
                    $tr.toggle(show);
                    if(show) vis++;
                });
                $count.text(vis + ' resultado(s)').show();
            }

            $inp.on('input', function(){
                clearTimeout(_timer);
                var q = $(this).val().trim().toLowerCase();

                // Filtro imediato das linhas da tabela (client-side)
                filterRows(q);

                if(q.length < 2){ $dd.hide().empty(); return; }

                $dd.html('<div style="padding:10px 14px;color:#94a3b8;font-size:12px">Buscando…</div>').show();

                _timer = setTimeout(function(){
                    $.getJSON(_ajaxUrl,{action:'tao_formula_search_ativos',nonce:_nonce,q:q},function(resp){
                        $dd.empty();
                        var lista = resp.success && Array.isArray(resp.data) ? resp.data : [];
                        if(!lista.length){
                            $dd.html('<div style="padding:10px 14px;color:#94a3b8;font-size:12px">Nenhum resultado.</div>');
                            return;
                        }
                        lista.forEach(function(a){
                            var grBadge = a.grupo==='E'
                                ? '<span style="font-size:10px;background:#e0f2fe;color:#0369a1;padding:1px 5px;border-radius:4px;margin-right:6px;flex-shrink:0">EMB</span>'
                                : '<span style="font-size:10px;background:#f0fdf4;color:#166534;padding:1px 5px;border-radius:4px;margin-right:6px;flex-shrink:0">MP</span>';
                            var $item = $('<div class="taof-s-item" data-sel="1">').html(
                                '<span style="display:flex;align-items:center;min-width:0">'+grBadge+
                                '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+escH(a.nome)+
                                (a.codigo_fc?' <span style="color:#94a3b8;font-size:11px">['+escH(a.codigo_fc)+']</span>':'')+
                                '</span></span>'+
                                '<small style="color:#64748b;font-size:11px;flex-shrink:0;margin-left:10px">R$ '+fmtN(a.preco_venda,2)+'</small>'
                            );
                            $item.data({id:a.id});
                            $item.on('mousedown',function(e){ e.preventDefault(); ddSelect($(this)); });
                            $item.on('mouseenter',function(){ ddHighlight(ddItems().index($(this))); });
                            $dd.append($item);
                        });
                        _idx = -1;
                    }).fail(function(){
                        $dd.html('<div style="padding:10px 14px;color:#dc2626;font-size:12px">Falha na busca.</div>');
                    });
                }, 280);
            });

            $inp.on('keydown',function(e){
                var $items = ddItems();
                if(e.key==='ArrowDown'){
                    e.preventDefault();
                    if(!$dd.is(':visible') && $(this).val().trim().length>=2) $dd.show();
                    ddHighlight(Math.min(_idx+1,$items.length-1));
                } else if(e.key==='ArrowUp'){
                    e.preventDefault();
                    ddHighlight(Math.max(_idx-1,0));
                } else if(e.key==='Enter'){
                    if(_idx>=0 && $dd.is(':visible')){ e.preventDefault(); ddSelect($items.eq(_idx)); }
                } else if(e.key==='Escape'){
                    $dd.hide(); _idx=-1;
                }
            });

            $inp.on('blur',function(){ setTimeout(function(){ $dd.hide(); _idx=-1; },160); });
            $(document).on('click',function(e){ if(!$(e.target).closest('#taof-s-inp,#taof-s-dd').length) $dd.hide(); });
            // ─────────────────────────────────────────────────────────────

            $(document).on('click','#taof-sin-ativo-add',function(){
                var aid = $(this).data('aid');
                var val = $('#taof-sin-ativo-inp').val().trim().toUpperCase();
                var $msg = $('#taof-sin-ativo-msg');
                if (!val) return;
                $.post(_ajaxUrl, {action:'tao_formula_salvar_sinonimo', nonce:_nonce, ativo_id:aid, sinonimo:val}, function(r){
                    if (r.success) {
                        $('#taof-sin-ativo-inp').val('');
                        $msg.text('Salvo!').css('color','#16a34a');
                        reloadSinonimos(aid);
                        setTimeout(function(){ $msg.text(''); }, 2000);
                    } else {
                        $msg.text(r.data && r.data.message ? r.data.message : 'Erro').css('color','#dc2626');
                    }
                });
            });
            $(document).on('keydown','#taof-sin-ativo-inp',function(e){
                if(e.key==='Enter') $('#taof-sin-ativo-add').click();
            });
            $(document).on('click','.taof-sin-del-modal',function(){
                var sid=$(this).data('sid'), aid=$(this).data('aid');
                if(!confirm('Remover este sinônimo?')) return;
                $.post(_ajaxUrl, {action:'tao_formula_excluir_sinonimo', nonce:_nonce, sin_id:sid}, function(r){
                    if(r.success) reloadSinonimos(aid);
                });
            });
            $(document).on('click','#taof-ativo-modal-close, #taof-ativo-modal .taof-overlay',function(){
                $('#taof-ativo-modal').hide();
            });
        }

        // Tenta agora (jQuery no <head>), senão aguarda window.load (jQuery no footer)
        taofAtivosSetup();
        window.addEventListener('load', taofAtivosSetup);
    })();
    </script>

    <script>
    (function(){
        function setup(){
            var $ = window.jQuery; if ( ! $ ) return setTimeout(setup, 60);
            if ( window._taofSinInit ) return; window._taofSinInit = true;
            var _nonce = '<?php echo esc_js( wp_create_nonce('tao_formula_nonce') ); ?>';
            var _ajax  = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';

            // ── Abas ──────────────────────────────────────────────────────
            $('.taof-tab-btn').on('click', function(){
                var pane = $(this).data('pane');
                $('.taof-tab-btn').css({ borderBottomColor:'transparent', color:'#475569', fontWeight:'400' });
                $(this).css({ borderBottomColor:'#2271b1', color:'#2271b1', fontWeight:'600' });
                $('#taof-pane-ativos').toggle( pane === 'ativos' );
                $('#taof-pane-sinonimos').toggle( pane === 'sinonimos' );
                if ( pane === 'sinonimos' && ! window._taofSinLoaded ) { window._taofSinLoaded = true; carregar(); }
            });

            // ── Carregar (dispatcher: lista de sinônimos OU termos sem associação) ─
            function carregar(){
                if ( $('#taof-sin-sem').is(':checked') ) return carregarNaoAtribuidos();
                $('#taof-sin-th1').text('Sinônimo'); $('#taof-sin-th2').text('Ativo associado');
                var q = $('#taof-sin-q').val() || '';
                $('#taof-sin-tbody').html('<tr><td colspan="3" style="color:#94a3b8">Carregando...</td></tr>');
                $.post(_ajax, { action:'tao_formula_sinonimos_lista', nonce:_nonce, q:q, sem_ativo:'' }, function(r){
                    var rows = ( r && r.success && r.data ) ? r.data : [];
                    $('#taof-sin-count').text(rows.length + ' sinônimo(s)');
                    var $b = $('#taof-sin-tbody').empty();
                    if ( ! rows.length ) { $b.append('<tr><td colspan="3" style="color:#94a3b8">Nenhum sinônimo.</td></tr>'); return; }
                    rows.forEach(function(s){
                        var at = s.ativos;
                        var $tr = $('<tr>').attr('data-id', s.id);
                        $tr.append( $('<td>').css('font-weight','600').text(s.sinonimo) );
                        var $cell = $('<td class="taof-sin-ativo-cell" style="position:relative">');
                        if ( at ) $cell.text( ( at.codigo_fc ? at.codigo_fc + ' — ' : '' ) + ( at.nome || '' ) );
                        else $cell.html('<span style="color:#dc2626">— sem associação —</span>');
                        $tr.append($cell);
                        var $ac = $('<td style="white-space:nowrap">');
                        $('<button class="button button-small">').text( at ? 'Alterar' : 'Associar' )
                            .on('click', function(){ assoc($tr, s.id); }).appendTo($ac);
                        $ac.append(' ');
                        $('<button class="button button-small" style="color:#dc2626;border-color:#dc2626">').text('Excluir')
                            .on('click', function(){
                                if ( ! confirm('Excluir o sinônimo "' + s.sinonimo + '"?') ) return;
                                $.post(_ajax, { action:'tao_formula_excluir_sinonimo', nonce:_nonce, sin_id:s.id }, function(r){
                                    if ( r && r.success ) carregar(); else alert('Erro ao excluir');
                                });
                            }).appendTo($ac);
                        $tr.append($ac);
                        $b.append($tr);
                    });
                });
            }

            // ── Termos dos ORÇAMENTOS sem ativo base associado ────────────
            function carregarNaoAtribuidos(){
                $('#taof-sin-th1').text('Termo do orçamento'); $('#taof-sin-th2').text('Ocorrências');
                $('#taof-sin-tbody').html('<tr><td colspan="3" style="color:#94a3b8">Varrendo orçamentos...</td></tr>');
                $.post(_ajax, { action:'tao_formula_sinonimos_nao_atribuidos', nonce:_nonce }, function(r){
                    var rows = ( r && r.success && r.data ) ? r.data : [];
                    var q = ( $('#taof-sin-q').val() || '' ).trim().toUpperCase();
                    if ( q ) rows = rows.filter(function(t){ return ( t.nome || '' ).toUpperCase().indexOf(q) >= 0; });
                    $('#taof-sin-count').text(rows.length + ' termo(s) sem associação');
                    var $b = $('#taof-sin-tbody').empty();
                    if ( ! rows.length ) { $b.append('<tr><td colspan="3" style="color:#94a3b8">Nenhum termo de orçamento sem associação. 🎉</td></tr>'); return; }
                    rows.forEach(function(t){
                        var $tr = $('<tr>');
                        $tr.append( $('<td>').css('font-weight','600').text(t.nome) );
                        var info = t.count + ' ocorrência(s)' + ( ( t.orcs && t.orcs.length ) ? ' · ex: ' + t.orcs.join(', ') : '' );
                        $tr.append( $('<td style="color:#64748b">').text(info) );
                        var $ac = $('<td style="position:relative">');
                        var w = mkAtivoSearch(function(a){
                            $.post(_ajax, { action:'tao_formula_criar_sinonimo', nonce:_nonce, sinonimo:t.nome, ativo_id:a.id }, function(r){
                                if ( r && r.success ) carregar(); else alert('Erro: ' + ( ( r && r.data && r.data.message ) || 'não foi possível associar' ) );
                            });
                        });
                        $ac.append( w.wrap );
                        $tr.append($ac);
                        $b.append($tr);
                    });
                });
            }

            // ── Widget reutilizável: busca de ativo com navegação por teclado ─
            function mkAtivoSearch(onPick){
                var $wrap = $('<span style="position:relative;display:inline-block">');
                var $inp  = $('<input type="text" placeholder="buscar ativo..." autocomplete="off" style="width:210px;padding:4px 8px">');
                var $dd   = $('<div style="position:absolute;top:30px;left:0;min-width:260px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);z-index:50;max-height:240px;overflow:auto;display:none"></div>');
                $wrap.append($inp).append($dd);
                var t, items = [];
                function hl($el){
                    $dd.find('.taof-as-item').removeClass('taof-as-hl').css('background','');
                    if ( $el && $el.length ) { $el.addClass('taof-as-hl').css('background','#eff6ff'); var el = $el[0]; if ( el && el.scrollIntoView ) el.scrollIntoView({ block:'nearest' }); }
                }
                function render(ats){
                    items = ats; $dd.empty();
                    if ( ! ats.length ) { $dd.html('<div style="padding:8px;color:#94a3b8;font-size:12px">nada encontrado</div>').show(); return; }
                    ats.forEach(function(a,i){
                        $('<div class="taof-as-item" data-i="'+i+'" style="padding:7px 10px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px">')
                            .text( ( a.codigo_fc ? a.codigo_fc + ' — ' : '' ) + a.nome )
                            .on('mouseenter', function(){ hl($(this)); })
                            .on('mousedown', function(e){ e.preventDefault(); onPick(a); }).appendTo($dd);
                    });
                    hl( $dd.find('.taof-as-item').first() );
                    $dd.show();
                }
                $inp.on('input', function(){
                    clearTimeout(t); var q = $inp.val().trim();
                    if ( q.length < 2 ) { $dd.hide().empty(); return; }
                    t = setTimeout(function(){
                        $.post(_ajax, { action:'tao_formula_buscar_ativos', nonce:_nonce, q:q }, function(r){
                            render( ( r && r.success && r.data ) ? r.data : [] );
                        });
                    }, 250);
                });
                $inp.on('keydown', function(e){
                    if ( ! $dd.is(':visible') ) return;
                    var $its = $dd.find('.taof-as-item'); if ( ! $its.length ) return;
                    var idx = $its.index( $dd.find('.taof-as-hl') );
                    if ( e.key === 'ArrowDown' ) { e.preventDefault(); hl( $( $its[ Math.min(idx+1, $its.length-1) ] ) ); }
                    else if ( e.key === 'ArrowUp' ) { e.preventDefault(); hl( $( $its[ Math.max(idx-1, 0) ] ) ); }
                    else if ( e.key === 'Enter' ) { e.preventDefault(); var i = idx < 0 ? 0 : idx; if ( items[i] ) onPick(items[i]); }
                    else if ( e.key === 'Escape' ) { $dd.hide(); }
                });
                return { wrap:$wrap, focus:function(){ $inp.focus(); } };
            }

            // ── Associar/alterar ativo de um sinônimo existente ───────────
            function assoc($tr, sinId){
                var $cell = $tr.find('.taof-sin-ativo-cell').empty().css('position','relative');
                var w = mkAtivoSearch(function(a){
                    $.post(_ajax, { action:'tao_formula_associar_sinonimo', nonce:_nonce, sin_id:sinId, ativo_id:a.id }, function(r){
                        if ( r && r.success ) carregar(); else alert('Erro ao associar');
                    });
                });
                $cell.append( w.wrap ); w.focus();
            }

            // ── Novo sinônimo ─────────────────────────────────────────────
            $('#taof-sin-novo-btn').on('click', function(){ $('#taof-sin-novo-form').toggle(); });
            var tn;
            $('#taof-sin-novo-ativo-q').on('input', function(){
                var $q = $(this), $dd = $('#taof-sin-novo-ativo-dd');
                $('#taof-sin-novo-ativo-id').val(''); $('#taof-sin-novo-ativo-sel').text('');
                clearTimeout(tn); var q = $q.val().trim(); if ( q.length < 2 ) { $dd.hide(); return; }
                tn = setTimeout(function(){
                    $.post(_ajax, { action:'tao_formula_buscar_ativos', nonce:_nonce, q:q }, function(r){
                        var ats = ( r && r.success && r.data ) ? r.data : []; $dd.empty();
                        ats.forEach(function(a){
                            $('<div style="padding:7px 10px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px">')
                                .text( ( a.codigo_fc ? a.codigo_fc + ' — ' : '' ) + a.nome )
                                .on('mousedown', function(){
                                    $('#taof-sin-novo-ativo-id').val(a.id);
                                    $q.val( ( a.codigo_fc ? a.codigo_fc + ' — ' : '' ) + a.nome );
                                    $('#taof-sin-novo-ativo-sel').text('✔'); $dd.hide();
                                }).appendTo($dd);
                        });
                        $dd.toggle( ats.length > 0 );
                    });
                }, 250);
            });
            $('#taof-sin-novo-salvar').on('click', function(){
                var nome = $('#taof-sin-novo-nome').val().trim();
                if ( ! nome ) { alert('Informe o sinônimo'); return; }
                $.post(_ajax, { action:'tao_formula_criar_sinonimo', nonce:_nonce, sinonimo:nome, ativo_id:$('#taof-sin-novo-ativo-id').val() }, function(r){
                    if ( r && r.success ) {
                        $('#taof-sin-novo-nome,#taof-sin-novo-ativo-q').val(''); $('#taof-sin-novo-ativo-id').val(''); $('#taof-sin-novo-ativo-sel').text('');
                        $('#taof-sin-novo-form').hide(); carregar();
                    } else { alert('Erro: ' + ( ( r && r.data && r.data.message ) || 'não foi possível criar' ) ); }
                });
            });

            $('#taof-sin-buscar').on('click', carregar);
            $('#taof-sin-q').on('keydown', function(e){ if ( e.key === 'Enter' ) carregar(); });
            $('#taof-sin-sem').on('change', carregar);
        }
        setup();
        window.addEventListener('load', setup);
    })();
    </script>
    <?php
}
