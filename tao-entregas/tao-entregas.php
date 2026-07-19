<?php
/**
 * Plugin Name: TAO Entregas
 * Description: Módulo transversal de entregas (última milha) — TAO Suite. Pluga no card do CRM e é acionável por qualquer módulo.
 * Version:     1.0.0
 * Author:      TAO Suite
 * Text Domain: tao-entregas
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAOENT_VERSION',    '1.0.0' );
define( 'TAOENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once TAOENT_PLUGIN_DIR . 'includes/ajax.php';
require_once TAOENT_PLUGIN_DIR . 'includes/painel.php';

/** Tipos de entrega (parametrizável no futuro). */
function tao_entregas_tipos() {
    return [
        'cliente' => 'Cliente (Uber / 99 / transp.)',
        'correio' => 'Correio',
        'motoboy' => 'Motoboy',
        'balcao'  => 'Balcão',
    ];
}

/** Acesso ao Supabase — reaproveita o cliente REST do TAO CRM. */
function tao_entregas_api( $path, $method = 'GET', $body = null ) {
    if ( function_exists( 'tao_crm_api' ) ) return tao_crm_api( $path, $method, $body );
    return [ 'ok' => false, 'data' => null, 'raw' => 'tao_crm_api indisponível' ];
}
function tao_entregas_can_access() {
    if ( function_exists( 'tao_crm_tela_oculta' ) && tao_crm_tela_oculta( 'entregas' ) ) return false;
    return function_exists( 'cbpm_can_access' ) ? cbpm_can_access() : current_user_can( 'read' );
}

/**
 * GATILHO PLUGÁVEL — qualquer módulo cria/garante uma entrega para um card.
 * Idempotente por card: se já existe, retorna a existente.
 * $args: card_id, workspace_id, contato_id, origem, ref_id, endereco, valor_receber
 */
function tao_entregas_criar( array $args ) {
    $card_id = $args['card_id'] ?? null;
    $ws      = $args['workspace_id'] ?? null;
    if ( ! $card_id || ! $ws ) return null;
    $ex = tao_entregas_api( "/entregas?card_id=eq.$card_id&select=id&limit=1" );
    if ( ! empty( $ex['ok'] ) && ! empty( $ex['data'] ) ) return $ex['data'][0]['id'];
    $r = tao_entregas_api( '/entregas', 'POST', [
        'workspace_id' => $ws, 'card_id' => $card_id, 'contato_id' => $args['contato_id'] ?? null,
        'origem' => $args['origem'] ?? 'crm', 'ref_id' => $args['ref_id'] ?? null,
        'endereco' => $args['endereco'] ?? null, 'valor_receber' => $args['valor_receber'] ?? null,
        'status' => 'pendente', 'criado_por' => get_current_user_id(),
    ] );
    return ( ! empty( $r['ok'] ) && ! empty( $r['data'] ) ) ? ( $r['data'][0]['id'] ?? null ) : null;
}

/**
 * GATILHO na aprovação do card (ganho → Pós-vendas): garante a entrega.
 * Desacoplado — o CRM só dispara `do_action('tao_entregas_card_ganho', …)`, como faz com o Caixa.
 * Idempotente (tao_entregas_criar não duplica). Nunca interrompe o fluxo do CRM.
 */
add_action( 'tao_entregas_card_ganho', function ( $card_id, $ws ) {
    if ( ! $card_id || ! $ws ) return;
    $contato_id = $valor = null;
    $rc = tao_entregas_api( "/crm_cards?id=eq.$card_id&select=contato_id,valor_oportunidade&limit=1" );
    if ( ! empty( $rc['ok'] ) && ! empty( $rc['data'] ) ) {
        $contato_id = $rc['data'][0]['contato_id'] ?? null;
        $valor      = $rc['data'][0]['valor_oportunidade'] ?? null;
    }
    tao_entregas_criar( [
        'card_id'       => $card_id,
        'workspace_id'  => $ws,
        'contato_id'    => $contato_id,
        'valor_receber' => $valor,
        'origem'        => 'crm',
    ] );
}, 10, 2 );

/**
 * TRAVAS de movimentação de card (Fase 2b):
 *  - "Pronto para Entrega": exige endereço na entrega (exceto retirada no Balcão).
 *  - "NPS": exige que o pagamento esteja registrado (pago).
 * Só bloqueia quando o card TEM entrega vinculada (não interfere em cards sem entrega).
 */
add_filter( 'tao_crm_veto_mover_card', function ( $veto, $card_id, $estagio_id, $card ) {
    if ( is_array( $veto ) && ! empty( $veto['bloqueado'] ) ) return $veto;   // já vetado por outro módulo
    if ( ! $estagio_id ) return $veto;

    $re   = tao_entregas_api( "/crm_estagios?id=eq.$estagio_id&select=nome&limit=1" );
    $nome = mb_strtoupper( ! empty( $re['data'] ) ? ( $re['data'][0]['nome'] ?? '' ) : '' );
    $is_pronto = ( mb_strpos( $nome, 'PRONTO PARA ENTREGA' ) !== false );
    $is_nps    = ( mb_strpos( $nome, 'NPS' ) !== false );
    if ( ! $is_pronto && ! $is_nps ) return $veto;

    $en  = tao_entregas_api( "/entregas?card_id=eq.$card_id&select=tipo,endereco,pago&order=criado_em.desc&limit=1" );
    $ent = ( ! empty( $en['ok'] ) && ! empty( $en['data'] ) ) ? $en['data'][0] : null;
    if ( ! $ent ) return $veto;   // card sem entrega: não é fluxo de entrega, não trava

    if ( $is_pronto ) {
        $tipo = $ent['tipo'] ?? '';
        if ( $tipo !== 'balcao' && empty( trim( (string) ( $ent['endereco'] ?? '' ) ) ) ) {
            return [ 'bloqueado' => true, 'code' => 'entrega_sem_endereco',
                     'msg' => 'Informe o endereço na aba Entrega antes de mover para "Pronto para Entrega" (ou marque o tipo Balcão, para retirada).' ];
        }
    }
    if ( $is_nps && empty( $ent['pago'] ) ) {
        return [ 'bloqueado' => true, 'code' => 'entrega_nao_paga',
                 'msg' => 'Registre o pagamento na aba Entrega antes de mover o card para NPS.' ];
    }
    return $veto;
}, 10, 4 );

/**
 * Sentido inverso: baixa dada no Caixa (venda do card quitada) → marca a entrega
 * como paga, liberando o NPS. Fecha o ciclo pelos dois caminhos (Caixa ↔ Entrega).
 */
add_action( 'tao_caixa_venda_paga', function ( $card_id ) {
    if ( ! $card_id || ! function_exists( 'tao_entregas_api' ) ) return;
    $r = tao_entregas_api( "/entregas?card_id=eq.$card_id&select=id,pago&order=criado_em.desc&limit=1" );
    if ( empty( $r['ok'] ) || empty( $r['data'] ) ) return;
    $e = $r['data'][0];
    if ( empty( $e['pago'] ) ) {
        tao_entregas_api( "/entregas?id=eq.{$e['id']}", 'PATCH', [ 'pago' => true, 'pago_em' => gmdate( 'c' ) ] );
    }
}, 10, 1 );

// ── Aba "Entrega" dentro do card (via o hook de painéis do CRM) ────────────────
add_action( 'tao_crm_card_paineis', function ( $card ) {
    if ( ! is_array( $card ) || empty( $card['id'] ) || ! tao_entregas_can_access() ) return;
    $cid   = esc_attr( $card['id'] );
    $ws    = esc_attr( $card['workspace_id'] ?? '' );
    $cont  = esc_attr( $card['contato_id'] ?? '' );
    $nonce = wp_create_nonce( 'tao_entregas_nonce' );
    $ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
    ?>
    <div class="crm-itens-section" id="taoent-section" style="margin-top:10px"
         data-card="<?php echo $cid; ?>" data-ws="<?php echo $ws; ?>" data-contato="<?php echo $cont; ?>"
         data-nonce="<?php echo $nonce; ?>" data-ajax="<?php echo $ajax; ?>">
        <div class="crm-itens-header" style="display:flex;align-items:center;justify-content:space-between">
            <strong style="font-size:13px">&#x1F69A; Entrega</strong>
            <span id="taoent-msg" style="font-size:11px;color:#94a3b8"></span>
        </div>
        <div id="taoent-body" style="font-size:12px;color:#94a3b8;padding:4px 0">Carregando…</div>
    </div>
    <script>
    (function(){
        var $s=jQuery('#taoent-section'), ajax=$s.data('ajax'), nonce=$s.data('nonce');
        var card=$s.data('card'), ws=$s.data('ws'), contato=$s.data('contato');
        var TIPOS=<?php echo wp_json_encode( tao_entregas_tipos() ); ?>, FORMAS=[];
        var CAIXA_URL=<?php echo function_exists( 'tao_caixa_pode_operar' ) ? wp_json_encode( home_url( '/robos/caixa-vendas/' ) ) : 'null'; ?>;
        function esc(t){return jQuery('<span>').text(t==null?'':t).html();}
        function selForma(val){
            var o='<option value="">— forma —</option>'+FORMAS.map(function(f){return '<option value="'+esc(f.id)+'"'+(val===f.id?' selected':'')+'>'+esc(f.nome)+'</option>';}).join('');
            return '<div style="margin-bottom:6px"><label style="font-size:10px;color:#64748b;text-transform:uppercase;display:block">Forma pagto (Caixa)</label>'+
                '<select data-f="forma_pagamento_id" style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">'+o+'</select></div>';
        }
        function brl(n){return 'R$ '+parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
        function carregar(){
            jQuery.getJSON(ajax,{action:'tao_entregas_get',nonce:nonce,card_id:card},function(r){
                if(!r.success){jQuery('#taoent-body').html('<span style="color:#dc2626">'+esc(r.data&&r.data.message||'erro')+'</span>');return;}
                render(r.data);
            });
        }
        function inp(lbl,name,val,type){
            return '<div style="margin-bottom:6px"><label style="font-size:10px;color:#64748b;text-transform:uppercase;display:block">'+lbl+'</label>'+
                '<input type="'+(type||'text')+'" data-f="'+name+'" value="'+esc(val==null?'':val)+'" style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px"></div>';
        }
        function render(e){
            if(!e){
                jQuery('#taoent-body').html('<button type="button" class="button button-small" id="taoent-criar">+ Criar entrega</button>');
                jQuery('#taoent-criar').on('click',function(){ salvar({status:'pendente'}); });
                return;
            }
            var tipoOpts=Object.keys(TIPOS).map(function(k){return '<option value="'+k+'"'+(e.tipo===k?' selected':'')+'>'+esc(TIPOS[k])+'</option>';}).join('');
            var stOpts=['pendente','em_rota','entregue','nao_entregue'].map(function(s){return '<option value="'+s+'"'+(e.status===s?' selected':'')+'>'+s+'</option>';}).join('');
            var h='<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px">'+
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'+
                '<div style="margin-bottom:6px"><label style="font-size:10px;color:#64748b;text-transform:uppercase;display:block">Tipo</label><select data-f="tipo" style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">'+tipoOpts+'</select></div>'+
                '<div style="margin-bottom:6px"><label style="font-size:10px;color:#64748b;text-transform:uppercase;display:block">Status</label><select data-f="status" style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">'+stOpts+'</select></div>'+
                '</div>'+
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'+inp('Custo (frete)','custo',e.custo,'number')+inp('A receber','valor_receber',e.valor_receber,'number')+'</div>'+
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'+inp('Entregador','entregador',e.entregador)+selForma(e.forma_pagamento_id)+'</div>'+
                '<div style="border:1px solid #e2e8f0;border-radius:4px;padding:6px;margin-bottom:6px;background:#fff">'+
                '<div style="display:flex;gap:6px;margin-bottom:5px">'+
                '<select id="taoent-end-sel" style="flex:1;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px"><option value="">— escolher endereço salvo —</option></select>'+
                '<input type="text" id="taoent-cep" placeholder="CEP" maxlength="9" style="width:78px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">'+
                '</div>'+
                inp('Endereço','endereco',e.endereco)+
                '<div style="margin-top:4px"><button type="button" class="button button-small" id="taoent-end-salvar" style="font-size:11px">💾 Salvar endereço p/ o cliente</button> <span id="taoent-end-msg" style="font-size:11px;margin-left:4px"></span></div></div>'+
                inp('Rastreio','rastreio',e.rastreio)+
                inp('Obs','obs',e.obs)+
                '<label style="font-size:12px"><input type="checkbox" data-f="pago" '+(e.pago?'checked':'')+'> Pago</label>'+
                '<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center"><button type="button" class="button button-primary button-small" id="taoent-salvar">💾 Salvar</button>'+
                (CAIXA_URL?' <a class="button button-small" href="'+esc(CAIXA_URL)+'?card='+esc(card)+'" target="_blank" title="Abrir a baixa deste card no Caixa" style="font-size:11px">💰 Receber no Caixa</a>':'')+
                '</div>'+
                '</div>';
            jQuery('#taoent-body').html(h);
            // endereços de entrega do cliente (cadastro + adicionais)
            var _ends={};
            if(contato){
                jQuery.getJSON(ajax,{action:'tao_entregas_enderecos',nonce:nonce,contato_id:contato},function(r){
                    if(!r||!r.success)return;
                    (r.data||[]).forEach(function(en){ _ends[en.id]=en.texto; jQuery('#taoent-end-sel').append('<option value="'+esc(en.id)+'">'+esc(en.apelido)+': '+esc(en.texto)+'</option>'); });
                });
            }
            jQuery('#taoent-end-sel').on('change',function(){ var t=_ends[this.value]; if(t) jQuery('#taoent-body input[data-f=endereco]').val(t); });
            jQuery('#taoent-cep').on('blur',function(){
                var cep=String(this.value).replace(/\D/g,''); if(cep.length!==8)return;
                jQuery.getJSON('https://viacep.com.br/ws/'+cep+'/json/',function(d){
                    if(d&&!d.erro){ var t=[d.logradouro,d.bairro,(d.localidade||'')+(d.uf?'/'+d.uf:'')].filter(Boolean).join(', ')+', '+cep;
                        jQuery('#taoent-body input[data-f=endereco]').val(t); }
                });
            });
            jQuery('#taoent-end-salvar').on('click',function(){
                var end=jQuery('#taoent-body input[data-f=endereco]').val().trim();
                if(!end){jQuery('#taoent-end-msg').css('color','#dc2626').text('informe o endereço');return;}
                var ap=prompt('Apelido do endereço (ex: Casa, Trabalho):','Entrega'); if(ap===null)return;
                jQuery.post(ajax,{action:'tao_entregas_end_salvar',nonce:nonce,workspace_id:ws,contato_id:contato,apelido:ap,logradouro:end,cep:jQuery('#taoent-cep').val()},function(r){
                    if(r.success){jQuery('#taoent-end-msg').css('color','#16a34a').text('✓ salvo');jQuery('#taoent-end-sel').append('<option value="'+esc(r.data.id)+'">'+esc(ap)+': '+esc(end)+'</option>');_ends[r.data.id]=end;}
                    else jQuery('#taoent-end-msg').css('color','#dc2626').text((r.data&&r.data.message)||'erro');
                });
            });
            jQuery('#taoent-salvar').on('click',function(){
                var d={id:e.id};
                jQuery('#taoent-body [data-f]').each(function(){
                    var n=jQuery(this).data('f');
                    d[n]=this.type==='checkbox'?(this.checked?'1':'0'):jQuery(this).val();
                });
                salvar(d);
            });
        }
        function salvar(d){
            d.action='tao_entregas_save'; d.nonce=nonce; d.card_id=card; d.workspace_id=ws; d.contato_id=contato;
            jQuery('#taoent-msg').css('color','#64748b').text('salvando…');
            jQuery.post(ajax,d,function(r){
                if(r.success){jQuery('#taoent-msg').css('color','#16a34a').text('✓');carregar();}
                else jQuery('#taoent-msg').css('color','#dc2626').text((r.data&&r.data.message)||'erro');
            });
        }
        jQuery.getJSON(ajax,{action:'tao_entregas_formas',nonce:nonce},function(r){ if(r&&r.success) FORMAS=r.data||[]; carregar(); });
    })();
    </script>
    <?php
} );
