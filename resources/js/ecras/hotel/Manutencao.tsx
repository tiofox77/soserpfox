import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    manutencao,
    type FiltrosDaManutencao,
    type OpcoesDaManutencao,
    type OrdemDeManutencao,
    type OrdemParaGravar,
} from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t, tPartes } from '@/i18n';

/**
 * A MANUTENÇÃO DO HOTEL — o que está partido e quem o está a arranjar.
 *
 * DUAS VISTAS DA MESMA COISA, e de propósito: o QUADRO serve para ver onde
 * está a fila, a LISTA serve para encontrar uma ordem. O ecrã em Blade tinha
 * as duas e é o que aqui volta.
 *
 * ISTO NUNCA CONSEGUIU GRAVAR UMA ORDEM. O modelo declarava colunas que a
 * tabela não tem — `type`, `estimated_cost`, `resolution_notes` — e o insert
 * respondia «Unknown column 'type'», em qualquer ordem, sempre. Foi preciso
 * uma migração para o corrigir; está escrito no cabeçalho do modelo.
 */

const COR_DA_PRIORIDADE: Record<string, 'neutra' | 'primaria' | 'aviso' | 'perigo'> = {
    low: 'neutra',
    normal: 'primaria',
    high: 'aviso',
    urgent: 'perigo',
};

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    in_progress: 'primaria',
    waiting_parts: 'neutra',
    completed: 'bom',
    cancelled: 'perigo',
};

const ICONE_DA_CATEGORIA: Record<string, string> = {
    electrical: 'fa-bolt',
    plumbing: 'fa-faucet-drip',
    hvac: 'fa-fan',
    furniture: 'fa-chair',
    appliance: 'fa-blender',
    structural: 'fa-trowel-bricks',
    other: 'fa-screwdriver-wrench',
};

const formularioVazio = (): OrdemParaGravar => ({
    title: '', type: 'corrective', priority: 'normal', category: 'other',
    room_id: '', assigned_to: '', description: '', location: '',
    estimated_cost: '', estimated_time: '', scheduled_date: '',
});

export default function Manutencao() {
    const cache = useQueryClient();

    const [vista, porVista] = useState<'quadro' | 'lista'>('quadro');
    const [filtros, porFiltros] = useState<FiltrosDaManutencao>({ procura: '', page: 1 });
    const [formulario, porFormulario] = useState<OrdemParaGravar | null>(null);
    const [aEditar, porAEditar] = useState<OrdemDeManutencao | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aVer, porAVer] = useState<OrdemDeManutencao | null>(null);
    const [aApagar, porAApagar] = useState<OrdemDeManutencao | null>(null);
    const [aConcluir, porAConcluir] = useState<OrdemDeManutencao | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['hotel', 'manutencao', 'opcoes'], queryFn: manutencao.opcoes, staleTime: 5 * 60_000 });

    const lista = useQuery({
        queryKey: ['hotel', 'manutencao', 'lista', filtros],
        queryFn: () => manutencao.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const quadro = useQuery({
        queryKey: ['hotel', 'manutencao', 'quadro'],
        queryFn: manutencao.quadro,
        enabled: vista === 'quadro',
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['hotel', 'manutencao'] });

    const gravar = useMutation({
        mutationFn: (dados: OrdemParaGravar) => (aEditar ? manutencao.guardar(aEditar.id, dados) : manutencao.criar(dados)),
        onSuccess: (r) => { invalidar(); porFormulario(null); porAEditar(null); porErros({}); porRecado(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const mudarEstado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => manutencao.estado(id, estado),
        onSuccess: (r) => { invalidar(); porRecado(r.message); porAVer(r.data); },
    });

    const atribuirMe = useMutation({
        mutationFn: (o: OrdemDeManutencao) => manutencao.atribuirMe(o.id),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const apagar = useMutation({
        mutationFn: (o: OrdemDeManutencao) => manutencao.apagar(o.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const resumo = lista.data?.resumo;
    const contas = lista.data?.meta;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    const abrirNovo = () => { porAEditar(null); porErros({}); porFormulario(formularioVazio()); };

    const abrirEdicao = (x: OrdemDeManutencao) => {
        porAEditar(x); porErros({});
        porFormulario({
            title: x.titulo,
            type: x.tipo,
            priority: x.prioridade,
            category: x.categoria,
            room_id: String(x.room_id ?? ''),
            assigned_to: String(x.assigned_to ?? ''),
            description: x.descricao ?? '',
            location: x.local ?? '',
            estimated_cost: x.custo_previsto === null ? '' : String(x.custo_previsto),
            estimated_time: x.tempo_previsto === null ? '' : String(x.tempo_previsto),
            scheduled_date: x.agendada ?? '',
        });
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Manutenção')}
                subtitulo={t('O que está partido, e quem o está a arranjar')}
                icone="fa-screwdriver-wrench"
                cor="aviso"
                accoes={o.permissoes.pode_criar && (
                    <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                        {t('Nova Ordem')}
                    </button>
                )}
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} className={cls('p-1', FOCO, RAIO)} aria-label={t('Fechar')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            )}

            <AvisoDeErro erro={apagar.error ?? mudarEstado.error ?? atribuirMe.error} />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <CartaoNumero aspecto="claro" rotulo={t('Ordens')} tom="indigo" icone="fa-clipboard-list"
                    nota={t('nesta casa')} valor={resumo ? numero(resumo.total) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Urgentes por fechar')}
                    tom={resumo && resumo.urgentes > 0 ? 'vermelho' : 'cinza'} icone="fa-bolt"
                    valor={resumo ? numero(resumo.urgentes) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Pendentes')} tom="ambar" icone="fa-clock"
                    valor={resumo ? numero(resumo.pendentes) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Em curso')} tom="azul" icone="fa-screwdriver-wrench"
                    valor={resumo ? numero(resumo.em_curso) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('À espera de peças')} tom="cinza" icone="fa-box-open"
                    valor={resumo ? numero(resumo.a_espera) : '—'} />
            </div>

            {/* AS DUAS VISTAS. O quadro é para ver a fila; a lista é para
                encontrar uma ordem. Não são a mesma pergunta. */}
            <div className={cls(CARTAO, 'p-4')}>
                <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label={t('Vista')}>
                    {([['quadro', t('Quadro'), 'fa-table-columns'], ['lista', t('Lista'), 'fa-list']] as const).map(([qual, rotulo, icone]) => (
                        <button
                            key={qual}
                            type="button"
                            role="tab"
                            aria-selected={vista === qual}
                            onClick={() => porVista(qual)}
                            className={cls(
                                'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                vista === qual
                                    ? 'border-amber-500 bg-amber-50 text-amber-700 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            )}
                        >
                            <i className={cls('fas', icone)} aria-hidden="true" />
                            {rotulo}
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('Procurar')}>
                        <input type="search" value={filtros.procura ?? ''} className={entrada}
                            placeholder={t('Nº, título ou descrição')}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} />
                    </Campo>
                    <Campo etiqueta={t('Prioridade')}>
                        <select value={filtros.prioridade ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, prioridade: e.target.value, page: 1 }))}>
                            <option value="">{t('Todas')}</option>
                            {o.prioridades.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Categoria')}>
                        <select value={filtros.categoria ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, categoria: e.target.value, page: 1 }))}>
                            <option value="">{t('Todas')}</option>
                            {o.categorias.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Quarto')}>
                        <select value={filtros.quarto ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, quarto: e.target.value, page: 1 }))}>
                            <option value="">{t('Todos')}</option>
                            {o.quartos.map((q) => <option key={q.valor} value={q.valor}>{q.rotulo}</option>)}
                        </select>
                    </Campo>

                    {vista === 'lista' && (
                        <>
                            <Campo etiqueta={t('Estado')}>
                                <select value={filtros.estado ?? ''} className={entrada}
                                    onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))}>
                                    <option value="">{t('Todos')}</option>
                                    {o.estados.map((e2) => <option key={e2.valor} value={e2.valor}>{e2.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('Responsável')}>
                                <select value={filtros.responsavel ?? ''} className={entrada}
                                    onChange={(e) => porFiltros((f) => ({ ...f, responsavel: e.target.value, page: 1 }))}>
                                    <option value="">{t('Todos')}</option>
                                    {o.pessoal.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                </select>
                            </Campo>
                            <PorPagina
                                valor={filtros.por_pagina ?? 15}
                                aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))}
                            />
                        </>
                    )}
                </div>
            </div>

            {vista === 'quadro' ? (
                quadro.isPending ? <Carregando linhas={6} /> : quadro.isError ? <Falhou erro={quadro.error} /> : (
                    <div className="grid gap-3 lg:grid-cols-4">
                        {(quadro.data?.colunas ?? []).map((coluna) => (
                            <section key={coluna.estado} className={cls(CARTAO, 'flex flex-col p-3')}>
                                <h2 className="mb-3 flex items-center justify-between text-sm font-bold text-slate-700">
                                    {coluna.rotulo}
                                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs tabular-nums text-slate-500">
                                        {numero(coluna.quantas)}
                                    </span>
                                </h2>

                                {coluna.ordens.length === 0 ? (
                                    <p className="py-6 text-center text-xs text-slate-400">{t('Nada aqui.')}</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {coluna.ordens
                                            .filter((x) => cabeNoFiltro(x, filtros))
                                            .map((x, i) => (
                                                <li key={x.id} className="entra" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                                    <button
                                                        type="button"
                                                        onClick={() => porAVer(x)}
                                                        className={cls(
                                                            'w-full border border-slate-200 bg-white p-3 text-left transition-all duration-200',
                                                            'hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-sm active:translate-y-0',
                                                            RAIO, FOCO,
                                                        )}
                                                    >
                                                        <span className="flex items-start justify-between gap-2">
                                                            {/* DUAS LINHAS e não uma: «Ar condicionado do 201 não
                                                                arrefece» cortado ao terceiro caracter não é um
                                                                título, é um enigma. */}
                                                            <span className="line-clamp-2 min-w-0 flex-1 text-sm font-semibold text-slate-800">{x.titulo}</span>
                                                            <Etiqueta cor={COR_DA_PRIORIDADE[x.prioridade] ?? 'neutra'}>{x.prioridade_rotulo}</Etiqueta>
                                                        </span>
                                                        <span className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                                            <span>
                                                                <i className={cls('fas mr-1', ICONE_DA_CATEGORIA[x.categoria] ?? 'fa-wrench')} aria-hidden="true" />
                                                                {x.categoria_rotulo}
                                                            </span>
                                                            {x.quarto && <span><i className="fas fa-door-closed mr-1" aria-hidden="true" />{x.quarto}</span>}
                                                            {x.responsavel && <span><i className="fas fa-user mr-1" aria-hidden="true" />{x.responsavel}</span>}
                                                        </span>
                                                    </button>
                                                </li>
                                            ))}
                                    </ul>
                                )}
                            </section>
                        ))}
                    </div>
                )
            ) : (
                <div className={cls(CARTAO, 'overflow-hidden', lista.isFetching && 'opacity-70 transition-opacity')}>
                    {lista.isPending ? (
                        <Carregando linhas={6} />
                    ) : (lista.data?.data.length ?? 0) === 0 ? (
                        <SemNada
                            icone="fa-screwdriver-wrench"
                            titulo={t('Nenhuma ordem de manutenção')}
                            frase={t('Limpe os filtros, ou abra a primeira ordem.')}
                            accao={o.permissoes.pode_criar && (
                                <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>{t('Nova Ordem')}</Botao>
                            )}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        <th scope="col" className="px-4 py-3 text-left">{t('Nº')}</th>
                                        <th scope="col" className="px-4 py-3 text-left">{t('Título')}</th>
                                        <th scope="col" className="px-4 py-3 text-left">{t('Categoria')}</th>
                                        <th scope="col" className="px-4 py-3 text-left">{t('Quarto')}</th>
                                        <th scope="col" className="px-4 py-3 text-left">{t('Responsável')}</th>
                                        <th scope="col" className="px-4 py-3 text-left">{t('Prioridade')}</th>
                                        <th scope="col" className="px-4 py-3 text-left">{t('Estado')}</th>
                                        <th scope="col" className="px-4 py-3 text-right">{t('Acções')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {(lista.data?.data ?? []).map((x, i) => (
                                        <tr key={x.id} className="entra transition-colors duration-150 hover:bg-slate-50"
                                            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-500">{x.numero}</td>
                                            <td className="px-4 py-2.5">
                                                <span className="block font-semibold text-slate-800">{x.titulo}</span>
                                                {x.local && <span className="block text-xs text-slate-400">{x.local}</span>}
                                            </td>
                                            <td className="px-4 py-2.5 text-slate-600">
                                                <i className={cls('fas mr-1.5 text-slate-400', ICONE_DA_CATEGORIA[x.categoria] ?? 'fa-wrench')} aria-hidden="true" />
                                                {x.categoria_rotulo}
                                            </td>
                                            <td className="px-4 py-2.5 text-slate-600">{x.quarto ?? '—'}</td>
                                            <td className="px-4 py-2.5 text-slate-600">{x.responsavel ?? t('Por atribuir')}</td>
                                            <td className="px-4 py-2.5">
                                                <Etiqueta cor={COR_DA_PRIORIDADE[x.prioridade] ?? 'neutra'}>{x.prioridade_rotulo}</Etiqueta>
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <Etiqueta cor={COR_DO_ESTADO[x.estado] ?? 'neutra'}>{x.estado_rotulo}</Etiqueta>
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <div className="flex justify-end gap-1">
                                                    <button type="button" onClick={() => porAVer(x)}
                                                        title={t('Ver')} aria-label={t('Ver: :nome', { nome: x.titulo })}
                                                        className={cls('p-2 text-slate-400 transition-all hover:scale-110 hover:text-indigo-600', RAIO, FOCO)}>
                                                        <i className="fas fa-eye" aria-hidden="true" />
                                                    </button>
                                                    {o.permissoes.pode_editar && (
                                                        <button type="button" onClick={() => abrirEdicao(x)}
                                                            title={t('Editar')} aria-label={t('Editar: :nome', { nome: x.titulo })}
                                                            className={cls('p-2 text-slate-400 transition-all hover:scale-110 hover:text-indigo-600', RAIO, FOCO)}>
                                                            <i className="fas fa-pen" aria-hidden="true" />
                                                        </button>
                                                    )}
                                                    {o.permissoes.pode_apagar && (
                                                        <button type="button" onClick={() => porAApagar(x)}
                                                            title={t('Apagar')} aria-label={t('Apagar: :nome', { nome: x.titulo })}
                                                            className={cls('p-2 text-slate-400 transition-all hover:scale-110 hover:text-red-600', RAIO, FOCO)}>
                                                            <i className="fas fa-trash" aria-hidden="true" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {contas && contas.last_page > 1 && (
                        <div className="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm">
                            <span className="text-slate-500">
                                {t('Página :actual de :total', { actual: contas.current_page, total: contas.last_page })}
                            </span>
                            <span className="flex gap-2">
                                <Botao disabled={contas.current_page <= 1} icone="fa-chevron-left"
                                    onClick={() => porFiltros((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}>
                                    {t('Anterior')}
                                </Botao>
                                <Botao disabled={contas.current_page >= contas.last_page} icone="fa-chevron-right"
                                    onClick={() => porFiltros((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}>
                                    {t('Seguinte')}
                                </Botao>
                            </span>
                        </div>
                    )}
                </div>
            )}

            {formulario && (
                <FormularioDaOrdem
                    o={o}
                    valores={formulario}
                    erros={erros}
                    titulo={aEditar ? t('Editar Ordem') : t('Nova Ordem')}
                    subtitulo={aEditar?.numero}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    aoFechar={() => { porFormulario(null); porAEditar(null); porErros({}); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            {aVer && (
                <FichaDaOrdem
                    ordem={aVer}
                    o={o}
                    aTrabalhar={mudarEstado.isPending || atribuirMe.isPending}
                    aoFechar={() => porAVer(null)}
                    aoMudarEstado={(estado) => {
                        if (estado === 'completed') { porAConcluir(aVer); return; }
                        mudarEstado.mutate({ id: aVer.id, estado });
                    }}
                    aoAtribuirMe={() => atribuirMe.mutate(aVer)}
                />
            )}

            {aConcluir && (
                <Concluir
                    ordem={aConcluir}
                    aoFechar={() => porAConcluir(null)}
                    aoConcluir={(m, actualizada) => {
                        porAConcluir(null); porRecado(m); porAVer(actualizada); invalidar();
                    }}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar ordem de manutenção')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}>
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {tPartes('Vai apagar :nome. Não há volta.', { nome: <strong>{aApagar?.titulo ?? ''}</strong> })}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

/**
 * O QUADRO RESPEITA OS FILTROS DE CIMA.
 *
 * Os filtros estão acima das duas vistas; se o quadro os ignorasse, filtrar
 * por «eléctrica» mudava a lista e deixava o quadro igual — e quem estava a
 * ver o quadro concluía que o filtro não funciona.
 */
function cabeNoFiltro(x: OrdemDeManutencao, f: FiltrosDaManutencao): boolean {
    if (f.prioridade && x.prioridade !== f.prioridade) return false;
    if (f.categoria && x.categoria !== f.categoria) return false;
    if (f.quarto && String(x.room_id ?? '') !== f.quarto) return false;

    if (f.procura) {
        const procura = f.procura.toLowerCase();
        const onde = `${x.numero} ${x.titulo} ${x.descricao ?? ''}`.toLowerCase();

        if (!onde.includes(procura)) return false;
    }

    return true;
}

function FormularioDaOrdem({ o, valores, erros, titulo, subtitulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDaManutencao;
    valores: OrdemParaGravar;
    erros: Record<string, string[]>;
    titulo: string;
    subtitulo?: string;
    aGravar: boolean;
    erroGeral: unknown;
    aoMudar: (v: OrdemParaGravar) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const mudar = (campo: keyof OrdemParaGravar, valor: string) => aoMudar({ ...valores, [campo]: valor });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={titulo}
            subtitulo={subtitulo}
            icone="fa-screwdriver-wrench"
            cor="aviso"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroGeral} />

            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('O que se passa')} obrigatorio erro={erros.title} className="sm:col-span-2">
                    <input value={valores.title} onChange={(e) => mudar('title', e.target.value)} className={entrada}
                        placeholder={t('Ex.: torneira do 203 a pingar')} />
                </Campo>

                <Campo etiqueta={t('Tipo')} obrigatorio erro={erros.type}
                    ajuda={t('Preventiva é a que se faz antes de partir.')}>
                    <select value={valores.type} onChange={(e) => mudar('type', e.target.value)} className={entrada}>
                        {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Prioridade')} obrigatorio erro={erros.priority}>
                    <select value={valores.priority} onChange={(e) => mudar('priority', e.target.value)} className={entrada}>
                        {o.prioridades.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Categoria')} obrigatorio erro={erros.category}>
                    <select value={valores.category} onChange={(e) => mudar('category', e.target.value)} className={entrada}>
                        {o.categorias.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Quarto')} erro={erros.room_id}
                    ajuda={t('Em branco quando é numa zona comum.')}>
                    <select value={valores.room_id} onChange={(e) => mudar('room_id', e.target.value)} className={entrada}>
                        <option value="">—</option>
                        {o.quartos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Local')} erro={erros.location} className="sm:col-span-2"
                    ajuda={t('Onde exactamente — «casa de banho», «cozinha do piso 1».')}>
                    <input value={valores.location} onChange={(e) => mudar('location', e.target.value)} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Responsável')} erro={erros.assigned_to}>
                    <select value={valores.assigned_to} onChange={(e) => mudar('assigned_to', e.target.value)} className={entrada}>
                        <option value="">{t('Por atribuir')}</option>
                        {o.pessoal.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Agendada para')} erro={erros.scheduled_date}>
                    <input type="date" value={valores.scheduled_date}
                        onChange={(e) => mudar('scheduled_date', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Custo previsto (Kz)')} erro={erros.estimated_cost}>
                    <input type="number" min={0} step="0.01" value={valores.estimated_cost}
                        onChange={(e) => mudar('estimated_cost', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Tempo previsto (minutos)')} erro={erros.estimated_time}>
                    <input type="number" min={0} step={5} value={valores.estimated_time}
                        onChange={(e) => mudar('estimated_time', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Descrição')} erro={erros.description} className="sm:col-span-2">
                    <textarea rows={3} value={valores.description}
                        onChange={(e) => mudar('description', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                </Campo>
            </div>
        </Modal>
    );
}

function FichaDaOrdem({ ordem, o, aTrabalhar, aoFechar, aoMudarEstado, aoAtribuirMe }: {
    ordem: OrdemDeManutencao;
    o: OpcoesDaManutencao;
    aTrabalhar: boolean;
    aoFechar: () => void;
    aoMudarEstado: (estado: string) => void;
    aoAtribuirMe: () => void;
}) {
    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={ordem.titulo}
            subtitulo={ordem.numero}
            icone={ICONE_DA_CATEGORIA[ordem.categoria] ?? 'fa-screwdriver-wrench'}
            cor="aviso"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            <div className="space-y-4">
                {o.permissoes.pode_editar && (
                    <div className={cls('flex flex-wrap items-center gap-2 border border-slate-200 bg-slate-50 p-3', RAIO)}>
                        <span className="text-xs font-bold uppercase tracking-wide text-slate-500">{t('Estado')}</span>
                        {o.estados.map((e) => {
                            const actual = e.valor === ordem.estado;

                            return (
                                <button
                                    key={e.valor}
                                    type="button"
                                    disabled={actual || aTrabalhar}
                                    onClick={() => aoMudarEstado(e.valor)}
                                    className={cls(
                                        'border px-2.5 py-1.5 text-xs font-bold transition-all duration-200',
                                        'hover:-translate-y-0.5 active:translate-y-0 disabled:translate-y-0',
                                        RAIO, FOCO,
                                        actual
                                            ? 'cursor-default border-amber-500 bg-amber-50 text-amber-700 shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                                    )}
                                >
                                    {actual && <i className="fas fa-check mr-1 text-[10px]" aria-hidden="true" />}
                                    {e.rotulo}
                                </button>
                            );
                        })}

                        {/* ATRIBUIR-ME só a quem TEM ficha de pessoal: o ecrã de
                            sempre mostrava-o a toda a gente, e quem não tinha
                            carregava e não acontecia nada. */}
                        {o.tenho_ficha && (
                            <Botao icone="fa-hand" onClick={aoAtribuirMe} aTrabalhar={aTrabalhar}>
                                {t('Atribuir-me')}
                            </Botao>
                        )}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h3 className="mb-3 text-sm font-bold text-slate-900">{t('A ordem')}</h3>
                        <dl className="space-y-1.5">
                            <Dado rotulo={t('Tipo')} valor={ordem.tipo_rotulo} />
                            <Dado rotulo={t('Categoria')} valor={ordem.categoria_rotulo} />
                            <Dado rotulo={t('Prioridade')} valor={ordem.prioridade_rotulo} />
                            <Dado rotulo={t('Quarto')} valor={ordem.quarto} />
                            <Dado rotulo={t('Local')} valor={ordem.local} />
                            <Dado rotulo={t('Responsável')} valor={ordem.responsavel ?? t('Por atribuir')} />
                            <Dado rotulo={t('Quem reportou')} valor={ordem.quem_reportou} />
                        </dl>
                    </section>

                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h3 className="mb-3 text-sm font-bold text-slate-900">{t('Tempos e custos')}</h3>
                        <dl className="space-y-1.5">
                            <Dado rotulo={t('Aberta em')} valor={ordem.aberta_em ? data(ordem.aberta_em) : null} />
                            <Dado rotulo={t('Agendada para')} valor={ordem.agendada ? data(ordem.agendada) : null} />
                            <Dado rotulo={t('Começou')} valor={ordem.iniciada ? data(ordem.iniciada) : null} />
                            <Dado rotulo={t('Acabou')} valor={ordem.concluida ? data(ordem.concluida) : null} />
                            <Dado rotulo={t('Tempo previsto')}
                                valor={ordem.tempo_previsto === null ? null : t(':n min', { n: ordem.tempo_previsto })} />
                            <Dado rotulo={t('Tempo gasto')}
                                valor={ordem.minutos_gastos === null ? null : t(':n min', { n: ordem.minutos_gastos })} />
                            <Dado rotulo={t('Custo previsto')}
                                valor={ordem.custo_previsto === null ? null : `${kz(ordem.custo_previsto)} Kz`} />
                            <Dado rotulo={t('Custo real')}
                                valor={ordem.custo === null ? null : `${kz(ordem.custo)} Kz`} />
                        </dl>
                    </section>
                </div>

                {ordem.descricao && (
                    <section className={cls('border border-slate-200 bg-slate-50 p-3', RAIO)}>
                        <h3 className="mb-1 text-sm font-bold text-slate-900">
                            <i className="fas fa-circle-info mr-2 text-slate-400" aria-hidden="true" />
                            {t('Descrição')}
                        </h3>
                        <p className="whitespace-pre-line text-sm text-slate-700">{ordem.descricao}</p>
                    </section>
                )}

                {ordem.resolucao && (
                    <section className={cls('border border-emerald-200 bg-emerald-50 p-3', RAIO)}>
                        <h3 className="mb-1 text-sm font-bold text-emerald-900">
                            <i className="fas fa-circle-check mr-2" aria-hidden="true" />
                            {t('O que se fez')}
                        </h3>
                        <p className="whitespace-pre-line text-sm text-emerald-800">{ordem.resolucao}</p>
                    </section>
                )}
            </div>
        </Modal>
    );
}

/**
 * FECHAR A ORDEM — e escrever o que se fez.
 *
 * O ecrã de sempre gravava a resolução e o custo real em `resolution_notes` e
 * `actual_cost`, que não são colunas: a ordem fechava sempre sem a resolução
 * escrita, e ninguém dava por isso porque também não a mostrava.
 */
function Concluir({ ordem, aoFechar, aoConcluir }: {
    ordem: OrdemDeManutencao;
    aoFechar: () => void;
    aoConcluir: (mensagem: string, actualizada: OrdemDeManutencao) => void;
}) {
    const [resolucao, porResolucao] = useState('');
    const [custo, porCusto] = useState(ordem.custo_previsto === null ? '' : String(ordem.custo_previsto));

    const concluir = useMutation({
        mutationFn: () => manutencao.estado(ordem.id, 'completed', { resolucao, custo }),
        onSuccess: (r) => aoConcluir(r.message, r.data),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Concluir a ordem')}
            subtitulo={ordem.titulo}
            icone="fa-circle-check"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={concluir.isPending}
                        onClick={() => concluir.mutate()}>
                        {t('Concluir')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={concluir.error} />

            <div className="grid gap-3">
                <Campo etiqueta={t('O que se fez')}
                    ajuda={t('Fica na ficha — é o que a próxima pessoa vai ler quando isto voltar a partir.')}>
                    <textarea rows={4} value={resolucao} onChange={(e) => porResolucao(e.target.value)}
                        className={cls(entrada, 'h-auto py-2')} />
                </Campo>
                <Campo etiqueta={t('Custo real (Kz)')}>
                    <input type="number" min={0} step="0.01" value={custo}
                        onChange={(e) => porCusto(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
            </div>
        </Modal>
    );
}

function Dado({ rotulo, valor }: { rotulo: string; valor?: string | null }) {
    if (!valor) return null;

    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="font-semibold text-slate-800">{valor}</dd>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a manutenção')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
