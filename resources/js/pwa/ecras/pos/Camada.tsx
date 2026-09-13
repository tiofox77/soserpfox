import { useCallback, useSyncExternalStore, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

/**
 * TUDO O QUE É `position: fixed` NO BALCÃO SAI PARA O `<body>`.
 *
 * O ecrã vive dentro da casca, numa caixa com animação de entrada. Com
 * `animation-fill-mode: both` essa animação deixava um contexto de
 * empilhamento para sempre, e o `z-index` de um filho só valia lá dentro — o
 * carrinho (z-50) e as folhas (z-70) ficavam POR BAIXO do menu de baixo (z-40)
 * e o «Finalizar Venda» tapado no telemóvel (medido no Chromium). A casca
 * passou a `backwards`, que não prende nada; o portal fica como segunda
 * defesa, porque o balcão é o último sítio onde um botão tapado se pode dar.
 *
 * Um portal põe estas camadas ao nível do `<body>`, onde o `z-index` quer
 * dizer o que diz. O React continua a tratá-las como filhas do ecrã
 * (contexto, eventos) — só o sítio no DOM muda.
 */
export function Camada({ children }: { children: ReactNode }) {
    return createPortal(children, document.body);
}

/**
 * O ecrã é largo (≥ 1024px, o `lg` do Tailwind)?
 *
 * O carrinho é uma coluna no ecrã largo e uma folha que sobe no telemóvel. Com
 * o portal as duas formas já não podem ser a mesma caixa com classes `lg:` —
 * desenha-se uma OU a outra, e nunca as duas (ids e campos repetidos).
 */
export function useEcraLargo(consulta = '(min-width: 1024px)'): boolean {
    const subscrever = useCallback((avisar: () => void) => {
        const m = window.matchMedia(consulta);
        m.addEventListener('change', avisar);

        return () => m.removeEventListener('change', avisar);
    }, [consulta]);

    return useSyncExternalStore(subscrever, () => window.matchMedia(consulta).matches, () => false);
}
