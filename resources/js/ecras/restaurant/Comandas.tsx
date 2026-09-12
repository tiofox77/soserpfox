import { useEffect, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { restaurante, type FiltrosDasComandas } from '@/api/restaurant';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, dataHora, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { COR_DO_ESTADO, ICONE_DO_CANAL, PainelDaComanda } from './PecasDaComanda';

/**
 * AS COMANDAS — a lista, e a que está aberta ao lado.
 *
 * Duas colunas e não duas páginas: quem procura uma comanda quer vê-la, e ir
 * e voltar a uma lista paginada a cada consulta era o que o ecrã em Blade
 * obrigava a fazer.
 *
 * O ENDEREÇO GUARDA A COMANDA (`?order=`) — é o que faz o link partilhável e o
 * que permite ao mapa da sala mandar para aqui já com a comanda certa aberta.
 */

export default function Comandas({ order }: { order?: number }) {
    const [aberta, porAberta] = useState<number | null>(order ?? null);
    const [filtros, porFiltros] = useState<FiltrosDasComandas>({ procura: '', abertas: true, page: 1, por_pagina: 12 });
    const [procura, porProcura] = useState('');
    const [recado, porRecado] = useState('');

    // A caixa de procura escreve-se depressa; o servidor não precisa de saber
    // de cada tecla.
    useEffect(() => {
        const id = setTimeout(() => porFiltros((f) => ({ ...f, procura, page: 1 })), 300);

        return () => clearTimeout(id);
    }, [procura]);

    // O endereço acompanha a comanda aberta, sem recarregar a página.
    useEffect(() => {
        const url = new URL(window.location.href);

        if (aberta) url.searchParams.set('order', String(aberta));
        else url.searchParams.delete('order');

        window.history.replaceState({}, '', url);
    }, [aberta]);

    const opcoes = useQuery({
        queryKey: ['restaurante', 'comandas', 'opcoes'],
        queryFn: restaurante.comandas.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['restaurante', 'comandas', 'lista', filtros],
        queryFn: () => restaurante.comandas.lista(filtros),
        placeholderData: keepPreviousData,
        refetchInterval: 30_000,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const meta = lista.data?.meta;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Comandas')}
                subtitulo={t('Encontrar, atender e fechar')}
                icone="fa-receipt"
                cor="laranja"
                accoes={
                    <>
                        <a href="/restaurant/pos" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-cash-register" aria-hidden="true" />
                            {t('Balcão')}
                        </a>
                        <a href="/restaurant/floor" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chair" aria-hidden="true" />
                            {t('Sala')}
                        </a>
                    </>
                }
            >
                {!o.tem_turno && (
                    <EstadoNaFaixa icone="fa-triangle-exclamation">
                        {t('Sem turno aberto — não é possível facturar')}
                    </EstadoNaFaixa>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_28rem]">
                <div className="space-y-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    type="search"
                                    value={procura}
                                    onChange={(e) => porProcura(e.target.value)}
                                    placeholder={t('Número, mesa ou cliente…')}
                                    className={cls(entrada, 'pl-9')}
                                />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Estado')} className="w-44">
                            <select
                                value={filtros.estado ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, estado: e.target.value, abertas: false, page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Canal')} className="w-40">
                            <select
                                value={filtros.canal ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, canal: e.target.value, page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {o.canais.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                            </select>
                        </Campo>

                        <label className="flex h-10 cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={!!filtros.abertas}
                                onChange={(e) => porFiltros({ ...filtros, abertas: e.target.checked, estado: '', page: 1 })}
                                className="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            />
                            {t('Só as abertas')}
                        </label>
                    </div>

                    <div className={cls(CARTAO, 'overflow-hidden')}>
                        {lista.isPending ? (
                            <div className="p-5"><Carregando linhas={6} /></div>
                        ) : (lista.data?.data.length ?? 0) === 0 ? (
                            <SemNada
                                icone="fa-receipt"
                                titulo={t('Nenhuma comanda')}
                                frase={t('Abra uma comanda no balcão ou tocando numa mesa da sala.')}
                                accao={
                                    <a href="/restaurant/pos" className={cls('inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-orange-600 to-red-600 px-4 text-sm font-semibold text-white shadow-md', FOCO)}>
                                        <i className="fas fa-cash-register" aria-hidden="true" />
                                        {t('Ir para o balcão')}
                                    </a>
                                }
                            />
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {lista.data?.data.map((c, i) => (
                                    <li key={c.id} style={cascata(i)} className="entra">
                                        <button
                                            type="button"
                                            onClick={() => porAberta(c.id)}
                                            className={cls(
                                                'flex w-full items-center gap-3 px-4 py-3 text-left transition',
                                                FOCO,
                                                aberta === c.id ? 'bg-orange-50' : 'hover:bg-slate-50',
                                            )}
                                        >
                                            <span className={cls(
                                                'grid h-10 w-10 flex-none place-items-center rounded-xl',
                                                aberta === c.id ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-500',
                                            )}>
                                                <i className={`fas ${ICONE_DO_CANAL[c.canal] ?? 'fa-receipt'}`} aria-hidden="true" />
                                            </span>

                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold text-slate-800">
                                                    {c.numero}
                                                    {c.mesa && <span className="ml-2 font-normal text-slate-500">· {c.mesa}</span>}
                                                </span>
                                                <span className="block truncate text-xs text-slate-500">
                                                    {c.canal_rotulo}
                                                    {c.empregado && ` · ${c.empregado}`}
                                                    {' · '}{dataHora(c.criada_em)}
                                                </span>
                                            </span>

                                            <span className="flex flex-none flex-col items-end gap-1">
                                                <span className="text-sm font-bold tabular-nums text-slate-900">{kz(c.total)}</span>
                                                <Etiqueta cor={COR_DO_ESTADO[c.estado] ?? 'neutra'}>{c.estado_rotulo}</Etiqueta>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {meta && meta.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-slate-500">
                                {t('A mostrar :de a :ate de :total', {
                                    de: String(meta.from ?? 0), ate: String(meta.to ?? 0), total: String(meta.total),
                                })}
                            </p>
                            <div className="flex items-center gap-2">
                                <PorPagina
                                    valor={filtros.por_pagina ?? 12}
                                    aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })}
                                />
                                <Botao
                                    altura="pequeno" icone="fa-chevron-left"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page - 1 })}
                                    aria-label={t('Página anterior')}
                                />
                                <span className="text-xs font-semibold tabular-nums text-slate-600">
                                    {meta.current_page}/{meta.last_page}
                                </span>
                                <Botao
                                    altura="pequeno" icone="fa-chevron-right"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page + 1 })}
                                    aria-label={t('Página seguinte')}
                                />
                            </div>
                        </div>
                    )}
                </div>

                <div className="lg:sticky lg:top-4 lg:self-start">
                    <Cartao titulo={t('Comanda')} icone="fa-utensils">
                        {aberta === null ? (
                            <SemNada icone="fa-hand-pointer" frase={t('Escolha uma comanda à esquerda.')} />
                        ) : (
                            <PainelDaComanda
                                key={aberta}
                                id={aberta}
                                opcoes={o}
                                aoRecado={porRecado}
                                aoTrocarDeComanda={porAberta}
                            />
                        )}
                    </Cartao>
                </div>
            </div>
        </div>
    );
}
