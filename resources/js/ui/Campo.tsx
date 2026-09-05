import type { ReactNode } from 'react';
import { cls } from './tokens';

/**
 * UM CAMPO DE FORMULÁRIO: rótulo, o controlo, e o erro do servidor por baixo.
 *
 * Existia em quatro cópias — nos clientes, nos artigos, no emissor e no recibo
 * — que é a duplicação que esta migração veio acabar, não repetir.
 *
 * O ASTERISCO DE OBRIGATÓRIO É `aria-hidden`. Sem isso, o nome acessível do
 * campo passa a ser «Cliente*» e um leitor de ecrã lê «Cliente asterisco». O
 * que se diz a quem ouve é «obrigatório», por extenso, num texto que só existe
 * para ele.
 */
export function Campo({
    etiqueta,
    erro,
    obrigatorio = false,
    className,
    children,
}: {
    etiqueta: string;
    erro?: string[];
    obrigatorio?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <label className={cls('block', className)}>
            <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                {etiqueta}
                {obrigatorio && (
                    <>
                        <span className="ml-0.5 text-red-500" aria-hidden="true">
                            *
                        </span>
                        <span className="sr-only"> (obrigatório)</span>
                    </>
                )}
            </span>

            {children}

            {erro?.[0] && (
                <span role="alert" className="mt-1 block text-xs font-medium text-red-600">
                    {erro[0]}
                </span>
            )}
        </label>
    );
}

/** O rótulo sozinho, para os sítios que não são um campo (um valor a mostrar). */
export function Rotulo({ children }: { children: ReactNode }) {
    return (
        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
            {children}
        </span>
    );
}

/** A entrada de texto padrão. Uma altura, um raio, um foco visível. */
export const entrada =
    'w-full h-10 px-3 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:border-indigo-500';
