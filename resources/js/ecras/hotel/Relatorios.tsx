import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { mapasDoHotel, type ColunaDoMapa, type FiltrosDoMapaDoHotel, type LinhaDoMapa } from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS MAPAS DO HOTEL — e os três números que a hotelaria pergunta.
 *
 * OCUPAÇÃO, ADR e RevPAR ficam por cima de qualquer mapa, porque é a primeira
 * coisa que se olha. O ADR diz a que preço médio se vendeu a noite; o RevPAR
 * multiplica-o pela ocupação, e é ESSE que se compara com o hotel do lado —
 * um meio vazio a preço alto e um cheio a preço baixo têm ADR muito diferentes
 * e RevPAR parecido.
 *
 * O MAPA DE HÓSPEDES lia a ficha antiga (`hotel_guests`), que está vazia:
 * mostrava sempre zero hóspedes e zero nacionalidades.
 */

const hoje = () => new Date().toISOString().slice(0, 10);
const inicioDoMes = () => {
    const d = new Date();

    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
};

const aDireita = (c: ColunaDoMapa) => c.formato !== 'texto' && c.formato !== 'data';

export default function Relatorios() {
    const [filtros, porFiltros] = useState<FiltrosDoMapaDoHotel>({
        mapa: 'ocupacao', de: inicioDoMes(), ate: hoje(), tipo_de_quarto: '',
    });

    const opcoes = useQuery({
        queryKey: ['hotel', 'mapas', 'opcoes'],
        queryFn: mapasDoHotel.opcoes,
        staleTime: 5 * 60_000,
    });

    const mapa = useQuery({
        queryKey: ['hotel', 'mapas', filtros],
        queryFn: () => mapasDoHotel.mostrar(filtros),
        placeholderData: keepPreviousData,
        enabled: opcoes.isSuccess,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const escolhido = o.mapas.find((m) => m.valor === filtros.mapa);
    const d = mapa.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 1 });

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Relatórios do Hotel')}
                subtitulo={escolhido?.rotulo}
                icone="fa-chart-line"
                cor="ciano"
                accoes={d && (
                    <>
                        <a href={d.descargas.pdf} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-print" aria-hidden="true" />
                            {t('Imprimir')}
                        </a>
                        <a href={d.descargas.excel} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-file-excel" aria-hidden="true" />
                            {t('Excel')}
                        </a>
                    </>
                )}
            />

            {/* OS TRÊS NÚMEROS, sempre visíveis — mudam com o período e com o
                tipo de quarto, e não com o mapa escolhido. */}
            {d && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero aspecto="claro" rotulo={t('Ocupação')} tom="verde" icone="fa-chart-pie"
                        nota={t(':vendidas de :disponiveis noites', {
                            vendidas: numero(d.kpis.noites_vendidas), disponiveis: numero(d.kpis.noites_disponiveis),
                        })}
                        valor={`${numero(d.kpis.ocupacao)}%`} />
                    <CartaoNumero aspecto="claro" rotulo={t('ADR')} tom="azul" icone="fa-tag"
                        nota={t('preço médio da noite vendida')} valor={`${kz(d.kpis.adr)} Kz`} />
                    <CartaoNumero aspecto="claro" rotulo={t('RevPAR')} tom="indigo" icone="fa-gauge-high"
                        nota={t('por noite disponível — é este que se compara')} valor={`${kz(d.kpis.revpar)} Kz`} />
                    <CartaoNumero aspecto="claro" rotulo={t('Receita do período')} tom="roxo" icone="fa-money-bill-wave"
                        nota={t(':n estada(s)', { n: numero(d.kpis.reservas) })} valor={`${kz(d.kpis.receita)} Kz`} />
                </div>
            )}

            <div className={cls(CARTAO, 'p-4')}>
                <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label={t('Relatório')}>
                    {o.mapas.map((m) => {
                        const activo = m.valor === filtros.mapa;

                        return (
                            <button
                                key={m.valor}
                                type="button"
                                role="tab"
                                aria-selected={activo}
                                onClick={() => porFiltros((f) => ({ ...f, mapa: m.valor }))}
                                className={cls(
                                    'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                    'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                    activo
                                        ? 'border-sky-500 bg-sky-50 text-sky-700 shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                                )}
                            >
                                <i className={cls('fas', m.icone)} aria-hidden="true" />
                                {m.rotulo}
                            </button>
                        );
                    })}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Campo etiqueta={t('De')}>
                        <input type="date" value={filtros.de ?? ''} className={cls(entrada, 'tabular-nums')}
                            onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value }))} />
                    </Campo>
                    <Campo etiqueta={t('Até')}>
                        <input type="date" value={filtros.ate ?? ''} className={cls(entrada, 'tabular-nums')}
                            onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value }))} />
                    </Campo>
                    <Campo etiqueta={t('Tipo de quarto')}>
                        <select value={filtros.tipo_de_quarto ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, tipo_de_quarto: e.target.value }))}>
                            <option value="">{t('Todos')}</option>
                            {o.tipos_de_quarto.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                </div>
            </div>

            {mapa.isPending ? (
                <Carregando linhas={8} />
            ) : mapa.isError ? (
                <Falhou erro={mapa.error} />
            ) : d && (
                <div className={cls(CARTAO, 'overflow-hidden', mapa.isFetching && 'opacity-70 transition-opacity')}>
                    {d.linhas.length === 0 ? (
                        <p className="px-4 py-12 text-center text-sm text-slate-400">
                            {d.nada ?? t('Não há nada para mostrar com estes filtros.')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 bg-slate-50">
                                        {d.colunas.map((c) => (
                                            <th key={c.chave} scope="col"
                                                className={cls('px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500',
                                                    aDireita(c) ? 'text-right' : 'text-left')}>
                                                {c.rotulo}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {d.linhas.map((l, i) => (
                                        <tr key={i} className="entra transition-colors duration-150 hover:bg-slate-50"
                                            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                            {d.colunas.map((c) => (
                                                <td key={c.chave} className={cls('px-4 py-2.5 text-slate-700', aDireita(c) && 'text-right tabular-nums')}>
                                                    <Celula c={c} l={l} />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                                {d.totais && (
                                    <tfoot>
                                        <tr className="border-t-2 border-slate-300 bg-slate-50 font-bold text-slate-900">
                                            {d.colunas.map((c) => (
                                                <td key={c.chave} className={cls('px-4 py-3', aDireita(c) && 'text-right tabular-nums')}>
                                                    <Celula c={c} l={d.totais!} />
                                                </td>
                                            ))}
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        </div>
                    )}

                    <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                        <i className="fas fa-calendar-days mr-1.5" aria-hidden="true" />
                        {t(':quantas linha(s) · :de a :ate', {
                            quantas: d.linhas.length.toLocaleString(etiquetaIntl()),
                            de: data(d.periodo.de),
                            ate: data(d.periodo.ate),
                        })}
                    </p>
                </div>
            )}
        </div>
    );
}

function Celula({ c, l }: { c: ColunaDoMapa; l: LinhaDoMapa }) {
    const v = l[c.chave];

    if (v === null || v === undefined || v === '') return null;

    switch (c.formato) {
        case 'dinheiro':
            return <>{kz(Number(v))}</>;
        case 'percentagem':
            return <>{Number(v).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 1 })}%</>;
        case 'numero':
            return <>{Number(v).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 })}</>;
        case 'data':
            return <span className="tabular-nums">{data(String(v))}</span>;
        default:
            return <>{String(v)}</>;
    }
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os relatórios do hotel')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
