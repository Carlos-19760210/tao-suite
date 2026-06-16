/* TAO Formulas — Orcamento Manual (calculo identico ao Formula Certa) */
(function ($) {
    'use strict';

    if (!document.getElementById('taof-orc-form')) return;

    var formas     = window.taofOrcFormas || {};
    var formaAtual = null;
    var ajaxUrl    = taoFormula.ajaxUrl;
    var nonce      = taoFormula.nonce;

    // ── Conversao para mg (pivot) ─────────────────────────────────────────────
    // UI nao tem conversao direta; para UI use calculo via diluicao/potencia
    var paraMg = { mg: 1, g: 1000, mcg: 0.001, ml: 1000 };

    function toMg(valor, unit) {
        return valor * (paraMg[unit] || 1);
    }

    // ── Formato BR ────────────────────────────────────────────────────────────
    function fmt(n, dec) {
        dec = (dec !== undefined) ? dec : 2;
        return parseFloat(n || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: dec, maximumFractionDigits: dec
        });
    }

    // ── Multiplicador (n_capsulas para cap; volume para gel/sol) ─────────────
    function getMultiplicador() {
        if (!formaAtual) return 1;
        return (formaAtual.tipo === 'cap') ? (formaAtual.nCapsulas || 1) : (formaAtual.volume || 1);
    }

    // ── CALCULO PRINCIPAL (Formula Certa logic) ───────────────────────────────
    // dose_prescrita (por capsula ou por mL do volume total)
    // diluicao  = FC03000.DILUICAO (ex: T3 1:1000 → 1000; puro → 1)
    // teor      = FC03000.TEOR em % (100 = puro; 98 = 98% puro → precisa 2% a mais)
    // densidade = g/mL (para calcular VOLAPA)
    // custo     = R$ por unidade_padrao
    //
    // Para capsulas:
    //   qtd_por_cap_mg = toMg(dose, doseUnit) × diluicao / (teor/100)
    //   qtd_total_g    = qtd_por_cap_mg × n_caps / 1000
    //   volapa_uL      = qtd_por_cap_mg / densidade  (µL por capsula)
    //   custo_total    = qtd_total_g × custo_g  (se unid_padrao=g)
    //
    // Para gels/solucoes:
    //   qtd_total_g    = toMg(dose, doseUnit) × diluicao / (teor/100) × volume_total / 1000
    //   (dose = qtd por mL ou por g de formula)
    function calcularLinha($row) {
        var dose       = parseFloat($row.find('.taof-orc-dose').val()) || 0;
        var doseUnit   = $row.find('.taof-orc-dose-unit').val() || 'mg';
        var fp         = parseFloat($row.find('.taof-orc-fp').val()) || 1;
        var custoUnit  = parseFloat($row.data('custo-unit')) || 0;  // R$/unidade_padrao
        var unidPadrao = $row.data('unid-padrao') || 'g';
        var diluicao   = parseFloat($row.data('diluicao')) || 1;
        var teor       = parseFloat($row.data('teor')) || 100;
        var densidade  = parseFloat($row.data('densidade')) || 1;
        var mult       = getMultiplicador();  // n_caps ou volume

        var isCap = formaAtual && formaAtual.tipo === 'cap';

        var dose_mg_unit;   // mg da dose prescrita por capsula (ou por mL)
        var isUI = (doseUnit === 'UI');

        if (isUI) {
            // Dosagem em UI: informamos que o calculo usa diluicao como escala
            // qtd_g = dose_UI * diluicao * mult / potencia_UI_per_g
            // Como nao temos potencia_UI_per_g no frontend ainda,
            // usamos custo_por_unidade que ja esta em R$/g, e informamos limitacao.
            // Por ora, converte 1 UI = 1 mg para nao travar o calculo (marcado como estimativa).
            dose_mg_unit = dose;
            $row.find('.taof-orc-volapa').text('UI — est.');
        } else {
            dose_mg_unit = toMg(dose, doseUnit);
        }

        // Quantidade por unidade (capsula ou por mL de gel), ja com diluicao e teor
        var qtd_mg_per_unit = dose_mg_unit * diluicao / (teor / 100);

        // Quantidade total no lote (em mg), multiplicado pela forma
        var qtd_total_mg = qtd_mg_per_unit * mult * fp;

        // Converte para g (unidade base de custo)
        var qtd_total_g = qtd_total_mg / 1000;

        // Volume aparente por capsula (µL) — mostra ocupacao na capsula
        var volapa_uL = (isCap && !isUI && densidade > 0) ? (qtd_mg_per_unit / densidade) : null;

        // Custo: unidade_padrao pode ser 'g', 'mg', 'mcg', 'UI'
        var qtd_em_unid_padrao;
        if (unidPadrao === 'g')   qtd_em_unid_padrao = qtd_total_g;
        else if (unidPadrao === 'mg')  qtd_em_unid_padrao = qtd_total_mg;
        else if (unidPadrao === 'mcg') qtd_em_unid_padrao = qtd_total_mg * 1000;
        else                           qtd_em_unid_padrao = qtd_total_g;

        var subtotal = qtd_em_unid_padrao * custoUnit;

        // Atualiza display
        var qtdLabel = (unidPadrao === 'g')  ? fmt(qtd_total_g, 4) + ' g'
                     : (unidPadrao === 'mg') ? fmt(qtd_total_mg, 3) + ' mg'
                     : fmt(qtd_em_unid_padrao, 3) + ' ' + unidPadrao;
        $row.find('.taof-orc-qtd-total').text(qtdLabel);
        $row.find('.taof-orc-subtotal').text('R$ ' + fmt(subtotal));

        if (volapa_uL !== null) {
            $row.find('.taof-orc-volapa').text(fmt(volapa_uL, 1) + ' µL');
        }

        // Salva para o save e para o calcularTotais
        $row.data('subtotal', subtotal);
        $row.data('qtd-total-g', qtd_total_g);
        $row.data('qtd-em-padrao', qtd_em_unid_padrao);
        $row.data('qtd-unit', unidPadrao);
        $row.data('dose', dose);
        $row.data('dose-unit', doseUnit);
        $row.data('diluicao', diluicao);
        $row.data('teor', teor);
        $row.data('fp', fp);
        $row.data('volapa-ul', volapa_uL || 0);

        calcularTotais();
    }

    // ── Totais (MPs + Embalagens) ─────────────────────────────────────────────
    function calcularTotais() {
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

        // Aviso de volume de capsula
        atualizarAvisoCapsula();
    }

    // ── Aviso de volume de capsula ────────────────────────────────────────────
    function atualizarAvisoCapsula() {
        if (!formaAtual || formaAtual.tipo !== 'cap' || !formaAtual.volCapUl) {
            $('#taof-cap-aviso').hide(); return;
        }
        var volTotal = 0;
        $('#taof-itens-body .taof-item-row').each(function () {
            volTotal += parseFloat($(this).data('volapa-ul')) || 0;
        });
        var cap = formaAtual.volCapUl * (formaAtual.ftenchcap || 1);
        var pct = cap > 0 ? (volTotal / cap * 100) : 0;
        var cor = pct > 100 ? '#dc2626' : (pct > 90 ? '#d97706' : '#16a34a');
        $('#taof-cap-aviso').html(
            'Volume da capsula: <strong style="color:' + cor + '">' +
            fmt(volTotal, 1) + ' / ' + fmt(cap, 0) + ' µL (' + fmt(pct, 0) + '%)</strong>'
        ).show();
    }

    // ── Forma select ──────────────────────────────────────────────────────────
    $('#taof-forma-sel').on('change', function () {
        var id = $(this).val();
        formaAtual = id ? (formas[id] || null) : null;

        var info = '';
        if (formaAtual) {
            if (formaAtual.tipo === 'cap') {
                info = formaAtual.nCapsulas + ' caps';
                if (formaAtual.tipoCapsula) info += ' ' + formaAtual.tipoCapsula + ' ' + (formaAtual.numeroCapsula || '');
                if (formaAtual.volCapUl)    info += ' (' + formaAtual.volCapUl + ' µL)';
            } else {
                info = formaAtual.volume + ' ' + formaAtual.unidVolume;
            }
            info += ' | Custo fixo: R$ ' + fmt(formaAtual.custoFixo);
            info += ' | Margem: ' + fmt(formaAtual.margemPct, 1) + '%';
            $('#taof-margem-input').val(formaAtual.margemPct);

            var multTxt = (formaAtual.tipo === 'cap')
                ? 'Multiplicador: x ' + formaAtual.nCapsulas + ' capsulas'
                : 'Multiplicador: x ' + formaAtual.volume + ' ' + formaAtual.unidVolume;
            $('#taof-forma-mult-txt').text(multTxt);
            $('#taof-forma-mult-badge').show();
        } else {
            $('#taof-forma-mult-badge').hide();
        }
        $('#taof-forma-info').text(info);

        $('#taof-itens-body .taof-item-row').each(function () { calcularLinha($(this)); });
    });

    $('#taof-margem-input').on('input', calcularTotais);

    // ── Adicionar linha de MP ─────────────────────────────────────────────────
    $('#taof-btn-add-item').on('click', function () {
        var tpl   = document.getElementById('taof-item-tpl');
        var clone = $(tpl.content.cloneNode(true));
        var $row  = clone.find('tr');
        $row.data('subtotal', 0).data('volapa-ul', 0);
        $('#taof-itens-body').append(clone);
        initRow($row);
    });

    function initRow($row) {
        $row.on('input change', '.taof-orc-dose, .taof-orc-dose-unit, .taof-orc-fp', function () {
            calcularLinha($row);
        });
        $row.on('click', '.taof-btn-del-item', function () {
            $row.remove(); calcularTotais();
        });
        initAutocomplete($row);
    }

    // ── Autocomplete MPs ──────────────────────────────────────────────────────
    function initAutocomplete($row) {
        var $inp   = $row.find('.taof-orc-ativo-search');
        var $res   = $row.find('.taof-autocomplete-results');
        var $idFld = $row.find('.taof-orc-ativo-id');
        var timer  = null;

        $inp.on('input', function () {
            var q = $(this).val().trim();
            clearTimeout(timer);
            if (q.length < 2) { $res.hide().empty(); return; }
            timer = setTimeout(function () { buscarAtivos(q); }, 300);
        });

        function buscarAtivos(q) {
            $.getJSON(ajaxUrl, { action: 'tao_formula_search_ativos', nonce: nonce, q: q, grupo: 'M' },
            function (resp) {
                $res.empty();
                if (!resp.success || !resp.data.length) {
                    $res.append('<div class="taof-ac-item" style="color:#94a3b8">Nenhum resultado.</div>');
                } else {
                    $.each(resp.data, function (_, a) {
                        var info = a.unidade_padrao;
                        if (a.diluicao && a.diluicao != 1) info += ' 1:' + a.diluicao;
                        if (a.teor && a.teor != 100) info += ' ' + a.teor + '%';
                        info += ' · R$ ' + fmt(a.custo_por_unidade, 4) + '/' + a.unidade_padrao;
                        var $item = $('<div class="taof-ac-item">').text(a.nome)
                            .append($('<small>').text(info));
                        $item.on('mousedown', function (e) { e.preventDefault(); selecionarAtivo(a); });
                        $res.append($item);
                    });
                }
                $res.show();
            });
        }

        function selecionarAtivo(a) {
            $inp.val(a.nome);
            $idFld.val(a.id);

            $row.data('ativo-id',   a.id);
            $row.data('ativo-nome', a.nome);
            $row.data('unid-padrao',a.unidade_padrao);
            $row.data('custo-unit', a.custo_por_unidade);
            $row.data('diluicao',   a.diluicao   || 1);
            $row.data('teor',       a.teor        || 100);
            $row.data('densidade',  a.densidade   || 1);
            $row.data('fp',         a.fator_perda || 1);

            $row.find('.taof-orc-fp').val(a.fator_perda || 1);

            // Unidade de dose padrao = unidade_padrao do ativo
            var unidSel = a.unidade_padrao;
            if (['mg','mcg','g','UI','ml'].indexOf(unidSel) === -1) unidSel = 'mg';
            $row.find('.taof-orc-dose-unit').val(unidSel);

            // Info: custo e diluicao
            var custoLabel = 'R$ ' + fmt(a.custo_por_unidade, 4) + '/' + a.unidade_padrao;
            if (a.diluicao && a.diluicao != 1)
                custoLabel += ' | dilui 1:' + a.diluicao;
            if (a.teor && a.teor != 100)
                custoLabel += ' | teor ' + a.teor + '%';
            $row.find('.taof-orc-custo-unit-label').text(custoLabel);

            $res.hide().empty();
            calcularLinha($row);
        }

        $inp.on('blur', function () { setTimeout(function () { $res.hide(); }, 150); });
        $inp.on('focus', function () { if ($res.children().length) $res.show(); });
    }

    // ── Embalagens ────────────────────────────────────────────────────────────
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
        $row.on('click', '.taof-btn-del-emb', function () { $row.remove(); calcularTotais(); });
        initEmbAutocomplete($row);
    }

    function initEmbAutocomplete($row) {
        var $inp   = $row.find('.taof-emb-search');
        var $res   = $row.find('.taof-autocomplete-results');
        var $idFld = $row.find('.taof-emb-id');
        var timer  = null;

        $inp.on('input', function () {
            var q = $(this).val().trim();
            clearTimeout(timer);
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
                                $inp.val(a.nome);
                                $idFld.val(a.id);
                                $row.data('emb-id', a.id).data('emb-nome', a.nome).data('custo-unit', a.custo_por_unidade);
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
        var tpl   = document.getElementById('taof-emb-tpl');
        var clone = $(tpl.content.cloneNode(true));
        var $row  = clone.find('tr');
        $row.data('subtotal-emb', 0);
        $('#taof-emb-body').append(clone);
        initEmbRow($row);
    });

    // ── Salvar ────────────────────────────────────────────────────────────────
    $('#taof-orc-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#taof-orc-salvar'), $sp = $('.taof-spinner'), $msg = $('.taof-msg');

        var itens = [], ok = true;

        $('#taof-itens-body .taof-item-row').each(function () {
            var $r = $(this);
            if (!$r.data('ativo-id')) { ok = false; return false; }
            itens.push({
                tipo:            'mp',
                ativo_id:        $r.data('ativo-id'),
                nome:            $r.data('ativo-nome'),
                dose:            $r.data('dose'),
                dose_unit:       $r.data('dose-unit'),
                multiplicador:   getMultiplicador(),
                diluicao:        $r.data('diluicao'),
                teor:            $r.data('teor'),
                fp:              $r.data('fp'),
                qtd_total_g:     $r.data('qtd-total-g'),
                qtd_em_padrao:   $r.data('qtd-em-padrao'),
                unidade_padrao:  $r.data('qtd-unit'),
                volapa_ul:       $r.data('volapa-ul'),
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                subtotal:        parseFloat($r.data('subtotal')) || 0
            });
        });

        if (!ok) { alert('Selecione o ativo em todas as linhas antes de salvar.'); return; }

        $('#taof-emb-body .taof-emb-row').each(function () {
            var $r = $(this);
            if (!$r.data('emb-id')) { ok = false; return false; }
            itens.push({
                tipo:            'emb',
                ativo_id:        $r.data('emb-id'),
                nome:            $r.data('emb-nome'),
                quantidade:      parseInt($r.find('.taof-emb-qty').val()) || 0,
                custo_por_unidade: parseFloat($r.data('custo-unit')) || 0,
                subtotal:        parseFloat($r.data('subtotal-emb')) || 0
            });
        });

        if (!ok) { alert('Selecione a embalagem em todas as linhas antes de salvar.'); return; }
        if (!itens.length) { alert('Adicione ao menos um ativo na receita.'); return; }

        var custoFixo = formaAtual ? (formaAtual.custoFixo || 0) : 0;
        var totalIns  = itens.reduce(function (s, i) { return s + (i.subtotal || 0); }, 0);
        var base      = totalIns + custoFixo;
        var margemPct = parseFloat($('#taof-margem-input').val()) || 0;
        var total     = base * (1 + margemPct / 100);

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
            $msg.text('Falha na requisicao.').addClass('err').show();
        });
    });

})(jQuery);
