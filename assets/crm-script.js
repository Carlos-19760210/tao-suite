/* TAO CRM — Script principal */
(function($){

    // ─── HELPER AJAX ──────────────────────────────────────────────────────────
    function crmPost(data, onSuccess, onError){
        $.ajax({
            url: taoCrm.ajax_url, type: 'POST', data: data, dataType: 'text',
            success: function(raw){
                var s = raw.indexOf('{'); if(s === -1) s = raw.indexOf('[');
                if(s > 0) raw = raw.substring(s);
                var resp; try { resp = JSON.parse(raw); } catch(e){ if(onError) onError('Resposta inválida'); return; }
                if(onSuccess) onSuccess(resp);
            },
            error: function(xhr){ if(onError) onError('HTTP ' + xhr.status); }
        });
    }

    // ─── MARK READ ao abrir card ──────────────────────────────────────────────
    if(typeof taoCrmCardId !== 'undefined' && taoCrmCardId){
        crmPost({ action:'tao_crm_mark_read', nonce:taoCrm.nonce, card_id:taoCrmCardId });
    }

    // ─── RESPONSÁVEL ─────────────────────────────────────────────────────────
    $('#tao-crm-responsavel').on('change', function(){
        crmPost({
            action: 'tao_crm_save_responsavel',
            nonce:  taoCrm.nonce,
            card_id: taoCrmCardId,
            responsavel_id: $(this).val()
        });
    });

    // ─── CAMPOS: auto-save com debounce ──────────────────────────────────────
    var campoTimers = {};

    function salvarCampo($el, campoId, cardId, valor){
        clearTimeout(campoTimers[campoId]);
        var $item = $el.closest('.campo-item');
        campoTimers[campoId] = setTimeout(function(){
            crmPost(
                { action:'tao_crm_save_valor', nonce:taoCrm.nonce, card_id:cardId, campo_id:campoId, valor:valor },
                function(resp){
                    if(resp.success){
                        $item.removeClass('campo-missing');
                        $item.find('.campo-saved').fadeIn(100).delay(1500).fadeOut(400);
                    }
                }
            );
        }, 800);
    }

    $(document).on('input change', '.campo-input', function(){
        var $el     = $(this);
        var campoId = $el.data('campo-id');
        var cardId  = $el.data('card-id');
        var valor   = $el.is('[type=checkbox]') ? ($el.is(':checked') ? '1' : '0') : $el.val();
        salvarCampo($el, campoId, cardId, valor);
    });

    // Sincronizar enable/disable do obrigatório com o checkbox "Mostrar"
    $(document).on('change', '.est-on', function(){
        var est   = $(this).data('est');
        var on    = $(this).is(':checked');
        $('.est-req[data-est="' + est + '"]').prop('disabled', !on);
        $('.est-ent[data-est="' + est + '"]').prop('disabled', !on);
        $('.est-ord[data-est="' + est + '"]').prop('disabled', !on);
    });

    // Select de tipo: mostrar/ocultar opções
    $('#cf-tipo').on('change', function(){
        $('#cf-opcoes-wrap').toggle($(this).val() === 'select');
    });

    // Auto-gerar chave a partir do nome
    $('#cf-nome').on('input', function(){
        if($('#cf-campo-id').val()) return; // editando: não sobrescrever
        var chave = $(this).val().toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g,'')
            .replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
        $('#cf-chave').val(chave);
    });

    // ─── SETTINGS: Salvar campo via AJAX ─────────────────────────────────────
    $('#tao-crm-campo-form').on('submit', function(e){
        e.preventDefault();

        var estagiosOn  = [];
        var estagiosReq = [];
        var estagiosOrd = {};

        $('.est-on:checked').each(function(){
            var est = $(this).data('est');
            estagiosOn.push(est);
        });
        var estagiosEnt = [];
        $('.est-req:checked').each(function(){
            estagiosReq.push($(this).data('est'));
        });
        $('.est-ent:checked').each(function(){
            estagiosEnt.push($(this).data('est'));
        });
        $('.est-ord').each(function(){
            estagiosOrd[$(this).data('est')] = $(this).val();
        });

        var data = {
            action:       'tao_crm_save_campo_def',
            nonce:        taoCrm.nonce,
            campo_id:     $('#cf-campo-id').val(),
            workspace_id: $('#cf-workspace-id').val(),
            pipeline_id:  $('#cf-pipeline-id').val(),
            nome:         $('#cf-nome').val(),
            chave:        $('#cf-chave').val(),
            tipo:         $('#cf-tipo').val(),
            opcoes:       $('#cf-opcoes').val(),
        };

        estagiosOn.forEach(function(id){ data['estagio_on[]'] = data['estagio_on[]'] || []; });
        // jQuery serializa arrays corretamente se passarmos assim:
        data['estagio_on']  = estagiosOn;
        data['estagio_req'] = estagiosReq;
        data['estagio_ent'] = estagiosEnt;

        // Passar ord como objeto serializado
        $.each(estagiosOrd, function(id, ord){
            data['estagio_ord[' + id + ']'] = ord;
        });

        var $btn = $('#cf-submit').prop('disabled',true).text('Salvando...');
        var $st  = $('#cf-status');

        crmPost(data,
            function(resp){
                $btn.prop('disabled',false).text($('#cf-campo-id').val() ? 'Atualizar campo' : 'Criar campo');
                if(resp.success){
                    $st.css('color','green').text('✔ Salvo');
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    $st.css('color','red').text('✘ ' + (resp.data || 'Erro'));
                }
            },
            function(err){
                $btn.prop('disabled',false);
                $st.css('color','red').text('✘ ' + err);
            }
        );
    });

    // ─── SETTINGS: Deletar campo ──────────────────────────────────────────────
    $(document).on('click', '.tao-crm-del-campo', function(){
        var id   = $(this).data('campo-id');
        var nome = $(this).data('nome');
        if(!confirm('Deletar campo "' + nome + '"? Todos os valores preenchidos serão apagados.')) return;
        var $row = $(this).closest('tr');
        crmPost(
            { action:'tao_crm_delete_campo', nonce:taoCrm.nonce, campo_id:id },
            function(resp){ if(resp.success) $row.fadeOut(); else alert('Erro ao deletar'); }
        );
    });

    // ─── KANBAN DRAG & DROP ───────────────────────────────────────────────────
    var draggedCardId = null;
    var draggedStageId = null;

    $(document).on('dragstart', '.tao-crm-card', function(e){
        draggedCardId  = $(this).data('card-id');
        draggedStageId = $(this).data('stage-id');
        $(this).addClass('dragging');
        e.originalEvent.dataTransfer.effectAllowed = 'move';
    });
    $(document).on('dragend',   '.tao-crm-card', function(){ $(this).removeClass('dragging'); });
    $(document).on('dragover',  '.tao-crm-cards-list', function(e){
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'move';
        $('.tao-crm-cards-list').removeClass('drag-over');
        $(this).addClass('drag-over');
    });
    $(document).on('dragleave', '.tao-crm-cards-list', function(){ $(this).removeClass('drag-over'); });

    $(document).on('drop', '.tao-crm-cards-list', function(e){
        e.preventDefault();
        $(this).removeClass('drag-over');
        if(!draggedCardId) return;

        var $list    = $(this);
        var newStage = $list.data('stage-id');
        var $card    = $('[data-card-id="' + draggedCardId + '"]');
        var oldStage = draggedStageId;

        if(newStage === oldStage) return;

        $list.prepend($card);
        $card.data('stage-id', newStage);
        $('.tao-crm-column').each(function(){
            $(this).find('.stage-count').text($(this).find('.tao-crm-card').length);
        });

        var movedId    = draggedCardId;
        var fromStage  = oldStage;
        draggedCardId  = null;
        draggedStageId = null;

        // Verifica campos obrigatórios na entrada da fase destino
        crmPost(
            { action:'tao_crm_get_campos_destino', nonce:taoCrm.nonce, estagio_id:newStage, card_id:movedId },
            function(resp){
                if(resp.success && resp.data && resp.data.campos && resp.data.campos.length > 0){
                    mostrarModalEntrada(resp.data.campos, resp.data.valores || {}, movedId, newStage, $card, $list);
                } else {
                    executarMoveCard(movedId, newStage, {});
                }
            },
            function(){ executarMoveCard(movedId, newStage, {}); }
        );
    });

    // ─── MODAL NOVO CARD ──────────────────────────────────────────────────────
    $('#tao-crm-new-card-btn').on('click', function(){
        $('#tao-crm-modal-card').fadeIn(150);
        $('[name="contato_nome"]').focus();
    });
    window.taoCrmCloseModal = function(){ $('#tao-crm-modal-card').hide(); };
    $('#tao-crm-modal-card').on('click', function(e){
        if($(e.target).is('#tao-crm-modal-card')) taoCrmCloseModal();
    });

    $('#tao-crm-new-card-form').on('submit', function(e){
        e.preventDefault();
        var $btn = $('#tao-crm-save-card-btn').prop('disabled',true).text('Criando...');
        var data = { action:'tao_crm_create_card', nonce:taoCrm.nonce };
        $(this).serializeArray().forEach(function(f){ data[f.name] = f.value; });
        crmPost(data, function(resp){
            $btn.prop('disabled',false).text('Criar Card');
            if(resp.success){ taoCrmCloseModal(); location.reload(); }
            else alert('Erro: ' + (resp.data || 'Tente novamente'));
        }, function(err){ $btn.prop('disabled',false); alert('Erro: ' + err); });
    });

    // ─── CARD DETAIL: MOVER ESTÁGIO com validação ────────────────────────────
    $('#tao-crm-btn-move').on('click', function(){
        var newStage = $('#tao-crm-move-stage').val();
        if(!newStage || !window.taoCrmCardId) return;
        if(newStage === window.taoCrmCurrentStage) return;

        var $btn    = $(this).prop('disabled',true).text('Movendo...');
        var $status = $('#tao-crm-move-status');

        crmPost(
            { action:'tao_crm_move_card', nonce:taoCrm.nonce, card_id:taoCrmCardId, estagio_id:newStage },
            function(resp){
                $btn.prop('disabled',false).text('Mover');
                if(resp.success){
                    $status.css('color','green').text('Movido!');
                    setTimeout(function(){
                        if(typeof taoCrmKanbanUrl !== 'undefined' && taoCrmKanbanUrl && confirm('Card movido com sucesso!\n\nDeseja voltar ao Kanban?')){
                            window.location.href = taoCrmKanbanUrl;
                        } else {
                            location.reload();
                        }
                    }, 300);
                } else {
                    if(resp.data && resp.data.code === 'campos_faltando'){
                        mostrarCamposFaltando(resp.data.campos);
                    } else {
                        $status.css('color','red').text('✘ ' + (resp.data && resp.data.msg ? resp.data.msg : (resp.data || 'Erro')));
                    }
                }
            },
            function(err){ $btn.prop('disabled',false); $status.css('color','red').text('✘ ' + err); }
        );
    });

    function mostrarCamposFaltando(campos){
        var $modal = $('#tao-crm-campos-modal');
        if(!$modal.length) { alert('Preencha os campos obrigatórios: ' + campos.join(', ')); return; }
        $('#tao-crm-campos-modal-msg').text('Preencha os campos obrigatórios antes de avançar para o próximo estágio:');
        var $ul = $('#tao-crm-campos-modal-list').empty();
        (campos||[]).forEach(function(n){ $ul.append('<li>' + escHtml(n) + '</li>'); });
        $modal.fadeIn(150);
    }

    function executarMoveCard(cardId, estagioId, valores){
        var data = { action:'tao_crm_move_card', nonce:taoCrm.nonce, card_id:cardId, estagio_id:estagioId };
        $.each(valores||{}, function(campoId, val){ data['valores[' + campoId + ']'] = val; });
        crmPost(data,
            function(resp){
                if(!resp.success){
                    if(resp.data && resp.data.code === 'campos_faltando'){
                        mostrarCamposFaltando(resp.data.campos);
                    } else {
                        alert('Erro ao mover: ' + (resp.data && resp.data.msg ? resp.data.msg : resp.data || 'Tente novamente'));
                    }
                    location.reload();
                }
            },
            function(err){ alert('Erro ao mover: ' + err); location.reload(); }
        );
    }

    function mostrarModalEntrada(campos, valores, cardId, estagioId, $card, $list){
        var $modal = $('#tao-crm-entrada-modal');
        if(!$modal.length){ executarMoveCard(cardId, estagioId, {}); return; }

        var $fields = $('#tao-crm-entrada-fields').empty();
        campos.forEach(function(c){
            var input;
            if(c.tipo === 'select'){
                var opts = Array.isArray(c.opcoes) ? c.opcoes : (typeof c.opcoes === 'string' ? JSON.parse(c.opcoes||'[]') : []);
                input = $('<select>').addClass('regular-text').attr({id:'entrada-f-'+c.id,'data-campo-id':c.id}).css('width','100%');
                input.append($('<option>').val('').text('— selecione —'));
                opts.forEach(function(o){ input.append($('<option>').val(o).text(o)); });
            } else if(c.tipo === 'textarea'){
                input = $('<textarea>').addClass('regular-text').attr({id:'entrada-f-'+c.id,'data-campo-id':c.id,rows:3}).css('width','100%');
            } else if(c.tipo === 'boolean'){
                input = $('<select>').addClass('regular-text').attr({id:'entrada-f-'+c.id,'data-campo-id':c.id}).css('width','100%');
                input.append($('<option>').val('').text('— selecione —'));
                input.append($('<option>').val('Sim').text('Sim'));
                input.append($('<option>').val('Não').text('Não'));
            } else {
                var itype = c.tipo === 'number' ? 'number' : (c.tipo === 'date' ? 'date' : (c.tipo === 'email' ? 'email' : (c.tipo === 'phone' ? 'tel' : 'text')));
                input = $('<input>').addClass('regular-text').attr({type:itype,id:'entrada-f-'+c.id,'data-campo-id':c.id}).css('width','100%');
            }
            if(valores[c.id] !== undefined) input.val(valores[c.id]);
            var $label = $('<label>').css({fontSize:'13px',fontWeight:'600',display:'block'})
                .text(c.nome + (valores[c.id] ? '' : ' *'))
                .append($('<div>').css('marginTop','4px').append(input));
            $fields.append($label);
        });

        $('#tao-crm-entrada-fechar, #tao-crm-entrada-cancelar').off('click.entrada').on('click.entrada', function(){
            $modal.hide();
            location.reload();
        });

        $('#tao-crm-entrada-form').off('submit.entrada').on('submit.entrada', function(e){
            e.preventDefault();
            var valores = {};
            $('[data-campo-id]', $fields).each(function(){
                valores[$(this).data('campo-id')] = $(this).val();
            });
            var $btn = $('#tao-crm-entrada-btn').prop('disabled',true).text('Movendo...');
            $modal.hide();
            executarMoveCard(cardId, estagioId, valores);
        });

        $modal.fadeIn(150);
    }

    // ─── CARD DETAIL: CHAT ───────────────────────────────────────────────────
    function scrollToBottom(){
        var $m = $('#tao-crm-messages');
        if($m.length) $m.scrollTop($m[0].scrollHeight);
    }
    scrollToBottom();

    $('#tao-crm-send-btn').on('click', function(){
        if($('#tao-crm-file-input')[0] && $('#tao-crm-file-input')[0].files.length > 0){
            enviarAnexo();
        } else {
            enviarMensagem();
        }
    });
    $('#tao-crm-msg-input').on('keydown', function(e){
        if(e.key === 'Enter' && (e.ctrlKey || e.metaKey)){ e.preventDefault(); enviarMensagem(); }
    });

    // ─── Seleção de arquivo ───────────────────────────────────────────────────
    $('#tao-crm-file-input').on('change', function(){
        var f = this.files[0];
        if(!f) return;
        var kb = (f.size / 1024).toFixed(0);
        var label = f.name + ' (' + (kb > 1024 ? (kb/1024).toFixed(1) + ' MB' : kb + ' KB') + ')';
        $('#tao-crm-file-name').text(label);
        $('#tao-crm-file-preview').show();
    });
    $('#tao-crm-file-clear').on('click', function(){
        $('#tao-crm-file-input').val('');
        $('#tao-crm-file-preview').hide();
    });

    function enviarMensagem(){
        var texto = $('#tao-crm-msg-input').val().trim();
        if(!texto || !window.taoCrmCardId) return;
        var $btn = $('#tao-crm-send-btn').prop('disabled',true).text('Enviando...');
        crmPost(
            { action:'tao_crm_send_message', nonce:taoCrm.nonce, card_id:taoCrmCardId, mensagem:texto },
            function(resp){
                $btn.prop('disabled',false).text('Enviar ▶');
                if(resp.success){
                    $('#tao-crm-msg-input').val('');
                    if(resp.data && resp.data.msg){ renderMessage(resp.data.msg); scrollToBottom(); taoCrmLastMsg = resp.data.msg.enviado_em || taoCrmLastMsg; }
                } else { alert('Erro ao enviar: ' + (resp.data || 'Tente novamente')); }
            },
            function(err){ $btn.prop('disabled',false).text('Enviar ▶'); alert('Erro: ' + err); }
        );
    }

    function enviarAnexo(){
        var file = $('#tao-crm-file-input')[0].files[0];
        if(!file || !window.taoCrmCardId) return;
        var caption = $('#tao-crm-msg-input').val().trim();
        var $btn = $('#tao-crm-send-btn').prop('disabled',true).text('Enviando...');
        var fd = new FormData();
        fd.append('action',  'tao_crm_send_attachment');
        fd.append('nonce',   taoCrm.nonce);
        fd.append('card_id', taoCrmCardId);
        fd.append('caption', caption);
        fd.append('arquivo', file);
        $.ajax({
            url: taoCrm.ajax_url, type: 'POST',
            data: fd, processData: false, contentType: false, dataType: 'text',
            success: function(raw){
                $btn.prop('disabled',false).text('Enviar ▶');
                var s = raw.indexOf('{'); if(s > 0) raw = raw.substring(s);
                var resp; try{ resp = JSON.parse(raw); }catch(e){ alert('Resposta inválida'); return; }
                if(resp.success){
                    $('#tao-crm-msg-input').val('');
                    $('#tao-crm-file-input').val('');
                    $('#tao-crm-file-preview').hide();
                    if(resp.data && resp.data.msg){ renderMessage(resp.data.msg); scrollToBottom(); taoCrmLastMsg = resp.data.msg.enviado_em || taoCrmLastMsg; }
                } else { alert('Erro ao enviar: ' + (resp.data || 'Tente novamente')); }
            },
            error: function(xhr){ $btn.prop('disabled',false).text('Enviar ▶'); alert('Erro HTTP ' + xhr.status); }
        });
    }

    function renderMessage(msg){
        var dir  = msg.direcao === 'out' ? 'out' : 'in';
        var nome = escHtml(msg.remetente_nome || (dir==='out'?'Atendente':'Cliente'));
        var hora = formatHoraBRT(msg.enviado_em);
        var conteudo = '';
        var tipo = msg.tipo || 'text';
        if(tipo === 'image' && msg.midia_url){
            conteudo = '<a href="' + escHtml(msg.midia_url) + '" target="_blank"><img src="' + escHtml(msg.midia_url) + '" class="msg-img" alt="imagem"></a>';
            if(msg.conteudo) conteudo += '<div class="msg-caption">' + escHtml(msg.conteudo).replace(/\n/g,'<br>') + '</div>';
        } else if(tipo === 'audio' && msg.midia_url){
            conteudo = '<audio controls src="' + escHtml(msg.midia_url) + '"></audio>';
        } else if((tipo === 'video' || tipo === 'document') && msg.midia_url){
            conteudo = '<a href="' + escHtml(msg.midia_url) + '" target="_blank" class="msg-doc">📄 ' + escHtml(msg.conteudo||'arquivo') + '</a>';
        } else {
            conteudo = escHtml(msg.conteudo||'').replace(/\n/g,'<br>');
        }
        $('#tao-crm-messages').append(
            '<div class="chat-msg ' + dir + '"><div class="msg-bubble">' +
            '<div class="msg-content">' + conteudo + '</div>' +
            '<div class="msg-meta"><span class="msg-sender">' + nome + '</span>' +
            '<span class="msg-time">' + hora + '</span></div></div></div>'
        );
    }

    // Polling de mensagens — pausa quando aba está em background ou inativa por 5min
    if(typeof taoCrmCardId !== 'undefined' && taoCrmCardId){
        var _cardPoller = null;
        var _lastActivity = Date.now();
        $(document).on('mousemove keydown click touchstart', function(){ _lastActivity = Date.now(); });

        function _doPollMessages(){
            crmPost(
                { action:'tao_crm_poll_messages', nonce:taoCrm.nonce, card_id:taoCrmCardId, desde:taoCrmLastMsg||'' },
                function(resp){
                    if(resp.success && resp.data && resp.data.length){
                        resp.data.forEach(function(msg){ renderMessage(msg); taoCrmLastMsg = msg.enviado_em; });
                        scrollToBottom();
                    }
                }
            );
        }
        function _scheduleCardPoll(){
            if(_cardPoller) clearTimeout(_cardPoller);
            var inactive = Date.now() - _lastActivity > 300000; // 5min
            var delay = document.hidden ? 15000 : (inactive ? 30000 : 4000);
            _cardPoller = setTimeout(function(){ _doPollMessages(); _scheduleCardPoll(); }, delay);
        }
        document.addEventListener('visibilitychange', function(){ if(!document.hidden) _scheduleCardPoll(); });
        _scheduleCardPoll();
    }

    // ─── KANBAN: Badge de notificações (polling leve) ────────────────────────
    if(typeof taoCrmWorkspaceId !== 'undefined' && typeof taoCrmCardId === 'undefined'){
        function atualizarInboxBadge(){
            crmPost(
                { action:'tao_crm_inbox_count', nonce:taoCrm.nonce, workspace_id:taoCrmWorkspaceId },
                function(resp){
                    if(!resp.success) return;
                    var cnt = resp.data.count || 0;
                    var $badge = $('.tao-crm-inbox-badge');
                    if(cnt > 0){ if(!$badge.length) $('.tao-crm-title').append('<span class="tao-crm-inbox-badge">' + cnt + '</span>'); else $badge.text(cnt); }
                    else $badge.remove();
                }
            );
        }
        atualizarInboxBadge(); // imediato na carga
        setInterval(atualizarInboxBadge, 30000);
    }

    // ─── SETTINGS: Automações ─────────────────────────────────────────────────
    $('#af-tipo').on('change', function(){
        var t = $(this).val();
        $('#af-delay-wrap').toggle(t === 'tempo_na_fase');
        $('#af-horas-wrap').toggle(t === 'sem_resposta');
    });

    $('#af-acao').on('change', function(){
        var a = $(this).val();
        $('#af-msg-wrap').toggle(a === 'enviar_mensagem');
        $('#af-fase-wrap').toggle(a === 'mover_fase');
        $('#af-resp-wrap').toggle(a === 'atribuir_responsavel');
    });

    $('#tao-crm-auto-form').on('submit', function(e){
        e.preventDefault();
        var autoId = $('#af-auto-id').val();
        var $btn   = $('#af-submit').prop('disabled', true).text('Salvando...');
        var $st    = $('#af-status');
        crmPost(
            {
                action:          'tao_crm_save_automacao',
                nonce:           taoCrm.nonce,
                auto_id:         autoId,
                workspace_id:    $('#af-workspace-id').val(),
                pipeline_id:     $('#af-pipeline-id').val(),
                estagio_id:      $('#af-estagio-id').val(),
                nome:            $('#af-nome').val(),
                tipo:                $('#af-tipo').val(),
                delay_minutos:       $('#af-delay').val() || 0,
                horas_sem_resposta:  $('#af-horas').val() || 24,
                acao:                $('#af-acao').val(),
                mensagem:        $('#af-mensagem').val(),
                para_estagio_id: $('#af-para-estagio').val(),
                responsavel_id:  $('#af-responsavel').val(),
                ordem:           $('#af-ordem').val() || 0,
                ativo:           $('#af-ativo').is(':checked') ? '1' : '',
            },
            function(resp){
                $btn.prop('disabled', false).text(autoId ? 'Atualizar automação' : 'Criar automação');
                if(resp.success){
                    $st.css('color','green').text('✔ Salvo');
                    if(!autoId){ setTimeout(function(){ location.reload(); }, 800); }
                } else {
                    $st.css('color','red').text('✘ ' + (resp.data || 'Erro'));
                }
            },
            function(err){
                $btn.prop('disabled', false);
                $st.css('color','red').text('✘ ' + err);
            }
        );
    });

    $(document).on('click', '.tao-crm-del-automacao', function(){
        var id   = $(this).data('auto-id');
        var nome = $(this).data('nome');
        if(!confirm('Deletar automação "' + nome + '"? Itens pendentes na fila serão cancelados.')) return;
        var $row = $(this).closest('tr');
        crmPost(
            { action:'tao_crm_delete_automacao', nonce:taoCrm.nonce, auto_id:id },
            function(resp){ if(resp.success) $row.fadeOut(); else alert('Erro ao deletar'); }
        );
    });

    // ─── CARD DETAIL: FECHAR NEGÓCIO / CANCELAR ──────────────────────────────
    var motivosGanho = [
        'Proposta aprovada pelo cliente',
        'Contrato assinado',
        'Pagamento confirmado',
        'Venda concluída por telefone',
        'Venda concluída presencialmente',
        'Outro (especificar)'
    ];
    var motivosPerdido = [
        'Sem retorno do cliente',
        'Cliente desistiu da compra',
        'Orçamento reprovado',
        'Concorrência — preço',
        'Concorrência — produto',
        'Fora do perfil do produto',
        'Lead inválido / número errado',
        'Cancelamento solicitado pelo cliente',
        'Outro (especificar)'
    ];

    function preencherMotivos(lista) {
        var $sel = $('#tao-crm-fechar-motivo').empty();
        $.each(lista, function(i, v){
            $sel.append('<option value="' + v + '">' + v + '</option>');
        });
        $('#tao-crm-fechar-outro-wrap').hide();
        $('#tao-crm-fechar-outro').val('');
    }

    $('#tao-crm-fechar-motivo').on('change', function(){
        var isOutro = $(this).val() === 'Outro (especificar)';
        $('#tao-crm-fechar-outro-wrap').toggle(isOutro);
        if(isOutro) $('#tao-crm-fechar-outro').focus();
    });

    $('#tao-crm-btn-ganho').on('click', function(){
        $('#tao-crm-fechar-titulo').text('✅ Fechar Negócio');
        $('#tao-crm-fechar-desc').text('O card será movido para Venda Concluída e fechado. A próxima mensagem do cliente abrirá um novo card automaticamente.');
        $('#tao-crm-fechar-tipo').val('ganho');
        $('#tao-crm-fechar-btn').removeClass('btn-perdido').addClass('button-primary').text('Confirmar');
        preencherMotivos(motivosGanho);
        $('#tao-crm-fechar-modal').fadeIn(150);
    });

    $('#tao-crm-btn-perdido').on('click', function(){
        $('#tao-crm-fechar-titulo').text('❌ Cancelar Negócio');
        $('#tao-crm-fechar-desc').text('O card será movido para Cards Cancelados e fechado. A próxima mensagem do cliente abrirá um novo card automaticamente.');
        $('#tao-crm-fechar-tipo').val('perdido');
        $('#tao-crm-fechar-btn').removeClass('button-primary').addClass('btn-perdido').text('Confirmar');
        preencherMotivos(motivosPerdido);
        $('#tao-crm-fechar-modal').fadeIn(150);
    });

    $('#tao-crm-fechar-modal').on('click', function(e){
        if($(e.target).is('#tao-crm-fechar-modal')) $(this).hide();
    });

    $('#tao-crm-fechar-form').on('submit', function(e){
        e.preventDefault();
        var tipo   = $('#tao-crm-fechar-tipo').val();
        var motivo = $('#tao-crm-fechar-motivo').val();
        if(motivo === 'Outro (especificar)'){
            motivo = $('#tao-crm-fechar-outro').val().trim() || 'Outro';
        }
        var $btn = $('#tao-crm-fechar-btn').prop('disabled', true).text('Fechando...');
        crmPost(
            { action: 'tao_crm_fechar_card', nonce: taoCrm.nonce, card_id: taoCrmCardId, tipo: tipo, motivo: motivo },
            function(resp){
                $btn.prop('disabled', false).text('Confirmar');
                if(resp.success){
                    $('#tao-crm-fechar-modal').hide();
                    var tipo = $('#tao-crm-fechar-tipo').val();
                    var msg = (resp.data && resp.data.pos_vendas)
                        ? 'Negócio ganho! Card enviado para o Funil de Pós-vendas.'
                        : (tipo === 'ganho' ? 'Negócio fechado com sucesso!' : 'Negócio cancelado.');
                    setTimeout(function(){
                        if(typeof taoCrmKanbanUrl !== 'undefined' && taoCrmKanbanUrl && confirm(msg + '\n\nDeseja voltar ao Kanban?')){
                            window.location.href = taoCrmKanbanUrl;
                        } else {
                            location.reload();
                        }
                    }, 200);
                } else {
                    alert('Erro: ' + (resp.data || 'Configure os estágios terminais em Configurações → Pipelines e Estágios.'));
                    $('#tao-crm-fechar-modal').hide();
                }
            },
            function(err){ $btn.prop('disabled', false).text('Confirmar'); alert('Erro: ' + err); }
        );
    });

    // ─── NOTA INTERNA: toggle de modo ────────────────────────────────────────
    var modoNota = false;
    $('#tao-crm-nota-toggle').on('click', function(){
        modoNota = !modoNota;
        var $inp  = $('#tao-crm-msg-input');
        var $send = $('#tao-crm-send-btn');
        var $atch = $('#tao-crm-attach-wrap');
        if(modoNota){
            $(this).addClass('active').attr('title','Voltar para modo WhatsApp');
            $inp.attr('placeholder','Escreva uma nota interna... (visível só para a equipe)');
            $send.text('Salvar Nota 📝').removeClass('button-primary').addClass('btn-nota');
            $atch.hide();
        } else {
            $(this).removeClass('active').attr('title','Alternar para modo nota interna');
            $inp.attr('placeholder','Digite uma mensagem... (Ctrl+Enter para enviar)');
            $send.text('Enviar ▶').addClass('button-primary').removeClass('btn-nota');
            $atch.show();
        }
    });

    // Substitui o handler de envio para checar modo nota
    $('#tao-crm-send-btn').off('click').on('click', function(){
        if(modoNota){ enviarNota(); return; }
        if($('#tao-crm-file-input')[0] && $('#tao-crm-file-input')[0].files.length > 0){
            enviarAnexo();
        } else {
            enviarMensagem();
        }
    });

    function enviarNota(){
        var texto = $('#tao-crm-msg-input').val().trim();
        if(!texto || !window.taoCrmCardId) return;
        var $btn = $('#tao-crm-send-btn').prop('disabled',true).text('Salvando...');
        crmPost(
            { action:'tao_crm_save_nota', nonce:taoCrm.nonce, card_id:taoCrmCardId, conteudo:texto },
            function(resp){
                $btn.prop('disabled',false).text('Salvar Nota 📝');
                if(resp.success){
                    $('#tao-crm-msg-input').val('');
                    if(resp.data && resp.data.id){ renderMessage(resp.data); scrollToBottom(); taoCrmLastMsg = resp.data.enviado_em || taoCrmLastMsg; }
                } else { alert('Erro: ' + (resp.data || 'Tente novamente')); }
            },
            function(err){ $btn.prop('disabled',false).text('Salvar Nota 📝'); alert('Erro: ' + err); }
        );
    }

    // ─── CARD INFO: edição ───────────────────────────────────────────────────
    $('#tao-crm-edit-info-btn').on('click', function(){
        $('#tao-crm-edit-modal').fadeIn(150);
        $('#tao-crm-edit-titulo').focus();
    });
    $('#tao-crm-edit-modal').on('click', function(e){
        if($(e.target).is('#tao-crm-edit-modal')) $(this).hide();
    });

    $(document).on('input', '#tao-crm-edit-cep, #ct-cep', function(){
        var cep = $(this).val().replace(/\D/g,'');
        if(cep.length !== 8) return;
        var $root = $(this).closest('form,#tao-crm-edit-modal,#ct-modal');
        $.getJSON('https://viacep.com.br/ws/' + cep + '/json/', function(data){
            if(data.erro) return;
            $root.find('[id$="-logradouro"],[id$="logradouro"]').val(data.logradouro || '');
            $root.find('[id$="-bairro"],[id$="bairro"]').val(data.bairro || '');
            $root.find('[id$="-cidade"],[id$="cidade"]').val(data.localidade || '');
        });
    });

    $('#tao-crm-edit-form').on('submit', function(e){
        e.preventDefault();
        var $btn = $('#tao-crm-edit-btn').prop('disabled',true).text('Salvando...');
        crmPost({
            action:             'tao_crm_update_card_info',
            nonce:              taoCrm.nonce,
            card_id:            taoCrmCardId,
            titulo:             $('#tao-crm-edit-titulo').val().trim(),
            contato_nome:       $('#tao-crm-edit-nome').val().trim(),
            contato_whatsapp:   $('#tao-crm-edit-whats').val().trim(),
            contato_email:         $('#tao-crm-edit-email').val().trim(),
            contato_cpf:           $('#tao-crm-edit-cpf').val().trim(),
            contato_cep:           $('#tao-crm-edit-cep').val().trim(),
            contato_logradouro:    $('#tao-crm-edit-logradouro').val().trim(),
            contato_numero:        $('#tao-crm-edit-numero').val().trim(),
            contato_complemento:   $('#tao-crm-edit-complemento').val().trim(),
            contato_bairro:        $('#tao-crm-edit-bairro').val().trim(),
            contato_cidade:        $('#tao-crm-edit-cidade').val().trim(),
            contato_classificacao: $('#tao-crm-edit-classificacao').val(),
            contato_observacao:    $('#tao-crm-edit-observacao').val().trim()
        }, function(resp){
            $btn.prop('disabled',false).text('Salvar');
            if(resp.success){
                // Atualiza display sem recarregar
                var titulo = $('#tao-crm-edit-titulo').val().trim() || $('#tao-crm-edit-nome').val().trim();
                $('#tao-crm-card-title-display').text(titulo);
                $('#tao-crm-contato-nome-display').text($('#tao-crm-edit-nome').val().trim());
                $('#tao-crm-contato-whats-display').text($('#tao-crm-edit-whats').val().trim());
                $('#tao-crm-edit-modal').hide();
            } else { alert('Erro: ' + (resp.data || 'Tente novamente')); }
        }, function(err){ $btn.prop('disabled',false).text('Salvar'); alert('Erro: ' + err); });
    });

    // ─── BUSCA E FILTROS: kanban e inbox ─────────────────────────────────────
    function aplicarFiltros(){
        var q           = ($('#tao-crm-search').val() || '').toLowerCase().trim();
        var atendente   = $('#tao-crm-filter-atendente').val() || '';
        var fase        = $('#tao-crm-filter-fase').val() || '';
        var status      = $('#tao-crm-filter-status').val() || '';
        var showClosed  = $('#tao-crm-show-closed').is(':checked');

        // Colunas fechadas
        $('.tao-crm-column.column-closed').each(function(){
            $(this).toggle(showClosed);
        });

        // Cards (kanban)
        $('.tao-crm-card').each(function(){
            var $el      = $(this);
            var matchQ   = !q       || ($el.data('search') || '').toLowerCase().indexOf(q) !== -1;
            var matchAt  = !atendente || String($el.data('responsavel-id') || '0') === atendente;
            var matchF   = !fase    || String($el.data('stage-id') || '') === fase;
            var matchSt  = true;
            if(status === 'handoff') matchSt = $el.data('handoff') === 1 || $el.data('handoff') === '1';
            else if(status === 'aberto')  matchSt = $el.data('fechado') !== 1 && $el.data('fechado') !== '1';
            else if(status === 'fechado') matchSt = $el.data('fechado') === 1 || $el.data('fechado') === '1';
            $el.toggle(matchQ && matchAt && matchF && matchSt);
        });

        // Inbox rows
        $('.tao-crm-inbox-row').each(function(){
            var $el     = $(this);
            var matchQ  = !q      || ($el.data('search') || '').toLowerCase().indexOf(q) !== -1;
            var matchAt = !atendente || String($el.data('responsavel-id') || '0') === atendente;
            var matchF  = !fase   || String($el.data('estagio-id') || '') === fase;
            var matchSt = true;
            if(status === 'handoff') matchSt = $el.data('handoff') === 1 || $el.data('handoff') === '1';
            else if(status === 'aberto')  matchSt = $el.data('fechado') !== 1 && $el.data('fechado') !== '1';
            else if(status === 'fechado') matchSt = $el.data('fechado') === 1 || $el.data('fechado') === '1';
            $el.toggle(matchQ && matchAt && matchF && matchSt);
        });

        // Atualizar contadores das colunas
        $('.tao-crm-column').each(function(){
            var visible = $(this).find('.tao-crm-card:visible').length;
            $(this).find('.stage-count').text(visible);
        });
    }

    // ─── Busca global AJAX (dropdown com resultados) ─────────────────────────
    var _searchTimer;
    $('#tao-crm-search').on('input', function(){
        aplicarFiltros();
        clearTimeout(_searchTimer);
        var q = $(this).val().trim();
        var $dd = $('#crm-search-dropdown');
        if (q.length < 2) { $dd.hide(); return; }
        _searchTimer = setTimeout(function(){
            crmPost({action:'tao_crm_search_global', nonce:taoCrm.nonce, q:q}, function(r){
                if (!r.success || !r.data || !r.data.length) { $dd.hide(); return; }
                var cardUrl  = taoCrm.adminUrl + 'admin.php?page=tao-crm-kanban&view=card&card_id=';
                var contUrl  = taoCrm.adminUrl + 'admin.php?page=tao-crm-contatos&edit=';
                var html = r.data.map(function(item){
                    var href = item.tipo === 'card' ? cardUrl + item.id : contUrl + item.id;
                    var badge = item.tipo === 'contato'
                        ? '<span style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;background:#dcfce7;color:#15803d;text-transform:uppercase">Contato</span>'
                        : (item.status === 'fechado'
                            ? '<span style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;background:#f1f5f9;color:#64748b;text-transform:uppercase">Fechado</span>'
                            : '<span style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;background:#dbeafe;color:#1d4ed8;text-transform:uppercase">Card</span>');
                    return '<a href="'+href+'" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit">'
                        + badge
                        + '<div><div style="font-size:13px;font-weight:600;color:#1e293b">'+escHtml(item.titulo)+'</div>'
                        + (item.sub ? '<div style="font-size:11px;color:#64748b">'+escHtml(item.sub)+'</div>' : '')
                        + '</div></a>';
                }).join('');
                $dd.html(html).show();
            });
        }, 350);
    });

    // Helper escHtml (se não definido)
    if (typeof escHtml === 'undefined') {
        window.escHtml = function(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); };
    }

    $(document).on('click', function(e){
        if (!$(e.target).closest('#tao-crm-search, #crm-search-dropdown').length) $('#crm-search-dropdown').hide();
    });

    $('#tao-crm-search').on('keydown', function(e){
        if (e.key === 'Escape') $('#crm-search-dropdown').hide();
    });

    $('#tao-crm-filter-atendente, #tao-crm-filter-fase, #tao-crm-filter-status').on('change', aplicarFiltros);
    $('#tao-crm-show-closed').on('change', aplicarFiltros);

    // Aplica filtros no carregamento: oculta cards fechados e colunas terminais por padrão
    // Pre-seleciona "abertos" para esconder cards fechados em colunas normais
    if($('#tao-crm-filter-status').length && !$('#tao-crm-filter-status').val()){
        $('#tao-crm-filter-status').val('aberto');
    }
    aplicarFiltros();

    $('#tao-crm-filter-toggle').on('click', function(){
        var $bar = $('#tao-crm-filter-bar');
        $bar.slideToggle(150);
        $(this).toggleClass('button-primary');
    });

    $('#tao-crm-filter-clear').on('click', function(){
        $('#tao-crm-search').val('');
        $('#tao-crm-filter-atendente').val('');
        $('#tao-crm-filter-fase').val('');
        $('#tao-crm-filter-status').val('');
        $('#tao-crm-show-closed').prop('checked', false);
        aplicarFiltros();
    });

    // ─── Kanban: "Ver mais" — expande cards ocultos na coluna ────────────────
    $(document).on('click', '.tao-crm-ver-mais', function(){
        var $btn  = $(this);
        var $list = $btn.closest('.tao-crm-cards-list');
        $list.find('.tao-crm-card[data-hidden]').show().removeAttr('data-hidden');
        $btn.remove();
        aplicarFiltros();
    });

    // ─── renderMessage: suporte a nota ───────────────────────────────────────
    // (redefine a função anterior adicionando o case 'note')
    var _origRender = renderMessage;
    renderMessage = function(msg){
        if(msg.direcao === 'note'){
            var nome = escHtml(msg.remetente_nome || 'Equipe');
            var hora = formatHoraBRT(msg.enviado_em);
            $('#tao-crm-messages').append(
                '<div class="chat-msg note"><div class="msg-bubble">' +
                '<div class="msg-note-label">📝 Nota interna</div>' +
                '<div class="msg-content">' + escHtml(msg.conteudo||'').replace(/\n/g,'<br>') + '</div>' +
                '<div class="msg-meta"><span class="msg-sender">' + nome + '</span>' +
                '<span class="msg-time">' + hora + '</span></div></div></div>'
            );
            return;
        }
        _origRender(msg);
    };

    // ─── KANBAN: Auto-refresh ao detectar alterações — pausa em background ───
    if(typeof taoCrmPipelineId !== 'undefined' && typeof taoCrmCardId === 'undefined'){
        var _kSince = typeof taoCrmLoadedAt !== 'undefined' ? taoCrmLoadedAt : '';
        var _kPoller = null;
        var _handoffSnd = null;
        try { _handoffSnd = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAA' +
            'AAAQABAAB9AAAB9AAAAAAAAEAATElTVA4AAABJTkZPSVNGVA4AAABMYXZmNTguMjkuMTAw' +
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'); } catch(e){}

        function _doKanbanCheck(){
            crmPost(
                { action:'tao_crm_kanban_check', nonce:taoCrm.nonce, pipeline_id:taoCrmPipelineId, since:_kSince },
                function(resp){
                    if(!resp.success || !resp.data) return;
                    var data = resp.data;
                    var last = data.last || '';
                    if(data.new_handoff){
                        if(typeof taoCrmHandoffNotified === 'undefined' || !taoCrmHandoffNotified[last]){
                            window.taoCrmHandoffNotified = window.taoCrmHandoffNotified || {};
                            window.taoCrmHandoffNotified[last] = true;
                            try { var ctx = new (window.AudioContext||window.webkitAudioContext)();
                                var osc = ctx.createOscillator(); var g = ctx.createGain();
                                osc.connect(g); g.connect(ctx.destination);
                                osc.frequency.value = 880; g.gain.setValueAtTime(0.3,ctx.currentTime);
                                g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.6);
                                osc.start(ctx.currentTime); osc.stop(ctx.currentTime+0.6);
                            } catch(e){}
                            if(window.Notification && Notification.permission === 'granted'){
                                new Notification('🙋 Novo atendimento', { body:'Um cliente está aguardando atendimento humano.', icon:'' });
                            } else if(window.Notification && Notification.permission !== 'denied'){
                                Notification.requestPermission();
                            }
                        }
                    }
                    if(last && _kSince && last > _kSince){ location.reload(); }
                }
            );
        }
        function _scheduleKanbanPoll(){
            if(_kPoller) clearTimeout(_kPoller);
            _kPoller = setTimeout(function(){ _doKanbanCheck(); _scheduleKanbanPoll(); }, document.hidden ? 60000 : 20000);
        }
        document.addEventListener('visibilitychange', function(){ if(!document.hidden) _scheduleKanbanPoll(); });
        _scheduleKanbanPoll();
    }

    // ─── KANBAN: Setas de scroll horizontal ──────────────────────────────────
    if($('#tao-scroll-left').length){
        var _scrollInt = null;
        $('#tao-scroll-left').on('mouseenter', function(){
            _scrollInt = setInterval(function(){ var b=$('#tao-crm-board')[0]; if(b) b.scrollLeft -= 10; }, 16);
        }).on('mouseleave', function(){ clearInterval(_scrollInt); _scrollInt = null; });
        $('#tao-scroll-right').on('mouseenter', function(){
            _scrollInt = setInterval(function(){ var b=$('#tao-crm-board')[0]; if(b) b.scrollLeft += 10; }, 16);
        }).on('mouseleave', function(){ clearInterval(_scrollInt); _scrollInt = null; });
    }

    // ─── KANBAN: Bulk Actions ─────────────────────────────────────────────────
    if($('#crm-bulk-toolbar').length){
        function _bulkUpdate(){
            var ids = _bulkSelected();
            var n   = ids.length;
            $('#crm-bulk-count').text(n + (n === 1 ? ' selecionado' : ' selecionados'));
            if(n > 0){ $('#crm-bulk-toolbar').css('display','flex'); }
            else      { $('#crm-bulk-toolbar').hide(); }
        }
        function _bulkSelected(){
            return $('.crm-card-checkbox:checked').map(function(){ return $(this).data('card-id'); }).get();
        }
        function _bulkClear(){
            $('.crm-card-checkbox').prop('checked', false);
            $('#crm-bulk-toolbar').hide();
        }
        function _bulkSend(action, extra, cb){
            var ids = _bulkSelected();
            if(!ids.length) return;
            var payload = $.extend({ action:'tao_crm_bulk_action', nonce:taoCrm.nonce, bulk_action:action }, extra);
            ids.forEach(function(id,i){ payload['card_ids['+i+']'] = id; });
            crmPost(payload, function(resp){
                if(resp.success){ cb(resp.data); } else { alert('Erro: ' + (resp.data||'falha')); }
            });
        }

        $(document).on('change', '.crm-card-checkbox', _bulkUpdate);

        $('#crm-bulk-deselect').on('click', _bulkClear);

        $('#crm-bulk-ganho').on('click', function(){
            if(!confirm('Fechar ' + _bulkSelected().length + ' card(s) como GANHO?')) return;
            _bulkSend('fechar_ganho', {}, function(d){ alert('✅ ' + d.ok + '/' + d.total + ' cards fechados.'); _bulkClear(); location.reload(); });
        });
        $('#crm-bulk-perdido').on('click', function(){
            if(!confirm('Fechar ' + _bulkSelected().length + ' card(s) como PERDIDO?')) return;
            _bulkSend('fechar_perdido', {}, function(d){ alert('✅ ' + d.ok + '/' + d.total + ' cards fechados.'); _bulkClear(); location.reload(); });
        });

        $('#crm-bulk-transferir').on('click', function(){
            $('#crm-bulk-transfer-modal').css('display','flex');
        });
        $('#crm-bulk-transfer-cancel').on('click', function(){
            $('#crm-bulk-transfer-modal').hide();
        });
        $('#crm-bulk-transfer-confirm').on('click', function(){
            var uid = $('#crm-bulk-transfer-user').val();
            if(!uid) return;
            $('#crm-bulk-transfer-status').text('Transferindo…');
            _bulkSend('transferir', { novo_responsavel_id: uid }, function(d){
                $('#crm-bulk-transfer-modal').hide();
                alert('✅ ' + d.ok + '/' + d.total + ' cards transferidos.'); _bulkClear(); location.reload();
            });
        });
    }

    // ─── SLA BADGES (usa taoCrmSlaMinutos se disponível) ────────────────────
    function calcSla(){
        var now = Date.now();
        var slaMap = (typeof taoCrmSlaMinutos !== 'undefined') ? taoCrmSlaMinutos : {};
        $('.tao-crm-card[data-movido-em]').each(function(){
            var ts = $(this).data('movido-em');
            if(!ts) return;
            var moved = new Date(ts).getTime();
            if(isNaN(moved)) return;
            var mins = (now - moved) / 60000;
            var stageId = $(this).data('stage-id');
            var sla = slaMap[stageId] || {alerta:480, critico:960};
            $(this).removeClass('sla-yellow sla-red');
            if(mins >= sla.critico)      $(this).addClass('sla-red');
            else if(mins >= sla.alerta)  $(this).addClass('sla-yellow');
        });
    }
    calcSla();

    // ─── TEMPLATES: Carregar e inserir no textarea ────────────────────────────
    if($('#tao-crm-template-select').length && typeof taoCrmWorkspaceId !== 'undefined'){
        crmPost(
            { action:'tao_crm_get_templates', nonce:taoCrm.nonce, workspace_id:taoCrmWorkspaceId },
            function(resp){
                if(!resp.success || !resp.data || !resp.data.length) return;
                var $sel = $('#tao-crm-template-select');
                resp.data.forEach(function(t){
                    $sel.append($('<option>').val(t.conteudo).text(t.nome));
                });
            }
        );
    }

    $('#tao-crm-template-select').on('change', function(){
        var val = $(this).val();
        if(!val) return;
        var $txt = $('#tao-crm-msg-input');
        var cur  = $txt.val().trim();
        $txt.val(cur ? cur + '\n' + val : val);
        $(this).val('');
        $txt.focus();
    });

    // ─── AUTOMAÇÕES: mostrar/ocultar email_destino ────────────────────────────
    $('#af-acao').on('change.email', function(){
        var a = $(this).val();
        $('#af-email-wrap').toggle(a === 'notificar_email');
    });

    // ─── UTILS ───────────────────────────────────────────────────────────────
    function escHtml(str){
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function formatHoraBRT(utcStr){
        if(!utcStr) return '';
        try { var d=new Date(utcStr),h=(d.getUTCHours()-3+24)%24,m=d.getUTCMinutes(); return ('0'+h).slice(-2)+':'+('0'+m).slice(-2); } catch(e){ return ''; }
    }



// ── v1.3.0: Tag filter kanban + Browser notifications ─────────────────────

// Filter by tag in kanban
$(document).on('change', '#tao-crm-filter-tag', function(){
    var tag = $(this).val();
    $('.tao-crm-card').each(function(){
        var tags = $(this).data('tags') || [];
        if(typeof tags === 'string') try { tags = JSON.parse(tags); } catch(e){ tags=[]; }
        if(!tag || tags.indexOf(tag) !== -1){
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    // Update column counts after filter
    $('.kanban-column').each(function(){
        var total = $(this).find('.tao-crm-card:visible').length;
        $(this).find('.kanban-col-count').text(total);
    });
});

// Browser notification permission request
function taoCrmRequestNotification(){
    if('Notification' in window && Notification.permission === 'default'){
        Notification.requestPermission();
    }
}
taoCrmRequestNotification();

// Enhanced inbox poll: also check lembretes due today
var taoCrmLembreteSound = null;
function taoCrmPlayAlert(){
    if(!taoCrmLembreteSound){
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = 880; osc.type = 'sine';
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4);
    }
}

function taoCrmBrowserNotify(title, body){
    if('Notification' in window && Notification.permission === 'granted'){
        new Notification(title, {body: body, icon: ''});
    }
}

// Patch inbox badge check to also trigger browser notification on new messages
var taoCrmLastCount = 0;
var _origBadge = window.atualizarInboxBadge;
function atualizarInboxBadge(){
    crmPost({action:'tao_crm_inbox_count', nonce:taoCrm.nonce}, function(resp){
        if(resp.success){
            var c = parseInt(resp.data) || 0;
            var $b = $('.tao-crm-inbox-badge');
            if(c > 0){
                $b.text(c).show();
                document.title = '(' + c + ') TAO CRM';
                if(c > taoCrmLastCount && taoCrmLastCount !== 0){
                    taoCrmPlayAlert();
                    taoCrmBrowserNotify('Nova mensagem — TAO CRM', c + ' mensagem(ns) não lida(s)');
                }
            } else {
                $b.hide();
                document.title = document.title.replace(/^\([^)]+\)\s*/, '');
            }
            taoCrmLastCount = c;
        }
    });
}


})(jQuery);
