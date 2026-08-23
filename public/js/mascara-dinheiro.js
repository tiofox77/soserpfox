/*
 * Máscara de dinheiro para os inputs de preço/valores dos documentos e do POS.
 *
 * Só formata a EXIBIÇÃO (1.234,56). O valor que segue para o servidor é lido lá
 * por App\Helpers\MoneyHelper::parse — de propósito: um preço é dinheiro num
 * documento fiscal e não pode depender de o JS ter corrido. Aqui a máscara
 * agrupa os milhares à medida que se escreve (mesmo sem separadores) e aceita
 * que o utilizador escreva o separador decimal.
 *
 * Alvo: qualquer <input data-moeda> com data-milhar / data-decimal / data-casas.
 * O listener é DELEGADO no documento para sobreviver aos re-render do Livewire
 * (morphdom), que troca os nós sem voltar a ligar handlers.
 */
(function () {
    'use strict';

    function cfgDe(el) {
        return {
            milhar: el.getAttribute('data-milhar') || '.',
            decimal: el.getAttribute('data-decimal') || ',',
            casas: parseInt(el.getAttribute('data-casas') || '2', 10),
        };
    }

    function agrupar(intStr, milhar) {
        return intStr.replace(/\B(?=(\d{3})+(?!\d))/g, milhar);
    }

    /**
     * Formata enquanto se escreve: agrupa milhares e só aceita como decimais o
     * separador decimal CONFIGURADO (a vírgula, no formato Angola).
     *
     * O separador de MILHARES que o utilizador escreva (o ponto) é IGNORADO — a
     * máscara agrupa sozinha. Era aqui o bug: escrever "100." era lido como
     * "100," (decimais) e a partir das 2 casas os dígitos eram recusados, o que
     * dava a sensação de o campo travar.
     */
    function mascara(valor, cfg) {
        if (valor == null) return '';
        var dec = cfg.decimal;

        // Manter só dígitos e o separador decimal; tudo o resto (milhar, espaço,
        // moeda, o outro separador) desaparece.
        var limpo = '';
        var s = String(valor);
        for (var i = 0; i < s.length; i++) {
            var ch = s[i];
            if (ch >= '0' && ch <= '9') limpo += ch;
            else if (ch === dec) limpo += dec;
        }
        if (limpo === '') return '';

        // A PRIMEIRA ocorrência do decimal separa a parte inteira das casas.
        var idx = limpo.indexOf(dec);
        var inteiro, decimal = '', temSep = false;
        if (idx >= 0) {
            temSep = true;
            inteiro = limpo.slice(0, idx);
            decimal = limpo.slice(idx + 1).split(dec).join('').slice(0, cfg.casas);
        } else {
            inteiro = limpo;
        }

        inteiro = inteiro.replace(/^0+(?=\d)/, '');
        if (inteiro === '') inteiro = '0';

        var out = agrupar(inteiro, cfg.milhar);
        if (temSep && cfg.casas > 0) {
            out += cfg.decimal + decimal;
        }
        return out;
    }

    /** Lê um valor mascarado e devolve Number, ciente do formato (mesma regra do MoneyHelper::parse). */
    function parse(valor, cfg) {
        if (valor == null || valor === '') return 0;
        var dec = cfg.decimal;
        var s = String(valor), out = '';
        for (var i = 0; i < s.length; i++) {
            var ch = s[i];
            if (ch >= '0' && ch <= '9') out += ch;
            else if (ch === dec) out += '.';   // decimal -> ponto
            else if (ch === '-') out += '-';
            // milhar e tudo o resto: ignorado
        }
        // garantir um só ponto decimal
        var p = out.indexOf('.');
        if (p >= 0) out = out.slice(0, p + 1) + out.slice(p + 1).replace(/\./g, '');
        var n = parseFloat(out);
        return isNaN(n) ? 0 : n;
    }

    /** Formata um Number já fechado (com as casas todas): 10000 → "10.000,00". */
    function format(n, cfg) {
        var neg = n < 0;
        n = Math.abs(Number(n) || 0);
        var fixo = n.toFixed(cfg.casas);
        var partes = fixo.split('.');
        var out = agrupar(partes[0], cfg.milhar);
        if (cfg.casas > 0) out += cfg.decimal + partes[1];
        return (neg ? '-' : '') + out;
    }

    // Escrever → mascara em directo.
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (el && el.matches && el.matches('input[data-moeda]')) {
            var pos = el.value.length - el.selectionStart; // distância ao fim
            el.value = mascara(el.value, cfgDe(el));
            // Repor o cursor a uma distância equivalente do fim (evita saltar
            // sempre para o extremo em edições no meio).
            try {
                var novo = Math.max(0, el.value.length - pos);
                el.setSelectionRange(novo, novo);
            } catch (_) { /* inputs sem selection range */ }
        }
    });

    // Ao sair, completar as casas decimais para leitura (10.000 → 10.000,00).
    document.addEventListener('blur', function (e) {
        var el = e.target;
        if (el && el.matches && el.matches('input[data-moeda]')) {
            var cfg = cfgDe(el);
            el.value = format(parse(el.value, cfg), cfg);
        }
    }, true);

    window.SosMoeda = { mascara: mascara, parse: parse, format: format };
})();
