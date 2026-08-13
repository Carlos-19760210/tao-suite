<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Integração do módulo Fórmula com o "ganho" do card (fechar negócio).
 * 100% desacoplado do CRM: o tao-crm só dispara um filtro (trava) e uma ação (criar OM);
 * não conhece o Fórmula. Espelha o padrão do tao-caixa / tao-entregas.
 *
 *  1) tao_crm_veto_fechar_ganho  — TRAVA: não deixa aprovar (fechar ganho) com item MP
 *     cujo ativo não exista na base (sinônimo sem correspondente na fórmula). RDC 67.
 *  2) tao_formula_card_ganho     — cria a OM (idempotente) no instante do ganho, junto de
 *     venda (Caixa) e entrega. O card nasce no Pós-vendas já com a OM.
 *
 * ⚠️ EM TESTES: o fluxo novo fica DESLIGADO por padrão (não trava nem cria OM automática).
 * Nada muda na operação atual até ligar a chave. Ativação por workspace (option
 * tao_formula_om_ganho_ativo_{ws} = '1') ou global (tao_formula_om_ganho_ativo = '1').
 */

/** O fluxo novo (OM no ganho + trava de ativos) está ligado para este workspace? Default: NÃO. */
function tao_formula_om_ganho_ativo( $ws = '' ) {
	if ( $ws && get_option( 'tao_formula_om_ganho_ativo_' . $ws, '' ) === '1' ) return true;
	return get_option( 'tao_formula_om_ganho_ativo', '0' ) === '1';
}

/** Orçamento APROVADO = status aprovado_farma ou aceito_paciente. Só o aprovado vira OM. */
function tao_formula_orc_aprovado( $status ) {
	return in_array( (string) $status, [ 'aprovado_farma', 'aceito_paciente' ], true );
}

/**
 * Varre os orçamentos APROVADOS do card e retorna as descrições dos itens MP SEM ativo correspondente:
 * item sem ativo_id (sinônimo não associado) OU com ativo_id que não existe mais na base.
 * (Orçamento não aprovado não trava por ativo — ele não vira OM.)
 * @return string[] descrições dos itens pendentes (vazio = tudo cadastrado)
 */
function tao_formula_card_ativos_faltando( $card_id ) {
	if ( ! $card_id || ! function_exists( 'tao_formula_api' ) ) return [];
	$ro = tao_formula_api( "/orcamentos?card_id=eq.$card_id&select=id,cliente_id,itens,status" );
	if ( ! $ro['ok'] || empty( $ro['data'] ) ) return [];

	$faltando   = [];   // chave normalizada => descrição (dedup)
	$checar_ids = [];   // ativo_id => ativo_id (a validar existência)
	$por_id     = [];   // ativo_id => [descrições] (p/ reportar se não existir)
	$cliente_id = '';

	foreach ( $ro['data'] as $orc ) {
		if ( ! tao_formula_orc_aprovado( $orc['status'] ?? '' ) ) continue;   // só valida ativos dos APROVADOS
		if ( ! $cliente_id ) $cliente_id = $orc['cliente_id'] ?? '';
		$itens = $orc['itens'] ?? [];
		if ( is_string( $itens ) ) $itens = json_decode( $itens, true ) ?: [];
		foreach ( (array) $itens as $it ) {
			if ( ( $it['tipo'] ?? 'mp' ) !== 'mp' ) continue;
			$desc = trim( (string) ( $it['nome_prescricao'] ?? $it['nome'] ?? '' ) );
			if ( $desc === '' ) $desc = '(item sem nome)';
			$aid = $it['ativo_id'] ?? '';
			if ( ! $aid ) {
				$faltando[ mb_strtoupper( $desc ) ] = $desc;   // sinônimo/termo sem ativo associado
			} else {
				$checar_ids[ $aid ] = $aid;
				$por_id[ $aid ][]   = $desc;
			}
		}
	}

	// Confere se os ativos referenciados existem de fato na base do cliente
	if ( $checar_ids && $cliente_id ) {
		$ids = implode( ',', array_keys( $checar_ids ) );
		$ra  = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&id=in.($ids)&select=id&limit=" . count( $checar_ids ) );
		$existem = [];
		foreach ( ( $ra['ok'] ? ( $ra['data'] ?? [] ) : [] ) as $a ) $existem[ $a['id'] ] = true;
		foreach ( $checar_ids as $aid ) {
			if ( empty( $existem[ $aid ] ) ) {
				foreach ( ( $por_id[ $aid ] ?? [] ) as $d ) $faltando[ mb_strtoupper( $d ) ] = $d;
			}
		}
	}

	return array_values( $faltando );
}

/**
 * TRAVA no fechar-ganho: barra a aprovação enquanto houver ativo faltando.
 * Só veta cards que TÊM orçamento de fórmula (senão retorna o $veto recebido, sem interferir).
 */
add_filter( 'tao_crm_veto_fechar_ganho', 'tao_formula_veto_ganho_ativos', 10, 3 );
function tao_formula_veto_ganho_ativos( $veto, $card_id, $card ) {
	if ( $veto ) return $veto;   // já vetado por outro módulo
	// EM TESTES: só trava se a chave estiver ligada para o workspace. Default: não trava nada.
	if ( ! tao_formula_om_ganho_ativo( $card['workspace_id'] ?? '' ) ) return $veto;
	try {
		// Confirmar no processo: se o card tem orçamentos, ao menos UM precisa estar aprovado
		// (os não aprovados não viram OM). Sem nenhum aprovado, não fecha como ganho.
		$rs = tao_formula_api( "/orcamentos?card_id=eq.$card_id&select=status" );
		$orcs = $rs['ok'] ? ( $rs['data'] ?? [] ) : [];
		if ( $orcs ) {
			$aprovados = 0;
			foreach ( $orcs as $oo ) if ( tao_formula_orc_aprovado( $oo['status'] ?? '' ) ) $aprovados++;
			if ( ! $aprovados ) {
				return [ 'veto' => true, 'code' => 'sem_orc_aprovado',
					'msg' => 'Decida os orçamentos deste card antes de fechar como ganho: aprove ao menos um (ou rejeite os que não valem). Só os aprovados viram Ordem de Manipulação.' ];
			}
		}
		$faltando = tao_formula_card_ativos_faltando( $card_id );
		if ( $faltando ) {
			$lista = implode( ', ', array_slice( $faltando, 0, 8 ) );
			if ( count( $faltando ) > 8 ) $lista .= '…';
			return [
				'veto' => true,
				'code' => 'ativo_sem_correspondente',
				'msg'  => 'Antes de aprovar, associe o ativo destes itens na base do TAO (não pode haver sinônimo sem ativo correspondente): ' . $lista . '.',
			];
		}
	} catch ( \Throwable $e ) {
		return $veto;   // falha na validação nunca bloqueia o fechamento
	}
	return $veto;
}

/**
 * LISTENER do ganho: cria a OM (idempotente) para cada orçamento do card.
 * Isolado em try/catch — nunca quebra o fluxo do CRM. Roda depois da trava de ativos,
 * então quando chega aqui a fórmula já está 100% cadastrada.
 */
add_action( 'tao_formula_card_ganho', 'tao_formula_criar_om_do_card', 10, 2 );
function tao_formula_criar_om_do_card( $card_id, $workspace_id ) {
	try {
		// CAPACIDADE (sempre disponível, com ou sem a chave): cria a OM só dos orçamentos
		// APROVADOS. Se nenhum foi aprovado — o caso da operação normal — nada é criado e a
		// operação segue intacta. A OBRIGATORIEDADE (travar o ganho por falta de aprovação/ativo)
		// é que fica atrás da chave (ver tao_formula_veto_ganho_ativos).
		if ( ! $card_id || ! function_exists( 'tao_formula_api' ) || ! function_exists( 'tao_formula_criar_om' ) ) return;
		$ro = tao_formula_api( "/orcamentos?card_id=eq.$card_id&select=id,cliente_id,status" );
		if ( ! $ro['ok'] || empty( $ro['data'] ) ) return;
		foreach ( $ro['data'] as $orc ) {
			if ( ! tao_formula_orc_aprovado( $orc['status'] ?? '' ) ) continue;   // só os APROVADOS viram OM
			$cid = $orc['cliente_id'] ?? '';
			$oid = $orc['id'] ?? '';
			if ( $cid && $oid ) tao_formula_criar_om( $cid, $oid );   // idempotente: não duplica OM
		}
	} catch ( \Throwable $e ) {
		// listener isolado — silencioso
	}
}

/** Interruptor mestre do fluxo novo (Configurações do Fórmula). Só gestor/admin liga/desliga. */
add_action( 'wp_ajax_tao_formula_toggle_om_ganho', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();   // evita o byte espúrio "?" antes do JSON (portal /robos)
	check_ajax_referer( 'tao_formula_nonce', '_wpnonce' );
	$pode = current_user_can( 'manage_options' )
		|| ( function_exists( 'tao_formula_is_master' ) && tao_formula_is_master() )
		|| ( function_exists( 'cbpm_is_gestor' ) && cbpm_is_gestor() );
	if ( ! $pode ) wp_send_json_error( [ 'message' => 'Apenas gestor/administrador' ], 403 );
	update_option( 'tao_formula_om_ganho_ativo', ( ( $_POST['on'] ?? '0' ) === '1' ) ? '1' : '0' );
	wp_send_json_success();
} );

/**
 * PEÇA 4 — Conclusão da OM na transição do card para "Pronto para Entrega".
 * Escuta o movimento do card (do_action no move_card do CRM). Quando entra em "Pronto para
 * Entrega" e a chave está ligada, conclui a(s) OM(s) do card: baixa de estoque + SNGPC + validade
 * (reusa as funções existentes; idempotente por baixou_estoque). Sem trava — só conclui.
 */
add_action( 'tao_crm_card_movido', 'tao_formula_concluir_om_ao_pronto', 10, 4 );
function tao_formula_concluir_om_ao_pronto( $card_id, $estagio_id, $de_estagio, $card ) {
	try {
		if ( ! tao_formula_om_ganho_ativo( is_array( $card ) ? ( $card['workspace_id'] ?? '' ) : '' ) ) return;
		if ( ! $card_id || ! $estagio_id || ! function_exists( 'tao_formula_api' ) ) return;
		$rn   = tao_formula_api( "/crm_estagios?id=eq.$estagio_id&select=nome&limit=1" );
		$nome = ( $rn['ok'] && ! empty( $rn['data'] ) ) ? mb_strtoupper( $rn['data'][0]['nome'] ?? '' ) : '';
		if ( mb_strpos( $nome, 'PRONTO PARA ENTREGA' ) === false ) return;
		tao_formula_concluir_om_do_card( $card_id );
	} catch ( \Throwable $e ) {}
}

/** Conclui as OMs abertas do card: valida validade pelo lote, baixa estoque + SNGPC, marca concluída. */
function tao_formula_concluir_om_do_card( $card_id ) {
	$ro = tao_formula_api( "/lab_ordens?card_id=eq.$card_id&select=id,cliente_id,baixou_estoque,status" );
	if ( ! $ro['ok'] || empty( $ro['data'] ) ) return;
	foreach ( $ro['data'] as $om ) {
		tao_formula_concluir_uma_om( $om['cliente_id'] ?? '', $om['id'] ?? '', $om );
	}
}

/**
 * Conclui UMA OM: valida validade pelo lote, baixa estoque + SNGPC, marca concluída.
 * Idempotente por baixou_estoque. É AQUI que o estoque mexe — em nenhum outro lugar.
 * @param array|null $om linha já carregada (evita refetch); se null, busca por id+cliente.
 */
function tao_formula_concluir_uma_om( $cid, $oid, $om = null ) {
	if ( ! $cid || ! $oid ) return false;
	if ( $om === null ) {
		$r  = tao_formula_api( "/lab_ordens?id=eq.$oid&cliente_id=eq.$cid&select=id,cliente_id,baixou_estoque,status&limit=1" );
		$om = ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
		if ( ! $om ) return false;
	}
	if ( ( $om['status'] ?? '' ) === 'concluida' && ! empty( $om['baixou_estoque'] ) ) return true; // idempotente
	if ( function_exists( 'tao_formula_recalc_validade_om' ) ) tao_formula_recalc_validade_om( $cid, $oid );
	if ( empty( $om['baixou_estoque'] ) && function_exists( 'tao_formula_baixar_estoque_om' ) ) tao_formula_baixar_estoque_om( $cid, $oid );
	tao_formula_api( "/lab_ordens?id=eq.$oid&cliente_id=eq.$cid", 'PATCH', [
		'status' => 'concluida', 'concluido_em' => gmdate( 'c' ), 'conferente_id' => get_current_user_id(),
	] );
	return true;
}

/**
 * Conclusão EXPLÍCITA de uma OM pela tela Produção (botão "Concluir OM").
 * Disponível SEMPRE (com ou sem a chave) — é o ponto único de baixa de estoque quando a chave
 * está desligada. Nada baixa sozinho: a baixa só acontece quando o operador clica aqui.
 */
add_action( 'wp_ajax_tao_formula_prod_concluir_om', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'msg' => 'Acesso negado' ], 403 );
	$cid = tao_formula_cliente_id();
	$oid = sanitize_text_field( $_POST['ordem_id'] ?? '' );
	if ( ! $cid || ! $oid ) wp_send_json_error( [ 'msg' => 'Parâmetros inválidos' ] );
	$ok = tao_formula_concluir_uma_om( $cid, $oid );
	$ok ? wp_send_json_success() : wp_send_json_error( [ 'msg' => 'OM não encontrada' ] );
} );

// ── Aprovação do ORÇAMENTO no card (qualquer perfil, em testes) — só o aprovado vira OM ──
add_action( 'wp_ajax_tao_formula_orc_aprovar', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
	$cid = tao_formula_cliente_id(); $orc = sanitize_text_field( $_POST['orc_id'] ?? '' );
	if ( ! $cid || ! $orc ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
	$ro_full = tao_formula_api( "/orcamentos?id=eq.$orc&cliente_id=eq.$cid&limit=1" );
	$orc_row = ( $ro_full['ok'] && ! empty( $ro_full['data'] ) ) ? $ro_full['data'][0] : null;
	if ( ! $orc_row ) wp_send_json_error( [ 'message' => 'Orçamento não encontrado' ] );
	// LOCK de card: se OUTRO atendente está com o card aberto, bloqueia a aprovação
	if ( function_exists( 'tao_crm_card_lock_guard' ) && ! empty( $orc_row['card_id'] ) )
		tao_crm_card_lock_guard( $orc_row['card_id'] );

	// ── Validações OBRIGATÓRIAS de aprovação (Carlos 13/08) — sem elas NÃO aprova ──
	$tem    = function ( $v ) { return is_string( $v ) ? trim( $v ) !== '' : ! empty( $v ); };
	$faltas = [];
	// 1. Cliente e paciente
	if ( ! $tem( $orc_row['nome_cliente'] ?? '' ) ) $faltas[] = 'Nome do CLIENTE não informado';
	if ( ! $tem( $orc_row['nome_paciente'] ?? '' ) && ! $tem( $orc_row['paciente_nome'] ?? '' ) )
		$faltas[] = 'Nome do PACIENTE não informado';
	// 2. Prescritor
	if ( ! $tem( $orc_row['prescritor'] ?? '' ) && ! $tem( $orc_row['prescritor_id'] ?? '' ) )
		$faltas[] = 'Prescritor não informado';
	// 3. Posologia
	if ( ! $tem( $orc_row['posologia'] ?? '' ) ) $faltas[] = 'Posologia não informada';
	// 4. Todos os itens (fórmula, cápsulas e embalagens) com valores preenchidos
	$itens_v = $orc_row['itens'] ?? [];
	if ( is_string( $itens_v ) ) $itens_v = json_decode( $itens_v, true ) ?: [];
	foreach ( (array) $itens_v as $iv ) {
		$nm = trim( (string) ( $iv['nome_prescricao'] ?? $iv['nome'] ?? 'item' ) );
		if ( ( $iv['tipo'] ?? 'mp' ) === 'mp' ) {
			if ( ! $tem( $iv['ativo_id'] ?? '' ) )              $faltas[] = "Item \"$nm\": sem ativo associado";
			elseif ( (float) ( $iv['preco_venda'] ?? 0 ) <= 0 ) $faltas[] = "Item \"$nm\": sem preço de venda";
			elseif ( (float) ( $iv['qtd_total_g'] ?? 0 ) <= 0 ) $faltas[] = "Item \"$nm\": sem quantidade calculada";
		} elseif ( ( $iv['tipo'] ?? '' ) === 'emb' ) {
			if ( (float) ( $iv['subtotal'] ?? 0 ) <= 0 )        $faltas[] = "Embalagem \"$nm\": sem valor";
		}
	}
	// 4b. Forma cápsula exige a cápsula definida (nº "0" é válido — não usar empty)
	if ( $tem( $orc_row['forma_id'] ?? '' ) ) {
		$rf_v  = tao_formula_api( "/formas_farmaceuticas?id=eq.{$orc_row['forma_id']}&select=tipo&limit=1" );
		$ftipo = ( $rf_v['ok'] && ! empty( $rf_v['data'] ) ) ? strtolower( (string) ( $rf_v['data'][0]['tipo'] ?? '' ) ) : '';
		if ( in_array( $ftipo, [ 'cap', 'duo_cap' ], true ) ) {
			$tem_cap = false;
			foreach ( (array) $itens_v as $iv ) {
				if ( ( $iv['tipo'] ?? '' ) === 'mp'
				     && isset( $iv['capsula_numero'] ) && $iv['capsula_numero'] !== '' && $iv['capsula_numero'] !== null ) { $tem_cap = true; break; }
			}
			if ( ! $tem_cap ) $faltas[] = 'Cápsula não definida (tamanho/nº da cápsula)';
		}
	}
	// 5. Controlado: CPF/documento e ENDEREÇO do cliente passam de aviso a EXIGÊNCIA
	$avisos_ctl = function_exists( 'tao_formula_validar_controlado' ) ? tao_formula_validar_controlado( $cid, $orc_row ) : [];
	foreach ( $avisos_ctl as $k_ctl => $m_ctl ) {
		if ( stripos( $m_ctl, 'CPF' ) !== false || stripos( $m_ctl, 'Endere' ) !== false ) {
			$faltas[] = 'Controlado: ' . $m_ctl . ' — preencha no cadastro do cliente (Contatos)';
			unset( $avisos_ctl[ $k_ctl ] );
		}
	}
	$avisos_ctl = array_values( $avisos_ctl );

	if ( $faltas )
		wp_send_json_error( [ 'message' => "Não é possível aprovar — estas informações são necessárias para a aprovação:\n\n• " . implode( "\n• ", $faltas ) ], 422 );

	// Demais validações de controlado (RDC 344/98) — INFORMATIVO por padrão;
	// bloqueia só se a option 'tao_formula_valida_ctl_bloqueia' estiver ligada.
	if ( $avisos_ctl && get_option( 'tao_formula_valida_ctl_bloqueia' ) === '1' )
		wp_send_json_error( [ 'message' => 'Controlado — regularize antes de aprovar: ' . implode( '; ', $avisos_ctl ) ], 409 );
	$r = tao_formula_api( "/orcamentos?id=eq.$orc&cliente_id=eq.$cid", 'PATCH', [
		'status' => 'aprovado_farma', 'aprovado_em' => gmdate( 'c' ), 'motivo_rejeicao' => null,
		'farmaceutico_id' => get_current_user_id(),   // rastro de auditoria: quem avaliou (RDC 67)
	] );
	if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro ao aprovar: ' . mb_substr( (string) $r['raw'], 0, 160 ) ] );

	// Card já GANHO (funil de pós-vendas) + chave ligada → a OM nasce na própria aprovação,
	// sem clique extra. (No fluxo normal ela nasce no ganho; aqui o ganho já passou.)
	$om_criada = false;
	$rq = tao_formula_api( "/orcamentos?id=eq.$orc&cliente_id=eq.$cid&select=card_id&limit=1" );
	$card_id = ( $rq['ok'] && ! empty( $rq['data'] ) ) ? ( $rq['data'][0]['card_id'] ?? '' ) : '';
	if ( $card_id && function_exists( 'tao_formula_estagios_producao' ) && function_exists( 'tao_formula_criar_om' ) ) {
		$rc2 = tao_formula_api( "/crm_cards?id=eq.$card_id&select=workspace_id,pipeline_id&limit=1" );
		if ( $rc2['ok'] && ! empty( $rc2['data'] ) ) {
			$ws  = $rc2['data'][0]['workspace_id'] ?? '';
			$est = tao_formula_estagios_producao( $ws );
			if ( tao_formula_om_ganho_ativo( $ws ) && $est['pipeline'] && ( $rc2['data'][0]['pipeline_id'] ?? '' ) === $est['pipeline'] ) {
				$res = tao_formula_criar_om( $cid, $orc );
				$om_criada = ! empty( $res['ok'] );
			}
		}
	}
	// Aprovar muda o que conta pro valor do card (card ganho só soma aprovados) → recalcula.
	if ( $card_id && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id );
	wp_send_json_success( [ 'om_criada' => $om_criada, 'avisos_controlado' => $avisos_ctl ] );
} );

add_action( 'wp_ajax_tao_formula_orc_rejeitar', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
	$cid = tao_formula_cliente_id(); $orc = sanitize_text_field( $_POST['orc_id'] ?? '' );
	$motivo = trim( sanitize_text_field( $_POST['motivo'] ?? '' ) );
	if ( ! $cid || ! $orc ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
	$r = tao_formula_api( "/orcamentos?id=eq.$orc&cliente_id=eq.$cid", 'PATCH', [
		'status' => 'rejeitado', 'motivo_rejeicao' => ( $motivo !== '' ? $motivo : 'Não aprovado' ), 'aprovado_em' => null,
		'farmaceutico_id' => get_current_user_id(),   // rastro de auditoria: quem avaliou (RDC 67)
	] );
	if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro ao rejeitar' ] );
	// Rejeitar tira o orçamento da conta do card ganho → recalcula o valor.
	$rqr = tao_formula_api( "/orcamentos?id=eq.$orc&cliente_id=eq.$cid&select=card_id&limit=1" );
	$card_id_r = ( $rqr['ok'] && ! empty( $rqr['data'] ) ) ? ( $rqr['data'][0]['card_id'] ?? '' ) : '';
	if ( $card_id_r && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id_r );
	wp_send_json_success();
} );

add_action( 'wp_ajax_tao_formula_orc_reabrir', function () {
	while ( ob_get_level() > 0 ) ob_end_clean();
	check_ajax_referer( 'tao_formula_nonce', 'nonce' );
	if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
	$cid = tao_formula_cliente_id(); $orc = sanitize_text_field( $_POST['orc_id'] ?? '' );
	if ( ! $cid || ! $orc ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

	// Estorno fecha o ciclo com a OM: cancela as OMs abertas do orçamento.
	// OM CONCLUÍDA (estoque baixado) bloqueia o estorno — exigiria estornar a produção.
	$roms = tao_formula_api( "/lab_ordens?orcamento_id=eq.$orc&cliente_id=eq.$cid&status=neq.cancelada&select=id,numero,status" );
	$oms  = $roms['ok'] ? ( $roms['data'] ?? [] ) : [];
	foreach ( $oms as $om ) {
		if ( ( $om['status'] ?? '' ) === 'concluida' ) {
			wp_send_json_error( [ 'message' => 'OM ' . ( $om['numero'] ?? '' ) . ' já concluída (estoque baixado) — estorno indisponível. Trate a devolução pelo Estoque.' ], 409 );
		}
	}
	foreach ( $oms as $om ) {
		tao_formula_api( "/lab_ordens?id=eq.{$om['id']}&cliente_id=eq.$cid", 'PATCH', [ 'status' => 'cancelada' ] );
	}

	$r = tao_formula_api( "/orcamentos?id=eq.$orc&cliente_id=eq.$cid", 'PATCH', [
		'status' => 'pendente_revisao', 'aprovado_em' => null, 'motivo_rejeicao' => null,
	] );
	if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro' ] );
	// Reabrir/estornar tira o orçamento da conta do card ganho → recalcula o valor.
	$rqe = tao_formula_api( "/orcamentos?id=eq.$orc&cliente_id=eq.$cid&select=card_id&limit=1" );
	$card_id_e = ( $rqe['ok'] && ! empty( $rqe['data'] ) ) ? ( $rqe['data'][0]['card_id'] ?? '' ) : '';
	if ( $card_id_e && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id_e );
	wp_send_json_success( [ 'oms_canceladas' => count( $oms ) ] );
} );
