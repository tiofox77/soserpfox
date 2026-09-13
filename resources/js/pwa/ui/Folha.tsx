import { useEffect, useId, type ReactNode } from 'react';

import { t } from '@/i18n';

/**
 * A FOLHA DO PWA — sobe do fundo no telemóvel, aparece ao centro no ecrã largo.
 *
 * No telemóvel de balcão o polegar está em baixo: um modal ao centro obriga a
 * esticar a mão para o botão de confirmar. A folha põe os botões onde o dedo
 * já está. No tablet e no portátil fica ao centro, que é onde se procura.
 *
 * O cabeçalho leva ícone e cor, como os modais da aplicação web: num relance
 * diz se isto é abrir turno (verde), fechar (vermelho) ou escolher (azul).
 */
export function Folha({
    aberta,
    aoFechar,
    titulo,
    subtitulo,
    icone,
    cor = 'from-blue-600 to-indigo-700',
    children,
    rodape,
    largura = 'sm:max-w-lg',
    fecharNoFundo = true,
    zIndex = 'z-[70]',
    semCabecalho = false,
    ...resto
}: {
    aberta: boolean;
    aoFechar: () => void;
    titulo: string;
    subtitulo?: string;
    icone?: string;
    /** As duas cores do gradiente do cabeçalho (classes do Tailwind). */
    cor?: string;
    children: ReactNode;
    rodape?: ReactNode;
    largura?: string;
    fecharNoFundo?: boolean;
    zIndex?: string;
    semCabecalho?: boolean;
    'data-ensaio'?: string;
}) {
    const idTitulo = useId();

    useEffect(() => {
        if (!aberta) return;
        const tecla = (e: KeyboardEvent) => { if (e.key === 'Escape') aoFechar(); };
        window.addEventListener('keydown', tecla);
        const antes = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', tecla);
            document.body.style.overflow = antes;
        };
    }, [aberta, aoFechar]);

    if (!aberta) return null;

    return (
        <div className={`fixed inset-0 ${zIndex} flex items-end sm:items-center justify-center sm:p-4`} {...resto}>
            <div className="pwa-fundo absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]" onClick={fecharNoFundo ? aoFechar : undefined} />
            <section role="dialog" aria-modal="true" aria-labelledby={idTitulo}
                     className={`pwa-sobe relative w-full ${largura} max-h-[92vh] bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl flex flex-col overflow-hidden`}>
                {/* A pega: diz, sem palavras, que isto se fecha para baixo. */}
                <div className="sm:hidden absolute top-2 left-1/2 -translate-x-1/2 w-10 h-1.5 rounded-full bg-white/50 z-10" aria-hidden="true" />

                {semCabecalho ? (
                    <h2 id={idTitulo} className="sr-only">{titulo}</h2>
                ) : (
                    <header className={`bg-gradient-to-r ${cor} text-white px-5 pt-5 pb-4 flex items-center gap-3 shrink-0`}>
                        {icone && (
                            <span className="w-10 h-10 shrink-0 rounded-xl bg-white/20 flex items-center justify-center">
                                <i className={`fas ${icone}`} aria-hidden="true" />
                            </span>
                        )}
                        <div className="min-w-0 flex-1">
                            <h2 id={idTitulo} className="text-base font-bold leading-tight">{titulo}</h2>
                            {subtitulo && <p className="text-xs opacity-85 mt-0.5">{subtitulo}</p>}
                        </div>
                        <button type="button" onClick={aoFechar} aria-label={t('Fechar')}
                                className="w-9 h-9 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center transition">
                            <i className="fas fa-xmark" aria-hidden="true" />
                        </button>
                    </header>
                )}

                <div className="overflow-y-auto overscroll-contain flex-1 p-5">{children}</div>

                {rodape && <footer className="shrink-0 border-t border-slate-100 p-4 bg-slate-50">{rodape}</footer>}
            </section>
        </div>
    );
}

/** As classes dos campos, iguais em todos os ecrãs do PWA. */
export const CAMPO = 'w-full px-3 py-2.5 border-2 border-slate-200 rounded-xl text-sm bg-white focus:border-blue-500 focus:outline-none transition';
export const ROTULO = 'block text-[11px] font-bold text-slate-600 uppercase tracking-wide mb-1';
