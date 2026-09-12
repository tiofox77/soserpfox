import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { projetos, type Tarefa } from '@/api/projetos';
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
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * AS TAREFAS DOS PROJETOS.
 *
 * NADA SE APAGA: cancelar é um estado. As horas já lançadas contra uma tarefa
 * continuam a valer, e uma tarefa que desaparecesse deixava-as sem explicação —
 * horas cobradas a um cliente por um trabalho que já não existe em lado nenhum.
 * Por isso não há caixote do lixo neste ecrã, e é de propósito.
 *
 * A ORDEM é urgente primeiro, depois o prazo mais próximo, e as sem prazo no
 * fim: quem não tem data não compete com quem tem.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    por_fazer: 'neutra',
    em_curso: 'primaria',
    bloqueada: 'aviso',
    concluida: 'bom',
    cancelada: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    por_fazer: 'fa-circle',
    em_curso: 'fa-spinner',
    bloqueada: 'fa-ban',
    concluida: 'fa-circle-check',
    cancelada: 'fa-xmark',
};

const COR_DA_PRIORIDADE: Record<string, 'neutra' | 'primaria' | 'aviso' | 'perigo'> = {
    baixa: 'neutra',
    normal: 'primaria',
    alta: 'aviso',
    urgente: 'perigo',
};

const VAZIO = {
    titulo: '', descricao: '', projeto_id: '', responsavel_id: '',
    prioridade: 'normal', prazo: '', horas_estimadas: '',
};

export default function TarefasDeProjeto() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{
        procura?: string; projeto?: number | ''; estado?: string;
        prioridade?: string; so_minhas?: boolean;
        por_pagina?: number; page?: number;
    }>({ estado: 'abertas', por_pagina: 20, page: 1 });

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);

    const opcoes = useQuery({ queryKey: ['projetos', 'tarefas', 'opcoes'], queryFn: () => projetos.tarefas.opcoes() });

    const lista = useQuery({
        queryKey: ['projetos', 'tarefas', filtros],
        queryFn: () => projetos.tarefas.listar(filtros),
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['projetos'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => projetos.tarefas.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const estado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => projetos.tarefas.estado(id, estado),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.pode_gerir;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const abrirNova = () => {
        porAEditar(null);
        porFormulario({ ...VAZIO, projeto_id: filtros.projeto ? String(filtros.projeto) : '' });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Tarefas de Projeto')}
                subtitulo={t('Urgente primeiro, depois o prazo mais próximo')}
                icone="fa-list-check"
                cor="roxo"
                accoes={
                    <>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNova}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova tarefa')}
                            </button>
                        )}
                        <a href="/projetos/lista" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-folder-open" aria-hidden="true" />
                            {t('Projetos')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-list-check">
                            {t(':n abertas', { n: numero(resumo.abertas) })}
                        </EstadoNaFaixa>
                        {resumo.atrasadas > 0 && (
                            <EstadoNaFaixa icone="fa-triangle-exclamation">
                                {t(':n atrasadas', { n: numero(resumo.atrasadas) })}
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

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <CartaoNumero
                        rotulo={t('Abertas')} valor={numero(resumo.abertas)} icone="fa-list-check" tom="roxo"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'abertas', so_minhas: false, page: 1 })}
                    />
                    <CartaoNumero
                        rotulo={t('Atrasadas')} valor={numero(resumo.atrasadas)} icone="fa-triangle-exclamation"
                        tom={resumo.atrasadas > 0 ? 'vermelho' : 'verde'}
                    />
                    <CartaoNumero
                        rotulo={t('Minhas')} valor={numero(resumo.minhas)} icone="fa-user-check" tom="indigo"
                        aoCarregar={() => porFiltros({ ...filtros, so_minhas: true, page: 1 })}
                    />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[12rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Título ou descrição…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Projeto')} className="w-56">
                    <select value={filtros.projeto ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, projeto: e.target.value ? Number(e.target.value) : '', page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {o.projetos.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-44">
                    <select value={filtros.estado ?? 'abertas'}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value, page: 1 })}
                        className={entrada}>
                        <option value="abertas">{t('Abertas')}</option>
                        <option value="todas">{t('Todas')}</option>
                        {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Prioridade')} className="w-40">
                    <select value={filtros.prioridade ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, prioridade: e.target.value || undefined, page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todas')}</option>
                        {o.prioridades.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <label className="flex h-11 items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" checked={filtros.so_minhas ?? false}
                        onChange={(e) => porFiltros({ ...filtros, so_minhas: e.target.checked, page: 1 })}
                        className="h-5 w-5 rounded text-purple-600" />
                    {t('Só as minhas')}
                </label>
            </div>

            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-list-check"
                    titulo={t('Nenhuma tarefa')}
                    frase={t('Uma tarefa organiza o trabalho do projeto — e é contra ela que as horas se lançam.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>
                            {t('Nova tarefa')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <ul className="space-y-2">
                    {lista.data.data.map((x, i) => (
                        <li key={x.id} style={cascata(i)}
                            className={cls('entra flex flex-wrap items-center gap-3 p-4 transition hover:shadow-md', CARTAO)}>
                            <span className={cls(
                                'grid h-10 w-10 flex-none place-items-center rounded-xl',
                                x.atrasada ? 'bg-red-50 text-red-600' : 'bg-purple-50 text-purple-600',
                            )}>
                                <i className={`fas ${ICONE_DO_ESTADO[x.estado] ?? 'fa-circle'}`} aria-hidden="true" />
                            </span>

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-slate-800">{x.titulo}</p>
                                <p className="truncate text-xs text-slate-500">
                                    {[x.projeto, x.responsavel].filter(Boolean).join(' · ') || t('Sem responsável')}
                                </p>
                            </div>

                            <Etiqueta cor={COR_DA_PRIORIDADE[x.prioridade] ?? 'neutra'} ponto>
                                {x.prioridade_rotulo}
                            </Etiqueta>

                            {x.prazo && (
                                <Etiqueta cor={x.atrasada ? 'perigo' : 'neutra'} icone="fa-calendar-day">
                                    {x.prazo}
                                </Etiqueta>
                            )}

                            {x.horas_estimadas !== null && (
                                <span className="flex-none text-xs tabular-nums text-slate-500">
                                    {t(':n h', { n: String(x.horas_estimadas) })}
                                </span>
                            )}

                            <Etiqueta cor={COR_DO_ESTADO[x.estado] ?? 'neutra'}>{x.estado_rotulo}</Etiqueta>

                            {pode && (
                                <div className="flex flex-none items-center gap-1.5">
                                    {x.estado === 'por_fazer' && (
                                        <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-play"
                                            aTrabalhar={estado.isPending}
                                            onClick={() => estado.mutate({ id: x.id, estado: 'em_curso' })}>
                                            {t('Começar')}
                                        </Botao>
                                    )}
                                    {x.estado === 'em_curso' && (
                                        <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-check"
                                            aTrabalhar={estado.isPending}
                                            onClick={() => estado.mutate({ id: x.id, estado: 'concluida' })}>
                                            {t('Concluir')}
                                        </Botao>
                                    )}
                                    {['concluida', 'cancelada'].includes(x.estado) && (
                                        <Botao altura="pequeno" icone="fa-rotate-left"
                                            aTrabalhar={estado.isPending}
                                            onClick={() => estado.mutate({ id: x.id, estado: 'por_fazer' })}
                                            aria-label={t('Reabrir')} />
                                    )}

                                    <Botao altura="pequeno" icone="fa-pen"
                                        onClick={() => {
                                            porAEditar(x.id);
                                            porFormulario({
                                                titulo: x.titulo,
                                                descricao: x.descricao ?? '',
                                                projeto_id: x.projeto_id ? String(x.projeto_id) : '',
                                                responsavel_id: x.responsavel_id ? String(x.responsavel_id) : '',
                                                prioridade: x.prioridade,
                                                prazo: x.prazo ?? '',
                                                horas_estimadas: x.horas_estimadas !== null ? String(x.horas_estimadas) : '',
                                            });
                                        }}
                                        aria-label={t('Editar tarefa')} />

                                    {/* NADA SE APAGA: cancelar é um estado, e as
                                        horas lançadas contra a tarefa continuam
                                        a valer. */}
                                    {!['concluida', 'cancelada'].includes(x.estado) && (
                                        <Botao altura="pequeno" cor="perigo" icone="fa-ban"
                                            aTrabalhar={estado.isPending}
                                            onClick={() => estado.mutate({ id: x.id, estado: 'cancelada' })}
                                            aria-label={t('Cancelar tarefa')} />
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
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

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar tarefa') : t('Nova tarefa')}
                subtitulo={t('Urgente e com prazo é o que sobe ao topo da lista')}
                icone="fa-list-check"
                cor="roxo"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                projeto_id: formulario.projeto_id ? Number(formulario.projeto_id) : null,
                                responsavel_id: formulario.responsavel_id ? Number(formulario.responsavel_id) : null,
                                prazo: formulario.prazo || null,
                                horas_estimadas: formulario.horas_estimadas === ''
                                    ? null : Number(formulario.horas_estimadas),
                            })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Título')} obrigatorio erro={erros.titulo}>
                            <input type="text" value={formulario.titulo}
                                onChange={(e) => porFormulario({ ...formulario, titulo: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Projeto')} obrigatorio erro={erros.projeto_id}>
                                <select value={formulario.projeto_id}
                                    onChange={(e) => porFormulario({ ...formulario, projeto_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Escolher…')}</option>
                                    {o.projetos.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Responsável')} erro={erros.responsavel_id}>
                                <select value={formulario.responsavel_id}
                                    onChange={(e) => porFormulario({ ...formulario, responsavel_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Sem responsável')}</option>
                                    {o.pessoas.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Prioridade')} obrigatorio erro={erros.prioridade}>
                                <select value={formulario.prioridade}
                                    onChange={(e) => porFormulario({ ...formulario, prioridade: e.target.value })}
                                    className={entrada}>
                                    {o.prioridades.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Prazo')} erro={erros.prazo}
                                ajuda={t('As sem prazo vão para o fim da lista.')}>
                                <input type="date" value={formulario.prazo}
                                    onChange={(e) => porFormulario({ ...formulario, prazo: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Horas estimadas')} erro={erros.horas_estimadas}>
                                <input type="number" step="0.25" min="0" value={formulario.horas_estimadas}
                                    onChange={(e) => porFormulario({ ...formulario, horas_estimadas: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Descrição')} erro={erros.descricao}>
                            <textarea rows={3} value={formulario.descricao}
                                onChange={(e) => porFormulario({ ...formulario, descricao: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>
        </div>
    );
}
