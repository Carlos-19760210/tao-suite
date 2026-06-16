/* TAO Fórmulas — Orçamento Manual (cálculo idêntico ao Formula Certa) */
(function ($) {
    'use strict';

    if (!document.getElementById('taof-orc-form')) return;

    // ── Estado global ─────────────────────────────────────────────────────────
    var formas   = window.taofOrcFormas || {};
    var formaAtual = null;
    var ajaxUrl  = taoFormula.ajaxUrl;
    var nonce    = taoFormula.nonce;

    // ── Conversão de unidades para mg ────────────────────────────────────────
    // Regra: tudo passa por mg como unidade pivô
    var paraMg = { mg: 1, g: 1000, mcg: 0.001, UI: 1, ml: 1000 };

    function converterParaUnidPadrao(valor, deUnit, paraUnit) {
        var emMg = valor * (paraMg[deUnit] || 1);
        return emMg / (paraMg[paraUnit] || 1);
    }

    // ── Multiplicador conforme tipo de forma ─────────────────────────────────
    function getMultiplicador() {
        if (!formaAtual) return 1;
        if (formaAtual.tipo === 'cap') return formaAtual.nCapsulas || 1;
        return formaAtual.volume || 1;
    }

    // ── Formata número BR ─────────────────────────────────────────────────────
    function fmt(n, dec) {
        dec = dec !== undefined ? dec : 2;
        return parseFloat(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }

    function fmtQtd(n, unit) {
        // Auto-choose decimal places by magnitude
        var dec = Math.abs(n) < 0.01 ? 6 : (Math.abs(n) < 1 ? 4 : 3);
        return fmt(n, dec) + ' ' + (unit || '');
    }

    // ── Calcula uma linha ─────────────────────────────────────────────────────
    function calcularLinha($row) {
        var dose       = parseFloat($row.find('.taof-orc-dose').val()) || 0;
        var doseUnit   = $row.find('.taof-orc-dose-unit').val() || 'mg';
        var fc         = parseFloat($row.find('.taof-orc-fc').val()) || 1;
        var fp         = parseFloat($row.find('.taof-orc-fp').val()) || 1;
        var custoUnit  = parseFloat($row.data('custo-unit')) || 0;
        var unidPadrao = $row.data('unid-padrao') || 'mg';
        var mult       = getMultiplicador();

        // Quantidade bruta × multiplicador da forma
        var qtdBruta = dose * mult;
        // Aplica FC (fator de correção) e FP (fator de perda)
        var qtdFinal = qtdBruta * fc * fp;
        // Converte da unidade da dose para a unidade padrão do ativo
        var qtdEmPadrao = converterParaUnidPadrao(qtdFinal, doseUnit, unidPadrao);
        // Custo = qtd (em unidade padrão) × custo por unidade padrão
        var subtotal = qtdEmPadrao * custoUnit;

        $row.find('.taof-orc-qtd-total').text(fmtQtd(qtdEmPadrao, unidPadrao));
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data('subtotal', subtotal);
        $row.data('qtd-em-padrao', qtdEmPadrao);
        $row.data('qtd-unit', unidPadrao);
        $row.data('dose', dose);
        $row.data('dose-unit', doseUnit);
        $row.data('fc', fc);
        $row.data('fp', fp);

        calcularTotais();
    }

    // ── Calcula totais ────────────────────────────────────────────────────────
    function calcularTotais() {
        var totalInsumos = 0;
        $('#taof-itens-body .taof-item-row').each(function () {
            totalInsumos += parseFloat($(this).data('subtotal')) || 0;
        });
        var custoFixo  = formaAtual ? (formaAtual.custoFixo || 0) : 0;
        var base       = totalInsumos + custoFixo;
        var margemPct  = parseFloat($('#taof-margem-input').val()) || 0;
        var margemVal  = base * margemPct / 100;
        var total      = base + margemVal;

        $('#taof-res-insumos').text('R$ ' + fmt(totalInsumos));
        $('#taof-res-custo-fixo').text('R$ ' + fmt(custoFixo));
        $('#taof-res-base').text('R$ ' + fmt(base));
        $('#taof-res-margem').text('R$ ' + fmt(margemVal));
        $('#taof-res-total').html('<strong>R$ ' + fmt(total) + '</strong>');
    }

    // ── Forma select ─────────────────────────────────────────────────────────
    $('#taof-forma-sel').on('change', function () {
        var id = $(this).val();
        formaAtual = id ? (formas[id] || null) : null;

        var info = '';
        if (formaAtual) {
            var multTxt = '';
            if (formaAtual.tipo === 'cap') {
                info = formaAtual.nCapsulas + ' capsulas';
                multTxt = 'Multiplicador: x ' + formaAtual.nCapsulas + ' capsulas';
            } else {
                info = formaAtual.volume + ' ' + formaAtual.unidVolume;
                multTxt = 'Multiplicador: x ' + formaAtual.volume + ' ' + formaAtual.unidVolume;
            }
            var $badge = $('#taof-forma-mult-badge');
            if (multTxt) { $('#taof-forma-mult-txt').text(multTxt); $badge.show(); }
            else { $badge.hide(); }
            info += ' | Custo fixo: R$ ' + fmt(formaAtual.custoFixo);
            info += ' | Margem: ' + fmt(formaAtual.margemPct, 1) + '%';
            $('#taof-margem-input').val(formaAtual.margemPct);
        }
        $('#taof-forma-info').text(info);

        // Recalcula todas as linhas com novo multiplicador
        $('#taof-itens-body .taof-item-row').each(function () {
            calcularLinha($(this));
        });
    });

    // Margem manual
    $('#taof-margem-input').on('input', calcularTotais);

    // ── Adicionar linha ───────────────────────────────────────────────────────
    $('#taof-btn-add-item').on('click', function () {
        var tpl = document.getElementById('taof-item-tpl');
        var clone = $(tpl.content.cloneNode(true));
        var $row = clone.find('tr');
        $row.data('subtotal', 0);
        $('#taof-itens-body').append(clone);
        initRow($row);
    });

    // ── Inicializa eventos de uma linha ──────────────────────────────────────
    function initRow($row) {
        // Cálculo em tempo real
        $row.on('input change', '.taof-orc-dose, .taof-orc-dose-unit, .taof-orc-fc, .taof-orc-fp', function () {
            calcularLinha($row);
        });

        // Remover linha
        $row.on('click', '.taof-btn-del-item', function () {
            $row.remove();
            calcularTotais();
        });

        // Autocomplete
        initAutocomplete($row);
    }

    // ── Autocomplete de ativos ────────────────────────────────────────────────
    function initAutocomplete($row) {
        var $inp     = $row.find('.taof-orc-ativo-search');
        var $res     = $row.find('.taof-autocomplete-results');
        var $idFld   = $row.find('.taof-orc-ativo-id');
        var $upFld   = $row.find('.taof-orc-unid-padrao');
        var $cuFld   = $row.find('.taof-orc-custo-unit');
        var timer    = null;

        $inp.on('input', function () {
            var q = $(this).val().trim();
            clearTimeout(timer);
            if (q.length < 2) { $res.hide().empty(); return; }
            timer = setTimeout(function () { buscarAtivos(q); }, 300);
        });

        function buscarAtivos(q) {
            $.getJSON(ajaxUrl, {
                action: 'tao_formula_search_ativos',
                nonce:  nonce,
                q:      q,
                grupo:  'M'
            }, function (resp) {
                $res.empty();
                if (!resp.success || !resp.data.length) {
                    $res.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhum resultado.</div>');
                } else {
                    $.each(resp.data, function (_, a) {
                        var $item = $('<div class="taof-ac-item">')
                            .text(a.nome)
                            .append($('<small>').text(a.unidade_padrao + ' · R$ ' + fmt(a.custo_por_unidade) + '/' + a.unidade_padrao));
                        $item.on('mousedown', function (e) {
                            e.preventDefault();
                            selecionarAtivo(a);
                        });
                        $res.append($item);
                    });
                }
                $res.show();
            });
        }

        function selecionarAtivo(a) {
            $inp.val(a.nome);
            $idFld.val(a.id);
            $upFld.val(a.unidade_padrao);
            $cuFld.val(a.custo_por_unidade);

            $row.data('ativo-id', a.id);
            $row.data('ativo-nome', a.nome);
            $row.data('unid-padrao', a.unidade_padrao);
            $row.data('custo-unit', a.custo_por_unidade);

            // Preenche FC e FP do ativo (editável)
            $row.find('.taof-orc-fc').val(a.fator_correcao || 1);
            $row.find('.taof-orc-fp').val(a.fator_perda || 1);

            // Unidade da dose default = unidade padrão do ativo
            var unidSel = a.unidade_padrao;
            if (['mg','mcg','g','UI','ml'].indexOf(unidSel) === -1) unidSel = 'mg';
            $row.find('.taof-orc-dose-unit').val(unidSel);

            // Label de custo por unidade
            $row.find('.taof-orc-custo-unit-label').text(
                'R$ ' + fmt(a.custo_por_unidade, 4) + '/' + a.unidade_padrao
            );

            $res.hide().empty();
            calcularLinha($row);
        }

        $inp.on('blur', function () {
            setTimeout(function () { $res.hide(); }, 150);
        });
        $inp.on('focus', function () {
            if ($res.children().length) $res.show();
        });
    }

    // ── Embalagens ───────────────────────────────────────────────────────────
    function calcularEmb($row) {
        var qty      = parseInt($row.find('.taof-emb-qty').val()) || 0;
        var custo    = parseFloat($row.data('custo-unit')) || 0;
        var subtotal = qty * custo;
        $row.find('.taof-emb-subtotal').text('R$ ' + fmt(subtotal));
        $row.data('subtotal-emb', subtotal);
        calcularTotais();
    }

    function initEmbRow($row) {
        $row.on('input', '.taof-emb-qty', function () { calcularEmb($row); });
        $row.on('click', '.taof-btn-del-emb', function () {
            $row.remove();
            calcularTotais();
        });
        initEmbAutocomplete($row);
    }

    function initEmbAutocomplete($row) {
        var $inp  = $row.find('.taof-emb-search');
        var $res  = $row.find('.taof-autocomplete-results');
        var $idFld= $row.find('.taof-emb-id');
        var timer = null;

        $inp.on('input', function () {
            var q = $(this).val().trim();
            clearTimeout(timer);
            if (q.length < 2) { $res.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: q, grupo: 'E' }, function (resp) {
                    $res.empty();
                    if (!resp.success || !resp.data.length) {
                        $res.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhuma embalagem encontrada.</div>');
                    } else {
                        $.each(resp.data, function (_, a) {
                            var $item = $('<div class="taof-ac-item">')
                                .text(a.nome)
                                .append($('<small>').text('R$ ' + fmt(a.custo_por_unidade, 4) + '/' + (a.unidade_padrao || 'un')));
                            $item.on('mousedown', function (e) {
                                e.preventDefault();
                                $inp.val(a.nome);
                                $idFld.val(a.id);
                                $row.data('emb-id', a.id);
                                $row.data('emb-nome', a.nome);
                                $row.data('custo-unit', a.custo_por_unidade);
                                $row.find('.taof-emb-custo-label').text('R$ ' + fmt(a.custo_por_unidade, 4) + '/un');
                                $res.hide().empty();
                                calcularEmb($row);
                            });
                            $res.append($item);
                        });
                    }
                    $res.show();
                });
            }, 300);
        });

        $inp.on('blur', function () { setTimeout(function () { $res.hide(); }, 150); });
        $inp.on('focus', function () { if ($res.children().length) $res.show(); });
    }

    $('#taof-btn-add-emb').on('click', function () {
        var tpl    = document.getElementById('taof-emb-tpl');
        var clone  = $(tpl.content.cloneNode(true));
        var $row   = clone.find('tr');
        $row.data('subtotal-emb', 0);
        $('#taof-emb-body').append(clone);
        initEmbRow($row);
    });

    // Sobreescreve calcularTotais para incluir embalagens
    var _calcTotaisOrig = calcularTotais;
    calcularTotais = function () {
        var totalMp = 0;
        $('#taof-itens-body .taof-item-row').each(function () {
            totalMp += parseFloat($(this).data('subtotal')) || 0;
        });
        var totalEmb = 0;
        $('#taof-emb-body .taof-emb-row').each(function () {
            totalEmb += parseFloat($(this).data('subtotal-emb')) || 0;
        });
        var totalInsumos = totalMp + totalEmb;
        var custoFixo    = formaAtual ? (formaAtual.custoFixo || 0) : 0;
        var base         = totalInsumos + custoFixo;
        var margemPct    = parseFloat($('#taof-margem-input').val()) || 0;
        var margemVal    = base * margemPct / 100;
        var total        = base + margemVal;

        $('#taof-res-insumos').text('R$ ' + fmt(totalInsumos));
        $('#taof-res-custo-fixo').text('R$ ' + fmt(custoFixo));
        $('#taof-res-base').text('R$ ' + fmt(base));
        $('#taof-res-margem').text('R$ ' + fmt(margemVal));
        $('#taof-res-total').html('<strong>R$ ' + fmt(total) + '</strong>');
    };

    // ── Salvar orçamento ─────────────────────────────────────────────────────
    $('#taof-orc-form').on('submit', function (e) {
        e.preventDefault();

        var $btn = $('#taof-orc-salvar');
        var $sp  = $('.taof-spinner');
        var $msg = $('.taof-msg');

        // Monta array de itens (MPs)
        var itens = [];
        var ok = true;
        $('#taof-itens-body .taof-item-row').each(function () {
            var $r = $(this);
            var id = $r.data('ativo-id');
            if (!id) { ok = false; return false; }
            itens.push({
                tipo:           'mp',
                ativo_id:       id,
                nome:           $r.data('ativo-nome'),
                dose:           $r.data('dose'),
                dose_unit:      $r.data('dose-unit'),
                multiplicador:  getMultiplicador(),
                fc:             $r.data('fc'),
                fp:             $r.data('fp'),
                qtd_em_padrao:  $r.data('qtd-em-padrao'),
                unidade_padrao: $r.data('qtd-unit'),
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                subtotal:       parseFloat($r.data('subtotal')) || 0
            });
        });

        if (!ok) { alert('Selecione o ativo em todas as linhas antes de salvar.'); return; }

        // Monta itens de embalagem
        $('#taof-emb-body .taof-emb-row').each(function () {
            var $r = $(this);
            var id = $r.data('emb-id');
            if (!id) { ok = false; return false; }
            itens.push({
                tipo:           'emb',
                ativo_id:       id,
                nome:           $r.data('emb-nome'),
                quantidade:     parseInt($r.find('.taof-emb-qty').val()) || 0,
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                subtotal:       parseFloat($r.data('subtotal-emb')) || 0
            });
        });

        if (!ok) { alert('Selecione a embalagem em todas as linhas antes de salvar.'); return; }
        if (!itens.length) { alert('Adicione ao menos um ativo na receita.'); return; }

        // Lê totais atuais
        var custoFixo  = formaAtual ? (formaAtual.custoFixo || 0) : 0;
        var totalIns   = itens.reduce(function(s, i){ return s + (i.subtotal||0); }, 0);
        var base       = totalIns + custoFixo;
        var margemPct  = parseFloat($('#taof-margem-input').val()) || 0;
        var total      = base * (1 + margemPct / 100);

        $btn.prop('disabled', true);
        $sp.css('visibility', 'visible');
        $msg.hide();

        $.post(ajaxUrl, {
            action:          'tao_formula_save_orcamento',
            nonce:           nonce,
            nome_paciente:   $('#taof-nome-paciente').val(),
            whatsapp:        $('#taof-whatsapp').val(),
            forma_id:        $('#taof-forma-sel').val() || '',
            forma_nome:      formaAtual ? formaAtual.nome : '',
            custo_fixo:      custoFixo,
            total_insumos:   totalIns,
            margem_pct:      margemPct,
            total_orcamento: total,
            observacoes:     $('#taof-observacoes').val(),
            itens:           JSON.stringify(itens)
        }, function (resp) {
            $sp.css('visibility', 'hidden');
            $btn.prop('disabled', false);
            if (resp.success) {
                window.location.href = window.taofOrcListUrl;
            } else {
                $msg.text('Erro: ' + (resp.data || 'desconhecido')).addClass('err').show();
            }
        }).fail(function () {
            $sp.css('visibility', 'hidden');
            $btn.prop('disabled', false);
            $msg.text('Falha na requisição.').addClass('err').show();
        });
    });

})(jQuery);
