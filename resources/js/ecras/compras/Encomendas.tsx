import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { compras, pagamentosAFornecedores, type Encomenda, type ItemDaEncomenda, type PassoDoRasto, type PedidoDePagamento, type Voto } from '@/api/compras';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
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
 *
 * O CIRCUITO COM SEPARAÇÃO DE FUNÇÕES (27/09/2026): a encomenda pode precisar
 * da aprovação de outras pessoas antes de ir para o fornecedor, e «Solicitar
 * pagamento» manda-a à tesouraria — é lá que o tesoureiro paga, sem mexer
 * nela. A ficha conta o rasto: cada passo, quem o deu e quando.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    rascunho: 'neutra',
    em_aprovacao: 'aviso',
    aprovada: 'bom',
    enviada: 'primaria',
    confirmada: 'primaria',
    parcial: 'aviso',
    recebida: 'bom',
    cancelada: 'perigo',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    rascunho: 'fa-pen-ruler',
    em_aprovacao: 'fa-user-clock',
    aprovada: 'fa-circle-check',
    enviada: 'fa-paper-plane',
    confirmada: 'fa-handshake',
    parcial: 'fa-box-open',
    recebida: 'fa-boxes-stacked',
    cancelada: 'fa-ban',
};

/** O pagamento de relance, na lista. */
const PAGAMENTO: Record<Encomenda['pagamento']['estado'], { cor: 'neutra' | 'primaria' | 'aviso' | 'bom'; icone: string; rotulo: string }> = {
    sem: { cor: 'neutra', icone: 'fa-minus', rotulo: 'Por pedir' },
    pedido: { cor: 'primaria', icone: 'fa-hourglass-half', rotulo: 'Pedido' },
    parcial: { cor: 'aviso', icone: 'fa-coins', rotulo: 'Pago em parte' },
    pago: { cor: 'bom', icone: 'fa-circle-check', rotulo: 'Pago' },
};

const COR_DO_PEDIDO: Record<PedidoDePagamento['estado'], 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    em_aprovacao: 'aviso',
    por_pagar: 'primaria',
    pago: 'bom',
    recusado: 'perigo',
    cancelado: 'neutra',
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

    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aVer, porAVer] = useState<number | null>(encomenda ?? null);
    const [aReceber, porAReceber] = useState<number | null>(null);
    const [procuraArtigo, porProcuraArtigo] = useState('');
    const [daRequisicao, porDaRequisicao] = useState<{ requisicao_id: string; supplier_id: string } | null>(null);
    const [aDecidir, porADecidir] = useState<{ e: Encomenda; aprova: boolean } | null>(null);
    const [aPedirPagamento, porAPedirPagamento] = useState<Encomenda | null>(null);
    const [regrasAbertas, porRegrasAbertas] = useState(false);

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
        mutationFn: ({ id, qual }: { id: number; qual: 'enviar' | 'confirmar' | 'facturar' | 'cancelar' | 'pedirAprovacao' }) =>
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
    const exigeAprovacao = o.regras.aprovacoes_encomenda > 0;
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
                        <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porRegrasAbertas(true)}>
                            <i className="fas fa-sliders" aria-hidden="true" />
                            {t('Regras')}
                        </button>
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
                        {resumo.em_aprovacao > 0 && (
                            <EstadoNaFaixa icone="fa-user-clock">
                                {t(':n à espera de aprovação', { n: numero(resumo.em_aprovacao) })}
                            </EstadoNaFaixa>
                        )}
                        {resumo.pagamentos_por_pagar > 0 && (
                            <EstadoNaFaixa icone="fa-hand-holding-dollar">
                                {t(':v Kz pedidos à tesouraria', { v: kz(resumo.pagamentos_por_pagar) })}
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
                                <th className="px-4 py-3 text-left">{t('Pagamento')}</th>
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
                                        {e.estado === 'rascunho' && e.motivo_recusa && (
                                            <p className="mt-1 max-w-[16rem] text-xs text-red-600" title={e.motivo_recusa}>
                                                <i className="fas fa-rotate-left mr-1" aria-hidden="true" />
                                                {t('Recusada: :m', { m: e.motivo_recusa })}
                                            </p>
                                        )}
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
                                        {e.estado === 'em_aprovacao' && (
                                            <p className="mt-1 text-xs tabular-nums text-slate-500">
                                                {t(':s de :n aprovações', { s: String(e.aprovacao.sins), n: String(e.aprovacao.necessarias) })}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {/* O DINHEIRO de relance: por pedir, pedido à tesouraria, pago em parte, pago. */}
                                        <Etiqueta cor={PAGAMENTO[e.pagamento.estado].cor} icone={PAGAMENTO[e.pagamento.estado].icone}>
                                            {t(PAGAMENTO[e.pagamento.estado].rotulo)}
                                        </Etiqueta>
                                        {e.pagamento.pago > 0 && e.pagamento.estado !== 'pago' && (
                                            <p className="mt-1 text-xs tabular-nums text-slate-500">
                                                {t(':p de :t Kz', { p: kz(e.pagamento.pago), t: kz(e.pagamento.a_pagar) })}
                                            </p>
                                        )}
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
                                                    {/*
                                                      * COM APROVAÇÃO EXIGIDA, o rascunho vai a
                                                      * aprovação; sem ela, sai direito para o
                                                      * fornecedor, como sempre.
                                                      */}
                                                    {exigeAprovacao ? (
                                                        <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-user-check"
                                                            aTrabalhar={passo.isPending}
                                                            onClick={() => passo.mutate({ id: e.id, qual: 'pedirAprovacao' })}>
                                                            {t('Pedir aprovação')}
                                                        </Botao>
                                                    ) : (
                                                        <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-paper-plane"
                                                            aTrabalhar={passo.isPending}
                                                            onClick={() => passo.mutate({ id: e.id, qual: 'enviar' })}>
                                                            {t('Enviar')}
                                                        </Botao>
                                                    )}
                                                    <Botao altura="pequeno" icone="fa-pen"
                                                        onClick={() => abrirEdicao(e.id)}
                                                        aria-label={t('Editar encomenda')} />
                                                </>
                                            )}

                                            {e.aprovacao.pode_votar && (
                                                <>
                                                    <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-check"
                                                        onClick={() => porADecidir({ e, aprova: true })}>
                                                        {t('Aprovar')}
                                                    </Botao>
                                                    <Botao altura="pequeno" cor="perigo" icone="fa-xmark"
                                                        onClick={() => porADecidir({ e, aprova: false })}
                                                        aria-label={t('Recusar a encomenda')} />
                                                </>
                                            )}

                                            {pode && e.estado === 'aprovada' && (
                                                <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-paper-plane"
                                                    aTrabalhar={passo.isPending}
                                                    onClick={() => passo.mutate({ id: e.id, qual: 'enviar' })}>
                                                    {t('Enviar')}
                                                </Botao>
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

                                            {o.permissoes.pode_pedir_pagamento && e.pagamento.pode_pedir && (
                                                <Botao altura="pequeno" icone="fa-hand-holding-dollar"
                                                    onClick={() => porAPedirPagamento(e)}>
                                                    {t('Pagamento')}
                                                </Botao>
                                            )}

                                            {o.permissoes.pode_facturar && e.pode_facturar && (
                                                <Botao altura="pequeno" cor="primaria" icone="fa-file-invoice"
                                                    aTrabalhar={passo.isPending}
                                                    onClick={() => passo.mutate({ id: e.id, qual: 'facturar' })}>
                                                    {t('Facturar')}
                                                </Botao>
                                            )}

                                            {pode && ['rascunho', 'em_aprovacao', 'aprovada', 'enviada', 'confirmada'].includes(e.estado) && (
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
                <Paginacao
                    pagina={meta.current_page}
                    ultima={meta.last_page}
                    aMudar={(p) => porFiltros({ ...filtros, page: p })}
                    total={meta.total}
                    de={meta.from}
                    ate={meta.to}
                    aCarregar={lista.isFetching}
                    emCartao
                    extra={<PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />}
                />
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

            <Ficha id={aVer} aoFechar={() => porAVer(null)} podeCancelarPedidos={pode} aoFeito={feito} />

            <Recepcao id={aReceber} aoFechar={() => porAReceber(null)} aoFeito={(m) => { feito(m); porAReceber(null); }} />

            <Decisao pedido={aDecidir} aoFechar={() => porADecidir(null)} aoFeito={(m) => { feito(m); porADecidir(null); }} />

            <PedirPagamento
                encomenda={aPedirPagamento}
                formas={o.formas}
                tesoureiros={o.tesoureiros}
                tesoureiroPadrao={o.regras.tesoureiro_id}
                comAprovacao={o.regras.aprovacoes_pagamento > 0}
                aoFechar={() => porAPedirPagamento(null)}
                aoFeito={(m) => { feito(m); porAPedirPagamento(null); }}
            />

            <Regras aberta={regrasAbertas} aoFechar={() => porRegrasAbertas(false)} aoFeito={(m) => { feito(m); porRegrasAbertas(false); }} />
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

function Ficha({ id, aoFechar, podeCancelarPedidos, aoFeito }: {
    id: number | null; aoFechar: () => void; podeCancelarPedidos: boolean; aoFeito: (m: string) => void;
}) {
    const ficha = useQuery({
        queryKey: ['compras', 'encomendas', 'ficha', id],
        queryFn: () => compras.encomendas.ficha(id as number),
        enabled: id !== null,
    });

    const cancelarPedido = useMutation({
        mutationFn: (pedido: number) => pagamentosAFornecedores.cancelar(pedido),
        onSuccess: (r) => { aoFeito(r.message); void ficha.refetch(); },
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

                    {e.motivo_recusa && e.estado === 'rascunho' && (
                        <p role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                            <i className="fas fa-rotate-left mr-2" aria-hidden="true" />
                            {t('Recusada na aprovação: :m', { m: e.motivo_recusa })}
                        </p>
                    )}

                    <AvisoDeErro erro={cancelarPedido.error} />

                    <PagamentosDaEncomenda
                        pagamentos={ficha.data.pagamentos}
                        resumo={e.pagamento}
                        podeCancelar={podeCancelarPedidos}
                        aCancelar={cancelarPedido.isPending}
                        aoCancelar={(p) => cancelarPedido.mutate(p)}
                    />

                    {ficha.data.aprovacoes.length > 0 && <Votos titulo={t('Aprovações da encomenda')} votos={ficha.data.aprovacoes} />}

                    <Rasto passos={ficha.data.rasto} />
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

/* ─── Aprovar ou recusar ──────────────────────────────────────────────── */

function Decisao({ pedido, aoFechar, aoFeito }: {
    pedido: { e: Encomenda; aprova: boolean } | null;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const [comentario, porComentario] = useState('');

    const decidir = useMutation({
        mutationFn: () => compras.encomendas.decidir(pedido!.e.id, pedido!.aprova, comentario.trim() || undefined),
        onSuccess: (r) => { porComentario(''); aoFeito(r.message); },
    });

    const aprova = pedido?.aprova ?? true;

    return (
        <Modal
            aberto={pedido !== null}
            aoFechar={aoFechar}
            titulo={aprova ? t('Aprovar a encomenda') : t('Recusar a encomenda')}
            subtitulo={pedido ? `${pedido.e.numero} · ${pedido.e.fornecedor ?? ''} · ${kz(pedido.e.total)} Kz` : undefined}
            icone={aprova ? 'fa-check' : 'fa-xmark'}
            cor={aprova ? 'bom' : 'perigo'}
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor={aprova ? 'bom' : 'perigo'} tom="solida" icone={aprova ? 'fa-check' : 'fa-xmark'}
                        aTrabalhar={decidir.isPending}
                        disabled={!aprova && comentario.trim().length < 3}
                        onClick={() => decidir.mutate()}>
                        {aprova ? t('Aprovar') : t('Recusar')}
                    </Botao>
                </>
            }
        >
            {pedido && (
                <div className="space-y-4">
                    <AvisoDeErro erro={decidir.error} />
                    {pedido.e.aprovacao.necessarias > 1 && (
                        <p className="text-sm text-slate-600">
                            {t('A empresa exige :n aprovações. Já tem :s.', { n: String(pedido.e.aprovacao.necessarias), s: String(pedido.e.aprovacao.sins) })}
                        </p>
                    )}
                    <Campo etiqueta={aprova ? t('Comentário (opcional)') : t('Motivo da recusa')} obrigatorio={!aprova}
                        ajuda={aprova ? undefined : t('Quem fez a encomenda vai lê-lo para a corrigir.')}>
                        <textarea id="decisao-comentario" rows={3} value={comentario}
                            onChange={(ev) => porComentario(ev.target.value)} className={entrada} />
                    </Campo>
                </div>
            )}
        </Modal>
    );
}

/* ─── Solicitar pagamento ─────────────────────────────────────────────── */

/**
 * «SOLICITAR PAGAMENTO»: a encomenda vai à tesouraria. Pode pedir-se em
 * parcelas (um sinal e o resto), nunca mais do que falta pedir. Quem pede
 * não paga — o pagamento é do tesoureiro, no ecrã da tesouraria.
 */
function PedirPagamento({ encomenda, formas, tesoureiros, tesoureiroPadrao, comAprovacao, aoFechar, aoFeito }: {
    encomenda: Encomenda | null;
    formas: Array<{ valor: string; rotulo: string }>;
    tesoureiros: Array<{ valor: string; rotulo: string }>;
    tesoureiroPadrao: string | null;
    comAprovacao: boolean;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const [dados, porDados] = useState({ valor: '', data_limite: '', forma: 'transfer', tesoureiro_id: '', notas: '' });
    const [preenchido, porPreenchido] = useState<number | null>(null);

    // O que falta pedir entra já escrito — uma vez, para não apagar o que se corrigiu.
    if (encomenda && preenchido !== encomenda.id) {
        porPreenchido(encomenda.id);
        porDados({
            valor: String(encomenda.pagamento.por_pedir), data_limite: '', forma: 'transfer',
            tesoureiro_id: tesoureiroPadrao ?? '', notas: '',
        });
    }

    const pedir = useMutation({
        mutationFn: () => compras.encomendas.pedirPagamento(encomenda!.id, {
            valor: Number(dados.valor || 0),
            data_limite: dados.data_limite || null,
            forma: dados.forma || null,
            tesoureiro_id: dados.tesoureiro_id ? Number(dados.tesoureiro_id) : null,
            notas: dados.notas.trim() || null,
        }),
        onSuccess: (r) => { porPreenchido(null); aoFeito(r.message); },
    });

    const erros = (pedir.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    return (
        <Modal
            aberto={encomenda !== null}
            aoFechar={() => { porPreenchido(null); aoFechar(); }}
            titulo={t('Solicitar pagamento')}
            subtitulo={encomenda ? `${encomenda.numero} · ${encomenda.fornecedor ?? ''}` : undefined}
            icone="fa-hand-holding-dollar"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={() => { porPreenchido(null); aoFechar(); }}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={pedir.isPending}
                        disabled={Number(dados.valor || 0) <= 0}
                        onClick={() => pedir.mutate()}>
                        {comAprovacao ? t('Pedir (vai a aprovação)') : t('Enviar à tesouraria')}
                    </Botao>
                </>
            }
        >
            {encomenda && (
                <div className="space-y-4">
                    <AvisoDeErro erro={pedir.error} />

                    <dl className={cls('grid grid-cols-3 gap-3 border border-slate-200 bg-slate-50 px-4 py-3', RAIO)}>
                        <Linha rotulo={t('A pagar')} valor={`${kz(encomenda.pagamento.a_pagar)} Kz`} />
                        <Linha rotulo={t('Já pedido')} valor={`${kz(encomenda.pagamento.pedido)} Kz`} />
                        <Linha rotulo={t('Já pago')} valor={`${kz(encomenda.pagamento.pago)} Kz`} />
                    </dl>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Valor a pagar')} obrigatorio erro={erros.valor}
                            ajuda={t('Até :v Kz. Pode pedir em parcelas.', { v: kz(encomenda.pagamento.por_pedir) })}>
                            <input id="pp-valor" type="number" step="0.01" min="0.01" max={encomenda.pagamento.por_pedir}
                                value={dados.valor} onChange={(ev) => porDados({ ...dados, valor: ev.target.value })}
                                className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta={t('Pagar até')} erro={erros.data_limite} ajuda={t('O prazo combinado com o fornecedor.')}>
                            <input id="pp-data" type="date" value={dados.data_limite}
                                onChange={(ev) => porDados({ ...dados, data_limite: ev.target.value })} className={entrada} />
                        </Campo>
                        <Campo etiqueta={t('Forma sugerida')} erro={erros.forma} ajuda={t('A tesouraria pode pagar por outra.')}>
                            <select id="pp-forma" value={dados.forma} onChange={(ev) => porDados({ ...dados, forma: ev.target.value })} className={entrada}>
                                {formas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Tesoureiro')} erro={erros.tesoureiro_id}
                            ajuda={t('Quem recebe o aviso. Sem ninguém, avisa quem pode pagar.')}>
                            <select id="pp-tesoureiro" value={dados.tesoureiro_id}
                                onChange={(ev) => porDados({ ...dados, tesoureiro_id: ev.target.value })} className={entrada}>
                                <option value="">{t('Qualquer tesoureiro')}</option>
                                {tesoureiros.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Notas para a tesouraria')} erro={erros.notas}>
                        <textarea id="pp-notas" rows={2} value={dados.notas} placeholder={t('IBAN do fornecedor, factura pró-forma, condições…')}
                            onChange={(ev) => porDados({ ...dados, notas: ev.target.value })} className={entrada} />
                    </Campo>
                </div>
            )}
        </Modal>
    );
}

/* ─── Os pagamentos, os votos e o rasto, na ficha ─────────────────────── */

function PagamentosDaEncomenda({ pagamentos, resumo, podeCancelar, aCancelar, aoCancelar }: {
    pagamentos: PedidoDePagamento[];
    resumo: Encomenda['pagamento'];
    podeCancelar: boolean;
    aCancelar: boolean;
    aoCancelar: (id: number) => void;
}) {
    return (
        <section aria-labelledby="ficha-pagamentos">
            <h3 id="ficha-pagamentos" className="mb-2 flex flex-wrap items-baseline justify-between gap-2 text-sm font-bold text-slate-800">
                <span><i className="fas fa-hand-holding-dollar mr-2 text-emerald-600" aria-hidden="true" />{t('Pagamentos')}</span>
                <span className="text-xs font-medium tabular-nums text-slate-500">
                    {t('Pago :p de :t Kz', { p: kz(resumo.pago), t: kz(resumo.a_pagar) })}
                </span>
            </h3>

            {pagamentos.length === 0 ? (
                <p className={cls('border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-400', RAIO)}>
                    {t('Ainda não foi pedido nenhum pagamento à tesouraria.')}
                </p>
            ) : (
                <ul className="space-y-2">
                    {pagamentos.map((p) => (
                        <li key={p.id} className={cls('border border-slate-200 px-4 py-3 text-sm', RAIO)}>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="font-mono font-semibold text-slate-800">{p.numero}</p>
                                <span className="font-semibold tabular-nums text-slate-900">{kz(p.valor)} Kz</span>
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                <Etiqueta cor={COR_DO_PEDIDO[p.estado]}>{p.estado_rotulo}</Etiqueta>
                                {p.aprovacao && (
                                    <span className="text-xs tabular-nums text-slate-500">
                                        {t(':s de :n aprovações', { s: String(p.aprovacao.sins), n: String(p.aprovacao.necessarias) })}
                                    </span>
                                )}
                                {p.atrasado && <Etiqueta cor="perigo" icone="fa-clock">{t('Prazo passado')}</Etiqueta>}
                            </div>
                            <p className="mt-1 text-xs text-slate-500">
                                {t('Pedido por :q em :d', { q: p.pedido_por ?? '—', d: p.pedido_em ?? '—' })}
                                {p.data_limite && ` · ${t('pagar até :d', { d: p.data_limite })}`}
                                {p.tesoureiro && ` · ${t('para :q', { q: p.tesoureiro })}`}
                            </p>
                            {p.estado === 'pago' && (
                                <p className="mt-1 text-xs text-emerald-700">
                                    <i className="fas fa-circle-check mr-1" aria-hidden="true" />
                                    {t('Pago por :q em :d', { q: p.pago_por ?? '—', d: p.pago_em ?? '—' })}
                                    {p.recibo && ` · ${t('recibo :r', { r: p.recibo })}`}
                                </p>
                            )}
                            {p.motivo_recusa && (
                                <p className="mt-1 text-xs text-red-600">{t('Devolvido: :m', { m: p.motivo_recusa })}</p>
                            )}
                            {podeCancelar && ['em_aprovacao', 'por_pagar'].includes(p.estado) && (
                                <div className="mt-2">
                                    <Botao altura="pequeno" cor="perigo" icone="fa-ban" aTrabalhar={aCancelar}
                                        onClick={() => aoCancelar(p.id)}>
                                        {t('Cancelar pedido')}
                                    </Botao>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function Votos({ titulo, votos }: { titulo: string; votos: Voto[] }) {
    return (
        <section>
            <h3 className="mb-2 text-sm font-bold text-slate-800">
                <i className="fas fa-user-check mr-2 text-emerald-600" aria-hidden="true" />{titulo}
            </h3>
            <ul className="space-y-1.5 text-sm">
                {votos.map((v, i) => (
                    <li key={i} className="flex flex-wrap items-baseline gap-2">
                        <Etiqueta cor={v.decisao === 'aprovado' ? 'bom' : 'perigo'} icone={v.decisao === 'aprovado' ? 'fa-check' : 'fa-xmark'}>
                            {v.decisao === 'aprovado' ? t('Aprovou') : t('Recusou')}
                        </Etiqueta>
                        <span className="font-medium text-slate-800">{v.quem ?? '—'}</span>
                        <span className="text-xs tabular-nums text-slate-500">{v.quando}</span>
                        {v.comentario && <span className="w-full text-xs text-slate-600">«{v.comentario}»</span>}
                    </li>
                ))}
            </ul>
        </section>
    );
}

const ICONE_DO_PASSO: Record<PassoDoRasto['tipo'], string> = {
    requisicao: 'fa-clipboard-list',
    encomenda: 'fa-truck',
    aprovacao: 'fa-user-check',
    pagamento: 'fa-hand-holding-dollar',
    recepcao: 'fa-box-open',
    factura: 'fa-file-invoice',
};

/** O RASTO: cada passo da compra, quem o deu e quando. */
function Rasto({ passos }: { passos: PassoDoRasto[] }) {
    if (passos.length === 0) return null;

    return (
        <section aria-labelledby="ficha-rasto">
            <h3 id="ficha-rasto" className="mb-2 text-sm font-bold text-slate-800">
                <i className="fas fa-route mr-2 text-emerald-600" aria-hidden="true" />{t('Rasto da compra')}
            </h3>
            <ol className="relative space-y-3 border-l border-slate-200 pl-5">
                {passos.map((p, i) => (
                    <li key={i} className="relative">
                        <span className="absolute -left-[1.85rem] top-0.5 grid h-5 w-5 place-items-center rounded-full bg-emerald-50 text-[10px] text-emerald-700 ring-2 ring-white">
                            <i className={cls('fas', ICONE_DO_PASSO[p.tipo])} aria-hidden="true" />
                        </span>
                        <p className="text-sm text-slate-800">
                            <span className="font-semibold">{p.quem ?? t('Sistema')}</span> — {p.passo}
                        </p>
                        <p className="text-xs tabular-nums text-slate-500">{p.quando ?? '—'}{p.detalhe ? ` · ${p.detalhe}` : ''}</p>
                    </li>
                ))}
            </ol>
        </section>
    );
}

/* ─── As regras do circuito ───────────────────────────────────────────── */

/**
 * QUANTAS PESSOAS APROVAM cada passo, e quem é o tesoureiro por omissão. Zero
 * aprovações é o comportamento de sempre. Não se exige mais aprovações do que
 * as pessoas que as podem dar — o servidor recusa, e aqui diz-se quantas são.
 */
function Regras({ aberta, aoFechar, aoFeito }: { aberta: boolean; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const regras = useQuery({
        queryKey: ['compras', 'definicoes'],
        queryFn: () => compras.definicoes.ler(),
        enabled: aberta,
    });

    const [dados, porDados] = useState<{ aprovacoes_encomenda: number; aprovacoes_pagamento: number; tesoureiro_id: string } | null>(null);

    if (regras.data && dados === null && aberta) {
        porDados(regras.data.data);
    }

    const guardar = useMutation({
        mutationFn: () => compras.definicoes.guardar({
            aprovacoes_encomenda: dados!.aprovacoes_encomenda,
            aprovacoes_pagamento: dados!.aprovacoes_pagamento,
            tesoureiro_id: dados!.tesoureiro_id ? Number(dados!.tesoureiro_id) : null,
        }),
        onSuccess: (r) => { porDados(null); aoFeito(r.message); },
    });

    const fechar = () => { porDados(null); aoFechar(); };
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};
    const r = regras.data;
    const somenteLer = !r?.pode_definir;
    const opcoesDeNumero = Array.from({ length: (r?.maximo ?? 5) + 1 }, (_, i) => i);

    return (
        <Modal
            aberto={aberta}
            aoFechar={fechar}
            titulo={t('Regras das compras')}
            subtitulo={t('Quem aprova cada passo e quem paga')}
            icone="fa-sliders"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={fechar}>{somenteLer ? t('Fechar') : t('Cancelar')}</Botao>
                    {!somenteLer && (
                        <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending}
                            disabled={!dados} onClick={() => guardar.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    )}
                </>
            }
        >
            {regras.isPending || !dados ? (
                <Carregando linhas={4} />
            ) : regras.isError ? (
                <AvisoDeErro erro={regras.error} />
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={guardar.error} />

                    <Campo etiqueta={t('Aprovações da encomenda')} erro={erros.aprovacoes_encomenda}
                        ajuda={t('Antes de ir para o fornecedor. :n pessoa(s) podem aprovar. Quem faz a encomenda não a aprova.', { n: String(r!.aprovadores.encomenda) })}>
                        <select id="regras-encomenda" value={dados.aprovacoes_encomenda} disabled={somenteLer}
                            onChange={(ev) => porDados({ ...dados, aprovacoes_encomenda: Number(ev.target.value) })} className={entrada}>
                            {opcoesDeNumero.map((n) => (
                                <option key={n} value={n}>{n === 0 ? t('Sem aprovação') : t(':n pessoa(s)', { n: String(n) })}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Aprovações do pagamento')} erro={erros.aprovacoes_pagamento}
                        ajuda={t('Antes de a tesouraria poder pagar. :n pessoa(s) podem aprovar. Quem pede não aprova.', { n: String(r!.aprovadores.pagamento) })}>
                        <select id="regras-pagamento" value={dados.aprovacoes_pagamento} disabled={somenteLer}
                            onChange={(ev) => porDados({ ...dados, aprovacoes_pagamento: Number(ev.target.value) })} className={entrada}>
                            {opcoesDeNumero.map((n) => (
                                <option key={n} value={n}>{n === 0 ? t('Sem aprovação — vai direito à tesouraria') : t(':n pessoa(s)', { n: String(n) })}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Tesoureiro por omissão')} erro={erros.tesoureiro_id}
                        ajuda={t('Recebe os pedidos de pagamento. Só aparecem as pessoas que podem pagar fornecedores.')}>
                        <select id="regras-tesoureiro" value={dados.tesoureiro_id} disabled={somenteLer}
                            onChange={(ev) => porDados({ ...dados, tesoureiro_id: ev.target.value })} className={entrada}>
                            <option value="">{t('Qualquer tesoureiro')}</option>
                            {r!.tesoureiros.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <p className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600', RAIO)}>
                        <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                        {t('Quem faz o quê define-se nos Papéis: criar requisições, aprovar, tratar das encomendas, pedir o pagamento, pagar (tesouraria), receber e facturar são permissões separadas.')}
                    </p>
                </div>
            )}
        </Modal>
    );
}