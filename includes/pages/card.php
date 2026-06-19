<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_crm_page_card() {
    $card_id = sanitize_text_field( $_GET['id'] ?? '' );
    if ( ! $card_id ) { echo '<p>Card inválido.</p>'; return; }

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) { echo '<p>Card não encontrado.</p>'; return; }
    $card = $rc['data'][0];

    // Pós Vendas: verifica se este card veio de um handoff de cliente com pedido em andamento
    $card_meta      = json_decode( $card['meta'] ?? '{}', true ) ?: [];
    $pv_card_id     = $card_meta['pos_vendas_card_id'] ?? null;
    $pv_card_info   = null;
    if ( $pv_card_id ) {
        $rpv = tao_crm_api( "/crm_cards?id=eq.$pv_card_id&select=id,titulo,estagio_id,pipeline_id&limit=1" );
        if ( $rpv['ok'] && ! empty( $rpv['data'] ) ) {
            $pv_card_info = $rpv['data'][0];
            // Busca nome do estágio
            $rpve = tao_crm_api( "/crm_estagios?id=eq.{$pv_card_info['estagio_id']}&select=nome,cor&limit=1" );
            if ( $rpve['ok'] && ! empty( $rpve['data'] ) ) {
                $pv_card_info['estagio_nome'] = $rpve['data'][0]['nome'];
                $pv_card_info['estagio_cor']  = $rpve['data'][0]['cor'] ?? '#6366f1';
            }
        }
    }

    // Atendentes só podem ver seus próprios cards
    if ( ! tao_crm_is_gestor( $card['workspace_id'] ?? '' ) ) {
        $uid = get_current_user_id();
        if ( intval( $card['responsavel_id'] ?? 0 ) !== $uid && ! empty( $card['responsavel_id'] ) ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Acesso negado — este card não está atribuído a você.</p></div></div>';
            return;
        }
    }

    $re       = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$card['pipeline_id']}&order=ordem.asc" );
    $estagios = $re['ok'] ? ( $re['data'] ?? [] ) : [];

    // Carrega histórico completo do contato (todos os cards do mesmo WhatsApp no workspace)
    $whatsapp_hist = $card['contato_whatsapp'] ?? '';
    $ws_hist       = $card['workspace_id']     ?? '';
    if ( $whatsapp_hist && $ws_hist ) {
        $rc_hist      = tao_crm_api( "/crm_cards?contato_whatsapp=eq.$whatsapp_hist&workspace_id=eq.$ws_hist&select=id&order=criado_em.asc" );
        $hist_ids     = array_column( $rc_hist['ok'] ? ( $rc_hist['data'] ?? [] ) : [], 'id' );
        if ( ! empty( $hist_ids ) ) {
            $ids_str = implode( ',', array_map( 'strval', $hist_ids ) );
            $rm = tao_crm_api( "/crm_mensagens?card_id=in.($ids_str)&order=enviado_em.asc&limit=500" );
        } else {
            $rm = tao_crm_api( "/crm_mensagens?card_id=eq.$card_id&order=enviado_em.asc&limit=200" );
        }
    } else {
        $rm = tao_crm_api( "/crm_mensagens?card_id=eq.$card_id&order=enviado_em.asc&limit=200" );
    }
    $msgs = $rm['ok'] ? ( $rm['data'] ?? [] ) : [];

    // Campos: definição do estágio atual + outros estágios com valores preenchidos
    $rce          = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.{$card['estagio_id']}&order=ordem.asc" );
    $campos_estagio = $rce['ok'] ? ( $rce['data'] ?? [] ) : [];

    $campos_ids_atuais = array_column( $campos_estagio, 'campo_id' );

    // Definições dos campos do estágio atual
    $campos_def = [];
    if ( ! empty( $campos_ids_atuais ) ) {
        $rcd = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $campos_ids_atuais ) . ')' );
        foreach ( ( $rcd['ok'] ? ( $rcd['data'] ?? [] ) : [] ) as $d ) {
            $campos_def[ $d['id'] ] = $d;
        }
    }

    // Todos os valores preenchidos neste card
    $rv = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id" );
    $valores = [];
    foreach ( ( $rv['ok'] ? ( $rv['data'] ?? [] ) : [] ) as $v ) {
        $valores[ $v['campo_id'] ] = $v['valor'];
    }

    // Campos de outros estágios que têm valor preenchido
    $outros_campo_ids = array_diff( array_keys( $valores ), $campos_ids_atuais );
    $campos_outros_def = [];
    if ( ! empty( $outros_campo_ids ) ) {
        $rco = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $outros_campo_ids ) . ')' );
        foreach ( ( $rco['ok'] ? ( $rco['data'] ?? [] ) : [] ) as $d ) {
            $campos_outros_def[ $d['id'] ] = $d;
        }
    }

    // Instância de origem do card
    $instancia_origem = null;
    if ( ! empty( $card['instancia_id'] ) ) {
        $ri = tao_crm_api( "/crm_instancias?id=eq.{$card['instancia_id']}&select=nome,evolution_instancia&limit=1" );
        if ( $ri['ok'] && ! empty( $ri['data'] ) ) {
            $instancia_origem = $ri['data'][0];
        }
    }

    // Dados do contato (email, CPF)
    $contato_extra = [];
    if ( ! empty( $card['contato_id'] ) ) {
        $rc_ct = tao_crm_api( "/crm_contatos?id=eq.{$card['contato_id']}&select=email,cpf,cep,logradouro,numero,complemento,bairro,cidade,classificacao,observacoes&limit=1" );
        if ( $rc_ct['ok'] && ! empty( $rc_ct['data'] ) ) {
            $contato_extra = $rc_ct['data'][0];
        }
    }

    // Tags do workspace e tags do card
    $rts      = tao_crm_api( "/crm_tags?workspace_id=eq.{$card['workspace_id']}&order=nome.asc" );
    $all_tags = $rts['ok'] ? ( $rts['data'] ?? [] ) : [];
    $rct      = tao_crm_api( "/crm_cards_tags?card_id=eq.$card_id&select=tag_id" );
    $card_tag_ids = array_column( $rct['ok'] ? ( $rct['data'] ?? [] ) : [], 'tag_id' );

    // Lembretes do card
    $rlm      = tao_crm_api( "/crm_lembretes?card_id=eq.$card_id&order=data_hora.asc&limit=50" );
    $lembretes = $rlm['ok'] ? ( $rlm['data'] ?? [] ) : [];

    // Histórico de movimentações
    $rh       = tao_crm_api( "/crm_cards_historico?card_id=eq.$card_id&order=criado_em.asc&limit=30" );
    $historico = $rh['ok'] ? ( $rh['data'] ?? [] ) : [];

    // Histórico de atendimentos anteriores do mesmo contato (outros cards)
    $cards_anteriores = [];
    if ( ! empty( $card['contato_whatsapp'] ) ) {
        $rca = tao_crm_api( "/crm_cards?workspace_id=eq.{$card['workspace_id']}&contato_whatsapp=eq.{$card['contato_whatsapp']}&id=neq.$card_id&select=id,titulo,contato_nome,criado_em,fechado,status,pipeline_id&order=criado_em.desc&limit=10" );
        $cards_anteriores = $rca['ok'] ? ( $rca['data'] ?? [] ) : [];
    }

    // Pipelines para nome no histórico de atendimentos
    $pipelines_map = [];
    if ( ! empty( $cards_anteriores ) ) {
        $rpl_all = tao_crm_api( "/crm_pipelines?workspace_id=eq.{$card['workspace_id']}&select=id,nome" );
        foreach ( $rpl_all['ok'] ? ( $rpl_all['data'] ?? [] ) : [] as $p ) {
            $pipelines_map[ $p['id'] ] = $p['nome'];
        }
    }

    // Verifica se este card é do pipeline de Pós Vendas
    $is_pos_vendas = ( $card['pipeline_id'] === get_option( 'tao_crm_pos_vendas_pipeline_' . $card['workspace_id'], '' ) );

    // Mapas de ids → nomes para historico
    $estagios_map = [];
    foreach ( $estagios as $e ) { $estagios_map[ $e['id'] ] = $e['nome']; }

    // Responsáveis: apenas equipe do workspace (gestores + vendedores)
    $ws_id    = $card['workspace_id'] ?? '';
    $wp_users = function_exists( 'tao_crm_get_equipe_ws' ) ? tao_crm_get_equipe_ws( $ws_id ) : get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );


    // Estágio atual
    $estagio_atual = null;
    foreach ( $estagios as $e ) {
        if ( $e['id'] === $card['estagio_id'] ) { $estagio_atual = $e; break; }
    }

    $kanban_url = tao_crm_url( [ 'workspace_id' => $card['workspace_id'], 'pipeline_id' => $card['pipeline_id'] ] );

    ?>
    <div class="wrap tao-crm-wrap">

        <div class="tao-crm-topbar">
            <a href="<?php echo esc_url( $kanban_url ); ?>" class="tao-crm-back">&#8592; Voltar ao Kanban</a>
            <?php if ( ! empty( $card['atendimento_humano'] ) ) : ?>
            <span class="tao-crm-handoff-badge">🙋 Em atendimento humano</span>
            <?php endif; ?>
            <?php if ( ! empty( $card['fechado'] ) ) : ?>
            <span class="tao-crm-fechado-badge">🔒 Card fechado — próxima mensagem do cliente abrirá novo card</span>
            <?php if ( ! empty( $card['fechado'] ) && tao_crm_is_gestor( $card['workspace_id'] ) ) : ?>
<button type="button" id="crm-reabrir-btn" class="button" style="margin-left:8px;color:#16a34a;border-color:#16a34a">
    &#x21BA; Reabrir card
</button>
            <?php endif; ?>
            <?php else : ?>
            <div class="tao-crm-fechar-actions">
                <button class="button tao-crm-btn-ganho" id="tao-crm-btn-ganho">&#x2705; Fechar Neg&oacute;cio</button>
                <button class="button tao-crm-btn-perdido" id="tao-crm-btn-perdido">&#x274C; Neg&oacute;cio Perdido</button>
            </div>
            <button type="button" id="crm-formalizar-btn" class="button" style="margin-left:8px" title="Gerar Or&ccedil;amento, Proposta ou Pedido formal">
                &#x1F4CB; Formalizar
            </button>
            <?php endif; ?>
        <?php if ( tao_crm_is_gestor( $card['workspace_id'] ?? '' ) && empty( $card['fechado'] ) ) : ?>
        <?php if ( ! empty( $card['atendimento_humano'] ) ) : ?>
        <button type="button" id="crm-devolver-chatbot-btn" class="button" style="margin-left:8px;color:#7c3aed;border-color:#7c3aed" title="Devolve o cliente ao chatbot sem fechar o card">
            &#x1F916; Devolver ao chatbot
        </button>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ( empty( $card['fechado'] ) && empty( $card['atendimento_humano'] ) ) : ?>
        <button type="button" id="crm-recuperar-atendimento-btn" class="button" style="margin-left:8px;color:#0ea5e9;border-color:#0ea5e9" title="Para o chatbot e assume o atendimento manual deste cliente">
            &#x1F91D; Recuperar atendimento
        </button>
        <?php endif; ?>
        <?php if ( tao_crm_is_gestor( $card['workspace_id'] ?? '' ) && empty( $card['fechado'] ) ) : ?>
        <?php if ( $is_pos_vendas ) : ?>
        <button type="button" id="crm-fechar-pv-btn" class="button" style="margin-left:8px;color:#16a34a;border-color:#16a34a" title="Marca o pedido como entregue e fecha o card">
            &#x2705; Pedido entregue
        </button>
        <?php endif; ?>
        <button type="button" id="crm-transfer-btn" class="button" style="margin-left:8px">
            &#x1F500; Transferir
        </button>
        <?php endif; ?>
        <?php if ( empty( $card['fechado'] ) ) : ?>
        <div style="display:flex;align-items:center;gap:6px;margin-left:12px;padding-left:12px;border-left:1px solid #e2e8f0">
            <span style="font-size:12px;color:#64748b;white-space:nowrap">Mover para:</span>
            <select id="tao-crm-move-stage" style="font-size:12px;padding:3px 6px;border:1px solid #d1d5db;border-radius:4px;max-width:160px">
                <?php foreach ( $estagios as $e ) : ?>
                <option value="<?php echo esc_attr( $e['id'] ); ?>"
                    <?php selected( $e['id'], $card['estagio_id'] ); ?>>
                    <?php echo esc_html( $e['nome'] ); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="button" id="tao-crm-btn-move">Mover</button>
            <span id="tao-crm-move-status" style="font-size:12px"></span>
        </div>
        <?php endif; ?>
        <?php if ( tao_crm_is_gestor( $card['workspace_id'] ?? '' ) && empty( $card['fechado'] ) ) : ?>
        <!-- Modal de transferência -->
        <div id="crm-transfer-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:12px;padding:24px;width:400px;max-width:90vw">
                <h3 style="margin:0 0 16px">Transferir card</h3>
                <label style="display:block;margin-bottom:10px;font-size:13px">Atendente
                    <select id="crm-transfer-user" style="width:100%;margin-top:4px">
                        <?php foreach ( $wp_users as $u ) : ?>
                        <option value="<?php echo esc_attr($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display:block;margin-bottom:16px;font-size:13px">Mensagem interna (opcional)
                    <textarea id="crm-transfer-msg" rows="3" style="width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:4px;padding:8px;font-size:13px" placeholder="Ex: Cliente pediu para falar com voc&#234; sobre or&#231;amento"></textarea>
                </label>
                <div style="display:flex;gap:8px">
                    <button class="button button-primary" id="crm-transfer-confirm">Transferir</button>
                    <button class="button" id="crm-transfer-cancel">Cancelar</button>
                    <span id="crm-transfer-status" style="font-size:12px"></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>

        <div class="tao-crm-card-layout" id="crm-card-layout">

            <!-- Painel esquerdo -->
            <div class="tao-crm-card-info" id="crm-panel-info">

                <div class="card-info-header">
                    <h2 id="tao-crm-card-title-display"><?php echo esc_html( $card['titulo'] ?: $card['contato_nome'] ); ?></h2>
                    <?php if ( $estagio_atual ) : ?>
                    <span class="card-stage-badge" style="background:<?php echo esc_attr( $estagio_atual['cor'] ?? '#6366f1' ); ?>">
                        <?php echo esc_html( $estagio_atual['nome'] ); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ( empty( $card['fechado'] ) ) : ?>
                    <button class="tao-crm-edit-btn" id="tao-crm-edit-info-btn" title="Editar dados do contato">✏</button>
                    <?php endif; ?>
                </div>

                <div class="card-info-body">
                    <div class="info-row">
                        <span class="info-label">Contato</span>
                        <span class="info-value" id="tao-crm-contato-nome-display"><?php echo esc_html( $card['contato_nome'] ); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">WhatsApp</span>
                        <span class="info-value" id="tao-crm-contato-whats-display">
                            <?php echo esc_html( tao_crm_format_phone( $card['contato_whatsapp'] ) ); ?>
                            <?php if ( function_exists( 'tao_crm_is_lid_num' ) && tao_crm_is_lid_num( $card['contato_whatsapp'] ) ) : ?>
                            <span title="N&uacute;mero de dispositivo WhatsApp (@lid). Edite para inserir o telefone real." style="cursor:help;color:#e67e22;font-size:11px;margin-left:4px">&#x26A0; ID dispositivo</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Criado em</span>
                        <span class="info-value"><?php echo esc_html( tao_crm_brt( $card['criado_em'], 'd/m/Y H:i' ) ); ?></span>
                    </div>
                    <?php if ( $instancia_origem ) : ?>
                    <div class="info-row">
                        <span class="info-label">Instância</span>
                        <span class="info-value" title="<?php echo esc_attr( $instancia_origem['evolution_instancia'] ); ?>" style="display:flex;align-items:center;gap:4px">
                            <span style="font-size:15px">📱</span>
                            <?php echo esc_html( $instancia_origem['nome'] ); ?>
                            <span style="color:#6b7280;font-size:11px">(<?php echo esc_html( $instancia_origem['evolution_instancia'] ); ?>)</span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Responsável -->
                    <div class="info-row" style="align-items:center">
                        <span class="info-label">Responsável</span>
                        <select id="tao-crm-responsavel" class="info-select"
                                data-original="<?php echo esc_attr( $card['responsavel_id'] ?? '' ); ?>"
                                style="max-width:150px;font-size:12px">
                            <option value="">— Ninguém —</option>
                            <?php foreach ( $wp_users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"
                                <?php selected( intval( $card['responsavel_id'] ?? 0 ), $u->ID ); ?>>
                                <?php echo esc_html( $u->display_name ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Valor de oportunidade -->
                    <div class="info-row crm-info-row" style="align-items:center;gap:6px">
                        <span class="info-label">Valor (R$)</span>
                        <input type="number" id="crm-valor-oportunidade" step="0.01" min="0"
                               value="<?php echo esc_attr( $card['valor_oportunidade'] ?? '' ); ?>"
                               data-original="<?php echo esc_attr( $card['valor_oportunidade'] ?? '' ); ?>"
                               placeholder="0,00"
                               title="Calculado automaticamente quando há itens cadastrados"
                               style="width:110px;font-size:12px;padding:3px 6px;border:1px solid #d1d5db;border-radius:4px">
                    </div>

                    <!-- Barra Salvar / Cancelar informações gerais -->
                    <?php if ( empty( $card['fechado'] ) ) : ?>
                    <div id="crm-info-action-bar" style="display:flex;align-items:center;gap:8px;padding:8px 0 2px;margin-top:2px;border-top:1px solid #e2e8f0">
                        <button type="button" id="crm-info-save" class="button button-primary button-small" style="font-size:12px">Salvar</button>
                        <button type="button" id="crm-info-cancel" class="button button-small" style="font-size:12px">Cancelar</button>
                        <span id="crm-info-status" style="display:none;font-size:11px;color:#16a34a">✔ salvo</span>
                    </div>
                    <?php endif; ?>

                    <!-- Itens do Negócio -->
                    <div class="crm-itens-section"
                         data-card-id="<?php echo esc_attr( $card_id ); ?>"
                         data-fechado="<?php echo empty( $card['fechado'] ) ? '0' : '1'; ?>">
                        <div class="crm-itens-header">
                            <strong style="font-size:13px">&#x1F6D2; Itens do Neg&oacute;cio</strong>
                            <?php if ( empty( $card['fechado'] ) ) : ?>
                            <button type="button" id="crm-item-add" class="button button-small" style="font-size:11px">+ Item</button>
                            <?php if ( function_exists( 'tao_formula_can_access' ) && tao_formula_can_access() ) : ?>
                            <button type="button" id="crm-formula-novo-btn" class="button button-small"
                                    style="font-size:11px;background:#0ea5e9;border-color:#0284c7;color:#fff">
                                &#x1F9EA; Or&ccedil;amento
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div id="crm-itens-loading" style="display:none;font-size:12px;color:#6b7280;padding:4px 0">Carregando...</div>
                        <div id="crm-itens-list"></div>
                        <div id="crm-itens-footer" style="display:none;display:flex;justify-content:space-between;padding:5px 4px 2px;border-top:2px solid #e2e8f0;margin-top:4px;font-size:12px">
                            <span style="color:#6b7280">Total</span>
                            <strong id="crm-itens-grand-total" style="color:#1e293b">R$ 0,00</strong>
                        </div>
                    </div>

                    <!-- Orçamentos Fórmula -->
                    <?php if ( function_exists( 'tao_formula_can_access' ) && tao_formula_can_access() ) : ?>
                    <div class="crm-itens-section" id="crm-formulas-section" style="margin-top:10px">
                        <div class="crm-itens-header">
                            <strong style="font-size:13px">&#x1F9EA; Or&ccedil;amentos F&oacute;rmula</strong>
                            <div style="display:flex;gap:5px">
                                <button type="button" id="crm-formula-reprocessar-btn"
                                        class="button button-small"
                                        style="font-size:11px;display:none"
                                        title="Re-tenta associar ativos pendentes usando sinônimos atuais">
                                    &#x1F504; Reprocessar
                                </button>
                                <button type="button" id="crm-formula-excluir-sel-btn"
                                        class="button button-small"
                                        style="font-size:11px;display:none;color:#dc2626;border-color:#fca5a5"
                                        title="Excluir orçamentos selecionados">
                                    &#x1F5D1; Excluir selecionados
                                </button>
                                <button type="button" id="crm-formula-enviar-btn"
                                        class="button button-primary button-small"
                                        style="font-size:11px;display:none">
                                    &#x1F4E4; Enviar WhatsApp
                                </button>
                            </div>
                        </div>
                        <div id="crm-formulas-list" style="font-size:12px;color:#94a3b8;padding:4px 0;max-height:280px;overflow-y:auto">
                            Carregando...
                        </div>

                        <?php if ( empty( $card['fechado'] ) ) : ?>
                        <!-- Upload Receita IA -->
                        <div id="crm-receita-section" style="margin-top:10px;border-top:1px solid #f1f5f9;padding-top:8px">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                <strong style="font-size:12px;color:#475569">&#x1F52C; Processar Receita (IA)</strong>
                                <span id="crm-receita-status" style="font-size:11px"></span>
                            </div>
                            <div id="crm-receita-dropzone"
                                 style="border:2px dashed #cbd5e1;border-radius:6px;padding:14px 10px;text-align:center;cursor:pointer;transition:border-color .2s;background:#f8fafc">
                                <div style="color:#94a3b8;font-size:12px;line-height:1.6">
                                    &#x1F4CE; Arraste, <strong>cole (Ctrl+V)</strong> ou
                                    <label for="crm-receita-file" style="color:#0ea5e9;cursor:pointer;text-decoration:underline">selecione o arquivo</label>
                                    <br><span style="font-size:11px">JPG &bull; PNG &bull; PDF</span>
                                </div>
                                <input type="file" id="crm-receita-file" accept="image/*,.pdf" style="display:none">
                            </div>
                            <div id="crm-receita-preview" style="display:none;margin-top:6px;font-size:12px;color:#475569;background:#f1f5f9;border-radius:4px;padding:6px 8px"></div>
                            <div style="margin-top:6px;display:flex;gap:6px">
                                <button type="button" id="crm-receita-processar" class="button button-primary button-small" style="display:none;font-size:11px">
                                    &#x1F916; Gerar Or&ccedil;amento
                                </button>
                                <button type="button" id="crm-receita-limpar" class="button button-small" style="display:none;font-size:11px">✕</button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Etiquetas (Tags) -->
                    <?php if ( ! empty( $all_tags ) ) : ?>
                    <div class="info-row crm-section-tags" style="flex-direction:column;align-items:flex-start;gap:6px">
                        <span class="info-label">Etiquetas</span>
                        <div id="crm-card-tags-display" style="display:flex;flex-wrap:wrap;gap:4px">
                            <?php foreach ( $all_tags as $tag ) :
                                if ( ! in_array( $tag['id'], $card_tag_ids ) ) continue;
                                $tc = esc_attr( $tag['cor'] ?? '#6366f1' );
                            ?>
                            <span class="crm-tag-pill"
                                  style="background:<?php echo $tc; ?>20;color:<?php echo $tc; ?>;border:1px solid <?php echo $tc; ?>40;padding:2px 8px;border-radius:12px;font-size:11px">
                                <?php echo esc_html( $tag['nome'] ); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="crm-tags-edit-btn" class="button button-small" style="font-size:11px">+ Editar</button>
                        <div id="crm-tags-picker" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;margin-top:4px;min-width:200px">
                            <?php foreach ( $all_tags as $tag ) :
                                $tc  = esc_attr( $tag['cor'] ?? '#6366f1' );
                                $chk = in_array( $tag['id'], $card_tag_ids ) ? 'checked' : '';
                            ?>
                            <label style="display:flex;align-items:center;gap:6px;margin-bottom:6px;cursor:pointer;font-size:12px">
                                <input type="checkbox" class="crm-tag-checkbox"
                                       value="<?php echo esc_attr( $tag['id'] ); ?>" <?php echo $chk; ?>>
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo $tc; ?>;flex-shrink:0"></span>
                                <?php echo esc_html( $tag['nome'] ); ?>
                            </label>
                            <?php endforeach; ?>
                            <div style="margin-top:8px;display:flex;gap:6px">
                                <button type="button" id="crm-tags-save" class="button button-primary button-small" style="font-size:11px">Salvar</button>
                                <button type="button" id="crm-tags-cancel" class="button button-small" style="font-size:11px">Cancelar</button>
                            </div>
                            <span id="crm-tags-status" style="display:none;font-size:11px;color:#16a34a;margin-top:4px">✔ salvo</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Painel: Pós Vendas (se card veio de handoff de cliente com pedido em andamento) -->
                <?php if ( $pv_card_info ) :
                    $pv_url = tao_crm_url( [ 'action' => 'card', 'id' => $pv_card_info['id'] ] );
                    $pv_cor = esc_attr( $pv_card_info['estagio_cor'] ?? '#6366f1' );
                ?>
                <div class="card-pv-panel" style="margin-bottom:16px;padding:12px 14px;background:#fef9ec;border:1px solid #f0c04b;border-radius:8px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <span style="font-size:16px">&#x1F4E6;</span>
                        <strong style="font-size:13px;color:#92400e">Cliente com pedido em Pós Vendas</strong>
                    </div>
                    <div style="font-size:12px;color:#78350f;margin-bottom:8px">
                        <strong>Pedido:</strong> <?php echo esc_html( $pv_card_info['titulo'] ); ?><br>
                        <?php if ( ! empty( $pv_card_info['estagio_nome'] ) ) : ?>
                        <strong>Fase:</strong>
                        <span style="background:<?php echo $pv_cor; ?>20;color:<?php echo $pv_cor; ?>;border:1px solid <?php echo $pv_cor; ?>40;border-radius:10px;padding:1px 7px;font-size:11px">
                            <?php echo esc_html( $pv_card_info['estagio_nome'] ); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo esc_url( $pv_url ); ?>" class="button button-small" style="font-size:11px">
                        &#x1F517; Ver pedido em Pós Vendas
                    </a>
                </div>
                <?php endif; ?>

                <!-- Campos do estágio atual -->
                <?php if ( ! empty( $campos_estagio ) ) : ?>
                <div class="card-campos-section">
                    <h3 class="campos-title">&#x1F4CB; Campos &mdash; <?php echo esc_html( $estagio_atual['nome'] ?? 'Est&aacute;gio atual' ); ?></h3>
                    <?php foreach ( $campos_estagio as $ce ) :
                        $def = $campos_def[ $ce['campo_id'] ] ?? null;
                        if ( ! $def ) continue;
                        $val = $valores[ $ce['campo_id'] ] ?? '';
                        $obg = ! empty( $ce['obrigatorio'] );
                    ?>
                    <div class="campo-item<?php echo $obg && $val === '' ? ' campo-missing' : ''; ?>"
                         data-campo-id="<?php echo esc_attr( $def['id'] ); ?>">
                        <label class="campo-label">
                            <?php echo esc_html( $def['nome'] ); ?>
                            <?php if ( $obg ) : ?><span class="campo-required" title="Obrigatório">*</span><?php endif; ?>
                        </label>
                        <?php echo tao_crm_render_campo_input( $def, $val, $card_id ); ?>
                        <span class="campo-saved" style="display:none">✔ salvo</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Campos de outros estágios com valores (editáveis inline) -->
                <?php if ( ! empty( $campos_outros_def ) ) : ?>
                <div class="card-campos-section">
                    <h3 class="campos-title">&#x1F4CB; Dados do negócio</h3>
                    <?php foreach ( $campos_outros_def as $cid => $def ) :
                        $val = $valores[ $cid ] ?? '';
                    ?>
                    <div class="campo-item" data-campo-id="<?php echo esc_attr( $cid ); ?>">
                        <label class="campo-label"><?php echo esc_html( $def['nome'] ); ?></label>
                        <?php echo tao_crm_render_campo_input( $def, $val, $card_id ); ?>
                        <span class="campo-saved" style="display:none">✔ salvo</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Lembretes -->
                <div class="crm-section-lembretes" style="margin-bottom:20px">
                    <h4 style="margin-bottom:10px;font-size:13px;font-weight:700">🔔 Lembretes</h4>
                    <div id="crm-lembretes-list">
                        <?php if ( empty( $lembretes ) ) : ?>
                        <p style="font-size:12px;color:#94a3b8">Nenhum lembrete ainda.</p>
                        <?php else : foreach ( $lembretes as $lem ) :
                            $lem_id   = esc_attr( $lem['id'] );
                            $lem_comp = ! empty( $lem['completado'] );
                            $lem_data = ! empty( $lem['data_hora'] ) ? tao_crm_brt( $lem['data_hora'], 'd/m/Y H:i' ) : '—';
                        ?>
                        <div class="crm-lembrete-item <?php echo $lem_comp ? 'lembrete-completado' : ''; ?>"
                             data-lembrete-id="<?php echo $lem_id; ?>"
                             style="display:flex;align-items:flex-start;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px">
                            <input type="checkbox" class="crm-lem-check" data-id="<?php echo $lem_id; ?>"
                                   <?php echo $lem_comp ? 'checked' : ''; ?> title="Marcar como concluído">
                            <div style="flex:1">
                                <div style="font-weight:600;<?php echo $lem_comp ? 'text-decoration:line-through;color:#94a3b8' : ''; ?>">
                                    <?php echo esc_html( $lem['titulo'] ?? '' ); ?>
                                </div>
                                <div style="color:#64748b"><?php echo esc_html( $lem_data ); ?></div>
                                <?php if ( ! empty( $lem['descricao'] ) ) : ?>
                                <div style="color:#94a3b8;font-size:11px"><?php echo esc_html( $lem['descricao'] ); ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="crm-lem-delete" data-id="<?php echo $lem_id; ?>"
                                    style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;padding:0 2px"
                                    title="Excluir lembrete">✕</button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="crm-lembrete-form" style="margin-top:12px;display:flex;flex-direction:column;gap:6px">
                        <input type="text" id="crm-lem-titulo" placeholder="Ex: Ligar amanhã de manhã"
                               style="font-size:12px;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px">
                        <input type="datetime-local" id="crm-lem-data"
                               style="font-size:12px;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px">
                        <textarea id="crm-lem-desc" rows="2" placeholder="Observação (opcional)"
                                  style="font-size:12px;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;resize:vertical"></textarea>
                        <button type="button" class="button button-small" id="crm-lem-add" style="font-size:12px;align-self:flex-start">+ Adicionar lembrete</button>
                        <span id="crm-lem-status" style="display:none;font-size:11px;color:#16a34a">✔ lembrete adicionado</span>
                    </div>
                </div>

                <!-- PATCH: Comentários Internos -->
                <div class="crm-section-comentarios" style="margin-bottom:20px">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px">&#x1F4DD; Notas internas</label>
                    <div id="crm-comentarios-list"><!-- preenchido via JS --></div>
                    <form id="crm-coment-form" style="margin-top:10px;display:flex;gap:8px">
                        <textarea id="crm-coment-texto" rows="2" placeholder="Nota interna (só a equipe vê)..."
                            style="flex:1;border:1px solid #cbd5e1;border-radius:4px;padding:6px 8px;font-size:13px;resize:vertical"></textarea>
                        <button type="submit" class="button button-primary" style="height:fit-content;align-self:flex-end">Salvar</button>
                    </form>
                </div>

                <!-- Histórico de movimentações (collapsible) -->
                <div class="card-history crm-section-historico">
                    <h3 style="cursor:pointer;user-select:none" onclick="(function(){var d=document.getElementById('crm-historico-body');var t=document.getElementById('hist-toggle');if(d.style.display==='none'){d.style.display='block';t.textContent='▲';}else{d.style.display='none';t.textContent='▼';}})()">
                        📋 Histórico <span id="hist-toggle" style="font-size:12px;color:#94a3b8">▼</span>
                    </h3>
                    <div id="crm-historico-body" style="display:none">
                        <?php if ( empty( $historico ) ) : ?>
                            <p class="no-history">Nenhuma movimentação ainda.</p>
                        <?php else : foreach ( $historico as $h ) :
                            $de   = $estagios_map[ $h['de_estagio_id']   ?? '' ] ?? '—';
                            $para = $estagios_map[ $h['para_estagio_id'] ?? '' ] ?? '—';
                        ?>
                        <div class="history-item">
                            <?php if ( ! empty( $h['de_estagio_id'] ) || ! empty( $h['para_estagio_id'] ) ) : ?>
                            <span class="history-arrow"><?php echo esc_html( $de ); ?> → <?php echo esc_html( $para ); ?></span>
                            <?php endif; ?>
                            <span class="history-date"><?php echo esc_html( tao_crm_brt( $h['criado_em'], 'd/m H:i' ) ); ?></span>
                            <?php if ( ! empty( $h['motivo'] ) ) : ?>
                            <span class="history-motivo"><?php echo esc_html( $h['motivo'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Histórico de atendimentos anteriores do mesmo contato -->
                <?php if ( ! empty( $cards_anteriores ) ) : ?>
                <div class="card-history crm-section-atendimentos-ant" style="margin-top:12px">
                    <h3 style="cursor:pointer;user-select:none" onclick="(function(){var d=document.getElementById('crm-ant-body');var t=document.getElementById('crm-ant-toggle');if(d.style.display==='none'){d.style.display='block';t.textContent='▲';}else{d.style.display='none';t.textContent='▼';}})()">
                        &#x1F4D6; Atendimentos anteriores (<?php echo count( $cards_anteriores ); ?>) <span id="crm-ant-toggle" style="font-size:12px;color:#94a3b8">▼</span>
                    </h3>
                    <div id="crm-ant-body" style="display:none">
                        <?php foreach ( $cards_anteriores as $ca ) :
                            $ca_url    = tao_crm_url( [ 'action' => 'card', 'id' => $ca['id'] ] );
                            $ca_status = ! empty( $ca['fechado'] ) ? ( $ca['status'] ?? 'fechado' ) : 'aberto';
                            $ca_cor    = $ca_status === 'ganho' ? '#16a34a' : ( $ca_status === 'perdido' ? '#dc2626' : '#6366f1' );
                            $ca_pl     = $pipelines_map[ $ca['pipeline_id'] ] ?? '';
                        ?>
                        <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px">
                            <a href="<?php echo esc_url( $ca_url ); ?>" style="font-weight:600;color:#1e293b;text-decoration:none">
                                <?php echo esc_html( $ca['titulo'] ?: $ca['contato_nome'] ); ?>
                            </a>
                            <span style="background:<?php echo esc_attr( $ca_cor ); ?>20;color:<?php echo esc_attr( $ca_cor ); ?>;border-radius:8px;padding:1px 6px;font-size:10px;margin-left:4px"><?php echo esc_html( $ca_status ); ?></span>
                            <?php if ( $ca_pl ) : ?><span style="color:#94a3b8;margin-left:4px"><?php echo esc_html( $ca_pl ); ?></span><?php endif; ?>
                            <span style="color:#94a3b8;float:right"><?php echo esc_html( tao_crm_brt( $ca['criado_em'], 'd/m/Y' ) ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /tao-crm-card-info -->

            <!-- ── Modal iframe: TAO Fórmula ──────────────────────────── -->
            <?php if ( function_exists( 'tao_formula_can_access' ) && tao_formula_can_access() ) : ?>
            <div id="taof-crm-modal"
                 style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:99998;align-items:center;justify-content:center">
                <div style="position:relative;width:98vw;max-width:1260px;height:93vh;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.45)">
                    <iframe id="taof-crm-iframe" src=""
                            style="width:100%;height:100%;border:none;display:block"></iframe>
                </div>
            </div>
            <?php
                $taof_novo_url = admin_url( 'admin.php?page=tao-formula-orc-novo' );
                $taof_nonce    = wp_create_nonce( 'tao_formula_nonce' );
            ?>
            <script>
            window.taofNovoUrl  = <?php echo wp_json_encode( $taof_novo_url ); ?>;
            window.taofNonce    = <?php echo wp_json_encode( $taof_nonce ); ?>;
            window.taofAjaxUrl  = <?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>;
            window.taofCrmCardId   = <?php echo wp_json_encode( $card_id ); ?>;
            window.taofCrmNome     = <?php echo wp_json_encode( $card['contato_nome'] ?? '' ); ?>;
            window.taofCrmWa       = <?php echo wp_json_encode( $card['contato_whatsapp'] ?? '' ); ?>;
            window.taofCrmFechado  = <?php echo empty( $card['fechado'] ) ? 'false' : 'true'; ?>;
            </script>
            <?php endif; ?>

            <!-- Divisor arrastável -->
            <div class="crm-panel-resizer" id="crm-panel-resizer" title="Arraste para redimensionar"></div>

            <!-- Painel direito: chat -->
            <div class="tao-crm-chat-panel" id="crm-panel-chat">

                <div class="chat-header">
                    <span class="chat-contact">💬 <?php echo esc_html( $card['contato_nome'] ); ?></span>
                    <span class="chat-number"><?php echo esc_html( tao_crm_format_phone( $card['contato_whatsapp'] ) ); ?></span>
                    <?php if ( $instancia_origem ) : ?>
                    <span class="chat-number" style="margin-left:8px;opacity:.75" title="Instância WhatsApp que recebeu este atendimento">📱 <?php echo esc_html( $instancia_origem['nome'] ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="chat-messages" id="tao-crm-messages">
                    <?php foreach ( $msgs as $msg ) : ?>
                    <?php echo tao_crm_render_message( $msg ); ?>
                    <?php endforeach; ?>
                </div>

                <div class="chat-input-area">
                    <?php if ( empty( $card['fechado'] ) ) : ?>
                    <div class="chat-template-bar">
                        <select id="tao-crm-template-select">
                            <option value="">&#x1F4DD; Inserir template...</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div id="tao-crm-file-preview" style="display:none">
                        <span id="tao-crm-file-name"></span>
                        <button type="button" id="tao-crm-file-clear" title="Remover arquivo">✕</button>
                    </div>
                    <textarea id="tao-crm-msg-input"
                              placeholder="Digite uma mensagem... (Ctrl+Enter para enviar)"
                              rows="3"></textarea>
                    <div class="chat-input-btns">
                        <?php if ( empty( $card['fechado'] ) ) : ?>
                        <label class="button tao-crm-attach-label" id="tao-crm-attach-wrap" title="Enviar anexo (imagem, vídeo, áudio, documento)">
                            📎
                            <input type="file" id="tao-crm-file-input"
                                   accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt"
                                   style="display:none">
                        </label>
                        <button class="button" id="tao-crm-nota-toggle" title="Alternar para modo nota interna">📝</button>
                        <button class="button button-primary" id="tao-crm-send-btn">Enviar ▶</button>
                        <?php else : ?>
                        <span style="color:#94a3b8;font-size:12px;padding:6px 0">Card encerrado — respostas desabilitadas</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( empty( $card['fechado'] ) ) : ?>
                    <!-- PATCH: Agendar mensagem -->
                    <div id="crm-agendar-wrap" style="margin-top:8px">
                        <button type="button" id="crm-agendar-btn" class="button" style="font-size:12px">&#x23F0; Agendar mensagem</button>
                        <div id="crm-agendar-form" style="display:none;margin-top:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px">
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <textarea id="crm-agendar-texto" rows="3" placeholder="Mensagem a enviar..."
                                    style="border:1px solid #cbd5e1;border-radius:4px;padding:6px 8px;font-size:13px"></textarea>
                                <input type="datetime-local" id="crm-agendar-quando"
                                    style="border:1px solid #cbd5e1;border-radius:4px;padding:6px 8px;font-size:13px">
                                <div style="display:flex;gap:8px">
                                    <button type="button" id="crm-agendar-salvar" class="button button-primary">Agendar</button>
                                    <button type="button" id="crm-agendar-cancelar" class="button">Cancelar</button>
                                    <span id="crm-agendar-status" style="font-size:12px;align-self:center"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>

    <!-- Modal: Editar dados do contato -->
    <div id="tao-crm-edit-modal" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content" style="max-width:520px">
            <div class="tao-crm-modal-header">
                <h2>✏ Editar dados do card</h2>
                <button class="tao-crm-modal-close" onclick="document.getElementById('tao-crm-edit-modal').style.display='none'">✕</button>
            </div>
            <form id="tao-crm-edit-form">
                <div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px;max-height:70vh;overflow-y:auto">
    <label style="font-size:13px;font-weight:600">Título do card
        <input type="text" id="tao-crm-edit-titulo" class="regular-text"
               value="<?php echo esc_attr( $card['titulo'] ); ?>"
               style="width:100%;margin-top:4px">
    </label>
    <label style="font-size:13px;font-weight:600">Nome do contato
        <input type="text" id="tao-crm-edit-nome" class="regular-text"
               value="<?php echo esc_attr( $card['contato_nome'] ); ?>"
               style="width:100%;margin-top:4px">
    </label>
    <label style="font-size:13px;font-weight:600">WhatsApp
        <input type="text" id="tao-crm-edit-whats" class="regular-text"
               value="<?php echo esc_attr( $card['contato_whatsapp'] ); ?>"
               placeholder="55119..." style="width:100%;margin-top:4px">
        <?php if ( function_exists( 'tao_crm_is_lid_num' ) && tao_crm_is_lid_num( $card['contato_whatsapp'] ) ) : ?>
        <span style="font-size:11px;color:#e67e22;font-weight:400;display:block;margin-top:3px">
            &#x26A0; ID de dispositivo WhatsApp. Digite o n&uacute;mero real (ex: 5511994604521) e salve para vincular automaticamente.
        </span>
        <?php endif; ?>
    </label>
    <label style="font-size:13px;font-weight:600">E-mail
        <input type="email" id="tao-crm-edit-email" class="regular-text"
               value="<?php echo esc_attr( $contato_extra['email'] ?? '' ); ?>"
               placeholder="email@exemplo.com" style="width:100%;margin-top:4px">
    </label>
    <label style="font-size:13px;font-weight:600">CPF
        <input type="text" id="tao-crm-edit-cpf" class="regular-text"
               value="<?php echo esc_attr( $contato_extra['cpf'] ?? '' ); ?>"
               placeholder="000.000.000-00" style="width:100%;margin-top:4px">
    </label>
    <div style="border-top:1px solid #e2e0dc;padding-top:10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">Endereço</div>
    <div style="display:flex;gap:8px">
        <label style="font-size:13px;font-weight:600;flex:0 0 130px">CEP
            <input type="text" id="tao-crm-edit-cep" class="regular-text"
                   value="<?php echo esc_attr( $contato_extra['cep'] ?? '' ); ?>"
                   placeholder="00000-000" maxlength="9" style="width:100%;margin-top:4px">
        </label>
        <label style="font-size:13px;font-weight:600;flex:0 0 90px">Número
            <input type="text" id="tao-crm-edit-numero" class="regular-text"
                   value="<?php echo esc_attr( $contato_extra['numero'] ?? '' ); ?>"
                   placeholder="123" style="width:100%;margin-top:4px">
        </label>
    </div>
    <label style="font-size:13px;font-weight:600">Logradouro
        <input type="text" id="tao-crm-edit-logradouro" class="regular-text"
               value="<?php echo esc_attr( $contato_extra['logradouro'] ?? '' ); ?>"
               placeholder="Rua, Av, Travessa..." style="width:100%;margin-top:4px">
    </label>
    <label style="font-size:13px;font-weight:600">Complemento
        <input type="text" id="tao-crm-edit-complemento" class="regular-text"
               value="<?php echo esc_attr( $contato_extra['complemento'] ?? '' ); ?>"
               placeholder="Apto, Bloco..." style="width:100%;margin-top:4px">
    </label>
    <div style="display:flex;gap:8px">
        <label style="font-size:13px;font-weight:600;flex:1">Bairro
            <input type="text" id="tao-crm-edit-bairro" class="regular-text"
                   value="<?php echo esc_attr( $contato_extra['bairro'] ?? '' ); ?>"
                   style="width:100%;margin-top:4px">
        </label>
        <label style="font-size:13px;font-weight:600;flex:1">Cidade
            <input type="text" id="tao-crm-edit-cidade" class="regular-text"
                   value="<?php echo esc_attr( $contato_extra['cidade'] ?? '' ); ?>"
                   style="width:100%;margin-top:4px">
        </label>
    </div>
    <div style="border-top:1px solid #e2e0dc;padding-top:10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280">Outros</div>
    <label style="font-size:13px;font-weight:600">Classificação
        <select id="tao-crm-edit-classificacao" style="width:100%;margin-top:4px">
            <option value="">— sem classificação —</option>
            <?php foreach ( ['Excelente','Bom','Regular','Ruim','Inadimplente'] as $cls ) : ?>
            <option value="<?php echo esc_attr($cls); ?>" <?php selected( ($contato_extra['classificacao'] ?? ''), $cls ); ?>><?php echo esc_html($cls); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label style="font-size:13px;font-weight:600">Observação
        <textarea id="tao-crm-edit-observacao" rows="3" placeholder="Observações sobre o cliente..." style="width:100%;margin-top:4px"><?php echo esc_textarea( $contato_extra['observacoes'] ?? '' ); ?></textarea>
    </label>
                </div>
                <div class="tao-crm-modal-footer">
                    <button type="button" class="button" onclick="document.getElementById('tao-crm-edit-modal').style.display='none'">Cancelar</button>
                    <button type="submit" class="button button-primary" id="tao-crm-edit-btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Barra de ação pós-movimentação (sobe do rodapé após mover o card) -->
    <?php if ( empty( $card['fechado'] ) ) : ?>
    <div id="crm-pos-move-bar" style="
        display:none;position:fixed;bottom:0;left:0;right:0;z-index:99999;
        background:#1e293b;color:#fff;padding:14px 24px;
        display:none;align-items:center;justify-content:space-between;gap:12px;
        box-shadow:0 -4px 20px rgba(0,0,0,.25);transform:translateY(100%);transition:transform .3s ease">
        <div style="font-size:13px;color:#94a3b8">Card movido com sucesso. O que deseja fazer?</div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <button type="button" id="crm-posm-ganho"
                style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">
                &#x2705; Fechar como Ganho
            </button>
            <button type="button" id="crm-posm-perdido"
                style="background:#dc2626;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">
                &#x274C; Negócio Perdido
            </button>
            <?php if ( isset( $taoCrmKanbanUrl ) || true ) : ?>
            <button type="button" id="crm-posm-kanban"
                style="background:#3b82f6;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;cursor:pointer">
                &#x21A9; Voltar ao Kanban
            </button>
            <?php endif; ?>
            <button type="button" id="crm-posm-fechar"
                style="background:transparent;color:#94a3b8;border:1px solid #475569;border-radius:6px;padding:8px 12px;font-size:13px;cursor:pointer"
                title="Continuar editando">
                &#x2715;
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Fechar Card -->
    <div id="tao-crm-fechar-modal" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content" style="max-width:440px">
            <div class="tao-crm-modal-header">
                <h2 id="tao-crm-fechar-titulo">Fechar Negócio</h2>
                <button class="tao-crm-modal-close" onclick="document.getElementById('tao-crm-fechar-modal').style.display='none'">✕</button>
            </div>
            <form id="tao-crm-fechar-form">
                <input type="hidden" id="tao-crm-fechar-tipo" value="">
                <div style="padding:16px 20px">
                    <p id="tao-crm-fechar-desc" style="color:#64748b;margin-bottom:14px;font-size:13px"></p>
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px">Motivo</label>
                    <select id="tao-crm-fechar-motivo" style="width:100%;font-size:13px;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px">
                        <!-- Opções preenchidas pelo JS conforme ganho/perdido -->
                    </select>
                    <div id="tao-crm-fechar-outro-wrap" style="display:none;margin-top:8px">
                        <input type="text" id="tao-crm-fechar-outro"
                               style="width:100%;font-size:13px;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px"
                               placeholder="Descreva o motivo...">
                    </div>
                    <div id="tao-crm-fechar-campos-wrap" style="display:none;margin-top:16px;border-top:1px solid #e5e7eb;padding-top:14px"></div>
                </div>
                <div class="tao-crm-modal-footer">
                    <button type="button" class="button" onclick="document.getElementById('tao-crm-fechar-modal').style.display='none'">Voltar</button>
                    <button type="submit" class="button button-primary" id="tao-crm-fechar-btn">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Adicionar / Editar Item do Negócio -->
    <div id="crm-item-modal" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content" style="max-width:420px">
            <div class="tao-crm-modal-header">
                <h2 id="crm-item-modal-titulo">Adicionar Item</h2>
                <button class="tao-crm-modal-close" id="crm-item-modal-fechar">&#x2715;</button>
            </div>
            <div class="crm-item-modal-body">
                <!-- Catálogo (visível apenas quando disponível e adicionando novo item) -->
                <div id="crm-item-catalogo-section" style="display:none">
                    <div class="crm-item-catalogo-label">&#x1F4E6; Selecionar do cat&aacute;logo</div>
                    <input type="text" id="crm-item-busca" placeholder="Buscar produto..." class="crm-item-busca-input">
                    <div id="crm-item-catalogo-lista"></div>
                    <div class="crm-item-ou"><span>ou preencha manualmente</span></div>
                </div>
                <!-- Formulário do item -->
                <div class="crm-item-form-grid">
                    <div class="crm-item-form-field" style="grid-column:1/-1">
                        <label>Descri&ccedil;&atilde;o *</label>
                        <input type="text" id="crm-item-f-desc" placeholder="Nome do produto ou servi&ccedil;o">
                    </div>
                    <div class="crm-item-form-field">
                        <label>Quantidade</label>
                        <input type="number" id="crm-item-f-qtd" min="0.001" step="0.001" value="1">
                    </div>
                    <div class="crm-item-form-field">
                        <label>Pre&ccedil;o Unit.</label>
                        <input type="number" id="crm-item-f-preco" min="0" step="0.01" value="0">
                    </div>
                    <div class="crm-item-form-field">
                        <label>Desconto</label>
                        <div class="crm-item-desc-row">
                            <select id="crm-item-f-desc-tipo">
                                <option value="pct">%</option>
                                <option value="valor">R$</option>
                            </select>
                            <input type="number" id="crm-item-f-desc-val" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="crm-item-form-field crm-item-total-preview">
                        <label>Total</label>
                        <div id="crm-item-f-total">R$ 0,00</div>
                    </div>
                </div>
            </div>
            <div class="tao-crm-modal-footer">
                <button type="button" class="button" id="crm-item-modal-cancelar">Cancelar</button>
                <button type="button" class="button button-primary" id="crm-item-modal-salvar">Salvar Item</button>
            </div>
        </div>
    </div>

    <!-- Modal: Campos na entrada da fase (movimentar card) -->
    <div id="tao-crm-entrada-modal" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content" style="max-width:480px">
            <div class="tao-crm-modal-header">
                <h2>&#x1F4CB; Campos obrigat&oacute;rios &mdash; entrada na fase</h2>
                <button class="tao-crm-modal-close" id="tao-crm-entrada-fechar">&#x2715;</button>
            </div>
            <form id="tao-crm-entrada-form">
                <div id="tao-crm-entrada-fields" style="padding:16px 20px;display:flex;flex-direction:column;gap:14px">
                    <!-- preenchido dinamicamente pelo JS -->
                </div>
                <div class="tao-crm-modal-footer">
                    <button type="button" class="button" id="tao-crm-entrada-cancelar">Cancelar</button>
                    <button type="submit" class="button button-primary" id="tao-crm-entrada-btn">Confirmar e Mover</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: campos obrigatórios faltando -->
    <div id="tao-crm-campos-modal" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content" style="max-width:400px">
            <div class="tao-crm-modal-header">
                <h2>&#x26A0; Campos obrigat&oacute;rios</h2>
                <button class="tao-crm-modal-close" onclick="document.getElementById('tao-crm-campos-modal').style.display='none'">✕</button>
            </div>
            <div style="padding:16px 20px">
                <p id="tao-crm-campos-modal-msg"></p>
                <ul id="tao-crm-campos-modal-list" style="margin:8px 0 0 16px;color:#dc2626"></ul>
            </div>
            <div class="tao-crm-modal-footer">
                <button class="button button-primary" onclick="document.getElementById('tao-crm-campos-modal').style.display='none'">Entendido</button>
            </div>
        </div>
    </div>

    <!-- Modal: Formalizar -->
    <?php if ( empty( $card['fechado'] ) ) : ?>
    <div id="crm-formalizar-modal" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content" style="max-width:580px">
            <div class="tao-crm-modal-header">
                <h2>&#x1F4CB; Formalizar</h2>
                <button class="tao-crm-modal-close" id="crm-formalizar-fechar">&#x2715;</button>
            </div>
            <div style="padding:16px 20px;display:flex;flex-direction:column;gap:14px;overflow-y:auto;max-height:calc(90vh - 120px)">

                <!-- Tipo -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:6px">Tipo de documento</label>
                    <div style="display:flex;gap:6px" id="crm-formalizar-tipo-group">
                        <button type="button" class="crm-formalizar-tipo-btn active" data-tipo="Orçamento"
                            style="padding:5px 14px;font-size:12px;border:1px solid #6366f1;border-radius:20px;cursor:pointer;background:#6366f1;color:#fff;font-weight:600">Or&ccedil;amento</button>
                        <button type="button" class="crm-formalizar-tipo-btn" data-tipo="Proposta"
                            style="padding:5px 14px;font-size:12px;border:1px solid #d1d5db;border-radius:20px;cursor:pointer;background:#fff;color:#374151">Proposta</button>
                        <button type="button" class="crm-formalizar-tipo-btn" data-tipo="Pedido"
                            style="padding:5px 14px;font-size:12px;border:1px solid #d1d5db;border-radius:20px;cursor:pointer;background:#fff;color:#374151">Pedido</button>
                    </div>
                </div>

                <!-- Itens -->
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <label style="font-size:12px;font-weight:600;color:#64748b">Itens</label>
                        <button type="button" id="crm-formalizar-add-item"
                            style="font-size:11px;padding:3px 10px;border:1px solid #6366f1;border-radius:4px;background:#fff;color:#6366f1;cursor:pointer">+ Item</button>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#f8fafc">
                                <th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:11px;color:#64748b">Descri&ccedil;&atilde;o</th>
                                <th style="text-align:center;padding:6px 4px;border-bottom:1px solid #e2e8f0;width:52px;font-weight:600;font-size:11px;color:#64748b">Qtd</th>
                                <th style="text-align:right;padding:6px 8px;border-bottom:1px solid #e2e8f0;width:92px;font-weight:600;font-size:11px;color:#64748b">Valor (R$)</th>
                                <th style="width:26px;border-bottom:1px solid #e2e8f0"></th>
                            </tr>
                        </thead>
                        <tbody id="crm-formalizar-itens-body"></tbody>
                    </table>
                    <div style="text-align:right;font-size:13px;font-weight:700;margin-top:8px;color:#1e293b">
                        Total: <span id="crm-formalizar-total">R$ 0,00</span>
                    </div>
                </div>

                <!-- Pagamento -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px">Condi&ccedil;&otilde;es de pagamento</label>
                    <input type="text" id="crm-formalizar-pagamento" list="crm-formalizar-pagamento-list"
                        style="width:100%;font-size:13px;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"
                        placeholder="Selecione ou digite...">
                    <datalist id="crm-formalizar-pagamento-list">
                        <option value="&Agrave; vista (PIX / dinheiro)">
                        <option value="Cart&atilde;o de cr&eacute;dito &agrave; vista">
                        <option value="Cart&atilde;o de cr&eacute;dito 2x sem juros">
                        <option value="Cart&atilde;o de cr&eacute;dito 3x sem juros">
                        <option value="50% entrada + 50% na entrega">
                        <option value="50% entrada + 50% em 30 dias">
                        <option value="30 dias">
                        <option value="30 / 60 dias">
                        <option value="30 / 60 / 90 dias">
                        <option value="Boleto banc&aacute;rio 28 dias">
                        <option value="Combinar">
                    </datalist>
                </div>

                <!-- Prazo -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px">Validade / Prazo de entrega</label>
                    <input type="text" id="crm-formalizar-prazo" list="crm-formalizar-prazo-list"
                        style="width:100%;font-size:13px;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"
                        placeholder="Ex: V&aacute;lido por 7 dias...">
                    <datalist id="crm-formalizar-prazo-list">
                        <option value="V&aacute;lido por 7 dias">
                        <option value="V&aacute;lido por 15 dias">
                        <option value="V&aacute;lido por 30 dias">
                        <option value="Entrega em 3 dias &uacute;teis">
                        <option value="Entrega em 5 dias &uacute;teis">
                        <option value="Entrega em 7 dias &uacute;teis">
                        <option value="Entrega imediata">
                        <option value="A combinar">
                    </datalist>
                </div>

                <!-- Observações -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px">Observa&ccedil;&otilde;es <span style="font-weight:400">(opcional)</span></label>
                    <textarea id="crm-formalizar-obs" rows="2"
                        style="width:100%;font-size:13px;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;resize:vertical;box-sizing:border-box"
                        placeholder="Informa&ccedil;&otilde;es adicionais..."></textarea>
                </div>

                <!-- Preview -->
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:6px">Preview da mensagem</label>
                    <div id="crm-formalizar-preview"
                        style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;font-size:12px;line-height:1.7;white-space:pre-wrap;color:#1e293b;min-height:80px;font-family:monospace;word-break:break-word"></div>
                </div>

            </div>
            <div class="tao-crm-modal-footer">
                <button type="button" class="button" id="crm-formalizar-nota-btn">Salvar como Nota</button>
                <button type="button" class="button button-primary" id="crm-formalizar-whatsapp-btn">&#x1F4F2; Enviar no WhatsApp</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    var taoCrmCardId       = <?php echo wp_json_encode( $card_id ); ?>;
    window._crmItensTotal    = 0;
    window._crmFormulasTotal = 0;
    window.atualizarOportunidade = (function () {
        var _timer;
        return function () {
            var tot = (window._crmItensTotal || 0) + (window._crmFormulasTotal || 0);
            var inp = document.getElementById('crm-valor-oportunidade');
            if (!inp) return;
            inp.value = tot > 0 ? tot.toFixed(2) : '';
            clearTimeout(_timer);
            _timer = setTimeout(function () {
                if (!window.taoCrm || !taoCrmCardId) return;
                var p = new URLSearchParams({
                    action: 'tao_crm_update_card',
                    card_id: taoCrmCardId,
                    field: 'valor_oportunidade',
                    value: inp.value,
                    _wpnonce: taoCrm.nonce
                });
                fetch(taoCrm.ajax_url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: p });
            }, 800);
        };
    })();
    var taoCrmLastMsg      = <?php echo wp_json_encode( end( $msgs ) ? end( $msgs )['enviado_em'] : '' ); ?>;
    var taoCrmCurrentStage = <?php echo wp_json_encode( $card['estagio_id'] ); ?>;
    var taoCrmWorkspaceId  = <?php echo wp_json_encode( $card['workspace_id'] ); ?>;
    <?php
    $rgs = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$card['pipeline_id']}&tipo=eq.ganho&limit=1" );
    $ganho_stage_id = ( $rgs['ok'] && ! empty( $rgs['data'] ) ) ? $rgs['data'][0]['id'] : '';

    // Pré-fetch campos obrigatórios do estágio ganho para evitar dependência de AJAX
    $ganho_campos_js  = [];
    $ganho_valores_js = [];
    if ( $ganho_stage_id ) {
        $rce = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.{$ganho_stage_id}&na_entrada=eq.true&order=ordem.asc" );
        if ( $rce['ok'] && ! empty( $rce['data'] ) ) {
            $cids    = array_column( $rce['data'], 'campo_id' );
            $obr_map = array_column( $rce['data'], 'obrigatorio', 'campo_id' );
            $rcd     = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $cids ) . ')&select=id,nome,tipo,opcoes' );
            foreach ( ( $rcd['ok'] ? ( $rcd['data'] ?? [] ) : [] ) as $d ) {
                $ganho_campos_js[] = [
                    'id'          => $d['id'],
                    'nome'        => $d['nome'],
                    'tipo'        => $d['tipo'],
                    'opcoes'      => $d['opcoes'] ?? null,
                    'obrigatorio' => ! empty( $obr_map[ $d['id'] ] ),
                ];
            }
            if ( $cids ) {
                $rv = tao_crm_api( "/crm_card_campos?card_id=eq.$card_id&campo_id=in.(" . implode( ',', $cids ) . ")" );
                foreach ( ( $rv['ok'] ? ( $rv['data'] ?? [] ) : [] ) as $v ) {
                    $ganho_valores_js[ $v['campo_id'] ] = $v['valor'];
                }
            }
        }
    }
    ?>
    var taoCrmGanhoStageId = <?php echo wp_json_encode( $ganho_stage_id ); ?>;
    var taoCrmGanhoCampos  = <?php echo wp_json_encode( $ganho_campos_js ); ?>;
    var taoCrmGanhoValores = <?php echo wp_json_encode( $ganho_valores_js ); ?>;
    var taoCrmKanbanUrl    = <?php echo wp_json_encode( tao_crm_url( [ 'workspace_id' => $card['workspace_id'], 'pipeline_id' => $card['pipeline_id'] ] ) ); ?>;
    var taoCrmCardTagIds   = <?php echo wp_json_encode( $card_tag_ids ); ?>;
    var taoCrmAllTags      = <?php echo wp_json_encode( $all_tags ); ?>;
    var taoCrmLembretes    = <?php echo wp_json_encode( $lembretes ); ?>;
    var taoCrmContatoNome  = <?php echo wp_json_encode( $card['contato_nome'] ?? '' ); ?>;

    // ── Valor de oportunidade ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {

        // Salvar valor de oportunidade
        var valorSaveBtn = document.getElementById('crm-valor-save');
        if ( valorSaveBtn ) {
            valorSaveBtn.addEventListener('click', function() {
                var val = document.getElementById('crm-valor-oportunidade').value;
                var st  = document.getElementById('crm-valor-status');
                fetch( ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'tao_crm_update_card',
                        card_id: taoCrmCardId,
                        field: 'valor_oportunidade',
                        value: val,
                        _wpnonce: taoCrmNonce
                    })
                }).then(function(r){ return r.json(); }).then(function(d) {
                    if ( d.ok ) {
                        st.style.display = 'inline';
                        setTimeout(function(){ st.style.display = 'none'; }, 2000);
                    } else {
                        alert('Erro ao salvar valor.');
                    }
                });
            });
        }

        // ── Etiquetas (tags) ─────────────────────────────────────────────
        var tagsEditBtn  = document.getElementById('crm-tags-edit-btn');
        var tagsPicker   = document.getElementById('crm-tags-picker');
        var tagsSaveBtn  = document.getElementById('crm-tags-save');
        var tagsCancelBtn = document.getElementById('crm-tags-cancel');
        var tagsStatus   = document.getElementById('crm-tags-status');

        if ( tagsEditBtn && tagsPicker ) {
            tagsEditBtn.addEventListener('click', function() {
                tagsPicker.style.display = tagsPicker.style.display === 'none' ? 'block' : 'none';
            });
            if ( tagsCancelBtn ) {
                tagsCancelBtn.addEventListener('click', function() {
                    tagsPicker.style.display = 'none';
                });
            }
            if ( tagsSaveBtn ) {
                tagsSaveBtn.addEventListener('click', function() {
                    var checked = Array.from( document.querySelectorAll('.crm-tag-checkbox:checked') ).map(function(cb){ return cb.value; });
                    fetch( ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'tao_crm_set_card_tags',
                            card_id: taoCrmCardId,
                            tag_ids: JSON.stringify( checked ),
                            _wpnonce: taoCrmNonce
                        })
                    }).then(function(r){ return r.json(); }).then(function(d) {
                        if ( d.ok ) {
                            // Atualizar pills no display
                            var display = document.getElementById('crm-card-tags-display');
                            if ( display ) {
                                display.innerHTML = '';
                                taoCrmAllTags.forEach(function(tag) {
                                    if ( checked.indexOf( String(tag.id) ) === -1 ) return;
                                    var tc = tag.cor || '#6366f1';
                                    var pill = document.createElement('span');
                                    pill.className = 'crm-tag-pill';
                                    pill.style.cssText = 'background:' + tc + '20;color:' + tc + ';border:1px solid ' + tc + '40;padding:2px 8px;border-radius:12px;font-size:11px';
                                    pill.textContent = tag.nome;
                                    display.appendChild( pill );
                                });
                            }
                            tagsStatus.style.display = 'inline';
                            setTimeout(function(){ tagsStatus.style.display = 'none'; tagsPicker.style.display = 'none'; }, 1500);
                        } else {
                            alert('Erro ao salvar etiquetas.');
                        }
                    });
                });
            }
        }

        // ── Lembretes ────────────────────────────────────────────────────
        // Marcar lembrete como concluído
        document.querySelectorAll('.crm-lem-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var lid = this.dataset.id;
                fetch( ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'tao_crm_update_lembrete',
                        lembrete_id: lid,
                        completado: this.checked ? '1' : '0',
                        _wpnonce: taoCrmNonce
                    })
                });
                var item = this.closest('.crm-lembrete-item');
                if ( item ) {
                    if ( this.checked ) item.classList.add('lembrete-completado');
                    else item.classList.remove('lembrete-completado');
                }
            });
        });

        // Excluir lembrete
        document.querySelectorAll('.crm-lem-delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if ( ! confirm('Excluir este lembrete?') ) return;
                var lid = this.dataset.id;
                fetch( ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'tao_crm_delete_lembrete',
                        lembrete_id: lid,
                        _wpnonce: taoCrmNonce
                    })
                }).then(function(r){ return r.json(); }).then(function(d) {
                    if ( d.ok ) {
                        var item = document.querySelector('.crm-lembrete-item[data-lembrete-id="' + lid + '"]');
                        if ( item ) item.remove();
                    }
                });
            });
        });

        // Adicionar lembrete
        var lemAddBtn = document.getElementById('crm-lem-add');
        if ( lemAddBtn ) {
            lemAddBtn.addEventListener('click', function() {
                var titulo = document.getElementById('crm-lem-titulo').value.trim();
                var data   = document.getElementById('crm-lem-data').value;
                var desc   = document.getElementById('crm-lem-desc').value.trim();
                var st     = document.getElementById('crm-lem-status');
                if ( ! titulo ) { alert('Informe o título do lembrete.'); return; }
                fetch( ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'tao_crm_add_lembrete',
                        card_id: taoCrmCardId,
                        titulo: titulo,
                        data_hora: data,
                        descricao: desc,
                        _wpnonce: taoCrmNonce
                    })
                }).then(function(r){ return r.json(); }).then(function(d) {
                    if ( d.ok ) {
                        st.style.display = 'inline';
                        setTimeout(function(){ st.style.display = 'none'; }, 2000);
                        document.getElementById('crm-lem-titulo').value = '';
                        document.getElementById('crm-lem-data').value   = '';
                        document.getElementById('crm-lem-desc').value   = '';
                        // Adicionar item à lista sem recarregar a página
                        var list = document.getElementById('crm-lembretes-list');
                        var noItem = list.querySelector('p');
                        if ( noItem ) noItem.remove();
                        var div = document.createElement('div');
                        div.className = 'crm-lembrete-item';
                        div.dataset.lembreteId = d.id;
                        div.style.cssText = 'display:flex;align-items:flex-start;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px';
                        div.innerHTML = '<input type="checkbox" class="crm-lem-check" data-id="' + d.id + '">'
                            + '<div style="flex:1"><div style="font-weight:600">' + titulo + '</div>'
                            + '<div style="color:#64748b">' + ( data ? data.replace('T',' ') : '—' ) + '</div>'
                            + ( desc ? '<div style="color:#94a3b8;font-size:11px">' + desc + '</div>' : '' )
                            + '</div>'
                            + '<button type="button" class="crm-lem-delete" data-id="' + d.id + '" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;padding:0 2px">✕</button>';
                        list.appendChild( div );
                    } else {
                        alert('Erro ao adicionar lembrete.');
                    }
                });
            });
        }

        // Transfer modal
        document.getElementById('crm-transfer-btn') && document.getElementById('crm-transfer-btn').addEventListener('click', function(){
            document.getElementById('crm-transfer-modal').style.display = 'flex';
        });
        document.getElementById('crm-transfer-cancel') && document.getElementById('crm-transfer-cancel').addEventListener('click', function(){
            document.getElementById('crm-transfer-modal').style.display = 'none';
        });
        (function(){
            var confirmBtn = document.getElementById('crm-transfer-confirm');
            if (!confirmBtn) return;
            confirmBtn.addEventListener('click', function(){
                var uid = document.getElementById('crm-transfer-user').value;
                var msg = document.getElementById('crm-transfer-msg').value.trim();
                var btn = this;
                btn.disabled = true; btn.textContent = 'Transferindo...';
                var params = new URLSearchParams({
                    action: 'tao_crm_transferir_card',
                    _wpnonce: taoCrm ? taoCrm.nonce : taoCrmNonce,
                    card_id: taoCrmCardId,
                    novo_responsavel_id: uid,
                    mensagem_interna: msg
                });
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: params
                }).then(function(r){ return r.json(); }).then(function(r){
                    btn.disabled = false; btn.textContent = 'Transferir';
                    var st = document.getElementById('crm-transfer-status');
                    if (r.success) {
                        st.style.color = 'green';
                        st.textContent = '✔ Transferido para ' + (r.data && r.data.responsavel_nome ? r.data.responsavel_nome : '');
                        setTimeout(function(){
                            document.getElementById('crm-transfer-modal').style.display = 'none';
                            location.reload();
                        }, 1500);
                    } else {
                        st.style.color = 'red';
                        st.textContent = '✘ ' + (r.data || 'Erro');
                    }
                }).catch(function(){
                    btn.disabled = false; btn.textContent = 'Transferir';
                    document.getElementById('crm-transfer-status').textContent = 'Erro de rede';
                });
            });
        })();

        // ── Comentários internos ──────────────────────────────────────────
        function taoCrmLoadComentarios() {
            crmPost({action:'tao_crm_get_comentarios', nonce:taoCrm.nonce, card_id:taoCrmCardId}, function(r){
                if (!r.success) return;
                var list = document.getElementById('crm-comentarios-list');
                if (!r.data || !r.data.length) { list.innerHTML='<p style="font-size:12px;color:#94a3b8">Nenhuma nota ainda.</p>'; return; }
                list.innerHTML = r.data.map(function(c){
                    var d = new Date(c.criado_em); var ds = d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
                    return '<div class="crm-coment-item" data-id="'+c.id+'">'
                        +'<div style="font-size:12px;color:#64748b;margin-bottom:3px"><strong>'+escHtml(c.autor_nome||'Equipe')+'</strong> · '+ds
                        +(c.can_delete ? ' <button class="crm-coment-del button-link" data-id="'+c.id+'" style="color:#dc2626;font-size:11px;margin-left:6px">excluir</button>' : '')
                        +'</div>'
                        +'<div style="font-size:13px;white-space:pre-wrap">'+escHtml(c.conteudo)+'</div>'
                        +'</div>';
                }).join('');
            });
        }
        taoCrmLoadComentarios();

        document.getElementById('crm-coment-form').addEventListener('submit', function(e){
            e.preventDefault();
            var txt = document.getElementById('crm-coment-texto').value.trim();
            if (!txt) return;
            crmPost({action:'tao_crm_save_comentario', nonce:taoCrm.nonce, card_id:taoCrmCardId, conteudo:txt}, function(r){
                if (r.success) { document.getElementById('crm-coment-texto').value=''; taoCrmLoadComentarios(); }
            });
        });

        document.addEventListener('click', function(e){
            if (e.target.classList.contains('crm-coment-del')) {
                if (!confirm('Excluir nota?')) return;
                crmPost({action:'tao_crm_delete_comentario', nonce:taoCrm.nonce, id:e.target.dataset.id}, function(r){
                    if (r.success) taoCrmLoadComentarios();
                });
            }
        });

        // ── Devolver ao chatbot ───────────────────────────────────────────
        var devolverBtn = document.getElementById('crm-devolver-chatbot-btn');
        if (devolverBtn) {
            devolverBtn.addEventListener('click', function(){
                if (!confirm('Devolver este cliente ao chatbot? O atendimento humano será encerrado e o TAO voltará a responder.')) return;
                crmPost({action:'tao_crm_devolver_chatbot', nonce:taoCrm.nonce, card_id:taoCrmCardId}, function(r){
                    if (r.success) location.reload();
                    else alert('Erro: ' + (r.data||'Não foi possível devolver ao chatbot'));
                });
            });
        }

        // ── Recuperar atendimento ─────────────────────────────────────────
        var recuperarBtn = document.getElementById('crm-recuperar-atendimento-btn');
        if (recuperarBtn) {
            recuperarBtn.addEventListener('click', function(){
                if (!confirm('Assumir o atendimento? O chatbot será pausado e você assumirá o atendimento manual.')) return;
                crmPost({action:'tao_crm_recuperar_atendimento', nonce:taoCrm.nonce, card_id:taoCrmCardId}, function(r){
                    if (r.success) location.reload();
                    else alert('Erro: ' + (r.data||'Não foi possível recuperar o atendimento'));
                });
            });
        }

        // ── Fechar Pós Vendas (pedido entregue) ──────────────────────────
        var fecharPvBtn = document.getElementById('crm-fechar-pv-btn');
        if (fecharPvBtn) {
            fecharPvBtn.addEventListener('click', function(){
                if (!confirm('Confirmar que o pedido foi entregue? O card será fechado.')) return;
                crmPost({action:'tao_crm_fechar_card', nonce:taoCrm.nonce, card_id:taoCrmCardId, tipo:'ganho', motivo:'Pedido entregue'}, function(r){
                    if (r.success) { alert('Pedido marcado como entregue!'); location.reload(); }
                    else alert('Erro: ' + (r.data||'Não foi possível fechar'));
                });
            });
        }

        // ── Reabrir card ──────────────────────────────────────────────────
        var reabrirBtn = document.getElementById('crm-reabrir-btn');
        if (reabrirBtn) {
            reabrirBtn.addEventListener('click', function(){
                if (!confirm('Reabrir este card?')) return;
                crmPost({action:'tao_crm_reabrir_card', nonce:taoCrm.nonce, card_id:taoCrmCardId}, function(r){
                    if (r.success) location.reload();
                    else alert('Erro: ' + (r.data||'Não foi possível reabrir'));
                });
            });
        }

        // ── Agendar mensagem ──────────────────────────────────────────────
        var agendarBtn = document.getElementById('crm-agendar-btn');
        if (agendarBtn) {
            agendarBtn.addEventListener('click', function(){
                document.getElementById('crm-agendar-form').style.display = 'block';
            });
            document.getElementById('crm-agendar-cancelar').addEventListener('click', function(){
                document.getElementById('crm-agendar-form').style.display = 'none';
            });
            document.getElementById('crm-agendar-salvar').addEventListener('click', function(){
                var txt    = document.getElementById('crm-agendar-texto').value.trim();
                var quando = document.getElementById('crm-agendar-quando').value;
                var st     = document.getElementById('crm-agendar-status');
                if (!txt || !quando) { st.textContent='Preencha mensagem e data/hora'; st.style.color='red'; return; }
                crmPost({action:'tao_crm_save_msg_agendada', nonce:taoCrm.nonce, card_id:taoCrmCardId, conteudo:txt, agendado_para:quando}, function(r){
                    if (r.success) {
                        st.style.color='green'; st.textContent='✔ Agendada!';
                        setTimeout(function(){ document.getElementById('crm-agendar-form').style.display='none'; st.textContent=''; }, 2000);
                    } else { st.style.color='red'; st.textContent='Erro: '+(r.data||''); }
                });
            });
        }

    }); // DOMContentLoaded
    </script>

    <?php if ( function_exists( 'tao_formula_can_access' ) && tao_formula_can_access() ) : ?>
    <script>
    (function () {
        if (!window.taofNovoUrl) return;

        var modal         = document.getElementById('taof-crm-modal');
        var iframe        = document.getElementById('taof-crm-iframe');
        var novoBtn       = document.getElementById('crm-formula-novo-btn');
        var enviarBtn     = document.getElementById('crm-formula-enviar-btn');
        var reprocessarBtn= document.getElementById('crm-formula-reprocessar-btn');
        var exclSelBtn    = document.getElementById('crm-formula-excluir-sel-btn');
        var listDiv       = document.getElementById('crm-formulas-list');
        var cardId   = window.taofCrmCardId;
        var baseUrl  = window.taofNovoUrl;
        var taofNonce= window.taofNonce;
        var ajaxUrl  = window.taofAjaxUrl;

        // ── Abre modal ────────────────────────────────────────────────
        function abrirModal(src) {
            iframe.src = src;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function fecharModal() {
            modal.style.display = 'none';
            iframe.src = '';
            document.body.style.overflow = '';
        }

        // Botão Novo Orçamento
        if (novoBtn) {
            novoBtn.addEventListener('click', function () {
                var src = baseUrl
                    + '&modal=1'
                    + '&card_id=' + encodeURIComponent(cardId)
                    + '&nome='    + encodeURIComponent(window.taofCrmNome  || '')
                    + '&whatsapp='+ encodeURIComponent(window.taofCrmWa    || '');
                abrirModal(src);
            });
        }

        // Clique fora do iframe fecha modal
        modal.addEventListener('click', function (e) {
            if (e.target === modal) fecharModal();
        });

        // postMessage do iframe
        window.addEventListener('message', function (e) {
            if (!e.data || !e.data.taofSaved && !e.data.taofClosed) return;
            fecharModal();
            if (e.data.taofSaved) carregarFormulas();
        });

        // ── Carrega lista de orçamentos do card ───────────────────────
        function statusLabel(st) {
            var m = {
                pendente_revisao: ['⏳ Pendente', '#fef3c7', '#92400e'],
                aprovado_farma:   ['✅ Aprovado',  '#dcfce7', '#166534'],
                enviado_paciente: ['📤 Enviado',   '#dbeafe', '#1d4ed8'],
                aceito_paciente:  ['🎉 Aceito',    '#dcfce7', '#166534'],
                rejeitado:        ['❌ Rejeitado', '#fee2e2', '#991b1b'],
            };
            return m[st] || [st, '#f1f5f9', '#475569'];
        }

        function carregarFormulas() {
            if (!listDiv) return;
            listDiv.innerHTML = '<span style="color:#94a3b8">Carregando...</span>';
            if (enviarBtn)      enviarBtn.style.display      = 'none';
            if (reprocessarBtn) reprocessarBtn.style.display = 'none';
            if (exclSelBtn)     exclSelBtn.style.display     = 'none';

            var params = new URLSearchParams({
                action:  'tao_formula_get_orcamentos_card',
                nonce:   taofNonce,
                card_id: cardId
            });
            fetch(ajaxUrl + '?' + params.toString())
                .then(function(r){ return r.text(); })
                .then(function(txt) {
                    var resp;
                    try { resp = JSON.parse(txt); }
                    catch(e) {
                        console.error('[TAO Fórmula] resposta não-JSON (raw):', txt.slice(0, 800));
                        throw e;
                    }
                    if (!resp.success || !Array.isArray(resp.data) || !resp.data.length) {
                        listDiv.innerHTML = '<span style="color:#94a3b8;font-size:12px">Nenhum orçamento.</span>';
                        return;
                    }
                    var html = '<table style="width:100%;border-collapse:collapse;font-size:12px">';
                    var anyCheck = false;
                    var anyPendente = false;
                    resp.data.forEach(function (o) {
                        var sl   = statusLabel(o.status);
                        var dt   = o.criado_em ? new Date(o.criado_em).toLocaleDateString('pt-BR') : '—';
                        var tot  = parseFloat(o.total_orcamento || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                        var canSend = (o.status !== 'rejeitado');
                        if (canSend) anyCheck = true;
                        // Extrai primeiro ativo dos itens para compor descrição
                        var itens = [];
                        try { itens = typeof o.itens === 'string' ? JSON.parse(o.itens) : (o.itens || []); } catch(e){}
                        var priAtivo = '';
                        for (var ii = 0; ii < itens.length; ii++) { if (itens[ii].nome) { priAtivo = itens[ii].nome; break; } }
                        if (!anyPendente) {
                            for (var jj = 0; jj < itens.length; jj++) { if (!itens[jj].ativo_id) { anyPendente = true; break; } }
                        }
                        var descOrc = (o.forma_nome || '—') + (priAtivo ? ' — ' + priAtivo : '');
                        var editUrl = baseUrl
                            + '&modal=1'
                            + '&orc_id='  + encodeURIComponent(o.id)
                            + '&card_id=' + encodeURIComponent(cardId);
                        html += '<tr style="border-bottom:1px solid #f1f5f9">'
                            + '<td style="padding:5px 2px;width:20px">'
                            + (canSend ? '<input type="checkbox" class="taof-orc-check" value="' + o.id + '" style="cursor:pointer">' : '')
                            + '</td>'
                            + '<td style="padding:5px 4px;font-weight:600;color:#0f172a">' + (o.numero_orcamento || '—') + '</td>'
                            + '<td style="padding:5px 4px;color:#475569;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + descOrc.replace(/"/g,'&quot;') + '">' + descOrc + '</td>'
                            + '<td style="padding:5px 4px;text-align:right;font-weight:700;color:#0f172a;white-space:nowrap">' + tot + '</td>'
                            + '<td style="padding:5px 4px">'
                            + '<span style="background:' + sl[1] + ';color:' + sl[2] + ';border-radius:10px;padding:1px 7px;font-size:10px;font-weight:600">' + sl[0] + '</span>'
                            + '</td>'
                            + '<td style="padding:5px 2px;color:#94a3b8">' + dt + '</td>'
                            + '<td style="padding:5px 2px;white-space:nowrap">'
                            + (!window.taofCrmFechado ? '<button class="button button-small taof-orc-editar" data-url="' + editUrl + '" style="font-size:10px;padding:2px 6px">✏</button> ' : '')
                            + (!window.taofCrmFechado ? '<button class="button button-small taof-orc-excluir" data-id="' + o.id + '" data-num="' + (o.numero_orcamento||'') + '" style="font-size:10px;padding:2px 6px;color:#dc2626;border-color:#fca5a5">🗑</button>' : '')
                            + '</td>'
                            + '</tr>';
                    });
                    html += '</table>';
                    listDiv.innerHTML = html;

                    // Soma total das fórmulas e atualiza campo Valor
                    var formulasTotal = resp.data.reduce(function(s, o) {
                        return s + parseFloat(o.total_orcamento || 0);
                    }, 0);
                    window._crmFormulasTotal = formulasTotal;
                    if (typeof window.atualizarOportunidade === 'function') window.atualizarOportunidade();

                    if (enviarBtn)      enviarBtn.style.display      = anyCheck    ? 'inline-block' : 'none';
                    if (reprocessarBtn) reprocessarBtn.style.display = anyPendente ? 'inline-block' : 'none';
                    if (exclSelBtn)     exclSelBtn.style.display     = 'none'; // visível só quando há checks marcados

                    // Atualizar visibilidade do Excluir selecionados ao marcar/desmarcar
                    listDiv.querySelectorAll('.taof-orc-check').forEach(function(cb) {
                        cb.addEventListener('change', function() {
                            var algum = listDiv.querySelectorAll('.taof-orc-check:checked').length > 0;
                            if (exclSelBtn) exclSelBtn.style.display = algum ? 'inline-block' : 'none';
                        });
                    });

                    // Botões editar
                    listDiv.querySelectorAll('.taof-orc-editar').forEach(function (btn) {
                        btn.addEventListener('click', function () { abrirModal(this.dataset.url); });
                    });

                    // Botões excluir
                    listDiv.querySelectorAll('.taof-orc-excluir').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var num = this.dataset.num || 'este orçamento';
                            if (!confirm('Excluir ' + num + '? Esta ação não pode ser desfeita.')) return;
                            var id = this.dataset.id;
                            var fd = new FormData();
                            fd.append('action',  'tao_formula_excluir_orcamento');
                            fd.append('nonce',   taofNonce);
                            fd.append('orc_id',  id);
                            fetch(ajaxUrl, { method:'POST', body:fd })
                                .then(function(r){ return r.json(); })
                                .then(function(r){ if (r.success) carregarFormulas(); });
                        });
                    });
                })
                .catch(function (e) {
                    console.error('[TAO Fórmula] catch carregarFormulas:', e);
                    listDiv.innerHTML = '<span style="color:#dc2626;font-size:12px">Erro ao carregar. Veja console (F12).</span>';
                });
        }

        // ── Enviar WhatsApp ───────────────────────────────────────────
        if (enviarBtn) {
            enviarBtn.addEventListener('click', function () {
                var checks = listDiv.querySelectorAll('.taof-orc-check:checked');
                if (!checks.length) { alert('Selecione ao menos um orçamento para enviar.'); return; }
                if (!confirm('Enviar ' + checks.length + ' orçamento(s) via WhatsApp?')) return;

                var ids = Array.from(checks).map(function(c){ return c.value; });
                enviarBtn.disabled = true;
                enviarBtn.textContent = 'Enviando...';

                var body = new URLSearchParams({ action: 'tao_crm_enviar_orcamento_formula', nonce: taoCrm.nonce, card_id: cardId });
                ids.forEach(function(id){ body.append('orc_ids[]', id); });

                fetch(ajaxUrl, { method: 'POST', body: body })
                    .then(function(r){ return r.json(); })
                    .then(function(resp) {
                        enviarBtn.disabled = false;
                        enviarBtn.textContent = '📤 Enviar WhatsApp';
                        if (resp.success) {
                            carregarFormulas();
                            // Mostra mensagem no chat (reload parcial)
                            setTimeout(function(){ location.reload(); }, 600);
                        } else {
                            alert('Erro: ' + (resp.data || 'desconhecido'));
                        }
                    })
                    .catch(function() {
                        enviarBtn.disabled = false;
                        enviarBtn.textContent = '📤 Enviar WhatsApp';
                        alert('Falha de comunicação ao enviar.');
                    });
            });
        }

        // ── Reprocessar ativos pendentes ──────────────────────────────
        if (reprocessarBtn) {
            reprocessarBtn.addEventListener('click', function () {
                reprocessarBtn.disabled = true;
                reprocessarBtn.textContent = 'Reprocessando...';
                var fd = new FormData();
                fd.append('action',  'tao_formula_reprocessar_orc');
                fd.append('nonce',   taofNonce);
                fd.append('card_id', cardId);
                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(resp) {
                        reprocessarBtn.disabled = false;
                        reprocessarBtn.textContent = '🔄 Reprocessar';
                        if (resp.success) {
                            carregarFormulas();
                            var msg = resp.data && resp.data.atualizados
                                ? resp.data.atualizados + ' ativo(s) associado(s) com sucesso!'
                                : (resp.data && resp.data.message) || 'Concluído.';
                            alert(msg);
                        } else {
                            alert('Erro: ' + (resp.data && resp.data.message ? resp.data.message : JSON.stringify(resp.data)));
                        }
                    })
                    .catch(function() {
                        reprocessarBtn.disabled = false;
                        reprocessarBtn.textContent = '🔄 Reprocessar';
                        alert('Falha de comunicação.');
                    });
            });
        }

        // ── Excluir selecionados ───────────────────────────────────────
        if (exclSelBtn) {
            exclSelBtn.addEventListener('click', function () {
                var checks = Array.from(listDiv.querySelectorAll('.taof-orc-check:checked'));
                if (!checks.length) return;
                if (!confirm('Excluir ' + checks.length + ' orçamento(s) selecionado(s)? Esta ação não pode ser desfeita.')) return;
                exclSelBtn.disabled = true;
                exclSelBtn.textContent = 'Excluindo...';
                var ids = checks.map(function(c){ return c.value; });
                var promessas = ids.map(function(id) {
                    var fd = new FormData();
                    fd.append('action',  'tao_formula_excluir_orcamento');
                    fd.append('nonce',   taofNonce);
                    fd.append('orc_id',  id);
                    return fetch(ajaxUrl, { method: 'POST', body: fd }).then(function(r){ return r.json(); });
                });
                Promise.all(promessas).then(function() {
                    exclSelBtn.disabled = false;
                    exclSelBtn.textContent = '🗑 Excluir selecionados';
                    carregarFormulas();
                }).catch(function() {
                    exclSelBtn.disabled = false;
                    exclSelBtn.textContent = '🗑 Excluir selecionados';
                    carregarFormulas();
                });
            });
        }

        // Carrega ao abrir a página
        carregarFormulas();

        // ── Upload / Paste de Receita ─────────────────────────────────
        (function () {
            var dropzone    = document.getElementById('crm-receita-dropzone');
            var fileInput   = document.getElementById('crm-receita-file');
            var previewEl   = document.getElementById('crm-receita-preview');
            var processBtn  = document.getElementById('crm-receita-processar');
            var limparBtn   = document.getElementById('crm-receita-limpar');
            var statusEl    = document.getElementById('crm-receita-status');
            if (!dropzone) return;   // card fechado não tem o painel

            function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

            var selectedFile = null;

            function setFile(file) {
                var ok = /^image\/(jpeg|png|gif|webp)$/.test(file.type) || file.type === 'application/pdf';
                if (!ok) { setStatus('Tipo não suportado (use JPG, PNG ou PDF)', '#dc2626'); return; }
                if (file.size > 20 * 1024 * 1024) { setStatus('Arquivo muito grande (máx 20MB)', '#dc2626'); return; }
                selectedFile = file;
                previewEl.style.display  = 'block';
                previewEl.innerHTML      = '📄 <strong>' + escHtml(file.name || 'imagem') + '</strong> ('
                                         + (file.size / 1024).toFixed(0) + ' KB)';
                processBtn.style.display = 'inline-block';
                limparBtn.style.display  = 'inline-block';
                dropzone.style.borderColor = '#0ea5e9';
                setStatus('', '');
            }

            function limpar() {
                selectedFile = null;
                previewEl.style.display  = 'none';
                processBtn.style.display = 'none';
                limparBtn.style.display  = 'none';
                dropzone.style.borderColor = '#cbd5e1';
                fileInput.value = '';
                setStatus('', '');
            }

            function setStatus(msg, cor) {
                if (!statusEl) return;
                statusEl.textContent  = msg;
                statusEl.style.color  = cor || '#64748b';
            }

            // Clique na zona abre o seletor (exceto no label que já abre)
            dropzone.addEventListener('click', function (e) {
                if (e.target.tagName !== 'LABEL') fileInput.click();
            });

            fileInput.addEventListener('change', function () {
                if (this.files[0]) setFile(this.files[0]);
            });

            // Drag & Drop
            dropzone.addEventListener('dragover',  function (e) { e.preventDefault(); dropzone.style.borderColor = '#0ea5e9'; });
            dropzone.addEventListener('dragleave', function ()   { if (!selectedFile) dropzone.style.borderColor = '#cbd5e1'; });
            dropzone.addEventListener('drop',      function (e)  { e.preventDefault(); if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]); });

            // Paste (Ctrl+V) — captura imagem da área de transferência
            document.addEventListener('paste', function (e) {
                var items = e.clipboardData && e.clipboardData.items;
                if (!items) return;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        var blob = items[i].getAsFile();
                        if (blob) {
                            setFile(new File([blob], 'receita-colada.png', { type: blob.type }));
                        }
                        break;
                    }
                }
            });

            limparBtn.addEventListener('click', limpar);

            // Registro rápido de sinônimos não encontrados
            function mostrarRegistroSinonimos(naoEnc) {
                var section = document.getElementById('crm-receita-section');
                var old = document.getElementById('crm-sin-reg-panel');
                if (old) old.remove();
                if (!naoEnc || !naoEnc.length) return;

                var panel = document.createElement('div');
                panel.id  = 'crm-sin-reg-panel';
                panel.style.cssText = 'margin-top:10px;border:1px solid #fbbf24;border-radius:6px;padding:8px 10px;background:#fffbeb';

                var html = '<div style="font-size:11px;font-weight:600;color:#92400e;margin-bottom:8px">⚠ ' + naoEnc.length + ' ativo(s) não encontrado(s) — associe ao ativo correto:</div>';
                naoEnc.forEach(function(nome, i) {
                    html += '<div class="crm-sin-item" style="display:flex;gap:6px;align-items:center;margin-bottom:6px" data-nome="' + escHtml(nome) + '" data-idx="' + i + '">' +
                        '<span style="font-size:11px;color:#78350f;width:120px;min-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(nome) + '">' + escHtml(nome) + '</span>' +
                        '<div style="flex:1;position:relative">' +
                            '<input type="text" class="crm-sin-busca-input" placeholder="Buscar ativo (↑↓ Enter)…" style="width:100%;font-size:11px;padding:3px 6px;border:1px solid #d1d5db;border-radius:4px;box-sizing:border-box">' +
                            '<div class="crm-sin-sugestoes" style="display:none;position:fixed;background:#fff;border:1px solid #d1d5db;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:999999;min-width:220px;max-height:180px;overflow-y:auto;font-size:11px"></div>' +
                        '</div>' +
                        '<button class="button button-small crm-sin-salvar-btn" disabled style="font-size:10px;padding:2px 8px;white-space:nowrap" data-ativo-id="" data-ativo-nome="">✓ Salvar</button>' +
                        '<span class="crm-sin-msg" style="font-size:10px;color:#16a34a;min-width:20px"></span>' +
                        '</div>';
                });
                panel.innerHTML = html;
                section.appendChild(panel);

                var items = Array.from(panel.querySelectorAll('.crm-sin-item'));

                items.forEach(function(item, itemIdx) {
                    var sinonimo  = item.dataset.nome;
                    var input     = item.querySelector('.crm-sin-busca-input');
                    var sugestoes = item.querySelector('.crm-sin-sugestoes');
                    var salvarBtn = item.querySelector('.crm-sin-salvar-btn');
                    var msgEl     = item.querySelector('.crm-sin-msg');
                    var searchTimer, hlIdx = -1;

                    function getOps() { return Array.from(sugestoes.querySelectorAll('.crm-sin-op')); }

                    function setHL(idx) {
                        var ops = getOps();
                        ops.forEach(function(o){ o.style.background=''; });
                        hlIdx = Math.max(-1, Math.min(idx, ops.length - 1));
                        if (hlIdx >= 0) { ops[hlIdx].style.background='#dbeafe'; ops[hlIdx].scrollIntoView({block:'nearest'}); }
                    }

                    function posDD() {
                        var r = input.getBoundingClientRect();
                        sugestoes.style.top   = (r.bottom + 2) + 'px';
                        sugestoes.style.left  = r.left + 'px';
                        sugestoes.style.width = Math.max(220, r.width) + 'px';
                    }

                    function selectAtivo(id, nome) {
                        input.value = nome;
                        salvarBtn.dataset.ativoId   = id;
                        salvarBtn.dataset.ativoNome = nome;
                        salvarBtn.disabled = false;
                        sugestoes.style.display = 'none';
                        hlIdx = -1;
                    }

                    input.addEventListener('input', function() {
                        clearTimeout(searchTimer);
                        var q = this.value.trim();
                        salvarBtn.disabled = true;
                        salvarBtn.dataset.ativoId = '';
                        if (q.length < 2) { sugestoes.style.display='none'; return; }
                        searchTimer = setTimeout(function() {
                            var fd = new FormData();
                            fd.append('action', 'tao_formula_buscar_ativos');
                            fd.append('nonce',  taofNonce);
                            fd.append('q', q);
                            fetch(ajaxUrl, { method:'POST', body:fd })
                                .then(function(r){ return r.json(); })
                                .then(function(r) {
                                    if (!r.success || !r.data.length) { sugestoes.style.display='none'; return; }
                                    sugestoes.innerHTML = r.data.map(function(a){
                                        return '<div class="crm-sin-op" data-id="'+a.id+'" data-nome="'+escHtml(a.nome)+'" style="padding:5px 8px;cursor:pointer;border-bottom:1px solid #f1f5f9">' +
                                            '<strong>'+escHtml(a.nome)+'</strong>' +
                                            (a.codigo_fc ? '<span style="color:#94a3b8;font-size:10px;margin-left:4px">'+escHtml(a.codigo_fc)+'</span>' : '') +
                                            '</div>';
                                    }).join('');
                                    hlIdx = -1;
                                    posDD();
                                    sugestoes.style.display = 'block';
                                    getOps().forEach(function(op, i) {
                                        op.addEventListener('mouseenter', function(){ setHL(i); });
                                        op.addEventListener('mousedown',  function(e){
                                            e.preventDefault(); // evita blur antes da seleção
                                            selectAtivo(this.dataset.id, this.dataset.nome);
                                        });
                                    });
                                });
                        }, 280);
                    });

                    input.addEventListener('keydown', function(e) {
                        var ops = getOps();
                        var ddVisible = sugestoes.style.display !== 'none';
                        if (ddVisible && ops.length) {
                            if (e.key === 'ArrowDown') { e.preventDefault(); setHL(hlIdx + 1); return; }
                            if (e.key === 'ArrowUp')   { e.preventDefault(); setHL(hlIdx - 1); return; }
                            if (e.key === 'Enter' && hlIdx >= 0) {
                                e.preventDefault();
                                var op = ops[hlIdx];
                                selectAtivo(op.dataset.id, op.dataset.nome);
                                return;
                            }
                            if (e.key === 'Escape') { sugestoes.style.display='none'; hlIdx=-1; return; }
                        }
                        if (e.key === 'Enter' && salvarBtn.dataset.ativoId) {
                            e.preventDefault();
                            salvarBtn.click();
                            return;
                        }
                        if (e.key === 'Tab') {
                            sugestoes.style.display = 'none';
                            var next = items[itemIdx + 1];
                            if (next) { e.preventDefault(); next.querySelector('.crm-sin-busca-input').focus(); }
                        }
                    });

                    input.addEventListener('blur', function() {
                        setTimeout(function(){ sugestoes.style.display='none'; }, 160);
                    });

                    salvarBtn.addEventListener('click', function() {
                        var aid = this.dataset.ativoId;
                        if (!aid) return;
                        var fd = new FormData();
                        fd.append('action',   'tao_formula_salvar_sinonimo');
                        fd.append('nonce',    taofNonce);
                        fd.append('ativo_id', aid);
                        fd.append('sinonimo', sinonimo);
                        fetch(ajaxUrl, { method:'POST', body:fd })
                            .then(function(r){ return r.json(); })
                            .then(function(r) {
                                if (r.success) {
                                    salvarBtn.disabled = true;
                                    msgEl.textContent  = '✓';
                                    item.style.opacity = '0.5';
                                    var next = items[itemIdx + 1];
                                    if (next) next.querySelector('.crm-sin-busca-input').focus();
                                } else {
                                    msgEl.style.color  = '#dc2626';
                                    msgEl.textContent  = 'Erro';
                                }
                            });
                    });
                });

                document.addEventListener('click', function sinCloseDD(e) {
                    if (!panel.contains(e.target)) {
                        panel.querySelectorAll('.crm-sin-sugestoes').forEach(function(s){ s.style.display='none'; });
                    }
                });

                // Foca no primeiro campo automaticamente
                setTimeout(function(){ var f = panel.querySelector('.crm-sin-busca-input'); if(f) f.focus(); }, 80);
            }

            // Processar
            processBtn.addEventListener('click', function () {
                if (!selectedFile) return;
                processBtn.disabled = true;
                setStatus('🤖 Analisando receita…', '#0ea5e9');

                var fd = new FormData();
                fd.append('action',        'tao_formula_processar_receita');
                fd.append('nonce',         taofNonce);
                fd.append('card_id',       cardId);
                fd.append('nome_paciente', window.taofCrmNome || '');
                fd.append('whatsapp',      window.taofCrmWa  || '');
                fd.append('receita_file',  selectedFile);

                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        processBtn.disabled = false;
                        if (resp.success) {
                            var d = resp.data;
                            var msg;
                            if (d.orcamentos && d.orcamentos.length) {
                                var nums = d.orcamentos.map(function(o){ return o.numero; }).join(', ');
                                msg = '✅ ' + d.total + ' orçamento(s) criado(s): ' + nums;
                            } else {
                                msg = '✅ ' + (d.numero || 'Orçamento') + ' criado!';
                            }
                            if (d.nao_encontrados && d.nao_encontrados.length) {
                                msg += ' ⚠ Revisar: ' + d.nao_encontrados.join(', ');
                            }
                            setStatus(msg, '#16a34a');
                            limpar();
                            carregarFormulas();
                            // UI para registrar sinônimos dos não encontrados
                            if (d.nao_encontrados && d.nao_encontrados.length) {
                                mostrarRegistroSinonimos(d.nao_encontrados);
                            }
                        } else {
                            var err = resp.data && resp.data.message ? resp.data.message : JSON.stringify(resp.data);
                            setStatus('❌ ' + err, '#dc2626');
                        }
                    })
                    .catch(function () {
                        processBtn.disabled = false;
                        setStatus('❌ Falha de comunicação', '#dc2626');
                    });
            });
        })();

    })();
    </script>
    <?php endif; ?>
    <?php
}

// ─── RENDER CAMPO INPUT ───────────────────────────────────────────────────────

function tao_crm_render_campo_input( $def, $val, $card_id ) {
    $id    = esc_attr( $def['id'] );
    $tipo  = $def['tipo'] ?? 'text';
    $val_e = esc_attr( $val );
    $attrs = "class='campo-input' data-campo-id='$id' data-card-id='" . esc_attr( $card_id ) . "'";

    if ( $tipo === 'textarea' ) {
        return "<textarea $attrs rows='3'>" . esc_textarea( $val ) . "</textarea>";
    }
    if ( $tipo === 'boolean' ) {
        $chk = $val === '1' || $val === 'true' ? 'checked' : '';
        return "<label class='campo-bool'><input type='checkbox' $attrs $chk value='1'> Sim</label>";
    }
    if ( $tipo === 'select' ) {
        $opcoes = $def['opcoes'] ?? [];
        if ( is_string( $opcoes ) ) $opcoes = json_decode( $opcoes, true ) ?? [];
        $html = "<select $attrs>";
        $html .= "<option value=''>— Selecione —</option>";
        foreach ( (array) $opcoes as $op ) {
            $op_e = esc_attr( $op );
            $sel  = selected( $val, $op, false );
            $html .= "<option value='$op_e' $sel>" . esc_html( $op ) . "</option>";
        }
        $html .= "</select>";
        return $html;
    }
    if ( $tipo === 'arquivo' ) {
        // val format: "STORAGE:{path}:{original_filename}" ou vazio
        $is_stored = str_starts_with( (string) $val, 'STORAGE:' );
        $filename  = '';
        if ( $is_stored ) {
            $parts    = explode( ':', $val, 3 );
            $filename = $parts[2] ?? basename( $parts[1] ?? '' );
        }
        $nonce  = wp_create_nonce( 'tao_crm_nonce' );
        $dl_url = admin_url( 'admin-ajax.php?action=tao_crm_download_campo_arquivo'
                    . '&nonce=' . $nonce
                    . '&card_id=' . urlencode( $card_id )
                    . '&campo_id=' . urlencode( $def['id'] ) );
        $html   = "<div class='campo-arquivo-wrap' data-campo-id='$id' data-card-id='" . esc_attr( $card_id ) . "'>";
        if ( $is_stored ) {
            $html .= "<div class='campo-arquivo-atual'>";
            $html .= "<span>&#x1F4CE;</span> ";
            $html .= "<a href='" . esc_url( $dl_url ) . "' target='_blank' class='campo-arquivo-link'>" . esc_html( $filename ) . "</a> ";
            $html .= "<button type='button' class='button button-small campo-arquivo-trocar' style='font-size:11px'>Trocar</button>";
            $html .= "</div>";
        }
        $ocult  = $is_stored ? " style='display:none'" : '';
        $html  .= "<div class='campo-arquivo-upload'$ocult>";
        $html  .= "<input type='file' class='campo-arquivo-input' style='font-size:12px;max-width:220px'>";
        $html  .= " <span class='campo-arquivo-status' style='font-size:11px'></span>";
        $html  .= "</div>";
        $html  .= "</div>";
        return $html;
    }

    $type_map = [ 'number' => 'number', 'date' => 'date', 'phone' => 'tel', 'email' => 'email' ];
    $input_type = $type_map[ $tipo ] ?? 'text';
    $step = ( $input_type === 'number' ) ? " step='any'" : '';
    return "<input type='$input_type'$step $attrs value='$val_e'>";
}

// ─── RENDER MESSAGE ───────────────────────────────────────────────────────────

function tao_crm_render_message( $msg ) {
    $dir   = $msg['direcao'] === 'out' ? 'out' : 'in';
    $nome  = esc_html( $msg['remetente_nome'] ?? ( $dir === 'out' ? 'Atendente' : 'Cliente' ) );
    $hora  = esc_html( tao_crm_brt( $msg['enviado_em'], 'H:i' ) );
    $texto = esc_html( $msg['conteudo'] ?? '' );
    $tipo  = $msg['tipo'] ?? 'text';
    $midia = esc_url( $msg['midia_url'] ?? '' );

    if ( $tipo === 'text' ) {
        $conteudo = nl2br( $texto );
    } elseif ( $tipo === 'image' && $midia ) {
        $conteudo = "<img src='$midia' class='chat-img' alt='imagem'>";
    } elseif ( $tipo === 'audio' && $midia ) {
        $conteudo = "<audio controls src='$midia'></audio>";
    } elseif ( $tipo === 'document' && $midia ) {
        $conteudo = "<a href='$midia' target='_blank' class='chat-doc'>📄 " . esc_html( basename( $midia ) ) . "</a>";
    } else {
        $conteudo = $texto ?: '<em>[mídia]</em>';
    }

    // Delivery ticks para mensagens enviadas (out)
    $tick = '';
    if ( $dir === 'out' ) {
        $se = $msg['status_entrega'] ?? null;
        if ( $se === 'read' )          $tick = '<span class="msg-tick msg-tick-read" title="Lida">✓✓</span>';
        elseif ( $se === 'delivered' ) $tick = '<span class="msg-tick msg-tick-delivered" title="Entregue">✓✓</span>';
        elseif ( $se === 'sent' )      $tick = '<span class="msg-tick msg-tick-sent" title="Enviada">✓</span>';
        else                           $tick = '<span class="msg-tick msg-tick-pending" title="Aguardando">⏱</span>';
    }

    return "
    <div class='chat-msg $dir'>
        <div class='msg-bubble'>
            <div class='msg-content'>$conteudo</div>
            <div class='msg-meta'>
                <span class='msg-sender'>$nome</span>
                <span class='msg-time'>$hora</span>$tick
            </div>
        </div>
    </div>";
}
