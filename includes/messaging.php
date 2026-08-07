<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Camada de mensageria multi-provider (Bloco 2).
 * O resto do TAO Neo envia mensagem sem saber o provedor: tao_messaging_for($instancia).
 * - EvolutionAdapter: embrulha tao_crm_evolution_send* (comportamento IDÊNTICO ao atual).
 * - MetaAdapter: Meta Cloud API (Graph). Regra da janela 24h é do domínio, só p/ meta_cloud.
 * Retorno padrão dos métodos: ['ok'=>bool,'external_id'=>?, 'codigo'=>?, 'error'=>?].
 * (Optamos por array de resultado — consistente com tao_crm_evolution_send —; a "janela
 *  expirada" vem como codigo='janela_expirada', o equivalente ao JanelaExpiradaError.)
 */

interface Tao_Messaging_Provider {
    public function sendText( array $conversa, string $texto ): array;
    public function sendTemplate( array $conversa, $template, array $variaveis = [] ): array;
    public function sendMedia( array $conversa, string $tipo, string $url_ou_arquivo, string $caption = '' ): array;
    public function markAsRead( array $mensagem ): array;
    public function getProviderName(): string;
}

// ── Config Meta (segredos em wp_options; ids públicos na instância) ────────────
function tao_meta_api_base()      { return rtrim( get_option( 'tao_crm_meta_api_base', 'https://graph.facebook.com' ), '/' ); }
function tao_meta_token( $inst )  { return get_option( 'tao_crm_meta_token_' . ( $inst['id'] ?? '' ), '' ) ?: get_option( 'tao_crm_meta_token', '' ); }
function tao_meta_graph_ver($inst){ return $inst['meta_graph_version'] ?? get_option( 'tao_crm_meta_graph_version', 'v26.0' ); }

// ── Fábrica: escolhe o adapter pelo provider da instância (permite híbrido) ─────
function tao_messaging_for( array $instancia ): Tao_Messaging_Provider {
    $prov = $instancia['provider'] ?? 'evolution';
    if ( $prov === 'meta_cloud' ) return new Tao_Meta_Adapter( $instancia );
    return new Tao_Evolution_Adapter( $instancia );
}
// Conveniência: adapter a partir de um card (conversa) — carrega a instância.
function tao_messaging_for_card( array $card ): ?Tao_Messaging_Provider {
    $iid = $card['instancia_id'] ?? '';
    if ( ! $iid ) return null;
    $r = tao_crm_api( "/crm_instancias?id=eq.$iid&limit=1" );
    if ( empty( $r['ok'] ) || empty( $r['data'] ) ) return null;
    return tao_messaging_for( $r['data'][0] );
}

// ── Adapter Evolution — embrulha as funções atuais (NÃO muda comportamento) ────
class Tao_Evolution_Adapter implements Tao_Messaging_Provider {
    private $inst;
    public function __construct( array $instancia ) { $this->inst = $instancia; }
    public function getProviderName(): string { return 'evolution'; }

    private function numero( array $conversa ) {
        return $conversa['contato_whatsapp'] ?? ( $conversa['numero'] ?? '' );
    }
    public function sendText( array $conversa, string $texto ): array {
        // Evolution NÃO tem janela de 24h — a regra é ignorada aqui (é do domínio, por provider).
        $r = tao_crm_evolution_send_with_retry( $this->inst, $this->numero( $conversa ), $texto );
        return [ 'ok' => ! empty( $r['ok'] ), 'external_id' => null, 'error' => $r['error'] ?? null ];
    }
    public function sendTemplate( array $conversa, $template, array $variaveis = [] ): array {
        // Evolution não tem template aprovado: renderiza o corpo com as variáveis e manda como texto.
        $corpo = is_array( $template ) ? ( $template['corpo'] ?? '' ) : (string) $template;
        foreach ( $variaveis as $i => $v ) $corpo = str_replace( '{{' . ( $i + 1 ) . '}}', (string) $v, $corpo );
        return $this->sendText( $conversa, $corpo );
    }
    public function sendMedia( array $conversa, string $tipo, string $url_ou_arquivo, string $caption = '' ): array {
        $mime = $tipo === 'image' ? 'image/jpeg' : ( $tipo === 'document' ? 'application/pdf' : 'application/octet-stream' );
        $r = tao_crm_evolution_send_media( $this->inst, $this->numero( $conversa ), $url_ou_arquivo, $mime, 'arquivo', $caption );
        return [ 'ok' => ! empty( $r['ok'] ), 'external_id' => null, 'error' => $r['error'] ?? null ];
    }
    public function markAsRead( array $mensagem ): array { return [ 'ok' => true ]; } // no-op
}

// ── Adapter Meta Cloud API (Graph) ────────────────────────────────────────────
class Tao_Meta_Adapter implements Tao_Messaging_Provider {
    private $inst;
    public function __construct( array $instancia ) { $this->inst = $instancia; }
    public function getProviderName(): string { return 'meta_cloud'; }

    private function endpoint(): string {
        $pnid = $this->inst['meta_phone_number_id'] ?? '';
        return tao_meta_api_base() . '/' . tao_meta_graph_ver( $this->inst ) . '/' . $pnid . '/messages';
    }
    private function numero( array $conversa ) {
        return tao_crm_e164_digits( $conversa['contato_whatsapp'] ?? ( $conversa['numero'] ?? '' ) );
    }
    private function janela_aberta( array $conversa ): bool {
        $exp = $conversa['janela_expira_em'] ?? '';
        if ( ! $exp ) return false;
        return strtotime( $exp ) > time();
    }

    // POST com retry só p/ transitórios (5xx / rate limit).
    private function post( array $payload ): array {
        $token = tao_meta_token( $this->inst );
        if ( ! $token ) return [ 'ok' => false, 'codigo' => 'sem_token', 'error' => 'Token da Meta não configurado' ];
        $url   = $this->endpoint();
        $delays = [ 0, 2, 4 ];
        $last   = null;
        foreach ( $delays as $i => $d ) {
            if ( $d ) sleep( $d );
            $resp = wp_remote_post( $url, [
                'timeout' => 20,
                'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( array_merge( [ 'messaging_product' => 'whatsapp' ], $payload ) ),
            ] );
            if ( is_wp_error( $resp ) ) { $last = [ 'ok' => false, 'codigo' => 'rede', 'error' => $resp->get_error_message() ]; continue; }
            $code = wp_remote_retrieve_response_code( $resp );
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( $code >= 200 && $code < 300 ) {
                return [ 'ok' => true, 'external_id' => $body['messages'][0]['id'] ?? null ];
            }
            $err  = $body['error'] ?? [];
            $map  = $this->mapear_erro( (int) ( $err['code'] ?? 0 ), $code, $err );
            $last = [ 'ok' => false, 'codigo' => $map['codigo'], 'error' => $map['msg'], 'erro_codigo' => (string) ( $err['code'] ?? $code ), 'erro_detalhe' => $err['message'] ?? '' ];
            if ( ! $map['transitorio'] ) break;   // permanente: não retenta
        }
        return $last ?? [ 'ok' => false, 'codigo' => 'desconhecido', 'error' => 'Falha desconhecida' ];
    }

    // Mapeia códigos comuns da Graph API p/ erro de domínio em pt-BR.
    private function mapear_erro( int $graph, int $http, array $err ): array {
        $t = [ 'transitorio' => true ];  $p = [ 'transitorio' => false ];
        switch ( $graph ) {
            case 190:    return $p + [ 'codigo' => 'token_invalido',   'msg' => 'Token da Meta inválido ou expirado — renove o acesso.' ];
            case 131026: return $p + [ 'codigo' => 'destino_invalido', 'msg' => 'Número de destino inválido ou sem WhatsApp.' ];
            case 131047: return $p + [ 'codigo' => 'janela_expirada',  'msg' => 'Janela de 24h expirada — use um template aprovado para reabrir a conversa.' ];
            case 132001: return $p + [ 'codigo' => 'template_reprovado','msg' => 'Template não aprovado/indisponível na Meta.' ];
            case 130429: case 80007:
                         return $t + [ 'codigo' => 'rate_limit',       'msg' => 'Limite de envio atingido — tentando novamente.' ];
        }
        if ( $http >= 500 ) return $t + [ 'codigo' => 'meta_5xx', 'msg' => 'Instabilidade na Meta — tentando novamente.' ];
        return $p + [ 'codigo' => 'graph_' . ( $err['code'] ?? $http ), 'msg' => $err['message'] ?? ( 'Erro Graph HTTP ' . $http ) ];
    }

    public function sendText( array $conversa, string $texto ): array {
        // REGRA DE DOMÍNIO: texto livre só dentro da janela de 24h.
        if ( ! $this->janela_aberta( $conversa ) ) {
            return [ 'ok' => false, 'codigo' => 'janela_expirada',
                     'error' => 'Janela de 24h expirada — envie um template aprovado para reabrir a conversa.' ];
        }
        return $this->post( [ 'to' => $this->numero( $conversa ), 'type' => 'text', 'text' => [ 'body' => $texto ] ] );
    }

    public function sendTemplate( array $conversa, $template, array $variaveis = [] ): array {
        $nome   = is_array( $template ) ? ( $template['nome'] ?? '' ) : (string) $template;
        $idioma = is_array( $template ) ? ( $template['idioma'] ?? 'pt_BR' ) : 'pt_BR';
        $tpl = [ 'name' => $nome, 'language' => [ 'code' => $idioma ] ];
        if ( $variaveis ) {
            $tpl['components'] = [ [ 'type' => 'body',
                'parameters' => array_map( fn( $v ) => [ 'type' => 'text', 'text' => (string) $v ], array_values( $variaveis ) ) ] ];
        }
        return $this->post( [ 'to' => $this->numero( $conversa ), 'type' => 'template', 'template' => $tpl ] );
    }

    public function sendMedia( array $conversa, string $tipo, string $url_ou_arquivo, string $caption = '' ): array {
        if ( ! $this->janela_aberta( $conversa ) ) {
            return [ 'ok' => false, 'codigo' => 'janela_expirada', 'error' => 'Janela de 24h expirada — use um template.' ];
        }
        $tipo = in_array( $tipo, [ 'image', 'document', 'video', 'audio' ], true ) ? $tipo : 'document';
        $obj  = [ 'link' => $url_ou_arquivo ];
        if ( $caption && in_array( $tipo, [ 'image', 'document', 'video' ], true ) ) $obj['caption'] = $caption;
        return $this->post( [ 'to' => $this->numero( $conversa ), 'type' => $tipo, $tipo => $obj ] );
    }

    public function markAsRead( array $mensagem ): array {
        $wamid = $mensagem['wamid'] ?? ( $mensagem['external_id'] ?? '' );
        if ( ! $wamid ) return [ 'ok' => true ];
        return $this->post( [ 'status' => 'read', 'message_id' => $wamid ] );
    }
}
