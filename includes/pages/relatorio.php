<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Relatório denormalizado de cards — 1 linha por card com TODAS as informações
 * (dados do card + campos customizados dinâmicos + origem/tipo de fechamento),
 * com filtros (período, negócio, funil, responsável) e exportação XLSX (SheetJS).
 * Read-only, restrito a gestão, tenant-scoped.
 */
function tao_crm_page_relatorio() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) {
        echo '<div class="wrap"><p>Acesso negado.</p></div>'; return;
    }
    $_is_master = current_user_can( 'manage_options' )
        || ( function_exists( 'cbpm_current_role' ) && cbpm_current_role() === 'master' );
    if ( ! $_is_master ) {
        $ws = tao_crm_get_workspace();
    } else {
        $ws_id_g = sanitize_text_field( $_GET['workspace_id'] ?? '' );
        $_def_ws = get_option( 'tao_crm_default_workspace_id', '' );
        $ws      = tao_crm_get_workspace( $ws_id_g ?: ( $_def_ws ?: null ) );
    }
    if ( ! $ws ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhum workspace configurado.</p></div></div>'; return;
    }
    $ws_id = $ws['id'];
    if ( ! tao_crm_is_gestor( $ws_id ) ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>&#x1F512; O Relatório é restrito a perfis de gestão.</p></div></div>'; return;
    }

    $nonce   = wp_create_nonce( 'tao_crm_nonce' );
    $ajaxurl = admin_url( 'admin-ajax.php' );
    $mes_ini = gmdate( 'Y-m-01' );
    $hoje    = gmdate( 'Y-m-d' );

    // funis (dropdown) e equipe (responsáveis) p/ os filtros
    $pipes = [];
    $rp = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&select=id,nome&order=nome.asc" );
    foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $p ) $pipes[ $p['id'] ] = $p['nome'];
    $equipe = function_exists( 'tao_crm_get_equipe_ws' ) ? tao_crm_get_equipe_ws( $ws_id ) : [];
    ?>
    <style>
      .tao-rel h1{margin-bottom:2px}
      .rel-filtros{display:flex;gap:10px;align-items:end;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px}
      .rel-f{display:flex;flex-direction:column;gap:3px}
      .rel-f label{font-size:11px;color:#64748b;font-weight:600}
      .rel-f input,.rel-f select{padding:6px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px}
      .rel-presets{display:flex;gap:4px;margin-top:6px;flex-wrap:wrap}
      .rel-presets .button{font-size:12px}
      .rel-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}
      .rel-status{font-size:13px;color:#475569}
      .rel-tbl-wrap{overflow:auto;max-height:62vh;border:1px solid #e2e8f0;border-radius:8px}
      table.rel-tbl{border-collapse:collapse;font-size:12px;white-space:nowrap}
      table.rel-tbl th,table.rel-tbl td{border:1px solid #e5e7eb;padding:5px 8px;text-align:left}
      table.rel-tbl th{background:#f1f5f9;position:sticky;top:0;z-index:1;color:#334155}
      table.rel-tbl tr:nth-child(even) td{background:#fafafa}
      .rel-hint{font-size:11px;color:#94a3b8}
    </style>

    <div class="wrap tao-rel">
        <h1>&#x1F4C4; Relatório de Cards</h1>
        <p style="margin:6px 0 12px;color:#64748b;font-size:13px">Uma linha por card com todas as informações (campos do card + campos personalizados + origem). Filtre e exporte para Excel (.xlsx).</p>

        <div class="rel-filtros">
            <div class="rel-f">
                <label>De</label>
                <input type="date" id="rel-de" value="<?php echo esc_attr( $mes_ini ); ?>">
            </div>
            <div class="rel-f">
                <label>Até</label>
                <input type="date" id="rel-ate" value="<?php echo esc_attr( $hoje ); ?>">
            </div>
            <div class="rel-f">
                <label>Negócio</label>
                <select id="rel-negocio">
                    <option value="todos">Todos</option>
                    <option value="ganho">Ganhos</option>
                    <option value="perda">Perdas / Cancelados</option>
                    <option value="andamento">Em andamento</option>
                </select>
            </div>
            <div class="rel-f">
                <label>Funil</label>
                <select id="rel-funil">
                    <option value="">Todos os funis</option>
                    <?php foreach ( $pipes as $pid => $pnome ) : ?>
                        <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pnome ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rel-f">
                <label>Responsável</label>
                <select id="rel-resp">
                    <option value="">Todos</option>
                    <?php foreach ( $equipe as $u ) : ?>
                        <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rel-f">
                <label>&nbsp;</label>
                <button type="button" class="button button-primary" id="rel-gerar">Gerar relatório</button>
            </div>
        </div>
        <div class="rel-presets">
            <button type="button" class="button" data-preset="hoje">Hoje</button>
            <button type="button" class="button" data-preset="mes">Mês corrente</button>
            <button type="button" class="button" data-preset="7">7 dias</button>
            <button type="button" class="button" data-preset="30">30 dias</button>
            <button type="button" class="button" data-preset="90">90 dias</button>
            <span class="rel-hint">— período pela data de <b>criação</b> do card.</span>
        </div>

        <div class="rel-bar">
            <span class="rel-status" id="rel-status">Defina os filtros e clique em <b>Gerar relatório</b>.</span>
            <span style="flex:1"></span>
            <button type="button" class="button" id="rel-xlsx" disabled>&#x2B07; Exportar XLSX</button>
            <button type="button" class="button" id="rel-csv" disabled>Exportar CSV</button>
        </div>

        <div class="rel-tbl-wrap" id="rel-tbl-wrap" style="display:none">
            <table class="rel-tbl" id="rel-tbl"><thead></thead><tbody></tbody></table>
        </div>
    </div>

    <script>
    (function () {
        var ajaxurl = <?php echo wp_json_encode( $ajaxurl ); ?>;
        var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
        var wsId    = <?php echo wp_json_encode( $ws_id ); ?>;
        var SHEETJS = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
        var PREVIEW_MAX = 300;   // linhas mostradas na tela (o export leva TODAS)
        var COLS=[], ROWS=[], sheetjsReady=false;

        function el(id){ return document.getElementById(id); }
        function toISO(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
        function setStatus(t){ el('rel-status').innerHTML=t; }
        function loadSheetJS(cb){ if(sheetjsReady){cb();return;} var s=document.createElement('script'); s.src=SHEETJS; s.onload=function(){sheetjsReady=true;cb();}; s.onerror=function(){ setStatus('⚠ Não consegui carregar a biblioteca de Excel. Tente o CSV.'); }; document.head.appendChild(s); }

        // presets de período
        Array.prototype.forEach.call(document.querySelectorAll('[data-preset]'), function(b){ b.onclick=function(){
            var p=b.dataset.preset, a=new Date(), d=new Date();
            if(p==='hoje'){ el('rel-de').value=toISO(a); el('rel-ate').value=toISO(a); }
            else if(p==='mes'){ el('rel-de').value=toISO(new Date(a.getFullYear(),a.getMonth(),1)); el('rel-ate').value=toISO(a); }
            else { d.setDate(d.getDate()-(parseInt(p,10)-1)); el('rel-de').value=toISO(d); el('rel-ate').value=toISO(a); }
        }; });

        function gerar(){
            setStatus('Gerando… buscando cards e campos.');
            el('rel-xlsx').disabled=true; el('rel-csv').disabled=true;
            var body='action=tao_crm_relatorio_dataset&nonce='+encodeURIComponent(nonce)
                +'&workspace_id='+encodeURIComponent(wsId)
                +'&de='+encodeURIComponent(el('rel-de').value)
                +'&ate='+encodeURIComponent(el('rel-ate').value)
                +'&negocio='+encodeURIComponent(el('rel-negocio').value)
                +'&pipeline_id='+encodeURIComponent(el('rel-funil').value)
                +'&responsavel_id='+encodeURIComponent(el('rel-resp').value);
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
            .then(function(r){return r.text();}).then(function(t){
                var i=t.indexOf('{'), j; try{ j=JSON.parse(i>0?t.slice(i):t); }catch(e){ setStatus('⚠ Erro ao ler a resposta.'); return; }
                if(!j||!j.success){ setStatus('⚠ '+((j&&j.data)||'Falha ao gerar.')); return; }
                COLS=j.data.colunas||[]; ROWS=j.data.rows||[];
                render();
                el('rel-xlsx').disabled=ROWS.length===0; el('rel-csv').disabled=ROWS.length===0;
                setStatus('<b>'+ROWS.length+'</b> card(s) · <b>'+COLS.length+'</b> colunas'+(ROWS.length>PREVIEW_MAX?' · mostrando os primeiros '+PREVIEW_MAX+' (o export leva todos)':''));
            }).catch(function(){ setStatus('⚠ Erro de rede ao gerar.'); });
        }

        function render(){
            var wrap=el('rel-tbl-wrap'); if(!ROWS.length){ wrap.style.display='none'; return; }
            wrap.style.display='block';
            var thead='<tr>'+COLS.map(function(c){return '<th>'+esc(c)+'</th>';}).join('')+'</tr>';
            var body=ROWS.slice(0,PREVIEW_MAX).map(function(r){ return '<tr>'+r.map(function(v){return '<td>'+esc(v==null?'':String(v))+'</td>';}).join('')+'</tr>'; }).join('');
            el('rel-tbl').querySelector('thead').innerHTML=thead;
            el('rel-tbl').querySelector('tbody').innerHTML=body;
        }
        function esc(s){ return String(s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }

        function nomeArq(ext){ return 'relatorio-cards_'+el('rel-de').value+'_a_'+el('rel-ate').value+'.'+ext; }

        function exportXLSX(){
            if(!ROWS.length) return;
            setStatus('Montando planilha…');
            loadSheetJS(function(){
                var aoa=[COLS].concat(ROWS);
                var ws=XLSX.utils.aoa_to_sheet(aoa);
                ws['!cols']=COLS.map(function(c,i){ var w=String(c).length; ROWS.forEach(function(r){ var v=r[i]==null?'':String(r[i]); if(v.length>w)w=v.length; }); return {wch:Math.min(Math.max(w+1,8),50)}; });
                var wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Cards');
                XLSX.writeFile(wb,nomeArq('xlsx'));
                setStatus('<b>'+ROWS.length+'</b> card(s) exportados para Excel.');
            });
        }
        function exportCSV(){
            if(!ROWS.length) return;
            function cell(v){ v=v==null?'':String(v); return /[";\n]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v; }
            var lines=[COLS.map(cell).join(';')].concat(ROWS.map(function(r){return r.map(cell).join(';');}));
            var blob=new Blob(['﻿'+lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});
            var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=nomeArq('csv'); a.click(); URL.revokeObjectURL(a.href);
        }

        el('rel-gerar').onclick=gerar;
        el('rel-xlsx').onclick=exportXLSX;
        el('rel-csv').onclick=exportCSV;
    })();
    </script>
    <?php
}
