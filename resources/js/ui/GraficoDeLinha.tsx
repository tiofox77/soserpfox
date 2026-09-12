import { useId } from 'react';

import { t } from '@/i18n';

import { kz } from './tokens';

/**
 * UMA SÉRIE NO TEMPO — linha, área por baixo, e os dias por baixo dessa.
 *
 * PORQUE NÃO SÃO BARRAS: noventa e dois dias em barras dão barras de dois
 * pixéis, e o que se quer ver numa série de dias é a FORMA — subiu, caiu,
 * estagnou. A `GraficoDeBarras` continua a ser a peça certa para seis meses ou
 * doze categorias; esta é para o tempo contínuo.
 *
 * O ecrã em Blade desenhava este SVG à mão, com `@php` a acumular a string dos
 * pontos a meio do HTML — e com a coordenada `y` calculada duas vezes, uma para
 * a linha e outra para os círculos, com as mesmas contas escritas de novo.
 *
 * UMA ESCALA SÓ coloca a linha, os pontos e as etiquetas, e a legenda nomeia o
 * máximo, que é um valor que o gráfico ATINGE — sem isso a linha é uma forma
 * sem grandeza. Quem não a vê tem o mesmo número em texto por baixo.
 */
export function GraficoDeLinha({
    dados,
    titulo,
    unidade,
    altura = 210,
}: {
    dados: Array<{ rotulo: string; valor: number }>;
    titulo: string;
    /** O que cada valor é — «visitante(s)», «Kz». Vai para a legenda. */
    unidade: string;
    altura?: number;
}) {
    // O gradiente é referenciado por id: dois gráficos na mesma página não
    // podem partilhar o mesmo, ou o segundo pinta-se com a cor do primeiro.
    const id = useId().replace(/:/g, '');

    if (dados.length === 0) {
        return <p className="py-8 text-center text-sm text-slate-400">{t('Ainda não há nada para mostrar.')}</p>;
    }

    const L = 1000;
    const A = 200;
    const MARGEM = 30;
    const maximo = Math.max(...dados.map((d) => d.valor), 0);
    const passo = dados.length > 1 ? (L - MARGEM * 2) / (dados.length - 1) : 0;

    // Um máximo de zero daria uma divisão por zero: a linha assenta no chão.
    const ponto = (valor: number, i: number) => ({
        x: MARGEM + i * passo,
        y: maximo > 0 ? A - (valor / maximo) * (A - MARGEM - 10) : A,
    });

    const pontos = dados.map((d, i) => ponto(d.valor, i));
    const linha = pontos.map((p) => `${p.x},${p.y}`).join(' ');

    // Com um ponto só não há área que faça sentido — e uma etiqueta por dia em
    // noventa dias fica ilegível: mostram-se as que cabem.
    const area = pontos.length > 1
        ? `M ${MARGEM},${A} L ${linha} L ${pontos[pontos.length - 1]!.x},${A} Z`
        : '';

    const saltoDasEtiquetas = Math.ceil(dados.length / 16);

    return (
        <figure className="m-0">
            <div className="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/70 to-blue-50/40 p-3">
                <svg
                    viewBox={`0 0 ${L} ${A + 30}`}
                    className="w-full"
                    preserveAspectRatio="none"
                    style={{ maxHeight: altura }}
                    role="img"
                    aria-label={titulo}
                >
                    <defs>
                        <linearGradient id={`linha-${id}`} x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stopColor="#4f46e5" stopOpacity="0.45" />
                            <stop offset="1" stopColor="#4f46e5" stopOpacity="0" />
                        </linearGradient>
                    </defs>

                    {/* As guias horizontais, que dão a leitura da altura. */}
                    {[0, 1, 2, 3, 4].map((i) => (
                        <line
                            key={i}
                            x1={MARGEM}
                            x2={L - MARGEM}
                            y1={MARGEM + (i * (A - MARGEM)) / 4}
                            y2={MARGEM + (i * (A - MARGEM)) / 4}
                            stroke="#cbd5e1"
                            strokeWidth="0.5"
                            strokeDasharray="2 2"
                        />
                    ))}

                    {area && <path d={area} fill={`url(#linha-${id})`} />}

                    <polyline
                        points={linha}
                        fill="none"
                        stroke="#4f46e5"
                        strokeWidth="2.5"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />

                    {pontos.map((p, i) => (
                        <g key={i} className="group">
                            <circle cx={p.x} cy={p.y} r="3.5" fill="white" stroke="#4f46e5" strokeWidth="2" />
                            {/* A área de toque é maior do que o ponto: 3,5 px
                                não se acertam com o rato. */}
                            <circle cx={p.x} cy={p.y} r="10" fill="transparent">
                                <title>{`${dados[i]!.rotulo}: ${kz(dados[i]!.valor, 0)} ${unidade}`}</title>
                            </circle>
                        </g>
                    ))}

                    {pontos.map((p, i) => (
                        i % saltoDasEtiquetas === 0 ? (
                            <text
                                key={i}
                                x={p.x}
                                y={A + 20}
                                textAnchor="middle"
                                fontSize="10"
                                fill="#64748b"
                            >
                                {dados[i]!.rotulo}
                            </text>
                        ) : null
                    ))}
                </svg>
            </div>

            <figcaption className="mt-2 text-xs text-slate-400">
                {t(':titulo · máximo :n :unidade', { titulo, n: kz(maximo, 0), unidade })}
            </figcaption>
        </figure>
    );
}
