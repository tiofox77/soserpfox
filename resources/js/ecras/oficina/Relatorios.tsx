import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { mapasDaOficina, type ColunaDoMapa, type FiltrosDoMapa, type LinhaDoMapa } from '@/api/oficina';
import { ErroDaApi } from '@/api/cliente';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS CINCO MAPAS DA OFICINA — numa tabela só.
 *
 * Cada mapa DECLARA AS SUAS COLUNAS no servidor, e este ecrã desenha-as. É a
 * mesma ideia do ecrã genérico dos catálogos, e pela mesma razão: cinco
 * tabelas escritas à mão aqui, mais as do papel e as do Excel, eram quatro
 * listas de colunas a divergir à primeira que se acrescentasse.
 *
 * O PAPEL E O EXCEL LEVAM OS MESMOS FILTROS — e as moradas vêm do servidor, que
 * é quem sabe o que a exportação aceita.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    scheduled: 'primaria',
    in_progress: 'aviso',
    completed: 'bom',
    delivered: 'neutra',
    cancelled: 'perigo',
};

const hoje = () => new Date().toISOString().slice(0, 10);
const inicioDoMes = () => {
    const d = new Date();

    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
};

export default function Relatorios() {
    const [filtros, porFiltros] = useState<FiltrosDoMapa>({
        mapa: 'servicos',
        de: inicioDoMes(),
        ate: hoje(),
        estado: '',
    });

    const opcoes = useQuery({
        queryKey: ['oficina', 'mapas', 'opcoes'],
        queryFn: mapasDaOficina.opcoes,
        staleTime: 5 * 60_000,
    });

    const mapa = useQuery({
        queryKey: ['oficina', 'mapas', filtros],
        queryFn: () => mapasDaOficina.mostrar(filtros),
        placeholderData: keepPreviousData,
        enabled: opcoes.isSuccess,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const escolhido = o.mapas.find((m) => m.valor === filtros.mapa);
    const d = mapa.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Relatórios da Oficina')}
                subtitulo={escolhido ? escolhido.rotulo : undefined}
                icone="fa-chart-bar"
                cor="bom"
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

            <div className={cls(CARTAO, 'p-4')}>
                {/* OS CINCO MAPAS. A lista vem do servidor — está na mesma
                    classe que faz as contas. */}
                <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label={t('Relatório')}>
                    {o.mapas.map((m) => {
                        const activo = m.valor === filtros.mapa;

                        return (
                            <button
                                key={m.valor}
                                type="button"
                                role="tab"
                                aria-selected={activo}
                                onClick={() => porFiltros((f) => ({ ...f, mapa: m.valor, estado: '' }))}
                                className={cls(
                                    'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                    'hover:-translate-y-0.5 active:translate-y-0',
                                    RAIO, FOCO,
                                    activo
                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700 shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                                )}
                            >
                                <i className={cls('fas', m.icone)} aria-hidden="true" />
                                {m.rotulo}
                            </button>
                        );
                    })}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('De')}>
                        <input type="date" value={filtros.de ?? ''} className={cls(entrada, 'tabular-nums')}
                            onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value }))} />
                    </Campo>
                    <Campo etiqueta={t('Até')}>
                        <input type="date" value={filtros.ate ?? ''} className={cls(entrada, 'tabular-nums')}
                            onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value }))} />
                    </Campo>

                    {/* O ESTADO só onde faz sentido: filtrar um mapa de
                        receita por «cancelada» não é uma pergunta. */}
                    {escolhido?.pede_estado && (
                        <Campo etiqueta={t('Estado')}>
                            <select value={filtros.estado ?? ''} className={entrada}
                                onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value }))}>
                                <option value="">{t('Todos')}</option>
                                {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                            </select>
                        </Campo>
                    )}
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

/* ─── As peças ──────────────────────────────────────────────────────── */

const aDireita = (c: ColunaDoMapa) => c.formato === 'numero' || c.formato === 'dinheiro';

function Celula({ c, l }: { c: ColunaDoMapa; l: LinhaDoMapa }) {
    const v = l[c.chave];

    if (v === null || v === undefined || v === '') return null;

    switch (c.formato) {
        case 'dinheiro':
            return <>{kz(Number(v))}</>;
        case 'numero':
            return <>{Number(v).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 })}</>;
        case 'data':
            return <span className="tabular-nums">{data(String(v))}</span>;
        /* O ESTADO SAI POR EXTENSO E COM COR: `in_progress` numa tabela não é
           uma resposta a ninguém, e a cor diz-se antes de se ler. */
        case 'estado':
            return (
                <Etiqueta cor={COR_DO_ESTADO[String(v)] ?? 'neutra'}>
                    {String(l[`${c.chave}_rotulo`] ?? v)}
                </Etiqueta>
            );
        default:
            return <>{String(v)}</>;
    }
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os relatórios da oficina')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
