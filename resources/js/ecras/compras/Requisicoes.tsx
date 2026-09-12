import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { compras, type Requisicao } from '@/api/compras';
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

/**
 * AS REQUISIÇÕES DE COMPRA — o pedido interno, antes de haver fornecedor.
 *
 * QUEM PEDE NÃO É QUEM APROVA, e é essa separação a razão de ser de uma
 * requisição: sem ela, o circuito é só burocracia. Os botões de decidir só
 * aparecem a quem tem a permissão de decidir — e a porta exige-a na mesma.
 *
 * UMA LINHA PODE NÃO ESTAR NO CATÁLOGO. Pedir algo que ainda não existe como
 * artigo é o caso mais comum — uma peça, um serviço, uma reparação — e obrigar
 * a criar o artigo primeiro era obrigar a inventar dados.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    rascunho: 'neutra',
    submetida: 'aviso',
    aprovada: 'bom',
    rejeitada: 'perigo',
    encomendada: 'primaria',
    cancelada: 'neutra',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    rascunho: 'fa-pen-ruler',
    submetida: 'fa-hourglass-half',
    aprovada: 'fa-circle-check',
    rejeitada: 'fa-xmark',
    encomendada: 'fa-truck',
    cancelada: 'fa-ban',
};

type Linha = {
    product_id: number | null;
    descricao: string;
    quantidade: number | string;
    custo_estimado: number | string;
    unidade: string | null;
    notas: string | null;
};

const VAZIO = {
    warehouse_id: '', necessaria_em: '', justificacao: '', linhas: [] as Linha[],
};

export default function Requisicoes({ requisicao }: { requisicao?: number }) {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{
        procura?: string; estado?: string; por_pagina?: number; page?: number;
    }>({ estado: 'todos', por_pagina: 15, page: 1 });

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<number | null>(requisicao ?? null);
    const [aRejeitar, porARejeitar] = useState<Requisicao | null>(null);
    const [motivo, porMotivo] = useState('');
    const [procuraArtigo, porProcuraArtigo] = useState('');

    const opcoes = useQuery({
        queryKey: ['compras', 'requisicoes', 'opcoes'],
        queryFn: () => compras.requisicoes.opcoes(),
    });

    const lista = useQuery({
        queryKey: ['compras', 'requisicoes', filtros],
        queryFn: () => compras.requisicoes.lista(filtros),
    });

    const artigos = useQuery({
        queryKey: ['compras', 'artigos', procuraArtigo],
        queryFn: () => compras.requisicoes.artigos(procuraArtigo),
        enabled: procuraArtigo.trim().length >= 2,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['compras'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => compras.requisicoes.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const passo = useMutation({
        mutationFn: ({ id, qual }: { id: number; qual: 'submeter' | 'aprovar' | 'cancelar' }) =>
            compras.requisicoes[qual](id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const rejeitar = useMutation({
        mutationFn: () => compras.requisicoes.rejeitar(aRejeitar!.id, motivo),
        onSuccess: (r) => { feito(r.message); porARejeitar(null); porMotivo(''); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.pode_gerir;
    const decide = o.permissoes.pode_decidir;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const abrirNova = () => {
        porAEditar(null);
        porFormulario({ ...VAZIO, warehouse_id: o.armazens[0]?.valor ?? '', linhas: [] });
        porProcuraArtigo('');
    };

    const mexerNaLinha = (i: number, campo: keyof Linha, valor: unknown) => {
        if (!formulario) return;

        const linhas = [...formulario.linhas];

        linhas[i] = { ...linhas[i]!, [campo]: valor } as Linha;
        porFormulario({ ...formulario, linhas });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Requisições de Compra')}
                subtitulo={t('O pedido interno, antes de haver fornecedor')}
                icone="fa-clipboard-list"
                cor="bom"
                accoes={
                    <>
                        {pode && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNova}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova requisição')}
                            </button>
                        )}
                        <a href="/compras/encomendas" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-truck" aria-hidden="true" />
                            {t('Encomendas')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <div className="flex flex-wrap items-center gap-2">
                        {resumo.submetidas > 0 && (
                            <EstadoNaFaixa icone="fa-hourglass-half">
                                {t(':n por decidir', { n: numero(resumo.submetidas) })}
                            </EstadoNaFaixa>
                        )}
                        <EstadoNaFaixa icone="fa-circle-check">
                            {t(':n aprovadas', { n: numero(resumo.aprovadas) })}
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
                    <CartaoNumero rotulo={t('Rascunhos')} valor={numero(resumo.rascunhos)} icone="fa-pen-ruler" tom="cinza"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'rascunho', page: 1 })} />
                    <CartaoNumero rotulo={t('Por decidir')} valor={numero(resumo.submetidas)} icone="fa-hourglass-half" tom="ambar"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'submetida', page: 1 })} />
                    <CartaoNumero rotulo={t('Aprovadas')} valor={numero(resumo.aprovadas)} icone="fa-circle-check" tom="verde"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'aprovada', page: 1 })} />
                    <CartaoNumero rotulo={t('Total')} valor={numero(resumo.total)} icone="fa-clipboard-list" tom="indigo"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'todos', page: 1 })} />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Número, justificação ou artigo…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-48">
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
                    icone="fa-clipboard-list"
                    titulo={t('Nenhuma requisição')}
                    frase={t('Uma requisição é o pedido de quem precisa — e é ela que dá a alguém a hipótese de dizer que sim ou que não.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>
                            {t('Nova requisição')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <div className={cls('overflow-x-auto', CARTAO)}>
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left">{t('Número')}</th>
                                <th className="px-4 py-3 text-left">{t('Quem pediu')}</th>
                                <th className="px-4 py-3 text-left">{t('Armazém')}</th>
                                <th className="px-4 py-3 text-left">{t('Precisa em')}</th>
                                <th className="px-4 py-3 text-right">{t('Linhas')}</th>
                                <th className="px-4 py-3 text-left">{t('Estado')}</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lista.data.data.map((r, i) => (
                                <tr key={r.id} style={cascata(i)} className="entra transition hover:bg-slate-50/70">
                                    <td className="px-4 py-3">
                                        <p className="font-mono font-semibold text-slate-800">{r.numero}</p>
                                        {r.justificacao && (
                                            <p className="truncate text-xs text-slate-500">{r.justificacao}</p>
                                        )}
                                        {r.motivo_recusa && (
                                            <p className="truncate text-xs text-red-600">{r.motivo_recusa}</p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{r.autor ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-600">{r.armazem ?? '—'}</td>
                                    <td className="px-4 py-3 tabular-nums text-slate-600">{r.necessaria_em ?? '—'}</td>
                                    <td className="px-4 py-3 text-right tabular-nums text-slate-600">{r.linhas}</td>
                                    <td className="px-4 py-3">
                                        <Etiqueta cor={COR_DO_ESTADO[r.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[r.estado]}>
                                            {r.estado_rotulo}
                                        </Etiqueta>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1.5">
                                            <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(r.id)}
                                                aria-label={t('Ver a requisição')} />

                                            {pode && r.estado === 'rascunho' && (
                                                <>
                                                    <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-paper-plane"
                                                        aTrabalhar={passo.isPending}
                                                        onClick={() => passo.mutate({ id: r.id, qual: 'submeter' })}>
                                                        {t('Submeter')}
                                                    </Botao>
                                                    <Botao altura="pequeno" icone="fa-pen"
                                                        onClick={() => abrirEdicao(r.id)}
                                                        aria-label={t('Editar requisição')} />
                                                </>
                                            )}

                                            {/*
                                              * QUEM PEDE NÃO É QUEM APROVA: estes
                                              * dois botões vivem de uma permissão
                                              * à parte, e a porta exige-a.
                                              */}
                                            {decide && r.estado === 'submetida' && (
                                                <>
                                                    <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-check"
                                                        aTrabalhar={passo.isPending}
                                                        onClick={() => passo.mutate({ id: r.id, qual: 'aprovar' })}>
                                                        {t('Aprovar')}
                                                    </Botao>
                                                    <Botao altura="pequeno" cor="perigo" icone="fa-xmark"
                                                        onClick={() => { porARejeitar(r); porMotivo(''); }}
                                                        aria-label={t('Recusar')} />
                                                </>
                                            )}

                                            {pode && ['rascunho', 'submetida'].includes(r.estado) && (
                                                <Botao altura="pequeno" icone="fa-ban"
                                                    aTrabalhar={passo.isPending}
                                                    onClick={() => passo.mutate({ id: r.id, qual: 'cancelar' })}
                                                    aria-label={t('Cancelar requisição')} />
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

            {/* ─── O formulário ───────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar requisição') : t('Nova requisição')}
                subtitulo={t('Pede-se o que se precisa, mesmo que ainda não esteja no catálogo')}
                icone="fa-clipboard-list"
                cor="bom"
                largura="xl"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            disabled={!formulario?.linhas.length}
                            onClick={() => formulario && guardar.mutate({
                                warehouse_id: formulario.warehouse_id ? Number(formulario.warehouse_id) : null,
                                necessaria_em: formulario.necessaria_em || null,
                                justificacao: formulario.justificacao || null,
                                linhas: formulario.linhas.map((l) => ({
                                    ...l,
                                    quantidade: Number(l.quantidade || 0),
                                    custo_estimado: l.custo_estimado === '' ? null : Number(l.custo_estimado),
                                })),
                            })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id}
                                ajuda={t('Onde a mercadoria vai entrar.')}>
                                <select value={formulario.warehouse_id}
                                    onChange={(e) => porFormulario({ ...formulario, warehouse_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Sem armazém')}</option>
                                    {o.armazens.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Precisa em')} erro={erros.necessaria_em}>
                                <input type="date" value={formulario.necessaria_em}
                                    onChange={(e) => porFormulario({ ...formulario, necessaria_em: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Justificação')} erro={erros.justificacao} className="sm:col-span-1">
                                <input type="text" value={formulario.justificacao}
                                    onChange={(e) => porFormulario({ ...formulario, justificacao: e.target.value })}
                                    placeholder={t('Para quê?')} className={entrada} />
                            </Campo>
                        </div>

                        {/* A procura do catálogo, e a linha livre ao lado. */}
                        <div className="flex flex-wrap items-end gap-3">
                            <Campo etiqueta={t('Procurar no catálogo')} className="min-w-[14rem] flex-1"
                                ajuda={t('A partir de duas letras.')}>
                                <div className="relative">
                                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                    <input type="search" value={procuraArtigo}
                                        onChange={(e) => porProcuraArtigo(e.target.value)}
                                        placeholder={t('Nome ou código do artigo…')} className={cls(entrada, 'pl-9')} />
                                </div>
                            </Campo>

                            {/*
                              * A LINHA LIVRE é o caso mais comum: pedir algo que
                              * ainda não é artigo do catálogo. Obrigar a criar o
                              * artigo primeiro era obrigar a inventar dados.
                              */}
                            <Botao icone="fa-pen" onClick={() => porFormulario({
                                ...formulario,
                                linhas: [...formulario.linhas, {
                                    product_id: null, descricao: '', quantidade: 1,
                                    custo_estimado: '', unidade: null, notas: null,
                                }],
                            })}>
                                {t('Linha livre')}
                            </Botao>
                        </div>

                        {(artigos.data?.data.length ?? 0) > 0 && (
                            <ul className="flex flex-wrap gap-2">
                                {artigos.data!.data.map((a) => (
                                    <li key={a.id}>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                porFormulario({
                                                    ...formulario,
                                                    linhas: [...formulario.linhas, {
                                                        product_id: a.id, descricao: a.nome, quantidade: 1,
                                                        custo_estimado: a.custo || '', unidade: a.unidade, notas: null,
                                                    }],
                                                });
                                                porProcuraArtigo('');
                                            }}
                                            className={cls('border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700', RAIO, FOCO)}
                                        >
                                            <i className="fas fa-plus mr-1.5" aria-hidden="true" />
                                            {a.nome}
                                            {a.codigo && <span className="ml-1.5 font-mono text-slate-400">{a.codigo}</span>}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {formulario.linhas.length === 0 ? (
                            <p className={cls('border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400', RAIO)}>
                                {t('Uma requisição sem linhas não pede nada.')}
                            </p>
                        ) : (
                            <div className={cls('overflow-x-auto', CARTAO)}>
                                <table className="w-full min-w-[44rem] text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2 text-left">{t('O quê')}</th>
                                            <th className="px-3 py-2 text-right">{t('Quantidade')}</th>
                                            <th className="px-3 py-2 text-left">{t('Unidade')}</th>
                                            <th className="px-3 py-2 text-right">{t('Custo estimado')}</th>
                                            <th className="px-3 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {formulario.linhas.map((l, i) => (
                                            <tr key={i}>
                                                <td className="px-3 py-2">
                                                    <input type="text" value={l.descricao}
                                                        onChange={(e) => mexerNaLinha(i, 'descricao', e.target.value)}
                                                        className={entrada} />
                                                </td>
                                                <td className="px-3 py-2 w-28">
                                                    <input type="number" step="0.01" min="0.0001" value={l.quantidade}
                                                        onChange={(e) => mexerNaLinha(i, 'quantidade', e.target.value)}
                                                        className={cls(entrada, 'text-right tabular-nums')} />
                                                </td>
                                                <td className="px-3 py-2 w-24">
                                                    <input type="text" value={l.unidade ?? ''}
                                                        onChange={(e) => mexerNaLinha(i, 'unidade', e.target.value)}
                                                        className={entrada} />
                                                </td>
                                                <td className="px-3 py-2 w-32">
                                                    <input type="number" step="0.01" min="0" value={l.custo_estimado}
                                                        onChange={(e) => mexerNaLinha(i, 'custo_estimado', e.target.value)}
                                                        className={cls(entrada, 'text-right tabular-nums')} />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                                        onClick={() => porFormulario({
                                                            ...formulario,
                                                            linhas: formulario.linhas.filter((_, j) => j !== i),
                                                        })}
                                                        aria-label={t('Tirar a linha')} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            <Ficha id={aVer} aoFechar={() => porAVer(null)} />

            <Modal
                aberto={aRejeitar !== null}
                aoFechar={() => porARejeitar(null)}
                titulo={t('Recusar a requisição')}
                subtitulo={aRejeitar?.numero}
                icone="fa-xmark"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porARejeitar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-check" aTrabalhar={rejeitar.isPending}
                            disabled={motivo.trim().length < 3} onClick={() => rejeitar.mutate()}>
                            {t('Recusar')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-4">
                    <AvisoDeErro erro={rejeitar.error} />

                    <Campo etiqueta={t('Motivo')} obrigatorio
                        ajuda={t('Quem pediu tem de saber porquê — senão volta a pedir o mesmo na semana seguinte.')}>
                        <textarea rows={3} value={motivo} onChange={(e) => porMotivo(e.target.value)}
                            className={entrada} />
                    </Campo>
                </div>
            </Modal>
        </div>
    );

    function abrirEdicao(id: number) {
        void compras.requisicoes.ficha(id).then((f) => {
            porAEditar(id);
            porFormulario({
                warehouse_id: f.data.warehouse_id ? String(f.data.warehouse_id) : '',
                necessaria_em: f.data.necessaria_em ?? '',
                justificacao: f.data.justificacao ?? '',
                linhas: f.itens.map((i) => ({
                    product_id: i.product_id,
                    descricao: i.descricao,
                    quantidade: i.quantidade,
                    custo_estimado: i.custo_estimado ?? '',
                    unidade: i.unidade,
                    notas: i.notas,
                })),
            });
        }).catch(porErro);
    }
}

/* ─── A ficha ─────────────────────────────────────────────────────────── */

function Ficha({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const ficha = useQuery({
        queryKey: ['compras', 'requisicoes', 'ficha', id],
        queryFn: () => compras.requisicoes.ficha(id as number),
        enabled: id !== null,
    });

    const r = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={r?.numero ?? t('Requisição')}
            subtitulo={r?.justificacao ?? undefined}
            icone="fa-clipboard-list"
            cor="bom"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {ficha.isPending ? (
                <Carregando linhas={5} />
            ) : ficha.isError ? (
                <AvisoDeErro erro={ficha.error} />
            ) : r ? (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Etiqueta cor={COR_DO_ESTADO[r.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[r.estado]}>
                            {r.estado_rotulo}
                        </Etiqueta>
                        {r.armazem && <Etiqueta cor="neutra" icone="fa-warehouse">{r.armazem}</Etiqueta>}
                    </div>

                    <dl className="grid gap-3 sm:grid-cols-2">
                        <Linha rotulo={t('Quem pediu')} valor={r.autor} />
                        <Linha rotulo={t('Quem decidiu')} valor={r.decisor} nota={r.decidida_em} />
                        <Linha rotulo={t('Precisa em')} valor={r.necessaria_em} />
                        <Linha rotulo={t('Criada em')} valor={r.criada_em} />
                    </dl>

                    {r.motivo_recusa && (
                        <p className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                            <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                            {r.motivo_recusa}
                        </p>
                    )}

                    <div className={cls('overflow-x-auto', CARTAO)}>
                        <table className="w-full min-w-[36rem] text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left">{t('O quê')}</th>
                                    <th className="px-3 py-2 text-right">{t('Pedido')}</th>
                                    <th className="px-3 py-2 text-right">{t('Encomendado')}</th>
                                    <th className="px-3 py-2 text-right">{t('Por encomendar')}</th>
                                    <th className="px-3 py-2 text-right">{t('Custo estimado')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {ficha.data.itens.map((i) => (
                                    <tr key={i.id}>
                                        <td className="px-3 py-2">
                                            <p className="font-medium text-slate-800">{i.descricao}</p>
                                            {i.codigo && <p className="font-mono text-xs text-slate-400">{i.codigo}</p>}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {i.quantidade} {i.unidade}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-500">{i.encomendada}</td>
                                        <td className={cls('px-3 py-2 text-right font-semibold tabular-nums',
                                            i.por_encomendar > 0 ? 'text-amber-600' : 'text-slate-400')}>
                                            {i.por_encomendar}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-600">
                                            {i.custo_estimado !== null ? kz(i.custo_estimado) : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {ficha.data.encomendas.length > 0 && (
                        <section>
                            <h3 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-truck mr-2 text-emerald-600" aria-hidden="true" />
                                {t('Encomendas que daqui saíram')}
                            </h3>
                            <ul className="flex flex-wrap gap-2">
                                {ficha.data.encomendas.map((e) => (
                                    <li key={e.id}>
                                        <a href={`/compras/encomendas?encomenda=${e.id}`}
                                            className={cls('inline-flex items-center gap-2 border border-slate-200 bg-white px-3 py-1.5 font-mono text-xs text-emerald-700 transition hover:border-emerald-300', RAIO, FOCO)}>
                                            {e.numero}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
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
