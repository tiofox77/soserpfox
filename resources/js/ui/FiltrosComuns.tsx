import { Rotulo, entrada } from './Campo';
import { cls } from './tokens';
import { t } from '@/i18n';

/**
 * OS FILTROS QUE TODAS AS LISTAS TÊM — e que a migração deixou cair em todas.
 *
 * O intervalo de datas e o «por página» estavam em todos os ecrãs em Blade
 * (`dateFrom`, `dateTo`, `perPage`) e desapareceram de todos ao mesmo tempo.
 * Ficam aqui, num sítio só: escritos ecrã a ecrã, o primeiro a mudar deixava
 * os outros para trás — foi o que aconteceu ao estado vazio, que existia em
 * duas versões diferentes até se juntarem.
 */

/**
 * O INTERVALO EM QUE A FICHA FOI CRIADA.
 *
 * «Que clientes entraram este mês», «que artigos foram lançados esta semana».
 * As duas datas são independentes: só a de início já é um filtro útil.
 */
export function IntervaloDeDatas({
    de,
    ate,
    aoMudar,
    className,
}: {
    de?: string;
    ate?: string;
    aoMudar: (campo: 'de' | 'ate', valor: string) => void;
    className?: string;
}) {
    return (
        <>
            <label className={cls('block', className)}>
                <Rotulo>{t('Criado de')}</Rotulo>
                <input
                    type="date"
                    value={de ?? ''}
                    onChange={(e) => aoMudar('de', e.target.value)}
                    // O fim manda no início: um intervalo ao contrário devolve
                    // sempre lista vazia e ninguém percebe porquê.
                    max={ate || undefined}
                    className={entrada}
                />
            </label>

            <label className={cls('block', className)}>
                <Rotulo>{t('até')}</Rotulo>
                <input
                    type="date"
                    value={ate ?? ''}
                    onChange={(e) => aoMudar('ate', e.target.value)}
                    min={de || undefined}
                    className={entrada}
                />
            </label>
        </>
    );
}

/** Os tamanhos de página. Os mesmos do ecrã de sempre. */
const TAMANHOS = [15, 25, 50, 100];

/**
 * QUANTAS LINHAS POR PÁGINA.
 *
 * Parece um pormenor e não é: com 300 artigos, quinze por página são vinte
 * cliques para chegar ao fim. A API aceitava-o desde o primeiro dia — o que
 * faltava era o botão.
 */
export function PorPagina({
    valor,
    aoMudar,
}: {
    valor?: number;
    aoMudar: (n: number) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-xs text-slate-500">
            <span className="whitespace-nowrap">{t('Por página')}</span>
            <select
                value={valor ?? 15}
                onChange={(e) => aoMudar(Number(e.target.value))}
                aria-label={t('Linhas por página')}
                className="h-8 rounded-lg border border-slate-300 bg-white px-2 text-xs text-slate-700 transition-colors hover:border-indigo-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                {TAMANHOS.map((n) => (
                    <option key={n} value={n}>
                        {n}
                    </option>
                ))}
            </select>
        </label>
    );
}
