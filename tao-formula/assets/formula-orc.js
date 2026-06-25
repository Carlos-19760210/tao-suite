/* TAO Formulas — Orcamento v2.0 */
(function ($) {
    'use strict';
    if (!document.getElementById('taof-orc-form')) return;

    var formasMap   = window.taofOrcFormasMap || {};
    var capsulas    = window.taofCapsulas || [];
    var formaAtual  = null;
    var ajaxUrl     = (typeof taoFormula !== 'undefined') ? taoFormula.ajaxUrl : '/wp-admin/admin-ajax.php';
    var nonce       = (typeof taoFormula !== 'undefined') ? taoFormula.nonce   : '';

    // Toast de feedback (ex.: sinônimo salvo) — visível para o usuário
    function taofToast(msg) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:22px;left:50%;transform:translateX(-50%);background:#16a34a;color:#fff;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;z-index:2147483647;box-shadow:0 4px 14px rgba(0,0,0,.25);opacity:0;transition:opacity .2s';
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.style.opacity = '1'; });
        setTimeout(function () { t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 250); }, 2800);
    }
    var IS_MODAL    = !! window.taofIsModal;
    var EDIT_ORC_ID = window.taofEditOrcId || '';
    var EDIT_DATA   = window.taofEditData  || null;

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
    function getVolDose() { return parseFloat($('#taof-vol-dose').val()) || 0; }
    function getMultiplicador() { return getVol() * getPotes(); }
    function getCustoFixo() { return parseFloat($('#taof-custo-fixo-inp').val()) || 0; }

    var LIQUID_TIPOS = ['solucao', 'locao', 'shampoo', 'floral'];
    function isLiquidForm() { return formaAtual && LIQUID_TIPOS.indexOf(formaAtual.tipo) !== -1; }

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

    // ── Popula "Tipo" do Envelope: capacidade do sachê (5 g / 15 g) ───
    function popularTipoEnvelope() {
        var $sel = $('#taof-forma-tipo');
        $sel.empty();
        [{ val: '5', lbl: '5 g' }, { val: '15', lbl: '15 g' }].forEach(function (o) {
            $sel.append($('<option>').val(o.val).text(o.lbl));
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
        } else if (formaTipo === 'envelope') {
            opts = [{ val: 'env', lbl: 'Env' }, { val: 'un', lbl: 'Unidade' }];
        } else if (formaTipo === 'un') {
            opts = [{ val: 'un', lbl: 'un.' }];
        } else if (['gel', 'creme', 'outro'].indexOf(formaTipo) !== -1) {
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
        // Ajustar um ativo solta o Valor Final travado do FC → o final passa a acompanhar o subtotal
        if (!_loadingEdit) _valorFinalTravado = null;
        var dose         = parseFloat($row.find('.taof-orc-dose').val()) || 0;
        var doseUnit     = $row.find('.taof-orc-dose-unit').val() || 'mg';
        var unidPadrao   = $row.data('unid-padrao') || 'mg';
        var fp           = parseFloat($row.data('fp'))           || 1;
        var diluicao     = parseFloat($row.data('diluicao'))     || 1;
        var teor         = parseFloat($row.data('teor'))         || 100;
        var densidade    = parseFloat($row.data('densidade'))    || 1;
        var vendaUnit    = parseFloat($row.data('venda-unit'))   || 0;
        var concentracao = parseFloat($row.data('concentracao')) || 0;
        var isCap     = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        var volDose   = getVolDose();
        var mult      = (isLiquidForm() && volDose > 0)
                        ? (getVol() / volDose) * getPotes()
                        : getMultiplicador();
        var isSpecial = (doseUnit === 'UI' || doseUnit === 'UFC' || doseUnit === 'BLH');

        // ── % (percentual do peso total da fórmula) ──────────────────────
        if (doseUnit === '%') {
            var unid_pct = getUnidade();
            var dens_pct = densidade;
            var totalG_pct = unid_pct === 'ml'
                ? getVol() * getPotes() * dens_pct
                : getVol() * getPotes();
            var qty_g_nom  = (dose / 100) * totalG_pct;
            var qty_g_real = qty_g_nom * diluicao / (teor / 100);
            var qty_g_fp   = qty_g_real * fp;
            var qty_mg_fp  = qty_g_fp * 1000;
            var qtdEmUnid_pct = unidPadrao === 'g' ? qty_g_fp : qty_mg_fp;
            var subtotal_pct  = qtdEmUnid_pct * vendaUnit;
            $row.find('.taof-orc-qtd-total').text(fmt(qty_mg_fp, 2) + ' mg (' + fmt(dose, 3) + '%)');
            $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal_pct));
            $row.data({ subtotal: subtotal_pct, 'qtd-total-g': qty_g_fp,
                'qtd-em-padrao': qtdEmUnid_pct, 'qtd-unit': unidPadrao,
                dose: dose, 'dose-unit': '%', 'volapa-ul': 0 });
            calcularTotais();
            return;
        }

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
        if (_loadingEdit) return;   // durante carga de edição, usa subtotal salvo
        var $qspRow = $('#taof-itens-body .taof-item-row.taof-row-qsp');
        if (!$qspRow.length || !formaAtual) return;

        var isCap = (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        if (isCap) {
            _qspCapsula($qspRow);
        } else if (formaAtual.tipo === 'envelope') {
            _qspEnvelope($qspRow);
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

    // QSP do Envelope: peso-alvo POR ENVELOPE informado na dose da linha QSP (independe da capacidade);
    // o excipiente completa os ativos até esse peso × nº de envelopes.
    function _qspEnvelope($row) {
        var dose     = parseFloat($row.find('.taof-orc-dose').val()) || 0;
        var doseUnit = $row.find('.taof-orc-dose-unit').val() || 'g';
        var alvoGperEnv;
        if      (doseUnit === 'mg')  alvoGperEnv = dose / 1000;
        else if (doseUnit === 'mcg') alvoGperEnv = dose / 1e6;
        else                          alvoGperEnv = dose;            // g (padrão)
        var alvoGtotal = alvoGperEnv * getVol() * getPotes();        // getVol() = nº de envelopes

        // Soma dos ativos não-QSP (já multiplicados pela qtde) em gramas
        var sumG = 0;
        $('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').each(function () {
            sumG += parseFloat($(this).data('qtd-total-g')) || 0;
        });

        var qspG  = Math.max(0, alvoGtotal - sumG);
        var qspMg = qspG * 1000;
        var vendaUnit  = parseFloat($row.data('venda-unit'))  || 0;
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var qtdEmUnid  = unidPadrao === 'g' ? qspG : unidPadrao === 'mg' ? qspMg : qspG;
        var subtotal   = qtdEmUnid * vendaUnit;

        $row.find('.taof-orc-qtd-total').text(fmt(qspMg, 2) + ' mg (QSP ' + fmt(alvoGperEnv, 2) + ' g/env)');
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qspG, 'volapa-ul': 0,
            dose: dose, 'dose-unit': doseUnit });
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

        var tipoSel = $('#taof-forma-tipo').val() || 'gelatinosa';   // sem nada específico → gelatinosa
        var ftench  = formaAtual ? (formaAtual.ftenchcap || 1) : 1;
        var pool    = capsulas.filter(function (c) {
            return c.tipo.toLowerCase() === tipoSel.toLowerCase();
        });
        if (!pool.length) pool = capsulas.slice();
        var sorted  = pool.slice().sort(function (a, b) { return a.vol_ul - b.vol_ul; });
        if (!sorted.length) return null;

        // Cápsula forçada manualmente (opção A): tamanho fixo, nº de cáps/dose automático
        if (_forcedCapId) {
            var fc = capsulas.filter(function (c) { return (c.tipo + '|' + c.numero) === _forcedCapId; })[0];
            if (fc) {
                var nF = forceN;
                if (!nF) {
                    nF = Math.ceil(totalVOLAPA / (fc.vol_ul * ftench)) || 1;
                    for (var k = 1; k <= 6; k++) { if (fc.vol_ul * k * ftench >= totalVOLAPA) { nF = k; break; } }
                }
                return {
                    cap: fc, nPerDose: nF,
                    volTotal: fc.vol_ul * nF * ftench, volapa: totalVOLAPA,
                    pct: totalVOLAPA / (fc.vol_ul * nF * ftench) * 100,
                    overflow: (fc.vol_ul * nF * ftench < totalVOLAPA)
                };
            }
        }

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

    // Popula o seletor de cápsula (uma vez) com "Automático" + todas as cápsulas
    function popularCapsulaSelect() {
        var $sel = $('#taof-caps-select');
        if (!$sel.length || $sel.data('pop')) return;
        var html = '<option value="auto">Automático</option>';
        capsulas.slice().sort(function (a, b) { return a.vol_ul - b.vol_ul; }).forEach(function (c) {
            var tipo = c.tipo.charAt(0).toUpperCase() + c.tipo.slice(1).toLowerCase();
            html += '<option value="' + c.tipo + '|' + c.numero + '">' + tipo + ' Nº ' + c.numero + ' (' + c.vol_ul + ' µL)</option>';
        });
        $sel.html(html).data('pop', true);
        $sel.on('change', function () {
            _forcedCapId = (this.value === 'auto') ? null : this.value;
            calcularTotais();
        });
    }

    function sugerirCapsula() {
        var isCap = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        if (!isCap || !capsulas.length) { $('#taof-card-capsulas').hide(); return null; }

        // Forma é cápsula: card sempre visível — mostra placeholder se doses ainda não informadas
        $('#taof-card-capsulas').show();
        popularCapsulaSelect();
        if ($('#taof-caps-select').length) { $('#taof-caps-select').val(_forcedCapId || 'auto'); }

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

        // Rótulo da opção "Automático" mostra a cápsula sugerida (não o texto genérico)
        var $autoOpt = $('#taof-caps-select option[value="auto"]');
        if ($autoOpt.length) {
            $autoOpt.text('Automático — ' + tipo + ' Nº ' + c.numero + ' (' + c.vol_ul + ' µL)');
        }

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

        // Custo fixo: predefinido na forma (R$ ou %) ou auto-calculado
        var $fixoInp   = $('#taof-custo-fixo-inp');
        var $fixoPct   = $('#taof-custo-fixo-pct');
        var $fixoBadge = $('#taof-custo-fixo-predef-badge');
        var cfTipo     = formaAtual ? (formaAtual.custoFixoTipo || '') : '';

        if (cfTipo === 'R') {
            custoFixo = formaAtual.custoFixo || 0;
            $fixoInp.val(custoFixo.toFixed(2)).prop('readonly', true);
            $fixoPct.hide();
            $fixoBadge.text('fixo pela forma').show();
        } else if (cfTipo === 'pct') {
            var pctPredef = formaAtual.custoFixo || 0;
            custoFixo = Math.round((calculado + custoCapsula) * pctPredef / 100 * 100) / 100;
            $fixoInp.val(custoFixo.toFixed(2)).prop('readonly', true);
            $fixoPct.hide();
            $fixoBadge.text(fmt(pctPredef) + '% sobre MP').show();
        } else {
            $fixoInp.prop('readonly', false);
            $fixoPct.show();
            $fixoBadge.hide();
            var baseFixo = calculado + custoCapsula;
            if ($fixoInp.data('manual')) {
                // Valor fixo informado/importado: o % exibido reflete o valor calculado
                custoFixo = parseFloat($fixoInp.val()) || 0;
                $fixoPct.val(baseFixo > 0 ? (custoFixo / baseFixo * 100).toFixed(1) : '0');
            } else {
                var pctFixo = parseFloat($fixoPct.val()) || 30;
                var sugPct  = Math.round(baseFixo * pctFixo / 100 * 100) / 100;
                $fixoInp.val(sugPct.toFixed(2));
                custoFixo = sugPct;
            }
        }

        var subtotal  = calculado + custoFixo + custoCapsula;
        window._taofSubtotal = subtotal;   // exposto p/ Recalcular e Aplicar margem

        // ── Acréscimo: % → valor (Sub-Total × %)  |  valor → % (valor ÷ Sub-Total) ──
        var $acrInp = $('#taof-acrescimo-val-inp');
        var acrescVal;
        if ($acrInp.data('manual')) {
            acrescVal = parseFloat($acrInp.val()) || 0;
            $('#taof-acrescimo-pct').val(subtotal > 0 ? (acrescVal / subtotal * 100).toFixed(2) : '0');
        } else {
            var acrescPct = parseFloat($('#taof-acrescimo-pct').val()) || 0;
            acrescVal = subtotal * acrescPct / 100;
            $acrInp.val(acrescVal.toFixed(2));
        }

        // Valor Sem Desconto = Sub-Total + Acréscimo
        var semDesconto = subtotal + acrescVal;

        // ── Desconto: % → valor (Sem Desconto × %)  |  valor → % (valor ÷ Sem Desconto) ──
        var $dscInp = $('#taof-desconto-val-inp');
        var desctVal;
        if ($dscInp.data('manual')) {
            desctVal = parseFloat($dscInp.val()) || 0;
            $('#taof-desconto-pct').val(semDesconto > 0 ? (desctVal / semDesconto * 100).toFixed(2) : '0');
        } else {
            var desctPct = parseFloat($('#taof-desconto-pct').val()) || 0;
            desctVal = semDesconto * desctPct / 100;
            $dscInp.val(desctVal.toFixed(2));
        }

        // VALOR FINAL (Com Desconto) = Valor Sem Desconto − Desconto
        var final = semDesconto - desctVal;

        // Valor mínimo da forma
        var valorMinimo = formaAtual ? (formaAtual.valorMinimo || 0) : 0;
        if (valorMinimo > 0 && final < valorMinimo) {
            final = valorMinimo;
            $('#taof-row-val-minimo').show();
            $('#taof-val-minimo-num').text('R$ ' + fmt(valorMinimo));
        } else {
            $('#taof-row-val-minimo').hide();
        }

        // Info excipiente base para cápsulas sem QSP
        atualizarInfoExcipiente();

        // Botão "Recalcular (importação)" só aparece em orçamento importado do FC
        if (typeof window._taofFcFinal !== 'undefined') $('#taof-recalc-import-wrap').toggle(window._taofFcFinal != null);

        $('#taof-res-calculado').text('R$ ' + fmt(calculado));
        $('#taof-res-custo-fixo').text('R$ ' + fmt(custoFixo));
        $('#taof-res-subtotal').text('R$ ' + fmt(subtotal));
        $('#taof-res-acrescimo').text('R$ ' + fmt(acrescVal));
        $('#taof-res-sem-desconto').text('R$ ' + fmt(semDesconto));
        $('#taof-res-desconto').text('R$ ' + fmt(desctVal));
        $('#taof-res-final').html('<strong>R$ ' + fmt(final) + '</strong>');
    }

    // Flag para bloquear recálculo de linhas durante loadEditData
    var _loadingEdit = false;
    var _forcedCapId = null;   // cápsula forçada manualmente (tipo|numero) ou null = automático
    var _valorFinalTravado = null;   // importado: VALOR FINAL fixo do orçamento (Opção 2)

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
                .attr('placeholder', isCap ? 'No. caps.' : (formaAtual.tipo === 'envelope' ? 'No. Env' : 'Qtde'));

            // Tipo: cápsula (tipos de cápsula) ou envelope (capacidade 5g/15g)
            if (isCap) {
                $('#taof-col-tipo-label').text('Tipo Cápsula');
                popularTipoCapsula();
                $colTipo.show();
                $('#taof-caps-por-dose').removeData('manual').val(1);
            } else if (formaAtual.tipo === 'envelope') {
                $('#taof-col-tipo-label').text('Tipo');
                popularTipoEnvelope();
                $colTipo.show();
                $('#taof-card-capsulas').hide();
                $('#taof-caps-por-dose').removeData('manual').val(1);
            } else {
                $('#taof-forma-tipo').empty();
                $colTipo.hide();
                $('#taof-card-capsulas').hide();
                $('#taof-caps-por-dose').removeData('manual').val(1);
            }

            // Unidade
            popularUnidade(formaAtual.tipo);

            // Vol/dose: visível apenas para formas líquidas (solução, loção, xarope...)
            $('#taof-col-vol-dose').toggle(isLiquidForm());
            if (!isLiquidForm()) $('#taof-vol-dose').val('');

            // Custo fixo: reseta para novo modo (predefinido ou auto)
            $('#taof-custo-fixo-inp').removeData('manual').prop('readonly', false).val('0.00');
            $('#taof-custo-fixo-pct').show().val(formaAtual.margemPct || 30);
            $('#taof-custo-fixo-predef-badge').hide();
            $('#taof-row-val-minimo').hide();
        } else {
            $('#taof-forma-vol').val('').attr('placeholder', 'Ex: 30');
            $('#taof-forma-tipo').empty();
            $colTipo.hide();
            $('#taof-card-capsulas').hide();
            $('#taof-caps-por-dose').removeData('manual').val(1);
            $('#taof-forma-unidade').empty().append('<option value="">-</option>');
            $('#taof-col-vol-dose').hide();
            $('#taof-vol-dose').val('');
            $('#taof-custo-fixo-inp').val('0.00');
        }

        if (!_loadingEdit) $('#taof-itens-body .taof-item-row').each(function () { calcularLinha($(this)); });
    });

    // Recalcula quando atendente altera Vol/Qtde, Tipo Capsula, Unidade, Potes ou Vol/dose
    $('#taof-forma-vol, #taof-forma-tipo, #taof-forma-unidade, #taof-qtde-potes, #taof-vol-dose').on('input change', function () {
        if (!_loadingEdit) $('#taof-itens-body .taof-item-row').each(function () { calcularLinha($(this)); });
    });
    // Acréscimo: % recalcula valor; valor direto marca manual
    $('#taof-acrescimo-pct').on('input change', function () {
        $('#taof-acrescimo-val-inp').removeData('manual');
        calcularTotais();
    });
    $('#taof-acrescimo-val-inp').on('input', function () {
        $(this).data('manual', true);
        calcularTotais();
    });
    // Desconto: idem
    $('#taof-desconto-pct').on('input change', function () {
        $('#taof-desconto-val-inp').removeData('manual');
        calcularTotais();
    });
    $('#taof-desconto-val-inp').on('input', function () {
        $(this).data('manual', true);
        calcularTotais();
    });
    // Custo fixo: marca como manual quando o usuário edita o valor
    $('#taof-custo-fixo-inp').on('input', function () {
        $(this).data('manual', true);
        calcularTotais();
    });
    // Recalcular pelas regras da importação (FINAL e Desconto do FC; Acréscimo = FINAL − Sub-Total)
    $('#taof-recalc-import').on('click', function () {
        var sub  = window._taofSubtotal || 0;
        var desc = (window._taofFcDesconto != null) ? window._taofFcDesconto : 0;
        if (window._taofFcDesconto != null) $('#taof-desconto-val-inp').val(desc.toFixed(2)).data('manual', true);
        if (window._taofFcFinal != null) {
            // acréscimo p/ o valor final voltar a ser o do FC: final = sub + acr − desc = FC
            var acr = window._taofFcFinal + desc - sub;
            $('#taof-acrescimo-val-inp').val(acr.toFixed(2)).data('manual', true);
        }
        calcularTotais();
    });
    // Alterar o % reseta o flag manual e recalcula
    $('#taof-custo-fixo-pct').on('input change', function () {
        $('#taof-custo-fixo-inp').removeData('manual');
        calcularTotais();
    });

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
            // Envelope: a dose vira o peso-alvo por envelope (editável). Demais formas: QSP automático.
            if (formaAtual && formaAtual.tipo === 'envelope') {
                $row.find('.taof-orc-dose').prop('readonly', false);
            } else {
                $row.find('.taof-orc-dose').prop('readonly', true).val('');
            }
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
    function adicionarLinha() {
        var frag = document.getElementById('taof-item-tpl').content.cloneNode(true);
        $('#taof-itens-body').append(frag);
        var $row = $('#taof-itens-body .taof-item-row').last();
        $row.data({ subtotal: 0, 'volapa-ul': 0, 'qtd-total-g': 0 });
        initRow($row);
        // Foca o campo de busca imediatamente
        setTimeout(function () { $row.find('.taof-orc-ativo-search').focus(); }, 30);
        return $row;
    }

    $('#taof-btn-add-item').on('click', adicionarLinha);

    function initRow($row) {
        $row.on('input change', '.taof-orc-dose, .taof-orc-dose-unit', function () { calcularLinha($row); });
        $row.on('click', '.taof-btn-del-item', function () { if (!_loadingEdit) _valorFinalTravado = null; $row.remove(); calcularTotais(); });
        // Enter/Tab na dose: QSP → cria nova linha; demais → navega entre linhas
        $row.on('keydown', '.taof-orc-dose', function (e) {
            if (e.key !== 'Enter' && !(e.key === 'Tab' && !e.shiftKey)) return;
            if ($row.hasClass('taof-row-qsp')) {
                e.preventDefault();
                adicionarLinha();
                return;
            }
            var $nonQsp = $('#taof-itens-body .taof-item-row:not(.taof-row-qsp)');
            var idx = $nonQsp.index($row);
            if (idx < $nonQsp.length - 1) {
                e.preventDefault();
                $nonQsp.eq(idx + 1).find('.taof-orc-ativo-search').focus();
            } else {
                var $qsp = $('#taof-itens-body .taof-item-row.taof-row-qsp');
                if ($qsp.length) { e.preventDefault(); $qsp.find('.taof-orc-dose').focus(); }
            }
        });
        initAtivoAC($row);
    }

    // ── Info excipiente base para cápsulas sem QSP ───────────────────
    function atualizarInfoExcipiente() {
        var $info = $('#taof-info-excipiente');
        if (!$info.length) return;
        var isCap = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        var temQsp = $('#taof-itens-body .taof-item-row.taof-row-qsp').length > 0;
        if (!isCap || temQsp) { $info.hide(); return; }

        var r = calcularCapsulaIdeal(getNPerDoseForced());
        if (!r) { $info.hide(); return; }

        var ftench   = formaAtual.ftenchcap || 1;
        var sumVOLAPA = 0;
        $('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').each(function () {
            sumVOLAPA += parseFloat($(this).data('volapa-ul')) || 0;
        });
        var availPerDose = r.cap.vol_ul * r.nPerDose * ftench;
        var restante_uL  = Math.max(0, availPerDose - sumVOLAPA);
        if (restante_uL <= 0) { $info.hide(); return; }

        // Converte µL → mg usando densidade 1 g/mL (approx)
        var restante_mg_dose  = restante_uL;                    // ρ≈1: 1µL ≈ 1mg
        var restante_mg_total = restante_mg_dose * getVol() * getPotes();

        $info.html(
            '<span style="color:#0369a1">⚠ Excipiente base (10577): <strong>~' + fmt(restante_mg_total, 0) + ' mg</strong>' +
            ' (' + fmt(restante_uL, 1) + ' µL/dose disponível)</span>' +
            ' <em style="color:#94a3b8;font-size:11px">— adicione o excipiente manualmente para calcular o custo</em>'
        ).show();
    }

    // ── Helper: posiciona dropdown em position:fixed ──────────────────
    // Necessário porque overflow-x:auto no wrapper recorta position:absolute
    function positionDropdown($inp, $dd) {
        var r = $inp[0].getBoundingClientRect();
        $dd.css({ top: r.bottom + 2, left: r.left, width: r.width });
    }

    // ── selecionarAtivo — partilhado por AC e auto-excipiente ────────
    // origName: texto original digitado (nome da prescrição) — preservado mesmo após troca de ativo
    function selecionarAtivo($row, a, origName) {
        var $s = $row.find('.taof-orc-ativo-search');
        $s.val(a.nome).css({ 'border-color': '', 'background-color': '' });
        $s.removeAttr('placeholder');
        $s.attr('placeholder', 'Buscar ativo...');
        $row.find('.taof-orc-ativo-id').val(a.id);
        $row.find('.taof-orc-cod').text(a.codigo_fc || '—');
        $row.find('.taof-orc-fp-label').text(
            parseFloat(a.fator_perda || 1).toLocaleString('pt-BR', { minimumFractionDigits: 3 })
        );
        // Preserva nome_prescricao: se já havia um (de importação), mantém; senão usa origName
        var prescAtual = $row.data('nome-prescricao');
        var novaPresc  = prescAtual || (origName ? origName.toUpperCase() : a.nome);
        $row.data({
            'ativo-id':         a.id,
            'ativo-nome':       a.nome,
            'nome-prescricao':  novaPresc,
            'codigo-fc':        a.codigo_fc   || '',
            'unid-padrao':      a.unidade_padrao,
            'preco-compra':     parseFloat(a.preco_compra)   || 0,
            'custo-unit':       a.custo_por_unidade,
            'venda-unit':       a.preco_venda,
            'diluicao':         parseFloat(a.diluicao)      || 1,
            'teor':             parseFloat(a.teor)           || 100,
            'densidade':        parseFloat(a.densidade)      || 1,
            'fp':               parseFloat(a.fator_perda)    || 1,
            'concentracao':     parseFloat(a.concentracao)   || 0,
        });
        var vendaLabel = parseFloat(a.preco_venda) > 0
            ? 'R$ ' + fmt(a.preco_venda, 4) + '/' + (a.unidade_padrao || 'g')
            : '—';
        $row.find('.taof-orc-preco-venda').text(vendaLabel);
        var u = a.unidade_padrao;
        if (['mg', 'mcg', 'g', 'UI', 'UFC', 'BLH', 'ml'].indexOf(u) === -1) u = 'mg';
        if (u === 'g') u = 'mg';
        if (u === 'UFC' || u === 'BLH') u = 'BLH';
        $row.find('.taof-orc-dose-unit').val(u);
        $row.find('.taof-ac-dropdown').hide().empty();
        calcularLinha($row);
    }

    // ── Autocomplete — Ativos ─────────────────────────────────────────
    function initAtivoAC($row) {
        var $inp   = $row.find('.taof-orc-ativo-search');
        var $dd    = $row.find('.taof-ac-dropdown');
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
                                var $item = $('<div class="taof-ac-item" data-sel="1">').html(
                                    '<span>' + $('<span>').text(a.nome).html() + '</span>' +
                                    '<small>' + $('<span>').text(info).html() + '</small>'
                                );
                                $item.data('ativo-obj', a).on('mousedown', function (e) {
                                    e.preventDefault();
                                    var origName = $inp.val().trim();
                                    var wasEmpty = !$row.find('.taof-orc-ativo-id').val();
                                    // Salva o NOME ORIGINAL do ingrediente (como escrito na fórmula), não o texto buscado
                                    var sinNome  = ($row.data('nome-prescricao') || origName || '').toString().trim().toUpperCase();
                                    selecionarAtivo($row, a, origName);
                                    if (wasEmpty && sinNome && sinNome !== a.nome.toUpperCase()) {
                                        $.post(ajaxUrl, { action: 'tao_formula_salvar_sinonimo', nonce: nonce, ativo_id: a.id, sinonimo: sinNome }, function () {
                                            taofToast('✓ Sinônimo salvo: "' + sinNome + '" → ' + a.nome);
                                        });
                                    }
                                });
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

        // Navegação por teclado no dropdown
        $inp.on('keydown', function (e) {
            if (!$dd.is(':visible')) {
                // ArrowDown reabre a busca quando dropdown está fechado
                if (e.key === 'ArrowDown' && $(this).val().trim().length >= 2) {
                    e.preventDefault();
                    $(this).trigger('input');
                }
                return;
            }
            var $items = $dd.find('.taof-ac-item[data-sel]');
            var $cur   = $items.filter('.taof-ac-hl');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                $items.removeClass('taof-ac-hl');
                var $nxt = $cur.length ? $cur.nextAll('[data-sel]').first() : $();
                ($nxt.length ? $nxt : $items.first()).addClass('taof-ac-hl');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                $items.removeClass('taof-ac-hl');
                var $prv = $cur.length ? $cur.prevAll('[data-sel]').first() : $();
                ($prv.length ? $prv : $items.last()).addClass('taof-ac-hl');
            } else if (e.key === 'Enter') {
                var $sel = $items.filter('.taof-ac-hl');
                if ($sel.length) {
                    e.preventDefault();
                    var aObj2 = $sel.data('ativo-obj');
                    var origName2 = $inp.val().trim();
                    var wasEmpty2 = !$row.find('.taof-orc-ativo-id').val();
                    var sinNome2  = ($row.data('nome-prescricao') || origName2 || '').toString().trim().toUpperCase();
                    selecionarAtivo($row, aObj2, origName2);
                    if (wasEmpty2 && sinNome2 && sinNome2 !== aObj2.nome.toUpperCase()) {
                        $.post(ajaxUrl, { action: 'tao_formula_salvar_sinonimo', nonce: nonce, ativo_id: aObj2.id, sinonimo: sinNome2 }, function () {
                            taofToast('✓ Sinônimo salvo: "' + sinNome2 + '" → ' + aObj2.nome);
                        });
                    }
                }
            } else if (e.key === 'Escape') {
                $dd.hide().empty();
            }
        });

        // blur esvazia o dropdown para não reaparecer no próximo focus
        $inp.on('blur',  function () { setTimeout(function () { $dd.hide().empty(); }, 150); });
    }

    // ── Embalagens ────────────────────────────────────────────────────
    function calcularEmb($row) {
        if (!_loadingEdit) _valorFinalTravado = null;
        var subtotal = (parseInt($row.find('.taof-emb-qty').val()) || 0) *
                       (parseFloat($row.data('custo-unit')) || 0);
        $row.find('.taof-emb-subtotal').text('R$ ' + fmt(subtotal));
        $row.data('subtotal-emb', subtotal);
        calcularTotais();
    }

    function initEmbRow($row) {
        $row.on('input', '.taof-emb-qty', function () { calcularEmb($row); });
        $row.on('click', '.taof-btn-del-emb', function () { if (!_loadingEdit) _valorFinalTravado = null; $row.remove(); calcularTotais(); });
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
                    var lista = (resp && Array.isArray(resp.data)) ? resp.data : [];
                    if (!lista.length) {
                        $dd.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhuma embalagem encontrada.</div>');
                    } else {
                        $.each(lista, function (_, a) {
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
                }).fail(function(jqXHR) {
                    console.error('[TAO Fórmula] embalagem search fail:', jqXHR.responseText && jqXHR.responseText.slice(0,300));
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

    // ── Sugestão automática de embalagem ─────────────────────────────
    function preencherEmbRow($row, a) {
        $row.find('.taof-emb-search').val(a.nome);
        $row.find('.taof-emb-id').val(a.id);
        $row.data({ 'emb-id': a.id, 'emb-nome': a.nome, 'custo-unit': a.custo_por_unidade || 0 });
        $row.find('.taof-emb-custo-label').text('R$ ' + fmt(a.custo_por_unidade || 0, 4) + '/un');
        calcularEmb($row);
    }

    function sugerirEmbalagem() {
        if (!formaAtual) return;
        if ($('#taof-emb-body .taof-emb-row').length > 0) return; // não sobrescreve existente

        var tipo = formaAtual.tipo;
        var vol  = getVol();

        var embs = (window.taofEmbalagens || []).filter(function (e) {
            return e.tipos.indexOf(tipo) !== -1;
        });
        if (!embs.length) return;

        var melhor;
        if (tipo === 'cap' || tipo === 'duo_cap') {
            // Para cápsulas: sugerir pote com volume >= qtde_caps × 0,35ml (aprox. vol cap #0)
            var totalCaps = vol * getPotes();
            var volEstimado = totalCaps * 0.35; // ~0.35ml por cápsula #0 (estimativa conservadora)
            embs.sort(function (a, b) { return a.vol - b.vol; });
            melhor = embs.filter(function (e) { return e.vol >= volEstimado; })[0] || embs[embs.length - 1];
        } else if (tipo === 'envelope') {
            // Envelope: a embalagem é o sachê laminado da capacidade escolhida (5 g / 15 g)
            var cap = parseFloat($('#taof-forma-tipo').val()) || 5;
            melhor = embs.filter(function (e) { return e.vol === cap; })[0] || embs[0];
        } else {
            // Menor embalagem que comporta o volume
            embs.sort(function (a, b) { return a.vol - b.vol; });
            melhor = embs.filter(function (e) { return e.vol >= vol; })[0] || embs[embs.length - 1];
        }
        if (!melhor) return;

        // Adiciona linha e busca no cadastro de ativos pelo código FC
        $('#taof-btn-add-emb').trigger('click');
        var $row = $('#taof-emb-body .taof-emb-row').last();
        $row.data('auto-emb', true);   // marca como sugestão automática (substituível ao trocar forma/capacidade)
        $row.find('.taof-emb-search').val('⏳ ' + melhor.nome + '…');

        $.getJSON(ajaxUrl, {
            action: 'tao_formula_search_ativos',
            nonce:  nonce,
            q:      String(melhor.codigo),
            grupo:  ''
        }, function (resp) {
            var lista = (resp && Array.isArray(resp.data)) ? resp.data : [];
            var ativo = null;
            // Prefere correspondência exata de codigo_fc
            for (var i = 0; i < lista.length; i++) {
                if (String(lista[i].codigo_fc) === String(melhor.codigo)) { ativo = lista[i]; break; }
            }
            if (!ativo && lista.length) ativo = lista[0];

            if (ativo) {
                preencherEmbRow($row, ativo);
            } else {
                // Produto não cadastrado: preenche só o nome como referência
                $row.find('.taof-emb-search').val(melhor.nome + ' (sem cadastro)');
                $row.find('.taof-emb-custo-label').text('Cód. ' + melhor.codigo + ' — cadastre o ativo');
            }
        }).fail(function () {
            $row.find('.taof-emb-search').val(melhor.nome);
        });
    }

    // Aciona sugestão quando forma + volume estão preenchidos
    $('#taof-forma-vol').on('change blur', function () {
        setTimeout(sugerirEmbalagem, 100);
    });
    $('#taof-forma-sel').on('change', function () {
        // Remove sugestão automática anterior (linha vazia/sem id ou marcada como auto)
        $('#taof-emb-body .taof-emb-row').each(function () {
            var $r = $(this);
            if (!$r.data('emb-id') || $r.data('auto-emb')) $r.remove();
        });
        setTimeout(sugerirEmbalagem, 200);
    });
    // Envelope: trocar a capacidade (Tipo) re-sugere o sachê correspondente
    $('#taof-forma-tipo').on('change', function () {
        if (!formaAtual || formaAtual.tipo !== 'envelope') return;
        $('#taof-emb-body .taof-emb-row').each(function () {
            if ($(this).data('auto-emb')) $(this).remove();
        });
        setTimeout(sugerirEmbalagem, 50);
    });

    // ── Salvar ────────────────────────────────────────────────────────
    $('#taof-orc-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#taof-orc-salvar'), $sp = $('.taof-spinner'), $msg = $('.taof-msg');
        var itens = [], ok = true;

        // Orçamentos importados por texto: dispensa forma obrigatória e itens sem ativo_id
        var isTextoImport = !!(EDIT_DATA && EDIT_DATA.tipo_entrada === 'texto');
        if (!formaAtual && !isTextoImport) { alert('Selecione a forma farmaceutica.'); return; }
        if (getVol() <= 0 && !isTextoImport) { alert('Informe o volume / quantidade.'); return; }

        var cap = (formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap'))
                  ? calcularCapsulaIdeal() : null;

        $('#taof-itens-body .taof-item-row').each(function () {
            var $r    = $(this);
            // Para ORCs importados por texto: permite salvar mesmo sem ativo_id associado
            if (!$r.data('ativo-id') && !isTextoImport) { ok = false; return false; }
            var isQsp = $r.hasClass('taof-row-qsp');
            itens.push({
                tipo:             'mp',
                ativo_id:         $r.data('ativo-id'),
                nome:             $r.data('ativo-nome'),
                nome_prescricao:  $r.data('nome-prescricao') || $r.data('ativo-nome') || '',
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
                unid_padrao:       $r.data('unid-padrao') || ($r.hasClass('taof-row-qsp') ? 'g' : 'mg'),
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
        if (!itens.length && !isTextoImport) { alert('Adicione ao menos um ativo.'); return; }

        var calculado = itens.reduce(function (s, i) { return s + (i.subtotal || 0); }, 0);
        var custoFixo = getCustoFixo();
        var subtotal  = calculado + custoFixo;
        var acrescVal = parseFloat($('#taof-acrescimo-val-inp').val()) || 0;
        var desctVal  = parseFloat($('#taof-desconto-val-inp').val())  || 0;
        var acrescPct = parseFloat($('#taof-acrescimo-pct').val()) || 0;
        var desctPct  = parseFloat($('#taof-desconto-pct').val())  || 0;
        var final     = subtotal + acrescVal - desctVal;

        $btn.prop('disabled', true);
        $sp.css('visibility', 'visible');
        $msg.hide();

        var saveAction = EDIT_ORC_ID ? 'tao_formula_update_orcamento' : 'tao_formula_save_orcamento';
        var postData = {
            action:          saveAction,
            nonce:           nonce,
            orc_id:          EDIT_ORC_ID || '',
            card_id:         window.taofCardId || '',
            nome_paciente:   $('#taof-nome-paciente').val(),
            whatsapp:        $('#taof-whatsapp').val(),
            forma_id:        $('#taof-forma-sel').val() || '',
            forma_nome:      formaAtual ? formaAtual.nome : (EDIT_DATA ? (EDIT_DATA.forma_nome || '') : ''),
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
        };

        $.post(ajaxUrl, postData, function (resp) {
            $sp.css('visibility', 'hidden');
            $btn.prop('disabled', false);
            if (resp.success) {
                if (IS_MODAL) {
                    window.parent.postMessage({
                        taofSaved: true,
                        orcId:     resp.data.id,
                        numero:    resp.data.numero,
                        isEdit:    !! EDIT_ORC_ID
                    }, '*');
                } else {
                    window.location.href = window.taofOrcListUrl;
                }
            } else {
                $msg.text('Erro: ' + (resp.data || 'desconhecido')).addClass('err').show();
            }
        }).fail(function (xhr) {
            $sp.css('visibility', 'hidden');
            $btn.prop('disabled', false);
            var det = xhr && xhr.status ? 'HTTP ' + xhr.status : 'sem resposta';
            var body = xhr && xhr.responseText ? xhr.responseText.substring(0, 120) : '';
            $msg.text('Falha na requisição (' + det + ')' + (body ? ': ' + body : '')).addClass('err').show();
        });
    });

    // ── Análise de Preços ─────────────────────────────────────────────
    var _analiseCompraTotal = 0; // para simulação

    function renderLinha(nome, unid, precoComp, precoCusto, precoVenda, subtotalVenda, isTotal, isSep) {
        if (isSep) return '<tr><td colspan="7" style="padding:4px 16px;background:#f8fafc;font-size:11px;color:#64748b;font-weight:600">' + nome + '</td></tr>';
        var baseV     = isTotal ? subtotalVenda : precoVenda;   // no TOTAL a margem é (venda total ÷ custo total)
        var divisor   = precoCusto > 0 ? precoCusto : precoComp; // custo; se não houver, usa o preço de compra
        var margem    = divisor > 0 ? baseV / divisor : null;
        var margBruta = divisor > 0 ? baseV - divisor : null;
        var mTxt = margem !== null
            ? fmt(margem, 2) + 'x <small>(' + fmt((margem - 1) * 100, 1) + '%)</small>'
            : '<span style="color:#94a3b8">—</span>';
        var bTxt, bStyle = '';
        if (margBruta !== null) {
            bTxt   = 'R$&nbsp;' + fmt(margBruta, 4);
            bStyle = margBruta < 0 ? 'color:#dc2626' : (margBruta > 0 ? 'color:#16a34a' : '');
        } else { bTxt = '<span style="color:#94a3b8">—</span>'; }
        var boldSt = isTotal ? 'font-weight:700;background:#f8fafc;border-top:2px solid #e2e8f0' : '';
        return '<tr style="' + boldSt + '">' +
            '<td>' + $('<span>').text(nome).html() + '</td>' +
            '<td>' + (precoComp  > 0 ? 'R$&nbsp;' + fmt(precoComp,  4) + (unid ? '/' + unid : '') : '<span style="color:#94a3b8">—</span>') + '</td>' +
            '<td>' + (precoCusto > 0 ? 'R$&nbsp;' + fmt(precoCusto, 4) + (unid ? '/' + unid : '') : '<span style="color:#94a3b8">—</span>') + '</td>' +
            '<td>' + (precoVenda > 0 ? 'R$&nbsp;' + fmt(precoVenda, 4) + (unid ? '/' + unid : '') : '<span style="color:#94a3b8">—</span>') + '</td>' +
            '<td>' + (subtotalVenda > 0 ? 'R$&nbsp;' + fmt(subtotalVenda) : '<span style="color:#94a3b8">—</span>') + '</td>' +
            '<td>' + mTxt + '</td>' +
            '<td style="' + bStyle + '">' + bTxt + '</td>' +
            '</tr>';
    }

    function abrirAnalise() {
        var rows = '', totalCompra = 0, totalCusto = 0, totalVenda = 0;
        var temItemSemCompra = false;

        // ── MPs ───────────────────────────────────────────────────────
        var mpLinhas = [];
        $('#taof-itens-body .taof-item-row').each(function () {
            var $r = $(this);
            if (!$r.data('ativo-id')) return;
            var nome       = $r.data('ativo-nome') || '—';
            var unid       = $r.data('unid-padrao') || 'g';
            var precoComp  = parseFloat($r.data('preco-compra')) || 0;
            var precoCusto = parseFloat($r.data('custo-unit'))   || 0;
            var precoVenda = parseFloat($r.data('venda-unit'))   || 0;
            var subtotalV  = parseFloat($r.data('subtotal'))     || 0;
            // qtd robusto: deriva do subtotal de venda (vale também p/ orçamentos importados sem qtd-em-padrao)
            var qtd        = precoVenda > 0 ? ( subtotalV / precoVenda ) : ( parseFloat($r.data('qtd-em-padrao')) || 0 );
            var subtotalC  = precoComp > 0 ? qtd * precoComp : 0;
            var custoBase  = precoCusto > 0 ? precoCusto : precoComp;   // custo; se não houver, compra
            if (custoBase <= 0) temItemSemCompra = true;   // sem custo E sem compra
            totalCompra += subtotalC;
            totalCusto  += qtd * custoBase;
            totalVenda  += subtotalV;
            mpLinhas.push({ nome: nome, unid: unid, precoComp: precoComp, precoCusto: precoCusto, precoVenda: precoVenda, subtotalV: subtotalV });
        });

        if (!mpLinhas.length) { alert('Adicione ativos para analisar os preços.'); return; }

        rows += renderLinha('Matérias-Primas', '', 0, 0, 0, 0, false, true);
        mpLinhas.forEach(function (l) {
            rows += renderLinha(l.nome, l.unid, l.precoComp, l.precoCusto, l.precoVenda, l.subtotalV, false, false);
        });

        // ── Cápsulas ──────────────────────────────────────────────────
        var capR = (formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap'))
                   ? calcularCapsulaIdeal(getNPerDoseForced()) : null;
        if (capR) {
            var totalCaps = getVol() * getPotes() * capR.nPerDose;
            var capVenda  = capR.cap.venda_unit || 0;
            var capSub    = totalCaps * capVenda;
            totalVenda   += capSub;
            totalCusto   += (capR.custoCapsula || 0);
            var capNome   = capR.cap.tipo.charAt(0).toUpperCase() + capR.cap.tipo.slice(1).toLowerCase() +
                            ' Nº' + capR.cap.numero + ' (' + totalCaps + ' un)';
            rows += renderLinha('Embalagens & Cápsulas', '', 0, 0, 0, 0, false, true);
            rows += renderLinha(capNome, 'un', 0, 0, capVenda, capSub, false, false);
        }

        // ── Embalagens ────────────────────────────────────────────────
        var embHdr = !capR;
        $('#taof-emb-body .taof-emb-row').each(function () {
            var $r = $(this);
            if (!$r.data('emb-id')) return;
            if (embHdr) { rows += renderLinha('Embalagens', '', 0, 0, 0, 0, false, true); embHdr = false; }
            var nome  = $r.data('emb-nome') || '—';
            var custo = parseFloat($r.data('custo-unit')) || 0;
            var qty   = parseInt($r.find('.taof-emb-qty').val()) || 0;
            var sub   = parseFloat($r.data('subtotal-emb')) || 0;
            totalVenda += sub;
            totalCusto += sub;   // embalagem é vendida ao custo
            rows += renderLinha(nome, 'un', 0, custo, custo, sub, false, false);
        });

        // Custo fixo + valor final real
        var custoFixoReal = getCustoFixo();
        var acrescPct = parseFloat($('#taof-acrescimo-pct').val()) || 0;
        var desctPct  = parseFloat($('#taof-desconto-pct').val())  || 0;
        var subtotalBase = totalVenda + custoFixoReal + (capR ? (capR.custoCapsula || 0) : 0);
        // Pega o valor final já calculado da tela
        var valorFinalTxt = $('#taof-res-final strong').text().replace('R$', '').replace(/\./g,'').replace(',','.').trim();
        var valorFinal = parseFloat(valorFinalTxt) || 0;

        // Reconciliação: diferença entre a venda dos itens e o valor final (custo fixo + acréscimo − desconto)
        var _ajuste = valorFinal - totalVenda;
        if (Math.abs(_ajuste) >= 0.01) {
            rows += renderLinha('Custo fixo + acréscimo − desconto', '', 0, 0, 0, _ajuste, false, false);
        }

        // ── Total ────────────────────────────────────────────────────
        rows += renderLinha('TOTAL', '', totalCompra > 0 ? totalCompra : 0, totalCusto, 0, valorFinal, true, false);

        _analiseCompraTotal = totalCusto;   // simulação de margem é sobre o CUSTO

        $('#taof-analise-body').html(rows);

        // Simulação (margem = valor final ÷ custo total)
        var margemAtual = totalCusto > 0 ? valorFinal / totalCusto : 0;
        $('#taof-sim-margem-atual').text(fmt(margemAtual, 2) + 'x (' + fmt(margemAtual * 100, 0) + '%)');
        $('#taof-sim-range').val(margemAtual.toFixed(2));
        $('#taof-sim-inp').val(margemAtual.toFixed(2));
        simularMargem(margemAtual);

        if (temItemSemCompra) {
            $('#taof-sim-aviso').show();
        } else {
            $('#taof-sim-aviso').hide();
        }

        $('#taof-modal-analise').show();
        document.body.style.overflow = 'hidden';
    }

    function simularMargem(mult) {
        if (!_analiseCompraTotal) { $('#taof-sim-novo').text('—'); return; }
        var novo = _analiseCompraTotal * mult;
        var cor  = mult < 1 ? '#dc2626' : (mult >= 2 ? '#16a34a' : '#d97706');
        $('#taof-sim-novo').html('<strong style="color:' + cor + '">R$&nbsp;' + fmt(novo) + '</strong>');
    }

    $(document).on('input', '#taof-sim-range', function () {
        var v = parseFloat($(this).val());
        $('#taof-sim-inp').val(v.toFixed(2));
        simularMargem(v);
    });
    $(document).on('input', '#taof-sim-inp', function () {
        var v = Math.max(0.01, parseFloat($(this).val()) || 0);
        $('#taof-sim-range').val(v);
        simularMargem(v);
    });

    // Aplica a margem simulada ao valor final do orçamento (final = custo × margem), via acréscimo
    function aplicarMargem() {
        if (!_analiseCompraTotal) { alert('Sem custo total para aplicar margem.'); return; }
        var mult = Math.max(0.01, parseFloat($('#taof-sim-inp').val()) || 0);
        var alvo = _analiseCompraTotal * mult;
        var sub  = window._taofSubtotal || 0;
        var desc = parseFloat($('#taof-desconto-val-inp').val()) || 0;
        $('#taof-desconto-val-inp').data('manual', true);     // congela o desconto atual (R$)
        var acr = alvo + desc - sub;                          // final = sub + acr − desc = alvo
        $('#taof-acrescimo-val-inp').val(acr.toFixed(2)).data('manual', true);
        calcularTotais();
        $('#taof-modal-analise').hide(); document.body.style.overflow = '';
        if (typeof taofToast === 'function') taofToast('✓ Margem ' + fmt(mult, 2) + 'x aplicada — valor final R$ ' + fmt(alvo));
    }
    $(document).on('click', '#taof-sim-aplicar', aplicarMargem);

    $('#taof-btn-analise').on('click', abrirAnalise);

    $('#taof-modal-analise').on('click', '#taof-analise-fechar, .taof-analise-overlay', function () {
        $('#taof-modal-analise').hide();
        document.body.style.overflow = '';
    });

    // ── Botão fechar modal ────────────────────────────────────────────
    $('#taof-modal-close-btn').on('click', function () {
        window.parent.postMessage({ taofClosed: true }, '*');
    });

    // ── Modo edição: carregar dados existentes ────────────────────────
    // Cancelar no modal fecha sem navegar
    $('#taof-cancel-btn').on('click', function () {
        window.parent.postMessage({ taofClosed: true }, '*');
    });

    function loadEditData(data) {
        if (!data) return;
        _loadingEdit = true;
        _forcedCapId = null;   // começa em Automático ao abrir um orçamento
        // Importado do FCerta: VALOR FINAL e DESCONTO travados (valores duráveis do FC)
        _valorFinalTravado     = (parseFloat(data.valor_final_fc) > 0) ? parseFloat(data.valor_final_fc) : null;
        window._taofFcFinal    = _valorFinalTravado;
        window._taofFcDesconto = (data.desconto_fc !== undefined && data.desconto_fc !== null) ? parseFloat(data.desconto_fc) : null;

        // Paciente
        $('#taof-nome-paciente').val(data.nome_paciente || '');
        $('#taof-whatsapp').val(data.whatsapp || '');

        // Forma farmacêutica
        if (data.forma_id && formasMap[data.forma_id]) {
            $('#taof-forma-sel').val(data.forma_id).trigger('change');
            // Aguarda o change popular unidade, então define os valores
            setTimeout(function () {
                if (data.forma_vol)      $('#taof-forma-vol').val(data.forma_vol).trigger('input');
                if (data.forma_tipo)     $('#taof-forma-tipo').val(data.forma_tipo);   // restaura Tipo Cápsula / Capacidade do Envelope
                if (data.forma_unidade)  $('#taof-forma-unidade').val(data.forma_unidade);
                if (data.qtde_potes)     $('#taof-qtde-potes').val(data.qtde_potes).trigger('input');
            }, 50);
        }

        // Itens MP e Embalagem
        var itens = data.itens || [];
        $('#taof-itens-body').empty();

        itens.forEach(function (item) {
            if (item.tipo !== 'mp') return;
            var $row = adicionarLinha();

            var $srch = $row.find('.taof-orc-ativo-search');
            $srch.val(item.nome || '');
            $row.find('.taof-orc-ativo-id').val(item.ativo_id || '');
            $row.find('.taof-orc-cod').text(item.codigo_fc || '—');

            // Marcar visualmente itens sem ativo associado
            if (!item.ativo_id && item.nome) {
                $srch.css({ 'border-color': '#f97316', 'background-color': '#fff7ed' })
                     .attr('placeholder', '⚠ Não associado — busque o ativo');
                // Ao focar pela 1ª vez: dispara busca automática com o nome da receita
                $srch.one('focus', function() { $(this).trigger('input'); });
            }
            $row.find('.taof-orc-fp-label').text(
                parseFloat(item.fp || 1).toLocaleString('pt-BR', { minimumFractionDigits: 3 })
            );
            var vendaUnit = parseFloat(item.preco_venda) || 0;
            // Para QSP: se unid_padrao foi salvo como 'mg' por fallback errado mas
            // o subtotal salvo é consistente com cálculo em 'g', corrige para 'g'
            var unidPadrao = item.unid_padrao || (item.is_qsp ? 'g' : (item.qtd_unit || 'mg'));
            if (item.is_qsp && unidPadrao === 'mg' && item.preco_venda > 0 && item.qtd_total_g > 0 && item.subtotal > 0) {
                var subComG = item.qtd_total_g * parseFloat(item.preco_venda);
                if (Math.abs(item.subtotal - subComG) / item.subtotal < 0.2) unidPadrao = 'g';
            }
            $row.find('.taof-orc-preco-venda').text(
                vendaUnit > 0 ? 'R$ ' + fmt(vendaUnit, 4) + '/' + unidPadrao : '—'
            );
            $row.data({
                'ativo-id':        item.ativo_id || '',
                'ativo-nome':      item.nome || '',
                'nome-prescricao': item.nome_prescricao || item.nome || '',
                'codigo-fc':       item.codigo_fc || '',
                'unid-padrao':     unidPadrao,
                'custo-unit':      parseFloat(item.custo_por_unidade || 0),
                'venda-unit':      vendaUnit,
                'diluicao':        parseFloat(item.diluicao || 1),
                'teor':            parseFloat(item.teor || 100),
                'densidade':       1,
                'fp':              parseFloat(item.fp || 1),
            });
            $row.find('.taof-orc-dose-unit').val(item.dose_unit || 'mg');

            if (item.is_qsp) {
                toggleQSP($row, true);
                // Restaura subtotal salvo para o primeiro calcularTotais não recalcular via atualizarQSPRow
                var sub = parseFloat(item.subtotal || 0);
                var qtdG = parseFloat(item.qtd_total_g || 0);
                $row.data({ subtotal: sub, 'qtd-total-g': qtdG });
                $row.find('.taof-orc-qtd-total').text(fmt(qtdG * 1000, 2) + ' mg (QSP)');
                $row.find('.taof-orc-subtotal').text('R$ ' + fmt(sub));
            } else {
                $row.find('.taof-orc-dose').val(item.dose || 0);
                var sub = parseFloat(item.subtotal || 0);
                var qtdG = parseFloat(item.qtd_total_g || 0);
                $row.data({ subtotal: sub, 'qtd-total-g': qtdG, 'volapa-ul': parseFloat(item.volapa_ul || 0),
                            dose: parseFloat(item.dose || 0), 'dose-unit': item.dose_unit || 'mg' });
                $row.find('.taof-orc-qtd-total').text(fmt(qtdG * 1000, 2) + ' mg');
                $row.find('.taof-orc-subtotal').text('R$ ' + fmt(sub));
            }
        });

        // Embalagens
        itens.forEach(function (item) {
            if (item.tipo !== 'emb') return;
            var frag = document.getElementById('taof-emb-tpl').content.cloneNode(true);
            $('#taof-emb-body').append(frag);
            var $row = $('#taof-emb-body .taof-emb-row').last();
            $row.data({ 'emb-id': item.ativo_id || '', 'emb-nome': item.nome || '',
                        'custo-unit': parseFloat(item.custo_por_unidade || 0),
                        'subtotal-emb': parseFloat(item.subtotal || 0) });
            $row.find('.taof-emb-search').val(item.nome || '');
            $row.find('.taof-emb-id').val(item.ativo_id || '');
            $row.find('.taof-emb-qty').val(item.quantidade || 1);
            $row.find('.taof-emb-custo-label').text('R$ ' + fmt(item.custo_por_unidade, 4) + '/un');
            $row.find('.taof-emb-subtotal').text('R$ ' + fmt(item.subtotal, 2));
            initEmbRow($row);
        });

        // Custo fixo / acréscimo / desconto
        if (data.custo_fixo_aplicado !== undefined && data.custo_fixo_aplicado !== null) {
            $('#taof-custo-fixo-inp').val(parseFloat(data.custo_fixo_aplicado).toFixed(2)).data('manual', true);
        }
        if (data.margem_aplicada) {
            $('#taof-acrescimo-pct').val(parseFloat(data.margem_aplicada).toFixed(1));
            $('#taof-acrescimo-val-inp').removeData('manual');
        }
        if (window._taofFcDesconto != null) {
            // Importado do FC: desconto fixo em R$ (não flutua com o sub-total)
            $('#taof-desconto-val-inp').val(window._taofFcDesconto.toFixed(2)).data('manual', true);
        } else if (data.desconto_pct) {
            $('#taof-desconto-pct').val(parseFloat(data.desconto_pct).toFixed(1));
            $('#taof-desconto-val-inp').removeData('manual');
        }

        // Obs
        $('#taof-observacoes').val(data.observacoes || '');

        // Orçamentos importados por texto SEM itens: exibir total_orcamento como custo fixo manual
        // (para ORCs com itens, o calc engine usa os subtotais normalmente)
        if (data.tipo_entrada === 'texto' && parseFloat(data.total_orcamento || 0) > 0 &&
                (!data.itens || !data.itens.length)) {
            $('#taof-custo-fixo-inp').val(parseFloat(data.total_orcamento).toFixed(2)).data('manual', true);
        }

        setTimeout(function () {
            calcularTotais();   // _loadingEdit ainda true → atualizarQSPRow pula; usa subtotal salvo
            _loadingEdit = false;
            // Foca no 1º ativo não associado (ativo_id vazio mas tem nome)
            var $primeiro = $('#taof-itens-body .taof-item-row').filter(function() {
                return !$(this).find('.taof-orc-ativo-id').val() &&
                        $(this).find('.taof-orc-ativo-search').val();
            }).first().find('.taof-orc-ativo-search');
            if ($primeiro.length) $primeiro.focus();
        }, 200);
    }

    if (EDIT_DATA) {
        loadEditData(EDIT_DATA);
    }

})(jQuery);
