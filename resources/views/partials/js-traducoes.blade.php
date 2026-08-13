{{--
    O dicionário para o JavaScript — o mesmo dos ecrãs, sem cópia.

    O POS trabalha OFFLINE, e é isso que manda no desenho:

      · o dicionário vai INLINE na página, e não num ficheiro à parte. Um
        /js/traducoes.js seria mais limpo, mas passava a ser mais um recurso
        que o service worker tem de cachear e invalidar — e num caixa que
        abre o POS sem rede, um recurso em falta é um POS em inglês a meio de
        uma venda. Inline, o dicionário é cacheado com a própria página, pelo
        mesmo mecanismo, ao mesmo tempo. Não há como um existir sem o outro;

      · os ficheiros de /js (pwa-invoicing.js, pos-offline-ticket.js) são
        carregados DEPOIS deste bloco no layout, portanto já encontram o
        window.__ definido;

      · em português não se emite dicionário nenhum: as chaves SÃO o texto
        português, e o __ devolve a chave. Zero bytes para a maioria das
        empresas.

    CONSEQUÊNCIA QUE VALE A PENA SABER: trocar de língua exige estar online
    uma vez. A página em cache traz o dicionário da língua com que foi
    cacheada; a troca só chega ao POS offline depois de o service worker
    voltar a buscar a página. É o comportamento certo — o contrário seria
    guardar três dicionários em cada página.
--}}
@php
    $linguaJs = app()->getLocale();
    $dicionarioJs = [];

    if ($linguaJs !== 'pt') {
        $ficheiro = base_path("lang/{$linguaJs}.json");
        $dicionarioJs = is_file($ficheiro)
            ? (json_decode(file_get_contents($ficheiro), true) ?: [])
            : [];
    }
@endphp
<script>
(function () {
    'use strict';

    window.SOS_LINGUA = @json($linguaJs);

    {{-- (object) e não o array cru: um array PHP vazio serializa para [] e não
         para {}. Funcionava por acaso — []['Guardar'] é undefined e o __
         devolvia a chave na mesma — mas um dicionário indexado por texto é um
         objecto, e o dia em que alguém fizesse Object.keys() sobre isto tinha
         uma surpresa. --}}
    window.SOS_TRADUCOES = @json((object) $dicionarioJs);

    /**
     * O mesmo __() do PHP, com as mesmas regras.
     *
     *   __('Guardar')                          -> 'Save'
     *   __('Fatura :n criada', {n: 12})        -> 'Invoice 12 created'
     *
     * Sem tradução, devolve a chave — que é o português. Um POS que fale
     * português no meio do inglês é feio; um POS que mostre
     * 'invoicing.button.save' a um caixa é inutilizável.
     */
    window.__ = function (chave, substituicoes) {
        var texto = window.SOS_TRADUCOES[chave] || chave;

        if (substituicoes) {
            Object.keys(substituicoes).forEach(function (nome) {
                texto = texto.split(':' + nome).join(substituicoes[nome]);
            });
        }

        return texto;
    };

    /**
     * Plurais, como o trans_choice.
     *
     *   __n(':n produto|:n produtos', 3, {n: 3})  -> '3 products'
     *
     * O ZERO não se comporta igual nas três línguas, e é o erro que passa
     * despercebido porque em português está certo:
     *
     *   pt:  0 produtos   1 produto    2 produtos   -> plural no zero
     *   en:  0 products   1 product    2 products   -> plural no zero
     *   fr:  0 produit    1 produit    2 produits   -> SINGULAR no zero
     *
     * Um carrinho vazio em francês dizia "0 produits". É a mesma regra que o
     * Laravel aplica do lado do PHP; aqui está escrita à mão porque o
     * JavaScript não traz as regras de plural do framework consigo.
     *
     * Duas formas chegam para estas três línguas. Russo ou árabe precisariam
     * de mais — quando isso acontecer, é aqui que se muda.
     */
    window.__n = function (chave, contagem, substituicoes) {
        var traduzido = window.SOS_TRADUCOES[chave] || chave;
        var formas = traduzido.split('|');

        var singular = (window.SOS_LINGUA === 'fr')
            ? (Math.abs(contagem) < 2)
            : (contagem === 1);

        var escolhida = (singular ? formas[0] : (formas[1] || formas[0]));

        if (substituicoes) {
            Object.keys(substituicoes).forEach(function (nome) {
                escolhida = escolhida.split(':' + nome).join(substituicoes[nome]);
            });
        }

        return escolhida;
    };
})();
</script>
