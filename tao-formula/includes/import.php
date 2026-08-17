<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Importação de catálogo por planilha .xlsx (alternativa ao FCerta).
 * Lê as abas Materias-Primas / Embalagens / Tipos-Capsula e grava no Supabase
 * com o MESMO upsert por 'codigo' preservando o id (mantém sinônimos/config)
 * + soft-deactivate dos ausentes (modo "completo").
 *
 * Tudo contido no módulo Fórmula (não toca em outras áreas).
 */

// ── Mapas de unidade (espelham sincronizar_tao.ps1 / importar_planilha.py) ───
function taof_imp_unid_map( $grupo ) {
    if ( $grupo === 'E' ) return [
        'UN'=>'un','CAP'=>'un','CAPS'=>'un','PC'=>'un','PAR'=>'un','KIT'=>'un','RL'=>'un','ENV'=>'un',
        'G'=>'g','MG'=>'mg','ML'=>'g','L'=>'g',
    ];
    return [
        'G'=>'g','GR'=>'g','KG'=>'g','L'=>'g','MG'=>'mg','MEQ'=>'mg',
        'MCG'=>'mcg','UG'=>'mcg','NG'=>'mcg','UI'=>'UI','IU'=>'UI','U'=>'UI',
        'UFC'=>'UFC','BLH'=>'BLH','ML'=>'g','UN'=>'un','CAP'=>'un','CAPS'=>'un',
    ];
}

// ── Parsing numérico tolerante (aceita vírgula ou ponto; vazio => null) ──────
function taof_imp_num( $v ) {
    if ( $v === null ) return null;
    $s = trim( (string) $v );
    if ( $s === '' ) return null;
    $s = str_replace( ' ', '', $s );
    if ( substr_count( $s, ',' ) && substr_count( $s, '.' ) ) {
        $s = str_replace( '.', '', $s );   // 1.234,56 -> remove separador de milhar
    }
    $s = str_replace( ',', '.', $s );
    return is_numeric( $s ) ? (float) $s : null;
}
function taof_imp_str( $v ) { return $v === null ? '' : trim( (string) $v ); }
function taof_imp_sn( $v ) {
    return in_array( strtoupper( taof_imp_str( $v ) ), [ 'S', 'SIM', 'TRUE', '1' ], true );
}
/** Normaliza texto p/ casamento robusto: sem acento, maiúsculas, só alfanumérico. */
function taof_imp_norm( $v ) {
    $s = taof_imp_str( $v );
    $t = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s );
    if ( $t !== false && $t !== '' ) $s = $t;
    return preg_replace( '/[^A-Z0-9]/', '', strtoupper( $s ) );
}

// ── Leitura de .xlsx via ZipArchive + SimpleXML (sem libs externas) ──────────
function taof_imp_xlsx_read( $path ) {
    if ( ! class_exists( 'ZipArchive' ) ) return null;
    $zip = new ZipArchive();
    if ( $zip->open( $path ) !== true ) return null;

    // shared strings
    $shared = [];
    $ss = $zip->getFromName( 'xl/sharedStrings.xml' );
    if ( $ss !== false ) {
        $x = @simplexml_load_string( $ss );
        if ( $x ) foreach ( $x->si as $si ) {
            $t = '';
            if ( isset( $si->t ) ) $t = (string) $si->t;
            else foreach ( $si->r as $r ) $t .= (string) $r->t;   // rich text
            $shared[] = $t;
        }
    }

    // mapa: nome da aba -> caminho do xml
    $sheets = [];
    $wb = @simplexml_load_string( $zip->getFromName( 'xl/workbook.xml' ) );
    $rl = @simplexml_load_string( $zip->getFromName( 'xl/_rels/workbook.xml.rels' ) );
    $relmap = [];
    if ( $rl ) foreach ( $rl->Relationship as $rel ) $relmap[ (string) $rel['Id'] ] = (string) $rel['Target'];
    if ( $wb ) {
        $ns = $wb->getNamespaces( true );
        $rns = $ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        foreach ( $wb->sheets->sheet as $sh ) {
            $name = (string) $sh['name'];
            $rid  = '';
            foreach ( $sh->attributes( $rns ) as $k => $v ) if ( $k === 'id' ) $rid = (string) $v;
            $tgt = $relmap[ $rid ] ?? '';
            if ( $tgt && strpos( $tgt, '/' ) !== 0 && strpos( $tgt, 'xl/' ) !== 0 ) $tgt = 'xl/' . $tgt;
            $sheets[ $name ] = ltrim( $tgt, '/' );
        }
    }

    $out = [];
    foreach ( $sheets as $name => $tgt ) {
        $xml = $tgt ? $zip->getFromName( $tgt ) : false;
        if ( $xml === false ) continue;
        $out[ $name ] = taof_imp_xlsx_rows( $xml, $shared );
    }
    $zip->close();
    return $out;
}

function taof_imp_cell_val( $c, $shared ) {
    $t = (string) ( $c['t'] ?? '' );
    if ( $t === 's' )        return $shared[ (int) $c->v ] ?? '';
    if ( $t === 'inlineStr' ) return isset( $c->is->t ) ? (string) $c->is->t : '';
    if ( isset( $c->v ) )    return (string) $c->v;
    return '';
}

function taof_imp_xlsx_rows( $xml, $shared ) {
    $x = @simplexml_load_string( $xml );
    if ( ! $x || ! isset( $x->sheetData ) ) return [];
    $raw = [];
    foreach ( $x->sheetData->row as $row ) {
        $cells = [];
        foreach ( $row->c as $c ) {
            $col = preg_replace( '/[0-9]+/', '', (string) $c['r'] );   // "B7" -> "B"
            $cells[ $col ] = taof_imp_cell_val( $c, $shared );
        }
        $raw[] = $cells;
    }
    if ( ! $raw ) return [];
    $hdr = [];
    foreach ( $raw[0] as $col => $val ) {
        $h = mb_strtolower( trim( $val ) );
        if ( $h !== '' ) $hdr[ $col ] = $h;
    }
    $data = [];
    for ( $i = 1; $i < count( $raw ); $i++ ) {
        $r = $raw[ $i ];
        $vazia = true;
        foreach ( $r as $v ) if ( trim( (string) $v ) !== '' ) { $vazia = false; break; }
        if ( $vazia ) continue;
        $assoc = [];
        foreach ( $hdr as $col => $name ) $assoc[ $name ] = $r[ $col ] ?? '';
        $data[] = $assoc;
    }
    return $data;
}

// ── Monta o registro do ativo (espelha Build-Ativo) ─────────────────────────
function taof_imp_build_ativo( $row, $grupo, $cliente_id, $agora ) {
    $unidade = taof_imp_str( $row['unidade'] ?? '' );
    $umap    = taof_imp_unid_map( $grupo );
    $up      = $umap[ strtoupper( $unidade ) ] ?? ( $grupo === 'E' ? 'un' : 'mg' );

    $dens = taof_imp_num( $row['densidade'] ?? '' );      $dens = ( $dens && $dens > 0 ) ? $dens : 1.0;
    $fcor = taof_imp_num( $row['fator_correcao'] ?? '' ); $fcor = ( $fcor && $fcor > 0 ) ? $fcor : 1.0;
    $dil  = taof_imp_num( $row['diluicao'] ?? '' );       $dil  = ( $dil  && $dil  > 0 ) ? $dil  : 1.0;
    $teor = taof_imp_num( $row['teor'] ?? '' );           $teor = ( $teor && $teor > 0 ) ? $teor : 100.0;
    $est  = taof_imp_num( $row['estoque_atual'] ?? '' );
    $pc   = taof_imp_num( $row['preco_custo'] ?? '' );
    $pv   = taof_imp_num( $row['preco_venda'] ?? '' );

    return [
        'cliente_id'        => $cliente_id,
        'codigo_fc'         => taof_imp_str( $row['codigo'] ?? '' ),
        'nome'              => taof_imp_str( $row['nome'] ?? '' ),
        'nome_original'     => taof_imp_str( $row['nome'] ?? '' ),
        'nome_alt'          => '',
        'grupo'             => $grupo,
        'unidade'           => $unidade,
        'unidade_padrao'    => $up,
        'estoque_atual'     => $est,
        'em_estoque'        => ( $est !== null && $est > 0 ),
        'preco_compra'      => taof_imp_num( $row['preco_compra'] ?? '' ),
        'preco_custo'       => $pc,
        'custo_por_unidade' => $pc !== null ? $pc : 0,
        'preco_venda'       => $pv !== null ? $pv : 0,
        'margem_padrao'     => null,
        'categoria'         => taof_imp_str( $row['categoria'] ?? '' ),
        'classe_terapeutica'=> ( taof_imp_str( $row['classe_terapeutica'] ?? '' ) ?: null ),
        'principio_ativo'   => taof_imp_str( $row['principio_ativo'] ?? '' ),
        'controlado'        => taof_imp_sn( $row['controlado'] ?? '' ),
        'densidade'         => $dens,
        'fator_correcao'    => $fcor,
        'fator_perda'       => 1.0,
        'diluicao'          => $dil,
        'teor'              => $teor,
        'excipiente_padrao' => null,
        'dcb'               => taof_imp_str( $row['dcb_sinonimos'] ?? '' ),
        'dose_min'          => taof_imp_num( $row['dose_min'] ?? '' ),
        'uni_dose_min'      => ( taof_imp_str( $row['uni_dose_min'] ?? '' ) ?: null ),
        'dose_minima_padrao'=> null,
        'dose_max'          => taof_imp_num( $row['dose_max'] ?? '' ),
        'uni_dose_max'      => ( taof_imp_str( $row['uni_dose_max'] ?? '' ) ?: null ),
        'dose_maxima_padrao'=> null,
        'observacoes'       => taof_imp_str( $row['observacoes'] ?? '' ),
        'concentracao'      => taof_imp_num( $row['concentracao_ui_ufc'] ?? '' ),
        'ativo'             => true,
        'sincronizado_em'   => $agora,
        'atualizado_em'     => $agora,
    ];
}

// ── HTTP Supabase (com Prefer customizável; não altera tao_formula_api) ──────
function taof_imp_sb( $method, $path, $body = null, $prefer = null ) {
    $url = rtrim( tao_formula_supabase_url(), '/' ) . '/rest/v1' . $path;
    $key = tao_formula_supabase_key();
    $headers = [ 'apikey' => $key, 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ];
    if ( $prefer ) $headers['Prefer'] = $prefer;
    $args = [ 'method' => $method, 'timeout' => 60, 'headers' => $headers ];
    if ( $body !== null ) $args['body'] = wp_json_encode( $body );
    $resp = wp_remote_request( $url, $args );
    if ( is_wp_error( $resp ) ) return [ 'ok' => false, 'code' => 0, 'raw' => $resp->get_error_message(), 'data' => [] ];
    $code = wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    return [ 'ok' => $code >= 200 && $code < 300, 'code' => $code, 'raw' => $raw,
             'data' => json_decode( $raw, true ), 'cr' => wp_remote_retrieve_header( $resp, 'content-range' ) ];
}

// mapa codigo_fc -> id dos ativos existentes (paginado)
function taof_imp_mapa_existentes( $cliente_id ) {
    $mapa = []; $off = 0; $pg = 1000;
    while ( true ) {
        $r = taof_imp_sb( 'GET', "/ativos?cliente_id=eq.$cliente_id&select=id,codigo_fc&limit=$pg&offset=$off" );
        $d = ( $r['ok'] && is_array( $r['data'] ) ) ? $r['data'] : [];
        foreach ( $d as $a ) if ( ! empty( $a['codigo_fc'] ) ) $mapa[ (string) $a['codigo_fc'] ] = $a['id'];
        if ( count( $d ) < $pg ) break;
        $off += $pg;
    }
    return $mapa;
}

// ── Handler AJAX ─────────────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_formula_importar_planilha', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_is_master() ) wp_send_json_error( 'Apenas o administrador pode importar o catálogo.', 403 );

    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado.', 400 );

    if ( empty( $_FILES['arquivo']['tmp_name'] ) ) wp_send_json_error( 'Envie um arquivo .xlsx.', 400 );
    $f = $_FILES['arquivo'];
    if ( (int) $f['error'] !== UPLOAD_ERR_OK ) wp_send_json_error( 'Falha no upload (código ' . (int) $f['error'] . ').', 400 );
    if ( strtolower( pathinfo( $f['name'], PATHINFO_EXTENSION ) ) !== 'xlsx' ) wp_send_json_error( 'Formato inválido: envie um arquivo .xlsx.', 400 );

    $dry  = ! empty( $_POST['dry'] );
    $modo = ( ( $_POST['modo'] ?? 'full' ) === 'incremental' ) ? 'incremental' : 'full';

    $sheets = taof_imp_xlsx_read( $f['tmp_name'] );
    if ( $sheets === null ) wp_send_json_error( 'Não consegui ler o .xlsx (arquivo inválido ou corrompido).', 400 );

    $agora  = gmdate( 'c' );
    $ativos = []; $erros = [];
    foreach ( [ [ 'Materias-Primas', 'M' ], [ 'Embalagens', 'E' ] ] as $sg ) {
        list( $aba, $grupo ) = $sg;
        $ln = 1;
        foreach ( ( $sheets[ $aba ] ?? [] ) as $row ) {
            $ln++;
            $cod = taof_imp_str( $row['codigo'] ?? '' );
            $nm  = taof_imp_str( $row['nome'] ?? '' );
            $un  = taof_imp_str( $row['unidade'] ?? '' );
            $falta = [];
            if ( $cod === '' ) $falta[] = 'codigo';
            if ( $nm  === '' ) $falta[] = 'nome';
            if ( $un  === '' ) $falta[] = 'unidade';
            if ( $falta ) { $erros[] = "$aba linha $ln: faltando " . implode( ', ', $falta ); continue; }
            $ativos[] = taof_imp_build_ativo( $row, $grupo, $cliente_id, $agora );
        }
    }

    $caps = []; $ln = 1;
    foreach ( ( $sheets['Tipos-Capsula'] ?? [] ) as $row ) {
        $ln++;
        $tipo = taof_imp_str( $row['tipo'] ?? '' );
        $num  = taof_imp_str( $row['numero'] ?? '' );
        $vol  = taof_imp_num( $row['vol_ul'] ?? '' );
        if ( $tipo === '' || $num === '' || $vol === null ) { $erros[] = "Tipos-Capsula linha $ln: tipo/numero/vol_ul obrigatórios"; continue; }
        $caps[] = [
            'cliente_id' => $cliente_id, 'tipo' => $tipo, 'numero' => $num, 'vol_ul' => $vol,
            'peso_vazio_mg' => taof_imp_num( $row['peso_vazio_mg'] ?? '' ),
            'cdpro_fc' => ( taof_imp_str( $row['codigo'] ?? '' ) ?: null ),
            'ativo' => true, 'sincronizado_em' => $agora,
        ];
    }

    // códigos duplicados na planilha (quebrariam a chave)
    $cods = array_column( $ativos, 'codigo_fc' );
    $dups = array_values( array_unique( array_diff_assoc( $cods, array_unique( $cods ) ) ) );
    if ( $dups ) $erros[] = 'códigos duplicados na planilha: ' . implode( ', ', array_slice( $dups, 0, 10 ) );

    $n_mp  = count( array_filter( $ativos, fn( $a ) => $a['grupo'] === 'M' ) );
    $n_emb = count( $ativos ) - $n_mp;

    // mapa de existentes -> classifica novos x atualizar
    $mapa = taof_imp_mapa_existentes( $cliente_id );
    $novos = $atual = 0;
    foreach ( $ativos as &$a ) {
        $c = (string) $a['codigo_fc'];
        if ( isset( $mapa[ $c ] ) ) { $a['id'] = $mapa[ $c ]; $atual++; }
        else                        { $a['id'] = wp_generate_uuid4(); $novos++; }
    }
    unset( $a );

    $resumo = [
        'mp' => $n_mp, 'emb' => $n_emb, 'caps' => count( $caps ),
        'atualizar' => $atual, 'inserir' => $novos, 'modo' => $modo,
        'erros' => $erros,
    ];

    if ( $dry ) { wp_send_json_success( array_merge( $resumo, [ 'dry' => true ] ) ); }
    if ( $erros ) { wp_send_json_error( [ 'message' => 'Corrija os erros antes de importar.', 'erros' => $erros ], 422 ); }

    // ── Commit: upsert por id (preserva sinônimos/config) ──
    $env = 0; $ok = true; $detalhe = '';
    foreach ( array_chunk( $ativos, 200 ) as $lote ) {
        $r = taof_imp_sb( 'POST', '/ativos?on_conflict=id', $lote, 'resolution=merge-duplicates,return=minimal' );
        if ( $r['ok'] ) { $env += count( $lote ); }
        else { $ok = false; $detalhe = $r['raw']; break; }
    }
    if ( ! $ok ) wp_send_json_error( [ 'message' => 'Erro ao gravar ativos: ' . $detalhe, 'enviados' => $env ], 500 );

    // soft-deactivate dos ausentes (só no modo completo)
    $desativados = 0;
    if ( $modo === 'full' && $ativos ) {
        $rd = taof_imp_sb( 'PATCH', "/ativos?cliente_id=eq.$cliente_id&sincronizado_em=lt." . rawurlencode( $agora ),
            [ 'ativo' => false, 'em_estoque' => false ], 'count=exact,return=minimal' );
        if ( $rd['ok'] && ! empty( $rd['cr'] ) && preg_match( '#/(\d+)$#', $rd['cr'], $m ) ) $desativados = (int) $m[1];
    }

    // cápsulas — casa por chave normalizada (sem acento/maiúsculas) preservando o id,
    // evitando duplicar por variação de grafia (ex.: "Entérica" vs "ENTÉRICA").
    $caps_ok = true;
    if ( $caps ) {
        $capmap = [];
        $rcg = taof_imp_sb( 'GET', "/tipos_capsula?cliente_id=eq.$cliente_id&select=id,tipo,numero&limit=5000" );
        if ( $rcg['ok'] && is_array( $rcg['data'] ) ) {
            foreach ( $rcg['data'] as $c ) {
                $capmap[ taof_imp_norm( $c['tipo'] ) . '|' . trim( (string) $c['numero'] ) ] = $c['id'];
            }
        }
        foreach ( $caps as &$c ) {
            $k = taof_imp_norm( $c['tipo'] ) . '|' . trim( (string) $c['numero'] );
            $c['id'] = $capmap[ $k ] ?? wp_generate_uuid4();
        }
        unset( $c );
        $rc = taof_imp_sb( 'POST', '/tipos_capsula?on_conflict=id', $caps, 'resolution=merge-duplicates,return=minimal' );
        $caps_ok = $rc['ok'];
    }

    wp_send_json_success( array_merge( $resumo, [
        'gravado' => true, 'enviados' => $env, 'desativados' => $desativados, 'caps_ok' => $caps_ok,
    ] ) );
} );
