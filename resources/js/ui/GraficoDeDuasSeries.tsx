import { cls, kz } from './tokens';
import { t } from '@/i18n';

/**
 * DUAS SÉRIES LADO A LADO, NA MESMA ESCALA.
 *
 * É o «Vendas vs. Compras» do painel de sempre: o que entra contra o que sai,
 * e a folga entre os dois.
 *
 * **UMA ESCALA SÓ, e é por isso que isto se pode fazer.** Vendas e compras são
 * a mesma grandeza (kwanzas) — duas escalas fariam parecer iguais um mês em que
 * se vendeu o dobro do que se comprou. Um gráfico de dois eixos nunca entra
 * aqui: se as grandezas fossem diferentes, seriam dois gráficos.
 *
 * A LEGENDA ESTÁ SEMPRE PRESENTE, e a cor nunca vai sozinha: cada série tem o
 * seu quadrado ao lado do nome, e cada barra diz por extenso o que vale no
 * `aria-label`.
 */
export function GraficoDeDuasSeries({
    dados,
    titulo,
    primeira,
    segunda,
    altura = 200,
}: {
    dados: Array<{ rotulo: string; a: number; b: number }>;
    titulo: string;
    /** O nome da primeira série — «Vendas». */
    primeira: string;
    /** O nome da segunda — «Compras». */
    segunda: string;
    altura?: number;
}) {
    if (dados.length === 0) {
        return <p className="py-8 text-center text-sm text-slate-400">{t('Ainda não há nada para mostrar.')}</p>;
    }

    const maximo = Math.max(...dados.flatMap((d) => [d.a, d.b]), 0);
    const escala = (v: number) => (maximo > 0 ? (v / maximo) * 100 : 0);

    return (
        <figure className="m-0">
            <div className="mb-3 flex flex-wrap gap-4 text-xs text-slate-600">
                <span className="flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-sm bg-indigo-500" aria-hidden="true" />
                    {primeira}
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-sm bg-amber-500" aria-hidden="true" />
                    {segunda}
                </span>
            </div>

            <div
                className="flex items-stretch gap-2 border-b border-slate-200"
                style={{ height: altura }}
                role="img"
                aria-label={titulo}
            >
                {dados.map((d, i) => (
                    <div key={i} className="group relative flex h-full flex-1 items-end gap-0.5">
                        {/* Os dois valores ao passar o rato, juntos: é a
                            comparação entre eles que interessa, não cada um. */}
                        <span className="pointer-events-none absolute -top-1 left-1/2 hidden -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white group-hover:block">
                            {primeira} {kz(d.a, 0)} · {segunda} {kz(d.b, 0)}
                        </span>

                        <div
                            className={cls(
                                'flex-1 rounded-t transition-all duration-500',
                                d.a > 0 ? 'bg-indigo-500 group-hover:bg-indigo-600' : 'bg-slate-100',
                            )}
                            style={{ height: `${Math.max(escala(d.a), d.a > 0 ? 2 : 1)}%` }}
                            aria-label={`${d.rotulo} — ${primeira}: ${kz(d.a, 0)}`}
                        />
                        <div
                            className={cls(
                                'flex-1 rounded-t transition-all duration-500',
                                d.b > 0 ? 'bg-amber-500 group-hover:bg-amber-600' : 'bg-slate-100',
                            )}
                            style={{ height: `${Math.max(escala(d.b), d.b > 0 ? 2 : 1)}%` }}
                            aria-label={`${d.rotulo} — ${segunda}: ${kz(d.b, 0)}`}
                        />
                    </div>
                ))}
            </div>

            <div className="mt-1.5 flex gap-2">
                {dados.map((d, i) => (
                    <span key={i} className="flex-1 text-center text-[11px] text-slate-400">
                        {d.rotulo}
                    </span>
                ))}
            </div>

            <figcaption className="mt-2 text-xs text-slate-400">
                {titulo} · {t('máximo')} {kz(maximo, 0)} Kz
            </figcaption>
        </figure>
    );
}
