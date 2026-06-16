/* TAO Formulas — Orcamento v1.7 */
(function ($) {
    'use strict';
    if (!document.getElementById('taof-orc-form')) return;

    var formasMap  = window.taofOrcFormasMap || {};
    var capsulas   = window.taofCapsulas || [];
    var formaAtual = null;
    var ajaxUrl    = taoFormula.ajaxUrl;
    var nonce      = taoFormula.nonce;

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
        if ($row.hasClass('taof-row-qsp')) {
            // Linha QSP: quantidade e custo calculados em calcularTotais
            calcularTotais();
            return;
        }
        var dose      = parseFloat($row.find('.taof-orc-dose').val()) || 0;
        var doseUnit  = $row.find('.taof-orc-dose-unit').val() || 'mg';
        var fp        = parseFloat($row.data('fp')) || 1;
        var custoUnit = parseFloat($row.data('custo-unit')) || 0;
        var unidPadrao= $row.data('unid-padrao') || 'g';
        var diluicao  = parseFloat($row.data('diluicao')) || 1;
        var teor      = parseFloat($row.data('teor')) || 100;
        var densidade = parseFloat($row.data('densidade')) || 1;
        var mult      = getMultiplicador();
        var isCap     = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
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
        $row.data({
            subtotal: subtotal, 'qtd-total-g': qtd_total_g,
            'qtd-em-padrao': qtd_em_unid, 'qtd-unit': unidPadrao,
            dose: dose, 'dose-unit': doseUnit, 'volapa-ul': volapa_uL
        });
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

        var custoUnit  = parseFloat($row.data('custo-unit'))  || 0;
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var qtdEmUnid  = unidPadrao === 'g' ? qspG : unidPadrao === 'mg' ? qspMg : qspG;
        var subtotal   = qtdEmUnid * custoUnit;

        $row.find('.taof-orc-qtd-total').text(fmt(qspG, 3) + ' g (QSP)');
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qspG, 'volapa-ul': 0 });
    }

    function _qspCapsula($row) {
        var r = calcularCapsulaIdeal();
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

        var custoUnit  = parseFloat($row.data('custo-unit')) || 0;
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var qtdEmUnid  = unidPadrao === 'g' ? qspTotalG : qspTotalMg;
        var subtotal   = qtdEmUnid * custoUnit;

        $row.find('.taof-orc-qtd-total').text(
            fmt(qspTotalG, 4) + ' g (' + fmt(qspVOLAPAPerCap, 1) + ' µL/caps QSP)'
        );
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));
        $row.data({ subtotal: subtotal, 'qtd-total-g': qspTotalG, 'volapa-ul': qspVOLAPAPerDose });
    }

    // ── Capsula ideal: filtra pelo tipo selecionado ───────────────────
    // Usa somente linhas NAO-QSP para soma do VOLAPA
    function calcularCapsulaIdeal() {
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

    function sugerirCapsula() {
        var isCap = formaAtual && (formaAtual.tipo === 'cap' || formaAtual.tipo === 'duo_cap');
        if (!isCap || !capsulas.length) { $('#taof-cap-sugerida').hide(); return; }

        var r = calcularCapsulaIdeal();
        if (!r) { $('#taof-cap-sugerida').hide(); return; }

        var c   = r.cap, n = r.nPerDose, pct = r.pct;
        var cor = pct > 100 ? '#dc2626' : (pct > 85 ? '#d97706' : '#16a34a');
        var tipo = c.tipo.charAt(0).toUpperCase() + c.tipo.slice(1).toLowerCase();
        var doses = n > 1
            ? ' &mdash; <strong style="color:#d97706">' + n + ' c&aacute;ps./dose</strong>'
            : '';
        $('#taof-cap-sugerida').html(
            '&#128138; <strong>' + tipo + ' N&ordm;&nbsp;' + c.numero + '</strong> (' + c.vol_ul + '&nbsp;&micro;L)' +
            doses +
            ' &mdash; <span style="color:' + cor + '">' +
            fmt(r.volapa, 1) + '&nbsp;/&nbsp;' + fmt(r.volTotal, 0) +
            '&nbsp;&micro;L (' + fmt(pct, 0) + '%)</span>'
        ).show();
    }

    // ── Totais ────────────────────────────────────────────────────────
    function calcularTotais() {
        // 1. Atualiza linha QSP (sem recursao)
        atualizarQSPRow();

        // 2. Soma todas as linhas (incluindo QSP ja atualizada)
        var calculado = 0;
        $('#taof-itens-body .taof-item-row').each(function () {
            calculado += parseFloat($(this).data('subtotal')) || 0;
        });
        $('#taof-emb-body .taof-emb-row').each(function () {
            calculado += parseFloat($(this).data('subtotal-emb')) || 0;
        });

        var custoFixo = getCustoFixo();
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
        $('#taof-res-final').html('<strong>R$ ' + fmt(final) + '</strong>');

        sugerirCapsula();
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
            } else {
                $('#taof-forma-tipo').empty();
                $colTipo.hide();
            }

            // Unidade
            popularUnidade(formaAtual.tipo);

            // Custo fixo editavel por orcamento
            $('#taof-custo-fixo-inp').val(parseFloat(formaAtual.custoFixo || 0).toFixed(2));
        } else {
            $('#taof-forma-vol').val('').attr('placeholder', 'Ex: 30');
            $('#taof-forma-tipo').empty();
            $colTipo.hide();
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
        var clone = $(document.getElementById('taof-item-tpl').content.cloneNode(true));
        var $row  = clone.find('tr');
        $row.data({ subtotal: 0, 'volapa-ul': 0, 'qtd-total-g': 0 });
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
        var $dd    = $row.find('.taof-ac-dropdown');
        var $idFld = $row.find('.taof-orc-ativo-id');
        var timer  = null;

        $inp.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            if (q.length < 2) { $dd.hide().empty(); return; }
            timer = setTimeout(function () {
                $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: q, grupo: 'M' },
                function (resp) {
                    $dd.empty();
                    if (!resp.success || !resp.data.length) {
                        $dd.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhum resultado.</div>');
                    } else {
                        $.each(resp.data, function (_, a) {
                            var custo = parseFloat(a.custo_por_unidade) > 0
                                ? 'R$ ' + fmt(a.custo_por_unidade, 4) + '/' + (a.unidade_padrao || 'g')
                                : 'sem custo';
                            var info = (a.codigo_fc ? '[' + a.codigo_fc + '] ' : '') + custo;
                            if (a.diluicao && a.diluicao != 1) info += ' · dil 1:' + a.diluicao;
                            var $item = $('<div class="taof-ac-item">').html(
                                '<span>' + $('<span>').text(a.nome).html() + '</span>' +
                                '<small>' + $('<span>').text(info).html() + '</small>'
                            );
                            $item.on('mousedown', function (e) { e.preventDefault(); selecionarAtivo(a); });
                            $dd.append($item);
                        });
                    }
                    $dd.show();
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
                'ativo-id':    a.id,
                'ativo-nome':  a.nome,
                'codigo-fc':   a.codigo_fc  || '',
                'unid-padrao': a.unidade_padrao,
                'custo-unit':  a.custo_por_unidade,
                'diluicao':    parseFloat(a.diluicao)   || 1,
                'teor':        parseFloat(a.teor)        || 100,
                'densidade':   parseFloat(a.densidade)   || 1,
                'fp':          parseFloat(a.fator_perda) || 1,
            });
            var u = a.unidade_padrao;
            if (['mg', 'mcg', 'g', 'UI', 'UFC', 'ml'].indexOf(u) === -1) u = 'mg';
            $row.find('.taof-orc-dose-unit').val(u);
            $dd.hide().empty();
            calcularLinha($row);
        }

        $inp.on('blur',  function () { setTimeout(function () { $dd.hide(); }, 150); });
        $inp.on('focus', function () { if ($dd.children().length) $dd.show(); });
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
                    $dd.show();
                });
            }, 280);
        });
        $inp.on('blur',  function () { setTimeout(function () { $dd.hide(); }, 150); });
        $inp.on('focus', function () { if ($dd.children().length) $dd.show(); });
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
