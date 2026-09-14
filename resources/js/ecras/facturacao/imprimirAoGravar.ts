import { useCallback, useState } from 'react';

/**
 * «IMPRIMIR AUTOMATICAMENTE AO GRAVAR» — o interruptor das definições da
 * facturação que a migração para React tinha deixado a falar sozinho.
 *
 * Gravava-se, e nenhum editor o lia. O servidor manda-o agora nas opções de
 * cada editor (`imprimir_ao_gravar`) e, quando está ligado, o editor abre o
 * PDF do documento numa janela nova assim que a gravação volta com sucesso.
 *
 * NUNCA NUM RASCUNHO. O Livewire disparava o evento em qualquer gravação —
 * e o evento nunca teve quem o ouvisse, por isso na prática nunca imprimiu
 * nada. Aqui escolhe-se o que faz sentido: o papel de um rascunho não é
 * documento para entregar a ninguém, e uma factura por emitir impressa é uma
 * factura que não existe na mão de um cliente.
 *
 * O BLOQUEADOR DE POP-UPS. A janela só abre DEPOIS da resposta do servidor —
 * antes disso não há documento nem morada. O browser deixa abrir janelas por
 * uns segundos depois do clique, e quase sempre a gravação cabe nesse tempo;
 * quando não cabe (ou num Safari mais desconfiado), o `window.open` devolve
 * `null` e o ecrã diz que foi bloqueado, com o botão do PDF ao lado. É o que
 * o talão do POS já faz. Abrir uma janela em branco antes do pedido evitava o
 * bloqueio, mas deixava um separador vazio a piscar a cada erro de validação.
 */

/**
 * Abre o PDF numa janela nova. Diz se o browser deixou.
 *
 * Sem `'noopener'` nas características, de propósito: com ele, o
 * `window.open` devolve SEMPRE `null` — e deixava de haver maneira de saber
 * se a janela abriu ou foi bloqueada. A ligação de volta corta-se à mão, que
 * é o que o `noopener` faria.
 */
export function abrirOPapel(pdf: string): boolean {
    const janela = window.open(pdf, '_blank');

    if (!janela) {
        return false;
    }

    try {
        janela.opener = null;
    } catch {
        /* uma janela que já não se deixa mexer também já não chega cá */
    }

    return true;
}

/**
 * O que um editor precisa: dizer que gravou, e saber se o papel foi bloqueado.
 *
 * `ligado` é o `imprimir_ao_gravar` das opções. O `estado` é o que o servidor
 * respondeu — `draft` não imprime; um documento sem estado na resposta (uma
 * nota, um adiantamento) nasce sempre emitido e imprime.
 */
export function useImprimirAoGravar(ligado: boolean | undefined) {
    const [bloqueado, porBloqueado] = useState(false);

    const depoisDeGravar = useCallback(
        (pdf: string | null | undefined, estado?: string | null) => {
            if (!ligado || !pdf || estado === 'draft') {
                porBloqueado(false);

                return;
            }

            porBloqueado(!abrirOPapel(pdf));
        },
        [ligado],
    );

    /** Ao voltar ao formulário («Emitir outra»), o aviso da anterior sai. */
    const esquecer = useCallback(() => porBloqueado(false), []);

    return { bloqueado, depoisDeGravar, esquecer };
}
