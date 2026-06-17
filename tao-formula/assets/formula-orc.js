/* TAO Formulas — Orcamento v2.0 */
(function ($) {
    'use strict';
    if (!document.getElementById('taof-orc-form')) return;

    var formasMap  = window.taofOrcFormasMap || {};
    var capsulas   = window.taofCapsulas || [];
    var formaAtual = null;
    var ajaxUrl    = (typeof taoFormula !== 'undefined') ? taoFormula.ajaxUrl : '/wp-admin/admin-ajax.php';
    var nonce      = (typeof taoFormula !== 'undefined') ? taoFormula.nonce   : '';

    // ── Formato BR ────────────────────────────────────────────────────
    function fmt(n, dec) {
        if (dec === undefined) dec = 2;
        return parseFloat(n || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: dec, maximumFractionDigits: dec
        });
    }

    var toMgMap = { mg: 1, g: 1000, mcg: 0.001, ml: 1000 };
    function toMg(v, u) { return v * (toMgMap[u] || 1); }

    function getVol()     { return Math.max(1, parseFloat($('#taof-forma-vol').val()) || 1); }
    function getPotes()   { return Math.max(1, parseInt($('#taof-qtde-potes').val()) || 1); }
    function getUnidade() { return $('#taof-forma-unidade').val() || 'g'; }
    function getMultiplicador() { return getVol() * getPotes(); }
    function getCustoFixo() { return parseFloat($('#taof-custo-fixo-inp').val()) || 0; }

    // ── Popula Tipo Capsula (somente para cap / duo_cap) ──────────────
    function popularTipoCapsula() {
        var $sel = $('#taof-forma-tipo');
        $sel.empty().append('<option value="">— Tipo capsula —</option>');
        var tipos = [];
        capsulas.forEach(function (c) {
            if (tipos.indexOf(c.tipo) === -1) tipos.push(c.tipo);
        });
        tipos.sort();
        tipos.forEach(function (t) {
            var lbl = t.charAt(0).toUpperCase() + t.slice(1).toLowerCase();
            $sel.append($('<option>').val(t).text(lbl));
        });
    }

    // ── Popula Unidade conforme a forma ──────────────────────────────
    function popularUnidade(formaTipo) {
        var $sel = $('#taof-forma-unidade');
        $sel.empty();
        var opts;
        var isCap = (formaTipo === 'cap' || formaTipo === 'duo_cap');
        if (isCap) {
            opts = [{ val: 'caps', lbl: 'caps.' }];
        } else if (formaTipo === 'un') {
            opts = [{ val: 'un', lbl: 'un.' }];
        } else if (['gel', 'creme', 'envelope', 'outro'].indexOf(formaTipo) !== -1) {
            opts = [{ val: 'g', lbl: 'g' }, { val: 'ml', lbl: 'ml' }];
        } else {
            // locao, shampoo, floral, solucao
            opts = [{ val: 'ml', lbl: 'ml' }, { val: 'g', lbl: 'g' }, { val: 'L', lbl: 'L' }];
        }
        opts.forEach(function (o) {
            $sel.append($('<option>').val(o.val).text(o.lbl));
        });
        // Pre-seleciona unidade default da forma cadastrada
        if (formaAtual && formaAtual.unidVolume) {
            $sel.val(formaAtual.unidVolume);
        }
    }

    // ── Calculo por linha ─────────────────────────────────────────────
    function calcularLinha($row) {
        if ($row.hasClass('taof-row-qsp')) { calcularTotais(); return; }
        var dose         = parseFloat($row.find('.taof-orc-dose').val()) || 0;
        var doseUnit     = $row.find('.taof-orc-dose-unit').val() || 'mg';
        var unidPadrao   = $row.data('unid-padrao') || 'mg';
        var fp           = parseFloat($row.data('fp'))           || 1;
        var diluicao     = parseFloat($row.data('diluicao'))     || 1;
        var teor         = parseFloat($row.data('teor'))         || 100;
        var densidade    = parseFloat($row.data('densidade'))    || 1;
        var vendaUnit    = parseFloat($row.data('venda-unit'))   || 0;
        var concentracao = parseFloat($row.data('concentracao')) || 0;
        var mult         = getMultiplicador();
        var isCap        = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        var isSpecial    = (doseUnit === 'UI' || doseUnit === 'UFC' || doseUnit === 'BLH');

        if (isSpecial) {
            // BLH: dose em bilhoes — converte para UFC antes de dividir pela concentracao (UFC/g)
            var dose_ufc       = (doseUnit === 'BLH') ? dose * 1e9 : dose;
            var qtd_g_per_dose = concentracao > 0 ? dose_ufc / concentracao : 0;
            var qtd_total_g    = qtd_g_per_dose * mult;
            var qtd_total_mg   = qtd_total_g * 1000;
            var qtd_esp_total  = dose * mult; // total na unidade original (BLH ou UFC/UI)

            var qtd_em_padrao;
            if      (unidPadrao === 'g')        qtd_em_padrao = qtd_total_g;
            else if (unidPadrao === 'mg')        qtd_em_padrao = qtd_total_mg;
            else if (unidPadrao === doseUnit)    qtd_em_padrao = qtd_esp_total;
            else                                 qtd_em_padrao = qtd_total_g;
            var subtotal = qtd_em_padrao * vendaUnit;

            // Label amigavel
            var doseLabel;
            if (doseUnit === 'BLH') {
                doseLabel = fmt(qtd_esp_total, 2) + ' Blh UFC';
            } else if (qtd_esp_total >= 1e9) {
                doseLabel = fmt(qtd_esp_total / 1e9, 2) + ' Blh ' + doseUnit;
            } else if (qtd_esp_total >= 1e6) {
                doseLabel = fmt(qtd_esp_total / 1e6, 2) + ' Mlh ' + doseUnit;
            } else {
                doseLabel = fmt(qtd_esp_total, 0) + ' ' + doseUnit;
            }

            // VOLAPA para ingredientes especiais: o produto continua sendo um pó com massa e densidade
            // Fallback quando concentracao=0: usa 10 BLH/g (10e9 UFC/g) como referência para probióticos
            var volapa_special = 0;
            if (isCap && densidade > 0 && dose > 0) {
                var conc_efetiva = concentracao > 0 ? concentracao : 10e9;
                volapa_special = (dose_ufc / conc_efetiva) * 1000 / densidade;
            }

            $row.find('.taof-orc-qtd-total').text(fmt(qtd_total_mg, 2) + ' mg (' + doseLabel + ')');
            $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
            $row.data({ subtotal: subtotal, 'qtd-total-g': qtd_total_g,
                'qtd-em-padrao': qtd_em_padrao, 'qtd-unit': unidPadrao,
                dose: dose, 'dose-unit': doseUnit, 'volapa-ul': volapa_special });
            calcularTotais();
            return;
        }

        // Unidades de massa: mg, g, mcg, ml
        var dose_mg;
        if      (doseUnit === 'mg')  dose_mg = dose;
        else if (doseUnit === 'g')   dose_mg = dose * 1000;
        else if (doseUnit === 'mcg') dose_mg = dose / 1000;
        else if (doseUnit === 'ml')  dose_mg = dose * densidade * 1000;
        else                         dose_mg = dose;

        // FC: QTREAL = dose_mg x DILUICAO / (TEOR/100)  — FP nao entra no VOLAPA
        var dose_mg_dil  = dose_mg * diluicao;
        var dose_mg_real = dose_mg_dil / (teor / 100);
        var volapa_uL    = (isCap && densidade > 0) ? (dose_mg_real / densidade) : 0;
        var qtd_total_mg = dose_mg_real * fp * mult;
        var qtd_total_g  = qtd_total_mg / 1000;

        var qtd_em_padrao;
        if      (unidPadrao === 'mg')  qtd_em_padrao = qtd_total_mg;
        else if (unidPadrao === 'g')   qtd_em_padrao = qtd_total_g;
        else if (unidPadrao === 'mcg') qtd_em_padrao = qtd_total_mg * 1000;
        else                           qtd_em_padrao = qtd_total_g;

        var subtotal   = qtd_em_padrao * vendaUnit;
        var totalLabel = doseUnit === 'ml'
            ? fmt(dose_mg_real * fp * mult / (densidade * 1000), 3) + ' ml'
            : fmt(qtd_total_mg, 2) + ' mg';
        $row.find('.taof-orc-qtd-total').text(totalLabel);
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qtd_total_g,
            'qtd-em-padrao': qtd_em_padrao, 'qtd-unit': unidPadrao,
            dose: dose, 'dose-unit': doseUnit, 'volapa-ul': volapa_uL });
        calcularTotais();
    }

    // ── QSP: atualiza a linha marcada como excipiente ─────────────────
    // Chamado DENTRO de calcularTotais (sem recursao)
    function atualizarQSPRow() {
        var $qspRow = $('#taof-itens-body .taof-item-row.taof-row-qsp');
        if (!$qspRow.length || !formaAtual) return;

        var isCap = (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        if (isCap) {
            _qspCapsula($qspRow);
        } else {
            _qspForma($qspRow);
        }
    }

    function _qspForma($row) {
        var vol      = getVol();
        var unidade  = getUnidade();
        var qspDens  = parseFloat($row.data('densidade')) || 1;

        // Total do lote em gramas
        var totalG;
        if      (unidade === 'g')    totalG = vol;
        else if (unidade === 'ml')   totalG = vol * qspDens;
        else if (unidade === 'L')    totalG = vol * 1000 * qspDens;
        else { $row.data({ subtotal: 0, 'qtd-total-g': 0 }); return; }

        // Soma ativos nao-QSP em gramas
        var sumG = 0;
        $('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').each(function () {
            sumG += parseFloat($(this).data('qtd-total-g')) || 0;
        });

        var qspG  = Math.max(0, totalG - sumG);
        var qspMg = qspG * 1000;

        var vendaUnit  = parseFloat($row.data('venda-unit'))  || 0;
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var qtdEmUnid  = unidPadrao === 'g' ? qspG : unidPadrao === 'mg' ? qspMg : qspG;
        var subtotal   = qtdEmUnid * vendaUnit;

        $row.find('.taof-orc-qtd-total').text(fmt(qspG * 1000, 2) + ' mg (QSP)');
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qspG, 'volapa-ul': 0 });
    }

    function _qspCapsula($row) {
        var r = calcularCapsulaIdeal(getNPerDoseForced());
        if (!r) { $row.data({ subtotal: 0, 'qtd-total-g': 0, 'volapa-ul': 0 }); return; }

        var ftench       = formaAtual.ftenchcap || 1;
        // sumVOLAPA ja e por dose (VOLAPA de cada ativo e qtd_mg_per_unit / densidade)
        var sumVOLAPA = 0;
        $('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').each(function () {
            sumVOLAPA += parseFloat($(this).data('volapa-ul')) || 0;
        });

        // Volume disponivel por dose = nPerDose capsulas × vol_ul × ftench
        var availPerDose     = r.cap.vol_ul * r.nPerDose * ftench;
        var qspVOLAPAPerDose = Math.max(0, availPerDose - sumVOLAPA);
        var qspVOLAPAPerCap  = r.nPerDose > 0 ? qspVOLAPAPerDose / r.nPerDose : qspVOLAPAPerDose;

        var qspDens    = parseFloat($row.data('densidade')) || 1;
        // densidade g/mL = mg/µL numericamente: mass_mg = vol_µL × dens_g_mL
        var qspMgPerDose = qspVOLAPAPerDose * qspDens;
        var qspTotalMg   = qspMgPerDose * getVol() * getPotes();
        var qspTotalG    = qspTotalMg / 1000;

        var vendaUnit  = parseFloat($row.data('venda-unit')) || 0;
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var qtdEmUnid  = unidPadrao === 'g' ? qspTotalG : qspTotalMg;
        var subtotal   = qtdEmUnid * vendaUnit;

        $row.find('.taof-orc-qtd-total').text(
            fmt(qspTotalMg, 2) + ' mg (' + fmt(qspVOLAPAPerCap, 1) + ' µL/caps QSP)'
        );
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qspTotalG, 'volapa-ul': qspVOLAPAPerDose });
    }

    // ── Capsula ideal: filtra pelo tipo selecionado ───────────────────
    // forceN: se informado, encontra a menor capsula que cabe nesse numero de caps/dose
    function calcularCapsulaIdeal(forceN) {
        var totalVOLAPA = 0;
        $('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').each(function () {
            totalVOLAPA += parseFloat($(this).data('volapa-ul')) || 0;
        });
        if (totalVOLAPA <= 0) return null;

        var tipoSel = $('#taof-forma-tipo').val();
        var ftench  = formaAtual ? (formaAtual.ftenchcap || 1) : 1;
        var pool    = capsulas.filter(function (c) {
            return !tipoSel || c.tipo.toLowerCase() === tipoSel.toLowerCase();
        });
        if (!pool.length) pool = capsulas.slice();
        var sorted  = pool.slice().sort(function (a, b) { return a.vol_ul - b.vol_ul; });
        if (!sorted.length) return null;

        if (forceN) {
            for (var i = 0; i < sorted.length; i++) {
                if (sorted[i].vol_ul * forceN * ftench >= totalVOLAPA) {
                    return {
                        cap: sorted[i], nPerDose: forceN,
                        volTotal: sorted[i].vol_ul * forceN * ftench, volapa: totalVOLAPA,
                        pct: totalVOLAPA / (sorted[i].vol_ul * forceN * ftench) * 100, overflow: false
                    };
                }
            }
            var maior = sorted[sorted.length - 1];
            return {
                cap: maior, nPerDose: forceN,
                volTotal: maior.vol_ul * forceN * ftench, volapa: totalVOLAPA,
                pct: totalVOLAPA / (maior.vol_ul * forceN * ftench) * 100, overflow: true
            };
        }

        for (var n = 1; n <= 6; n++) {
            for (var i = 0; i < sorted.length; i++) {
                if (sorted[i].vol_ul * n * ftench >= totalVOLAPA) {
                    return {
                        cap: sorted[i], nPerDose: n,
                        volTotal: sorted[i].vol_ul * n * ftench, volapa: totalVOLAPA,
                        pct: totalVOLAPA / (sorted[i].vol_ul * n * ftench) * 100, overflow: false
                    };
                }
            }
        }
        var maior   = sorted[sorted.length - 1];
        var nNeeded = Math.ceil(totalVOLAPA / (maior.vol_ul * ftench));
        return {
            cap: maior, nPerDose: nNeeded,
            volTotal: maior.vol_ul * nNeeded * ftench, volapa: totalVOLAPA,
            pct: totalVOLAPA / (maior.vol_ul * nNeeded * ftench) * 100, overflow: true
        };
    }

    function getNPerDoseForced() {
        var $input = $('#taof-caps-por-dose');
        return $input.data('manual') ? Math.max(1, parseInt($input.val()) || 1) : null;
    }

    function sugerirCapsula() {
        var isCap = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        if (!isCap || !capsulas.length) { $('#taof-card-capsulas').hide(); return null; }

        // Forma é cápsula: card sempre visível — mostra placeholder se doses ainda não informadas
        $('#taof-card-capsulas').show();

        var forceN = getNPerDoseForced();
        var r = calcularCapsulaIdeal(forceN);
        if (!r) {
            $('#taof-cap-sugerida').html('<em style="color:#94a3b8">Informe as doses para calcular a cápsula</em>');
            $('#taof-caps-nome').text('—');
            $('#taof-caps-vol').text('—');
            $('#taof-caps-volapa').text('—');
            $('#taof-caps-total-un').text('—');
            $('#taof-caps-preco-un').text('—');
            $('#taof-caps-subtotal').text('R$ 0,00');
            return null;
        }

        // Modo auto: atualiza o campo sem disparar evento
        if (!forceN) { $('#taof-caps-por-dose').val(r.nPerDose); }

        var c    = r.cap, n = r.nPerDose, pct = r.pct;
        var cor  = pct > 100 ? '#dc2626' : (pct > 85 ? '#d97706' : '#16a34a');
        var tipo = c.tipo.charAt(0).toUpperCase() + c.tipo.slice(1).toLowerCase();

        // Header: resumo fill da capsula
        $('#taof-cap-sugerida').html(
            tipo + ' N&ordm;&nbsp;' + c.numero + ' (' + c.vol_ul + '&nbsp;&micro;L)' +
            ' &mdash; <span style="color:' + cor + '">' +
            fmt(r.volapa, 1) + '&nbsp;/&nbsp;' + fmt(r.volTotal, 0) +
            '&nbsp;&micro;L (' + fmt(pct, 0) + '% cheio)</span>'
        );

        // Linha da tabela de cápsulas
        var totalCaps    = getVol() * getPotes() * n;
        var custoCapsula = (c.venda_unit > 0) ? c.venda_unit * totalCaps : 0;
        var nDoseLabel   = n > 1
            ? '<strong style="color:#d97706">' + n + ' cáps/dose</strong>'
            : '1 cáps/dose';

        $('#taof-caps-nome').html(
            tipo + ' N&ordm;&nbsp;' + c.numero +
            (n > 1 ? ' &nbsp;<span style="color:#d97706;font-size:11px">(' + n + '&times;)</span>' : '')
        );
        $('#taof-caps-vol').text(c.vol_ul + ' µL');
        $('#taof-caps-volapa').html(
            fmt(r.volapa, 1) + ' µL ' +
            '<span style="color:' + cor + ';font-size:11px">(' + fmt(pct, 0) + '%)</span>'
        );
        $('#taof-caps-total-un').html('<strong>' + totalCaps + '</strong> un');
        $('#taof-caps-preco-un').text(c.venda_unit > 0 ? 'R$ ' + fmt(c.venda_unit) : '—');
        $('#taof-caps-subtotal').text('R$ ' + fmt(custoCapsula));

        r.custoCapsula = custoCapsula;
        return r;
    }

    // ── Totais ────────────────────────────────────────────────────────
    function calcularTotais() {
        // 1. Sugestao de capsula primeiro: atualiza #taof-caps-por-dose em modo auto
        //    antes de atualizarQSPRow (que usa nPerDose para calcular QSP)
        var r = sugerirCapsula();

        // 2. Atualiza linha QSP (usa caps-por-dose ja atualizado acima)
        atualizarQSPRow();

        // 3. Soma todas as linhas (incluindo QSP ja atualizada)
        var calculado = 0;
        $('#taof-itens-body .taof-item-row').each(function () {
            calculado += parseFloat($(this).data('subtotal')) || 0;
        });
        $('#taof-emb-body .taof-emb-row').each(function () {
            calculado += parseFloat($(this).data('subtotal-emb')) || 0;
        });

        // 4. Custo fixo e cápsulas
        var custoFixo    = getCustoFixo();
        var custoCapsula = r ? (r.custoCapsula || 0) : 0;

        if (custoCapsula > 0) {
            var totalCaps = getVol() * getPotes() * r.nPerDose;
            $('#taof-caps-custo-label').html(
                '(' + totalCaps + ' un &times; R$&nbsp;' + fmt(r.cap.venda_unit) + '/un)'
            );
            $('#taof-res-caps-custo').text('R$ ' + fmt(custoCapsula));
            $('#taof-row-caps-custo').show();
        } else {
            $('#taof-row-caps-custo').hide();
        }

        var subtotal  = calculado + custoFixo + custoCapsula;
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
        $('#taof-res-final').html('<strong>R$ ' + fmt(final) + '</strong>');
    }

    // ── Forma select ──────────────────────────────────────────────────
    $('#taof-forma-sel').on('change', function () {
        var id = $(this).val();
        formaAtual = id ? (formasMap[id] || null) : null;

        var $colTipo = $('#taof-col-tipo');

        if (formaAtual) {
            var isCap = (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');

            // Vol/Qtde: pre-preenche com padrao da forma
            var defaultVol = isCap ? formaAtual.nCapsulas : formaAtual.volume;
            $('#taof-forma-vol')
                .val(defaultVol || '')
                .attr('placeholder', isCap ? 'No. caps.' : 'Qtde');

            // Tipo Capsula: somente para cap/duo_cap
            if (isCap) {
                popularTipoCapsula();
                $colTipo.show();
                $('#taof-caps-por-dose').removeData('manual').val(1);
            } else {
                $('#taof-forma-tipo').empty();
                $colTipo.hide();
                $('#taof-card-capsulas').hide();
                $('#taof-caps-por-dose').removeData('manual').val(1);
            }

            // Unidade
            popularUnidade(formaAtual.tipo);

            // Custo fixo editavel por orcamento
            $('#taof-custo-fixo-inp').val(parseFloat(formaAtual.custoFixo || 0).toFixed(2));
        } else {
            $('#taof-forma-vol').val('').attr('placeholder', 'Ex: 30');
            $('#taof-forma-tipo').empty();
            $colTipo.hide();
            $('#taof-card-capsulas').hide();
            $('#taof-caps-por-dose').removeData('manual').val(1);
            $('#taof-forma-unidade').empty().append('<option value="">-</option>');
            $('#taof-custo-fixo-inp').val('0.00');
        }

        $('#taof-itens-body .taof-item-row').each(function () { calcularLinha($(this)); });
    });

    // Recalcula quando atendente altera Vol/Qtde, Tipo Capsula, Unidade ou Potes
    $('#taof-forma-vol, #taof-forma-tipo, #taof-forma-unidade, #taof-qtde-potes').on('input change', function () {
        $('#taof-itens-body .taof-item-row').each(function () { calcularLinha($(this)); });
    });
    $('#taof-acrescimo-pct, #taof-desconto-pct, #taof-custo-fixo-inp').on('input change', calcularTotais);

    // Caps/dose: override manual ativa multiplicador; botao auto redefine para calculo automatico
    $('#taof-caps-por-dose').on('input change', function () {
        $(this).data('manual', true);
        calcularTotais();
    });
    $('#taof-caps-auto-btn').on('click', function () {
        $('#taof-caps-por-dose').removeData('manual').val(1);
        calcularTotais();
    });

    // ── QSP toggle ────────────────────────────────────────────────────
    function toggleQSP($row, ativar) {
        if (ativar) {
            // Remove QSP de qualquer outra linha (apenas 1 QSP por formula)
            $('#taof-itens-body .taof-item-row.taof-row-qsp').each(function () {
                toggleQSP($(this), false);
            });
            $row.addClass('taof-row-qsp');
            $row.find('.taof-btn-qsp').addClass('ativo').text('QSP v');
            $row.find('.taof-orc-dose').prop('readonly', true).val('');
        } else {
            $row.removeClass('taof-row-qsp');
            $row.find('.taof-btn-qsp').removeClass('ativo').text('QSP');
            $row.find('.taof-orc-dose').prop('readonly', false);
            $row.find('.taof-orc-qtd-total').text('—');
            $row.data({ subtotal: 0, 'qtd-total-g': 0, 'volapa-ul': 0 });
        }
        calcularTotais();
    }

    $(document).on('click', '.taof-btn-qsp', function () {
        var $row = $(this).closest('.taof-item-row');
        toggleQSP($row, !$row.hasClass('taof-row-qsp'));
    });

    // ── Adicionar linha de ativo ──────────────────────────────────────
    $('#taof-btn-add-item').on('click', function () {
        var frag = document.getElementById('taof-item-tpl').content.cloneNode(true);
        $('#taof-itens-body').append(frag);
        var $row = $('#taof-itens-body .taof-item-row').last();
        $row.data({ subtotal: 0, 'volapa-ul': 0, 'qtd-total-g': 0 });
        initRow($row);
    });

    function initRow($row) {
        $row.on('input change', '.taof-orc-dose, .taof-orc-dose-unit', function () { calcularLinha($row); });
        $row.on('click', '.taof-btn-del-item', function () { $row.remove(); calcularTotais(); });
        initAtivoAC($row);
    }

    // ── Helper: posiciona dropdown em position:fixed ──────────────────
    // Necessário porque overflow-x:auto no wrapper recorta position:absolute
    function positionDropdown($inp, $dd) {
        var r = $inp[0].getBoundingClientRect();
        $dd.css({ top: r.bottom + 2, left: r.left, width: r.width });
    }

    // ── Autocomplete — Ativos ─────────────────────────────────────────
    function initAtivoAC($row) {
        var $inp   = $row.find('.taof-orc-ativo-search');
        var $dd    = $row.find('.taof-ac-dropdown');
        var $idFld = $row.find('.taof-orc-ativo-id');
        var timer  = null;

        $inp.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            if (q.length < 2) { $dd.hide().empty(); return; }
            positionDropdown($inp, $dd);
            $dd.empty().append('<div class="taof-ac-item" style="color:#94a3b8">Buscando...</div>').show();
            timer = setTimeout(function () {
                $.ajax({
                    url:      ajaxUrl,
                    method:   'GET',
                    dataType: 'text',
                    data:     { action: 'tao_formula_search_ativos', nonce: nonce, q: q, grupo: 'M' },
                    success:  function (text) {
                        var resp;
                        try {
                            while (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
                            resp = JSON.parse(text);
                        } catch (e) {
                            $dd.empty().append(
                                '<div class="taof-ac-item" style="color:#dc2626">Resposta inválida: ' +
                                $('<span>').text(String(text).substring(0, 100)).html() + '</div>'
                            );
                            positionDropdown($inp, $dd);
                            $dd.show();
                            return;
                        }
                        $dd.empty();
                        var lista = resp && resp.success && Array.isArray(resp.data) ? resp.data : [];
                        if (!lista.length) {
                            $dd.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhum resultado para "' + $('<span>').text(q).html() + '".</div>');
                        } else {
                            $.each(lista, function (_, a) {
                                var venda = parseFloat(a.preco_venda) > 0
                                    ? 'R$ ' + fmt(a.preco_venda, 4) + '/' + (a.unidade_padrao || 'g')
                                    : 'sem preço venda';
                                var info = (a.codigo_fc ? '[' + a.codigo_fc + '] ' : '') + venda;
                                if (a.diluicao && a.diluicao != 1) info += ' · dil 1:' + a.diluicao;
                                var $item = $('<div class="taof-ac-item">').html(
                                    '<span>' + $('<span>').text(a.nome).html() + '</span>' +
                                    '<small>' + $('<span>').text(info).html() + '</small>'
                                );
                                $item.on('mousedown', function (e) { e.preventDefault(); selecionarAtivo(a); });
                                $dd.append($item);
                            });
                        }
                        positionDropdown($inp, $dd);
                        $dd.show();
                    },
                    error: function (xhr, status, err) {
                        var raw = xhr.responseText || '';
                        var msg = '(' + status + ') ' + (raw || err || status);
                        $dd.empty().append(
                            '<div class="taof-ac-item" style="color:#dc2626">Erro ' + xhr.status + ': ' +
                            $('<span>').text(String(msg).substring(0, 140)).html() + '</div>'
                        );
                        positionDropdown($inp, $dd);
                        $dd.show();
                    }
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
                'ativo-id':     a.id,
                'ativo-nome':   a.nome,
                'codigo-fc':    a.codigo_fc   || '',
                'unid-padrao':  a.unidade_padrao,
                'custo-unit':   a.custo_por_unidade,
                'venda-unit':   a.preco_venda,
                'diluicao':     parseFloat(a.diluicao)      || 1,
                'teor':         parseFloat(a.teor)           || 100,
                'densidade':    parseFloat(a.densidade)      || 1,
                'fp':           parseFloat(a.fator_perda)    || 1,
                'concentracao': parseFloat(a.concentracao)   || 0,
            });
            var vendaLabel = parseFloat(a.preco_venda) > 0
                ? 'R$ ' + fmt(a.preco_venda, 4) + '/' + (a.unidade_padrao || 'g')
                : '—';
            $row.find('.taof-orc-preco-venda').text(vendaLabel);
            var u = a.unidade_padrao;
            if (['mg', 'mcg', 'g', 'UI', 'UFC', 'BLH', 'ml'].indexOf(u) === -1) u = 'mg';
            if (u === 'g') u = 'mg'; // pos vendidos em g sao prescritos em mg
            // Probióticos UFC ou BLH: sempre prescrevem em BLH (bilhoes) para facilitar entrada
            if (u === 'UFC' || u === 'BLH') u = 'BLH';
            $row.find('.taof-orc-dose-unit').val(u);
            $dd.hide().empty();
            calcularLinha($row);
        }

        $inp.on('blur',  function () { setTimeout(function () { $dd.hide(); }, 150); });
        $inp.on('focus', function () { if ($dd.children().length) { positionDropdown($inp, $dd); $dd.show(); } });
    }

    // ── Embalagens ────────────────────────────────────────────────────
    function calcularEmb($row) {
        var subtotal = (parseInt($row.find('.taof-emb-qty').val()) || 0) *
                       (parseFloat($row.data('custo-unit')) || 0);
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
        var $dd    = $row.find('.taof-ac-dropdown');
        var $idFld = $row.find('.taof-emb-id');
        var timer  = null;
        $inp.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            if (q.length < 2) { $dd.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: q, grupo: 'E' },
                function (resp) {
                    $dd.empty();
                    if (!resp.success || !resp.data.length) {
                        $dd.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhuma embalagem.</div>');
                    } else {
                        $.each(resp.data, function (_, a) {
                            var $item = $('<div class="taof-ac-item">').html(
                                '<span>' + $('<span>').text(a.nome).html() + '</span>' +
                                '<small>R$ ' + fmt(a.custo_por_unidade, 4) + '/un</small>'
                            );
                            $item.on('mousedown', function (e) {
                                e.preventDefault();
                                $inp.val(a.nome); $idFld.val(a.id);
                                $row.data({ 'emb-id': a.id, 'emb-nome': a.nome, 'custo-unit': a.custo_por_unidade });
                                $row.find('.taof-emb-custo-label').text('R$ ' + fmt(a.custo_por_unidade, 4) + '/un');
                                $dd.hide().empty();
                                calcularEmb($row);
                            });
                            $dd.append($item);
                        });
                    }
                    positionDropdown($inp, $dd);
                    $dd.show();
                });
            }, 280);
        });
        $inp.on('blur',  function () { setTimeout(function () { $dd.hide(); }, 150); });
        $inp.on('focus', function () { if ($dd.children().length) { positionDropdown($inp, $dd); $dd.show(); } });
    }

    $('#taof-btn-add-emb').on('click', function () {
        var frag = document.getElementById('taof-emb-tpl').content.cloneNode(true);
        $('#taof-emb-body').append(frag);
        var $row = $('#taof-emb-body .taof-emb-row').last();
        $row.data('subtotal-emb', 0);
        initEmbRow($row);
    });

    // ── Salvar ────────────────────────────────────────────────────────
    $('#taof-orc-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#taof-orc-salvar'), $sp = $('.taof-spinner'), $msg = $('.taof-msg');
        var itens = [], ok = true;

        if (!formaAtual)           { alert('Selecione a forma farmaceutica.'); return; }
        if (getVol() <= 0)         { alert('Informe o volume / quantidade.');  return; }

        var cap = (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap')
                  ? calcularCapsulaIdeal() : null;

        $('#taof-itens-body .taof-item-row').each(function () {
            var $r    = $(this);
            if (!$r.data('ativo-id')) { ok = false; return false; }
            var isQsp = $r.hasClass('taof-row-qsp');
            itens.push({
                tipo:             'mp',
                ativo_id:         $r.data('ativo-id'),
                nome:             $r.data('ativo-nome'),
                codigo_fc:        $r.data('codigo-fc') || '',
                is_qsp:           isQsp,
                dose:             isQsp ? null : $r.data('dose'),
                dose_unit:        $r.data('dose-unit'),
                multiplicador:    getMultiplicador(),
                qtde_potes:       getPotes(),
                n_caps_por_dose:  cap ? cap.nPerDose : 1,
                capsula_tipo:     cap ? cap.cap.tipo : null,
                capsula_numero:   cap ? cap.cap.numero : null,
                diluicao:         $r.data('diluicao'),
                teor:             $r.data('teor'),
                fp:               $r.data('fp'),
                qtd_total_g:      $r.data('qtd-total-g'),
                volapa_ul:        $r.data('volapa-ul'),
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                preco_venda:       parseFloat($r.data('venda-unit')) || 0,
                subtotal:          parseFloat($r.data('subtotal'))    || 0
            });
        });
        if (!ok) { alert('Selecione o ativo em todas as linhas.'); return; }

        $('#taof-emb-body .taof-emb-row').each(function () {
            var $r = $(this);
            if (!$r.data('emb-id')) { ok = false; return false; }
            itens.push({
                tipo:             'emb',
                ativo_id:         $r.data('emb-id'),
                nome:             $r.data('emb-nome'),
                quantidade:       parseInt($r.find('.taof-emb-qty').val()) || 0,
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                subtotal:          parseFloat($r.data('subtotal-emb')) || 0
            });
        });
        if (!ok) { alert('Selecione a embalagem em todas as linhas.'); return; }
        if (!itens.length) { alert('Adicione ao menos um ativo.'); return; }

        var calculado = itens.reduce(function (s, i) { return s + (i.subtotal || 0); }, 0);
        var custoFixo = getCustoFixo();
        var subtotal  = calculado + custoFixo;
        var acrescPct = parseFloat($('#taof-acrescimo-pct').val()) || 0;
        var desctPct  = parseFloat($('#taof-desconto-pct').val())  || 0;
        var final     = subtotal + (subtotal * acrescPct / 100) - (subtotal * desctPct / 100);

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
            forma_vol:       getVol(),
            forma_tipo:      $('#taof-forma-tipo').val(),
            forma_unidade:   getUnidade(),
            qtde_potes:      getPotes(),
            custo_fixo:      custoFixo,
            total_insumos:   calculado,
            margem_pct:      acrescPct,
            desconto_pct:    desctPct,
            total_orcamento: final,
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
            $msg.text('Falha na requisicao.').addClass('err').show();
        });
    });

})(jQuery);
