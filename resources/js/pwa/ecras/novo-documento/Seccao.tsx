import type { CSSProperties, ReactNode } from 'react';

/**
 * UM CARTÃO DO FORMULÁRIO — o `bg-white rounded-2xl shadow` do Blade, com o
 * ícone e a cor de cada secção para se achar num relance onde se está.
 *
 * Entra com `pwa-entra` e um atraso pequeno por ordem: os cartões sobem em
 * escada. Nenhum destes cartões tem filhos `fixed` (as folhas e o aviso de
 * guardado vivem fora deles), por isso o transform da animação não desloca nada.
 */
export function Seccao({
    titulo,
    icone,
    cor = 'bg-blue-100 text-blue-700',
    direita,
    ordem = 0,
    className = '',
    children,
    htmlFor,
    ...resto
}: {
    titulo?: string;
    icone?: string;
    cor?: string;
    direita?: ReactNode;
    ordem?: number;
    className?: string;
    children: ReactNode;
    /** Com um só campo, o título é o rótulo dele. */
    htmlFor?: string;
    'data-ensaio'?: string;
}) {
    const estilo: CSSProperties = { animationDelay: `${Math.min(ordem, 10) * 35}ms` };
    const classeDoTitulo = 'flex items-center gap-2 text-xs font-bold text-slate-600 uppercase tracking-wide';
    const conteudoDoTitulo = (
        <>
            {icone && (
                <span className={`w-6 h-6 rounded-lg flex items-center justify-center text-[11px] ${cor}`}>
                    <i className={`fas ${icone}`} aria-hidden="true" />
                </span>
            )}
            {titulo}
        </>
    );

    return (
        <section style={estilo} className={`pwa-entra bg-white rounded-2xl shadow-sm ring-1 ring-slate-100 p-3 mb-3 transition-shadow hover:shadow-md ${className}`} {...resto}>
            {titulo && (
                <div className="flex items-center justify-between gap-2 mb-2 px-1">
                    {htmlFor
                        ? <label htmlFor={htmlFor} className={classeDoTitulo}>{conteudoDoTitulo}</label>
                        : <p className={classeDoTitulo}>{conteudoDoTitulo}</p>}
                    {direita}
                </div>
            )}
            {children}
        </section>
    );
}

/** Os campos pequenos das linhas e dos descontos (o `text-sm border rounded-lg` do Blade). */
export const CAMPO_PEQUENO = 'w-full px-2 py-1.5 border-2 border-slate-200 rounded-lg text-sm bg-white focus:border-blue-500 focus:outline-none transition';
export const ROTULO_PEQUENO = 'block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-0.5';
