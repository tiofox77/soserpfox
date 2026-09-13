import { useCallback, useRef, useState } from 'react';

import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { useFecharFora } from './comum';

/**
 * A LÍNGUA DO ECRÃ.
 *
 * Um GET com `?lang=` chega ao `DefinirLingua`, que guarda no perfil e no
 * cookie — sem estado nenhum aqui. A ligação é a própria página com a língua
 * trocada, como o `fullUrlWithQuery` do Blade fazia.
 *
 * Os nomes das línguas ficam FORA do t(): cada língua escreve-se na sua.
 */
const LINGUAS: Array<[string, string]> = [['pt', 'Português'], ['en', 'English'], ['fr', 'Français']];

function comLingua(sigla: string): string {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', sigla);
    return url.toString();
}

export default function Lingua({ actual }: { actual: string }) {
    const [aberto, porAberto] = useState(false);
    const caixa = useRef<HTMLDivElement>(null);
    const fechar = useCallback(() => porAberto(false), []);

    useFecharFora(caixa, aberto, fechar);

    return (
        <div ref={caixa} className="relative">
            <button type="button" onClick={() => porAberto((a) => !a)} title="Língua / Language / Langue" aria-expanded={aberto} aria-haspopup="menu"
                className={cls('flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm font-bold uppercase text-gray-600 hover:bg-gray-100 hover:text-gray-900', TRANSICAO, FOCO)}>
                <i className={cls('fas fa-globe text-gray-400 transition-transform duration-500', aberto && 'rotate-180 text-blue-500')} aria-hidden="true" />
                {actual}
            </button>

            {aberto && (
                <div role="menu" className="animate-scale-in absolute right-0 top-full z-50 mt-1 min-w-[10rem] origin-top-right overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                    {LINGUAS.map(([sigla, nome]) => (
                        <a key={sigla} href={comLingua(sigla)} role="menuitem" lang={sigla}
                            className={cls('flex items-center justify-between px-4 py-2.5 text-sm transition hover:bg-gray-50', actual === sigla ? 'font-bold text-blue-700' : 'text-gray-700')}>
                            {nome}
                            {actual === sigla && <i className="fas fa-check text-xs text-blue-600" aria-hidden="true" />}
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
