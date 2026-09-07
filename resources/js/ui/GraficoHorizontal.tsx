import { cls, kz } from './tokens';
import { t } from '@/i18n';

/**
 * UM RANKING EM BARRAS DEITADAS: nome à esquerda, barra e valor à direita.
 *
 * PORQUÊ DEITADO E NÃO EM PÉ: aqui as categorias são NOMES — «Transferência
 * bancária», «Refrigerante 1,5L» —, e num gráfico em pé o rótulo não cabe
 * debaixo da barra. O painel em Blade resolvia-o com um donut do Chart.js, que
 * empurra os nomes para uma legenda ao lado e obriga a saltar entre a cor e o
 * texto para ler cada fatia. Deitado, cada linha lê-se de uma vez.
 *
 * O VALOR ESTÁ SEMPRE ESCRITO, e não só ao passar o rato: são cinco linhas, não
 * doze barras, e cabe. Quem não vê o gráfico lê a mesma lista.
 */
export function GraficoHorizontal({
    dados,
    titulo,
    unidade = 'Kz',
    vazio,
}: {
    dados: Array<{ rotulo: string; valor: number }>;
    titulo: string;
    unidade?: string;
    /** O que dizer quando não há nada — específico do painel que o usa. */
    vazio?: string;
}) {
    if (dados.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-slate-400">
                {vazio ?? t('Ainda não há nada para mostrar.')}
            </p>
        );
    }

    const maximo = Math.max(...dados.map((d) => d.valor), 0);
    const escala = (v: number) => (maximo > 0 ? (v / maximo) * 100 : 0);

    return (
        <figure className="m-0" role="img" aria-label={titulo}>
            <ul className="space-y-2.5">
                {dados.map((d, i) => (
                    <li
                        key={i}
                        className="entra group"
                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                    >
                        <div className="mb-1 flex items-baseline justify-between gap-3">
                            <span className="min-w-0 truncate text-xs font-medium text-slate-700" title={d.rotulo}>
                                {d.rotulo}
                            </span>
                            <span className="shrink-0 text-xs font-bold tabular-nums text-slate-900">
                                {kz(d.valor, 0)}
                            </span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                            {/* A barra cresce da esquerda com transição: entra a
                                desenhar-se em vez de aparecer feita. */}
                            <div
                                className={cls(
                                    'h-full rounded-full transition-all duration-700 ease-out',
                                    'bg-gradient-to-r from-indigo-400 to-indigo-600 group-hover:from-indigo-500 group-hover:to-indigo-700',
                                )}
                                style={{ width: `${Math.max(escala(d.valor), d.valor > 0 ? 3 : 0)}%` }}
                                aria-label={`${d.rotulo}: ${kz(d.valor, 0)} ${unidade}`}
                            />
                        </div>
                    </li>
                ))}
            </ul>

            <figcaption className="mt-3 text-xs text-slate-400">
                {titulo} · {t('máximo')} {kz(maximo, 0)} {unidade}
            </figcaption>
        </figure>
    );
}
