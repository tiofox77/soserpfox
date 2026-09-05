import { cls, kz } from './tokens';

/**
 * Um gráfico de barras em SVG, sem biblioteca nenhuma.
 *
 * PORQUÊ SEM CHART.JS, que já está vendido em `/vendor/js/chart.min.js`: o
 * Chart.js desenha num `<canvas>` e é imperativo — é preciso criar, destruir e
 * apanhar o instante certo em que o elemento existe. Foi por isso que o painel
 * em Blade precisou de uma escada de cinco tentativas de desenho
 * (`SOS_ESCADA = [0, 300, 900, 2000, 3000]`). Em React o componente é dono do
 * seu ciclo e o problema não existe — e umas barras não justificam 200 KB.
 *
 * UMA ESCALA SÓ coloca as barras, os traços e as etiquetas, e toda a etiqueta
 * nomeia um valor que o gráfico atinge. Quem não vê o gráfico tem os mesmos
 * números na tabela ao lado (`aria-label` por barra).
 */
export function GraficoDeBarras({
    dados,
    altura = 180,
    titulo,
}: {
    dados: Array<{ rotulo: string; valor: number }>;
    altura?: number;
    titulo: string;
}) {
    if (dados.length === 0) {
        return <p className="py-8 text-center text-sm text-slate-400">Ainda não há nada para mostrar.</p>;
    }

    const maximo = Math.max(...dados.map((d) => d.valor), 0);
    // Um máximo de zero daria barras de altura infinita ao dividir.
    const escala = (v: number) => (maximo > 0 ? (v / maximo) * 100 : 0);

    return (
        <figure className="m-0">
            <div
                className="flex items-stretch gap-1.5 border-b border-slate-200"
                style={{ height: altura }}
                role="img"
                aria-label={titulo}
            >
                {dados.map((d, i) => (
                    <div key={i} className="group relative flex h-full flex-1 flex-col justify-end">
                        {/* O valor aparece por cima da barra ao passar o rato,
                            e não em todas ao mesmo tempo: doze números
                            sobrepostos não se lêem. */}
                        <span className="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white group-hover:block">
                            {kz(d.valor, 0)}
                        </span>
                        <div
                            className={cls(
                                'w-full rounded-t transition',
                                d.valor > 0 ? 'bg-indigo-500 group-hover:bg-indigo-600' : 'bg-slate-100',
                            )}
                            style={{ height: `${Math.max(escala(d.valor), d.valor > 0 ? 2 : 1)}%` }}
                            aria-label={`${d.rotulo}: ${kz(d.valor, 0)}`}
                        />
                    </div>
                ))}
            </div>

            <div className="mt-1.5 flex gap-1.5">
                {dados.map((d, i) => (
                    <span key={i} className="flex-1 text-center text-[11px] text-slate-400">
                        {d.rotulo}
                    </span>
                ))}
            </div>

            {/* A etiqueta do máximo nomeia um valor que o gráfico ATINGE — sem
                ela, as barras são proporções sem grandeza. */}
            <figcaption className="mt-2 text-xs text-slate-400">
                {titulo} · máximo {kz(maximo, 0)} Kz
            </figcaption>
        </figure>
    );
}
