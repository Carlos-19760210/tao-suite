<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_supabase_url() {
    if ( function_exists( 'cbpm_supabase_url' ) ) return cbpm_supabase_url();
    return get_option( 'tao_formula_supabase_url', '' );
}

function tao_caixa_supabase_key() {
    if ( function_exists( 'cbpm_supabase_key' ) ) return cbpm_supabase_key();
    return get_option( 'tao_formula_supabase_key', '' );
}

function tao_caixa_can_access() {
    if ( function_exists( 'cbpm_can_access' ) ) return cbpm_can_access();
    return current_user_can( 'manage_options' );
}

function tao_caixa_is_master() {
    if ( function_exists( 'cbpm_is_master' ) ) return cbpm_is_master();
    return current_user_can( 'manage_options' );
}

function tao_caixa_cliente_id() {
    if ( function_exists( 'cbpm_current_cliente_id' ) ) {
        $id = cbpm_current_cliente_id();
        if ( $id ) return $id;
    }
    return get_option( 'tao_formula_default_cliente_id', null ) ?: null;
}

/**
 * URL de uma página do TAO Caixa — admin ou frontend conforme contexto.
 */
function tao_caixa_url( $section = 'caixa-dashboard', $params = [] ) {
    global $cbpm_is_frontend;
    if ( ! empty( $cbpm_is_frontend ) && function_exists( 'cbpm_url' ) ) {
        $slug_fe = $section === 'caixa-dashboard' ? 'caixa' : $section;
        return cbpm_url( $slug_fe, $params );
    }
    $slugs = [
        'caixa-dashboard'   => 'tao-caixa',
        'caixa-adquirentes' => 'tao-caixa-adquirentes',
        'caixa-taxas'       => 'tao-caixa-taxas',
        'caixa-formas'      => 'tao-caixa-formas',
        'caixa-pdv'         => 'tao-caixa-pdv',
    ];
    $page = $slugs[ $section ] ?? 'tao-caixa';
    $url  = admin_url( "admin.php?page=$page" );
    return $params ? add_query_arg( $params, $url ) : $url;
}

/**
 * Chamada REST ao Supabase.
 */
function tao_caixa_api( $path, $method = 'GET', $body = null ) {
    $url = rtrim( tao_caixa_supabase_url(), '/' ) . '/rest/v1' . $path;
    $key = tao_caixa_supabase_key();

    $args = [
        'method'  => $method,
        'timeout' => 15,
        'headers' => [
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ],
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
 * CSS + JS embutidos (uma vez). Funciona em admin e no frontend /robos/.
 */
function tao_caixa_assets() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    $nonce = wp_create_nonce( 'tao_caixa_nonce' );
    ?>
    <style>
    .taoc-wrap{max-width:1100px}
    .taoc-wrap h1{font-size:22px;color:#1e293b}
    .taoc-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin:14px 0}
    .taoc-btn{display:inline-block;padding:7px 14px;border-radius:6px;font-size:13px;font-weight:600;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;text-decoration:none}
    .taoc-btn:hover{background:#f1f5f9}
    .taoc-btn-primary{background:#152C42;color:#fff;border-color:#152C42}
    .taoc-btn-primary:hover{background:#1e3a5f;color:#fff}
    .taoc-btn-danger{color:#b91c1c}
    .taoc-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taoc-table th{text-align:left;padding:10px 12px;background:#f8fafc;color:#475569;font-weight:600;border-bottom:1px solid #e2e8f0}
    .taoc-table td{padding:9px 12px;border-bottom:1px solid #f1f5f9;color:#334155}
    .taoc-table tr:last-child td{border-bottom:none}
    .taoc-empty{padding:30px;text-align:center;color:#94a3b8;background:#fff;border:1px dashed #cbd5e1;border-radius:8px}
    .taoc-modal{display:none;position:fixed;inset:0;z-index:99999}
    .taoc-modal .taoc-overlay{position:absolute;inset:0;background:rgba(15,23,42,.45)}
    .taoc-modal .taoc-box{position:relative;max-width:480px;margin:8vh auto;background:#fff;border-radius:10px;padding:22px 24px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
    .taoc-modal h2{margin:0 0 16px;font-size:17px;color:#1e293b}
    .taoc-field{margin-bottom:12px}
    .taoc-field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px}
    .taoc-field input[type=text],.taoc-field input[type=number],.taoc-field select{width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;box-sizing:border-box}
    .taoc-field-inline{display:flex;align-items:center;gap:8px}
    .taoc-actions{display:flex;gap:8px;align-items:center;margin-top:18px}
    .taoc-pill{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600}
    .taoc-pill.on{background:#dcfce7;color:#166534}
    .taoc-pill.off{background:#fee2e2;color:#991b1b}
    </style>
    <script>
    window.taoCaixa = { ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce: <?php echo wp_json_encode( $nonce ); ?> };
    (function(){
        var C = window.taoCaixa;
        function post(action, data){
            var fd = new FormData();
            fd.append('action', action); fd.append('nonce', C.nonce);
            Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
            return fetch(C.ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); });
        }
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
            if(t = e.target.closest('[data-caixa-new]')){
                var m = document.getElementById(t.getAttribute('data-modal'));
                fillForm(m.querySelector('form'), null);
                var ti = m.querySelector('[data-title]'); if(ti) ti.textContent = t.getAttribute('data-title')||'Novo';
                m.style.display='block';
            } else if(t = e.target.closest('[data-caixa-edit]')){
                var row = t.closest('[data-row]');
                var obj = JSON.parse(row.getAttribute('data-json')||'{}');
                var m = document.getElementById(t.getAttribute('data-modal'));
                fillForm(m.querySelector('form'), obj);
                var ti = m.querySelector('[data-title]'); if(ti) ti.textContent = 'Editar';
                m.style.display='block';
            } else if(t = e.target.closest('[data-caixa-cancel]')){
                var mc = t.closest('.taoc-modal'); if(mc) mc.style.display='none';
            } else if(e.target.classList && e.target.classList.contains('taoc-overlay')){
                var mo = e.target.closest('.taoc-modal'); if(mo) mo.style.display='none';
            } else if(t = e.target.closest('[data-caixa-del]')){
                if(!confirm('Excluir este registro?')) return;
                var row2 = t.closest('[data-row]');
                post(t.getAttribute('data-action'), { id: row2.getAttribute('data-id') }).then(function(r){
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
            post(f.getAttribute('data-action'), serialize(f)).then(function(r){
                if(r.success){ location.reload(); }
                else { alert('Erro: '+(r.data||'falha')); if(btn){ btn.disabled=false; btn.textContent=btn._txt; } }
            }).catch(function(){ alert('Falha de rede'); if(btn){ btn.disabled=false; btn.textContent=btn._txt; } });
        });
    })();
    </script>
    <?php
}
