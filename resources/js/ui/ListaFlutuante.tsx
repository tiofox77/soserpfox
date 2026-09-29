import { useLayoutEffect, useState, type CSSProperties, type HTMLAttributes, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';

import { RAIO, cls } from './tokens';

/**
 * A LISTA QUE SE ABRE POR BAIXO DE UM CAMPO — por cima de tudo, presa ao campo.
 *
 * As listas de sugestões eram um `absolute` dentro do campo, e o `Cartao` tem
 * `overflow-hidden` (os cantos redondos). A lista ficava cortada pelo fundo do
 * cartão: no recibo via-se meio nome do primeiro cliente e mais nada
 * (29/09/2026). Aqui a lista sai do cartão:
 *
 * - numa página, vai para o `body` com `position: fixed`, nas coordenadas do
 *   campo;
 * - dentro de uma janela (`<dialog>`, que está na camada de topo e tem
 *   `overflow-hidden`), vai para o próprio `<dialog>` — fora da zona que faz
 *   scroll — com `position: absolute` medido a partir dele.
 *
 * Se por baixo não há espaço, abre para cima. Acompanha o scroll e o
 * redimensionar. Carregar na lista (incluindo na barra de scroll) não tira o
 * foco ao campo — era isso que a fechava a meio de a percorrer.
 */
export function ListaFlutuante({
    ancora,
    children,
    alturaMaxima = 240,
    como = 'ul',
    className,
    onMouseDown,
    ...resto
}: {
    /** O campo (ou o invólucro dele) de onde a lista pende. */
    ancora: RefObject<HTMLElement | null>;
    children: ReactNode;
    alturaMaxima?: number;
    como?: 'ul' | 'div';
    className?: string;
} & Omit<HTMLAttributes<HTMLElement>, 'style' | 'className'>) {
    const [alvo, porAlvo] = useState<HTMLElement | null>(null);
    const [posicao, porPosicao] = useState<CSSProperties>({ position: 'fixed', top: -9999, left: -9999, visibility: 'hidden' });

    useLayoutEffect(() => {
        const campo = ancora.current;
        if (!campo) return;

        const janela = campo.closest('dialog');
        porAlvo(janela ?? document.body);

        let pedido = 0;
        let ultima = '';
        const medir = () => {
            const r = campo.getBoundingClientRect();
            const caixa = janela
                ? janela.getBoundingClientRect()
                : { top: 0, left: 0, bottom: window.innerHeight, height: window.innerHeight };

            const abaixo = caixa.bottom - r.bottom - 8;
            const acima = r.top - caixa.top - 8;
            const paraCima = abaixo < Math.min(alturaMaxima, 160) && acima > abaixo;
            const altura = Math.max(96, Math.min(alturaMaxima, paraCima ? acima : abaixo));

            const nova: CSSProperties = {
                position: janela ? 'absolute' : 'fixed',
                left: r.left - caixa.left,
                width: r.width,
                maxHeight: altura,
                zIndex: janela ? 30 : 1000,
                ...(paraCima
                    ? { bottom: caixa.height - (r.top - caixa.top) + 4 }
                    : { top: r.bottom - caixa.top + 4 }),
            };

            // Só re-desenha quando alguma coisa mudou de facto.
            const chave = JSON.stringify(nova);
            if (chave !== ultima) {
                ultima = chave;
                porPosicao(nova);
            }
        };

        /*
         * MEDE-SE A CADA QUADRO enquanto a lista está aberta. Escutar o scroll
         * e o redimensionar não chegava: a janela abre a crescer (animação de
         * escala) e muda de sítio quando o conteúdo muda de altura, sem que
         * nenhum desses eventos dispare — a lista ficava estreita e fora do
         * sítio. Um `getBoundingClientRect` por quadro, só enquanto está
         * aberta, não custa nada.
         */
        const passo = () => {
            medir();
            pedido = window.requestAnimationFrame(passo);
        };

        medir();
        pedido = window.requestAnimationFrame(passo);

        return () => window.cancelAnimationFrame(pedido);
    }, [ancora, alturaMaxima]);

    if (!alvo) return null;

    const Elemento = como;

    return createPortal(
        <Elemento
            {...resto}
            style={posicao}
            onMouseDown={(e) => {
                // Não tirar o foco ao campo: o `blur` fechava a lista.
                e.preventDefault();
                onMouseDown?.(e);
            }}
            className={cls('overflow-y-auto border border-slate-200 bg-white shadow-lg', RAIO, className)}
        >
            {children}
        </Elemento>,
        alvo,
    );
}
