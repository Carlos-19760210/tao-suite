/* TAO Fórmulas — Admin JS */
(function($){
    'use strict';

    // ── Helpers ──────────────────────────────────────────────────────────────

    function showMsg($el, txt, isErr) {
        $el.text(txt).removeClass('ok err').addClass(isErr ? 'err' : 'ok').show();
        setTimeout(function(){ $el.fadeOut(); }, 3500);
    }

    function spin($btn, $sp, on) {
        $btn.prop('disabled', on);
        $sp.css('visibility', on ? 'visible' : 'hidden');
    }

    // ── Formas Farmacêuticas ─────────────────────────────────────────────────

    var $modal  = $('#taof-forma-modal');
    var $form   = $('#taof-forma-form');
    var $tipo   = $('#taof-tipo');
    var $rowVol = $('#taof-row-volume');
    var $rowCap = $('#taof-row-capsulas');
    var $btnSave= $('#taof-btn-salvar');
    var $spinner= $form.find('.taof-spinner');
    var $msg    = $form.find('.taof-msg');

    function toggleTipo() {
        var t = $tipo.val();
        if (t === 'cap') {
            $rowVol.hide(); $rowCap.show();
        } else {
            $rowVol.show(); $rowCap.hide();
        }
    }

    function openModal(data) {
        if (data) {
            $('#taof-modal-title').text('Editar Forma Farmacêutica');
            $('#taof-forma-id').val(data.id);
            $('#taof-nome').val(data.nome);
            $tipo.val(data.tipo);
            $('#taof-volume').val(data.volume);
            $('#taof-unidade-volume').val(data.unidadeVolume || 'g');
            $('#taof-ncap').val(data.nCapsulas);
            $('#taof-custo-fixo').val(data.custoFixo);
            $('#taof-margem').val(data.margemPct);
        } else {
            $('#taof-modal-title').text('Nova Forma Farmacêutica');
            $form[0].reset();
            $('#taof-forma-id').val('');
        }
        toggleTipo();
        $modal.show();
        $('#taof-nome').focus();
    }

    $tipo.on('change', toggleTipo);

    $(document).on('click', '.taof-btn-nova', function(e){
        e.preventDefault(); openModal(null);
    });

    $('#taof-btn-cancelar').on('click', function(){ $modal.hide(); });
    $(document).on('click', '.taof-overlay', function(){ $modal.hide(); });

    $(document).on('click', '.taof-btn-edit', function(){
        var $tr = $(this).closest('tr');
        openModal({
            id:           $tr.data('id'),
            nome:         $tr.data('nome'),
            tipo:         $tr.data('tipo'),
            volume:       $tr.data('volume'),
            unidadeVolume:$tr.data('unidade-volume'),
            nCapsulas:    $tr.data('n-capsulas'),
            custoFixo:    $tr.data('custo-fixo'),
            margemPct:    $tr.data('margem-pct'),
        });
    });

    $(document).on('click', '.taof-btn-del', function(){
        var $tr = $(this).closest('tr');
        var nome = $tr.data('nome');
        if (!confirm('Excluir "' + nome + '"?')) return;
        var id = $tr.data('id');
        $.post(taoFormula.ajaxUrl, {
            action: 'tao_formula_delete_forma',
            nonce:  taoFormula.nonce,
            id:     id
        }, function(res){
            if (res.success) { $tr.fadeOut(300, function(){ $(this).remove(); }); }
            else { alert('Erro ao excluir: ' + (res.data || '')); }
        });
    });

    $form.on('submit', function(e){
        e.preventDefault();
        spin($btnSave, $spinner, true);
        var data = $form.serializeArray().reduce(function(o,f){ o[f.name]=f.value; return o; }, {});
        data.action = 'tao_formula_save_forma';
        data.nonce  = taoFormula.nonce;
        $.post(taoFormula.ajaxUrl, data, function(res){
            spin($btnSave, $spinner, false);
            if (res.success) {
                showMsg($msg, '✅ Salvo com sucesso!', false);
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                showMsg($msg, '❌ Erro: ' + (res.data || 'desconhecido'), true);
            }
        }).fail(function(){
            spin($btnSave, $spinner, false);
            showMsg($msg, '❌ Falha na requisição.', true);
        });
    });

    // ── Orçamentos — botões de status ────────────────────────────────────────

    function updateOrcStatus(id, status, $btn) {
        $btn.prop('disabled', true).text('...');
        $.post(taoFormula.ajaxUrl, {
            action: 'tao_formula_update_orc_status',
            nonce:  taoFormula.nonce,
            id:     id,
            status: status
        }, function(res){
            if (res.success) { location.reload(); }
            else { alert('Erro: ' + (res.data || '')); $btn.prop('disabled', false); }
        });
    }

    $(document).on('click', '.taof-orc-aprovar', function(){
        var id = $(this).data('id');
        if (confirm('Aprovar este orçamento?')) updateOrcStatus(id, 'aprovado_farma', $(this));
    });
    $(document).on('click', '.taof-orc-rejeitar', function(){
        var id = $(this).data('id');
        if (confirm('Rejeitar este orçamento?')) updateOrcStatus(id, 'rejeitado', $(this));
    });
    $(document).on('click', '.taof-orc-enviar', function(){
        var id = $(this).data('id');
        if (confirm('Marcar como enviado ao paciente?')) updateOrcStatus(id, 'enviado_paciente', $(this));
    });

    // ── Configurações ─────────────────────────────────────────────────────────

    $('#taof-config-form').on('submit', function(e){
        e.preventDefault();
        var $btn = $(this).find('[type=submit]');
        var $sp  = $(this).find('.taof-spinner');
        var $msg = $(this).find('.taof-msg');
        spin($btn, $sp, true);
        var data = $(this).serializeArray().reduce(function(o,f){ o[f.name]=f.value; return o; }, {});
        data.action = 'tao_formula_save_config';
        data.nonce  = taoFormula.nonce;
        $.post(taoFormula.ajaxUrl, data, function(res){
            spin($btn, $sp, false);
            showMsg($msg, res.success ? '✅ Configurações salvas!' : '❌ ' + (res.data||'Erro'), !res.success);
        });
    });

})(jQuery);
