import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { crm, type Oportunidade } from '@/api/crm';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * AS OPORTUNIDADES — a lista e o funil, no mesmo ecrã.
 *
 * Eram duas moradas para o mesmo assunto visto de duas maneiras: a lista é o
 * registo, o funil é o quadro da parede. Quem movia um cartão no funil tinha de
 * ir à lista para lhe mexer no valor.
 *
 * O NEGÓCIO GANHO VIRA DOCUMENTO SEM SAIR DAQUI. Sem isso o CRM dizia «vá à
 * Facturação» e o valor era escrito duas vezes: uma no funil, outra no
 * documento — duas verdades sobre o mesmo negócio divergem à primeira correcção.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'bom' | 'perigo'> = {
    open: 'primaria',
    won: 'bom',
    lost: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    open: 'fa-circle-notch',
    won: 'fa-trophy',
    lost: 'fa-xmark',
};

const VAZIO = {
    title: '', client_id: '', stage_id: '', amount: '0',
    expected_close_date: '', notes: '',
};

export default function Oportunidades({ separador }: { separador?: string }) {
    const cache = useQueryClient();

    const [aba, porAba] = useState(separador === 'funil' ? 'funil' : 'lista');
    const [filtros, porFiltros] = useState<{
        procura?: string; estado?: string; etapa?: number | '';
        por_pagina?: number; page?: number;
    }>({ por_pagina: 20, page: 1 });

    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aPerder, porAPerder] = useState<Oportunidade | null>(null);
    const [motivo, porMotivo] = useState('');
    const [aHistorico, porAHistorico] = useState<Oportunidade | null>(null);

    const opcoes = useQuery({
        queryKey: ['crm', 'oportunidades', 'opcoes'],
        queryFn: () => crm.oportunidades.opcoes(),
    });

    const lista = useQuery({
        queryKey: ['crm', 'oportunidades', filtros],
        queryFn: () => crm.oportunidades.lista(filtros),
        enabled: aba === 'lista',
    });

    const funil = useQuery({
        queryKey: ['crm', 'oportunidades', 'funil'],
        queryFn: () => crm.oportunidades.funil(),
        enabled: aba === 'funil',
    });

    const resumo = useQuery({
        queryKey: ['crm', 'oportunidades', 'resumo'],
        queryFn: () => crm.oportunidades.lista({ por_pagina: 5, page: 1 }),
        select: (d) => d.resumo,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['crm'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => crm.oportunidades.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const ganhar = useMutation({
        mutationFn: (id: number) => crm.oportunidades.ganhar(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const perder = useMutation({
        mutationFn: () => crm.oportunidades.perder(aPerder!.id, motivo),
        onSuccess: (r) => { feito(r.message); porAPerder(null); porMotivo(''); },
        onError: porErro,
    });

    const reabrir = useMutation({
        mutationFn: (id: number) => crm.oportunidades.reabrir(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const facturar = useMutation({
        mutationFn: (id: number) => crm.oportunidades.facturar(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const mover = useMutation({
        mutationFn: ({ id, direccao }: { id: number; direccao: 'frente' | 'tras' }) =>
            crm.oportunidades.mover(id, direccao),
        onSuccess: () => void cache.invalidateQueries({ queryKey: ['crm'] }),
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.pode_gerir;
    const r = resumo.data;
    const meta = lista.data?.meta;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const abrirNova = () => {
        porAEditar(null);
        porFormulario({ ...VAZIO, stage_id: o.etapas[0]?.valor ?? '' });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Oportunidades')}
                subtitulo={t('A lista é o registo; o funil é o quadro da parede')}
                icone="fa-handshake"
                cor="ciano"
                accoes={
                    <>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNova}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova oportunidade')}
                            </button>
                        )}
                        <a href="/crm/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-bullseye" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                {r && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-filter">
                            {t(':n em aberto', { n: numero(r.abertas) })}
                        </EstadoNaFaixa>
                        {r.por_facturar > 0 && (
                            <EstadoNaFaixa icone="fa-file-invoice">
                                {t(':n ganhos por facturar', { n: numero(r.por_facturar) })}
                            </EstadoNaFaixa>
                        )}
                    </div>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {r && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero
                        aspecto="claro" rotulo={t('Em aberto')} valor={numero(r.abertas)}
                        icone="fa-circle-notch" tom="indigo"
                    />
                    <CartaoNumero
                        aspecto="claro" rotulo={t('Valor no funil')} valor={kz(r.valor)} sufixo="Kz"
                        icone="fa-sack-dollar" tom="teal"
                    />
                    <CartaoNumero
                        aspecto="claro" rotulo={t('Ponderado')} valor={kz(r.ponderado)} sufixo="Kz"
                        icone="fa-scale-balanced" tom="azul"
                        nota={t('Cada negócio vale a probabilidade da sua etapa')}
                    />
                    <CartaoNumero
                        aspecto="claro" rotulo={t('Ganhas')} valor={numero(r.ganhas)}
                        icone="fa-trophy" tom={r.por_facturar > 0 ? 'ambar' : 'verde'}
                        nota={r.por_facturar > 0
                            ? t(':n por facturar', { n: numero(r.por_facturar) })
                            : t('Todas facturadas')}
                    />
                </div>
            )}

            <Separadores
                activa={aba}
                aoMudar={porAba}
                abas={[
                    { chave: 'lista', rotulo: t('Lista'), icone: 'fa-list' },
                    { chave: 'funil', rotulo: t('Funil'), icone: 'fa-filter' },
                ]}
            />

            <PainelDoSeparador chave="lista" activa={aba}>
                <div className="space-y-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input type="search" value={filtros.procura ?? ''}
                                    onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                                    placeholder={t('Título ou cliente…')} className={cls(entrada, 'pl-9')} />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Estado')} className="w-44">
                            <select value={filtros.estado ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined, page: 1 })}
                                className={entrada}>
                                <option value="">{t('Todos')}</option>
                                {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Etapa')} className="w-48">
                            <select value={filtros.etapa ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, etapa: e.target.value ? Number(e.target.value) : '', page: 1 })}
                                className={entrada}>
                                <option value="">{t('Todas')}</option>
                                {o.etapas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>

                    {lista.isPending ? (
                        <Carregando linhas={6} />
                    ) : lista.isError ? (
                        <AvisoDeErro erro={lista.error} />
                    ) : lista.data.data.length === 0 ? (
                        <SemNada
                            icone="fa-handshake"
                            titulo={t('Nenhuma oportunidade')}
                            frase={t('Um negócio com nome, valor e etapa: é o que enche o funil e dá a taxa de conversão.')}
                            accao={pode ? (
                                <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>
                                    {t('Nova oportunidade')}
                                </Botao>
                            ) : undefined}
                        />
                    ) : (
                        <div className={cls('overflow-x-auto', CARTAO)}>
                            <table className="w-full min-w-[54rem] text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3 text-left">{t('Negócio')}</th>
                                        <th className="px-4 py-3 text-left">{t('Cliente')}</th>
                                        <th className="px-4 py-3 text-left">{t('Etapa')}</th>
                                        <th className="px-4 py-3 text-right">{t('Valor')}</th>
                                        <th className="px-4 py-3 text-right">{t('Ponderado')}</th>
                                        <th className="px-4 py-3 text-left">{t('Estado')}</th>
                                        <th className="px-4 py-3 text-left">{t('Factura')}</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {lista.data.data.map((x, i) => (
                                        <tr key={x.id} style={cascata(i)} className="entra transition hover:bg-slate-50/70">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-800">{x.titulo}</p>
                                                {x.fecho_previsto && (
                                                    <p className="text-xs text-slate-500">
                                                        {t('Fecho previsto: :dia', { dia: x.fecho_previsto })}
                                                    </p>
                                                )}
                                                {x.motivo_da_perda && (
                                                    <p className="text-xs text-red-600">{x.motivo_da_perda}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{x.cliente ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <span className="text-slate-600">{x.etapa ?? '—'}</span>
                                                <span className="ml-1.5 text-xs text-slate-400">{x.probabilidade}%</span>
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                                {kz(x.valor)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-slate-500">
                                                {kz(x.ponderado)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Etiqueta cor={COR_DO_ESTADO[x.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[x.estado]}>
                                                    {x.estado_rotulo}
                                                </Etiqueta>
                                            </td>
                                            <td className="px-4 py-3">
                                                {x.factura ? (
                                                    <a href={`/invoicing/sales-invoices/${x.factura.id}`}
                                                        className={cls('font-mono text-xs text-cyan-700 underline-offset-2 hover:underline', FOCO)}>
                                                        {x.factura.numero}
                                                    </a>
                                                ) : x.estado === 'won' ? (
                                                    <Etiqueta cor="aviso" icone="fa-hourglass">{t('Por facturar')}</Etiqueta>
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Botao altura="pequeno" icone="fa-clock-rotate-left"
                                                        onClick={() => porAHistorico(x)} aria-label={t('Histórico')} />

                                                    {pode && x.estado === 'open' && (
                                                        <>
                                                            <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-trophy"
                                                                aTrabalhar={ganhar.isPending}
                                                                onClick={() => ganhar.mutate(x.id)}>
                                                                {t('Ganhar')}
                                                            </Botao>
                                                            <Botao altura="pequeno" cor="perigo" icone="fa-xmark"
                                                                onClick={() => { porAPerder(x); porMotivo(''); }}
                                                                aria-label={t('Dar por perdida')} />
                                                        </>
                                                    )}

                                                    {pode && x.estado === 'won' && !x.factura && o.permissoes.pode_facturar && (
                                                        <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-file-invoice"
                                                            aTrabalhar={facturar.isPending}
                                                            onClick={() => facturar.mutate(x.id)}>
                                                            {t('Facturar')}
                                                        </Botao>
                                                    )}

                                                    {pode && x.estado !== 'open' && !x.factura && (
                                                        <Botao altura="pequeno" icone="fa-rotate-left"
                                                            aTrabalhar={reabrir.isPending}
                                                            onClick={() => reabrir.mutate(x.id)}
                                                            aria-label={t('Reabrir')} />
                                                    )}

                                                    {pode && (
                                                        <Botao altura="pequeno" icone="fa-pen"
                                                            onClick={() => {
                                                                porAEditar(x.id);
                                                                porFormulario({
                                                                    title: x.titulo,
                                                                    client_id: x.client_id ? String(x.client_id) : '',
                                                                    stage_id: x.stage_id ? String(x.stage_id) : '',
                                                                    amount: String(x.valor),
                                                                    expected_close_date: x.fecho_previsto ?? '',
                                                                    notes: x.notas ?? '',
                                                                });
                                                            }}
                                                            aria-label={t('Editar oportunidade')} />
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {meta && meta.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-slate-500">
                                {t('A mostrar :de a :ate de :total', {
                                    de: String(meta.from ?? 0), ate: String(meta.to ?? 0), total: String(meta.total),
                                })}
                            </p>
                            <div className="flex items-center gap-2">
                                <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
                                <Botao altura="pequeno" icone="fa-chevron-left" disabled={meta.current_page <= 1}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page - 1 })}
                                    aria-label={t('Página anterior')} />
                                <span className="text-xs font-semibold tabular-nums text-slate-600">
                                    {meta.current_page}/{meta.last_page}
                                </span>
                                <Botao altura="pequeno" icone="fa-chevron-right" disabled={meta.current_page >= meta.last_page}
                                    onClick={() => porFiltros({ ...filtros, page: meta.current_page + 1 })}
                                    aria-label={t('Página seguinte')} />
                            </div>
                        </div>
                    )}
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="funil" activa={aba}>
                {funil.isPending ? (
                    <Carregando linhas={6} />
                ) : funil.isError ? (
                    <AvisoDeErro erro={funil.error} />
                ) : (
                    <div className="overflow-x-auto pb-2">
                        <div className="flex min-w-max gap-4">
                            {funil.data.colunas.map((coluna, i) => (
                                <section
                                    key={coluna.id}
                                    style={cascata(i)}
                                    className={cls('entra flex w-72 flex-none flex-col', CARTAO)}
                                >
                                    <header className="border-b border-slate-100 bg-slate-50/60 px-4 py-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate text-sm font-bold text-slate-800">{coluna.nome}</p>
                                            <Etiqueta cor="neutra">{coluna.probabilidade}%</Etiqueta>
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500 tabular-nums">
                                            {t(':n · :v Kz', {
                                                n: String(coluna.cartoes.length), v: kz(coluna.total, 0),
                                            })}
                                        </p>
                                        {/*
                                          * O PONDERADO POR COLUNA é o que torna o
                                          * quadro honesto: um negócio na primeira
                                          * etapa vale 10% do que diz.
                                          */}
                                        <p className="text-[11px] text-slate-400 tabular-nums">
                                            {t('Ponderado: :v Kz', { v: kz(coluna.ponderado, 0) })}
                                        </p>
                                    </header>

                                    <ul className="flex-1 space-y-2 p-3">
                                        {coluna.cartoes.length === 0 ? (
                                            <li className="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                                                {t('Coluna vazia.')}
                                            </li>
                                        ) : coluna.cartoes.map((c) => (
                                            <li key={c.id}
                                                className={cls('border border-slate-200 bg-white p-3 transition hover:-translate-y-0.5 hover:shadow-md', RAIO)}>
                                                <p className="truncate text-sm font-semibold text-slate-800">{c.titulo}</p>
                                                <p className="truncate text-xs text-slate-500">
                                                    {c.cliente ?? t('Sem cliente')}
                                                </p>
                                                <div className="mt-2 flex items-center justify-between gap-2">
                                                    <span className="text-sm font-bold tabular-nums text-slate-900">
                                                        {kz(c.valor)}
                                                    </span>
                                                    {funil.data.permissoes.pode_gerir && (
                                                        <span className="flex items-center gap-1">
                                                            <Botao altura="pequeno" icone="fa-chevron-left"
                                                                aTrabalhar={mover.isPending}
                                                                onClick={() => mover.mutate({ id: c.id, direccao: 'tras' })}
                                                                aria-label={t('Etapa anterior')} />
                                                            <Botao altura="pequeno" icone="fa-chevron-right"
                                                                aTrabalhar={mover.isPending}
                                                                onClick={() => mover.mutate({ id: c.id, direccao: 'frente' })}
                                                                aria-label={t('Etapa seguinte')} />
                                                        </span>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                        </div>
                    </div>
                )}
            </PainelDoSeparador>

            {/* ─── O formulário ───────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar oportunidade') : t('Nova oportunidade')}
                subtitulo={t('A etapa traz consigo a probabilidade')}
                icone="fa-handshake"
                cor="ciano"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                client_id: formulario.client_id ? Number(formulario.client_id) : null,
                                stage_id: formulario.stage_id ? Number(formulario.stage_id) : null,
                                amount: Number(formulario.amount || 0),
                                expected_close_date: formulario.expected_close_date || null,
                            })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Título')} obrigatorio erro={erros.title}>
                            <input type="text" value={formulario.title}
                                onChange={(e) => porFormulario({ ...formulario, title: e.target.value })}
                                placeholder={t('Ex.: Proposta de sonorização')} className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Cliente')} erro={erros.client_id}>
                                <select value={formulario.client_id}
                                    onChange={(e) => porFormulario({ ...formulario, client_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Sem cliente')}</option>
                                    {o.clientes.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Etapa')} obrigatorio erro={erros.stage_id}
                                ajuda={t('A probabilidade da etapa é a que pesa o valor no funil.')}>
                                <select value={formulario.stage_id}
                                    onChange={(e) => porFormulario({ ...formulario, stage_id: e.target.value })}
                                    className={entrada}>
                                    {o.etapas.map((x) => (
                                        <option key={x.valor} value={x.valor}>
                                            {x.rotulo} ({x.probabilidade}%)
                                        </option>
                                    ))}
                                </select>
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Valor')} erro={erros.amount}>
                                <input type="number" step="0.01" min="0" value={formulario.amount}
                                    onChange={(e) => porFormulario({ ...formulario, amount: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta={t('Fecho previsto')} erro={erros.expected_close_date}>
                                <input type="date" value={formulario.expected_close_date}
                                    onChange={(e) => porFormulario({ ...formulario, expected_close_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Notas')} erro={erros.notes}>
                            <textarea rows={3} value={formulario.notes}
                                onChange={(e) => porFormulario({ ...formulario, notes: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aPerder !== null}
                aoFechar={() => porAPerder(null)}
                titulo={t('Dar por perdida')}
                subtitulo={aPerder?.titulo}
                icone="fa-xmark"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAPerder(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-check" aTrabalhar={perder.isPending}
                            disabled={motivo.trim().length < 3} onClick={() => perder.mutate()}>
                            {t('Registar')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-4">
                    <AvisoDeErro erro={perder.error} />

                    <Campo etiqueta={t('Motivo')} obrigatorio
                        ajuda={t('Os motivos somados dizem onde se perde — é para isso que se pergunta.')}>
                        <textarea rows={3} value={motivo} onChange={(e) => porMotivo(e.target.value)}
                            className={entrada} />
                    </Campo>
                </div>
            </Modal>

            <Historico oportunidade={aHistorico} aoFechar={() => porAHistorico(null)}
                tipos={o.tipos_de_actividade} pode={pode} aoFeito={feito} />
        </div>
    );
}

/* ─── O histórico ─────────────────────────────────────────────────────── */

function Historico({
    oportunidade, aoFechar, tipos, pode, aoFeito,
}: {
    oportunidade: Oportunidade | null;
    aoFechar: () => void;
    tipos: Array<{ valor: string; rotulo: string }>;
    pode: boolean;
    aoFeito: (m: string) => void;
}) {
    const cache = useQueryClient();
    const [nova, porNova] = useState({ type: 'chamada', subject: '', notes: '' });

    const historico = useQuery({
        queryKey: ['crm', 'oportunidades', 'historico', oportunidade?.id],
        queryFn: () => crm.oportunidades.historico(oportunidade!.id),
        enabled: oportunidade !== null,
    });

    const registar = useMutation({
        mutationFn: () => crm.oportunidades.actividade(oportunidade!.id, nova),
        onSuccess: (r) => {
            porNova({ type: 'chamada', subject: '', notes: '' });
            aoFeito(r.message);
            void cache.invalidateQueries({ queryKey: ['crm', 'oportunidades', 'historico'] });
        },
    });

    return (
        <Modal
            aberto={oportunidade !== null}
            aoFechar={aoFechar}
            titulo={t('Histórico')}
            subtitulo={oportunidade?.titulo}
            icone="fa-clock-rotate-left"
            cor="ciano"
            largura="md"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            <div className="space-y-4">
                {historico.isPending ? (
                    <Carregando linhas={4} />
                ) : historico.isError ? (
                    <AvisoDeErro erro={historico.error} />
                ) : historico.data.data.length === 0 ? (
                    <SemNada icone="fa-clock-rotate-left" titulo={t('Sem histórico')}
                        frase={t('Cada chamada, reunião ou visita registada aparece aqui, por ordem.')} />
                ) : (
                    <ol className="space-y-2">
                        {historico.data.data.map((a, i) => (
                            <li key={a.id} style={cascata(i)}
                                className={cls('entra border border-slate-200 bg-white px-3 py-2', RAIO)}>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-xs font-semibold text-slate-700">
                                        {a.tipo_rotulo} · {a.assunto}
                                    </span>
                                    <span className="flex-none text-[11px] tabular-nums text-slate-500">{a.quando}</span>
                                </div>
                                {a.notas && <p className="mt-1 text-sm text-slate-700">{a.notas}</p>}
                            </li>
                        ))}
                    </ol>
                )}

                {pode && (
                    <form
                        className="space-y-3 border-t border-slate-100 pt-4"
                        onSubmit={(e) => { e.preventDefault(); if (nova.subject.trim()) registar.mutate(); }}
                    >
                        <AvisoDeErro erro={registar.error} />

                        <div className="flex flex-wrap items-end gap-3">
                            <Campo etiqueta={t('Tipo')} className="w-40">
                                <select value={nova.type} onChange={(e) => porNova({ ...nova, type: e.target.value })}
                                    className={entrada}>
                                    {tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Assunto')} className="min-w-[12rem] flex-1">
                                <input type="text" value={nova.subject}
                                    onChange={(e) => porNova({ ...nova, subject: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Botao type="submit" cor="primaria" tom="solida" icone="fa-plus"
                                aTrabalhar={registar.isPending} disabled={!nova.subject.trim()}>
                                {t('Registar')}
                            </Botao>
                        </div>
                    </form>
                )}
            </div>
        </Modal>
    );
}
