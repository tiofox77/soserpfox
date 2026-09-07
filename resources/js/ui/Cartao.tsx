import type { ReactNode } from 'react';

import { CARTAO, TRANSICAO, cls } from './tokens';

/**
 * O CARTÃO: o bloco em que tudo neste sistema se arruma.
 *
 * O cabeçalho aceita um ÍCONE porque é assim que o resto da aplicação se lê —
 * uma página com seis blocos brancos iguais obriga a ler os títulos todos para
 * encontrar um; com ícone, encontra-se de relance.
 *
 * A sombra levanta-se um nada ao passar o rato. É pouco de propósito: um
 * cartão não é um botão, e exagerar aqui faria a página inteira a saltar.
 */
export function Cartao({
    titulo,
    subtitulo,
    icone,
    accoes,
    children,
    semPadding = false,
    className,
}: {
    titulo?: ReactNode;
    /**
     * A linha pequena por baixo do título — o que o cartão mede, em palavras.
     * Os cartões do painel em Blade tinham-na («Vencidas contadas à parte»,
     * «Por valor vendido»), e é ela que evita ler o número ao contrário.
     */
    subtitulo?: string;
    icone?: string;
    accoes?: ReactNode;
    children: ReactNode;
    semPadding?: boolean;
    className?: string;
}) {
    return (
        <section className={cls(CARTAO, 'overflow-hidden hover:shadow-md', TRANSICAO, className)}>
            {(titulo || accoes) && (
                <header className="flex items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/60 px-5 py-3.5">
                    {typeof titulo === 'string' ? (
                        <div className="flex min-w-0 items-center gap-2">
                            {icone && (
                                <span className="grid h-7 w-7 flex-none place-items-center rounded-lg bg-indigo-50 text-indigo-600">
                                    <i className={`fas ${icone} text-xs`} aria-hidden="true" />
                                </span>
                            )}
                            <div className="min-w-0">
                                <h2 className="text-sm font-bold tracking-tight text-slate-800">{titulo}</h2>
                                {subtitulo && <p className="text-xs text-slate-500">{subtitulo}</p>}
                            </div>
                        </div>
                    ) : (
                        titulo
                    )}
                    {accoes && <div className="flex items-center gap-2">{accoes}</div>}
                </header>
            )}

            <div className={semPadding ? '' : 'p-5'}>{children}</div>
        </section>
    );
}
