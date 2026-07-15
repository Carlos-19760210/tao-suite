<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cadastro de Fornecedores — CRUD (espelho do FC02000 do FCerta).
 * Dados fiscais completos (p/ casar NF), licenças sanitárias e qualificação RDC 67.
 */
function tao_formula_page_fornecedores() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>🏭 Fornecedores</h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="text" id="taof-fn-busca" class="regular-text" style="width:340px"
               placeholder="Buscar por nome, razão social ou CNPJ..." autocomplete="off">
        <button type="button" class="button button-primary" id="taof-fn-novo">+ Novo Fornecedor</button>
        <span id="taof-fn-count" style="font-size:12px;color:#64748b"></span>
    </div>

    <div id="taof-fn-lista"></div>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap">
        <label>Itens por página:
            <select id="taof-fn-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-fn-prev">‹ Anterior</button>
        <span id="taof-fn-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-fn-next">Próxima ›</button>
    </div>

    <!-- Modal -->
    <div id="taof-fn-modal" style="display:none">
        <div class="taof-overlay"></div>
        <div class="taof-modal-box" style="width:760px;max-width:98vw">
            <div id="taof-fn-modal-body"></div>
        </div>
    </div>

    <style>
    .taof-fn-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden}
    .taof-fn-table th,.taof-fn-table td{padding:6px 12px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:13px;white-space:nowrap}
    .taof-fn-table td.taof-fn-nome{white-space:normal;max-width:420px}
    .taof-fn-table td.taof-fn-nome .rz{color:#94a3b8;font-size:11px;font-weight:400}
    .taof-fn-table th{background:#f8fafc;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#64748b}
    .taof-fn-table tr:hover td{background:#f8fafc}
    .taof-fn-twrap{overflow-x:auto;min-width:0}
    #taof-fn-modal .taof-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-fn-modal .taof-modal-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:20px 22px;z-index:9999;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    .taof-fn-sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#0369a1;border-bottom:1px solid #e0f2fe;padding-bottom:3px;margin:14px 0 8px}
    .taof-badge{display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:600}
    .taof-badge-ok{background:#dcfce7;color:#166534}.taof-badge-no{background:#fee2e2;color:#991b1b}
    .taof-badge-venc{background:#fef3c7;color:#92400e}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl = taoFormula.ajaxUrl, nonce = taoFormula.nonce, timer = null, q = '';
        var pg = 0, sz = 30, total = 0;
        function esc(t){ return $('<span>').text(t==null?'':t).html(); }

        function inp(lbl,name,val,type){
            return '<div><label style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px">'+lbl+'</label>' +
                   '<input type="'+(type||'text')+'" name="'+name+'" value="'+esc(val)+'" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px"></div>';
        }
        function sel(lbl,name,val,opts){
            var o = opts.map(function(op){ return '<option value="'+esc(op[0])+'"'+(String(val||'')===String(op[0])?' selected':'')+'>'+esc(op[1])+'</option>'; }).join('');
            return '<div><label style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px">'+lbl+'</label>' +
                   '<select name="'+name+'" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;background:#fff">'+o+'</select></div>';
        }
        function grid(n,items){ return '<div style="display:grid;grid-template-columns:repeat('+n+',1fr);gap:10px 14px;margin-bottom:4px">'+items.join('')+'</div>'; }
        function sec(t){ return '<div class="taof-fn-sec">'+t+'</div>'; }

        function abrirForm(f){
            f = f || {};
            var isNovo = !f.id;
            var html = '<h2 style="margin:0 0 4px;font-size:18px">'+(isNovo?'➕ Novo Fornecedor':'✏️ '+esc(f.nome))+'</h2>';
            html += '<form id="taof-fn-form">';

            html += sec('Identificação');
            html += grid(4,[
                sel('Tipo pessoa','tipo_pessoa',f.tipo_pessoa||'PJ',[['PJ','Pessoa Jurídica'],['PF','Pessoa Física']]),
                '<div style="grid-column:span 3">'+inp('Nome / apelido *','nome',f.nome)+'</div>'
            ]);
            html += grid(2,[
                inp('Razão social','razao_social',f.razao_social),
                inp('Nome fantasia','nome_fantasia',f.nome_fantasia)
            ]);
            html += grid(4,[
                inp('CNPJ / CPF','cnpj',f.cnpj),
                sel('Tipo','tipo',f.tipo,[['','—'],['fabricante','Fabricante'],['distribuidor','Distribuidor'],['importador','Importador'],['transportadora','Transportadora'],['outro','Outro']]),
                sel('Regime (CRT)','crt',f.crt,[['','—'],['1','Simples Nacional'],['2','Simples excesso'],['3','Regime Normal']]),
                inp('Cód. FCerta','codigo_fc_show',f.codigo_fc)
            ]);
            html += grid(4,[
                inp('Inscr. Estadual','inscr_estadual',f.inscr_estadual),
                inp('Inscr. Municipal','inscr_municipal',f.inscr_municipal),
                inp('SUFRAMA','suframa',f.suframa),
                inp('Reg. MAPA','reg_mapa',f.reg_mapa)
            ]);

            html += sec('Endereço');
            html += grid(6,[
                '<div style="grid-column:span 4">'+inp('Logradouro','endereco',f.endereco)+'</div>',
                inp('Número','endereco_nr',f.endereco_nr),
                inp('Complemento','complemento',f.complemento)
            ]);
            html += grid(4,[
                inp('Bairro','bairro',f.bairro),
                inp('Cidade','cidade',f.cidade),
                inp('UF','uf',f.uf),
                inp('CEP','cep',f.cep)
            ]);

            html += sec('Contato & Comercial');
            html += grid(4,[
                inp('Contato (pessoa)','contato',f.contato),
                inp('Telefone','telefone',f.telefone),
                inp('Telefone 2','telefone2',f.telefone2),
                inp('WhatsApp','whatsapp',f.whatsapp)
            ]);
            html += grid(4,[
                '<div style="grid-column:span 2">'+inp('E-mail','email',f.email)+'</div>',
                inp('Site','site',f.site),
                inp('Prazo pagto','prazo_pagamento',f.prazo_pagamento)
            ]);
            html += grid(4,[ inp('Pedido mínimo (R$)','valor_min_pedido',f.valor_min_pedido) ]);

            html += sec('Licenças sanitárias (RDC 67)');
            html += grid(4,[
                inp('AFE (ANVISA)','afe',f.afe),
                inp('Validade AFE','afe_validade',f.afe_validade,'date'),
                inp('Autorização Especial','autoriz_especial',f.autoriz_especial),
                inp('Validade AE','ae_validade',f.ae_validade,'date')
            ]);
            html += grid(4,[
                '<div style="grid-column:span 2">'+inp('Licença/Alvará Sanitário (VISA)','licenca_sanitaria',f.licenca_sanitaria)+'</div>',
                inp('Validade Licença','licenca_validade',f.licenca_validade,'date')
            ]);

            html += sec('Qualificação de fornecedor');
            html += grid(4,[
                '<div><label style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px">Qualificado</label>' +
                    '<label style="font-size:13px"><input type="checkbox" name="qualificado" value="1"'+(f.qualificado?' checked':'')+'> Aprovado</label></div>',
                inp('Data qualificação','qualif_data',f.qualif_data,'date'),
                '<div style="grid-column:span 2">'+inp('Avaliado por','qualif_por',f.qualif_por)+'</div>'
            ]);
            html += grid(1,[ inp('Observações da qualificação','qualif_obs',f.qualif_obs) ]);
            html += grid(1,[ inp('Observações gerais','obs',f.obs) ]);

            html += '<p style="margin:12px 0 0">' +
                    '<label style="font-size:13px;margin-right:14px"><input type="checkbox" name="ativo" value="1"'+((f.ativo===false)?'':' checked')+'> Ativo</label>' +
                    '<button type="submit" class="button button-primary">💾 Salvar</button> ' +
                    '<button type="button" class="button" id="taof-fn-cancel">Cancelar</button> ' +
                    '<span id="taof-fn-msg" style="font-size:12px;margin-left:8px"></span></p></form>';
            $('#taof-fn-modal-body').html(html);
            $('#taof-fn-modal').show();
            $('#taof-fn-form input[name=nome]').focus();
            $('#taof-fn-cancel').on('click', function(){ $('#taof-fn-modal').hide(); });
            $('#taof-fn-form').on('submit', function(e){
                e.preventDefault();
                var $msg = $('#taof-fn-msg');
                var data = {};
                $(this).find('input,select').each(function(){
                    var n = this.name; if (!n || n==='codigo_fc_show') return;
                    if (this.type==='checkbox') data[n] = this.checked ? '1' : '0';
                    else data[n] = this.value;
                });
                data.action = 'tao_formula_salvar_fornecedor';
                data.nonce  = nonce;
                if (!isNovo) data.id = f.id;
                $msg.css('color','#64748b').text('Salvando…');
                $.post(ajaxUrl, data, function(r){
                    if (r.success) { $('#taof-fn-modal').hide(); carregar(true); }
                    else $msg.css('color','#dc2626').text((r.data && r.data.message) || 'Erro');
                }).fail(function(){ $msg.css('color','#dc2626').text('Falha na requisição'); });
            });
        }

        function badgeLic(nome, validade){
            if (!validade) return '';
            var hoje = new Date().toISOString().slice(0,10);
            var cls = validade < hoje ? 'taof-badge-venc' : 'taof-badge-ok';
            var txt = validade < hoje ? nome+' vencido' : nome;
            return ' <span class="taof-badge '+cls+'">'+esc(txt)+'</span>';
        }

        function carregar(reset){
            if (reset) { pg = 0; }
            $.getJSON(ajaxUrl, {action:'tao_formula_fornecedores_lista', nonce:nonce, q:q, size:sz, offset:pg*sz}, function(r){
                if (!r.success) { $('#taof-fn-lista').html('<p style="color:#dc2626">'+esc(r.data && r.data.message || 'Erro')+'</p>'); return; }
                var items = (r.data && r.data.items) || []; total = (r.data && r.data.total) || 0;
                var rows = '';
                items.forEach(function(f){
                    var qual = f.qualificado ? '<span class="taof-badge taof-badge-ok">Qualificado</span>' : '<span class="taof-badge taof-badge-no">—</span>';
                    var lic = badgeLic('AFE', f.afe_validade) + badgeLic('AE', f.ae_validade) + badgeLic('VISA', f.licenca_validade);
                    rows += '<tr data-f=\''+JSON.stringify(f).replace(/'/g,'&#39;')+'\'>' +
                        '<td class="taof-fn-nome"><strong>'+esc(f.nome || f.razao_social)+'</strong>'+(f.razao_social && f.razao_social!==f.nome ? ' <span class="rz">· '+esc(f.razao_social)+'</span>':'')+'</td>' +
                        '<td>'+esc(f.cnpj||'—')+'</td>' +
                        '<td>'+esc(f.tipo||'—')+'</td>' +
                        '<td>'+esc(f.cidade ? f.cidade+(f.uf?'/'+f.uf:'') : '—')+'</td>' +
                        '<td>'+qual+lic+'</td>' +
                        '<td style="text-align:center"><button type="button" class="button button-small taof-fn-edit">Editar</button></td></tr>';
                });
                var tbl = '<div class="taof-fn-twrap"><table class="taof-fn-table"><tr><th>Nome</th><th>CNPJ</th><th>Tipo</th><th>Cidade</th><th>Situação</th><th></th></tr>' + rows + '</table></div>';
                $('#taof-fn-lista').html(rows ? tbl : '<p style="color:#94a3b8">Nenhum fornecedor encontrado.</p>');
                var paginas = Math.max(1, Math.ceil(total / sz));
                $('#taof-fn-pg').text('Página ' + (pg+1) + ' de ' + paginas);
                $('#taof-fn-prev').prop('disabled', pg <= 0);
                $('#taof-fn-next').prop('disabled', pg >= paginas - 1);
                $('#taof-fn-count').text(total + ' fornecedor(es)');
            });
        }

        $(document).on('click','.taof-fn-edit',function(){
            abrirForm(JSON.parse($(this).closest('tr').attr('data-f')));
        });
        $('#taof-fn-novo').on('click', function(){ abrirForm({}); });
        $('#taof-fn-size').on('change', function(){ sz = parseInt(this.value, 10) || 30; carregar(true); });
        $('#taof-fn-prev').on('click', function(){ if (pg > 0) { pg--; carregar(false); } });
        $('#taof-fn-next').on('click', function(){ pg++; carregar(false); });
        $('#taof-fn-busca').on('input', function(){
            clearTimeout(timer);
            var v = $(this).val().trim();
            timer = setTimeout(function(){ q = v; carregar(true); }, 300);
        });
        $('#taof-fn-modal').on('click','.taof-overlay',function(){ $('#taof-fn-modal').hide(); });

        carregar(true);
    });
    </script>
    </div>
    <?php
}
