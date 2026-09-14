import { useEffect, useState, type FormEvent, type ReactNode } from 'react';

import { CARTAO, FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * A PAGINAÇÃO DAS TABELAS — POR NÚMERO.
 *
 * Cada ecrã tinha a sua: «Anterior · Página 1 de 397 · Seguinte». Com 397
 * páginas, chegar à 200 eram 199 cliques. Agora há os números à volta da
 * página actual, a primeira e a última sempre à vista, as reticências no meio
 * e, quando são muitas, uma caixa para ir directo a uma página.
 *
 * É UM COMPONENTE SÓ, para os ecrãs todos: a mesma forma, os mesmos
 * atalhos, a mesma animação — e mudar a paginação é mudar este ficheiro.
 */

/** Um lugar na fila: um número de página, ou reticências. */
export type LugarDaPaginacao = number | 'antes' | 'depois';

/**
 * As páginas a mostrar: a primeira, a última, a actual com `vizinhos` de cada
 * lado — e reticências onde se salta. Uma reticência que só esconderia UMA
 * página mostra a página em vez dela (1 2 3, e não 1 … 3).
 */
export function paginasVisiveis(pagina: number, ultima: number, vizinhos = 1): LugarDaPaginacao[] {
    if (ultima <= 0) return [];
    const actual = Math.min(Math.max(1, pagina), ultima);
    // Até aqui cabe tudo sem reticências: primeira + última + actual + vizinhos + 2 saltos.
    if (ultima <= 5 + vizinhos * 2) return Array.from({ length: ultima }, (_, i) => i + 1);

    const inicio = Math.max(2, actual - vizinhos);
    const fim = Math.min(ultima - 1, actual + vizinhos);
    const lugares: LugarDaPaginacao[] = [1];

    if (inicio > 3) lugares.push('antes');
    else for (let p = 2; p < inicio; p++) lugares.push(p);

    for (let p = inicio; p <= fim; p++) lugares.push(p);

    if (fim < ultima - 2) lugares.push('depois');
    else for (let p = fim + 1; p < ultima; p++) lugares.push(p);

    lugares.push(ultima);

    return lugares;
}

const numero = (n: number) => n.toLocaleString('pt-PT');

export function Paginacao({
    pagina,
    ultima,
    aMudar,
    total,
    de,
    ate,
    aCarregar = false,
    emCartao = false,
    extra,
    className,
}: {
    pagina: number;
    ultima: number;
    aMudar: (pagina: number) => void;
    /** Quantos registos há ao todo — com `de` e `ate`, diz «1–15 de 5 952». */
    total?: number | null;
    de?: number | null;
    ate?: number | null;
    /** A página nova está a chegar: o número escolhido gira. */
    aCarregar?: boolean;
    /** Um cartão próprio (fora de uma tabela); por omissão é a faixa de baixo do cartão. */
    emCartao?: boolean;
    /** O que vive ao lado do resumo — o «Por página» — e fica à vista mesmo com uma página só. */
    extra?: ReactNode;
    className?: string;
}) {
    const [destino, porDestino] = useState('');
    const [pedida, porPedida] = useState<number | null>(null);

    // Chegou a página pedida (ou outra): o giro pára.
    useEffect(() => {
        if (!aCarregar) porPedida(null);
    }, [aCarregar, pagina]);

    const variasPaginas = ultima > 1;

    if (!variasPaginas && !extra) return null;

    const actual = Math.min(Math.max(1, pagina), Math.max(1, ultima));
    const ir = (p: number) => {
        const certa = Math.min(Math.max(1, Math.round(p)), ultima);
        if (certa === actual) return;
        porPedida(certa);
        aMudar(certa);
    };
    const irDirecto = (e: FormEvent) => {
        e.preventDefault();
        const n = Number(destino);
        if (Number.isFinite(n) && n >= 1) ir(n);
        porDestino('');
    };

    const seta = cls(
        'grid h-9 w-9 flex-none place-items-center text-slate-600 hover:-translate-y-0.5 hover:bg-indigo-50 hover:text-indigo-700 hover:shadow-sm active:translate-y-0 active:scale-95 disabled:pointer-events-none disabled:opacity-35',
        RAIO,
        TRANSICAO,
        FOCO,
    );
    const lugares = paginasVisiveis(actual, ultima);

    return (
        <nav
            aria-label={t('Páginas')}
            data-paginacao
            className={cls(
                'flex flex-col items-center justify-between gap-3 px-4 py-3 text-sm sm:flex-row sm:px-5',
                emCartao ? CARTAO : 'border-t border-slate-200 bg-slate-50/70',
                className,
            )}
        >
            <div className="order-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 sm:order-1 sm:justify-start">
                <p className="text-slate-500 tabular-nums">
                    {total != null && de != null && ate != null ? (
                        <>
                            <i className="fas fa-list-ol mr-1.5 text-slate-400" aria-hidden="true" />
                            {t('A mostrar :de–:ate de :total', { de: numero(de), ate: numero(ate), total: numero(total) })}
                        </>
                    ) : (
                        <>
                            <i className="fas fa-book-open mr-1.5 text-slate-400" aria-hidden="true" />
                            {t('Página :pagina de :paginas', { pagina: numero(actual), paginas: numero(Math.max(1, ultima)) })}
                        </>
                    )}
                </p>
                {extra}
            </div>

            {variasPaginas && (
                <div className="order-1 flex flex-wrap items-center justify-center gap-1 sm:order-2">
                    <button type="button" onClick={() => ir(1)} disabled={actual <= 1} aria-label={t('Primeira página')} title={t('Primeira página')} className={cls(seta, 'hidden sm:grid')}>
                        <i className="fas fa-angles-left text-xs" aria-hidden="true" />
                    </button>
                    <button type="button" onClick={() => ir(actual - 1)} disabled={actual <= 1} aria-label={t('Página anterior')} title={t('Página anterior')} className={seta}>
                        <i className="fas fa-chevron-left text-xs" aria-hidden="true" />
                    </button>

                    <ul className="flex items-center gap-1">
                        {lugares.map((l) =>
                            typeof l === 'number' ? (
                                <li key={l} className={cls(l !== actual && l !== 1 && l !== ultima && 'hidden sm:block')}>
                                    <button
                                        type="button"
                                        onClick={() => ir(l)}
                                        aria-label={t('Página :pagina', { pagina: numero(l) })}
                                        aria-current={l === actual ? 'page' : undefined}
                                        className={cls(
                                            'h-9 min-w-9 px-2.5 font-semibold tabular-nums',
                                            RAIO,
                                            TRANSICAO,
                                            FOCO,
                                            l === actual
                                                ? 'animate-scale-in bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/30'
                                                : 'text-slate-700 hover:-translate-y-0.5 hover:bg-white hover:text-indigo-700 hover:shadow-md hover:ring-1 hover:ring-indigo-100 active:translate-y-0 active:scale-95',
                                        )}
                                    >
                                        {aCarregar && pedida === l ? <i className="fas fa-spinner fa-spin text-xs" aria-hidden="true" /> : numero(l)}
                                    </button>
                                </li>
                            ) : (
                                <li key={l} aria-hidden="true" className="select-none px-1 text-slate-400">
                                    …
                                </li>
                            ),
                        )}
                    </ul>

                    <button type="button" onClick={() => ir(actual + 1)} disabled={actual >= ultima} aria-label={t('Página seguinte')} title={t('Página seguinte')} className={seta}>
                        <i className="fas fa-chevron-right text-xs" aria-hidden="true" />
                    </button>
                    <button type="button" onClick={() => ir(ultima)} disabled={actual >= ultima} aria-label={t('Última página')} title={t('Última página')} className={cls(seta, 'hidden sm:grid')}>
                        <i className="fas fa-angles-right text-xs" aria-hidden="true" />
                    </button>

                    {/* Muitas páginas: escreve-se o número e vai-se directo. No telemóvel ficam
                        só a primeira, a actual e a última, com as setas. */}
                    {ultima > 7 && (
                        <form onSubmit={irDirecto} className="ml-1 hidden items-center gap-1 md:flex">
                            <input
                                type="number"
                                min={1}
                                max={ultima}
                                inputMode="numeric"
                                value={destino}
                                onChange={(e) => porDestino(e.target.value)}
                                placeholder={t('Ir para')}
                                aria-label={t('Ir para a página')}
                                className={cls('h-9 w-20 border border-slate-300 bg-white px-2 text-center tabular-nums text-slate-900 shadow-sm placeholder:text-slate-400 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none', RAIO, TRANSICAO, FOCO)}
                            />
                            <button type="submit" disabled={destino === ''} aria-label={t('Ir para a página')} title={t('Ir para a página')} className={seta}>
                                <i className="fas fa-arrow-turn-down rotate-90 text-xs" aria-hidden="true" />
                            </button>
                        </form>
                    )}
                </div>
            )}
        </nav>
    );
}
