<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_cot_supabase_url() {
    if ( function_exists( 'cbpm_supabase_url' ) ) return cbpm_supabase_url();
    return get_option( 'tao_formula_supabase_url', '' );
}

function tao_cot_supabase_key() {
    if ( function_exists( 'cbpm_supabase_key' ) ) return cbpm_supabase_key();
    return get_option( 'tao_formula_supabase_key', '' );
}

function tao_cot_is_master() {
    if ( function_exists( 'cbpm_is_master' ) ) return cbpm_is_master();
    return current_user_can( 'manage_options' );
}

/** Quem pode operar cotações: master ou gestor do negócio. */
function tao_cot_pode() {
    if ( current_user_can( 'manage_options' ) ) return true;
    if ( function_exists( 'cbpm_is_master' ) && cbpm_is_master() ) return true;
    if ( function_exists( 'cbpm_is_gestor' ) && cbpm_is_gestor() ) return true;
    return false;
}

function tao_cot_cliente_id() {
    if ( function_exists( 'cbpm_current_cliente_id' ) ) {
        $id = cbpm_current_cliente_id();
        if ( $id ) return $id;
    }
    return get_option( 'tao_formula_default_cliente_id', null ) ?: null;
}

/**
 * URL de uma página do módulo — admin ou frontend conforme contexto.
 */
function tao_cot_url( $section = 'cotacoes', $params = [] ) {
    global $cbpm_is_frontend;
    if ( ! empty( $cbpm_is_frontend ) && function_exists( 'cbpm_url' ) ) {
        return cbpm_url( $section, $params );
    }
    $slugs = [
        'cotacoes'              => 'tao-cotacoes',
        'cotacoes-nova'         => 'tao-cotacoes-nova',
        'cotacoes-fornecedores' => 'tao-cotacoes-fornecedores',
    ];
    $page = $slugs[ $section ] ?? 'tao-cotacoes';
    $url  = admin_url( "admin.php?page=$page" );
    return $params ? add_query_arg( $params, $url ) : $url;
}

/**
 * Chamada REST ao Supabase.
 */
function tao_cot_api( $path, $method = 'GET', $body = null, $headers_extra = [] ) {
    $url = rtrim( tao_cot_supabase_url(), '/' ) . '/rest/v1' . $path;
    $key = tao_cot_supabase_key();

    $args = [
        'method'  => $method,
        'timeout' => 20,
        'headers' => array_merge( [
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ], $headers_extra ),
    ];
    if ( $body !== null ) $args['body'] = wp_json_encode( $body );

    $resp = wp_remote_request( $url, $args );
    if ( is_wp_error( $resp ) ) {
        return [ 'ok' => false, 'error' => $resp->get_error_message(), 'data' => [] ];
    }
    $code = wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    $data = json_decode( $raw, true );

    return [
        'ok'   => $code >= 200 && $code < 300,
        'code' => $code,
        'data' => is_array( $data ) ? $data : [],
        'raw'  => $raw,
    ];
}

/**
 * Instâncias WhatsApp disponíveis para o cliente (via workspaces do CRM).
 */
function tao_cot_instancias( $cliente_id ) {
    $rw = tao_cot_api( "/crm_workspaces?cliente_id=eq.$cliente_id&select=id,nome" );
    if ( ! $rw['ok'] || empty( $rw['data'] ) ) return [];
    $ws_ids = implode( ',', array_map( fn( $w ) => $w['id'], $rw['data'] ) );
    $ri = tao_cot_api( "/crm_instancias?workspace_id=in.($ws_ids)&select=id,nome,evolution_url,evolution_key,evolution_instancia,workspace_id&order=nome.asc" );
    return $ri['ok'] ? $ri['data'] : [];
}

/**
 * Envia texto via Evolution usando uma linha de crm_instancias.
 */
function tao_cot_evolution_send( $instancia, $numero, $texto ) {
    $url  = rtrim( $instancia['evolution_url'] ?? '', '/' );
    $key  = $instancia['evolution_key'] ?? '';
    $inst = $instancia['evolution_instancia'] ?? '';
    if ( ! $url || ! $key || ! $inst ) return [ 'ok' => false, 'error' => 'Instância sem credenciais Evolution' ];

    $resp = wp_remote_post( "$url/message/sendText/" . rawurlencode( $inst ), [
        'timeout' => 25,
        'headers' => [ 'apikey' => $key, 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [
            'number'      => preg_replace( '/\D/', '', $numero ),
            'textMessage' => [ 'text' => $texto ],
            'text'        => $texto, // compat Evolution v2
        ] ),
    ] );
    if ( is_wp_error( $resp ) ) return [ 'ok' => false, 'error' => $resp->get_error_message() ];
    $code = wp_remote_retrieve_response_code( $resp );
    return [ 'ok' => $code >= 200 && $code < 300, 'code' => $code, 'raw' => wp_remote_retrieve_body( $resp ) ];
}

/**
 * Monta a mensagem de solicitação de proposta para um fornecedor.
 */
function tao_cot_montar_msg( $cotacao, $itens, $nome_negocio, $nome_fornecedor ) {
    $linhas = [];
    // Urgentes primeiro
    usort( $itens, function( $a, $b ) {
        return ( empty( $b['urgente'] ) ? 0 : 1 ) <=> ( empty( $a['urgente'] ) ? 0 : 1 );
    } );
    foreach ( $itens as $it ) {
        $qtd  = (float) ( $it['qtd'] ?? 0 );
        $un   = trim( $it['unidade'] ?? '' );
        $lin  = '- ' . $it['descricao'];
        if ( $qtd > 0 ) $lin .= ' — ' . rtrim( rtrim( number_format( $qtd, 2, ',', '.' ), '0' ), ',' ) . ( $un ? " $un" : '' );
        if ( ! empty( $it['urgente'] ) ) $lin .= ' ⭐';
        $linhas[] = $lin;
    }
    $msg  = "Olá" . ( $nome_fornecedor ? ", $nome_fornecedor" : '' ) . "! Aqui é a $nome_negocio.\n\n";
    $msg .= "Estamos com uma cotação de insumos aberta (nº {$cotacao['numero']}) e gostaríamos de receber a sua proposta para os itens abaixo:\n\n";
    $msg .= implode( "\n", $linhas );
    $msg .= "\n\n⭐ = itens prioritários para esta compra.";
    $msg .= "\n\nPode responder por aqui mesmo, de preferência com a proposta em PDF ou foto da tabela de preços (informando fracionamento mínimo e validade). Obrigado!";
    return $msg;
}

/**
 * CSS + JS embutidos (uma vez). Funciona em admin e no frontend /robos/.
 */
function tao_cot_assets() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    $nonce = wp_create_nonce( 'tao_cot_nonce' );
    ?>
    <style>
    .taocot-wrap{max-width:1150px}
    .taocot-wrap h1{font-size:22px;color:#1e293b}
    .taocot-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin:14px 0}
    .taocot-btn{display:inline-block;padding:7px 14px;border-radius:6px;font-size:13px;font-weight:600;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;text-decoration:none}
    .taocot-btn:hover{background:#f1f5f9}
    .taocot-btn-primary{background:#152C42;color:#fff;border-color:#152C42}
    .taocot-btn-primary:hover{background:#1e3a5f;color:#fff}
    .taocot-btn-danger{color:#b91c1c}
    .taocot-btn[disabled]{opacity:.5;cursor:not-allowed}
    .taocot-tscroll{overflow-x:auto}
    .taocot-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taocot-table th{text-align:left;padding:10px 12px;background:#f8fafc;color:#475569;font-weight:600;border-bottom:1px solid #e2e8f0;white-space:nowrap}
    .taocot-table td{padding:8px 12px;border-bottom:1px solid #f1f5f9;color:#334155}
    .taocot-table tr:last-child td{border-bottom:none}
    .taocot-table tr.taocot-urgente td{background:#fffbeb}
    .taocot-empty{padding:30px;text-align:center;color:#94a3b8;background:#fff;border:1px dashed #cbd5e1;border-radius:8px}
    .taocot-modal{display:none;position:fixed;inset:0;z-index:99999}
    .taocot-modal .taocot-overlay{position:absolute;inset:0;background:rgba(15,23,42,.45)}
    .taocot-modal .taocot-box{position:relative;max-width:480px;margin:8vh auto;background:#fff;border-radius:10px;padding:22px 24px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
    .taocot-modal h2{margin:0 0 16px;font-size:17px;color:#1e293b}
    .taocot-field{margin-bottom:12px}
    .taocot-field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px}
    .taocot-field input[type=text],.taocot-field input[type=number],.taocot-field select,.taocot-field textarea{width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;box-sizing:border-box}
    .taocot-field-inline{display:flex;align-items:center;gap:8px}
    .taocot-actions{display:flex;gap:8px;align-items:center;margin-top:18px;flex-wrap:wrap}
    .taocot-pill{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
    .taocot-pill.rascunho{background:#e2e8f0;color:#475569}
    .taocot-pill.enviada,.taocot-pill.enviado{background:#dbeafe;color:#1d4ed8}
    .taocot-pill.recebendo,.taocot-pill.respondeu{background:#fef9c3;color:#a16207}
    .taocot-pill.concluida,.taocot-pill.processado,.taocot-pill.on{background:#dcfce7;color:#166534}
    .taocot-pill.cancelada,.taocot-pill.erro,.taocot-pill.off{background:#fee2e2;color:#991b1b}
    .taocot-pill.pendente{background:#f1f5f9;color:#64748b}
    .taocot-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;margin-bottom:16px}
    .taocot-card h2{margin:0 0 12px;font-size:15px;color:#1e293b}
    .taocot-muted{color:#94a3b8;font-size:12px}
    .taocot-star{cursor:pointer;font-size:16px;opacity:.25;user-select:none}
    .taocot-star.on{opacity:1}
    /* combo digitável de ativos */
    .taocot-combo{position:relative;min-width:280px;flex:1}
    .taocot-combo input{width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;box-sizing:border-box}
    .taocot-combo-list{position:absolute;top:100%;left:0;right:0;z-index:1000;background:#fff;border:1px solid #cbd5e1;border-radius:0 0 8px 8px;box-shadow:0 12px 30px rgba(0,0,0,.15);max-height:260px;overflow:auto;display:none}
    .taocot-combo-list .taocot-opt{padding:8px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid #f1f5f9}
    .taocot-combo-list .taocot-opt:last-child{border-bottom:none}
    .taocot-combo-list .taocot-opt.sel,.taocot-combo-list .taocot-opt:hover{background:#eff6ff}
    .taocot-combo-list .taocot-opt .cod{color:#94a3b8;font-size:11px;margin-left:6px}
    .taocot-combo-list .taocot-opt.livre{color:#a16207;font-style:italic}
    .taocot-upload{border:2px dashed #cbd5e1;border-radius:10px;padding:26px;text-align:center;background:#fff;cursor:pointer}
    .taocot-upload:hover{border-color:#152C42}
    .taocot-upload input{display:none}
    .taocot-status-msg{margin:10px 0;font-size:13px;color:#475569}
    @media(max-width:900px){.taocot-table{min-width:640px}}
    </style>
    <script>
    window.taoCot = { ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce: <?php echo wp_json_encode( $nonce ); ?> };
    (function(){
        var C = window.taoCot;
        C.post = function(action, data){
            var fd = new FormData();
            fd.append('action', action); fd.append('nonce', C.nonce);
            Object.keys(data||{}).forEach(function(k){ fd.append(k, data[k]); });
            return fetch(C.ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); });
        };
        C.postFile = function(action, file, extra){
            var fd = new FormData();
            fd.append('action', action); fd.append('nonce', C.nonce); fd.append('file', file);
            Object.keys(extra||{}).forEach(function(k){ fd.append(k, extra[k]); });
            return fetch(C.ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); });
        };
        function fillForm(form, obj){
            Array.prototype.forEach.call(form.elements, function(el){
                if(!el.name) return;
                var v = obj ? obj[el.name] : '';
                if(el.type==='checkbox'){ el.checked = (v===true||v==='true'||v==1||v==='1'); }
                else { el.value = (v===null||v===undefined)?'':v; }
            });
        }
        function serialize(form){
            var o = {};
            Array.prototype.forEach.call(form.elements, function(el){
                if(!el.name) return;
                o[el.name] = (el.type==='checkbox') ? (el.checked?'1':'0') : el.value;
            });
            return o;
        }
        document.addEventListener('click', function(e){
            var t;
            if(t = e.target.closest('[data-cot-new]')){
                var m = document.getElementById(t.getAttribute('data-modal'));
                fillForm(m.querySelector('form'), null);
                var ti = m.querySelector('[data-title]'); if(ti) ti.textContent = t.getAttribute('data-title')||'Novo';
                m.style.display='block';
            } else if(t = e.target.closest('[data-cot-edit]')){
                var row = t.closest('[data-row]');
                var obj = JSON.parse(row.getAttribute('data-json')||'{}');
                var m2 = document.getElementById(t.getAttribute('data-modal'));
                fillForm(m2.querySelector('form'), obj);
                var ti2 = m2.querySelector('[data-title]'); if(ti2) ti2.textContent = 'Editar';
                m2.style.display='block';
            } else if(t = e.target.closest('[data-cot-cancel]')){
                var mc = t.closest('.taocot-modal'); if(mc) mc.style.display='none';
            } else if(e.target.classList && e.target.classList.contains('taocot-overlay')){
                var mo = e.target.closest('.taocot-modal'); if(mo) mo.style.display='none';
            } else if(t = e.target.closest('[data-cot-del]')){
                if(!confirm('Excluir este registro?')) return;
                var row2 = t.closest('[data-row]');
                C.post(t.getAttribute('data-action'), { id: row2.getAttribute('data-id') }).then(function(r){
                    if(r.success){ location.reload(); } else { alert('Erro: '+(r.data||'falha')); }
                });
            }
        });
        document.addEventListener('submit', function(e){
            var f = e.target.closest('form[data-action]');
            if(!f) return;
            e.preventDefault();
            var btn = f.querySelector('[type=submit]');
            if(btn){ btn.disabled=true; btn._txt=btn.textContent; btn.textContent='Salvando...'; }
            C.post(f.getAttribute('data-action'), serialize(f)).then(function(r){
                if(r.success){ location.reload(); }
                else { alert('Erro: '+(r.data||'falha')); if(btn){ btn.disabled=false; btn.textContent=btn._txt; } }
            }).catch(function(){ alert('Falha de rede'); if(btn){ btn.disabled=false; btn.textContent=btn._txt; } });
        });

        /* Combo digitável de ativos com navegação por setas.
           opts: { input, onPick(obj), permitirLivre } — obj = {ativo_id, codigo_fc, nome, livre} */
        C.combo = function(opts){
            var inp = opts.input, box = inp.closest('.taocot-combo');
            var list = box.querySelector('.taocot-combo-list');
            var sel = -1, results = [], timer = null;
            function render(){
                list.innerHTML = '';
                results.forEach(function(r, i){
                    var d = document.createElement('div');
                    d.className = 'taocot-opt' + (r.livre?' livre':'') + (i===sel?' sel':'');
                    d.innerHTML = r.livre
                        ? 'Usar "<strong></strong>" como item livre (só desta cotação)'
                        : '<strong></strong><span class="cod"></span>';
                    d.querySelector('strong').textContent = r.nome;
                    if(!r.livre) d.querySelector('.cod').textContent = r.codigo_fc ? ('#'+r.codigo_fc) : '';
                    d.addEventListener('mousedown', function(ev){ ev.preventDefault(); pick(i); });
                    list.appendChild(d);
                });
                list.style.display = results.length ? 'block' : 'none';
            }
            function pick(i){
                var r = results[i]; if(!r) return;
                list.style.display='none'; results=[]; sel=-1;
                opts.onPick(r);
                inp.value=''; inp.focus();
            }
            inp.addEventListener('input', function(){
                clearTimeout(timer);
                var q = inp.value.trim();
                if(q.length < 2){ list.style.display='none'; results=[]; return; }
                timer = setTimeout(function(){
                    C.post('tao_cot_search_ativos', { q:q }).then(function(r){
                        results = (r.success ? (r.data||[]) : []).map(function(a){
                            return { ativo_id:a.id, codigo_fc:a.codigo_fc||'', nome:a.nome, livre:false };
                        });
                        if(opts.permitirLivre !== false){
                            results.push({ ativo_id:null, codigo_fc:'', nome:q, livre:true });
                        }
                        sel = results.length ? 0 : -1;
                        render();
                    });
                }, 280);
            });
            inp.addEventListener('keydown', function(e){
                if(list.style.display==='none' || !results.length) return;
                if(e.key==='ArrowDown'){ e.preventDefault(); sel=Math.min(sel+1,results.length-1); render(); }
                else if(e.key==='ArrowUp'){ e.preventDefault(); sel=Math.max(sel-1,0); render(); }
                else if(e.key==='Enter'){ e.preventDefault(); if(sel>=0) pick(sel); }
                else if(e.key==='Escape'){ list.style.display='none'; }
            });
            inp.addEventListener('blur', function(){ setTimeout(function(){ list.style.display='none'; }, 180); });
        };
    })();
    </script>
    <?php
}
