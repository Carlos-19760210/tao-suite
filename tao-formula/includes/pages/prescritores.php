<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cadastro de Prescritores — CRUD (espelho do FC04000 do FCerta).
 * Tratamento, tipo/nº/UF do registro, especialidade, contato, endereço.
 */
function tao_formula_page_prescritores() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>🩺 Prescritores</h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="text" id="taof-pr-busca" class="regular-text" style="width:320px"
               placeholder="Buscar por nome ou nº de registro..." autocomplete="off">
        <button type="button" class="button button-primary" id="taof-pr-novo">+ Novo Prescritor</button>
        <span id="taof-pr-count" style="font-size:12px;color:#64748b"></span>
    </div>

    <div id="taof-pr-lista"></div>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap">
        <label>Itens por página:
            <select id="taof-pr-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-pr-prev">‹ Anterior</button>
        <span id="taof-pr-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-pr-next">Próxima ›</button>
    </div>

    <!-- Modal -->
    <div id="taof-pr-modal" style="display:none">
        <div class="taof-overlay"></div>
        <div class="taof-modal-box" style="width:640px;max-width:98vw">
            <div id="taof-pr-modal-body"></div>
        </div>
    </div>

    <style>
    .taof-pr-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden}
    .taof-pr-table th,.taof-pr-table td{padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:13px}
    .taof-pr-table th{background:#f8fafc;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#64748b}
    .taof-pr-table tr:hover td{background:#f8fafc}
    .taof-pr-twrap{overflow-x:auto;min-width:0}
    #taof-pr-modal .taof-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-pr-modal .taof-modal-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:20px 22px;z-index:9999;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl = taoFormula.ajaxUrl, nonce = taoFormula.nonce, timer = null, q = '';
        var pg = 0, sz = 30, total = 0;

        function esc(t){ return $('<span>').text(t==null?'':t).html(); }

        function inp(lbl,name,val,w){
            return '<div><label style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px">'+lbl+'</label>' +
                   '<input type="text" name="'+name+'" value="'+esc(val)+'" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px"></div>';
        }
        function grid(n,items){ return '<div style="display:grid;grid-template-columns:repeat('+n+',1fr);gap:10px 14px;margin-bottom:12px">'+items.join('')+'</div>'; }

        function abrirForm(p){
            p = p || {};
            var isNovo = !p.id;
            var html = '<h2 style="margin:0 0 12px;font-size:18px">'+(isNovo?'➕ Novo Prescritor':'✏️ '+esc(p.nome))+'</h2>';
            html += '<form id="taof-pr-form">';
            html += grid(4,[
                inp('Tratamento','tratamento',p.tratamento||'Dr'),
                '<div style="grid-column:span 3">'+inp('Nome *','nome',p.nome)+'</div>'
            ]);
            html += grid(3,[
                inp('Tipo registro (CRM/CRO/CRN...)','tipo_registro',p.tipo_registro),
                inp('Nº registro','nr_registro',p.nr_registro),
                inp('UF registro','uf_registro',p.uf_registro)
            ]);
            html += grid(2,[
                inp('Especialidade','especialidade',p.especialidade),
                inp('E-mail','email',p.email)
            ]);
            html += grid(2,[
                inp('Celular / WhatsApp','celular',p.celular),
                inp('Telefone','telefone',p.telefone)
            ]);
            html += grid(1,[ inp('Endereço','endereco',p.endereco) ]);
            html += grid(3,[
                inp('Cidade','cidade',p.cidade),
                inp('UF','uf',p.uf),
                inp('CEP','cep',p.cep)
            ]);
            html += grid(1,[ inp('Observações','obs',p.obs) ]);
            html += '<p style="margin:6px 0 0">' +
                    '<button type="submit" class="button button-primary">💾 Salvar</button> ' +
                    '<button type="button" class="button" id="taof-pr-cancel">Cancelar</button> ' +
                    '<span id="taof-pr-msg" style="font-size:12px;margin-left:8px"></span></p></form>';
            $('#taof-pr-modal-body').html(html);
            $('#taof-pr-modal').show();
            $('#taof-pr-form input[name=nome]').focus();
            // CEP → preenche endereço/cidade/UF automaticamente (ViaCEP)
            var $frm = $('#taof-pr-form');
            function buscaCep(){
                var cep = ($frm.find('input[name=cep]').val()||'').replace(/\D/g,'');
                if (cep.length !== 8) return;
                $.getJSON('https://viacep.com.br/ws/'+cep+'/json/', function(d){
                    if (!d || d.erro) return;
                    var end = [d.logradouro, d.bairro].filter(Boolean).join(', ');
                    if (end) $frm.find('input[name=endereco]').val(end);
                    if (d.localidade) $frm.find('input[name=cidade]').val(d.localidade);
                    if (d.uf) $frm.find('input[name=uf]').val(d.uf);
                });
            }
            $frm.find('input[name=cep]').on('blur', buscaCep).on('keyup', function(e){
                if ((this.value||'').replace(/\D/g,'').length === 8) buscaCep();
            });
            $('#taof-pr-cancel').on('click', function(){ $('#taof-pr-modal').hide(); });
            $('#taof-pr-form').on('submit', function(e){
                e.preventDefault();
                var $msg = $('#taof-pr-msg');
                var data = $(this).serializeArray().reduce(function(o,f){o[f.name]=f.value;return o;},{});
                data.action = 'tao_formula_salvar_prescritor';
                data.nonce  = nonce;
                if (!isNovo) data.id = p.id;
                $msg.css('color','#64748b').text('Salvando…');
                $.post(ajaxUrl, data, function(r){
                    if (r.success) { $('#taof-pr-modal').hide(); carregar(true); }
                    else $msg.css('color','#dc2626').text((r.data && r.data.message) || 'Erro');
                }).fail(function(){ $msg.css('color','#dc2626').text('Falha na requisição'); });
            });
        }

        function carregar(reset){
            if (reset) { pg = 0; }
            $.getJSON(ajaxUrl, {action:'tao_formula_prescritores_lista', nonce:nonce, q:q, size:sz, offset:pg*sz}, function(r){
                if (!r.success) { $('#taof-pr-lista').html('<p style="color:#dc2626">'+esc(r.data && r.data.message || 'Erro')+'</p>'); return; }
                var items = (r.data && r.data.items) || []; total = (r.data && r.data.total) || 0;
                var rows = '';
                items.forEach(function(p){
                    var reg = [p.tipo_registro, p.nr_registro].filter(Boolean).join(' ') + (p.uf_registro ? '/'+p.uf_registro : '');
                    rows += '<tr data-p=\''+JSON.stringify(p).replace(/'/g,'&#39;')+'\'>' +
                        '<td><strong>'+esc((p.tratamento?p.tratamento+' ':'')+p.nome)+'</strong></td>' +
                        '<td>'+esc(reg||'—')+'</td>' +
                        '<td>'+esc(p.especialidade||'—')+'</td>' +
                        '<td>'+esc(p.celular||p.telefone||'—')+'</td>' +
                        '<td>'+esc(p.cidade ? p.cidade+(p.uf?'/'+p.uf:'') : '—')+'</td>' +
                        '<td style="text-align:center"><button type="button" class="button button-small taof-pr-edit">Editar</button></td></tr>';
                });
                var tbl = '<div class="taof-pr-twrap"><table class="taof-pr-table"><tr><th>Nome</th><th>Registro</th><th>Especialidade</th><th>Contato</th><th>Cidade</th><th></th></tr>' + rows + '</table></div>';
                $('#taof-pr-lista').html(rows ? tbl : '<p style="color:#94a3b8">Nenhum prescritor encontrado.</p>');
                var paginas = Math.max(1, Math.ceil(total / sz));
                $('#taof-pr-pg').text('Página ' + (pg+1) + ' de ' + paginas);
                $('#taof-pr-prev').prop('disabled', pg <= 0);
                $('#taof-pr-next').prop('disabled', pg >= paginas - 1);
                $('#taof-pr-count').text(total + ' prescritor(es)');
            });
        }

        $(document).on('click','.taof-pr-edit',function(){
            abrirForm(JSON.parse($(this).closest('tr').attr('data-p')));
        });
        $('#taof-pr-novo').on('click', function(){ abrirForm({}); });
        $('#taof-pr-size').on('change', function(){ sz = parseInt(this.value, 10) || 30; carregar(true); });
        $('#taof-pr-prev').on('click', function(){ if (pg > 0) { pg--; carregar(false); } });
        $('#taof-pr-next').on('click', function(){ pg++; carregar(false); });
        $('#taof-pr-busca').on('input', function(){
            clearTimeout(timer);
            var v = $(this).val().trim();
            timer = setTimeout(function(){ q = v; carregar(true); }, 300);
        });
        /* clicar no overlay NÃO fecha (evita perder edição) — fecha só por Cancelar */

        carregar(true);
    });
    </script>
    </div>
    <?php
}
