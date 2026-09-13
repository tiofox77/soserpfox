import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { projetos, type Projeto } from '@/api/projetos';
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

/**
 * OS PROJETOS — o orçamento, o que já se gastou, e o que falta cobrar.
 *
 * UM PROJETO TEM TRÊS NÚMEROS que se confundem e não são o mesmo:
 *
 *   · o ORÇAMENTO, que é o tecto;
 *   · o CONSUMIDO, que são as horas lançadas ao preço a que foram lançadas;
 *   · e o FACTURADO, que é o que já saiu em documento.
 *
 * A ficha mostra os três lado a lado, com os documentos que dela saíram — sem
 * isso, facturava-se e perdia-se o rasto.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    rascunho: 'neutra',
    activo: 'primaria',
    em_pausa: 'aviso',
    concluido: 'bom',
    cancelado: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    rascunho: 'fa-pen-ruler',
    activo: 'fa-play',
    em_pausa: 'fa-pause',
    concluido: 'fa-flag-checkered',
    cancelado: 'fa-xmark',
};

const VAZIO = {
    nome: '', client_id: '', responsavel_id: '', data_inicio: '',
    data_fim_prevista: '', orcamento: '', valor_hora: '', descricao: '',
};

export default function ListaDeProjetos({ projeto: abrirProjeto }: { projeto?: number }) {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{
        procura?: string; estado?: string; por_pagina?: number; page?: number;
    }>({ estado: 'todos', por_pagina: 12, page: 1 });

    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<number | null>(abrirProjeto ?? null);
    const [aFacturar, porAFacturar] = useState<Projeto | null>(null);

    const opcoes = useQuery({ queryKey: ['projetos', 'opcoes'], queryFn: () => projetos.lista.opcoes() });

    const lista = useQuery({
        queryKey: ['projetos', 'lista', filtros],
        queryFn: () => projetos.lista.listar(filtros),
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['projetos'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => projetos.lista.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const estado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => projetos.lista.estado(id, estado),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const facturar = useMutation({
        mutationFn: (id: number) => projetos.lista.facturar(id),
        onSuccess: (r) => { feito(r.message); porAFacturar(null); },
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

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Projetos')}
                subtitulo={t('O orçamento, o consumido e o facturado — os três, lado a lado')}
                icone="fa-diagram-project"
                cor="roxo"
                accoes={
                    <>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA}
                                onClick={() => {
                                    porAEditar(null);
                                    porFormulario({ ...VAZIO, data_inicio: new Date().toISOString().slice(0, 10) });
                                }}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo projeto')}
                            </button>
                        )}
                        <a href="/projetos/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chart-pie" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-play">
                            {t(':n a andar', { n: numero(resumo.activos) })}
                        </EstadoNaFaixa>
                        <EstadoNaFaixa icone="fa-flag-checkered">
                            {t(':n concluídos', { n: numero(resumo.concluidos) })}
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
                <div className="grid gap-4 sm:grid-cols-3">
                    <CartaoNumero aspecto="claro" rotulo={t('Projetos')} valor={numero(resumo.total)}
                        icone="fa-diagram-project" tom="roxo" />
                    <CartaoNumero aspecto="claro" rotulo={t('A andar')} valor={numero(resumo.activos)}
                        icone="fa-play" tom="indigo" />
                    <CartaoNumero aspecto="claro" rotulo={t('Concluídos')} valor={numero(resumo.concluidos)}
                        icone="fa-flag-checkered" tom="verde" />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Código ou nome do projeto…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-44">
                    <select value={filtros.estado ?? 'todos'}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value, page: 1 })}
                        className={entrada}>
                        <option value="todos">{t('Todos')}</option>
                        {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-diagram-project"
                    titulo={t('Nenhum projeto')}
                    frase={t('Um projeto guarda o orçamento e o preço/hora — e é contra ele que as horas se lançam e se facturam.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => {
                                porAEditar(null);
                                porFormulario({ ...VAZIO, data_inicio: new Date().toISOString().slice(0, 10) });
                            }}>
                            {t('Novo projeto')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {lista.data.data.map((p, i) => (
                        <article key={p.id} style={cascata(i)}
                            className={cls('entra flex flex-col gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md', CARTAO)}>
                            <div className="flex items-start gap-3">
                                <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-purple-50 text-purple-600">
                                    <i className="fas fa-folder" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold text-slate-800">{p.nome}</p>
                                    <p className="truncate font-mono text-xs text-slate-500">{p.codigo}</p>
                                </div>
                                <Etiqueta cor={COR_DO_ESTADO[p.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[p.estado]}>
                                    {p.estado_rotulo}
                                </Etiqueta>
                            </div>

                            <dl className="space-y-1 text-xs text-slate-600">
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-user w-4 text-slate-400" aria-hidden="true" />
                                    <span className="truncate">{p.cliente ?? t('Sem cliente')}</span>
                                </div>
                                {p.responsavel && (
                                    <div className="flex items-center gap-2">
                                        <i className="fas fa-user-tie w-4 text-slate-400" aria-hidden="true" />
                                        <span className="truncate">{p.responsavel}</span>
                                    </div>
                                )}
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-clock w-4 text-slate-400" aria-hidden="true" />
                                    <span className="tabular-nums">
                                        {t(':n horas lançadas', { n: String(p.horas) })}
                                    </span>
                                </div>
                            </dl>

                            {/* A BARRA DO ORÇAMENTO. Sem orçamento não há barra. */}
                            {p.percentagem === null ? (
                                <p className="text-xs italic text-slate-400">{t('Sem orçamento definido')}</p>
                            ) : (
                                <div>
                                    <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className={cls('h-full rounded-full transition-all duration-500',
                                                p.acima_do_orcamento ? 'bg-red-500'
                                                    : p.percentagem > 80 ? 'bg-amber-500' : 'bg-emerald-500')}
                                            style={{ width: `${Math.min(100, Math.max(2, p.percentagem))}%` }}
                                        />
                                    </div>
                                    <p className="mt-1 flex items-center justify-between text-[11px] tabular-nums text-slate-500">
                                        <span>{t(':v de :o Kz', { v: kz(p.consumido, 0), o: kz(p.orcamento, 0) })}</span>
                                        <span className={cls('font-bold', p.acima_do_orcamento && 'text-red-600')}>
                                            {p.percentagem}%
                                        </span>
                                    </p>
                                </div>
                            )}

                            <div className="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                                <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(p.id)}>
                                    {t('Ficha')}
                                </Botao>

                                {pode && (
                                    <>
                                        {p.estado === 'rascunho' && (
                                            <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-play"
                                                aTrabalhar={estado.isPending}
                                                onClick={() => estado.mutate({ id: p.id, estado: 'activo' })}>
                                                {t('Arrancar')}
                                            </Botao>
                                        )}
                                        {p.estado === 'activo' && (
                                            <>
                                                <Botao altura="pequeno" icone="fa-pause"
                                                    aTrabalhar={estado.isPending}
                                                    onClick={() => estado.mutate({ id: p.id, estado: 'em_pausa' })}
                                                    aria-label={t('Pôr em pausa')} />
                                                <Botao altura="pequeno" cor="bom" icone="fa-flag-checkered"
                                                    aTrabalhar={estado.isPending}
                                                    onClick={() => estado.mutate({ id: p.id, estado: 'concluido' })}
                                                    aria-label={t('Concluir')} />
                                            </>
                                        )}
                                        {p.estado === 'em_pausa' && (
                                            <Botao altura="pequeno" cor="primaria" icone="fa-play"
                                                aTrabalhar={estado.isPending}
                                                onClick={() => estado.mutate({ id: p.id, estado: 'activo' })}>
                                                {t('Retomar')}
                                            </Botao>
                                        )}

                                        <Botao altura="pequeno" icone="fa-pen"
                                            onClick={() => {
                                                porAEditar(p.id);
                                                porFormulario({
                                                    nome: p.nome,
                                                    client_id: p.client_id ? String(p.client_id) : '',
                                                    responsavel_id: p.responsavel_id ? String(p.responsavel_id) : '',
                                                    data_inicio: p.inicio ?? '',
                                                    data_fim_prevista: p.fim_previsto ?? '',
                                                    orcamento: p.orcamento ? String(p.orcamento) : '',
                                                    valor_hora: p.valor_hora ? String(p.valor_hora) : '',
                                                    descricao: p.descricao ?? '',
                                                });
                                            }}
                                            aria-label={t('Editar projeto')} />
                                    </>
                                )}

                                {o.permissoes.pode_facturar && (
                                    <Botao altura="pequeno" cor="bom" icone="fa-file-invoice"
                                        onClick={() => porAFacturar(p)}>
                                        {t('Facturar')}
                                    </Botao>
                                )}
                            </div>
                        </article>
                    ))}
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

            {/* ─── O formulário ───────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar projeto') : t('Novo projeto')}
                subtitulo={t('O preço/hora é o que transforma horas em dinheiro')}
                icone="fa-diagram-project"
                cor="roxo"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                client_id: formulario.client_id ? Number(formulario.client_id) : null,
                                responsavel_id: formulario.responsavel_id ? Number(formulario.responsavel_id) : null,
                                data_inicio: formulario.data_inicio || null,
                                data_fim_prevista: formulario.data_fim_prevista || null,
                                orcamento: formulario.orcamento === '' ? null : Number(formulario.orcamento),
                                valor_hora: formulario.valor_hora === '' ? null : Number(formulario.valor_hora),
                            })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.nome}>
                            <input type="text" value={formulario.nome}
                                onChange={(e) => porFormulario({ ...formulario, nome: e.target.value })}
                                placeholder={t('Ex.: Remodelação do escritório')} className={entrada} />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Cliente')} erro={erros.client_id}
                                ajuda={t('Sem cliente não há a quem facturar as horas.')}>
                                <select value={formulario.client_id}
                                    onChange={(e) => porFormulario({ ...formulario, client_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Sem cliente')}</option>
                                    {o.clientes.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
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

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Início')} erro={erros.data_inicio}>
                                <input type="date" value={formulario.data_inicio}
                                    onChange={(e) => porFormulario({ ...formulario, data_inicio: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Fim previsto')} erro={erros.data_fim_prevista}>
                                <input type="date" value={formulario.data_fim_prevista}
                                    onChange={(e) => porFormulario({ ...formulario, data_fim_prevista: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Orçamento')} erro={erros.orcamento}
                                ajuda={t('Sem orçamento não há percentagem — e o painel não avisa de estouro nenhum.')}>
                                <input type="number" step="0.01" min="0" value={formulario.orcamento}
                                    onChange={(e) => porFormulario({ ...formulario, orcamento: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>

                            <Campo etiqueta={t('Preço/hora')} erro={erros.valor_hora}
                                ajuda={t('Congela em cada hora lançada — mudar aqui não reescreve o passado.')}>
                                <input type="number" step="0.01" min="0" value={formulario.valor_hora}
                                    onChange={(e) => porFormulario({ ...formulario, valor_hora: e.target.value })}
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

            <Ficha id={aVer} aoFechar={() => porAVer(null)} />

            <Modal
                aberto={aFacturar !== null}
                aoFechar={() => porAFacturar(null)}
                titulo={t('Facturar as horas')}
                subtitulo={aFacturar?.nome}
                icone="fa-file-invoice"
                cor="bom"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAFacturar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="bom" tom="solida" icone="fa-file-invoice" aTrabalhar={facturar.isPending}
                            onClick={() => aFacturar && facturar.mutate(aFacturar.id)}>
                            {t('Criar factura')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-3">
                    <AvisoDeErro erro={facturar.error} />
                    <p className="text-sm text-slate-600">
                        {t('As horas facturáveis ainda não cobradas vão para uma factura em RASCUNHO, para conferir antes de emitir.')}
                    </p>
                    <p className="text-xs text-slate-500">
                        {t('Cada hora fica marcada com a factura que a levou — é essa marca que impede cobrá-la duas vezes.')}
                    </p>
                </div>
            </Modal>
        </div>
    );
}

/* ─── A ficha ─────────────────────────────────────────────────────────── */

/**
 * A FICHA: os três números lado a lado, e os documentos que dela saíram.
 *
 * Orçamento, consumido e facturado não são o mesmo, e é vê-los juntos que
 * responde à pergunta que interessa: quanto é que ainda falta cobrar.
 */
function Ficha({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const ficha = useQuery({
        queryKey: ['projetos', 'ficha', id],
        queryFn: () => projetos.lista.ficha(id as number),
        enabled: id !== null,
    });

    const p = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={p?.nome ?? t('Projeto')}
            subtitulo={p?.codigo}
            icone="fa-folder-open"
            cor="roxo"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {ficha.isPending ? (
                <Carregando linhas={6} />
            ) : ficha.isError ? (
                <AvisoDeErro erro={ficha.error} />
            ) : p ? (
                <div className="space-y-5">
                    <div className="flex flex-wrap items-center gap-2">
                        <Etiqueta cor={COR_DO_ESTADO[p.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[p.estado]}>
                            {p.estado_rotulo}
                        </Etiqueta>
                        {p.acima_do_orcamento && (
                            <Etiqueta cor="perigo" icone="fa-fire">{t('Acima do orçamento')}</Etiqueta>
                        )}
                    </div>

                    {/* OS TRÊS NÚMEROS, lado a lado. */}
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Numero rotulo={t('Orçamento')} valor={p.orcamento} nota={t('O tecto')} tom="slate" />
                        <Numero rotulo={t('Consumido')} valor={p.consumido}
                            nota={t(':n horas lançadas', { n: String(p.horas) })}
                            tom={p.acima_do_orcamento ? 'red' : 'amber'} />
                        <Numero rotulo={t('Facturado')} valor={p.facturado}
                            nota={t(':n horas cobradas', { n: String(p.horas_facturadas) })} tom="emerald" />
                    </div>

                    {p.por_facturar > 0 && (
                        <p className={cls('border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800', RAIO)}>
                            <i className="fas fa-file-invoice mr-2" aria-hidden="true" />
                            {t('Há :v Kz de horas trabalhadas e ainda não cobradas.', { v: kz(p.por_facturar) })}
                        </p>
                    )}

                    <dl className="grid gap-3 sm:grid-cols-2">
                        <Linha rotulo={t('Cliente')} valor={p.cliente} />
                        <Linha rotulo={t('Responsável')} valor={p.responsavel} />
                        <Linha rotulo={t('Início')} valor={p.inicio} />
                        <Linha rotulo={t('Fim previsto')} valor={p.fim_previsto} />
                        <Linha rotulo={t('Preço/hora')} valor={p.valor_hora ? `${kz(p.valor_hora)} Kz` : null} />
                        <Linha rotulo={t('Criado por')} valor={p.autor} />
                    </dl>

                    {p.descricao && (
                        <p className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                            {p.descricao}
                        </p>
                    )}

                    <section>
                        <h3 className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-list-check mr-2 text-purple-600" aria-hidden="true" />
                            {t('Tarefas')}
                        </h3>
                        <p className="text-sm text-slate-600">
                            {t(':a abertas de :t no total', {
                                a: String(ficha.data.tarefas.abertas), t: String(ficha.data.tarefas.total),
                            })}
                        </p>
                    </section>

                    <section>
                        <h3 className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-file-invoice mr-2 text-purple-600" aria-hidden="true" />
                            {t('Facturas deste projeto')}
                        </h3>

                        {ficha.data.facturas.length === 0 ? (
                            <p className="text-sm text-slate-500">{t('Ainda não saiu nenhuma factura deste projeto.')}</p>
                        ) : (
                            <ul className="space-y-1.5">
                                {ficha.data.facturas.map((f) => (
                                    <li key={f.id} className={cls('flex items-center gap-3 border border-slate-200 bg-white px-3 py-2', RAIO)}>
                                        <a href={`/invoicing/sales-invoices/${f.id}`}
                                            className={cls('font-mono text-xs text-purple-700 underline-offset-2 hover:underline', FOCO)}>
                                            {f.numero}
                                        </a>
                                        <span className="text-xs text-slate-500">{f.dia}</span>
                                        <span className="ml-auto text-sm font-bold tabular-nums text-slate-900">
                                            {kz(f.total)}
                                        </span>
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

function Numero({
    rotulo, valor, nota, tom,
}: {
    rotulo: string; valor: number; nota: string; tom: 'slate' | 'amber' | 'red' | 'emerald';
}) {
    const cores = {
        slate: 'border-slate-200 bg-slate-50 text-slate-700',
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
        red: 'border-red-200 bg-red-50 text-red-800',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    }[tom];

    return (
        <div className={cls('border px-4 py-3', RAIO, cores)}>
            <p className="text-xs font-semibold uppercase tracking-wider opacity-80">{rotulo}</p>
            <p className="text-lg font-bold tabular-nums">{kz(valor)}</p>
            <p className="text-[11px] opacity-70">{nota}</p>
        </div>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor?: string | null }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className="text-sm font-medium text-slate-800">{valor || '—'}</dd>
        </div>
    );
}
