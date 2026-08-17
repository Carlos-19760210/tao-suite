/* TAO Formulas — Orcamento v2.0 */
(function ($) {
    'use strict';
    if (!document.getElementById('taof-orc-form')) return;

    var formasMap   = window.taofOrcFormasMap || {};
    var capsulas    = window.taofCapsulas || [];
    var formaAtual  = null;
    var _subInfo    = null;   // sublingual: {n: comprimidos/dose, tam: tamanho escolhido}
    var ajaxUrl     = (typeof taoFormula !== 'undefined') ? taoFormula.ajaxUrl : '/wp-admin/admin-ajax.php';
    var nonce       = (typeof taoFormula !== 'undefined') ? taoFormula.nonce   : '';
    // Motor farmacotécnico v2 (option tao_formula_motor_v2): equivalência do sinônimo,
    // alerta de dose máxima, trava de restrição e teor real do lote. OFF = cálculo idêntico ao atual.
    var MOTOR_ON    = !!((typeof taoFormula !== 'undefined') && taoFormula.motorOn);

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

    // Forma "dosada por volume" (modelo FCerta): tudo que não é cápsula/envelope/sublingual —
    // gel, pomada, creme, solução, gotas, "outras"... O campo Vol/dose só tem efeito quando
    // preenchido (>0); vazio mantém o comportamento antigo (forma_vol = nº de doses).
    var NAO_LIQUID_TIPOS = ['cap', 'duo_cap', 'envelope', 'sublingual'];
    function isLiquidForm() { return formaAtual && NAO_LIQUID_TIPOS.indexOf(formaAtual.tipo) === -1; }

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

    // ── Coluna "Tipo" do Sublingual: apenas identifica a forma (o TAMANHO do comprimido
    //    vive na área de embalagem, em #taof-sub-tam). ─────────────────────────────
    function popularTipoSublingual() {
        var $sel = $('#taof-forma-tipo');
        $sel.empty().append($('<option>').val('sublingual').text('Sublingual'));
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
        } else if (formaTipo === 'sublingual') {
            opts = [{ val: 'un', lbl: 'doses' }];
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

    // ── Alerta de dose máxima (motor v2) ──────────────────────────────
    // Compara a dose prescrita com dose_max_dia (Zanini) ou dose_max do cadastro.
    // Só unidades de massa; alerta visual, NÃO bloqueia (posologia pode dividir a dose).
    var _massaMg = { mg: 1, g: 1000, mcg: 0.001 };
    function alertaDoseMax($row, dose, doseUnit) {
        if (!MOTOR_ON) return;
        var $dose = $row.find('.taof-orc-dose');
        var dmax  = parseFloat($row.data('dose-max'))  || 0;
        var dmaxU = String($row.data('dose-max-un') || 'mg').toLowerCase();
        if (dmax <= 0 || !(doseUnit in _massaMg) || !(dmaxU in _massaMg) ||
            dose * _massaMg[doseUnit] <= dmax * _massaMg[dmaxU]) {
            $dose.css({ 'border-color': '', 'background-color': '' }).removeAttr('title');
            return;
        }
        $dose.css({ 'border-color': '#dc2626', 'background-color': '#fef2f2' })
             .attr('title', '⚠ Acima da dose máxima cadastrada: ' + fmt(dmax, 2) + ' ' + dmaxU);
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
        // Equivalência sal↔base do sinônimo (FC03200.EQUIV): multiplica a dose prescrita
        var equiv        = MOTOR_ON ? (parseFloat($row.data('equiv')) || 1) : 1;
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
            // % é DIRETO sobre o volume total (volume × potes), SEM densidade — modelo FCerta
            // (ex.: 20% de 500ml = 100g). Igual ao backend (reprocessar/importar). Não usar densidade
            // aqui: senão líquidos saíam massa = %×volume×densidade (ex.: 5% de 150ml × 0,61 = 4,575 no lugar de 7,5).
            var totalG_pct = getVol() * getPotes();
            var qty_g_nom  = (dose / 100) * totalG_pct;
            var qty_g_real = qty_g_nom * equiv * diluicao / (teor / 100);
            var qty_g_fp   = qty_g_real * fp;
            var qty_mg_fp  = qty_g_fp * 1000;
            var qtdEmUnid_pct = unidPadrao === 'g' ? qty_g_fp : qty_mg_fp;
            var subtotal_pct  = qtdEmUnid_pct * vendaUnit;
            $row.find('.taof-orc-qtd-total').text(fmt(qty_mg_fp, 2) + ' mg (' + fmt(dose, 3) + '%)');
            $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal_pct));
            $row.data({ subtotal: subtotal_pct, 'qtd-total-g': qty_g_fp,
                'qtd-em-padrao': qtdEmUnid_pct, 'qtd-unit': unidPadrao,
                dose: dose, 'dose-unit': '%', 'volapa-ul': 0 });
            alertaDoseMax($row, dose, '%');
            calcularTotais();
            return;
        }

        // ── un: produto vendido por UNIDADE (ex.: cápsulas prontas — CAPS OMEGA 3) ──
        // qtde = un por dose × doses × potes; preço de venda é POR UNIDADE.
        // Sem teor/diluição/equivalência/FP/densidade (produto pronto) e sem massa:
        // não entra em pesagem nem consome volume de cápsula (separação, não balança).
        if (doseUnit === 'un') {
            var qtd_un = dose * mult;
            var sub_un = qtd_un * vendaUnit;
            $row.find('.taof-orc-qtd-total').text(fmt(qtd_un, 0) + ' un');
            $row.find('.taof-orc-subtotal').text('R$ ' + fmt(sub_un));
            $row.data({ subtotal: sub_un, 'qtd-total-g': 0,
                'qtd-em-padrao': qtd_un, 'qtd-unit': 'un',
                dose: dose, 'dose-unit': 'un', 'volapa-ul': 0 });
            calcularTotais();
            return;
        }

        if (isSpecial) {
            // BLH: dose em bilhoes — converte para UFC antes de dividir pela concentracao (UFC/g)
            var dose_ufc       = (doseUnit === 'BLH') ? dose * 1e9 : dose;
            var qtd_g_per_dose = concentracao > 0 ? dose_ufc / concentracao : 0;
            // aplica diluição/teor/perda igual aos demais ramos (a concentração é da substância PURA;
            // p/ ativo diluído — ex.: VIT D3 1:100 — sem isto a pesagem sai ÷diluição)
            var qtd_total_g    = qtd_g_per_dose * equiv * diluicao / (teor / 100) * fp * mult;
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

        // FC: QTREAL = dose_mg x EQUIV x DILUICAO / (TEOR/100)  — FP nao entra no VOLAPA
        var dose_mg_dil  = dose_mg * equiv * diluicao;
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
        alertaDoseMax($row, dose, doseUnit);
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
        } else if (formaAtual.tipo === 'sublingual') {
            _qspSublingual($qspRow);
        } else {
            _qspForma($qspRow);
        }
    }

    // QSP do Sublingual/Orodispersível: base OROTAB por VOLUME. Ativos ocupam <=25% do volume
    // do comprimido; a base preenche o resto. Tamanho automático (menor nº de comprimidos/dose)
    // salvo se o usuário forçar no campo "Tamanho". Base = (nComp*tam − volAtivos/dose) × doses × densidade.
    function _qspSublingual($row) {
        var ndoses = getVol() * getPotes();   // getVol() = nº de doses
        var volAtivos = 0;
        $('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').each(function () {
            var g = parseFloat($(this).data('qtd-total-g')) || 0;
            var d = parseFloat($(this).data('densidade')) || 1;
            volAtivos += d > 0 ? g / d : g;
        });
        var vd = ndoses > 0 ? volAtivos / ndoses : volAtivos;   // volume de ativo por dose
        var tamSel = parseFloat($('#taof-sub-tam').val()) || 0;   // seletor na área de embalagem; vazio = automático
        var best = null;
        (tamSel > 0 ? [tamSel] : [0.21, 0.8]).forEach(function (tam) {
            var n = Math.max(1, Math.ceil(vd / (0.25 * tam)));
            var vt = n * tam;
            if (!best || n < best.n || (n === best.n && vt < best.vt)) best = { tam: tam, n: n, vt: vt };
        });
        var volBaseTotal = Math.max(0, best.n * best.tam - vd) * ndoses;
        var qspDens = parseFloat($row.data('densidade')) || 0.64;
        var qspG = volBaseTotal * qspDens;
        var vendaUnit  = parseFloat($row.data('venda-unit')) || 0;
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var qtdEmUnid  = unidPadrao === 'mg' ? qspG * 1000 : qspG;
        var subtotal   = qtdEmUnid * vendaUnit;
        $row.find('.taof-orc-qtd-total').text(fmt(qspG, 3) + ' g · comp ' + fmt(best.tam, 2) + ' × ' + best.n + '/dose');
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qspG, 'volapa-ul': 0 });
        _subInfo = { n: best.n, tam: best.tam, auto: !(tamSel > 0) };
        $('#taof-sub-comp-info').text(
            'comp. ' + fmt(best.tam, 2) + (tamSel > 0 ? '' : ' (automático)') + ' · ' + best.n + '/dose'
        );
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

    // QSP do Envelope: excipiente (base efervescente) = 1 g A MAIS que o volume da fórmula POR ENVELOPE
    // → 1 g por envelope, independente da capacidade. Total = 1 g × nº de envelopes.
    var TAOF_ENV_QSP_G_POR_ENV = 1;
    function _qspEnvelope($row) {
        var qspG  = TAOF_ENV_QSP_G_POR_ENV * getVol() * getPotes();   // getVol() = nº de envelopes
        var qspMg = qspG * 1000;
        var vendaUnit  = parseFloat($row.data('venda-unit'))  || 0;
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var qtdEmUnid  = unidPadrao === 'g' ? qspG : unidPadrao === 'mg' ? qspMg : qspG;
        var subtotal   = qtdEmUnid * vendaUnit;

        $row.find('.taof-orc-qtd-total').text(fmt(qspMg, 2) + ' mg (' + fmt(TAOF_ENV_QSP_G_POR_ENV, 0) + ' g/env)');
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
    // ── Informativo "N por dose" (antes dos totais) ───────────────────
    // Cápsula: nº de cápsulas/dose (da sugestão de cápsula). Sublingual: nº de comprimidos/dose.
    function atualizarPorDose(r) {
        var isCap = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        var isSub = formaAtual && formaAtual.tipo === 'sublingual';
        var $wrap = $('#taof-npd-wrap'), $txt = $('#taof-npd-txt');
        if (isCap && r && r.nPerDose > 0) {
            var totCaps = getVol() * getPotes() * r.nPerDose;
            $txt.html('💊 <strong>' + r.nPerDose + '</strong> cápsula(s) por dose · <strong>' + totCaps + '</strong> cápsulas no total');
            $wrap.css('display', 'block');
        } else if (isSub && _subInfo && _subInfo.n > 0) {
            var totComp = getVol() * getPotes() * _subInfo.n;
            $txt.html('💊 <strong>' + _subInfo.n + '</strong> comprimido(s) sublingual(is) por dose · comp. ' +
                      fmt(_subInfo.tam, 2) + (_subInfo.auto ? ' (automático)' : '') +
                      ' · <strong>' + totComp + '</strong> comprimidos no total');
            $wrap.css('display', 'block');
            atualizarBlisterQtd();   // linha dinâmica do blister (9/blister) acompanha o total
        } else {
            $wrap.hide();
        }
    }

    function calcularTotais() {
        // 1. Sugestao de capsula primeiro: atualiza #taof-caps-por-dose em modo auto
        //    antes de atualizarQSPRow (que usa nPerDose para calcular QSP)
        var r = sugerirCapsula();

        // 2. Atualiza linha QSP (usa caps-por-dose ja atualizado acima)
        atualizarQSPRow();

        // 2b. Informativo de unidades por dose (cápsula / comprimido sublingual)
        atualizarPorDose(r);

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
            } else if (formaAtual.tipo === 'sublingual') {
                $('#taof-col-tipo-label').text('Tipo');
                popularTipoSublingual();
                $colTipo.show();
                $('#taof-forma-vol').attr('placeholder', 'No. doses');
                $('#taof-card-capsulas').hide();
                $('#taof-caps-por-dose').removeData('manual').val(1);
                $('#taof-emb-hdr').text('Embalagem / Comprimido');       // área acomoda o comprimido
                $('#taof-sub-comp-wrap').css('display', 'flex');          // tamanho do comprimido
            } else {
                $('#taof-forma-tipo').empty();
                $colTipo.hide();
                $('#taof-card-capsulas').hide();
                $('#taof-caps-por-dose').removeData('manual').val(1);
            }
            // Fora do sublingual: esconde o seletor de comprimido, remove o blister e restaura o título
            if (!formaAtual || formaAtual.tipo !== 'sublingual') {
                $('#taof-sub-comp-wrap').hide();
                $('#taof-emb-hdr').text('Embalagem');
                $('#taof-emb-body .taof-emb-row[data-sub-blister]').remove();
                _subInfo = null;
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
        if (!_loadingEdit) setTimeout(garantirExcipienteEnvelope, 120); setTimeout(garantirBaseSublingual, 120); setTimeout(garantirBlisterSublingual, 140);   // envelope: garante base efervescente; sublingual: base + blister
    });

    // Recalcula quando atendente altera Vol/Qtde, Tipo Capsula, Unidade, Potes ou Vol/dose
    $('#taof-forma-vol, #taof-forma-tipo, #taof-forma-unidade, #taof-qtde-potes, #taof-vol-dose, #taof-sub-tam').on('input change', function () {
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
            $row.find('.taof-orc-dose').prop('readonly', true).val('');   // QSP auto (cápsula=volume, envelope=1g/env)
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
        // ✏ Nome de EXIBIÇÃO para o cliente (nome_prescricao): rótulo, mensagem WhatsApp e livro
        // mostram este texto (ex.: prescrição "SILÍCIO ORGÂNICO" p/ o ativo "SILICIUM MAX")
        $row.on('click', '.taof-btn-nome-cli', function () {
            var atual = ($row.data('nome-prescricao') || $row.data('ativo-nome') || '').toString();
            var novo  = prompt('Nome de exibição para o CLIENTE (rótulo e mensagem):', atual);
            if (novo === null) return;
            novo = novo.trim().toUpperCase();
            if (!novo) return;
            $row.data('nome-prescricao', novo);
            var difere = novo !== ($row.data('ativo-nome') || '').toString().toUpperCase();
            $row.find('.taof-btn-nome-cli')
                .css('color', difere ? '#0369a1' : '')
                .attr('title', difere ? ('Cliente vê: ' + novo) : 'Nome de exibição para o cliente (rótulo e mensagem)');
            taofToast('✓ Cliente verá: ' + novo);
        });
        // Enter na dose: navegação rápida (QSP → cria nova linha; demais → próxima linha).
        // Tab fica natural: passa por Unidade e botão QSP, sem criar linha.
        $row.on('keydown', '.taof-orc-dose', function (e) {
            if (e.key !== 'Enter') return;
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
        // Motor v2: trava de substância bloqueada/restrita (coluna restricao + regra GLP-1 IN 360/2025)
        if (MOTOR_ON) {
            var nomeUp = String(a.nome || '').toUpperCase();
            var restr  = String(a.restricao || '').toLowerCase();
            if (restr.indexOf('bloqueada') === 0 || nomeUp.indexOf('SEMAGLUTIDA') !== -1) {
                alert('⛔ ' + a.nome + ' está BLOQUEADA para manipulação' +
                      (nomeUp.indexOf('SEMAGLUTIDA') !== -1 ? ' (IN 360/2025 — GLP-1)' : '') +
                      '.\nO ativo não foi adicionado ao orçamento.');
                return;
            }
            if (restr || nomeUp.indexOf('TIRZEPATIDA') !== -1) {
                taofToast('⚠ ' + a.nome + ': substância RESTRITA (' + (a.restricao || 'GLP-1 IN 360/2025') + ') — confira as exigências antes de aprovar');
            }
        }
        // Bloqueio para manipulação — NÃO impede o orçamento; avisa sobre a data de entrega
        if (a.bloqueado) {
            taofToast('⚠ ' + a.nome + ' está BLOQUEADO para manipulação — a data de entrega deverá ser confirmada com o Farmacêutico.');
            $row.css('box-shadow', 'inset 3px 0 0 #f59e0b').attr('title', 'Produto bloqueado — data de entrega a confirmar com o Farmacêutico');
        }
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
            // Motor v2 (neutros com a option OFF)
            'equiv':            parseFloat(a.fator_equiv)    || 1,
            'dose-max':         parseFloat(a.dose_max_dia || a.dose_max) || 0,
            'dose-max-un':      a.dose_max_unidade || a.uni_dose_max || 'mg',
            'restricao':        a.restricao || '',
            'nr-lote':          '',
        });
        var vendaLabel = parseFloat(a.preco_venda) > 0
            ? 'R$ ' + fmt(a.preco_venda, 4) + '/' + (a.unidade_padrao || 'g')
            : '—';
        $row.find('.taof-orc-preco-venda').text(vendaLabel);
        var u = a.unidade_padrao;
        if (u === 'UN') u = 'un';   // cadastro por unidade → dose em 'un'
        if (['mg', 'mcg', 'g', 'UI', 'UFC', 'BLH', 'ml', 'un'].indexOf(u) === -1) u = 'mg';
        if (u === 'g') u = 'mg';
        if (u === 'UFC' || u === 'BLH') u = 'BLH';
        $row.find('.taof-orc-dose-unit').val(u);
        $row.find('.taof-ac-dropdown').hide().empty();
        calcularLinha($row);
        // Motor v2: laudo REAL do lote FEFO prevalece sobre o nominal do ativo
        if (MOTOR_ON && !_loadingEdit && !$row.hasClass('taof-row-qsp')) {
            $.getJSON(ajaxUrl, { action: 'tao_formula_lote_fefo', nonce: nonce, ativo_id: a.id }, function (resp) {
                var l = resp && resp.success ? resp.data : null;
                if (!l || $row.data('ativo-id') !== a.id) return;   // sem lote ou a linha já trocou de ativo
                var upd = { 'nr-lote': l.nr_lote || '' };
                if (parseFloat(l.teor_pct)       > 0) upd.teor      = parseFloat(l.teor_pct);
                if (parseFloat(l.densidade)      > 0) upd.densidade = parseFloat(l.densidade);
                if (parseFloat(l.fator_diluicao) > 1) upd.diluicao  = parseFloat(l.fator_diluicao);
                $row.data(upd);
                if (l.nr_lote) {
                    $row.find('.taof-orc-cod')
                        .attr('title', 'Lote ' + l.nr_lote +
                              (l.dt_validade ? ' · val. ' + l.dt_validade : '') +
                              (parseFloat(l.teor_pct) > 0 ? ' · teor ' + fmt(l.teor_pct, 2) + '%' : ''))
                        .css('border-bottom', '1px dotted #0369a1');
                }
                if (upd.teor !== undefined || upd.densidade !== undefined || upd.diluicao !== undefined) calcularLinha($row);
            });
        }
        // Ao associar um ativo (não-QSP): envelope garante excipiente base; sublingual garante base + blister
        if (!$row.hasClass('taof-row-qsp') && formaAtual &&
            (formaAtual.tipo === 'envelope' || formaAtual.tipo === 'sublingual')) {
            setTimeout(garantirExcipienteEnvelope, 120); setTimeout(garantirBaseSublingual, 120); setTimeout(garantirBlisterSublingual, 140);
        }
    }

    // ── Fórmula padrão: busca + aplica no editor ──────────────────────
    var _fpadUnit = { 'G': 'g', 'MG': 'mg', 'MCG': 'mcg', 'ML': 'ml', '%': '%', 'UI': 'UI', 'UFC': 'UFC', 'BLH': 'BLH', 'UN': 'un' };
    function fpadDoseUnit(u) {
        u = String(u || '').trim().toUpperCase();
        return _fpadUnit[u] || 'mg';
    }

    function aplicarFormulaPadrao(det) {
        if (!det || !det.formula) return;
        var f = det.formula;

        // Forma: cápsula pelo tipo; demais ficam p/ a atendente confirmar (dado do FCerta é ambíguo)
        var alvoTipo = f.tipo_capsula ? 'cap' : null;
        if (alvoTipo) {
            var fid = null;
            Object.keys(formasMap).forEach(function (k) {
                if (!fid && formasMap[k] && formasMap[k].tipo === alvoTipo) fid = k;
            });
            if (fid) $('#taof-forma-sel').val(fid).trigger('change');
        }
        if (f.volume) setTimeout(function () { $('#taof-forma-vol').val(f.volume).trigger('input'); }, 60);

        // Substitui as linhas atuais pelas da fórmula padrão
        $('#taof-itens-body').empty();
        (det.itens || []).forEach(function (it) {
            var $row = adicionarLinha();
            if (it.ativo && it.ativo.id) {
                selecionarAtivo($row, it.ativo, it.descricao);
            } else {
                // Ativo não cadastrado: deixa o nome da prescrição p/ a atendente associar
                $row.find('.taof-orc-ativo-search')
                    .val(it.descricao || '')
                    .css({ 'border-color': '#f97316', 'background-color': '#fff7ed' });
                $row.data('nome-prescricao', (it.descricao || '').toUpperCase());
            }
            if (it.eh_qsp) {
                toggleQSP($row, true);
            } else if (it.qtd != null) {
                $row.find('.taof-orc-dose-unit').val(fpadDoseUnit(it.unidade));
                $row.find('.taof-orc-dose').val(it.qtd);
                calcularLinha($row);
            }
        });
        calcularTotais();
        taofToast('✓ Fórmula padrão aplicada: ' + f.nome + (alvoTipo ? '' : ' — confira a forma farmacêutica'));
    }

    (function initFpad() {
        var $btn = $('#taof-btn-fpad'), $dd = $('#taof-fpad-dd');
        if (!$btn.length) return;
        var timer = null;

        function abrir() {
            $dd.html('<div style="padding:8px"><input type="text" id="taof-fpad-inp" placeholder="Buscar fórmula padrão..." ' +
                     'style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px" autocomplete="off"></div>' +
                     '<div id="taof-fpad-res"></div>');
            var r = $btn[0].getBoundingClientRect();
            $dd.css({ position: 'fixed', top: r.bottom + 2, left: r.left, 'z-index': 2147483000 }).show();
            $('#taof-fpad-inp').focus().on('input', function () {
                clearTimeout(timer);
                var q = $(this).val().trim();
                if (q.length < 2) { $('#taof-fpad-res').empty(); return; }
                timer = setTimeout(function () {
                    $.getJSON(ajaxUrl, { action: 'tao_formula_fpad_busca', nonce: nonce, q: q }, function (resp) {
                        var $res = $('#taof-fpad-res').empty();
                        if (!resp.success) { $res.html('<div style="padding:8px;color:#dc2626;font-size:12px">' + (resp.data && resp.data.message || 'Erro') + '</div>'); return; }
                        var lista = resp.data || [];
                        if (!lista.length) { $res.html('<div style="padding:8px;color:#94a3b8;font-size:12px">Nada encontrado.</div>'); return; }
                        lista.forEach(function (f) {
                            var sub = [f.volume ? (parseFloat(f.volume) + ' ' + (f.unidade || '').trim()) : '', f.tipo_capsula || ''].filter(Boolean).join(' · ');
                            $('<div class="taof-ac-item">')
                                .html('<span>' + $('<span>').text(f.nome).html() + '</span>' + (sub ? '<small>' + $('<span>').text(sub).html() + '</small>' : ''))
                                .on('mousedown', function (e) {
                                    e.preventDefault();
                                    $dd.hide().empty();
                                    $.getJSON(ajaxUrl, { action: 'tao_formula_fpad_detalhe', nonce: nonce, id: f.id }, function (d) {
                                        if (d.success) aplicarFormulaPadrao(d.data);
                                        else alert('Erro ao carregar a fórmula: ' + (d.data && d.data.message || ''));
                                    });
                                })
                                .appendTo($res);
                        });
                    });
                }, 280);
            });
        }

        $btn.on('click', function (e) {
            e.stopPropagation();
            if ($dd.is(':visible')) { $dd.hide().empty(); } else { abrir(); }
        });
        $(document).on('mousedown', function (e) {
            if (!$dd.is(':visible')) return;
            if (!$(e.target).closest('#taof-fpad-dd, #taof-btn-fpad').length) $dd.hide().empty();
        });
    })();

    // ── Autocomplete de PRESCRITOR (cadastro de prescritores) ─────────
    (function initPrescritorAC() {
        var $inp = $('#taof-prescritor'), $dd = $('#taof-prescritor-dd'), timer = null;
        if (!$inp.length) return;
        function label(p) {
            var reg = [p.tipo_registro, p.nr_registro].filter(Boolean).join(' ') + (p.uf_registro ? '/' + p.uf_registro : '');
            return ((p.tratamento ? p.tratamento + ' ' : '') + p.nome + (reg ? ' — ' + reg : '')).trim();
        }
        $inp.on('input', function () {
            clearTimeout(timer);
            $('#taof-prescritor-id').val('');   // digitação livre invalida o vínculo
            var q = $(this).val().trim();
            if (q.length < 2) { $dd.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_prescritores_busca', nonce: nonce, q: q }, function (resp) {
                    var lista = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
                    $dd.empty();
                    if (!lista.length) { $dd.hide(); return; }
                    lista.forEach(function (p) {
                        $('<div class="taof-ac-item">')
                            .html('<span>' + $('<span>').text(label(p)).html() + '</span>' +
                                  (p.especialidade ? '<small>' + $('<span>').text(p.especialidade).html() + '</small>' : ''))
                            .on('mousedown', function (e) {
                                e.preventDefault();
                                $inp.val(label(p));
                                $('#taof-prescritor-id').val(p.id);
                                $dd.hide().empty();
                            })
                            .appendTo($dd);
                    });
                    positionDropdown($inp, $dd);
                    $dd.show();
                });
            }, 280);
        });
        $inp.on('blur', function () { setTimeout(function () { $dd.hide().empty(); }, 150); });
    })();

    // ── Autocomplete de PACIENTE (base de contatos do CRM) ────────────
    (function initPacienteAC() {
        var $inp = $('#taof-nome-paciente'), $dd = $('#taof-nome-paciente-dd'), timer = null;
        if (!$inp.length || $inp.prop('readonly')) return;   // card já traz o paciente travado
        function pick($item) {
            var c = $item.data('contato');
            if (!c) return;
            $inp.val(c.nome || '');
            $('#taof-contato-id').val(c.id || '');
            if (c.whatsapp && !$('#taof-whatsapp').prop('readonly')) $('#taof-whatsapp').val(c.whatsapp);
            $dd.hide().empty();
        }
        $inp.on('input', function () {
            clearTimeout(timer);
            $('#taof-contato-id').val('');   // digitação livre invalida o vínculo
            var q = $(this).val().trim();
            if (q.length < 2) { $dd.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_cliente_busca', nonce: nonce, q: q }, function (resp) {
                    var lista = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
                    $dd.empty();
                    if (!lista.length) { $dd.hide(); return; }
                    lista.forEach(function (c) {
                        $('<div class="taof-ac-item" data-sel="1">')
                            .html('<span>' + $('<span>').text(c.nome).html() + '</span>' +
                                  (c.whatsapp ? '<small>' + $('<span>').text(c.whatsapp).html() + '</small>' : ''))
                            .data('contato', c)
                            .on('mousedown', function (e) { e.preventDefault(); pick($(this)); })
                            .appendTo($dd);
                    });
                    positionDropdown($inp, $dd);
                    $dd.show();
                });
            }, 280);
        });
        // Navegação por teclado (↑ ↓ Enter Esc)
        $inp.on('keydown', function (e) {
            if (!$dd.is(':visible')) {
                if (e.key === 'ArrowDown' && $(this).val().trim().length >= 2) { e.preventDefault(); $(this).trigger('input'); }
                return;
            }
            var $items = $dd.find('.taof-ac-item[data-sel]');
            var $cur   = $items.filter('.taof-ac-hl');
            if (e.key === 'ArrowDown') {
                e.preventDefault(); $items.removeClass('taof-ac-hl');
                var $nxt = $cur.length ? $cur.nextAll('[data-sel]').first() : $();
                ($nxt.length ? $nxt : $items.first()).addClass('taof-ac-hl');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault(); $items.removeClass('taof-ac-hl');
                var $prv = $cur.length ? $cur.prevAll('[data-sel]').first() : $();
                ($prv.length ? $prv : $items.last()).addClass('taof-ac-hl');
            } else if (e.key === 'Enter') {
                var $sel = $items.filter('.taof-ac-hl');
                if ($sel.length) { e.preventDefault(); pick($sel); }
            } else if (e.key === 'Escape') { $dd.hide().empty(); }
        });
        $inp.on('blur', function () { setTimeout(function () { $dd.hide().empty(); }, 150); });
    })();

    // ── Autocomplete — CID-10 (diagnóstico opcional) ──────────────────
    (function () {
        var $inp = $('#taof-cid'), $dd = $('#taof-cid-dd'), timer = null;
        if (!$inp.length) return;
        $inp.on('input', function () {
            clearTimeout(timer);
            $('#taof-cid-codigo').val(''); $('#taof-cid-descricao').val('');   // digitação livre limpa o vínculo
            var q = $(this).val().trim();
            if (q.length < 2) { $dd.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_cid_busca', nonce: nonce, q: q }, function (resp) {
                    var lista = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
                    $dd.empty();
                    if (!lista.length) { $dd.hide(); return; }
                    lista.forEach(function (c) {
                        $('<div class="taof-ac-item">')
                            .html('<span><strong>' + $('<span>').text(c.codigo).html() + '</strong> ' + $('<span>').text(c.descricao).html() + '</span>')
                            .on('mousedown', function (e) {
                                e.preventDefault();
                                $inp.val(c.codigo + ' — ' + c.descricao);
                                $('#taof-cid-codigo').val(c.codigo);
                                $('#taof-cid-descricao').val(c.descricao);
                                $dd.hide().empty();
                            })
                            .appendTo($dd);
                    });
                    positionDropdown($inp, $dd);
                    $dd.show();
                });
            }, 280);
        });
        $inp.on('blur', function () { setTimeout(function () { $dd.hide().empty(); }, 150); });
    })();

    // ── Envelope: auto-associa EXCIPIENTE BASE (cód 10577) na linha QSP (1 g/env) ──
    var _excipienteEnv = null;
    function garantirExcipienteEnvelope() {
        if (_loadingEdit) return;
        if (!formaAtual || formaAtual.tipo !== 'envelope') return;
        if (!$('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').length) return;   // ainda sem ativos
        // Regra Carlos 13/08: se a fórmula JÁ TEM base (efervescente/sachê), NÃO adiciona nada.
        // Sem base: entra BASE P/SACHE (12903) como item NORMAL com dose 1 g/env — não é QSP
        // (sachê não completa peso; a base é volume fixo de 1 g por envelope).
        var temBase = false;
        $('#taof-itens-body .taof-item-row').each(function () {
            var n = String($(this).data('ativo-nome') || '').toUpperCase();
            if (n.indexOf('EFERVESC') !== -1 || (n.indexOf('BASE') !== -1 && n.indexOf('SACHE') !== -1)) temBase = true;
        });
        if (temBase) return;

        var aplicar = function (ativo) {
            if (!ativo) return;
            var $row = adicionarLinha();
            selecionarAtivo($row, ativo, 'BASE P/ SACHE');
            $row.find('.taof-orc-dose').val(1);
            $row.find('.taof-orc-dose-unit').val('g');
            calcularLinha($row);
            calcularTotais();
        };
        if (_excipienteEnv) { aplicar(_excipienteEnv); return; }
        $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: '12903', grupo: '' }, function (resp) {
            var lista = (resp && Array.isArray(resp.data)) ? resp.data : [];
            var ativo = null;
            for (var i = 0; i < lista.length; i++) { if (String(lista[i].codigo_fc) === '12903') { ativo = lista[i]; break; } }
            if (!ativo && lista.length) ativo = lista[0];
            _excipienteEnv = ativo;
            aplicar(ativo);
        });
    }

    // Sublingual/Orodispersível: garante a BASE OROTAB (11166) como QSP (por volume). Igual ao envelope.
    var _baseSub = null;
    function garantirBaseSublingual() {
        if (_loadingEdit) return;
        if (!formaAtual || formaAtual.tipo !== 'sublingual') return;
        if (!$('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').length) return;   // ainda sem ativos
        var $qsp = $('#taof-itens-body .taof-item-row.taof-row-qsp').first();
        if ($qsp.length && $qsp.find('.taof-orc-ativo-id').val()) return;              // já associada
        var aplicar = function (ativo) {
            if (!ativo) return;
            var $row = $('#taof-itens-body .taof-item-row.taof-row-qsp').first();
            if (!$row.length) $row = adicionarLinha();
            if (!$row.hasClass('taof-row-qsp')) toggleQSP($row, true);
            selecionarAtivo($row, ativo, 'BASE OROTAB');
            calcularTotais();
        };
        if (_baseSub) { aplicar(_baseSub); return; }
        $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: '11166', grupo: '' }, function (resp) {
            var lista = (resp && Array.isArray(resp.data)) ? resp.data : [];
            var ativo = null;
            for (var i = 0; i < lista.length; i++) { if (String(lista[i].codigo_fc) === '11166') { ativo = lista[i]; break; } }
            if (!ativo && lista.length) ativo = lista[0];
            _baseSub = ativo;
            aplicar(ativo);
        });
    }

    // Sublingual: LINHA DINÂMICA do blister BLISTER OROTAB - 9 (11313) — 9 sublinguais/blister.
    // Quantidade = ceil(total_comprimidos / 9), recalculada a cada mudança de dose/tamanho/potes.
    var _blisterSub = null;
    function _marcarBlisterRow($row, a) {
        $row.attr('data-sub-blister', '1').data({ 'auto-emb': true, 'sub-blister': true });
        if (a) {
            var custo = a.custo_por_unidade || a.preco_venda || 0;
            $row.data({ 'emb-id': a.id, 'emb-nome': a.nome, 'custo-unit': custo });
            $row.find('.taof-emb-search').val(a.nome);
            $row.find('.taof-emb-id').val(a.id);
            $row.find('.taof-emb-custo-label').text('R$ ' + fmt(custo, 4) + '/un');
        }
        $row.find('.taof-emb-search').prop('readonly', true);
        $row.find('.taof-emb-qty').prop('readonly', true)
            .attr('title', '9 sublinguais por blister — quantidade automática');
    }
    // Só ajusta a quantidade/subtotal (chamada DENTRO de calcularTotais, sem recursão).
    function atualizarBlisterQtd() {
        if (_loadingEdit) return;
        var $row = $('#taof-emb-body .taof-emb-row[data-sub-blister]').first();
        if (!$row.length || !_subInfo || !(_subInfo.n > 0)) return;
        var totComp  = _subInfo.n * getVol() * getPotes();
        var nBlister = Math.max(1, Math.ceil(totComp / 9));
        var custo    = parseFloat($row.data('custo-unit')) || 0;
        var subtotal = nBlister * custo;
        $row.find('.taof-emb-qty').val(nBlister);                 // .val() não dispara input → sem recursão
        $row.find('.taof-emb-subtotal').text('R$ ' + fmt(subtotal));
        $row.data('subtotal-emb', subtotal);
    }
    function garantirBlisterSublingual() {
        if (_loadingEdit) return;
        if (!formaAtual || formaAtual.tipo !== 'sublingual') return;
        if (!$('#taof-itens-body .taof-item-row:not(.taof-row-qsp)').length) return;   // sem ativos ainda
        // Já existe (criada agora ou carregada do salvo)? marca como dinâmica e atualiza.
        var $existe = $('#taof-emb-body .taof-emb-row[data-sub-blister]');
        if (!$existe.length) {
            $('#taof-emb-body .taof-emb-row').each(function () {
                var nome = String($(this).data('emb-nome') || $(this).find('.taof-emb-search').val() || '');
                if (/blister\s*orotab/i.test(nome)) { _marcarBlisterRow($(this), null); $existe = $(this); }
            });
        }
        if ($existe.length) { atualizarBlisterQtd(); calcularTotais(); return; }
        var criar = function (a) {
            if (!a) return;
            $('#taof-btn-add-emb').trigger('click');
            var $row = $('#taof-emb-body .taof-emb-row').last();
            $row.data('subtotal-emb', 0);
            _marcarBlisterRow($row, a);
            calcularTotais();   // dispara atualizarPorDose → atualizarBlisterQtd
        };
        if (_blisterSub) { criar(_blisterSub); return; }
        $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: '11313', grupo: 'E' }, function (resp) {
            var lista = (resp && Array.isArray(resp.data)) ? resp.data : [];
            var a = null;
            for (var i = 0; i < lista.length; i++) { if (String(lista[i].codigo_fc) === '11313') { a = lista[i]; break; } }
            if (!a && lista.length) a = lista[0];
            _blisterSub = a;
            criar(a);
        });
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
                                if (MOTOR_ON && a._sinonimo) info += ' · sin: ' + a._sinonimo;
                                if (MOTOR_ON && parseFloat(a.fator_equiv || 1) !== 1) info += ' · equiv ×' + fmt(a.fator_equiv, 3);
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
                                    // Só salva o sinônimo se a seleção foi aplicada (trava de restrição pode ter recusado)
                                    if ($row.find('.taof-orc-ativo-id').val() != a.id) return;
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
                    // Só salva o sinônimo se a seleção foi aplicada (trava de restrição pode ter recusado)
                    if ($row.find('.taof-orc-ativo-id').val() != aObj2.id) return;
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
                // QSP de líquido dosado: persiste o Vol/dose no item (é a DOSE do veículo —
                // ex.: 2 ml — usada pra rederivar o nº de doses ao reabrir/importar)
                dose:             isQsp ? (isLiquidForm() && getVolDose() > 0 ? getVolDose() : null) : $r.data('dose'),
                dose_unit:        (isQsp && isLiquidForm() && getVolDose() > 0)
                                      ? ($r.data('dose-unit') || (getUnidade() === 'g' ? 'g' : 'ml'))
                                      : $r.data('dose-unit'),
                multiplicador:    getMultiplicador(),
                qtde_potes:       getPotes(),
                n_caps_por_dose:  cap ? cap.nPerDose : 1,
                capsula_tipo:     cap ? cap.cap.tipo : null,
                capsula_numero:   cap ? cap.cap.numero : null,
                diluicao:         $r.data('diluicao'),
                teor:             $r.data('teor'),
                equiv:            parseFloat($r.data('equiv')) || 1,
                nr_lote:          $r.data('nr-lote') || null,
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
        // Sub-Total = o MESMO exibido na tela (window._taofSubtotal, setado por calcularTotais),
        // que inclui o custo das CÁPSULAS. Sem isso, o valor salvo ignora as cápsulas e diverge
        // do que o modal mostra (bug do card exibindo total menor que o editor).
        var subtotal  = (typeof window._taofSubtotal === 'number' && window._taofSubtotal > 0)
                        ? window._taofSubtotal : (calculado + custoFixo);
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
            contato_id:      $('#taof-contato-id').val() || '',
            nome_cliente:    $('#taof-nome-cliente').val() || '',
            prescritor:      $('#taof-prescritor').val()   || '',
            prescritor_id:   $('#taof-prescritor-id').val() || '',
            posologia:       $('#taof-posologia').val()    || '',
            cid_codigo:      $('#taof-cid-codigo').val()    || '',
            cid_descricao:   $('#taof-cid-descricao').val() || '',
            whatsapp:        $('#taof-whatsapp').val(),
            forma_id:        $('#taof-forma-sel').val() || '',
            forma_nome:      formaAtual ? formaAtual.nome : (EDIT_DATA ? (EDIT_DATA.forma_nome || '') : ''),
            forma_vol:       getVol(),
            // Sublingual: forma_tipo carrega o TAMANHO forçado do comprimido (vazio = automático)
            forma_tipo:      (formaAtual && formaAtual.tipo === 'sublingual')
                                 ? ($('#taof-sub-tam').val() || '')
                                 : $('#taof-forma-tipo').val(),
            forma_unidade:   getUnidade(),
            qtde_potes:      getPotes(),
            custo_fixo:      custoFixo,
            total_insumos:   calculado,
            acrescimo:       acrescVal,     // Acréscimo em R$ (fonte da verdade)
            desconto_val:    desctVal,      // Desconto em R$ (fonte da verdade → desconto_fc)
            margem_pct:      acrescPct,     // % derivado (exibição)
            desconto_pct:    desctPct,
            total_orcamento: final,
            observacoes:     $('#taof-observacoes').val(),
            // Controlados (344/98) — receita + comprador; a OM herda na criação
            tp_receita:       ($('#taof-ctl-tipo').val()      || ''),
            nr_notificacao:   ($('#taof-ctl-notif').val()     || ''),
            comprador_nome:   ($('#taof-ctl-comprador').val() || ''),
            comprador_doc_tp: ($('#taof-ctl-doctp').val()     || ''),
            comprador_doc_nr: ($('#taof-ctl-docnr').val()     || ''),
            itens:           JSON.stringify(itens)
        };
        // Orçamento importado do FC: propaga o Final pro card (Kanban lê valor_final_fc)
        if (window._taofFcFinal != null) postData.valor_final_fc = final;

        $.post(ajaxUrl, postData, function (resp) {
            $sp.css('visibility', 'hidden');
            $btn.prop('disabled', false);
            if (resp.success) {
                // Fluxo "Aprovar orçamento": salvou o que está na tela → aprova na sequência
                if (_aprovarAposSalvar) {
                    _aprovarAposSalvar = false;
                    aprovarOrcAposSave((resp.data && resp.data.id) || EDIT_ORC_ID);
                    return;
                }
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

    // ── Aprovar orçamento a partir DESTA tela (fluxo do card: abrir → revisar → aprovar) ──
    // Salva o que está na tela e aprova na sequência: o que se vê é o que vira OM.
    var _aprovarAposSalvar = false;
    $('#taof-orc-aprovar-btn').on('click', function () {
        if (!EDIT_ORC_ID) return;
        if (!confirm('Aprovar este orçamento? Ele vira OM e fica travado para edição.')) return;
        _aprovarAposSalvar = true;
        $('#taof-orc-form').trigger('submit');
    });
    function aprovarOrcAposSave(orcId) {
        var fd = new FormData();
        fd.append('action', 'tao_formula_orc_aprovar');
        fd.append('nonce', nonce);
        fd.append('orc_id', orcId);
        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (!r || !r.success) {
                    alert((r && r.data && r.data.message) || 'Orçamento SALVO, mas NÃO aprovado — verifique (farmacêutico responsável / trava do card).');
                } else {
                    var av = r.data && r.data.avisos_controlado;
                    if (av && av.length) alert('⚠ Controlado (RDC 344/98) — pendências:\n\n• ' + av.join('\n• ') + '\n\nAprovado; regularize os itens acima.');
                }
                if (IS_MODAL) {
                    window.parent.postMessage({ taofSaved: true, orcId: orcId, isEdit: true }, '*');
                } else {
                    window.location.href = window.taofOrcListUrl;
                }
            })
            .catch(function () {
                alert('Orçamento salvo, mas a comunicação da aprovação falhou — confira o status na lista.');
                if (IS_MODAL) window.parent.postMessage({ taofSaved: true, orcId: orcId, isEdit: true }, '*');
            });
    }
    // Aberto pelo ✅ do card (&aprovar=1): destaca o botão de aprovação
    if (window.taofAprovarFlag) {
        var $apBtn = $('#taof-orc-aprovar-btn');
        if ($apBtn.length) $apBtn.css('box-shadow', '0 0 0 3px #86efac');
    }

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

        // Custo fixo da forma — linha própria na análise (pedido Carlos 17/07)
        if (custoFixoReal > 0) {
            rows += renderLinha('Custo fixo da forma (manipulação)', '', 0, 0, 0, custoFixoReal, false, false);
        }
        // Reconciliação: o que resta entre venda dos itens + custo fixo e o valor final (CM/acréscimo − desconto)
        var _ajuste = valorFinal - totalVenda - custoFixoReal;
        if (Math.abs(_ajuste) >= 0.01) {
            rows += renderLinha('CM (+) − Desconto (−)', '', 0, 0, 0, _ajuste, false, false);
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

        // Paciente / cliente (contratante) / prescritor / posologia
        $('#taof-nome-paciente').val(data.nome_paciente || '');
        $('#taof-nome-cliente').val(data.nome_cliente || '');
        $('#taof-prescritor').val(data.prescritor || '');
        $('#taof-prescritor-id').val(data.prescritor_id || '');
        $('#taof-posologia').val(data.posologia || '');
        $('#taof-cid-codigo').val(data.cid_codigo || '');
        $('#taof-cid-descricao').val(data.cid_descricao || '');
        $('#taof-cid').val(data.cid_codigo ? (data.cid_codigo + (data.cid_descricao ? ' — ' + data.cid_descricao : '')) : '');
        $('#taof-whatsapp').val(data.whatsapp || '');

        // Forma farmacêutica
        if (data.forma_id && formasMap[data.forma_id]) {
            $('#taof-forma-sel').val(data.forma_id).trigger('change');
            // Aguarda o change popular unidade, então define os valores
            setTimeout(function () {
                if (data.forma_vol)      $('#taof-forma-vol').val(data.forma_vol).trigger('input');
                // Sublingual: forma_tipo guarda o TAMANHO forçado do comprimido (ou vazio = auto) → vai no seletor da embalagem
                if (formaAtual && formaAtual.tipo === 'sublingual') {
                    $('#taof-sub-tam').val(data.forma_tipo || '');
                } else if (data.forma_tipo) {
                    $('#taof-forma-tipo').val(data.forma_tipo);   // restaura Tipo Cápsula / Capacidade do Envelope
                }
                if (data.forma_unidade)  $('#taof-forma-unidade').val(data.forma_unidade);
                if (data.qtde_potes)     $('#taof-qtde-potes').val(data.qtde_potes).trigger('input');
                // Líquido dosado: recupera o Vol/dose do item veículo/QSP importado
                // (ex.: "SOLUÇAO ORAL MAGISTAO 2 ml" → 2). Sem isso o recálculo do editor
                // usava forma_vol como nº de doses e a pesagem saía dobro/metade.
                if (isLiquidForm()) {
                    var qspImp = (data.itens || []).filter(function (i) {
                        return i.tipo === 'mp' && i.is_qsp && parseFloat(i.dose) > 0;
                    })[0];
                    if (qspImp && !parseFloat($('#taof-vol-dose').val())) {
                        $('#taof-vol-dose').val(parseFloat(qspImp.dose));
                    }
                }
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
            });
            // Pincel azul quando o cliente vê um nome diferente do produto (nome_prescricao ≠ nome)
            if ((item.nome_prescricao || '') && item.nome_prescricao !== item.nome) {
                $row.find('.taof-btn-nome-cli').css('color', '#0369a1').attr('title', 'Cliente vê: ' + item.nome_prescricao);
            }
            $row.data({
                'unid-padrao':     unidPadrao,
                'custo-unit':      parseFloat(item.custo_por_unidade || 0),
                'venda-unit':      vendaUnit,
                'diluicao':        parseFloat(item.diluicao || 1),
                'teor':            parseFloat(item.teor || 100),
                'densidade':       1,
                'fp':              parseFloat(item.fp || 1),
                'equiv':           parseFloat(item.equiv || 1),
                'nr-lote':         item.nr_lote || '',
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

        // Custo fixo / acréscimo / desconto — REGRA: o VALOR (R$) manda, o % é só derivado.
        // Carrega sempre pelo R$ salvo (reproduz exato, sem "andar"); o % aparece recalculado.
        // No modal o atendente pode digitar % OU valor pra ajustar.
        if (data.custo_fixo_aplicado !== undefined && data.custo_fixo_aplicado !== null) {
            $('#taof-custo-fixo-inp').val(parseFloat(data.custo_fixo_aplicado).toFixed(2)).data('manual', true);
        }
        // Acréscimo: pelo valor R$ salvo; fallback p/ % (orçamentos antigos sem o R$)
        if (data.acrescimo_aplicado !== undefined && data.acrescimo_aplicado !== null && data.acrescimo_aplicado !== '') {
            $('#taof-acrescimo-val-inp').val(parseFloat(data.acrescimo_aplicado).toFixed(2)).data('manual', true);
        } else if (data.margem_aplicada) {
            $('#taof-acrescimo-pct').val(parseFloat(data.margem_aplicada).toFixed(2));
            $('#taof-acrescimo-val-inp').removeData('manual');
        }
        // Desconto: pelo valor R$ salvo (desconto_fc); fallback p/ %
        if (window._taofFcDesconto != null) {
            $('#taof-desconto-val-inp').val(window._taofFcDesconto.toFixed(2)).data('manual', true);
        } else if (data.desconto_fc !== undefined && data.desconto_fc !== null && data.desconto_fc !== '') {
            $('#taof-desconto-val-inp').val(parseFloat(data.desconto_fc).toFixed(2)).data('manual', true);
        } else if (data.desconto_pct) {
            $('#taof-desconto-pct').val(parseFloat(data.desconto_pct).toFixed(2));
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
            // Self-healing dos orçamentos FC antigos (sem acrescimo_aplicado salvo):
            // re-ancora o Acréscimo(R$) pelo Sub-Total já calculado p/ reproduzir o Final do FC
            // (Sem Desconto = Final + Desconto = bruto). Evita o desconto em dobro da margem antiga.
            // Novas importações já gravam acrescimo_aplicado, então não caem aqui.
            if (window._taofFcFinal != null &&
                (data.acrescimo_aplicado === undefined || data.acrescimo_aplicado === null || data.acrescimo_aplicado === '')) {
                var _sub  = window._taofSubtotal || 0;
                var _desc = (window._taofFcDesconto != null) ? window._taofFcDesconto : 0;
                $('#taof-acrescimo-val-inp').val((window._taofFcFinal + _desc - _sub).toFixed(2)).data('manual', true);
                if (window._taofFcDesconto != null) $('#taof-desconto-val-inp').val(_desc.toFixed(2)).data('manual', true);
                calcularTotais();
            }
            _loadingEdit = false;
            // Envelope: excipiente base na QSP; sublingual: base + blister (marca a linha carregada como dinâmica)
            setTimeout(garantirExcipienteEnvelope, 150); setTimeout(garantirBaseSublingual, 150); setTimeout(garantirBlisterSublingual, 170);
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
