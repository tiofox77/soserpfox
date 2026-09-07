import type { ReactNode } from 'react';

import { CORES, cls, type Cor } from './tokens';

/**
 * A ETIQUETA DE ESTADO.
 *
 * Ganhou um anel da própria cor: sobre um fundo branco, um `bg-emerald-50`
 * sozinho quase não se via — e um estado que não se vê é um estado que não
 * está lá. O ponto à esquerda dá a mesma informação a quem não distingue
 * verde de âmbar.
 */
export function Etiqueta({
    cor = 'neutra',
    icone,
    ponto = false,
    children,
}: {
    cor?: Cor;
    icone?: string;
    /** Um ponto da cor, para o estado não depender só do fundo. */
    ponto?: boolean;
    children: ReactNode;
}) {
    return (
        <span
            className={cls(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
                'ring-1 ring-inset',
                CORES[cor].suave,
                {
                    primaria: 'ring-indigo-200',
                    neutra: 'ring-slate-200',
                    bom: 'ring-emerald-200',
                    aviso: 'ring-amber-200',
                    perigo: 'ring-red-200',
                }[cor],
            )}
        >
            {ponto && (
                <span
                    className={cls(
                        'h-1.5 w-1.5 rounded-full',
                        { primaria: 'bg-indigo-500', neutra: 'bg-slate-400', bom: 'bg-emerald-500', aviso: 'bg-amber-500', perigo: 'bg-red-500' }[cor],
                    )}
                    aria-hidden="true"
                />
            )}
            {icone && <i className={`fas ${icone}`} aria-hidden="true" />}
            {children}
        </span>
    );
}
