import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { compras, type Encomenda, type ItemDaEncomenda } from '@/api/compras';
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
 * AS ENCOMENDAS AO FORNECEDOR — enviar, receber e facturar.
 *
 * A RECEPÇÃO É O MOMENTO EM QUE O STOCK ENTRA, e é por isso que tem uma janela
 * própria que mostra O QUE FALTA de cada linha, já preenchido: quem recebe
 * confere e corrige, em vez de escrever tudo de novo. E tem permissão própria,
 * como a contagem física.
 *
 * SÓ SE FACTURA O QUE JÁ CHEGOU, uma vez só. A encomenda guarda a factura que
 * dela nasceu, e é essa marca que impede pagar duas vezes o mesmo material.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    rascunho: 'neutra',
    enviada: 'primaria',
    confirmada: 'primaria',
    parcial: 'aviso',
    recebida: 'bom',
    cancelada: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    rascunho: 'fa-pen-ruler',
    enviada: 'fa-paper-plane',
    confirmada: 'fa-handshake',
    parcial: 'fa-box-open',
    recebida: 'fa-boxes-stacked',
    cancelada: 'fa-ban',
};

type Linha = {
    product_id: number | null;
    descricao: string;
    quantidade: number | string;
    preco_unitario: number | string;
    desconto_percent: number | string;
    unidade: string | null;
};

const VAZIO = {
    supplier_id: '', warehouse_id: '', requisicao_id: '',
    data_encomenda: '', entrega_prevista: '', notas: '', linhas: [] as Linha[],
};

export default function Encomendas({ encomenda }: { encomenda?: number }) {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{
        procura?: string; estado?: string; fornecedor?: number | '';
        por_pagina?: number; page?: number;
    }>({ estado: 'todos', por_pagina: 15, page: 1 });

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<number | null>(encomenda ?? null);
    const [aReceber, porAReceber] = useState<number | null>(null);
    const [procuraArtigo, porProcuraArtigo] = useState('');
    const [daRequisicao, porDaRequisicao] = useState<{ requisicao_id: string; supplier_id: string } | null>(null);

    const opcoes = useQuery({
        queryKey: ['compras', 'encomendas', 'opcoes'],
        queryFn: () => compras.encomendas.opcoes(),
    });

    const lista = useQuery({
        queryKey: ['compras', 'encomendas', filtros],
        queryFn: () => compras.encomendas.lista(filtros),
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
        mutationFn: (d: Record<string, unknown>) => compras.encomendas.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const passo = useMutation({
        mutationFn: ({ id, qual }: { id: number; qual: 'enviar' | 'confirmar' | 'facturar' | 'cancelar' }) =>
            compras.encomendas[qual](id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const puxar = useMutation({
        mutationFn: () => compras.encomendas.daRequisicao({
            requisicao_id: Number(daRequisicao!.requisicao_id),
            supplier_id: Number(daRequisicao!.supplier_id),
        }),
        onSuccess: (r) => { feito(r.message); porDaRequisicao(null); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const pode = o.permissoes.pode_gerir;
    const recebe = o.permissoes.pode_receber;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const abrirNova = () => {
        porAEditar(null);
        porFormulario({
            ...VAZIO,
            warehouse_id: o.armazens.find((a) => a.padrao)?.valor ?? o.armazens[0]?.valor ?? '',
            data_encomenda: new Date().toISOString().slice(0, 10),
            linhas: [],
        });
        porProcuraArtigo('');
    };

    const mexerNaLinha = (i: number, campo: keyof Linha, valor: unknown) => {
        if (!formulario) return;

        const linhas = [...formulario.linhas];

        linhas[i] = { ...linhas[i]!, [campo]: valor } as Linha;
        porFormulario({ ...formulario, linhas });
    };

    const totalDoFormulario = (formulario?.linhas ?? []).reduce((soma, l) => {
        const bruto = Number(l.quantidade || 0) * Number(l.preco_unitario || 0);

        return soma + bruto * (1 - Number(l.desconto_percent || 0) / 100);
    }, 0);

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Encomendas de Compra')}
                subtitulo={t('Enviar, receber e facturar — e o stock entra na recepção')}
                icone="fa-truck"
                cor="bom"
                accoes={
                    <>
                        {pode && (
                            <>
                                <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNova}>
                                    <i className="fas fa-plus" aria-hidden="true" />
                                    {t('Nova encomenda')}
                                </button>
                                {o.requisicoes.length > 0 && (
                                    <button type="button" className={ACCAO_DA_FAIXA}
                                        onClick={() => porDaRequisicao({ requisicao_id: '', supplier_id: '' })}>
                                        <i className="fas fa-clipboard-check" aria-hidden="true" />
                                        {t('De uma requisição')}
                                    </button>
                                )}
                            </>
                        )}
                        <a href="/compras/requisicoes" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-clipboard-list" aria-hidden="true" />
                            {t('Requisições')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <div className="flex flex-wrap items-center gap-2">
                        <EstadoNaFaixa icone="fa-truck">
                            {t(':n em curso', { n: numero(resumo.abertas) })}
                        </EstadoNaFaixa>
                        {resumo.por_facturar > 0 && (
                            <EstadoNaFaixa icone="fa-file-invoice">
                                {t(':n por facturar', { n: numero(resumo.por_facturar) })}
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
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero aspecto="claro" rotulo={t('Em curso')} valor={numero(resumo.abertas)}
                        icone="fa-truck" tom="indigo" />
                    <CartaoNumero aspecto="claro" rotulo={t('Atrasadas')} valor={numero(resumo.atrasadas)}
                        icone="fa-triangle-exclamation" tom={resumo.atrasadas > 0 ? 'vermelho' : 'verde'} />
                    <CartaoNumero aspecto="claro" rotulo={t('Por facturar')} valor={numero(resumo.por_facturar)}
                        icone="fa-file-invoice" tom={resumo.por_facturar > 0 ? 'ambar' : 'teal'}
                        nota={t('Chegou e ainda não há factura')} />
                    <CartaoNumero aspecto="claro" rotulo={t('Valor em curso')} valor={kz(resumo.valor_aberto)} sufixo="Kz"
                        icone="fa-sack-dollar" tom="verde" />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Número, fornecedor ou artigo…')} className={cls(entrada, 'pl-9')} />
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

                <Campo etiqueta={t('Fornecedor')} className="w-52">
                    <select value={filtros.fornecedor ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, fornecedor: e.target.value ? Number(e.target.value) : '', page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {o.fornecedores.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-truck"
                    titulo={t('Nenhuma encomenda')}
                    frase={t('Uma encomenda é o que se manda ao fornecedor — e é na recepção dela que o stock entra.')}
                    accao={pode ? (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>
                            {t('Nova encomenda')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <div className={cls('overflow-x-auto', CARTAO)}>
                    <table className="w-full min-w-[58rem] text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left">{t('Número')}</th>
                                <th className="px-4 py-3 text-left">{t('Fornecedor')}</th>
                                <th className="px-4 py-3 text-left">{t('Entrega prevista')}</th>
                                <th className="px-4 py-3 text-left">{t('Recebido')}</th>
                                <th className="px-4 py-3 text-right">{t('Total')}</th>
                                <th className="px-4 py-3 text-left">{t('Estado')}</th>
                                <th className="px-4 py-3 text-left">{t('Factura')}</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lista.data.data.map((e, i) => (
                                <tr key={e.id} style={cascata(i)} className="entra transition hover:bg-slate-50/70">
                                    <td className="px-4 py-3">
                                        <p className="font-mono font-semibold text-slate-800">{e.numero}</p>
                                        <p className="text-xs text-slate-500">{e.armazem ?? '—'}</p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{e.fornecedor ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span className={cls('tabular-nums', e.atrasada ? 'font-bold text-red-600' : 'text-slate-600')}>
                                            {e.entrega_prevista ?? '—'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {/* A BARRA DO RECEBIDO: diz de relance se
                                            a encomenda está fechada, em parte, ou
                                            ainda por sair de casa. */}
                                        <div className="flex items-center gap-2">
                                            <div className="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className={cls('h-full rounded-full transition-all duration-500',
                                                        e.percentagem_recebida >= 100 ? 'bg-emerald-500'
                                                            : e.percentagem_recebida > 0 ? 'bg-amber-500' : 'bg-slate-300')}
                                                    style={{ width: `${Math.min(100, Math.max(2, e.percentagem_recebida))}%` }}
                                                />
                                            </div>
                                            <span className="text-xs tabular-nums text-slate-500">
                                                {e.percentagem_recebida}%
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                        {kz(e.total)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Etiqueta cor={COR_DO_ESTADO[e.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[e.estado]}>
                                            {e.estado_rotulo}
                                        </Etiqueta>
                                    </td>
                                    <td className="px-4 py-3">
                                        {e.factura ? (
                                            <a href={`/invoicing/purchase-invoices/${e.factura.id}`}
                                                className={cls('font-mono text-xs text-emerald-700 underline-offset-2 hover:underline', FOCO)}>
                                                {e.factura.numero}
                                            </a>
                                        ) : e.pode_facturar ? (
                                            <Etiqueta cor="aviso" icone="fa-hourglass">{t('Por facturar')}</Etiqueta>
                                        ) : (
                                            <span className="text-slate-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1.5">
                                            <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(e.id)}
                                                aria-label={t('Ver a encomenda')} />

                                            {pode && e.estado === 'rascunho' && (
                                                <>
                                                    <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-paper-plane"
                                                        aTrabalhar={passo.isPending}
                                                        onClick={() => passo.mutate({ id: e.id, qual: 'enviar' })}>
                                                        {t('Enviar')}
                                                    </Botao>
                                                    <Botao altura="pequeno" icone="fa-pen"
                                                        onClick={() => abrirEdicao(e.id)}
                                                        aria-label={t('Editar encomenda')} />
                                                </>
                                            )}

                                            {pode && e.estado === 'enviada' && (
                                                <Botao altura="pequeno" icone="fa-handshake"
                                                    aTrabalhar={passo.isPending}
                                                    onClick={() => passo.mutate({ id: e.id, qual: 'confirmar' })}>
                                                    {t('Confirmar')}
                                                </Botao>
                                            )}

                                            {/*
                                              * RECEBER É A ENTRADA DE STOCK, e tem
                                              * permissão própria: a partir daqui há
                                              * mercadoria na casa e dinheiro a dever.
                                              */}
                                            {recebe && e.pode_receber && (
                                                <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-box-open"
                                                    onClick={() => porAReceber(e.id)}>
                                                    {t('Receber')}
                                                </Botao>
                                            )}

                                            {pode && e.pode_facturar && (
                                                <Botao altura="pequeno" cor="primaria" icone="fa-file-invoice"
                                                    aTrabalhar={passo.isPending}
                                                    onClick={() => passo.mutate({ id: e.id, qual: 'facturar' })}>
                                                    {t('Facturar')}
                                                </Botao>
                                            )}

                                            {pode && ['rascunho', 'enviada', 'confirmada'].includes(e.estado) && (
                                                <Botao altura="pequeno" cor="perigo" icone="fa-ban"
                                                    aTrabalhar={passo.isPending}
                                                    onClick={() => passo.mutate({ id: e.id, qual: 'cancelar' })}
                                                    aria-label={t('Cancelar encomenda')} />
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
                titulo={aEditar ? t('Editar encomenda') : t('Nova encomenda')}
                subtitulo={t('O armazém é onde a mercadoria vai entrar')}
                icone="fa-truck"
                cor="bom"
                largura="xl"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            disabled={!formulario?.linhas.length}
                            onClick={() => formulario && guardar.mutate({
                                supplier_id: formulario.supplier_id ? Number(formulario.supplier_id) : null,
                                warehouse_id: formulario.warehouse_id ? Number(formulario.warehouse_id) : null,
                                requisicao_id: formulario.requisicao_id ? Number(formulario.requisicao_id) : null,
                                data_encomenda: formulario.data_encomenda || null,
                                entrega_prevista: formulario.entrega_prevista || null,
                                notas: formulario.notas || null,
                                linhas: formulario.linhas.map((l) => ({
                                    ...l,
                                    quantidade: Number(l.quantidade || 0),
                                    preco_unitario: Number(l.preco_unitario || 0),
                                    desconto_percent: Number(l.desconto_percent || 0),
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

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Fornecedor')} obrigatorio erro={erros.supplier_id}>
                                <select value={formulario.supplier_id}
                                    onChange={(e) => porFormulario({ ...formulario, supplier_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Escolher…')}</option>
                                    {o.fornecedores.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id}
                                ajuda={t('É aqui que a mercadoria entra na recepção.')}>
                                <select value={formulario.warehouse_id}
                                    onChange={(e) => porFormulario({ ...formulario, warehouse_id: e.target.value })}
                                    className={entrada}>
                                    <option value="">{t('Sem armazém')}</option>
                                    {o.armazens.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Data da encomenda')} erro={erros.data_encomenda}>
                                <input type="date" value={formulario.data_encomenda}
                                    onChange={(e) => porFormulario({ ...formulario, data_encomenda: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Entrega prevista')} erro={erros.entrega_prevista}
                                ajuda={t('Passada esta data e por receber, entra nos atrasos do painel.')}>
                                <input type="date" value={formulario.entrega_prevista}
                                    onChange={(e) => porFormulario({ ...formulario, entrega_prevista: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

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

                            <Botao icone="fa-pen" onClick={() => porFormulario({
                                ...formulario,
                                linhas: [...formulario.linhas, {
                                    product_id: null, descricao: '', quantidade: 1,
                                    preco_unitario: 0, desconto_percent: 0, unidade: null,
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
                                                        preco_unitario: a.custo, desconto_percent: 0, unidade: a.unidade,
                                                    }],
                                                });
                                                porProcuraArtigo('');
                                            }}
                                            className={cls('border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700', RAIO, FOCO)}
                                        >
                                            <i className="fas fa-plus mr-1.5" aria-hidden="true" />
                                            {a.nome}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {formulario.linhas.length === 0 ? (
                            <p className={cls('border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400', RAIO)}>
                                {t('Uma encomenda sem linhas não encomenda nada.')}
                            </p>
                        ) : (
                            <div className={cls('overflow-x-auto', CARTAO)}>
                                <table className="w-full min-w-[48rem] text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2 text-left">{t('O quê')}</th>
                                            <th className="px-3 py-2 text-right">{t('Quantidade')}</th>
                                            <th className="px-3 py-2 text-right">{t('Preço')}</th>
                                            <th className="px-3 py-2 text-right">{t('Desc. %')}</th>
                                            <th className="px-3 py-2 text-right">{t('Total')}</th>
                                            <th className="px-3 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {formulario.linhas.map((l, i) => {
                                            const total = Number(l.quantidade || 0) * Number(l.preco_unitario || 0)
                                                * (1 - Number(l.desconto_percent || 0) / 100);

                                            return (
                                                <tr key={i}>
                                                    <td className="px-3 py-2">
                                                        <input type="text" value={l.descricao}
                                                            onChange={(e) => mexerNaLinha(i, 'descricao', e.target.value)}
                                                            className={entrada} />
                                                    </td>
                                                    <td className="w-28 px-3 py-2">
                                                        <input type="number" step="0.01" min="0.0001" value={l.quantidade}
                                                            onChange={(e) => mexerNaLinha(i, 'quantidade', e.target.value)}
                                                            className={cls(entrada, 'text-right tabular-nums')} />
                                                    </td>
                                                    <td className="w-32 px-3 py-2">
                                                        <input type="number" step="0.01" min="0" value={l.preco_unitario}
                                                            onChange={(e) => mexerNaLinha(i, 'preco_unitario', e.target.value)}
                                                            className={cls(entrada, 'text-right tabular-nums')} />
                                                    </td>
                                                    <td className="w-24 px-3 py-2">
                                                        <input type="number" step="0.01" min="0" max="100" value={l.desconto_percent}
                                                            onChange={(e) => mexerNaLinha(i, 'desconto_percent', e.target.value)}
                                                            className={cls(entrada, 'text-right tabular-nums')} />
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">
                                                        {kz(total)}
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
                                            );
                                        })}
                                    </tbody>
                                    <tfoot className="bg-slate-50">
                                        <tr>
                                            <td colSpan={4} className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                                {t('Total')}
                                            </td>
                                            <td className="px-3 py-2 text-right text-base font-bold tabular-nums text-slate-900">
                                                {kz(totalDoFormulario)}
                                            </td>
                                            <td />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}

                        <Campo etiqueta={t('Notas')} erro={erros.notas}>
                            <textarea rows={2} value={formulario.notas}
                                onChange={(e) => porFormulario({ ...formulario, notas: e.target.value })}
                                className={entrada} />
                        </Campo>
                    </div>
                )}
            </Modal>

            {/* ─── Da requisição ──────────────────────────────────────── */}

            <Modal
                aberto={daRequisicao !== null}
                aoFechar={() => porDaRequisicao(null)}
                titulo={t('Encomenda de uma requisição')}
                subtitulo={t('As linhas por encomendar passam para a encomenda')}
                icone="fa-clipboard-check"
                cor="bom"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porDaRequisicao(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={puxar.isPending}
                            disabled={!daRequisicao?.requisicao_id || !daRequisicao?.supplier_id}
                            onClick={() => puxar.mutate()}>
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {daRequisicao && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={puxar.error} />

                        <Campo etiqueta={t('Requisição')} obrigatorio>
                            <select value={daRequisicao.requisicao_id}
                                onChange={(e) => porDaRequisicao({ ...daRequisicao, requisicao_id: e.target.value })}
                                className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {o.requisicoes.map((r) => (
                                    <option key={r.valor} value={r.valor}>
                                        {r.rotulo} ({t(':n linhas', { n: String(r.linhas) })})
                                    </option>
                                ))}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Fornecedor')} obrigatorio
                            ajuda={t('Uma requisição não tem fornecedor — a encomenda tem.')}>
                            <select value={daRequisicao.supplier_id}
                                onChange={(e) => porDaRequisicao({ ...daRequisicao, supplier_id: e.target.value })}
                                className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {o.fornecedores.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>
                )}
            </Modal>

            <Ficha id={aVer} aoFechar={() => porAVer(null)} />

            <Recepcao id={aReceber} aoFechar={() => porAReceber(null)} aoFeito={(m) => { feito(m); porAReceber(null); }} />
        </div>
    );

    function abrirEdicao(id: number) {
        void compras.encomendas.ficha(id).then((f) => {
            porAEditar(id);
            porFormulario({
                supplier_id: f.data.supplier_id ? String(f.data.supplier_id) : '',
                warehouse_id: f.data.warehouse_id ? String(f.data.warehouse_id) : '',
                requisicao_id: f.data.requisicao_id ? String(f.data.requisicao_id) : '',
                data_encomenda: f.data.data ?? '',
                entrega_prevista: f.data.entrega_prevista ?? '',
                notas: f.data.notas ?? '',
                linhas: f.itens.map((i) => ({
                    product_id: i.product_id,
                    descricao: i.descricao,
                    quantidade: i.quantidade,
                    preco_unitario: i.preco_unitario,
                    desconto_percent: i.desconto_percent,
                    unidade: i.unidade,
                })),
            });
        }).catch(porErro);
    }
}

/* ─── A recepção ──────────────────────────────────────────────────────── */

/**
 * A RECEPÇÃO, com o que falta de cada linha já preenchido.
 *
 * Quem recebe confere e corrige, em vez de escrever tudo de novo. É a
 * diferença entre uma recepção que se faz com a mercadoria à frente e uma que
 * se faz de memória no fim do dia.
 */
function Recepcao({
    id, aoFechar, aoFeito,
}: {
    id: number | null;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const [quantidades, porQuantidades] = useState<Record<string, string>>({});
    const [preenchido, porPreenchido] = useState<number | null>(null);

    const recepcao = useQuery({
        queryKey: ['compras', 'encomendas', 'recepcao', id],
        queryFn: () => compras.encomendas.recepcao(id as number),
        enabled: id !== null,
    });

    // O que falta entra já escrito — mas só uma vez, para não apagar o que
    // quem está a receber corrigiu.
    if (recepcao.data && preenchido !== id) {
        porPreenchido(id);
        porQuantidades(Object.fromEntries(
            recepcao.data.itens.map((i: ItemDaEncomenda) => [String(i.id), i.por_receber ? String(i.por_receber) : '']),
        ));
    }

    const receber = useMutation({
        mutationFn: () => compras.encomendas.receber(id as number, quantidades),
        onSuccess: (r) => aoFeito(r.message),
    });

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={t('Receber mercadoria')}
            subtitulo={recepcao.data?.data.numero}
            icone="fa-box-open"
            cor="bom"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={receber.isPending}
                        onClick={() => receber.mutate()}>
                        {t('Dar entrada')}
                    </Botao>
                </>
            }
        >
            {recepcao.isPending ? (
                <Carregando linhas={5} />
            ) : recepcao.isError ? (
                <AvisoDeErro erro={recepcao.error} />
            ) : recepcao.data ? (
                <div className="space-y-4">
                    <AvisoDeErro erro={receber.error} />

                    <p className={cls('border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('O stock entra em :armazem assim que gravar. Confira com a mercadoria à frente.', {
                            armazem: recepcao.data.data.armazem ?? '—',
                        })}
                    </p>

                    <div className={cls('overflow-x-auto', CARTAO)}>
                        <table className="w-full min-w-[40rem] text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left">{t('O quê')}</th>
                                    <th className="px-3 py-2 text-right">{t('Encomendado')}</th>
                                    <th className="px-3 py-2 text-right">{t('Já recebido')}</th>
                                    <th className="px-3 py-2 text-right">{t('Falta')}</th>
                                    <th className="px-3 py-2 text-right">{t('Chegou agora')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recepcao.data.itens.map((i) => (
                                    <tr key={i.id}>
                                        <td className="px-3 py-2">
                                            <p className="font-medium text-slate-800">{i.descricao}</p>
                                            {i.codigo && <p className="font-mono text-xs text-slate-400">{i.codigo}</p>}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-600">
                                            {i.quantidade} {i.unidade}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-500">{i.recebida}</td>
                                        <td className={cls('px-3 py-2 text-right font-semibold tabular-nums',
                                            i.por_receber > 0 ? 'text-amber-600' : 'text-emerald-600')}>
                                            {i.por_receber}
                                        </td>
                                        <td className="w-32 px-3 py-2">
                                            <input
                                                type="number" step="0.01" min="0"
                                                value={quantidades[String(i.id)] ?? ''}
                                                onChange={(e) => porQuantidades({
                                                    ...quantidades, [String(i.id)]: e.target.value,
                                                })}
                                                className={cls(entrada, 'text-right tabular-nums')}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ) : null}
        </Modal>
    );
}

/* ─── A ficha ─────────────────────────────────────────────────────────── */

function Ficha({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const ficha = useQuery({
        queryKey: ['compras', 'encomendas', 'ficha', id],
        queryFn: () => compras.encomendas.ficha(id as number),
        enabled: id !== null,
    });

    const e = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={e?.numero ?? t('Encomenda')}
            subtitulo={e?.fornecedor ?? undefined}
            icone="fa-truck"
            cor="bom"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {ficha.isPending ? (
                <Carregando linhas={5} />
            ) : ficha.isError ? (
                <AvisoDeErro erro={ficha.error} />
            ) : e ? (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Etiqueta cor={COR_DO_ESTADO[e.estado] ?? 'neutra'} icone={ICONE_DO_ESTADO[e.estado]}>
                            {e.estado_rotulo}
                        </Etiqueta>
                        {e.atrasada && <Etiqueta cor="perigo" icone="fa-clock">{t('Atrasada')}</Etiqueta>}
                        {e.armazem && <Etiqueta cor="neutra" icone="fa-warehouse">{e.armazem}</Etiqueta>}
                    </div>

                    <dl className="grid gap-3 sm:grid-cols-2">
                        <Linha rotulo={t('Fornecedor')} valor={e.fornecedor}
                            nota={[e.fornecedor_telefone, e.fornecedor_email].filter(Boolean).join(' · ')} />
                        <Linha rotulo={t('Da requisição')} valor={e.requisicao} />
                        <Linha rotulo={t('Data')} valor={e.data} />
                        <Linha rotulo={t('Entrega prevista')} valor={e.entrega_prevista} />
                        <Linha rotulo={t('Criada por')} valor={e.autor} />
                        <Linha rotulo={t('Total')} valor={`${kz(e.total)} Kz`} />
                    </dl>

                    {e.notas && (
                        <p className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                            {e.notas}
                        </p>
                    )}

                    <div className={cls('overflow-x-auto', CARTAO)}>
                        <table className="w-full min-w-[40rem] text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left">{t('O quê')}</th>
                                    <th className="px-3 py-2 text-right">{t('Encomendado')}</th>
                                    <th className="px-3 py-2 text-right">{t('Recebido')}</th>
                                    <th className="px-3 py-2 text-right">{t('Preço')}</th>
                                    <th className="px-3 py-2 text-right">{t('Total')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {ficha.data.itens.map((i) => (
                                    <tr key={i.id}>
                                        <td className="px-3 py-2">
                                            <p className="font-medium text-slate-800">{i.descricao}</p>
                                            {i.codigo && <p className="font-mono text-xs text-slate-400">{i.codigo}</p>}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{i.quantidade} {i.unidade}</td>
                                        <td className={cls('px-3 py-2 text-right font-semibold tabular-nums',
                                            i.recebida >= i.quantidade ? 'text-emerald-600' : 'text-amber-600')}>
                                            {i.recebida}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-slate-600">
                                            {kz(i.preco_unitario)}
                                        </td>
                                        <td className="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">
                                            {kz(i.total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {e.factura && (
                        <p className={cls('border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800', RAIO)}>
                            <i className="fas fa-file-invoice mr-2" aria-hidden="true" />
                            {t('Facturada em :numero.', { numero: e.factura.numero })}
                        </p>
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
