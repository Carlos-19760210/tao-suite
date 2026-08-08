<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Página "WhatsApp Oficial (Meta)" — config de conexão por instância + gestão de templates.
 * Tudo aditivo/isolado. Se a migration ainda não rodou, mostra aviso e degrada.
 */
function tao_crm_page_whatsapp_oficial() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    $nonce = wp_create_nonce( 'tao_crm_nonce' );

    // instâncias (com colunas Meta se existirem) — detecta se a migration rodou
    $ri = tao_crm_api( "/crm_instancias?select=id,nome,workspace_id,provider,meta_phone_number_id,meta_waba_id,meta_graph_version&order=nome.asc" );
    $migrada = ! empty( $ri['ok'] );
    $inst    = $migrada ? ( $ri['data'] ?? [] ) : [];
    if ( ! $migrada ) {
        $ri2  = tao_crm_api( "/crm_instancias?select=id,nome,workspace_id&order=nome.asc" );
        $inst = ( $ri2['ok'] ?? false ) ? ( $ri2['data'] ?? [] ) : [];
    }
    $api_base   = get_option( 'tao_crm_meta_api_base', 'https://graph.facebook.com' );
    $fwd_n8n    = get_option( 'tao_crm_meta_forward_n8n', '0' );
    $tem_token  = get_option( 'tao_crm_meta_token', '' ) ? 'sim' : 'não';
    $tem_secret = get_option( 'tao_crm_meta_app_secret', '' ) ? 'sim' : 'não';
    $tem_verify = get_option( 'tao_crm_meta_verify_token', '' ) ? 'sim' : 'não';
    ?>
    <div class="wrap" style="max-width:1000px">
    <h1>🟢 WhatsApp Oficial (Meta Cloud)</h1>
    <p style="color:#475569">Ative a API oficial <b>por instância</b> — dá pra ter Evolution e Meta ao mesmo tempo (híbrido). Segredos ficam em <code>wp_options</code> (fora do banco de conversas).</p>
    <?php if ( ! $migrada ) : ?>
        <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px 14px;margin:12px 0">
            ⚠️ A migration <code>migration_messaging_meta_v1.sql</code> ainda não foi rodada no Supabase.
            A configuração por instância fica indisponível até você rodá-la (o Evolution segue normal).
        </div>
    <?php endif; ?>

    <h2>1. Conexão Meta (global do app)</h2>
    <table class="form-table"><tbody>
        <tr><th>API base URL</th><td><input type="text" id="mo-apibase" value="<?php echo esc_attr( $api_base ); ?>" style="width:340px"> <span class="description">simulador local: <code>http://127.0.0.1:8089</code></span></td></tr>
        <tr><th>Access Token</th><td><input type="password" id="mo-token" placeholder="<?php echo $tem_token === 'sim' ? '•••• (configurado)' : 'colar token'; ?>" style="width:340px"></td></tr>
        <tr><th>App Secret</th><td><input type="password" id="mo-secret" placeholder="<?php echo $tem_secret === 'sim' ? '•••• (configurado)' : 'valida HMAC do webhook'; ?>" style="width:340px"></td></tr>
        <tr><th>Verify Token</th><td><input type="text" id="mo-verify" placeholder="<?php echo $tem_verify === 'sim' ? '•••• (configurado)' : 'token de verificação do webhook'; ?>" style="width:340px"></td></tr>
        <tr><th>Encaminhar ao agente N8N</th><td><label><input type="checkbox" id="mo-fwd" <?php checked( $fwd_n8n, '1' ); ?>> mensagens Meta acionam o agente (mesmo fluxo do Evolution)</label></td></tr>
    </tbody></table>
    <p><button class="button button-primary" id="mo-save-global">Salvar conexão</button> <span id="mo-global-msg" style="margin-left:8px"></span></p>
    <p class="description">Webhook a cadastrar no painel da Meta: <code><?php echo esc_html( rest_url( 'tao-crm/v1/meta-webhook' ) ); ?></code></p>

    <h2 style="margin-top:26px">2. Instâncias</h2>
    <table class="widefat striped"><thead><tr><th>Instância</th><th>Provider</th><th>Phone Number ID</th><th>WABA ID</th><th>Graph</th><th></th></tr></thead>
    <tbody>
        <?php foreach ( $inst as $i ) : ?>
        <tr data-id="<?php echo esc_attr( $i['id'] ); ?>">
            <td><strong><?php echo esc_html( $i['nome'] ?? '' ); ?></strong></td>
            <td><select class="mo-prov" <?php disabled( ! $migrada ); ?>>
                <option value="evolution"  <?php selected( $i['provider'] ?? 'evolution', 'evolution' ); ?>>evolution</option>
                <option value="meta_cloud" <?php selected( $i['provider'] ?? '', 'meta_cloud' ); ?>>meta_cloud</option>
            </select></td>
            <td><input class="mo-pnid" value="<?php echo esc_attr( $i['meta_phone_number_id'] ?? '' ); ?>" style="width:150px" <?php disabled( ! $migrada ); ?>></td>
            <td><input class="mo-waba" value="<?php echo esc_attr( $i['meta_waba_id'] ?? '' ); ?>" style="width:150px" <?php disabled( ! $migrada ); ?>></td>
            <td><input class="mo-gv" value="<?php echo esc_attr( $i['meta_graph_version'] ?? 'v26.0' ); ?>" style="width:70px" <?php disabled( ! $migrada ); ?>></td>
            <td><button class="button mo-save-inst" <?php disabled( ! $migrada ); ?>>Salvar</button> <span class="mo-inst-msg"></span></td>
        </tr>
        <?php endforeach; ?>
    </tbody></table>

    <h2 style="margin-top:26px">3. Templates <span style="font-size:12px;color:#94a3b8;font-weight:400">(modelos aprovados 1× pela Meta — abrir conversa fria/campanha)</span></h2>
    <div style="border:2px dashed #cbd5e1;border-radius:10px;padding:14px;background:#f8fafc;max-width:720px">
        <input type="hidden" id="tpl-id">
        <table class="form-table"><tbody>
            <tr><th>Nome (slug)</th><td><input id="tpl-nome" placeholder="orcamento_pronto" style="width:260px"></td></tr>
            <tr><th>Categoria</th><td><select id="tpl-cat"><option value="utility">utility</option><option value="marketing">marketing</option><option value="authentication">authentication</option></select>
                &nbsp; Idioma <input id="tpl-idioma" value="pt_BR" style="width:80px"></td></tr>
            <tr><th>Corpo</th><td><textarea id="tpl-corpo" rows="3" style="width:100%" placeholder="Olá {{1}}, seu orçamento nº {{2}} está pronto! Deseja finalizar?"></textarea>
                <span class="description">use {{1}}, {{2}}… para variáveis</span></td></tr>
            <tr><th>Status</th><td><select id="tpl-status"><option value="rascunho">rascunho</option><option value="submetido">submetido</option><option value="aprovado">aprovado</option><option value="rejeitado">rejeitado</option><option value="pausado">pausado</option></select></td></tr>
        </tbody></table>
        <button class="button button-primary" id="tpl-save">Salvar template</button>
        <button class="button" id="tpl-clear">Limpar</button>
        <span id="tpl-msg" style="margin-left:8px"></span>
    </div>
    <table class="widefat striped" style="margin-top:12px;max-width:900px"><thead><tr><th>Nome</th><th>Categoria</th><th>Idioma</th><th>Status</th><th>Corpo</th><th></th></tr></thead>
    <tbody id="tpl-lista"><tr><td colspan="6">carregando…</td></tr></tbody></table>
    </div>

    <script>
    (function(){
        var AJAX = ajaxurl, N = <?php echo wp_json_encode( $nonce ); ?>;
        function post(action, data){ var fd=new FormData(); fd.append('action',action); fd.append('nonce',N);
            for(var k in data) fd.append(k, data[k]); return fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}); }
        function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':String(s)); return d.innerHTML; }

        // 1. conexão global
        document.getElementById('mo-save-global').addEventListener('click', function(){
            var m=document.getElementById('mo-global-msg'); m.textContent='Salvando…';
            post('tao_crm_meta_config_global', {
                api_base: document.getElementById('mo-apibase').value.trim(),
                token: document.getElementById('mo-token').value,
                app_secret: document.getElementById('mo-secret').value,
                verify_token: document.getElementById('mo-verify').value,
                forward_n8n: document.getElementById('mo-fwd').checked ? '1' : '0'
            }).then(function(r){ m.textContent = r.success ? '✓ salvo' : ('erro: '+(r.data||'')); m.style.color=r.success?'#16a34a':'#dc2626'; });
        });

        // 2. instâncias
        document.querySelectorAll('.mo-save-inst').forEach(function(b){ b.addEventListener('click', function(){
            var tr=b.closest('tr'), m=tr.querySelector('.mo-inst-msg'); m.textContent='…';
            post('tao_crm_meta_config_instancia', {
                id: tr.getAttribute('data-id'),
                provider: tr.querySelector('.mo-prov').value,
                meta_phone_number_id: tr.querySelector('.mo-pnid').value.trim(),
                meta_waba_id: tr.querySelector('.mo-waba').value.trim(),
                meta_graph_version: tr.querySelector('.mo-gv').value.trim()
            }).then(function(r){ m.textContent = r.success ? '✓' : ('erro: '+(r.data||'')); m.style.color=r.success?'#16a34a':'#dc2626'; });
        }); });

        // 3. templates
        function tplList(){
            post('tao_crm_wa_templates_list', {}).then(function(r){
                var tb=document.getElementById('tpl-lista');
                if(!r.success){ tb.innerHTML='<tr><td colspan="6" style="color:#dc2626">'+(r.data||'erro — rode a migration')+'</td></tr>'; return; }
                var ts=r.data||[]; if(!ts.length){ tb.innerHTML='<tr><td colspan="6" style="color:#64748b">Nenhum template.</td></tr>'; return; }
                tb.innerHTML=''; ts.forEach(function(t){
                    var tr=document.createElement('tr');
                    tr.innerHTML='<td><strong>'+esc(t.nome)+'</strong></td><td>'+esc(t.categoria)+'</td><td>'+esc(t.idioma)+'</td><td>'+esc(t.status_aprovacao)+'</td><td style="max-width:320px">'+esc((t.corpo||'').slice(0,120))+'</td>'+
                        '<td><button class="button button-small tpl-edit">editar</button> <button class="button button-small tpl-del">excluir</button></td>';
                    tr.querySelector('.tpl-edit').addEventListener('click', function(){
                        document.getElementById('tpl-id').value=t.id; document.getElementById('tpl-nome').value=t.nome||'';
                        document.getElementById('tpl-cat').value=t.categoria||'utility'; document.getElementById('tpl-idioma').value=t.idioma||'pt_BR';
                        document.getElementById('tpl-corpo').value=t.corpo||''; document.getElementById('tpl-status').value=t.status_aprovacao||'rascunho';
                        window.scrollTo(0,0);
                    });
                    tr.querySelector('.tpl-del').addEventListener('click', function(){ if(!confirm('Excluir template?'))return;
                        post('tao_crm_wa_template_delete',{id:t.id}).then(tplList); });
                    tb.appendChild(tr);
                });
            });
        }
        document.getElementById('tpl-save').addEventListener('click', function(){
            var m=document.getElementById('tpl-msg'); m.textContent='Salvando…';
            post('tao_crm_wa_template_save', {
                id: document.getElementById('tpl-id').value,
                nome: document.getElementById('tpl-nome').value.trim(),
                categoria: document.getElementById('tpl-cat').value,
                idioma: document.getElementById('tpl-idioma').value.trim(),
                corpo: document.getElementById('tpl-corpo').value,
                status_aprovacao: document.getElementById('tpl-status').value
            }).then(function(r){ if(r.success){ m.textContent='✓ salvo'; m.style.color='#16a34a'; document.getElementById('tpl-clear').click(); tplList(); }
                else { m.textContent='erro: '+(r.data||''); m.style.color='#dc2626'; } });
        });
        document.getElementById('tpl-clear').addEventListener('click', function(){
            ['tpl-id','tpl-nome','tpl-corpo'].forEach(function(i){ document.getElementById(i).value=''; });
            document.getElementById('tpl-cat').value='utility'; document.getElementById('tpl-idioma').value='pt_BR'; document.getElementById('tpl-status').value='rascunho';
            document.getElementById('tpl-msg').textContent='';
        });
        tplList();
    })();
    </script>
    <?php
}

// ── AJAX: config global (wp_options) ──────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_meta_config_global', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'negado' );
    if ( isset( $_POST['api_base'] ) )     update_option( 'tao_crm_meta_api_base', esc_url_raw( trim( $_POST['api_base'] ) ) ?: 'https://graph.facebook.com' );
    if ( ! empty( $_POST['token'] ) )      update_option( 'tao_crm_meta_token', sanitize_text_field( $_POST['token'] ) );
    if ( ! empty( $_POST['app_secret'] ) ) update_option( 'tao_crm_meta_app_secret', sanitize_text_field( $_POST['app_secret'] ) );
    if ( ! empty( $_POST['verify_token'] ) ) update_option( 'tao_crm_meta_verify_token', sanitize_text_field( $_POST['verify_token'] ) );
    update_option( 'tao_crm_meta_forward_n8n', ( $_POST['forward_n8n'] ?? '0' ) === '1' ? '1' : '0' );
    wp_send_json_success();
} );

// ── AJAX: config por instância (crm_instancias) ───────────────────────────────
add_action( 'wp_ajax_tao_crm_meta_config_instancia', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'negado' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id' );
    $patch = [
        'provider'             => ( $_POST['provider'] ?? 'evolution' ) === 'meta_cloud' ? 'meta_cloud' : 'evolution',
        'meta_phone_number_id' => sanitize_text_field( $_POST['meta_phone_number_id'] ?? '' ) ?: null,
        'meta_waba_id'         => sanitize_text_field( $_POST['meta_waba_id'] ?? '' ) ?: null,
        'meta_graph_version'   => sanitize_text_field( $_POST['meta_graph_version'] ?? 'v26.0' ) ?: 'v26.0',
    ];
    $r = tao_crm_api( "/crm_instancias?id=eq.$id", 'PATCH', $patch );
    empty( $r['ok'] ) ? wp_send_json_error( 'Falha (migration rodada?) ' . substr( (string) ( $r['error'] ?? '' ), 0, 120 ) ) : wp_send_json_success();
} );

// ── AJAX: templates CRUD (wa_templates) ───────────────────────────────────────
add_action( 'wp_ajax_tao_crm_wa_templates_list', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'negado' );
    $r = tao_crm_api( "/wa_templates?select=*&order=atualizado_em.desc&limit=500" );
    empty( $r['ok'] ) ? wp_send_json_error( 'Tabela indisponível — rode a migration.' ) : wp_send_json_success( $r['data'] ?? [] );
} );
add_action( 'wp_ajax_tao_crm_wa_template_save', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'negado' );
    $nome = sanitize_title( $_POST['nome'] ?? '' );
    if ( ! $nome ) wp_send_json_error( 'Informe o nome (slug).' );
    $body = [
        'nome'             => $nome,
        'categoria'        => in_array( $_POST['categoria'] ?? '', [ 'utility', 'marketing', 'authentication' ], true ) ? $_POST['categoria'] : 'utility',
        'idioma'           => sanitize_text_field( $_POST['idioma'] ?? 'pt_BR' ) ?: 'pt_BR',
        'corpo'            => wp_kses_post( wp_unslash( $_POST['corpo'] ?? '' ) ),
        'status_aprovacao' => in_array( $_POST['status_aprovacao'] ?? '', [ 'rascunho', 'submetido', 'aprovado', 'rejeitado', 'pausado' ], true ) ? $_POST['status_aprovacao'] : 'rascunho',
        'atualizado_em'    => gmdate( 'c' ),
    ];
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    $r = $id
        ? tao_crm_api( "/wa_templates?id=eq.$id", 'PATCH', $body )
        : tao_crm_api( '/wa_templates', 'POST', $body );
    empty( $r['ok'] ) ? wp_send_json_error( 'Falha ao salvar (migration?).' ) : wp_send_json_success();
} );
add_action( 'wp_ajax_tao_crm_wa_template_delete', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'negado' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( $id ) tao_crm_api( "/wa_templates?id=eq.$id", 'DELETE' );
    wp_send_json_success();
} );
