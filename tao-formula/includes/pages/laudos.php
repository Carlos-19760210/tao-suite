<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Certificados / Laudos de MP. Consulta os laudos importados por lote
 * e emite o Certificado de Análise da farmácia (RDC 67) a partir do laudo do fornecedor.
 */
function tao_formula_page_laudos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>📄 Certificados / Laudos de MP <small style="font-size:12px;color:#94a3b8;font-weight:400">(laudos importados por lote + emissão do certificado da farmácia)</small></h1>
    <p style="color:#475569;max-width:820px">Laudos (Certificados de Análise) importados dos fornecedores, por lote. Clique em um item para ver os dados e <b>gerar o Certificado de Análise da farmácia</b> daquele lote (RDC 67).</p>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:12px 0">
        <div><label style="font-size:11px;color:#64748b;display:block">Buscar (ativo, lote, fabricante)</label><input id="lc-busca" style="width:260px;padding:5px"></div>
        <div><label style="font-size:11px;color:#64748b;display:block">Resultado</label>
            <select id="lc-res" style="padding:5px"><option value="">Todos</option><option value="aprovado">Aprovado</option><option value="reprovado">Reprovado</option></select></div>
        <button type="button" class="button" id="lc-buscar">Filtrar</button>
    </div>

    <div id="lc-lista">carregando…</div>
    <div id="lc-detalhe" style="margin-top:14px"></div>
    </div>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce;
        var CACHE={};   // id -> detalhe
        function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
        function dBR(iso){ if(!iso) return '—'; var m=String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?(m[3]+'/'+m[2]+'/'+m[1]):iso; }
        function pill(res){ if(res==='aprovado') return '<span style="background:#dcfce7;color:#166534;padding:1px 7px;border-radius:10px;font-size:11px">Aprovado</span>';
            if(res==='reprovado') return '<span style="background:#fee2e2;color:#991b1b;padding:1px 7px;border-radius:10px;font-size:11px">Reprovado</span>'; return '<span style="color:#94a3b8">—</span>'; }

        function carregar(){
            $('#lc-lista').html('carregando…');
            $.post(ajaxUrl,{action:'tao_formula_laudos_lista',nonce:nonce,busca:$('#lc-busca').val(),resultado:$('#lc-res').val()},function(r){
                if(!r||!r.success){ $('#lc-lista').text('Erro ao carregar.'); return; }
                var ls=r.data||[]; if(!ls.length){ $('#lc-lista').html('<p style="color:#64748b">Nenhum laudo importado ainda.</p>'); return; }
                var h='<table class="widefat"><thead><tr><th>Ativo</th><th>Lote</th><th>Validade</th><th>Fabricante</th><th>Resultado</th><th>PDF</th><th></th></tr></thead><tbody>';
                ls.forEach(function(l){
                    h+='<tr><td><b>'+esc(l.ativo_nome||l.produto_nome||'?')+'</b>'+(l.produto_nome&&l.ativo_nome?('<br><small style="color:#94a3b8">'+esc(l.produto_nome)+'</small>'):'')+'</td>'+
                       '<td>'+esc(l.nr_lote||l.lote_original||'—')+'</td><td>'+dBR(l.dt_validade)+'</td><td>'+esc(l.fabricante||'—')+'</td><td>'+pill(l.resultado)+'</td>'+
                       '<td>'+(l.pdf_url?('<a href="'+ajaxUrl+'?action=tao_formula_laudo_pdf&nonce='+nonce+'&id='+esc(l.id)+'" target="_blank">abrir</a>'):'—')+'</td>'+
                       '<td><a href="#" class="lc-ver" data-id="'+esc(l.id)+'">ver / certificado</a></td></tr>';
                });
                h+='</tbody></table>';
                $('#lc-lista').html(h);
            });
        }
        $('#lc-buscar').on('click',carregar);
        $('#lc-busca').on('keydown',function(e){ if(e.which===13) carregar(); });
        $('#lc-res').on('change',carregar);

        function linha(rot,val){ if(val==null||val==='') return ''; return '<tr><td style="color:#64748b;width:180px">'+esc(rot)+'</td><td>'+esc(val)+'</td></tr>'; }
        $(document).on('click','.lc-ver',function(e){ e.preventDefault(); var id=$(this).data('id');
            var $d=$('#lc-detalhe').html('<p style="color:#64748b">carregando detalhe…</p>');
            $.post(ajaxUrl,{action:'tao_formula_laudo_detalhe',nonce:nonce,id:id},function(r){
                if(!r||!r.success){ $d.text('Erro.'); return; }
                CACHE[id]=r.data; var L=r.data.laudo, A=r.data.ativo||{}, LT=r.data.lote||{};
                var h='<div style="border:1px solid #cbd5e1;border-radius:10px;padding:14px;background:#fff">'+
                    '<div style="display:flex;justify-content:space-between;align-items:start;gap:10px">'+
                    '<h2 style="margin:0">'+esc(A.nome||L.produto_nome||'Laudo')+' <small style="color:#94a3b8;font-weight:400">lote '+esc(LT.nr_lote||L.lote_original||'')+'</small></h2>'+
                    '<div>'+(L.pdf_url?('<a class="button" href="'+ajaxUrl+'?action=tao_formula_laudo_pdf&nonce='+nonce+'&id='+esc(id)+'" target="_blank">📎 PDF do fornecedor</a> '):'')+
                    '<button type="button" class="button button-primary lc-cert" data-id="'+esc(id)+'">📄 Gerar Certificado</button></div></div>'+
                    '<table class="widefat" style="margin-top:10px;max-width:720px"><tbody>'+
                    linha('Produto (laudo)',L.produto_nome)+linha('Nome científico',L.nome_cientifico)+linha('Parte utilizada',L.parte_utilizada)+
                    linha('Lote (casado)',LT.nr_lote)+linha('Lote original',L.lote_original)+linha('Lote interno',L.lote_interno)+
                    linha('Fabricação',dBR(L.dt_fabricacao))+linha('Validade',dBR(L.dt_validade))+linha('Emissão',dBR(L.dt_emissao))+
                    linha('Fabricante',L.fabricante)+linha('Origem',L.origem)+linha('Procedência',L.procedencia)+
                    linha('DCB',L.dcb)+linha('CAS',L.cas)+
                    linha('Conservação (temp.)',L.conservacao_temp)+linha('Conservação (umid.)',L.conservacao_umid)+
                    linha('Higroscópico',L.higroscopico===true?'Sim':(L.higroscopico===false?'Não':''))+
                    linha('Fotossensível',L.fotossensivel===true?'Sim':(L.fotossensivel===false?'Não':''))+
                    linha('Conclusão',L.conclusao)+linha('Resultado',L.resultado)+linha('Resp. técnico (laudo)',(L.rt_nome||'')+(L.rt_crf?(' — '+L.rt_crf):''))+
                    '</tbody></table>';
                var ens=r.data.ensaios||[];
                if(ens.length){ h+='<h4 style="margin:12px 0 4px">Ensaios</h4><table class="widefat" style="max-width:720px"><thead><tr><th>Teste</th><th>Especificação</th><th>Resultado</th></tr></thead><tbody>';
                    ens.forEach(function(e){ h+='<tr><td>'+esc(e.teste)+'</td><td>'+esc(e.especificacao)+'</td><td>'+esc(e.resultado)+'</td></tr>'; }); h+='</tbody></table>'; }
                else if(L.texto_extraido){ h+='<h4 style="margin:12px 0 4px">Ensaios (bloco do laudo)</h4><pre style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px;max-width:720px;font-size:12px">'+esc(L.texto_extraido)+'</pre>'; }
                h+='</div>';
                $d.html(h); $('html,body').animate({scrollTop:$d.offset().top-20},250);
            });
        });

        // ── Gera o Certificado de Análise da FARMÁCIA (documento imprimível) ──
        $(document).on('click','.lc-cert',function(){
            var id=$(this).data('id'), D=CACHE[id]; if(!D) return;
            var L=D.laudo, A=D.ativo||{}, LT=D.lote||{}, F=D.farmacia||{};
            var farm=esc(F.nome_fantasia||F.razao_social||'Farmácia'), cnpj=esc(F.cnpj||'');
            var rt=esc(F.rt_nome||''), crf=esc((F.rt_crf||'')+(F.rt_uf?(' / '+F.rt_uf):''));
            var hoje=new Date().toLocaleDateString('pt-BR');
            var ens=D.ensaios||[], ensRows='';
            ens.forEach(function(e){ ensRows+='<tr><td>'+esc(e.teste)+'</td><td>'+esc(e.especificacao)+'</td><td>'+esc(e.resultado)+'</td><td style="text-align:center">'+(e.conforme===false?'Não':(e.conforme===true?'Sim':'—'))+'</td></tr>'; });
            if(!ens.length && L.texto_extraido){ ensRows='<tr><td colspan="4"><div style="white-space:pre-wrap;font-size:11px">'+esc(L.texto_extraido)+'</div></td></tr>'; }
            function li(r,v){ return v?('<tr><td class="k">'+esc(r)+'</td><td>'+esc(v)+'</td></tr>'):''; }
            var resultado = L.resultado==='reprovado' ? 'REPROVADO' : (L.resultado==='aprovado' ? 'APROVADO' : '—');
            var html='<!doctype html><html><head><meta charset="utf-8"><title>Certificado de Análise — '+esc(A.nome||L.produto_nome||'')+'</title>'+
              '<style>body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:28px;font-size:12px}h1{font-size:18px;text-align:center;margin:0 0 2px}'+
              '.sub{text-align:center;color:#444;margin:0 0 14px}table{border-collapse:collapse;width:100%;margin:8px 0}td,th{border:1px solid #999;padding:4px 6px;vertical-align:top}'+
              'th{background:#eee;text-align:left}.k{color:#555;width:190px}.box{border:1px solid #999;padding:8px;margin:8px 0}.assin{margin-top:46px;text-align:center}'+
              '.res{font-size:15px;font-weight:bold;text-align:center;padding:6px;border:2px solid #111;margin:10px 0}@media print{button{display:none}}</style></head><body>'+
              '<div style="text-align:right"><button onclick="window.print()">🖨 Imprimir / PDF</button></div>'+
              '<h1>'+farm+'</h1><p class="sub">'+(cnpj?('CNPJ '+cnpj+' · '):'')+'CERTIFICADO DE ANÁLISE — MATÉRIA-PRIMA</p>'+
              '<table><tbody>'+
                li('Insumo / Ativo',A.nome||L.produto_nome)+li('Código',A.codigo_fc)+li('Nome científico',L.nome_cientifico)+li('Parte utilizada',L.parte_utilizada)+
                li('Lote',LT.nr_lote||L.lote_original)+li('Lote do fabricante',L.lote_original)+li('DCB',L.dcb)+li('CAS',L.cas)+
                li('Fabricante',L.fabricante)+li('Fornecedor',L.fabricante)+li('Origem / Procedência',(L.origem||'')+(L.procedencia?(' / '+L.procedencia):''))+
                li('Fabricação',dBR(L.dt_fabricacao))+li('Validade',dBR(L.dt_validade))+
                li('Conservação',(L.conservacao_temp||'')+(L.conservacao_umid?(' · Umidade: '+L.conservacao_umid):''))+
                li('Higroscópico / Fotossensível',(L.higroscopico===true?'Higroscópico':'')+(L.fotossensivel===true?' · Fotossensível':'')||'—')+
              '</tbody></table>'+
              '<h3 style="margin:14px 0 2px">Ensaios / Especificações</h3>'+
              '<table><thead><tr><th>Teste</th><th>Especificação</th><th>Resultado</th><th style="width:70px">Conforme</th></tr></thead><tbody>'+(ensRows||'<tr><td colspan="4" style="color:#777">Ver laudo do fornecedor anexo.</td></tr>')+'</tbody></table>'+
              '<div class="box"><b>Identificação da matéria-prima:</b> ( &nbsp; ) Realizada / Conforme &nbsp;&nbsp; ( &nbsp; ) Conforme laudo do fornecedor'+
              '<br><small>Caracteres organolépticos conferidos e de acordo com a especificação. Laudo do fornecedor arquivado no dossiê do lote.</small></div>'+
              '<div class="res">RESULTADO: '+resultado+'</div>'+
              (L.conclusao?('<p><b>Conclusão:</b> '+esc(L.conclusao)+'</p>'):'')+
              '<table style="margin-top:6px"><tbody>'+li('Certificado nº',(LT.nr_lote||L.lote_original||'')+' / '+(new Date().getFullYear()))+li('Data de emissão',hoje)+'</tbody></table>'+
              '<div class="assin">_______________________________________<br><b>'+(rt||'Responsável Técnico')+'</b>'+(crf?('<br>CRF '+crf):'')+'<br>Farmacêutico(a) Responsável Técnico</div>'+
              '<p style="margin-top:20px;font-size:10px;color:#777">Emitido pelo TAO Neo com base no Certificado de Análise do fornecedor'+(L.lote_interno?(' (lote interno '+esc(L.lote_interno)+')'):'')+'. Documento sujeito a conferência da RT.</p>'+
              '</body></html>';
            var w=window.open('','_blank'); if(!w){ alert('Permita pop-ups para gerar o certificado.'); return; }
            w.document.open(); w.document.write(html); w.document.close();
        });

        carregar();
    });
    </script>
    <?php
}
