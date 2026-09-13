import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    eventos, type DiaDoCalendario, type Evento, type FiltrosDaAgenda, type Tarefa,
} from '@/api/eventos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';
import { cor } from './Painel';

/**
 * A AGENDA DOS EVENTOS — a lista e o calendário no mesmo ecrã.
 *
 * O CALENDÁRIO É DESENHADO AQUI. O ecrã antigo puxava o FullCalendar de um CDN:
 * numa instalação sem internet a página ficava um quadrado branco sem aviso
 * nenhum, e mesmo com rede o mês só aparecia depois de a biblioteca descer.
 *
 * E O ESTADO PASSOU A TER REGRAS: os botões que aparecem são os passos que a
 * marcação pode mesmo dar, e vêm do servidor. Antes estavam lá todos, e dava
 * para concluir um evento cancelado.
 */

const HOJE = () => new Date().toISOString().slice(0, 10);

const VAZIO = {
    name: '', start_date: '', end_date: '', type_id: '', client_id: '', venue_id: '',
    expected_attendees: '', total_value: '0', description: '', notes: '',
    setup_start: '', teardown_end: '',
};

type Formulario = typeof VAZIO;

export default function Agenda({ evento: abrirEvento }: { evento?: number }) {
    const cache = useQueryClient();

    const [vista, porVista] = useState<'lista' | 'calendario'>('calendario');
    const [mes, porMes] = useState(() => new Date().toISOString().slice(0, 7));
    const [filtros, porFiltros] = useState<FiltrosDaAgenda>({ por_pagina: 15, page: 1 });
    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<Formulario | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<number | null>(abrirEvento ?? null);
    const [aApagar, porAApagar] = useState<Evento | null>(null);

    const [novoCliente, porNovoCliente] = useState<Record<string, string> | null>(null);
    const [novoLocal, porNovoLocal] = useState<Record<string, string> | null>(null);
    const [novoTipo, porNovoTipo] = useState<Record<string, string> | null>(null);

    const opcoes = useQuery({ queryKey: ['eventos', 'agenda', 'opcoes'], queryFn: () => eventos.agenda.opcoes() });

    const lista = useQuery({
        queryKey: ['eventos', 'agenda', 'lista', filtros],
        queryFn: () => eventos.agenda.lista(filtros),
        enabled: vista === 'lista',
    });

    const calendario = useQuery({
        queryKey: ['eventos', 'agenda', 'calendario', mes, filtros.estado, filtros.fase, filtros.tipo],
        queryFn: () => eventos.agenda.calendario({
            mes, estado: filtros.estado, fase: filtros.fase, tipo: filtros.tipo,
        }),
        enabled: vista === 'calendario',
    });

    const resumo = useQuery({
        queryKey: ['eventos', 'agenda', 'resumo'],
        queryFn: () => eventos.agenda.lista({ por_pagina: 5, page: 1 }),
        select: (d) => d.resumo,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['eventos'] });
    };

    const guardar = useMutation({
        mutationFn: (dados: Record<string, unknown>) => eventos.agenda.guardar(aEditar, dados),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const estado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => eventos.agenda.estado(id, estado),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const fase = useMutation({
        mutationFn: (id: number) => eventos.agenda.avancarFase(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => eventos.agenda.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.pode_gerir;
    const r = resumo.data;

    const abrirNovo = (dia?: string) => {
        const inicio = dia ? `${dia}T09:00` : `${HOJE()}T09:00`;
        const fim = dia ? `${dia}T18:00` : `${HOJE()}T18:00`;

        porAEditar(null);
        porFormulario({ ...VAZIO, start_date: inicio, end_date: fim });
    };

    const abrirEdicao = (e: Evento) => {
        porAEditar(e.id);
        porFormulario({
            name: e.nome,
            start_date: e.inicio ?? '',
            end_date: e.fim ?? '',
            type_id: e.type_id ? String(e.type_id) : '',
            client_id: e.client_id ? String(e.client_id) : '',
            venue_id: e.venue_id ? String(e.venue_id) : '',
            expected_attendees: e.pessoas ? String(e.pessoas) : '',
            total_value: String(e.valor ?? 0),
            description: '', notes: '', setup_start: '', teardown_end: '',
        });
    };

    const submeter = () => {
        if (!formulario) return;

        guardar.mutate({
            name: formulario.name,
            start_date: formulario.start_date,
            end_date: formulario.end_date,
            type_id: formulario.type_id ? Number(formulario.type_id) : null,
            client_id: formulario.client_id ? Number(formulario.client_id) : null,
            venue_id: formulario.venue_id ? Number(formulario.venue_id) : null,
            expected_attendees: formulario.expected_attendees ? Number(formulario.expected_attendees) : null,
            total_value: Number(formulario.total_value || 0),
            description: formulario.description || null,
            notes: formulario.notes || null,
            setup_start: formulario.setup_start || null,
            teardown_end: formulario.teardown_end || null,
        });
    };

    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};
    const meta = lista.data?.meta;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    const andarMes = (passo: number) => {
        const partes = mes.split('-');
        const d = new Date(Number(partes[0]), Number(partes[1]) - 1 + passo, 1);

        porMes(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Agenda de Eventos')}
                subtitulo={t('O mês inteiro, ou a lista com filtros')}
                icone="fa-calendar-days"
                cor="rosa"
                accoes={
                    <>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={() => abrirNovo()}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo evento')}
                            </button>
                        )}
                        <a href="/events/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chart-pie" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                {r && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-file-lines">
                            {t(':n em orçamento', { n: numero(r.orcamento) })}
                        </EstadoNaFaixa>
                        <EstadoNaFaixa icone="fa-play">
                            {t(':n a decorrer', { n: numero(r.a_decorrer) })}
                        </EstadoNaFaixa>
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
                    <CartaoNumero rotulo={t('Total')} valor={numero(r.total)} icone="fa-calendar" tom="roxo" />
                    <CartaoNumero rotulo={t('Confirmados')} valor={numero(r.confirmados)} icone="fa-circle-check" tom="azul" />
                    <CartaoNumero rotulo={t('A decorrer')} valor={numero(r.a_decorrer)} icone="fa-play" tom="ambar" />
                    <CartaoNumero rotulo={t('Concluídos no mês')} valor={numero(r.concluidos_no_mes)} icone="fa-flag-checkered" tom="verde" />
                </div>
            )}

            {/* As duas vistas do mesmo mês. */}
            <div className="flex flex-wrap items-center gap-2">
                <div className={cls('inline-flex overflow-hidden border border-slate-200 bg-white', RAIO)}>
                    {(['calendario', 'lista'] as const).map((v) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => porVista(v)}
                            className={cls(
                                'px-4 py-2 text-sm font-semibold transition', FOCO,
                                vista === v ? 'bg-pink-600 text-white' : 'text-slate-600 hover:bg-slate-50',
                            )}
                        >
                            <i className={`fas ${v === 'lista' ? 'fa-list' : 'fa-calendar-days'} mr-1.5`} aria-hidden="true" />
                            {v === 'lista' ? t('Lista') : t('Calendário')}
                        </button>
                    ))}
                </div>

                <Campo etiqueta={t('Estado')} className="w-44">
                    <select
                        value={filtros.estado ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined, page: 1 })}
                        className={entrada}
                    >
                        <option value="">{t('Todos')}</option>
                        {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Fase')} className="w-44">
                    <select
                        value={filtros.fase ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, fase: e.target.value || undefined, page: 1 })}
                        className={entrada}
                    >
                        <option value="">{t('Todas')}</option>
                        {o.fases.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Tipo')} className="w-48">
                    <select
                        value={filtros.tipo ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, tipo: e.target.value ? Number(e.target.value) : '', page: 1 })}
                        className={entrada}
                    >
                        <option value="">{t('Todos')}</option>
                        {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.icone} {x.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {vista === 'calendario' ? (
                <section className={cls('overflow-hidden', CARTAO)}>
                    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3">
                        <div className="flex items-center gap-2">
                            <Botao altura="pequeno" icone="fa-chevron-left" onClick={() => andarMes(-1)} aria-label={t('Mês anterior')} />
                            <p className="min-w-[10rem] text-center text-sm font-bold capitalize text-slate-800">
                                {calendario.data?.rotulo ?? mes}
                            </p>
                            <Botao altura="pequeno" icone="fa-chevron-right" onClick={() => andarMes(1)} aria-label={t('Mês seguinte')} />
                        </div>
                        <Botao altura="pequeno" onClick={() => porMes(new Date().toISOString().slice(0, 7))}>
                            {t('Este mês')}
                        </Botao>
                    </header>

                    {calendario.isPending ? (
                        <div className="p-5"><Carregando linhas={6} /></div>
                    ) : calendario.isError ? (
                        <div className="p-5"><AvisoDeErro erro={calendario.error} /></div>
                    ) : (
                        <GrelhaDoMes
                            dias={calendario.data.dias}
                            podeCriar={pode}
                            aoNovo={abrirNovo}
                            aoAbrir={porAVer}
                        />
                    )}
                </section>
            ) : (
                <>
                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    type="search"
                                    value={filtros.procura ?? ''}
                                    onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                                    placeholder={t('Número ou nome do evento…')}
                                    className={cls(entrada, 'pl-9')}
                                />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('De')} className="w-40">
                            <input type="date" value={filtros.de ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, de: e.target.value, page: 1 })}
                                className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Até')} className="w-40">
                            <input type="date" value={filtros.ate ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, ate: e.target.value, page: 1 })}
                                className={entrada} />
                        </Campo>
                    </div>

                    {lista.isPending ? (
                        <Carregando linhas={6} />
                    ) : lista.isError ? (
                        <AvisoDeErro erro={lista.error} />
                    ) : lista.data.data.length === 0 ? (
                        <SemNada
                            icone="fa-calendar-xmark"
                            titulo={t('Nenhum evento')}
                            frase={t('Marque o primeiro, ou limpe os filtros.')}
                        />
                    ) : (
                        <div className={cls('overflow-x-auto', CARTAO)}>
                            <table className="w-full min-w-[56rem] text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3 text-left">{t('Evento')}</th>
                                        <th className="px-4 py-3 text-left">{t('Cliente')}</th>
                                        <th className="px-4 py-3 text-left">{t('Local')}</th>
                                        <th className="px-4 py-3 text-left">{t('Quando')}</th>
                                        <th className="px-4 py-3 text-left">{t('Fase')}</th>
                                        <th className="px-4 py-3 text-left">{t('Estado')}</th>
                                        <th className="px-4 py-3 text-right">{t('Valor')}</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {lista.data.data.map((e, i) => (
                                        <tr key={e.id} style={cascata(i)} className="entra transition hover:bg-slate-50/70">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-800">
                                                    {e.tipo_icone && <span className="mr-1.5">{e.tipo_icone}</span>}
                                                    {e.nome}
                                                </p>
                                                <p className="text-xs text-slate-500">{e.numero}</p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{e.cliente ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600">{e.local ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600 tabular-nums">
                                                {e.inicio?.replace('T', ' ') ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                                                        <div
                                                            className={cls('h-full rounded-full',
                                                                e.progresso >= 80 ? 'bg-emerald-500' : e.progresso >= 40 ? 'bg-amber-500' : 'bg-red-400')}
                                                            style={{ width: `${Math.min(100, Math.max(2, e.progresso))}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-xs text-slate-500">{e.fase_rotulo}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Etiqueta cor={cor(e.estado)} icone={e.estado_icone}>{e.estado_rotulo}</Etiqueta>
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                                {kz(e.valor)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(e.id)} aria-label={t('Ver o evento')} />
                                                    {pode && (
                                                        <>
                                                            <Botao altura="pequeno" icone="fa-pen" onClick={() => abrirEdicao(e)} aria-label={t('Editar evento')} />
                                                            <Botao altura="pequeno" cor="perigo" icone="fa-trash" onClick={() => porAApagar(e)} aria-label={t('Eliminar evento')} />
                                                        </>
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
                </>
            )}

            {/* ─── A janela do evento ─────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar evento') : t('Novo evento')}
                subtitulo={t('O local tem capacidade, e ela conta')}
                icone="fa-calendar-plus"
                cor="rosa"
                largura="lg"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending} onClick={submeter}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Nome do evento')} obrigatorio erro={erros.name}>
                            <input type="text" value={formulario.name}
                                onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                placeholder={t('Ex.: Conferência Anual')} className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Início')} obrigatorio erro={erros.start_date}>
                                <input type="datetime-local" value={formulario.start_date}
                                    onChange={(e) => porFormulario({ ...formulario, start_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Fim')} obrigatorio erro={erros.end_date}>
                                <input type="datetime-local" value={formulario.end_date}
                                    onChange={(e) => porFormulario({ ...formulario, end_date: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta={t('Tipo')} obrigatorio erro={erros.type_id}
                                ajuda={t('A cor do tipo é a que pinta o evento no calendário.')}
                            >
                                <div className="flex gap-2">
                                    <select value={formulario.type_id}
                                        onChange={(e) => porFormulario({ ...formulario, type_id: e.target.value })}
                                        className={entrada}>
                                        <option value="">{t('Escolher…')}</option>
                                        {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.icone} {x.rotulo}</option>)}
                                    </select>
                                    {o.permissoes.pode_criar_tipo && (
                                        <Botao icone="fa-plus" onClick={() => porNovoTipo({ name: '', icon: '📌', color: '#8b5cf6' })}
                                            aria-label={t('Tipo novo')} />
                                    )}
                                </div>
                            </Campo>

                            <Campo etiqueta={t('Pessoas esperadas')} erro={erros.expected_attendees}>
                                <input type="number" min="1" value={formulario.expected_attendees}
                                    onChange={(e) => porFormulario({ ...formulario, expected_attendees: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta={t('Cliente')} erro={erros.client_id}
                                ajuda={t('Quem encomenda raramente já está no sistema.')}
                            >
                                <div className="flex gap-2">
                                    <select value={formulario.client_id}
                                        onChange={(e) => porFormulario({ ...formulario, client_id: e.target.value })}
                                        className={entrada}>
                                        <option value="">{t('Sem cliente')}</option>
                                        {o.clientes.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                                    </select>
                                    {o.permissoes.pode_criar_cliente && (
                                        <Botao icone="fa-plus" onClick={() => porNovoCliente({ name: '', nif: '', country: 'AO', email: '', phone: '' })}
                                            aria-label={t('Cliente novo')} />
                                    )}
                                </div>
                            </Campo>

                            <Campo
                                etiqueta={t('Local')} erro={erros.venue_id}
                                ajuda={t('A capacidade do local recusa um evento que não caiba.')}
                            >
                                <div className="flex gap-2">
                                    <select value={formulario.venue_id}
                                        onChange={(e) => porFormulario({ ...formulario, venue_id: e.target.value })}
                                        className={entrada}>
                                        <option value="">{t('Sem local')}</option>
                                        {o.locais.map((l) => (
                                            <option key={l.valor} value={l.valor}>
                                                {l.rotulo}{l.capacidade > 0 ? ` (${l.capacidade})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {o.permissoes.pode_criar_local && (
                                        <Botao icone="fa-plus" onClick={() => porNovoLocal({ name: '', address: '', capacity: '' })}
                                            aria-label={t('Local novo')} />
                                    )}
                                </div>
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Montagem começa')} erro={erros.setup_start}>
                                <input type="datetime-local" value={formulario.setup_start}
                                    onChange={(e) => porFormulario({ ...formulario, setup_start: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Desmontagem acaba')} erro={erros.teardown_end}>
                                <input type="datetime-local" value={formulario.teardown_end}
                                    onChange={(e) => porFormulario({ ...formulario, teardown_end: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Valor')} erro={erros.total_value}>
                            <input type="number" step="0.01" min="0" value={formulario.total_value}
                                onChange={(e) => porFormulario({ ...formulario, total_value: e.target.value })}
                                className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>

                        <Campo etiqueta={t('Descrição')} erro={erros.description}>
                            <textarea rows={3} value={formulario.description}
                                onChange={(e) => porFormulario({ ...formulario, description: e.target.value })}
                                placeholder={t('O que o evento é, em duas linhas…')} className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Notas internas')} erro={erros.notes}>
                            <textarea rows={2} value={formulario.notes}
                                onChange={(e) => porFormulario({ ...formulario, notes: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>

            <FichaDoEventoModal
                id={aVer}
                aoFechar={() => porAVer(null)}
                pode={pode}
                aEstado={(id, e) => estado.mutate({ id, estado: e })}
                aFase={(id) => fase.mutate(id)}
                aTrabalhar={estado.isPending || fase.isPending}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar evento')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />
                <p className="text-sm text-slate-600">
                    {t('Eliminar «:nome»? Um evento concluído não se apaga — cancele-o, que o historial fica.', {
                        nome: aApagar?.nome ?? '',
                    })}
                </p>
            </Modal>

            <CriarRapido
                titulo={t('Cliente novo')}
                icone="fa-user-plus"
                campos={[
                    { chave: 'name', rotulo: t('Nome'), obrigatorio: true },
                    { chave: 'nif', rotulo: t('NIF'), obrigatorio: true, ajuda: t('Obrigatório para facturar (SAFT-AO).') },
                    { chave: 'email', rotulo: t('E-mail'), tipo: 'email' },
                    { chave: 'phone', rotulo: t('Telefone') },
                ]}
                valores={novoCliente}
                aoMudar={porNovoCliente}
                aoGravar={async (dados) => {
                    const r = await eventos.agenda.clienteRapido(dados);

                    porFormulario((f) => (f ? { ...f, client_id: r.data.valor } : f));
                    feito(r.message);
                }}
            />

            <CriarRapido
                titulo={t('Local novo')}
                icone="fa-map-marker-alt"
                campos={[
                    { chave: 'name', rotulo: t('Nome'), obrigatorio: true },
                    { chave: 'address', rotulo: t('Morada') },
                    { chave: 'capacity', rotulo: t('Capacidade'), tipo: 'number', ajuda: t('Quantas pessoas leva — e o evento não passa daí.') },
                ]}
                valores={novoLocal}
                aoMudar={porNovoLocal}
                aoGravar={async (dados) => {
                    const r = await eventos.agenda.localRapido(dados);

                    porFormulario((f) => (f ? { ...f, venue_id: r.data.valor } : f));
                    feito(r.message);
                }}
            />

            <Modal
                aberto={novoTipo !== null}
                aoFechar={() => porNovoTipo(null)}
                titulo={t('Tipo novo')}
                icone="fa-tags"
                cor="rosa"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porNovoTipo(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check"
                            onClick={async () => {
                                if (!novoTipo) return;

                                try {
                                    const r = await eventos.agenda.tipoRapido(novoTipo);

                                    porFormulario((f) => (f ? { ...f, type_id: r.data.valor } : f));
                                    porNovoTipo(null);
                                    feito(r.message);
                                } catch (e) {
                                    porErro(e);
                                }
                            }}
                        >
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {novoTipo && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} obrigatorio>
                            <input type="text" value={novoTipo.name}
                                onChange={(e) => porNovoTipo({ ...novoTipo, name: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Ícone')} obrigatorio>
                            <div className="flex flex-wrap gap-2">
                                {o.emojis.map((e) => (
                                    <button
                                        key={e.valor}
                                        type="button"
                                        onClick={() => porNovoTipo({ ...novoTipo, icon: e.valor })}
                                        title={e.rotulo}
                                        className={cls(
                                            'grid h-10 w-10 place-items-center rounded-xl border-2 text-lg transition hover:-translate-y-0.5', FOCO,
                                            novoTipo.icon === e.valor ? 'border-pink-500 bg-pink-50' : 'border-slate-200 bg-white',
                                        )}
                                    >
                                        {e.valor}
                                    </button>
                                ))}
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Cor')} obrigatorio ajuda={t('É esta que pinta o evento no calendário.')}>
                            <input type="color" value={novoTipo.color}
                                onChange={(e) => porNovoTipo({ ...novoTipo, color: e.target.value })}
                                className="h-11 w-full cursor-pointer rounded-xl border border-slate-200" />
                        </Campo>
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── A grelha do mês ─────────────────────────────────────────────────── */

const DIAS_DA_SEMANA = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

/**
 * O MÊS EM SEMANAS INTEIRAS.
 *
 * Os dias vêm do servidor já agrupados — e um evento de três dias aparece nos
 * três, que era outra coisa que o calendário antigo não fazia: comparava só a
 * data de início, e um evento que atravessasse o fim do mês desaparecia do mês
 * em que estava mesmo a acontecer.
 */
function GrelhaDoMes({
    dias, podeCriar, aoNovo, aoAbrir,
}: {
    dias: DiaDoCalendario[];
    podeCriar: boolean;
    aoNovo: (dia: string) => void;
    aoAbrir: (id: number) => void;
}) {
    return (
        <div className="overflow-x-auto">
            <div className="min-w-[48rem]">
                <div className="grid grid-cols-7 border-b border-slate-100 bg-slate-50/60">
                    {DIAS_DA_SEMANA.map((d) => (
                        <div key={d} className="px-2 py-2 text-center text-xs font-bold uppercase tracking-wider text-slate-500">
                            {t(d)}
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-7">
                    {dias.map((d, i) => (
                        <div
                            key={d.dia}
                            style={cascata(Math.min(i, 12))}
                            className={cls(
                                'entra min-h-[6.5rem] border-b border-r border-slate-100 p-1.5',
                                d.do_mes ? 'bg-white' : 'bg-slate-50/50',
                                d.hoje && 'ring-2 ring-inset ring-pink-400',
                            )}
                        >
                            <div className="mb-1 flex items-center justify-between">
                                <span className={cls(
                                    'text-xs font-bold tabular-nums',
                                    d.hoje ? 'grid h-5 w-5 place-items-center rounded-full bg-pink-600 text-white'
                                           : d.do_mes ? 'text-slate-700' : 'text-slate-300',
                                )}>
                                    {d.numero}
                                </span>
                                {podeCriar && d.do_mes && (
                                    <button
                                        type="button"
                                        onClick={() => aoNovo(d.dia)}
                                        className={cls('grid h-5 w-5 place-items-center rounded-md text-slate-300 opacity-0 transition hover:bg-pink-50 hover:text-pink-600 focus-visible:opacity-100 group-hover:opacity-100', FOCO)}
                                        aria-label={t('Marcar evento neste dia')}
                                    >
                                        <i className="fas fa-plus text-[10px]" aria-hidden="true" />
                                    </button>
                                )}
                            </div>

                            <ul className="space-y-1">
                                {d.eventos.slice(0, 3).map((e) => (
                                    <li key={`${d.dia}-${e.id}`}>
                                        <button
                                            type="button"
                                            onClick={() => aoAbrir(e.id)}
                                            title={`${e.nome} — ${e.estado_rotulo}`}
                                            className={cls('w-full truncate rounded-md px-1.5 py-0.5 text-left text-[11px] font-semibold text-white transition hover:brightness-110', FOCO)}
                                            style={{ backgroundColor: e.cor ?? '#6366f1' }}
                                        >
                                            {e.tipo_icone && <span className="mr-0.5">{e.tipo_icone}</span>}
                                            {e.nome}
                                        </button>
                                    </li>
                                ))}
                                {d.eventos.length > 3 && (
                                    <li className="px-1.5 text-[10px] font-semibold text-slate-400">
                                        {t('+:n', { n: String(d.eventos.length - 3) })}
                                    </li>
                                )}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ─── A ficha ─────────────────────────────────────────────────────────── */

/**
 * A FICHA DO EVENTO, com o CHECKLIST da fase.
 *
 * É aqui que a fase se avança — e só avança com as tarefas obrigatórias feitas.
 * O aviso diz-o antes de se tentar, em vez de dar um erro depois de carregar.
 */
function FichaDoEventoModal({
    id, aoFechar, pode, aEstado, aFase, aTrabalhar,
}: {
    id: number | null;
    aoFechar: () => void;
    pode: boolean;
    aEstado: (id: number, estado: string) => void;
    aFase: (id: number) => void;
    aTrabalhar: boolean;
}) {
    const cache = useQueryClient();

    const ficha = useQuery({
        queryKey: ['eventos', 'agenda', 'ficha', id],
        queryFn: () => eventos.agenda.ficha(id as number),
        enabled: id !== null,
    });

    const tarefa = useMutation({
        mutationFn: (t: number) => eventos.agenda.tarefa(t),
        onSuccess: () => void cache.invalidateQueries({ queryKey: ['eventos'] }),
    });

    const e = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={e?.nome ?? t('Evento')}
            subtitulo={e?.numero}
            icone="fa-calendar-check"
            cor="rosa"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {ficha.isPending ? (
                <Carregando linhas={6} />
            ) : ficha.isError ? (
                <AvisoDeErro erro={ficha.error} />
            ) : e ? (
                <div className="space-y-5">
                    <div className="flex flex-wrap items-center gap-2">
                        <Etiqueta cor={cor(e.estado)} icone={e.estado_icone}>{e.estado_rotulo}</Etiqueta>
                        <Etiqueta cor="primaria" icone="fa-diagram-project">{e.fase_rotulo}</Etiqueta>
                        <Etiqueta cor={e.progresso >= 80 ? 'bom' : e.progresso >= 40 ? 'aviso' : 'perigo'} icone="fa-list-check">
                            {t(':n% feito', { n: String(e.progresso) })}
                        </Etiqueta>
                    </div>

                    <dl className="grid gap-3 sm:grid-cols-2">
                        <Linha rotulo={t('Cliente')} valor={e.cliente} nota={e.cliente_telefone} />
                        <Linha rotulo={t('Local')} valor={e.local} nota={[e.local_cidade, e.local_capacidade ? t(':n lugares', { n: String(e.local_capacidade) }) : null].filter(Boolean).join(' · ')} />
                        <Linha rotulo={t('Início')} valor={e.inicio?.replace('T', ' ')} />
                        <Linha rotulo={t('Fim')} valor={e.fim?.replace('T', ' ')} />
                        <Linha rotulo={t('Montagem')} valor={e.montagem_em} />
                        <Linha rotulo={t('Desmontagem')} valor={e.desmontagem_em} />
                        <Linha rotulo={t('Pessoas')} valor={e.pessoas ? String(e.pessoas) : null} />
                        <Linha rotulo={t('Valor')} valor={`${kz(e.valor)} Kz`} />
                        <Linha rotulo={t('Responsável')} valor={e.responsavel} />
                        <Linha rotulo={t('Tipo')} valor={e.tipo} />
                    </dl>

                    {e.descricao && (
                        <p className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                            {e.descricao}
                        </p>
                    )}

                    {pode && (
                        <div className="flex flex-wrap items-center gap-2">
                            {e.pode.map((p) => (
                                <Botao
                                    key={p.valor}
                                    altura="pequeno"
                                    cor={p.valor === 'cancelado' ? 'perigo' : p.valor === 'concluido' ? 'bom' : 'primaria'}
                                    tom="solida"
                                    icone="fa-arrow-right"
                                    aTrabalhar={aTrabalhar}
                                    onClick={() => aEstado(e.id, p.valor)}
                                >
                                    {p.rotulo}
                                </Botao>
                            ))}

                            <Botao
                                altura="pequeno"
                                icone="fa-forward"
                                disabled={!e.pode_avancar}
                                aTrabalhar={aTrabalhar}
                                onClick={() => aFase(e.id)}
                                title={e.pode_avancar ? undefined : t('Faltam tarefas obrigatórias nesta fase.')}
                            >
                                {t('Avançar de fase')}
                            </Botao>
                        </div>
                    )}

                    <section>
                        <h3 className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-list-check mr-2 text-pink-600" aria-hidden="true" />
                            {t('O que falta fazer')}
                        </h3>

                        {(ficha.data.tarefas ?? []).length === 0 ? (
                            <p className="text-sm text-slate-500">{t('Este evento não tem tarefas.')}</p>
                        ) : (
                            <ul className="space-y-1.5">
                                {ficha.data.tarefas.map((x: Tarefa) => (
                                    <li key={x.id}>
                                        <label className={cls(
                                            'flex cursor-pointer items-center gap-3 border px-3 py-2 transition hover:bg-slate-50', RAIO,
                                            x.feita ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200 bg-white',
                                        )}>
                                            <input
                                                type="checkbox"
                                                checked={x.feita}
                                                disabled={!pode || tarefa.isPending}
                                                onChange={() => tarefa.mutate(x.id)}
                                                className="h-4 w-4 rounded text-pink-600"
                                            />
                                            <span className={cls('min-w-0 flex-1 text-sm', x.feita ? 'text-slate-400 line-through' : 'text-slate-700')}>
                                                {x.tarefa}
                                            </span>
                                            {x.obrigatoria && (
                                                <Etiqueta cor="aviso" icone="fa-asterisk">{t('Obrigatória')}</Etiqueta>
                                            )}
                                            <span className="flex-none text-[10px] uppercase tracking-wider text-slate-400">
                                                {x.fase_rotulo}
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            ) : null}
        </Modal>
    );
}

function Linha({ rotulo, valor, nota }: { rotulo: string; valor?: string | null; nota?: string | null }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className="text-sm font-medium text-slate-800">{valor || '—'}</dd>
            {nota && <dd className="text-xs text-slate-500">{nota}</dd>}
        </div>
    );
}

/* ─── O «criar aqui mesmo» ────────────────────────────────────────────── */

/**
 * As janelinhas de criar sem sair da marcação.
 *
 * São três — cliente, local e tipo — e tinham o mesmo desenho copiado três
 * vezes no Blade. Aqui é um componente só, e o que muda são os campos.
 */
function CriarRapido({
    titulo, icone, campos, valores, aoMudar, aoGravar,
}: {
    titulo: string;
    icone: string;
    campos: Array<{ chave: string; rotulo: string; tipo?: string; obrigatorio?: boolean; ajuda?: string }>;
    valores: Record<string, string> | null;
    aoMudar: (v: Record<string, string> | null) => void;
    aoGravar: (dados: Record<string, string>) => Promise<void>;
}) {
    const [erro, porErro] = useState<unknown>(null);
    const [aTrabalhar, porATrabalhar] = useState(false);

    useEffect(() => { if (valores === null) porErro(null); }, [valores]);

    return (
        <Modal
            aberto={valores !== null}
            aoFechar={() => aoMudar(null)}
            titulo={titulo}
            icone={icone}
            cor="rosa"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={() => aoMudar(null)}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aTrabalhar}
                        onClick={async () => {
                            if (!valores) return;

                            porATrabalhar(true);
                            porErro(null);

                            try {
                                await aoGravar(valores);
                                aoMudar(null);
                            } catch (e) {
                                porErro(e);
                            } finally {
                                porATrabalhar(false);
                            }
                        }}
                    >
                        {t('Criar')}
                    </Botao>
                </>
            }
        >
            {valores && (
                <div className="space-y-4">
                    <AvisoDeErro erro={erro} />

                    {campos.map((c) => (
                        <Campo key={c.chave} etiqueta={c.rotulo} obrigatorio={c.obrigatorio} ajuda={c.ajuda}>
                            <input
                                type={c.tipo ?? 'text'}
                                value={valores[c.chave] ?? ''}
                                onChange={(e) => aoMudar({ ...valores, [c.chave]: e.target.value })}
                                className={entrada}
                            />
                        </Campo>
                    ))}
                </div>
            )}
        </Modal>
    );
}
