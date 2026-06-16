/* TAO Formulas — Orcamento v1.3 (master-detail, auto-capsula, Formula Certa calc) */
(function ($) {
    'use strict';
    if (!document.getElementById('taof-orc-form')) return;

    var formas   = window.taofOrcFormas || {};
    var capsulas = window.taofCapsulas  || [];
    var formaAtual = null;
    var ajaxUrl  = taoFormula.ajaxUrl;
    var nonce    = taoFormula.nonce;

    // ── Conversão para mg ─────────────────────────────────────────────
    var toMgMap = { mg: 1, g: 1000, mcg: 0.001, ml: 1000 };
    function toMg(v, u) { return v * (toMgMap[u] || 1); }

    // ── Formato BR ────────────────────────────────────────────────────
    function fmt(n, dec) {
        if (dec === undefined) dec = 2;
        return parseFloat(n || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: dec, maximumFractionDigits: dec
        });
    }

    // ── Multiplicadores ───────────────────────────────────────────────
    function getPotes() { return Math.max(1, parseInt($('#taof-qtde-potes').val()) || 1); }
    function getUnidPorBatch() {
        if (!formaAtual) return 1;
        return formaAtual.tipo === 'cap' ? (formaAtual.nCapsulas || 1) : (formaAtual.volume || 1);
    }
    function getMultiplicador() { return getUnidPorBatch() * getPotes(); }

    // ── Cálculo por linha (Formula Certa) ─────────────────────────────
    function calcularLinha($row) {
        var dose      = parseFloat($row.find('.taof-orc-dose').val()) || 0;
        var doseUnit  = $row.find('.taof-orc-dose-unit').val() || 'mg';
        var fp        = parseFloat($row.data('fp')) || 1;
        var custoUnit = parseFloat($row.data('custo-unit')) || 0;
        var unidPadrao= $row.data('unid-padrao') || 'g';
        var diluicao  = parseFloat($row.data('diluicao')) || 1;
        var teor      = parseFloat($row.data('teor')) || 100;
        var densidade = parseFloat($row.data('densidade')) || 1;
        var mult      = getMultiplicador();
        var isCap     = formaAtual && formaAtual.tipo === 'cap';
        var isUI      = (doseUnit === 'UI' || doseUnit === 'UFC');

        var dose_mg_unit    = isUI ? dose : toMg(dose, doseUnit);
        var qtd_mg_per_unit = dose_mg_unit * diluicao / (teor / 100) * fp;
        var qtd_total_mg    = qtd_mg_per_unit * mult;
        var qtd_total_g     = qtd_total_mg / 1000;
        var volapa_uL       = (isCap && !isUI && densidade > 0) ? (qtd_mg_per_unit / densidade) : 0;

        var qtd_em_unid;
        if      (unidPadrao === 'g')   qtd_em_unid = qtd_total_g;
        else if (unidPadrao === 'mg')  qtd_em_unid = qtd_total_mg;
        else if (unidPadrao === 'mcg') qtd_em_unid = qtd_total_mg * 1000;
        else                           qtd_em_unid = qtd_total_g;

        var subtotal = qtd_em_unid * custoUnit;

        var totalLabel = unidPadrao === 'g'  ? fmt(qtd_total_g, 4) + ' g'
                       : unidPadrao === 'mg' ? fmt(qtd_total_mg, 3) + ' mg'
                       : fmt(qtd_em_unid, 3) + ' ' + unidPadrao;
        if (isUI) totalLabel += ' (est.)';

        $row.find('.taof-orc-qtd-total').text(totalLabel);
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qtd_total_g,
                    'qtd-em-padrao': qtd_em_unid, 'qtd-unit': unidPadrao,
                    dose: dose, 'dose-unit': doseUnit, 'volapa-ul': volapa_uL });
        calcularTotais();
    }

    // ── Auto-sugestão de cápsula ──────────────────────────────────────
    function sugerirCapsula() {
        if (!formaAtual || formaAtual.tipo !== 'cap' || !capsulas.length) {
            $('#taof-cap-sugerida').hide(); return;
        }
        var totalVOLAPA = 0;
        $('#taof-itens-body .taof-item-row').each(function () {
            totalVOLAPA += parseFloat($(this).data('volapa-ul')) || 0;
        });
        if (totalVOLAPA <= 0) { $('#taof-cap-sugerida').hide(); return; }

        var ftench  = formaAtual.ftenchcap || 1;
        var sorted  = capsulas.slice().sort(function(a,b){ return a.vol_ul - b.vol_ul; });
        var sugerida = null;
        for (var i = 0; i < sorted.length; i++) {
            if (sorted[i].vol_ul * ftench >= totalVOLAPA) { sugerida = sorted[i]; break; }
        }
        if (!sugerida) sugerida = sorted[sorted.length - 1];

        var pct = totalVOLAPA / (sugerida.vol_ul * ftench) * 100;
        var cor = pct > 100 ? '#dc2626' : (pct > 85 ? '#d97706' : '#16a34a');
        var ico = pct > 100 ? '&#9888;&#65039;' : '&#128138;';
        var tipoNome = sugerida.tipo.charAt(0).toUpperCase() + sugerida.tipo.slice(1).toLowerCase();

        $('#taof-cap-sugerida').html(
            ico + ' <strong>C&aacute;psula: ' + tipoNome + ' N&ordm; ' + sugerida.numero +
            ' (' + sugerida.vol_ul + ' &micro;L)</strong>' +
            ' &mdash; <span style="color:' + cor + '">' +
            fmt(totalVOLAPA, 1) + ' / ' + fmt(sugerida.vol_ul * ftench, 0) +
            ' &micro;L (' + fmt(pct, 0) + '% ocupado)</span>'
        ).show();
    }

    // ── Totais ────────────────────────────────────────────────────────
    function calcularTotais() {
        var calculado = 0;
        $('#taof-itens-body .taof-item-row').each(function () {
            calculado += parseFloat($(this).data('subtotal')) || 0;
        });
        $('#taof-emb-body .taof-emb-row').each(function () {
            calculado += parseFloat($(this).data('subtotal-emb')) || 0;
        });

        var custoFixo = formaAtual ? (formaAtual.custoFixo || 0) : 0;
        var subtotal  = calculado + custoFixo;
        var acrescPct = parseFloat($('#taof-acrescimo-pct').val()) || 0;
        var desctPct  = parseFloat($('#taof-desconto-pct').val())  || 0;
        var acrescVal = subtotal * acrescPct / 100;
        var desctVal  = subtotal * desctPct  / 100;
        var final     = subtotal + acrescVal - desctVal;

        $('#taof-res-calculado').text('R$ ' + fmt(calculado));
        $('#taof-res-custo-fixo').text('R$ ' + fmt(custoFixo));
        $('#taof-res-subtotal').text('R$ ' + fmt(subtotal));
        $('#taof-res-acrescimo').text('R$ ' + fmt(acrescVal));
        $('#taof-res-desconto').text('R$ ' + fmt(desctVal));
        $('#taof-acrescimo-hint').text(acrescPct > 0 ? '= R$ ' + fmt(acrescVal) : '');
        $('#taof-desconto-hint').text(desctPct  > 0 ? '= R$ ' + fmt(desctVal)  : '');
        $('#taof-res-final').html('<strong>R$ ' + fmt(final) + '</strong>');

        sugerirCapsula();
    }

    // ── Forma select ──────────────────────────────────────────────────
    $('#taof-forma-sel').on('change', function () {
        var id = $(this).val();
        formaAtual = id ? (formas[id] || null) : null;
        if (formaAtual) {
            var volLabel = formaAtual.tipo === 'cap'
                ? formaAtual.nCapsulas + ' cápsulas'
                : formaAtual.volume + ' ' + formaAtual.unidVolume;
            $('#taof-forma-vol-label').text(volLabel);
            $('#taof-forma-tipo-label').text(formaAtual.tipoLabel || formaAtual.tipo);
        } else {
            $('#taof-forma-vol-label, #taof-forma-tipo-label').text('—');
        }
        $('#taof-itens-body .taof-item-row').each(function () { calcularLinha($(this)); });
    });

    $('#taof-qtde-potes').on('input change', function () {
        $('#taof-itens-body .taof-item-row').each(function () { calcularLinha($(this)); });
    });

    $('#taof-acrescimo-pct, #taof-desconto-pct').on('input change', calcularTotais);

    // ── Adicionar linha de ativo ──────────────────────────────────────
    $('#taof-btn-add-item').on('click', function () {
        var clone = $(document.getElementById('taof-item-tpl').content.cloneNode(true));
        var $row  = clone.find('tr');
        $row.data({ subtotal: 0, 'volapa-ul': 0 });
        $('#taof-itens-body').append(clone);
        initRow($row);
    });

    function initRow($row) {
        $row.on('input change', '.taof-orc-dose, .taof-orc-dose-unit', function () { calcularLinha($row); });
        $row.on('click', '.taof-btn-del-item', function () { $row.remove(); calcularTotais(); });
        initAtivoAC($row);
    }

    // ── Autocomplete — Ativos ─────────────────────────────────────────
    function initAtivoAC($row) {
        var $inp   = $row.find('.taof-orc-ativo-search');
        var $res   = $row.find('.taof-autocomplete-results');
        var $idFld = $row.find('.taof-orc-ativo-id');
        var timer  = null;

        $inp.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            if (q.length < 2) { $res.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: q, grupo: 'M' },
                function (resp) {
                    $res.empty();
                    if (!resp.success || !resp.data.length) {
                        $res.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhum resultado.</div>');
                    } else {
                        $.each(resp.data, function (_, a) {
                            var info = 'R$ ' + fmt(a.custo_por_unidade, 4) + '/' + a.unidade_padrao;
                            if (a.diluicao && a.diluicao != 1) info += ' · dil 1:' + a.diluicao;
                            var $item = $('<div class="taof-ac-item">').text(a.nome).append($('<small>').text(info));
                            $item.on('mousedown', function (e) { e.preventDefault(); selecionarAtivo(a); });
                            $res.append($item);
                        });
                    }
                    $res.show();
                });
            }, 280);
        });

        function selecionarAtivo(a) {
            $inp.val(a.nome);
            $idFld.val(a.id);
            $row.find('.taof-orc-cod').text(a.codigo_fc || '—');
            $row.find('.taof-orc-fp-label').text(
                parseFloat(a.fator_perda || 1).toLocaleString('pt-BR', { minimumFractionDigits: 3 })
            );
            $row.data({
                'ativo-id': a.id, 'ativo-nome': a.nome, 'codigo-fc': a.codigo_fc || '',
                'unid-padrao': a.unidade_padrao, 'custo-unit': a.custo_por_unidade,
                'diluicao': parseFloat(a.diluicao)   || 1,
                'teor':     parseFloat(a.teor)        || 100,
                'densidade':parseFloat(a.densidade)   || 1,
                'fp':       parseFloat(a.fator_perda) || 1,
            });
            var u = a.unidade_padrao;
            if (['mg','mcg','g','UI','UFC','ml'].indexOf(u) === -1) u = 'mg';
            $row.find('.taof-orc-dose-unit').val(u);
            $res.hide().empty();
            calcularLinha($row);
        }

        $inp.on('blur',  function () { setTimeout(function () { $res.hide(); }, 150); });
        $inp.on('focus', function () { if ($res.children().length) $res.show(); });
    }

    // ── Embalagens ────────────────────────────────────────────────────
    function calcularEmb($row) {
        var qty      = parseInt($row.find('.taof-emb-qty').val()) || 0;
        var subtotal = qty * (parseFloat($row.data('custo-unit')) || 0);
        $row.find('.taof-emb-subtotal').text('R$ ' + fmt(subtotal));
        $row.data('subtotal-emb', subtotal);
        calcularTotais();
    }

    function initEmbRow($row) {
        $row.on('input', '.taof-emb-qty', function () { calcularEmb($row); });
        $row.on('click', '.taof-btn-del-emb', function () { $row.remove(); calcularTotais(); });
        initEmbAC($row);
    }

    function initEmbAC($row) {
        var $inp   = $row.find('.taof-emb-search');
        var $res   = $row.find('.taof-autocomplete-results');
        var $idFld = $row.find('.taof-emb-id');
        var timer  = null;
        $inp.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            if (q.length < 2) { $res.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: q, grupo: 'E' },
                function (resp) {
                    $res.empty();
                    if (!resp.success || !resp.data.length) {
                        $res.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhuma embalagem.</div>');
                    } else {
                        $.each(resp.data, function (_, a) {
                            var $item = $('<div class="taof-ac-item">').text(a.nome)
                                .append($('<small>').text('R$ ' + fmt(a.custo_por_unidade, 4) + '/un'));
                            $item.on('mousedown', function (e) {
                                e.preventDefault();
                                $inp.val(a.nome); $idFld.val(a.id);
                                $row.data({ 'emb-id': a.id, 'emb-nome': a.nome, 'custo-unit': a.custo_por_unidade });
                                $row.find('.taof-emb-custo-label').text('R$ ' + fmt(a.custo_por_unidade, 4) + '/un');
                                $res.hide().empty();
                                calcularEmb($row);
                            });
                            $res.append($item);
                        });
                    }
                    $res.show();
                });
            }, 280);
        });
        $inp.on('blur',  function () { setTimeout(function () { $res.hide(); }, 150); });
        $inp.on('focus', function () { if ($res.children().length) $res.show(); });
    }

    $('#taof-btn-add-emb').on('click', function () {
        var clone = $(document.getElementById('taof-emb-tpl').content.cloneNode(true));
        var $row  = clone.find('tr');
        $row.data('subtotal-emb', 0);
        $('#taof-emb-body').append(clone);
        initEmbRow($row);
    });

    // ── Salvar ────────────────────────────────────────────────────────
    $('#taof-orc-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#taof-orc-salvar'), $sp = $('.taof-spinner'), $msg = $('.taof-msg');
        var itens = [], ok = true;

        $('#taof-itens-body .taof-item-row').each(function () {
            var $r = $(this);
            if (!$r.data('ativo-id')) { ok = false; return false; }
            itens.push({
                tipo: 'mp', ativo_id: $r.data('ativo-id'), nome: $r.data('ativo-nome'),
                codigo_fc: $r.data('codigo-fc') || '',
                dose: $r.data('dose'), dose_unit: $r.data('dose-unit'),
                multiplicador: getMultiplicador(), qtde_potes: getPotes(),
                diluicao: $r.data('diluicao'), teor: $r.data('teor'), fp: $r.data('fp'),
                qtd_total_g: $r.data('qtd-total-g'), volapa_ul: $r.data('volapa-ul'),
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                subtotal: parseFloat($r.data('subtotal')) || 0
            });
        });
        if (!ok) { alert('Selecione o ativo em todas as linhas.'); return; }

        $('#taof-emb-body .taof-emb-row').each(function () {
            var $r = $(this);
            if (!$r.data('emb-id')) { ok = false; return false; }
            itens.push({
                tipo: 'emb', ativo_id: $r.data('emb-id'), nome: $r.data('emb-nome'),
                quantidade: parseInt($r.find('.taof-emb-qty').val()) || 0,
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                subtotal: parseFloat($r.data('subtotal-emb')) || 0
            });
        });
        if (!ok) { alert('Selecione a embalagem em todas as linhas.'); return; }
        if (!itens.length) { alert('Adicione ao menos um ativo.'); return; }

        var calculado = itens.reduce(function(s,i){ return s + (i.subtotal||0); }, 0);
        var custoFixo = formaAtual ? (formaAtual.custoFixo || 0) : 0;
        var subtotal  = calculado + custoFixo;
        var acrescPct = parseFloat($('#taof-acrescimo-pct').val()) || 0;
        var desctPct  = parseFloat($('#taof-desconto-pct').val())  || 0;
        var final     = subtotal + (subtotal * acrescPct / 100) - (subtotal * desctPct / 100);

        $btn.prop('disabled', true);
        $sp.css('visibility', 'visible');
        $msg.hide();

        $.post(ajaxUrl, {
            action: 'tao_formula_save_orcamento', nonce: nonce,
            nome_paciente: $('#taof-nome-paciente').val(),
            whatsapp:      $('#taof-whatsapp').val(),
            forma_id:      $('#taof-forma-sel').val() || '',
            forma_nome:    formaAtual ? formaAtual.nome : '',
            qtde_potes:    getPotes(),
            custo_fixo:    custoFixo,
            total_insumos: calculado,
            margem_pct:    acrescPct,
            desconto_pct:  desctPct,
            total_orcamento: final,
            observacoes:   $('#taof-observacoes').val(),
            itens:         JSON.stringify(itens)
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
