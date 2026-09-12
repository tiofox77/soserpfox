import { useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    salao,
    type FiltrosDasMarcacoes,
    type Marcacao,
    type MarcacaoParaGravar,
} from '@/api/salao';
import { ErroDaApi } from '@/api/cliente';
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
import { etiquetaIntl, t } from '@/i18n';
import { COR_DO_ESTADO, ICONE_DO_ESTADO } from './Painel';

/**
 * AS MARCAÇÕES — a lista e o calendário.
 *
 * DUAS VISTAS DA MESMA COISA, e de propósito: a LISTA serve para encontrar uma
 * marcação, o CALENDÁRIO serve para ver onde há espaço. Quem atende ao telefone
 * usa a segunda; quem procura a marcação de ontem usa a primeira.
 *
 * O QUE SE PODE FAZER A SEGUIR VEM DO SERVIDOR. O ecrã em Livewire mostrava os
 * botões todos a toda a hora — e dava para concluir uma marcação cancelada, que
 * passava a contar na receita.
 *
 * A DURAÇÃO E O PREÇO SAEM DOS SERVIÇOS: escolhem-se serviços, e o catálogo diz
 * quanto tempo levam e quanto custam.
 */

const formularioVazio = (): MarcacaoParaGravar => ({
    client_id: '', professional_id: '', date: new Date().toISOString().slice(0, 10),
    start_time: '09:00', service_ids: [], notes: '', source: 'system',
});

const clienteVazio = () => ({ name: '', phone: '', email: '', nif: '', city: '' });

export default function Marcacoes() {
    const cache = useQueryClient();

    const [vista, porVista] = useState<'lista' | 'calendario'>('lista');
    const [filtros, porFiltros] = useState<FiltrosDasMarcacoes>({ procura: '', page: 1, por_pagina: 15 });
    const [procura, porProcura] = useState('');

    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [vistaDoCalendario, porVistaDoCalendario] = useState<'dia' | 'semana' | 'mes'>('mes');

    const [formulario, porFormulario] = useState<MarcacaoParaGravar | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aCancelar, porACancelar] = useState<Marcacao | null>(null);
    const [motivo, porMotivo] = useState('');
    const [novaCliente, porNovaCliente] = useState<ReturnType<typeof clienteVazio> | null>(null);
    const [procuraDeCliente, porProcuraDeCliente] = useState('');

    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useState('');

    useEffect(() => {
        const id = setTimeout(() => porFiltros((f) => ({ ...f, procura, page: 1 })), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const opcoes = useQuery({
        queryKey: ['salao', 'marcacoes', 'opcoes'],
        queryFn: salao.marcacoes.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['salao', 'marcacoes', 'lista', filtros],
        queryFn: () => salao.marcacoes.lista(filtros),
        placeholderData: keepPreviousData,
        enabled: vista === 'lista',
    });

    const calendario = useQuery({
        queryKey: ['salao', 'marcacoes', 'calendario', dia, vistaDoCalendario, filtros.profissional],
        queryFn: () => salao.marcacoes.calendario({
            dia, vista: vistaDoCalendario, profissional: filtros.profissional,
        }),
        placeholderData: keepPreviousData,
        enabled: vista === 'calendario',
    });

    const ficha = useQuery({
        queryKey: ['salao', 'marcacoes', 'ficha', aVer],
        queryFn: () => salao.marcacoes.ficha(aVer!),
        enabled: aVer !== null,
    });

    const clientes = useQuery({
        queryKey: ['salao', 'marcacoes', 'clientes', procuraDeCliente],
        queryFn: () => salao.marcacoes.clientes(procuraDeCliente),
        enabled: formulario !== null,
    });

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['salao'] });
    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };

    const guardar = useMutation({
        mutationFn: () => salao.marcacoes.guardar(aEditar, {
            ...formulario,
            client_id: Number(formulario!.client_id),
            professional_id: Number(formulario!.professional_id),
        }),
        onSuccess: (r) => { porFormulario(null); porAEditar(null); porErros({}); porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const estado = useMutation({
        mutationFn: ({ id, estado, motivo }: { id: number; estado: string; motivo?: string }) =>
            salao.marcacoes.estado(id, estado, motivo),
        onSuccess: (r) => { porACancelar(null); porMotivo(''); porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const criarCliente = useMutation({
        mutationFn: () => salao.marcacoes.clienteRapido(novaCliente!),
        onSuccess: (r) => {
            porFormulario((f) => (f ? { ...f, client_id: r.data.valor } : f));
            porNovaCliente(null); porErros({}); porErro(null); porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['salao', 'marcacoes', 'clientes'] });
        },
        onError: falhou,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;

    /** A duração e o preço do que está escolhido — a conta que o servidor vai repetir. */
    const escolhidos = formulario
        ? o.servicos.filter((s) => formulario.service_ids.includes(Number(s.valor)))
        : [];

    const duracao = escolhidos.reduce((soma, s) => soma + s.duracao, 0);
    const preco = escolhidos.reduce((soma, s) => soma + s.preco, 0);

    const abrirNova = () => { porAEditar(null); porErros({}); porProcuraDeCliente(''); porFormulario(formularioVazio()); };

    const abrirEdicao = (m: Marcacao) => {
        porAEditar(m.id);
        porErros({});
        porProcuraDeCliente('');
        porFormulario({
            client_id: String(m.client_id ?? ''),
            professional_id: String(m.professional_id ?? ''),
            date: m.dia ?? new Date().toISOString().slice(0, 10),
            start_time: m.inicio ?? '09:00',
            service_ids: m.servicos.map((s) => s.id),
            notes: m.observacoes ?? '',
            source: m.origem,
        });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Marcações')}
                subtitulo={t('A agenda do salão — em lista ou em calendário')}
                icone="fa-calendar-check"
                cor="rosa"
                accoes={
                    <>
                        {o.permissoes.pode_criar && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNova}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova marcação')}
                            </button>
                        )}
                        <a href="/salon/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-spa" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-calendar-day">
                            {t(':n hoje', { n: String(resumo.hoje) })}
                        </EstadoNaFaixa>
                        <EstadoNaFaixa icone="fa-globe">
                            {t(':n pela página pública', { n: String(resumo.online) })}
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

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero rotulo={t('Hoje')} valor={resumo.hoje} icone="fa-calendar-day" tom="roxo" />
                    <CartaoNumero rotulo={t('Por atender')} valor={resumo.por_atender} icone="fa-hourglass-half" tom="ambar" />
                    <CartaoNumero rotulo={t('Concluídas no mês')} valor={resumo.concluidas_no_mes} icone="fa-flag-checkered" tom="verde" />
                    <CartaoNumero
                        rotulo={t('Receita do mês')} valor={kz(resumo.receita_do_mes)} sufixo="Kz"
                        icone="fa-sack-dollar" tom="indigo"
                    />
                </div>
            )}

            <div className="flex flex-wrap items-center gap-2">
                <div className={cls('inline-flex overflow-hidden border border-slate-200 bg-white', RAIO)}>
                    {(['lista', 'calendario'] as const).map((v) => (
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
            </div>

            {vista === 'lista' ? (
                <>
                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    type="search"
                                    value={procura}
                                    onChange={(e) => porProcura(e.target.value)}
                                    placeholder={t('Número, cliente ou telefone…')}
                                    className={cls(entrada, 'pl-9')}
                                />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Estado')} className="w-44">
                            <select
                                value={filtros.estado ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, estado: e.target.value, page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Profissional')} className="w-52">
                            <select
                                value={filtros.profissional ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, profissional: e.target.value ? Number(e.target.value) : '', page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {o.profissionais.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Origem')} className="w-44">
                            <select
                                value={filtros.origem ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, origem: e.target.value, page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Todas')}</option>
                                {o.origens.map((s) => <option key={s.valor} value={s.valor}>{s.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Quando')} className="w-40">
                            <select
                                value={filtros.quando ?? ''}
                                onChange={(e) => porFiltros({ ...filtros, quando: e.target.value, page: 1 })}
                                className={entrada}
                            >
                                <option value="">{t('Sempre')}</option>
                                <option value="hoje">{t('Hoje')}</option>
                                <option value="amanha">{t('Amanhã')}</option>
                                <option value="semana">{t('Esta semana')}</option>
                            </select>
                        </Campo>
                    </div>

                    <div className={cls(CARTAO, 'overflow-hidden')}>
                        {lista.isPending ? (
                            <div className="p-5"><Carregando linhas={6} /></div>
                        ) : (lista.data?.data.length ?? 0) === 0 ? (
                            <SemNada
                                icone="fa-calendar-check"
                                titulo={t('Nenhuma marcação')}
                                frase={t('Marque a primeira, ou limpe os filtros.')}
                                accao={o.permissoes.pode_criar && (
                                    <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>
                                        {t('Nova marcação')}
                                    </Botao>
                                )}
                            />
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {lista.data?.data.map((m, i) => (
                                    <li key={m.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-4 py-3">
                                        <span className="grid h-12 w-16 flex-none place-items-center rounded-xl bg-pink-50 text-pink-700">
                                            <span className="text-sm font-bold tabular-nums">{m.inicio ?? '—'}</span>
                                            <span className="text-[10px]">
                                                {m.dia ? new Date(m.dia).toLocaleDateString(etiquetaIntl(), { day: '2-digit', month: '2-digit' }) : ''}
                                            </span>
                                        </span>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-slate-800">
                                                {m.cliente}
                                                {m.profissional && <span className="ml-2 font-normal text-slate-500">· {m.profissional}</span>}
                                            </p>
                                            <p className="truncate text-xs text-slate-500">
                                                {m.numero}
                                                {' · '}
                                                {m.servicos.map((s) => s.nome).join(', ') || t('Sem serviços')}
                                            </p>
                                        </div>

                                        <Etiqueta cor={COR_DO_ESTADO[m.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[m.estado]}>
                                            {m.estado_rotulo}
                                        </Etiqueta>

                                        <span className="flex-none text-sm font-bold tabular-nums text-slate-900">{kz(m.total)}</span>

                                        <div className="flex flex-none flex-wrap gap-1">
                                            {m.pode.map((p) => (
                                                <Botao
                                                    key={p.valor}
                                                    altura="pequeno"
                                                    cor={['cancelled', 'no_show'].includes(p.valor) ? 'perigo' : p.valor === 'completed' ? 'bom' : 'primaria'}
                                                    icone={ICONE_DO_ESTADO[p.valor]}
                                                    onClick={() => {
                                                        if (p.valor === 'cancelled') { porMotivo(''); porACancelar(m); return; }

                                                        estado.mutate({ id: m.id, estado: p.valor });
                                                    }}
                                                >
                                                    {p.rotulo}
                                                </Botao>
                                            ))}

                                            <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(m.id)} aria-label={t('Ver')} />

                                            {o.permissoes.pode_editar && m.pode.length > 0 && (
                                                <Botao altura="pequeno" icone="fa-pen" onClick={() => abrirEdicao(m)} aria-label={t('Editar')} />
                                            )}
                                        </div>
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
                                <PorPagina valor={filtros.por_pagina ?? 15} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
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
                </>
            ) : (
                <Calendario
                    dia={dia}
                    vista={vistaDoCalendario}
                    dados={calendario.data}
                    aCarregar={calendario.isPending}
                    profissionais={o.profissionais}
                    profissional={filtros.profissional ?? ''}
                    aoProfissional={(p) => porFiltros({ ...filtros, profissional: p })}
                    aoDia={porDia}
                    aoVista={porVistaDoCalendario}
                    aoEscolher={porAVer}
                />
            )}

            {/* ─── O formulário ─── */}
            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={aEditar ? t('Editar marcação') : t('Nova marcação')}
                subtitulo={t('A duração e o preço saem dos serviços escolhidos')}
                icone={aEditar ? 'fa-pen' : 'fa-calendar-plus'}
                cor="rosa"
                largura="lg"
                rodape={
                    <>
                        <Botao onClick={() => porFormulario(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check"
                            aTrabalhar={guardar.isPending}
                            disabled={(formulario?.service_ids.length ?? 0) === 0 || !formulario?.client_id}
                            onClick={() => guardar.mutate()}
                        >
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <Campo
                            etiqueta={t('Cliente')}
                            obrigatorio
                            erro={erros.client_id}
                            ajuda={o.permissoes.pode_criar_cliente
                                ? t('Não está na lista? Crie-a aqui ao lado.')
                                : undefined}
                        >
                            <div className="flex gap-2">
                                <select
                                    value={formulario.client_id}
                                    onChange={(e) => porFormulario({ ...formulario, client_id: e.target.value })}
                                    className={entrada}
                                >
                                    <option value="">{t('Escolher…')}</option>
                                    {clientes.data?.data.map((c) => (
                                        <option key={c.valor} value={c.valor}>{c.rotulo}</option>
                                    ))}
                                </select>

                                {o.permissoes.pode_criar_cliente && (
                                    <Botao
                                        icone="fa-user-plus"
                                        onClick={() => { porErros({}); porNovaCliente(clienteVazio()); }}
                                        aria-label={t('Cliente nova')}
                                    />
                                )}
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Procurar cliente')} ajuda={t('Nome, telefone ou e-mail.')}>
                            <input
                                type="search"
                                value={procuraDeCliente}
                                onChange={(e) => porProcuraDeCliente(e.target.value)}
                                className={entrada}
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Profissional')} obrigatorio erro={erros.professional_id}>
                                <select
                                    value={formulario.professional_id}
                                    onChange={(e) => porFormulario({ ...formulario, professional_id: e.target.value })}
                                    className={entrada}
                                >
                                    <option value="">{t('Escolher…')}</option>
                                    {o.profissionais.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Dia')} obrigatorio erro={erros.date}>
                                <input
                                    type="date"
                                    value={formulario.date}
                                    onChange={(e) => porFormulario({ ...formulario, date: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Hora')} obrigatorio erro={erros.start_time}>
                                <input
                                    type="time"
                                    value={formulario.start_time}
                                    onChange={(e) => porFormulario({ ...formulario, start_time: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>

                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('Serviços')}
                                <span className="ml-0.5 text-red-500" aria-hidden="true">*</span>
                            </legend>

                            <div className={cls('max-h-56 overflow-y-auto border border-slate-200 bg-white', RAIO)}>
                                <ul className="divide-y divide-slate-100">
                                    {o.servicos.map((s) => {
                                        const dentro = formulario.service_ids.includes(Number(s.valor));

                                        return (
                                            <li key={s.valor}>
                                                <label className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-pink-50">
                                                    <input
                                                        type="checkbox"
                                                        checked={dentro}
                                                        onChange={() => porFormulario({
                                                            ...formulario,
                                                            service_ids: dentro
                                                                ? formulario.service_ids.filter((x) => x !== Number(s.valor))
                                                                : [...formulario.service_ids, Number(s.valor)],
                                                        })}
                                                        className="h-4 w-4 rounded border-slate-300 text-pink-600 focus:ring-pink-500"
                                                    />
                                                    <span className="min-w-0 flex-1 truncate text-sm text-slate-800">{s.rotulo}</span>
                                                    <span className="flex-none text-xs text-slate-500">
                                                        {t(':n min', { n: String(s.duracao) })}
                                                    </span>
                                                    <span className="w-24 flex-none text-right text-sm font-semibold tabular-nums text-slate-900">
                                                        {kz(s.preco)}
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>

                            {erros.service_ids?.[0] && (
                                <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.service_ids[0]}</p>
                            )}

                            <div className="mt-2 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                <span className="font-semibold text-slate-700">
                                    {t(':n min', { n: String(duracao) })}
                                    {formulario.start_time && duracao > 0 && (
                                        <span className="ml-2 font-normal text-slate-500">
                                            {t('acaba às :h', { h: somarMinutos(formulario.start_time, duracao) })}
                                        </span>
                                    )}
                                </span>
                                <span className="font-bold tabular-nums text-slate-900">
                                    {kz(preco)} <span className="text-xs font-normal text-slate-400">Kz</span>
                                </span>
                            </div>
                        </fieldset>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Origem')} erro={erros.source}>
                                <select
                                    value={formulario.source}
                                    onChange={(e) => porFormulario({ ...formulario, source: e.target.value })}
                                    className={entrada}
                                >
                                    {o.origens.map((s) => <option key={s.valor} value={s.valor}>{s.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Observações')} erro={erros.notes}>
                                <input
                                    value={formulario.notes}
                                    onChange={(e) => porFormulario({ ...formulario, notes: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── Cliente nova, sem sair da marcação ─── */}
            <Modal
                aberto={novaCliente !== null}
                aoFechar={() => porNovaCliente(null)}
                titulo={t('Cliente nova')}
                subtitulo={t('Quem liga a marcar raramente já está no sistema')}
                icone="fa-user-plus"
                cor="rosa"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porNovaCliente(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={criarCliente.isPending} onClick={() => criarCliente.mutate()}>
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {novaCliente && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input
                                value={novaCliente.name}
                                onChange={(e) => porNovaCliente({ ...novaCliente, name: e.target.value })}
                                className={entrada}
                                autoFocus
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                                <input
                                    value={novaCliente.phone}
                                    onChange={(e) => porNovaCliente({ ...novaCliente, phone: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('E-mail')} erro={erros.email}>
                                <input
                                    type="email"
                                    value={novaCliente.email}
                                    onChange={(e) => porNovaCliente({ ...novaCliente, email: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('NIF')} erro={erros.nif} ajuda={t('Em branco fica «999999999».')}>
                                <input
                                    value={novaCliente.nif}
                                    onChange={(e) => porNovaCliente({ ...novaCliente, nif: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Cidade')} erro={erros.city}>
                                <input
                                    value={novaCliente.city}
                                    onChange={(e) => porNovaCliente({ ...novaCliente, city: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── Cancelar ─── */}
            <Modal
                aberto={aCancelar !== null}
                aoFechar={() => porACancelar(null)}
                titulo={t('Cancelar marcação')}
                subtitulo={aCancelar?.cliente}
                icone="fa-xmark"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porACancelar(null)}>{t('Voltar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-xmark"
                            aTrabalhar={estado.isPending}
                            onClick={() => aCancelar && estado.mutate({ id: aCancelar.id, estado: 'cancelled', motivo })}
                        >
                            {t('Cancelar marcação')}
                        </Botao>
                    </>
                }
            >
                <Campo etiqueta={t('Motivo')} ajuda={t('Fica na ficha — é o que distingue um engano de uma desistência.')}>
                    <textarea
                        value={motivo}
                        onChange={(e) => porMotivo(e.target.value)}
                        rows={3}
                        className={cls(entrada, 'h-auto py-2')}
                    />
                </Campo>
            </Modal>

            {/* ─── A ficha ─── */}
            <Modal
                aberto={aVer !== null}
                aoFechar={() => porAVer(null)}
                titulo={t('Marcação')}
                subtitulo={ficha.data?.data.numero}
                icone="fa-calendar-check"
                cor="rosa"
                rodape={<Botao onClick={() => porAVer(null)}>{t('Fechar')}</Botao>}
            >
                {ficha.isPending ? (
                    <Carregando linhas={4} />
                ) : ficha.data ? (
                    <FichaDaMarcacao m={ficha.data.data} />
                ) : null}
            </Modal>
        </div>
    );
}

/** A hora de fim, para o formulário dizer onde a marcação acaba. */
function somarMinutos(hora: string, minutos: number): string {
    const [h, m] = hora.split(':').map(Number);
    const total = (h ?? 0) * 60 + (m ?? 0) + minutos;

    return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function FichaDaMarcacao({ m }: { m: Marcacao }) {
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <Etiqueta cor={COR_DO_ESTADO[m.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[m.estado]}>
                    {m.estado_rotulo}
                </Etiqueta>
                <Etiqueta icone="fa-signal">{m.origem_rotulo}</Etiqueta>
            </div>

            <dl className="grid gap-3 sm:grid-cols-2">
                <Linha rotulo={t('Cliente')} valor={m.cliente} />
                <Linha rotulo={t('Telefone')} valor={m.telefone ?? '—'} />
                <Linha rotulo={t('Profissional')} valor={m.profissional ?? '—'} />
                <Linha rotulo={t('Quando')} valor={`${m.dia ?? '—'} · ${m.inicio ?? '—'}–${m.fim ?? '—'}`} />
                <Linha rotulo={t('Previsto')} valor={t(':n min', { n: String(m.duracao) })} />
                <Linha
                    rotulo={t('Real')}
                    valor={m.duracao_real != null ? t(':n min', { n: String(m.duracao_real) }) : '—'}
                />
                <Linha
                    rotulo={t('Espera')}
                    valor={m.espera != null ? t(':n min', { n: String(m.espera) }) : '—'}
                />
                <Linha rotulo={t('Total')} valor={`${kz(m.total)} Kz`} />
            </dl>

            <div>
                <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Serviços')}</p>
                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    {m.servicos.map((s) => (
                        <li key={s.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <span className="truncate text-slate-800">{s.nome}</span>
                            <span className="flex-none text-xs text-slate-500">{t(':n min', { n: String(s.duracao) })}</span>
                            <span className="w-24 flex-none text-right font-semibold tabular-nums text-slate-900">{kz(s.preco)}</span>
                        </li>
                    ))}
                </ul>
            </div>

            {m.observacoes && (
                <p className={cls('border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900', RAIO)}>
                    « {m.observacoes} »
                </p>
            )}

            {m.motivo_do_cancelamento && (
                <p className={cls('border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900', RAIO)}>
                    <i className="fas fa-xmark mr-1.5" aria-hidden="true" />
                    {m.motivo_do_cancelamento}
                </p>
            )}
        </div>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className="text-sm text-slate-800">{valor}</dd>
        </div>
    );
}

/* ─── O calendário ────────────────────────────────────────────────────── */

function Calendario({
    dia, vista, dados, aCarregar, profissionais, profissional, aoProfissional, aoDia, aoVista, aoEscolher,
}: {
    dia: string;
    vista: 'dia' | 'semana' | 'mes';
    dados?: { de: string; ate: string; dias: Record<string, Marcacao[]> };
    aCarregar: boolean;
    profissionais: Array<{ valor: string; rotulo: string }>;
    profissional: number | '';
    aoProfissional: (p: number | '') => void;
    aoDia: (d: string) => void;
    aoVista: (v: 'dia' | 'semana' | 'mes') => void;
    aoEscolher: (id: number) => void;
}) {
    /** Os dias que o quadro desenha, do primeiro ao último — sem buracos. */
    const grelha = useMemo(() => {
        if (!dados) return [];

        const saida: string[] = [];
        const fim = new Date(dados.ate);

        for (let d = new Date(dados.de); d <= fim; d.setDate(d.getDate() + 1)) {
            saida.push(d.toISOString().slice(0, 10));
        }

        return saida;
    }, [dados]);

    const andar = (passo: number) => {
        const d = new Date(dia);

        if (vista === 'dia') d.setDate(d.getDate() + passo);
        if (vista === 'semana') d.setDate(d.getDate() + passo * 7);
        if (vista === 'mes') d.setMonth(d.getMonth() + passo);

        aoDia(d.toISOString().slice(0, 10));
    };

    const hoje = new Date().toISOString().slice(0, 10);

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex items-center gap-1.5">
                    <Botao icone="fa-chevron-left" onClick={() => andar(-1)} aria-label={t('Anterior')} />
                    <Botao onClick={() => aoDia(hoje)}>{t('Hoje')}</Botao>
                    <Botao icone="fa-chevron-right" onClick={() => andar(1)} aria-label={t('Seguinte')} />
                </div>

                <div className={cls('inline-flex overflow-hidden border border-slate-200 bg-white', RAIO)}>
                    {(['dia', 'semana', 'mes'] as const).map((v) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => aoVista(v)}
                            className={cls(
                                'px-3 py-2 text-xs font-semibold transition', FOCO,
                                vista === v ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-50',
                            )}
                        >
                            {v === 'dia' ? t('Dia') : v === 'semana' ? t('Semana') : t('Mês')}
                        </button>
                    ))}
                </div>

                <Campo etiqueta={t('Profissional')} className="w-52">
                    <select
                        value={profissional}
                        onChange={(e) => aoProfissional(e.target.value ? Number(e.target.value) : '')}
                        className={entrada}
                    >
                        <option value="">{t('Todos')}</option>
                        {profissionais.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {aCarregar ? (
                <Carregando linhas={6} />
            ) : (
                <div className={cls(CARTAO, 'overflow-hidden p-3')}>
                    <div className={cls('grid gap-1.5', vista === 'dia' ? 'grid-cols-1' : 'grid-cols-7')}>
                        {vista !== 'dia' && [t('Seg'), t('Ter'), t('Qua'), t('Qui'), t('Sex'), t('Sáb'), t('Dom')].map((d) => (
                            <p key={d} className="pb-1 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">
                                {d}
                            </p>
                        ))}

                        {grelha.map((d) => {
                            const doDia = dados?.dias[d] ?? [];

                            return (
                                <div
                                    key={d}
                                    className={cls(
                                        'min-h-[5.5rem] rounded-lg border p-1.5',
                                        d === hoje ? 'border-pink-300 bg-pink-50/50' : 'border-slate-100',
                                    )}
                                >
                                    <p className={cls(
                                        'mb-1 text-xs font-bold tabular-nums',
                                        d === hoje ? 'text-pink-700' : 'text-slate-400',
                                    )}>
                                        {new Date(d).getDate()}
                                    </p>

                                    <ul className="space-y-1">
                                        {doDia.slice(0, 4).map((m) => (
                                            <li key={m.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => aoEscolher(m.id)}
                                                    title={`${m.inicio} · ${m.cliente}`}
                                                    className={cls(
                                                        'w-full truncate rounded px-1.5 py-0.5 text-left text-[11px] font-medium',
                                                        FOCO,
                                                        {
                                                            scheduled: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
                                                            confirmed: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200',
                                                            arrived: 'bg-violet-100 text-violet-800 hover:bg-violet-200',
                                                            in_progress: 'bg-sky-100 text-sky-800 hover:bg-sky-200',
                                                            completed: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
                                                            cancelled: 'bg-red-100 text-red-800 line-through hover:bg-red-200',
                                                            no_show: 'bg-slate-200 text-slate-600 line-through hover:bg-slate-300',
                                                        }[m.estado] ?? 'bg-slate-100 text-slate-700',
                                                    )}
                                                >
                                                    {m.inicio} {m.cliente}
                                                </button>
                                            </li>
                                        ))}

                                        {doDia.length > 4 && (
                                            <li className="px-1.5 text-[11px] text-slate-400">
                                                {t('+:n', { n: String(doDia.length - 4) })}
                                            </li>
                                        )}
                                    </ul>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
