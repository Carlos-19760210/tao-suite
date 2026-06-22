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
    window.crmPost = crmPost; // expose globally so inline scripts in card.php can call it

    // ─── MARK READ ao abrir card ──────────────────────────────────────────────
    if(typeof taoCrmCardId !== 'undefined' && taoCrmCardId){
        crmPost({ action:'tao_crm_mark_read', nonce:taoCrm.nonce, card_id:taoCrmCardId });
    }

    // ─── INFO GERAL: Salvar / Cancelar ───────────────────────────────────────
    $('#crm-info-save').on('click', function(){
        var $btn  = $(this).prop('disabled', true).text('Salvando...');
        var $stat = $('#crm-info-status').hide();
        var resp  = $('#tao-crm-responsavel').val();
        var valor = $('#crm-valor-oportunidade').val();
        var done  = 0;
        var erros = [];

        function onDone(ok, msg){
            done++;
            if(!ok) erros.push(msg || 'Erro');
            if(done >= 2){
                $btn.prop('disabled', false).text('Salvar');
                if(erros.length){
                    alert('Erro ao salvar: ' + erros.join(', '));
                } else {
                    // Atualiza originais
                    $('#tao-crm-responsavel').data('original', resp);
                    $('#crm-valor-oportunidade').data('original', valor);
                    $stat.text('✔ salvo').show().delay(2500).fadeOut(400);
                }
            }
        }

        crmPost(
            { action:'tao_crm_save_responsavel', nonce:taoCrm.nonce, card_id:taoCrmCardId, responsavel_id:resp },
            function(r){ onDone(r.success, r.data); },
            function(e){ onDone(false, e); }
        );
        crmPost(
            { action:'tao_crm_save_valor_oportunidade', nonce:taoCrm.nonce, card_id:taoCrmCardId, valor:valor },
            function(r){ onDone(r.success, r.data); },
            function(e){ onDone(false, e); }
        );
    });

    $('#crm-info-cancel').on('click', function(){
        var $resp  = $('#tao-crm-responsavel');
        var $valor = $('#crm-valor-oportunidade');
        $resp.val($resp.data('original') || '');
        $valor.val($valor.data('original') || '');
        $('#crm-info-status').hide();
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
    var draggedCardId  = null;
    var draggedStageId = null;
    var _lastMovedCardId = null;

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
    // ─── BARRA PÓS-MOVIMENTAÇÃO ──────────────────────────────────────────────
    function _mostrarPosMovBar(){
        var $bar = $('#crm-pos-move-bar');
        if(!$bar.length) return;
        $bar.css('display','flex');
        setTimeout(function(){ $bar.css('transform','translateY(0)'); }, 20);
    }
    function _ocultarPosMovBar(){
        var $bar = $('#crm-pos-move-bar');
        $bar.css('transform','translateY(100%)');
        setTimeout(function(){ $bar.css('display','none'); }, 320);
    }

    $('#crm-posm-ganho').on('click', function(){
        _ocultarPosMovBar();
        $('#tao-crm-btn-ganho').trigger('click');
    });
    $('#crm-posm-perdido').on('click', function(){
        _ocultarPosMovBar();
        $('#tao-crm-btn-perdido').trigger('click');
    });
    $('#crm-posm-kanban').on('click', function(){
        if(typeof taoCrmKanbanUrl !== 'undefined' && taoCrmKanbanUrl){
            window.location.href = taoCrmKanbanUrl;
        } else { location.reload(); }
    });
    $('#crm-posm-fechar').on('click', function(){
        _ocultarPosMovBar();
        location.reload();
    });

    function fazerMoveCardDetalhe(cardId, estagioId, valores, $btn, $status){
        var data = { action:'tao_crm_move_card', nonce:taoCrm.nonce, card_id:cardId, estagio_id:estagioId };
        $.each(valores||{}, function(k,v){ data['valores['+k+']'] = v; });
        crmPost(data,
            function(resp){
                if($btn) $btn.prop('disabled',false).text('Mover');
                if(resp.success){
                    if($status) $status.css('color','green').text('✔ Movido!');
                    window.taoCrmCurrentStage = estagioId;
                    _mostrarPosMovBar();
                } else {
                    if(resp.data && resp.data.code === 'campos_faltando'){
                        mostrarCamposFaltando(resp.data.campos);
                    } else {
                        if($status) $status.css('color','red').text('✘ ' + (resp.data && resp.data.msg ? resp.data.msg : (resp.data || 'Erro')));
                    }
                }
            },
            function(err){
                if($btn) $btn.prop('disabled',false).text('Mover');
                if($status) $status.css('color','red').text('✘ ' + err);
            }
        );
    }

    $('#tao-crm-btn-move').on('click', function(){
        var newStage = $('#tao-crm-move-stage').val();
        if(!newStage || !window.taoCrmCardId) return;
        if(newStage === window.taoCrmCurrentStage) return;

        var $btn    = $(this).prop('disabled',true).text('Verificando...');
        var $status = $('#tao-crm-move-status');

        // Verifica campos obrigatórios na entrada da fase destino (igual ao drag-and-drop)
        crmPost(
            { action:'tao_crm_get_campos_destino', nonce:taoCrm.nonce, estagio_id:newStage, card_id:taoCrmCardId },
            function(resp){
                if(resp.success && resp.data && resp.data.campos && resp.data.campos.length > 0){
                    $btn.prop('disabled',false).text('Mover');
                    // Usa o modal de entrada existente, mas com callback de card-detail (reload)
                    mostrarModalEntrada(resp.data.campos, resp.data.valores || {}, taoCrmCardId, newStage, null, null);
                    $('#tao-crm-entrada-form').off('submit.entrada').on('submit.entrada', function(e){
                        e.preventDefault();
                        var vals = {};
                        $('[data-campo-id]', '#tao-crm-entrada-fields').each(function(){
                            vals[$(this).data('campo-id')] = $(this).val();
                        });
                        $('#tao-crm-entrada-modal').hide();
                        fazerMoveCardDetalhe(taoCrmCardId, newStage, vals, null, $status);
                    });
                } else {
                    $btn.text('Movendo...');
                    fazerMoveCardDetalhe(taoCrmCardId, newStage, {}, $btn, $status);
                }
            },
            function(){
                $btn.text('Movendo...');
                fazerMoveCardDetalhe(taoCrmCardId, newStage, {}, $btn, $status);
            }
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
                } else {
                    _lastMovedCardId = cardId;
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
                var iattrs = {type:itype,id:'entrada-f-'+c.id,'data-campo-id':c.id};
                if(itype === 'number') iattrs.step = 'any';
                input = $('<input>').addClass('regular-text').attr(iattrs).css('width','100%');
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

    var _enviando = false;
    function enviarMensagem(){
        if(_enviando) return;
        var texto = $('#tao-crm-msg-input').val().trim();
        if(!texto || !window.taoCrmCardId) return;
        _enviando = true;
        var $btn = $('#tao-crm-send-btn').prop('disabled',true).text('Enviando...');
        crmPost(
            { action:'tao_crm_send_message', nonce:taoCrm.nonce, card_id:taoCrmCardId, mensagem:texto },
            function(resp){
                _enviando = false;
                $btn.prop('disabled',false).text('Enviar ▶');
                if(resp.success){
                    $('#tao-crm-msg-input').val('');
                    if(resp.data && resp.data.msg){ renderMessage(resp.data.msg); scrollToBottom(); taoCrmLastMsg = resp.data.msg.enviado_em || taoCrmLastMsg; }
                    if(resp.data && resp.data.responsavel_changed){
                        var rc = resp.data.responsavel_changed;
                        $('#tao-crm-responsavel').val(String(rc.id)).data('original', String(rc.id));
                    }
                } else { alert('Erro ao enviar: ' + (resp.data || 'Tente novamente')); }
            },
            function(err){ _enviando = false; $btn.prop('disabled',false).text('Enviar ▶'); alert('Erro: ' + err); }
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
                    if(resp.data && resp.data.responsavel_changed){
                        var rc = resp.data.responsavel_changed;
                        $('#tao-crm-responsavel').val(String(rc.id)).data('original', String(rc.id));
                    }
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

    $(document).on('click', '#af-submit', function(e){
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
                    $st.css('color','green').text('✔ Salvo!');
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    var errMsg = resp.data || 'Erro ao salvar automação';
                    $st.css('color','red').text('✘ ' + errMsg);
                    alert('Erro ao salvar automação: ' + errMsg);
                }
            },
            function(err){
                $btn.prop('disabled', false);
                $st.css('color','red').text('✘ ' + err);
                alert('Erro de comunicação ao salvar automação: ' + err);
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

    var _fecharValores = {};
    var _fecharCardId  = '';

    function _abrirModalFechar(tipo, campos, valores) {
        campos = campos || []; valores = valores || {};
        if (tipo === 'ganho') {
            $('#tao-crm-fechar-titulo').text('✅ Fechar Negócio');
            $('#tao-crm-fechar-desc').text('O card será movido para Venda Concluída e fechado. A próxima mensagem do cliente abrirá um novo card automaticamente.');
            $('#tao-crm-fechar-tipo').val('ganho');
            $('#tao-crm-fechar-btn').removeClass('btn-perdido').addClass('button-primary').text('Confirmar');
        } else {
            $('#tao-crm-fechar-titulo').text('❌ Cancelar Negócio');
            $('#tao-crm-fechar-desc').text('O card será movido para Cards Cancelados e fechado. A próxima mensagem do cliente abrirá um novo card automaticamente.');
            $('#tao-crm-fechar-tipo').val('perdido');
            $('#tao-crm-fechar-btn').removeClass('button-primary').addClass('btn-perdido').text('Confirmar');
        }
        preencherMotivos(tipo === 'ganho' ? motivosGanho : motivosPerdido);
        var css = {width:'100%',fontSize:'13px',padding:'6px 8px',border:'1px solid #d1d5db',borderRadius:'4px',boxSizing:'border-box'};
        var $wrap = $('#tao-crm-fechar-campos-wrap').empty().toggle(campos.length > 0);
        campos.forEach(function(c) {
            var input;
            if (c.tipo === 'boolean') {
                input = $('<select>').css(css).attr({'data-fechar-campo-id': c.id, 'data-obrigatorio': c.obrigatorio ? '1' : '0'});
                input.append($('<option>').val('').text('— selecione —'));
                input.append($('<option>').val('Sim').text('Sim'));
                input.append($('<option>').val('Não').text('Não'));
            } else if (c.tipo === 'select') {
                var opts = Array.isArray(c.opcoes) ? c.opcoes : (c.opcoes ? JSON.parse(c.opcoes) : []);
                input = $('<select>').css(css).attr({'data-fechar-campo-id': c.id, 'data-obrigatorio': c.obrigatorio ? '1' : '0'});
                input.append($('<option>').val('').text('— selecione —'));
                opts.forEach(function(o) { input.append($('<option>').val(o).text(o)); });
            } else if (c.tipo === 'textarea') {
                input = $('<textarea>').css(css).attr({'data-fechar-campo-id': c.id, 'data-obrigatorio': c.obrigatorio ? '1' : '0', rows: 3});
            } else {
                var itype = c.tipo === 'number' ? 'number' : (c.tipo === 'date' ? 'date' : (c.tipo === 'email' ? 'email' : (c.tipo === 'phone' ? 'tel' : 'text')));
                var fAttrs = {type: itype, 'data-fechar-campo-id': c.id, 'data-obrigatorio': c.obrigatorio ? '1' : '0'};
                if(itype === 'number') fAttrs.step = 'any';
                input = $('<input>').css(css).attr(fAttrs);
            }
            if (valores[c.id] !== undefined && valores[c.id] !== null && valores[c.id] !== '') input.val(valores[c.id]);
            var req = c.obrigatorio ? ' <span style="color:#ef4444">*</span>' : '';
            $wrap.append($('<div>').css({marginBottom: '12px'})
                .append($('<label>').css({fontSize: '13px', fontWeight: '600', display: 'block', marginBottom: '4px'}).html(c.nome + req))
                .append(input));
        });
        $('#tao-crm-fechar-modal').fadeIn(150);
    }

    $('#tao-crm-btn-ganho').on('click', function() {
        // Regra: para fechar como Ganho é preciso ao menos 1 item do negócio
        // (do catálogo, um orçamento de fórmula, ou via "+ Item")
        var nItens = $('#crm-itens-list .crm-item-row').length;
        var nOrcs  = $('#crm-formulas-list tr').length;
        if (nItens + nOrcs < 1) {
            alert('Adicione ao menos um item ao negócio (catálogo, orçamento ou "+ Item") antes de fechar o negócio.');
            return;
        }
        var cardId = (typeof taoCrmCardId !== 'undefined') ? taoCrmCardId : '';
        _fecharCardId = cardId;
        var campos = (typeof taoCrmGanhoCampos !== 'undefined') ? taoCrmGanhoCampos : [];
        var valores = (typeof taoCrmGanhoValores !== 'undefined') ? taoCrmGanhoValores : {};
        _abrirModalFechar('ganho', campos, valores);
    });

    $('#tao-crm-btn-perdido').on('click', function() {
        _fecharValores = {};
        _abrirModalFechar('perdido', [], {});
    });

    $('#tao-crm-fechar-modal').on('click', function(e){
        if($(e.target).is('#tao-crm-fechar-modal')) $(this).hide();
    });

    $('#tao-crm-fechar-form').on('submit', function(e){
        e.preventDefault();
        var camposValores = {}, camposOk = true;
        $('[data-fechar-campo-id]', '#tao-crm-fechar-campos-wrap').each(function() {
            var $el = $(this), val = $el.val();
            var isObrig = $el.data('obrigatorio') === '1' || $el.data('obrigatorio') === 1;
            if (isObrig && (!val || val === '')) {
                $el.css('borderColor', '#ef4444'); camposOk = false;
            } else {
                $el.css('borderColor', '#d1d5db');
                if (val && val !== '') camposValores[$el.data('fechar-campo-id')] = val;
            }
        });
        if (!camposOk) return;
        _fecharValores = camposValores;
        var tipo   = $('#tao-crm-fechar-tipo').val();
        var motivo = $('#tao-crm-fechar-motivo').val();
        if (motivo === 'Outro (especificar)') {
            motivo = $('#tao-crm-fechar-outro').val().trim() || 'Outro';
        }
        var $btn = $('#tao-crm-fechar-btn').prop('disabled', true).text('Fechando...');
        var cardToClose = _fecharCardId || (typeof taoCrmCardId !== 'undefined' ? taoCrmCardId : '');
        var postData = {action: 'tao_crm_fechar_card', nonce: taoCrm.nonce, card_id: cardToClose, tipo: tipo, motivo: motivo};
        $.each(_fecharValores || {}, function(k, v) { postData['valores[' + k + ']'] = v; });
        crmPost(
            postData,
            function(resp){
                $btn.prop('disabled', false).text('Confirmar');
                _fecharCardId = '';
                if(resp.success){
                    $('#tao-crm-fechar-modal').hide();
                    if(typeof _ocultarKanbanPosMovBar === 'function') _ocultarKanbanPosMovBar();
                    $('.crm-card-checkbox').prop('checked', false);
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
            function(err){ $btn.prop('disabled', false).text('Confirmar'); _fecharCardId = ''; alert('Erro: ' + err); }
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

    // ─── FORMALIZAR ──────────────────────────────────────────────────────────
    var _fmTipo  = 'Orçamento';
    var _fmItens = [];

    $(document).on('click', '#crm-formalizar-btn', function(){
        _fmTipo = 'Orçamento';
        $('#crm-formalizar-tipo-group .crm-formalizar-tipo-btn').each(function(){
            var ativo = $(this).data('tipo') === _fmTipo;
            $(this).css({ background: ativo ? '#6366f1' : '#fff', color: ativo ? '#fff' : '#374151', borderColor: ativo ? '#6366f1' : '#d1d5db' });
        });
        $('#crm-formalizar-pagamento, #crm-formalizar-obs').val('');
        $('#crm-formalizar-prazo').val('Válido por 7 dias');
        crmPost(
            { action:'tao_crm_get_card_itens', nonce:taoCrm.nonce, card_id:taoCrmCardId },
            function(resp){
                _fmItens = [];
                if(resp.success && resp.data && resp.data.length){
                    resp.data.forEach(function(it){
                        _fmItens.push({ desc: it.descricao || '', qtd: parseFloat(it.quantidade) || 1, valor: parseFloat(it.total) || 0 });
                    });
                }
                _fmRenderItens(); _fmAtualizarPreview();
                $('#crm-formalizar-modal').fadeIn(150);
            },
            function(){
                _fmItens = []; _fmRenderItens(); _fmAtualizarPreview();
                $('#crm-formalizar-modal').fadeIn(150);
            }
        );
    });

    $('#crm-formalizar-fechar').on('click', function(){ $('#crm-formalizar-modal').fadeOut(150); });
    $('#crm-formalizar-modal').on('click', function(e){ if($(e.target).is('#crm-formalizar-modal')) $(this).fadeOut(150); });

    $('#crm-formalizar-tipo-group').on('click', '.crm-formalizar-tipo-btn', function(){
        _fmTipo = $(this).data('tipo');
        $('#crm-formalizar-tipo-group .crm-formalizar-tipo-btn').each(function(){
            var ativo = $(this).data('tipo') === _fmTipo;
            $(this).css({ background: ativo ? '#6366f1' : '#fff', color: ativo ? '#fff' : '#374151', borderColor: ativo ? '#6366f1' : '#d1d5db' });
        });
        _fmAtualizarPreview();
    });

    $('#crm-formalizar-add-item').on('click', function(){
        _fmItens.push({ desc:'', qtd:1, valor:0 });
        _fmRenderItens();
        $('#crm-formalizar-itens-body tr:last .fm-item-desc').focus();
    });

    $(document).on('input change', '#crm-formalizar-itens-body input', function(){
        var $row = $(this).closest('tr'), idx = parseInt($row.data('idx'));
        _fmItens[idx] = {
            desc:  $row.find('.fm-item-desc').val(),
            qtd:   parseFloat($row.find('.fm-item-qtd').val())   || 0,
            valor: parseFloat($row.find('.fm-item-valor').val()) || 0
        };
        _fmAtualizarPreview();
    });
    $(document).on('click', '.fm-item-rm', function(){
        var idx = parseInt($(this).closest('tr').data('idx'));
        _fmItens.splice(idx, 1); _fmRenderItens(); _fmAtualizarPreview();
    });
    $('#crm-formalizar-pagamento, #crm-formalizar-prazo, #crm-formalizar-obs').on('input', _fmAtualizarPreview);

    function _fmRenderItens(){
        var $b = $('#crm-formalizar-itens-body').empty();
        if(!_fmItens.length){
            $b.append('<tr><td colspan="4" style="padding:10px 8px;color:#94a3b8;font-style:italic;font-size:12px">Nenhum item — clique em "+ Item" para adicionar.</td></tr>');
            return;
        }
        _fmItens.forEach(function(it, i){
            $b.append(
                '<tr data-idx="'+i+'">' +
                '<td style="padding:3px 2px"><input class="fm-item-desc" type="text" value="'+escHtml(it.desc)+'" placeholder="Descrição" style="width:100%;font-size:12px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px"></td>' +
                '<td style="padding:3px 2px"><input class="fm-item-qtd" type="number" value="'+it.qtd+'" min="0.001" step="0.001" style="width:48px;font-size:12px;padding:4px;border:1px solid #d1d5db;border-radius:4px;text-align:center"></td>' +
                '<td style="padding:3px 2px"><input class="fm-item-valor" type="number" value="'+it.valor+'" min="0" step="0.01" style="width:80px;font-size:12px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;text-align:right"></td>' +
                '<td style="padding:3px 2px;text-align:center"><button type="button" class="fm-item-rm" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:13px;padding:2px 4px" title="Remover">&#x2715;</button></td>' +
                '</tr>'
            );
        });
    }

    function _fmMoeda(v){
        var n = parseFloat(v||0);
        return 'R$ ' + n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    }

    function _fmAtualizarPreview(){
        var total = 0;
        _fmItens.forEach(function(it){ total += (parseFloat(it.qtd)||0) * (parseFloat(it.valor)||0); });
        $('#crm-formalizar-total').text(_fmMoeda(total));

        var nome  = (typeof taoCrmContatoNome !== 'undefined' && taoCrmContatoNome) ? taoCrmContatoNome : 'Cliente';
        var pag   = $('#crm-formalizar-pagamento').val().trim();
        var prazo = $('#crm-formalizar-prazo').val().trim();
        var obs   = $('#crm-formalizar-obs').val().trim();

        var linhas = ['📋 *' + _fmTipo.toUpperCase() + ' — ' + nome + '*', ''];
        var temItens = false;
        _fmItens.forEach(function(it){
            if(!it.desc) return;
            temItens = true;
            var sub = (parseFloat(it.qtd)||0) * (parseFloat(it.valor)||0);
            var qtdStr = (parseFloat(it.qtd||0) % 1 === 0) ? parseInt(it.qtd)+'x' : parseFloat(it.qtd).toFixed(2).replace('.',',')+'x';
            linhas.push('• ' + it.desc + ' — ' + qtdStr + ' — ' + _fmMoeda(sub));
        });
        if(temItens) { linhas.push(''); linhas.push('*Total: ' + _fmMoeda(total) + '*'); }
        if(pag)   linhas.push('Pagamento: ' + pag);
        if(prazo) linhas.push('Prazo: ' + prazo);
        if(obs)   { linhas.push(''); linhas.push(obs); }

        $('#crm-formalizar-preview').text(linhas.join('\n'));
    }

    $('#crm-formalizar-nota-btn').on('click', function(){
        var texto = $('#crm-formalizar-preview').text().trim();
        if(!texto) return;
        var $btn = $(this).prop('disabled',true).text('Salvando...');
        crmPost(
            { action:'tao_crm_save_nota', nonce:taoCrm.nonce, card_id:taoCrmCardId, conteudo:texto },
            function(resp){
                $btn.prop('disabled',false).text('Salvar como Nota');
                if(resp.success){
                    $('#crm-formalizar-modal').fadeOut(150);
                    if(resp.data && resp.data.id){ renderMessage(resp.data); scrollToBottom(); }
                } else { alert('Erro: ' + (resp.data||'Tente novamente')); }
            },
            function(err){ $btn.prop('disabled',false).text('Salvar como Nota'); alert('Erro: '+err); }
        );
    });

    $('#crm-formalizar-whatsapp-btn').on('click', function(){
        var texto = $('#crm-formalizar-preview').text().trim();
        if(!texto) return;
        var $btn = $(this).prop('disabled',true).text('Enviando...');
        crmPost(
            { action:'tao_crm_send_message', nonce:taoCrm.nonce, card_id:taoCrmCardId, mensagem:texto },
            function(resp){
                $btn.prop('disabled',false).text('📲 Enviar no WhatsApp');
                if(resp.success){
                    $('#crm-formalizar-modal').fadeOut(150);
                    if(resp.data && resp.data.msg){ renderMessage(resp.data.msg); scrollToBottom(); taoCrmLastMsg = resp.data.msg.enviado_em || taoCrmLastMsg; }
                } else { alert('Erro: ' + (resp.data||'Tente novamente')); }
            },
            function(err){ $btn.prop('disabled',false).text('📲 Enviar no WhatsApp'); alert('Erro: '+err); }
        );
    });

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
        var qDigits     = q.replace(/\D/g, '');   // só dígitos: busca por telefone com (), - e espaços
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
            var _sd      = ($el.data('search') || '').toLowerCase();
            var matchQ   = !q || _sd.indexOf(q) !== -1
                           || (qDigits.length >= 4 && _sd.replace(/\D/g, '').indexOf(qDigits) !== -1);
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
            var _sd2    = ($el.data('search') || '').toLowerCase();
            var matchQ  = !q || _sd2.indexOf(q) !== -1
                          || (qDigits.length >= 4 && _sd2.replace(/\D/g, '').indexOf(qDigits) !== -1);
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
        var _kSince  = typeof taoCrmLoadedAt !== 'undefined' ? taoCrmLoadedAt : '';
        var _kPoller = null;
        var _REFRESH_KEY = 'tao_crm_refresh_interval';

        // Restaura intervalo salvo
        var _savedInterval = parseInt(localStorage.getItem(_REFRESH_KEY) || '20', 10);
        var $intSel = $('#tao-crm-refresh-interval');
        if($intSel.find('option[value="' + _savedInterval + '"]').length){
            $intSel.val(_savedInterval);
        }

        function _getInterval(){ return parseInt($('#tao-crm-refresh-interval').val() || '20', 10); }

        $intSel.on('change', function(){
            localStorage.setItem(_REFRESH_KEY, $(this).val());
            if(_kPoller){ clearTimeout(_kPoller); _kPoller = null; }
            if(_getInterval() > 0) _scheduleKanbanPoll();
        });

        var _kRefreshing = false;
        function _softRefreshKanban(){
            if(_kRefreshing) return;
            _kRefreshing = true;
            var $board = $('#tao-crm-board');
            var scrollLeft = $board[0] ? $board[0].scrollLeft : 0;
            $board.css({ opacity:'0.4', transition:'opacity 0.15s' });
            fetch(window.location.href, { credentials:'same-origin' })
                .then(function(r){ return r.text(); })
                .then(function(html){
                    var doc = (new DOMParser()).parseFromString(html, 'text/html');
                    var newBoard = doc.getElementById('tao-crm-board');
                    if(newBoard && $board[0]){
                        $board[0].innerHTML = newBoard.innerHTML;
                        $board[0].scrollLeft = scrollLeft;
                    }
                    var m = html.match(/taoCrmLoadedAt\s*=\s*"([^"]+)"/);
                    if(m) _kSince = m[1];
                    $board.css({ opacity:'1' });
                    _kRefreshing = false;
                })
                .catch(function(){ $board.css({ opacity:'1' }); _kRefreshing = false; location.reload(); });
        }
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
                    if(last && _kSince && last > _kSince){ _softRefreshKanban(); }
                }
            );
        }
        function _scheduleKanbanPoll(){
            if(_kPoller) clearTimeout(_kPoller);
            var ms = _getInterval();
            if(ms <= 0) return; // desligado
            _kPoller = setTimeout(function(){ _doKanbanCheck(); _scheduleKanbanPoll(); }, document.hidden ? Math.max(ms, 60) * 1000 : ms * 1000);
        }
        document.addEventListener('visibilitychange', function(){ if(!document.hidden && _getInterval() > 0) _scheduleKanbanPoll(); });
        if(_getInterval() > 0) _scheduleKanbanPoll();

        // ─── Barra pós-movimentação (kanban drag) ────────────────────────────
        function _mostrarKanbanPosMovBar(){
            var $bar = $('#crm-kanban-pos-move-bar'); if(!$bar.length) return;
            $bar.css('display','flex');
            setTimeout(function(){ $bar.css('transform','translateY(0)'); }, 20);
        }
        function _ocultarKanbanPosMovBar(){
            var $bar = $('#crm-kanban-pos-move-bar');
            $bar.css('transform','translateY(100%)');
            setTimeout(function(){ $bar.css('display','none'); }, 320);
        }
        $('#crm-kposm-fechar').on('click', function(){ _ocultarKanbanPosMovBar(); });
        $('#crm-kposm-fechar').on('click', function(){
            $('.crm-card-checkbox').prop('checked', false);
            _ocultarKanbanPosMovBar();
        });

        $('#crm-kposm-ganho').on('click', function(){
            var ids = $('.crm-card-checkbox:checked').map(function(){ return $(this).data('card-id'); }).get();
            if(!ids.length) return;
            if(ids.length === 1) {
                // 1 card selecionado: abre checklist de campos pré-carregados (sem AJAX)
                _fecharCardId = ids[0];
                var campos = (typeof taoCrmGanhoCampos !== 'undefined') ? taoCrmGanhoCampos : [];
                var valores = (typeof taoCrmGanhoValores !== 'undefined') ? taoCrmGanhoValores : {};
                _abrirModalFechar('ganho', campos, valores);
            } else {
                // Múltiplos cards: ação em lote (sem checklist)
                if(!confirm('Fechar ' + ids.length + ' card(s) como GANHO?')) return;
                var $btn = $(this).prop('disabled', true).text('Fechando...');
                var payload = { action:'tao_crm_bulk_action', nonce:taoCrm.nonce, bulk_action:'fechar_ganho' };
                ids.forEach(function(id, i){ payload['card_ids['+i+']'] = id; });
                crmPost(payload,
                    function(resp){
                        $btn.prop('disabled', false).text('✅ Fechar como Ganho');
                        if(resp.success) alert('✅ ' + resp.data.ok + '/' + resp.data.total + ' cards fechados.');
                        else alert('Erro: ' + (resp.data||'falha'));
                        $('.crm-card-checkbox').prop('checked', false);
                        _ocultarKanbanPosMovBar();
                        location.reload();
                    },
                    function(err){ $btn.prop('disabled', false).text('✅ Fechar como Ganho'); alert('Erro: ' + err); }
                );
            }
        });

        $('#crm-kposm-perdido').on('click', function(){
            var ids = $('.crm-card-checkbox:checked').map(function(){ return $(this).data('card-id'); }).get();
            if(!ids.length) return;
            if(!confirm('Fechar ' + ids.length + ' card(s) como PERDIDO?')) return;
            var $btn = $(this).prop('disabled', true).text('Fechando...');
            var payload = { action:'tao_crm_bulk_action', nonce:taoCrm.nonce, bulk_action:'fechar_perdido' };
            ids.forEach(function(id, i){ payload['card_ids['+i+']'] = id; });
            crmPost(payload,
                function(resp){
                    $btn.prop('disabled', false).text('❌ Negócio Perdido');
                    if(resp.success) alert('✅ ' + resp.data.ok + '/' + resp.data.total + ' cards fechados.');
                    else alert('Erro: ' + (resp.data||'falha'));
                    $('.crm-card-checkbox').prop('checked', false);
                    _ocultarKanbanPosMovBar();
                    location.reload();
                },
                function(err){ $btn.prop('disabled', false).text('❌ Negócio Perdido'); alert('Erro: ' + err); }
            );
        });

        $('#crm-kposm-transferir').on('click', function(){
            if(!$('.crm-card-checkbox:checked').length) return;
            $('#crm-bulk-transfer-modal').css('display', 'flex');
        });
    }

    // ─── KANBAN: Preservar posição horizontal ao navegar para card ──────────
    if($('#tao-crm-board').length){
        var _savedScroll = localStorage.getItem('tao_crm_kanban_scroll');
        if(_savedScroll){ $('#tao-crm-board').scrollLeft(parseInt(_savedScroll, 10)); }
        $(window).on('beforeunload', function(){
            localStorage.setItem('tao_crm_kanban_scroll', $('#tao-crm-board').scrollLeft());
        });
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



// ─── v1.8.0: ITENS DE VENDA ───────────────────────────────────────────────────

var taoCrmItens = {
    itens: [],
    catalogo: [],
    cardId: null,
    fechado: false,
    _editandoId:    null,
    _editandoCatId: '',

    fmtBRL: function(v){
        return 'R$ ' + parseFloat(v||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    },

    calcTotal: function(qtd, preco, tipoDesc, valDesc){
        var bruto = parseFloat(qtd||1) * parseFloat(preco||0);
        if(tipoDesc === 'valor') return Math.max(0, bruto - parseFloat(valDesc||0));
        return Math.max(0, bruto * (1 - Math.min(100,Math.max(0,parseFloat(valDesc||0)))/100));
    },

    atualizaRodape: function(){
        var grand = 0;
        this.itens.forEach(function(it){ grand += parseFloat(it.total||0); });
        $('#crm-itens-grand-total').text(taoCrmItens.fmtBRL(grand));
        $('#crm-itens-footer').css('display', grand > 0 ? 'flex' : 'none');
        window._crmItensTotal = grand;
        if (typeof window.atualizarOportunidade === 'function') {
            window.atualizarOportunidade();
        } else {
            $('#crm-valor-oportunidade').val(grand.toFixed(2));
        }
    },

    renderLista: function(){
        var self = this;
        var $list = $('#crm-itens-list').empty();
        if(!self.itens.length){
            $list.append($('<div class="crm-itens-empty">').text('Nenhum item adicionado'));
            self.atualizaRodape();
            return;
        }
        self.itens.forEach(function(it){
            var $row = $('<div class="crm-item-row">');
            var detalhe = it.quantidade + ' × ' + taoCrmItens.fmtBRL(it.preco_unitario);
            if(parseFloat(it.desconto_valor) > 0){
                detalhe += (it.desconto_tipo==='pct') ? ' − '+it.desconto_valor+'%' : ' − '+taoCrmItens.fmtBRL(it.desconto_valor);
            }
            var $info = $('<div class="crm-item-info">').append(
                $('<div class="crm-item-nome">').append(
                    it.catalogo_id ? $('<span title="Produto do catálogo">📦 </span>') : null,
                    document.createTextNode(it.descricao)
                ),
                $('<div class="crm-item-detalhe">').text(detalhe)
            );
            var $total = $('<div class="crm-item-row-total">').text(taoCrmItens.fmtBRL(it.total));
            var $acoes = $('<div class="crm-item-acoes">');
            if(!self.fechado){
                $acoes.append(
                    $('<button type="button" class="crm-item-edit" title="Editar">').text('✏').on('click',(function(item){ return function(){ taoCrmItens.abrirModal(item); }; })(it)),
                    $('<button type="button" class="crm-item-del"  title="Remover">').text('✕').on('click',(function(item,$r){ return function(){ taoCrmItens.deletarItem(item.id,$r); }; })(it,$row))
                );
            }
            $row.append($info, $total, $acoes);
            $list.append($row);
        });
        self.atualizaRodape();
    },

    _atualizaModalTotal: function(){
        var qtd   = parseFloat($('#crm-item-f-qtd').val()||1);
        var preco = parseFloat($('#crm-item-f-preco').val()||0);
        var tipo  = $('#crm-item-f-desc-tipo').val();
        var dv    = parseFloat($('#crm-item-f-desc-val').val()||0);
        $('#crm-item-f-total').text(taoCrmItens.fmtBRL(taoCrmItens.calcTotal(qtd,preco,tipo,dv)));
    },

    _renderCatalogo: function(filtro){
        var self = this;
        var $lista = $('#crm-item-catalogo-lista').empty();
        var items = filtro ? self.catalogo.filter(function(p){ return p.nome.toLowerCase().indexOf(filtro.toLowerCase())>=0; }) : self.catalogo;
        if(!items.length){ $lista.append($('<div class="crm-catalogo-vazio">').text('Nenhum produto encontrado')); return; }
        items.forEach(function(p){
            $('<div class="crm-catalogo-item">').append(
                $('<span class="crm-catalogo-nome">').text(p.nome),
                $('<span class="crm-catalogo-preco">').text(taoCrmItens.fmtBRL(p.preco))
            ).on('click', function(){
                self._editandoCatId = p.id;
                $('#crm-item-f-desc').val(p.nome);
                $('#crm-item-f-preco').val(parseFloat(p.preco||0).toFixed(2));
                $('#crm-item-f-qtd').val(1);
                $('#crm-item-f-desc-val').val(0);
                self._atualizaModalTotal();
                $('#crm-item-catalogo-section').slideUp(150);
                setTimeout(function(){ $('#crm-item-f-qtd').focus().select(); }, 160);
            }).appendTo($lista);
        });
    },

    abrirModal: function(item){
        var self = this;
        self._editandoId    = item ? item.id    : null;
        self._editandoCatId = item ? (item.catalogo_id||'') : '';
        $('#crm-item-modal-titulo').text(item ? 'Editar Item' : 'Adicionar Item');
        $('#crm-item-f-desc').val(item ? item.descricao       : '');
        $('#crm-item-f-qtd' ).val(item ? item.quantidade      : 1);
        $('#crm-item-f-preco').val(item ? item.preco_unitario  : 0);
        $('#crm-item-f-desc-tipo').val(item ? (item.desconto_tipo||'pct') : 'pct');
        $('#crm-item-f-desc-val').val(item ? (item.desconto_valor||0)     : 0);
        self._atualizaModalTotal();
        if(self.catalogo.length > 0 && !item){
            $('#crm-item-catalogo-section').show();
            $('#crm-item-busca').val('');
            self._renderCatalogo('');
            setTimeout(function(){ $('#crm-item-busca').focus(); }, 50);
        } else {
            $('#crm-item-catalogo-section').hide();
            setTimeout(function(){ $('#crm-item-f-desc').focus(); }, 50);
        }
        $('#crm-item-modal').fadeIn(150);
    },

    salvarModal: function(){
        var self = this;
        var desc = $('#crm-item-f-desc').val().trim();
        if(!desc){ $('#crm-item-f-desc').addClass('crm-field-error').focus(); return; }
        $('#crm-item-f-desc').removeClass('crm-field-error');
        var $btn = $('#crm-item-modal-salvar').prop('disabled',true).text('Salvando...');
        crmPost({
            action:'tao_crm_save_card_item', nonce:taoCrm.nonce,
            card_id:        self.cardId,
            item_id:        self._editandoId  || '',
            catalogo_id:    self._editandoCatId || '',
            descricao:      desc,
            quantidade:     $('#crm-item-f-qtd').val(),
            preco_unitario: $('#crm-item-f-preco').val(),
            desconto_tipo:  $('#crm-item-f-desc-tipo').val(),
            desconto_valor: $('#crm-item-f-desc-val').val(),
            ordem:          self._editandoId ? self.itens.findIndex(function(x){return x.id===self._editandoId;}) : self.itens.length
        }, function(r){
            $btn.prop('disabled',false).text('Salvar Item');
            if(!r.success){ alert('Erro: '+(r.data||'Tente novamente')); return; }
            var saved = r.data;
            if(self._editandoId){
                var idx = self.itens.findIndex(function(x){return x.id===self._editandoId;});
                if(idx>=0) self.itens[idx]=saved; else self.itens.push(saved);
            } else {
                self.itens.push(saved);
            }
            self.renderLista();
            $('#crm-item-modal').fadeOut(150);
        }, function(){ $btn.prop('disabled',false).text('Salvar Item'); });
    },

    deletarItem: function(itemId, $row){
        var self = this;
        if(!confirm('Remover este item?')) return;
        crmPost({action:'tao_crm_delete_card_item', nonce:taoCrm.nonce, card_id:self.cardId, item_id:itemId}, function(r){
            if(!r.success) return;
            self.itens = self.itens.filter(function(x){return x.id!==itemId;});
            self.renderLista();
        });
    },

    carregar: function(){
        var self = this;
        $('#crm-itens-loading').show();
        crmPost({action:'tao_crm_get_card_itens', nonce:taoCrm.nonce, card_id:self.cardId}, function(r){
            $('#crm-itens-loading').hide();
            if(!r.success) return;
            self.itens = r.data || [];
            self.renderLista();
        });
    },

    init: function(){
        var self = this;
        var $sec = $('.crm-itens-section');
        if(!$sec.length) return;
        self.cardId  = $sec.data('card-id');
        self.fechado = $sec.data('fechado') == '1';
        crmPost({action:'tao_crm_get_catalogo_para_card', nonce:taoCrm.nonce, card_id:self.cardId}, function(r){
            self.catalogo = (r.success && r.data) ? r.data : [];
        });
        self.carregar();
        // namespace .crm-itens garante que init() chamado N vezes não duplica handlers
        $(document).off('.crm-itens');
        $(document).on('click.crm-itens',  '#crm-item-add',             function(){ taoCrmItens.abrirModal(null); });
        $(document).on('click.crm-itens',  '#crm-item-modal-fechar, #crm-item-modal-cancelar', function(){ $('#crm-item-modal').fadeOut(150); });
        $(document).on('click.crm-itens',  '#crm-item-modal',            function(e){ if($(e.target).is('#crm-item-modal')) $('#crm-item-modal').fadeOut(150); });
        $(document).on('click.crm-itens',  '#crm-item-modal-salvar',     function(){ taoCrmItens.salvarModal(); });
        $(document).on('input.crm-itens',  '#crm-item-f-qtd,#crm-item-f-preco,#crm-item-f-desc-val', taoCrmItens._atualizaModalTotal);
        $(document).on('change.crm-itens', '#crm-item-f-desc-tipo',      taoCrmItens._atualizaModalTotal);
        $(document).on('input.crm-itens',  '#crm-item-busca',            function(){ taoCrmItens._renderCatalogo($(this).val()); });
        $(document).on('keydown.crm-itens','#crm-item-modal',            function(e){ if(e.key==='Escape') $('#crm-item-modal').fadeOut(150); });
    }
};

// ─── REMOVER funções antigas vinculadas à tabela (não mais usadas) ─────────────

// renderLinha foi substituído por renderLista — bloco abaixo é placeholder vazio
var _taoCrmItensLegado = { renderLinha: function(it){
        var tid  = it.id;
        var edit = !this.fechado;
        var row  = $('<tr>').attr('data-item-id', tid).attr('data-catalogo-id', it.catalogo_id || '');

        // Badge de origem: mostra se veio do catálogo TAO Neo
        var origemBadge = (it.catalogo_id && !edit)
            ? $('<span>').text('📦').attr('title','Produto do catálogo TAO Neo').css({fontSize:'11px',marginRight:'3px'})
            : null;

        var descEl = edit
            ? $('<input type="text" class="crm-item-descricao">').val(it.descricao)
            : $('<span>').text(it.descricao);

        var qtdEl = edit
            ? $('<input type="number" class="crm-item-qtd" min="0.001" step="0.001">').val(it.quantidade)
            : $('<span>').text(it.quantidade);

        var precoEl = edit
            ? $('<input type="number" class="crm-item-preco" min="0" step="0.01">').val(it.preco_unitario)
            : $('<span>').text(it.preco_unitario);

        var descTipoSel = $('<select class="crm-item-desc-tipo">')
            .append('<option value="pct">%</option><option value="valor">R$</option>')
            .val(it.desconto_tipo||'pct');

        var descValEl = edit
            ? $('<input type="number" class="crm-item-desc-val" min="0" step="0.01">').val(it.desconto_valor||0)
            : $('<span>').text(it.desconto_valor||0);

        var descCell = $('<td>').css({whiteSpace:'nowrap',gap:'2px'}).append(
            edit ? descTipoSel : $('<span>').text(it.desconto_tipo==='valor'?'R$':'%'),
            ' ',
            descValEl
        );

        var totalEl = $('<td class="crm-item-total-cell">').css({fontWeight:600,whiteSpace:'nowrap',fontSize:'12px'})
            .text(taoCrmItens.fmtBRL(it.total));

        var acaoCell = $('<td>');
        if(edit){
            acaoCell.append(
                $('<button type="button" class="crm-item-del" title="Remover item">').text('✕')
            );
        }

        var descCell2 = $('<td>').css({minWidth:'80px'});
        if(origemBadge) descCell2.append(origemBadge);
        descCell2.append(descEl);

        row.append(
            descCell2,
            $('<td>').append(qtdEl),
            $('<td>').append(precoEl),
            descCell,
            totalEl,
            acaoCell
        );

        if(edit){
            // Recalcula total em tempo real ao alterar qualquer campo
            row.find('.crm-item-qtd,.crm-item-preco,.crm-item-desc-tipo,.crm-item-desc-val').on('input change', function(){
                var qtd   = parseFloat(row.find('.crm-item-qtd').val()||1);
                var preco = parseFloat(row.find('.crm-item-preco').val()||0);
                var tipo  = row.find('.crm-item-desc-tipo').val();
                var dv    = parseFloat(row.find('.crm-item-desc-val').val()||0);
                var tot   = taoCrmItens.calcTotal(qtd,preco,tipo,dv);
                row.find('.crm-item-total-cell').text(taoCrmItens.fmtBRL(tot));
                // Atualiza cache local
                var idx = taoCrmItens.itens.findIndex(function(x){return x.id===tid;});
                if(idx>=0) taoCrmItens.itens[idx].total = tot;
                taoCrmItens.atualizaFooter();
            });

            // Salva no blur de qualquer campo
            row.find('input,select').on('blur', function(){
                taoCrmItens.salvarLinha(row, tid);
            });

            // Botão remover
            row.find('.crm-item-del').on('click', function(){
                taoCrmItens.deletarItem(tid, row);
            });
        }
        return row;
    },

    carregar: function(){
        var self = this;
        $('#crm-itens-loading').show();
        crmPost({action:'tao_crm_get_card_itens', nonce:taoCrm.nonce, card_id:self.cardId}, function(r){
            $('#crm-itens-loading').hide();
            if(!r.success) return;
            self.itens = r.data || [];
            var $tbody = $('#crm-itens-tbody').empty();
            self.itens.forEach(function(it){ $tbody.append(self.renderLinha(it)); });
            self.atualizaFooter();
        });
    },

    salvarLinha: function(row, itemId){
        var self  = this;
        var dados = {
            action:          'tao_crm_save_card_item',
            nonce:           taoCrm.nonce,
            card_id:         self.cardId,
            item_id:         itemId || '',
            catalogo_id:     row.attr('data-catalogo-id') || '',
            descricao:       row.find('.crm-item-descricao').val().trim(),
            quantidade:      row.find('.crm-item-qtd').val(),
            preco_unitario:  row.find('.crm-item-preco').val(),
            desconto_tipo:   row.find('.crm-item-desc-tipo').val(),
            desconto_valor:  row.find('.crm-item-desc-val').val(),
            ordem:           row.index()
        };
        if(!dados.descricao) return; // nada a salvar sem descrição
        crmPost(dados, function(r){
            if(!r.success) return;
            var saved = r.data;
            if(!itemId){
                // era nova linha: atualiza id e cache
                row.attr('data-item-id', saved.id);
                var idx = self.itens.findIndex(function(x){return !x.id;});
                if(idx>=0){ self.itens[idx] = saved; }
                else { self.itens.push(saved); }
            } else {
                var idx2 = self.itens.findIndex(function(x){return x.id===itemId;});
                if(idx2>=0) self.itens[idx2] = saved;
            }
            row.find('.crm-item-total-cell').text(taoCrmItens.fmtBRL(saved.total));
            self.atualizaFooter();
            $('#crm-valor-oportunidade').val(
                parseFloat($('#crm-valor-oportunidade').val()||0).toFixed(2)
            );
        });
    },

    novaLinha: function(){
        var self = this;

        // Se há catálogo disponível, abre um mini-modal de seleção antes de criar a linha
        if(self.catalogo.length > 0){
            self._abrirModalCatalogo(function(produto){
                var novoItem = {
                    id: '',
                    catalogo_id: produto ? produto.id : '',
                    descricao:   produto ? produto.nome  : '',
                    quantidade:  1,
                    preco_unitario: produto ? parseFloat(produto.preco||0) : 0,
                    desconto_tipo: 'pct',
                    desconto_valor: 0,
                    total: produto ? parseFloat(produto.preco||0) : 0,
                };
                self.itens.push(novoItem);
                var row = self.renderLinha(novoItem);
                $('#crm-itens-tbody').append(row);
                if(!produto) row.find('.crm-item-descricao').focus();
                else         row.find('.crm-item-qtd').focus();
            });
        } else {
            var novoItem = {id:'', catalogo_id:'', descricao:'', quantidade:1, preco_unitario:0, desconto_tipo:'pct', desconto_valor:0, total:0};
            self.itens.push(novoItem);
            var row = self.renderLinha(novoItem);
            $('#crm-itens-tbody').append(row);
            row.find('.crm-item-descricao').focus();
        }
    },

}; // fim _taoCrmItensLegado (não mais usado)

// ─── v1.8.0: CAMPO TIPO ARQUIVO ───────────────────────────────────────────────

$(document).on('change', '.campo-arquivo-input', function(){
    var $input  = $(this);
    var $wrap   = $input.closest('.campo-arquivo-wrap');
    var $status = $wrap.find('.campo-arquivo-status');
    var campoId = $wrap.data('campo-id');
    var cardId  = $wrap.data('card-id');
    var file    = $input[0].files[0];
    if(!file) return;

    var fd = new FormData();
    fd.append('action',    'tao_crm_upload_campo_arquivo');
    fd.append('nonce',     taoCrm.nonce);
    fd.append('card_id',   cardId);
    fd.append('campo_id',  campoId);
    fd.append('arquivo',   file, file.name);

    $status.text('Enviando...').css('color','#6b7280');

    $.ajax({
        url:         taoCrm.ajaxUrl || ajaxurl,
        type:        'POST',
        data:        fd,
        processData: false,
        contentType: false,
        success: function(r){
            if(!r.success){ $status.text('Erro: ' + (r.data||'falha')).css('color','#dc2626'); return; }
            $status.text('').css('color','');
            // Salva o valor STORAGE:... em crm_cards_valores via campo-input padrão
            // Cria input hidden para acionar o save handler existente
            $wrap.closest('.campo-item').find('.campo-input').trigger('focus');
            // Atualiza UI
            var atualDiv = $wrap.find('.campo-arquivo-atual');
            var nonce    = taoCrm.nonce;
            var dlUrl    = taoCrm.adminUrl + '&action=tao_crm_download_campo_arquivo&nonce=' + nonce + '&card_id=' + encodeURIComponent(cardId) + '&campo_id=' + encodeURIComponent(campoId);
            if(!atualDiv.length){
                atualDiv = $('<div class="campo-arquivo-atual">');
                $wrap.prepend(atualDiv);
            }
            atualDiv.html(
                '<span>&#x1F4CE;</span> ' +
                '<a href="' + dlUrl + '" target="_blank" class="campo-arquivo-link">' + $('<span>').text(r.data.filename).html() + '</a> ' +
                '<button type="button" class="button button-small campo-arquivo-trocar" style="font-size:11px">Trocar</button>'
            ).show();
            $wrap.find('.campo-arquivo-upload').hide();

            // Persiste valor no Supabase via AJAX padrão
            crmPost({
                action:   'tao_crm_save_campo_valor',
                nonce:    taoCrm.nonce,
                card_id:  cardId,
                campo_id: campoId,
                valor:    r.data.stored
            }, function(){});
        },
        error: function(){ $status.text('Erro de conexão').css('color','#dc2626'); }
    });
});

$(document).on('click', '.campo-arquivo-trocar', function(){
    var $wrap = $(this).closest('.campo-arquivo-wrap');
    $wrap.find('.campo-arquivo-atual').hide();
    $wrap.find('.campo-arquivo-upload').show().find('input[type=file]').val('').trigger('focus');
});

// Inicializa itens quando estiver na tela de card
if($('.crm-itens-section').length){
    taoCrmItens.init();
}

// ─── PANEL RESIZER ────────────────────────────────────────────────────────
(function(){
    var resizer   = document.getElementById('crm-panel-resizer');
    var panelInfo = document.getElementById('crm-panel-info');
    var panelChat = document.getElementById('crm-panel-chat');
    var layout    = document.getElementById('crm-card-layout');
    if(!resizer || !panelInfo || !panelChat || !layout) return;

    var STORAGE_KEY = 'tao_crm_panel_chat_width';
    var saved = localStorage.getItem(STORAGE_KEY);
    if(saved){
        var w = parseInt(saved, 10);
        if(w >= 260 && w <= 600) panelChat.style.flex = '0 0 ' + w + 'px';
    }

    resizer.addEventListener('mousedown', function(e){
        e.preventDefault();
        resizer.classList.add('dragging');
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });

    function onMove(e){
        var rect  = layout.getBoundingClientRect();
        // margem-esquerda do chat (10px) + metade do resizer (5px) = ~15px
        var chatW = Math.max(260, Math.min(rect.right - e.clientX - 15, rect.width - 300));
        panelChat.style.flex = '0 0 ' + chatW + 'px';
    }

    function onUp(){
        resizer.classList.remove('dragging');
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        var m = panelChat.style.flex.match(/(\d+)px/);
        if(m) localStorage.setItem(STORAGE_KEY, m[1]);
    }
})();


// ─── NOTIFICAÇÕES: polling de handoff abertos ─────────────────────────────────
(function(){
    if(typeof taoCrm === 'undefined' || !taoCrm.nonce || !taoCrm.ws_id) return;

    var lastCount = -1;
    var notifPermGranted = false;

    function requestNotifPerm(){
        if(typeof Notification === 'undefined') return;
        if(Notification.permission === 'granted'){ notifPermGranted = true; return; }
        if(Notification.permission !== 'denied'){
            Notification.requestPermission(function(perm){ notifPermGranted = (perm === 'granted'); });
        }
    }

    function updateBadge(count){
        ['tao-crm-badge','tao-crm-inbox-badge'].forEach(function(id){
            var el = document.getElementById(id);
            if(!el) return;
            if(count > 0){
                el.textContent = count;
                el.style.display = 'inline-block';
            } else {
                el.style.display = 'none';
            }
        });
        // Atualiza título da página
        var title = document.title.replace(/^\(\d+\)\s*/, '');
        document.title = count > 0 ? '(' + count + ') ' + title : title;
    }

    function pollNotif(){
        var fd = new FormData();
        fd.append('action','tao_crm_notif_count');
        fd.append('nonce', taoCrm.nonce);
        fd.append('workspace_id', taoCrm.ws_id);
        fetch(taoCrm.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(resp){
            if(!resp.success) return;
            var count = resp.data.count || 0;
            updateBadge(count);
            if(count > lastCount && lastCount >= 0 && notifPermGranted && document.hidden){
                new Notification('TAO CRM', {
                    body: count + ' cliente(s) aguardando atendimento',
                    icon: '/wp-admin/images/wordpress-logo.svg'
                });
            }
            lastCount = count;
        })
        .catch(function(){});
    }

    // Pede permissão de notificação após interação
    document.addEventListener('click', function(){ requestNotifPerm(); }, { once: true });

    pollNotif();
    setInterval(pollNotif, 60000);
})();

})(jQuery);
