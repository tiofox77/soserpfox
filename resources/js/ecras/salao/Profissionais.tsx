import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { salao, type Profissional } from '@/api/salao';
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
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * OS PROFISSIONAIS — quem atende, quando, e em quê.
 *
 * O HORÁRIO NÃO É DECORAÇÃO: é ele que decide o que a página pública de
 * marcação oferece. Um profissional sem dias de trabalho não aparece lá, e um
 * com almoço marcado não recebe marcações a essa hora — por isso os dias estão
 * à vista na lista, e não escondidos na ficha.
 *
 * IMPORTAR DO RH existe porque o cabeleireiro já está em `hr_employees`.
 * Escrevê-lo outra vez à mão era o caminho mais curto para duas fichas da mesma
 * pessoa com dados diferentes.
 */

const vazio = () => ({
    name: '', nickname: '', email: '', phone: '', document: '', address: '',
    specialization: '', level: 'pleno', bio: '', birth_date: '', hire_date: '',
    working_days: [1, 2, 3, 4, 5, 6] as number[],
    work_start: '09:00', work_end: '18:00', lunch_start: '', lunch_end: '',
    commission_percent: '0', hourly_rate: '0', daily_rate: '0',
    accepts_online_booking: true, is_active: true, is_available: true,
    service_ids: [] as number[],
});

export default function Profissionais() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState({ procura: '', por_pagina: 15, page: 1 });
    const [procura, porProcura] = useState('');

    const [formulario, porFormulario] = useState<ReturnType<typeof vazio> | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<Profissional | null>(null);
    const [aApagar, porAApagar] = useState<Profissional | null>(null);
    const [aImportar, porAImportar] = useState(false);
    const [escolhidos, porEscolhidos] = useState<number[]>([]);

    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    useEffect(() => {
        const id = setTimeout(() => porFiltros((f) => ({ ...f, procura, page: 1 })), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const opcoes = useQuery({
        queryKey: ['salao', 'profissionais', 'opcoes'],
        queryFn: salao.profissionais.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['salao', 'profissionais', 'lista', filtros],
        queryFn: () => salao.profissionais.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const doRh = useQuery({
        queryKey: ['salao', 'profissionais', 'do-rh'],
        queryFn: salao.profissionais.doRh,
        enabled: aImportar,
    });

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['salao', 'profissionais'] });
    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };
    const feito = (r: { message: string }) => { porErros({}); porErro(null); porRecado(r.message); refrescar(); };

    const guardar = useMutation({
        mutationFn: () => salao.profissionais.guardar(aEditar, {
            ...formulario,
            commission_percent: Number(formulario!.commission_percent),
            hourly_rate: Number(formulario!.hourly_rate),
            daily_rate: Number(formulario!.daily_rate),
            birth_date: formulario!.birth_date || null,
            hire_date: formulario!.hire_date || null,
            lunch_start: formulario!.lunch_start || null,
            lunch_end: formulario!.lunch_end || null,
        }),
        onSuccess: (r) => { porFormulario(null); porAEditar(null); feito(r); },
        onError: falhou,
    });

    const alternar = useMutation({
        mutationFn: (id: number) => salao.profissionais.alternar(id),
        onSuccess: feito, onError: falhou,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => salao.profissionais.apagar(id),
        onSuccess: (r) => { porAApagar(null); feito(r); },
        onError: (e) => { porAApagar(null); falhou(e); },
    });

    const importar = useMutation({
        mutationFn: () => salao.profissionais.importarDoRh(escolhidos),
        onSuccess: (r) => { porAImportar(false); porEscolhidos([]); feito(r); },
        onError: falhou,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;

    const abrirNovo = () => { porAEditar(null); porErros({}); porFormulario(vazio()); };

    const abrirEdicao = (p: Profissional) => {
        porAEditar(p.id);
        porErros({});
        porFormulario({
            name: p.nome, nickname: p.alcunha ?? '', email: p.email ?? '', phone: p.telefone ?? '',
            document: p.documento ?? '', address: p.morada ?? '',
            specialization: p.especialidade ?? '', level: p.nivel ?? 'pleno', bio: p.biografia ?? '',
            birth_date: p.nascimento ?? '', hire_date: p.admissao ?? '',
            working_days: p.dias.length > 0 ? p.dias : [1, 2, 3, 4, 5, 6],
            work_start: p.entrada ?? '09:00', work_end: p.saida ?? '18:00',
            lunch_start: p.almoco_de ?? '', lunch_end: p.almoco_ate ?? '',
            commission_percent: String(p.comissao), hourly_rate: String(p.preco_hora), daily_rate: String(p.preco_dia),
            accepts_online_booking: p.marcacao_online, is_active: p.activo, is_available: p.disponivel,
            service_ids: p.service_ids,
        });
    };

    const nomeDoDia = (d: number) => o.dias.find((x) => x.valor === d)?.rotulo ?? String(d);

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Profissionais')}
                subtitulo={t('Quem atende, quando, e em quê')}
                icone="fa-user-group"
                cor="roxo"
                accoes={
                    <>
                        {o.permissoes.pode_criar && (
                            <>
                                <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNovo}>
                                    <i className="fas fa-plus" aria-hidden="true" />
                                    {t('Novo profissional')}
                                </button>
                                <button
                                    type="button"
                                    className={ACCAO_DA_FAIXA}
                                    onClick={() => { porEscolhidos([]); porAImportar(true); }}
                                >
                                    <i className="fas fa-file-import" aria-hidden="true" />
                                    {t('Importar do RH')}
                                </button>
                            </>
                        )}
                        <a href="/salon/services" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-scissors" aria-hidden="true" />
                            {t('Serviços')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <EstadoNaFaixa icone="fa-globe">
                        {t(':n na página de marcação', { n: String(resumo.na_marcacao_online) })}
                    </EstadoNaFaixa>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <CartaoNumero aspecto="claro" rotulo={t('Profissionais')} valor={resumo.total} icone="fa-user-group" tom="roxo" />
                    <CartaoNumero aspecto="claro" rotulo={t('Activos')} valor={resumo.activos} icone="fa-circle-check" tom="verde" />
                    <CartaoNumero
                        aspecto="claro" rotulo={t('Na página de marcação')} valor={resumo.na_marcacao_online}
                        icone="fa-globe" tom="indigo" nota={t('Quem a cliente pode escolher sozinha')}
                    />
                </div>
            )}

            <Campo etiqueta={t('Procurar')}>
                <div className="relative max-w-md">
                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        type="search"
                        value={procura}
                        onChange={(e) => porProcura(e.target.value)}
                        placeholder={t('Nome ou especialidade…')}
                        className={cls(entrada, 'pl-9')}
                    />
                </div>
            </Campo>

            <div className={cls(CARTAO, 'overflow-hidden')}>
                {lista.isPending ? (
                    <div className="p-5"><Carregando linhas={5} /></div>
                ) : (lista.data?.data.length ?? 0) === 0 ? (
                    <SemNada
                        icone="fa-user-group"
                        titulo={t('Ainda não há profissionais')}
                        frase={t('Sem profissionais não há agenda — e a página de marcação não oferece horas nenhumas.')}
                        accao={o.permissoes.pode_criar && (
                            <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                                {t('Novo profissional')}
                            </Botao>
                        )}
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {lista.data?.data.map((p, i) => (
                            <li key={p.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-4 py-3">
                                <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-purple-50 text-purple-600">
                                    <i className="fas fa-scissors" aria-hidden="true" />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">
                                        {p.nome}
                                        {p.alcunha && <span className="ml-2 font-normal text-slate-500">«{p.alcunha}»</span>}
                                    </p>
                                    <p className="truncate text-xs text-slate-500">
                                        {p.especialidade ?? t('Sem especialidade')}
                                        {p.nivel_rotulo && ` · ${p.nivel_rotulo}`}
                                        {p.telefone && ` · ${p.telefone}`}
                                    </p>
                                    {/* O HORÁRIO À VISTA: é o que decide a agenda. */}
                                    <p className="mt-0.5 flex flex-wrap items-center gap-1 text-xs">
                                        <span className="tabular-nums text-slate-600">
                                            {p.entrada}–{p.saida}
                                        </span>
                                        {p.dias.map((d) => (
                                            <span key={d} className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">
                                                {nomeDoDia(d).slice(0, 3)}
                                            </span>
                                        ))}
                                    </p>
                                </div>

                                <div className="flex flex-none flex-col items-end gap-1">
                                    <Etiqueta cor={p.activo ? 'bom' : 'neutra'} ponto>
                                        {p.activo ? t('Activo') : t('Desligado')}
                                    </Etiqueta>
                                    {p.marcacao_online && (
                                        <Etiqueta cor="primaria" icone="fa-globe">{t('na página')}</Etiqueta>
                                    )}
                                </div>

                                <span className="w-20 flex-none text-right text-xs text-slate-500">
                                    {t(':n serviços', { n: String(p.servicos.length) })}
                                </span>

                                <div className="flex flex-none gap-1">
                                    <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(p)} aria-label={t('Ver')} />
                                    {o.permissoes.pode_editar && (
                                        <>
                                            <Botao altura="pequeno" icone="fa-pen" onClick={() => abrirEdicao(p)} aria-label={t('Editar')} />
                                            <Botao
                                                altura="pequeno"
                                                cor={p.activo ? 'aviso' : 'bom'}
                                                icone={p.activo ? 'fa-eye-slash' : 'fa-eye'}
                                                onClick={() => alternar.mutate(p.id)}
                                                aria-label={p.activo ? t('Desligar') : t('Ligar')}
                                            />
                                        </>
                                    )}
                                    {o.permissoes.pode_apagar && (
                                        <Botao altura="pequeno" cor="perigo" icone="fa-trash" onClick={() => porAApagar(p)} aria-label={t('Eliminar')} />
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
                        <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />
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

            {/* ─── A ficha ─── */}
            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={aEditar ? t('Editar profissional') : t('Novo profissional')}
                subtitulo={t('O horário decide o que a página de marcação oferece')}
                icone={aEditar ? 'fa-pen' : 'fa-user-plus'}
                cor="roxo"
                largura="lg"
                rodape={
                    <>
                        <Botao onClick={() => porFormulario(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                                <input value={formulario.name} onChange={(e) => porFormulario({ ...formulario, name: e.target.value })} className={entrada} autoFocus />
                            </Campo>
                            <Campo etiqueta={t('Alcunha')} erro={erros.nickname} ajuda={t('O nome por que as clientes o tratam.')}>
                                <input value={formulario.nickname} onChange={(e) => porFormulario({ ...formulario, nickname: e.target.value })} className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('E-mail')} erro={erros.email}>
                                <input type="email" value={formulario.email} onChange={(e) => porFormulario({ ...formulario, email: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                                <input value={formulario.phone} onChange={(e) => porFormulario({ ...formulario, phone: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Documento')} erro={erros.document}>
                                <input value={formulario.document} onChange={(e) => porFormulario({ ...formulario, document: e.target.value })} className={entrada} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Morada')} erro={erros.address}>
                            <input value={formulario.address} onChange={(e) => porFormulario({ ...formulario, address: e.target.value })} className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-4">
                            <Campo etiqueta={t('Especialidade')} erro={erros.specialization} className="sm:col-span-2">
                                <input value={formulario.specialization} onChange={(e) => porFormulario({ ...formulario, specialization: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Nível')} erro={erros.level}>
                                <select value={formulario.level} onChange={(e) => porFormulario({ ...formulario, level: e.target.value })} className={entrada}>
                                    {o.niveis.map((n) => <option key={n.valor} value={n.valor}>{n.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('Admissão')} erro={erros.hire_date}>
                                <input type="date" value={formulario.hire_date} onChange={(e) => porFormulario({ ...formulario, hire_date: e.target.value })} className={entrada} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Biografia')} erro={erros.bio} ajuda={t('Aparece na página de marcação, por baixo do nome.')}>
                            <textarea value={formulario.bio} onChange={(e) => porFormulario({ ...formulario, bio: e.target.value })} rows={2} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>

                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('Dias de trabalho')}
                                <span className="ml-0.5 text-red-500" aria-hidden="true">*</span>
                            </legend>

                            <div className="flex flex-wrap gap-1.5">
                                {o.dias.map((d) => {
                                    const dentro = formulario.working_days.includes(d.valor);

                                    return (
                                        <button
                                            key={d.valor}
                                            type="button"
                                            onClick={() => porFormulario({
                                                ...formulario,
                                                working_days: dentro
                                                    ? formulario.working_days.filter((x) => x !== d.valor)
                                                    : [...formulario.working_days, d.valor],
                                            })}
                                            aria-pressed={dentro}
                                            className={cls(
                                                'px-3 py-1.5 text-xs font-semibold transition-all duration-200', RAIO, FOCO,
                                                dentro ? 'bg-purple-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                                            )}
                                        >
                                            {d.rotulo}
                                        </button>
                                    );
                                })}
                            </div>

                            {erros.working_days?.[0] && (
                                <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.working_days[0]}</p>
                            )}
                        </fieldset>

                        <div className="grid gap-4 sm:grid-cols-4">
                            <Campo etiqueta={t('Entrada')} obrigatorio erro={erros.work_start}>
                                <input type="time" value={formulario.work_start} onChange={(e) => porFormulario({ ...formulario, work_start: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Saída')} obrigatorio erro={erros.work_end}>
                                <input type="time" value={formulario.work_end} onChange={(e) => porFormulario({ ...formulario, work_end: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Almoço de')} erro={erros.lunch_start}>
                                <input type="time" value={formulario.lunch_start} onChange={(e) => porFormulario({ ...formulario, lunch_start: e.target.value })} className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Almoço até')} erro={erros.lunch_end}>
                                <input type="time" value={formulario.lunch_end} onChange={(e) => porFormulario({ ...formulario, lunch_end: e.target.value })} className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Comissão %')} erro={erros.commission_percent}>
                                <input type="number" step="0.01" min="0" max="100" value={formulario.commission_percent} onChange={(e) => porFormulario({ ...formulario, commission_percent: e.target.value })} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta={t('Preço/hora')} erro={erros.hourly_rate}>
                                <input type="number" step="0.01" min="0" value={formulario.hourly_rate} onChange={(e) => porFormulario({ ...formulario, hourly_rate: e.target.value })} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                            <Campo etiqueta={t('Preço/dia')} erro={erros.daily_rate}>
                                <input type="number" step="0.01" min="0" value={formulario.daily_rate} onChange={(e) => porFormulario({ ...formulario, daily_rate: e.target.value })} className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>

                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('Serviços que faz')}
                            </legend>

                            <div className={cls('max-h-40 overflow-y-auto border border-slate-200 bg-white p-2', RAIO)}>
                                <div className="flex flex-wrap gap-1.5">
                                    {o.servicos.map((s) => {
                                        const dentro = formulario.service_ids.includes(Number(s.valor));

                                        return (
                                            <button
                                                key={s.valor}
                                                type="button"
                                                onClick={() => porFormulario({
                                                    ...formulario,
                                                    service_ids: dentro
                                                        ? formulario.service_ids.filter((x) => x !== Number(s.valor))
                                                        : [...formulario.service_ids, Number(s.valor)],
                                                })}
                                                aria-pressed={dentro}
                                                className={cls(
                                                    'px-2.5 py-1 text-xs font-medium transition', RAIO, FOCO,
                                                    dentro ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                                                )}
                                            >
                                                {s.rotulo}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </fieldset>

                        <div className="flex flex-wrap gap-6">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" checked={formulario.is_active} onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500" />
                                {t('Activo')}
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" checked={formulario.is_available} onChange={(e) => porFormulario({ ...formulario, is_available: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500" />
                                {t('Disponível')}
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" checked={formulario.accepts_online_booking} onChange={(e) => porFormulario({ ...formulario, accepts_online_booking: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500" />
                                {t('Aparece na página de marcação')}
                            </label>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── Ver ─── */}
            <Modal
                aberto={aVer !== null}
                aoFechar={() => porAVer(null)}
                titulo={aVer?.nome ?? ''}
                subtitulo={aVer?.especialidade ?? undefined}
                icone="fa-user"
                cor="roxo"
                rodape={<Botao onClick={() => porAVer(null)}>{t('Fechar')}</Botao>}
            >
                {aVer && (
                    <div className="space-y-4">
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <Linha rotulo={t('E-mail')} valor={aVer.email ?? '—'} />
                            <Linha rotulo={t('Telefone')} valor={aVer.telefone ?? '—'} />
                            <Linha rotulo={t('Documento')} valor={aVer.documento ?? '—'} />
                            <Linha rotulo={t('Admissão')} valor={aVer.admissao ? data(aVer.admissao) : '—'} />
                            <Linha rotulo={t('Horário')} valor={`${aVer.entrada ?? '—'}–${aVer.saida ?? '—'}`} />
                            <Linha
                                rotulo={t('Almoço')}
                                valor={aVer.almoco_de ? `${aVer.almoco_de}–${aVer.almoco_ate ?? '—'}` : '—'}
                            />
                            <Linha rotulo={t('Comissão')} valor={`${aVer.comissao}%`} />
                            <Linha rotulo={t('Preço/hora')} valor={`${kz(aVer.preco_hora)} Kz`} />
                        </dl>

                        <div>
                            <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Dias')}</p>
                            <div className="flex flex-wrap gap-1.5">
                                {aVer.dias.map((d) => (
                                    <span key={d} className="rounded-lg bg-purple-50 px-2 py-1 text-xs font-semibold text-purple-700">
                                        {nomeDoDia(d)}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div>
                            <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Serviços')}</p>
                            {aVer.servicos.length === 0 ? (
                                <p className="text-sm text-slate-500">{t('Sem serviços atribuídos.')}</p>
                            ) : (
                                <div className="flex flex-wrap gap-1.5">
                                    {aVer.servicos.map((s) => (
                                        <span key={s.id} className="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            {s.nome}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>

                        {aVer.biografia && <p className="text-sm text-slate-600">{aVer.biografia}</p>}
                    </div>
                )}
            </Modal>

            {/* ─── Importar do RH ─── */}
            <Modal
                aberto={aImportar}
                aoFechar={() => porAImportar(false)}
                titulo={t('Importar do RH')}
                subtitulo={t('Quem já está no pessoal e ainda não atende no salão')}
                icone="fa-file-import"
                cor="primaria"
                rodape={
                    <>
                        <Botao onClick={() => porAImportar(false)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check"
                            disabled={escolhidos.length === 0}
                            aTrabalhar={importar.isPending}
                            onClick={() => importar.mutate()}
                        >
                            {t('Importar :n', { n: String(escolhidos.length) })}
                        </Botao>
                    </>
                }
            >
                {doRh.isPending ? (
                    <Carregando linhas={4} />
                ) : (doRh.data?.data.length ?? 0) === 0 ? (
                    <SemNada
                        icone="fa-user-check"
                        frase={t('Não há ninguém no RH que ainda não esteja aqui — a importação recusa quem já cá está, por e-mail ou por telefone.')}
                    />
                ) : (
                    <div className="space-y-2">
                        <label className="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                            <input
                                type="checkbox"
                                checked={escolhidos.length === (doRh.data?.data.length ?? 0)}
                                onChange={(e) => porEscolhidos(e.target.checked ? (doRh.data?.data ?? []).map((f) => f.id) : [])}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            {t('Escolher todos')}
                        </label>

                        <ul className={cls('divide-y divide-slate-100 border border-slate-200 bg-white', RAIO)}>
                            {doRh.data?.data.map((f) => (
                                <li key={f.id}>
                                    <label className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-indigo-50">
                                        <input
                                            type="checkbox"
                                            checked={escolhidos.includes(f.id)}
                                            onChange={() => porEscolhidos(escolhidos.includes(f.id)
                                                ? escolhidos.filter((x) => x !== f.id)
                                                : [...escolhidos, f.id])}
                                            className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-slate-800">{f.nome}</span>
                                            <span className="block truncate text-xs text-slate-500">
                                                {f.cargo ?? t('Sem cargo')}
                                                {f.telefone && ` · ${f.telefone}`}
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar profissional')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Uma agenda por cumprir não desaparece com a ficha de quem a ia cumprir: com marcações por atender, desligue em vez de apagar.')}
                </p>
            </Modal>
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
