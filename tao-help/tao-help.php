<?php
/**
 * Plugin Name: TAO Help (Ajuda)
 * Description: Central de Ajuda / Base de Conhecimento do TAO Neo, no portal /robos/ajuda. Renderiza os .md de tao-help/kb.
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAOH_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAOH_KB_URL', plugins_url( 'kb/', __FILE__ ) );  // base p/ imagens (![alt](img/...))

/* ─── Manifesto da KB (sidebar). Cada grupo: [ label, admin_only, itens[[slug,titulo]] ].
 * slug = caminho relativo em kb/ sem .md. Só o que está aqui é acessível (whitelist).
 * admin_only = true → visível apenas para admin (manage_options); docs internos. */
function tao_help_manifest() {
    return [
        [ 'Começe aqui', false, [ [ 'INDEX', 'Visão geral' ] ] ],
        [ 'O sistema',   false, [ [ 'arquitetura/mer', 'Visão do sistema (modelo de dados)' ] ] ],
        [ 'Módulos',     false, [ [ 'modulos/cotacoes', 'Cotações (compras)' ] ] ],
        // ↓ internos — só admin
        [ 'Comercial (interno)', true, [
            [ 'comercial/comparativo-texs', 'TAO Neo × TEXs' ],
            [ 'comercial/precificacao', 'Precificação' ],
        ] ],
        [ 'Segurança (interno)', true, [ [ 'seguranca/supabase', 'Supabase / RLS / LGPD' ] ] ],
        [ 'Roadmap (interno)',   true, [ [ 'roadmap/gaps', 'Gaps vs concorrência' ] ] ],
    ];
}

/* grupos visíveis ao usuário atual (respeita admin_only) */
function tao_help_grupos_visiveis() {
    $adm = current_user_can( 'manage_options' );
    $out = [];
    foreach ( tao_help_manifest() as $g ) { if ( $g[1] && ! $adm ) continue; $out[] = $g; }
    return $out;
}

/* slug -> título (só o que o usuário pode ver — vira a whitelist) */
function tao_help_index() {
    $ix = [];
    foreach ( tao_help_grupos_visiveis() as $g ) foreach ( $g[2] as $it ) $ix[ $it[0] ] = $it[1];
    return $ix;
}

/* ─── Markdown → HTML (parser compacto: headings, listas, tabelas, código,
 *     citações, hr, negrito/itálico/código/links). Suficiente para a KB. ─── */
function tao_help_md( $md ) {
    $md = str_replace( "\r\n", "\n", (string) $md );
    $lines = explode( "\n", $md );
    $html = '';
    $n = count( $lines );
    $i = 0;
    $inline = function ( $t ) {
        $t = esc_html( $t );
        $t = preg_replace_callback( '/`([^`]+)`/', fn( $m ) => '<code>' . $m[1] . '</code>', $t );
        // imagem: ![alt](src)  — src relativo a kb/ (ou URL absoluta)
        $t = preg_replace_callback( '/!\[([^\]]*)\]\(([^)]+)\)/', function ( $m ) {
            $src = $m[2];
            if ( ! preg_match( '#^https?://#', $src ) ) $src = TAOH_KB_URL . ltrim( $src, '/' );
            return '<img class="taoh-img" src="' . esc_attr( $src ) . '" alt="' . esc_attr( $m[1] ) . '" loading="lazy">';
        }, $t );
        $t = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t );
        $t = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $t );
        $t = preg_replace_callback( '/\[([^\]]+)\]\(([^)]+)\)/', function ( $m ) {
            $u = $m[2];
            $ext = preg_match( '#^https?://#', $u ) ? ' target="_blank" rel="noopener"' : '';
            return '<a href="' . esc_attr( $u ) . '"' . $ext . '>' . $m[1] . '</a>';
        }, $t );
        return $t;
    };
    while ( $i < $n ) {
        $l = $lines[ $i ];
        // fenced code
        if ( preg_match( '/^```/', $l ) ) {
            $buf = ''; $i++;
            while ( $i < $n && ! preg_match( '/^```/', $lines[ $i ] ) ) { $buf .= esc_html( $lines[ $i ] ) . "\n"; $i++; }
            $i++; $html .= '<pre class="taoh-code"><code>' . $buf . '</code></pre>'; continue;
        }
        // heading
        if ( preg_match( '/^(#{1,6})\s+(.*)$/', $l, $m ) ) {
            $lv = strlen( $m[1] ); $html .= "<h$lv>" . $inline( $m[2] ) . "</h$lv>"; $i++; continue;
        }
        // hr
        if ( preg_match( '/^(-{3,}|\*{3,})\s*$/', $l ) ) { $html .= '<hr>'; $i++; continue; }
        // tabela (linha com | e próxima de separador ---|---)
        if ( strpos( $l, '|' ) !== false && $i + 1 < $n && preg_match( '/^\s*\|?[\s:|-]+\|[\s:|-]+$/', $lines[ $i + 1 ] ) ) {
            $split = function ( $row ) {
                $row = trim( $row ); $row = preg_replace( '/^\||\|$/', '', $row );
                return array_map( 'trim', explode( '|', $row ) );
            };
            $head = $split( $l ); $i += 2;
            $html .= '<div class="taoh-tw"><table class="taoh-table"><thead><tr>';
            foreach ( $head as $c ) $html .= '<th>' . $inline( $c ) . '</th>';
            $html .= '</tr></thead><tbody>';
            while ( $i < $n && strpos( $lines[ $i ], '|' ) !== false && trim( $lines[ $i ] ) !== '' ) {
                $cells = $split( $lines[ $i ] );
                $html .= '<tr>';
                foreach ( $cells as $c ) $html .= '<td>' . $inline( $c ) . '</td>';
                $html .= '</tr>'; $i++;
            }
            $html .= '</tbody></table></div>'; continue;
        }
        // blockquote
        if ( preg_match( '/^>\s?(.*)$/', $l, $m ) ) {
            $buf = $inline( $m[1] ); $i++;
            while ( $i < $n && preg_match( '/^>\s?(.*)$/', $lines[ $i ], $mm ) ) { $buf .= '<br>' . $inline( $mm[1] ); $i++; }
            $html .= '<blockquote>' . $buf . '</blockquote>'; continue;
        }
        // lista (ul/ol)
        if ( preg_match( '/^\s*([-*]|\d+\.)\s+/', $l ) ) {
            $ol = (bool) preg_match( '/^\s*\d+\./', $l );
            $tag = $ol ? 'ol' : 'ul';
            $html .= "<$tag>";
            while ( $i < $n && preg_match( '/^\s*([-*]|\d+\.)\s+(.*)$/', $lines[ $i ], $m ) ) {
                $html .= '<li>' . $inline( $m[2] ) . '</li>'; $i++;
            }
            $html .= "</$tag>"; continue;
        }
        // linha em branco
        if ( trim( $l ) === '' ) { $i++; continue; }
        // parágrafo (junta linhas até vazio)
        $buf = $inline( $l ); $i++;
        while ( $i < $n && trim( $lines[ $i ] ) !== '' && ! preg_match( '/^(#{1,6}\s|```|>\s?|\s*([-*]|\d+\.)\s|-{3,}\s*$)/', $lines[ $i ] ) && strpos( $lines[ $i ], '|' ) === false ) {
            $buf .= ' ' . $inline( $lines[ $i ] ); $i++;
        }
        $html .= '<p>' . $buf . '</p>';
    }
    return $html;
}

/* lê um doc da KB pelo slug (validado no manifesto) */
function tao_help_read( $slug ) {
    $ix = tao_help_index();
    if ( ! isset( $ix[ $slug ] ) ) return null;
    $path = TAOH_DIR . 'kb/' . $slug . '.md';
    // trava anti path traversal (o slug já é whitelisted, mas garante)
    $real = realpath( $path );
    $base = realpath( TAOH_DIR . 'kb' );
    if ( ! $real || ! $base || strpos( $real, $base ) !== 0 || ! is_file( $real ) ) return null;
    return file_get_contents( $real );
}

/* ─── Página da Ajuda (usada pelo portal /robos/ajuda e pelo wp-admin) ─── */
function tao_help_page() {
    $ix   = tao_help_index();
    $slug = sanitize_text_field( $_GET['doc'] ?? 'INDEX' );
    if ( ! isset( $ix[ $slug ] ) ) $slug = 'INDEX';
    $md   = tao_help_read( $slug );
    $body = $md !== null ? tao_help_md( $md ) : '<p>Documento não encontrado.</p>';
    $url  = function ( $s ) {
        if ( function_exists( 'cbpm_url' ) ) return esc_url( cbpm_url( 'ajuda', [ 'doc' => $s ] ) );
        return esc_url( add_query_arg( [ 'page' => 'tao-ajuda', 'doc' => $s ], admin_url( 'admin.php' ) ) );
    };
    ?>
    <style>
    .taoh{display:flex;gap:22px;max-width:1160px;align-items:flex-start}
    .taoh-side{flex:0 0 250px;position:sticky;top:12px;font-size:13.5px}
    .taoh-side .grp{color:#94a3b8;text-transform:uppercase;font-size:11px;letter-spacing:.04em;margin:14px 0 4px}
    .taoh-side a{display:block;padding:6px 10px;border-radius:7px;color:#334155;text-decoration:none}
    .taoh-side a:hover{background:#f1f5f9}
    .taoh-side a.on{background:#152C42;color:#fff}
    .taoh-main{flex:1 1 auto;min-width:0;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:26px 30px}
    .taoh-main h1{font-size:26px;margin:0 0 12px}.taoh-main h2{font-size:20px;margin:26px 0 8px;border-bottom:1px solid #eef2f7;padding-bottom:4px}
    .taoh-main h3{font-size:16px;margin:18px 0 6px}
    .taoh-main p,.taoh-main li{font-size:14.5px;line-height:1.65;color:#334155}
    .taoh-main code{background:#eef2f7;padding:1px 6px;border-radius:5px;font-size:13px;font-family:Consolas,Menlo,monospace}
    .taoh-main pre.taoh-code{background:#0f172a;color:#e2e8f0;padding:14px 16px;border-radius:8px;overflow:auto;font-size:13px}
    .taoh-main pre.taoh-code code{background:none;color:inherit;padding:0}
    .taoh-main blockquote{border-left:4px solid #cbd5e1;margin:12px 0;padding:6px 14px;color:#475569;background:#f8fafc;border-radius:0 8px 8px 0}
    .taoh-tw{overflow-x:auto}.taoh-table{border-collapse:collapse;width:100%;font-size:13.5px;margin:12px 0}
    .taoh-table th,.taoh-table td{border:1px solid #e2e8f0;padding:7px 10px;text-align:left}
    .taoh-table th{background:#f8fafc}
    .taoh-main hr{border:0;border-top:1px solid #eef2f7;margin:22px 0}
    .taoh-main a{color:#2563eb}
    .taoh-main img.taoh-img{max-width:100%;border:1px solid #e2e8f0;border-radius:8px;margin:10px 0;display:block;box-shadow:0 2px 10px rgba(0,0,0,.07);cursor:zoom-in}
    #taoh-lb{display:none;position:fixed;inset:0;z-index:99999;background:rgba(6,10,16,.92);align-items:center;justify-content:center;padding:24px;cursor:zoom-out}
    #taoh-lb img{max-width:96%;max-height:94%;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5)}
    @media(max-width:820px){.taoh{flex-direction:column}.taoh-side{position:static;flex-basis:auto;width:100%}}
    </style>
    <div class="taoh">
        <nav class="taoh-side">
            <?php foreach ( tao_help_grupos_visiveis() as $g ) : ?>
                <div class="grp"><?php echo esc_html( $g[0] ); ?></div>
                <?php foreach ( $g[2] as $it ) : ?>
                    <a class="<?php echo $slug === $it[0] ? 'on' : ''; ?>" href="<?php echo $url( $it[0] ); ?>"><?php echo esc_html( $it[1] ); ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <article class="taoh-main"><?php echo $body; // já sanitizado no parser ?></article>
    </div>
    <div id="taoh-lb"><img src="" alt=""></div>
    <script>(function(){var lb=document.getElementById('taoh-lb'),im=lb.querySelector('img');
        document.querySelectorAll('.taoh-main img.taoh-img').forEach(function(g){g.addEventListener('click',function(){im.src=g.src;lb.style.display='flex';});});
        lb.addEventListener('click',function(){lb.style.display='none';im.src='';});})();</script>
    <?php
}

/* Menu wp-admin (fallback; o principal é o portal /robos/ajuda) */
add_action( 'admin_menu', function () {
    add_menu_page( 'Ajuda', 'Ajuda TAO', 'read', 'tao-ajuda', 'tao_help_page', 'dashicons-editor-help', 59 );
} );
