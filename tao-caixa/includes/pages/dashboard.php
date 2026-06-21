<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_page_dashboard() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid = tao_caixa_cliente_id();
    ?>
    <div class="wrap taoc-wrap">
        <h1>&#x1F4B0; TAO Caixa</h1>
        <p style="color:#64748b;font-size:14px;margin:6px 0 18px">Módulo financeiro da TAO Suite &mdash; em construção (Fase 1).</p>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado. Verifique as configurações do TAO Neo.</p></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px">
            <a href="<?php echo esc_url( tao_caixa_url( 'caixa-adquirentes' ) ); ?>" class="taoc-card" style="display:block;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:18px;text-decoration:none;color:#1e293b">
                <div style="font-size:22px">&#x1F3E6;</div>
                <strong style="display:block;margin-top:6px">Adquirentes</strong>
                <span style="font-size:12px;color:#64748b">Operadoras de cartão (Cielo, Rede…) e taxa de antecipação</span>
            </a>
            <div class="taoc-card" style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:18px;color:#94a3b8">
                <div style="font-size:22px">&#x1F4CA;</div>
                <strong style="display:block;margin-top:6px">Tabela de Taxas (MDR)</strong>
                <span style="font-size:12px">Em breve</span>
            </div>
            <div class="taoc-card" style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:18px;color:#94a3b8">
                <div style="font-size:22px">&#x1F4B3;</div>
                <strong style="display:block;margin-top:6px">Formas de Pagamento</strong>
                <span style="font-size:12px">Em breve</span>
            </div>
        </div>
    </div>
    <?php
}
