import type { ReactNode } from 'react';
import { CARTAO, cls } from './tokens';

/**
 * Uma caixa. Sem gradiente no cabeçalho — o título já diz o que é.
 */
export function Cartao({ titulo, accoes, children, semPadding = false }: {
    titulo?: ReactNode;
    accoes?: ReactNode;
    children: ReactNode;
    semPadding?: boolean;
}) {
    return (
        <section className={cls(CARTAO, 'overflow-hidden')}>
            {(titulo || accoes) && (
                <header className="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-3.5">
                    {typeof titulo === 'string' ? (
                        <h2 className="text-sm font-bold tracking-tight text-slate-800">{titulo}</h2>
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
